<?php

namespace App\Livewire;

use App\Models\Origen as OrigenModel;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Origen extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $nombre_origen = '';

    public $tipos = [
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

    public function searchOrigenes()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openOrigenModal');
    }

    public function openEditModal($id)
    {
        $origen = OrigenModel::findOrFail($id);
        $this->editingId = $origen->id;
        $this->nombre_origen = $origen->nombre_origen;

        $this->dispatch('openOrigenModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $origen = OrigenModel::findOrFail($this->editingId);
            $origen->update($this->payload());
            session()->flash('success', 'Origen actualizado correctamente.');
        } else {
            OrigenModel::create($this->payload());
            session()->flash('success', 'Origen creado correctamente.');
        }

        $this->dispatch('closeOrigenModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $origen = OrigenModel::findOrFail($id);
        $origen->delete();
        session()->flash('success', 'Origen eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre_origen',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre_origen' => [
                'required',
                'string',
                'max:255',
                Rule::in($this->tipos),
            ],
        ];
    }

    protected function payload()
    {
        return [
            'nombre_origen' => $this->nombre_origen,
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $origenes = OrigenModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre_origen', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.origen', [
            'origenes' => $origenes,
        ]);
    }
}
