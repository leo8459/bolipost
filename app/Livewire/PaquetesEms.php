<?php

namespace App\Livewire;

use App\Models\Destino;
use App\Models\Estado;
use App\Models\Origen;
use App\Models\PaqueteEms;
use App\Models\PaqueteEmsFormulario;
use App\Models\RemitenteEms;
use App\Models\Servicio;
use App\Models\Tarifario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class PaquetesEms extends Component
{
    use WithPagination;

    public $mode = 'admision';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $selectedPaquetes = [];
    public $almacenEstadoFiltro = 'TODOS';
    public $regionalDestino = '';
    public $regionalTransportMode = 'TERRESTRE';
    public $regionalTransportNumber = '';
    public $showCn33Reprint = false;
    public $cn33Despacho = '';

    public $ciudades = [
        'LA PAZ',
        'SANTA CRUZ',
        'PANDO',
        'BENI',
        'TARIJA',
        'CHUQUISACA',
        'ORURO',
        'COCHABAMBA',
        'POTOSI',
    ];

    public $origen = '';
    public $tipo_correspondencia = '';
    public $servicio_especial = '';
    public $contenido = '';
    public $cantidad = '';
    public $peso = '';
    public $codigo = '';
    public $auto_codigo = true;
    public $precio = '';
    public $precio_confirm = null;
    public $nombre_remitente = '';
    public $nombre_envia = '';
    public $carnet = '';
    public $telefono_remitente = '';
    public $nombre_destinatario = '';
    public $telefono_destinatario = '';
    public $ciudad = '';
    public $servicio_id = '';
    public $tarifario_id = '';
    public $destino_id = '';
    public $is_ems = false;
    public $user_origen_id = null;
    public $estado_id = null;
    public $remitenteSugerencias = [];

    protected $paginationTheme = 'bootstrap';

    public $servicios = [];
    public $destinos = [];

    public function mount($mode = 'admision')
    {
        $allowedModes = ['admision', 'almacen_ems', 'transito_ems'];
        $this->mode = in_array($mode, $allowedModes, true) ? $mode : 'admision';
        if ($this->isAlmacenEms) {
            $this->almacenEstadoFiltro = 'TODOS';
        }
        $this->setOrigenFromUser();
        if ($this->isAdmision || $this->isAlmacenEms) {
            $this->servicios = Servicio::orderBy('nombre_servicio')->get();
            $this->destinos = Destino::orderBy('nombre_destino')->get();
            $this->setUserOrigenId();
        }
    }

    public function getIsAdmisionProperty()
    {
        return $this->mode === 'admision';
    }

    public function getIsAlmacenEmsProperty()
    {
        return $this->mode === 'almacen_ems';
    }

    public function getIsTransitoEmsProperty()
    {
        return $this->mode === 'transito_ems';
    }

    public function getRegionalEstadoLabelProperty(): string
    {
        return $this->resolveRegionalEstado()['nombre'] ?? 'ENVIADOS';
    }

    public function getCanSelectProperty()
    {
        return $this->isAdmision || $this->isAlmacenEms || $this->isTransitoEms;
    }

    public function setAlmacenFiltro($filtro)
    {
        if (!in_array($filtro, ['TODOS', 'ALMACEN', 'RECIBIDO'], true)) {
            return;
        }

        $this->almacenEstadoFiltro = $filtro;
        $this->selectedPaquetes = [];
        $this->resetPage();
    }

    public function searchPaquetes($seleccionarPorCodigo = false)
    {
        $this->searchQuery = $this->search;
        $this->resetPage();

        if (!$seleccionarPorCodigo) {
            return;
        }

        $codigo = trim((string) $this->search);
        if ($codigo === '') {
            return;
        }

        $paquete = $this->basePaquetesQuery()
            ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
            ->first(['id']);

        if (!$paquete) {
            session()->flash('error', 'No se encontro un paquete con ese codigo.');
            $this->search = '';
            $this->searchQuery = '';
            return;
        }

        $actuales = collect($this->selectedPaquetes)
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->selectedPaquetes = collect($actuales)
            ->push((string) $paquete->id)
            ->unique()
            ->values()
            ->all();

        session()->flash('success', 'Paquete seleccionado automaticamente por codigo.');
        $this->search = '';
        $this->searchQuery = '';
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        if (empty($this->servicios)) {
            $this->servicios = Servicio::orderBy('nombre_servicio')->get();
        }
        if (empty($this->destinos)) {
            $this->destinos = Destino::orderBy('nombre_destino')->get();
        }
        $this->setOrigenFromUser();
        $this->setUserOrigenId();
        $this->editingId = null;
        $this->is_ems = false;
        $this->auto_codigo = true;
        if ($this->isAlmacenEms) {
            $this->tipo_correspondencia = 'OFICIAL';
        }
        $this->dispatch('openPaqueteModal');
    }

    public function openRegionalModal()
    {
        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('error', 'Selecciona al menos un paquete.');
            return;
        }

        $this->regionalDestino = '';
        $this->regionalTransportMode = 'TERRESTRE';
        $this->regionalTransportNumber = '';
        $this->dispatch('openRegionalModal');
    }

    public function toggleCn33Reprint()
    {
        if (!$this->isAlmacenEms) {
            return;
        }

        $this->showCn33Reprint = !$this->showCn33Reprint;
        if (!$this->showCn33Reprint) {
            $this->cn33Despacho = '';
        }
    }

    public function reimprimirCn33()
    {
        if (!$this->isAlmacenEms) {
            return;
        }

        $despacho = strtoupper(trim((string) $this->cn33Despacho));
        if ($despacho === '') {
            session()->flash('error', 'Ingresa el despacho (cod_especial) para reimprimir CN-33.');
            return;
        }

        $paquetes = PaqueteEms::query()
            ->whereRaw('trim(upper(cod_especial)) = trim(upper(?))', [$despacho])
            ->orderBy('id')
            ->get([
                'id',
                'codigo',
                'cod_especial',
                'origen',
                'ciudad',
                'cantidad',
                'peso',
                'nombre_remitente',
                'user_id',
                'created_at',
                'updated_at',
            ]);

        if ($paquetes->isEmpty()) {
            session()->flash('error', 'No se encontraron paquetes para el despacho ' . $despacho . '.');
            return;
        }

        $generatedAt = $paquetes->first()->updated_at ?: now();
        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);
        $destinationCity = trim((string) optional($paquetes->first())->ciudad);

        $pdf = Pdf::loadView('paquetes_ems.reporte-regional', [
            'paquetes' => $paquetes,
            'generatedAt' => $generatedAt,
            'currentManifiesto' => $despacho,
            'loggedInUserCity' => $loggedInUserCity !== '' ? $loggedInUserCity : 'N/A',
            'destinationCity' => $destinationCity !== '' ? $destinationCity : 'N/A',
            'selectedTransport' => 'N/A',
            'numeroVuelo' => '-',
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
        ])->setPaper('a4', 'portrait');

        session()->flash('success', 'Reimpresion CN-33 generada para despacho ' . $despacho . '.');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'cn33-' . $despacho . '-reimpresion.pdf');
    }

    public function openEditModal($id)
    {
        $paquete = PaqueteEms::query()->with('formulario')->findOrFail($id);
        $formulario = $paquete->formulario;

        if (empty($this->servicios)) {
            $this->servicios = Servicio::orderBy('nombre_servicio')->get();
        }
        if (empty($this->destinos)) {
            $this->destinos = Destino::orderBy('nombre_destino')->get();
        }

        $this->editingId = $paquete->id;
        $this->origen = $formulario->origen ?? $paquete->origen;
        $this->tipo_correspondencia = $formulario->tipo_correspondencia ?? $paquete->tipo_correspondencia;
        $this->servicio_especial = $formulario->servicio_especial ?? $paquete->servicio_especial;
        $this->contenido = $formulario->contenido ?? $paquete->contenido;
        $this->cantidad = $formulario->cantidad ?? $paquete->cantidad;
        $this->peso = $formulario->peso ?? $paquete->peso;
        $this->codigo = $formulario->codigo ?? $paquete->codigo;
        $this->precio = $formulario->precio ?? $paquete->precio;
        $this->nombre_remitente = $formulario->nombre_remitente ?? $paquete->nombre_remitente;
        $this->nombre_envia = $formulario->nombre_envia ?? $paquete->nombre_envia;
        $this->carnet = $formulario->carnet ?? $paquete->carnet;
        $this->telefono_remitente = $formulario->telefono_remitente ?? $paquete->telefono_remitente;
        $this->nombre_destinatario = $formulario->nombre_destinatario ?? $paquete->nombre_destinatario;
        $this->telefono_destinatario = $formulario->telefono_destinatario ?? $paquete->telefono_destinatario;
        $this->ciudad = $formulario->ciudad ?? $paquete->ciudad;
        $this->tarifario_id = $formulario->tarifario_id ?? $paquete->tarifario_id;
        $this->servicio_id = optional($paquete->tarifario)->servicio_id;
        $this->destino_id = optional($paquete->tarifario)->destino_id;
        $this->auto_codigo = false;

        $this->refreshEmsState();
        $this->setUserOrigenId();
        $this->applyTarifarioMatch();

        $this->dispatch('openPaqueteModal');
    }

    public function save()
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $this->setOrigenFromUser();
        if ($this->isAlmacenEms) {
            $this->tipo_correspondencia = 'OFICIAL';
        }

        if (!$this->isCertificadoShipment()) {
            $this->applyTarifarioMatch();
            if (!$this->tarifario_id) {
                $this->addError('peso', 'No existe tarifario para este servicio, destino y peso.');
                return;
            }
        } else {
            $this->tarifario_id = null;
            $this->precio = null;
        }

        $this->precio_confirm = $this->precio;
        if (
            $this->servicio_especial === 'IDA Y VUELTA' &&
            $this->precio !== '' &&
            $this->precio !== null
        ) {
            $this->precio_confirm = (string) ((float) $this->precio * 2);
        }

        if ($this->auto_codigo) {
            $this->codigo = $this->generateCodigo();
        }

        $this->validate($this->rules());
        $this->dispatch('openPaqueteConfirm');
    }

    public function saveConfirmed()
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        if (!$this->isCertificadoShipment()) {
            $this->applyTarifarioMatch();
        } else {
            $this->tarifario_id = null;
            $this->precio = null;
        }
        if ($this->precio_confirm !== null) {
            $this->precio = $this->precio_confirm;
        }

        if ($this->editingId) {
            $paquete = PaqueteEms::findOrFail($this->editingId);
            $paquete->update($this->payload());
            $this->syncFormularioData($paquete);
            $this->saveRemitenteData();
            session()->flash('success', 'Paquete actualizado correctamente.');
        } else {
            $this->setOrigenFromUser();
            $this->setEstadoByMode();
            if (!$this->estado_id) {
                session()->flash('error', 'No se encontro el estado requerido para crear el paquete.');
                return;
            }
            $paquete = PaqueteEms::create($this->payload($user->id));
            $this->syncFormularioData($paquete);
            $this->saveRemitenteData();
            session()->flash('success', 'Paquete creado correctamente.');
            $this->dispatch('closePaqueteConfirm');
            $this->dispatch('closePaqueteModal');
            $this->resetForm();
            return $this->redirect(route('paquetes-ems.boleta', $paquete->id), navigate: false);
        }

        $this->dispatch('closePaqueteConfirm');
        $this->dispatch('closePaqueteModal');
        $this->resetForm();
    }

    public function mandarSeleccionadosGeneradosHoy()
    {
        return $this->mandarSeleccionadosAlmacenEms(true);
    }

    public function mandarSeleccionadosSinFiltroFecha()
    {
        return $this->mandarSeleccionadosAlmacenEms(false);
    }

    public function mandarSeleccionadosRegional()
    {
        if (trim((string) $this->regionalDestino) === '') {
            session()->flash('error', 'Selecciona la ciudad de destino para regional.');
            return;
        }

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('error', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoRegional = $this->resolveRegionalEstado();
        $estadoRegionalId = $estadoRegional['id'] ?? null;
        $estadoRegionalNombre = $estadoRegional['nombre'] ?? null;

        if (!$estadoRegionalId || !$estadoRegionalNombre) {
            session()->flash('error', 'No existe el estado ENVIADOS ni TRANSITO en la tabla estados.');
            return;
        }

        $generatedAt = now();
        $updated = 0;
        $paquetes = collect();

        $manifiesto = '';

        DB::transaction(function () use ($ids, $estadoRegionalId, &$manifiesto, &$updated, &$paquetes) {
            $paquetes = PaqueteEms::query()
                ->whereIn('id', $ids)
                ->with(['user:id,name'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'codigo',
                    'cod_especial',
                    'origen',
                    'ciudad',
                    'cantidad',
                    'peso',
                    'nombre_remitente',
                    'user_id',
                    'created_at',
                ]);

            if ($paquetes->isEmpty()) {
                return;
            }

            $correlative = $this->nextSpecialCodeCorrelative();
            $manifiesto = 'E' . str_pad((string) $correlative, 5, '0', STR_PAD_LEFT);

            foreach ($paquetes as $paquete) {
                $paquete->cod_especial = $manifiesto;
                $paquete->estado_id = $estadoRegionalId;
                $paquete->ciudad = $this->regionalDestino;
                $paquete->save();
                $updated++;
            }
        });

        if ($paquetes->isEmpty()) {
            session()->flash('error', 'No hay paquetes seleccionados para enviar.');
            return;
        }

        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);
        $pdf = Pdf::loadView('paquetes_ems.reporte-regional', [
            'paquetes' => $paquetes,
            'generatedAt' => $generatedAt,
            'currentManifiesto' => $manifiesto,
            'loggedInUserCity' => $loggedInUserCity !== '' ? $loggedInUserCity : 'N/A',
            'destinationCity' => $this->regionalDestino,
            'selectedTransport' => $this->regionalTransportMode,
            'numeroVuelo' => $this->regionalTransportNumber,
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
        ])->setPaper('a4', 'portrait');

        $this->selectedPaquetes = [];
        $this->regionalDestino = '';
        $this->dispatch('closeRegionalModal');

        session()->flash('success', $updated . ' paquete(s) enviado(s) a regional (' . $estadoRegionalNombre . ').');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'manifiesto-regional-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function recibirSeleccionadosRegional()
    {
        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('error', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoRecibido = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['RECIBIDO'])
            ->value('id');

        if (!$estadoRecibido) {
            session()->flash('error', 'No existe el estado RECIBIDO en la tabla estados.');
            return;
        }

        $updated = PaqueteEms::query()
            ->whereIn('id', $ids)
            ->update(['estado_id' => $estadoRecibido]);

        $this->selectedPaquetes = [];
        session()->flash('success', $updated . ' paquete(s) recibido(s) en RECIBIDO.');
    }

    protected function mandarSeleccionadosAlmacenEms(bool $soloHoy)
    {
        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('error', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoAlmacenEms = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ALMACEN'])
            ->value('id');

        if (!$estadoAlmacenEms) {
            session()->flash('error', 'No existe el estado ALMACEN en la tabla estados.');
            return;
        }

        $paquetes = PaqueteEms::query()
            ->whereIn('id', $ids)
            ->when($soloHoy, function ($query) {
                $query->whereDate('created_at', now()->toDateString());
            })
            ->with(['user:id,name'])
            ->orderBy('id')
            ->get(['id', 'codigo', 'user_id', 'created_at']);

        if ($paquetes->isEmpty()) {
            session()->flash(
                'error',
                $soloHoy
                    ? 'No hay paquetes seleccionados que se hayan generado hoy.'
                    : 'No hay paquetes seleccionados para enviar.'
            );
            return;
        }

        PaqueteEms::query()
            ->whereIn('id', $paquetes->pluck('id')->all())
            ->update(['estado_id' => $estadoAlmacenEms]);

        $generatedAt = now();
        $loggedUserName = trim((string) optional(Auth::user())->name);
        $pdf = Pdf::loadView('paquetes_ems.reporte-envio', [
            'paquetes' => $paquetes,
            'generatedAt' => $generatedAt,
            'filtro' => $soloHoy ? 'GENERADOS HOY' : 'SELECCIONADOS',
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
        ])->setPaper('a4', 'portrait');

        $this->selectedPaquetes = [];

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-envio-ems-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function delete($id)
    {
        $paquete = PaqueteEms::findOrFail($id);
        $paquete->delete();
        session()->flash('success', 'Paquete eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'origen',
            'tipo_correspondencia',
            'servicio_especial',
            'contenido',
            'cantidad',
            'peso',
            'codigo',
            'auto_codigo',
            'precio',
            'precio_confirm',
            'nombre_remitente',
            'nombre_envia',
            'carnet',
            'telefono_remitente',
            'nombre_destinatario',
            'telefono_destinatario',
            'ciudad',
            'servicio_id',
            'tarifario_id',
            'destino_id',
            'estado_id',
            'remitenteSugerencias',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        $requiresTarifario = !$this->isCertificadoShipment();

        return [
            'origen' => 'nullable|string|max:255',
            'tipo_correspondencia' => 'required|string|max:255',
            'servicio_especial' => 'nullable|string|max:255',
            'contenido' => 'required|string',
            'cantidad' => 'required|integer|min:1',
            'peso' => 'required|numeric|min:0',
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('paquetes_ems', 'codigo')->ignore($this->editingId),
            ],
            'precio' => $requiresTarifario ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'nombre_remitente' => 'required|string|max:255',
            'nombre_envia' => 'required|string|max:255',
            'carnet' => 'required|string|max:255',
            'telefono_remitente' => 'required|string|max:50',
            'nombre_destinatario' => 'required|string|max:255',
            'telefono_destinatario' => 'required|string|max:50',
            'ciudad' => ['nullable', 'string', 'max:255'],
            'servicio_id' => $requiresTarifario
                ? ['required', 'integer', Rule::exists('servicio', 'id')]
                : ['nullable', 'integer', Rule::exists('servicio', 'id')],
            'destino_id' => $requiresTarifario
                ? ['required', 'integer', Rule::exists('destino', 'id')]
                : ['nullable', 'integer', Rule::exists('destino', 'id')],
        ];
    }

    protected function payload($userId = null)
    {
        $payload = [
            'origen' => $this->origen,
            'tipo_correspondencia' => $this->tipo_correspondencia,
            'servicio_especial' => $this->servicio_especial,
            'contenido' => $this->contenido,
            'cantidad' => $this->cantidad,
            'peso' => $this->peso,
            'codigo' => $this->codigo,
            'precio' => $this->precio === '' ? null : $this->precio,
            'nombre_remitente' => $this->nombre_remitente,
            'nombre_envia' => $this->nombre_envia,
            'carnet' => $this->carnet,
            'telefono_remitente' => $this->telefono_remitente,
            'nombre_destinatario' => $this->nombre_destinatario,
            'telefono_destinatario' => $this->telefono_destinatario,
            'ciudad' => $this->ciudad,
            'tarifario_id' => $this->tarifario_id ?: null,
            'estado_id' => $this->estado_id ?? null,
        ];

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        return $payload;
    }

    public function render()
    {
        $paquetes = $this->basePaquetesQuery()->simplePaginate(10);

        return view('livewire.paquetes-ems', [
            'paquetes' => $paquetes,
        ]);
    }

    protected function basePaquetesQuery(): Builder
    {
        $q = trim($this->searchQuery);
        $columns = [
            'origen',
            'tipo_correspondencia',
            'contenido',
            'cantidad',
            'peso',
            'codigo',
            'precio',
            'nombre_remitente',
            'nombre_envia',
            'carnet',
            'telefono_remitente',
            'nombre_destinatario',
            'telefono_destinatario',
            'ciudad',
            'cod_especial',
        ];

        $estadoIds = [];
        if ($this->isAlmacenEms) {
            $estadoAlmacen = $this->findEstadoId('ALMACEN');
            $estadoRecibido = $this->findEstadoId('RECIBIDO');
            if ($estadoAlmacen) {
                $estadoIds[] = $estadoAlmacen;
            }
            if ($estadoRecibido) {
                $estadoIds[] = $estadoRecibido;
            }
        } elseif ($this->isTransitoEms) {
            $estadoRegionalId = $this->resolveRegionalEstado()['id'] ?? null;
            if ($estadoRegionalId) {
                $estadoIds[] = $estadoRegionalId;
            }
        } else {
            $estadoAdmision = $this->findEstadoId('ADMISIONES');
            if ($estadoAdmision) {
                $estadoIds[] = $estadoAdmision;
            }
        }

        $estadoAlmacenId = null;
        $estadoRecibidoId = null;
        if ($this->isAlmacenEms) {
            $estadoAlmacenId = $this->findEstadoId('ALMACEN');
            $estadoRecibidoId = $this->findEstadoId('RECIBIDO');
        }

        $userCity = trim((string) optional(Auth::user())->ciudad);

        return PaqueteEms::query()
            ->leftJoin('tarifario', 'tarifario.id', '=', 'paquetes_ems.tarifario_id')
            ->leftJoin('servicio', 'servicio.id', '=', 'tarifario.servicio_id')
            ->leftJoin('destino', 'destino.id', '=', 'tarifario.destino_id')
            ->with(['formulario'])
            ->select([
                'paquetes_ems.*',
                DB::raw('servicio.nombre_servicio as servicio_nombre'),
                DB::raw('destino.nombre_destino as destino_nombre'),
            ])
            ->when(!empty($estadoIds), function ($query) use ($estadoIds) {
                $query->whereIn('paquetes_ems.estado_id', $estadoIds);
            })
            ->when(empty($estadoIds), function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($this->isAlmacenEms && $userCity !== '', function ($query) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                $query->where(function ($sub) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                    if ($this->almacenEstadoFiltro === 'ALMACEN' && $estadoAlmacenId) {
                        $sub->where('paquetes_ems.estado_id', $estadoAlmacenId)
                            ->whereRaw('trim(upper(paquetes_ems.origen)) = trim(upper(?))', [$userCity]);
                        return;
                    }

                    if ($this->almacenEstadoFiltro === 'RECIBIDO' && $estadoRecibidoId) {
                        $sub->where('paquetes_ems.estado_id', $estadoRecibidoId)
                            ->whereRaw(
                                'trim(upper(COALESCE(destino.nombre_destino, paquetes_ems.ciudad))) = trim(upper(?))',
                                [$userCity]
                            );
                        return;
                    }

                    $sub->where(function ($q) use ($estadoAlmacenId, $userCity) {
                        if ($estadoAlmacenId) {
                            $q->where('paquetes_ems.estado_id', $estadoAlmacenId)
                                ->whereRaw('trim(upper(paquetes_ems.origen)) = trim(upper(?))', [$userCity]);
                        } else {
                            $q->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q) use ($estadoRecibidoId, $userCity) {
                        if ($estadoRecibidoId) {
                            $q->where('paquetes_ems.estado_id', $estadoRecibidoId)
                                ->whereRaw(
                                    'trim(upper(COALESCE(destino.nombre_destino, paquetes_ems.ciudad))) = trim(upper(?))',
                                    [$userCity]
                                );
                        } else {
                            $q->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->when($userCity === '' && $this->isAlmacenEms, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q, $columns) {
                $query->where(function ($sub) use ($q, $columns) {
                    foreach ($columns as $column) {
                        $sub->orWhere('paquetes_ems.' . $column, 'like', "%{$q}%");
                    }
                    $sub->orWhereHas('formulario', function ($formQuery) use ($q) {
                        $formQuery
                            ->where('tipo_correspondencia', 'like', "%{$q}%")
                            ->orWhere('servicio_especial', 'like', "%{$q}%")
                            ->orWhere('contenido', 'like', "%{$q}%")
                            ->orWhere('cantidad', 'like', "%{$q}%")
                            ->orWhere('peso', 'like', "%{$q}%")
                            ->orWhere('nombre_remitente', 'like', "%{$q}%")
                            ->orWhere('nombre_envia', 'like', "%{$q}%")
                            ->orWhere('carnet', 'like', "%{$q}%")
                            ->orWhere('telefono_remitente', 'like', "%{$q}%")
                            ->orWhere('nombre_destinatario', 'like', "%{$q}%")
                            ->orWhere('telefono_destinatario', 'like', "%{$q}%")
                            ->orWhere('ciudad', 'like', "%{$q}%");
                    });
                    $sub->orWhere('servicio.nombre_servicio', 'like', "%{$q}%");
                    $sub->orWhere('destino.nombre_destino', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('paquetes_ems.id');
    }

    protected function setOrigenFromUser()
    {
        $user = Auth::user();
        if ($user && !empty($user->ciudad)) {
            $this->origen = $user->ciudad;
        }
    }

    protected function setUserOrigenId()
    {
        $this->user_origen_id = null;
        $user = Auth::user();
        if (!$user || empty($user->ciudad)) {
            return;
        }

        $origen = Origen::query()
            ->whereRaw('trim(upper(nombre_origen)) = trim(upper(?))', [$user->ciudad])
            ->first();

        if ($origen) {
            $this->user_origen_id = $origen->id;
        }
    }

    public function updated($name, $value)
    {
        if ($name === 'servicio_id') {
            $this->tarifario_id = '';
            $this->destino_id = '';
            $this->peso = '';
            $this->precio = '';
            $this->refreshEmsState();
            if ($this->auto_codigo) {
                $this->codigo = $this->generateCodigo();
            }
            return;
        }

        if ($name === 'destino_id') {
            if ($this->destino_id) {
                $destino = $this->destinos->firstWhere('id', (int) $this->destino_id);
                if ($destino) {
                    $this->ciudad = $destino->nombre_destino;
                }
            }
            $this->applyTarifarioMatch();
            return;
        }

        if ($name === 'peso') {
            $this->applyTarifarioMatch();
        }

        if ($name === 'auto_codigo') {
            if ($this->auto_codigo) {
                $this->codigo = $this->generateCodigo();
            }
        }

        if ($name === 'nombre_remitente') {
            $this->refreshRemitenteSugerencias((string) $value);
            $this->applyRegisteredRemitente((string) $value);
        }
    }

    protected function refreshEmsState()
    {
        $this->is_ems = false;
        if (!$this->servicio_id) {
            return;
        }

        $servicio = $this->servicios->firstWhere('id', (int) $this->servicio_id);
        if ($servicio && strtoupper(trim($servicio->nombre_servicio)) === 'EMS') {
            $this->is_ems = true;
        }
    }

    protected function applyTarifarioMatch()
    {
        if ($this->isCertificadoShipment()) {
            $this->tarifario_id = null;
            $this->precio = null;
            return;
        }

        $this->setUserOrigenId();

        if (
            !$this->servicio_id ||
            !$this->destino_id ||
            !$this->user_origen_id ||
            $this->peso === '' ||
            $this->peso === null
        ) {
            $this->tarifario_id = '';
            $this->precio = '';
            return;
        }

        $peso = (float) $this->peso;

        $tarifario = Tarifario::query()
            ->with(['peso'])
            ->where('servicio_id', $this->servicio_id)
            ->where('destino_id', $this->destino_id)
            ->where('origen_id', $this->user_origen_id)
            ->whereHas('peso', function ($query) use ($peso) {
                $query->where('peso_inicial', '<=', $peso)
                    ->where('peso_final', '>=', $peso);
            })
            ->orderBy('id')
            ->first();

        if (!$tarifario) {
            $this->tarifario_id = '';
            $this->precio = '';
            return;
        }

        $this->tarifario_id = $tarifario->id;
        $this->precio = $tarifario->precio;
    }

    protected function isCertificadoShipment(): bool
    {
        $tipo = strtoupper(trim((string) $this->tipo_correspondencia));
        return $tipo !== '' && (str_contains($tipo, 'OFICIAL') || str_contains($tipo, 'CERTIFIC'));
    }

    protected function generateCodigo()
    {
        $prefix = $this->getCodigoPrefix();
        if ($prefix === null) {
            return $this->codigo;
        }
        $suffix = $this->getCodigoSuffix();

        $last = PaqueteEms::query()
            ->where('codigo', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('codigo');

        $nextNumber = 1;
        if ($last) {
            $num = (int) substr($last, strlen($prefix), 9);
            if ($num > 0) {
                $nextNumber = $num + 1;
            }
        }

        return $prefix . str_pad((string) $nextNumber, 9, '0', STR_PAD_LEFT) . $suffix;
    }

    protected function getCodigoPrefix()
    {
        if ($this->isAlmacenEms) {
            return 'AG';
        }

        if (!$this->servicio_id) {
            return null;
        }

        $servicio = $this->servicios->firstWhere('id', (int) $this->servicio_id);
        if (!$servicio) {
            return null;
        }

        $name = strtoupper(trim($servicio->nombre_servicio));
        if ($name === 'EMS') {
            return 'EN';
        }
        if ($name === 'ENCOMIENDA') {
            return 'CP';
        }
        if ($name === 'ECA') {
            return 'EC';
        }

        return null;
    }

    protected function getCodigoSuffix(): string
    {
        if ($this->isAlmacenEms) {
            return 'BC';
        }

        return 'BO';
    }

    protected function setEstadoAdmision()
    {
        $this->estado_id = $this->findEstadoId('ADMISIONES');
    }

    protected function setEstadoByMode()
    {
        if ($this->isAlmacenEms) {
            $this->estado_id = $this->findEstadoId('ALMACEN');
            return;
        }

        if ($this->isTransitoEms) {
            $this->estado_id = $this->resolveRegionalEstado()['id'] ?? null;
            return;
        }

        $this->setEstadoAdmision();
    }

    protected function findEstadoId(string $nombre): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($nombre))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function resolveRegionalEstado(): array
    {
        $enviadosId = $this->findEstadoId('ENVIADOS');
        if ($enviadosId) {
            return [
                'id' => $enviadosId,
                'nombre' => 'ENVIADOS',
            ];
        }

        $transitoId = $this->findEstadoId('TRANSITO');
        if ($transitoId) {
            return [
                'id' => $transitoId,
                'nombre' => 'TRANSITO',
            ];
        }

        return [
            'id' => null,
            'nombre' => null,
        ];
    }

    protected function saveRemitenteData(): void
    {
        $carnet = trim((string) $this->carnet);
        if ($carnet === '') {
            return;
        }

        RemitenteEms::updateOrCreate(
            ['carnet' => $carnet],
            [
                'nombre_remitente' => trim((string) $this->nombre_remitente),
                'telefono_remitente' => trim((string) $this->telefono_remitente),
                'nombre_envia' => trim((string) $this->nombre_envia),
            ]
        );
    }

    protected function syncFormularioData(PaqueteEms $paquete): void
    {
        PaqueteEmsFormulario::updateOrCreate(
            ['paquete_ems_id' => $paquete->id],
            [
                'origen' => $this->origen,
                'tipo_correspondencia' => $this->tipo_correspondencia,
                'servicio_especial' => $this->servicio_especial,
                'contenido' => $this->contenido,
                'cantidad' => $this->cantidad,
                'peso' => $this->peso,
                'codigo' => $this->codigo,
                'precio' => $this->precio,
                'nombre_remitente' => $this->nombre_remitente,
                'nombre_envia' => $this->nombre_envia,
                'carnet' => $this->carnet,
                'telefono_remitente' => $this->telefono_remitente,
                'nombre_destinatario' => $this->nombre_destinatario,
                'telefono_destinatario' => $this->telefono_destinatario,
                'ciudad' => $this->ciudad,
                'servicio_id' => $this->servicio_id ?: null,
                'destino_id' => $this->destino_id ?: null,
                'tarifario_id' => $this->tarifario_id ?: null,
            ]
        );
    }

    protected function nextSpecialCodeCorrelative(): int
    {
        $specialCodes = PaqueteEms::query()
            ->whereNotNull('cod_especial')
            ->lockForUpdate()
            ->pluck('cod_especial');

        if ($specialCodes->isEmpty()) {
            return 1;
        }

        $maxCorrelative = 0;
        foreach ($specialCodes as $specialCode) {
            if (preg_match('/^E(\d{5})$/', (string) $specialCode, $matches)) {
                $value = (int) $matches[1];
                if ($value > $maxCorrelative) {
                    $maxCorrelative = $value;
                }
            }
        }

        return $maxCorrelative + 1;
    }

    protected function refreshRemitenteSugerencias(string $value): void
    {
        $term = trim($value);
        if ($term === '') {
            $this->remitenteSugerencias = [];
            return;
        }

        $this->remitenteSugerencias = RemitenteEms::query()
            ->where('nombre_remitente', 'like', '%' . $term . '%')
            ->orderBy('nombre_remitente')
            ->limit(10)
            ->pluck('nombre_remitente')
            ->unique()
            ->values()
            ->all();
    }

    protected function applyRegisteredRemitente(string $value): void
    {
        $nombre = trim($value);
        if ($nombre === '') {
            return;
        }

        $remitente = RemitenteEms::query()
            ->whereRaw('trim(upper(nombre_remitente)) = trim(upper(?))', [$nombre])
            ->orderByDesc('updated_at')
            ->first();

        if (!$remitente) {
            return;
        }

        $this->telefono_remitente = $remitente->telefono_remitente;
        $this->carnet = $remitente->carnet;
        $this->nombre_envia = $remitente->nombre_envia;

        $this->applyLastFormularioDataByRemitente($nombre);
    }

    protected function applyLastFormularioDataByRemitente(string $nombreRemitente): void
    {
        if ($this->editingId) {
            return;
        }

        $formulario = PaqueteEmsFormulario::query()
            ->whereRaw('trim(upper(nombre_remitente)) = trim(upper(?))', [$nombreRemitente])
            ->orderByDesc('updated_at')
            ->first();

        if (!$formulario) {
            return;
        }

        $this->tipo_correspondencia = (string) ($formulario->tipo_correspondencia ?? $this->tipo_correspondencia);
        $this->servicio_especial = (string) ($formulario->servicio_especial ?? $this->servicio_especial);
        $this->contenido = (string) ($formulario->contenido ?? $this->contenido);
        $this->cantidad = $formulario->cantidad ?? $this->cantidad;
        $this->peso = $formulario->peso ?? $this->peso;
        $this->nombre_destinatario = (string) ($formulario->nombre_destinatario ?? $this->nombre_destinatario);
        $this->telefono_destinatario = (string) ($formulario->telefono_destinatario ?? $this->telefono_destinatario);
        $this->ciudad = (string) ($formulario->ciudad ?? $this->ciudad);

        if (!empty($formulario->servicio_id)) {
            $this->servicio_id = (string) $formulario->servicio_id;
        }

        if (!empty($formulario->destino_id)) {
            $this->destino_id = (string) $formulario->destino_id;
        }

        $this->applyTarifarioMatch();

        if ($this->auto_codigo) {
            $this->codigo = $this->generateCodigo();
        }
    }
}
