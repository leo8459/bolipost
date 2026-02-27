<?php

namespace App\Livewire;

use App\Models\Cartero;
use App\Models\CodigoEmpresa;
use App\Models\Destino;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Origen;
use App\Models\PaqueteEms;
use App\Models\PaqueteEmsFormulario;
use App\Models\Recojo as RecojoContrato;
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

    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 295;
    private const EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO = 297;
    private const EVENTO_ID_SACA_INTERNA_CREADA_SALIDA = 240;
    private const EVENTO_ID_PAQUETE_ENVIADO_VENTANILLA_EMS = 312;
    private const EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE = 316;

    public $mode = 'admision';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $selectedPaquetes = [];
    public $selectedContratos = [];
    public $perPagePaquetes = 25;
    public $perPageContratos = 25;
    public $filtroServicioId = '';
    public $almacenEstadoFiltro = 'TODOS';
    public $regionalDestino = '';
    public $regionalTransportMode = 'TERRESTRE';
    public $regionalTransportNumber = '';
    public $regionalDestinoContrato = '';
    public $regionalTransportModeContrato = 'TERRESTRE';
    public $regionalTransportNumberContrato = '';
    public $contratoCodigoPeso = '';
    public $contratoPeso = '';
    public $contratoDestino = '';
    public $contratoPesoContratoId = null;
    public $contratoPesoResumen = null;
    public $registroContratoCodigo = '';
    public $registroContratoOrigen = '';
    public $registroContratoDestino = '';
    public $registroContratoPeso = '';
    public $showCn33Reprint = false;
    public $cn33Despacho = '';
    public $generadosHoyCount = 0;
    public $entregaRecibidoPor = '';
    public $entregaDescripcion = '';
    public $recibirRegionalPreview = [];

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
    public $direccion = '';
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
        $allowedModes = ['admision', 'almacen_ems', 'transito_ems', 'ventanilla_ems'];
        $this->mode = in_array($mode, $allowedModes, true) ? $mode : 'admision';
        if ($this->isAlmacenEms) {
            $this->almacenEstadoFiltro = 'TODOS';
            $this->perPagePaquetes = 100;
            $this->perPageContratos = 50;
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

    public function getIsVentanillaEmsProperty()
    {
        return $this->mode === 'ventanilla_ems';
    }

    public function getRegionalEstadoLabelProperty(): string
    {
        if ($this->isTransitoEms) {
            return $this->resolveRegionalRecepcionEstado()['nombre'] ?? 'ENVIADO';
        }

        return $this->resolveRegionalEstado()['nombre'] ?? 'TRANSITO';
    }

    public function getCanSelectProperty()
    {
        return $this->isAdmision || $this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms;
    }

    public function setAlmacenFiltro($filtro)
    {
        if (!in_array($filtro, ['TODOS', 'ALMACEN', 'RECIBIDO'], true)) {
            return;
        }

        $this->almacenEstadoFiltro = $filtro;
        $this->selectedPaquetes = [];
        $this->resetPage();
        $this->resetPage('contratosPage');
    }

    public function searchPaquetes($seleccionarPorCodigo = false)
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
        if ($this->isAlmacenEms) {
            $this->resetPage('contratosPage');
        }

        if (!$seleccionarPorCodigo) {
            return;
        }

        $codigo = trim((string) $this->search);
        if ($codigo === '') {
            return;
        }

        // En ALMACEN EMS, permitir autoseleccion por codigo tanto en EMS como en CONTRATOS.
        if ($this->isAlmacenEms) {
            $paqueteEms = $this->basePaquetesQuery(false)
                ->whereRaw('trim(upper(paquetes_ems.codigo)) = trim(upper(?))', [$codigo])
                ->first(['paquetes_ems.id', 'paquetes_ems.codigo']);

            if ($paqueteEms) {
                $actualesEms = collect($this->selectedPaquetes)
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $this->selectedPaquetes = collect($actualesEms)
                    ->push((string) $paqueteEms->id)
                    ->unique()
                    ->values()
                    ->all();

                session()->flash('success', 'Paquete EMS seleccionado automaticamente por codigo.');
                $this->search = '';
                $this->searchQuery = '';
                $this->resetPage();
                return;
            }

            $userCity = trim((string) optional(Auth::user())->ciudad);
            $estadoAlmacenId = $this->findEstadoId('ALMACEN');

            $contrato = RecojoContrato::query()
                ->when(!empty($estadoAlmacenId), function ($query) use ($estadoAlmacenId) {
                    $query->where('estados_id', (int) $estadoAlmacenId);
                }, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->when($userCity !== '', function ($query) use ($userCity) {
                    $query->whereRaw('trim(upper(origen)) = trim(upper(?))', [$userCity]);
                }, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigo]);
                })
                ->first(['id', 'codigo', 'cod_especial']);

            if ($contrato) {
                $actualesContrato = collect($this->selectedContratos)
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $this->selectedContratos = collect($actualesContrato)
                    ->push((string) $contrato->id)
                    ->unique()
                    ->values()
                    ->all();

                session()->flash('success', 'Contrato seleccionado automaticamente por codigo.');
                $this->search = '';
                $this->searchQuery = '';
                $this->resetPage();
                return;
            }

            session()->flash('error', 'No se encontro un paquete o contrato con ese codigo.');
            $this->search = '';
            $this->searchQuery = '';
            return;
        }

        $paquete = $this->basePaquetesQuery(false)
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
        $idsEms = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $idsContratos = collect($this->selectedContratos)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($idsEms) && empty($idsContratos)) {
            session()->flash('error', 'Selecciona al menos un paquete o contrato.');
            return;
        }

        $this->regionalDestino = '';
        $this->regionalTransportMode = 'TERRESTRE';
        $this->regionalTransportNumber = '';
        $this->dispatch('openRegionalModal');
    }

    public function openRegionalContratoModal()
    {
        $ids = collect($this->selectedContratos)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('error', 'Selecciona al menos un contrato.');
            return;
        }

        $this->regionalDestinoContrato = '';
        $this->regionalTransportModeContrato = 'TERRESTRE';
        $this->regionalTransportNumberContrato = '';
        $this->dispatch('openRegionalContratoModal');
    }

    public function openContratoRegistrarModal()
    {
        if (!$this->isAlmacenEms) {
            return;
        }

        $user = Auth::user();
        $origen = strtoupper(trim((string) optional($user)->ciudad));
        if ($origen === '') {
            $origen = strtoupper(trim((string) optional($user)->name));
        }

        $this->registroContratoCodigo = '';
        $this->registroContratoOrigen = $origen;
        $this->registroContratoDestino = '';
        $this->registroContratoPeso = '';
        $this->resetValidation([
            'registroContratoCodigo',
            'registroContratoDestino',
            'registroContratoPeso',
        ]);

        $this->dispatch('openContratoRegistrarModal');
    }

    public function registrarContratoRapido()
    {
        if (!$this->isAlmacenEms) {
            return;
        }

        $validated = $this->validate([
            'registroContratoCodigo' => 'required|string|max:50',
            'registroContratoDestino' => ['required', 'string', Rule::in($this->ciudades)],
            'registroContratoPeso' => 'required|numeric|min:0.001',
        ], [], [
            'registroContratoCodigo' => 'codigo',
            'registroContratoDestino' => 'destino',
            'registroContratoPeso' => 'peso',
        ]);

        $user = Auth::user();
        if (!$user) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $codigo = strtoupper(trim((string) $validated['registroContratoCodigo']));
        $codigo = preg_replace('/\s+/', '', $codigo) ?: '';
        if ($codigo === '') {
            $this->addError('registroContratoCodigo', 'Ingresa un codigo valido.');
            return;
        }

        $codigoExistente = RecojoContrato::query()
            ->whereRaw('trim(upper(codigo)) = ?', [$codigo])
            ->exists();
        if ($codigoExistente) {
            $this->addError('registroContratoCodigo', 'Ese codigo ya esta registrado en contratos.');
            return;
        }

        $estadoAlmacenId = $this->findEstadoId('ALMACEN');
        if (!$estadoAlmacenId) {
            session()->flash('error', 'No existe el estado ALMACEN en la tabla estados.');
            return;
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? 'ORIGEN')));
        }

        $destino = strtoupper(trim((string) $validated['registroContratoDestino']));
        $peso = (float) $validated['registroContratoPeso'];
        $empresaId = $this->resolveEmpresaIdByCodigoContrato($codigo);

        $contrato = RecojoContrato::query()->create([
            'user_id' => (int) $user->id,
            'empresa_id' => $empresaId,
            'codigo' => $codigo,
            'cod_especial' => null,
            'estados_id' => (int) $estadoAlmacenId,
            'origen' => $origen,
            'destino' => $destino,
            'nombre_r' => 'SIN REMITENTE',
            'telefono_r' => '-',
            'contenido' => 'CONTRATO',
            'direccion_r' => 'SIN DIRECCION',
            'nombre_d' => 'SIN DESTINATARIO',
            'telefono_d' => null,
            'direccion_d' => 'SIN DIRECCION',
            'mapa' => null,
            'provincia' => null,
            'peso' => $peso,
            'fecha_recojo' => now()->toDateString(),
            'observacion' => 'REGISTRO RAPIDO DESDE ALMACEN EMS',
            'justificacion' => null,
            'imagen' => null,
        ]);

        $this->selectedContratos = collect($this->selectedContratos)
            ->map(fn ($id) => (string) $id)
            ->push((string) $contrato->id)
            ->unique()
            ->values()
            ->all();

        $this->dispatch('closeContratoRegistrarModal');
        session()->flash(
            'success',
            'Contrato registrado correctamente en ALMACEN.' . ($empresaId ? ' Empresa detectada y asignada.' : ' No se detecto empresa por codigo.')
        );
    }

    public function openContratoPesoModal()
    {
        if (!$this->isAlmacenEms) {
            return;
        }

        $this->contratoCodigoPeso = '';
        $this->contratoPeso = '';
        $this->contratoDestino = '';
        $this->contratoPesoContratoId = null;
        $this->contratoPesoResumen = null;
        $this->resetValidation([
            'contratoCodigoPeso',
            'contratoPeso',
            'contratoDestino',
            'contratoPesoContratoId',
        ]);

        $this->dispatch('openContratoPesoModal');
    }

    public function buscarContratoParaPeso()
    {
        if (!$this->isAlmacenEms) {
            return;
        }

        $validated = $this->validate([
            'contratoCodigoPeso' => 'required|string|max:50',
        ], [], [
            'contratoCodigoPeso' => 'codigo',
        ]);

        $codigo = strtoupper(trim((string) $validated['contratoCodigoPeso']));
        $contrato = $this->findContratoForPesoByCodigo($codigo);

        if (!$contrato) {
            $this->contratoPesoContratoId = null;
            $this->contratoPesoResumen = null;
            session()->flash('error', 'No se encontro un contrato en ALMACEN con ese codigo.');
            return;
        }

        $this->hydrateContratoPesoDetectedData($contrato);
        session()->flash('success', 'Contrato detectado. Ya puedes guardar peso y destino.');
    }

    public function guardarPesoContratoPorCodigo()
    {
        if (!$this->isAlmacenEms) {
            return;
        }

        $validated = $this->validate([
            'contratoCodigoPeso' => 'required|string|max:50',
            'contratoPeso' => 'required|numeric|min:0.001',
            'contratoDestino' => 'nullable|string|max:120',
        ], [], [
            'contratoCodigoPeso' => 'codigo',
            'contratoPeso' => 'peso',
            'contratoDestino' => 'destino',
        ]);

        $codigo = strtoupper(trim((string) $validated['contratoCodigoPeso']));
        $contrato = $this->findContratoForPesoByCodigo($codigo);
        if (!$contrato) {
            $this->contratoPesoContratoId = null;
            $this->contratoPesoResumen = null;
            $this->addError('contratoCodigoPeso', 'No se encontro un contrato en ALMACEN con ese codigo.');
            return;
        }

        $this->hydrateContratoPesoDetectedData($contrato);
        $contrato->peso = (float) $validated['contratoPeso'];
        $destino = trim((string) ($validated['contratoDestino'] ?? ''));
        if ($destino !== '') {
            $contrato->destino = strtoupper($destino);
        }
        $contrato->save();

        $this->contratoCodigoPeso = '';
        $this->contratoPeso = '';
        $this->contratoDestino = '';
        $this->contratoPesoContratoId = null;
        $this->contratoPesoResumen = null;
        $this->resetValidation([
            'contratoCodigoPeso',
            'contratoPesoContratoId',
            'contratoPeso',
            'contratoDestino',
        ]);

        session()->flash('success', 'Peso actualizado correctamente para contrato.');
    }

    protected function findContratoForPesoByCodigo(string $codigo): ?RecojoContrato
    {
        $userCity = trim((string) optional(Auth::user())->ciudad);
        $estadoAlmacenId = $this->findEstadoId('ALMACEN');

        if (!$estadoAlmacenId) {
            return null;
        }

        return RecojoContrato::query()
            ->when($userCity !== '', function ($query) use ($userCity) {
                $query->whereRaw('trim(upper(origen)) = trim(upper(?))', [$userCity]);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->where('estados_id', (int) $estadoAlmacenId)
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
                    ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigo]);
            })
            ->first([
                'id',
                'codigo',
                'cod_especial',
                'origen',
                'destino',
                'peso',
                'nombre_r',
                'nombre_d',
            ]);
    }

    protected function hydrateContratoPesoDetectedData(RecojoContrato $contrato): void
    {
        $this->contratoPesoContratoId = (int) $contrato->id;
        $this->contratoPeso = $contrato->peso !== null ? (string) $contrato->peso : '';
        $this->contratoDestino = (string) ($contrato->destino ?? '');
        $this->contratoPesoResumen = [
            'codigo' => (string) $contrato->codigo,
            'cod_especial' => (string) ($contrato->cod_especial ?? ''),
            'remitente' => (string) ($contrato->nombre_r ?? ''),
            'destinatario' => (string) ($contrato->nombre_d ?? ''),
            'origen' => (string) ($contrato->origen ?? ''),
        ];

        $this->selectedContratos = collect($this->selectedContratos)
            ->map(fn ($id) => (string) $id)
            ->push((string) $contrato->id)
            ->unique()
            ->values()
            ->all();

        $this->resetValidation(['contratoPesoContratoId']);
    }

    protected function resolveEmpresaIdByCodigoContrato(string $codigo): ?int
    {
        $codigoNormalizado = strtoupper(trim((string) $codigo));
        if ($codigoNormalizado === '') {
            return null;
        }

        $empresaIdPorCodigo = CodigoEmpresa::query()
            ->whereRaw('trim(upper(codigo)) = ?', [$codigoNormalizado])
            ->value('empresa_id');

        if (!empty($empresaIdPorCodigo)) {
            return (int) $empresaIdPorCodigo;
        }

        if (preg_match('/^C([A-Z0-9]+)A\d{5}BO$/', $codigoNormalizado, $matches)) {
            $codigoCliente = strtoupper(trim((string) ($matches[1] ?? '')));
            if ($codigoCliente !== '') {
                $empresaIdPorCliente = Empresa::query()
                    ->whereRaw('trim(upper(codigo_cliente)) = ?', [$codigoCliente])
                    ->value('id');

                if (!empty($empresaIdPorCliente)) {
                    return (int) $empresaIdPorCliente;
                }
            }
        }

        return null;
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
        $this->direccion = $formulario->direccion ?? $paquete->direccion;
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
            $paquete = null;
            DB::transaction(function () use ($user, &$paquete) {
                $paquete = PaqueteEms::create($this->payload($user->id));
                $this->syncFormularioData($paquete);
                $this->saveRemitenteData();
                $this->registerAdmisionEvento($paquete, (int) $user->id);
            });

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
        if (!$this->isAdmision) {
            return;
        }

        $this->generadosHoyCount = count($this->idsGeneradosHoyEnAdmision());
        $this->dispatch('openGeneradosHoyModal');
    }

    public function confirmarMandarGeneradosHoy()
    {
        if (!$this->isAdmision) {
            return;
        }

        $ids = $this->idsGeneradosHoyEnAdmision();
        $this->generadosHoyCount = count($ids);

        if (empty($ids)) {
            $this->dispatch('closeGeneradosHoyModal');
            session()->flash('error', 'No hay paquetes generados hoy en ADMISIONES para enviar.');
            return;
        }

        $this->selectedPaquetes = collect($ids)
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $this->dispatch('closeGeneradosHoyModal');
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

        $idsEms = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $idsContratos = collect($this->selectedContratos)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($idsEms) && empty($idsContratos)) {
            session()->flash('error', 'Selecciona al menos un paquete o contrato.');
            return;
        }

        $estadoRegional = $this->resolveRegionalEstado();
        $estadoRegionalId = $estadoRegional['id'] ?? null;
        $estadoRegionalNombre = $estadoRegional['nombre'] ?? null;

        if (!$estadoRegionalId || !$estadoRegionalNombre) {
            session()->flash('error', 'No existe el estado TRANSITO en la tabla estados.');
            return;
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $estadoAlmacenId = $this->findEstadoId('ALMACEN');
        if (!$estadoAlmacenId) {
            session()->flash('error', 'No existe el estado ALMACEN en la tabla estados.');
            return;
        }

        $generatedAt = now();
        $updated = 0;
        $paquetes = collect();
        $contratos = collect();

        $manifiesto = '';

        DB::transaction(function () use (
            $idsEms,
            $idsContratos,
            $estadoRegionalId,
            $estadoAlmacenId,
            $actorUserId,
            &$manifiesto,
            &$updated,
            &$paquetes,
            &$contratos
        ) {
            if (!empty($idsEms)) {
                $paquetes = PaqueteEms::query()
                    ->whereIn('id', $idsEms)
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
            } else {
                $paquetes = collect();
            }

            if (!empty($idsContratos)) {
                $contratos = RecojoContrato::query()
                    ->whereIn('id', $idsContratos)
                    ->where('estados_id', (int) $estadoAlmacenId)
                    ->with(['user:id,name'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get([
                        'id',
                        'codigo',
                        'cod_especial',
                        'origen',
                        'destino',
                        'peso',
                        'nombre_r',
                        'user_id',
                        'created_at',
                    ]);
            } else {
                $contratos = collect();
            }

            if ($paquetes->isEmpty() && $contratos->isEmpty()) {
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

            foreach ($contratos as $contrato) {
                $contrato->cod_especial = $manifiesto;
                $contrato->estados_id = (int) $estadoRegionalId;
                $contrato->save();
                $updated++;
            }

            $this->registerEventosEms(
                $paquetes->merge($contratos),
                $actorUserId,
                self::EVENTO_ID_SACA_INTERNA_CREADA_SALIDA
            );
        });

        if ($paquetes->isEmpty() && $contratos->isEmpty()) {
            session()->flash('error', 'No hay paquetes/contratos seleccionados para enviar.');
            return;
        }

        $paquetesPdf = $paquetes->map(function ($paquete) {
            return (object) [
                'codigo' => $paquete->codigo,
                'origen' => $paquete->origen,
                'cantidad' => (int) ($paquete->cantidad ?? 1),
                'peso' => (float) ($paquete->peso ?? 0),
                'nombre_remitente' => $paquete->nombre_remitente,
            ];
        })->merge(
            $contratos->map(function ($contrato) {
                return (object) [
                    'codigo' => $contrato->codigo,
                    'origen' => $contrato->origen,
                    'cantidad' => 1,
                    'peso' => (float) ($contrato->peso ?? 0),
                    'nombre_remitente' => $contrato->nombre_r,
                ];
            })
        );

        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);
        $pdf = Pdf::loadView('paquetes_ems.reporte-regional', [
            'paquetes' => $paquetesPdf,
            'generatedAt' => $generatedAt,
            'currentManifiesto' => $manifiesto,
            'loggedInUserCity' => $loggedInUserCity !== '' ? $loggedInUserCity : 'N/A',
            'destinationCity' => $this->regionalDestino,
            'selectedTransport' => $this->regionalTransportMode,
            'numeroVuelo' => $this->regionalTransportNumber,
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
        ])->setPaper('a4', 'portrait');

        $this->selectedPaquetes = [];
        $this->selectedContratos = [];
        $this->regionalDestino = '';
        $this->dispatch('closeRegionalModal');

        session()->flash('success', $updated . ' paquete(s)/contrato(s) enviado(s) a regional (' . $estadoRegionalNombre . ').');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'manifiesto-regional-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function mandarSeleccionadosContratosRegional()
    {
        if (trim((string) $this->regionalDestinoContrato) === '') {
            session()->flash('error', 'Selecciona la ciudad de destino para regional (contratos).');
            return;
        }

        $ids = collect($this->selectedContratos)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('error', 'Selecciona al menos un contrato.');
            return;
        }

        $estadoRegional = $this->resolveRegionalEstado();
        $estadoRegionalId = $estadoRegional['id'] ?? null;
        $estadoRegionalNombre = $estadoRegional['nombre'] ?? null;

        if (!$estadoRegionalId || !$estadoRegionalNombre) {
            session()->flash('error', 'No existe el estado TRANSITO en la tabla estados.');
            return;
        }

        $estadoAlmacenId = $this->findEstadoId('ALMACEN');
        if (!$estadoAlmacenId) {
            session()->flash('error', 'No existe el estado ALMACEN en la tabla estados.');
            return;
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $generatedAt = now();
        $updated = 0;
        $contratos = collect();
        $manifiesto = '';

        DB::transaction(function () use ($ids, $estadoRegionalId, $estadoAlmacenId, $actorUserId, &$manifiesto, &$updated, &$contratos) {
            $contratos = RecojoContrato::query()
                ->whereIn('id', $ids)
                ->where('estados_id', (int) $estadoAlmacenId)
                ->with(['user:id,name'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'codigo',
                    'cod_especial',
                    'origen',
                    'destino',
                    'peso',
                    'nombre_r',
                    'user_id',
                    'created_at',
                ]);

            if ($contratos->isEmpty()) {
                return;
            }

            $correlative = $this->nextSpecialCodeCorrelative();
            $manifiesto = 'E' . str_pad((string) $correlative, 5, '0', STR_PAD_LEFT);

            foreach ($contratos as $contrato) {
                $contrato->cod_especial = $manifiesto;
                $contrato->estados_id = (int) $estadoRegionalId;
                $contrato->save();
                $updated++;
            }

            $this->registerEventosEms(
                $contratos,
                $actorUserId,
                self::EVENTO_ID_SACA_INTERNA_CREADA_SALIDA
            );
        });

        if ($contratos->isEmpty()) {
            session()->flash('error', 'No hay contratos seleccionados para enviar.');
            return;
        }

        $paquetesPdf = $contratos->map(function ($contrato) {
            return (object) [
                'codigo' => $contrato->codigo,
                'origen' => $contrato->origen,
                'cantidad' => 1,
                'peso' => $contrato->peso ?? 0,
                'nombre_remitente' => $contrato->nombre_r,
            ];
        });

        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);
        $pdf = Pdf::loadView('paquetes_ems.reporte-regional', [
            'paquetes' => $paquetesPdf,
            'generatedAt' => $generatedAt,
            'currentManifiesto' => $manifiesto,
            'loggedInUserCity' => $loggedInUserCity !== '' ? $loggedInUserCity : 'N/A',
            'destinationCity' => $this->regionalDestinoContrato,
            'selectedTransport' => $this->regionalTransportModeContrato,
            'numeroVuelo' => $this->regionalTransportNumberContrato,
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
        ])->setPaper('a4', 'portrait');

        $this->selectedContratos = [];
        $this->regionalDestinoContrato = '';
        $this->dispatch('closeRegionalContratoModal');

        session()->flash('success', $updated . ' contrato(s) enviado(s) a regional (' . $estadoRegionalNombre . ').');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'manifiesto-regional-contratos-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function mandarSeleccionadosVentanillaEms()
    {
        if (!$this->isAlmacenEms) {
            session()->flash('error', 'Esta accion solo esta disponible en ALMACEN EMS.');
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

        $estadoVentanilla = $this->resolveVentanillaEstado();
        $estadoVentanillaId = $estadoVentanilla['id'] ?? null;
        if (!$estadoVentanillaId) {
            session()->flash('error', 'No existe el estado VENTANILLA EMS (o VENTANILLA) en la tabla estados.');
            return;
        }

        $paquetes = PaqueteEms::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'codigo']);

        if ($paquetes->isEmpty()) {
            session()->flash('error', 'No hay paquetes seleccionados para enviar.');
            return;
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        DB::transaction(function () use ($paquetes, $estadoVentanillaId, $actorUserId) {
            PaqueteEms::query()
                ->whereIn('id', $paquetes->pluck('id')->all())
                ->update(['estado_id' => $estadoVentanillaId]);

            $this->registerEventosEms(
                $paquetes,
                $actorUserId,
                self::EVENTO_ID_PAQUETE_ENVIADO_VENTANILLA_EMS
            );
        });

        $updated = $paquetes->count();
        $this->selectedPaquetes = [];
        session()->flash('success', $updated . ' paquete(s) enviado(s) a VENTANILLA EMS.');

        return $this->redirect(route('paquetes-ems.ventanilla'), navigate: false);
    }

    public function openEntregaVentanillaModal()
    {
        if (!$this->isVentanillaEms) {
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

        $this->entregaRecibidoPor = '';
        $this->entregaDescripcion = '';
        $this->dispatch('openEntregaVentanillaModal');
    }

    public function confirmarEntregaVentanilla()
    {
        if (!$this->isVentanillaEms) {
            return;
        }

        $this->validate([
            'entregaRecibidoPor' => ['required', 'string', 'max:255'],
            'entregaDescripcion' => ['nullable', 'string'],
        ]);

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

        $estadoDomicilioId = $this->findEstadoId('DOMICILIO');
        if (!$estadoDomicilioId) {
            session()->flash('error', 'No existe el estado DOMICILIO en la tabla estados.');
            return;
        }
        $estadoDomicilioNombre = (string) (Estado::query()
            ->where('id', $estadoDomicilioId)
            ->value('nombre_estado') ?? 'DOMICILIO');

        $eventoEntregaId = self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE;
        $eventoExiste = DB::table('eventos')
            ->where('id', $eventoEntregaId)
            ->exists();

        if (!$eventoExiste) {
            session()->flash('error', 'No existe el evento con ID ' . $eventoEntregaId . ' (Paquete entregado exitosamente).');
            return;
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $paquetes = PaqueteEms::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get([
                'id',
                'codigo',
                'nombre_destinatario',
                'telefono_destinatario',
                'ciudad',
                'direccion',
                'peso',
            ]);

        if ($paquetes->isEmpty()) {
            session()->flash('error', 'No hay paquetes seleccionados para entregar.');
            return;
        }
        $sinCodigo = $paquetes->filter(fn ($paquete) => trim((string) $paquete->codigo) === '');
        if ($sinCodigo->isNotEmpty()) {
            session()->flash('error', 'Hay paquetes sin codigo. No se puede registrar el evento 316 para todos.');
            return;
        }

        $recibidoPor = trim((string) $this->entregaRecibidoPor);
        $descripcion = trim((string) $this->entregaDescripcion);
        $generatedAt = now();
        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);

        DB::transaction(function () use ($paquetes, $estadoDomicilioId, $actorUserId, $eventoEntregaId, $recibidoPor, $descripcion) {
            PaqueteEms::query()
                ->whereIn('id', $paquetes->pluck('id')->all())
                ->update(['estado_id' => $estadoDomicilioId]);

            $this->registerEventosEms(
                $paquetes,
                $actorUserId,
                $eventoEntregaId
            );

            foreach ($paquetes as $paquete) {
                $asignacion = Cartero::query()->firstOrNew([
                    'id_paquetes_ems' => (int) $paquete->id,
                ]);
                $asignacion->id_paquetes_certi = null;
                $asignacion->id_estados = $estadoDomicilioId;
                $asignacion->id_user = $actorUserId;
                $asignacion->recibido_por = $recibidoPor;
                $asignacion->descripcion = $descripcion !== '' ? $descripcion : null;
                $asignacion->save();
            }
        });

        $pdf = Pdf::loadView('paquetes_ems.guia-entrega', [
            'paquetes' => $paquetes,
            'generatedAt' => $generatedAt,
            'estadoEntrega' => strtoupper(trim($estadoDomicilioNombre)),
            'recibidoPor' => $recibidoPor,
            'descripcion' => $descripcion,
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
            'loggedInUserCity' => $loggedInUserCity !== '' ? $loggedInUserCity : 'N/A',
        ])->setPaper('a4', 'portrait');

        $updated = $paquetes->count();
        $this->selectedPaquetes = [];
        $this->entregaRecibidoPor = '';
        $this->entregaDescripcion = '';
        $this->dispatch('closeEntregaVentanillaModal');

        session()->flash('success', $updated . ' paquete(s) entregado(s) correctamente.');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'guia-entrega-ventanilla-ems-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function openRecibirRegionalModal()
    {
        if (!$this->isTransitoEms) {
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

        $paquetes = $this->basePaquetesQuery()
            ->whereIn('paquetes_ems.id', $ids)
            ->orderBy('paquetes_ems.id')
            ->get(['paquetes_ems.id', 'paquetes_ems.codigo', 'paquetes_ems.nombre_remitente', 'paquetes_ems.nombre_destinatario', 'paquetes_ems.ciudad', 'paquetes_ems.peso']);

        if ($paquetes->isEmpty()) {
            session()->flash('error', 'No hay paquetes validos para recibir en este listado.');
            return;
        }

        $this->recibirRegionalPreview = $paquetes
            ->map(function ($paquete) {
                $formulario = $paquete->formulario;

                return [
                    'id' => (int) $paquete->id,
                    'codigo' => (string) $paquete->codigo,
                    'nombre_remitente' => (string) ($formulario->nombre_remitente ?? $paquete->nombre_remitente),
                    'nombre_destinatario' => (string) ($formulario->nombre_destinatario ?? $paquete->nombre_destinatario),
                    'ciudad' => (string) ($formulario->ciudad ?? $paquete->ciudad),
                    'peso' => (string) ($formulario->peso ?? $paquete->peso),
                ];
            })
            ->values()
            ->all();

        $this->dispatch('openRecibirRegionalModal');
    }

    public function recibirSeleccionadosRegional()
    {
        if (!$this->isTransitoEms) {
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
        $this->recibirRegionalPreview = [];
        $this->dispatch('closeRecibirRegionalModal');
        session()->flash('success', $updated . ' paquete(s) recibido(s) en RECIBIDO.');
    }

    public function devolverAAdmisiones($id)
    {
        if (!$this->isAlmacenEms) {
            session()->flash('error', 'Esta accion solo esta disponible en ALMACEN.');
            return;
        }

        $estadoAdmision = $this->findEstadoId('ADMISIONES');
        if (!$estadoAdmision) {
            session()->flash('error', 'No existe el estado ADMISIONES en la tabla estados.');
            return;
        }

        $paquete = $this->basePaquetesQuery()
            ->where('paquetes_ems.id', (int) $id)
            ->first(['paquetes_ems.id', 'paquetes_ems.codigo']);

        if (!$paquete) {
            session()->flash('error', 'No se encontro el paquete en el listado actual.');
            return;
        }

        $updated = PaqueteEms::query()
            ->whereKey((int) $id)
            ->update(['estado_id' => $estadoAdmision]);

        if (!$updated) {
            session()->flash('error', 'No se pudo devolver el paquete a ADMISIONES.');
            return;
        }

        $this->selectedPaquetes = collect($this->selectedPaquetes)
            ->reject(fn ($selectedId) => (int) $selectedId === (int) $id)
            ->values()
            ->all();

        session()->flash('success', 'Paquete ' . $paquete->codigo . ' devuelto a ADMISIONES.');
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

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        DB::transaction(function () use ($paquetes, $estadoAlmacenEms, $actorUserId) {
            PaqueteEms::query()
                ->whereIn('id', $paquetes->pluck('id')->all())
                ->update(['estado_id' => $estadoAlmacenEms]);

            $this->registerEventosEms(
                $paquetes,
                $actorUserId,
                self::EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO
            );
        });

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
            'direccion',
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
            'direccion' => 'nullable|string|max:255',
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
            'direccion' => $this->direccion,
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
        $contratosAlmacen = null;
        $almacenRows = null;

        if ($this->isAlmacenEms) {
            $almacenRows = $this->almacenUnificadoQuery()
                ->simplePaginate($this->normalizePerPage($this->perPagePaquetes));
            $paquetes = $almacenRows;
        } else {
            $paquetes = $this->basePaquetesQuery()
                ->simplePaginate($this->normalizePerPage($this->perPagePaquetes));
        }

        return view('livewire.paquetes-ems', [
            'paquetes' => $paquetes,
            'almacenRows' => $almacenRows,
            'contratosAlmacen' => $contratosAlmacen,
        ]);
    }

    protected function almacenUnificadoQuery()
    {
        $q = trim((string) $this->searchQuery);
        $userCity = trim((string) optional(Auth::user())->ciudad);
        $estadoAlmacenId = $this->findEstadoId('ALMACEN');
        $estadoRecibidoId = $this->findEstadoId('RECIBIDO');

        $emsQuery = DB::table('paquetes_ems')
            ->leftJoin('paquetes_ems_formulario as formulario', 'formulario.paquete_ems_id', '=', 'paquetes_ems.id')
            ->leftJoin('tarifario', 'tarifario.id', '=', 'paquetes_ems.tarifario_id')
            ->leftJoin('servicio', 'servicio.id', '=', 'tarifario.servicio_id')
            ->leftJoin('destino', 'destino.id', '=', 'tarifario.destino_id')
            ->selectRaw("'EMS' as record_type")
            ->selectRaw('paquetes_ems.id as record_id')
            ->selectRaw("coalesce(nullif(trim(formulario.codigo), ''), paquetes_ems.codigo) as codigo")
            ->selectRaw("coalesce(formulario.tipo_correspondencia, paquetes_ems.tipo_correspondencia, 'EMS') as tipo")
            ->selectRaw("coalesce(formulario.servicio_especial, paquetes_ems.servicio_especial, '-') as servicio_especial")
            ->selectRaw("coalesce(servicio.nombre_servicio, '-') as servicio")
            ->selectRaw("coalesce(destino.nombre_destino, formulario.ciudad, paquetes_ems.ciudad, '-') as destino")
            ->selectRaw("coalesce(formulario.contenido, paquetes_ems.contenido, '-') as contenido")
            ->selectRaw('coalesce(formulario.cantidad, paquetes_ems.cantidad, 1) as cantidad')
            ->selectRaw('coalesce(formulario.peso, paquetes_ems.peso, 0) as peso')
            ->selectRaw("coalesce(formulario.nombre_remitente, paquetes_ems.nombre_remitente, '-') as remitente")
            ->selectRaw("coalesce(formulario.nombre_envia, paquetes_ems.nombre_envia, '-') as nombre_envia")
            ->selectRaw("coalesce(formulario.carnet, paquetes_ems.carnet, '-') as carnet")
            ->selectRaw("coalesce(formulario.telefono_remitente, paquetes_ems.telefono_remitente, '-') as telefono_r")
            ->selectRaw("coalesce(formulario.nombre_destinatario, paquetes_ems.nombre_destinatario, '-') as destinatario")
            ->selectRaw("coalesce(formulario.telefono_destinatario, paquetes_ems.telefono_destinatario, '-') as telefono_d")
            ->selectRaw("coalesce(formulario.ciudad, paquetes_ems.ciudad, '-') as ciudad")
            ->selectRaw("'-' as empresa")
            ->selectRaw('paquetes_ems.cod_especial as cod_especial')
            ->selectRaw('paquetes_ems.created_at as created_at');

        $emsQuery->when(!empty($estadoAlmacenId) || !empty($estadoRecibidoId), function ($query) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
            if ($userCity === '') {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where(function ($sub) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                if ($this->almacenEstadoFiltro === 'ALMACEN' && $estadoAlmacenId) {
                    $sub->where('paquetes_ems.estado_id', (int) $estadoAlmacenId)
                        ->whereRaw('trim(upper(paquetes_ems.origen)) = trim(upper(?))', [$userCity]);
                    return;
                }

                if ($this->almacenEstadoFiltro === 'RECIBIDO' && $estadoRecibidoId) {
                    $sub->where('paquetes_ems.estado_id', (int) $estadoRecibidoId)
                        ->whereRaw(
                            'trim(upper(coalesce(destino.nombre_destino, formulario.ciudad, paquetes_ems.ciudad))) = trim(upper(?))',
                            [$userCity]
                        );
                    return;
                }

                $sub->where(function ($q2) use ($userCity, $estadoAlmacenId) {
                    if ($estadoAlmacenId) {
                        $q2->where('paquetes_ems.estado_id', (int) $estadoAlmacenId)
                            ->whereRaw('trim(upper(paquetes_ems.origen)) = trim(upper(?))', [$userCity]);
                    } else {
                        $q2->whereRaw('1 = 0');
                    }
                })->orWhere(function ($q2) use ($userCity, $estadoRecibidoId) {
                    if ($estadoRecibidoId) {
                        $q2->where('paquetes_ems.estado_id', (int) $estadoRecibidoId)
                            ->whereRaw(
                                'trim(upper(coalesce(destino.nombre_destino, formulario.ciudad, paquetes_ems.ciudad))) = trim(upper(?))',
                                [$userCity]
                            );
                    } else {
                        $q2->whereRaw('1 = 0');
                    }
                });
            });
        }, function ($query) {
            $query->whereRaw('1 = 0');
        });

        $emsQuery->when($this->filtroServicioId !== '', function ($query) {
            $query->where('tarifario.servicio_id', (int) $this->filtroServicioId);
        });

        $contratosQuery = DB::table('paquetes_contrato')
            ->leftJoin('empresa as empresa_directa', 'empresa_directa.id', '=', 'paquetes_contrato.empresa_id')
            ->leftJoin('users', 'users.id', '=', 'paquetes_contrato.user_id')
            ->leftJoin('empresa as empresa_usuario', 'empresa_usuario.id', '=', 'users.empresa_id')
            ->selectRaw("'CONTRATO' as record_type")
            ->selectRaw('paquetes_contrato.id as record_id')
            ->selectRaw('paquetes_contrato.codigo as codigo')
            ->selectRaw("'CONTRATO' as tipo")
            ->selectRaw("'-' as servicio_especial")
            ->selectRaw("'CONTRATO' as servicio")
            ->selectRaw("coalesce(paquetes_contrato.destino, '-') as destino")
            ->selectRaw("coalesce(paquetes_contrato.contenido, '-') as contenido")
            ->selectRaw('1 as cantidad')
            ->selectRaw('coalesce(paquetes_contrato.peso, 0) as peso')
            ->selectRaw("coalesce(paquetes_contrato.nombre_r, '-') as remitente")
            ->selectRaw("'-' as nombre_envia")
            ->selectRaw("'-' as carnet")
            ->selectRaw("coalesce(paquetes_contrato.telefono_r, '-') as telefono_r")
            ->selectRaw("coalesce(paquetes_contrato.nombre_d, '-') as destinatario")
            ->selectRaw("coalesce(paquetes_contrato.telefono_d, '-') as telefono_d")
            ->selectRaw("coalesce(paquetes_contrato.destino, '-') as ciudad")
            ->selectRaw(
                "coalesce(empresa_directa.nombre, empresa_usuario.nombre, '-') ||
                case
                    when coalesce(empresa_directa.sigla, empresa_usuario.sigla, '') <> ''
                    then ' (' || coalesce(empresa_directa.sigla, empresa_usuario.sigla) || ')'
                    else ''
                end as empresa"
            )
            ->selectRaw('paquetes_contrato.cod_especial as cod_especial')
            ->selectRaw('paquetes_contrato.created_at as created_at')
            ->when(!empty($estadoAlmacenId), function ($query) use ($estadoAlmacenId) {
                $query->where('paquetes_contrato.estados_id', (int) $estadoAlmacenId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($userCity !== '', function ($query) use ($userCity) {
                $query->whereRaw('trim(upper(paquetes_contrato.origen)) = trim(upper(?))', [$userCity]);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($this->filtroServicioId !== '', function ($query) {
                $query->whereRaw('1 = 0');
            });

        $union = $emsQuery->unionAll($contratosQuery);

        return DB::query()
            ->fromSub($union, 'almacen_mix')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'like', "%{$q}%")
                        ->orWhere('tipo', 'like', "%{$q}%")
                        ->orWhere('servicio', 'like', "%{$q}%")
                        ->orWhere('servicio_especial', 'like', "%{$q}%")
                        ->orWhere('destino', 'like', "%{$q}%")
                        ->orWhere('contenido', 'like', "%{$q}%")
                        ->orWhere('remitente', 'like', "%{$q}%")
                        ->orWhere('destinatario', 'like', "%{$q}%")
                        ->orWhere('empresa', 'like', "%{$q}%")
                        ->orWhere('cod_especial', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('record_id');
    }

    protected function contratosAlmacenQuery(): Builder
    {
        $q = trim($this->searchQuery);
        $userCity = trim((string) optional(Auth::user())->ciudad);
        $estadoAlmacenId = $this->findEstadoId('ALMACEN');

        return RecojoContrato::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla',
                'user:id,name,empresa_id',
                'user.empresa:id,nombre,sigla',
            ])
            ->when(!empty($estadoAlmacenId), function ($query) use ($estadoAlmacenId) {
                $query->where('estados_id', (int) $estadoAlmacenId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($userCity !== '', function ($query) use ($userCity) {
                $query->whereRaw('trim(upper(origen)) = trim(upper(?))', [$userCity]);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'like', "%{$q}%")
                        ->orWhere('cod_especial', 'like', "%{$q}%")
                        ->orWhere('origen', 'like', "%{$q}%")
                        ->orWhere('destino', 'like', "%{$q}%")
                        ->orWhere('nombre_r', 'like', "%{$q}%")
                        ->orWhere('nombre_d', 'like', "%{$q}%")
                        ->orWhere('telefono_r', 'like', "%{$q}%")
                        ->orWhere('telefono_d', 'like', "%{$q}%")
                        ->orWhereHas('estadoRegistro', function ($estadoQuery) use ($q) {
                            $estadoQuery->where('nombre_estado', 'like', "%{$q}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($q) {
                            $userQuery->where('name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('user.empresa', function ($empresaQuery) use ($q) {
                            $empresaQuery->where('nombre', 'like', "%{$q}%")
                                ->orWhere('sigla', 'like', "%{$q}%");
                        })
                        ->orWhereHas('empresa', function ($empresaQuery) use ($q) {
                            $empresaQuery->where('nombre', 'like', "%{$q}%")
                                ->orWhere('sigla', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id');
    }

    protected function basePaquetesQuery(bool $applyServicioFilter = true): Builder
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
            'direccion',
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
            $estadoRegionalId = $this->resolveRegionalRecepcionEstado()['id'] ?? null;
            if ($estadoRegionalId) {
                $estadoIds[] = $estadoRegionalId;
            }
        } elseif ($this->isVentanillaEms) {
            $estadoVentanillaId = $this->resolveVentanillaEstado()['id'] ?? null;
            if ($estadoVentanillaId) {
                $estadoIds[] = $estadoVentanillaId;
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
            ->when($applyServicioFilter && $this->filtroServicioId !== '', function ($query) {
                $query->where('tarifario.servicio_id', (int) $this->filtroServicioId);
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
                            ->orWhere('direccion', 'like', "%{$q}%")
                            ->orWhere('ciudad', 'like', "%{$q}%");
                    });
                    $sub->orWhere('servicio.nombre_servicio', 'like', "%{$q}%");
                    $sub->orWhere('destino.nombre_destino', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('paquetes_ems.id');
    }

    protected function normalizePerPage($value): int
    {
        $allowed = [10, 25, 50, 100, 250, 500, 1000];
        $intValue = (int) $value;

        if (in_array($intValue, $allowed, true)) {
            return $intValue;
        }

        return 25;
    }

    protected function setOrigenFromUser()
    {
        $user = Auth::user();
        if ($user && !empty($user->ciudad)) {
            $this->origen = $user->ciudad;
        }
    }

    protected function idsGeneradosHoyEnAdmision(): array
    {
        $estadoAdmisionId = $this->findEstadoId('ADMISIONES');
        if (!$estadoAdmisionId) {
            return [];
        }

        return PaqueteEms::query()
            ->where('estado_id', $estadoAdmisionId)
            ->whereDate('created_at', now()->toDateString())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    protected function registerAdmisionEvento(PaqueteEms $paquete, int $userId): void
    {
        if (!$this->isAdmision) {
            return;
        }

        $this->registerEventosEms(
            [$paquete],
            $userId,
            self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE
        );
    }

    protected function registerEventosEms(iterable $paquetes, int $userId, int $eventoId): void
    {
        if ($userId <= 0 || $eventoId <= 0) {
            return;
        }

        $now = now();

        $rows = collect($paquetes)
            ->map(function ($paquete) use ($eventoId, $userId, $now) {
                $codigo = trim((string) ($paquete->codigo ?? ''));
                if ($codigo === '') {
                    return null;
                }

                return [
                    'codigo' => $codigo,
                    'evento_id' => (int) $eventoId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (empty($rows)) {
            return;
        }

        DB::table('eventos_ems')->insert($rows);
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
        if ($name === 'perPagePaquetes') {
            $this->perPagePaquetes = $this->normalizePerPage($value);
            $this->resetPage();
            return;
        }

        if ($name === 'perPageContratos') {
            $this->perPageContratos = $this->normalizePerPage($value);
            $this->resetPage('contratosPage');
            return;
        }

        if ($name === 'filtroServicioId') {
            $this->selectedPaquetes = [];
            $this->resetPage();
            return;
        }

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

        if ($this->isVentanillaEms) {
            $this->estado_id = $this->resolveVentanillaEstado()['id'] ?? null;
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
        $transitoId = $this->findEstadoId('TRANSITO');
        if ($transitoId) {
            return [
                'id' => $transitoId,
                'nombre' => 'TRANSITO',
            ];
        }

        // Compatibilidad para instalaciones con flujo antiguo.
        $enviadoId = $this->findEstadoId('ENVIADO');
        if ($enviadoId) {
            return [
                'id' => $enviadoId,
                'nombre' => 'ENVIADO',
            ];
        }

        // Compatibilidad con instalaciones antiguas que usaban "ENVIADOS".
        $enviadosLegacyId = $this->findEstadoId('ENVIADOS');
        if ($enviadosLegacyId) {
            return [
                'id' => $enviadosLegacyId,
                'nombre' => 'ENVIADOS',
            ];
        }

        return [
            'id' => null,
            'nombre' => null,
        ];
    }

    protected function resolveRegionalRecepcionEstado(): array
    {
        $enviadoId = $this->findEstadoId('ENVIADO');
        if ($enviadoId) {
            return [
                'id' => $enviadoId,
                'nombre' => 'ENVIADO',
            ];
        }

        return [
            'id' => null,
            'nombre' => null,
        ];
    }

    protected function resolveVentanillaEstado(): array
    {
        $ventanillaEmsId = $this->findEstadoId('VENTANILLA EMS');
        if ($ventanillaEmsId) {
            return [
                'id' => $ventanillaEmsId,
                'nombre' => 'VENTANILLA EMS',
            ];
        }

        $ventanillaId = $this->findEstadoId('VENTANILLA');
        if ($ventanillaId) {
            return [
                'id' => $ventanillaId,
                'nombre' => 'VENTANILLA',
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
                'direccion' => $this->direccion,
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
            ->pluck('cod_especial')
            ->merge(
                RecojoContrato::query()
                    ->whereNotNull('cod_especial')
                    ->lockForUpdate()
                    ->pluck('cod_especial')
            );

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
        $this->direccion = (string) ($formulario->direccion ?? $this->direccion);
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
