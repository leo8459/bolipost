<?php

namespace App\Livewire;

use App\Models\Destino;
use App\Models\Origen;
use App\Models\Peso;
use App\Models\Servicio;
use App\Models\Tarifario as TarifarioModel;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Tarifario extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $servicio_id = '';
    public $destino_id = '';
    public $peso_id = '';
    public $origen_id = '';
    public $precio = '';
    public $observacion = '';

    public $servicios = [];
    public $destinos = [];
    public $pesos = [];
    public $origenes = [];

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->servicios = Servicio::orderBy('nombre_servicio')->get();
        $this->destinos = Destino::orderBy('nombre_destino')->get();
        $this->pesos = Peso::orderBy('peso_inicial')->get();
        $this->origenes = Origen::orderBy('nombre_origen')->get();
    }

    public function searchTarifarios()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openTarifarioModal');
    }

    public function openEditModal($id)
    {
        $tarifario = TarifarioModel::findOrFail($id);
        $this->editingId = $tarifario->id;
        $this->servicio_id = $tarifario->servicio_id;
        $this->destino_id = $tarifario->destino_id;
        $this->peso_id = $tarifario->peso_id;
        $this->origen_id = $tarifario->origen_id;
        $this->precio = $tarifario->precio;
        $this->observacion = $tarifario->observacion;

        $this->dispatch('openTarifarioModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $tarifario = TarifarioModel::findOrFail($this->editingId);
            $tarifario->update($this->payload());
            session()->flash('success', 'Tarifario actualizado correctamente.');
        } else {
            TarifarioModel::create($this->payload());
            session()->flash('success', 'Tarifario creado correctamente.');
        }

        $this->dispatch('closeTarifarioModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $tarifario = TarifarioModel::findOrFail($id);
        $tarifario->delete();
        session()->flash('success', 'Tarifario eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'servicio_id',
            'destino_id',
            'peso_id',
            'origen_id',
            'precio',
            'observacion',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'servicio_id' => ['required', 'integer', Rule::exists('servicio', 'id')],
            'destino_id' => ['required', 'integer', Rule::exists('destino', 'id')],
            'peso_id' => ['required', 'integer', Rule::exists('peso', 'id')],
            'origen_id' => ['required', 'integer', Rule::exists('origen', 'id')],
            'precio' => 'required|numeric|min:0',
            'observacion' => 'nullable|string',
        ];
    }

    protected function payload()
    {
        return [
            'servicio_id' => $this->servicio_id,
            'destino_id' => $this->destino_id,
            'peso_id' => $this->peso_id,
            'origen_id' => $this->origen_id,
            'precio' => $this->precio,
            'observacion' => $this->observacion,
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $tarifarios = TarifarioModel::query()
            ->with(['servicio', 'destino', 'peso', 'origen'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('precio', 'ILIKE', "%{$q}%")
                        ->orWhere('observacion', 'ILIKE', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.tarifario', [
            'tarifarios' => $tarifarios,
        ]);
    }
}
