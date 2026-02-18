<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use Livewire\Component;
use Livewire\WithPagination;

class DespachoTodos extends Component
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
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('oforigen', 'ILIKE', "%{$q}%")
                        ->orWhere('ofdestino', 'ILIKE', "%{$q}%")
                        ->orWhere('categoria', 'ILIKE', "%{$q}%")
                        ->orWhere('subclase', 'ILIKE', "%{$q}%")
                        ->orWhere('nro_despacho', 'ILIKE', "%{$q}%")
                        ->orWhere('nro_envase', 'ILIKE', "%{$q}%")
                        ->orWhere('peso', 'ILIKE', "%{$q}%")
                        ->orWhere('identificador', 'ILIKE', "%{$q}%")
                        ->orWhere('anio', 'ILIKE', "%{$q}%")
                        ->orWhere('departamento', 'ILIKE', "%{$q}%")
                        ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                            $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereRaw("TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI:SS') ILIKE ?", ["%{$q}%"])
                        ->orWhereRaw("TO_CHAR(updated_at, 'YYYY-MM-DD HH24:MI:SS') ILIKE ?", ["%{$q}%"]);
                });
            })
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.despacho-todos', [
            'despachos' => $despachos,
        ]);
    }
}

