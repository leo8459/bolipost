<?php

namespace App\Livewire;

use App\Models\Destino as DestinoModel;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Destino extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $nombre_destino = '';

    public $departamentos = [
        'LA PAZ',
        'SANTA CRUZ',
        'PANDO',
        'BENI',
        'TARIJA',
        'CHUQUISACA',
        'ORURO',
        'COCHABAMBA',
        'POTOSI',
    ];

    protected $paginationTheme = 'bootstrap';

    public function searchDestinos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openDestinoModal');
    }

    public function openEditModal($id)
    {
        $destino = DestinoModel::findOrFail($id);
        $this->editingId = $destino->id;
        $this->nombre_destino = $destino->nombre_destino;

        $this->dispatch('openDestinoModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $destino = DestinoModel::findOrFail($this->editingId);
            $destino->update($this->payload());
            session()->flash('success', 'Destino actualizado correctamente.');
        } else {
            DestinoModel::create($this->payload());
            session()->flash('success', 'Destino creado correctamente.');
        }

        $this->dispatch('closeDestinoModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $destino = DestinoModel::findOrFail($id);
        $destino->delete();
        session()->flash('success', 'Destino eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre_destino',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre_destino' => [
                'required',
                'string',
                'max:255',
                Rule::in($this->departamentos),
            ],
        ];
    }

    protected function payload()
    {
        return [
            'nombre_destino' => $this->nombre_destino,
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $destinos = DestinoModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre_destino', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.destino', [
            'destinos' => $destinos,
        ]);
    }
}
