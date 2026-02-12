<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use App\Models\Saca as SacaModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class DespachoAdmitido extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $receptaculosInput = '';
    public $previewSacas = [];
    public $previewDespachoIds = [];
    public $receptaculosNoEncontrados = [];
    public $receptaculosEscaneados = [];
    public $receptaculosResultado = [];
    public $receptaculosEscaneadosCount = 0;
    public $receptaculosEncontradosCount = 0;

    protected $paginationTheme = 'bootstrap';

    public function searchDespachos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openAdmitirModal()
    {
        $this->resetAdmitirForm();
        $this->dispatch('openAdmitirDespachoModal');
    }

    public function previewAdmitir()
    {
        $receptaculosEscaneados = $this->parseReceptaculos($this->receptaculosInput);
        $this->receptaculosEscaneados = $receptaculosEscaneados->values()->all();
        $this->receptaculosEscaneadosCount = $receptaculosEscaneados->count();

        // Query with unique values, but keep full scanned list for UI.
        $receptaculos = $receptaculosEscaneados->unique()->values();

        if ($receptaculos->isEmpty()) {
            $this->addError('receptaculosInput', 'Ingresa al menos un receptaculo.');
            $this->previewSacas = [];
            $this->previewDespachoIds = [];
            $this->receptaculosNoEncontrados = [];
            $this->receptaculosEscaneados = [];
            $this->receptaculosResultado = [];
            $this->receptaculosEncontradosCount = 0;
            return;
        }

        $normalizedReceptaculoSql = "REGEXP_REPLACE(UPPER(COALESCE(receptaculo, '')), '[^A-Z0-9]', '', 'g')";

        $sacasCandidatas = SacaModel::query()
            ->with('despacho:id,identificador,nro_despacho,anio,fk_estado')
            ->whereIn(DB::raw($normalizedReceptaculoSql), $receptaculos->all())
            ->select('*')
            ->selectRaw($normalizedReceptaculoSql . ' as receptaculo_normalizado')
            ->get();

        $sacas = $sacasCandidatas
            ->filter(function ($saca) {
                return (int) $saca->fk_estado === 15
                    && (int) optional($saca->despacho)->fk_estado === 19;
            })
            ->values();

        $this->previewSacas = $sacas->map(function ($saca) {
            return [
                'id' => $saca->id,
                'receptaculo' => $saca->receptaculo,
                'identificador' => $saca->identificador,
                'fk_despacho' => $saca->fk_despacho,
                'despacho' => optional($saca->despacho)->identificador,
            ];
        })->values()->all();

        $this->previewDespachoIds = collect($this->previewSacas)
            ->pluck('fk_despacho')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $mostrados = $receptaculosEscaneados;

        $sacasMostradas = SacaModel::query()
            ->with('despacho:id,fk_estado')
            ->whereIn(DB::raw($normalizedReceptaculoSql), $mostrados->all())
            ->select('*')
            ->selectRaw($normalizedReceptaculoSql . ' as receptaculo_normalizado')
            ->get();

        $validasMostradas = $sacasMostradas
            ->filter(function ($saca) {
                return (int) $saca->fk_estado === 15
                    && (int) optional($saca->despacho)->fk_estado === 19;
            })
            ->values();

        $encontrados = $validasMostradas->pluck('receptaculo_normalizado')
            ->filter()
            ->unique()
            ->values();
        $this->receptaculosEncontradosCount = $encontrados->count();

        $this->receptaculosResultado = $mostrados->map(function ($codigo) use ($sacasMostradas, $validasMostradas) {
            $validas = $validasMostradas->where('receptaculo_normalizado', $codigo);
            if ($validas->isNotEmpty()) {
                return [
                    'codigo' => $codigo,
                    'ok' => true,
                    'detalle' => 'Valido para recibir',
                ];
            }

            $candidatas = $sacasMostradas->where('receptaculo_normalizado', $codigo);
            if ($candidatas->isEmpty()) {
                return [
                    'codigo' => $codigo,
                    'ok' => false,
                    'detalle' => 'No encontrado',
                ];
            }

            $primera = $candidatas->first();
            $estadoSaca = (int) $primera->fk_estado;
            $estadoDespacho = (int) optional($primera->despacho)->fk_estado;

            return [
                'codigo' => $codigo,
                'ok' => false,
                'detalle' => "No valido: saca {$estadoSaca}, despacho {$estadoDespacho}",
            ];
        })->values()->all();

        $this->receptaculosNoEncontrados = collect($this->receptaculosResultado)
            ->filter(fn ($item) => !$item['ok'])
            ->pluck('codigo')
            ->unique()
            ->values()
            ->all();
    }

    public function admitirDespachos()
    {
        if (empty($this->previewSacas)) {
            $this->previewAdmitir();
        }

        $sacaIds = collect($this->previewSacas)->pluck('id')->filter()->values();
        $despachoIds = collect($this->previewDespachoIds)->filter()->values();

        if ($sacaIds->isEmpty() || $despachoIds->isEmpty()) {
            $this->addError('receptaculosInput', 'No hay sacas validas para admitir.');
            return;
        }

        $despachosActualizados = collect();

        DB::transaction(function () use ($sacaIds, $despachoIds, &$despachosActualizados) {
            SacaModel::query()
                ->whereIn('id', $sacaIds->all())
                ->where('fk_estado', 15)
                ->whereHas('despacho', function ($query) {
                    $query->where('fk_estado', 19);
                })
                ->update(['fk_estado' => 22]);

            $despachosCompletos = DespachoModel::query()
                ->whereIn('id', $despachoIds->all())
                ->where('fk_estado', 19)
                ->whereDoesntHave('sacas', function ($query) {
                    $query->where('fk_estado', '!=', 22);
                })
                ->pluck('id');

            if ($despachosCompletos->isNotEmpty()) {
                DespachoModel::query()
                    ->whereIn('id', $despachosCompletos->all())
                    ->update(['fk_estado' => 21]);
            }

            $despachosActualizados = $despachosCompletos;
        });

        $this->dispatch('closeAdmitirDespachoModal');
        $this->resetAdmitirForm();
        if ($despachosActualizados->isEmpty()) {
            session()->flash('success', 'Sacas recibidas. Ningun despacho completo para cambiar a estado 21.');
        } else {
            session()->flash('success', 'Sacas recibidas y despachos completos cambiados a estado 21.');
        }
    }

    public function resetAdmitirForm()
    {
        $this->reset([
            'receptaculosInput',
            'previewSacas',
            'previewDespachoIds',
            'receptaculosNoEncontrados',
            'receptaculosEscaneados',
            'receptaculosResultado',
            'receptaculosEscaneadosCount',
            'receptaculosEncontradosCount',
        ]);

        $this->resetValidation();
    }

    protected function parseReceptaculos($raw)
    {
        // One-by-one scan mode: each code must come in a new line (Enter).
        return collect(preg_split('/\r\n|\r|\n/', strtoupper((string) $raw)))
            ->map(function ($item) {
                $normalized = strtoupper(trim((string) $item));
                // Receptaculo is stored as plain alphanumeric in DB.
                return preg_replace('/[^A-Z0-9]/', '', $normalized);
            })
            ->filter(fn ($item) => $item !== '')
            ->values();
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $despachos = DespachoModel::query()
            ->with('estado:id,nombre_estado')
            ->where('fk_estado', 21)
            ->withCount([
                'sacas as sacas_totales',
                'sacas as sacas_recibidas' => function ($query) {
                    $query->where('fk_estado', 22);
                },
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('oforigen', 'ILIKE', "%{$q}%")
                        ->orWhere('ofdestino', 'ILIKE', "%{$q}%")
                        ->orWhere('categoria', 'ILIKE', "%{$q}%")
                        ->orWhere('subclase', 'ILIKE', "%{$q}%")
                        ->orWhere('nro_despacho', 'ILIKE', "%{$q}%")
                        ->orWhere('identificador', 'ILIKE', "%{$q}%")
                        ->orWhere('anio', 'ILIKE', "%{$q}%")
                        ->orWhere('departamento', 'ILIKE', "%{$q}%")
                        ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                            $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.despacho-admitido', [
            'despachos' => $despachos,
        ]);
    }
}
