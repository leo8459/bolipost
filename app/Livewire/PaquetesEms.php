<?php

namespace App\Livewire;

use App\Models\Bitacora;
use App\Models\Cartero;
use App\Models\CodigoEmpresa;
use App\Models\Destino;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Origen;
use App\Models\PaqueteEms;
use App\Models\PaqueteEmsFormulario;
use App\Models\Preregistro;
use App\Models\Recojo as RecojoContrato;
use App\Models\RemitenteEms;
use App\Models\Servicio;
use App\Models\SolicitudCliente;
use App\Models\TarifaContrato;
use App\Models\Tarifario;
use App\Models\TarifarioTiktoker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PaquetesEms extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 295;
    private const EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO = 297;
    private const EVENTO_ID_SACA_INTERNA_CREADA_SALIDA = 240;
    private const EVENTO_ID_PAQUETE_ENVIADO_VENTANILLA_EMS = 312;
    private const EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE = 316;
    private const EVENTO_NOMBRE_PAQUETE_ENVIADO_DEVOLUCION = 'Paquete enviado a devolucion.';
    private const SPECIAL_CODE_PREFIX_BY_CITY = [
        'LA PAZ' => 'LPZ',
        'COCHABAMBA' => 'CBB',
        'SANTA CRUZ' => 'SRZ',
        'ORURO' => 'ORU',
        'POTOSI' => 'POI',
        'TARIJA' => 'TJA',
        'CHUQUISACA' => 'SRE',
        'SUCRE' => 'SRE',
        'TRINIDAD' => 'TDD',
        'TRINIDAD' => 'TDD',
        'COBIJA' => 'CIJ',
        'COBIJA' => 'CIJ',
    ];
    private const EMS_CODE_SERVICE_NAMES = [
        'EMS',
        'EMS_NACIONAL',
        'SUPER_EXPRESS_NACIONAL',
        'EMS_LOCAL_COBERTURA_1',
        'EMS_LOCAL_COBERTURA_2',
        'EMS_LOCAL_COBERTURA_3',
        'EMS_LOCAL_COBERTURA_4',
        'CIUDADES_INTERMEDIAS',
        'TRINIDAD_COBIJA',
        'CIUDADES_INTERMEDIAS_TRINIDAD_COBIJA',
    ];
    private const TELEFONO_DESTINATARIO_RECARGO = 1.00;
    private const MODE_ROUTE_PERMISSIONS = [
        'admision' => 'paquetes-ems.index',
        'create_ems' => 'paquetes-ems.create',
        'almacen_ems' => 'paquetes-ems.almacen',
        'ventanilla_ems' => 'paquetes-ems.ventanilla',
        'devolucion_ems' => 'paquetes-ems.devolucion',
        'transito_ems' => 'paquetes-ems.recibir-regional',
        'en_transito_ems' => 'paquetes-ems.en-transito',
    ];
    private const ALMACEN_EMS_REGISTER_CONTRACT_PERMISSION = 'feature.paquetes-ems.almacen.registercontract';
    private const ALMACEN_EMS_WEIGH_CONTRACT_PERMISSION = 'feature.paquetes-ems.almacen.weighcontract';
    private const ALMACEN_EMS_WEIGH_TIKTOKER_PERMISSION = 'feature.paquetes-ems.almacen.weightiktoker';
    private const ALMACEN_EMS_SEND_VENTANILLA_PERMISSION = 'feature.paquetes-ems.almacen.sendventanilla';
    private const ALMACEN_EMS_SEND_REGIONAL_PERMISSION = 'feature.paquetes-ems.almacen.sendregional';
    private const ALMACEN_EMS_REPRINT_CN33_PERMISSION = 'feature.paquetes-ems.almacen.reprintcn33';
    private const ALMACEN_ADMISIONES_ROUTE_PERMISSION = 'paquetes-ems.almacen-admisiones';

    public $mode = 'admision';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $selectedPaquetes = [];
    public $selectedContratos = [];
    public $selectedSolicitudes = [];
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
    public $tiktokerCodigoPeso = '';
    public $tiktokerPeso = '';
    public $tiktokerSolicitudId = null;
    public $tiktokerPesoResumen = null;
    public $registroContratoCodigo = '';
    public $registroContratoOrigen = '';
    public $registroContratoDestino = '';
    public $registroContratoPeso = '';
    public $showCn33Reprint = false;
    public $cn33Despacho = '';
    public $generadosHoyCount = 0;
    public $entregaRecibidoPor = '';
    public $entregaDescripcion = '';
    public $devolucionRecibidoPor = '';
    public $devolucionDescripcion = '';
    public $devolucionImagen = null;
    public $recibirRegionalPreview = [];
    public $recibirRegionalPesos = [];

    public $ciudades = [
        'LA PAZ',
        'SANTA CRUZ',
        'COBIJA',
        'TRINIDAD',
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
    public $showPaqueteConfirmModal = false;
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
    public $autofillMessage = '';
    public $preregistro_codigo = '';
    public $preregistroAutofillMessage = '';

    protected $paginationTheme = 'bootstrap';

    public $servicios = [];
    public $destinos = [];

    public function mount($mode = 'admision')
    {
        $allowedModes = ['admision', 'almacen_ems', 'en_transito_ems', 'transito_ems', 'ventanilla_ems', 'devolucion_ems', 'create_ems'];
        $this->mode = in_array($mode, $allowedModes, true) ? $mode : 'admision';
        if ($this->isAlmacenEms) {
            $this->almacenEstadoFiltro = 'TODOS';
            $this->perPagePaquetes = 100;
            $this->perPageContratos = 50;
        }
        $this->setOrigenFromUser();
        if ($this->isAdmision || $this->isAlmacenEms || $this->isCreateEms) {
            $this->servicios = Servicio::orderBy('nombre_servicio')->get();
            $this->destinos = Destino::orderBy('nombre_destino')->get();
            $this->setUserOrigenId();
        }

        if ($this->isCreateEms) {
            $this->resetForm();
            $this->setOrigenFromUser();
            $this->setUserOrigenId();
            $this->auto_codigo = true;
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

    public function getIsEnTransitoEmsProperty()
    {
        return $this->mode === 'en_transito_ems';
    }

    public function getIsVentanillaEmsProperty()
    {
        return $this->mode === 'ventanilla_ems';
    }

    public function getIsDevolucionEmsProperty()
    {
        return $this->mode === 'devolucion_ems';
    }

    public function getIsCreateEmsProperty()
    {
        return $this->mode === 'create_ems';
    }

    public function getRegionalEstadoLabelProperty(): string
    {
        if ($this->isTransitoEms) {
            return $this->resolveRegionalRecepcionEstado()['nombre'] ?? 'TRANSITO';
        }

        return $this->resolveRegionalEstado()['nombre'] ?? 'TRANSITO';
    }

    public function getCanSelectProperty()
    {
        return $this->isAdmision || $this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms;
    }

    public function setAlmacenFiltro($filtro)
    {
        if (!in_array($filtro, ['TODOS', 'ALMACEN', 'RECIBIDO'], true)) {
            return;
        }

        $this->almacenEstadoFiltro = $filtro;
        $this->selectedPaquetes = [];
        $this->selectedSolicitudes = [];
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
        if (!$this->canSelect) {
            $seleccionarPorCodigo = false;
        }

        if (!$seleccionarPorCodigo) {
            return;
        }

        $codigo = trim((string) $this->search);
        if ($codigo === '') {
            return;
        }

        // En ALMACEN EMS, permitir autoseleccion por codigo en EMS, CONTRATOS y SOLICITUDES.
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
            $estadoRecibidoId = $this->findEstadoId('RECIBIDO');

            $contrato = RecojoContrato::query()
                ->when($userCity === '', function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->when($userCity !== '', function ($query) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                    $query->where(function ($sub) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                        if ($this->almacenEstadoFiltro === 'ALMACEN' && $estadoAlmacenId) {
                            $sub->where('estados_id', (int) $estadoAlmacenId)
                                ->whereRaw('trim(upper(origen)) = trim(upper(?))', [$userCity]);
                            return;
                        }

                        if ($this->almacenEstadoFiltro === 'RECIBIDO' && $estadoRecibidoId) {
                            $sub->where('estados_id', (int) $estadoRecibidoId)
                                ->whereRaw('trim(upper(destino)) = trim(upper(?))', [$userCity]);
                            return;
                        }

                        $sub->where(function ($q2) use ($userCity, $estadoAlmacenId) {
                            if ($estadoAlmacenId) {
                                $q2->where('estados_id', (int) $estadoAlmacenId)
                                    ->whereRaw('trim(upper(origen)) = trim(upper(?))', [$userCity]);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        })->orWhere(function ($q2) use ($userCity, $estadoRecibidoId) {
                            if ($estadoRecibidoId) {
                                $q2->where('estados_id', (int) $estadoRecibidoId)
                                    ->whereRaw('trim(upper(destino)) = trim(upper(?))', [$userCity]);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        });
                    });
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

            $solicitud = $this->baseSolicitudesQuery()
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('trim(upper(codigo_solicitud)) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(barcode, \'\'))) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigo]);
                })
                ->first(['id', 'codigo_solicitud']);

            if ($solicitud) {
                $actualesSolicitud = collect($this->selectedSolicitudes)
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $this->selectedSolicitudes = collect($actualesSolicitud)
                    ->push((string) $solicitud->id)
                    ->unique()
                    ->values()
                    ->all();

                session()->flash('success', 'Solicitud seleccionada automaticamente por codigo.');
                $this->search = '';
                $this->searchQuery = '';
                $this->resetPage();
                return;
            }

            session()->flash('error', 'No se encontro un paquete, contrato o solicitud con ese codigo.');
            $this->search = '';
            $this->searchQuery = '';
            return;
        }

        // En RECIBIR REGIONAL, permitir autoseleccion por codigo en EMS, CONTRATOS y SOLICITUDES.
        if ($this->isTransitoEms) {
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

            $estadoRecibirRegionalId = $this->resolveRegionalRecepcionEstado()['id'] ?? null;
            $contrato = RecojoContrato::query()
                ->when(!empty($estadoRecibirRegionalId), function ($query) use ($estadoRecibirRegionalId) {
                    $query->where('estados_id', (int) $estadoRecibirRegionalId);
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

            $solicitud = $this->baseSolicitudesQuery()
                ->when(!empty($estadoRecibirRegionalId), function ($query) use ($estadoRecibirRegionalId) {
                    $query->where('estado_id', (int) $estadoRecibirRegionalId);
                }, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('trim(upper(codigo_solicitud)) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(barcode, \'\'))) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigo]);
                })
                ->first(['id', 'codigo_solicitud']);

            if ($solicitud) {
                $actualesSolicitud = collect($this->selectedSolicitudes)
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $this->selectedSolicitudes = collect($actualesSolicitud)
                    ->push((string) $solicitud->id)
                    ->unique()
                    ->values()
                    ->all();

                session()->flash('success', 'Solicitud seleccionada automaticamente por codigo.');
                $this->search = '';
                $this->searchQuery = '';
                $this->resetPage();
                return;
            }

            session()->flash('error', 'No se encontro un paquete, contrato o solicitud con ese codigo.');
            $this->search = '';
            $this->searchQuery = '';
            return;
        }

        if ($this->isDevolucionEms) {
            $paqueteEms = $this->basePaquetesQuery(false)
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('trim(upper(paquetes_ems.codigo)) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(paquetes_ems.cod_especial, \'\'))) = trim(upper(?))', [$codigo]);
                })
                ->first(['paquetes_ems.id', 'paquetes_ems.codigo']);

            if ($paqueteEms) {
                $this->selectedPaquetes = collect($this->selectedPaquetes)
                    ->map(fn ($id) => (string) $id)
                    ->push((string) $paqueteEms->id)
                    ->unique()
                    ->values()
                    ->all();

                $this->search = '';
                $this->searchQuery = '';
                $this->resetPage();
                $this->dispatch('openDevolucionEmsModal');
                session()->flash('success', 'Paquete EMS seleccionado automaticamente por codigo.');
                return;
            }

            $userCity = trim((string) optional(Auth::user())->ciudad);
            $estadoAlmacenId = $this->findEstadoId('ALMACEN');
            $estadoRecibidoId = $this->findEstadoId('RECIBIDO');

            $contrato = RecojoContrato::query()
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigo]);
                })
                ->whereIn('estados_id', $this->devolucionEstadoIdsContrato())
                ->where(function ($query) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                    if ($userCity === '') {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->where(function ($sub) use ($userCity, $estadoAlmacenId) {
                        if ($estadoAlmacenId) {
                            $sub->where('estados_id', (int) $estadoAlmacenId)
                                ->whereRaw('trim(upper(origen)) = trim(upper(?))', [$userCity]);
                        } else {
                            $sub->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($sub) use ($userCity, $estadoRecibidoId) {
                        if ($estadoRecibidoId) {
                            $sub->where('estados_id', (int) $estadoRecibidoId)
                                ->whereRaw('trim(upper(destino)) = trim(upper(?))', [$userCity]);
                        } else {
                            $sub->whereRaw('1 = 0');
                        }
                    });
                })
                ->first(['id', 'codigo']);

            if ($contrato) {
                $this->selectedContratos = collect($this->selectedContratos)
                    ->map(fn ($id) => (string) $id)
                    ->push((string) $contrato->id)
                    ->unique()
                    ->values()
                    ->all();

                $this->search = '';
                $this->searchQuery = '';
                $this->resetPage();
                $this->dispatch('openDevolucionEmsModal');
                session()->flash('success', 'Contrato seleccionado automaticamente por codigo.');
                return;
            }

            $solicitud = $this->baseSolicitudesQuery()
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('trim(upper(codigo_solicitud)) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(barcode, \'\'))) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigo]);
                })
                ->first(['id', 'codigo_solicitud']);

            if ($solicitud) {
                $this->selectedSolicitudes = collect($this->selectedSolicitudes)
                    ->map(fn ($id) => (string) $id)
                    ->push((string) $solicitud->id)
                    ->unique()
                    ->values()
                    ->all();

                $this->search = '';
                $this->searchQuery = '';
                $this->resetPage();
                $this->dispatch('openDevolucionEmsModal');
                session()->flash('success', 'Solicitud seleccionada automaticamente por codigo.');
                return;
            }

            session()->flash('error', 'No se encontro un paquete, contrato o solicitud con ese codigo para devolucion.');
            $this->search = '';
            $this->searchQuery = '';
            return;
        }

        if ($this->isVentanillaEms) {
            $paquete = $this->basePaquetesQuery(false)
                ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
                ->first(['id']);

            if ($paquete) {
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
                return;
            }

            $solicitud = $this->baseSolicitudesQuery()
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('trim(upper(codigo_solicitud)) = trim(upper(?))', [$codigo])
                        ->orWhereRaw('trim(upper(COALESCE(barcode, \'\'))) = trim(upper(?))', [$codigo]);
                })
                ->first(['id']);

            if (!$solicitud) {
                session()->flash('error', 'No se encontro un paquete o solicitud con ese codigo.');
                $this->search = '';
                $this->searchQuery = '';
                return;
            }

            $actuales = collect($this->selectedSolicitudes)
                ->map(fn ($id) => (string) $id)
                ->all();

            $this->selectedSolicitudes = collect($actuales)
                ->push((string) $solicitud->id)
                ->unique()
                ->values()
                ->all();

            session()->flash('success', 'Solicitud seleccionada automaticamente por codigo.');
            $this->search = '';
            $this->searchQuery = '';
            $this->resetPage();
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
        $this->authorizePermission($this->modeFeaturePermission('create'));
        $this->authorizeCreateRouteAccess();

        return $this->redirect(route('paquetes-ems.create', absolute: false), navigate: false);
    }

    public function openRegionalModal()
    {
        $this->authorizePermission(self::ALMACEN_EMS_SEND_REGIONAL_PERMISSION);

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

        $idsSolicitudes = collect($this->selectedSolicitudes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($idsEms) && empty($idsContratos) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete, contrato o solicitud.');
            return;
        }

        $this->regionalDestino = '';
        $this->regionalTransportMode = 'TERRESTRE';
        $this->regionalTransportNumber = '';
        $this->dispatch('openRegionalModal');
    }

    public function openRegionalContratoModal()
    {
        $this->authorizePermission(self::ALMACEN_EMS_SEND_REGIONAL_PERMISSION);

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
        $this->authorizePermission(self::ALMACEN_EMS_REGISTER_CONTRACT_PERMISSION);

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
        $this->authorizePermission(self::ALMACEN_EMS_REGISTER_CONTRACT_PERMISSION);

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

        $eligibleEstadoIds = $this->regionalEligibleEstadoIds();
        if (empty($eligibleEstadoIds)) {
            session()->flash('error', 'No existen los estados ALMACEN/RECIBIDO en la tabla estados.');
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
            'precio' => null,
            'tarifa_contrato_id' => null,
            'fecha_recojo' => now(),
            'observacion' => 'REGISTRO RAPIDO DESDE ALMACEN EMS',
            'justificacion' => null,
            'imagen' => null,
        ]);

        $tarifaAplicada = $this->applyTarifaContratoPricing($contrato);

        $this->selectedContratos = collect($this->selectedContratos)
            ->map(fn ($id) => (string) $id)
            ->push((string) $contrato->id)
            ->unique()
            ->values()
            ->all();

        $this->dispatch('closeContratoRegistrarModal');
        session()->flash(
            'success',
            'Contrato registrado correctamente en ALMACEN.'
            . ($empresaId ? ' Empresa detectada y asignada.' : ' No se detecto empresa por codigo.')
            . ($tarifaAplicada ? ' Precio calculado automaticamente.' : ' Precio pendiente hasta asignar una tarifa coincidente.')
        );
    }

    public function openContratoPesoModal()
    {
        $this->authorizePermission(self::ALMACEN_EMS_WEIGH_CONTRACT_PERMISSION);

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
        $this->authorizePermission(self::ALMACEN_EMS_WEIGH_CONTRACT_PERMISSION);

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
        $this->authorizePermission(self::ALMACEN_EMS_WEIGH_CONTRACT_PERMISSION);

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
        $teniaTarifaAsignada = (int) ($contrato->tarifa_contrato_id ?? 0) > 0;
        $contrato->peso = round((float) $validated['contratoPeso'], 3);
        $destino = trim((string) ($validated['contratoDestino'] ?? ''));
        if ($destino !== '') {
            $destinoNormalizado = strtoupper($destino);
            $destinoActual = strtoupper(trim((string) $contrato->destino));
            if ($destinoNormalizado !== $destinoActual) {
                $contrato->destino = $destinoNormalizado;
                // Si cambia departamento, la provincia previa puede quedar inconsistente.
                $contrato->provincia = null;
                $contrato->tarifa_contrato_id = null;
            }
        }
        $tarifaAplicada = $this->applyTarifaContratoPricing($contrato, $teniaTarifaAsignada);

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

        session()->flash(
            'success',
            $tarifaAplicada
                ? 'Peso actualizado y precio calculado automaticamente para contrato.'
                : 'Peso actualizado, pero no se encontro tarifa para calcular el precio automaticamente.'
        );
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
                'empresa_id',
                'origen',
                'destino',
                'provincia',
                'peso',
                'tarifa_contrato_id',
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

    protected function applyTarifaContratoPricing(RecojoContrato $contrato, bool $allowAutoResolve = false): bool
    {
        $peso = round((float) $contrato->peso, 3);
        $contrato->peso = $peso;

        $empresaId = (int) ($contrato->empresa_id ?? 0);
        if ($peso <= 0 || $empresaId <= 0) {
            $contrato->precio = null;
            if ($empresaId <= 0) {
                $contrato->tarifa_contrato_id = null;
            }
            $contrato->save();

            return false;
        }

        $tarifa = null;
        $tarifaIdActual = (int) ($contrato->tarifa_contrato_id ?? 0);
        if ($tarifaIdActual > 0) {
            $tarifa = TarifaContrato::query()
                ->where('id', $tarifaIdActual)
                ->where('empresa_id', $empresaId)
                ->first();
        }

        if (!$tarifa && $allowAutoResolve) {
            $tarifa = $this->resolveTarifaContratoForContrato($contrato);
        }

        if (!$tarifa) {
            $contrato->precio = null;
            $contrato->tarifa_contrato_id = null;
            $contrato->save();

            return false;
        }

        $contrato->tarifa_contrato_id = (int) $tarifa->id;
        $contrato->precio = $this->calculatePrecioContrato(
            $peso,
            (float) $tarifa->kilo,
            (float) $tarifa->kilo_extra
        );
        $contrato->save();

        return true;
    }

    public function openTiktokerPesoModal()
    {
        $this->authorizePermission(self::ALMACEN_EMS_WEIGH_TIKTOKER_PERMISSION);

        if (!$this->isAlmacenEms) {
            return;
        }

        $this->tiktokerCodigoPeso = '';
        $this->tiktokerPeso = '';
        $this->tiktokerSolicitudId = null;
        $this->tiktokerPesoResumen = null;
        $this->resetValidation([
            'tiktokerCodigoPeso',
            'tiktokerPeso',
            'tiktokerSolicitudId',
        ]);

        $this->dispatch('openTiktokerPesoModal');
    }

    public function buscarSolicitudTiktokerParaPeso()
    {
        $this->authorizePermission(self::ALMACEN_EMS_WEIGH_TIKTOKER_PERMISSION);

        if (!$this->isAlmacenEms) {
            return;
        }

        $validated = $this->validate([
            'tiktokerCodigoPeso' => 'required|string|max:50',
        ], [], [
            'tiktokerCodigoPeso' => 'codigo',
        ]);

        $codigo = strtoupper(trim((string) $validated['tiktokerCodigoPeso']));
        $solicitud = $this->findSolicitudTiktokerForPesoByCodigo($codigo);

        if (!$solicitud) {
            $this->tiktokerSolicitudId = null;
            $this->tiktokerPesoResumen = null;
            session()->flash('error', 'No se encontro una solicitud tiktokera valida con ese codigo.');
            return;
        }

        $this->hydrateSolicitudTiktokerPesoDetectedData($solicitud);
        session()->flash('success', 'Solicitud tiktokera detectada. Ya puedes asignar el peso.');
    }

    public function guardarPesoSolicitudTiktoker()
    {
        $this->authorizePermission(self::ALMACEN_EMS_WEIGH_TIKTOKER_PERMISSION);

        if (!$this->isAlmacenEms) {
            return;
        }

        $validated = $this->validate([
            'tiktokerCodigoPeso' => 'required|string|max:50',
            'tiktokerPeso' => 'required|numeric|min:0.001',
        ], [], [
            'tiktokerCodigoPeso' => 'codigo',
            'tiktokerPeso' => 'peso',
        ]);

        $codigo = strtoupper(trim((string) $validated['tiktokerCodigoPeso']));
        $solicitud = $this->findSolicitudTiktokerForPesoByCodigo($codigo);

        if (!$solicitud) {
            $this->tiktokerSolicitudId = null;
            $this->tiktokerPesoResumen = null;
            $this->addError('tiktokerCodigoPeso', 'No se encontro una solicitud tiktokera valida con ese codigo.');
            return;
        }

        $this->hydrateSolicitudTiktokerPesoDetectedData($solicitud);

        $estadoAlmacenId = $this->findEstadoId('ALMACEN');
        if (!$estadoAlmacenId) {
            session()->flash('error', 'No existe el estado ALMACEN en la tabla estados.');
            return;
        }

        try {
            [$tarifario, $precio] = $this->resolveTarifarioTiktokerYPrecio(
                (int) $solicitud->servicio_extra_id,
                (string) $solicitud->origen,
                (int) $solicitud->destino_id,
                (float) $validated['tiktokerPeso'],
                (bool) $solicitud->pago_destinatario
            );
        } catch (\RuntimeException $exception) {
            $this->addError('tiktokerPeso', $exception->getMessage());
            return;
        }

        $solicitud->peso = round((float) $validated['tiktokerPeso'], 3);
        $solicitud->precio = $precio;
        $solicitud->tarifario_tiktoker_id = (int) $tarifario->id;
        $solicitud->estado_id = (int) $estadoAlmacenId;
        $solicitud->save();

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId > 0) {
            $this->registerEventosTiktoker(
                [$solicitud],
                $actorUserId,
                self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE
            );
        }

        $this->selectedSolicitudes = collect($this->selectedSolicitudes)
            ->map(fn ($id) => (string) $id)
            ->push((string) $solicitud->id)
            ->unique()
            ->values()
            ->all();

        $this->tiktokerCodigoPeso = '';
        $this->tiktokerPeso = '';
        $this->tiktokerSolicitudId = null;
        $this->tiktokerPesoResumen = null;
        $this->resetValidation([
            'tiktokerCodigoPeso',
            'tiktokerPeso',
            'tiktokerSolicitudId',
        ]);

        $this->dispatch('closeTiktokerPesoModal');
        session()->flash('success', 'Peso asignado a solicitud tiktokera, precio recalculado y estado cambiado a ALMACEN.');
    }

    protected function findSolicitudTiktokerForPesoByCodigo(string $codigo): ?SolicitudCliente
    {
        $estadoAlmacenId = $this->findEstadoId('ALMACEN');

        if (!$estadoSolicitudId && !$estadoAlmacenId) {
            return null;
        }

        return SolicitudCliente::query()
            ->where(function ($query) use ($estadoSolicitudId, $estadoAlmacenId) {
                $aplicado = false;

                if ($estadoSolicitudId) {
                    $query->where('estado_id', (int) $estadoSolicitudId);
                    $aplicado = true;
                }

                if ($estadoAlmacenId) {
                    if ($aplicado) {
                        $query->orWhere('estado_id', (int) $estadoAlmacenId);
                    } else {
                        $query->where('estado_id', (int) $estadoAlmacenId);
                    }
                    $aplicado = true;
                }

                if (!$aplicado) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->whereNotNull('servicio_extra_id')
            ->whereNotNull('destino_id')
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('trim(upper(codigo_solicitud)) = trim(upper(?))', [$codigo])
                    ->orWhereRaw('trim(upper(COALESCE(barcode, \'\'))) = trim(upper(?))', [$codigo]);
            })
            ->first([
                'id',
                'codigo_solicitud',
                'barcode',
                'estado_id',
                'origen',
                'ciudad',
                'peso',
                'precio',
                'servicio_extra_id',
                'destino_id',
                'pago_destinatario',
                'nombre_remitente',
                'nombre_destinatario',
                'telefono_destinatario',
            ]);
    }

    protected function hydrateSolicitudTiktokerPesoDetectedData(SolicitudCliente $solicitud): void
    {
        $this->tiktokerSolicitudId = (int) $solicitud->id;
        $this->tiktokerPeso = $solicitud->peso !== null ? (string) $solicitud->peso : '';
        $this->tiktokerPesoResumen = [
            'codigo' => (string) ($solicitud->codigo_solicitud ?: $solicitud->barcode ?: 'SIN CODIGO'),
            'origen' => (string) ($solicitud->origen ?? ''),
            'destino' => (string) ($solicitud->ciudad ?? ''),
            'remitente' => (string) ($solicitud->nombre_remitente ?? ''),
            'destinatario' => (string) ($solicitud->nombre_destinatario ?? ''),
            'precio' => $solicitud->precio !== null ? number_format((float) $solicitud->precio, 2, '.', '') : null,
        ];

        $this->resetValidation(['tiktokerSolicitudId']);
    }

    protected function resolveTarifaContratoForContrato(RecojoContrato $contrato): ?TarifaContrato
    {
        $empresaId = (int) ($contrato->empresa_id ?? 0);
        $origen = $this->normalizeTarifaText((string) ($contrato->origen ?? ''));
        $destino = $this->normalizeTarifaText((string) ($contrato->destino ?? ''));
        $provincia = $this->normalizeTarifaText((string) ($contrato->provincia ?? ''));

        if ($empresaId <= 0 || $origen === '' || $destino === '') {
            return null;
        }

        $baseQuery = TarifaContrato::query()
            ->where('empresa_id', $empresaId)
            ->whereRaw('trim(upper(origen)) = ?', [$origen])
            ->whereRaw('trim(upper(destino)) = ?', [$destino]);

        if ($provincia !== '') {
            $exacta = (clone $baseQuery)
                ->whereRaw('trim(upper(provincia)) = ?', [$provincia])
                ->orderByDesc('id')
                ->first();

            if ($exacta) {
                return $exacta;
            }
        }

        $sinProvincia = (clone $baseQuery)
            ->whereNull('provincia')
            ->orderByDesc('id')
            ->first();

        if ($sinProvincia) {
            return $sinProvincia;
        }

        if ((clone $baseQuery)->count() === 1) {
            return (clone $baseQuery)->first();
        }

        return null;
    }

    protected function calculatePrecioContrato(float $peso, float $precioBaseKilo, float $precioKiloExtra): float
    {
        if ($peso <= 0) {
            return 0.00;
        }

        if ($peso <= 1.0) {
            return round($precioBaseKilo, 2);
        }

        $bloquesExtra = max(0, (int) ceil($peso - 1.0));

        return round($precioBaseKilo + ($bloquesExtra * $precioKiloExtra), 2);
    }

    protected function formatPesoEditable($peso): string
    {
        if ($peso === null || $peso === '') {
            return '';
        }

        return number_format((float) $peso, 3, '.', '');
    }

    protected function normalizeTarifaText(string $value): string
    {
        return strtoupper(trim($value));
    }

    protected function composeCn33Remitente(?string $nombreRemitente, ?string $empresaNombre): string
    {
        $nombre = trim((string) $nombreRemitente);
        $empresa = trim((string) $empresaNombre);

        if ($empresa === '') {
            return $nombre !== '' ? $nombre : 'SIN REMITENTE';
        }

        if ($nombre === '' || in_array(strtoupper($nombre), ['SIN REMITENTE', '-'], true)) {
            return $empresa;
        }

        if (stripos($nombre, $empresa) !== false) {
            return $nombre;
        }

        return $nombre . ' / ' . $empresa;
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
        $this->authorizePermission(self::ALMACEN_EMS_REPRINT_CN33_PERMISSION);

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
        $this->authorizePermission(self::ALMACEN_EMS_REPRINT_CN33_PERMISSION);

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
            ->with(['user:id,empresa_id', 'user.empresa:id,nombre'])
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

        $contratos = RecojoContrato::query()
            ->whereRaw('trim(upper(cod_especial)) = trim(upper(?))', [$despacho])
            ->with(['empresa:id,nombre'])
            ->orderBy('id')
            ->get([
                'id',
                'codigo',
                'cod_especial',
                'empresa_id',
                'origen',
                'destino',
                'peso',
                'nombre_r',
                'user_id',
                'created_at',
                'updated_at',
            ]);

        if ($paquetes->isEmpty() && $contratos->isEmpty()) {
            session()->flash('error', 'No se encontraron paquetes/contratos para el despacho ' . $despacho . '.');
            return;
        }

        $rowsPdf = collect($paquetes->map(function ($paquete) {
            return (object) [
                'codigo' => $paquete->codigo,
                'origen' => $paquete->origen,
                'cantidad' => (int) ($paquete->cantidad ?? 1),
                'peso' => (float) ($paquete->peso ?? 0),
                'nombre_remitente' => $this->composeCn33Remitente(
                    $paquete->nombre_remitente,
                    optional(optional($paquete->user)->empresa)->nombre
                ),
            ];
        })->all())
            ->concat($contratos->map(function ($contrato) {
                return (object) [
                    'codigo' => $contrato->codigo,
                    'origen' => $contrato->origen,
                    'cantidad' => 1,
                    'peso' => (float) ($contrato->peso ?? 0),
                    'nombre_remitente' => $this->composeCn33Remitente(
                        $contrato->nombre_r,
                        optional($contrato->empresa)->nombre
                    ),
                ];
            })->all())
            ->values();

        $generatedAt = collect([$paquetes->max('updated_at'), $contratos->max('updated_at')])
            ->filter()
            ->sortDesc()
            ->first() ?: now();
        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);
        $destinationCity = trim((string) (optional($paquetes->first())->ciudad ?? optional($contratos->first())->destino));

        $pdf = Pdf::loadView('paquetes_ems.reporte-regional', [
            'paquetes' => $rowsPdf,
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
        $this->authorizePermission($this->modeFeaturePermission('edit'));

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
        $permission = $this->editingId
            ? $this->modeFeaturePermission('edit')
            : $this->modeFeaturePermission('create');

        $this->authorizePermission($permission);

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
                $this->addError('peso', 'No existe tarifario para este servicio y peso.');
                return;
            }
        } else {
            $this->tarifario_id = null;
            $this->precio = null;
        }

        $this->precio_confirm = $this->precio;

        if ($this->auto_codigo) {
            $this->codigo = $this->generateCodigo();
        }

        $this->validate($this->rules());

        $this->showPaqueteConfirmModal = true;
        $this->dispatch('openPaqueteConfirm');
    }

    public function closePaqueteConfirmModal()
    {
        $this->showPaqueteConfirmModal = false;
        $this->dispatch('closePaqueteConfirm');
    }

    public function saveConfirmed()
    {
        $permission = $this->editingId
            ? $this->modeFeaturePermission('edit')
            : $this->modeFeaturePermission('create');

        $this->authorizePermission($permission);

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
                $this->linkPreregistroToPaquete($paquete, (int) $user->id);
                $this->registerAdmisionEvento($paquete, (int) $user->id);
            });

            session()->flash('success', 'Paquete creado correctamente.');
            $this->showPaqueteConfirmModal = false;
            $this->dispatch('closePaqueteConfirm');
            $this->dispatch('closePaqueteModal');
            $this->resetForm();
            if ($this->isCreateEms) {
                $this->setOrigenFromUser();
                $this->setUserOrigenId();
                $this->auto_codigo = true;
                return redirect()
                    ->route('paquetes-ems.index')
                    ->with('download_boleta_url', route('paquetes-ems.boleta', $paquete->id, false));
            }
            return $this->redirect(route('paquetes-ems.boleta', $paquete->id, false), navigate: false);
        }

        $this->showPaqueteConfirmModal = false;
        $this->dispatch('closePaqueteConfirm');
        $this->dispatch('closePaqueteModal');
        $this->resetForm();
    }

    public function mandarSeleccionadosGeneradosHoy()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'admision'));

        if (!$this->isAdmision) {
            return;
        }

        $this->generadosHoyCount = count($this->idsGeneradosHoyEnAdmision());
        $this->dispatch('openGeneradosHoyModal');
    }

    public function confirmarMandarGeneradosHoy()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'admision'));

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
        $this->authorizePermission($this->modeFeaturePermission('assign', 'admision'));

        return $this->mandarSeleccionadosAlmacenEms(false);
    }

    public function mandarSeleccionadosRegional()
    {
        $this->authorizePermission(self::ALMACEN_EMS_SEND_REGIONAL_PERMISSION);

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

        $idsSolicitudes = collect($this->selectedSolicitudes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($idsEms) && empty($idsContratos) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete, contrato o solicitud.');
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

        $eligibleEstadoIds = $this->regionalEligibleEstadoIds();
        if (empty($eligibleEstadoIds)) {
            session()->flash('error', 'No existen los estados ALMACEN/RECIBIDO en la tabla estados.');
            return;
        }

        $generatedAt = now();
        $updated = 0;
        $paquetes = collect();
        $contratos = collect();
        $solicitudes = collect();

        $manifiesto = '';

        DB::transaction(function () use (
            $idsEms,
            $idsContratos,
            $idsSolicitudes,
            $estadoRegionalId,
            $eligibleEstadoIds,
            $actorUserId,
            &$manifiesto,
            &$updated,
            &$paquetes,
            &$contratos,
            &$solicitudes
        ) {
            if (!empty($idsEms)) {
                $paquetes = PaqueteEms::query()
                    ->whereIn('id', $idsEms)
                    ->whereIn('estado_id', $eligibleEstadoIds)
                    ->with(['user:id,name,empresa_id', 'user.empresa:id,nombre'])
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
                        'precio',
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
                    ->whereIn('estados_id', $eligibleEstadoIds)
                    ->with(['user:id,name', 'empresa:id,nombre'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get([
                        'id',
                        'codigo',
                        'cod_especial',
                        'empresa_id',
                        'origen',
                        'destino',
                        'peso',
                        'precio',
                        'nombre_r',
                        'user_id',
                        'created_at',
                    ]);
            } else {
                $contratos = collect();
            }

            if (!empty($idsSolicitudes)) {
                $solicitudes = SolicitudCliente::query()
                    ->whereIn('id', $idsSolicitudes)
                    ->whereIn('estado_id', $eligibleEstadoIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get([
                        'id',
                        'codigo_solicitud',
                        'barcode',
                        'cod_especial',
                        'origen',
                        'ciudad',
                        'cantidad',
                        'peso',
                        'precio',
                        'nombre_remitente',
                        'created_at',
                    ]);
            } else {
                $solicitudes = collect();
            }

            if ($paquetes->isEmpty() && $contratos->isEmpty() && $solicitudes->isEmpty()) {
                return;
            }

            $manifiesto = $this->nextSpecialCodeForLoggedUser();

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

            foreach ($solicitudes as $solicitud) {
                $solicitud->cod_especial = $manifiesto;
                $solicitud->estado_id = (int) $estadoRegionalId;
                $solicitud->ciudad = $this->regionalDestino;
                $solicitud->save();
                $updated++;
            }

            $this->registrarBitacoraPorCodEspecial(
                $manifiesto,
                $paquetes,
                $contratos,
                $actorUserId,
                $this->regionalTransportMode,
                $this->regionalDestino
            );

            $this->registerEventosEms(
                $paquetes,
                $actorUserId,
                self::EVENTO_ID_SACA_INTERNA_CREADA_SALIDA
            );

            $this->registerEventosContrato(
                $contratos,
                $actorUserId,
                self::EVENTO_ID_SACA_INTERNA_CREADA_SALIDA
            );

            $this->registerEventosTiktoker(
                $solicitudes,
                $actorUserId,
                self::EVENTO_ID_SACA_INTERNA_CREADA_SALIDA
            );
        });

        if ($paquetes->isEmpty() && $contratos->isEmpty() && $solicitudes->isEmpty()) {
            session()->flash('error', 'No hay paquetes, contratos o solicitudes seleccionados para enviar.');
            return;
        }

        $paquetesPdf = $paquetes->map(function ($paquete) {
            return (object) [
                'codigo' => $paquete->codigo,
                'origen' => $paquete->origen,
                'cantidad' => (int) ($paquete->cantidad ?? 1),
                'peso' => (float) ($paquete->peso ?? 0),
                'nombre_remitente' => $this->composeCn33Remitente(
                    $paquete->nombre_remitente,
                    optional(optional($paquete->user)->empresa)->nombre
                ),
            ];
        })->merge(
            $contratos->map(function ($contrato) {
                return (object) [
                    'codigo' => $contrato->codigo,
                    'origen' => $contrato->origen,
                    'cantidad' => 1,
                    'peso' => (float) ($contrato->peso ?? 0),
                    'nombre_remitente' => $this->composeCn33Remitente(
                        $contrato->nombre_r,
                        optional($contrato->empresa)->nombre
                    ),
                ];
            })
        )->merge(
            $solicitudes->map(function ($solicitud) {
                return (object) [
                    'codigo' => $solicitud->codigo_solicitud ?: ($solicitud->barcode ?: 'SIN CODIGO'),
                    'origen' => $solicitud->origen,
                    'cantidad' => (int) ($solicitud->cantidad ?? 1),
                    'peso' => (float) ($solicitud->peso ?? 0),
                    'nombre_remitente' => (string) ($solicitud->nombre_remitente ?? 'SIN REMITENTE'),
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
        $this->selectedSolicitudes = [];
        $this->regionalDestino = '';
        $this->dispatch('closeRegionalModal');

        session()->flash('success', $updated . ' registro(s) enviado(s) a regional (' . $estadoRegionalNombre . ').');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'manifiesto-regional-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function mandarSeleccionadosContratosRegional()
    {
        $this->authorizePermission(self::ALMACEN_EMS_SEND_REGIONAL_PERMISSION);

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
                ->with(['user:id,name', 'empresa:id,nombre'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'codigo',
                    'cod_especial',
                    'empresa_id',
                    'origen',
                    'destino',
                    'peso',
                    'precio',
                    'nombre_r',
                    'user_id',
                    'created_at',
                ]);

            if ($contratos->isEmpty()) {
                return;
            }

            $manifiesto = $this->nextSpecialCodeForLoggedUser();

            foreach ($contratos as $contrato) {
                $contrato->cod_especial = $manifiesto;
                $contrato->estados_id = (int) $estadoRegionalId;
                $contrato->save();
                $updated++;
            }

            $this->registrarBitacoraPorCodEspecial(
                $manifiesto,
                collect(),
                $contratos,
                $actorUserId,
                $this->regionalTransportModeContrato,
                $this->regionalDestinoContrato
            );

            $this->registerEventosContrato(
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
                'nombre_remitente' => $this->composeCn33Remitente(
                    $contrato->nombre_r,
                    optional($contrato->empresa)->nombre
                ),
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
        $this->authorizePermission(self::ALMACEN_EMS_SEND_VENTANILLA_PERMISSION);

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

        $idsSolicitudes = collect($this->selectedSolicitudes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete o solicitud.');
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

        $solicitudes = SolicitudCliente::query()
            ->whereIn('id', $idsSolicitudes)
            ->orderBy('id')
            ->get(['id', 'codigo_solicitud', 'barcode']);

        if ($paquetes->isEmpty() && $solicitudes->isEmpty()) {
            session()->flash('error', 'No hay paquetes o solicitudes seleccionados para enviar.');
            return;
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        DB::transaction(function () use ($paquetes, $solicitudes, $estadoVentanillaId, $actorUserId) {
            if ($paquetes->isNotEmpty()) {
                PaqueteEms::query()
                    ->whereIn('id', $paquetes->pluck('id')->all())
                    ->update(['estado_id' => $estadoVentanillaId]);

                $this->registerEventosEms(
                    $paquetes,
                    $actorUserId,
                    self::EVENTO_ID_PAQUETE_ENVIADO_VENTANILLA_EMS
                );
            }

            if ($solicitudes->isNotEmpty()) {
                SolicitudCliente::query()
                    ->whereIn('id', $solicitudes->pluck('id')->all())
                    ->update(['estado_id' => $estadoVentanillaId]);

                $this->registerEventosTiktoker(
                    $solicitudes,
                    $actorUserId,
                    self::EVENTO_ID_PAQUETE_ENVIADO_VENTANILLA_EMS
                );
            }
        });

        $updated = $paquetes->count() + $solicitudes->count();
        $this->selectedPaquetes = [];
        $this->selectedSolicitudes = [];
        session()->flash('success', $updated . ' registro(s) enviado(s) a VENTANILLA EMS.');

        return $this->redirect(route('paquetes-ems.ventanilla'), navigate: false);
    }

    public function openEntregaVentanillaModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('deliver', 'ventanilla_ems'));

        if (!$this->isVentanillaEms) {
            return;
        }

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $idsSolicitudes = collect($this->selectedSolicitudes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete o solicitud.');
            return;
        }

        $this->entregaRecibidoPor = '';
        $this->entregaDescripcion = '';
        $this->dispatch('openEntregaVentanillaModal');
    }

    public function confirmarEntregaVentanilla()
    {
        $this->authorizePermission($this->modeFeaturePermission('deliver', 'ventanilla_ems'));

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

        $idsSolicitudes = collect($this->selectedSolicitudes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete o solicitud.');
            return;
        }

        // La entrega por ventanilla debe marcarse como ENTREGADO, no DOMICILIO
        $estadoEntregadoId = $this->findEstadoId('ENTREGADO');
        if (!$estadoEntregadoId) {
            session()->flash('error', 'No existe el estado ENTREGADO en la tabla estados.');
            return;
        }
        $estadoEntregadoNombre = (string) (Estado::query()
            ->where('id', $estadoEntregadoId)
            ->value('nombre_estado') ?? 'ENTREGADO');

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

        $solicitudes = SolicitudCliente::query()
            ->whereIn('id', $idsSolicitudes)
            ->orderBy('id')
            ->get([
                'id',
                'codigo_solicitud',
                'barcode',
                'nombre_destinatario',
                'telefono_destinatario',
                'ciudad',
                'direccion',
                'peso',
            ]);

        if ($paquetes->isEmpty() && $solicitudes->isEmpty()) {
            session()->flash('error', 'No hay paquetes o solicitudes seleccionados para entregar.');
            return;
        }
        $sinCodigo = $paquetes->filter(fn ($paquete) => trim((string) $paquete->codigo) === '');
        $sinCodigoSolicitud = $solicitudes->filter(function ($solicitud) {
            return trim((string) ($solicitud->codigo_solicitud ?: $solicitud->barcode)) === '';
        });
        if ($sinCodigo->isNotEmpty() || $sinCodigoSolicitud->isNotEmpty()) {
            session()->flash('error', 'Hay paquetes sin codigo. No se puede registrar el evento 316 para todos.');
            return;
        }

        $recibidoPor = trim((string) $this->entregaRecibidoPor);
        $descripcion = trim((string) $this->entregaDescripcion);
        $generatedAt = now();
        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);

        DB::transaction(function () use ($paquetes, $solicitudes, $estadoEntregadoId, $actorUserId, $eventoEntregaId, $recibidoPor, $descripcion) {
            if ($paquetes->isNotEmpty()) {
                PaqueteEms::query()
                    ->whereIn('id', $paquetes->pluck('id')->all())
                    ->update(['estado_id' => $estadoEntregadoId]);

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
                    $asignacion->id_estados = $estadoEntregadoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->recibido_por = $recibidoPor;
                    $asignacion->descripcion = $descripcion !== '' ? $descripcion : null;
                    $asignacion->save();
                }
            }

            if ($solicitudes->isNotEmpty()) {
                SolicitudCliente::query()
                    ->whereIn('id', $solicitudes->pluck('id')->all())
                    ->update([
                        'estado_id' => $estadoEntregadoId,
                        'recepcionado_por' => $recibidoPor !== '' ? $recibidoPor : null,
                        'observacion' => $descripcion !== '' ? $descripcion : null,
                    ]);

                $this->registerEventosTiktoker(
                    $solicitudes,
                    $actorUserId,
                    $eventoEntregaId
                );
            }
        });

        $paquetesPdf = collect($paquetes->map(function ($paquete) {
            return (object) [
                'codigo' => $paquete->codigo,
                'nombre_destinatario' => $paquete->nombre_destinatario,
                'telefono_destinatario' => $paquete->telefono_destinatario,
                'ciudad' => $paquete->ciudad,
                'direccion' => $paquete->direccion,
                'peso' => $paquete->peso,
            ];
        })->all())->merge($solicitudes->map(function ($solicitud) {
            return (object) [
                'codigo' => $solicitud->codigo_solicitud ?: $solicitud->barcode,
                'nombre_destinatario' => $solicitud->nombre_destinatario,
                'telefono_destinatario' => $solicitud->telefono_destinatario,
                'ciudad' => $solicitud->ciudad,
                'direccion' => $solicitud->direccion,
                'peso' => $solicitud->peso,
            ];
        })->all());

        $pdf = Pdf::loadView('paquetes_ems.guia-entrega', [
            'paquetes' => $paquetesPdf,
            'generatedAt' => $generatedAt,
            'estadoEntrega' => strtoupper(trim($estadoEntregadoNombre)),
            'recibidoPor' => $recibidoPor,
            'descripcion' => $descripcion,
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
            'loggedInUserCity' => $loggedInUserCity !== '' ? $loggedInUserCity : 'N/A',
        ])->setPaper('a4', 'portrait');

        $updated = $paquetes->count() + $solicitudes->count();
        $this->selectedPaquetes = [];
        $this->selectedSolicitudes = [];
        $this->entregaRecibidoPor = '';
        $this->entregaDescripcion = '';
        $this->dispatch('closeEntregaVentanillaModal');

        session()->flash('success', $updated . ' paquete(s) entregado(s) correctamente.');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'guia-entrega-ventanilla-ems-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function openDevolucionEmsModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('deliver', 'devolucion_ems'));

        if (!$this->isDevolucionEms) {
            return;
        }

        $idsEms = collect($this->selectedPaquetes)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $idsContratos = collect($this->selectedContratos)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $idsSolicitudes = collect($this->selectedSolicitudes)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if (empty($idsEms) && empty($idsContratos) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete, contrato o solicitud.');
            return;
        }

        $this->devolucionRecibidoPor = '';
        $this->devolucionDescripcion = '';
        $this->devolucionImagen = null;
        $this->resetValidation([
            'devolucionRecibidoPor',
            'devolucionDescripcion',
            'devolucionImagen',
        ]);

        $this->dispatch('openDevolucionEmsModal');
    }

    public function confirmarDevolucionEms()
    {
        $this->authorizePermission($this->modeFeaturePermission('deliver', 'devolucion_ems'));

        if (!$this->isDevolucionEms) {
            return;
        }

        $this->validate([
            'devolucionRecibidoPor' => ['required', 'string', 'max:255'],
            'devolucionDescripcion' => ['nullable', 'string'],
            'devolucionImagen' => ['required', 'image', 'max:5120'],
        ]);

        $idsEms = collect($this->selectedPaquetes)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $idsContratos = collect($this->selectedContratos)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $idsSolicitudes = collect($this->selectedSolicitudes)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if (empty($idsEms) && empty($idsContratos) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete, contrato o solicitud.');
            return;
        }

        $estadoDevolucionId = $this->findEstadoId('DEVOLUCION');
        if (!$estadoDevolucionId) {
            session()->flash('error', 'No existe el estado DEVOLUCION en la tabla estados.');
            return;
        }

        $eventoDevolucionId = $this->findEventoIdByName(self::EVENTO_NOMBRE_PAQUETE_ENVIADO_DEVOLUCION);
        if (!$eventoDevolucionId) {
            session()->flash('error', 'No existe el evento de devolucion en la tabla eventos.');
            return;
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $paquetes = PaqueteEms::query()
            ->whereIn('id', $idsEms)
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

        $contratos = RecojoContrato::query()
            ->whereIn('id', $idsContratos)
            ->orderBy('id')
            ->get([
                'id',
                'codigo',
                'nombre_d',
                'telefono_d',
                'destino',
                'direccion_d',
                'peso',
            ]);

        $solicitudes = SolicitudCliente::query()
            ->whereIn('id', $idsSolicitudes)
            ->orderBy('id')
            ->get([
                'id',
                'codigo_solicitud',
                'barcode',
                'nombre_destinatario',
                'telefono_destinatario',
                'ciudad',
                'direccion',
                'peso',
            ]);

        if ($paquetes->isEmpty() && $contratos->isEmpty() && $solicitudes->isEmpty()) {
            session()->flash('error', 'No hay registros seleccionados para devolucion.');
            return;
        }

        $imagenPath = $this->storeDevolucionImage($this->devolucionImagen);
        $recibidoPor = trim((string) $this->devolucionRecibidoPor);
        $descripcion = trim((string) $this->devolucionDescripcion);
        $generatedAt = now();
        $loggedUserName = trim((string) optional(Auth::user())->name);
        $loggedInUserCity = trim((string) optional(Auth::user())->ciudad);

        DB::transaction(function () use (
            $paquetes,
            $contratos,
            $solicitudes,
            $estadoDevolucionId,
            $actorUserId,
            $eventoDevolucionId,
            $imagenPath,
            $recibidoPor,
            $descripcion
        ) {
            if ($paquetes->isNotEmpty()) {
                PaqueteEms::query()
                    ->whereIn('id', $paquetes->pluck('id')->all())
                    ->update([
                        'estado_id' => $estadoDevolucionId,
                        'imagen' => $imagenPath,
                    ]);

                $this->registerEventosEms($paquetes, $actorUserId, $eventoDevolucionId);

                foreach ($paquetes as $paquete) {
                    $asignacion = Cartero::query()->firstOrNew([
                        'id_paquetes_ems' => (int) $paquete->id,
                    ]);
                    $asignacion->id_estados = $estadoDevolucionId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->recibido_por = $recibidoPor;
                    $asignacion->descripcion = $descripcion !== '' ? $descripcion : null;
                    $asignacion->imagen_devolucion = $imagenPath;
                    $asignacion->save();
                }
            }

            if ($contratos->isNotEmpty()) {
                RecojoContrato::query()
                    ->whereIn('id', $contratos->pluck('id')->all())
                    ->update([
                        'estados_id' => $estadoDevolucionId,
                        'imagen' => $imagenPath,
                    ]);

                $this->registerEventosContrato($contratos, $actorUserId, $eventoDevolucionId);

                foreach ($contratos as $contrato) {
                    $asignacion = Cartero::query()->firstOrNew([
                        'id_paquetes_contrato' => (int) $contrato->id,
                    ]);
                    $asignacion->id_estados = $estadoDevolucionId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->recibido_por = $recibidoPor;
                    $asignacion->descripcion = $descripcion !== '' ? $descripcion : null;
                    $asignacion->imagen_devolucion = $imagenPath;
                    $asignacion->save();
                }
            }

            if ($solicitudes->isNotEmpty()) {
                SolicitudCliente::query()
                    ->whereIn('id', $solicitudes->pluck('id')->all())
                    ->update([
                        'estado_id' => $estadoDevolucionId,
                        'recepcionado_por' => $recibidoPor !== '' ? $recibidoPor : null,
                        'observacion' => $descripcion !== '' ? $descripcion : null,
                        'imagen' => $imagenPath,
                    ]);

                $this->registerEventosTiktoker($solicitudes, $actorUserId, $eventoDevolucionId);

                foreach ($solicitudes as $solicitud) {
                    $asignacion = Cartero::query()->firstOrNew([
                        'id_solicitud_cliente' => (int) $solicitud->id,
                    ]);
                    $asignacion->id_estados = $estadoDevolucionId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->recibido_por = $recibidoPor;
                    $asignacion->descripcion = $descripcion !== '' ? $descripcion : null;
                    $asignacion->imagen_devolucion = $imagenPath;
                    $asignacion->save();
                }
            }
        });

        $paquetesPdf = collect($paquetes->map(function ($paquete) {
            return (object) [
                'codigo' => $paquete->codigo,
                'nombre_destinatario' => $paquete->nombre_destinatario,
                'telefono_destinatario' => $paquete->telefono_destinatario,
                'ciudad' => $paquete->ciudad,
                'direccion' => $paquete->direccion,
                'peso' => $paquete->peso,
            ];
        })->all())->merge($contratos->map(function ($contrato) {
            return (object) [
                'codigo' => $contrato->codigo,
                'nombre_destinatario' => $contrato->nombre_d,
                'telefono_destinatario' => $contrato->telefono_d,
                'ciudad' => $contrato->destino,
                'direccion' => $contrato->direccion_d,
                'peso' => $contrato->peso,
            ];
        })->all())->merge($solicitudes->map(function ($solicitud) {
            return (object) [
                'codigo' => $solicitud->codigo_solicitud ?: $solicitud->barcode,
                'nombre_destinatario' => $solicitud->nombre_destinatario,
                'telefono_destinatario' => $solicitud->telefono_destinatario,
                'ciudad' => $solicitud->ciudad,
                'direccion' => $solicitud->direccion,
                'peso' => $solicitud->peso,
            ];
        })->all());

        $pdf = Pdf::loadView('paquetes_ems.guia-entrega', [
            'paquetes' => $paquetesPdf,
            'generatedAt' => $generatedAt,
            'estadoEntrega' => 'DEVOLUCION',
            'recibidoPor' => $recibidoPor,
            'descripcion' => $descripcion,
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
            'loggedInUserCity' => $loggedInUserCity !== '' ? $loggedInUserCity : 'N/A',
        ])->setPaper('a4', 'portrait');

        $updated = $paquetes->count() + $contratos->count() + $solicitudes->count();
        $this->selectedPaquetes = [];
        $this->selectedContratos = [];
        $this->selectedSolicitudes = [];
        $this->devolucionRecibidoPor = '';
        $this->devolucionDescripcion = '';
        $this->devolucionImagen = null;
        $this->dispatch('closeDevolucionEmsModal');

        session()->flash('success', $updated . ' registro(s) enviado(s) a DEVOLUCION correctamente.');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'guia-devolucion-ems-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function openRecibirRegionalModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'transito_ems'));

        if (!$this->isTransitoEms) {
            return;
        }

        $this->recibirRegionalPreview = [];
        $this->recibirRegionalPesos = [];

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

        $idsSolicitudes = collect($this->selectedSolicitudes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($idsEms) && empty($idsContratos) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete, contrato o solicitud.');
            return;
        }

        $estadoRegionalRecepcionId = $this->resolveRegionalRecepcionEstado()['id'] ?? null;
        if (!$estadoRegionalRecepcionId) {
            session()->flash('error', 'No existe el estado ENVIADO en la tabla estados.');
            return;
        }

        $paquetes = collect();
        if (!empty($idsEms)) {
            $paquetes = $this->basePaquetesQuery()
                ->whereIn('paquetes_ems.id', $idsEms)
                ->orderBy('paquetes_ems.id')
                ->get(['paquetes_ems.id', 'paquetes_ems.codigo', 'paquetes_ems.nombre_remitente', 'paquetes_ems.nombre_destinatario', 'paquetes_ems.ciudad', 'paquetes_ems.peso']);
        }

        $contratos = collect();
        if (!empty($idsContratos)) {
            $contratos = RecojoContrato::query()
                ->whereIn('id', $idsContratos)
                ->where('estados_id', (int) $estadoRegionalRecepcionId)
                ->orderBy('id')
                ->get([
                    'id',
                    'codigo',
                    'nombre_r',
                    'nombre_d',
                    'destino',
                    'peso',
                ]);
        }

        $solicitudes = collect();
        if (!empty($idsSolicitudes)) {
            $solicitudes = SolicitudCliente::query()
                ->whereIn('id', $idsSolicitudes)
                ->where('estado_id', (int) $estadoRegionalRecepcionId)
                ->orderBy('id')
                ->get([
                    'id',
                    'codigo_solicitud',
                    'nombre_remitente',
                    'nombre_destinatario',
                    'ciudad',
                    'peso',
                ]);
        }

        if ($paquetes->isEmpty() && $contratos->isEmpty() && $solicitudes->isEmpty()) {
            session()->flash('error', 'No hay paquetes, contratos o solicitudes validos para recibir en este listado.');
            return;
        }

        $previewEms = $paquetes
            ->map(function ($paquete) {
                $formulario = $paquete->formulario;
                $peso = $this->formatPesoEditable($formulario->peso ?? $paquete->peso);

                return [
                    'id' => (int) $paquete->id,
                    'peso_key' => 'ems_' . (int) $paquete->id,
                    'tipo' => 'EMS',
                    'codigo' => (string) $paquete->codigo,
                    'nombre_remitente' => (string) ($formulario->nombre_remitente ?? $paquete->nombre_remitente),
                    'nombre_destinatario' => (string) ($formulario->nombre_destinatario ?? $paquete->nombre_destinatario),
                    'ciudad' => (string) ($formulario->ciudad ?? $paquete->ciudad),
                    'peso' => $peso,
                ];
            })
            ->values();

        $previewContratos = $contratos
            ->map(function ($contrato) {
                $peso = $this->formatPesoEditable($contrato->peso);

                return [
                    'id' => (int) $contrato->id,
                    'peso_key' => 'contrato_' . (int) $contrato->id,
                    'tipo' => 'CONTRATO',
                    'codigo' => (string) $contrato->codigo,
                    'nombre_remitente' => (string) ($contrato->nombre_r ?? ''),
                    'nombre_destinatario' => (string) ($contrato->nombre_d ?? ''),
                    'ciudad' => (string) ($contrato->destino ?? ''),
                    'peso' => $peso,
                ];
            })
            ->values();

        $previewSolicitudes = $solicitudes
            ->map(function ($solicitud) {
                $peso = $this->formatPesoEditable($solicitud->peso);

                return [
                    'id' => (int) $solicitud->id,
                    'peso_key' => 'solicitud_' . (int) $solicitud->id,
                    'tipo' => 'SOLICITUD',
                    'codigo' => (string) ($solicitud->codigo_solicitud ?: 'SIN CODIGO'),
                    'nombre_remitente' => (string) ($solicitud->nombre_remitente ?? ''),
                    'nombre_destinatario' => (string) ($solicitud->nombre_destinatario ?? ''),
                    'ciudad' => (string) ($solicitud->ciudad ?? ''),
                    'peso' => $peso,
                ];
            })
            ->values();

        $this->recibirRegionalPreview = $previewEms
            ->merge($previewContratos)
            ->merge($previewSolicitudes)
            ->values()
            ->all();

        $this->recibirRegionalPesos = collect($this->recibirRegionalPreview)
            ->mapWithKeys(function ($item) {
                return [($item['peso_key'] ?? '') => $item['peso'] ?? ''];
            })
            ->filter(function ($value, $key) {
                return $key !== '';
            })
            ->all();

        $this->dispatch('openRecibirRegionalModal');
    }

    public function recibirSeleccionadosRegional()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'transito_ems'));

        if (!$this->isTransitoEms) {
            return;
        }

        if (empty($this->recibirRegionalPreview)) {
            session()->flash('error', 'No hay registros preparados para recibir.');
            return;
        }

        $rules = [];
        $messages = [];
        foreach ($this->recibirRegionalPreview as $item) {
            $pesoKey = $item['peso_key'] ?? null;
            $codigo = (string) ($item['codigo'] ?? 'SIN CODIGO');
            if (!$pesoKey) {
                continue;
            }

            $rules['recibirRegionalPesos.' . $pesoKey] = 'required|numeric|min:0';
            $messages['recibirRegionalPesos.' . $pesoKey . '.required'] = 'El peso para ' . $codigo . ' es obligatorio.';
            $messages['recibirRegionalPesos.' . $pesoKey . '.numeric'] = 'El peso para ' . $codigo . ' debe ser numerico.';
            $messages['recibirRegionalPesos.' . $pesoKey . '.min'] = 'El peso para ' . $codigo . ' no puede ser negativo.';
        }

        $this->validate($rules, $messages);

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

        $idsSolicitudes = collect($this->selectedSolicitudes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($idsEms) && empty($idsContratos) && empty($idsSolicitudes)) {
            session()->flash('error', 'Selecciona al menos un paquete, contrato o solicitud.');
            return;
        }

        $estadoRecibido = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['RECIBIDO'])
            ->value('id');

        if (!$estadoRecibido) {
            session()->flash('error', 'No existe el estado RECIBIDO en la tabla estados.');
            return;
        }

        $estadoRegionalRecepcionId = $this->resolveRegionalRecepcionEstado()['id'] ?? null;
        if (!$estadoRegionalRecepcionId) {
            session()->flash('error', 'No existe el estado ENVIADO en la tabla estados.');
            return;
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId <= 0) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $paquetesRecibir = collect();
        if (!empty($idsEms)) {
            $paquetesRecibir = PaqueteEms::query()
                ->with('formulario:id,paquete_ems_id,peso')
                ->whereIn('id', $idsEms)
                ->where('estado_id', (int) $estadoRegionalRecepcionId)
                ->orderBy('id')
                ->get(['id', 'codigo', 'peso', 'estado_id']);
        }

        $contratosRecibir = collect();
        if (!empty($idsContratos)) {
            $contratosRecibir = RecojoContrato::query()
                ->whereIn('id', $idsContratos)
                ->where('estados_id', (int) $estadoRegionalRecepcionId)
                ->orderBy('id')
                ->get(['id', 'codigo', 'peso', 'estados_id']);
        }

        $solicitudesRecibir = collect();
        if (!empty($idsSolicitudes)) {
            $solicitudesRecibir = SolicitudCliente::query()
                ->whereIn('id', $idsSolicitudes)
                ->where('estado_id', (int) $estadoRegionalRecepcionId)
                ->orderBy('id')
                ->get(['id', 'codigo_solicitud', 'peso', 'estado_id']);
        }

        $updatedEms = 0;
        $updatedContratos = 0;
        $updatedSolicitudes = 0;
        $pesosRecibir = collect($this->recibirRegionalPesos)
            ->mapWithKeys(function ($peso, $key) {
                return [$key => round((float) $peso, 3)];
            });

        DB::transaction(function () use (
            $estadoRecibido,
            $paquetesRecibir,
            $contratosRecibir,
            $solicitudesRecibir,
            $actorUserId,
            $pesosRecibir,
            &$updatedEms,
            &$updatedContratos,
            &$updatedSolicitudes
        ) {
            if ($paquetesRecibir->isNotEmpty()) {
                foreach ($paquetesRecibir as $paquete) {
                    $peso = $pesosRecibir->get('ems_' . (int) $paquete->id);
                    if ($peso !== null) {
                        $paquete->peso = $peso;
                    }

                    $paquete->estado_id = $estadoRecibido;
                    $paquete->save();

                    if ($paquete->formulario) {
                        $paquete->formulario->peso = $paquete->peso;
                        $paquete->formulario->save();
                    }

                    $updatedEms++;
                }

                $this->registerEventosEms(
                    $paquetesRecibir,
                    $actorUserId,
                    self::EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO
                );
            }

            if ($contratosRecibir->isNotEmpty()) {
                foreach ($contratosRecibir as $contrato) {
                    $peso = $pesosRecibir->get('contrato_' . (int) $contrato->id);
                    if ($peso !== null) {
                        $contrato->peso = $peso;
                    }

                    $contrato->estados_id = $estadoRecibido;
                    $contrato->save();
                    $updatedContratos++;
                }

                $this->registerEventosContrato(
                    $contratosRecibir,
                    $actorUserId,
                    self::EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO
                );
            }

            if ($solicitudesRecibir->isNotEmpty()) {
                foreach ($solicitudesRecibir as $solicitud) {
                    $peso = $pesosRecibir->get('solicitud_' . (int) $solicitud->id);
                    if ($peso !== null) {
                        $solicitud->peso = $peso;
                    }

                    $solicitud->estado_id = $estadoRecibido;
                    $solicitud->save();
                    $updatedSolicitudes++;
                }

                $this->registerEventosTiktoker(
                    $solicitudesRecibir,
                    $actorUserId,
                    self::EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO
                );
            }
        });

        $updatedTotal = (int) $updatedEms + (int) $updatedContratos + (int) $updatedSolicitudes;

        $this->selectedPaquetes = [];
        $this->selectedContratos = [];
        $this->selectedSolicitudes = [];
        $this->recibirRegionalPreview = [];
        $this->recibirRegionalPesos = [];
        $this->dispatch('closeRecibirRegionalModal');
        session()->flash(
            'success',
            $updatedTotal . ' registro(s) recibido(s) en RECIBIDO. EMS: ' . $updatedEms . ', Contratos: ' . $updatedContratos . ', Solicitudes: ' . $updatedSolicitudes . '.'
        );
    }

    public function devolverAAdmisiones($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('restore', 'almacen_ems'));

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
        $this->authorizePermission($this->modeFeaturePermission('assign', 'admision'));

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
        $this->authorizePermission($this->modeFeaturePermission('delete', 'admision'));

        $paquete = PaqueteEms::findOrFail($id);
        $paquete->delete();
        session()->flash('success', 'Paquete eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'preregistro_codigo',
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
            'autofillMessage',
            'preregistroAutofillMessage',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        $requiresTarifario = !$this->isCertificadoShipment();

        return [
            'origen' => 'nullable|string|max:255',
            'tipo_correspondencia' => 'nullable|string|max:255',
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
            'precio' => 'nullable|numeric|min:0',
            'nombre_remitente' => 'required|string|max:255',
            'nombre_envia' => 'nullable|string|max:255',
            'carnet' => 'required|string|max:255',
            'telefono_remitente' => 'required|string|max:50',
            'nombre_destinatario' => 'required|string|max:255',
            'telefono_destinatario' => 'nullable|string|max:50',
            'direccion' => 'required|string|max:255',
            'ciudad' => ['nullable', 'string', 'max:255'],
            'servicio_id' => ['required', 'integer', Rule::exists('servicio', 'id')],
            'destino_id' => ['required', 'integer', Rule::exists('destino', 'id')],
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

        if ($this->isCreateEms) {
            return view('livewire.paquetes-ems', [
                'paquetes' => collect(),
                'almacenRows' => $almacenRows,
                'contratosAlmacen' => $contratosAlmacen,
                'canEmsAssign' => false,
                'canEmsCreate' => $this->userCan($this->modeFeaturePermission('create')),
                'canEmsAdmisionCreate' => $this->userCan($this->modeFeaturePermission('create', 'admision')),
                'canEmsCreateRoute' => $this->canAccessCreateRoute(),
                'canEmsEdit' => false,
                'canEmsDelete' => false,
                'canEmsPrint' => false,
                'canEmsRestore' => false,
                'canEmsDeliver' => false,
                'canEmsRegisterContract' => false,
                'canEmsWeighContract' => false,
                'canEmsWeighTiktoker' => false,
                'canEmsSendVentanilla' => false,
                'canEmsSendRegional' => false,
                'canEmsReprintCn33' => false,
                'canEmsAlmacenAdmisiones' => false,
                'canContratoAlmacenPrint' => false,
            ]);
        }

        if ($this->isAlmacenEms || $this->isEnTransitoEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms) {
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
            'canEmsAssign' => $this->userCan($this->modeFeaturePermission('assign')),
            'canEmsCreate' => $this->userCan($this->modeFeaturePermission('create')),
            'canEmsAdmisionCreate' => $this->userCan($this->modeFeaturePermission('create', 'admision')),
            'canEmsCreateRoute' => $this->canAccessCreateRoute(),
            'canEmsEdit' => $this->userCan($this->modeFeaturePermission('edit')),
            'canEmsDelete' => $this->userCan($this->modeFeaturePermission('delete')),
            'canEmsPrint' => $this->userCan($this->modeFeaturePermission('print')),
            'canEmsRestore' => $this->userCan($this->modeFeaturePermission('restore')),
            'canEmsDeliver' => $this->userCan($this->modeFeaturePermission('deliver')),
            'canEmsRegisterContract' => $this->userCan(self::ALMACEN_EMS_REGISTER_CONTRACT_PERMISSION),
            'canEmsWeighContract' => $this->userCan(self::ALMACEN_EMS_WEIGH_CONTRACT_PERMISSION),
            'canEmsWeighTiktoker' => $this->userCan(self::ALMACEN_EMS_WEIGH_TIKTOKER_PERMISSION),
            'canEmsSendVentanilla' => $this->userCan(self::ALMACEN_EMS_SEND_VENTANILLA_PERMISSION),
            'canEmsSendRegional' => $this->userCan(self::ALMACEN_EMS_SEND_REGIONAL_PERMISSION),
            'canEmsReprintCn33' => $this->userCan(self::ALMACEN_EMS_REPRINT_CN33_PERMISSION),
            'canEmsAlmacenAdmisiones' => $this->userCan(self::ALMACEN_ADMISIONES_ROUTE_PERMISSION),
            'canContratoAlmacenPrint' => $this->userCan('feature.paquetes-contrato.almacen.print'),
        ]);
    }

    private function modeFeaturePermission(string $action, ?string $mode = null): string
    {
        $modeKey = $mode ?? $this->mode;
        $routePermission = self::MODE_ROUTE_PERMISSIONS[$modeKey] ?? self::MODE_ROUTE_PERMISSIONS['admision'];

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

    private function canAccessCreateRoute(): bool
    {
        return $this->userCan('feature.paquetes-ems.index.create')
            || $this->userCan('feature.paquetes-ems.almacen.create')
            || $this->userCan('paquetes-ems.create');
    }

    private function authorizeCreateRouteAccess(): void
    {
        if (! $this->canAccessCreateRoute()) {
            abort(403, 'No tienes permiso para abrir Crear Admision.');
        }
    }

    protected function almacenUnificadoQuery()
    {
        $q = trim((string) $this->searchQuery);
        $userCity = trim((string) optional(Auth::user())->ciudad);
        $estadoSolicitudId = $this->findEstadoId('SOLICITUD');
        $estadoAlmacenId = $this->findEstadoId('ALMACEN');
        $estadoRecibidoId = $this->findEstadoId('RECIBIDO');
        $estadoTransitoId = $this->findEstadoId('TRANSITO');
        $estadoRecibirRegionalId = $this->resolveRegionalRecepcionEstado()['id'] ?? null;
        $estadoVentanillaId = $this->resolveVentanillaEstado()['id'] ?? null;
        $isModoDevolucion = $this->isDevolucionEms;
        $isModoEnTransito = $this->isEnTransitoEms;
        $isModoRecibirRegional = $this->isTransitoEms;
        $isModoVentanilla = $this->isVentanillaEms;

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
            ->selectRaw("cast(coalesce(formulario.cantidad, paquetes_ems.cantidad, 1) as varchar) as cantidad")
            ->selectRaw('coalesce(formulario.peso, paquetes_ems.peso, 0) as peso')
            ->selectRaw("coalesce(formulario.nombre_remitente, paquetes_ems.nombre_remitente, '-') as remitente")
            ->selectRaw("coalesce(formulario.nombre_envia, paquetes_ems.nombre_envia, '-') as nombre_envia")
            ->selectRaw("coalesce(formulario.carnet, paquetes_ems.carnet, '-') as carnet")
            ->selectRaw("coalesce(formulario.telefono_remitente, paquetes_ems.telefono_remitente, '-') as telefono_r")
            ->selectRaw("coalesce(formulario.nombre_destinatario, paquetes_ems.nombre_destinatario, '-') as destinatario")
            ->selectRaw("coalesce(formulario.telefono_destinatario, paquetes_ems.telefono_destinatario, '-') as telefono_d")
            ->selectRaw("coalesce(formulario.ciudad, paquetes_ems.ciudad, '-') as ciudad")
            ->selectRaw("coalesce(paquetes_ems.origen, '-') as origen")
            ->selectRaw("'-' as empresa")
            ->selectRaw('paquetes_ems.cod_especial as cod_especial')
            ->selectRaw('paquetes_ems.created_at as created_at');

        $emsQuery->when($isModoEnTransito, function ($query) use ($userCity, $estadoTransitoId) {
            if ($userCity === '' || empty($estadoTransitoId)) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where('paquetes_ems.estado_id', (int) $estadoTransitoId)
                ->whereRaw('trim(upper(paquetes_ems.origen)) = trim(upper(?))', [$userCity]);
        }, function ($query) use ($isModoRecibirRegional, $isModoVentanilla, $isModoDevolucion, $estadoRecibirRegionalId, $estadoVentanillaId, $userCity, $estadoAlmacenId, $estadoRecibidoId) {
            if ($isModoRecibirRegional) {
                if (empty($estadoRecibirRegionalId)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where('paquetes_ems.estado_id', (int) $estadoRecibirRegionalId);
                return;
            }

            if ($isModoVentanilla) {
                if (empty($estadoVentanillaId)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where('paquetes_ems.estado_id', (int) $estadoVentanillaId);
                return;
            }

            if ($isModoDevolucion) {
                if ($userCity === '') {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($sub) use ($userCity, $estadoAlmacenId, $estadoRecibidoId, $estadoVentanillaId) {
                    $sub->where(function ($q2) use ($estadoAlmacenId, $userCity) {
                        if ($estadoAlmacenId) {
                            $q2->where('paquetes_ems.estado_id', (int) $estadoAlmacenId)
                                ->whereRaw('trim(upper(paquetes_ems.origen)) = trim(upper(?))', [$userCity]);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q2) use ($estadoRecibidoId, $userCity) {
                        if ($estadoRecibidoId) {
                            $q2->where('paquetes_ems.estado_id', (int) $estadoRecibidoId)
                                ->whereRaw(
                                    'trim(upper(coalesce(destino.nombre_destino, formulario.ciudad, paquetes_ems.ciudad))) = trim(upper(?))',
                                    [$userCity]
                                );
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q2) use ($estadoVentanillaId) {
                        if ($estadoVentanillaId) {
                            $q2->where('paquetes_ems.estado_id', (int) $estadoVentanillaId);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    });
                });
                return;
            }

            if (empty($estadoAlmacenId) && empty($estadoRecibidoId)) {
                $query->whereRaw('1 = 0');
                return;
            }

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
            ->selectRaw("cast(coalesce(paquetes_contrato.cantidad, '-') as varchar) as cantidad")
            ->selectRaw('coalesce(paquetes_contrato.peso, 0) as peso')
            ->selectRaw("coalesce(paquetes_contrato.nombre_r, '-') as remitente")
            ->selectRaw("'-' as nombre_envia")
            ->selectRaw("'-' as carnet")
            ->selectRaw("coalesce(paquetes_contrato.telefono_r, '-') as telefono_r")
            ->selectRaw("coalesce(paquetes_contrato.nombre_d, '-') as destinatario")
            ->selectRaw("coalesce(paquetes_contrato.telefono_d, '-') as telefono_d")
            ->selectRaw("coalesce(paquetes_contrato.destino, '-') as ciudad")
            ->selectRaw("coalesce(paquetes_contrato.origen, '-') as origen")
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
            ->when($isModoEnTransito, function ($query) use ($estadoTransitoId) {
                if (!empty($estadoTransitoId)) {
                    $query->where('paquetes_contrato.estados_id', (int) $estadoTransitoId);
                    return;
                }

                $query->whereRaw('1 = 0');
            }, function ($query) use ($isModoRecibirRegional, $isModoVentanilla, $isModoDevolucion, $estadoRecibirRegionalId, $estadoAlmacenId, $estadoRecibidoId) {
                if ($isModoRecibirRegional) {
                    if (!empty($estadoRecibirRegionalId)) {
                        $query->where('paquetes_contrato.estados_id', (int) $estadoRecibirRegionalId);
                        return;
                    }

                    $query->whereRaw('1 = 0');
                    return;
                }

                if ($isModoVentanilla) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                if ($isModoDevolucion) {
                    $query->where(function ($sub) use ($estadoAlmacenId, $estadoRecibidoId) {
                        $sub->where(function ($q2) use ($estadoAlmacenId) {
                            if ($estadoAlmacenId) {
                                $q2->where('paquetes_contrato.estados_id', (int) $estadoAlmacenId);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        })->orWhere(function ($q2) use ($estadoRecibidoId) {
                            if ($estadoRecibidoId) {
                                $q2->where('paquetes_contrato.estados_id', (int) $estadoRecibidoId);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        });
                    });
                    return;
                }

                if ($this->almacenEstadoFiltro === 'ALMACEN' && !empty($estadoAlmacenId)) {
                    $query->where('paquetes_contrato.estados_id', (int) $estadoAlmacenId);
                    return;
                }

                if ($this->almacenEstadoFiltro === 'RECIBIDO' && !empty($estadoRecibidoId)) {
                    $query->where('paquetes_contrato.estados_id', (int) $estadoRecibidoId);
                    return;
                }

                $query->where(function ($sub) use ($estadoAlmacenId, $estadoRecibidoId) {
                    $sub->where(function ($q2) use ($estadoAlmacenId) {
                        if ($estadoAlmacenId) {
                            $q2->where('paquetes_contrato.estados_id', (int) $estadoAlmacenId);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q2) use ($estadoRecibidoId) {
                        if ($estadoRecibidoId) {
                            $q2->where('paquetes_contrato.estados_id', (int) $estadoRecibidoId);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->when(!$isModoRecibirRegional, function ($query) use ($userCity, $isModoEnTransito, $isModoDevolucion, $estadoAlmacenId, $estadoRecibidoId) {
                if ($userCity !== '') {
                    if ($isModoEnTransito) {
                        $query->whereRaw('trim(upper(paquetes_contrato.origen)) = trim(upper(?))', [$userCity]);
                        return;
                    }

                    if ($isModoDevolucion) {
                        $query->where(function ($sub) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                            $sub->where(function ($q2) use ($userCity, $estadoAlmacenId) {
                                if ($estadoAlmacenId) {
                                    $q2->where('paquetes_contrato.estados_id', (int) $estadoAlmacenId)
                                        ->whereRaw('trim(upper(paquetes_contrato.origen)) = trim(upper(?))', [$userCity]);
                                } else {
                                    $q2->whereRaw('1 = 0');
                                }
                            })->orWhere(function ($q2) use ($userCity, $estadoRecibidoId) {
                                if ($estadoRecibidoId) {
                                    $q2->where('paquetes_contrato.estados_id', (int) $estadoRecibidoId)
                                        ->whereRaw('trim(upper(paquetes_contrato.destino)) = trim(upper(?))', [$userCity]);
                                } else {
                                    $q2->whereRaw('1 = 0');
                                }
                            });
                        });
                        return;
                    }

                    $query->where(function ($sub) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                        if ($this->almacenEstadoFiltro === 'ALMACEN' && $estadoAlmacenId) {
                            $sub->whereRaw('trim(upper(paquetes_contrato.origen)) = trim(upper(?))', [$userCity]);
                            return;
                        }

                        if ($this->almacenEstadoFiltro === 'RECIBIDO' && $estadoRecibidoId) {
                            $sub->whereRaw('trim(upper(paquetes_contrato.destino)) = trim(upper(?))', [$userCity]);
                            return;
                        }

                        $sub->where(function ($q2) use ($userCity, $estadoAlmacenId) {
                            if ($estadoAlmacenId) {
                                $q2->where('paquetes_contrato.estados_id', (int) $estadoAlmacenId)
                                    ->whereRaw('trim(upper(paquetes_contrato.origen)) = trim(upper(?))', [$userCity]);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        })->orWhere(function ($q2) use ($userCity, $estadoRecibidoId) {
                            if ($estadoRecibidoId) {
                                $q2->where('paquetes_contrato.estados_id', (int) $estadoRecibidoId)
                                    ->whereRaw('trim(upper(paquetes_contrato.destino)) = trim(upper(?))', [$userCity]);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        });
                    });
                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->when($this->filtroServicioId !== '', function ($query) {
                $query->whereRaw('1 = 0');
            });

        $solicitudesQuery = DB::table('solicitud_clientes')
            ->leftJoin('servicio_extras', 'servicio_extras.id', '=', 'solicitud_clientes.servicio_extra_id')
            ->leftJoin('destino', 'destino.id', '=', 'solicitud_clientes.destino_id')
            ->selectRaw("'SOLICITUD' as record_type")
            ->selectRaw('solicitud_clientes.id as record_id')
            ->selectRaw("coalesce(nullif(trim(solicitud_clientes.codigo_solicitud), ''), nullif(trim(solicitud_clientes.barcode), ''), 'SIN CODIGO') as codigo")
            ->selectRaw("'SOLICITUD' as tipo")
            ->selectRaw("coalesce(servicio_extras.descripcion, servicio_extras.nombre, '-') as servicio_especial")
            ->selectRaw("'SOLICITUD CLIENTE' as servicio")
            ->selectRaw("coalesce(solicitud_clientes.ciudad, destino.nombre_destino, '-') as destino")
            ->selectRaw("coalesce(solicitud_clientes.contenido, '-') as contenido")
            ->selectRaw("cast(coalesce(solicitud_clientes.cantidad, 1) as varchar) as cantidad")
            ->selectRaw('coalesce(solicitud_clientes.peso, 0) as peso')
            ->selectRaw("coalesce(solicitud_clientes.nombre_remitente, '-') as remitente")
            ->selectRaw("coalesce(solicitud_clientes.nombre_envia, '-') as nombre_envia")
            ->selectRaw("coalesce(solicitud_clientes.carnet, '-') as carnet")
            ->selectRaw("coalesce(solicitud_clientes.telefono_remitente, '-') as telefono_r")
            ->selectRaw("coalesce(solicitud_clientes.nombre_destinatario, '-') as destinatario")
            ->selectRaw("coalesce(solicitud_clientes.telefono_destinatario, '-') as telefono_d")
            ->selectRaw("coalesce(solicitud_clientes.ciudad, destino.nombre_destino, '-') as ciudad")
            ->selectRaw("coalesce(solicitud_clientes.origen, '-') as origen")
            ->selectRaw("'CLIENTE' as empresa")
            ->selectRaw('solicitud_clientes.cod_especial as cod_especial')
            ->selectRaw('solicitud_clientes.created_at as created_at')
            ->when($isModoEnTransito, function ($query) use ($userCity, $estadoTransitoId) {
                if ($userCity === '' || empty($estadoTransitoId)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where('solicitud_clientes.estado_id', (int) $estadoTransitoId)
                    ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
            }, function ($query) use ($isModoRecibirRegional, $isModoVentanilla, $isModoDevolucion, $estadoRecibirRegionalId, $estadoVentanillaId, $estadoAlmacenId, $estadoRecibidoId, $userCity) {
                if ($isModoRecibirRegional) {
                    if (empty($estadoRecibirRegionalId)) {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->where('solicitud_clientes.estado_id', (int) $estadoRecibirRegionalId);
                    return;
                }

                if ($isModoVentanilla) {
                    if (empty($estadoVentanillaId)) {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->where('solicitud_clientes.estado_id', (int) $estadoVentanillaId);
                    return;
                }

                if ($isModoDevolucion) {
                    if ($userCity === '') {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->where(function ($sub) use ($estadoAlmacenId, $estadoRecibidoId, $estadoVentanillaId, $userCity) {
                        $sub->where(function ($q2) use ($estadoAlmacenId, $userCity) {
                            if ($estadoAlmacenId) {
                                $q2->where('solicitud_clientes.estado_id', (int) $estadoAlmacenId)
                                    ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        })->orWhere(function ($q2) use ($estadoRecibidoId, $userCity) {
                            if ($estadoRecibidoId) {
                                $q2->where('solicitud_clientes.estado_id', (int) $estadoRecibidoId)
                                    ->whereRaw('trim(upper(coalesce(solicitud_clientes.ciudad, destino.nombre_destino))) = trim(upper(?))', [$userCity]);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        })->orWhere(function ($q2) use ($estadoVentanillaId) {
                            if ($estadoVentanillaId) {
                                $q2->where('solicitud_clientes.estado_id', (int) $estadoVentanillaId);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        });
                    });
                    return;
                }

                if ($userCity === '') {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($sub) use ($estadoAlmacenId, $estadoRecibidoId, $userCity) {
                    if ($this->almacenEstadoFiltro === 'ALMACEN' && $estadoAlmacenId) {
                        $sub->where('solicitud_clientes.estado_id', (int) $estadoAlmacenId)
                            ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
                        return;
                    }

                    if ($this->almacenEstadoFiltro === 'RECIBIDO' && $estadoRecibidoId) {
                        $sub->where('solicitud_clientes.estado_id', (int) $estadoRecibidoId)
                            ->whereRaw('trim(upper(coalesce(solicitud_clientes.ciudad, destino.nombre_destino))) = trim(upper(?))', [$userCity]);
                        return;
                    }

                    $sub->where(function ($q2) use ($estadoAlmacenId, $userCity) {
                        if ($estadoAlmacenId) {
                            $q2->where('solicitud_clientes.estado_id', (int) $estadoAlmacenId)
                                ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q2) use ($estadoRecibidoId, $userCity) {
                        if ($estadoRecibidoId) {
                            $q2->where('solicitud_clientes.estado_id', (int) $estadoRecibidoId)
                                ->whereRaw('trim(upper(coalesce(solicitud_clientes.ciudad, destino.nombre_destino))) = trim(upper(?))', [$userCity]);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->when($this->filtroServicioId !== '', function ($query) {
                $query->whereRaw('1 = 0');
            });

        $union = $emsQuery->unionAll($contratosQuery)->unionAll($solicitudesQuery);

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
                          ->orWhere('cantidad', 'like', "%{$q}%")
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

    protected function baseSolicitudesQuery(): Builder
    {
        $q = trim((string) $this->searchQuery);
        $userCity = trim((string) optional(Auth::user())->ciudad);
        $estadoAlmacenId = $this->findEstadoId('ALMACEN');
        $estadoRecibidoId = $this->findEstadoId('RECIBIDO');
        $estadoTransitoId = $this->findEstadoId('TRANSITO');
        $estadoRegionalRecepcionId = $this->resolveRegionalRecepcionEstado()['id'] ?? null;
        $estadoVentanillaId = $this->resolveVentanillaEstado()['id'] ?? null;

        return SolicitudCliente::query()
            ->leftJoin('servicio_extras', 'servicio_extras.id', '=', 'solicitud_clientes.servicio_extra_id')
            ->leftJoin('destino', 'destino.id', '=', 'solicitud_clientes.destino_id')
            ->select([
                'solicitud_clientes.*',
                DB::raw('servicio_extras.nombre as servicio_extra_nombre'),
                DB::raw('servicio_extras.descripcion as servicio_extra_descripcion'),
                DB::raw('destino.nombre_destino as destino_nombre'),
            ])
            ->when($this->isEnTransitoEms, function ($query) use ($userCity, $estadoTransitoId) {
                if ($userCity === '' || empty($estadoTransitoId)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where('solicitud_clientes.estado_id', (int) $estadoTransitoId)
                    ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
            })
            ->when($this->isTransitoEms, function ($query) use ($estadoRegionalRecepcionId) {
                if (empty($estadoRegionalRecepcionId)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where('solicitud_clientes.estado_id', (int) $estadoRegionalRecepcionId);
            })
            ->when($this->isVentanillaEms, function ($query) use ($estadoVentanillaId) {
                if (empty($estadoVentanillaId)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where('solicitud_clientes.estado_id', (int) $estadoVentanillaId);
            })
            ->when($this->isDevolucionEms, function ($query) use ($userCity, $estadoAlmacenId, $estadoRecibidoId, $estadoVentanillaId) {
                if ($userCity === '') {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($sub) use ($estadoAlmacenId, $estadoRecibidoId, $estadoVentanillaId, $userCity) {
                    $sub->where(function ($q2) use ($estadoAlmacenId, $userCity) {
                        if ($estadoAlmacenId) {
                            $q2->where('solicitud_clientes.estado_id', (int) $estadoAlmacenId)
                                ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q2) use ($estadoRecibidoId, $userCity) {
                        if ($estadoRecibidoId) {
                            $q2->where('solicitud_clientes.estado_id', (int) $estadoRecibidoId)
                                ->whereRaw('trim(upper(coalesce(solicitud_clientes.ciudad, destino.nombre_destino))) = trim(upper(?))', [$userCity]);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q2) use ($estadoVentanillaId) {
                        if ($estadoVentanillaId) {
                            $q2->where('solicitud_clientes.estado_id', (int) $estadoVentanillaId);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->when($this->isAlmacenEms, function ($query) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                if ($userCity === '') {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($sub) use ($estadoAlmacenId, $estadoRecibidoId, $userCity) {
                    if ($this->almacenEstadoFiltro === 'ALMACEN' && $estadoAlmacenId) {
                        $sub->where('solicitud_clientes.estado_id', (int) $estadoAlmacenId)
                            ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
                        return;
                    }

                    if ($this->almacenEstadoFiltro === 'RECIBIDO' && $estadoRecibidoId) {
                        $sub->where('solicitud_clientes.estado_id', (int) $estadoRecibidoId)
                            ->whereRaw('trim(upper(coalesce(solicitud_clientes.ciudad, destino.nombre_destino))) = trim(upper(?))', [$userCity]);
                        return;
                    }

                    $sub->where(function ($q2) use ($estadoAlmacenId, $userCity) {
                        if ($estadoAlmacenId) {
                            $q2->where('solicitud_clientes.estado_id', (int) $estadoAlmacenId)
                                ->whereRaw('trim(upper(solicitud_clientes.origen)) = trim(upper(?))', [$userCity]);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($q2) use ($estadoRecibidoId, $userCity) {
                        if ($estadoRecibidoId) {
                            $q2->where('solicitud_clientes.estado_id', (int) $estadoRecibidoId)
                                ->whereRaw('trim(upper(coalesce(solicitud_clientes.ciudad, destino.nombre_destino))) = trim(upper(?))', [$userCity]);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->when(!$this->isAlmacenEms && !$this->isTransitoEms && !$this->isEnTransitoEms && !$this->isVentanillaEms && !$this->isDevolucionEms, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('solicitud_clientes.codigo_solicitud', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.barcode', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.cod_especial', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.origen', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.contenido', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.nombre_remitente', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.nombre_destinatario', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.telefono_remitente', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.telefono_destinatario', 'like', "%{$q}%")
                        ->orWhere('solicitud_clientes.ciudad', 'like', "%{$q}%")
                        ->orWhere('servicio_extras.nombre', 'like', "%{$q}%")
                        ->orWhere('servicio_extras.descripcion', 'like', "%{$q}%")
                        ->orWhere('destino.nombre_destino', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('solicitud_clientes.id');
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
        } elseif ($this->isDevolucionEms) {
            $estadoAlmacen = $this->findEstadoId('ALMACEN');
            $estadoRecibido = $this->findEstadoId('RECIBIDO');
            $estadoVentanillaId = $this->resolveVentanillaEstado()['id'] ?? null;
            foreach ([$estadoAlmacen, $estadoRecibido, $estadoVentanillaId] as $estadoId) {
                if ($estadoId) {
                    $estadoIds[] = $estadoId;
                }
            }
        } else {
            $estadoAdmision = $this->findEstadoId('ADMISIONES');
            if ($estadoAdmision) {
                $estadoIds[] = $estadoAdmision;
            }
        }

        $estadoAlmacenId = null;
        $estadoRecibidoId = null;
        if ($this->isAlmacenEms || $this->isDevolucionEms) {
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
            ->when($this->isDevolucionEms && $userCity !== '', function ($query) use ($userCity, $estadoAlmacenId, $estadoRecibidoId) {
                $estadoVentanillaId = $this->resolveVentanillaEstado()['id'] ?? null;

                $query->where(function ($sub) use ($userCity, $estadoAlmacenId, $estadoRecibidoId, $estadoVentanillaId) {
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
                    })->orWhere(function ($q) use ($estadoVentanillaId) {
                        if ($estadoVentanillaId) {
                            $q->where('paquetes_ems.estado_id', (int) $estadoVentanillaId);
                        } else {
                            $q->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->when($userCity === '' && ($this->isAlmacenEms || $this->isDevolucionEms), function ($query) {
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

    protected function resolveTarifarioTiktokerYPrecio(int $servicioExtraId, string $origen, int $destinoId, float $peso, bool $pagoDestinatario = false): array
    {
        $origenNombre = strtoupper(trim($origen));

        $origenId = (int) (Origen::query()
            ->whereRaw('trim(upper(nombre_origen)) = ?', [$origenNombre])
            ->value('id') ?? 0);

        if ($origenId <= 0) {
            throw new \RuntimeException('No existe el origen ' . $origenNombre . ' en la tabla origen.');
        }

        $tarifario = TarifarioTiktoker::query()
            ->where('origen_id', $origenId)
            ->where('destino_id', $destinoId)
            ->where('servicio_extra_id', $servicioExtraId)
            ->first();

        if (!$tarifario) {
            throw new \RuntimeException('No existe tarifario tiktoker para el servicio, origen y destino seleccionados.');
        }

        return [$tarifario, $this->calculatePrecioTiktokerInterno($tarifario, $peso, $pagoDestinatario)];
    }

    protected function calculatePrecioTiktokerInterno(TarifarioTiktoker $tarifario, float $peso, bool $pagoDestinatario = false): float
    {
        if ($peso <= 0.500) {
            $precioBase = (float) $tarifario->peso1;
        } elseif ($peso <= 2.000) {
            $precioBase = (float) $tarifario->peso2;
        } elseif ($peso <= 5.000) {
            $precioBase = (float) $tarifario->peso3;
        } else {
            $bloquesExtra = (int) ceil($peso - 5);
            $precioBase = (float) $tarifario->peso3 + ($bloquesExtra * (float) $tarifario->peso_extra);
        }

        if ($pagoDestinatario) {
            $precioBase += 2.50;
        }

        return round($precioBase, 2);
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
        if (!$this->isAdmision && !$this->isCreateEms) {
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

    protected function registerEventosContrato(iterable $paquetes, int $userId, int $eventoId): void
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

        DB::table('eventos_contrato')->insert($rows);
    }

    protected function registerEventosTiktoker(iterable $solicitudes, int $userId, int $eventoId): void
    {
        if ($userId <= 0 || $eventoId <= 0) {
            return;
        }

        $now = now();

        $rows = collect($solicitudes)
            ->map(function ($solicitud) use ($eventoId, $userId, $now) {
                $codigo = trim((string) ($solicitud->codigo_solicitud ?? $solicitud->barcode ?? ''));
                if ($codigo === '') {
                    return null;
                }

                return [
                    'codigo' => $codigo,
                    'evento_id' => (int) $eventoId,
                    'user_id' => $userId,
                    'cliente_id' => null,
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

        DB::table('eventos_tiktoker')->insert($rows);
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

        if ($name === 'preregistro_codigo') {
            $this->applyPreregistroAutofill((string) $value);
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

        if ($name === 'servicio_especial') {
            $this->applyTarifarioMatch();
        }

        if ($name === 'telefono_destinatario') {
            $this->applyTarifarioMatch();
        }

        if ($name === 'auto_codigo') {
            if ($this->auto_codigo) {
                $this->codigo = $this->generateCodigo();
            }
        }

        if ($name === 'carnet') {
            $this->refreshRemitenteSugerencias((string) $value);
            $this->applyRegisteredRemitenteByCarnet((string) $value);
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
        $this->precio = $this->calculatePrecioFinal((float) $tarifario->precio);
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
        if ($this->usesEmsCodePrefix($name)) {
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

    protected function usesEmsCodePrefix(string $serviceName): bool
    {
        return in_array(strtoupper(trim($serviceName)), self::EMS_CODE_SERVICE_NAMES, true);
    }

    protected function hasTelefonoDestinatarioRecargo(): bool
    {
        return trim((string) $this->telefono_destinatario) !== '';
    }

    protected function calculatePrecioFinal(float $basePrice): float
    {
        $price = round($basePrice, 2);

        if ($this->servicio_especial === 'IDA Y VUELTA') {
            $price = round($price * 2, 2);
        }

        if (! $this->hasTelefonoDestinatarioRecargo()) {
            return $price;
        }

        return round($price + self::TELEFONO_DESTINATARIO_RECARGO, 2);
    }

    protected function getCodigoSuffix(): string
    {
        if ($this->isAlmacenEms) {
            return 'BC';
        }

        return $this->resolveOriginIsoSuffix();
    }

    protected function resolveOriginIsoSuffix(): string
    {
        $candidates = [
            strtoupper(trim((string) $this->origen)),
            strtoupper(trim((string) optional(Auth::user())->ciudad)),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            foreach (self::SPECIAL_CODE_PREFIX_BY_CITY as $city => $isoCode) {
                if ($candidate === $city || str_contains($candidate, $city)) {
                    return $isoCode;
                }
            }
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

        if ($this->isEnTransitoEms) {
            $this->estado_id = $this->resolveRegionalEstado()['id'] ?? null;
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

        if ($this->isDevolucionEms) {
            $this->estado_id = $this->findEstadoId('DEVOLUCION');
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

    protected function findEventoIdByName(string $nombreEvento): ?int
    {
        $id = DB::table('eventos')
            ->whereRaw('trim(upper(nombre_evento)) = ?', [strtoupper(trim($nombreEvento))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function devolucionEstadoIdsContrato(): array
    {
        return collect([
            $this->findEstadoId('ALMACEN'),
            $this->findEstadoId('RECIBIDO'),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    protected function storeDevolucionImage(?TemporaryUploadedFile $image): ?string
    {
        if (!$image) {
            return null;
        }

        return (string) $image->store('carteros/entregas', 'public');
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

    protected function applyPreregistroAutofill(string $codigo): void
    {
        if ($this->editingId) {
            return;
        }

        $codigo = $this->normalizePreregistroCode($codigo);
        $this->preregistro_codigo = $codigo;

        if ($codigo === '') {
            $this->preregistroAutofillMessage = '';
            return;
        }

        $preregistro = Preregistro::query()
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('trim(upper(COALESCE(codigo_preregistro, \'\'))) = ?', [$codigo])
                    ->orWhereRaw('trim(upper(COALESCE(codigo_generado, \'\'))) = ?', [$codigo]);
            })
            ->first();

        if (!$preregistro) {
            $this->preregistroAutofillMessage = 'No existe un preregistro con ese codigo.';
            return;
        }

        if (strtoupper(trim((string) $preregistro->estado)) === 'VALIDADO') {
            $this->preregistroAutofillMessage = 'Ese preregistro ya fue validado y convertido en EMS.';
            return;
        }

        $this->origen = (string) $preregistro->origen;
        $this->tipo_correspondencia = (string) ($preregistro->tipo_correspondencia ?? '');
        $this->servicio_especial = (string) ($preregistro->servicio_especial ?? '');
        $this->contenido = (string) $preregistro->contenido;
        $this->cantidad = (string) $preregistro->cantidad;
        $this->peso = (string) $preregistro->peso;
        $this->nombre_remitente = (string) $preregistro->nombre_remitente;
        $this->nombre_envia = (string) ($preregistro->nombre_envia ?? '');
        $this->carnet = (string) $preregistro->carnet;
        $this->telefono_remitente = (string) ($preregistro->telefono_remitente ?? '');
        $this->nombre_destinatario = (string) $preregistro->nombre_destinatario;
        $this->telefono_destinatario = (string) ($preregistro->telefono_destinatario ?? '');
        $this->direccion = (string) $preregistro->direccion;
        $this->ciudad = (string) $preregistro->ciudad;
        $this->servicio_id = (string) $preregistro->servicio_id;
        $this->destino_id = (string) $preregistro->destino_id;
        $this->auto_codigo = true;

        $this->refreshEmsState();
        $this->applyTarifarioMatch();

        if ($this->auto_codigo) {
            $this->codigo = $this->generateCodigo();
        }

        $this->preregistroAutofillMessage = 'Datos del preregistro ' . $codigo . ' cargados correctamente.';
    }

    protected function linkPreregistroToPaquete(PaqueteEms $paquete, int $userId): void
    {
        $codigo = $this->normalizePreregistroCode((string) $this->preregistro_codigo);
        if ($codigo === '' || !$paquete->exists || $userId <= 0) {
            return;
        }

        $preregistro = Preregistro::query()
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('trim(upper(COALESCE(codigo_preregistro, \'\'))) = ?', [$codigo])
                    ->orWhereRaw('trim(upper(COALESCE(codigo_generado, \'\'))) = ?', [$codigo]);
            })
            ->lockForUpdate()
            ->first();

        if (!$preregistro || strtoupper(trim((string) $preregistro->estado)) === 'VALIDADO') {
            return;
        }

        $preregistro->update([
            'estado' => 'VALIDADO',
            'validado_por' => $userId,
            'validado_at' => now(),
            'paquete_ems_id' => (int) $paquete->id,
            'codigo_generado' => (string) $paquete->codigo,
        ]);
    }

    protected function normalizePreregistroCode(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return '';
        }

        if (preg_match('/^\d{1,8}$/', $codigo) === 1) {
            return 'PRE' . str_pad($codigo, 8, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^PRE\d{1,8}$/', $codigo) === 1) {
            return 'PRE' . str_pad(substr($codigo, 3), 8, '0', STR_PAD_LEFT);
        }

        return $codigo;
    }

    protected function nextSpecialCodeForLoggedUser(): string
    {
        $prefix = $this->resolveSpecialCodePrefixForLoggedUser();
        $specialCodes = PaqueteEms::query()
            ->whereNotNull('cod_especial')
            ->where('cod_especial', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('cod_especial')
            ->merge(
                RecojoContrato::query()
                    ->whereNotNull('cod_especial')
                    ->where('cod_especial', 'like', $prefix . '%')
                    ->lockForUpdate()
                    ->pluck('cod_especial')
            )->merge(
                SolicitudCliente::query()
                    ->whereNotNull('cod_especial')
                    ->where('cod_especial', 'like', $prefix . '%')
                    ->lockForUpdate()
                    ->pluck('cod_especial')
            );

        if ($specialCodes->isEmpty()) {
            return $prefix . '00001';
        }

        $maxCorrelative = 0;
        foreach ($specialCodes as $specialCode) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{5})$/', (string) $specialCode, $matches)) {
                $value = (int) $matches[1];
                if ($value > $maxCorrelative) {
                    $maxCorrelative = $value;
                }
            }
        }

        return $prefix . str_pad((string) ($maxCorrelative + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function registrarBitacoraPorCodEspecial(
        string $codEspecial,
        Collection $paquetes,
        Collection $contratos,
        int $userId,
        ?string $transportadora = null,
        ?string $provincia = null
    ): void {
        $codEspecial = strtoupper(trim($codEspecial));
        if ($codEspecial === '' || ($paquetes->isEmpty() && $contratos->isEmpty()) || $userId <= 0) {
            return;
        }

        $totales = $this->obtenerTotalesPorCodEspecial($codEspecial);
        $rows = [];
        $now = now();
        $transportadora = ($transportadora !== null && trim($transportadora) !== '') ? trim($transportadora) : null;
        $provincia = ($provincia !== null && trim($provincia) !== '') ? trim($provincia) : null;
        $precioTotal = $totales['precio_total'] > 0 ? $totales['precio_total'] : null;

        foreach ($paquetes as $paquete) {
            $rows[] = [
                'paquetes_ems_id' => (int) $paquete->id,
                'paquetes_contrato_id' => null,
                'user_id' => $userId,
                'cod_especial' => $codEspecial,
                'transportadora' => $transportadora,
                'provincia' => $provincia,
                'factura' => null,
                'precio_total' => $precioTotal,
                'peso' => $totales['peso'],
                'imagen_factura' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($contratos as $contrato) {
            $rows[] = [
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => (int) $contrato->id,
                'user_id' => $userId,
                'cod_especial' => $codEspecial,
                'transportadora' => $transportadora,
                'provincia' => $provincia,
                'factura' => null,
                'precio_total' => $precioTotal,
                'peso' => $totales['peso'],
                'imagen_factura' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            Bitacora::query()->insert($rows);
        }
    }

    protected function obtenerTotalesPorCodEspecial(string $codEspecial): array
    {
        $codigoNormalizado = strtoupper(trim($codEspecial));

        $pesoEms = (float) PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigoNormalizado])
            ->sum('peso');

        $pesoContrato = (float) RecojoContrato::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigoNormalizado])
            ->sum('peso');

        $precioEms = (float) PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigoNormalizado])
            ->sum('precio');

        $precioContrato = (float) RecojoContrato::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigoNormalizado])
            ->sum('precio');

        $pesoSolicitud = (float) SolicitudCliente::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigoNormalizado])
            ->sum('peso');

        $precioSolicitud = (float) SolicitudCliente::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigoNormalizado])
            ->sum('precio');

        return [
            'peso' => round($pesoEms + $pesoContrato + $pesoSolicitud, 3),
            'precio_total' => round($precioEms + $precioContrato + $precioSolicitud, 2),
        ];
    }

    protected function resolveSpecialCodePrefixForLoggedUser(): string
    {
        $user = Auth::user();
        $candidates = [
            strtoupper(trim((string) optional($user)->ciudad)),
            strtoupper(trim((string) optional($user)->name)),
            strtoupper(trim((string) $this->origen)),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            foreach (self::SPECIAL_CODE_PREFIX_BY_CITY as $key => $prefix) {
                if ($candidate === $key || str_contains($candidate, $key)) {
                    return $prefix;
                }
            }
        }

        return 'EMS';
    }

    protected function regionalEligibleEstadoIds(): array
    {
        $estadoIds = [
            $this->findEstadoId('SOLICITUD'),
            $this->findEstadoId('ALMACEN'),
            $this->findEstadoId('RECIBIDO'),
        ];

        return collect($estadoIds)
            ->filter(fn ($id) => !empty($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function refreshRemitenteSugerencias(string $value): void
    {
        $term = trim($value);
        if ($term === '') {
            $this->remitenteSugerencias = [];
            $this->autofillMessage = '';
            return;
        }

        $this->remitenteSugerencias = RemitenteEms::query()
            ->where('carnet', 'like', '%' . $term . '%')
            ->orderBy('carnet')
            ->limit(10)
            ->pluck('carnet')
            ->unique()
            ->values()
            ->all();
    }

    protected function applyRegisteredRemitenteByCarnet(string $value): void
    {
        $carnet = trim($value);
        if ($carnet === '') {
            $this->autofillMessage = '';
            return;
        }

        $remitente = RemitenteEms::query()
            ->whereRaw('trim(upper(carnet)) = trim(upper(?))', [$carnet])
            ->orderByDesc('updated_at')
            ->first();

        if (!$remitente) {
            $this->autofillMessage = '';
            return;
        }

        $this->nombre_remitente = $remitente->nombre_remitente;
        $this->telefono_remitente = $remitente->telefono_remitente;
        $this->carnet = $remitente->carnet;
        $this->nombre_envia = $remitente->nombre_envia;
        $this->autofillMessage = 'Datos del remitente autocompletados por carnet.';

        $this->applyLastFormularioDataByCarnet($carnet);
    }

    protected function applyLastFormularioDataByCarnet(string $carnet): void
    {
        if ($this->editingId) {
            return;
        }

        $formulario = PaqueteEmsFormulario::query()
            ->whereRaw('trim(upper(carnet)) = trim(upper(?))', [$carnet])
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

        $this->autofillMessage = 'Se recuperaron tambien los ultimos datos usados con ese carnet.';
        $this->applyTarifarioMatch();

        if ($this->auto_codigo) {
            $this->codigo = $this->generateCodigo();
        }
    }
}
