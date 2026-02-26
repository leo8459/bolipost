<?php

namespace App\Livewire;

use App\Models\Estado;
use App\Models\PaqueteOrdi;
use App\Models\Ventanilla;
use Livewire\Component;
use Livewire\WithPagination;

class PaquetesOrdi extends Component
{
    use WithPagination;

    public $mode = 'clasificacion';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $selectedPaquetes = [];

    public $codigo = '';
    public $destinatario = '';
    public $telefono = '';
    public $ciudad = '';
    public $zona = '';
    public $peso = '';
    public $aduana = '';
    public $observaciones = '';
    public $cod_especial = '';
    public $fk_ventanilla = '';
    public $fk_estado = '';

    protected $paginationTheme = 'bootstrap';

    private const ESTADO_CLASIFICACION = 'CLASIFICACION';
    private const ESTADO_DESPACHO = 'DESPACHO';

    public function mount($mode = 'clasificacion')
    {
        $allowedModes = ['clasificacion', 'despacho'];
        $this->mode = in_array($mode, $allowedModes, true) ? $mode : 'clasificacion';
    }

    public function getIsClasificacionProperty()
    {
        return $this->mode === 'clasificacion';
    }

    public function getIsDespachoProperty()
    {
        return $this->mode === 'despacho';
    }

    public function searchPaquetes()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;

        $userCity = trim((string) optional(auth()->user())->ciudad);
        if ($userCity !== '') {
            $this->ciudad = $userCity;
        }

        $this->dispatch('openPaqueteOrdiModal');
    }

    public function openEditModal($id)
    {
        $paquete = PaqueteOrdi::findOrFail($id);

        $this->editingId = $paquete->id;
        $this->codigo = $paquete->codigo;
        $this->destinatario = $paquete->destinatario;
        $this->telefono = $paquete->telefono;
        $this->ciudad = $paquete->ciudad;
        $this->zona = $paquete->zona;
        $this->peso = $paquete->peso;
        $this->aduana = $paquete->aduana;
        $this->observaciones = $paquete->observaciones ?? '';
        $this->cod_especial = $paquete->cod_especial ?? '';
        $this->fk_ventanilla = $paquete->fk_ventanilla ? (string) $paquete->fk_ventanilla : '';
        $this->fk_estado = $paquete->fk_estado ? (string) $paquete->fk_estado : '';

        $this->dispatch('openPaqueteOrdiModal');
    }

    public function save()
    {
        if (!$this->editingId) {
            $estadoClasificacionId = $this->getClasificacionEstadoId();
            if (!$estadoClasificacionId) {
                session()->flash('success', 'No existe el estado CLASIFICACION en la tabla estados.');
                return;
            }
            $this->fk_estado = (string) $estadoClasificacionId;
        }

        $this->validate($this->rules());

        if ($this->editingId) {
            $paquete = PaqueteOrdi::findOrFail($this->editingId);
            $paquete->update($this->payload());
            session()->flash('success', 'Paquete ordinario actualizado correctamente.');
        } else {
            PaqueteOrdi::create($this->payload());
            session()->flash('success', 'Paquete ordinario creado correctamente.');
        }

        $this->dispatch('closePaqueteOrdiModal');
        $this->resetForm();
    }

    public function despacharSeleccionados()
    {
        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoDespachoId = $this->getEstadoIdByNombre(self::ESTADO_DESPACHO);
        if (!$estadoDespachoId) {
            session()->flash('success', 'No existe el estado DESPACHO en la tabla estados.');
            return;
        }

        PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->update(['fk_estado' => $estadoDespachoId]);

        $this->selectedPaquetes = [];
        session()->flash('success', 'Paquetes despachados correctamente.');
        $this->resetPage();
    }

    public function delete($id)
    {
        $paquete = PaqueteOrdi::findOrFail($id);
        $paquete->delete();
        session()->flash('success', 'Paquete ordinario eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'codigo',
            'destinatario',
            'telefono',
            'ciudad',
            'zona',
            'peso',
            'aduana',
            'observaciones',
            'cod_especial',
            'fk_ventanilla',
            'fk_estado',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'codigo' => 'required|string|max:255',
            'destinatario' => 'required|string|max:255',
            'telefono' => 'required|string|max:30',
            'ciudad' => 'required|string|max:255',
            'zona' => 'nullable|string|max:255',
            'peso' => 'required|numeric|min:0',
            'aduana' => 'required|string|max:50',
            'observaciones' => 'nullable|string|max:1000',
            'cod_especial' => 'nullable|string|max:255',
            'fk_ventanilla' => 'required|exists:ventanilla,id',
            'fk_estado' => 'required|exists:estados,id',
        ];
    }

    protected function payload()
    {
        return [
            'codigo' => $this->upper($this->codigo),
            'destinatario' => $this->upper($this->destinatario),
            'telefono' => trim((string) $this->telefono),
            'ciudad' => $this->upper($this->ciudad),
            'zona' => $this->upper($this->zona),
            'peso' => $this->peso,
            'aduana' => $this->upper($this->aduana),
            'observaciones' => $this->emptyToNull($this->observaciones),
            'cod_especial' => $this->emptyToNull($this->cod_especial),
            'fk_ventanilla' => $this->fk_ventanilla,
            'fk_estado' => $this->fk_estado,
        ];
    }

    protected function emptyToNull($value)
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $this->upper($trimmed);
    }

    protected function upper($value)
    {
        return strtoupper(trim((string) $value));
    }

    public function updatedCiudad($value)
    {
        $this->ciudad = $this->upper($value);
        $this->fk_ventanilla = '';
        $this->resetPage();
    }

    public function changeCiudad($value)
    {
        $this->updatedCiudad($value);
    }

    protected function getClasificacionEstadoId(): ?int
    {
        return $this->getEstadoIdByNombre(self::ESTADO_CLASIFICACION);
    }

    protected function getEstadoIdByNombre(string $estado): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($estado))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function getVentanillasByCiudad()
    {
        $ciudad = $this->upper($this->ciudad);

        return Ventanilla::query()
            ->when($ciudad === 'LA PAZ', function ($query) {
                $query->where(function ($sub) {
                    $sub->whereRaw('trim(upper(nombre_ventanilla)) = ?', ['DD'])
                        ->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', ['DND']);
                });
            })
            ->when($ciudad !== '' && $ciudad !== 'LA PAZ', function ($query) {
                $query->whereRaw('trim(upper(nombre_ventanilla)) = ?', ['UNICA']);
            })
            ->orderBy('nombre_ventanilla')
            ->get();
    }

    public function render()
    {
        $q = trim($this->searchQuery);
        $estadoModoId = $this->isDespacho
            ? $this->getEstadoIdByNombre(self::ESTADO_DESPACHO)
            : $this->getClasificacionEstadoId();

        $paquetes = PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->when($estadoModoId, function ($query) use ($estadoModoId) {
                $query->where('fk_estado', $estadoModoId);
            })
            ->when(!$estadoModoId, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'ILIKE', "%{$q}%")
                        ->orWhere('destinatario', 'ILIKE', "%{$q}%")
                        ->orWhere('telefono', 'ILIKE', "%{$q}%")
                        ->orWhere('ciudad', 'ILIKE', "%{$q}%")
                        ->orWhere('zona', 'ILIKE', "%{$q}%")
                        ->orWhere('peso', 'ILIKE', "%{$q}%")
                        ->orWhere('aduana', 'ILIKE', "%{$q}%")
                        ->orWhere('observaciones', 'ILIKE', "%{$q}%")
                        ->orWhere('cod_especial', 'ILIKE', "%{$q}%")
                        ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                            $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('ventanillaRef', function ($ventanillaQuery) use ($q) {
                            $ventanillaQuery->where('nombre_ventanilla', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.paquetes-ordi', [
            'paquetes' => $paquetes,
            'ventanillas' => $this->getVentanillasByCiudad(),
        ]);
    }
}
