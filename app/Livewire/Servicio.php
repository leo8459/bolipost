<?php

namespace App\Livewire;

use App\Models\Servicio as ServicioModel;
use Livewire\Component;
use Livewire\WithPagination;

class Servicio extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $nombre_servicio = '';

    protected $paginationTheme = 'bootstrap';

    public function searchServicios()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openServicioModal');
    }

    public function openEditModal($id)
    {
        $servicio = ServicioModel::findOrFail($id);
        $this->editingId = $servicio->id;
        $this->nombre_servicio = $servicio->nombre_servicio;

        $this->dispatch('openServicioModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $servicio = ServicioModel::findOrFail($this->editingId);
            $servicio->update($this->payload());
            session()->flash('success', 'Servicio actualizado correctamente.');
        } else {
            ServicioModel::create($this->payload());
            session()->flash('success', 'Servicio creado correctamente.');
        }

        $this->dispatch('closeServicioModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $servicio = ServicioModel::findOrFail($id);
        $servicio->delete();
        session()->flash('success', 'Servicio eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre_servicio',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre_servicio' => 'required|string|max:255',
        ];
    }

    protected function payload()
    {
        return [
            'nombre_servicio' => $this->nombre_servicio,
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $servicios = ServicioModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre_servicio', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.servicio', [
            'servicios' => $servicios,
        ]);
    }
}
