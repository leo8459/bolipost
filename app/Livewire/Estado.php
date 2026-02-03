<?php

namespace App\Livewire;

use App\Models\Estado as EstadoModel;
use Livewire\Component;
use Livewire\WithPagination;

class Estado extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nombre_estado = '';
    public $activo = true;

    protected $paginationTheme = 'bootstrap';

    public function searchEstados()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openEstadoModal');
    }

    public function openEditModal($id)
    {
        $estado = EstadoModel::findOrFail($id);

        $this->editingId = $estado->id;
        $this->nombre_estado = $estado->nombre_estado;
        $this->activo = (bool) $estado->activo;

        $this->dispatch('openEstadoModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $estado = EstadoModel::findOrFail($this->editingId);
            $estado->update($this->payload());
            session()->flash('success', 'Estado actualizado correctamente.');
        } else {
            EstadoModel::create($this->payload());
            session()->flash('success', 'Estado creado correctamente.');
        }

        $this->dispatch('closeEstadoModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $estado = EstadoModel::findOrFail($id);
        $estado->delete();
        session()->flash('success', 'Estado eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre_estado',
            'activo',
        ]);

        $this->activo = true;
        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre_estado' => 'required|string|max:255',
            'activo' => 'required|boolean',
        ];
    }

    protected function payload()
    {
        return [
            'nombre_estado' => $this->nombre_estado,
            'activo' => (bool) $this->activo,
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $estados = EstadoModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre_estado', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.estado', [
            'estados' => $estados,
        ]);
    }
}
