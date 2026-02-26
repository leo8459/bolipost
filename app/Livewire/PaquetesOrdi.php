<?php

namespace App\Livewire;

use App\Models\Estado;
use App\Models\PaqueteOrdi;
use App\Models\Ventanilla;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
    public $selectAll = false;
    public $selectedCiudadMarcado = '';
    public $reprintCodEspecial = '';
    public $codigoRecibir = '';
    public $previewRecibirIds = [];

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
    private const ESTADO_TRANSITO = 'TRANSITO';
    private const ESTADO_ENVIADO = 'ENVIADO';
    private const ESTADO_RECIBIDO = 'RECIBIDO';
    private const ESTADO_ENTREGADO = 'ENTREGADO';

    public function mount($mode = 'clasificacion')
    {
        $allowedModes = ['clasificacion', 'despacho', 'almacen', 'entregado'];
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

    public function getIsAlmacenProperty()
    {
        return $this->mode === 'almacen';
    }

    public function getIsEntregadoProperty()
    {
        return $this->mode === 'entregado';
    }

    public function searchPaquetes()
    {
        $this->searchQuery = $this->search;
        $this->selectAll = false;
        $this->selectedPaquetes = [];
        $this->selectedCiudadMarcado = '';
        $this->resetPage();
    }

    public function openRecibirModal()
    {
        if (!$this->isAlmacen) {
            return;
        }

        $this->codigoRecibir = '';
        $this->previewRecibirIds = [];
        $this->dispatch('openRecibirModal');
    }

    public function addCodigoRecibir()
    {
        if (!$this->isAlmacen) {
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $codigo = $this->upper($this->codigoRecibir);
        if ($codigo === '') {
            session()->flash('success', 'Ingresa un codigo para recibir.');
            return;
        }

        $estadoEnviadoId = $this->getEstadoIdByNombre(self::ESTADO_ENVIADO);
        if (!$estadoEnviadoId) {
            session()->flash('success', 'No existe el estado ENVIADO en la tabla estados.');
            return;
        }

        $paquete = PaqueteOrdi::query()
            ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
            ->where('fk_estado', $estadoEnviadoId)
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity])
            ->first();

        if (!$paquete) {
            session()->flash('success', 'El paquete no existe, no esta ENVIADO o no pertenece a tu ciudad.');
            return;
        }

        $this->previewRecibirIds = collect($this->previewRecibirIds)
            ->push((int) $paquete->id)
            ->unique()
            ->values()
            ->all();

        $this->codigoRecibir = '';
    }

    public function confirmarRecibir()
    {
        if (!$this->isAlmacen) {
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $ids = collect($this->previewRecibirIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('success', 'No hay paquetes en la previsualizacion.');
            return;
        }

        $estadoEnviadoId = $this->getEstadoIdByNombre(self::ESTADO_ENVIADO);
        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);

        if (!$estadoEnviadoId || !$estadoRecibidoId) {
            session()->flash('success', 'Faltan estados ENVIADO/RECIBIDO en la tabla estados.');
            return;
        }

        PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->where('fk_estado', $estadoEnviadoId)
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity])
            ->update(['fk_estado' => $estadoRecibidoId]);

        $this->previewRecibirIds = [];
        $this->codigoRecibir = '';
        $this->dispatch('closeRecibirModal');
        session()->flash('success', 'Paquetes recibidos correctamente.');
        $this->resetPage();
    }

    public function bajaPaquetes()
    {
        if (!$this->isAlmacen) {
            return;
        }

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

        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        $estadoEntregadoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);

        if (!$estadoRecibidoId || !$estadoEntregadoId) {
            session()->flash('success', 'Faltan estados RECIBIDO/ENTREGADO en la tabla estados.');
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->where('fk_estado', $estadoRecibidoId)
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity])
            ->update(['fk_estado' => $estadoEntregadoId]);

        $this->selectAll = false;
        $this->selectedPaquetes = [];
        session()->flash('success', 'Paquetes enviados a ENTREGADO correctamente.');
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

        $estadoTransitoId = $this->getEstadoIdByNombre(self::ESTADO_TRANSITO);
        if (!$estadoTransitoId) {
            session()->flash('success', 'No existe el estado TRANSITO en la tabla estados.');
            return;
        }

        DB::transaction(function () use ($ids, $estadoTransitoId) {
            $paquetes = PaqueteOrdi::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $correlativo = $this->nextOrdinarioCorrelative();
            $manifiesto = 'O' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT);

            foreach ($paquetes as $paquete) {
                $paquete->fk_estado = $estadoTransitoId;
                $paquete->cod_especial = $manifiesto;
                $paquete->save();
            }
        });

        $packages = PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $this->selectAll = false;
        $this->selectedPaquetes = [];
        $this->selectedCiudadMarcado = '';
        session()->flash('success', 'Paquetes enviados a TRANSITO correctamente.');
        $this->resetPage();

        $manifiesto = (string) optional($packages->first())->cod_especial ?: 'O00000';

        return $this->buildManifiestoResponse($packages, $manifiesto);
    }

    public function reimprimirManifiesto()
    {
        if (!$this->isDespacho) {
            return;
        }

        $codigo = $this->upper($this->reprintCodEspecial);
        if ($codigo === '') {
            session()->flash('success', 'Ingresa un cod_especial para reimprimir.');
            return;
        }

        $packages = PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->whereRaw('trim(upper(cod_especial)) = trim(upper(?))', [$codigo])
            ->orderBy('id')
            ->get();

        if ($packages->isEmpty()) {
            session()->flash('success', 'No se encontraron paquetes con ese cod_especial.');
            return;
        }

        $this->reprintCodEspecial = '';

        return $this->buildManifiestoResponse($packages, $codigo);
    }

    protected function nextOrdinarioCorrelative(): int
    {
        $lastCode = PaqueteOrdi::query()
            ->whereRaw("cod_especial ~ '^O[0-9]{5}$'")
            ->lockForUpdate()
            ->orderByDesc('cod_especial')
            ->value('cod_especial');

        if (!$lastCode) {
            return 1;
        }

        $number = (int) substr((string) $lastCode, 1, 5);
        return $number > 0 ? $number + 1 : 1;
    }

    protected function siglasCiudad(string $ciudad): string
    {
        $normalized = $this->upper($ciudad);

        $map = [
            'LA PAZ' => 'LP',
            'COCHABAMBA' => 'CB',
            'SANTA CRUZ' => 'SC',
            'ORURO' => 'OR',
            'POTOSI' => 'PT',
            'SUCRE' => 'SQ',
            'TARIJA' => 'TJ',
            'TRINIDAD' => 'BN',
            'COBIJA' => 'PD',
            'VARIOS' => 'VR',
            'N/A' => 'NA',
        ];

        return $map[$normalized] ?? substr(str_replace(' ', '', $normalized), 0, 2);
    }

    protected function buildManifiestoResponse($packages, string $manifiesto)
    {
        $ciudadOrigen = $this->upper((string) optional(auth()->user())->ciudad ?: 'N/A');
        $ciudadesDestino = $packages->pluck('ciudad')
            ->map(fn ($city) => $this->upper($city))
            ->filter()
            ->unique()
            ->values();

        $ciudadDestino = $ciudadesDestino->count() === 1
            ? (string) $ciudadesDestino->first()
            : ($ciudadesDestino->count() > 1 ? 'VARIOS' : 'N/A');

        $pdf = Pdf::loadView('paquetes_ordi.manifiesto-despacho', [
            'packages' => $packages,
            'manifiesto' => $manifiesto,
            'ciudadOrigen' => $ciudadOrigen,
            'ciudadDestino' => $ciudadDestino,
            'siglasOrigen' => $this->siglasCiudad($ciudadOrigen),
            'siglasDestino' => $this->siglasCiudad($ciudadDestino),
            'anioPaquete' => now()->format('Y'),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'manifiesto-clasificacion-' . $manifiesto . '.pdf');
    }

    public function delete($id)
    {
        $paquete = PaqueteOrdi::findOrFail($id);
        $paquete->delete();
        session()->flash('success', 'Paquete ordinario eliminado correctamente.');
    }

    public function devolverAClasificacion($id)
    {
        $estadoClasificacionId = $this->getClasificacionEstadoId();
        if (!$estadoClasificacionId) {
            session()->flash('success', 'No existe el estado CLASIFICACION en la tabla estados.');
            return;
        }

        $paquete = PaqueteOrdi::findOrFail($id);
        $paquete->update([
            'fk_estado' => $estadoClasificacionId,
        ]);

        session()->flash('success', 'Paquete devuelto a CLASIFICACION correctamente.');
        $this->resetPage();
    }

    public function altaAAlmacen($id)
    {
        if (!$this->isEntregado) {
            return;
        }

        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        if (!$estadoRecibidoId) {
            session()->flash('success', 'No existe el estado RECIBIDO en la tabla estados.');
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $paquete = PaqueteOrdi::query()
            ->where('id', $id)
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity])
            ->firstOrFail();

        $paquete->update([
            'fk_estado' => $estadoRecibidoId,
        ]);

        session()->flash('success', 'Paquete dado de alta a ALMACEN correctamente.');
        $this->resetPage();
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
        $this->selectAll = false;
        $this->selectedPaquetes = [];
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

    public function toggleSelectAll($checked)
    {
        if (!$this->isClasificacion && !$this->isAlmacen) {
            return;
        }

        if ((bool) $checked) {
            $this->selectedPaquetes = collect($this->allFilteredPaqueteIds())
                ->map(fn ($id) => (string) $id)
                ->all();
            $this->selectAll = true;
            return;
        }

        $this->selectAll = false;
        $this->selectedPaquetes = [];
    }

    public function updatedSelectedPaquetes()
    {
        if (!$this->isClasificacion && !$this->isAlmacen) {
            return;
        }

        $allIds = $this->allFilteredPaqueteIds();
        if (empty($allIds)) {
            $this->selectAll = false;
            return;
        }

        $selected = collect($this->selectedPaquetes)
            ->map(fn ($id) => (int) $id);

        $this->selectAll = collect($allIds)->every(
            fn ($id) => $selected->contains((int) $id)
        );
    }

    public function updatedSelectedCiudadMarcado($value)
    {
        if (!$this->isClasificacion) {
            return;
        }

        $ciudad = $this->upper($value);
        $this->selectedCiudadMarcado = $ciudad;

        if ($ciudad === '') {
            $this->selectAll = false;
            $this->selectedPaquetes = [];
            return;
        }

        $this->selectedPaquetes = $this->basePaquetesQuery()
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$ciudad])
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->updatedSelectedPaquetes();
    }

    protected function ciudadesDisponibles(): array
    {
        if ($this->isDespacho) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_TRANSITO);
        } elseif ($this->isAlmacen) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        } elseif ($this->isEntregado) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        } else {
            $estadoModoId = $this->getClasificacionEstadoId();
        }
        $userCity = $this->upper((string) optional(auth()->user())->ciudad);

        if (!$estadoModoId || $userCity === '') {
            return [];
        }

        return PaqueteOrdi::query()
            ->where('fk_estado', $estadoModoId)
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity])
            ->whereNotNull('ciudad')
            ->select('ciudad')
            ->distinct()
            ->orderBy('ciudad')
            ->pluck('ciudad')
            ->map(fn ($ciudad) => $this->upper($ciudad))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function allFilteredPaqueteIds(): array
    {
        return $this->basePaquetesQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function basePaquetesQuery(): Builder
    {
        $q = trim($this->searchQuery);
        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($this->isDespacho) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_TRANSITO);
        } elseif ($this->isAlmacen) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        } elseif ($this->isEntregado) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        } else {
            $estadoModoId = $this->getClasificacionEstadoId();
        }

        return PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->when($estadoModoId, function ($query) use ($estadoModoId) {
                $query->where('fk_estado', $estadoModoId);
            })
            ->when(!$estadoModoId, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($userCity !== '', function ($query) use ($userCity) {
                $query->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity]);
            })
            ->when($userCity === '', function ($query) {
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
            ->orderByDesc('id');
    }

    public function render()
    {
        $paquetes = $this->basePaquetesQuery()->paginate(10);
        $previewRecibirPaquetes = collect();
        if (!empty($this->previewRecibirIds)) {
            $previewRecibirPaquetes = PaqueteOrdi::query()
                ->with(['estado', 'ventanillaRef'])
                ->whereIn('id', $this->previewRecibirIds)
                ->orderBy('id')
                ->get();
        }

        return view('livewire.paquetes-ordi', [
            'paquetes' => $paquetes,
            'ventanillas' => $this->getVentanillasByCiudad(),
            'ciudadesDisponibles' => $this->ciudadesDisponibles(),
            'previewRecibirPaquetes' => $previewRecibirPaquetes,
        ]);
    }
}
