<?php

namespace App\Livewire;

use App\Models\VehicleBrand;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleBrandManager extends Component
{
    use WithPagination;

    private const COUNTRY_OPTIONS = [
        'Japon',
        'Corea del Sur',
        'China',
        'India',
        'Tailandia',
        'Indonesia',
        'Estados Unidos',
        'Mexico',
        'Canada',
        'Brasil',
        'Argentina',
        'Alemania',
        'Francia',
        'Italia',
        'Espana',
        'Reino Unido',
        'Suecia',
        'Republica Checa',
        'Turquia',
        'Sudafrica',
        'Rusia',
        'Hungria',
    ];

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
        return view('livewire.vehicle-brand-manager', [
            'brands' => $brands,
            'countryOptions' => self::COUNTRY_OPTIONS,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->search = $this->sanitizeText($this->search);
        $this->resetPage();
    }

    public function updatedNombre(string $value): void
    {
        $this->nombre = $this->sanitizeText($value);
    }

    public function updatedPaisOrigen(string $value): void
    {
        $clean = $this->sanitizeText($value);
        $this->pais_origen = in_array($clean, self::COUNTRY_OPTIONS, true) ? $clean : '';
    }

    public function save()
    {
        $this->nombre = $this->sanitizeText($this->nombre);
        $this->pais_origen = in_array($this->pais_origen, self::COUNTRY_OPTIONS, true) ? $this->pais_origen : '';

        $this->validate([
            'nombre' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN\s\-\/\.\(\)]+$/u'],
            'pais_origen' => ['nullable', 'string', 'max:255', 'in:' . implode(',', self::COUNTRY_OPTIONS)],
        ]);

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

    private function sanitizeText(?string $value): string
    {
        $clean = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', (string) $value) ?? '';
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? $clean;
        return $clean;
    }
}
