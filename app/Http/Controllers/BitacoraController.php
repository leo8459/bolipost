<?php

namespace App\Http\Controllers;

use App\Exports\BitacoraDepartamentoExport;
use App\Models\Bitacora;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Models\User;
use App\Services\BitacoraFacturaQrService;
use App\Support\BitacoraCn33Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class BitacoraController extends Controller
{
    public function __construct(
        private readonly BitacoraCn33Service $cn33Service,
        private readonly BitacoraFacturaQrService $facturaQrService
    ) {
    }

    public function index(Request $request): View
    {
        return view('bitacoras.index', $this->buildIndexData($request));
    }

    public function exportExcel(Request $request)
    {
        $data = $this->buildIndexData($request, false);
        $filename = 'reporte-bitacoras-departamentos-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new BitacoraDepartamentoExport($this->buildDepartmentReportPayload($data)), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildIndexData($request, false);
        $payload = $this->buildDepartmentReportPayload($data);
        $pdf = Pdf::loadView('bitacoras.report-departamentos-pdf', $payload)->setPaper('A4', 'landscape');

        return $pdf->download('reporte-bitacoras-departamentos-' . now()->format('Ymd_His') . '.pdf');
    }

    private function buildIndexData(Request $request, bool $paginate = true): array
    {
        $regionalScope = $this->resolveRegionalScopeForUser(Auth::user());
        $q = trim((string) $request->query('q', ''));
        $userId = (int) $request->query('user_id', 0);
        $codEspecial = strtoupper(trim((string) $request->query('cod_especial', '')));
        $provincia = strtoupper(trim((string) $request->query('provincia', '')));
        $regional = strtoupper(trim((string) $request->query('regional', '')));
        $origenCn33 = strtoupper(trim((string) $request->query('origen_cn33', '')));

        $filteredCodEspecialesQuery = Bitacora::query()
            ->selectRaw('trim(upper(cod_especial)) as cod_especial_normalizado')
            ->groupBy(DB::raw('trim(upper(cod_especial))'));

        $this->applyBitacoraFilters($filteredCodEspecialesQuery, $q, $userId, $codEspecial, $provincia, $regional, $origenCn33);

        $latestIdsQuery = Bitacora::query()
            ->selectRaw('max(id) as id')
            ->whereIn(DB::raw('trim(upper(cod_especial))'), $filteredCodEspecialesQuery)
            ->whereNotNull('precio_total')
            ->whereNotNull('peso')
            ->groupBy(DB::raw('trim(upper(cod_especial))'));

        $bitacorasQuery = Bitacora::query()
            ->with([
                'user:id,name',
                'paqueteEms:id,codigo,cod_especial,origen,ciudad',
                'paqueteContrato:id,codigo,cod_especial,origen,destino',
                'paqueteOrdi:id,codigo,cod_especial',
                'paqueteCerti:id,codigo,cod_especial',
            ])
            ->whereIn('id', $latestIdsQuery)
            ->orderByDesc('id');

        $bitacoras = $paginate
            ? $bitacorasQuery->paginate(15)->withQueryString()
            : $bitacorasQuery->get();

        $codEspecialesPagina = collect($bitacoras instanceof \Illuminate\Contracts\Pagination\Paginator ? $bitacoras->getCollection() : $bitacoras)
            ->pluck('cod_especial')
            ->map(fn ($codigo) => strtoupper(trim((string) $codigo)))
            ->filter()
            ->unique()
            ->values();

        $detallesPorCodEspecial = Bitacora::query()
            ->with([
                'user:id,name',
                'paqueteEms:id,codigo,cod_especial,origen,ciudad',
                'paqueteContrato:id,codigo,cod_especial,origen,destino',
                'paqueteOrdi:id,codigo,cod_especial',
                'paqueteCerti:id,codigo,cod_especial',
            ])
            ->whereIn(DB::raw('trim(upper(cod_especial))'), $codEspecialesPagina)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Bitacora $bitacora) => strtoupper(trim((string) $bitacora->cod_especial)));

        $users = User::query()
            ->when($regional !== '', function ($query) use ($regional) {
                $this->applyUserRegionalFilter($query, $regional);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $regionales = $this->departamentosFiltro();
        $origenesCn33 = $this->departamentosFiltro();

        $provincias = Bitacora::query()
            ->select('provincia')
            ->whereNotNull('provincia')
            ->whereRaw("trim(provincia) <> ''")
            ->distinct()
            ->orderBy('provincia')
            ->pluck('provincia');

        $pendingCn33Alert = $this->cn33Service->getPendingRegistrationAlert(regional: $regionalScope);
        $reportRows = $this->buildDepartmentReportRows($latestIdsQuery);
        $reportByOrigin = $reportRows
            ->groupBy('origen_departamento')
            ->map(function ($rows, $origin) {
                return (object) [
                    'departamento' => $origin,
                    'total_registros' => (int) $rows->sum('total_registros'),
                    'total_precio' => round((float) $rows->sum('total_precio'), 2),
                    'total_peso' => round((float) $rows->sum('total_peso'), 3),
                ];
            })
            ->sortByDesc('total_precio')
            ->values();

        $reportByDestination = $reportRows
            ->groupBy('destino_departamento')
            ->map(function ($rows, $destination) {
                return (object) [
                    'departamento' => $destination,
                    'total_registros' => (int) $rows->sum('total_registros'),
                    'total_precio' => round((float) $rows->sum('total_precio'), 2),
                    'total_peso' => round((float) $rows->sum('total_peso'), 3),
                ];
            })
            ->sortByDesc('total_precio')
            ->values();

        $reportByTransportadora = $this->buildTransportadoraRankingRows($latestIdsQuery);

        $reportTotals = [
            'total_registros' => (int) $reportRows->sum('total_registros'),
            'total_precio' => round((float) $reportRows->sum('total_precio'), 2),
            'total_peso' => round((float) $reportRows->sum('total_peso'), 3),
            'origenes' => $reportByOrigin->count(),
            'destinos' => $reportByDestination->count(),
            'transportadoras' => $reportByTransportadora->count(),
        ];

        return compact(
            'bitacoras',
            'detallesPorCodEspecial',
            'users',
            'provincias',
            'regionales',
            'origenesCn33',
            'q',
            'userId',
            'codEspecial',
            'provincia',
            'regional',
            'origenCn33',
            'pendingCn33Alert',
            'reportRows',
            'reportByOrigin',
            'reportByDestination',
            'reportByTransportadora',
            'reportTotals'
        );
    }

    private function buildDepartmentReportRows($latestIdsQuery)
    {
        return Bitacora::query()
            ->from('bitacoras as b')
            ->leftJoin('paquetes_ems as pe', 'pe.id', '=', 'b.paquetes_ems_id')
            ->leftJoin('paquetes_contrato as pc', 'pc.id', '=', 'b.paquetes_contrato_id')
            ->whereIn('b.id', $latestIdsQuery)
            ->selectRaw("
                COALESCE(NULLIF(trim(upper(COALESCE(pe.origen, pc.origen, ''))), ''), 'SIN ORIGEN') as origen_departamento,
                COALESCE(NULLIF(trim(upper(COALESCE(pe.ciudad, pc.destino, ''))), ''), 'SIN DESTINO') as destino_departamento,
                COUNT(*) as total_registros,
                COALESCE(SUM(b.precio_total), 0) as total_precio,
                COALESCE(SUM(b.peso), 0) as total_peso
            ")
            ->groupByRaw("
                COALESCE(NULLIF(trim(upper(COALESCE(pe.origen, pc.origen, ''))), ''), 'SIN ORIGEN'),
                COALESCE(NULLIF(trim(upper(COALESCE(pe.ciudad, pc.destino, ''))), ''), 'SIN DESTINO')
            ")
            ->orderByDesc('total_precio')
            ->get();
    }

    private function buildDepartmentReportPayload(array $data): array
    {
        return [
            'generatedAt' => now(),
            'filters' => [
                'q' => (string) ($data['q'] ?? ''),
                'regional' => (string) ($data['regional'] ?? ''),
                'user' => optional(collect($data['users'] ?? [])->firstWhere('id', (int) ($data['userId'] ?? 0)))->name,
                'codEspecial' => (string) ($data['codEspecial'] ?? ''),
                'provincia' => (string) ($data['provincia'] ?? ''),
                'origenCn33' => (string) ($data['origenCn33'] ?? ''),
            ],
            'reportRows' => $data['reportRows'] ?? collect(),
            'reportByOrigin' => $data['reportByOrigin'] ?? collect(),
            'reportByDestination' => $data['reportByDestination'] ?? collect(),
            'reportByTransportadora' => $data['reportByTransportadora'] ?? collect(),
            'reportTotals' => $data['reportTotals'] ?? [],
        ];
    }

    private function buildTransportadoraRankingRows($latestIdsQuery)
    {
        return Bitacora::query()
            ->from('bitacoras as b')
            ->whereIn('b.id', $latestIdsQuery)
            ->selectRaw("
                COALESCE(NULLIF(trim(upper(COALESCE(b.transportadora, ''))), ''), 'SIN TRANSPORTADORA') as transportadora,
                COUNT(*) as total_registros,
                COALESCE(SUM(b.precio_total), 0) as total_precio,
                COALESCE(SUM(b.peso), 0) as total_peso
            ")
            ->groupByRaw("COALESCE(NULLIF(trim(upper(COALESCE(b.transportadora, ''))), ''), 'SIN TRANSPORTADORA')")
            ->orderByDesc('total_registros')
            ->orderByDesc('total_precio')
            ->get();
    }

    private function applyBitacoraFilters($query, string $q, int $userId, string $codEspecial, string $provincia, string $regional, string $origenCn33): void
    {
        $query
            ->when($userId > 0, function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->when($regional !== '', function ($query) use ($regional) {
                $query->whereHas('user', function ($userQuery) use ($regional) {
                    $this->applyUserRegionalFilter($userQuery, $regional);
                });
            })
            ->when($codEspecial !== '', function ($query) use ($codEspecial) {
                $query->whereRaw('trim(upper(cod_especial)) = ?', [$codEspecial]);
            })
            ->when($provincia !== '', function ($query) use ($provincia) {
                $query->whereRaw('trim(upper(COALESCE(provincia, \'\'))) = ?', [$provincia]);
            })
            ->when($origenCn33 !== '', function ($query) use ($origenCn33) {
                $query->where(function ($sub) use ($origenCn33) {
                    $sub->whereHas('paqueteEms', function ($emsQuery) use ($origenCn33) {
                        $emsQuery->whereRaw('trim(upper(COALESCE(origen, \'\'))) = ?', [$origenCn33]);
                    })
                        ->orWhereHas('paqueteContrato', function ($contratoQuery) use ($origenCn33) {
                            $contratoQuery->whereRaw('trim(upper(COALESCE(origen, \'\'))) = ?', [$origenCn33]);
                        });
                });
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('cod_especial', 'ILIKE', "%{$q}%")
                        ->orWhere('transportadora', 'ILIKE', "%{$q}%")
                        ->orWhere('provincia', 'ILIKE', "%{$q}%")
                        ->orWhere('factura', 'ILIKE', "%{$q}%")
                        ->orWhereHas('user', function ($userQuery) use ($q) {
                            $userQuery->where('name', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteEms', function ($emsQuery) use ($q) {
                            $emsQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteContrato', function ($contratoQuery) use ($q) {
                            $contratoQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteOrdi', function ($ordiQuery) use ($q) {
                            $ordiQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteCerti', function ($certiQuery) use ($q) {
                            $certiQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        });
                });
            });
    }

    private function applyUserRegionalFilter($query, string $regional): void
    {
        $query->where(function ($sub) use ($regional) {
            $sub->whereRaw('trim(upper(COALESCE(ciudad, \'\'))) = ?', [$regional])
                ->orWhereJsonContains('regionales', $regional);
        });
    }

    private function departamentosFiltro(): array
    {
        return ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    }

    public function create(): View
    {
        $regionalScope = $this->resolveRegionalScopeForUser(Auth::user());

        return view('bitacoras.create', [
            'bitacora' => new Bitacora(),
            'pendingCn33Alert' => $this->cn33Service->getPendingRegistrationAlert(regional: $regionalScope),
        ]);
    }

    public function cn33Summary(Request $request): JsonResponse
    {
        $codEspecial = strtoupper(trim((string) $request->query('cod_especial', '')));
        $regionalScope = $this->resolveRegionalScopeForUser($request->user());
        $summary = $this->cn33Service->getDispatchSummary($codEspecial, $regionalScope);

        return response()->json($summary);
    }

    public function extractFacturaQr(Request $request): JsonResponse
    {
        @set_time_limit(120);

        $request->validate([
            'imagen_factura' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $file = $request->file('imagen_factura');
        $startedAt = microtime(true);

        Log::info('Bitacora QR: solicitud recibida.', [
            'file' => $file?->getClientOriginalName(),
            'mime' => $file?->getMimeType(),
            'size' => $file?->getSize(),
            'user_id' => $request->user()?->id,
        ]);

        $result = $this->facturaQrService->extractFromUploadedFile($file);

        Log::info('Bitacora QR: solicitud finalizada.', [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return $this->facturaQrJsonResponse($result);
    }

    public function extractFacturaQrText(Request $request): JsonResponse
    {
        $request->validate([
            'qr_text' => ['required', 'string', 'max:4096'],
        ]);

        $startedAt = microtime(true);
        $qrText = (string) $request->input('qr_text');

        Log::info('Bitacora QR: texto recibido desde camara.', [
            'user_id' => $request->user()?->id,
            'qr_preview' => \Illuminate\Support\Str::limit($qrText, 160),
        ]);

        $result = $this->facturaQrService->extractFromQrText($qrText);

        Log::info('Bitacora QR: texto de camara procesado.', [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return $this->facturaQrJsonResponse($result);
    }

    private function facturaQrJsonResponse(array $result): JsonResponse
    {
        $invoiceData = $result['invoice_data'] ?? [];

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'QR procesado correctamente.',
            'data' => [
                'qr_url' => $result['qr_url'] ?? null,
                'qr_texto' => $result['qr_text'] ?? null,
                'factura' => $invoiceData['numero_factura'] ?? null,
                'precio_total' => $invoiceData['monto_total'] ?? null,
                'factura_fecha_emision' => $invoiceData['fecha_emision'] ?? null,
                'factura_nit_emisor' => $invoiceData['nit_emisor'] ?? null,
                'factura_cuf' => $invoiceData['cuf'] ?? null,
                'factura_razon_social' => $invoiceData['razon_social_emisor'] ?? null,
                'factura_cliente' => $invoiceData['nombre_cliente'] ?? null,
                'factura_direccion' => $invoiceData['direccion_emisor'] ?? null,
                'qr_datos' => $invoiceData,
                'verificado' => (bool) ($invoiceData['verified'] ?? false),
            ],
        ]);
    }

    private function resolveRegionalScopeForUser($user): ?string
    {
        if (!$user) {
            return null;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return null;
        }

        if (!method_exists($user, 'hasRole')) {
            return null;
        }

        if ($user->hasRole('encargado_ems') || $user->hasRole('cartero_ems')) {
            $regional = strtoupper(trim((string) ($user->ciudad ?? '')));

            return $regional !== '' ? $regional : null;
        }

        return null;
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validateStoreData($request);
        $createdOrUpdated = $this->storeByCodEspecial($payload);

        return redirect()
            ->route('bitacoras.index')
            ->with('success', $createdOrUpdated . ' bitacora(s) registrada(s) correctamente para el cod_especial ' . $payload['cod_especial'] . '.');
    }

    public function edit(Bitacora $bitacora): View
    {
        return view('bitacoras.edit', [
            'bitacora' => $bitacora,
        ]);
    }

    public function update(Request $request, Bitacora $bitacora): RedirectResponse
    {
        $data = $this->validateEditData($request, $bitacora);
        $message = 'No se actualizaron datos. La bitacora quedo sin cambios.';

        if ($data !== []) {
            $bitacora->update($data);
            $message = 'Factura e imagen de bitacora actualizadas correctamente.';
        }

        return redirect()
            ->route('bitacoras.index')
            ->with('success', $message);
    }

    public function destroy(Bitacora $bitacora): RedirectResponse
    {
        if (!empty($bitacora->imagen_factura) && Storage::disk('public')->exists($bitacora->imagen_factura)) {
            Storage::disk('public')->delete($bitacora->imagen_factura);
        }

        $bitacora->delete();

        return redirect()
            ->route('bitacoras.index')
            ->with('success', 'Bitacora eliminada correctamente.');
    }

    private function validateStoreData(Request $request): array
    {
        $validator = Validator::make(
            $request->all(),
            [
                'cod_especial' => ['required', 'string', 'max:50'],
                'transportadora' => ['nullable', 'string', 'max:255'],
                'provincia' => ['nullable', 'string', 'max:255'],
                'factura' => ['nullable', 'string', 'max:255'],
                'precio_total' => ['nullable', 'numeric', 'min:0'],
                'peso' => ['nullable', 'numeric', 'min:0'],
                'qr_url' => ['nullable', 'url', 'max:2048'],
                'factura_fecha_emision' => ['nullable', 'date'],
                'factura_nit_emisor' => ['nullable', 'string', 'max:50'],
                'factura_cuf' => ['nullable', 'string', 'max:255'],
                'factura_razon_social' => ['nullable', 'string', 'max:255'],
                'factura_cliente' => ['nullable', 'string', 'max:255'],
                'factura_direccion' => ['nullable', 'string', 'max:255'],
                'imagen_factura' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            ],
            [],
            [
                'cod_especial' => 'cod especial',
                'transportadora' => 'transportadora',
                'provincia' => 'provincia',
                'factura' => 'factura',
                'precio_total' => 'precio total',
                'peso' => 'peso',
                'qr_url' => 'url del QR',
                'factura_fecha_emision' => 'fecha de emision',
                'factura_nit_emisor' => 'NIT emisor',
                'factura_cuf' => 'CUF',
                'factura_razon_social' => 'razon social',
                'factura_cliente' => 'cliente de factura',
                'factura_direccion' => 'direccion de factura',
                'imagen_factura' => 'imagen de factura',
            ]
        );

        $validator->after(function ($validator) use ($request) {
            $codEspecial = strtoupper(trim((string) $request->input('cod_especial')));
            if ($codEspecial === '') {
                return;
            }

            $emsExists = PaqueteEms::query()
                ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                ->exists();

            $contratoExists = Recojo::query()
                ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                ->exists();

            $ordiExists = PaqueteOrdi::query()
                ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                ->exists();

            $certiExists = PaqueteCerti::query()
                ->where(function ($query) use ($codEspecial) {
                    $query->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                        ->orWhereRaw('trim(upper(COALESCE(codigo, \'\'))) = ?', [$codEspecial]);
                })
                ->exists();

            if (!$emsExists && !$contratoExists && !$ordiExists && !$certiExists) {
                $validator->errors()->add('cod_especial', 'No existen paquetes EMS, contratos, ordinarios o certificados con ese codigo.');
            }
        });

        $data = $validator->validate();

        $codEspecial = strtoupper(trim((string) ($data['cod_especial'] ?? '')));
        $totales = $this->obtenerTotalesPorCodEspecial($codEspecial);

        $data['cod_especial'] = $codEspecial;
        $data['user_id'] = (int) Auth::id();
        $data['transportadora'] = $this->normalizeUpperOrNull($data['transportadora'] ?? null);
        $data['provincia'] = $this->normalizeUpperOrNull($data['provincia'] ?? null);
        $data['factura'] = $this->emptyToNull($data['factura'] ?? null);
        $data['precio_total'] = $data['precio_total'] ?? ($totales['precio_total'] > 0 ? $totales['precio_total'] : null);
        $data['peso'] = $data['peso'] ?? $totales['peso'];
        $data['qr_url'] = $this->emptyToNull($data['qr_url'] ?? null);
        $data['factura_nit_emisor'] = $this->emptyToNull($data['factura_nit_emisor'] ?? null);
        $data['factura_cuf'] = $this->emptyToNull($data['factura_cuf'] ?? null);
        $data['factura_razon_social'] = $this->emptyToNull($data['factura_razon_social'] ?? null);
        $data['factura_cliente'] = $this->emptyToNull($data['factura_cliente'] ?? null);
        $data['factura_direccion'] = $this->emptyToNull($data['factura_direccion'] ?? null);
        $data['factura_fecha_emision'] = $this->normalizeDateTimeOrNull($data['factura_fecha_emision'] ?? null);
        $data['qr_texto'] = null;
        $data['qr_datos'] = null;

        if ($request->hasFile('imagen_factura')) {
            $data = $this->mergeQrFacturaData($data, $request->file('imagen_factura'));
            $data['imagen_factura'] = $request->file('imagen_factura')->store('bitacoras/facturas', 'public');
        } else {
            $data['imagen_factura'] = null;
        }

        return $data;
    }

    private function storeByCodEspecial(array $payload): int
    {
        $codEspecial = (string) $payload['cod_especial'];
        $ems = PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
            ->orderBy('id')
            ->get(['id']);

        $contratos = Recojo::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
            ->orderBy('id')
            ->get(['id']);

        $ordinarios = PaqueteOrdi::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
            ->orderBy('id')
            ->get(['id']);

        $certificados = PaqueteCerti::query()
            ->where(function ($query) use ($codEspecial) {
                $query->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                    ->orWhereRaw('trim(upper(COALESCE(codigo, \'\'))) = ?', [$codEspecial]);
            })
            ->orderBy('id')
            ->get(['id']);

        $total = 0;
        $items = collect();

        foreach ($ems as $paquete) {
            $items->push([
                'paquetes_ems_id' => (int) $paquete->id,
                'paquetes_contrato_id' => null,
                'paquetes_ordi_id' => null,
                'paquetes_certi_id' => null,
            ]);
        }

        foreach ($contratos as $contrato) {
            $items->push([
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => (int) $contrato->id,
                'paquetes_ordi_id' => null,
                'paquetes_certi_id' => null,
            ]);
        }

        foreach ($ordinarios as $ordinario) {
            $items->push([
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => null,
                'paquetes_ordi_id' => (int) $ordinario->id,
                'paquetes_certi_id' => null,
            ]);
        }

        foreach ($certificados as $certificado) {
            $items->push([
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => null,
                'paquetes_ordi_id' => null,
                'paquetes_certi_id' => (int) $certificado->id,
            ]);
        }

        DB::transaction(function () use ($payload, $items, &$total) {
            $lastIndex = max(0, $items->count() - 1);

            foreach ($items->values() as $index => $item) {
                $attributes = [
                    'cod_especial' => $payload['cod_especial'],
                    'paquetes_ems_id' => $item['paquetes_ems_id'],
                    'paquetes_contrato_id' => $item['paquetes_contrato_id'],
                    'paquetes_ordi_id' => $item['paquetes_ordi_id'],
                    'paquetes_certi_id' => $item['paquetes_certi_id'],
                ];
                $values = [
                    'user_id' => $payload['user_id'],
                    'transportadora' => $payload['transportadora'],
                    'provincia' => $payload['provincia'],
                    'factura' => $payload['factura'],
                    'precio_total' => $index === $lastIndex ? $payload['precio_total'] : null,
                    'peso' => $index === $lastIndex ? $payload['peso'] : null,
                    'qr_url' => $payload['qr_url'] ?? null,
                    'qr_texto' => $payload['qr_texto'] ?? null,
                    'qr_datos' => $payload['qr_datos'] ?? null,
                    'factura_fecha_emision' => $payload['factura_fecha_emision'] ?? null,
                    'factura_nit_emisor' => $payload['factura_nit_emisor'] ?? null,
                    'factura_cuf' => $payload['factura_cuf'] ?? null,
                    'factura_razon_social' => $payload['factura_razon_social'] ?? null,
                    'factura_cliente' => $payload['factura_cliente'] ?? null,
                    'factura_direccion' => $payload['factura_direccion'] ?? null,
                ];

                if (!empty($payload['imagen_factura'])) {
                    $values['imagen_factura'] = $payload['imagen_factura'];
                }

                Bitacora::query()->updateOrCreate($attributes, $values);
                $total++;
            }
        });

        return $total;
    }

    private function validateEditData(Request $request, Bitacora $bitacora): array
    {
        $data = $request->validate(
            [
                'factura' => ['nullable', 'string', 'max:255'],
                'qr_url' => ['nullable', 'url', 'max:2048'],
                'factura_fecha_emision' => ['nullable', 'date'],
                'factura_nit_emisor' => ['nullable', 'string', 'max:50'],
                'factura_cuf' => ['nullable', 'string', 'max:255'],
                'factura_razon_social' => ['nullable', 'string', 'max:255'],
                'factura_cliente' => ['nullable', 'string', 'max:255'],
                'factura_direccion' => ['nullable', 'string', 'max:255'],
                'imagen_factura' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            ],
            [],
            [
                'factura' => 'factura',
                'qr_url' => 'url del QR',
                'factura_fecha_emision' => 'fecha de emision',
                'factura_nit_emisor' => 'NIT emisor',
                'factura_cuf' => 'CUF',
                'factura_razon_social' => 'razon social',
                'factura_cliente' => 'cliente de factura',
                'factura_direccion' => 'direccion de factura',
                'imagen_factura' => 'imagen de factura',
            ]
        );

        $updates = [];

        $factura = $this->emptyToNull($data['factura'] ?? null);
        if ($factura !== $this->emptyToNull($bitacora->factura)) {
            $updates['factura'] = $factura;
        }

        foreach ([
            'qr_url',
            'factura_nit_emisor',
            'factura_cuf',
            'factura_razon_social',
            'factura_cliente',
            'factura_direccion',
        ] as $field) {
            $incoming = $this->emptyToNull($data[$field] ?? null);
            $current = $this->emptyToNull($bitacora->{$field});

            if ($incoming !== $current) {
                $updates[$field] = $incoming;
            }
        }

        $facturaFecha = $this->normalizeDateTimeOrNull($data['factura_fecha_emision'] ?? null);
        $facturaFechaActual = $this->normalizeDateTimeOrNull($bitacora->factura_fecha_emision?->format('Y-m-d H:i:s'));
        if ($facturaFecha !== $facturaFechaActual) {
            $updates['factura_fecha_emision'] = $facturaFecha;
        }

        if ($request->hasFile('imagen_factura')) {
            $updates = $this->mergeQrFacturaData($updates, $request->file('imagen_factura'));

            if (!empty($bitacora->imagen_factura) && Storage::disk('public')->exists($bitacora->imagen_factura)) {
                Storage::disk('public')->delete($bitacora->imagen_factura);
            }

            $updates['imagen_factura'] = $request->file('imagen_factura')->store('bitacoras/facturas', 'public');
        }

        return $updates;
    }

    private function obtenerTotalesPorCodEspecial(string $codEspecial): array
    {
        $codigo = strtoupper(trim($codEspecial));

        $pesoEms = (float) PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('peso');

        $pesoContrato = (float) Recojo::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('peso');

        $pesoOrdi = (float) PaqueteOrdi::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('peso');

        $pesoCerti = (float) PaqueteCerti::query()
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
                    ->orWhereRaw('trim(upper(COALESCE(codigo, \'\'))) = ?', [$codigo]);
            })
            ->sum('peso');

        $precioEms = (float) PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('precio');

        $precioContrato = (float) Recojo::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('precio');

        return [
            'peso' => round($pesoEms + $pesoContrato + $pesoOrdi + $pesoCerti, 3),
            'precio_total' => round($precioEms + $precioContrato, 2),
        ];
    }

    private function emptyToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizeUpperOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : strtoupper($text);
    }

    private function normalizeDateTimeOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($text)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function mergeQrFacturaData(array $data, \Illuminate\Http\UploadedFile $file): array
    {
        $result = $this->facturaQrService->extractFromUploadedFile($file);
        if (!($result['success'] ?? false)) {
            return $data;
        }

        $invoiceData = $result['invoice_data'] ?? [];

        $data['qr_url'] = $result['qr_url'] ?? ($data['qr_url'] ?? null);
        $data['qr_texto'] = $result['qr_text'] ?? ($data['qr_texto'] ?? null);
        $data['qr_datos'] = $invoiceData;
        $data['factura'] = $data['factura'] ?? ($invoiceData['numero_factura'] ?? null);
        $data['precio_total'] = $data['precio_total'] ?? ($invoiceData['monto_total'] ?? null);
        $data['factura_fecha_emision'] = $data['factura_fecha_emision'] ?? $this->normalizeDateTimeOrNull($invoiceData['fecha_emision'] ?? null);
        $data['factura_nit_emisor'] = $data['factura_nit_emisor'] ?? ($invoiceData['nit_emisor'] ?? null);
        $data['factura_cuf'] = $data['factura_cuf'] ?? ($invoiceData['cuf'] ?? null);
        $data['factura_razon_social'] = $data['factura_razon_social'] ?? ($invoiceData['razon_social_emisor'] ?? null);
        $data['factura_cliente'] = $data['factura_cliente'] ?? ($invoiceData['nombre_cliente'] ?? null);
        $data['factura_direccion'] = $data['factura_direccion'] ?? ($invoiceData['direccion_emisor'] ?? null);

        return $data;
    }
}
