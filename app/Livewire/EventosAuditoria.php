<?php

namespace App\Livewire;

use App\Models\Auditoria as AuditoriaModel;
use App\Models\EventoAuditoria as EventoAuditoriaModel;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class EventosAuditoria extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $codigo = '';
    public $auditoria_id = '';
    public $user_id = '';

    protected $paginationTheme = 'bootstrap';

    public function searchEventosAuditoria()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openEventosAuditoriaModal');
    }

    public function openEditModal($id)
    {
        $eventoAuditoria = EventoAuditoriaModel::findOrFail($id);

        $this->editingId = $eventoAuditoria->id;
        $this->codigo = $eventoAuditoria->codigo;
        $this->auditoria_id = (string) $eventoAuditoria->auditoria_id;
        $this->user_id = (string) $eventoAuditoria->user_id;

        $this->dispatch('openEventosAuditoriaModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $eventoAuditoria = EventoAuditoriaModel::findOrFail($this->editingId);
            $eventoAuditoria->update($this->payload());
            session()->flash('success', 'Evento Auditoria actualizado correctamente.');
        } else {
            EventoAuditoriaModel::create($this->payload());
            session()->flash('success', 'Evento Auditoria creado correctamente.');
        }

        $this->dispatch('closeEventosAuditoriaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $eventoAuditoria = EventoAuditoriaModel::findOrFail($id);
        $eventoAuditoria->delete();
        session()->flash('success', 'Evento Auditoria eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'codigo',
            'auditoria_id',
            'user_id',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'codigo' => 'required|string|max:255',
            'auditoria_id' => 'required|integer|exists:auditoria,id',
            'user_id' => 'required|integer|exists:users,id',
        ];
    }

    protected function payload()
    {
        return [
            'codigo' => trim((string) $this->codigo),
            'auditoria_id' => (int) $this->auditoria_id,
            'user_id' => (int) $this->user_id,
        ];
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $registros = EventoAuditoriaModel::query()
            ->with(['auditoria', 'user'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'ILIKE', "%{$q}%")
                        ->orWhereHas('auditoria', function ($auditoriaQuery) use ($q) {
                            $auditoriaQuery->where('nombre_evento', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($q) {
                            $userQuery->where('name', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(100);

        return view('livewire.eventos-auditoria', [
            'registros' => $registros,
            'auditorias' => AuditoriaModel::query()->orderBy('nombre_evento')->get(['id', 'nombre_evento']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
