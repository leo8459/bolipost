<?php

namespace App\Livewire;

use App\Models\VehicleBrand;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleBrandManager extends Component
{
    use WithPagination;

    public string $search = '';

    #[Validate('required|string|max:255')]
    public string $nombre = '';

    public string $pais_origen = '';

    public bool $isEdit = false;
    public ?int $editingBrandId = null;
    public bool $showForm = false;

    public function render()
    {
        $query = VehicleBrand::query()->orderBy('nombre');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('pais_origen', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"]);
            });
        }

        $brands = $query->paginate(10);
        return view('livewire.vehicle-brand-manager', ['brands' => $brands]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save()
    {
        $this->validate();

        $payload = [
            'nombre' => $this->nombre,
            'pais_origen' => $this->pais_origen !== '' ? $this->pais_origen : null,
        ];

        if ($this->isEdit && $this->editingBrandId) {
            $brand = VehicleBrand::find($this->editingBrandId);
            if ($brand) {
                $brand->update($payload);
                session()->flash('message', 'Marca actualizada correctamente.');
            }
        } else {
            VehicleBrand::create($payload);
            session()->flash('message', 'Marca creada correctamente.');
        }

        $this->resetForm();
    }

    public function edit(VehicleBrand $brand)
    {
        $this->showForm = true;
        $this->isEdit = true;
        $this->editingBrandId = $brand->id;
        $this->nombre = $brand->nombre;
        $this->pais_origen = (string) ($brand->pais_origen ?? '');
    }

    public function delete(VehicleBrand $brand)
    {
        $brand->delete();
        session()->flash('message', 'Marca eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->nombre = '';
        $this->pais_origen = '';
        $this->isEdit = false;
        $this->editingBrandId = null;
        $this->showForm = false;
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function cancelForm()
    {
        $this->resetForm();
    }
}
