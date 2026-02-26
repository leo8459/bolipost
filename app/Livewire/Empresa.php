<?php

namespace App\Livewire;

use App\Models\Empresa as EmpresaModel;
use Livewire\Component;
use Livewire\WithPagination;

class Empresa extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nombre = '';
    public $sigla = '';
    public $codigo_cliente = '';

    protected $paginationTheme = 'bootstrap';

    public function searchEmpresas()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openEmpresaModal');
    }

    public function openEditModal($id)
    {
        $empresa = EmpresaModel::findOrFail($id);

        $this->editingId = $empresa->id;
        $this->nombre = $empresa->nombre;
        $this->sigla = $empresa->sigla;
        $this->codigo_cliente = $empresa->codigo_cliente;

        $this->dispatch('openEmpresaModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $empresa = EmpresaModel::findOrFail($this->editingId);
            $empresa->update($this->payload());
            session()->flash('success', 'Empresa actualizada correctamente.');
        } else {
            EmpresaModel::create($this->payload());
            session()->flash('success', 'Empresa creada correctamente.');
        }

        $this->dispatch('closeEmpresaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $empresa = EmpresaModel::findOrFail($id);
        $empresa->delete();
        session()->flash('success', 'Empresa eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre',
            'sigla',
            'codigo_cliente',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre' => 'required|string|max:255',
            'sigla' => 'required|string|max:255',
            'codigo_cliente' => 'required|string|max:255',
        ];
    }

    protected function payload()
    {
        return [
            'nombre' => $this->upper($this->nombre),
            'sigla' => $this->upper($this->sigla),
            'codigo_cliente' => $this->upper($this->codigo_cliente),
        ];
    }

    protected function upper($value)
    {
        return strtoupper(trim((string) $value));
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $empresas = EmpresaModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre', 'ILIKE', "%{$q}%")
                    ->orWhere('sigla', 'ILIKE', "%{$q}%")
                    ->orWhere('codigo_cliente', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.empresa', [
            'empresas' => $empresas,
        ]);
    }
}

