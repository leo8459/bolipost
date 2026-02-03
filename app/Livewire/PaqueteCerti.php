<?php

namespace App\Livewire;

use App\Models\Estado as EstadoModel;
use App\Models\PaqueteCerti as PaqueteCertiModel;
use Livewire\Component;
use Livewire\WithPagination;

class PaqueteCerti extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $codigo = '';
    public $destinatario = '';
    public $telefono = '';
    public $cuidad = '';
    public $zona = '';
    public $ventanilla = '';
    public $peso = '';
    public $tipo = '';
    public $aduana = '';
    public $fk_estado = '';

    protected $paginationTheme = 'bootstrap';

    public function searchPaquetes()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openPaqueteCertiModal');
    }

    public function openEditModal($id)
    {
        $paquete = PaqueteCertiModel::findOrFail($id);

        $this->editingId = $paquete->id;
        $this->codigo = $paquete->codigo;
        $this->destinatario = $paquete->destinatario;
        $this->telefono = $paquete->telefono;
        $this->cuidad = $paquete->cuidad;
        $this->zona = $paquete->zona;
        $this->ventanilla = $paquete->ventanilla;
        $this->peso = $paquete->peso;
        $this->tipo = $paquete->tipo;
        $this->aduana = $paquete->aduana;
        $this->fk_estado = $paquete->fk_estado ? (string) $paquete->fk_estado : '';

        $this->dispatch('openPaqueteCertiModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $paquete = PaqueteCertiModel::findOrFail($this->editingId);
            $paquete->update($this->payload());
            session()->flash('success', 'Paquete certificado actualizado correctamente.');
        } else {
            PaqueteCertiModel::create($this->payload());
            session()->flash('success', 'Paquete certificado creado correctamente.');
        }

        $this->dispatch('closePaqueteCertiModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $paquete = PaqueteCertiModel::findOrFail($id);
        $paquete->delete();
        session()->flash('success', 'Paquete certificado eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'codigo',
            'destinatario',
            'telefono',
            'cuidad',
            'zona',
            'ventanilla',
            'peso',
            'tipo',
            'aduana',
            'fk_estado',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'codigo' => 'required|string|max:255',
            'destinatario' => 'required|string|max:255',
            'telefono' => 'required|integer|min:0',
            'cuidad' => 'required|string|max:255',
            'zona' => 'required|string|max:255',
            'ventanilla' => 'required|string|max:255',
            'peso' => 'required|numeric|min:0',
            'tipo' => 'required|string|max:255',
            'aduana' => 'required|string|max:255',
            'fk_estado' => 'required|exists:estados,id',
        ];
    }

    protected function payload()
    {
        return [
            'codigo' => $this->upper($this->codigo),
            'destinatario' => $this->upper($this->destinatario),
            'telefono' => $this->telefono,
            'cuidad' => $this->upper($this->cuidad),
            'zona' => $this->upper($this->zona),
            'ventanilla' => $this->upper($this->ventanilla),
            'peso' => $this->peso,
            'tipo' => $this->upper($this->tipo),
            'aduana' => $this->upper($this->aduana),
            'fk_estado' => $this->fk_estado,
        ];
    }

    protected function upper($value)
    {
        return strtoupper(trim((string) $value));
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $paquetes = PaqueteCertiModel::query()
            ->with('estado')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('codigo', 'ILIKE', "%{$q}%")
                    ->orWhere('destinatario', 'ILIKE', "%{$q}%")
                    ->orWhere('telefono', 'ILIKE', "%{$q}%")
                    ->orWhere('cuidad', 'ILIKE', "%{$q}%")
                    ->orWhere('zona', 'ILIKE', "%{$q}%")
                    ->orWhere('ventanilla', 'ILIKE', "%{$q}%")
                    ->orWhere('peso', 'ILIKE', "%{$q}%")
                    ->orWhere('tipo', 'ILIKE', "%{$q}%")
                    ->orWhere('aduana', 'ILIKE', "%{$q}%")
                    ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                        $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                    });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.paquete-certi', [
            'paquetes' => $paquetes,
            'estados' => EstadoModel::orderBy('nombre_estado')->get(),
        ]);
    }
}
