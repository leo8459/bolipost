<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\ServicioContrato;
use App\Models\TarifaContrato;
use App\Models\TarifarioContrato;
use App\Services\TarifaContratoDetallePdf;
use App\Services\TarifarioContratoService;
use App\Support\AclPermissionRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TarifaContratoController extends Controller
{
    /**
     * Familias que se presentan en las cinco tablas del reporte.
     * Las modalidades terrestre y aerea se conservan en la columna Servicio.
     */
    private const GRUPOS_SERVICIO_REPORTE = [
        [
            'titulo' => 'Envío nacional regular',
            'servicios' => [
                'ENVIO NACIONAL (REGULAR)',
                'ENVIO NACIONAL (REGULAR) TERRESTRE',
                'ENVIO NACIONAL (REGULAR) AEREO',
            ],
        ],
        [
            'titulo' => 'Envío nacional express',
            'servicios' => [
                'ENVIO NACIONAL (EXPRESS)',
                'ENVIO NACIONAL (EXPRESS) TERRESTRE',
                'ENVIO NACIONAL (EXPRESS) AEREO',
            ],
        ],
        [
            'titulo' => 'Envío local regular',
            'servicios' => ['ENVIO LOCAL(REGULAR)'],
        ],
        [
            'titulo' => 'Envío local express',
            'servicios' => ['ENVIO LOCAL(EXPRESS)'],
        ],
        [
            'titulo' => 'Envío interprovincial',
            'servicios' => [
                'ENVIO INTERPROVINCIAL',
                'ENVIO INTERPROVINCIAL TERRESTRE',
                'ENVIO INTERPROVINCIAL AEREO',
            ],
        ],
    ];

    private const DEPARTAMENTOS = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];

    private const PROVINCIAS_POR_DEPARTAMENTO = [
        'LA PAZ' => [
            'MURILLO', 'OMASUYOS', 'PACAJES', 'CAMACHO', 'MUNECAS', 'LARECAJA',
            'FRANZ TAMAYO', 'INGAVI', 'LOAYZA', 'INQUISIVI', 'SUD YUNGAS',
            'LOS ANDES', 'AROMA', 'NOR YUNGAS', 'ABEL ITURRALDE',
            'BAUTISTA SAAVEDRA', 'MANCO KAPAC', 'GUALBERTO VILLARROEL',
            'JOSE MANUEL COBIJA', 'CARANAVI',
        ],
        'COCHABAMBA' => [
            'CERCADO', 'CAMPERO', 'AYOPAYA', 'ESTEBAN ARCE', 'ARANI', 'ARQUE',
            'CAPINOTA', 'GERMAN JORDAN', 'QUILLACOLLO', 'CHAPARE', 'TAPACARI',
            'CARRASCO', 'MIZQUE', 'PUNATA', 'BOLIVAR', 'TIRAQUE',
        ],
        'SANTA CRUZ' => [
            'ANDRES IBANEZ', 'WARNES', 'VALLEGRANDE', 'ICHILO', 'CHIQUITOS',
            'SARA', 'CORDILLERA', 'FLORIDA', 'MANUEL MARIA CABALLERO',
            'GUARAYOS', 'NUFLO DE CHAVEZ', 'VELASCO', 'ANGEL SANDOVAL',
            'GERMAN BUSCH',
        ],
        'ORURO' => [
            'CERCADO', 'CARANGAS', 'SAUCARI', 'SABAYA', 'LADISLAO CABRERA',
            'LITORAL', 'POOPO', 'PANTALEON DALENCE', 'SAJAMA',
            'SAN PEDRO DE TOTORA', 'SEBASTIAN PAGADOR', 'EDUARDO AVAROA',
            'NOR CARANGAS', 'SUR CARANGAS', 'TOMAS BARRON',
        ],
        'POTOSI' => [
            'TOMAS FRIAS', 'RAFAEL BUSTILLO', 'CORNELIO SAAVEDRA', 'CHAYANTA',
            'CHARCAS', 'NOR CHICHAS', 'ALONSO DE IBANEZ', 'SUD CHICHAS',
            'NOR LIPEZ', 'SUD LIPEZ', 'JOSE MARIA LINARES', 'ANTONIO QUIJARRO',
            'DANIEL CAMPOS', 'MODESTO OMISTE', 'BILBAO RIOJA', 'ENRIQUE BALDIVIESO',
        ],
        'TARIJA' => [
            'CERCADO', 'ANICETO ARCE', 'BURDETT OCONNOR', 'GRAN CHACO',
            'JOSE MARIA AVILES', 'MENDEZ',
        ],
        'SUCRE' => [
            'OROPEZA', 'AZURDUY', 'ZUDANEZ', 'TOMINA', 'HERNANDO SILES',
            'YAMPARAEZ', 'NOR CINTI', 'SUD CINTI', 'BELISARIO BOETO', 'LUIS CALVO',
        ],
        'TRINIDAD' => [
            'CERCADO', 'VACA DIEZ', 'JOSE BALLIVIAN', 'YACUMA', 'MOXOS',
            'MAMORE', 'MARBAN', 'ITENE',
        ],
        'COBIJA' => [
            'NICOLAS SUAREZ', 'MANURIPI', 'MADRE DE DIOS', 'ABUNA', 'FEDERICO ROMAN',
        ],
    ];

    private const IMPORT_COLUMNS = [
        'origen',
        'destino',
        'servicio',
        'kilo',
        'kilo_extra',
        'horas_entrega',
        'direccion',
        'zona',
        'provincia_origen',
        'provincia_destino',
        'retencion',
    ];

    private const IMPORT_COLUMN_LABELS = [
        'origen' => 'Origen *',
        'destino' => 'Destino *',
        'servicio' => 'Servicio *',
        'kilo' => 'Primer kilogramo: precio (Bs) *',
        'kilo_extra' => 'Kilogramo extra: precio (Bs) *',
        'horas_entrega' => 'Entrega (horas) *',
        'direccion' => 'Dirección (opcional)',
        'zona' => 'Zona (subservicio local)',
        'provincia_origen' => 'Provincia origen',
        'provincia_destino' => 'Provincia destino',
        'retencion' => 'Retención (%) (opcional)',
    ];

    private const REQUIRED_IMPORT_COLUMNS = [
        'origen',
        'destino',
        'servicio',
        'kilo',
        'kilo_extra',
        'horas_entrega',
    ];

    private const FORM_FIELDS = [
        'tarifario_contrato_id',
        'origen',
        'destino',
        'servicio',
        'direccion',
        'zona',
        'peso',
        'kilo',
        'kilo_extra',
        'provincia',
        'provincia_origen',
        'retencion',
        'horas_entrega',
    ];

    private const MAX_FILAS_MANUALES = 50;

    public function servicios(Request $request)
    {
        abort_unless(auth()->user(), 403);
        $request->validate(['anio' => ['nullable', Rule::in([...range(2000, 2100), 'sin-anio'])], 'q' => ['nullable', 'string', 'max:255']]);
        $anio = $request->input('anio') === 'sin-anio' ? null : (int) $request->input('anio', now()->year);
        $q = trim((string) $request->input('q', ''));
        if ($request->expectsJson()) {
            return response()->json(['anio' => $anio, 'servicios' => ServicioContrato::deAnio($anio)->orderBy('nombre')->pluck('nombre')->values()]);
        }

        return view('tarifa_contrato.servicios', [
            'anio' => $anio, 'q' => $q,
            'anios' => ServicioContrato::query()->distinct()->pluck('anio')->filter()->push(now()->year, now()->year + 1)->unique()->sortDesc(),
            'servicios' => ServicioContrato::deAnio($anio)->withCount('tarifas')
                ->when($q !== '', fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($q).'%']))
                ->orderBy('nombre')->get(),
        ]);
    }

    public function guardarServicio(Request $request)
    {
        $this->authorizeTarifaContratoButtonAction('save');
        $data = $this->validarServicio($request);
        ServicioContrato::query()->create($data);

        return redirect()->route('tarifa-contrato.servicios', ['anio' => $data['anio']])->with('success', 'Servicio añadido al año seleccionado.');
    }

    public function actualizarServicio(Request $request, ServicioContrato $servicio)
    {
        $this->authorizeTarifaContratoButtonAction('save');
        $data = $this->validarServicio($request, $servicio->id);
        DB::transaction(function () use ($servicio, $data) {
            $servicio = ServicioContrato::query()->lockForUpdate()->findOrFail($servicio->id);
            if ($servicio->tarifas()->exists()) {
                throw ValidationException::withMessages(['nombre' => 'El servicio ya está relacionado con tarifas. Añade otro servicio para conservar el historial.']);
            }
            $servicio->update($data);
        });

        return redirect()->route('tarifa-contrato.servicios', ['anio' => $data['anio']])->with('success', 'Servicio actualizado.');
    }

    public function eliminarServicio(ServicioContrato $servicio)
    {
        $this->authorizeTarifaContratoButtonAction('delete');
        $anio = $servicio->anio;
        DB::transaction(function () use ($servicio) {
            $servicio = ServicioContrato::query()->lockForUpdate()->findOrFail($servicio->id);
            if ($servicio->tarifas()->exists()) {
                throw ValidationException::withMessages(['nombre' => 'No se puede eliminar un servicio que tiene tarifas relacionadas.']);
            }
            $servicio->delete();
        });

        return redirect()->route('tarifa-contrato.servicios', ['anio' => $anio ?? 'sin-anio'])->with('success', 'Servicio eliminado.');
    }

    public function copiarServicios(Request $request)
    {
        $this->authorizeTarifaContratoButtonAction('save');
        $data = $request->validate([
            'copiar_de_anio' => ['required', Rule::in([...range(2000, 2100), 'sin-anio'])],
            'anio' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $origen = $data['copiar_de_anio'] === 'sin-anio' ? null : (int) $data['copiar_de_anio'];
        DB::transaction(function () use ($data, $origen) {
            $services = ServicioContrato::deAnio($origen)->orderBy('id')->lockForUpdate()->get();
            if ($services->isEmpty()) {
                throw ValidationException::withMessages(['copiar_de_anio' => 'El año de origen no tiene servicios registrados.']);
            }
            foreach ($services as $service) ServicioContrato::asegurar($service->nombre, (int) $data['anio']);
        });

        return redirect()->route('tarifa-contrato.servicios', ['anio' => $data['anio']])->with('success', 'Servicios copiados. Los del año anterior se conservan.');
    }

    private function validarServicio(Request $request, ?int $id = null): array
    {
        $request->validate(['anio' => ['required', 'integer', 'between:2000,2100']]);
        if (is_string($request->input('nombre'))) {
            $request->merge(['nombre' => ServicioContrato::normalizarNombre($request->input('nombre'))]);
        }

        return $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('servicios_contrato')->where('anio', $request->integer('anio'))->ignore($id)],
            'anio' => ['required', 'integer', 'between:2000,2100'],
        ]);
    }

    public function tarifarios()
    {
        abort_unless(auth()->user(), 403);

        return view('tarifa_contrato.tarifarios', [
            'tarifarios' => TarifarioContrato::query()->withCount(['tarifas', 'empresasActivas'])->withExists('empresas')
                ->with(['empresaTitular:id,nombre,codigo_cliente', 'empresasActivas:id,nombre'])
                ->orderByRaw('anio IS NULL')->orderByDesc('anio')->orderBy('nombre')->get(),
            'empresas' => Empresa::query()->with(['tarifariosContrato' => fn ($query) => $query->orderByDesc('asignado_en')])
                ->orderBy('nombre')->get(),
        ]);
    }

    public function guardarTarifario(Request $request)
    {
        $this->authorizeTarifaContratoButtonAction('save');
        $data = $request->validate([
            'empresa_titular_id' => ['required', 'integer', Rule::exists('empresa', 'id')],
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'copiar_de_id' => ['nullable', 'integer', Rule::exists('tarifarios_contrato', 'id')],
        ], [
            'empresa_titular_id.required' => 'Selecciona la empresa titular.',
            'empresa_titular_id.exists' => 'Selecciona una empresa registrada.',
        ]);
        $tarifario = DB::transaction(function () use ($data) {
            $source = ! empty($data['copiar_de_id'])
                ? TarifarioContrato::query()->lockForUpdate()->findOrFail($data['copiar_de_id']) : null;
            $empresa = Empresa::query()->lockForUpdate()->findOrFail($data['empresa_titular_id']);
            $this->validarTarifarioUnico($empresa->id, $empresa->nombre, (int) $data['anio']);
            $tarifario = TarifarioContrato::query()->create([
                'empresa_titular_id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'anio' => $data['anio'],
            ]);
            $source?->tarifas()->chunkById(200, function ($tarifas) use ($tarifario) {
                foreach ($tarifas as $tarifa) {
                    ServicioContrato::asegurar($tarifa->servicio, $tarifario->anio);
                    $copy = $tarifa->replicate(['empresa_id']);
                    $copy->tarifario_contrato_id = $tarifario->id;
                    $copy->save();
                }
            });

            return $tarifario;
        });

        return redirect()->route('tarifa-contrato.index', ['tarifario_id' => $tarifario->id])
            ->with('success', "Tarifario de {$tarifario->nombre} creado. Carga los precios y aprueba su uso cuando estén listos.");
    }

    public function actualizarTarifario(Request $request, TarifarioContrato $tarifario)
    {
        $this->authorizeTarifaContratoButtonAction('save');
        $data = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'empresa_titular_id' => $tarifario->publicado_en
                ? ['prohibited']
                : ['required', 'integer', Rule::exists('empresa', 'id')],
        ], [
            'empresa_titular_id.required' => 'Selecciona la empresa titular.',
            'empresa_titular_id.exists' => 'Selecciona una empresa registrada.',
            'empresa_titular_id.prohibited' => 'La empresa titular de un tarifario aprobado se conserva.',
        ]);
        DB::transaction(function () use ($tarifario, $data) {
            $tarifario = TarifarioContrato::query()->lockForUpdate()->findOrFail($tarifario->id);
            if ($tarifario->publicado_en && isset($data['empresa_titular_id'])) {
                throw ValidationException::withMessages(['empresa_titular_id' => 'La empresa titular de un tarifario aprobado se conserva.']);
            }
            $empresaId = $data['empresa_titular_id'] ?? $tarifario->empresa_titular_id;
            $empresa = $empresaId ? Empresa::query()->lockForUpdate()->findOrFail($empresaId) : null;
            $nombre = $empresa?->nombre ?? $tarifario->nombre;
            $this->validarTarifarioUnico($empresa?->id, $nombre, (int) $data['anio'], $tarifario->id);

            if ($tarifario->anio !== (int) $data['anio']) {
                $tarifario->tarifas()->select(['id', 'servicio'])->chunkById(200, function ($tarifas) use ($data) {
                    foreach ($tarifas as $tarifa) {
                        $servicio = ServicioContrato::asegurar($tarifa->servicio, (int) $data['anio']);
                        DB::table('tarifa_contrato')->where('id', $tarifa->id)->update(['servicio_contrato_id' => $servicio->id]);
                    }
                });
            }
            $tarifario->update([
                'empresa_titular_id' => $empresa?->id,
                'nombre' => $nombre,
                'anio' => $data['anio'],
            ]);
        });

        return redirect()->route('tarifa-contrato.tarifarios')->with('success', 'Datos del tarifario actualizados.');
    }

    private function validarTarifarioUnico(?int $empresaId, string $nombre, int $anio, ?int $exceptoId = null): void
    {
        $existe = TarifarioContrato::query()->where('empresa_titular_id', $empresaId)->where('anio', $anio)
            ->when($empresaId === null, fn ($query) => $query->where('nombre', $nombre))
            ->when($exceptoId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();
        if ($existe) {
            throw ValidationException::withMessages(['anio' => $empresaId
                ? 'La empresa ya tiene un tarifario para ese año. Ábrelo desde Tarifarios registrados.'
                : 'Ya existe un tarifario con ese nombre y año.']);
        }
    }

    public function aprobarTarifario(TarifarioContrato $tarifario)
    {
        $this->authorizeTarifaContratoButtonAction('save');
        app(TarifarioContratoService::class)->aprobar($tarifario->id);

        $empresa = $tarifario->refresh()->empresaTitular;

        return redirect()->route('tarifa-contrato.tarifarios')->with('success', match (true) {
            $empresa && $tarifario->empresasActivas()->whereKey($empresa->id)->exists() =>
                "Tarifario aprobado para {$empresa->nombre}. Las nuevas guías usarán estos precios.",
            $empresa !== null => 'Este tarifario ya estaba aprobado. Consulta su asignación en Tarifarios registrados.',
            default => 'Tarifario aprobado para uso. Sus precios están protegidos y ya puede asignarse a empresas.',
        });
    }

    public function asignarTarifario(Request $request, TarifarioContrato $tarifario)
    {
        $this->authorizeTarifaContratoButtonAction('save');
        $data = $request->validate([
            'empresas' => ['required', 'array', 'min:1'],
            'empresas.*' => ['required', 'integer', 'distinct', Rule::exists('empresa', 'id')],
        ]);
        app(TarifarioContratoService::class)->asignar($tarifario->id, $data['empresas']);

        return redirect()->route('tarifa-contrato.tarifarios')
            ->with('success', 'Tarifario asignado. Los nuevos envíos usarán estos precios; los anteriores conservan su tarifa.');
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $servicio = strtoupper(trim((string) $request->query('servicio', '')));
        $origen = $this->normalizeDepartamento($request->query('origen', ''));
        $destino = $this->normalizeDepartamento($request->query('destino', ''));
        $empresaId = (int) $request->query('empresa_id', 0);
        $tarifarioId = (int) $request->query('tarifario_id', 0);

        $empresaId = $empresaId > 0 ? $empresaId : 0;

        $serviciosFiltro = TarifaContrato::query()
            ->select('servicio')
            ->whereNotNull('servicio')
            ->whereRaw("trim(servicio) <> ''")
            ->distinct()
            ->orderBy('servicio')
            ->pluck('servicio');

        $empresasFiltro = Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla']);

        $tarifas = TarifaContrato::query()
            ->with(['tarifario.empresasActivas', 'tarifario.empresaTitular:id,nombre,codigo_cliente'])
            ->when($tarifarioId > 0, fn ($query) => $query->where('tarifario_contrato_id', $tarifarioId))
            ->when($empresaId > 0, function ($query) use ($empresaId) {
                $query->paraEmpresa($empresaId);
            })
            ->when($servicio !== '', function ($query) use ($servicio) {
                $query->whereRaw('trim(upper(servicio)) = ?', [$servicio]);
            })
            ->when($origen !== '', function ($query) use ($origen) {
                $query->whereRaw('trim(upper(origen)) = ?', [$origen]);
            })
            ->when($destino !== '', function ($query) use ($destino) {
                $query->whereRaw('trim(upper(destino)) = ?', [$destino]);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('origen', 'ILIKE', "%{$q}%")
                        ->orWhere('destino', 'ILIKE', "%{$q}%")
                        ->orWhere('servicio', 'ILIKE', "%{$q}%")
                        ->orWhere('provincia', 'ILIKE', "%{$q}%")
                        ->orWhere('provincia_origen', 'ILIKE', "%{$q}%")
                        ->orWhere('horas_entrega', 'ILIKE', "%{$q}%")
                        ->orWhereHas('tarifario', fn ($tarifario) => $tarifario->where('nombre', 'ILIKE', "%{$q}%"))
                        ->orWhereHas('tarifario.empresasActivas', function ($empresa) use ($q) {
                            $empresa->where('nombre', 'ILIKE', "%{$q}%")
                                ->orWhere('sigla', 'ILIKE', "%{$q}%")
                                ->orWhere('codigo_cliente', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('tarifa_contrato.index', [
            'tarifas' => $tarifas,
            'q' => $q,
            'servicio' => $servicio,
            'origen' => $origen,
            'destino' => $destino,
            'empresaId' => $empresaId,
            'tarifarioId' => $tarifarioId,
            'tarifarios' => TarifarioContrato::query()->with('empresaTitular:id,nombre,codigo_cliente')
                ->orderByRaw('anio IS NULL')->orderByDesc('anio')->orderBy('nombre')->get(),
            'serviciosFiltro' => $serviciosFiltro,
            'origenesFiltro' => self::DEPARTAMENTOS,
            'destinosFiltro' => self::DEPARTAMENTOS,
            'empresasFiltro' => $empresasFiltro,
        ]);
    }

    public function create(Request $request)
    {
        $copyId = (int) $request->query('copy_id', 0);
        $hasTarifarioOrigen = $request->filled('tarifario_origen_id');
        $this->authorizeTarifaContratoButtonAction($copyId > 0 || $hasTarifarioOrigen ? 'duplicate' : 'create');

        $tarifarioOrigen = null;
        $filasPrecargadas = [];
        if ($hasTarifarioOrigen) {
            $request->validate(['tarifario_origen_id' => ['required', 'integer', Rule::exists('tarifarios_contrato', 'id')]]);
            $tarifarioOrigen = TarifarioContrato::query()->findOrFail($request->integer('tarifario_origen_id'));
            $filasPrecargadas = $tarifarioOrigen->tarifas()->orderBy('id')->get()->map(function (TarifaContrato $tarifa) {
                $fila = Arr::except(Arr::only($tarifa->toArray(), self::FORM_FIELDS), ['tarifario_contrato_id']);
                $fila['origen'] = $this->normalizeDepartamento($tarifa->origen);
                $fila['destino'] = $this->normalizeDepartamento($tarifa->destino);
                $fila['servicio'] = $this->normalizeServicio($tarifa->servicio);
                $fila['zona'] = $tarifa->zonaLocal() ?? $tarifa->zona;

                return $fila;
            })->all();
        }

        if ($request->boolean('reset')) {
            $request->session()->forget('tarifa_contrato_defaults');
        }

        $defaults = (array) $request->session()->get('tarifa_contrato_defaults', []);
        $copySource = null;

        if ($copyId > 0) {
            $copySource = TarifaContrato::query()->find($copyId);
            if ($copySource) {
                $defaults = Arr::only($copySource->toArray(), self::FORM_FIELDS);
            }
        }
        if ($request->filled('tarifario_id')) {
            $defaults['tarifario_contrato_id'] = (int) $request->query('tarifario_id');
        }
        if (ServicioContrato::esLocal((string) ($defaults['servicio'] ?? ''))) {
            $defaults['zona'] = TarifaContrato::normalizarZona($defaults['zona'] ?? null);
        }

        return view('tarifa_contrato.create', [
            'tarifaContrato' => new TarifaContrato(),
            'serviciosPorAnio' => ServicioContrato::catalogoPorAnio(),
            'departamentos' => self::DEPARTAMENTOS,
            'provinciasPorDepartamento' => self::PROVINCIAS_POR_DEPARTAMENTO,
            'defaults' => $defaults,
            'copySource' => $copySource,
            'tarifarioOrigen' => $tarifarioOrigen,
            'filasPrecargadas' => $filasPrecargadas,
            'maxSheetRows' => count($filasPrecargadas) + self::MAX_FILAS_MANUALES,
            'tarifariosOrigen' => TarifarioContrato::query()->whereHas('tarifas')->withCount('tarifas')
                ->with(['empresaTitular:id,nombre,codigo_cliente', 'empresasActivas:id,nombre'])->orderByDesc('anio')->orderBy('nombre')->get(),
            'tarifarios' => TarifarioContrato::query()->with('empresaTitular:id,nombre,codigo_cliente')->whereNull('publicado_en')
                ->when($tarifarioOrigen, fn ($query, $source) => $query->whereKeyNot($source->id))
                ->orderByRaw('anio IS NULL')->orderByDesc('anio')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTarifaContratoButtonAction('save');

        if ($request->hasAny(['tarifario_origen_id', 'tarifas_serializadas'])) {
            $this->authorizeTarifaContratoButtonAction('duplicate');
            $data = $request->validate([
                'tarifario_origen_id' => ['required', 'integer', Rule::exists('tarifarios_contrato', 'id')],
                'tarifas_serializadas' => ['required', 'string', 'json'],
            ]);
            $origen = TarifarioContrato::query()->withCount('tarifas')->findOrFail($data['tarifario_origen_id']);
            $request->merge(['tarifas' => json_decode($data['tarifas_serializadas'], true)]);
            $request->offsetUnset('tarifas_serializadas');

            return $this->storeMultiple($request, $origen);
        }

        if ($request->has('tarifas')) {
            return $this->storeMultiple($request);
        }

        $data = $this->validateData($request);
        DB::transaction(function () use ($data) {
            app(TarifarioContratoService::class)->editable((int) $data['tarifario_contrato_id']);
            TarifaContrato::query()->create($data);
        });

        if ((string) $request->input('action') === 'save_and_new') {
            return redirect()
                ->route('tarifa-contrato.create')
                ->with('success', 'Tarifa creada. Puedes registrar otra rapidamente.')
                ->with('tarifa_contrato_defaults', Arr::only($data, self::FORM_FIELDS));
        }

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato creada correctamente.');
    }

    private function storeMultiple(Request $request, ?TarifarioContrato $origen = null)
    {
        $limiteFilas = self::MAX_FILAS_MANUALES + ($origen?->tarifas_count ?? 0);
        $request->validate([
            'tarifario_contrato_id' => ['required', 'integer', Rule::exists('tarifarios_contrato', 'id')],
            'tarifas' => ['required', 'array', 'list', 'min:1', 'max:'.$limiteFilas],
            'tarifas.*' => ['array'],
        ]);

        $rows = [];
        $destino = TarifarioContrato::query()->findOrFail($request->integer('tarifario_contrato_id'));
        if ($origen?->is($destino)) {
            throw ValidationException::withMessages(['tarifario_contrato_id' => 'El tarifario de destino debe ser distinto del tarifario de origen.']);
        }
        $anio = $destino->anio;
        $rowRules = $this->validationRules(anio: $anio);
        if ($origen) {
            $serviciosPermitidos = $destino->serviciosDisponibles()->pluck('nombre')->merge(
                $origen->tarifas()->distinct()->pluck('servicio')->map(fn ($nombre) => $this->normalizeServicio($nombre))
            )->unique()->all();
            $rowRules['servicio'] = ['required', 'string', 'max:255', Rule::in($serviciosPermitidos)];
        }
        $rules = [];
        $attributes = [];

        foreach ($request->input('tarifas') as $index => $row) {
            $row = Arr::only($row, self::FORM_FIELDS);
            if (! collect($row)->contains(fn ($value) => $value !== null && $value !== '')) {
                continue;
            }

            $row['tarifario_contrato_id'] = $request->input('tarifario_contrato_id');
            if ($origen && ($row['peso'] ?? '') === '') {
                $row['peso'] = null;
            }
            $rows[$index] = $this->normalizeFormData($row);
            foreach (['peso', 'kilo', 'kilo_extra', 'retencion'] as $field) {
                // Accept Excel decimals with a comma, keeping invalid text for validation.
                $parsed = is_scalar($rows[$index][$field] ?? null)
                    ? $this->parseDecimal($rows[$index][$field]) : null;
                if ($parsed !== null) {
                    $rows[$index][$field] = $parsed;
                }
            }

            foreach ($rowRules as $field => $fieldRules) {
                $key = "tarifas.{$index}.{$field}";
                $rules[$key] = $fieldRules;
                $attributes[$key] = 'fila '.($index + 1).', '.str_replace('_', ' ', $field);
            }
        }

        if ($rows === []) {
            return back()->withErrors(['tarifas' => 'Completa al menos una fila para guardar.'])->withInput();
        }

        $validated = Validator::make(['tarifas' => $rows], $rules, [], $attributes)->validate();

        $filas = $validated['tarifas'];
        $insertadas = DB::transaction(function () use ($filas, $destino, $origen, $anio) {
            $editable = app(TarifarioContratoService::class)->editable($destino->id);
            if ($origen) {
                if ($editable->anio !== $anio) {
                    throw ValidationException::withMessages(['tarifario_contrato_id' => 'El año del tarifario de destino cambió. Revisa la selección antes de guardar.']);
                }

                foreach (collect($filas)->pluck('servicio')->unique() as $servicio) {
                    ServicioContrato::asegurar($servicio, $anio);
                }

                $nuevas = 0;
                foreach ($filas as $fila) {
                    $nuevas += (int) $this->guardarTarifaImportada($fila, compararPeso: true);
                }

                return $nuevas;
            }

            foreach ($filas as $row) {
                TarifaContrato::query()->create($row);
            }

            return count($filas);
        });

        if ($origen) {
            $actualizadas = count($filas) - $insertadas;

            return redirect()->route('tarifa-contrato.index', ['tarifario_id' => $destino->id])
                ->with('success', "Tarifario {$destino->etiqueta} guardado. Creadas: {$insertadas}, actualizadas: {$actualizadas}.");
        }

        return redirect()->route('tarifa-contrato.index')
            ->with('success', "Se registraron {$insertadas} tarifas de contrato correctamente.");
    }

    public function importForm()
    {
        $this->authorizeTarifaContratoButtonAction('import');

        return view('tarifa_contrato.import', [
            'serviciosPorAnio' => ServicioContrato::catalogoPorAnio(),
            'departamentos' => self::DEPARTAMENTOS,
            'columnas' => array_values(self::IMPORT_COLUMN_LABELS),
            'tarifarios' => TarifarioContrato::query()->with('empresaTitular:id,nombre,codigo_cliente')
                ->whereNull('publicado_en')->orderByRaw('anio IS NULL')->orderByDesc('anio')->orderBy('nombre')->get(),
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizeTarifaContratoButtonAction('import');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
            'tarifario_contrato_id' => ['required', 'integer', Rule::exists('tarifarios_contrato', 'id')],
        ]);

        $filePath = (string) $request->file('archivo')->getRealPath();

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo Excel.'])->withInput();
        }

        $sheet = $spreadsheet->getSheetByName('TarifaContrato');
        if (!$sheet) {
            $sheet = $spreadsheet->getSheet(0);
        }

        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            return back()->withErrors(['archivo' => 'El archivo esta vacio.'])->withInput();
        }

        $header = array_shift($rows);
        $header = array_map(fn ($value) => $this->normalizeHeader($value), $header);
        if (array_intersect(['empresa_codigo', 'empresa_nombre'], $header)) {
            return back()->withErrors(['archivo' => 'Descarga la nueva plantilla: las tarifas pertenecen al tarifario seleccionado y las empresas se asignan después.'])->withInput();
        }
        $missing = array_values(array_diff(self::REQUIRED_IMPORT_COLUMNS, $header));

        if (!empty($missing)) {
            return back()
                ->withErrors(['archivo' => 'Columnas faltantes en Excel: ' . implode(', ', array_map(fn ($column) => self::IMPORT_COLUMN_LABELS[$column], $missing))])
                ->withInput();
        }

        return DB::transaction(function () use ($request, $rows, $header) {
            $tarifario = app(TarifarioContratoService::class)->editable((int) $request->input('tarifario_contrato_id'));
            $created = 0;
            $updated = 0;
            $errors = [];
            $compararPeso = in_array('kilo_de_1_a_2', $header, true) || in_array('peso', $header, true);
            $line = 1; // Cabecera

            foreach ($rows as $row) {
                $line++;
                $hasValues = collect($row)->contains(fn ($value) => trim((string) $value) !== '');
                if (!$hasValues) {
                    continue;
                }

                $data = [];
                foreach ($header as $index => $column) {
                    $data[$column] = trim((string) ($row[$index] ?? ''));
                }

                $filaResumen = $this->buildImportRowSummary($data);

                $erroresNumericos = $this->detectInvalidNumericImportValues($data);
                if (!empty($erroresNumericos)) {
                    $errors[] = "Linea {$line} ({$filaResumen}): no se guardo porque " . implode('; ', $erroresNumericos) . '.';
                    continue;
                }

                $payload = [
                    'tarifario_contrato_id' => $request->input('tarifario_contrato_id'),
                    'origen' => $this->normalizeDepartamento($data['origen'] ?? null),
                    'destino' => $this->normalizeDepartamento($data['destino'] ?? null),
                    'servicio' => $this->normalizeServicio((string) ($data['servicio'] ?? '')),
                    'direccion' => $this->normalizeNullableUpper($data['direccion'] ?? null),
                    'zona' => $this->normalizeNullableUpper($data['zona'] ?? null),
                    'peso' => $this->parseDecimal($data['kilo_de_1_a_2'] ?? ($data['peso'] ?? null)),
                    'kilo' => $this->parseDecimal($data['kilo'] ?? null),
                    'kilo_extra' => $this->parseDecimal($data['kilo_extra'] ?? null),
                    'provincia_origen' => $this->normalizeNullableUpper($data['provincia_origen'] ?? null),
                    'provincia' => $this->normalizeNullableUpper($data['provincia_destino'] ?? ($data['provincia'] ?? null)),
                    'retencion' => $this->parseDecimal($data['retencion'] ?? null) ?? 0,
                    'horas_entrega' => $data['horas_entrega'] ?? null,
                ];

                $validator = Validator::make(
                    $payload,
                    $this->validationRules($tarifario->anio),
                    $this->importValidationMessages(),
                    [
                        'tarifario_contrato_id' => 'tarifario',
                        'origen' => 'origen',
                        'destino' => 'destino',
                        'servicio' => 'servicio',
                        'direccion' => 'direccion',
                        'zona' => 'zona',
                        'peso' => 'peso',
                        'kilo' => 'kilo',
                        'kilo_extra' => 'kilo extra',
                        'provincia' => 'provincia destino',
                        'provincia_origen' => 'provincia origen',
                        'retencion' => 'retencion',
                        'horas_entrega' => 'horas de entrega',
                    ]
                );

                if ($validator->fails()) {
                    $errors[] = "Linea {$line} ({$filaResumen}): " . $this->buildFriendlyImportValidationError($validator, $payload);
                    continue;
                }

                if ($this->guardarTarifaImportada($validator->validated(), $compararPeso)) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            $message = "Importación completada. Creadas: {$created}, actualizadas: {$updated}.";
            $redirectRoute = 'tarifa-contrato.import-form';
            $redirectParams = ['tarifario_id' => $tarifario->id];
            if ($request->boolean('volver_a_crear')) {
                $redirectRoute = 'tarifa-contrato.create';
                $redirectParams['copy_id'] = $request->integer('copy_id') ?: null;
            }
            $redirect = redirect()->route($redirectRoute, $redirectParams);

            if ($created === 0 && $updated === 0 && empty($errors)) {
                $redirect->with('warning', 'No se guardo ninguna fila. Revisa que el archivo tenga datos desde la fila 2 y valores validos.');
                return $redirect;
            }

            $redirect->with('success', $message);

            if (!empty($errors)) {
                $redirect->with('warning', 'Se encontraron ' . count($errors) . ' fila(s) con error.');
                $redirect->with('import_errors', array_slice($errors, 0, 20));
            }

            return $redirect;
        });
    }

    private function guardarTarifaImportada(array $data, bool $compararPeso): bool
    {
        $clave = Arr::only($data, ['tarifario_contrato_id', 'origen', 'destino', 'servicio', 'provincia', 'provincia_origen']);
        if ($compararPeso) {
            $clave['peso'] = $data['peso'] ?? null;
        }

        $coincidencias = TarifaContrato::query()->where($clave)->orderByDesc('id');
        if (ServicioContrato::esLocal($data['servicio'])) {
            $zona = TarifaContrato::normalizarZona($data['zona'] ?? null);
            $tarifa = $coincidencias->get()->first(fn (TarifaContrato $item) => $item->zonaLocal() === $zona);
        } else {
            $tarifa = $coincidencias->first();
        }

        if ($tarifa) {
            $tarifa->update(Arr::only($data, ['direccion', 'zona', 'kilo', 'kilo_extra', 'retencion', 'horas_entrega']));

            return false;
        }

        TarifaContrato::query()->create($data);

        return true;
    }

    private function buildImportRowSummary(array $data): string
    {
        $origen = trim((string) ($data['origen'] ?? 'SIN ORIGEN'));
        $destino = trim((string) ($data['destino'] ?? 'SIN DESTINO'));
        $servicio = trim((string) ($data['servicio'] ?? 'SIN SERVICIO'));

        return "origen: {$origen}, destino: {$destino}, servicio: {$servicio}";
    }

    private function detectInvalidNumericImportValues(array $data): array
    {
        $errores = [];

        $decimalColumns = [
            'kilo_de_1_a_2' => 'kilo_de_1_a_2 (peso)',
            'kilo' => 'kilo',
            'kilo_extra' => 'kilo_extra',
            'retencion' => 'retencion',
        ];

        foreach ($decimalColumns as $column => $label) {
            $raw = trim((string) ($data[$column] ?? ''));
            if ($raw === '') {
                continue;
            }

            if ($this->parseDecimal($raw) === null) {
                $errores[] = "el campo {$label} tiene formato numerico invalido ('{$raw}')";
            }
        }

        $horasRaw = trim((string) ($data['horas_entrega'] ?? ''));
        if ($horasRaw !== '' && filter_var($horasRaw, FILTER_VALIDATE_INT) === false) {
            $errores[] = "el campo horas_entrega debe ser un numero entero ('{$horasRaw}')";
        }

        return $errores;
    }

    private function importValidationMessages(): array
    {
        return [
            'required' => 'el campo :attribute es obligatorio.',
            'numeric' => 'el campo :attribute debe ser numerico.',
            'integer' => 'el campo :attribute debe ser un numero entero.',
            'min.numeric' => 'el campo :attribute no puede ser menor a :min.',
            'min.integer' => 'el campo :attribute no puede ser menor a :min.',
            'max.numeric' => 'el campo :attribute no puede ser mayor a :max.',
            'max.string' => 'el campo :attribute no debe exceder :max caracteres.',
            'in' => 'el valor de :attribute no es valido para el departamento/servicio seleccionado.',
            'exists' => 'el valor de :attribute no existe en el sistema.',
        ];
    }

    private function buildFriendlyImportValidationError($validator, array $payload): string
    {
        $failed = $validator->failed();
        $field = array_key_first($failed);
        $rules = is_string($field) ? array_keys((array) ($failed[$field] ?? [])) : [];
        $rule = $rules[0] ?? null;

        if (in_array($field, ['origen', 'destino'], true) && in_array($rule, ['In', 'Required'], true)) {
            return "elige uno de los nueve departamentos de la plantilla para {$field}.";
        }

        if ($field === 'servicio' && in_array($rule, ['In', 'Exists'], true)) {
            return 'no se guardo porque el servicio no coincide con uno permitido en la plantilla. Revisa la columna servicio y usa uno de los valores del archivo modelo.';
        }

        if (in_array($field, ['kilo', 'kilo_extra', 'peso', 'retencion'], true) && in_array($rule, ['Numeric', 'Min', 'Max'], true)) {
            return 'no se guardo porque uno de los importes o pesos tiene un formato invalido. Revisa que esos campos solo tengan numeros.';
        }

        if ($field === 'horas_entrega' && in_array($rule, ['Integer', 'Min'], true)) {
            return 'no se guardo porque horas_entrega debe ser un numero entero mayor o igual a 0.';
        }

        if ($field === 'tarifario_contrato_id' && in_array($rule, ['Exists', 'Required'], true)) {
            return 'no se guardo porque falta seleccionar un tarifario válido.';
        }

        return 'no se guardo porque ' . $validator->errors()->first();
    }

    public function downloadTemplateExcel(Request $request): StreamedResponse
    {
        $this->authorizeTarifaContratoButtonAction('export');
        $request->validate([
            'tarifario_id' => ['nullable', 'integer', Rule::exists('tarifarios_contrato', 'id')],
            'anio' => ['nullable', Rule::in([...range(2000, 2100), 'sin-anio'])],
        ]);
        $anio = $request->filled('tarifario_id')
            ? TarifarioContrato::query()->findOrFail($request->integer('tarifario_id'))->anio
            : ($request->input('anio') === 'sin-anio' ? null : (int) $request->input('anio', now()->year));

        return $this->downloadTarifarioExcel($anio, 'plantilla_tarifa_contrato.xlsx');
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorizeTarifaContratoButtonAction('export');
        $validated = $request->validate([
            'empresa_id' => ['nullable', 'required_without:tarifario_id', 'integer', Rule::exists('empresa', 'id')],
            'tarifario_id' => ['nullable', 'required_without:empresa_id', 'integer', Rule::exists('tarifarios_contrato', 'id')],
        ], [
            'empresa_id.required_without' => 'Selecciona una empresa o un tarifario para descargar.',
            'tarifario_id.required_without' => 'Selecciona una empresa o un tarifario para descargar.',
        ]);

        $tarifarios = TarifarioContrato::query()
            ->when($validated['tarifario_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($validated['empresa_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'empresasActivas', fn ($empresas) => $empresas->whereKey($id)
            ))
            ->limit(2)
            ->get();

        if ($tarifarios->isEmpty()) {
            throw ValidationException::withMessages(['empresa_id' => 'La empresa no tiene un tarifario activo que coincida con la selección.']);
        }
        if ($tarifarios->count() > 1) {
            throw ValidationException::withMessages(['tarifario_id' => 'La empresa tiene más de un tarifario activo. Elige cuál deseas descargar.']);
        }

        $tarifario = $tarifarios->first();
        $nombre = Str::slug($tarifario->nombre) ?: (string) $tarifario->id;
        $anio = $tarifario->anio ?? 'sin-anio';

        return $this->downloadTarifarioExcel($tarifario->anio, "tarifario-{$nombre}-{$anio}.xlsx", $tarifario);
    }

    private function downloadTarifarioExcel(?int $anio, string $filename, ?TarifarioContrato $tarifario = null): StreamedResponse
    {
        $servicios = ServicioContrato::deAnio($anio)->orderBy('nombre')->pluck('nombre')->all();

        return response()->streamDownload(function () use ($servicios, $anio, $tarifario) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('TarifaContrato');
            $resumen = $tarifario ? $spreadsheet->createSheet()->setTitle('Resumen') : null;
            $columns = self::IMPORT_COLUMNS;
            $columnasServicio = ['destino', 'origen', ...array_slice(self::IMPORT_COLUMNS, 2)];
            $hojasServicio = [];
            $tarifas = $tarifario?->tarifas()->orderBy('servicio')->orderByRaw('UPPER(TRIM(destino))')
                ->orderBy('origen')->orderBy('zona')->orderBy('id')->cursor() ?? [];
            $row = 2;
            foreach ($tarifas as $tarifa) {
                if ($tarifa->peso !== null && ! in_array('peso', $columns, true)) {
                    $columns[] = 'peso';
                    $columnasServicio[] = 'peso';
                }
                $this->writeTarifaExcelRow($sheet, $row++, $tarifa, $columns);

                $servicio = $tarifa->servicio;
                if (! isset($hojasServicio[$servicio])) {
                    $prefijo = sprintf('%02d - ', count($hojasServicio) + 1);
                    $nombre = trim(str_replace([...Worksheet::getInvalidCharacters(), "'"], ' ', $servicio));
                    $titulo = $prefijo.mb_substr($nombre ?: 'Servicio', 0, 31 - strlen($prefijo));
                    $hojasServicio[$servicio] = ['hoja' => $spreadsheet->createSheet()->setTitle($titulo), 'fila' => 4];
                }

                $this->writeTarifaExcelRow(
                    $hojasServicio[$servicio]['hoja'],
                    $hojasServicio[$servicio]['fila']++,
                    $tarifa,
                    $columnasServicio,
                );
            }

            $lastRow = $row - 1;
            $lastEditableRow = max(5000, $lastRow);
            $this->formatearHojaTarifas($sheet, $columns, 1, $lastRow, $lastEditableRow);
            if ($resumen) {
                foreach ($hojasServicio as $servicio => ['hoja' => $hoja, 'fila' => $siguienteFila]) {
                    $this->formatearHojaTarifas($hoja, $columnasServicio, 3, $siguienteFila - 1, $siguienteFila - 1, 'Departamento de destino');
                    $ultimaColumna = $hoja->getHighestDataColumn();
                    $hoja->mergeCells("A1:{$ultimaColumna}1");
                    $hoja->setCellValueExplicit('A1', $servicio, DataType::TYPE_STRING);
                    $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                    $hoja->getRowDimension(1)->setRowHeight(28);
                    $hoja->setCellValue('A2', 'Volver al resumen');
                    $this->enlazarHojaExcel($hoja, 'A2', $resumen);
                    $hoja->setCellValue('C2', 'Abrir hoja para importar');
                    $this->enlazarHojaExcel($hoja, 'C2', $sheet);
                    $hoja->freezePane('B4');
                }
                $this->llenarResumenExcel($resumen, $tarifario->etiqueta, $sheet, $hojasServicio);
            }

            $catalogos = $spreadsheet->createSheet();
            $catalogos->setTitle('Catalogos');
            $catalogos->fromArray(['SERVICIOS '.($anio ?? 'AÑO POR DEFINIR'), 'DEPARTAMENTOS', 'ZONAS LOCALES'], null, 'A1');
            foreach ($servicios as $index => $servicio) {
                $catalogos->setCellValue('A'.($index + 2), $servicio);
            }
            foreach (self::DEPARTAMENTOS as $index => $departamento) {
                $catalogos->setCellValue('B'.($index + 2), $departamento);
            }
            foreach (TarifaContrato::ZONAS_LOCALES as $index => $zona) {
                $catalogos->setCellValue('C'.($index + 2), $zona);
            }
            $catalogos->getColumnDimension('A')->setWidth(48);
            $catalogos->getColumnDimension('B')->setWidth(24);
            $catalogos->getColumnDimension('C')->setWidth(24);
            $lastService = max(2, count($servicios) + 1);
            $lastDepartment = count(self::DEPARTAMENTOS) + 1;
            $formulaDepartamentos = "=Catalogos!\$B\$2:\$B\${$lastDepartment}";
            foreach (['A', 'B'] as $columna) {
                $this->applyListValidation($sheet, "{$columna}2:{$columna}{$lastEditableRow}", $formulaDepartamentos, allowBlank: false);
                foreach ($hojasServicio as ['hoja' => $hoja, 'fila' => $siguienteFila]) {
                    $ultimaFila = $siguienteFila - 1;
                    $this->applyListValidation($hoja, "{$columna}4:{$columna}{$ultimaFila}", $formulaDepartamentos, allowBlank: false);
                }
            }
            $this->applyListValidation($sheet, "C2:C{$lastEditableRow}", "=Catalogos!\$A\$2:\$A\${$lastService}");
            $this->applyListValidation($sheet, "H2:H{$lastEditableRow}", '=Catalogos!$C$2:$C$'.(count(TarifaContrato::ZONAS_LOCALES) + 1), strict: false);

            $instructions = $spreadsheet->createSheet();
            $instructions->setTitle('Instrucciones');
            $instructions->fromArray([
                ['TARIFARIOS DE CONTRATO POR AÑO'],
                ['Selecciona el año y un tarifario en borrador en la página de registro o importación.'],
                ['Cada archivo carga las tarifas de un solo tarifario; las empresas se asignan después.'],
                ['No cambies los encabezados. Empieza a llenar datos desde la fila 2.'],
                ['Los títulos son los mismos que en la plataforma. Los campos con * son obligatorios.'],
                ['En Origen y Destino, selecciona uno de los nueve valores en mayúsculas de la hoja Catalogos.'],
                ['Primer kilogramo: precio (Bs) y Kilogramo extra: precio (Bs) indican los precios. No es necesario llenar un peso.'],
                ['Para precios locales por zona, usa una fila por ruta y zona. En la columna Zona puedes elegir Zona 1, Zona 2 o Zona 3.'],
                ['Opcionales: dirección, zona, provincias y retención. Retención vacía equivale a 0 %.'],
                ['Para pegar en la página: copia solo las celdas de datos de A a K (sin la cabecera, máximo 50 filas por vez).'],
                ['Los tarifarios publicados conservan sus precios. Crea o copia otro tarifario para renovar.'],
                ['Haz clic en la primera celda de Origen, pega con Ctrl+V, revisa los datos y pulsa Guardar tarifas.'],
            ], null, 'A1');
            if (in_array('peso', $columns, true)) {
                $instructions->setCellValue('A'.($instructions->getHighestDataRow() + 1), 'Usa Importar Excel para conservar los datos de la columna Peso (kg).');
            }
            $instructions->getColumnDimension('A')->setWidth(120);
            $instructions->getStyle('A1:A'.$instructions->getHighestDataRow())->getAlignment()->setWrapText(true);
            $spreadsheet->setActiveSheetIndexByName($resumen ? 'Resumen' : 'TarifaContrato');
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function formatearHojaTarifas(
        Worksheet $hoja,
        array $columnas,
        int $cabecera,
        int $ultimaFila,
        int $ultimaFilaConFormato,
        ?string $tituloDestino = null,
    ): void {
        $titulos = array_map(fn ($columna) => match ($columna) {
            'destino' => $tituloDestino ?? self::IMPORT_COLUMN_LABELS['destino'],
            'peso' => 'Peso (kg) (opcional)',
            default => self::IMPORT_COLUMN_LABELS[$columna],
        }, $columnas);
        $hoja->fromArray($titulos, null, 'A'.$cabecera);
        $primeraFila = $cabecera + 1;
        $ultimaColumna = $hoja->getHighestDataColumn();
        $hoja->freezePane('A'.$primeraFila);
        $hoja->setAutoFilter("A{$cabecera}:{$ultimaColumna}{$ultimaFila}");
        $hoja->getStyle("A{$cabecera}:{$ultimaColumna}{$cabecera}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF217346']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getRowDimension($cabecera)->setRowHeight(44);
        foreach (range('A', $ultimaColumna) as $columna) {
            $hoja->getColumnDimension($columna)->setWidth(24);
        }
        $hoja->getColumnDimension('C')->setWidth(42);
        $hoja->getColumnDimension('D')->setWidth(32);
        $hoja->getColumnDimension('E')->setWidth(32);
        $hoja->getStyle("D{$primeraFila}:E{$ultimaFilaConFormato}")->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle("F{$primeraFila}:F{$ultimaFilaConFormato}")->getNumberFormat()->setFormatCode('0');
        $hoja->getStyle("K{$primeraFila}:K{$ultimaFilaConFormato}")->getNumberFormat()->setFormatCode('0.00');
        if ($ultimaColumna === 'L') {
            $hoja->getStyle("L{$primeraFila}:L{$ultimaFilaConFormato}")->getNumberFormat()->setFormatCode('0.00');
        }
    }

    private function llenarResumenExcel(Worksheet $resumen, string $nombreTarifario, Worksheet $hojaImportable, array $hojasServicio): void
    {
        $resumen->mergeCells('A1:C1')->setCellValue('A1', 'Tarifario '.$nombreTarifario);
        $resumen->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $resumen->getRowDimension(1)->setRowHeight(30);
        $resumen->mergeCells('A2:C2')->setCellValue('A2', 'Abre una pestaña por servicio para consultar los departamentos de destino de la A a la Z.');
        $resumen->mergeCells('A3:C3')->setCellValue('A3', 'Abrir TarifaContrato para editar y volver a importar');
        $this->enlazarHojaExcel($resumen, 'A3', $hojaImportable);
        $resumen->fromArray(['Servicio', 'Tarifas', 'Pestaña'], null, 'A5');
        $resumen->getStyle('A5:C5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF217346']],
        ]);
        $resumen->getColumnDimension('A')->setWidth(70);
        $resumen->getColumnDimension('B')->setWidth(12);
        $resumen->getColumnDimension('C')->setWidth(18);
        $resumen->freezePane('A6');

        $fila = 6;
        foreach ($hojasServicio as $servicio => ['hoja' => $hoja, 'fila' => $siguienteFila]) {
            $resumen->setCellValueExplicit('A'.$fila, $servicio, DataType::TYPE_STRING);
            $resumen->setCellValue('B'.$fila, $siguienteFila - 4);
            $resumen->setCellValue('C'.$fila, 'Abrir hoja');
            $this->enlazarHojaExcel($resumen, 'C'.$fila, $hoja);
            $fila++;
        }
        if ($hojasServicio === []) {
            $resumen->setCellValue('A6', 'Este tarifario todavía no tiene tarifas.');
        } else {
            $resumen->getStyle('A6:A'.($fila - 1))->getAlignment()->setWrapText(true);
        }
    }

    private function enlazarHojaExcel(Worksheet $origen, string $celda, Worksheet $destino): void
    {
        $origen->getCell($celda)->getHyperlink()->setUrl("sheet://'".$destino->getTitle()."'!A1");
        $origen->getStyle($celda)->getFont()->setUnderline(true)->getColor()->setARGB('FF146344');
    }

    private function writeTarifaExcelRow(Worksheet $sheet, int $row, TarifaContrato $tarifa, array $columns): void
    {
        foreach ($columns as $index => $column) {
            $value = match ($column) {
                'origen', 'destino' => $this->normalizeDepartamento($tarifa->$column),
                'zona' => $tarifa->zonaLocal() ?? $tarifa->zona,
                'provincia_destino' => $tarifa->provincia,
                'kilo', 'kilo_extra', 'retencion', 'peso' => $tarifa->$column === null ? null : (float) $tarifa->$column,
                default => $tarifa->$column,
            };

            if ($value !== null) {
                $type = is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                $sheet->setCellValueExplicit([$index + 1, $row], $value, $type);
            }
        }
    }

    public function reporte()
    {
        $this->authorizeTarifaContratoAnyButtonAction(['report', 'export']);

        $totalTarifas = TarifaContrato::query()->count();
        $totalEmpresasConTarifa = DB::table('tarifario_contrato_empresa')
            ->where('activo', true)
            ->distinct('empresa_id')
            ->count('empresa_id');
        $totalServicios = TarifaContrato::query()
            ->whereNotNull('servicio')
            ->whereRaw("trim(servicio) <> ''")
            ->distinct('servicio')
            ->count('servicio');
        $totalRutas = TarifaContrato::query()
            ->select('origen', 'destino')
            ->whereNotNull('origen')
            ->whereNotNull('destino')
            ->whereRaw("trim(origen) <> ''")
            ->whereRaw("trim(destino) <> ''")
            ->distinct()
            ->get()
            ->count();

        $tarifasPorEmpresa = TarifaContrato::query()
            ->join('tarifario_contrato_empresa as asignacion', 'asignacion.tarifario_contrato_id', '=', 'tarifa_contrato.tarifario_contrato_id')
            ->join('empresa', 'empresa.id', '=', 'asignacion.empresa_id')
            ->where('asignacion.activo', true)
            ->selectRaw("COALESCE(NULLIF(TRIM(empresa.nombre), ''), 'SIN EMPRESA') as empresa_nombre, COUNT(*) as total")
            ->groupByRaw("COALESCE(NULLIF(TRIM(empresa.nombre), ''), 'SIN EMPRESA')")
            ->orderByDesc('total')
            ->orderBy('empresa_nombre')
            ->get();

        $tarifasPorServicio = TarifaContrato::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(servicio), ''), 'SIN SERVICIO') as servicio_nombre, COUNT(*) as total")
            ->groupByRaw("COALESCE(NULLIF(TRIM(servicio), ''), 'SIN SERVICIO')")
            ->orderByDesc('total')
            ->orderBy('servicio_nombre')
            ->get();

        $tarifasPorRuta = TarifaContrato::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(origen), ''), 'SIN ORIGEN') as origen_nombre")
            ->selectRaw("COALESCE(NULLIF(TRIM(destino), ''), 'SIN DESTINO') as destino_nombre")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw("COALESCE(NULLIF(TRIM(origen), ''), 'SIN ORIGEN')")
            ->groupByRaw("COALESCE(NULLIF(TRIM(destino), ''), 'SIN DESTINO')")
            ->orderByDesc('total')
            ->orderBy('origen_nombre')
            ->orderBy('destino_nombre')
            ->limit(20)
            ->get();

        $generatedAt = now();
        $pdf = Pdf::loadView('tarifa_contrato.reporte-pdf', [
            'totalTarifas' => $totalTarifas,
            'totalEmpresasConTarifa' => $totalEmpresasConTarifa,
            'totalServicios' => $totalServicios,
            'totalRutas' => $totalRutas,
            'tarifasPorEmpresa' => $tarifasPorEmpresa,
            'tarifasPorServicio' => $tarifasPorServicio,
            'tarifasPorRuta' => $tarifasPorRuta,
            'generatedAt' => $generatedAt,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream('reporte-tarifas-contrato-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function reporteDetalle(Request $request)
    {
        $this->authorizeTarifaContratoAnyButtonAction(['report', 'export']);

        $tarifas = TarifaContrato::query()
            ->with('tarifario')
            ->when($request->filled('tarifario_id'), fn ($query) => $query->where('tarifario_contrato_id', $request->integer('tarifario_id')))
            ->orderBy('tarifario_contrato_id')
            ->orderBy('origen')
            ->orderBy('destino')
            ->orderBy('provincia')
            ->orderBy('provincia_origen')
            ->orderBy('servicio')
            ->get();

        $gruposServicio = collect(self::GRUPOS_SERVICIO_REPORTE)
            ->map(fn (array $grupo): array => [
                ...$grupo,
                'servicios_normalizados' => array_map(
                    fn (string $servicio): string => $this->normalizeServicio($servicio),
                    $grupo['servicios']
                ),
            ]);

        $agrupados = $gruposServicio->pluck('servicios_normalizados')->flatten()->all();
        $otros = $tarifas->pluck('servicio')->unique()->filter(fn ($nombre) => !in_array($this->normalizeServicio($nombre), $agrupados, true))->values()->all();
        if ($otros !== []) {
            $gruposServicio->push([
                'titulo' => 'Otros servicios del tarifario', 'servicios' => $otros,
                'servicios_normalizados' => array_map(fn ($nombre) => $this->normalizeServicio($nombre), $otros),
            ]);
        }

        $empresasConTarifas = $tarifas
            ->groupBy(fn (TarifaContrato $tarifa) => $tarifa->tarifario_contrato_id ?? 0)
            ->map(fn ($tarifasEmpresa): array => [
                'empresa' => $tarifasEmpresa->first()?->tarifario,
                'tarifas' => $tarifasEmpresa,
            ])
            ->sortBy(fn (array $grupo): string => strtoupper((string) ($grupo['empresa']?->nombre ?? 'SIN EMPRESA'))
                . '|' . (string) ($grupo['empresa']?->anio ?? ''))
            ->values();

        $generatedAt = now();
        $pdf = new TarifaContratoDetallePdf($generatedAt->format('d/m/Y H:i'));

        foreach ($empresasConTarifas as $grupoEmpresa) {
            $empresa = $grupoEmpresa['empresa'];
            $tarifasEmpresa = $grupoEmpresa['tarifas'];

            $pdf->beginCompany(
                $empresa?->nombre ?: 'Sin tarifario',
                (string) ($empresa?->anio ?? 'Por definir')
            );

            foreach ($gruposServicio as $grupoServicio) {
                $tarifasServicio = $tarifasEmpresa
                    ->filter(fn (TarifaContrato $tarifa): bool => in_array(
                        $this->normalizeServicio((string) $tarifa->servicio),
                        $grupoServicio['servicios_normalizados'],
                        true
                    ))
                    ->values();

                if ($tarifasServicio->isEmpty()) {
                    continue;
                }

                $pdf->beginServiceGroup(
                    $grupoServicio['titulo'],
                    $grupoServicio['servicios'],
                    $tarifasServicio->count()
                );

                foreach ($tarifasServicio as $tarifa) {
                    $pdf->addTariffRow([
                        $tarifa->servicio ?: '—',
                        $tarifa->origen ?: '—',
                        $tarifa->destino ?: '—',
                        $tarifa->direccion ?: '—',
                        ($tarifa->zonaLocal() ?? $tarifa->zona) ?: '—',
                        $tarifa->peso !== null ? \App\Support\BolivianNumber::format($tarifa->peso, 2) : '—',
                        \App\Support\BolivianNumber::format($tarifa->kilo, 2),
                        \App\Support\BolivianNumber::format($tarifa->kilo_extra, 2),
                        $tarifa->provincia ?: '—',
                        $tarifa->provincia_origen ?: '—',
                        $tarifa->horas_entrega !== null ? \App\Support\BolivianNumber::format($tarifa->horas_entrega) . ' h' : '—',
                    ]);
                }
            }
        }

        $filename = 'detalle-tarifario-contratos-' . $generatedAt->format('Ymd-His') . '.pdf';

        return response($pdf->outputPdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function edit(TarifaContrato $tarifaContrato)
    {
        $this->authorizeTarifaContratoButtonAction('edit');

        return view('tarifa_contrato.edit', [
            'tarifaContrato' => $tarifaContrato,
            'servicios' => $tarifaContrato->tarifario->serviciosDisponibles()->pluck('nombre')->all(),
            'serviciosPorAnio' => ServicioContrato::catalogoPorAnio(),
            'departamentos' => self::DEPARTAMENTOS,
            'provinciasPorDepartamento' => self::PROVINCIAS_POR_DEPARTAMENTO,
            'defaults' => [],
            'tarifarios' => TarifarioContrato::query()->with('empresaTitular:id,nombre,codigo_cliente')
                ->whereNull('publicado_en')->orWhereKey($tarifaContrato->tarifario_contrato_id)->orderByRaw('anio IS NULL')->orderByDesc('anio')->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, TarifaContrato $tarifaContrato)
    {
        $this->authorizeTarifaContratoButtonAction('save');

        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $tarifaContrato) {
            // Lock both sets in the same order as other mutations.
            foreach (collect([$tarifaContrato->tarifario_contrato_id, $data['tarifario_contrato_id']])->unique()->sort() as $id) {
                app(TarifarioContratoService::class)->editable((int) $id);
            }
            $tarifaContrato->update($data);
        });

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato actualizada correctamente.');
    }

    public function destroy(TarifaContrato $tarifaContrato)
    {
        $this->authorizeTarifaContratoButtonAction('delete');

        DB::transaction(function () use ($tarifaContrato) {
            app(TarifarioContratoService::class)->editable((int) $tarifaContrato->tarifario_contrato_id);
            $tarifaContrato->delete();
        });

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato eliminada correctamente.');
    }

    private function validateData(Request $request): array
    {
        $request->validate(['tarifario_contrato_id' => ['required', 'integer', Rule::exists('tarifarios_contrato', 'id')]]);
        $anio = TarifarioContrato::query()->findOrFail($request->integer('tarifario_contrato_id'))->anio;
        $request->merge($this->normalizeFormData($request->only(self::FORM_FIELDS)));

        $data = $request->validate($this->validationRules($anio), [
            'origen.in' => 'Elige el origen de la lista de departamentos.',
            'destino.in' => 'Elige el destino de la lista de departamentos.',
        ]);

        if ($request->filled('peso')) {
            $data['peso'] = $this->parseDecimal($request->input('peso'));
        }

        return $data;
    }

    private function normalizeFormData(array $data): array
    {
        if (($data['retencion'] ?? '') === '') {
            $data['retencion'] = 0;
        }

        // Leave malformed values intact so the validator can reject them.
        foreach (['origen', 'destino', 'servicio', 'direccion', 'zona', 'provincia', 'provincia_origen'] as $field) {
            $value = $data[$field] ?? null;
            if ($value !== null && ! is_scalar($value)) {
                continue;
            }

            $data[$field] = match ($field) {
                'origen', 'destino' => $this->normalizeDepartamento($value),
                'servicio' => $this->normalizeServicio((string) $value),
                default => $this->normalizeNullableUpper($value),
            };
        }

        return $data;
    }

    private function validationRules(?int $anio = null): array
    {
        return [
            'tarifario_contrato_id' => ['required', 'integer', Rule::exists('tarifarios_contrato', 'id')],
            'origen' => ['required', 'string', Rule::in(self::DEPARTAMENTOS)],
            'destino' => ['required', 'string', Rule::in(self::DEPARTAMENTOS)],
            'servicio' => ['required', 'string', 'max:255', Rule::exists('servicios_contrato', 'nombre')->where(fn ($query) => $anio === null ? $query->whereNull('anio') : $query->where('anio', $anio))],
            'direccion' => ['nullable', 'string', 'max:255'],
            'zona' => ['nullable', 'string', 'max:255'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'kilo' => 'required|numeric|min:0',
            'kilo_extra' => 'required|numeric|min:0',
            'provincia' => ['nullable', 'string', 'max:255'],
            'provincia_origen' => ['nullable', 'string', 'max:255'],
            'retencion' => 'nullable|numeric|min:0|max:100',
            'horas_entrega' => 'required|integer|min:0',
        ];
    }

    private function normalizeHeader($value): string
    {
        $value = mb_strtolower(rtrim(trim((string) $value), ' *'));

        foreach (self::IMPORT_COLUMN_LABELS as $column => $label) {
            if ($value === mb_strtolower(rtrim($label, ' *'))) {
                return $column;
            }
        }

        return match ($value) {
            'zona (opcional)', 'zona / subservicio' => 'zona',
            'peso', 'peso (kg)', 'peso (kg) (opcional)' => 'kilo_de_1_a_2',
            'kilo de 1 a 2', 'kilo 1 a 2', 'kilo_de_1_a_2' => 'kilo_de_1_a_2',
            'provincia origen', 'provincia_origen' => 'provincia_origen',
            'provincia destino', 'provincia_destino', 'provincia' => 'provincia_destino',
            default => $value,
        };
    }

    private function applyListValidation(Worksheet $sheet, string $range, string $formula, bool $strict = true, bool $allowBlank = true): void
    {
        [$start, $end] = explode(':', $range);
        $startCell = $sheet->getCell($start);
        $validation = $startCell->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage($strict);
        $validation->setErrorTitle('Valor invalido');
        $validation->setError('Selecciona un valor de la lista.');
        $validation->setFormula1($formula);

        [$startCol, $startRow] = $this->splitCell($start);
        [$endCol, $endRow] = $this->splitCell($end);

        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($col = $startCol; $col <= $endCol; $col++) {
                $cell = $col . $row;
                $sheet->getCell($cell)->setDataValidation(clone $validation);
            }
        }
    }

    private function splitCell(string $cell): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($cell), $m);
        return [$m[1], (int) $m[2]];
    }

    private function parseDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $hasComma = str_contains($text, ',');
        $hasDot = str_contains($text, '.');

        if ($hasComma && $hasDot) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif ($hasComma) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function authorizeTarifaContratoButtonAction(string $action): void
    {
        $this->authorizeTarifaContratoAnyButtonAction([$action]);
    }

    private function authorizeTarifaContratoAnyButtonAction(array $actions): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        $permissions = AclPermissionRegistry::existingPermissionsFrom(
            collect($actions)
                ->filter(fn ($action): bool => is_string($action) && trim($action) !== '')
                ->map(fn (string $action): string => 'feature.tarifa-contrato.'.trim($action))
                ->values()
                ->all()
        );

        if ($permissions === []) {
            if ((bool) config('acl.route_permission.allow_when_permission_missing', true)) {
                return;
            }

            abort(403, 'No se encontro la configuracion de permisos para esta accion.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }

    private function normalizeServicio(string $value): string
    {
        return ServicioContrato::normalizarNombre($value);
    }

    private function normalizeNullableUpper($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return strtoupper($text);
    }

    private function normalizeDepartamento($value): string
    {
        return is_scalar($value) ? mb_strtoupper(trim((string) $value)) : '';
    }

}

