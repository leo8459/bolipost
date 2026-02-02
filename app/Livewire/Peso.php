<?php

namespace App\Livewire;

use App\Models\Peso as PesoModel;
use Livewire\Component;
use Livewire\WithPagination;

class Peso extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $peso_inicial = '';
    public $peso_final = '';

    protected $paginationTheme = 'bootstrap';

    public function searchPesos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openPesoModal');
    }

    public function openEditModal($id)
    {
        $peso = PesoModel::findOrFail($id);
        $this->editingId = $peso->id;
        $this->peso_inicial = $peso->peso_inicial;
        $this->peso_final = $peso->peso_final;

        $this->dispatch('openPesoModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $peso = PesoModel::findOrFail($this->editingId);
            $peso->update($this->payload());
            session()->flash('success', 'Peso actualizado correctamente.');
        } else {
            PesoModel::create($this->payload());
            session()->flash('success', 'Peso creado correctamente.');
        }

        $this->dispatch('closePesoModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $peso = PesoModel::findOrFail($id);
        $peso->delete();
        session()->flash('success', 'Peso eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'peso_inicial',
            'peso_final',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'peso_inicial' => 'required|numeric|min:0.001|max:0.250',
            'peso_final' => 'required|numeric|min:0.001|max:0.250|gte:peso_inicial',
        ];
    }

    protected function payload()
    {
        return [
            'peso_inicial' => $this->peso_inicial,
            'peso_final' => $this->peso_final,
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $pesos = PesoModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('peso_inicial', 'ILIKE', "%{$q}%")
                    ->orWhere('peso_final', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.peso', [
            'pesos' => $pesos,
        ]);
    }
}
