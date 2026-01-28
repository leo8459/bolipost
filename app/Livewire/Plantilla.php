<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Plantilla as PlantillaModel;
use Illuminate\Support\Facades\Schema;

class Plantilla extends Component
{
    use WithPagination;

    public string $q = '';

    protected $paginationTheme = 'bootstrap'; // para que links() se vea bien con bootstrap

    // Cuando escribes, vuelve a la primera página
    public function updatingQ()
    {
        $this->resetPage();
    }

    public function limpiar()
    {
        $this->q = '';
        $this->resetPage();
    }

    public function render()
    {
        $q = trim($this->q);

        $columns = Schema::getColumnListing('plantillas');

        $plantillas = PlantillaModel::query()
            ->when($q !== '', function ($query) use ($q, $columns) {
                $query->where(function ($sub) use ($q, $columns) {
                    foreach ($columns as $column) {
                        // PostgreSQL: ILIKE (case-insensitive)
                        $sub->orWhere($column, 'ILIKE', "%{$q}%");
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.plantilla', [
            'plantillas' => $plantillas,
        ]);
    }
}
