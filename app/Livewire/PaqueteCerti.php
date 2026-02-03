<?php

namespace App\Livewire;

use App\Models\Estado as EstadoModel;
use App\Models\PaqueteCerti as PaqueteCertiModel;
use App\Models\Ventanilla as VentanillaModel;
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
    public $peso = '';
    public $tipo = '';
    public $aduana = '';
    public $fk_estado = '';
    public $fk_ventanilla = '';

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
        $this->fk_ventanilla = $paquete->fk_ventanilla ? (string) $paquete->fk_ventanilla : '';
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
            'peso',
            'tipo',
            'aduana',
            'fk_estado',
            'fk_ventanilla',
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
            'peso' => 'required|numeric|min:0',
            'tipo' => 'required|string|max:255',
            'aduana' => 'required|string|max:255',
            'fk_estado' => 'required|exists:estados,id',
            'fk_ventanilla' => 'required|exists:ventanilla,id',
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
            'peso' => $this->peso,
            'tipo' => $this->upper($this->tipo),
            'aduana' => $this->upper($this->aduana),
            'fk_estado' => $this->fk_estado,
            'fk_ventanilla' => $this->fk_ventanilla,
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
            ->with(['estado', 'ventanillaRef'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where('codigo', 'ILIKE', "%{$q}%")
                    ->orWhere('destinatario', 'ILIKE', "%{$q}%")
                    ->orWhere('telefono', 'ILIKE', "%{$q}%")
                    ->orWhere('cuidad', 'ILIKE', "%{$q}%")
                    ->orWhere('zona', 'ILIKE', "%{$q}%")
                    ->orWhere('peso', 'ILIKE', "%{$q}%")
                    ->orWhere('tipo', 'ILIKE', "%{$q}%")
                    ->orWhere('aduana', 'ILIKE', "%{$q}%")
                    ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                        $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                    })
                    ->orWhereHas('ventanillaRef', function ($ventanillaQuery) use ($q) {
                        $ventanillaQuery->where('nombre_ventanilla', 'ILIKE', "%{$q}%");
                    });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.paquete-certi', [
            'paquetes' => $paquetes,
            'estados' => EstadoModel::orderBy('nombre_estado')->get(),
            'ventanillas' => VentanillaModel::orderBy('nombre_ventanilla')->get(),
        ]);
    }
}
