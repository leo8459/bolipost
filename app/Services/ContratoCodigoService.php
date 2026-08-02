<?php

namespace App\Services;

use App\Models\CodigoEmpresa;
use App\Models\Recojo;
use Illuminate\Support\Facades\DB;

class ContratoCodigoService
{
    public const MENSAJE_CODIGO_DUPLICADO = 'Código duplicado. No se pudo registrar el envío porque el código ya existe. Por favor, contáctese con el Área de Sistemas.';

    public function reservarSiguiente(string $codigoCliente): int
    {
        return $this->reservarRango($codigoCliente, 1);
    }

    public function reservarRango(string $codigoCliente, int $cantidad): int
    {
        $cliente = $this->normalizarCodigoCliente($codigoCliente);

        if ($cliente === '') {
            throw new \RuntimeException('La empresa no tiene un codigo_cliente valido.');
        }

        if ($cantidad < 1) {
            throw new \InvalidArgumentException('La cantidad de correlativos debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($cliente, $cantidad) {
            DB::table('correlativos_contrato')->insertOrIgnore([
                'codigo_cliente' => $cliente,
                'ultimo_correlativo' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $contador = DB::table('correlativos_contrato')
                ->where('codigo_cliente', $cliente)
                ->lockForUpdate()
                ->first();

            $ultimo = (int) ($contador->ultimo_correlativo ?? 0);

            if ($ultimo === 0) {
                $ultimo = $this->maximoExistente($cliente);
            }

            $inicio = $ultimo + 1;

            DB::table('correlativos_contrato')
                ->where('codigo_cliente', $cliente)
                ->update([
                    'ultimo_correlativo' => $ultimo + $cantidad,
                    'updated_at' => now(),
                ]);

            return $inicio;
        });
    }

    public function construirCodigo(string $codigoCliente, int $correlativo): string
    {
        return 'C'.$this->normalizarCodigoCliente($codigoCliente).'A'.str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT).'BO';
    }

    public function sincronizarDesdeCodigo(string $codigo): void
    {
        $codigo = strtoupper(trim($codigo));

        if (! preg_match('/^C(.+)A(\d+)BO$/', $codigo, $matches)) {
            return;
        }

        $cliente = $this->normalizarCodigoCliente($matches[1]);
        $correlativo = (int) $matches[2];

        if ($cliente === '' || $correlativo < 1) {
            return;
        }

        DB::transaction(function () use ($cliente, $correlativo) {
            DB::table('correlativos_contrato')->insertOrIgnore([
                'codigo_cliente' => $cliente,
                'ultimo_correlativo' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $contador = DB::table('correlativos_contrato')
                ->where('codigo_cliente', $cliente)
                ->lockForUpdate()
                ->first();

            $ultimo = (int) ($contador->ultimo_correlativo ?? 0);
            $nuevoUltimo = $ultimo === 0
                ? max($correlativo, $this->maximoExistente($cliente))
                : max($correlativo, $ultimo);

            if ($nuevoUltimo !== $ultimo) {
                DB::table('correlativos_contrato')
                    ->where('codigo_cliente', $cliente)
                    ->update([
                        'ultimo_correlativo' => $nuevoUltimo,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function normalizarCodigoCliente(string $codigoCliente): string
    {
        $cliente = strtoupper(trim($codigoCliente));

        return preg_replace('/\s+/', '', $cliente) ?: '';
    }

    private function maximoExistente(string $cliente): int
    {
        $prefix = 'C'.$cliente.'A';
        $pattern = '/^C'.preg_quote($cliente, '/').'A(\d+)BO$/';
        $maximo = 0;

        $codigosReservados = CodigoEmpresa::query()
            ->where(function ($query) use ($prefix) {
                $query->where('codigo', 'like', $prefix.'%BO')
                    ->orWhere('barcode', 'like', $prefix.'%BO');
            })
            ->get(['codigo', 'barcode'])
            ->flatMap(fn ($row) => [$row->codigo, $row->barcode]);

        $codigosContrato = Recojo::query()
            ->where('codigo', 'like', $prefix.'%BO')
            ->pluck('codigo');

        foreach ($codigosReservados->concat($codigosContrato) as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $maximo = max($maximo, (int) $matches[1]);
            }
        }

        return $maximo;
    }
}
