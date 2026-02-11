<?php

namespace App\Livewire;

use App\Models\Despacho;
use App\Models\Saca as SacaModel;
use Livewire\Component;
use Livewire\WithPagination;

class Saca extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nro_saca = '';
    public $identificador = '';
    public $estado = '';
    public $peso = '';
    public $paquetes = '';
    public $busqueda = '';
    public $receptaculo = '';
    public $fk_despacho = '';

    protected $paginationTheme = 'bootstrap';

    public function searchSacas()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openSacaModal');
    }

    public function openEditModal($id)
    {
        $saca = SacaModel::findOrFail($id);

        $this->editingId = $saca->id;
        $this->nro_saca = $saca->nro_saca;
        $this->identificador = $saca->identificador;
        $this->estado = $saca->estado;
        $this->peso = $saca->peso;
        $this->paquetes = $saca->paquetes;
        $this->busqueda = $saca->busqueda;
        $this->receptaculo = $saca->receptaculo;
        $this->fk_despacho = $saca->fk_despacho;

        $this->dispatch('openSacaModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $saca = SacaModel::findOrFail($this->editingId);
            $saca->update($this->payload());
            session()->flash('success', 'Saca actualizada correctamente.');
        } else {
            SacaModel::create($this->payload());
            session()->flash('success', 'Saca creada correctamente.');
        }

        $this->dispatch('closeSacaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $saca = SacaModel::findOrFail($id);
        $saca->delete();
        session()->flash('success', 'Saca eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nro_saca',
            'identificador',
            'estado',
            'peso',
            'paquetes',
            'busqueda',
            'receptaculo',
            'fk_despacho',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nro_saca' => 'required|string|max:255',
            'identificador' => 'required|string|max:255',
            'estado' => 'required|string|max:255',
            'peso' => 'nullable|numeric|min:0.001',
            'paquetes' => 'nullable|integer|min:0',
            'busqueda' => 'nullable|string|max:255',
            'receptaculo' => 'nullable|string|max:255',
            'fk_despacho' => 'required|integer|exists:despacho,id',
        ];
    }

    protected function payload()
    {
        return [
            'nro_saca' => $this->nro_saca,
            'identificador' => $this->identificador,
            'estado' => $this->estado,
            'peso' => $this->normalizeNullable($this->peso),
            'paquetes' => $this->normalizeNullable($this->paquetes),
            'busqueda' => $this->normalizeNullable($this->busqueda),
            'receptaculo' => $this->normalizeNullable($this->receptaculo),
            'fk_despacho' => $this->fk_despacho,
        ];
    }

    protected function normalizeNullable($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $sacas = SacaModel::query()
            ->with('despacho:id,identificador,nro_despacho,anio')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nro_saca', 'ILIKE', "%{$q}%")
                    ->orWhere('identificador', 'ILIKE', "%{$q}%")
                    ->orWhere('estado', 'ILIKE', "%{$q}%")
                    ->orWhere('busqueda', 'ILIKE', "%{$q}%")
                    ->orWhere('receptaculo', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        $despachos = Despacho::query()
            ->orderByDesc('id')
            ->get(['id', 'identificador', 'nro_despacho', 'anio']);

        return view('livewire.saca', [
            'sacas' => $sacas,
            'despachos' => $despachos,
        ]);
    }
}
