<?php

namespace App\Livewire;

use App\Models\Ventanilla as VentanillaModel;
use Livewire\Component;
use Livewire\WithPagination;

class Ventanilla extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nombre_ventanilla = '';

    protected $paginationTheme = 'bootstrap';

    public function searchVentanillas()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openVentanillaModal');
    }

    public function openEditModal($id)
    {
        $ventanilla = VentanillaModel::findOrFail($id);

        $this->editingId = $ventanilla->id;
        $this->nombre_ventanilla = $ventanilla->nombre_ventanilla;

        $this->dispatch('openVentanillaModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $ventanilla = VentanillaModel::findOrFail($this->editingId);
            $ventanilla->update($this->payload());
            session()->flash('success', 'Ventanilla actualizada correctamente.');
        } else {
            VentanillaModel::create($this->payload());
            session()->flash('success', 'Ventanilla creada correctamente.');
        }

        $this->dispatch('closeVentanillaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $ventanilla = VentanillaModel::findOrFail($id);
        $ventanilla->delete();
        session()->flash('success', 'Ventanilla eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre_ventanilla',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre_ventanilla' => 'required|string|max:255',
        ];
    }

    protected function payload()
    {
        return [
            'nombre_ventanilla' => $this->upper($this->nombre_ventanilla),
        ];
    }

    protected function upper($value)
    {
        return strtoupper(trim((string) $value));
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $ventanillas = VentanillaModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre_ventanilla', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.ventanilla', [
            'ventanillas' => $ventanillas,
        ]);
    }
}
