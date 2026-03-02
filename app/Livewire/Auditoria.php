<?php

namespace App\Livewire;

use App\Models\Auditoria as AuditoriaModel;
use Livewire\Component;
use Livewire\WithPagination;

class Auditoria extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nombre_evento = '';

    protected $paginationTheme = 'bootstrap';

    public function searchAuditoria()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openAuditoriaModal');
    }

    public function openEditModal($id)
    {
        $auditoria = AuditoriaModel::findOrFail($id);

        $this->editingId = $auditoria->id;
        $this->nombre_evento = $auditoria->nombre_evento;

        $this->dispatch('openAuditoriaModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $auditoria = AuditoriaModel::findOrFail($this->editingId);
            $auditoria->update($this->payload());
            session()->flash('success', 'Auditoria actualizada correctamente.');
        } else {
            AuditoriaModel::create($this->payload());
            session()->flash('success', 'Auditoria creada correctamente.');
        }

        $this->dispatch('closeAuditoriaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $auditoria = AuditoriaModel::findOrFail($id);
        $auditoria->delete();
        session()->flash('success', 'Auditoria eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre_evento',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre_evento' => 'required|string|max:255',
        ];
    }

    protected function payload()
    {
        return [
            'nombre_evento' => trim((string) $this->nombre_evento),
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $auditorias = AuditoriaModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre_evento', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.auditoria', [
            'auditorias' => $auditorias,
        ]);
    }
}

