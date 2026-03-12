<?php

namespace App\Livewire;

use App\Models\Estado as EstadoModel;
use App\Models\PaqueteCerti as PaqueteCertiModel;
use App\Models\Ventanilla as VentanillaModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PaqueteCerti extends Component
{
    use WithPagination;
    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 168;
    private const EVENTO_ID_CORRECCION_DATOS = 173;
    private const EVENTO_ID_PAQUETE_PROCESADO_CLASIFICACION = 316;
    private const EVENTO_ID_PAQUETE_RETENIDO_PUNTO_ENTREGA = 183;
    private const EVENTO_ID_PAQUETE_RECIBIDO_OFICINA_ENTREGA = 172;
    private const EVENTO_ID_PAQUETE_MARCADO_ELIMINADO = 278;
    private const ESTADO_VENTANILLA = 'VENTANILLA';
    private const ESTADO_ENTREGADO = 'ENTREGADO';
    private const ESTADO_REZAGO = 'REZAGO';
    private const MODE_ROUTE_PERMISSIONS = [
        'almacen' => 'paquetes-certificados.almacen',
        'inventario' => 'paquetes-certificados.inventario',
        'rezago' => 'paquetes-certificados.rezago',
        'todos' => 'paquetes-certificados.todos',
    ];

    public $mode = 'almacen';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $selectedPaquetes = [];
    public $reencaminarId = null;
    public $reencaminarCuidad = '';

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

    public function mount($mode = 'almacen')
    {
        $allowedModes = ['almacen', 'inventario', 'rezago', 'todos'];
        $this->mode = in_array($mode, $allowedModes, true) ? $mode : 'almacen';
    }

    public function getIsAlmacenProperty()
    {
        return $this->mode === 'almacen';
    }

    public function getIsInventoryProperty()
    {
        return $this->mode === 'inventario';
    }

    public function getIsRezagoProperty()
    {
        return $this->mode === 'rezago';
    }

    public function getIsTodosProperty()
    {
        return $this->mode === 'todos';
    }

    public function getCanReturnToVentanillaProperty()
    {
        return $this->isInventory || $this->isRezago;
    }

    public function searchPaquetes()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('create', 'almacen'));

        $this->resetForm();
        $this->editingId = null;
        $userCity = trim((string) optional(auth()->user())->ciudad);
        if ($userCity !== '') {
            $this->cuidad = $userCity;
        }
        if ($this->isAlmacen) {
            $estadoVentanillaId = $this->getEstadoIdByNombre(self::ESTADO_VENTANILLA);
            if ($estadoVentanillaId) {
                $this->fk_estado = (string) $estadoVentanillaId;
            }
        }
        $this->dispatch('openPaqueteCertiModal');
    }

    public function openEditModal($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

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
        $permission = $this->editingId
            ? $this->modeFeaturePermission('edit')
            : $this->modeFeaturePermission('create', 'almacen');

        $this->authorizePermission($permission);

        $this->validate($this->rules());

        if ($this->editingId) {
            $paquete = PaqueteCertiModel::findOrFail($this->editingId);
            $paquete->update($this->payload());
            $this->registrarEventoCerti((string) $paquete->codigo, self::EVENTO_ID_CORRECCION_DATOS);
            session()->flash('success', 'Paquete certificado actualizado correctamente.');
        } else {
            $payload = $this->payload();
            if (empty($payload['cuidad'])) {
                $userCity = trim((string) optional(auth()->user())->ciudad);
                if ($userCity !== '') {
                    $payload['cuidad'] = $this->upper($userCity);
                }
            }
            if ($this->isAlmacen) {
                $estadoVentanillaId = $this->getEstadoIdByNombre(self::ESTADO_VENTANILLA);
                if (!$estadoVentanillaId) {
                    session()->flash('success', 'No existe el estado VENTANILLA en la tabla estados.');
                    return;
                }
                $payload['fk_estado'] = $estadoVentanillaId;
            }
            $paquete = PaqueteCertiModel::create($payload);
            $this->registrarEventoCerti((string) $paquete->codigo, self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE);
            session()->flash('success', 'Paquete certificado creado correctamente.');
        }

        $this->dispatch('closePaqueteCertiModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('delete'));

        $paquete = PaqueteCertiModel::findOrFail($id);
        $codigo = (string) $paquete->codigo;
        $paquete->delete();
        $this->registrarEventoCerti($codigo, self::EVENTO_ID_PAQUETE_MARCADO_ELIMINADO);
        session()->flash('success', 'Paquete certificado eliminado correctamente.');
    }

    public function openReencaminarModal($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

        $paquete = PaqueteCertiModel::findOrFail($id);
        $this->reencaminarId = $paquete->id;
        $this->reencaminarCuidad = $paquete->cuidad;
        $this->dispatch('openReencaminarModal');
    }

    public function saveReencaminar()
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

        $this->validate([
            'reencaminarId' => 'required|integer|exists:paquetes_certi,id',
            'reencaminarCuidad' => 'required|string|max:255',
        ]);

        $paquete = PaqueteCertiModel::findOrFail($this->reencaminarId);
        $paquete->update([
            'cuidad' => $this->upper($this->reencaminarCuidad),
        ]);
        $this->registrarEventoCerti((string) $paquete->codigo, self::EVENTO_ID_CORRECCION_DATOS);

        $this->reset(['reencaminarId', 'reencaminarCuidad']);
        $this->dispatch('closeReencaminarModal');
        session()->flash('success', 'Paquete reencaminado correctamente.');
    }

    public function marcarInventario($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('edit', 'almacen'));

        $estadoEntregadoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        if (!$estadoEntregadoId) {
            session()->flash('success', 'No existe el estado ENTREGADO en la tabla estados.');
            return;
        }

        $paquete = PaqueteCertiModel::findOrFail($id);
        $paquete->update([
            'fk_estado' => $estadoEntregadoId,
        ]);
        $this->registrarEventoCerti((string) $paquete->codigo, self::EVENTO_ID_PAQUETE_PROCESADO_CLASIFICACION);
        session()->flash('success', 'Paquete enviado a ENTREGADO.');
    }

    public function bajaMasiva()
    {
        $this->authorizePermission($this->modeFeaturePermission('dropoff', 'almacen'));

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoEntregadoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        if (!$estadoEntregadoId) {
            session()->flash('success', 'No existe el estado ENTREGADO en la tabla estados.');
            return;
        }

        PaqueteCertiModel::query()
            ->whereIn('id', $ids)
            ->update(['fk_estado' => $estadoEntregadoId]);
        $this->registrarEventoCertiPorIds($ids, self::EVENTO_ID_PAQUETE_PROCESADO_CLASIFICACION);

        $this->selectedPaquetes = [];
        session()->flash('success', 'Paquetes enviados a ENTREGADO correctamente.');

        $this->resetPage();
        $this->dispatch('$refresh');

        $this->dispatch('openBajaPdf', [
            'url' => route('paquetes-certificados.baja-pdf', ['ids' => implode(',', $ids)]),
        ]);
    }

    public function rezagoMasivo()
    {
        $this->authorizePermission($this->modeFeaturePermission('rezago', 'almacen'));

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoRezagoId = $this->getEstadoIdByNombre(self::ESTADO_REZAGO);
        if (!$estadoRezagoId) {
            session()->flash('success', 'No existe el estado REZAGO en la tabla estados.');
            return;
        }

        PaqueteCertiModel::query()
            ->whereIn('id', $ids)
            ->update(['fk_estado' => $estadoRezagoId]);
        $this->registrarEventoCertiPorIds($ids, self::EVENTO_ID_PAQUETE_RETENIDO_PUNTO_ENTREGA);

        $this->selectedPaquetes = [];
        session()->flash('success', 'Paquetes enviados a rezago correctamente.');

        $this->resetPage();
        $this->dispatch('$refresh');

        $this->dispatch('openBajaPdf', [
            'url' => route('paquetes-certificados.rezago-pdf', ['ids' => implode(',', $ids)]),
        ]);
    }

    public function marcarVentanilla($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('assign'));

        $estadoVentanillaId = $this->getEstadoIdByNombre(self::ESTADO_VENTANILLA);
        if (!$estadoVentanillaId) {
            session()->flash('success', 'No existe el estado VENTANILLA en la tabla estados.');
            return;
        }

        $paquete = PaqueteCertiModel::findOrFail($id);
        $paquete->update([
            'fk_estado' => $estadoVentanillaId,
        ]);
        $this->registrarEventoCerti((string) $paquete->codigo, self::EVENTO_ID_PAQUETE_RECIBIDO_OFICINA_ENTREGA);
        session()->flash('success', 'Paquete enviado a ventanilla.');
    }

    public function reimprimirPdf($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('export', 'inventario'));

        $paquete = PaqueteCertiModel::findOrFail($id);

        $this->dispatch('openBajaPdf', [
            'url' => route('paquetes-certificados.baja-pdf', ['ids' => $paquete->id]),
        ]);
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
        $ventanillaNombre = '';
        if (!empty($this->fk_ventanilla)) {
            $ventanillaNombre = (string) optional(
                VentanillaModel::find($this->fk_ventanilla)
            )->nombre_ventanilla;
        }

        return [
            'codigo' => $this->upper($this->codigo),
            'destinatario' => $this->upper($this->destinatario),
            'telefono' => $this->telefono,
            'cuidad' => $this->upper($this->cuidad),
            'zona' => $this->upper($this->zona),
            'ventanilla' => $this->upper($ventanillaNombre),
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

    protected function getEstadoIdByNombre(string $nombre): ?int
    {
        $id = EstadoModel::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($nombre))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function registrarEventoCertiPorIds(array $ids, int $eventoId): void
    {
        $ids = collect($ids)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        $codigos = PaqueteCertiModel::query()
            ->whereIn('id', $ids)
            ->pluck('codigo')
            ->filter(fn ($codigo) => trim((string) $codigo) !== '')
            ->values()
            ->all();

        $this->registrarEventosCerti($codigos, $eventoId);
    }

    protected function registrarEventoCerti(string $codigo, int $eventoId): void
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return;
        }

        $this->registrarEventosCerti([$codigo], $eventoId);
    }

    protected function registrarEventosCerti(iterable $codigos, int $eventoId): void
    {
        $userId = (int) optional(Auth::user())->id;

        if ($eventoId <= 0 || $userId <= 0) {
            return;
        }

        $now = now();
        $rows = collect($codigos)
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter()
            ->unique()
            ->map(function (string $codigo) use ($eventoId, $userId, $now) {
                return [
                    'codigo' => $codigo,
                    'evento_id' => $eventoId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if (empty($rows)) {
            return;
        }

        DB::table('eventos_certi')->insert($rows);
    }

    public function render()
    {
        $q = trim($this->searchQuery);
        $userCity = trim((string) optional(auth()->user())->ciudad);
        $estadoEntregadoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        $estadoRezagoId = $this->getEstadoIdByNombre(self::ESTADO_REZAGO);
        $estadoVentanillaId = $this->getEstadoIdByNombre(self::ESTADO_VENTANILLA);

        $paquetes = PaqueteCertiModel::query()
            ->with(['estado', 'ventanillaRef'])
            ->when($userCity !== '', function ($query) use ($userCity) {
                $query->whereRaw('TRIM(UPPER(cuidad)) = TRIM(UPPER(?))', [$userCity]);
            })
            ->when($userCity === '', function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($this->isInventory, function ($query) use ($estadoEntregadoId) {
                if ($estadoEntregadoId) {
                    $query->where('fk_estado', $estadoEntregadoId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->when($this->isRezago, function ($query) use ($estadoRezagoId) {
                if ($estadoRezagoId) {
                    $query->where('fk_estado', $estadoRezagoId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->when($this->isAlmacen, function ($query) use ($estadoVentanillaId) {
                if ($estadoVentanillaId) {
                    $query->where('fk_estado', $estadoVentanillaId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
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
            'canCertiCreate' => $this->userCan($this->modeFeaturePermission('create')),
            'canCertiEdit' => $this->userCan($this->modeFeaturePermission('edit')),
            'canCertiDelete' => $this->userCan($this->modeFeaturePermission('delete')),
            'canCertiDropoff' => $this->userCan($this->modeFeaturePermission('dropoff')),
            'canCertiRezago' => $this->userCan($this->modeFeaturePermission('rezago')),
            'canCertiAssign' => $this->userCan($this->modeFeaturePermission('assign')),
            'canCertiExport' => $this->userCan($this->modeFeaturePermission('export')),
        ]);
    }

    private function modeFeaturePermission(string $action, ?string $mode = null): string
    {
        $routePermission = self::MODE_ROUTE_PERMISSIONS[$mode ?? $this->mode] ?? self::MODE_ROUTE_PERMISSIONS['almacen'];

        return 'feature.'.$routePermission.'.'.$action;
    }

    private function userCan(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can($permission);
    }

    private function authorizePermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }
}
