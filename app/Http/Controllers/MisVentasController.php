<?php

namespace App\Http\Controllers;

use App\Models\FacturacionCart;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Milon\Barcode\DNS2D;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MisVentasController extends Controller
{
    public function index(Request $request): View
    {
        [$user, $filters] = $this->resolveRequestContext($request);

        $query = FacturacionCart::query()
            ->with('items')
            ->where('user_id', $user->id)
            ->latest('emitido_en')
            ->latest('id');

        $this->applyFilters(
            $query,
            $filters['estado'],
            $filters['estado_emision'],
            $filters['from'],
            $filters['to'],
            $filters['q']
        );

        $carts = $query->paginate($filters['per_page'])->withQueryString();

        $summaryBase = FacturacionCart::query()
            ->where('user_id', $user->id);

        $this->applyFilters(
            $summaryBase,
            $filters['estado'],
            $filters['estado_emision'],
            $filters['from'],
            $filters['to'],
            $filters['q']
        );

        $summary = [
            'totalVentas' => (clone $summaryBase)->where('estado', 'emitido')->count(),
            'totalBorradores' => (clone $summaryBase)->where('estado', 'borrador')->count(),
            'facturadas' => (clone $summaryBase)->whereRaw("upper(coalesce(estado_emision, '')) = 'FACTURADA'")->count(),
            'pendientes' => (clone $summaryBase)->whereRaw("upper(coalesce(estado_emision, '')) = 'PENDIENTE'")->count(),
            'rechazadas' => (clone $summaryBase)->whereRaw("upper(coalesce(estado_emision, '')) = 'RECHAZADA'")->count(),
            'montoTotal' => (float) ((clone $summaryBase)->where('estado', 'emitido')->sum('total')),
        ];

        return view('facturacion.mis-ventas', [
            'carts' => $carts,
            'summary' => $summary,
            'filters' => $filters,
        ]);
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        [$user, $filters] = $this->resolveRequestContext($request);

        $carts = FacturacionCart::query()
            ->with('items')
            ->where('user_id', $user->id)
            ->latest('emitido_en')
            ->latest('id');

        $this->applyFilters(
            $carts,
            $filters['estado'],
            $filters['estado_emision'],
            $filters['from'],
            $filters['to'],
            $filters['q']
        );

        $carts = $carts->get();
        $rows = $this->buildPdfRows($carts);

        $totals = [
            'parcial' => round((float) $rows->sum('importe_parcial'), 2),
            'general' => round((float) $rows->sum('importe_general'), 2),
        ];

        $pdf = Pdf::loadView('facturacion.mis-ventas-kardex-pdf', [
            'user' => $user,
            'filters' => $filters,
            'carts' => $carts,
            'rows' => $rows,
            'totals' => $totals,
            'generatedAt' => now(),
        ])->setPaper('A4', 'portrait');

        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function ticket(Request $request, FacturacionCart $cart): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');
        abort_unless((int) $cart->user_id === (int) $user->id, 403, 'No puedes ver un ticket de otra venta.');

        $ticket = $this->buildTicketData($cart, $user);
        $paper = [0, 0, 226.77, 680];
        $pdf = Pdf::loadView('facturacion.mis-ventas-ticket', [
            'cart' => $cart,
            'ticket' => $ticket,
        ])->setPaper($paper, 'portrait');

        $filename = 'ticket-' . ($cart->codigo_orden ?: ('venta-' . $cart->id)) . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    private function resolveRequestContext(Request $request): array
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');

        $validated = $request->validate([
            'estado' => ['nullable', 'in:all,borrador,emitido'],
            'estado_emision' => ['nullable', 'in:all,FACTURADA,PENDIENTE,RECHAZADA,ERROR'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return [
            $user,
            [
                'estado' => (string) ($validated['estado'] ?? 'all'),
                'estado_emision' => (string) ($validated['estado_emision'] ?? 'all'),
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'q' => trim((string) ($validated['q'] ?? '')),
                'per_page' => (int) ($validated['per_page'] ?? 20),
            ],
        ];
    }

    private function buildPdfRows(Collection $carts): Collection
    {
        return $carts->flatMap(function (FacturacionCart $cart) {
            $respuesta = (array) ($cart->respuesta_emision ?? []);
            $numeroFactura = trim((string) (
                data_get($respuesta, 'factura.nroFactura')
                ?? data_get($respuesta, 'factura.numeroFactura')
                ?? data_get($respuesta, 'consultaSefe.nroFactura')
                ?? data_get($respuesta, 'consultaSefe.detalleFactura.cabecera.numeroFactura')
                ?? data_get($respuesta, 'factura.numero')
                ?? data_get($respuesta, 'numeroFactura')
                ?? $cart->id
            ));

            return $cart->items->map(function ($item) use ($cart, $numeroFactura) {
                $resumen = (array) ($item->resumen_origen ?? []);
                $fecha = $cart->emitido_en ?? $cart->created_at;
                $origen = trim((string) ($resumen['ciudad'] ?? $resumen['origen'] ?? $item->origen_tipo ?? ''));
                $tipoEnvio = trim((string) ($item->nombre_servicio ?: $item->titulo ?: 'SIN SERVICIO'));
                $codigoItem = trim((string) (($resumen['codigo'] ?? null) ?: $item->codigo ?: ('ITEM-' . $item->id)));
                $peso = (float) ($resumen['peso'] ?? 0);
                $cantidad = max(1, (int) ($item->cantidad ?? 1));
                $importeParcial = round((float) ($item->monto_base ?? 0), 2);
                $importeGeneral = round((float) ($item->total_linea ?? 0), 2);

                return [
                    'fecha' => $fecha?->format('d/m/Y') ?? '-',
                    'origen' => $origen !== '' ? $origen : '-',
                    'tipo_envio' => $tipoEnvio,
                    'codigo_item' => $codigoItem,
                    'peso' => $peso,
                    'cantidad' => $cantidad,
                    'numero_factura' => $numeroFactura !== '' ? $numeroFactura : '-',
                    'importe_parcial' => $importeParcial,
                    'importe_general' => $importeGeneral,
                ];
            });
        })->values();
    }

    private function buildTicketData(FacturacionCart $cart, $user): array
    {
        $respuesta = (array) ($cart->respuesta_emision ?? []);
        $factura = (array) ($respuesta['factura'] ?? []);
        $cabecera = (array) data_get($respuesta, 'consultaSefe.detalleFactura.cabecera', []);
        $xmlCabecera = $this->extractXmlCabecera($respuesta);
        $sucursal = $user?->sucursal;

        $numeroFactura = trim((string) (
            data_get($respuesta, 'factura.nroFactura')
            ?? data_get($respuesta, 'factura.numeroFactura')
            ?? data_get($respuesta, 'consultaSefe.nroFactura')
            ?? data_get($respuesta, 'consultaSefe.detalleFactura.cabecera.numeroFactura')
            ?? data_get($respuesta, 'factura.numero')
            ?? data_get($respuesta, 'numeroFactura')
            ?? ($xmlCabecera['numeroFactura'] ?? null)
            ?? ''
        ));

        $nitEmisor = preg_replace('/\D+/', '', (string) (
            data_get($factura, 'nitEmisor')
            ?? data_get($cabecera, 'nitEmisor')
            ?? data_get($respuesta, 'nitEmisor')
            ?? ($xmlCabecera['nitEmisor'] ?? null)
            ?? config('services.facturacion_bridge.nit_emisor')
            ?? ''
        )) ?: '';

        $cuf = trim((string) (
            data_get($factura, 'cuf')
            ?? data_get($cabecera, 'cuf')
            ?? data_get($respuesta, 'cuf')
            ?? ($xmlCabecera['cuf'] ?? null)
            ?? ''
        ));

        $qrPayload = trim((string) (
            data_get($respuesta, 'factura.qrCode')
            ?? data_get($respuesta, 'factura.qr_url')
            ?? data_get($respuesta, 'factura.qrUrl')
            ?? data_get($respuesta, 'factura.urlQr')
            ?? data_get($respuesta, 'factura.qr')
            ?? data_get($respuesta, 'qrCode')
            ?? data_get($respuesta, 'consultaSefe.qrUrl')
            ?? data_get($cabecera, 'urlSin')
            ?? ''
        ));

        if ($qrPayload === '' && $nitEmisor !== '' && $cuf !== '' && $numeroFactura !== '') {
            $qrPayload = 'https://pilotosiat.impuestos.gob.bo/consulta/QR?nit='
                . urlencode($nitEmisor)
                . '&cuf=' . urlencode($cuf)
                . '&numero=' . urlencode($numeroFactura)
                . '&t=1';
        }

        $direccion = trim((string) (
            data_get($factura, 'direccion')
            ?? data_get($cabecera, 'direccion')
            ?? ($xmlCabecera['direccion'] ?? null)
            ?? ''
        ));
        $telefono = trim((string) (
            data_get($factura, 'telefono')
            ?? data_get($cabecera, 'telefono')
            ?? ($xmlCabecera['telefono'] ?? null)
            ?? ($sucursal->telefono ?? '')
        ));
        $municipio = trim((string) (
            data_get($factura, 'municipio')
            ?? data_get($cabecera, 'municipio')
            ?? ($xmlCabecera['municipio'] ?? null)
            ?? ($sucursal->municipio ?? '')
        ));
        $departamento = trim((string) (
            data_get($factura, 'departamento')
            ?? data_get($cabecera, 'departamento')
            ?? ($sucursal->departamento ?? '')
        ));
        $sucursalNombre = trim((string) (
            data_get($factura, 'sucursal')
            ?? data_get($cabecera, 'sucursal')
            ?? ($sucursal->nombre ?? $sucursal->descripcion ?? '')
        ));

        $ubicacionParts = array_values(array_filter([$direccion, $municipio, $departamento]));
        $direccionCompleta = implode(' - ', $ubicacionParts);

        $qrImage = null;
        if ($qrPayload !== '') {
            try {
                $qrImage = (new DNS2D())->getBarcodePNG($qrPayload, 'QRCODE,H', 5, 5);
            } catch (\Throwable) {
                $qrImage = null;
            }
        }

        return [
            'empresa' => trim((string) (($xmlCabecera['razonSocialEmisor'] ?? null) ?: (config('app.name') ?: 'Agencia Boliviana de Correos'))),
            'sucursal' => $sucursalNombre !== '' ? $sucursalNombre : 'Sucursal',
            'direccion' => $direccionCompleta,
            'telefono' => $telefono,
            'nit' => $nitEmisor !== '' ? $nitEmisor : 'S/N',
            'orden' => trim((string) ($cart->codigo_orden ?? ('VENT-' . $cart->id))),
            'nombre' => trim((string) ($cart->razon_social ?: 'SIN NOMBRE')),
            'documento' => trim((string) ($cart->numero_documento ?: '99003')),
            'numero_factura' => $numeroFactura !== '' ? $numeroFactura : 'S/N',
            'fecha' => optional($cart->emitido_en ?? $cart->created_at)->format('d/m/Y H:i:s') ?? '-',
            'importe' => round((float) $cart->total, 2),
            'metodo_pago' => 'Pago de contado',
            'qr_payload' => $qrPayload,
            'qr_image' => $qrImage,
            'cuf' => $cuf,
            'pdf_url' => trim((string) data_get($respuesta, 'factura.pdfUrl', '')),
        ];
    }

    private function extractXmlCabecera(array $respuesta): array
    {
        $possibleXml = [
            data_get($respuesta, 'factura.xml'),
            data_get($respuesta, 'factura.xmlContent'),
            data_get($respuesta, 'factura.xmlString'),
            data_get($respuesta, 'xml'),
            data_get($respuesta, 'xmlContent'),
            data_get($respuesta, 'consultaSefe.xml'),
            data_get($respuesta, 'consultaSefe.detalleFactura.xml'),
        ];

        foreach ($possibleXml as $xmlCandidate) {
            $xml = trim((string) $xmlCandidate);
            if ($xml === '' || !str_contains($xml, '<facturaElectronicaCompraVenta')) {
                continue;
            }

            try {
                $document = @simplexml_load_string($xml);
                if ($document === false || !isset($document->cabecera)) {
                    continue;
                }

                return [
                    'nitEmisor' => trim((string) ($document->cabecera->nitEmisor ?? '')),
                    'razonSocialEmisor' => trim((string) ($document->cabecera->razonSocialEmisor ?? '')),
                    'municipio' => trim((string) ($document->cabecera->municipio ?? '')),
                    'telefono' => trim((string) ($document->cabecera->telefono ?? '')),
                    'numeroFactura' => trim((string) ($document->cabecera->numeroFactura ?? '')),
                    'cuf' => trim((string) ($document->cabecera->cuf ?? '')),
                    'direccion' => trim((string) ($document->cabecera->direccion ?? '')),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    private function applyFilters(
        Builder $query,
        string $estado,
        string $estadoEmision,
        ?string $from,
        ?string $to,
        string $search
    ): void {
        if ($estado !== 'all') {
            $query->where('estado', $estado);
        }

        if ($estadoEmision !== 'all') {
            $query->whereRaw('upper(coalesce(estado_emision, ?)) = ?', ['', strtoupper($estadoEmision)]);
        }

        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('codigo_orden', 'like', $like)
                    ->orWhere('codigo_seguimiento', 'like', $like)
                    ->orWhere('numero_documento', 'like', $like)
                    ->orWhere('razon_social', 'like', $like)
                    ->orWhere('mensaje_emision', 'like', $like);
            });
        }
    }
}
