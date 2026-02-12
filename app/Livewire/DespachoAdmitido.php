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
        $receptaculos = $this->parseReceptaculos($this->receptaculosInput);
        $this->receptaculosEscaneados = $receptaculos->all();
        $this->receptaculosEscaneadosCount = $receptaculos->count();

        if ($receptaculos->isEmpty()) {
            $this->addError('receptaculosInput', 'Ingresa al menos un receptaculo.');
            $this->previewSacas = [];
            $this->previewDespachoIds = [];
            $this->receptaculosNoEncontrados = [];
            $this->receptaculosEscaneados = [];
            $this->receptaculosEncontradosCount = 0;
            return;
        }

        $sacas = SacaModel::query()
            ->with('despacho:id,identificador,nro_despacho,anio')
            ->where('fk_estado', 15)
            ->whereHas('despacho', function ($query) {
                $query->where('fk_estado', 19);
            })
            ->whereIn('receptaculo', $receptaculos->all())
            ->get();

        $encontrados = $sacas->pluck('receptaculo')
            ->filter()
            ->unique()
            ->values();
        $this->receptaculosEncontradosCount = $encontrados->count();

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

        $this->receptaculosNoEncontrados = $receptaculos
            ->diff($encontrados)
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

        DB::transaction(function () use ($sacaIds, $despachoIds) {
            SacaModel::query()
                ->whereIn('id', $sacaIds->all())
                ->where('fk_estado', 15)
                ->update(['fk_estado' => 21]);

            DespachoModel::query()
                ->whereIn('id', $despachoIds->all())
                ->where('fk_estado', 19)
                ->update(['fk_estado' => 21]);
        });

        $this->dispatch('closeAdmitirDespachoModal');
        $this->resetAdmitirForm();
        session()->flash('success', 'Despachos admitidos correctamente.');
    }

    public function resetAdmitirForm()
    {
        $this->reset([
            'receptaculosInput',
            'previewSacas',
            'previewDespachoIds',
            'receptaculosNoEncontrados',
            'receptaculosEscaneados',
            'receptaculosEscaneadosCount',
            'receptaculosEncontradosCount',
        ]);

        $this->resetValidation();
    }

    protected function parseReceptaculos($raw)
    {
        return collect(preg_split('/[\s,;]+/', (string) $raw))
            ->map(function ($item) {
                $normalized = strtoupper(trim((string) $item));
                // Receptaculo is stored as plain alphanumeric in DB.
                return preg_replace('/[^A-Z0-9]/', '', $normalized);
            })
            ->filter(fn ($item) => $item !== '')
            ->unique()
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
                    $query->where('fk_estado', 21);
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
