<?php

namespace App\Http\Controllers;

use App\Models\CodigoEmpresa;
use App\Models\Empresa;
use App\Models\Recojo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecojoController extends Controller
{
    public function index()
    {
        return view('paquetes_contrato.index');
    }

    public function create()
    {
        $user = Auth::user();
        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? '')));
        }

        $departamentos = [
            'LA PAZ',
            'COCHABAMBA',
            'SANTA CRUZ',
            'ORURO',
            'POTOSI',
            'TARIJA',
            'CHUQUISACA',
            'BENI',
            'PANDO',
        ];

        return view('paquetes_contrato.create', [
            'origen' => $origen,
            'departamentos' => $departamentos,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (empty($user->empresa_id)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Tu usuario no tiene empresa asignada. Asigna empresa al usuario para generar codigo.');
        }

        $departamentos = [
            'LA PAZ',
            'COCHABAMBA',
            'SANTA CRUZ',
            'ORURO',
            'POTOSI',
            'TARIJA',
            'CHUQUISACA',
            'BENI',
            'PANDO',
        ];

        $data = $request->validate([
            'nombre_r' => 'required|string|max:255',
            'telefono_r' => 'required|string|max:50',
            'contenido' => 'required|string',
            'direccion_r' => 'required|string|max:255',
            'nombre_d' => 'required|string|max:255',
            'telefono_d' => 'nullable|string|max:50',
            'destino' => 'required|string|in:' . implode(',', $departamentos),
            'direccion' => 'required|string|max:255',
            'mapa' => 'nullable|string|max:500',
            'provincia' => 'nullable|string|max:255',
        ]);

        $empresa = Empresa::query()->find((int) $user->empresa_id);
        if (!$empresa) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se encontro la empresa asociada al usuario.');
        }

        $codigoCliente = strtoupper(trim((string) $empresa->codigo_cliente));
        $codigoCliente = preg_replace('/\s+/', '', $codigoCliente) ?: '';
        if ($codigoCliente === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'La empresa asociada no tiene codigo_cliente valido.');
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? 'ORIGEN')));
        }

        $contrato = null;
        DB::transaction(function () use ($data, $user, $empresa, $codigoCliente, $origen, &$contrato) {
            $correlativo = $this->nextCorrelativo((int) $empresa->id, $codigoCliente);
            $codigo = $this->buildCodigo($codigoCliente, $correlativo);

            $contrato = Recojo::query()->create([
                'user_id' => (int) $user->id,
                'codigo' => $codigo,
                'estado' => 'CREADO',
                'origen' => $origen,
                'destino' => strtoupper(trim((string) $data['destino'])),
                'nombre_r' => strtoupper(trim((string) $data['nombre_r'])),
                'telefono_r' => trim((string) $data['telefono_r']),
                'contenido' => trim((string) $data['contenido']),
                'direccion_r' => strtoupper(trim((string) $data['direccion_r'])),
                'nombre_d' => strtoupper(trim((string) $data['nombre_d'])),
                'telefono_d' => !empty($data['telefono_d']) ? trim((string) $data['telefono_d']) : null,
                'direccion_d' => strtoupper(trim((string) $data['direccion'])),
                'mapa' => !empty($data['mapa']) ? trim((string) $data['mapa']) : null,
                'provincia' => !empty($data['provincia']) ? strtoupper(trim((string) $data['provincia'])) : null,
                'peso' => 0,
                'fecha_recojo' => now()->toDateString(),
                'observacion' => null,
                'justificacion' => null,
                'imagen' => null,
            ]);

            // Reservamos codigo tambien en codigo_empresa para mantener correlativo global por empresa.
            CodigoEmpresa::query()->create([
                'codigo' => $codigo,
                'barcode' => $codigo,
                'empresa_id' => (int) $empresa->id,
            ]);
        });

        return redirect()
            ->route('paquetes-contrato.index')
            ->with('success', 'GUARDADO')
            ->with('download_reporte_url', route('paquetes-contrato.reporte', $contrato->id));
    }

    public function reporte(Recojo $contrato)
    {
        $generatedAt = now();
        $pdf = Pdf::loadView('paquetes_contrato.reporte', [
            'contrato' => $contrato,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contrato-' . $contrato->codigo . '-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function reporteHoy()
    {
        $hoy = now()->toDateString();
        $contratos = Recojo::query()
            ->whereDate('created_at', $hoy)
            ->orderBy('id')
            ->get();

        if ($contratos->isEmpty()) {
            return redirect()
                ->route('paquetes-contrato.index')
                ->with('error', 'No hay contratos generados hoy.');
        }

        $generatedAt = now();
        $pdf = Pdf::loadView('paquetes_contrato.reporte-hoy', [
            'contratos' => $contratos,
            'generatedAt' => $generatedAt,
            'fechaHoy' => $hoy,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contratos-generados-hoy-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    protected function nextCorrelativo(int $empresaId, string $codigoCliente): int
    {
        $cliente = strtoupper(trim($codigoCliente));
        $prefix = 'C' . $cliente . 'A';
        $pattern = '/^C' . preg_quote($cliente, '/') . 'A(\d{5})BO$/';
        $max = 0;

        $codigosEmpresa = CodigoEmpresa::query()
            ->where('empresa_id', $empresaId)
            ->pluck('codigo');

        foreach ($codigosEmpresa as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $valor = (int) $matches[1];
                if ($valor > $max) {
                    $max = $valor;
                }
            }
        }

        $codigosContrato = Recojo::query()
            ->where('codigo', 'like', $prefix . '%BO')
            ->pluck('codigo');

        foreach ($codigosContrato as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $valor = (int) $matches[1];
                if ($valor > $max) {
                    $max = $valor;
                }
            }
        }

        return $max + 1;
    }

    protected function buildCodigo(string $codigoCliente, int $correlativo): string
    {
        return 'C' . $codigoCliente . 'A' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT) . 'BO';
    }
}
