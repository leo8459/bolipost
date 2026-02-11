<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use Livewire\Component;
use Livewire\WithPagination;

class DespachoExpedicion extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';

    protected $paginationTheme = 'bootstrap';

    public function searchDespachos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $despachos = DespachoModel::query()
            ->with('estado:id,nombre_estado')
            ->where('fk_estado', 19)
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

        return view('livewire.despacho-expedicion', [
            'despachos' => $despachos,
        ]);
    }
}
