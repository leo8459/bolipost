<?php

namespace App\Livewire;

use App\Models\CodigoEmpresa as CodigoEmpresaModel;
use App\Models\Empresa as EmpresaModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CodigoEmpresa extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $codigo = '';
    public $barcode = '';
    public $empresa_id = '';
    public $operacion = 'IMPRIMIR';
    public $operacion_empresa_id = '';
    public $cantidad_generar = 1;
    public $reimprimir_desde = '';
    public $reimprimir_hasta = '';
    public $reporte_empresa_id = '';
    public $reporte_fecha_desde = '';
    public $reporte_fecha_hasta = '';

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $today = now()->toDateString();
        $this->reporte_fecha_desde = $today;
        $this->reporte_fecha_hasta = $today;
    }

    public function searchCodigos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openCodigoEmpresaModal');
    }

    public function openEditModal($id)
    {
        $registro = CodigoEmpresaModel::findOrFail($id);

        $this->editingId = $registro->id;
        $this->codigo = $registro->codigo;
        $this->barcode = $registro->barcode;
        $this->empresa_id = (string) $registro->empresa_id;

        $this->dispatch('openCodigoEmpresaModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $registro = CodigoEmpresaModel::findOrFail($this->editingId);
            $registro->update($this->payload());
            session()->flash('success', 'Codigo de empresa actualizado correctamente.');
        } else {
            CodigoEmpresaModel::create($this->payload());
            session()->flash('success', 'Codigo de empresa creado correctamente.');
        }

        $this->dispatch('closeCodigoEmpresaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $registro = CodigoEmpresaModel::findOrFail($id);
        $registro->delete();
        session()->flash('success', 'Codigo de empresa eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'codigo',
            'barcode',
            'empresa_id',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'codigo' => 'required|string|max:255',
            'barcode' => 'required|string|max:255',
            'empresa_id' => 'required|integer|exists:empresa,id',
        ];
    }

    protected function payload()
    {
        return [
            'codigo' => trim((string) $this->codigo),
            'barcode' => trim((string) $this->barcode),
            'empresa_id' => (int) $this->empresa_id,
        ];
    }

    public function setOperacion($operacion)
    {
        $allowed = ['IMPRIMIR', 'REIMPRIMIR', 'REPORTE'];
        $op = strtoupper(trim((string) $operacion));
        if (!in_array($op, $allowed, true)) {
            return;
        }

        $this->operacion = $op;
        $this->resetValidation();
    }

    public function ejecutarOperacion()
    {
        if ($this->operacion === 'IMPRIMIR') {
            return $this->imprimirCodigos();
        }

        if ($this->operacion === 'REIMPRIMIR') {
            return $this->reimprimirCodigos();
        }

        return $this->reporteCodigos();
    }

    protected function imprimirCodigos()
    {
        $this->prepareRuntimeForPdf();

        $this->validate([
            'operacion_empresa_id' => 'required|integer|exists:empresa,id',
            'cantidad_generar' => 'required|integer|min:1|max:1000',
        ]);

        $empresa = EmpresaModel::findOrFail((int) $this->operacion_empresa_id);
        $inicio = $this->nextCorrelativo($empresa);
        $cantidad = (int) $this->cantidad_generar;
        $now = now();

        $rows = [];
        $codigos = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $correlativo = $inicio + $i;
            $codigo = $this->buildCodigo((string) $empresa->codigo_cliente, $correlativo);
            $rows[] = [
                'codigo' => $codigo,
                'barcode' => $codigo,
                'empresa_id' => (int) $empresa->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $codigos[] = $codigo;
        }

        DB::table('codigo_empresa')->insert($rows);

        $pdf = Pdf::loadView('codigo_empresa.etiquetas-oficio', [
            'codigos' => $codigos,
            'empresa' => $empresa,
            'generatedAt' => $now,
        ])->setPaper([0, 0, 612, 936], 'portrait');

        session()->flash('success', count($codigos) . ' codigo(s) generado(s) correctamente.');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'codigos-' . strtoupper((string) $empresa->sigla) . '-' . $now->format('Ymd-His') . '.pdf');
    }

    protected function reimprimirCodigos()
    {
        $this->prepareRuntimeForPdf();

        $this->validate([
            'operacion_empresa_id' => 'required|integer|exists:empresa,id',
            'reimprimir_desde' => 'required|integer|min:1|max:99999',
            'reimprimir_hasta' => 'required|integer|min:1|max:99999',
        ]);

        $desde = (int) $this->reimprimir_desde;
        $hasta = (int) $this->reimprimir_hasta;
        if ($hasta < $desde) {
            $this->addError('reimprimir_hasta', 'El numero final debe ser mayor o igual al inicial.');
            return;
        }

        if (($hasta - $desde + 1) > 1000) {
            $this->addError('reimprimir_hasta', 'El rango maximo permitido es de 1000 codigos.');
            return;
        }

        $empresa = EmpresaModel::findOrFail((int) $this->operacion_empresa_id);

        $solicitados = collect(range($desde, $hasta))
            ->map(fn ($n) => $this->buildCodigo((string) $empresa->codigo_cliente, (int) $n))
            ->values();

        $existentes = CodigoEmpresaModel::query()
            ->where('empresa_id', (int) $empresa->id)
            ->whereIn('codigo', $solicitados->all())
            ->pluck('codigo')
            ->flip();

        $codigos = $solicitados
            ->filter(fn ($codigo) => isset($existentes[$codigo]))
            ->values()
            ->all();

        if (empty($codigos)) {
            session()->flash('error', 'No se encontraron codigos en ese rango para reimprimir.');
            return;
        }

        $now = now();
        $pdf = Pdf::loadView('codigo_empresa.etiquetas-oficio', [
            'codigos' => $codigos,
            'empresa' => $empresa,
            'generatedAt' => $now,
        ])->setPaper([0, 0, 612, 936], 'portrait');

        session()->flash('success', count($codigos) . ' codigo(s) reimpreso(s) correctamente.');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reimpresion-codigos-' . strtoupper((string) $empresa->sigla) . '-' . $now->format('Ymd-His') . '.pdf');
    }

    protected function reporteCodigos()
    {
        $this->prepareRuntimeForPdf();

        $this->validate([
            'reporte_empresa_id' => 'nullable|integer|exists:empresa,id',
            'reporte_fecha_desde' => 'required|date',
            'reporte_fecha_hasta' => 'required|date|after_or_equal:reporte_fecha_desde',
        ]);

        $query = CodigoEmpresaModel::query()
            ->join('empresa', 'empresa.id', '=', 'codigo_empresa.empresa_id')
            ->when(!empty($this->reporte_empresa_id), function ($subQuery) {
                $subQuery->where('codigo_empresa.empresa_id', (int) $this->reporte_empresa_id);
            })
            ->whereDate('codigo_empresa.created_at', '>=', $this->reporte_fecha_desde)
            ->whereDate('codigo_empresa.created_at', '<=', $this->reporte_fecha_hasta)
            ->selectRaw('
                codigo_empresa.empresa_id,
                empresa.nombre,
                empresa.sigla,
                empresa.codigo_cliente,
                COUNT(*) as total_codigos,
                MIN(codigo_empresa.created_at) as primera_fecha,
                MAX(codigo_empresa.created_at) as ultima_fecha
            ')
            ->groupBy('codigo_empresa.empresa_id', 'empresa.nombre', 'empresa.sigla', 'empresa.codigo_cliente')
            ->orderBy('empresa.codigo_cliente');

        $resumen = $query->get();

        if ($resumen->isEmpty()) {
            session()->flash('error', 'No hay codigos para generar el reporte.');
            return;
        }

        $empresa = null;
        if (!empty($this->reporte_empresa_id)) {
            $empresa = EmpresaModel::find((int) $this->reporte_empresa_id);
        }

        $now = now();
        $pdf = Pdf::loadView('codigo_empresa.reporte-oficio', [
            'resumen' => $resumen,
            'empresa' => $empresa,
            'generatedAt' => $now,
            'fechaDesde' => $this->reporte_fecha_desde,
            'fechaHasta' => $this->reporte_fecha_hasta,
        ])->setPaper([0, 0, 612, 936], 'portrait');

        session()->flash('success', 'Reporte generado correctamente.');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-codigos-' . $now->format('Ymd-His') . '.pdf');
    }

    protected function prepareRuntimeForPdf(): void
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);
    }

    protected function buildCodigo(string $codigoCliente, int $correlativo): string
    {
        $cliente = strtoupper(trim($codigoCliente));
        $cliente = preg_replace('/\s+/', '', $cliente) ?: '';

        return 'C' . $cliente . 'A' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT) . 'BO';
    }

    protected function nextCorrelativo(EmpresaModel $empresa): int
    {
        $cliente = strtoupper(trim((string) $empresa->codigo_cliente));
        $cliente = preg_replace('/\s+/', '', $cliente) ?: '';
        $pattern = '/^C' . preg_quote($cliente, '/') . 'A(\d{5})BO$/';

        $max = 0;
        $codigos = CodigoEmpresaModel::query()
            ->where('empresa_id', (int) $empresa->id)
            ->pluck('codigo');

        foreach ($codigos as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $valor = (int) $matches[1];
                if ($valor > $max) {
                    $max = $valor;
                }
            }
        }

        return $max + 1;
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $codigos = CodigoEmpresaModel::query()
            ->with('empresa')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'ILIKE', "%{$q}%")
                        ->orWhere('barcode', 'ILIKE', "%{$q}%")
                        ->orWhereHas('empresa', function ($empresaQuery) use ($q) {
                            $empresaQuery->where('nombre', 'ILIKE', "%{$q}%")
                                ->orWhere('sigla', 'ILIKE', "%{$q}%")
                                ->orWhere('codigo_cliente', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.codigo-empresa', [
            'codigos' => $codigos,
            'empresas' => EmpresaModel::query()->orderBy('codigo_cliente')->get(['id', 'nombre', 'sigla', 'codigo_cliente']),
        ]);
    }
}
