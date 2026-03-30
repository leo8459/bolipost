<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Models\FuelInvoice;
use App\Models\FuelInvoiceDetail;
use App\Models\GasStation;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use App\Models\Vehicle;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use App\Exports\FuelBitacoraExport;
use App\Exports\VehicleLogsExport;

class FuelLogController extends Controller
{
    use AuthorizesRequests;

    private const FUEL_REPORT_COLUMNS = [
        'station_name',
        'invoice_number',
        'regional',
        'fecha_carga',
        'litros',
        'importe_bs',
        'total_km',
        'placa',
        'vehiculo',
        'driver_name',
    ];

    private const VEHICLE_REPORT_COLUMNS = [
        'fecha',
        'placa',
        'vehiculo',
        'driver_name',
        'kilometraje_salida',
        'kilometraje_recorrido',
        'kilometraje_llegada',
        'recorrido',
        'combustible',
    ];

    public function index()
    {
        $this->authorize('viewAny', FuelInvoice::class);

        $fuelLogs = FuelInvoice::with(['gasStation', 'details'])
            ->latest('fecha_emision')
            ->paginate(15);

        return view('fuel-logs.index', compact('fuelLogs'));
    }

    public function create()
    {
        $this->authorize('create', FuelInvoice::class);

        $gasStations = $this->getSelectableGasStations();
        $vehicles = Vehicle::with('brand')
            ->where('activo', true)
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca_id', 'kilometraje_inicial', 'kilometraje_actual', 'kilometraje']);
        $drivers = \App\Models\Driver::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $assignments = VehicleAssignment::query()
            ->where('activo', true)
            ->orderByDesc('fecha_inicio')
            ->get(['driver_id', 'vehicle_id', 'fecha_inicio', 'fecha_fin']);

        return view('fuel-logs.create', compact('gasStations', 'vehicles', 'drivers', 'assignments'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', FuelInvoice::class);

        $validated = $request->validate([
            'numero_factura' => 'required|string|max:50|unique:fuel_invoices,numero_factura',
            // se permite cualquier fecha/hora parseable; se normaliza mÃ¡s abajo
            'fecha_emision' => 'required|date',
            'gas_station_id' => 'nullable|exists:gas_stations,id',
            'nombre_cliente' => 'required|string|max:255',
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:drivers,id',
            'kilometraje_salida' => 'required|numeric|min:0',
            'kilometraje_llegada' => 'nullable|numeric|gte:kilometraje_salida',
            'recorrido_inicio' => 'required|string|max:255',
            'recorrido_destino' => 'required|string|max:255',
            'details' => 'required|array|min:1',
            'details.*.cantidad' => 'required|numeric|min:0.00001',
            'details.*.precio_unitario' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $invoice = FuelInvoice::create([
                    'numero_factura' => $validated['numero_factura'],
                    // normalize datetime to strip seconds if present
                    'fecha_emision' => \Carbon\Carbon::parse($validated['fecha_emision'])->format('Y-m-d H:i'),
                    'nombre_cliente' => $validated['nombre_cliente'],
                    'monto_total' => 0,
                ]);

                $total = 0;
                foreach ($validated['details'] as $row) {
                    $subtotal = (float) $row['cantidad'] * (float) $row['precio_unitario'];
                    $total += $subtotal;

                    $detail = FuelInvoiceDetail::create([
                        'fuel_invoice_id' => $invoice->id,
                        'gas_station_id' => $validated['gas_station_id'] ?? null,
                        'cantidad' => $row['cantidad'],
                        'precio_unitario' => $row['precio_unitario'],
                        'subtotal' => $subtotal,
                        'estado' => 'Falta verificar',
                    ]);

                    VehicleLog::create([
                        'drivers_id' => $validated['driver_id'],
                        'vehicles_id' => $validated['vehicle_id'],
                        'fuel_log_id' => $detail->id,
                        'fecha' => \Carbon\Carbon::parse($validated['fecha_emision'])->toDateString(),
                        'kilometraje_salida' => $validated['kilometraje_salida'],
                        'kilometraje_llegada' => $validated['kilometraje_llegada'] ?? null,
                        'recorrido_inicio' => $validated['recorrido_inicio'],
                        'recorrido_destino' => $validated['recorrido_destino'],
                        'abastecimiento_combustible' => true,
                    ]);
                }

                $this->updateVehicleKilometrajeFromTrip(
                    (int) $validated['vehicle_id'],
                    isset($validated['kilometraje_salida']) ? (float) $validated['kilometraje_salida'] : null,
                    isset($validated['kilometraje_llegada']) ? (float) $validated['kilometraje_llegada'] : null
                );

                $invoice->update(['monto_total' => $total]);
            });

            return redirect()->route('fuel-logs.index')
                ->with('success', 'Factura de combustible creada exitosamente.');
        } catch (Exception $e) {
            return back()->withInput()
                ->with('error', 'Error al crear la factura: ' . $e->getMessage());
        }
    }

    public function show(FuelInvoice $fuelInvoice)
    {
        $this->authorize('view', $fuelInvoice);

        $fuelInvoice->load(['gasStation', 'details']);

        return view('fuel-logs.show', ['fuelLog' => $fuelInvoice]);
    }

    public function edit(FuelInvoice $fuelInvoice)
    {
        $this->authorize('update', $fuelInvoice);

        $fuelInvoice->load('details');
        $gasStations = $this->getSelectableGasStations();
        $vehicles = Vehicle::with('brand')
            ->where('activo', true)
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca_id', 'kilometraje_inicial', 'kilometraje_actual', 'kilometraje']);
        $drivers = \App\Models\Driver::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $assignments = VehicleAssignment::query()
            ->where('activo', true)
            ->orderByDesc('fecha_inicio')
            ->get(['driver_id', 'vehicle_id', 'fecha_inicio', 'fecha_fin']);

        // Use dedicated edit view (shares form partial with create)
        return view('fuel-logs.edit', [
            'fuelLog' => $fuelInvoice,
            'gasStations' => $gasStations,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'assignments' => $assignments,
        ]);
    }

    public function update(Request $request, FuelInvoice $fuelInvoice)
    {
        $this->authorize('update', $fuelInvoice);

        $validated = $request->validate([
            'numero_factura' => 'required|string|max:50|unique:fuel_invoices,numero_factura,' . $fuelInvoice->id,
            'fecha_emision' => 'required|date',
            'gas_station_id' => 'nullable|exists:gas_stations,id',
            'nombre_cliente' => 'required|string|max:255',
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:drivers,id',
            'kilometraje_salida' => 'required|numeric|min:0',
            'kilometraje_llegada' => 'nullable|numeric|gte:kilometraje_salida',
            'recorrido_inicio' => 'required|string|max:255',
            'recorrido_destino' => 'required|string|max:255',
            'details' => 'required|array|min:1',
            'details.*.cantidad' => 'required|numeric|min:0.00001',
            'details.*.precio_unitario' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::transaction(function () use ($validated, $fuelInvoice) {
                $fuelInvoice->update([
                    'numero_factura' => $validated['numero_factura'],
                    'fecha_emision' => \Carbon\Carbon::parse($validated['fecha_emision'])->format('Y-m-d H:i'),
                    'nombre_cliente' => $validated['nombre_cliente'],
                ]);

                $oldDetailIds = $fuelInvoice->details()->pluck('id')->all();
                if (!empty($oldDetailIds)) {
                    VehicleLog::query()->whereIn('fuel_log_id', $oldDetailIds)->delete();
                }
                $fuelInvoice->details()->delete();

                $total = 0;
                foreach ($validated['details'] as $row) {
                    $subtotal = (float) $row['cantidad'] * (float) $row['precio_unitario'];
                    $total += $subtotal;

                    $detail = FuelInvoiceDetail::create([
                        'fuel_invoice_id' => $fuelInvoice->id,
                        'gas_station_id' => $validated['gas_station_id'] ?? null,
                        'cantidad' => $row['cantidad'],
                        'precio_unitario' => $row['precio_unitario'],
                        'subtotal' => $subtotal,
                        'estado' => 'Falta verificar',
                    ]);

                    VehicleLog::create([
                        'drivers_id' => $validated['driver_id'],
                        'vehicles_id' => $validated['vehicle_id'],
                        'fuel_log_id' => $detail->id,
                        'fecha' => \Carbon\Carbon::parse($validated['fecha_emision'])->toDateString(),
                        'kilometraje_salida' => $validated['kilometraje_salida'],
                        'kilometraje_llegada' => $validated['kilometraje_llegada'] ?? null,
                        'recorrido_inicio' => $validated['recorrido_inicio'],
                        'recorrido_destino' => $validated['recorrido_destino'],
                        'abastecimiento_combustible' => true,
                    ]);
                }

                $this->updateVehicleKilometrajeFromTrip(
                    (int) $validated['vehicle_id'],
                    isset($validated['kilometraje_salida']) ? (float) $validated['kilometraje_salida'] : null,
                    isset($validated['kilometraje_llegada']) ? (float) $validated['kilometraje_llegada'] : null
                );

                $fuelInvoice->update(['monto_total' => $total]);
            });

            return redirect()->route('fuel-logs.show', $fuelInvoice)
                ->with('success', 'Factura de combustible actualizada exitosamente.');
        } catch (Exception $e) {
            return back()->withInput()
                ->with('error', 'Error al actualizar la factura: ' . $e->getMessage());
        }
    }

    public function destroy(FuelInvoice $fuelInvoice)
    {
        $this->authorize('delete', $fuelInvoice);

        try {
            $fuelInvoice->delete();

            return redirect()->route('fuel-logs.index')
                ->with('success', 'Factura eliminada exitosamente.');
        } catch (Exception $e) {
            return back()->with('error', 'Error al eliminar la factura: ' . $e->getMessage());
        }
    }

    public function byVehicle(Vehicle $vehicle)
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);

        $fuelLogs = $vehicle->fuelLogs()
            ->with('driver')
            ->latest('created_at')
            ->get();

        return response()->json($fuelLogs);
    }

    public function bitacoraPdf(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'recepcion'], true), 403);

        $reportData = $this->buildFuelBitacoraReportData($request);

        $html = view('reports.fuel-log-bitacora-pdf', [
            ...$reportData,
            'generatedAt' => now(),
            'reportMode' => 'fuel_bitacora',
        ])->render();

        $download = $request->boolean('download');

        return $this->renderBitacoraPdfResponse(
            $html,
            'bitacora-combustible-' . now()->format('Ymd-His') . '.pdf',
            $download,
            'bitacora'
        );
    }

    public function bitacoraExcel(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'recepcion'], true), 403);

        $reportData = $this->buildFuelBitacoraReportData($request);

        return Excel::download(
            new FuelBitacoraExport($reportData),
            'planilla-combustible-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function vehicleLogsPdf(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'recepcion'], true), 403);

        $reportData = $this->buildVehicleLogsReportData($request);

        $html = view('reports.fuel-log-bitacora-pdf', [
            ...$reportData,
            'generatedAt' => now(),
            'reportMode' => 'vehicle_logs',
        ])->render();

        $download = $request->boolean('download');

        return $this->renderBitacoraPdfResponse(
            $html,
            'bitacora-vehicular-' . now()->format('Ymd-His') . '.pdf',
            $download,
            'bitacora vehicular'
        );
    }

    public function vehicleLogsExcel(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'recepcion'], true), 403);

        $reportData = $this->buildVehicleLogsReportData($request);

        return Excel::download(
            new VehicleLogsExport($reportData),
            'bitacora-vehicular-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    private function renderBitacoraPdfResponse(string $html, string $filename, bool $download, string $context)
    {
        $disposition = $download ? 'attachment' : 'inline';

        try {
            $pdf = Browsershot::html($html)
                ->format('A4')
                ->landscape()
                ->showBackground()
                ->margins(6, 6, 6, 6)
                ->waitUntilNetworkIdle()
                ->pdf();

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::warning("No se pudo generar PDF de {$context} con Browsershot. Se usa fallback dompdf.", [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $pdf = Pdf::loadHTML($html)->setPaper('A4', 'landscape')->output();

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error("No se pudo generar PDF de {$context} con dompdf fallback.", [
                'error' => $e->getMessage(),
            ]);

            if ($download) {
                return response()->json([
                    'message' => 'No se pudo generar el PDF en este momento.',
                ], 500);
            }

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }
    }

    private function buildFuelBitacoraReportData(Request $request): array
    {
        $user = $request->user();
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');
        $placaFiltro = mb_strtoupper(trim((string) $request->query('placa_filtro', '')));
        $vehicleId = (int) $request->query('vehicle_id', 0);
        $driverId = (int) $request->query('driver_id', 0);
        $visibleColumns = $this->normalizeReportColumns(
            $request->query('columns'),
            self::FUEL_REPORT_COLUMNS
        );

        $query = VehicleLog::query()
            ->with(['vehicle.brand', 'driver', 'fuelLog.invoice', 'fuelLog.gasStation'])
            ->where('abastecimiento_combustible', true)
            ->whereNotNull('fuel_log_id')
            ->orderBy('fecha')
            ->orderBy('id');

        if ($user?->role === 'conductor') {
            $driverId = (int) ($user->resolvedDriver()?->id ?? 0);
            if ($driverId <= 0) {
                abort(403);
            }
            $query->where('drivers_id', $driverId);
        }

        if ($placaFiltro !== '') {
            $query->whereHas('vehicle', function ($q) use ($placaFiltro) {
                $q->whereRaw('UPPER(placa) LIKE ?', ['%' . $placaFiltro . '%']);
            });
        }

        if ($vehicleId > 0) {
            $query->where('vehicles_id', $vehicleId);
        }

        if ($driverId > 0) {
            $query->where('drivers_id', $driverId);
        }

        if ($fechaDesde) {
            $query->whereDate('fecha', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('fecha', '<=', $fechaHasta);
        }

        $rows = $query->get()->map(function (VehicleLog $row) {
            $fuel = $row->fuelLog;
            $kmSalida = $row->kilometraje_salida !== null ? (float) $row->kilometraje_salida : null;
            $kmLlegada = $row->kilometraje_llegada !== null ? (float) $row->kilometraje_llegada : null;
            $totalKm = ($kmSalida !== null && $kmLlegada !== null) ? max(0, $kmLlegada - $kmSalida) : null;

            return [
                'station_name' => trim((string) ($fuel?->gasStation?->razon_social ?? $fuel?->gasStation?->nombre ?? 'SIN ESTACION')),
                'invoice_number' => trim((string) ($fuel?->invoice?->numero_factura ?? $fuel?->invoice?->numero ?? '')),
                'regional' => trim((string) ($fuel?->gasStation?->ciudad ?? '-')),
                'fecha_carga' => optional($fuel?->fecha_emision)->format('j/n/Y')
                    ?? optional($row->fecha)->format('j/n/Y')
                    ?? '-',
                'litros' => $fuel?->cantidad !== null ? (float) $fuel->cantidad : (float) ($fuel?->galones ?? 0),
                'importe_bs' => $fuel?->subtotal !== null ? (float) $fuel->subtotal : (float) ($fuel?->invoice?->monto_total ?? 0),
                'total_km' => $totalKm,
                'placa' => (string) ($row->vehicle?->placa ?? 'SIN PLACA'),
                'vehiculo' => trim((string) (($row->vehicle?->brand?->nombre ?? '') . ' ' . ($row->vehicle?->modelo ?? ''))),
                'driver_name' => trim((string) ($row->driver?->nombre ?? 'SIN CONDUCTOR')),
            ];
        })->values();

        return [
            'rows' => $rows,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'placaFiltro' => $placaFiltro,
            'visibleColumns' => $visibleColumns,
            'selectedVehicleLabel' => $vehicleId > 0 ? (string) (Vehicle::query()->whereKey($vehicleId)->value('placa') ?? '') : '',
            'selectedDriverLabel' => $driverId > 0 ? (string) (\App\Models\Driver::withTrashed()->whereKey($driverId)->value('nombre') ?? '') : '',
            'totals' => [
                'litros' => (float) $rows->sum('litros'),
                'importe_bs' => (float) $rows->sum('importe_bs'),
                'total_km' => (float) $rows->sum(fn (array $row) => (float) ($row['total_km'] ?? 0)),
            ],
            // Compatibilidad con la otra salida del mismo blade.
            'groups' => collect(),
        ];
    }

    private function buildVehicleLogsReportData(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');
        $vehicleId = (int) $request->query('vehicle_id', 0);
        $driverId = (int) $request->query('driver_id', 0);
        $visibleColumns = $this->normalizeReportColumns(
            $request->query('columns'),
            self::VEHICLE_REPORT_COLUMNS
        );

        $query = VehicleLog::query()
            ->with(['vehicle.brand', 'driver', 'fuelLog'])
            ->orderBy('fecha')
            ->orderBy('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('recorrido_inicio', 'like', '%' . $search . '%')
                    ->orWhere('recorrido_destino', 'like', '%' . $search . '%')
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('placa', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('driver', function ($driverQuery) use ($search) {
                        $driverQuery->where('nombre', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($fechaDesde) {
            $query->whereDate('fecha', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('fecha', '<=', $fechaHasta);
        }

        if ($vehicleId > 0) {
            $query->where('vehicles_id', $vehicleId);
        }

        if ($driverId > 0) {
            $query->where('drivers_id', $driverId);
        }

        $logs = $query->get();

        $groups = $logs
            ->groupBy(fn (VehicleLog $log) => (int) ($log->vehicles_id ?? 0))
            ->map(function ($rows) {
                $rows = $rows->sortBy('fecha')->values();
                $vehicle = $rows->first()?->vehicle;
                $startDriver = $rows->first()?->driver;
                $driversUsed = $rows
                    ->map(fn (VehicleLog $row) => trim((string) ($row->driver?->nombre ?? '')))
                    ->filter(fn (string $name) => $name !== '')
                    ->unique()
                    ->values();
                $rowChunks = $rows->chunk(12)->values();

                return [
                    'vehicle' => $vehicle,
                    'driver' => $startDriver,
                    'drivers_used' => $driversUsed,
                    'rows' => $rows->values(),
                    'row_chunks' => $rowChunks,
                ];
            })
            ->sortBy(fn (array $group) => (string) ($group['vehicle']?->placa ?? ''))
            ->values();

        $rows = $logs->map(function (VehicleLog $row) {
            $kmSalida = $row->kilometraje_salida !== null ? (float) $row->kilometraje_salida : null;
            $kmLlegada = $row->kilometraje_llegada !== null ? (float) $row->kilometraje_llegada : null;
            $kmRecorrido = $row->kilometraje_recorrido !== null
                ? (float) $row->kilometraje_recorrido
                : (($kmSalida !== null && $kmLlegada !== null) ? max(0, $kmLlegada - $kmSalida) : null);
            $fuelLabel = 'No';
            if ($row->fuel_log_id) {
                $litros = $row->fuelLog?->cantidad ?? $row->fuelLog?->galones;
                $fuelLabel = $litros !== null ? 'Si - ' . number_format((float) $litros, 2) . ' L' : 'Si';
            }

            return [
                'fecha' => optional($row->fecha)->format('d/m/Y') ?? '-',
                'placa' => (string) ($row->vehicle?->placa ?? 'SIN PLACA'),
                'vehiculo' => trim((string) (($row->vehicle?->brand?->nombre ?? '') . ' ' . ($row->vehicle?->modelo ?? ''))),
                'driver_name' => trim((string) ($row->driver?->nombre ?? 'SIN CONDUCTOR')),
                'kilometraje_salida' => $kmSalida,
                'kilometraje_recorrido' => $kmRecorrido,
                'kilometraje_llegada' => $kmLlegada,
                'recorrido' => trim(((string) ($row->recorrido_inicio ?? '-')) . ' -> ' . ((string) ($row->recorrido_destino ?? '-'))),
                'recorrido_inicio' => (string) ($row->recorrido_inicio ?? ''),
                'recorrido_destino' => (string) ($row->recorrido_destino ?? ''),
                'combustible' => $fuelLabel,
            ];
        })->values();

        return [
            'groups' => $groups,
            'rows' => $rows,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'placaFiltro' => $search,
            'visibleColumns' => $visibleColumns,
            'selectedVehicleLabel' => $vehicleId > 0 ? (string) (Vehicle::query()->whereKey($vehicleId)->value('placa') ?? '') : '',
            'selectedDriverLabel' => $driverId > 0 ? (string) (\App\Models\Driver::withTrashed()->whereKey($driverId)->value('nombre') ?? '') : '',
            'totals' => [
                'kilometraje_recorrido' => (float) $rows->sum(fn (array $row) => (float) ($row['kilometraje_recorrido'] ?? 0)),
            ],
        ];
    }

    /**
     * @param mixed $requestedColumns
     * @param array<int, string> $allowedColumns
     * @return array<int, string>
     */
    private function normalizeReportColumns(mixed $requestedColumns, array $allowedColumns): array
    {
        $columns = is_array($requestedColumns)
            ? $requestedColumns
            : (is_string($requestedColumns) ? explode(',', $requestedColumns) : []);

        $normalized = collect($columns)
            ->map(fn ($column) => trim((string) $column))
            ->filter(fn (string $column) => in_array($column, $allowedColumns, true))
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $allowedColumns;
    }

    public function scrapeFromQr(Request $request)
    {
        if (!$request->is('api/*')) {
            abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);
        }
        @set_time_limit(45);

        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $url = $validated['url'];

        if (!Str::startsWith($url, ['http://', 'https://'])) {
            return response()->json(['message' => 'URL no permitida.'], 422);
        }

        $data = [
            'numero_factura' => null,
            'fecha_emision' => null,
            'nombre_cliente' => null,
            'monto_total' => null,
            'gas_station_id' => null,
            'razonSocialEmisor' => null,
            'direccion' => null,
            'cantidad' => null,
            'details' => [],
        ];

        $parts = parse_url($url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $pick = function (array $source, array $keys): ?string {
            foreach ($keys as $k) {
                if (array_key_exists($k, $source) && trim((string) $source[$k]) !== '') {
                    return trim((string) $source[$k]);
                }
            }
            return null;
        };

        $queryFactura = $pick($query, ['numero_factura', 'nro_factura', 'nrofactura', 'factura', 'numero', 'nf', 'numeroFactura', 'nro']);
        $queryFecha = $pick($query, ['fecha_emision', 'fecha', 'date', 'fechaEmision']);
        $queryCliente = $pick($query, ['nombre_cliente', 'cliente', 'nombre', 'razon_social_cliente', 'razonSocial']);
        $queryTotal = $pick($query, ['monto_total', 'total', 'importe', 'monto', 'montoTotal']);
        $queryCantidad = $pick($query, ['cantidad', 'litros', 'volumen']);
        $queryPrecio = $pick($query, ['precio_unitario', 'precio', 'pu', 'precioUnitario']);
        $queryNit = $pick($query, ['nit_emisor', 'nit', 'emisor_nit', 'nitEmisor']);
        $queryCuf = $pick($query, ['cuf', 'codigo_unico_factura']);
        $queryRazonSocial = $pick($query, ['razon_social', 'nombre_estacion', 'razonSocialEmisor']);
        $queryDireccion = $pick($query, ['direccion', 'direccionEstacion']);

        $parseFecha = function (?string $value): ?string {
            if (!$value) {
                return null;
            }
            $raw = trim($value);
            $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
            foreach ($formats as $format) {
                try {
                    return \Carbon\Carbon::createFromFormat($format, $raw)->format('Y-m-d H:i');
                } catch (\Throwable $e) {
                }
            }
            try {
                return \Carbon\Carbon::parse(str_replace('/', '-', $raw))->format('Y-m-d H:i');
            } catch (\Throwable $e) {
                return null;
            }
        };

        $normalizeNumber = function (?string $value): ?float {
            if ($value === null) {
                return null;
            }
            $clean = preg_replace('/[^0-9,\.\-]/', '', $value);
            if ($clean === '') {
                return null;
            }
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');
            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    $clean = str_replace('.', '', $clean);
                    $clean = str_replace(',', '.', $clean);
                } else {
                    $clean = str_replace(',', '', $clean);
                }
            } elseif ($lastComma !== false) {
                $clean = str_replace(',', '.', $clean);
            }
            return is_numeric($clean) ? (float) $clean : null;
        };

        // Fallback parcial desde query del QR (solo para mostrar algo antes de SIAT).
        $data['numero_factura'] = $queryFactura;
        $data['nombre_cliente'] = $queryCliente;
        $data['monto_total'] = $normalizeNumber($queryTotal);
        $data['fecha_emision'] = $parseFecha($queryFecha);
        $data['razonSocialEmisor'] = $queryRazonSocial;
        $data['direccion'] = $queryDireccion;
        $data['cantidad'] = $normalizeNumber($queryCantidad);

        // Algunos QR llevan payload codificado.
        $encodedPayload = $this->extractFromEncodedQueryPayloads($query);
        $queryFactura = $queryFactura ?: ($encodedPayload['numero_factura'] ?? null);
        $queryNit = $queryNit ?: ($encodedPayload['nit_emisor'] ?? null);
        $queryCuf = $queryCuf ?: ($pick($query, ['cuf', 'codigo_unico_factura']) ?? null);

        if (!$data['numero_factura']) {
            $data['numero_factura'] = $encodedPayload['numero_factura'] ?? null;
        }
        if (!$data['nombre_cliente']) {
            $data['nombre_cliente'] = $encodedPayload['nombre_cliente'] ?? null;
        }
        if (!$data['fecha_emision']) {
            $data['fecha_emision'] = $parseFecha($encodedPayload['fecha_emision'] ?? null);
        }
        if (!$data['monto_total']) {
            $data['monto_total'] = $normalizeNumber($encodedPayload['monto_total'] ?? null);
        }
        if (!$data['razonSocialEmisor']) {
            $data['razonSocialEmisor'] = $encodedPayload['razon_social'] ?? null;
        }
        if (!$data['direccion']) {
            $data['direccion'] = $encodedPayload['direccion'] ?? null;
        }
        if (!$data['cantidad']) {
            $data['cantidad'] = $normalizeNumber($encodedPayload['cantidad'] ?? null);
        }

        $persistStations = !$request->is('api/*');
        $upsertStationByNit = function (?string $nit, ?string $razonSocial = null, ?string $direccion = null) use ($persistStations): ?int {
            if (!$nit || !Schema::hasColumn('gas_stations', 'nit_emisor')) {
                return null;
            }

            $station = GasStation::query()->where('nit_emisor', $nit)->first();
            if ($station) {
                $updates = [];
                if ($razonSocial && Schema::hasColumn('gas_stations', 'razon_social')) {
                    $updates['razon_social'] = $razonSocial;
                }
                if ($direccion && Schema::hasColumn('gas_stations', 'direccion')) {
                    $updates['direccion'] = $direccion;
                }
                if (!empty($updates)) {
                    $station->fill($updates)->save();
                }
                return $station->id;
            }

            if (!$persistStations) {
                return null;
            }

            $label = $razonSocial ?: ('NIT ' . $nit);
            $create = ['nit_emisor' => $nit];
            if (Schema::hasColumn('gas_stations', 'razon_social')) {
                $create['razon_social'] = $label;
            }
            if ($direccion && Schema::hasColumn('gas_stations', 'direccion')) {
                $create['direccion'] = $direccion;
            }
            if (Schema::hasColumn('gas_stations', 'nombre')) {
                $create['nombre'] = $label;
            }
            if (Schema::hasColumn('gas_stations', 'ubicacion')) {
                $create['ubicacion'] = $direccion ?: 'SIN DIRECCION';
            }
            if (Schema::hasColumn('gas_stations', 'activa')) {
                $create['activa'] = true;
            }

            return GasStation::query()->create($create)->id;
        };

        $data['gas_station_id'] = $upsertStationByNit($queryNit, $queryRazonSocial, $queryDireccion);

        $cantidad = $normalizeNumber($queryCantidad);
        $precio = $normalizeNumber($queryPrecio);
        if ($cantidad && $precio) {
            $data['details'][] = [
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => round($cantidad * $precio, 2),
            ];
        }

        // Consumo directo del endpoint REST SIAT.
        $siatFactura = $this->getFacturaSiat($queryNit, $queryCuf, $data['numero_factura'] ?? $queryFactura);
        if (is_array($siatFactura)) {
            $siatNumero = data_get($siatFactura, 'numeroFactura')
                ?? data_get($siatFactura, 'cabecera.numeroFactura');
            $siatFecha = data_get($siatFactura, 'fechaEmision')
                ?? data_get($siatFactura, 'cabecera.fechaEmision')
                ?? data_get($siatFactura, 'fecha');
            $siatCliente = data_get($siatFactura, 'nombreRazonSocial')
                ?? data_get($siatFactura, 'nombreCliente')
                ?? data_get($siatFactura, 'cabecera.nombreRazonSocial');
            $siatTotal = data_get($siatFactura, 'montoTotal')
                ?? data_get($siatFactura, 'cabecera.montoTotal')
                ?? data_get($siatFactura, 'total');
            $siatNit = data_get($siatFactura, 'nitEmisor')
                ?? data_get($siatFactura, 'cabecera.nitEmisor');
            $siatRazonSocial = data_get($siatFactura, 'razonSocialEmisor')
                ?? data_get($siatFactura, 'cabecera.razonSocialEmisor');
            $siatDireccion = data_get($siatFactura, 'direccion')
                ?? data_get($siatFactura, 'cabecera.direccion');

            $data['numero_factura'] = $data['numero_factura'] ?? ($siatNumero ? (string) $siatNumero : null);
            $data['fecha_emision'] = $data['fecha_emision'] ?? $parseFecha($siatFecha ? (string) $siatFecha : null);
            $data['nombre_cliente'] = $data['nombre_cliente'] ?? ($siatCliente ? (string) $siatCliente : null);
            $data['monto_total'] = $data['monto_total'] ?? $normalizeNumber($siatTotal !== null ? (string) $siatTotal : null);
            $data['razonSocialEmisor'] = $data['razonSocialEmisor'] ?? ($siatRazonSocial ? (string) $siatRazonSocial : null);
            $data['direccion'] = $data['direccion'] ?? ($siatDireccion ? (string) $siatDireccion : null);
            if (!$data['gas_station_id'] && $siatNit) {
                $data['gas_station_id'] = $upsertStationByNit((string) $siatNit, $siatRazonSocial, $siatDireccion);
            }

            if (empty($data['details'])) {
                // The SIAT response sometimes uses different keys for the detail lines
                $siatDetalles = data_get($siatFactura, 'detalles')
                    ?? data_get($siatFactura, 'detalle')
                    ?? data_get($siatFactura, 'listaDetalle')        // <----- new
                    ?? data_get($siatFactura, 'lista_detalle')
                    ?? data_get($siatFactura, 'cabecera.detalles')
                    ?? [];

                if (is_array($siatDetalles)) {
                    foreach ($siatDetalles as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $cantidadRow = $normalizeNumber((string) (data_get($row, 'cantidad')
                            ?? data_get($row, 'cantidadProducto')
                            ?? ''));
                        $precioRow = $normalizeNumber((string) (data_get($row, 'precioUnitario')
                            ?? data_get($row, 'precio_unitario')
                            ?? data_get($row, 'precio')
                            ?? ''));
                        $subtotalRow = $normalizeNumber((string) (data_get($row, 'subTotal')
                            ?? data_get($row, 'subtotal')
                            ?? ''));

                        if (!$cantidadRow && !$precioRow && !$subtotalRow) {
                            continue;
                        }
                        if (!$subtotalRow && $cantidadRow && $precioRow) {
                            $subtotalRow = round($cantidadRow * $precioRow, 2);
                        }

                        $data['details'][] = [
                            'cantidad' => $cantidadRow ?: 1,
                            'precio_unitario' => $precioRow ?: ($subtotalRow ?: 0),
                            'subtotal' => $subtotalRow ?: (($cantidadRow ?: 1) * ($precioRow ?: 0)),
                        ];
                    }
                }
            }

            if (!$data['monto_total'] && !empty($data['details'])) {
                $data['monto_total'] = (float) collect($data['details'])->sum(function ($row) {
                    return (float) ($row['subtotal'] ?? 0);
                });
            }
            if (!$data['cantidad'] && !empty($data['details'])) {
                $data['cantidad'] = (float) ($data['details'][0]['cantidad'] ?? 0);
            }
            $data['galones'] = $data['cantidad'] ?? null;
            $data['precio_galon'] = $data['precio_unitario'] ?? null;
            $data['total_calculado'] = $data['monto_total'] ?? null;

            return response()->json([
                'success' => true,
                'message' => 'Datos obtenidos de SIAT REST.',
                'data' => $data,
            ]);
        }

        // Flujo exclusivo REST: sin scraping HTML.
        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener la factura desde SIAT REST. Verifica nitEmisor, cuf y numeroFactura en el QR.',
            'data' => $data,
        ], 422);
    }

    private function getFacturaSiat(?string $nitEmisor, ?string $cuf, ?string $numeroFactura): ?array
    {
        $nit = $nitEmisor ? preg_replace('/\D+/', '', $nitEmisor) : null;
        $numero = $numeroFactura ? preg_replace('/\D+/', '', (string) $numeroFactura) : null;

        if (!$nit || !$cuf || !$numero) {
            return null;
        }

        $payload = [
            'nitEmisor' => $nit,
            'cuf' => trim($cuf),
            'numeroFactura' => $numero,
        ];

        try {
            $verifySsl = filter_var(env('SIAT_VERIFY_SSL', false), FILTER_VALIDATE_BOOL);
            $response = Http::timeout(20)
                ->retry(2, 350)
                ->withOptions(['verify' => $verifySsl])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/plain, */*',
                    'Origin' => 'https://siat.impuestos.gob.bo',
                    'Referer' => 'https://siat.impuestos.gob.bo/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                ])
                ->put('https://siatrest.impuestos.gob.bo/sre-sfe-shared-v2-rest/consulta/factura', $payload);

            if (!$response->ok()) {
                Log::warning('SIAT REST consulta/factura no exitoso', [
                    'status' => $response->status(),
                    'payload' => $payload,
                    'body' => Str::limit($response->body(), 500),
                ]);

                return null;
            }

            $json = $response->json();
            if (!is_array($json)) {
                return null;
            }

            // SIAT suele indicar éxito en "transaccion".
            if (array_key_exists('transaccion', $json) && $json['transaccion'] === false) {
                Log::warning('SIAT REST transaccion=false', [
                    'payload' => $payload,
                    'body' => Str::limit($response->body(), 500),
                ]);
                return null;
            }

            $objeto = data_get($json, 'objeto');
            if (is_array($objeto)) {
                return $objeto;
            }

            $objeto = data_get($json, 'data.objeto');
            return is_array($objeto) ? $objeto : null;
        } catch (\Throwable $e) {
            Log::warning('Error consumiendo SIAT REST consulta/factura', [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractFromEncodedQueryPayloads(array $query): array
    {
        $result = [
            'numero_factura' => null,
            'fecha_emision' => null,
            'nombre_cliente' => null,
            'monto_total' => null,
            'nit_emisor' => null,
            'razon_social' => null,
            'direccion' => null,
            'cantidad' => null,
            'precio_unitario' => null,
        ];

        $pick = function (array $source, array $keys): ?string {
            foreach ($keys as $k) {
                if (array_key_exists($k, $source) && trim((string) $source[$k]) !== '') {
                    return trim((string) $source[$k]);
                }
            }
            return null;
        };

        $mergeKnownKeys = function (array $payload) use (&$result, $pick) {
            $result['numero_factura'] = $result['numero_factura'] ?: $pick($payload, ['numero_factura', 'nro_factura', 'numeroFactura', 'factura', 'numero', 'nf']);
            $result['fecha_emision'] = $result['fecha_emision'] ?: $pick($payload, ['fecha_emision', 'fecha', 'fechaEmision']);
            $result['nombre_cliente'] = $result['nombre_cliente'] ?: $pick($payload, ['nombre_cliente', 'cliente', 'nombre', 'razonSocial']);
            $result['monto_total'] = $result['monto_total'] ?: $pick($payload, ['monto_total', 'montoTotal', 'total', 'importe']);
            $result['nit_emisor'] = $result['nit_emisor'] ?: $pick($payload, ['nit_emisor', 'nit', 'nitEmisor']);
            $result['razon_social'] = $result['razon_social'] ?: $pick($payload, ['razon_social', 'razonSocial', 'nombre_estacion']);
            $result['direccion'] = $result['direccion'] ?: $pick($payload, ['direccion']);
            $result['cantidad'] = $result['cantidad'] ?: $pick($payload, ['cantidad', 'litros', 'volumen']);
            $result['precio_unitario'] = $result['precio_unitario'] ?: $pick($payload, ['precio_unitario', 'precioUnitario', 'precio', 'pu']);
        };

        foreach ($query as $value) {
            $raw = trim((string) $value);
            if ($raw === '' || strlen($raw) < 16) {
                continue;
            }

            $decoded = urldecode($raw);
            if (str_contains($decoded, '.') && substr_count($decoded, '.') === 2) {
                // JWT payload
                $parts = explode('.', $decoded);
                $payload = $parts[1] ?? '';
                $payload = str_replace(['-', '_'], ['+', '/'], $payload);
                $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
                $json = base64_decode($payload, true);
                if ($json) {
                    $arr = json_decode($json, true);
                    if (is_array($arr)) {
                        $mergeKnownKeys($arr);
                    }
                }
            }

            $b64 = base64_decode($decoded, true);
            if ($b64 !== false && $b64 !== '') {
                $arr = json_decode($b64, true);
                if (is_array($arr)) {
                    $mergeKnownKeys($arr);
                } else {
                    $pairs = [];
                    parse_str($b64, $pairs);
                    if (!empty($pairs)) {
                        $mergeKnownKeys($pairs);
                    }
                }
            }

            $pairs = [];
            parse_str($decoded, $pairs);
            if (!empty($pairs)) {
                $mergeKnownKeys($pairs);
            }
        }

        return $result;
    }


    private function getSelectableGasStations()
    {
        $query = GasStation::query();

        if (Schema::hasColumn('gas_stations', 'activa')) {
            $query->where('activa', true);
        }

        if (Schema::hasColumn('gas_stations', 'razon_social') && Schema::hasColumn('gas_stations', 'nombre')) {
            $query->orderByRaw('COALESCE(razon_social, nombre) asc');
        } elseif (Schema::hasColumn('gas_stations', 'razon_social')) {
            $query->orderBy('razon_social');
        } elseif (Schema::hasColumn('gas_stations', 'nombre')) {
            $query->orderBy('nombre');
        } else {
            $query->orderBy('id');
        }

        return $query->get();
    }

    private function updateVehicleKilometrajeFromTrip(int $vehicleId, ?float $kmSalida, ?float $kmLlegada): void
    {
        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle) {
            return;
        }

        $kmActual = $kmLlegada ?? $kmSalida;
        if ($kmActual === null) {
            return;
        }

        $hasInicial = Schema::hasColumn('vehicles', 'kilometraje_inicial');
        $hasActual = Schema::hasColumn('vehicles', 'kilometraje_actual');
        $hasLegacy = Schema::hasColumn('vehicles', 'kilometraje');

        $updates = [];

        if ($hasInicial && $kmSalida !== null && $vehicle->kilometraje_inicial === null) {
            $updates['kilometraje_inicial'] = $kmSalida;
        }

        if ($hasActual) {
            $prev = $vehicle->kilometraje_actual !== null ? (float) $vehicle->kilometraje_actual : null;
            if ($prev === null || $kmActual >= $prev) {
                $updates['kilometraje_actual'] = $kmActual;
            }
        }

        if ($hasLegacy) {
            $prevLegacy = $vehicle->kilometraje !== null ? (float) $vehicle->kilometraje : null;
            if ($prevLegacy === null || $kmActual >= $prevLegacy) {
                $updates['kilometraje'] = $kmActual;
            }
        }

        if (!empty($updates)) {
            $vehicle->update($updates);
        }
    }
}






