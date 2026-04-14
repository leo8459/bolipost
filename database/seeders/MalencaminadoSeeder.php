<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MalencaminadoSeeder extends Seeder
{
    private const DEPARTAMENTOS = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];

    public function run(): void
    {
        $ems = DB::table('paquetes_ems')
            ->selectRaw('id, codigo, upper(trim(coalesce(origen, \'\'))) as origen')
            ->get();

        $contratos = DB::table('paquetes_contrato')
            ->selectRaw('id, codigo, upper(trim(coalesce(origen, \'\'))) as origen')
            ->get();

        if ($ems->isEmpty() && $contratos->isEmpty()) {
            $this->command?->warn('No hay paquetes EMS ni Contrato para generar malencaminados.');
            return;
        }

        $emsByDept = $ems->groupBy(fn ($r) => (string) $r->origen);
        $contratosByDept = $contratos->groupBy(fn ($r) => (string) $r->origen);

        $emsAll = $ems->values();
        $contratosAll = $contratos->values();

        $maxByEms = DB::table('malencaminados')
            ->whereNotNull('paquetes_ems_id')
            ->selectRaw('paquetes_ems_id as id, max(malencaminamiento) as maximo')
            ->groupBy('paquetes_ems_id')
            ->pluck('maximo', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $maxByContrato = DB::table('malencaminados')
            ->whereNotNull('paquetes_contrato_id')
            ->selectRaw('paquetes_contrato_id as id, max(malencaminamiento) as maximo')
            ->groupBy('paquetes_contrato_id')
            ->pluck('maximo', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $departamento = self::DEPARTAMENTOS[$i % count(self::DEPARTAMENTOS)];
            $usarEms = (bool) random_int(0, 1);

            if ($usarEms && $emsAll->isNotEmpty()) {
                $pool = ($emsByDept[$departamento] ?? collect());
                $selected = ($pool->isNotEmpty() ? $pool : $emsAll)->random();
                $packageId = (int) $selected->id;
                $counter = (int) (($maxByEms[$packageId] ?? 0) + 1);
                $maxByEms[$packageId] = $counter;

                [$destinoAnterior, $destinoNuevo] = $this->resolveDestinos($departamento);
                $createdAt = Carbon::now()->subDays(random_int(0, 90))->subMinutes(random_int(0, 1440));

                $rows[] = [
                    'codigo' => (string) $selected->codigo,
                    'departamento_origen' => $departamento,
                    'observacion' => 'MALENCAMINADO DE PRUEBA ' . ($i + 1),
                    'malencaminamiento' => $counter,
                    'paquetes_ems_id' => $packageId,
                    'paquetes_contrato_id' => null,
                    'destino_anterior' => $destinoAnterior,
                    'destino_nuevo' => $destinoNuevo,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                continue;
            }

            if ($contratosAll->isEmpty()) {
                continue;
            }

            $pool = ($contratosByDept[$departamento] ?? collect());
            $selected = ($pool->isNotEmpty() ? $pool : $contratosAll)->random();
            $packageId = (int) $selected->id;
            $counter = (int) (($maxByContrato[$packageId] ?? 0) + 1);
            $maxByContrato[$packageId] = $counter;

            [$destinoAnterior, $destinoNuevo] = $this->resolveDestinos($departamento);
            $createdAt = Carbon::now()->subDays(random_int(0, 90))->subMinutes(random_int(0, 1440));

            $rows[] = [
                'codigo' => (string) $selected->codigo,
                'departamento_origen' => $departamento,
                'observacion' => 'MALENCAMINADO DE PRUEBA ' . ($i + 1),
                'malencaminamiento' => $counter,
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => $packageId,
                'destino_anterior' => $destinoAnterior,
                'destino_nuevo' => $destinoNuevo,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        if ($rows !== []) {
            DB::table('malencaminados')->insert($rows);
        }
    }

    private function resolveDestinos(string $departamento): array
    {
        $anterior = self::DEPARTAMENTOS[array_rand(self::DEPARTAMENTOS)];
        $nuevo = self::DEPARTAMENTOS[array_rand(self::DEPARTAMENTOS)];

        if ($nuevo === $anterior) {
            $nuevo = $departamento;
        }

        return [$anterior, $nuevo];
    }
}

