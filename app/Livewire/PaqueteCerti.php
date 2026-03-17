<?php

namespace App\Livewire;

use App\Models\Estado as EstadoModel;
use App\Models\PaqueteCerti as PaqueteCertiModel;
use App\Models\Ventanilla as VentanillaModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PaqueteCerti extends Component
{
    use WithPagination;

    private const ROLE_VENTANILLA_MAP = [
        'auxiliar_urbano_dnd' => ['DND', 'CASILLA'],
        'auxiliar_urbano' => ['DD'],
        'auxiliar_7' => ['DD'],
        'auxiliar_urbano_casilla' => ['DND', 'CASILLA'],
        'encargado_urbano' => ['DD', 'DND'],
    ];

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
    public $reencaminarCuidad = '';
    public $previewReencaminarIds = [];

    public $codigo = '';
    public $cod_especial = '';
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

    public function updatedSearch($value)
    {
        $this->searchQuery = $value;
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

        $paquete = $this->findAuthorizedPaqueteOrFail($id);

        $this->editingId = $paquete->id;
        $this->codigo = $paquete->codigo;
        $this->cod_especial = $paquete->cod_especial ?? '';
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

    public function updatedDestinatario($value)
    {
        $this->destinatario = $this->upper($value);
        $this->autocompletarDatosDestinatario();
    }

    public function updatedTelefono($value)
    {
        $this->telefono = trim((string) $value);
        $this->autocompletarDatosDestinatario();
    }

    public function save()
    {
        $permission = $this->editingId
            ? $this->modeFeaturePermission('edit')
            : $this->modeFeaturePermission('create', 'almacen');

        $this->authorizePermission($permission);

        $this->validate($this->rules());

        if (! $this->selectedVentanillaIsAllowed()) {
            $this->addError('fk_ventanilla', 'No puedes asignar esa ventanilla.');
            return;
        }

        if ($this->editingId) {
            $paquete = $this->findAuthorizedPaqueteOrFail($this->editingId);
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

        $paquete = $this->findAuthorizedPaqueteOrFail($id);
        $codigo = (string) $paquete->codigo;
        $paquete->delete();
        $this->registrarEventoCerti($codigo, self::EVENTO_ID_PAQUETE_MARCADO_ELIMINADO);
        session()->flash('success', 'Paquete certificado eliminado correctamente.');
    }

    public function openReencaminarModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('edit', 'almacen'));

        if (! $this->isAlmacen) {
            return;
        }

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete para reencaminar.');
            return;
        }

        $this->previewReencaminarIds = $ids;
        $this->reencaminarCuidad = '';
        $this->resetValidation();
        $this->dispatch('openReencaminarModal');
    }

    public function saveReencaminar()
    {
        $this->authorizePermission($this->modeFeaturePermission('edit', 'almacen'));

        if (! $this->isAlmacen) {
            return;
        }

        $this->validate([
            'reencaminarCuidad' => 'required|string|max:255',
        ]);

        $ids = collect($this->previewReencaminarIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'No hay paquetes validos para reencaminar.');
            return;
        }

        $ciudadDestino = $this->upper($this->reencaminarCuidad);

        $paquetesReporte = PaqueteCertiModel::query()
            ->with(['estado', 'ventanillaRef'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->map(function (PaqueteCertiModel $paquete) use ($ciudadDestino) {
                $paquete->ciudad_origen = $paquete->cuidad;
                $paquete->ciudad_destino = $ciudadDestino;

                return $paquete;
            });

        PaqueteCertiModel::query()
            ->whereIn('id', $ids)
            ->update([
                'cuidad' => $ciudadDestino,
            ]);
        $this->registrarEventoCertiPorIds($ids, self::EVENTO_ID_CORRECCION_DATOS);

        $cantidad = count($ids);

        $this->selectedPaquetes = [];
        $this->reset(['reencaminarCuidad', 'previewReencaminarIds']);
        $this->dispatch('closeReencaminarModal');
        session()->flash('success', $cantidad === 1
            ? 'Paquete reencaminado correctamente.'
            : 'Paquetes reencaminados correctamente.');
        $this->resetPage();

        $pdf = Pdf::loadView('paquetes_certi.reporte_reencaminar', [
            'packages' => $paquetesReporte,
            'generatedAt' => now(),
            'generatedBy' => (string) optional(auth()->user())->name,
        ])->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-reencaminar-certificados-' . now()->format('Ymd-His') . '.pdf');
    }

    public function marcarInventario($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('edit', 'almacen'));

        $estadoEntregadoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        if (!$estadoEntregadoId) {
            session()->flash('success', 'No existe el estado ENTREGADO en la tabla estados.');
            return;
        }

        $paquete = $this->findAuthorizedPaqueteOrFail($id);
        DB::transaction(function () use ($paquete, $estadoEntregadoId) {
            $paquete->refresh();
            if ($this->emptyToNull($paquete->cod_especial) === null) {
                $paquete->cod_especial = $this->nextCertiCodEspecial();
            }
            $paquete->fk_estado = $estadoEntregadoId;
            $paquete->save();
        });
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

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoEntregadoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        if (!$estadoEntregadoId) {
            session()->flash('success', 'No existe el estado ENTREGADO en la tabla estados.');
            return;
        }

        DB::transaction(function () use ($ids, $estadoEntregadoId) {
            $codEspecial = $this->nextCertiCodEspecial();

            PaqueteCertiModel::query()
                ->whereIn('id', $ids)
                ->where(function ($query) {
                    $query->whereNull('cod_especial')
                        ->orWhereRaw("trim(cod_especial) = ''");
                })
                ->update(['cod_especial' => $codEspecial]);

            PaqueteCertiModel::query()
                ->whereIn('id', $ids)
                ->update(['fk_estado' => $estadoEntregadoId]);
        });
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

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoRezagoId = $this->getEstadoIdByNombre(self::ESTADO_REZAGO);
        if (!$estadoRezagoId) {
            session()->flash('success', 'No existe el estado REZAGO en la tabla estados.');
            return;
        }

        DB::transaction(function () use ($ids, $estadoRezagoId) {
            $codEspecial = $this->nextCertiCodEspecial();

            PaqueteCertiModel::query()
                ->whereIn('id', $ids)
                ->where(function ($query) {
                    $query->whereNull('cod_especial')
                        ->orWhereRaw("trim(cod_especial) = ''");
                })
                ->update(['cod_especial' => $codEspecial]);

            PaqueteCertiModel::query()
                ->whereIn('id', $ids)
                ->update(['fk_estado' => $estadoRezagoId]);
        });
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

        $paquete = $this->findAuthorizedPaqueteOrFail($id);
        $paquete->update([
            'fk_estado' => $estadoVentanillaId,
        ]);
        $this->registrarEventoCerti((string) $paquete->codigo, self::EVENTO_ID_PAQUETE_RECIBIDO_OFICINA_ENTREGA);
        session()->flash('success', 'Paquete enviado a ventanilla.');
    }

    public function reimprimirPdf($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('export', 'inventario'));

        $paquete = $this->findAuthorizedPaqueteOrFail($id);

        $this->dispatch('openBajaPdf', [
            'url' => route('paquetes-certificados.baja-pdf', ['ids' => $paquete->id]),
        ]);
    }

    public function resetForm()
    {
        $this->reset([
            'codigo',
            'cod_especial',
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
            'cod_especial' => 'nullable|string|max:255',
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

    protected function autocompletarDatosDestinatario(): void
    {
        if ($this->editingId) {
            return;
        }

        $destinatario = $this->upper($this->destinatario);
        $telefono = trim((string) $this->telefono);

        if ($destinatario === '' && $telefono === '') {
            return;
        }

        $registro = PaqueteCertiModel::query()
            ->when($this->upper($this->cuidad) !== '', function ($query) {
                $query->whereRaw('trim(upper(cuidad)) = trim(upper(?))', [$this->upper($this->cuidad)]);
            })
            ->when($destinatario !== '' && $telefono !== '', function ($query) use ($destinatario, $telefono) {
                $query->whereRaw('trim(upper(destinatario)) = trim(upper(?))', [$destinatario])
                    ->whereRaw('trim(cast(telefono as text)) = trim(?)', [$telefono]);
            }, function ($query) use ($destinatario, $telefono) {
                $query->where(function ($subQuery) use ($destinatario, $telefono) {
                    if ($destinatario !== '') {
                        $subQuery->orWhereRaw('trim(upper(destinatario)) = trim(upper(?))', [$destinatario]);
                    }

                    if ($telefono !== '') {
                        $subQuery->orWhereRaw('trim(cast(telefono as text)) = trim(?)', [$telefono]);
                    }
                });
            })
            ->orderByDesc('id')
            ->first(['destinatario', 'telefono', 'zona']);

        if (!$registro) {
            return;
        }

        $this->destinatario = $this->upper($registro->destinatario ?: $this->destinatario);
        $this->telefono = trim((string) ($registro->telefono ?: $this->telefono));
        $this->zona = $this->upper($registro->zona ?: $this->zona);
    }

    protected function payload()
    {
        $ventanillaNombre = '';
        if (!empty($this->fk_ventanilla)) {
            $ventanillaNombre = (string) optional(
                $this->ventanillasQuery()->find($this->fk_ventanilla)
            )->nombre_ventanilla;
        }

        return [
            'codigo' => $this->upper($this->codigo),
            'cod_especial' => $this->emptyToNull($this->cod_especial),
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

    protected function emptyToNull($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $this->upper($text);
    }

    protected function nextCertiCodEspecial(): string
    {
        return 'C' . str_pad((string) $this->nextCertiCorrelative(), 5, '0', STR_PAD_LEFT);
    }

    protected function nextCertiCorrelative(): int
    {
        $lastCode = PaqueteCertiModel::query()
            ->whereRaw("cod_especial ~ '^C[0-9]{5}$'")
            ->lockForUpdate()
            ->orderByDesc('cod_especial')
            ->value('cod_especial');

        if (!$lastCode) {
            return 1;
        }

        $number = (int) substr((string) $lastCode, 1, 5);

        return $number > 0 ? $number + 1 : 1;
    }

    protected function getEstadoIdByNombre(string $nombre): ?int
    {
        $id = EstadoModel::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($nombre))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function selectedVentanillaIsAllowed(): bool
    {
        if (empty($this->fk_ventanilla)) {
            return false;
        }

        return $this->ventanillasQuery()
            ->whereKey($this->fk_ventanilla)
            ->exists();
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

        $paquetes = $this->authorizedPaquetesQuery()
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

        $previewReencaminarPaquetes = collect();
        if (! empty($this->previewReencaminarIds)) {
            $previewReencaminarPaquetes = $this->authorizedPaquetesQuery()
                ->with(['estado', 'ventanillaRef'])
                ->whereIn('id', $this->previewReencaminarIds)
                ->orderBy('id')
                ->get();
        }

        return view('livewire.paquete-certi', [
            'paquetes' => $paquetes,
            'previewReencaminarPaquetes' => $previewReencaminarPaquetes,
            'estados' => EstadoModel::orderBy('nombre_estado')->get(),
            'ventanillas' => $this->ventanillasQuery()->orderBy('nombre_ventanilla')->get(),
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

    private function authorizedPaquetesQuery()
    {
        return $this->applyRoleVentanillaScope(PaqueteCertiModel::query());
    }

    private function findAuthorizedPaqueteOrFail(int $id): PaqueteCertiModel
    {
        return $this->authorizedPaquetesQuery()->findOrFail($id);
    }

    private function filterAuthorizedIds(array $ids): array
    {
        $ids = collect($ids)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        return $this->authorizedPaquetesQuery()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function ventanillasQuery()
    {
        $query = VentanillaModel::query();
        $ventanillas = $this->restrictedVentanillaNames();

        if ($ventanillas !== null) {
            $query->where(function ($restrictedQuery) use ($ventanillas) {
                foreach ($ventanillas as $ventanilla) {
                    $restrictedQuery->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', [$ventanilla]);
                }
            });
        }

        return $query;
    }

    private function applyRoleVentanillaScope($query)
    {
        $ventanillas = $this->restrictedVentanillaNames();

        if ($ventanillas === null) {
            return $query;
        }

        return $query->where(function ($restrictedQuery) use ($ventanillas) {
            $restrictedQuery->where(function ($ventanillaColumnQuery) use ($ventanillas) {
                foreach ($ventanillas as $ventanilla) {
                    $ventanillaColumnQuery->orWhereRaw('trim(upper(ventanilla)) = ?', [$ventanilla]);
                }
            })->orWhereHas('ventanillaRef', function ($ventanillaQuery) use ($ventanillas) {
                $ventanillaQuery->where(function ($restrictedVentanillaQuery) use ($ventanillas) {
                    foreach ($ventanillas as $ventanilla) {
                        $restrictedVentanillaQuery->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', [$ventanilla]);
                    }
                });
            });
        });
    }

    private function restrictedVentanillaNames(): ?array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return null;
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');
        if ($superAdminRole !== '' && $user->hasRole($superAdminRole)) {
            return null;
        }

        // Roles de Ventanilla Única
        if ($user->hasRole('encargado_unica') || $user->hasRole('auxiliar_unica')) {
            return ['UNICA'];
        }

        foreach (self::ROLE_VENTANILLA_MAP as $role => $ventanillas) {
            if ($user->hasRole($role)) {
                return $ventanillas;
            }
        }

        return null;
    }
}
