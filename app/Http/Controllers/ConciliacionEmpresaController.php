<?php

namespace App\Http\Controllers;

use App\Models\ConciliacionEmpresa;
use App\Models\Empresa;
use App\Services\FacturacionReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConciliacionEmpresaController extends Controller
{
    public function __construct(private readonly FacturacionReportService $facturacion) {}

    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $anio = (int) ($filters['anio'] ?? now()->year);
        $mes = (int) ($filters['mes'] ?? now()->month);
        $empresaUsuarioId = $this->esUsuarioEmpresa($request)
            ? $this->empresaIdUsuarioAutorizada($request)
            : null;

        $conciliaciones = ConciliacionEmpresa::query()
            ->where('anio', $anio)
            ->when($empresaUsuarioId !== null, function ($query) use ($empresaUsuarioId): void {
                $query->where('empresa_id', $empresaUsuarioId);
            })
            ->get()
            ->keyBy(fn (ConciliacionEmpresa $item) => $item->empresa_id.'-'.$item->mes);

        $empresas = Empresa::query()
            ->when($empresaUsuarioId !== null, function ($query) use ($empresaUsuarioId): void {
                $query->whereKey($empresaUsuarioId);
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla', 'codigo_cliente'])
            ->each(function (Empresa $empresa) use ($conciliaciones, $mes): void {
                $empresa->setRelation('conciliacionActual', $conciliaciones->get($empresa->id.'-'.$mes));
            });

        $resumenMeses = collect(self::MESES)->mapWithKeys(function (string $nombre, int $numero) use ($conciliaciones, $empresas): array {
            $items = $conciliaciones->filter(fn (ConciliacionEmpresa $item) => $item->mes === $numero);

            return [$numero => [
                'nombre' => $nombre,
                'documentos' => $items->whereNotNull('documento_path')->count(),
                'conciliados' => $items->whereNotNull('conciliado_at')->count(),
                'por_cobrar' => $items->whereNotNull('factura_venta_id')->count(),
                'pagos_recibidos' => $items->whereNotNull('pago_comprobante_path')->count(),
                'pagos_confirmados' => $items->whereNotNull('confirmacion_pago_at')->count(),
                'total' => $empresas->count(),
            ]];
        });

        return view('conciliacion.conciliaciones', compact(
            'anio', 'mes', 'empresas', 'conciliaciones', 'resumenMeses'
        ));
    }

    public function subirDocumento(Request $request, Empresa $empresa)
    {
        $this->autorizarEmpresa($request, $empresa);
        $periodo = $this->validarPeriodo($request);
        $conciliacion = ConciliacionEmpresa::query()
            ->where('empresa_id', $empresa->id)
            ->where('anio', (int) $periodo['anio'])
            ->where('mes', (int) $periodo['mes'])
            ->first();
        if ($conciliacion?->conciliado_at) {
            return $this->volver($periodo)->withErrors([
                'documento' => 'El archivo ya no puede modificarse porque la empresa fue marcada como Conciliada.',
            ]);
        }

        $request->validate([
            'documento' => [
                'required',
                'file',
                'max:20480',
                'mimetypes:application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extensions:pdf,xls,xlsx',
            ],
        ], [
            'documento.required' => 'Selecciona un documento PDF o Excel.',
            'documento.max' => 'El documento no puede superar los 20 MB.',
            'documento.mimetypes' => 'El documento debe ser PDF o Excel.',
            'documento.extensions' => 'El documento debe tener extensión PDF, XLS o XLSX.',
        ]);

        $conciliacion ??= $this->registro($empresa, $periodo);
        $archivo = $request->file('documento');
        $path = $archivo->store("conciliaciones/{$periodo['anio']}/{$periodo['mes']}", 'public');
        $anterior = $conciliacion->documento_path;

        $conciliacion->forceFill([
            'conciliacion_at' => now(),
            'conciliacion_por' => $request->user()?->id,
            'documento_path' => $path,
            'documento_nombre' => $archivo->getClientOriginalName(),
            'documento_mime' => $archivo->getMimeType(),
            'documento_tamano' => $archivo->getSize(),
            'documento_at' => now(),
            'documento_por' => $request->user()?->id,
        ])->save();

        if ($anterior && $anterior !== $path) {
            Storage::disk('public')->delete($anterior);
        }

        return $this->volver($periodo, 'Documento guardado y asociado a '.$empresa->nombre.'.');
    }

    public function descargarDocumento(Request $request, ConciliacionEmpresa $conciliacion)
    {
        $this->autorizarConciliacion($request, $conciliacion);
        abort_unless($conciliacion->documento_path, 404);
        abort_unless(Storage::disk('public')->exists($conciliacion->documento_path), 404);

        return Storage::disk('public')->download(
            $conciliacion->documento_path,
            $conciliacion->documento_nombre ?: basename($conciliacion->documento_path)
        );
    }

    public function marcarConciliado(Request $request, ConciliacionEmpresa $conciliacion)
    {
        $this->autorizarConciliacion($request, $conciliacion);
        if (! $conciliacion->documento_path) {
            return $this->volver([
                'anio' => $conciliacion->anio,
                'mes' => $conciliacion->mes,
            ])->withErrors([
                'conciliado' => 'Primero debes subir el Excel o PDF de conciliación.',
            ]);
        }

        if (! $conciliacion->conciliado_at) {
            $conciliacion->forceFill([
                'conciliado_at' => now(),
                'conciliado_por' => $request->user()?->id,
            ])->save();
        }

        return $this->volver([
            'anio' => $conciliacion->anio,
            'mes' => $conciliacion->mes,
        ], 'Conciliación aprobada para '.$conciliacion->empresa?->nombre.'.');
    }

    public function asociarPorCobrar(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 1)],
            'mes' => ['required', 'integer', 'between:1,12'],
            'facturado_anio' => ['required', 'integer', 'between:2000,'.(now()->year + 1)],
            'facturado_mes' => ['required', 'integer', 'between:1,12'],
            'factura_venta_id' => ['required', 'string', 'max:100'],
            'origen' => ['nullable', 'in:conciliaciones,facturado'],
        ]);

        $empresa = Empresa::query()->findOrFail((int) $data['empresa_id']);
        $this->autorizarEmpresa($request, $empresa);
        $conciliacion = $this->registro($empresa, $data);
        if (! $conciliacion->conciliado_at) {
            throw ValidationException::withMessages([
                'empresa_id' => "Primero debes marcar como Conciliado el documento de {$empresa->nombre} para ese mes.",
            ]);
        }

        try {
            $factura = $this->buscarFacturaContratos(
                (string) $data['factura_venta_id'],
                (int) $data['facturado_mes'],
                (int) $data['facturado_anio']
            );
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'factura_venta_id' => 'No se pudo verificar la factura con la API. Intenta nuevamente.',
            ]);
        }
        if (! $factura) {
            throw ValidationException::withMessages([
                'factura_venta_id' => 'La factura ya no se encuentra en la API para el período facturado seleccionado.',
            ]);
        }

        $ocupada = ConciliacionEmpresa::query()
            ->where('factura_venta_id', $data['factura_venta_id'])
            ->whereKeyNot($conciliacion->id)
            ->with('empresa:id,nombre')
            ->first();
        if ($ocupada) {
            throw ValidationException::withMessages([
                'factura_venta_id' => 'Esta factura ya está asociada a '.($ocupada->empresa?->nombre ?? 'otra empresa').'.',
            ]);
        }

        try {
            $pdf = $this->facturacion->invoicePdf((string) ($factura['codigoSeguimiento'] ?? ''));
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'factura_venta_id' => 'La factura fue encontrada, pero no se pudo obtener su PDF oficial. Intenta nuevamente.',
            ]);
        }
        if ($this->esUsuarioEmpresa($request) && ! $this->facturaCorrespondeAlUsuario($request, $pdf)) {
            throw ValidationException::withMessages([
                'factura_venta_id' => 'La factura seleccionada no corresponde al código de cliente de tu empresa.',
            ]);
        }

        $pdfPath = 'conciliaciones/facturas/'.$data['facturado_anio'].'/'.$data['facturado_mes'].'/'.Str::uuid().'.pdf';
        Storage::disk('public')->put($pdfPath, $pdf['content']);
        $pdfAnterior = $conciliacion->factura_pdf_path;
        $pagoComprobanteAnterior = $conciliacion->pago_comprobante_path;

        $conciliacion->forceFill([
            'factura_venta_id' => (string) $factura['ventaId'],
            'factura_detalle_id' => filled($factura['detalleId'] ?? null) ? (string) $factura['detalleId'] : null,
            'factura_descripcion' => (string) ($factura['descripcion'] ?? ''),
            'factura_codigo_orden' => (string) ($factura['codigoOrden'] ?? ''),
            'factura_codigo_seguimiento' => (string) ($factura['codigoSeguimiento'] ?? ''),
            'factura_fecha' => filled($factura['fecha'] ?? null) ? Carbon::parse($factura['fecha']) : null,
            'factura_monto' => (float) ($factura['totalLinea'] ?? 0),
            'facturado_anio' => (int) $data['facturado_anio'],
            'facturado_mes' => (int) $data['facturado_mes'],
            'por_cobrar_at' => now(),
            'por_cobrar_por' => $request->user()?->id,
            'factura_cuf' => $pdf['cuf'],
            'factura_numero' => $pdf['numero'],
            'factura_pdf_path' => $pdfPath,
            ...$this->facturaClienteAttributes($pdf),
            'pago_recibido_at' => null,
            'pago_recibido_por' => null,
            'pago_comprobante_path' => null,
            'pago_comprobante_nombre' => null,
            'pago_comprobante_tamano' => null,
            'confirmacion_pago_at' => null,
            'confirmacion_pago_por' => null,
        ])->save();

        if ($pdfAnterior && $pdfAnterior !== $pdfPath) {
            Storage::disk('public')->delete($pdfAnterior);
        }
        if ($pagoComprobanteAnterior) {
            Storage::disk('public')->delete($pagoComprobanteAnterior);
        }

        $route = ($data['origen'] ?? 'facturado') === 'conciliaciones'
            ? 'dashboard.conciliacion.conciliaciones'
            : 'dashboard.conciliacion.facturado';
        $parameters = $route === 'dashboard.conciliacion.conciliaciones'
            ? ['anio' => $data['anio'], 'mes' => $data['mes']]
            : ['anio' => $data['facturado_anio'], 'meses' => [$data['facturado_mes']]];

        return redirect()->route($route, $parameters)
            ->with('success', "Factura asociada a {$empresa->nombre} en Por cobrar.");
    }

    public function facturasDisponibles(Request $request)
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 1)],
            'mes' => ['required', 'integer', 'between:1,12'],
        ]);

        try {
            $facturas = $this->facturasContratos((int) $data['mes'], (int) $data['anio']);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo consultar la API de facturación. Intenta nuevamente.',
            ], 422);
        }

        $asociaciones = ConciliacionEmpresa::query()
            ->with('empresa:id,nombre')
            ->whereIn('factura_venta_id', $facturas->pluck('ventaId')->filter())
            ->get()
            ->keyBy('factura_venta_id');
        $datosFiscales = $this->facturacion->invoiceFiscalDataBatch(
            $facturas->pluck('codigoSeguimiento')->filter()->all()
        );

        $facturasRespuesta = $facturas->map(function (array $factura) use ($asociaciones, $datosFiscales): array {
            $asociacion = $asociaciones->get((string) ($factura['ventaId'] ?? ''));
            $fiscal = $datosFiscales[(string) ($factura['codigoSeguimiento'] ?? '')] ?? [];

            return [
                'ventaId' => (string) ($factura['ventaId'] ?? ''),
                'codigoOrden' => (string) ($factura['codigoOrden'] ?? ''),
                'codigoSeguimiento' => (string) ($factura['codigoSeguimiento'] ?? ''),
                'descripcion' => (string) ($factura['descripcion'] ?? ''),
                'fecha' => (string) ($factura['fecha'] ?? ''),
                'totalLinea' => (float) ($factura['totalLinea'] ?? 0),
                'asociadaEmpresaId' => $asociacion?->empresa_id,
                'asociadaEmpresa' => $asociacion?->empresa?->nombre,
                'razonSocial' => (string) ($fiscal['razon_social'] ?? ''),
                'codigoCliente' => (string) ($fiscal['codigo_cliente'] ?? ''),
                'numeroDocumento' => (string) ($fiscal['numero_documento'] ?? ''),
            ];
        });
        if ($this->esUsuarioEmpresa($request)) {
            $codigoCliente = $this->codigoClienteUsuario($request);
            $facturasRespuesta = $facturasRespuesta->filter(
                fn (array $factura): bool => $codigoCliente !== ''
                    && $this->normalizarCodigoCliente($factura['codigoCliente']) === $codigoCliente
            );
        }

        return response()->json([
            'facturas' => $facturasRespuesta->values(),
        ]);
    }

    public function descargarFacturaPdf(Request $request, ConciliacionEmpresa $conciliacion)
    {
        $this->autorizarConciliacion($request, $conciliacion);
        abort_unless($conciliacion->factura_venta_id, 404);

        if (
            ! $conciliacion->factura_pdf_path
            || ! Storage::disk('public')->exists($conciliacion->factura_pdf_path)
            || blank($conciliacion->factura_razon_social)
        ) {
            try {
                $pdf = $this->facturacion->invoicePdf((string) $conciliacion->factura_codigo_seguimiento);
                $path = $conciliacion->factura_pdf_path;
                if (! $path || ! Storage::disk('public')->exists($path)) {
                    $path = 'conciliaciones/facturas/'.$conciliacion->facturado_anio.'/'.$conciliacion->facturado_mes.'/'.Str::uuid().'.pdf';
                    Storage::disk('public')->put($path, $pdf['content']);
                }
                $conciliacion->forceFill([
                    'factura_cuf' => $pdf['cuf'],
                    'factura_numero' => $pdf['numero'],
                    'factura_pdf_path' => $path,
                    ...$this->facturaClienteAttributes($pdf),
                ])->save();
            } catch (\Throwable $exception) {
                report($exception);
                abort(404, 'No se pudo obtener el PDF oficial de la factura.');
            }
        }

        $filename = 'factura-'.($conciliacion->factura_numero ?: $conciliacion->factura_codigo_orden ?: $conciliacion->id).'.pdf';

        return Storage::disk('public')->download($conciliacion->factura_pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function marcarPagoRecibido(Request $request, ConciliacionEmpresa $conciliacion)
    {
        $this->autorizarConciliacion($request, $conciliacion);
        if (! $conciliacion->factura_venta_id) {
            return redirect()->route('dashboard.conciliacion.conciliaciones', [
                'anio' => $conciliacion->anio,
                'mes' => $conciliacion->mes,
            ])->withErrors([
                'pago_recibido' => 'Primero debes asociar una factura en Por cobrar.',
            ]);
        }

        $request->validate([
            'comprobante_pago' => [
                'required',
                'file',
                'max:20480',
                'mimetypes:application/pdf',
                'extensions:pdf',
            ],
        ], [
            'comprobante_pago.required' => 'Selecciona el PDF de confirmación del pago.',
            'comprobante_pago.max' => 'El PDF de confirmación no puede superar los 20 MB.',
            'comprobante_pago.mimetypes' => 'El comprobante de pago debe ser un archivo PDF.',
            'comprobante_pago.extensions' => 'El comprobante de pago debe tener extensión PDF.',
        ]);

        $archivo = $request->file('comprobante_pago');
        $path = $archivo->store("conciliaciones/pagos/{$conciliacion->anio}/{$conciliacion->mes}", 'public');
        $anterior = $conciliacion->pago_comprobante_path;

        $conciliacion->forceFill([
            'pago_recibido_at' => now(),
            'pago_recibido_por' => $request->user()?->id,
            'pago_comprobante_path' => $path,
            'pago_comprobante_nombre' => $archivo->getClientOriginalName(),
            'pago_comprobante_tamano' => $archivo->getSize(),
            'confirmacion_pago_at' => null,
            'confirmacion_pago_por' => null,
        ])->save();

        if ($anterior && $anterior !== $path) {
            Storage::disk('public')->delete($anterior);
        }

        return redirect()->route('dashboard.conciliacion.conciliaciones', [
            'anio' => $conciliacion->anio,
            'mes' => $conciliacion->mes,
        ])->with('success', 'Pago recibido registrado para '.$conciliacion->empresa?->nombre.'.');
    }

    private function facturaClienteAttributes(array $pdf): array
    {
        return [
            'factura_razon_social' => $pdf['razon_social'] ?: null,
            'factura_codigo_cliente' => $pdf['codigo_cliente'] ?: null,
            'factura_numero_documento' => $pdf['numero_documento'] ?: null,
            'factura_tipo_documento' => $pdf['tipo_documento'] ?: null,
        ];
    }

    public function descargarComprobantePago(Request $request, ConciliacionEmpresa $conciliacion)
    {
        $this->autorizarConciliacion($request, $conciliacion);
        abort_unless($conciliacion->pago_comprobante_path, 404);
        abort_unless(Storage::disk('public')->exists($conciliacion->pago_comprobante_path), 404);

        return Storage::disk('public')->download(
            $conciliacion->pago_comprobante_path,
            $conciliacion->pago_comprobante_nombre ?: 'comprobante-pago-'.$conciliacion->id.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function confirmarPago(Request $request, ConciliacionEmpresa $conciliacion)
    {
        $this->autorizarConciliacion($request, $conciliacion);
        if (! $conciliacion->pago_comprobante_path) {
            return redirect()->route('dashboard.conciliacion.conciliaciones', [
                'anio' => $conciliacion->anio,
                'mes' => $conciliacion->mes,
            ])->withErrors([
                'confirmacion_pago' => 'Primero debes registrar Pago recibido y subir su PDF de confirmación.',
            ]);
        }

        if (! $conciliacion->confirmacion_pago_at) {
            $conciliacion->forceFill([
                'confirmacion_pago_at' => now(),
                'confirmacion_pago_por' => $request->user()?->id,
            ])->save();
        }

        return redirect()->route('dashboard.conciliacion.conciliaciones', [
            'anio' => $conciliacion->anio,
            'mes' => $conciliacion->mes,
        ])->with('success', 'Pago confirmado para '.$conciliacion->empresa?->nombre.'.');
    }

    private function buscarFacturaContratos(string $ventaId, int $mes, int $anio): ?array
    {
        return $this->facturasContratos($mes, $anio)
            ->first(fn (array $row) => (string) ($row['ventaId'] ?? '') === $ventaId);
    }

    private function facturasContratos(int $mes, int $anio)
    {
        $servicios = collect($this->facturacion->services($mes, $anio, 200)['servicios'] ?? [])
            ->pluck('servicio')
            ->filter(fn ($servicio) => str_contains(mb_strtolower((string) $servicio), 'contrato'));

        $facturas = collect();
        foreach ($servicios as $servicio) {
            $rows = (array) (($this->facturacion->serviceDetail((string) $servicio, $mes, $anio)['servicio'] ?? [])['rows'] ?? []);
            foreach ($rows as $row) {
                $facturas->push((array) $row);
            }
        }

        return $facturas
            ->filter(fn (array $row) => filled($row['ventaId'] ?? null))
            ->unique(fn (array $row) => (string) $row['ventaId'])
            ->sortByDesc('fecha')
            ->values();
    }

    private function esUsuarioEmpresa(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('empresa');
    }

    private function autorizarEmpresa(Request $request, Empresa $empresa): void
    {
        if (! $this->esUsuarioEmpresa($request)) {
            return;
        }

        $empresaAsignada = $request->user()?->empresa()->first(['id', 'codigo_cliente']);
        $codigoAsignado = $this->normalizarCodigoCliente($empresaAsignada?->codigo_cliente);
        $codigoSolicitado = $this->normalizarCodigoCliente($empresa->codigo_cliente);

        abort_unless(
            $empresaAsignada
                && (int) $empresaAsignada->id === (int) $empresa->id
                && $codigoAsignado !== ''
                && $codigoAsignado === $codigoSolicitado,
            403,
            'No tienes acceso a la conciliación de esta empresa.'
        );
    }

    private function empresaIdUsuarioAutorizada(Request $request): int
    {
        $empresa = $request->user()?->empresa()->first(['id', 'codigo_cliente']);

        return $empresa && $this->normalizarCodigoCliente($empresa->codigo_cliente) !== ''
            ? (int) $empresa->id
            : -1;
    }

    private function autorizarConciliacion(Request $request, ConciliacionEmpresa $conciliacion): void
    {
        $conciliacion->loadMissing('empresa:id,codigo_cliente');
        abort_unless($conciliacion->empresa, 404);
        $this->autorizarEmpresa($request, $conciliacion->empresa);
    }

    private function facturaCorrespondeAlUsuario(Request $request, array $pdf): bool
    {
        $codigoCliente = $this->codigoClienteUsuario($request);

        return $codigoCliente !== ''
            && $this->normalizarCodigoCliente($pdf['codigo_cliente'] ?? null) === $codigoCliente;
    }

    private function codigoClienteUsuario(Request $request): string
    {
        return $this->normalizarCodigoCliente(
            $request->user()?->empresa()->value('codigo_cliente')
        );
    }

    private function normalizarCodigoCliente(mixed $codigo): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', trim((string) $codigo)));
    }

    private function validarPeriodo(Request $request): array
    {
        return $request->validate([
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 1)],
            'mes' => ['required', 'integer', 'between:1,12'],
        ]);
    }

    private function registro(Empresa $empresa, array $periodo): ConciliacionEmpresa
    {
        return ConciliacionEmpresa::query()->firstOrCreate([
            'empresa_id' => $empresa->id,
            'anio' => (int) $periodo['anio'],
            'mes' => (int) $periodo['mes'],
        ]);
    }

    private function volver(array $periodo, ?string $mensaje = null)
    {
        $redirect = redirect()->route('dashboard.conciliacion.conciliaciones', $periodo);

        return $mensaje ? $redirect->with('success', $mensaje) : $redirect;
    }
}
