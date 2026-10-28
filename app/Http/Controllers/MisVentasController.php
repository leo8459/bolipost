<?php

namespace App\Http\Controllers;

use App\Models\FacturaTicketSnapshot;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteInt;
use App\Models\PaqueteOrdi;
use App\Models\User;
use App\Services\FacturacionCartService;
use App\Services\SitraIpsClient;
use App\Services\SitraTrackingDestinationClient;
use App\Services\SitraTrackingEventsClient;
use App\Services\TrackingProgressService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Milon\Barcode\DNS2D;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MisVentasController extends Controller
{
    private const KARDEX_EVENT_DELIVERED = 316;
    private const KARDEX_EVENT_SENT_TO_WINDOW_EMS = 312;
    private const KARDEX_EVENT_SENT_TO_WINDOW_CERTI = 172;
    private const KARDEX_EVENT_ASSIGNED_TO_COURIER = 184;
    private const KARDEX_EVENT_ADMISSION = 295;
    private const KARDEX_EVENT_CERTI_ADMISSION = 168;
    private const KARDEX_EVENT_COUNTER_DELIVERY = [183, 185, 2238, 4478];
    private const KARDEX_EVENT_COURIER_DELIVERY = [165, 315, 4477];

    public function index(Request $request, FacturacionCartService $service): View
    {
        $effectiveScope = $this->resolveEffectiveOwnScope($request);
        return $this->renderVentasPage($request, $service, $effectiveScope, 'own');
    }

    public function branchIndex(Request $request, FacturacionCartService $service): View
    {
        return $this->renderVentasPage($request, $service, 'branch', 'branch');
    }

    private function resolveCajaContext(FacturacionCartService $service, $user): array
    {
        try {
            return $service->fetchCajaEstado($user);
        } catch (\Throwable) {
            return [
                'estado' => 'SIN_APERTURA',
                'mensaje' => 'No se pudo consultar el estado de caja.',
                'caja' => [],
            ];
        }
    }

    public function exportPdf(Request $request, FacturacionCartService $service): Response
    {
        $effectiveScope = $this->resolveEffectiveOwnScope($request);
        return $this->exportVentasPdf($request, $service, $effectiveScope, 'own');
    }

    public function branchExportPdf(Request $request, FacturacionCartService $service): Response
    {
        return $this->exportVentasPdf($request, $service, 'branch', 'branch');
    }

    public function kardex(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): View
    {
        $report = $this->buildKardexReport($request, $service, $ipsClient, $destinationClient, $eventsClient);

        return view('facturacion.kardex', array_merge($report, [
            'scope' => 'own',
            'canViewBranchVentas' => false,
            'branchCashierSummary' => collect(),
        ]));
    }

    public function exportKardexPdf(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): Response
    {
        $report = $this->buildKardexReport($request, $service, $ipsClient, $destinationClient, $eventsClient);
        $pdf = Pdf::loadView('facturacion.kardex-pdf', array_merge($report, [
            'generatedAt' => now(),
        ]))->setPaper('A4', 'landscape');

        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportKardexExcel(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): StreamedResponse
    {
        $report = $this->buildKardexReport($request, $service, $ipsClient, $destinationClient, $eventsClient);
        $spreadsheet = $this->buildKardexSpreadsheet($report);
        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    private function buildKardexReport(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): array
    {
        [$user, $filters] = $this->resolveRequestContext($request, 'own');
        $kardexError = null;

        try {
            // Carga los registros del cajero en formato de detalle Kardex y complementa sus destinos mediante tracking.
            [$sales] = $this->loadScopedVentas($service, $user, array_merge($filters, ['q' => '']), 'own');
            $rows = $this->buildKardexViewRows($sales, $ipsClient, $destinationClient, $eventsClient);
            $summary = array_merge($this->emptySummary(), [
                'totalVentas' => $rows->unique('venta_group_key')->count(),
                'montoTotal' => round((float) $rows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $rows = collect();
            $summary = $this->emptySummary();
            $kardexError = 'No se pudieron cargar las ventas del cajero para el Kardex: ' . $e->getMessage();
        }

        $visibleRows = $this->applyKardexSearchFilter(
                $rows,
                (string) $request->input('q', '')
            )
            ->values()
            ->map(function (array $row, int $index): array {
                $row['nro'] = $index + 1;
                return $row;
            });
        $paymentMetrics = $this->summarizeKardexPaymentMetrics($visibleRows);

        return [
            'user' => $user,
            'filters' => $filters,
            'summary' => $summary,
            'rows' => $rows,
            'visibleRows' => $visibleRows,
            'totalVentas' => $paymentMetrics['totalVentas'],
            'ventasEfectivo' => $paymentMetrics['ventasEfectivo'],
            'montoEfectivo' => $paymentMetrics['montoEfectivo'],
            'ventasQrPagadas' => $paymentMetrics['ventasQrPagadas'],
            'montoQr' => $paymentMetrics['montoQr'],
            'totalImporte' => round((float) $visibleRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
            'totalPeso' => round((float) $visibleRows->sum(fn ($row) => (float) data_get($row, 'peso', 0)), 3),
            'totalAnuladas' => $visibleRows->filter(fn ($row) => (bool) data_get($row, 'es_anulada'))->count(),
            'kardexError' => $kardexError,
            'officePostal' => $this->resolveKardexUserOffice($user),
            'responsableName' => strtoupper(trim((string) ($user->name ?? ''))),
            'ventanillaName' => (string) data_get($user, 'ventanilla', data_get($user, 'id', '')),
        ];
    }

    private function summarizeKardexPaymentMetrics(Collection $rows): array
    {
        $sales = $rows->groupBy(function ($row, $index): string {
            $key = trim((string) data_get($row, 'venta_group_key', ''));

            return $key !== '' ? 'sale:' . $key : 'row:' . $index;
        });

        $cashSales = $sales->filter(function (Collection $details): bool {
            $sale = $details->first();

            return strtolower(trim((string) data_get($sale, 'estado_venta', ''))) === 'emitido'
                && strtolower(trim((string) data_get($sale, 'estado_pago', ''))) === 'pagado'
                && strtoupper(trim((string) data_get($sale, 'estado_emision', ''))) === 'FACTURADA'
                && strtolower(trim((string) data_get($sale, 'metodo_pago', ''))) !== 'qr'
                && ! $this->isQrPaymentRow($sale)
                && (bool) data_get($sale, 'contabiliza_en_caja', true)
                && ! (bool) data_get($sale, 'es_cuenta_por_cobrar', false)
                && strtolower(trim((string) data_get($sale, 'canal_emision', ''))) !== 'oficial'
                && ! (bool) data_get($sale, 'es_anulada', false);
        });
        $qrPaidSales = $sales->filter(function (Collection $details): bool {
            $sale = $details->first();

            return $this->isQrPaymentRow($sale)
                && strtolower(trim((string) data_get($sale, 'estado_venta', ''))) === 'emitido'
                && strtolower(trim((string) data_get($sale, 'estado_pago', ''))) === 'pagado'
                && ! (bool) data_get($sale, 'es_anulada', false);
        });
        $sumSaleDetails = static fn (Collection $groups): float => round((float) $groups->sum(
            fn (Collection $details): float => (float) $details->sum(
                fn ($detail): float => (float) data_get($detail, 'importe', 0)
            )
        ), 2);

        return [
            'totalVentas' => $sales->count(),
            'ventasEfectivo' => $cashSales->count(),
            'montoEfectivo' => $sumSaleDetails($cashSales),
            'ventasQrPagadas' => $qrPaidSales->count(),
            'montoQr' => $sumSaleDetails($qrPaidSales),
        ];
    }

    private function resolveKardexUserOffice(User $user): string
    {
        $office = method_exists($user, 'regionalesTexto') ? $user->regionalesTexto() : '';
        $office = trim((string) ($office ?: $user->ciudad ?: data_get($user, 'sucursal.departamento', '')));

        return strtoupper($office !== '' ? $office : '-');
    }
    private function isKardexCasillaService(string $tipoServicio, string $guiaCasilla = ''): bool
    {
        $normalized = $this->normalizeKardexText($tipoServicio . ' ' . $guiaCasilla);
        if (str_contains($normalized, 'ESTAMPILLA')) {
            return false;
        }

        return str_contains($normalized, 'CASILLA');
    }

    private function applyKardexSearchFilter(Collection $rows, string $query): Collection
    {
        $query = $this->normalizeKardexText($query);
        if ($query === '') {
            return $rows;
        }

        return $rows->filter(function ($row) use ($query): bool {
            $haystack = collect([
                data_get($row, 'fecha'),
                data_get($row, 'origen'),
                data_get($row, 'regional_registro'),
                data_get($row, 'venta_referencia'),
                data_get($row, 'metodo_pago_label'),
                data_get($row, 'estado_venta_label'),
                data_get($row, 'estado_label'),
                data_get($row, 'tipo_correspondencia'),
                data_get($row, 'tipo_servicio'),
                data_get($row, 'cliente'),
                data_get($row, 'guia_casilla'),
                data_get($row, 'pais_ciudad'),
                data_get($row, 'numero_factura'),
                data_get($row, 'importe'),
                data_get($row, 'modalidad_entrega'),
            ])->map(fn ($value) => $this->normalizeKardexText((string) $value))->implode(' ');

            return str_contains($haystack, $query);
        });
    }
    private function buildKardexSpreadsheet(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kardex');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);

        foreach (['A' => 6, 'B' => 13, 'C' => 11, 'D' => 10, 'E' => 42, 'F' => 31, 'G' => 10, 'H' => 24, 'I' => 26, 'J' => 13, 'K' => 15] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        foreach ([1 => 24, 2 => 24, 3 => 18, 4 => 18, 5 => 18, 6 => 23, 7 => 23, 8 => 8, 9 => 30] as $row => $height) {
            $sheet->getRowDimension($row)->setRowHeight($height);
        }

        $sheet->mergeCells('A1:B2');
        $sheet->mergeCells('C1:E2');
        $sheet->mergeCells('F1:K2');
        $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $this->addKardexLogo($sheet, public_path('images/LOGO 19-2-26.png'), 'A1', 118, 46);
        $this->addKardexLogo($sheet, public_path('images/LOGO-BOLIVIA.png'), 'C1', 190, 46);
        $this->addKardexLogo($sheet, public_path('images/ministerio-obras-publicas.png'), 'F1', 250, 46);

        $sheet->mergeCells('B3:E5');
        $sheet->setCellValue('B3', "KARDEX DIARIO DE RENDICIÓN\nAGENCIA BOLIVIANA DE CORREOS\nEXPRESADO EN BS.");
        $sheet->getStyle('B3:E5')->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B3:E5')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('B3:E5')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

        $sheet->mergeCells('H3:K3');
        $sheet->mergeCells('H4:K4');
        $sheet->mergeCells('H5:K5');
        $sheet->setCellValue('H3', 'Dirección de Operaciones');
        $sheet->setCellValue('H4', 'Operación postal');
        $sheet->setCellValue('H5', 'Kardex 1');
        $sheet->getStyle('H3:K5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('H3:K5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('A6', 'Oficina Postal:');
        $sheet->mergeCells('B6:C6');
        $sheet->setCellValue('B6', strtoupper((string) data_get($report, 'officePostal', '')));
        $sheet->setCellValue('D6', 'Nombre Responsable:');
        $sheet->mergeCells('E6:G7');
        $sheet->setCellValue('E6', strtoupper((string) data_get($report, 'responsableName', '')));
        $sheet->setCellValue('H6', 'Fecha de recaudación:');
        $sheet->mergeCells('I6:K6');
        $sheet->setCellValue('I6', $this->formatKardexExportDate((string) data_get($report, 'filters.to')));
        $sheet->setCellValue('A7', 'Ventanilla:');
        $sheet->mergeCells('B7:C7');
        $sheet->setCellValue('B7', (string) data_get($report, 'ventanillaName', ''));
        $sheet->mergeCells('H7:K7');
        $sheet->getStyle('A6:K7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A6:K7')->getFont()->setBold(true);
        $sheet->getStyle('A6:K7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('B6:C7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E6:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I6:K6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = ['N°', 'FECHA', 'CANTIDAD', 'REGIONAL', 'TIPO DE CORRESPONDENCIA', 'CÓDIGO DE ENVÍO', 'PESO', 'PAIS/CIUDAD DE DESTINO', 'OPERACIÓN', 'N° FACTURA', 'IMPORTE'];
        $sheet->fromArray($headers, null, 'A9');
        $sheet->getStyle('A9:K9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $sheet->getStyle('A9:K9')->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('A9:K9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A9:K9')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $rowNumber = 10;
        foreach ($report['visibleRows'] as $row) {
            $tipoCorrespondencia = (string) data_get($row, 'tipo_correspondencia');
            $detalleCasilla = trim((string) data_get($row, 'detalle_casilla', ''));
            if ((bool) data_get($row, 'es_casilla') && $detalleCasilla !== '') {
                $tipoCorrespondencia .= ' - ' . $detalleCasilla;
            }
            $sheet->fromArray([
                data_get($row, 'nro'),
                data_get($row, 'fecha'),
                data_get($row, 'cantidad'),
                data_get($row, 'origen'),
                $tipoCorrespondencia,
                data_get($row, 'guia_casilla'),
                data_get($row, 'peso') !== null && data_get($row, 'peso') !== '' ? number_format((float) data_get($row, 'peso'), 3, ',', '.') : '',
                data_get($row, 'pais_ciudad'),
                data_get($row, 'modalidad_entrega', '-'),
                data_get($row, 'numero_factura'),
                'Bs ' . number_format((float) data_get($row, 'importe', 0), 2, ',', '.'),
            ], null, 'A' . $rowNumber);
            $hasDeliveryMode = trim((string) data_get($row, 'modalidad_entrega', '-')) !== '-';
            $sheet->getRowDimension($rowNumber)->setRowHeight(((bool) data_get($row, 'es_casilla') && $detalleCasilla !== '') || $hasDeliveryMode ? 30 : 18);
            $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle('A' . $rowNumber . ':D' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . $rowNumber . ':J' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('K' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            if ((bool) data_get($row, 'es_anulada')) {
                $sheet->getStyle('J' . $rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
                $sheet->getStyle('J' . $rowNumber)->getFont()->getColor()->setRGB('C00000');
                $sheet->getStyle('J' . $rowNumber)->getFont()->setBold(true);
            } else {
                $sheet->getStyle('J' . $rowNumber)->getFont()->getColor()->setRGB('198754');
                $sheet->getStyle('J' . $rowNumber)->getFont()->setBold(true);
            }
            $rowNumber++;
        }

        $lastDataRow = max(10, $rowNumber - 1);
        $totalRow = $rowNumber;
        $sheet->mergeCells('A' . $totalRow . ':J' . $totalRow);
        $sheet->setCellValue('A' . $totalRow, 'TOTAL PARCIAL');
        $sheet->setCellValue('K' . $totalRow, 'Bs ' . number_format((float) $report['totalImporte'], 2, ',', '.'));
        $sheet->mergeCells('A' . ($totalRow + 1) . ':J' . ($totalRow + 1));
        $sheet->setCellValue('A' . ($totalRow + 1), 'TOTAL GENERAL');
        $sheet->setCellValue('K' . ($totalRow + 1), 'Bs ' . number_format((float) $report['totalImporte'], 2, ',', '.'));
        $sheet->getStyle('A' . $totalRow . ':K' . ($totalRow + 1))->getFont()->setBold(true);
        $sheet->getStyle('A' . $totalRow . ':K' . ($totalRow + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $totalRow . ':A' . ($totalRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('K' . $totalRow . ':K' . ($totalRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $obsRow = $totalRow + 3;
        $sheet->mergeCells('A' . $obsRow . ':E' . ($obsRow + 5));
        $sheet->setCellValue('A' . $obsRow, 'Observaciones');
        $sheet->mergeCells('F' . $obsRow . ':G' . ($obsRow + 5));
        $sheet->setCellValue('F' . ($obsRow + 5), "SELLO / FIRMA DE CONFORMIDAD\nRECAUDADOR");
        $sheet->mergeCells('H' . $obsRow . ':I' . ($obsRow + 5));
        $sheet->setCellValue('H' . ($obsRow + 5), "SELLO / FIRMA DE CONFORMIDAD\nREVISOR");
        $sheet->mergeCells('J' . $obsRow . ':K' . ($obsRow + 5));
        $sheet->setCellValue('J' . ($obsRow + 5), "SELLO RECEPCIÓN\nTESORERÍA");
        foreach (range($obsRow, $obsRow + 5) as $footerRow) {
            $sheet->getRowDimension($footerRow)->setRowHeight(22);
        }
        $sheet->getStyle('A' . $obsRow . ':K' . ($obsRow + 5))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('F' . $obsRow . ':K' . ($obsRow + 5))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_BOTTOM)->setWrapText(true);
        $sheet->getStyle('A' . $obsRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $sheet->freezePane('A10');
        $sheet->setAutoFilter('A9:K' . $lastDataRow);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0)->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setLeft(0.25)->setBottom(0.35);
        $sheet->getPageSetup()->setPrintArea('A1:K' . ($obsRow + 5));
        $sheet->getStyle('A1:K' . ($obsRow + 5))->getAlignment()->setShrinkToFit(false);

        return $spreadsheet;
    }
    private function addKardexLogo($sheet, string $path, string $cell, int $width, int $height): void
    {
        if (! is_file($path)) {
            return;
        }

        $drawing = new Drawing();
        $drawing->setPath($path);
        $drawing->setCoordinates($cell);
        $drawing->setWidth($width);
        $drawing->setHeight($height);
        $drawing->setWorksheet($sheet);
    }

    private function formatKardexExportDate(?string $date): string
    {
        if (! $date) {
            return now()->format('d/m/Y');
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }
    private function renderVentasPage(Request $request, FacturacionCartService $service, string $scope, string $routeScope = 'own'): View
    {
        [$user, $filters] = $this->resolveRequestContext($request, $scope);
        $cajaContext = $this->resolveCajaContext($service, $user);
        [$rows, $summary, $extra] = $this->loadScopedVentas($service, $user, $filters, $scope);

        $pageRows = $rows
            ->forPage((int) $filters['page'], (int) $filters['per_page'])
            ->values();

        $carts = new LengthAwarePaginator(
            $pageRows,
            $rows->count(),
            (int) $filters['per_page'],
            (int) $filters['page'],
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('facturacion.mis-ventas', [
            'carts' => $carts,
            'summary' => $summary,
            'filters' => $filters,
            'cajaContext' => $cajaContext,
            'pageContext' => $this->pageContextForScope($scope, $routeScope),
            'branchCashierSummary' => $extra['branch_cashiers'] ?? collect(),
        ]);
    }

    private function exportVentasPdf(Request $request, FacturacionCartService $service, string $scope, string $routeScope = 'own'): Response
    {
        [$user, $filters] = $this->resolveRequestContext($request, $scope);
        $exportFilters = $filters;
        $exportFilters['per_page'] = 100;
        [$carts] = $this->loadScopedVentas($service, $user, $exportFilters, $scope);
        $rows = $this->buildPdfRows($carts);

        $totals = [
            'parcial' => round((float) $rows
                ->filter(fn ($row) => (bool) data_get($row, 'contabiliza_en_caja', true))
                ->sum(fn ($row) => (float) data_get($row, 'importe_parcial', 0)), 2),
            'general' => round((float) $rows
                ->filter(fn ($row) => (bool) data_get($row, 'contabiliza_en_caja', true))
                ->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
            'qr' => round((float) $rows
                ->filter(fn ($row) => strtolower((string) data_get($row, 'metodo_pago', '')) === 'qr')
                ->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
        ];

        $pdf = Pdf::loadView('facturacion.mis-ventas-kardex-pdf', [
            'user' => $user,
            'filters' => $filters,
            'generatedAt' => now(),
            'carts' => $carts,
            'rows' => $rows,
            'totals' => $totals,
            'scope' => $scope,
            'routeScope' => $routeScope,
        ])->setPaper('a4', 'portrait');

        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function ticket(Request $request, int $cart, FacturacionCartService $service, SitraIpsClient $ips): StreamedResponse
    {
        $user = $this->authorizeVentasViewer($request->user(), $request->query('scope') === 'branch' ? 'branch' : 'own');
        $sourceUser = $this->resolveSourceUserForRequest($request, $user);
        $venta = $this->resolveVentaForDetail($service, $sourceUser, $cart);

        abort_unless($venta, 404, 'No se encontro la venta solicitada.');

        $snapshot = $this->ticketSnapshot($venta, $ips);
        abort_unless($this->ticketSnapshotHasEligiblePackage($snapshot), 409, 'El paquete todavía no está listo para entrega.');
        $ticket = $this->buildTicketData($venta, $user, $snapshot);
        $pdf = Pdf::loadView('facturacion.mis-ventas-ticket', ['cart' => $venta, 'ticket' => $ticket])->setPaper([0, 0, 226.77, 680], 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), 'ticket-' . ($venta->codigo_orden ?: ('venta-' . $venta->id)) . '.pdf');
    }

    private function ticketSnapshotHasEligiblePackage(array $snapshot): bool
    {
        $progressService = app(TrackingProgressService::class);

        foreach ((array) data_get($snapshot, 'items', []) as $item) {
            $eventText = trim((string) (($item['estado'] ?? '') . ' ' . ($item['evento'] ?? '')));
            $normalizedEventText = mb_strtolower($eventText);
            $progress = $progressService->resolve([[
                'codigo_evento' => $item['evento_cd'] ?? null,
                'nombre_evento' => $eventText,
            ]], 'TRACKING');
            $steps = array_values((array) ($progress['steps'] ?? []));
            $currentIndex = (int) ($progress['current_index'] ?? -1);
            $expeditionIndex = collect($steps)
                ->search(fn ($step) => strcasecmp((string) $step, 'Expedicion') === 0);

            if ($expeditionIndex !== false && $currentIndex >= (int) $expeditionIndex) {
                return true;
            }

            // El API puede devolver solo la etiqueta de etapa; estos estados
            // también confirman que el paquete ya pasó a la fase de entrega.
            if (str_contains($normalizedEventText, 'aduana')
                || str_contains($normalizedEventText, 'reparto')) {
                return true;
            }
        }

        return false;
    }

    private function ticketSnapshot(object $venta, SitraIpsClient $ips): array
    {
        $cartId = (int) ($venta->id ?? 0);
        if ($cartId <= 0) return [];
        $items = [];
        foreach ($this->normalizeItems($venta->items ?? []) as $item) {
            $code = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo_paquete', data_get($item, 'codigo_paquete', ''))));
            if ($code === '') continue;
            try {
                $package = $ips->package($code)['package'] ?? [];
                $items[] = ['codigo' => $code, 'evento_cd' => $package['operational_event_cd'] ?? null, 'evento' => $package['event_name'] ?? null, 'estado' => $package['stage']['label'] ?? null, 'oficina' => $package['office_name'] ?? null];
            } catch (\Throwable) {
                $items[] = ['codigo' => $code, 'evento_cd' => null, 'evento' => null, 'estado' => null, 'oficina' => null];
            }
        }
        $data = ['captured_at' => now()->toIso8601String(), 'items' => $items];
        FacturaTicketSnapshot::updateOrCreate(
            ['cart_id' => $cartId],
            ['data' => $data, 'captured_at' => now()]
        );
        return $data;
    }

    public function detail(Request $request, int $cart, FacturacionCartService $service): JsonResponse
    {
        $user = $this->authorizeVentasViewer($request->user(), $request->query('scope') === 'branch' ? 'branch' : 'own');
        $sourceUser = $this->resolveSourceUserForRequest($request, $user);
        $venta = $this->resolveVentaForDetail($service, $sourceUser, $cart);
        abort_unless($venta, 404, 'No se encontro la venta solicitada.');

        $items = $this->normalizeItems(data_get($venta, 'items', []))
            ->map(function ($item) {
                $item = is_array($item) ? (object) $item : $item;
                $codigoReferencia = collect([
                    trim((string) data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_item', '')),
                    trim((string) data_get($item, 'codigo', '')),
                    trim((string) data_get($item, 'codigo_item', '')),
                ])->first(fn ($code) => $code !== '' && ! $this->isServiceReferenceCode((string) $code));

                return [
                    'id' => (int) data_get($item, 'id', 0),
                    'codigo' => trim((string) data_get($item, 'codigo', '')),
                    'codigo_detalle_enviado' => trim((string) data_get($item, 'codigo_detalle_enviado', data_get($item, 'resumen_origen.codigo_detalle_enviado', ''))),
                    'codigo_paquete' => trim((string) data_get($item, 'codigo_paquete', data_get($item, 'resumen_origen.codigo_paquete', ''))),
                    'codigo_item' => trim((string) data_get($item, 'codigo_item', '')),
                    'codigo_referencia' => is_string($codigoReferencia) ? $codigoReferencia : '',
                    'origen_tipo' => trim((string) data_get($item, 'origen_tipo', '')),
                    'titulo' => trim((string) data_get($item, 'titulo', '')),
                    'nombre_servicio' => trim((string) data_get($item, 'nombre_servicio', '')),
                    'nombre_destinatario' => trim((string) data_get($item, 'nombre_destinatario', '')),
                    'resumen_origen' => (array) data_get($item, 'resumen_origen', []),
                    'tarifario_manual_referencia' => (array) data_get($item, 'resumen_origen.tarifario_manual_referencia', []),
                    'cantidad' => (int) data_get($item, 'cantidad', 0),
                    'monto_base' => round((float) data_get($item, 'monto_base', 0), 2),
                    'monto_extras' => round((float) data_get($item, 'monto_extras', 0), 2),
                    'total_linea' => round((float) data_get($item, 'total_linea', 0), 2),
                ];
            })
            ->values();

        return response()->json([
            'cart' => [
                'id' => (int) data_get($venta, 'id', $cart),
                'codigo_orden' => trim((string) data_get($venta, 'codigo_orden', '')),
                'total' => round((float) data_get($venta, 'total', 0), 2),
                'items_count' => max((int) data_get($venta, 'items_count', 0), $items->count()),
            ],
            'items' => $items,
        ]);
    }

    private function resolveRequestContext(Request $request, string $scope = 'own'): array
    {
        $user = $this->authorizeVentasViewer($request->user(), $scope);
        $today = now()->toDateString();

        $validated = $request->validate([
            'estado' => ['nullable', 'in:all,borrador,pendiente_pago,emitido'],
            'estado_emision' => ['nullable', 'in:all,FACTURADA,PENDIENTE,RECHAZADA,ERROR,NO_APLICA,ANULADA,PENDIENTE_ANULACION,ANULACION_OBSERVADA'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return [$user, [
            'estado' => (string) (($validated['estado'] ?? 'all') === 'borrador' ? 'all' : ($validated['estado'] ?? 'all')),
            'estado_emision' => (string) ($validated['estado_emision'] ?? 'all'),
            'from' => $validated['from'] ?? $today,
            'to' => $validated['to'] ?? $today,
            'q' => trim((string) ($validated['q'] ?? '')),
            'per_page' => (int) ($validated['per_page'] ?? 20),
            'page' => (int) ($validated['page'] ?? 1),
        ]];
    }

    private function resolveEffectiveOwnScope(Request $request): string
    {
        $requestedScope = strtolower(trim((string) $request->query('scope', '')));
        if ($requestedScope === 'own') {
            return 'own';
        }

        $user = $request->user();
        if ($requestedScope === 'branch' && $this->canAccessBranchVentas($user)) {
            return 'branch';
        }

        return $this->canAccessBranchVentas($user) ? 'branch' : 'own';
    }

    private function canAccessBranchVentas(?User $user): bool
    {
        if (!$user || (int) ($user->sucursal_id ?? 0) <= 0) {
            return false;
        }

        return $user->can('ventas-sucursal.export.pdf')
            || $user->can('ventas-sucursal.index');
    }

    private function authorizeVentasViewer(?User $user, string $scope = 'own'): User
    {
        abort_unless($user, 403, 'No tienes permiso para ver ventas.');

        if ($scope === 'branch') {
            abort_unless($this->canAccessBranchVentas($user), 403, 'No tienes permiso para ver ventas de sucursal.');
            abort_unless((int) ($user->sucursal_id ?? 0) > 0, 403, 'Tu cuenta no tiene una sucursal de facturacion asignada.');

            return $user;
        }

        abort_unless($user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');

        return $user;
    }

    private function pageContextForScope(string $scope, string $routeScope = 'own'): array
    {
        if ($scope === 'branch') {
            return [
                'page_title' => 'Ventas sucursal',
                'panel_title' => 'Ventas de sucursal',
                'panel_subtitle' => 'Ventas emitidas por los cajeros de tu sucursal de facturacion.',
                'filter_route' => $routeScope === 'branch' ? 'ventas-sucursal.index' : 'mis-ventas.index',
                'export_route' => $routeScope === 'branch' ? 'ventas-sucursal.export.pdf' : 'mis-ventas.export.pdf',
                'show_cashier_column' => true,
                'scope' => 'branch',
                'route_scope' => $routeScope,
            ];
        }

        return [
            'page_title' => 'Mis ventas',
            'panel_title' => 'Historial de ventas',
            'panel_subtitle' => 'Detalle de emisiones registradas para tu cuenta.',
            'filter_route' => 'mis-ventas.index',
            'export_route' => 'mis-ventas.export.pdf',
            'show_cashier_column' => false,
            'scope' => 'own',
            'route_scope' => $routeScope,
        ];
    }

    private function loadScopedVentas(FacturacionCartService $service, User $user, array $filters, string $scope): array
    {
        $rawRows = $scope === 'branch'
            ? $this->fetchBranchRawRows($service, $user, $filters)
            : $this->fetchOwnRawRows($service, $user, $filters);

        if ($rawRows->isEmpty()) {
            return [collect(), $this->emptySummary(), [
                'branch_cashiers' => collect(),
            ]];
        }

        $rows = $this->normalizeKardexRows($rawRows)
            ->unique(fn ($row) => $this->normalizedVentaRowKey($row))
            ->sortByDesc(fn ($row) => strtotime((string) (data_get($row, 'emitido_en') ?: data_get($row, 'created_at', '1970-01-01 00:00:00'))))
            ->values();
        $rows = $this->applyLocalFilters($rows, $filters);

        return [
            $rows->values(),
            $this->summaryFromRows($rows),
            [
                'branch_cashiers' => $scope === 'branch' ? $this->buildBranchCashierSummary($rows) : collect(),
            ],
        ];
    }

    private function fetchOwnRawRows(FacturacionCartService $service, User $user, array $filters): Collection
    {
        $fullRangeFilters = $filters;
        $fullRangeFilters['page'] = 1;
        $fullRangeFilters['per_page'] = 100;
        $fullRangeFilters['limite'] = 500;

        $kardex = $service->fetchKardexVentas($user, $fullRangeFilters);

        $ventasPages = collect();
        $currentPage = 1;
        $lastPage = 1;

        do {
            $ventasFilters = $fullRangeFilters;
            $ventasFilters['page'] = $currentPage;

            $ventasPage = $service->fetchVentas($user, $ventasFilters);
            $ventasPages = $ventasPages->concat(collect($ventasPage['carts'] ?? []));

            $pagination = (array) ($ventasPage['pagination'] ?? []);
            $lastPage = max(
                (int) ($pagination['last_page'] ?? 0),
                (int) ($pagination['lastPage'] ?? 0),
                (int) ($pagination['total_pages'] ?? 0),
                (int) ($pagination['pages'] ?? 0),
                1
            );

            $currentPage++;
        } while ($currentPage <= $lastPage);

        return $ventasPages
            ->concat(collect($kardex['detalle'] ?? []))
            ->map(fn ($row) => is_array($row) ? (object) $row : $row)
            ->filter(fn ($row) => is_object($row))
            ->values();
    }

    private function fetchBranchRawRows(FacturacionCartService $service, User $viewer, array $filters): Collection
    {
        return $this->resolveBranchCashiers($viewer)
            ->flatMap(function (User $cashier) use ($service, $filters) {
                try {
                    return $this->fetchOwnRawRows($service, $cashier, $filters)
                        ->map(function ($row) use ($cashier) {
                            $sucursal = $cashier->sucursal;
                            if (!isset($row->origenUsuarioId) || trim((string) ($row->origenUsuarioId ?? '')) === '') {
                                $row->origenUsuarioId = (string) $cashier->id;
                            }
                            if (!isset($row->origenUsuarioNombre) || trim((string) ($row->origenUsuarioNombre ?? '')) === '') {
                                $row->origenUsuarioNombre = (string) $cashier->name;
                            }
                            if (!isset($row->origenUsuarioEmail) || trim((string) ($row->origenUsuarioEmail ?? '')) === '') {
                                $row->origenUsuarioEmail = (string) $cashier->email;
                            }
                            if (!isset($row->origenUsuarioAlias) || trim((string) ($row->origenUsuarioAlias ?? '')) === '') {
                                $row->origenUsuarioAlias = (string) ($cashier->alias ?? '');
                            }
                            if (!isset($row->origenUsuarioCarnet) || trim((string) ($row->origenUsuarioCarnet ?? '')) === '') {
                                $row->origenUsuarioCarnet = (string) ($cashier->ci ?? '');
                            }
                            if (!isset($row->origenUsuarioCiudad) || trim((string) ($row->origenUsuarioCiudad ?? '')) === '') {
                                $row->origenUsuarioCiudad = (string) ($cashier->ciudad ?? '');
                            }
                            if (!isset($row->origenSucursalNombre) || trim((string) ($row->origenSucursalNombre ?? '')) === '') {
                                $row->origenSucursalNombre = (string) ($sucursal->nombre ?? $sucursal->descripcion ?? '');
                            }
                            if (!isset($row->origenSucursalMunicipio) || trim((string) ($row->origenSucursalMunicipio ?? '')) === '') {
                                $row->origenSucursalMunicipio = (string) ($sucursal->municipio ?? '');
                            }
                            if (!isset($row->origenSucursalDepartamento) || trim((string) ($row->origenSucursalDepartamento ?? '')) === '') {
                                $row->origenSucursalDepartamento = (string) ($sucursal->departamento ?? '');
                            }

                            return $row;
                        });
                } catch (\Throwable) {
                    return collect();
                }
            })
            ->sortByDesc(fn ($row) => strtotime((string) data_get($row, 'fecha', '1970-01-01 00:00:00')))
            ->values();
    }

    private function resolveBranchCashiers(User $viewer): Collection
    {
        return User::query()
            ->where('sucursal_id', (int) $viewer->sucursal_id)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $candidate) => $candidate->can('feature.dashboard.facturacion'))
            ->values();
    }

    private function buildBranchCashierSummary(Collection $rows): Collection
    {
        return $rows
            ->groupBy(function ($row) {
                return trim((string) data_get($row, 'origen_usuario_id', data_get($row, 'origen_usuario_email', 'sin-usuario')));
            })
            ->map(function (Collection $cashierRows) {
                $first = $cashierRows->first();

                return [
                    'usuario_id' => trim((string) data_get($first, 'origen_usuario_id', '')),
                    'nombre' => trim((string) data_get($first, 'origen_usuario_nombre', 'Sin usuario')),
                    'email' => trim((string) data_get($first, 'origen_usuario_email', '')),
                    'cantidad_ventas' => $cashierRows->count(),
                    'total_vendido' => round((float) $cashierRows->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
                    'total_caja' => round((float) $cashierRows
                        ->filter(fn ($row) => $this->contabilizaEnCaja($row)
                            && !$this->isQrPaymentRow($row)
                            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado')
                        ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
                ];
            })
            ->sortByDesc('total_vendido')
            ->values();
    }

    private function resolveSourceUserForRequest(Request $request, User $viewer): User
    {
        $sourceUserId = (int) $request->query('source_user_id', 0);
        if ($sourceUserId <= 0 || ! $this->canAccessBranchVentas($viewer)) {
            return $viewer;
        }

        $sourceUser = User::query()
            ->whereKey($sourceUserId)
            ->where('sucursal_id', (int) $viewer->sucursal_id)
            ->first();

        return $sourceUser ?: $viewer;
    }

    private function summaryFromKardex(array $resumen): array
    {
        $pendientes = (int) ($resumen['pendientes'] ?? 0);
        $qrPendientes = (int) ($resumen['ventasQrPendientes'] ?? 0);
        $qrNoFacturados = (int) ($resumen['ventasQrNoFacturadas'] ?? 0);

        return [
            'totalVentas' => (int) ($resumen['ventas'] ?? 0),
            'totalBorradores' => 0,
            'facturadas' => (int) ($resumen['facturadas'] ?? 0),
            'efectivoCount' => (int) ($resumen['ventasEfectivo'] ?? 0),
            'pendientes' => $pendientes,
            'anuladas' => (int) ($resumen['anuladas'] ?? 0),
            'rechazadas' => (int) ($resumen['observadas'] ?? 0),
            'qrPagados' => (int) ($resumen['ventasQr'] ?? 0),
            'qrPendientes' => $qrPendientes,
            'qrNoFacturados' => $qrNoFacturados,
            'pendientesOperativas' => $pendientes + $qrPendientes + $qrNoFacturados,
            'montoQr' => (float) ($resumen['totalQr'] ?? 0),
            'montoAnulado' => (float) ($resumen['totalAnulado'] ?? 0),
            'montoEfectivo' => (float) ($resumen['totalVendido'] ?? 0),
            'montoTotal' => (float) ($resumen['totalVendido'] ?? 0),
        ];
    }

    private function summaryFromRows(Collection $rows): array
    {
        $paidStatuses = ['FACTURADA'];
        $effectiveRows = $rows->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === 'emitido'
            && $this->contabilizaEnCaja($row)
            && !$this->isQrPaymentRow($row)
            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado'
            && strtoupper((string) data_get($row, 'estado_emision', '')) === 'FACTURADA');
        $qrPaidRows = $rows->filter(fn ($row) => $this->isQrPaymentRow($row)
            && strtolower((string) data_get($row, 'estado', '')) === 'emitido'
            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado');
        $pendientes = $rows->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) === 'PENDIENTE')->count();
        $qrPendientes = $rows->filter(fn ($row) => $this->isQrPaymentRow($row)
            && strtolower((string) data_get($row, 'estado', '')) === 'pendiente_pago'
            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pendiente')->count();
        $qrNoFacturados = $qrPaidRows->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) !== 'FACTURADA')->count();

        return [
            'totalVentas' => $rows->filter(fn ($row) => (string) data_get($row, 'estado', '') === 'emitido')->count(),
            'totalBorradores' => $rows->filter(fn ($row) => (string) data_get($row, 'estado', '') === 'borrador')->count(),
            'facturadas' => $rows->filter(fn ($row) => in_array(strtoupper((string) data_get($row, 'estado_emision', '')), $paidStatuses, true))->count(),
            'efectivoCount' => $effectiveRows->count(),
            'pendientes' => $pendientes,
            'anuladas' => $rows->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === 'anulado'
                || in_array(strtoupper((string) data_get($row, 'estado_emision', '')), ['ANULADA', 'ANULADO'], true))->count(),
            'rechazadas' => $rows->filter(function ($row) {
                $estadoEmision = strtoupper((string) data_get($row, 'estado_emision', ''));
                $isQrPayment = $this->isQrPaymentRow($row);
                $estadoPago = strtolower((string) data_get($row, 'estado_pago', 'pendiente'));

                return $estadoEmision === 'RECHAZADA'
                    || ($isQrPayment && $estadoPago === 'cancelado');
            })->count(),
            'qrPagados' => $qrPaidRows->count(),
            'qrNoFacturados' => $qrNoFacturados,
            'qrFacturados' => $qrPaidRows->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) === 'FACTURADA'
            )->count(),
            'qrPendientes' => $qrPendientes,
            'pendientesOperativas' => $pendientes + $qrPendientes + $qrNoFacturados,
            'montoQr' => round((float) $qrPaidRows
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
            'montoAnulado' => round((float) $rows
                ->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === 'anulado'
                    || in_array(strtoupper((string) data_get($row, 'estado_emision', '')), ['ANULADA', 'ANULADO'], true))
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
            'montoEfectivo' => round((float) $effectiveRows
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
            'montoTotal' => round((float) $effectiveRows
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
        ];
    }

    private function contabilizaEnCaja(object|array $row): bool
    {
        return (bool) data_get($row, 'contabiliza_en_caja', true);
    }

    private function emptySummary(): array
    {
        return [
            'totalVentas' => 0,
            'totalBorradores' => 0,
            'facturadas' => 0,
            'efectivoCount' => 0,
            'pendientes' => 0,
            'anuladas' => 0,
            'rechazadas' => 0,
            'qrPagados' => 0,
            'qrNoFacturados' => 0,
            'qrFacturados' => 0,
            'qrPendientes' => 0,
            'pendientesOperativas' => 0,
            'montoQr' => 0.0,
            'montoAnulado' => 0.0,
            'montoEfectivo' => 0.0,
            'montoTotal' => 0.0,
        ];
    }

    private function applyLocalFilters(Collection $rows, array $filters): Collection
    {
        $filtered = $rows;

        $filtered = $filtered->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) !== 'borrador');

        if (($filters['estado'] ?? 'all') !== 'all') {
            $estado = strtolower((string) $filters['estado']);
            $filtered = $filtered->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === $estado);
        }

        if (($filters['estado_emision'] ?? 'all') !== 'all') {
            $estadoEmision = strtoupper((string) $filters['estado_emision']);
            $filtered = $filtered->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) === $estadoEmision);
        }

        if (!empty($filters['from'])) {
            $fromTs = strtotime((string) $filters['from'] . ' 00:00:00');
            $filtered = $filtered->filter(function ($row) use ($fromTs) {
                $date = data_get($row, 'emitido_en') ?: data_get($row, 'created_at');
                return $date ? strtotime((string) $date) >= $fromTs : false;
            });
        }

        if (!empty($filters['to'])) {
            $toTs = strtotime((string) $filters['to'] . ' 23:59:59');
            $filtered = $filtered->filter(function ($row) use ($toTs) {
                $date = data_get($row, 'emitido_en') ?: data_get($row, 'created_at');
                return $date ? strtotime((string) $date) <= $toTs : false;
            });
        }

        if (($filters['q'] ?? '') !== '') {
            $needle = mb_strtolower((string) $filters['q']);
            $filtered = $filtered->filter(function ($row) use ($needle) {
                $fields = [
                    data_get($row, 'codigo_orden'),
                    data_get($row, 'codigo_seguimiento'),
                    data_get($row, 'codigo_seguimiento_fiscal'),
                    data_get($row, 'qr_transaction_id'),
                    data_get($row, 'numero_documento'),
                    data_get($row, 'razon_social'),
                    data_get($row, 'origen_usuario_nombre'),
                    data_get($row, 'origen_usuario_email'),
                    data_get($row, 'mensaje_emision'),
                ];

                foreach ($fields as $field) {
                    if ($field !== null && str_contains(mb_strtolower((string) $field), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $filtered->values();
    }

    private function normalizeKardexRows(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $ventaId = (int) data_get($row, 'id', 0);
            $origenVentaId = $this->extractOrigenVentaId($row);
            $items = $this->normalizeItems(data_get($row, 'items', data_get($row, 'detalle', [])));

            $codigoOrden = trim((string) (
                data_get($row, 'codigoOrden')
                ?? data_get($row, 'codigo_orden')
                ?? ''
            ));
            $codigoSeguimiento = trim((string) (
                data_get($row, 'codigoSeguimiento')
                ?? data_get($row, 'codigo_seguimiento')
                ?? ''
            ));
            $numeroFactura = trim((string) (
                data_get($row, 'numeroFactura')
                ?? data_get($row, 'nroFactura')
                ?? data_get($row, 'respuesta_emision.factura.nroFactura')
                ?? data_get($row, 'respuesta_emision.factura.numeroFactura')
            ));
            $estadoSufe = strtoupper(trim((string) (
                data_get($row, 'estadoSufe')
                ?? data_get($row, 'respuesta_emision.estadoSufe')
                ?? data_get($row, 'estado_emision')
                ?? ''
            )));
            $cuf = trim((string) (
                data_get($row, 'cuf')
                ?? data_get($row, 'respuesta_emision.factura.cuf')
            ));

            $pdfUrl = trim((string) (
                data_get($row, 'pdfUrl')
                ?? data_get($row, 'urlPdf')
                ?? data_get($row, 'respuesta_emision.factura.pdfUrl')
            ));
            $pdfUrl = $this->normalizeSefePublicUrl($pdfUrl);
            if ($pdfUrl === '' && $cuf !== '' && $estadoSufe === 'PROCESADA') {
                $pdfUrl = $this->sefePublicPdfUrl($cuf);
            }

            $createdAt = (string) (
                data_get($row, 'fecha')
                ?? data_get($row, 'emitido_en')
                ?? data_get($row, 'created_at')
                ?? ''
            );
            $isOficial = $this->isOfficialVentaPayload($row);
            $canalEmision = $this->resolveCanalEmisionVentaPayload($row, null, null, $isOficial);
            $metodoPago = $this->resolveMetodoPagoVentaPayload($row, null, null, $canalEmision);
            $emision = $this->mapEstadoSufeToBridge($estadoSufe);
            if (
                in_array($estadoSufe, ['FACTURADA', 'PROCESADA'], true)
                || $numeroFactura !== ''
                || $pdfUrl !== ''
            ) {
                $emision = [
                    'estado' => 'FACTURADA',
                    'mensaje' => 'Factura emitida correctamente.',
                ];
            }
            $estadoCart = strtolower(trim((string) data_get($row, 'estado', '')));
            if ($estadoCart === '') {
                $estadoCart = 'emitido';
            }
            $estadoPago = strtolower(trim((string) data_get($row, 'estado_pago', '')));
            if ($estadoPago === '') {
                $estadoPago = $metodoPago === 'qr' ? 'pendiente' : 'pagado';
            }
            $codigoSeguimientoFiscal = trim((string) data_get($row, 'codigo_seguimiento_fiscal', $codigoSeguimiento));
            $qrTransactionId = trim((string) data_get($row, 'qr_transaction_id', ''));
            $pesoTotal = round((float) (
                data_get($row, 'peso_total')
                ?? data_get($row, 'pesoTotal')
                ?? data_get($row, 'peso')
                ?? 0
            ), 3);
            if ($metodoPago === 'qr') {
                $qrEstadoEmision = strtoupper(trim((string) data_get($row, 'estado_emision', 'NO_APLICA')));

                if (
                    in_array($estadoSufe, ['FACTURADA', 'PROCESADA'], true)
                    || $numeroFactura !== ''
                    || $pdfUrl !== ''
                ) {
                    $qrEstadoEmision = 'FACTURADA';
                }

                $emision = [
                    'estado' => $qrEstadoEmision,
                    'mensaje' => $this->buildQrStatusMessage($estadoPago, trim((string) data_get($row, 'mensaje_emision', ''))),
                ];
            }

            if ($estadoCart === 'descartado') {
                $estadoCart = 'anulado';
                $emision = [
                    'estado' => 'ANULADA',
                    'mensaje' => trim((string) data_get($row, 'mensaje_emision', '')) ?: 'Venta descartada localmente tras rechazo o anulacion de la factura.',
                ];
            }

            $resolvedCartId = $origenVentaId > 0 ? $origenVentaId : $ventaId;

            return (object) [
                'id' => $resolvedCartId,
                'origen_venta_id' => $origenVentaId > 0 ? $origenVentaId : null,
                'venta_id' => $ventaId,
                'origen_usuario_id' => trim((string) data_get($row, 'origenUsuarioId', data_get($row, 'origen_usuario_id', ''))),
                'origen_usuario_nombre' => trim((string) data_get($row, 'origenUsuarioNombre', data_get($row, 'origen_usuario_nombre', ''))),
                'origen_usuario_email' => trim((string) data_get($row, 'origenUsuarioEmail', data_get($row, 'origen_usuario_email', ''))),
                'origen_usuario_ciudad' => trim((string) data_get($row, 'origenUsuarioCiudad', data_get($row, 'origen_usuario_ciudad', ''))),
                'origen_sucursal_nombre' => trim((string) data_get($row, 'origenSucursalNombre', data_get($row, 'origen_sucursal_nombre', ''))),
                'origen_sucursal_codigo' => trim((string) data_get($row, 'origenSucursalCodigo', data_get($row, 'origen_sucursal_codigo', ''))),
                'origen_sucursal_municipio' => trim((string) data_get($row, 'origenSucursalMunicipio', data_get($row, 'origen_sucursal_municipio', ''))),
                'origen_sucursal_departamento' => trim((string) data_get($row, 'origenSucursalDepartamento', data_get($row, 'origen_sucursal_departamento', ''))),
                'created_at' => $createdAt,
                'emitido_en' => $createdAt,
                'codigo_orden' => $codigoOrden,
                'numero_documento' => $isOficial ? '' : trim((string) data_get($row, 'documentoIdentidad', data_get($row, 'numero_documento', ''))),
                'razon_social' => trim((string) data_get($row, 'razonSocial', data_get($row, 'razon_social', ''))),
                'modalidad_facturacion' => $isOficial
                    ? 'registro_interno'
                    : (trim((string) data_get($row, 'codigoCliente', data_get($row, 'codigo_cliente', ''))) !== '' ? 'con_datos' : 'sin_cliente'),
                'canal_emision' => $canalEmision,
                'metodo_pago' => $metodoPago,
                'es_oficial' => $isOficial,
                'estado' => $estadoCart,
                'estado_pago' => $estadoPago,
                'estado_emision' => $emision['estado'],
                'mensaje_emision' => $emision['mensaje'],
                'codigo_seguimiento' => $codigoSeguimiento,
                'codigo_seguimiento_fiscal' => $codigoSeguimientoFiscal !== '' ? $codigoSeguimientoFiscal : $codigoSeguimiento,
                'qr_transaction_id' => $qrTransactionId !== '' ? $qrTransactionId : null,
                'total' => (float) data_get($row, 'total', 0),
                'peso_total' => $pesoTotal,
                'items_count' => (int) data_get($row, 'itemsCount', data_get($row, 'cantidadItems', data_get($row, 'cantidad_items', $items->count()))),
                'items' => $items,
                'respuesta_emision' => [
                    'factura' => [
                        'nroFactura' => $numeroFactura,
                        'numeroFactura' => $numeroFactura,
                        'cuf' => $cuf,
                        'pdfUrl' => $pdfUrl,
                    ],
                    'estadoSufe' => $estadoSufe,
                ],
            ];
        })->values();
    }

    private function normalizedVentaRowKey(object $row): string
    {
        $id = (int) data_get($row, 'id', 0);
        if ($id > 0) {
            return 'id:' . $id;
        }

        $codigoOrden = trim((string) data_get($row, 'codigo_orden', ''));
        if ($codigoOrden !== '') {
            return 'codigo:' . $codigoOrden;
        }

        $codigoSeguimiento = trim((string) data_get($row, 'codigo_seguimiento', ''));
        if ($codigoSeguimiento !== '') {
            return 'seguimiento:' . $codigoSeguimiento;
        }

        return md5(json_encode($row));
    }

    private function extractOrigenVentaId(object|array|null $payload): int
    {
        if ($payload === null) {
            return 0;
        }

        $candidates = [
            data_get($payload, 'origenVentaId'),
            data_get($payload, 'origen_venta_id'),
            data_get($payload, 'origenVenta.id'),
            data_get($payload, 'origen_venta.id'),
            data_get($payload, 'venta.origenVentaId'),
            data_get($payload, 'venta.origen_venta_id'),
            data_get($payload, 'venta.origenVenta.id'),
            data_get($payload, 'venta.origen_venta.id'),
        ];

        foreach ($candidates as $candidate) {
            $value = (int) $candidate;
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }

    private function resolveVentaMergeCartId(object|array $row): int
    {
        $origenVentaId = $this->extractOrigenVentaId($row);
        if ($origenVentaId > 0) {
            return $origenVentaId;
        }

        return (int) data_get($row, 'id', 0);
    }

    private function resolveVentaForDetail(FacturacionCartService $service, object $user, int $cart): ?object
    {
        $venta = $service->fetchVentaById($user, $cart);
        if (!$venta) {
            return null;
        }

        $cartItems = $this->normalizeItems(data_get($venta, 'items', []));
        if ($cartItems->isNotEmpty()) {
            return $venta;
        }

        $ventaId = (int) data_get($venta, 'venta_id', 0);
        if ($ventaId <= 0) {
            $ventaId = $this->extractOrigenVentaId($venta);
        }
        if ($ventaId <= 0) {
            $ventaId = $cart;
        }

        $ventaDetalle = $service->fetchVentaDetalleByVentaId($user, $ventaId);
        if ($ventaDetalle) {
            return $this->mapVentaDetailToCart($ventaDetalle);
        }

        return $venta;
    }

    private function mapVentaDetailToCart(object $venta): object
    {
        $detalle = collect((array) data_get($venta, 'detalle', []))
            ->map(fn ($item) => $this->mapVentaDetalleItemToCartItem($item))
            ->filter()
            ->values();

        $codigoOrden = trim((string) data_get($venta, 'codigoOrden', data_get($venta, 'codigo_orden', '')));
        $numeroFactura = $this->extractNumeroFacturaFromVentaDetail($venta);
        $cuf = trim((string) (
            data_get($venta, 'seguimiento.cuf')
            ?? data_get($venta, 'cuf')
        ));
        $pdfUrl = trim((string) data_get($venta, 'seguimiento.urlPdf', ''));
        $pdfUrl = $this->normalizeSefePublicUrl($pdfUrl);
        if ($pdfUrl === '' && $cuf !== '') {
            $pdfUrl = $this->sefePublicPdfUrl($cuf);
        }

        $isOficial = $this->isOfficialVentaPayload($venta);
        $canalEmision = $this->resolveCanalEmisionVentaPayload($venta, null, $venta, $isOficial);
        $metodoPago = $this->resolveMetodoPagoVentaPayload($venta, null, $venta, $canalEmision);

        return (object) [
            'id' => (int) data_get($venta, 'id', 0),
            'codigo_orden' => $codigoOrden,
            'numero_documento' => $isOficial
                ? ''
                : trim((string) data_get($venta, 'cliente.documentoIdentidad', data_get($venta, 'documentoIdentidad', ''))),
            'razon_social' => trim((string) data_get($venta, 'cliente.razonSocial', data_get($venta, 'razonSocial', ''))),
            'emitido_en' => (string) data_get($venta, 'created_at', data_get($venta, 'fecha', '')),
            'total' => (float) data_get($venta, 'montoTotal', data_get($venta, 'total', 0)),
            'modalidad_facturacion' => $isOficial ? 'registro_interno' : 'con_datos',
            'canal_emision' => $canalEmision,
            'metodo_pago' => $metodoPago,
            'canal_operativo' => (string) data_get($venta, 'canal_operativo', 'normal'),
            'contabiliza_en_caja' => (bool) data_get($venta, 'contabiliza_en_caja', true),
            'es_cuenta_por_cobrar' => (bool) data_get($venta, 'es_cuenta_por_cobrar', false),
            'empresa_nombre' => trim((string) data_get($venta, 'empresa_nombre', '')),
            'empresa_codigo_cliente' => trim((string) data_get($venta, 'empresa_codigo_cliente', '')),
            'es_oficial' => $isOficial,
            'items' => $detalle,
            'respuesta_emision' => [
                'factura' => [
                    'nroFactura' => $numeroFactura,
                    'numeroFactura' => $numeroFactura,
                    'cuf' => $cuf,
                    'pdfUrl' => $pdfUrl,
                ],
            ],
        ];
    }

    private function extractNumeroFacturaFromVentaDetail(object $venta): string
    {
        return trim((string) (
            data_get($venta, 'numero_factura')
            ?? data_get($venta, 'numeroFactura')
            ?? data_get($venta, 'seguimiento.detalle.nroFactura')
            ?? data_get($venta, 'seguimiento.detalle.numeroFactura')
        ));
    }

    private function mapVentaDetalleItemToCartItem(mixed $item): ?object
    {
        if (is_object($item)) {
            $item = (array) $item;
        }
        if (!is_array($item)) {
            return null;
        }

        $cantidad = (int) max(1, (float) ($item['cantidad'] ?? 1));
        $base = (float) ($item['monto_base'] ?? $item['precio'] ?? 0);
        $extras = (float) ($item['monto_extras'] ?? 0);
        $totalLinea = (float) ($item['total_linea'] ?? (($base + $extras) * $cantidad));

        return (object) [
            'id' => (int) ($item['id'] ?? 0),
            'codigo' => (string) ($item['codigo'] ?? ''),
            'codigo_paquete' => (string) ($item['codigo_paquete'] ?? data_get($item, 'resumen_origen.codigo_paquete', '')),
            'codigo_item' => (string) ($item['codigo_item'] ?? data_get($item, 'resumen_origen.codigo_item', '')),
            'codigo_detalle_enviado' => (string) ($item['codigo_detalle_enviado'] ?? data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
            'origen_tipo' => (string) ($item['origen_tipo'] ?? ''),
            'titulo' => (string) ($item['titulo'] ?? $item['descripcion'] ?? 'Sin detalle'),
            'nombre_servicio' => (string) ($item['descripcion'] ?? $item['titulo'] ?? ''),
            'nombre_destinatario' => (string) ($item['subtitulo'] ?? ''),
            'resumen_origen' => (array) ($item['resumen_origen'] ?? []),
            'cantidad' => $cantidad,
            'monto_base' => round($base, 2),
            'monto_extras' => round($extras, 2),
            'total_linea' => round($totalLinea, 2),
        ];
    }

    private function mapEstadoSufeToBridge(string $estadoSufe): array
    {
        return match ($estadoSufe) {
            'FACTURADA' => ['estado' => 'FACTURADA', 'mensaje' => 'Factura emitida correctamente.'],
            'PROCESADA' => ['estado' => 'FACTURADA', 'mensaje' => 'Factura emitida correctamente.'],
            'ANULADA', 'ANULADO' => ['estado' => 'ANULADA', 'mensaje' => 'Factura anulada correctamente.'],
            'ANULACION_SOLICITADA' => ['estado' => 'PENDIENTE_ANULACION', 'mensaje' => 'La anulacion fue enviada y espera confirmacion final.'],
            'ANULACION_OBSERVADA' => ['estado' => 'ANULACION_OBSERVADA', 'mensaje' => 'La anulacion fue observada y requiere revision.'],
            'PENDIENTE' => ['estado' => 'PENDIENTE', 'mensaje' => 'Emision en proceso de confirmacion.'],
            'RECEPCIONADA', 'CONTINGENCIA_CREADA' => ['estado' => 'PENDIENTE', 'mensaje' => 'Emision en proceso de confirmacion.'],
            'REGISTRADA_OFICIAL' => ['estado' => 'SIN ESTADO', 'mensaje' => 'Registro oficial almacenado sin emision electronica.'],
            'RECHAZADA' => ['estado' => 'RECHAZADA', 'mensaje' => 'Requiere revision antes de reenviar.'],
            'OBSERVADA' => ['estado' => 'RECHAZADA', 'mensaje' => 'Requiere revision antes de reenviar.'],
            'ERROR' => ['estado' => 'ERROR', 'mensaje' => 'Se registro un error en la emision.'],
            default => ['estado' => 'SIN ESTADO', 'mensaje' => 'Sin observaciones registradas.'],
        };
    }

    private function buildQrStatusMessage(string $estadoPago, string $fallback = ''): string
    {
        $estadoPago = strtolower(trim($estadoPago));
        $fallbackNormalized = strtolower(trim($fallback));

        if ($fallback !== '' && (
            str_contains($fallbackNormalized, 'factura')
            || str_contains($fallbackNormalized, 'sefe')
            || str_contains($fallbackNormalized, 'fiscal')
        )) {
            return $fallback;
        }

        return match ($estadoPago) {
            'pagado' => $fallback !== '' ? $fallback : 'Pago QR confirmado. La factura electronica se emitira automaticamente.',
            'cancelado' => $fallback !== '' ? $fallback : 'Pago QR cancelado o rechazado.',
            default => $fallback !== '' ? $fallback : 'QR generado. Si el cliente no completo el pago, la venta queda pendiente hasta actualizar su estado.',
        };
    }

    private function isOfficialVentaPayload(object|array $venta): bool
    {
        $codigoOrden = strtoupper(trim((string) data_get($venta, 'codigoOrden', data_get($venta, 'codigo_orden', ''))));
        $razonSocial = strtoupper(trim((string) data_get($venta, 'cliente.razonSocial', data_get($venta, 'razonSocial', ''))));
        $estadoSufe = strtoupper(trim((string) data_get($venta, 'estadoSufe', data_get($venta, 'estado_sufe', ''))));
        $tipoOrigen = strtoupper(trim((string) data_get($venta, 'origenVenta.tipo', '')));

        return str_starts_with($codigoOrden, 'OFI-')
            || $razonSocial === 'ENVIO OFICIAL'
            || $estadoSufe === 'REGISTRADA_OFICIAL'
            || $tipoOrigen === 'OFICIAL';
    }

    private function resolveCanalEmisionVentaPayload(
        object|array $venta,
        object|array|null $bridgeCart = null,
        object|array|null $ventaDetalle = null,
        ?bool $isOficial = null
    ): string {
        if ($isOficial ?? $this->isOfficialVentaPayload($venta)) {
            return 'oficial';
        }

        $candidates = [
            data_get($venta, 'canal_emision'),
            data_get($venta, 'canalEmision'),
            data_get($bridgeCart, 'canal_emision'),
            data_get($bridgeCart, 'canalEmision'),
            data_get($ventaDetalle, 'canal_emision'),
            data_get($ventaDetalle, 'canalEmision'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));
            if (in_array($normalized, ['factura_electronica', 'qr'], true)) {
                return $normalized;
            }
        }

        $codigoOrden = strtoupper(trim((string) (
            data_get($venta, 'codigoOrden')
            ?? data_get($venta, 'codigo_orden')
            ?? data_get($bridgeCart, 'codigo_orden')
            ?? data_get($ventaDetalle, 'codigoOrden')
            ?? ''
        )));
        if ($this->hasQrOrderCodePrefix($codigoOrden)) {
            return 'qr';
        }

        $paymentHints = [
            data_get($venta, 'metodo_pago'),
            data_get($venta, 'metodoPago'),
            data_get($bridgeCart, 'metodo_pago'),
            data_get($bridgeCart, 'metodoPago'),
            data_get($ventaDetalle, 'metodo_pago'),
            data_get($ventaDetalle, 'metodoPago'),
        ];

        foreach ($paymentHints as $hint) {
            if (strtolower(trim((string) $hint)) === 'qr') {
                return 'qr';
            }
        }

        return 'factura_electronica';
    }

    private function resolveMetodoPagoVentaPayload(
        object|array $venta,
        object|array|null $bridgeCart = null,
        object|array|null $ventaDetalle = null,
        ?string $canalEmision = null
    ): string {
        $paymentHints = [
            data_get($venta, 'metodo_pago'),
            data_get($venta, 'metodoPago'),
            data_get($bridgeCart, 'metodo_pago'),
            data_get($bridgeCart, 'metodoPago'),
            data_get($ventaDetalle, 'metodo_pago'),
            data_get($ventaDetalle, 'metodoPago'),
        ];

        foreach ($paymentHints as $hint) {
            $normalized = strtolower(trim((string) $hint));
            if (in_array($normalized, ['efectivo', 'qr'], true)) {
                return $normalized;
            }
        }

        $qrTransactionId = trim((string) (
            data_get($venta, 'qr_transaction_id')
            ?? data_get($bridgeCart, 'qr_transaction_id')
            ?? data_get($ventaDetalle, 'qr_transaction_id')
            ?? ''
        ));
        if ($qrTransactionId !== '') {
            return 'qr';
        }

        return strtolower(trim((string) ($canalEmision ?? 'factura_electronica'))) === 'qr' ? 'qr' : 'efectivo';
    }

    private function isQrPaymentRow(object|array $row): bool
    {
        $codigoOrden = strtoupper(trim((string) data_get($row, 'codigo_orden', data_get($row, 'codigoOrden', ''))));

        return strtolower(trim((string) data_get($row, 'metodo_pago', ''))) === 'qr'
            || trim((string) data_get($row, 'qr_transaction_id', '')) !== ''
            || strtolower(trim((string) data_get($row, 'canal_emision', ''))) === 'qr'
            || $this->hasQrOrderCodePrefix($codigoOrden);
    }

    private function hasQrOrderCodePrefix(string $codigoOrden): bool
    {
        $codigoOrden = strtoupper(trim($codigoOrden));

        return str_starts_with($codigoOrden, 'VQ-')
            || str_starts_with($codigoOrden, 'VQC-');
    }

    private function labelCanalEmision(string $canalEmision): string
    {
        return match (strtolower(trim($canalEmision))) {
            'qr' => 'QR',
            'oficial' => 'Envio oficial',
            default => 'Factura electronica',
        };
    }

    private function resolvePdfSectionKey(object|array $cart): string
    {
        $estadoCart = strtolower(trim((string) data_get($cart, 'estado', '')));
        $estadoEmision = strtoupper(trim((string) data_get($cart, 'estado_emision', 'NO_APLICA')));

        if ($estadoCart === 'anulado' || in_array($estadoEmision, ['ANULADA', 'ANULADO'], true)) {
            return 'factura_anulada';
        }

        if ($this->isQrPaymentRow($cart)) {
            $estadoPago = strtolower(trim((string) data_get($cart, 'estado_pago', 'pendiente')));

            if ($estadoPago === 'cancelado') {
                return 'qr_cancelado';
            }

            if ($estadoPago !== 'pagado') {
                return 'qr_pendiente';
            }

            if ($estadoEmision === 'FACTURADA') {
                return 'qr_facturado';
            }

            return 'qr_pagado_pendiente_factura';
        }

        return strtolower(trim((string) data_get($cart, 'canal_emision', 'factura_electronica')));
    }

    private function resolvePdfEstadoColumn(string $sectionKey, string $estadoEmision, string $estadoPago, string $mensajeEmision): array
    {
        $normalizedMessage = trim($mensajeEmision);

        return match ($sectionKey) {
            'factura_anulada' => [
                'label' => 'VENTA ANULADA',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Venta anulada localmente y excluida de caja.',
            ],
            'qr_facturado' => [
                'label' => 'FACTURADA',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Cobro QR convertido en factura electronica.',
            ],
            'qr_pagado_pendiente_factura' => [
                'label' => 'QR PAGADO',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Cobro QR confirmado. Factura pendiente o en otro estado.',
            ],
            'qr_cancelado' => [
                'label' => 'QR ANULADO',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'El intento de cobro QR no se concreto.',
            ],
            'qr_pendiente' => [
                'label' => 'QR PENDIENTE',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Pendiente de pago QR.',
            ],
            default => match ($estadoEmision) {
                'FACTURADA' => [
                    'label' => 'EMITIDO',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Factura emitida correctamente.',
                ],
                'PENDIENTE' => [
                    'label' => 'PENDIENTE',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Emision en proceso de confirmacion.',
                ],
                'RECHAZADA' => [
                    'label' => 'RECHAZADA',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'La factura requiere revision.',
                ],
                'PENDIENTE_ANULACION' => [
                    'label' => 'PENDIENTE ANULACION',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'La anulacion fue enviada y espera confirmacion final.',
                ],
                'ANULACION_OBSERVADA' => [
                    'label' => 'ANULACION OBSERVADA',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'La anulacion fue observada y requiere revision.',
                ],
                'ERROR' => [
                    'label' => 'ERROR',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Se registro un error en la emision.',
                ],
                default => [
                    'label' => strtoupper($estadoPago === 'cancelado' ? 'ANULADO' : ($estadoEmision !== '' ? $estadoEmision : 'SIN ESTADO')),
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Sin observaciones registradas.',
                ],
            },
        };
    }

    private function buildPdfRows(Collection $carts, bool $includeKardexSourceItems = false): Collection
    {
        return $carts->values()->map(function ($cart, int $cartIndex) use ($includeKardexSourceItems) {
            $cart = is_array($cart) ? (object) $cart : $cart;
            $items = $this->normalizeItems(data_get($cart, 'items', []));

            $respuesta = (array) data_get($cart, 'respuesta_emision', []);
            $numeroFactura = trim((string) (
                data_get($respuesta, 'factura.nroFactura')
                ?? data_get($respuesta, 'factura.numeroFactura')
                ?? data_get($respuesta, 'consultaSefe.nroFactura')
                ?? ''
            ));
            $cuf = trim((string) (
                data_get($respuesta, 'factura.cuf')
                ?? data_get($respuesta, 'cuf')
                ?? data_get($cart, 'cuf')
                ?? ''
            ));
            $ventaId = (int) data_get($cart, 'id', data_get($cart, 'venta_id', 0));
            $codigoOrdenOriginal = trim((string) data_get($cart, 'codigo_orden', data_get($cart, 'codigoOrden', '')));
            $pdfUrl = trim((string) (
                data_get($respuesta, 'factura.pdfUrl')
                ?? data_get($respuesta, 'factura.pdf_url')
                ?? data_get($cart, 'pdf_url')
                ?? data_get($cart, 'pdfUrl')
                ?? ''
            ));
            $pdfUrl = $this->normalizeSefePublicUrl($pdfUrl);
            if ($pdfUrl === '' && $cuf !== '') {
                $pdfUrl = $this->sefePublicPdfUrl($cuf);
            }
            $saleGroupKey = $ventaId > 0
                ? 'venta:' . $ventaId
                : ($codigoOrdenOriginal !== ''
                    ? 'codigo:' . mb_strtoupper($codigoOrdenOriginal)
                    : ($numeroFactura !== '' ? 'factura:' . $numeroFactura : 'cart:' . $cartIndex));
            $fecha = $cart->emitido_en ?? $cart->created_at;
            $canalEmision = strtolower(trim((string) data_get($cart, 'canal_emision', 'factura_electronica')));
            $metodoPago = strtolower(trim((string) data_get($cart, 'metodo_pago', $canalEmision === 'qr' ? 'qr' : 'efectivo')));
            $estadoPago = strtolower(trim((string) data_get($cart, 'estado_pago', 'pendiente')));
            $estadoEmision = strtoupper(trim((string) data_get($cart, 'estado_emision', '')));
            $mensajeEmision = trim((string) data_get($cart, 'mensaje_emision', ''));
            $esCuentaPorCobrar = (bool) data_get($cart, 'es_cuenta_por_cobrar', false);
            $empresaContrato = trim((string) data_get($cart, 'empresa_nombre', ''));
            $contabilizaEnCaja = (bool) data_get($cart, 'contabiliza_en_caja', $metodoPago !== 'qr');
            $sectionKey = $this->resolvePdfSectionKey($cart);
            $estadoColumn = $this->resolvePdfEstadoColumn($sectionKey, $estadoEmision, $estadoPago, $mensajeEmision);
            $isAnnulled = $sectionKey === 'factura_anulada';
            $contabilizaEnCaja = $contabilizaEnCaja && ! $isAnnulled;
            if ($esCuentaPorCobrar) {
                $estadoColumn = [
                    'label' => 'FACTURADA',
                    'detalle' => 'Cuenta por cobrar'
                        . ($empresaContrato !== '' ? ' a ' . $empresaContrato : '')
                        . '. No suma a caja.',
                ];
            }

            $emisionLabel = match ($sectionKey) {
                'qr_facturado' => 'QR pagado + facturado',
                'qr_pagado_pendiente_factura' => 'QR pagado',
                'qr_cancelado' => 'QR cancelado',
                'qr_pendiente' => 'QR pendiente',
                default => $this->labelCanalEmision($canalEmision),
            };

            $tipoEnvio = $items
                ->map(fn ($item) => trim((string) data_get($item, 'nombre_servicio', data_get($item, 'titulo', ''))))
                ->filter()
                ->unique()
                ->implode(' / ');
            $detalleItems = $items
                ->map(fn ($item) => trim((string) data_get($item, 'titulo', data_get($item, 'nombre_servicio', ''))))
                ->filter()
                ->unique()
                ->implode(' / ');
            $codigoOrden = $codigoOrdenOriginal !== ''
                ? $codigoOrdenOriginal
                : (trim((string) data_get($cart, 'codigo_seguimiento', '')) ?: '-');
            $detalleCodigos = $this->buildDetailCodeEntriesFromItems($items);
            $codigosPaquete = $detalleCodigos
                ->pluck('codigo')
                ->filter()
                ->values();
            $pesoTotal = (float) $items->sum(fn ($item) => (float) data_get($item, 'resumen_origen.peso', 0));
            if ($pesoTotal <= 0) {
                $pesoTotal = round((float) (
                    data_get($cart, 'peso_total')
                    ?? data_get($cart, 'pesoTotal')
                    ?? data_get($cart, 'peso')
                    ?? 0
                ), 3);
            }
            $cantidadTotal = (int) data_get($cart, 'items_count', 0);
            if ($cantidadTotal <= 0) {
                $cantidadTotal = max(1, $items->count());
            }
            $packageItemsCount = $items
                ->filter(fn ($item) => $this->isPackageCartItem($item))
                ->count();
            $serviceItemsCount = max(0, $cantidadTotal - $packageItemsCount);
            $detalleResumen = collect([
                $packageItemsCount > 0 ? $packageItemsCount . ' paquete' . ($packageItemsCount === 1 ? '' : 's') : null,
                $serviceItemsCount > 0 ? $serviceItemsCount . ' servicio' . ($serviceItemsCount === 1 ? '' : 's') : null,
            ])->filter()->implode(' + ');
            $clienteLabel = trim((string) data_get($cart, 'razon_social', ''));
            if ($clienteLabel === '') {
                $clienteLabel = (bool) data_get($cart, 'es_oficial', false) ? 'ENVIO OFICIAL' : 'Sin cliente';
            }

            $row = [
                'venta_id' => $ventaId,
                'venta_group_key' => $saleGroupKey,
                'venta_referencia' => $codigoOrden !== '-' ? $codigoOrden : 'VENTA-' . ($ventaId > 0 ? $ventaId : ($cartIndex + 1)),
                'numero_documento' => trim((string) data_get($cart, 'numero_documento', data_get($cart, 'documentoIdentidad', ''))),
                'origen_usuario_id' => trim((string) data_get($cart, 'origen_usuario_id', '')),
                'origen_usuario_nombre' => trim((string) data_get($cart, 'origen_usuario_nombre', '')),
                'origen_usuario_email' => trim((string) data_get($cart, 'origen_usuario_email', '')),
                'origen_usuario_ciudad' => trim((string) data_get($cart, 'origen_usuario_ciudad', '')),
                'origen_sucursal_nombre' => trim((string) data_get($cart, 'origen_sucursal_nombre', '')),
                'origen_sucursal_codigo' => trim((string) data_get($cart, 'origen_sucursal_codigo', '')),
                'origen_sucursal_municipio' => trim((string) data_get($cart, 'origen_sucursal_municipio', '')),
                'origen_sucursal_departamento' => trim((string) data_get($cart, 'origen_sucursal_departamento', '')),
                'fecha' => $fecha ? date('d/m/Y', strtotime((string) $fecha)) : '-',
                'fecha_hora' => $fecha ? date('d/m/Y H:i', strtotime((string) $fecha)) : '-',
                'fecha_sort' => $fecha ? strtotime((string) $fecha) : 0,
                'cliente' => $clienteLabel,
                'estado_venta' => strtolower(trim((string) data_get($cart, 'estado', ''))),
                'codigo_seguimiento' => trim((string) data_get($cart, 'codigo_seguimiento', '')),
                'codigo_seguimiento_fiscal' => trim((string) data_get($cart, 'codigo_seguimiento_fiscal', data_get($cart, 'codigo_seguimiento', ''))),
                'qr_transaction_id' => trim((string) data_get($cart, 'qr_transaction_id', '')),
                'mensaje_emision' => trim((string) data_get($cart, 'mensaje_emision', '')),
                'tipo_envio' => $tipoEnvio !== '' ? $tipoEnvio : 'SIN DETALLE REAL',
                'detalle_items' => $detalleItems !== '' ? $detalleItems : 'Sin detalle real',
                'detalle_resumen' => $detalleResumen,
                'detalle_codigos' => $detalleCodigos,
                'codigo_item' => $codigoOrden,
                'codigo_paquetes' => $codigosPaquete,
                'codigo_paquetes_texto' => $codigosPaquete->implode(', '),
                'codigo_referencia' => $codigosPaquete->isNotEmpty()
                    ? $codigosPaquete->implode(', ')
                    : $codigoOrden,
                'peso' => $pesoTotal,
                'cantidad' => $cantidadTotal,
                'canal_emision' => $canalEmision,
                'metodo_pago' => $metodoPago,
                'estado_pago' => $estadoPago,
                'estado_emision' => $estadoEmision,
                'section_key' => $sectionKey,
                'emision_label' => $emisionLabel,
                'estado_label' => $estadoColumn['label'],
                'estado_detalle' => $estadoColumn['detalle'],
                'contabiliza_en_caja' => $contabilizaEnCaja,
                'es_cuenta_por_cobrar' => $esCuentaPorCobrar,
                'empresa_nombre' => $empresaContrato,
                'cobrada' => ! $isAnnulled && in_array($sectionKey, ['factura_electronica', 'qr_facturado', 'qr_pagado_pendiente_factura', 'oficial'], true),
                'numero_factura' => $numeroFactura !== '' ? $numeroFactura : '-',
                'pdf_url' => $pdfUrl,
                'cuf' => $cuf,
                'importe_parcial' => round((float) data_get($cart, 'total', 0), 2),
                'importe_general' => round((float) data_get($cart, 'total', 0), 2),
                'importe_venta' => round((float) data_get($cart, 'total', 0), 2),
            ];

            if ($includeKardexSourceItems) {
                $row['kardex_source_items'] = $items;
            }

            return $row;
        })->values();
    }

    private function buildKardexViewRows(Collection $carts, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): Collection
    {
        $pdfRows = $this->buildPdfRows($carts, true);
        $packageCodes = $pdfRows
            ->flatMap(fn (array $row) => collect($row['detalle_codigos'] ?? [])
                ->filter(fn (array $entry): bool => $this->isKardexPackageEntry($entry))
                ->pluck('codigo'))
            ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
            ->filter()
            ->unique()
            ->values();
        $ipsServices = $ipsClient->packageServices($packageCodes->all());
        $localPackageServices = $this->lookupLocalKardexPackageServices($pdfRows);
        $pdfRows = $pdfRows->map(function (array $row) use ($ipsServices, $localPackageServices): array {
            $row['detalle_codigos'] = collect($row['detalle_codigos'] ?? [])
                ->map(function (array $entry) use ($ipsServices, $localPackageServices): array {
                    if (! $this->isKardexPackageEntry($entry)) {
                        return $entry;
                    }

                    $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                    $source = $entry['source'] ?? null;
                    $origin = is_object($source) ? ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\') : '';
                    $originId = is_object($source) ? (int) data_get($source, 'origen_id', 0) : 0;
                    $localKey = $origin !== '' && $originId > 0 ? $origin.'#'.$originId : '';
                    $entry['servicio'] = $ipsServices[$code]
                        ?? ($localKey !== '' ? ($localPackageServices[$localKey] ?? null) : null)
                        ?? 'NO VERIFICADO EN IPS';

                    return $entry;
                });

            return $row;
        });

        $destinationEntries = $pdfRows->flatMap(function (array $row): Collection {
            $detalleCodigos = collect($row['detalle_codigos'] ?? []);
            $esCasilla = $this->isKardexCasillaService(
                (string) ($row['tipo_envio'] ?? ''),
                $detalleCodigos->pluck('codigo')->filter()->implode(', ')
            );
            if ($esCasilla) {
                return collect();
            }

            return $detalleCodigos->map(function (array $entry) use ($row): ?array {
                $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                if ($code === '') {
                    return null;
                }

                return [
                    'codigo' => $code,
                    'servicio' => trim((string) ($entry['servicio'] ?? '')) ?: (string) ($row['tipo_envio'] ?? ''),
                ];
            })->filter();
        })->unique(fn (array $entry) => $entry['codigo'].'|'.$this->normalizeKardexText($entry['servicio']))->values();

        $allCodes = $destinationEntries->pluck('codigo')->unique()->values();
        $internationalCodes = $destinationEntries
            ->filter(fn (array $entry) => $this->isKardexInternationalService($entry['servicio']))
            ->pluck('codigo')
            ->unique()
            ->values();
        $localDestinations = $this->lookupKardexDestinationsInBatch($allCodes);
        $trackingDestinations = $destinationClient->destinations($internationalCodes->all());
        $trackingEvents = $eventsClient->events($internationalCodes->all());
        foreach ($this->lookupKardexDestinationsFromTrackingEvents($trackingEvents) as $code => $destination) {
            if ($this->cleanKardexReportText((string) ($trackingDestinations[$code] ?? '')) === '') {
                $trackingDestinations[$code] = $destination;
            }
        }

        $splitRows = $pdfRows
            ->flatMap(fn (array $row) => $this->splitKardexPackageRows($row))
            ->values();
        $operationalStages = $this->lookupKardexOperationalStages($splitRows, $trackingEvents);
        $viewRows = $splitRows
            ->map(function (array $splitRow) use ($localDestinations, $trackingDestinations, $operationalStages): array {
                $codes = collect($splitRow['detalle_codigos'] ?? [])
                    ->pluck('codigo')
                    ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
                    ->filter()
                    ->unique()
                    ->values();
                $modes = $codes
                    ->map(fn (string $code) => $operationalStages[$code] ?? null)
                    ->filter()
                    ->unique()
                    ->values();
                $splitRow['modalidad_entrega'] = $modes->isNotEmpty() ? $modes->implode(' / ') : '-';

                return $this->formatKardexViewRow($splitRow, $localDestinations, $trackingDestinations);
            })
            ->values();

        return $viewRows
            ->groupBy('venta_group_key')
            ->flatMap(function (Collection $saleRows): Collection {
                $detailCount = $saleRows->count();

                return $saleRows->values()->map(function (array $row, int $index) use ($detailCount): array {
                    $row['venta_detalle_orden'] = $index + 1;
                    $row['venta_detalles_total'] = $detailCount;

                    return $row;
                });
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['nro'] = $index + 1;

                return $row;
            });
    }

    private function lookupKardexOperationalStages(Collection $rows, array $trackingEventsByCode = []): array
    {
        $codes = $rows
            ->flatMap(fn (array $row) => collect($row['detalle_codigos'] ?? [])->pluck('codigo'))
            ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
            ->filter()
            ->unique()
            ->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $eventTypesByTable = [
            'eventos_ems' => [
                'admission' => [self::KARDEX_EVENT_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'non_domiciliary' => array_merge([self::KARDEX_EVENT_SENT_TO_WINDOW_EMS], self::KARDEX_EVENT_COUNTER_DELIVERY),
            ],
            'eventos_contrato' => [
                'admission' => [self::KARDEX_EVENT_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'non_domiciliary' => array_merge([self::KARDEX_EVENT_SENT_TO_WINDOW_EMS], self::KARDEX_EVENT_COUNTER_DELIVERY),
            ],
            'eventos_certi' => [
                'admission' => [self::KARDEX_EVENT_CERTI_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'non_domiciliary' => array_merge([self::KARDEX_EVENT_SENT_TO_WINDOW_CERTI], self::KARDEX_EVENT_COUNTER_DELIVERY),
            ],
            'eventos_ordi' => [
                'admission' => [self::KARDEX_EVENT_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'non_domiciliary' => self::KARDEX_EVENT_COUNTER_DELIVERY,
            ],
        ];
        $eventsByCode = [];
        foreach ($eventTypesByTable as $table => $eventTypes) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'codigo')
                || ! Schema::hasColumn($table, 'evento_id')
                || ! Schema::hasColumn($table, 'created_at')) {
                continue;
            }

            $eventIds = array_values(array_unique(array_merge(
                $eventTypes['admission'],
                $eventTypes['domiciliary'],
                $eventTypes['non_domiciliary'],
                [self::KARDEX_EVENT_DELIVERED]
            )));

            foreach ($codes->chunk(1000) as $codeChunk) {
                $events = DB::table($table)
                    ->select(['codigo', 'evento_id', 'created_at'])
                    ->whereIn(DB::raw('TRIM(UPPER(codigo))'), $codeChunk->all())
                    ->whereIn('evento_id', $eventIds)
                    ->orderBy('created_at')
                    ->get();

                foreach ($events as $event) {
                    $code = $this->normalizeKardexPackageLookupCode((string) $event->codigo);
                    if ($code !== '') {
                        $eventsByCode[$code][$table][] = $event;
                    }
                }
            }
        }

        $stages = [];
        $stageTimestamps = [];
        foreach ($eventsByCode as $code => $eventsByTable) {
            $history = collect();
            foreach ($eventsByTable as $table => $events) {
                foreach ($events as $event) {
                    $timestamp = strtotime((string) $event->created_at);
                    if ($timestamp === false) {
                        continue;
                    }
                    $eventId = (int) $event->evento_id;
                    $category = null;
                    foreach ($eventTypesByTable[$table] as $type => $ids) {
                        if (in_array($eventId, $ids, true)) {
                            $category = $type;
                            break;
                        }
                    }
                    if ($category !== null || $eventId === self::KARDEX_EVENT_DELIVERED) {
                        $history->push([
                            'timestamp' => $timestamp,
                            'event_id' => $eventId,
                            'category' => $category,
                        ]);
                    }
                }
            }

            if ($history->isEmpty()) {
                continue;
            }

            $history = $history->sortBy('timestamp')->values();
            $latest = $history->last();
            if ((int) $latest['event_id'] === self::KARDEX_EVENT_DELIVERED) {
                $route = $history->filter(fn (array $event) => $event['category'] !== null)->last();
                $category = in_array($route['category'] ?? null, ['domiciliary', 'non_domiciliary'], true)
                    ? $route['category']
                    : null;
            } else {
                $category = $latest['category'];
            }

            $stages[$code] = match ($category) {
                'admission' => 'ADMISIÓN',
                'domiciliary' => 'DISTRIBUCIÓN DOMICILIARIA',
                'non_domiciliary' => 'DISTRIBUCIÓN NO DOMICILIARIA',
                default => '-',
            };
            $stageTimestamps[$code] = (int) ($latest['timestamp'] ?? 0);
        }

        foreach ($trackingEventsByCode as $rawCode => $events) {
            $code = $this->normalizeKardexPackageLookupCode((string) $rawCode);
            if ($code === '') {
                continue;
            }

            $latestTrackingTimestamp = collect((array) $events)
                ->map(fn ($event) => strtotime((string) (data_get($event, 'timeline_sort_at') ?? data_get($event, 'created_at', ''))) ?: 0)
                ->filter(fn (int $timestamp) => $timestamp > 0)
                ->max();
            if (isset($stages[$code])
                && ($latestTrackingTimestamp === null
                    || $latestTrackingTimestamp <= (int) ($stageTimestamps[$code] ?? 0))) {
                continue;
            }

            $stage = $this->resolveKardexSitraOperationalStage((array) $events, $code);
            if ($stage !== null) {
                $stages[$code] = $stage;
                $stageTimestamps[$code] = (int) ($latestTrackingTimestamp ?? 0);
            }
        }

        return $stages;
    }

    private function resolveKardexSitraOperationalStage(array $events, string $code): ?string
    {
        $events = collect($events)
            ->map(fn ($event) => (object) (is_array($event) ? $event : (array) $event))
            ->sortByDesc(fn ($event) => strtotime((string) ($event->timeline_sort_at ?? $event->created_at ?? '')) ?: 0)
            ->values();
        if ($events->isEmpty()) {
            return null;
        }

        $service = preg_match('/^R[A-Z]\d{9}[A-Z]{2}$/', $code) === 1 ? 'CERTI' : 'EMS';
        $progress = app(\App\Services\TrackingProgressService::class)->resolve($events, $service);
        $steps = collect($progress['steps'] ?? [])->map(fn ($step) => $this->normalizeKardexText((string) $step));
        $currentStep = $this->normalizeKardexText((string) data_get($progress, 'steps.'.(int) data_get($progress, 'current_index', 0), ''));
        $progressStatus = $this->normalizeKardexText((string) data_get($progress, 'status', ''));
        $latestEvent = (object) $events->first();
        $latestEventCode = is_numeric($latestEvent->codigo_evento ?? null)
            ? (int) $latestEvent->codigo_evento
            : null;
        $latestLocalEventId = is_numeric($latestEvent->evento_id ?? null)
            ? (int) $latestEvent->evento_id
            : null;
        $latestEventText = $this->normalizeKardexText(implode(' ', array_filter([
            (string) ($latestEvent->nombre_evento ?? ''),
            (string) ($latestEvent->eventType ?? ''),
            (string) ($latestEvent->evento ?? ''),
            (string) ($latestEvent->descripcion ?? ''),
        ])));
        $counterEventCodes = [32, 73, 75];
        $counterEventIds = array_merge(
            $counterEventCodes,
            self::KARDEX_EVENT_COUNTER_DELIVERY,
            [self::KARDEX_EVENT_SENT_TO_WINDOW_EMS, self::KARDEX_EVENT_SENT_TO_WINDOW_CERTI]
        );
        $courierEventCodes = [36, 39, 67, 74];
        $courierEventIds = array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY);
        $latestRouteEvent = $events->first(function ($event) use ($counterEventIds, $courierEventCodes, $courierEventIds): bool {
            $event = (object) $event;
            $eventCode = is_numeric($event->codigo_evento ?? null) ? (int) $event->codigo_evento : null;
            $eventId = is_numeric($event->evento_id ?? null) ? (int) $event->evento_id : null;

            return in_array($eventCode, $courierEventCodes, true)
                || in_array($eventCode, [32, 73, 75], true)
                || in_array($eventId, $courierEventIds, true)
                || in_array($eventId, $counterEventIds, true);
        });
        $latestRouteEvent = $latestRouteEvent !== null ? (object) $latestRouteEvent : null;
        $latestRouteEventCode = is_numeric($latestRouteEvent?->codigo_evento ?? null)
            ? (int) $latestRouteEvent->codigo_evento
            : null;
        $latestRouteLocalEventId = is_numeric($latestRouteEvent?->evento_id ?? null)
            ? (int) $latestRouteEvent->evento_id
            : null;
        $latestRouteIsDomiciliary = in_array($latestRouteEventCode, $courierEventCodes, true)
            || in_array($latestRouteLocalEventId, $courierEventIds, true);

        if (in_array($progressStatus, ['ENVIO CANCELADO', 'DEVOLUCION EN CURSO', 'DEVUELTO AL REMITENTE'], true)) {
            return null;
        }

        // El evento actual manda sobre los eventos históricos. Por ejemplo,
        // un envío entregado puede conservar eventos anteriores de Aduana.
        if ($progressStatus === 'ENTREGADO' || in_array($latestEventCode, [37, 1250], true)) {
            $deliveryMode = strtoupper(trim((string) ($latestEvent->delivery_mode ?? '')));
            if ($deliveryMode === 'DOMICILIARIA') {
                return 'DISTRIBUCIÓN DOMICILIARIA';
            }
            if ($deliveryMode === 'NO_DOMICILIARIA') {
                return 'DISTRIBUCIÓN NO DOMICILIARIA';
            }
            if ($latestRouteIsDomiciliary) {
                return 'DISTRIBUCIÓN DOMICILIARIA';
            }
            $hasCounterDeliveryEvidence = in_array($latestRouteLocalEventId, self::KARDEX_EVENT_COUNTER_DELIVERY, true)
                || $latestRouteEventCode === 75
                || ($latestEventCode === 37 && $events->contains(fn ($event) => (int) ($event->codigo_evento ?? 0) === 38));

            return $hasCounterDeliveryEvidence ? 'DISTRIBUCIÓN NO DOMICILIARIA' : null;
        }

        if (str_contains($currentStep, 'cartero')
            || in_array($latestEventCode, $courierEventCodes, true)
            || in_array($latestLocalEventId, $courierEventIds, true)) {
            return 'DISTRIBUCIÓN DOMICILIARIA';
        }
        if ((bool) data_get($progress, 'is_pickup_available')
            || (bool) data_get($progress, 'pickup_ready_at_destination_customs')
            || in_array($latestEventCode, $counterEventCodes, true)
            || in_array($latestLocalEventId, $counterEventIds, true)
            || $currentStep === 'VENTANILLA'
            // El evento 38 libera el envío de Aduana hacia la ventanilla postal.
            || ($currentStep === 'VENTANILLA = ADUANA' && $latestEventCode === 38)) {
            return 'DISTRIBUCIÓN NO DOMICILIARIA';
        }

        $isAdmissionEvent = in_array($latestEventCode, [1, 45, 68, 78], true)
            || in_array($latestLocalEventId, [self::KARDEX_EVENT_ADMISSION, self::KARDEX_EVENT_CERTI_ADMISSION, 2239], true)
            || str_contains($latestEventText, 'ADMISION')
            || str_contains($latestEventText, 'RECIBIR ENVIO DEL CLIENTE')
            || str_contains($latestEventText, 'PAQUETE RECIBIDO DEL CLIENTE');
        if ($isAdmissionEvent) {
            return 'ADMISIÓN';
        }

        return null;
    }

    private function splitKardexPackageRows(array $row): Collection
    {
        $detalleCodigos = collect($row['detalle_codigos'] ?? []);
        $packageEntries = $detalleCodigos
            ->filter(fn (array $entry): bool => $this->isKardexPackageEntry($entry))
            ->filter(function (array $entry): bool {
                $source = $entry['source'] ?? null;

                return is_object($source) && max(1, (int) data_get($source, 'cantidad', 1)) === 1;
            })
            ->unique(fn (array $entry) => spl_object_id($entry['source']))
            ->values();

        if ($packageEntries->isEmpty()) {
            $sourceItems = collect($row['kardex_source_items'] ?? [])->filter(fn ($item) => is_object($item));
            $hasPackageItems = $sourceItems->contains(fn ($item) => $this->isPackageCartItem($item))
                || $detalleCodigos->contains(fn (array $entry) => $this->isKardexPackageEntry($entry));
            $sourceItemDetails = $sourceItems
                ->map(fn ($item) => $this->resolveKardexItemDisplayDetail($item))
                ->filter()
                ->unique()
                ->implode(' / ');
            if ($sourceItemDetails !== '') {
                $row['detalle_items'] = $sourceItemDetails;
            }
            if ($hasPackageItems) {
                $packageCodes = $detalleCodigos->pluck('codigo')->filter()->values();
                $row['codigo_referencia'] = $packageCodes->isNotEmpty() ? $packageCodes->implode(', ') : '-';
                $serviceNames = $detalleCodigos
                    ->pluck('servicio')
                    ->map(fn ($service) => trim((string) $service))
                    ->filter()
                    ->unique()
                    ->values();
                if ($serviceNames->isNotEmpty()) {
                    $row['tipo_envio'] = $serviceNames->implode(' / ');
                }
            } else {
                $row['codigo_referencia'] = '-';
                $row['kardex_non_package_service'] = true;
            }
            unset($row['kardex_source_items']);

            return collect([$row]);
        }

        $sourceItems = collect($row['kardex_source_items'] ?? [])->filter(fn ($item) => is_object($item));
        $packageSourceIds = $packageEntries
            ->map(fn (array $entry) => spl_object_id($entry['source']))
            ->flip();
        $remainingItems = $sourceItems
            ->reject(fn ($item) => $packageSourceIds->has(spl_object_id($item)))
            ->values();
        $packageAmounts = $packageEntries
            ->map(fn (array $entry) => $this->resolveKardexItemAmount($entry['source']))
            ->all();
        $invoiceAmount = round((float) ($row['importe_general'] ?? 0), 2);
        $packageAmountTotal = round(array_sum($packageAmounts), 2);

        if ($remainingItems->isEmpty() && $packageAmountTotal !== $invoiceAmount) {
            $packageAmounts = $this->reconcileKardexLineAmounts($invoiceAmount, $packageAmounts);
            $packageAmountTotal = round(array_sum($packageAmounts), 2);
        } elseif ($packageAmountTotal > $invoiceAmount) {
            $packageAmounts = $this->reconcileKardexLineAmounts($invoiceAmount, $packageAmounts);
            $packageAmountTotal = round(array_sum($packageAmounts), 2);
        }

        $splitRows = $packageEntries
            ->values()
            ->map(function (array $entry, int $index) use ($row, $packageAmounts): array {
                $source = $entry['source'];
                $code = trim((string) ($entry['codigo'] ?? ''));
                $service = trim((string) ($entry['servicio'] ?? ''));
                $entryCollection = collect([$entry]);
                $amount = (float) ($packageAmounts[$index] ?? 0);

                return array_merge($row, [
                    'tipo_envio' => $service !== '' ? $service : (string) ($row['tipo_envio'] ?? ''),
                    'detalle_items' => trim((string) (
                        data_get($source, 'titulo')
                        ?: data_get($source, 'nombre_servicio')
                        ?: $service
                    )),
                    'detalle_codigos' => $entryCollection,
                    'codigo_paquetes' => collect([$code])->filter()->values(),
                    'codigo_paquetes_texto' => $code,
                    'codigo_referencia' => $code,
                    'cantidad' => 1,
                    'peso' => (float) data_get($source, 'resumen_origen.peso', data_get($source, 'peso', 0)),
                    'importe_parcial' => $amount,
                    'importe_general' => $amount,
                ]);
            });

        if ($remainingItems->isNotEmpty()) {
            $remainingEntries = $detalleCodigos
                ->reject(function (array $entry) use ($packageSourceIds): bool {
                    $source = $entry['source'] ?? null;

                    return is_object($source) && $packageSourceIds->has(spl_object_id($source));
                })
                ->values();
            $serviceNames = $remainingItems
                ->map(fn ($item) => trim((string) (data_get($item, 'nombre_servicio') ?: data_get($item, 'titulo', ''))))
                ->filter()
                ->unique()
                ->values();
            $itemTitles = $remainingItems
                ->map(fn ($item) => $this->resolveKardexItemDisplayDetail($item))
                ->filter()
                ->unique()
                ->values();
            $codes = $remainingEntries->pluck('codigo')->filter()->values();
            $residualAmount = max(0, round($invoiceAmount - $packageAmountTotal, 2));
            $residualRow = array_merge($row, [
                'tipo_envio' => $serviceNames->isNotEmpty() ? $serviceNames->implode(' / ') : 'OTROS SERVICIOS',
                'detalle_items' => $itemTitles->isNotEmpty() ? $itemTitles->implode(' / ') : 'Otros servicios',
                'detalle_codigos' => $remainingEntries,
                'codigo_paquetes' => $codes,
                'codigo_paquetes_texto' => $codes->implode(', '),
                'codigo_referencia' => '-',
                'kardex_non_package_service' => true,
                'cantidad' => max(1, (int) $remainingItems->sum(fn ($item) => max(1, (int) data_get($item, 'cantidad', 1)))),
                'peso' => round((float) $remainingItems->sum(fn ($item) => (float) data_get($item, 'resumen_origen.peso', data_get($item, 'peso', 0))), 3),
                'importe_parcial' => $residualAmount,
                'importe_general' => $residualAmount,
            ]);
            $splitRows->push($residualRow);
        }

        unset($row['kardex_source_items']);

        return $splitRows
            ->map(function (array $splitRow): array {
                unset($splitRow['kardex_source_items']);

                return $splitRow;
            })
            ->values();
    }

    private function resolveKardexItemDisplayDetail(mixed $item): string
    {
        $title = trim((string) (data_get($item, 'titulo') ?: data_get($item, 'nombre_servicio', '')));
        $serviceDescription = trim((string) (
            data_get($item, 'resumen_origen.descripcion_servicio')
            ?: data_get($item, 'descripcion_servicio')
            ?: ''
        ));
        $itemService = trim((string) (data_get($item, 'nombre_servicio') ?: $title));
        $isCasilla = str_contains($this->normalizeKardexText($title . ' ' . $itemService . ' ' . $serviceDescription), 'CASILLA');

        return $isCasilla && $serviceDescription !== '' ? $serviceDescription : $title;
    }

    private function isKardexPackageEntry(array $entry): bool
    {
        $source = $entry['source'] ?? null;
        $code = trim((string) ($entry['codigo'] ?? ''));
        if (! is_object($source) || $code === '') {
            return false;
        }

        $origin = ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\');
        $packageOrigins = [PaqueteEms::class, PaqueteInt::class, PaqueteOrdi::class, PaqueteCerti::class];
        if (in_array($origin, array_map(fn ($type) => ltrim($type, '\\'), $packageOrigins), true)) {
            return true;
        }

        if ($origin !== 'App\\Models\\ConceptoFacturacion') {
            return false;
        }

        $references = [
            data_get($source, 'codigo'),
            data_get($source, 'codigo_paquete'),
            data_get($source, 'resumen_origen.codigo_paquete'),
        ];

        foreach ($references as $reference) {
            if (preg_match('/^SRVE-(?:2|3|4|11|12)\\s*-/i', trim((string) $reference)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function resolveKardexItemAmount(object $item): float
    {
        $base = (float) data_get($item, 'monto_base', data_get($item, 'precio', 0));
        $extras = (float) data_get($item, 'monto_extras', 0);
        $quantity = max(1, (int) data_get($item, 'cantidad', 1));
        $lineTotal = data_get($item, 'total_linea');

        if (is_numeric($lineTotal) && ((float) $lineTotal > 0 || ($base + $extras) <= 0)) {
            return round((float) $lineTotal, 2);
        }

        return round(($base + $extras) * $quantity, 2);
    }

    private function reconcileKardexLineAmounts(float $targetAmount, array $amounts): array
    {
        if ($amounts === []) {
            return [];
        }

        $weights = array_map(fn ($amount) => max(0, (float) $amount), $amounts);
        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            $weights = array_fill(0, count($amounts), 1.0);
            $weightTotal = count($weights);
        }

        $remainingCents = max(0, (int) round($targetAmount * 100));
        $allocated = [];
        foreach ($weights as $index => $weight) {
            if ($index === array_key_last($weights)) {
                $allocated[] = $remainingCents / 100;
                break;
            }

            $cents = (int) floor(($targetAmount * 100) * ($weight / $weightTotal));
            $cents = max(0, min($remainingCents, $cents));
            $allocated[] = $cents / 100;
            $remainingCents -= $cents;
        }

        return $allocated;
    }

    private function formatKardexViewRow(array $row, array $localDestinations, array $trackingDestinations): array
    {
        $detalleCodigos = collect($row['detalle_codigos'] ?? []);
        $tipoServicio = trim((string) ($row['tipo_envio'] ?? ''));
        $isNonPackageService = (bool) ($row['kardex_non_package_service'] ?? false);
        $guiaCasilla = $isNonPackageService
            ? '-'
            : $detalleCodigos->pluck('codigo')->filter()->implode(', ');
        $hasDestinationHint = $detalleCodigos
            ->pluck('destino_hint')
            ->contains(fn ($hint) => trim((string) $hint) !== '');
        if (! $isNonPackageService && $guiaCasilla === '' && ! $hasDestinationHint) {
            $guiaCasilla = trim((string) ($row['codigo_referencia'] ?? ''));
        }
        $regional = $this->resolveKardexRegional($row);
        $esCasilla = $this->isKardexCasillaService($tipoServicio, $guiaCasilla);
        $tipoCorrespondencia = $esCasilla
            ? $this->formatKardexCasillaLabel($tipoServicio, $guiaCasilla)
            : $this->resolveKardexServiceDisplayName($tipoServicio);
        $estadoEmision = strtoupper(trim((string) ($row['estado_emision'] ?? '')));
        $estadoVenta = strtolower(trim((string) ($row['estado_venta'] ?? '')));
        $esAnulada = in_array($estadoEmision, ['ANULADA', 'ANULADO'], true)
            || in_array($estadoVenta, ['anulado', 'descartado'], true)
            || ($row['section_key'] ?? '') === 'factura_anulada';
        $metodoPago = strtolower(trim((string) ($row['metodo_pago'] ?? '')));
        $estadoPago = strtolower(trim((string) ($row['estado_pago'] ?? '')));
        $metodoPagoLabel = (bool) ($row['es_cuenta_por_cobrar'] ?? false)
            ? 'CUENTA POR COBRAR'
            : (strtolower(trim((string) ($row['canal_emision'] ?? ''))) === 'oficial'
                ? 'REGISTRO OFICIAL'
                : ($metodoPago === 'qr'
                    ? 'QR · ' . strtoupper($estadoPago !== '' ? $estadoPago : 'PENDIENTE')
                    : 'EFECTIVO'));
        $estadoVentaLabel = match ($estadoVenta) {
            'emitido' => 'EMITIDO',
            'pendiente_pago' => 'PENDIENTE DE PAGO',
            'borrador' => 'BORRADOR',
            'anulado', 'descartado' => 'ANULADO',
            default => $estadoEmision !== '' ? $estadoEmision : 'SIN ESTADO',
        };
        $destino = $esCasilla
            ? ''
            : $this->resolveKardexDestinationFromBatch($row, $localDestinations, $trackingDestinations);
        $detalleCasilla = '';
        if ($esCasilla) {
            $detalleCasilla = trim((string) ($row['detalle_items'] ?? ''));
            if ($detalleCasilla === '' || in_array($this->normalizeKardexText($detalleCasilla), ['SIN DETALLE REAL', 'OTROS SERVICIOS'], true)) {
                $detalleCasilla = $tipoServicio;
            }

            $detalleCasilla = collect(preg_split('/\s+\/\s+/', $detalleCasilla) ?: [$detalleCasilla])
                ->map(fn ($detail) => trim((string) (preg_replace('/^\s*(?:SERVICIO\s+)?CASILLA\s*[-:\x{2013}\x{2014}]?\s*/iu', '', (string) $detail) ?? $detail)))
                ->filter(fn ($detail) => $detail !== '' && ! in_array($this->normalizeKardexText($detail), ['CASILLA', 'SERVICIO CASILLA'], true))
                ->implode(' | ');
        }

        return array_merge($row, [
            'fecha' => $row['fecha'] ?? '-',
            'cantidad' => (int) ($row['cantidad'] ?? 0),
            'regional_registro' => $regional,
            'origen' => $this->resolveKardexOriginCode($regional, $row['origen_sucursal_codigo'] ?? null),
            'tipo_servicio' => $tipoServicio !== '' ? $tipoServicio : 'SIN DETALLE',
            'tipo_correspondencia' => $tipoCorrespondencia,
            'detalle_casilla' => $detalleCasilla,
            'guia_casilla' => $esCasilla ? '' : ($guiaCasilla !== '' ? $guiaCasilla : '-'),
            'peso' => $esCasilla ? null : (float) ($row['peso'] ?? 0),
            'pais_ciudad' => $destino,
            'numero_factura' => trim((string) ($row['numero_factura'] ?? '-')) ?: '-',
            'importe' => (float) ($row['importe_general'] ?? 0),
            'importe_venta' => (float) ($row['importe_venta'] ?? $row['importe_general'] ?? 0),
            'metodo_pago_label' => $metodoPagoLabel,
            'facturacion_label' => trim((string) ($row['estado_label'] ?? '')) ?: 'SIN ESTADO',
            'estado_venta_label' => $estadoVentaLabel,
            'estado_emision' => $estadoEmision,
            'es_anulada' => $esAnulada,
            'es_casilla' => $esCasilla,
        ]);
    }

    private function resolveKardexDestinationFromBatch(array $row, array $localDestinations, array $trackingDestinations): string
    {
        $destinations = collect($row['detalle_codigos'] ?? [])
            ->map(function (array $entry) use ($row, $localDestinations, $trackingDestinations): string {
                $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                $destinationHint = $this->cleanKardexReportText((string) ($entry['destino_hint'] ?? ''));
                $service = trim((string) ($entry['servicio'] ?? '')) ?: (string) ($row['tipo_envio'] ?? '');
                $isInternational = $this->isKardexInternationalService($service);
                if ($code === '') {
                    return $isInternational ? $this->kardexInternationalCountry($destinationHint) : $destinationHint;
                }

                if ($isInternational) {
                    $trackingDestination = $this->cleanKardexReportText((string) ($trackingDestinations[$code] ?? ''));
                    if ($trackingDestination !== '') {
                        return $this->normalizeKardexInternationalCountry($trackingDestination);
                    }
                }

                foreach ($this->kardexDestinationLookupOrder($code, $service) as $lookup) {
                    $destination = $this->cleanKardexReportText((string) data_get($localDestinations, $code.'.'.$lookup, ''));
                    if ($destination !== '') {
                        return $isInternational ? $this->normalizeKardexInternationalCountry($destination) : $destination;
                    }
                }

                return $isInternational ? $this->normalizeKardexInternationalCountry($destinationHint) : $destinationHint;
            })
            ->filter()
            ->unique()
            ->values();

        if ($destinations->isNotEmpty()) {
            return $destinations->implode(', ');
        }

        $fallback = $this->cleanKardexReportText($this->resolveKardexDestination($row, 'place'));
        if ($fallback !== '' && $this->isKardexInternationalService((string) ($row['tipo_envio'] ?? ''))) {
            $fallback = $this->normalizeKardexInternationalCountry($fallback);
        }

        return $fallback !== '' ? $fallback : '-';
    }

    private function lookupKardexDestinationsFromTrackingEvents(array $trackingEventsByCode): array
    {
        $destinations = [];

        foreach ($trackingEventsByCode as $rawCode => $rawEvents) {
            $code = $this->normalizeKardexPackageLookupCode((string) $rawCode);
            if ($code === '') {
                continue;
            }

            $events = collect((array) $rawEvents)
                ->map(fn ($event) => (array) $event)
                ->sortByDesc(fn (array $event) => strtotime((string) ($event['created_at'] ?? '')) ?: 0)
                ->values();

            foreach ($events as $event) {
                $countryName = trim((string) (
                    $event['destination_country_name']
                    ?? $event['pais_destino_nombre']
                    ?? $event['pais_destino']
                    ?? $event['country_destino']
                    ?? ''
                ));
                $countryCode = strtoupper(trim((string) (
                    $event['destination_country_code']
                    ?? $event['pais_destino_iso2']
                    ?? ''
                )));
                if ($countryName === '' && $countryCode !== '') {
                    $countryName = $countryCode;
                }

                $country = $this->normalizeKardexInternationalCountry($countryName);
                if ($country !== '') {
                    $destinations[$code] = $country;
                    break;
                }
            }

            if (isset($destinations[$code])) {
                continue;
            }

            $enteredBolivia = $events->contains(function (array $event): bool {
                $eventCode = $event['codigo_evento'] ?? $event['eventCode'] ?? $event['event_type_cd'] ?? null;
                if (! is_numeric($eventCode) || ! in_array((int) $eventCode, [31, 34, 38, 76], true)) {
                    return false;
                }

                $offices = [
                    $event['office_code'] ?? null,
                    $event['officeCode'] ?? null,
                    $event['office'] ?? null,
                    $event['oficina'] ?? null,
                ];

                foreach ($offices as $office) {
                    if (preg_match('/^\s*BO[A-Z0-9]{2,}/i', (string) $office) === 1) {
                        return true;
                    }
                }

                return false;
            });

            if ($enteredBolivia) {
                $destinations[$code] = 'BOLIVIA (PLURINATIONAL STATE OF)';
            }
        }

        return $destinations;
    }

    private function normalizeKardexInternationalCountry(string $country): string
    {
        $country = $this->kardexInternationalCountry($this->cleanKardexReportText($country));
        $normalized = $this->normalizeKardexText($country);

        if (in_array($normalized, [
            'BO',
            'BOL',
            'BOLIVIA',
            'BOLIVIA PLURINATIONAL STATE OF',
            'BOLIVIA (PLURINATIONAL STATE OF)',
            'PLURINATIONAL STATE OF BOLIVIA',
        ], true)) {
            return 'BOLIVIA (PLURINATIONAL STATE OF)';
        }

        return $country;
    }

    private function kardexInternationalCountry(string $destination): string
    {
        $parts = explode('/', $destination, 2);
        $country = trim((string) ($parts[1] ?? $parts[0]));

        return $country !== '' ? $country : trim($destination);
    }

    private function lookupKardexDestinationsInBatch(Collection $codes): array
    {
        $codes = $codes
            ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
            ->filter()
            ->unique()
            ->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $destinations = [];
        $models = [
            'ems' => [PaqueteEms::class, ['ciudad']],
            'int' => [PaqueteInt::class, ['destino']],
            'ordi' => [PaqueteOrdi::class, ['pais', 'ciudad']],
            'certi' => [PaqueteCerti::class, ['cuidad']],
        ];

        foreach ($models as $type => [$model, $columns]) {
            foreach ($codes->chunk(400) as $chunk) {
                $values = $chunk->all();
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $rows = $model::query()
                    ->where(function ($query) use ($placeholders, $values): void {
                        $query->whereRaw('trim(upper(codigo)) IN ('.$placeholders.')', $values)
                            ->orWhereRaw("trim(upper(coalesce(cod_especial, ''))) IN (".$placeholders.')', $values);
                    })
                    ->get(array_merge(['codigo', 'cod_especial'], $columns));

                foreach ($rows as $package) {
                    $code = $this->normalizeKardexPackageLookupCode((string) data_get($package, 'codigo', ''));
                    $specialCode = $this->normalizeKardexPackageLookupCode((string) data_get($package, 'cod_especial', ''));
                    $country = $this->cleanKardexReportText((string) data_get($package, 'pais', ''));
                    $city = $this->cleanKardexReportText((string) (
                        data_get($package, 'ciudad')
                        ?? data_get($package, 'cuidad')
                        ?? data_get($package, 'destino')
                        ?? ''
                    ));
                    $destination = $type === 'ordi' && $country !== '' ? $country : $city;

                    foreach ([$code, $specialCode] as $alias) {
                        if ($alias !== '' && $chunk->contains($alias) && $destination !== '') {
                            $destinations[$alias][$type] ??= $destination;
                        }
                    }
                }
            }
        }

        return $destinations;
    }

    private function isKardexInternationalService(string $service): bool
    {
        $service = $this->normalizeKardexText($service);

        return str_contains($service, 'INTERNACIONAL') || str_contains($service, 'ENCOMIENDA RETOUR');
    }

    private function kardexDestinationLookupOrder(string $code, string $service = ''): array
    {
        $service = $this->normalizeKardexText($service);

        if (str_contains($service, 'EMS') && ! str_contains($service, 'INTERNACIONAL')) {
            return ['ems', 'int', 'ordi', 'certi'];
        }
        if (str_contains($service, 'ORDINARIA') || str_contains($service, 'ORDINARIAS')) {
            return str_contains($service, 'INTERNACIONAL') ? ['int', 'ordi', 'ems', 'certi'] : ['ordi', 'ems', 'int', 'certi'];
        }
        if (str_contains($service, 'CERTIFICADO') || str_contains($service, 'CERTIFICADAS')) {
            return str_contains($service, 'INTERNACIONAL') ? ['int', 'certi', 'ems', 'ordi'] : ['certi', 'int', 'ems', 'ordi'];
        }
        if (str_contains($service, 'ENCOMIENDA') || str_contains($service, 'INTERNACIONAL')) {
            return ['int', 'ems', 'ordi', 'certi'];
        }

        return $this->looksLikeInternationalBolivianTracking($code)
            ? ['int', 'ems', 'ordi', 'certi']
            : ['ems', 'ordi', 'certi', 'int'];
    }

    private function looksLikeInternationalBolivianTracking(string $code): bool
    {
        return (bool) preg_match('/^(EE|CP|RR)[A-Z0-9]+BO$/i', $code);
    }

    private function normalizeKardexPackageLookupCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/^VFC-\d+\s+PAQUETES:\s*/i', '', $code) ?? $code;
        $code = preg_replace('/^VQC-\d+\s+PAQUETES:\s*/i', '', $code) ?? $code;
        $code = preg_replace('/^SRVE-\d+\s*-\s*/i', '', $code) ?? $code;
        $code = trim($code);

        return $this->isServiceReferenceCode($code) ? '' : $code;
    }

    private function cleanKardexReportText(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?: '';
        if ($value === '' || $value === '-') {
            return '';
        }

        return mb_strtoupper($value);
    }
    private function resolveKardexServiceDisplayName(string $service): string
    {
        $service = str_replace('_', ' ', $service);
        $service = strtoupper(trim(preg_replace('/\s+/', ' ', $service) ?? $service));
        if ($service === '') {
            return 'SIN DETALLE';
        }

        if (str_contains($this->normalizeKardexText($service), 'PAGO DE CASILLA')) {
            return $service;
        }

        $beforeDash = strtoupper(trim((string) (preg_split('/\s+-\s*/', $service, 2)[0] ?? $service)));
        $beforeDashNormalized = $this->normalizeKardexText($beforeDash);
        $normalized = $this->normalizeKardexText($service);

        $map = [
            'SERVICIO AEROLINEA' => 'AEROLINEA',
            'AEROLINEA' => 'AEROLINEA',
            'SERVICIO CASILLA' => 'CASILLA',
            'CASILLA' => 'CASILLA',
            'SERVICIO CERTIFICADO INTERNACIONAL' => 'CERTIFICADO INTERNACIONAL',
            'CERTIFICADO INTERNACIONAL' => 'CERTIFICADO INTERNACIONAL',
            'SERVICIO CERTIFICADO' => 'CERTIFICADO',
            'CERTIFICADO' => 'CERTIFICADO',
            'SERVICIO CERTIFICADAS' => 'CERTIFICADAS',
            'CERTIFICADAS' => 'CERTIFICADAS',
            'SERVICIO CONTRATOS' => 'CONTRATOS',
            'CONTRATOS' => 'CONTRATOS',
            'SERVICIO ECA INTERNACIONAL' => 'ECA INTERNACIONAL',
            'ECA INTERNACIONAL' => 'ECA INTERNACIONAL',
            'SERVICIO ECA' => 'ECA',
            'ECA' => 'ECA',
            'SERVICIO EMS INTERNACIONAL' => 'EMS INTERNACIONAL',
            'EMS INTERNACIONAL' => 'EMS INTERNACIONAL',
            'SERVICIO EMS LOCAL COBERTURA 1' => 'EMS LOCAL COBERTURA 1',
            'EMS LOCAL COBERTURA 1' => 'EMS LOCAL COBERTURA 1',
            'SERVICIO EMS LOCAL COBERTURA 2' => 'EMS LOCAL COBERTURA 2',
            'EMS LOCAL COBERTURA 2' => 'EMS LOCAL COBERTURA 2',
            'SERVICIO EMS LOCAL COBERTURA 3' => 'EMS LOCAL COBERTURA 3',
            'EMS LOCAL COBERTURA 3' => 'EMS LOCAL COBERTURA 3',
            'SERVICIO EMS LOCAL COBERTURA 4' => 'EMS LOCAL COBERTURA 4',
            'EMS LOCAL COBERTURA 4' => 'EMS LOCAL COBERTURA 4',
            'SERVICIO EMS NACIONAL' => 'EMS NACIONAL',
            'EMS NACIONAL' => 'EMS NACIONAL',
            'SERVICIO EMS' => 'EMS',
            'EMS' => 'EMS',
            'SERVICIO ENCOMIENDA INTERNACIONAL' => 'ENCOMIENDA INTERNACIONAL',
            'ENCOMIENDA INTERNACIONAL' => 'ENCOMIENDA INTERNACIONAL',
            'SERVICIO ENCOMIENDA RETOUR' => 'ENCOMIENDA RETOUR',
            'ENCOMIENDA RETOUR' => 'ENCOMIENDA RETOUR',
            'SERVICIO ENCOMIENDA' => 'ENCOMIENDA',
            'ENCOMIENDA' => 'ENCOMIENDA',
            'SERVICIO VENTA DE ESTAMPILLAS' => 'ESTAMPILLAS',
            'VENTA DE ESTAMPILLAS' => 'ESTAMPILLAS',
            'ESTAMPILLAS' => 'ESTAMPILLAS',
            'SERVICIO ORDINARIA INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'SERVICIO ORDINARIAS INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'ORDINARIA INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'ORDINARIAS INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'SERVICIO ORDINARIAS' => 'ORDINARIAS',
            'SERVICIO ORDINARIA' => 'ORDINARIAS',
            'ORDINARIAS' => 'ORDINARIAS',
            'SERVICIO CIUDADES INTERMEDIAS TRINIDAD COBIJA' => 'CIUDADES INTERMEDIAS TRINIDAD COBIJA',
            'CIUDADES INTERMEDIAS TRINIDAD COBIJA' => 'CIUDADES INTERMEDIAS TRINIDAD COBIJA',
            'SERVICIO CIUDADES INTERMEDIAS' => 'CIUDADES INTERMEDIAS',
            'CIUDADES INTERMEDIAS' => 'CIUDADES INTERMEDIAS',
            'SERVICIO SUPER EXPRESS NACIONAL' => 'SUPER EXPRESS NACIONAL',
            'SUPER EXPRESS NACIONAL' => 'SUPER EXPRESS NACIONAL',
            'SERVICIO TRINIDAD COBIJA' => 'TRINIDAD COBIJA',
            'TRINIDAD COBIJA' => 'TRINIDAD COBIJA',
            'SERVICIO INTERNACIONAL' => 'INTERNACIONAL',
            'INTERNACIONAL' => 'INTERNACIONAL',
            'SERVICIO VENTA DE TARJETA POSTAL' => 'TARJETA POSTAL',
            'VENTA DE TARJETA POSTAL' => 'TARJETA POSTAL',
            'TARJETA POSTAL' => 'TARJETA POSTAL',
            'VENTANILLA' => 'VENTANILLA',
        ];

        return $map[$beforeDashNormalized] ?? $map[$normalized] ?? $beforeDash;
    }
    private function resolveKardexOriginCode(string $regional, mixed $codigoSucursal = null): string
    {
        $normalized = $this->normalizeKardexText($regional);
        $map = [
            'LA PAZ' => 'LPB',
            'COCHABAMBA' => 'CBB',
            'SANTA CRUZ' => 'SCZ',
            'SANTA CRUZ DE LA SIERRA' => 'SCZ',
            'ORURO' => 'ORU',
            'POTOSI' => 'POI',
            'CHUQUISACA' => 'SRE',
            'SUCRE' => 'SRE',
            'TARIJA' => 'TJA',
            'BENI' => 'TDD',
            'TRINIDAD' => 'TDD',
            'PANDO' => 'CIJ',
            'COBIJA' => 'CIJ',
        ];

        return $map[$normalized] ?? (trim((string) $codigoSucursal) !== '' ? trim((string) $codigoSucursal) : '-');
    }

    private function resolveKardexCorrespondenceType(string $tipoServicio, string $guiaCasilla): string
    {
        $normalized = $this->normalizeKardexText($tipoServicio . ' ' . $guiaCasilla);

        if (str_contains($normalized, 'CASILLA')) {
            return $this->formatKardexCasillaLabel($tipoServicio, $guiaCasilla);
        }

        if (str_contains($normalized, 'CERTIFICADO')) {
            return 'CERTIFICADO';
        }

        if (str_contains($normalized, 'ENCOMIENDA')) {
            return 'ENCOMIENDA';
        }

        if (str_contains($normalized, 'EMS')) {
            return 'EMS';
        }

        return strtoupper($tipoServicio !== '' ? $tipoServicio : 'SIN DETALLE');
    }

    private function formatKardexCasillaLabel(string $tipoServicio, string $guiaCasilla): string
    {
        $tipoServicio = trim($tipoServicio);
        $guiaCasilla = trim($guiaCasilla);
        $candidate = $guiaCasilla !== '' && $guiaCasilla !== '-' && str_contains($this->normalizeKardexText($guiaCasilla), 'PAGO DE CASILLA')
            ? $guiaCasilla
            : $tipoServicio;

        if ($candidate === '' || ! str_contains($this->normalizeKardexText($candidate), 'PAGO DE CASILLA')) {
            $candidate = trim($tipoServicio . ' ' . ($guiaCasilla !== '-' ? $guiaCasilla : ''));
        }

        $candidate = preg_replace('/\s+/', ' ', $candidate) ?: 'PAGO DE CASILLA';

        return mb_strtoupper(trim($candidate));
    }

    private function normalizeKardexText(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = $normalized !== false ? $normalized : $value;

        return strtoupper(trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized));
    }

    private function resolveKardexRegional(array $row): string
    {
        $regional = trim((string) (
            data_get($row, 'origen_sucursal_municipio')
            ?: data_get($row, 'origen_sucursal_departamento')
            ?: data_get($row, 'origen_sucursal_nombre')
            ?: data_get($row, 'origen_usuario_ciudad')
        ));

        return $regional !== '' ? $regional : '-';
    }

    private function resolveKardexDestination(array $row, string $mode): string
    {
        $values = collect($row['detalle_codigos'] ?? [])
            ->map(function ($entry) use ($mode) {
                $source = data_get($entry, 'source');
                $country = collect([
                    data_get($source, 'resumen_origen.pais_destino'),
                    data_get($source, 'resumen_origen.pais'),
                    data_get($source, 'pais_destino'),
                    data_get($source, 'pais'),
                    data_get($source, 'destino_pais'),
                ])->first(fn ($value) => trim((string) $value) !== '');
                $city = collect([
                    data_get($source, 'resumen_origen.ciudad_destino'),
                    data_get($source, 'resumen_origen.ciudad'),
                    data_get($source, 'ciudad_destino'),
                    data_get($source, 'ciudad'),
                    data_get($source, 'destino_ciudad'),
                ])->first(fn ($value) => trim((string) $value) !== '');

                return $mode === 'region'
                    ? trim((string) ($country ?: $city))
                    : trim(implode(' / ', array_filter([trim((string) $city), trim((string) $country)])));
            })
            ->filter()
            ->unique()
            ->values();

        return $values->isNotEmpty() ? $values->implode(', ') : '-';
    }

    private function extractDetailCodesFromItems(Collection $items): Collection
    {
        return $items
            ->flatMap(function ($item) {
                $candidates = [
                    trim((string) data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_item', '')),
                    trim((string) data_get($item, 'codigo', '')),
                    trim((string) data_get($item, 'codigo_item', '')),
                ];

                return collect($candidates)
                    ->filter(fn ($code) => $code !== '' && ! $this->isServiceReferenceCode((string) $code))
                    ->take(1)
                    ->all();
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function buildDetailCodeEntriesFromItems(Collection $items): Collection
    {
        return $items
            ->map(function ($item) {
                $candidates = collect([
                    trim((string) data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_item', '')),
                    trim((string) data_get($item, 'codigo', '')),
                    trim((string) data_get($item, 'codigo_item', '')),
                ])->filter();

                $codigo = '';
                $destinoHint = '';
                foreach ($candidates as $candidate) {
                    $candidate = $this->normalizeKardexPackageLookupCode((string) $candidate);
                    if ($candidate === '') {
                        continue;
                    }

                    if (preg_match('/^(?:VFC|VQC)-\d+/i', $candidate) === 1) {
                        continue;
                    }

                    $countryHint = $this->kardexCountryDestinationHint($candidate);
                    if ($countryHint !== '') {
                        $destinoHint = $destinoHint !== '' ? $destinoHint : $countryHint;
                        continue;
                    }

                    // A tracking code must contain a digit or a separator.
                    // This prevents a destination label such as FRANCIA from
                    // being shown or sent to SITRA as the shipment identifier.
                    if (preg_match('/[0-9-]/', $candidate) !== 1) {
                        continue;
                    }

                    $codigo = $candidate;
                    break;
                }

                if ($codigo === '' && $destinoHint === '') {
                    return null;
                }

                $servicio = trim((string) (
                    data_get($item, 'titulo')
                    ?: data_get($item, 'nombre_servicio')
                    ?: data_get($item, 'resumen_origen.descripcion_servicio')
                    ?: data_get($item, 'resumen_origen.servicio_nombre')
                    ?: 'Sin servicio'
                ));

                return [
                    'servicio' => $servicio,
                    'codigo' => $codigo,
                    'destino_hint' => $destinoHint,
                    'source' => $item,
                ];
            })
            ->filter()
            ->unique(fn ($entry) => mb_strtoupper(trim((string) data_get($entry, 'servicio', ''))) . '|' . mb_strtoupper(trim((string) data_get($entry, 'codigo', ''))))
            ->values();
    }

    private function kardexCountryDestinationHint(string $value): string
    {
        $value = $this->normalizeKardexText($value);
        $countries = [
            'AR' => 'ARGENTINA', 'ARGENTINA' => 'ARGENTINA',
            'BE' => 'BELGICA', 'BELGICA' => 'BELGICA', 'BELGIUM' => 'BELGICA',
            'BO' => 'BOLIVIA', 'BOLIVIA' => 'BOLIVIA',
            'BR' => 'BRASIL', 'BRASIL' => 'BRASIL', 'BRAZIL' => 'BRASIL',
            'CA' => 'CANADA', 'CANADA' => 'CANADA',
            'CH' => 'SUIZA', 'SUIZA' => 'SUIZA', 'SWITZERLAND' => 'SUIZA',
            'CL' => 'CHILE', 'CHILE' => 'CHILE',
            'CN' => 'CHINA', 'CHINA' => 'CHINA',
            'CO' => 'COLOMBIA', 'COLOMBIA' => 'COLOMBIA',
            'DE' => 'ALEMANIA', 'ALEMANIA' => 'ALEMANIA', 'GERMANY' => 'ALEMANIA',
            'DO' => 'REPUBLICA DOMINICANA', 'REPUBLICA DOMINICANA' => 'REPUBLICA DOMINICANA',
            'EC' => 'ECUADOR', 'ECUADOR' => 'ECUADOR',
            'ES' => 'ESPANA', 'ESPANA' => 'ESPANA', 'SPAIN' => 'ESPANA',
            'FR' => 'FRANCIA', 'FRANCIA' => 'FRANCIA', 'FRANCE' => 'FRANCIA',
            'GB' => 'REINO UNIDO', 'REINO UNIDO' => 'REINO UNIDO', 'UNITED KINGDOM' => 'REINO UNIDO',
            'IT' => 'ITALIA', 'ITALIA' => 'ITALIA',
            'JP' => 'JAPON', 'JAPON' => 'JAPON', 'JAPAN' => 'JAPON',
            'KR' => 'COREA DEL SUR', 'COREA DEL SUR' => 'COREA DEL SUR', 'SOUTH KOREA' => 'COREA DEL SUR',
            'MX' => 'MEXICO', 'MEXICO' => 'MEXICO',
            'NL' => 'PAISES BAJOS', 'PAISES BAJOS' => 'PAISES BAJOS', 'NETHERLANDS' => 'PAISES BAJOS',
            'PE' => 'PERU', 'PERU' => 'PERU',
            'PT' => 'PORTUGAL', 'PORTUGAL' => 'PORTUGAL',
            'PY' => 'PARAGUAY', 'PARAGUAY' => 'PARAGUAY',
            'SE' => 'SUECIA', 'SUECIA' => 'SUECIA', 'SWEDEN' => 'SUECIA',
            'US' => 'ESTADOS UNIDOS', 'ESTADOS UNIDOS' => 'ESTADOS UNIDOS', 'UNITED STATES' => 'ESTADOS UNIDOS',
            'UY' => 'URUGUAY', 'URUGUAY' => 'URUGUAY',
            'VE' => 'VENEZUELA', 'VENEZUELA' => 'VENEZUELA',
        ];

        return $countries[$value] ?? '';
    }

    private function isPackageCartItem(object|array $item): bool
    {
        $codigo = strtoupper(trim((string) data_get($item, 'codigo', '')));
        $codigoItem = strtoupper(trim((string) data_get($item, 'codigo_item', '')));
        $codigoPaquete = strtoupper(trim((string) data_get($item, 'codigo_paquete', '')));
        $resumenCodigo = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo', '')));
        $resumenCodigoItem = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo_item', '')));
        $resumenCodigoPaquete = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')));

        $references = collect([
            $codigoPaquete,
            $resumenCodigoPaquete,
            $codigo,
            $codigoItem,
            $resumenCodigo,
            $resumenCodigoItem,
        ])->filter();

        if ($references->isEmpty()) {
            return false;
        }

        $originHints = strtoupper(trim(implode(' ', array_filter([
            (string) data_get($item, 'origen_tipo', ''),
            (string) data_get($item, 'titulo', ''),
            (string) data_get($item, 'nombre_servicio', ''),
            (string) data_get($item, 'resumen_origen.descripcion_servicio', ''),
            (string) data_get($item, 'resumen_origen.servicio_nombre', ''),
        ]))));

        $hasPackageReference = $references->contains(function (string $reference): bool {
            return ! $this->isServiceReferenceCode($reference);
        });

        if (! $hasPackageReference) {
            return false;
        }

        return ! str_contains($originHints, 'SERVICIO')
            && ! str_contains($originHints, 'ADMISION')
            && ! str_contains($originHints, 'EXTRA');
    }

    private function isServiceReferenceCode(string $reference): bool
    {
        $reference = strtoupper(trim($reference));

        if ($reference === '') {
            return false;
        }

        return str_starts_with($reference, 'SRVE-')
            || str_starts_with($reference, 'SERV-')
            || str_starts_with($reference, 'SERVICIO-');
    }

    private function normalizeItems(mixed $items): Collection
    {
        $rows = $items instanceof Collection
            ? $items
            : (is_array($items) ? collect($items) : collect());

        return $rows
            ->map(fn ($item) => is_array($item) ? (object) $item : $item)
            ->filter(fn ($item) => is_object($item))
            ->values();
    }

    private function lookupLocalKardexPackageServices(Collection $pdfRows): array
    {
        $packageOrigins = [
            ltrim(PaqueteEms::class, '\\'),
            ltrim(PaqueteInt::class, '\\'),
            ltrim(PaqueteOrdi::class, '\\'),
            ltrim(PaqueteCerti::class, '\\'),
        ];
        $idsByOrigin = [];

        foreach ($pdfRows as $row) {
            foreach ($row['detalle_codigos'] ?? [] as $entry) {
                if (! $this->isKardexPackageEntry($entry)) {
                    continue;
                }

                $source = $entry['source'] ?? null;
                $origin = is_object($source) ? ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\') : '';
                $originId = is_object($source) ? (int) data_get($source, 'origen_id', 0) : 0;
                if ($originId > 0 && in_array($origin, $packageOrigins, true)) {
                    $idsByOrigin[$origin][] = $originId;
                }
            }
        }

        $services = [];
        foreach ($idsByOrigin as $origin => $ids) {
            $ids = array_values(array_unique($ids));

            if ($origin === ltrim(PaqueteEms::class, '\\')) {
                $packages = PaqueteEms::query()->with('tarifario.servicio')->whereIn('id', $ids)->get();
                foreach ($packages as $package) {
                    $name = trim((string) ($package->tarifario?->servicio?->nombre_servicio ?? ''));
                    if ($name !== '') {
                        $services[$origin.'#'.$package->id] = $name;
                    }
                }

                continue;
            }

            $model = match ($origin) {
                ltrim(PaqueteInt::class, '\\') => PaqueteInt::class,
                ltrim(PaqueteOrdi::class, '\\') => PaqueteOrdi::class,
                ltrim(PaqueteCerti::class, '\\') => PaqueteCerti::class,
                default => null,
            };
            if ($model === null) {
                continue;
            }

            foreach ($model::query()->with('servicio')->whereIn('id', $ids)->get() as $package) {
                $name = trim((string) ($package->servicio?->nombre_servicio ?? ''));
                if ($name !== '') {
                    $services[$origin.'#'.$package->id] = $name;
                }
            }
        }

        return $services;
    }

    private function buildTicketData(object $cart, $user, array $snapshot = []): array
    {
        $respuesta = (array) ($cart->respuesta_emision ?? []);
        $numeroFactura = trim((string) (data_get($respuesta, 'factura.nroFactura') ?? data_get($respuesta, 'factura.numeroFactura') ?? data_get($respuesta, 'numeroFactura') ?? ''));
        $cuf = trim((string) (data_get($respuesta, 'factura.cuf') ?? data_get($respuesta, 'cuf') ?? ''));
        $nit = preg_replace('/\D+/', '', (string) (data_get($respuesta, 'factura.nitEmisor') ?? data_get($respuesta, 'nitEmisor') ?? config('services.facturacion_bridge.nit_emisor') ?? '')) ?: 'S/N';
        $qrPayload = trim((string) (data_get($respuesta, 'factura.qrCode') ?? data_get($respuesta, 'factura.qrUrl') ?? data_get($respuesta, 'consultaSefe.qrUrl') ?? ''));
        if ($qrPayload === '' && $nit !== '' && $cuf !== '' && $numeroFactura !== '') {
            $qrPayload = 'https://pilotosiat.impuestos.gob.bo/consulta/QR?nit=' . urlencode($nit) . '&cuf=' . urlencode($cuf) . '&numero=' . urlencode($numeroFactura) . '&t=1';
        }
        $qrImage = null;
        if ($qrPayload !== '') {
            try {
                $qrImage = (new DNS2D())->getBarcodePNG($qrPayload, 'QRCODE,H', 5, 5);
            } catch (\Throwable) {
                $qrImage = null;
            }
        }
        $sucursal = $user?->sucursal;

        return [
            'empresa' => config('app.name') ?: 'Agencia Boliviana de Correos',
            'sucursal' => trim((string) ($sucursal->nombre ?? $sucursal->descripcion ?? 'Sucursal')),
            'direccion' => trim((string) (data_get($respuesta, 'factura.direccion') ?? '')),
            'telefono' => trim((string) (data_get($respuesta, 'factura.telefono') ?? ($sucursal->telefono ?? ''))),
            'nit' => $nit,
            'orden' => trim((string) ($cart->codigo_orden ?? ('VENT-' . $cart->id))),
            'nombre' => trim((string) ($cart->razon_social ?: 'SIN NOMBRE')),
            'documento' => trim((string) ($cart->numero_documento ?: '99003')),
            'numero_factura' => $numeroFactura !== '' ? $numeroFactura : 'S/N',
            'comprobante_label' => strtolower(trim((string) ($cart->metodo_pago ?? 'efectivo'))) === 'qr' ? 'Referencia QR' : 'Factura N°',
            'comprobante_valor' => strtolower(trim((string) ($cart->metodo_pago ?? 'efectivo'))) === 'qr'
                ? trim((string) ($cart->qr_transaction_id ?? $cart->codigo_orden ?? 'S/N'))
                : ($numeroFactura !== '' ? $numeroFactura : 'S/N'),
            'fecha' => !empty($cart->emitido_en) ? date('d/m/Y H:i:s', strtotime((string) $cart->emitido_en)) : '-',
            'importe' => round((float) $cart->total, 2),
            'metodo_pago' => match (strtolower(trim((string) ($cart->metodo_pago ?? 'efectivo')))) {
                'qr' => 'Pago QR',
                default => 'Pago de contado',
            },
            'qr_payload' => $qrPayload,
            'qr_image' => $qrImage,
            'cuf' => $cuf,
            'pdf_url' => $this->normalizeSefePublicUrl(trim((string) data_get($respuesta, 'factura.pdfUrl', ''))),
            'ips_snapshot' => $snapshot,
        ];
    }

    private function sefePublicBaseUrl(): string
    {
        return rtrim((string) config('services.facturacion_bridge.sefe_public_base_url', 'https://sefe.agetic.gob.bo'), '/');
    }

    private function sefePublicPdfUrl(string $cuf): string
    {
        return $this->sefePublicBaseUrl() . '/public/facturas_pdf/' . ltrim($cuf, '/') . '.pdf';
    }

    private function normalizeSefePublicUrl(?string $url): string
    {
        $resolvedUrl = trim((string) $url);
        if ($resolvedUrl === '') {
            return '';
        }

        $path = (string) parse_url($resolvedUrl, PHP_URL_PATH);
        if ($path === '' || !str_starts_with($path, '/public/')) {
            return $resolvedUrl;
        }

        return $this->sefePublicBaseUrl() . $path;
    }
}
