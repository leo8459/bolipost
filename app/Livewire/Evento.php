<?php

namespace App\Livewire;

use App\Models\Evento as EventoModel;
use Livewire\Component;
use Livewire\WithPagination;

class Evento extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nombre_evento = '';

    protected $paginationTheme = 'bootstrap';

    public function searchEventos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openEventoModal');
    }

    public function openEditModal($id)
    {
        $evento = EventoModel::findOrFail($id);

        $this->editingId = $evento->id;
        $this->nombre_evento = $evento->nombre_evento;

        $this->dispatch('openEventoModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        $this->nombre_evento = trim((string) $this->nombre_evento);
        $duplicateExists = EventoModel::query()
            ->whereRaw('TRIM(UPPER(nombre_evento)) = ?', [mb_strtoupper($this->nombre_evento)])
            ->when($this->editingId, fn ($query) => $query->where('id', '<>', (int) $this->editingId))
            ->exists();

        if ($duplicateExists) {
            $this->addError('nombre_evento', 'Ya existe un evento con este nombre.');

            return;
        }

        if ($this->editingId) {
            $evento = EventoModel::findOrFail($this->editingId);
            $evento->update($this->payload());
            session()->flash('success', 'Evento actualizado correctamente.');
        } else {
            EventoModel::create($this->payload());
            session()->flash('success', 'Evento creado correctamente.');
        }

        $this->dispatch('closeEventoModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $evento = EventoModel::findOrFail($id);
        $evento->delete();
        session()->flash('success', 'Evento eliminado correctamente.');
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

        $eventos = EventoModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre_evento', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(100);

        return view('livewire.evento', [
            'eventos' => $eventos,
        ]);
    }
}

