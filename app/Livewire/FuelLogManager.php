<?php

namespace App\Livewire;

use App\Models\FuelLog;
use App\Models\FuelInvoice;
use App\Models\FuelAntifraudCase;
use App\Models\GasStation;
use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use App\Models\VehicleLogStageEvent;
use App\Services\FuelInvoiceDocumentService;
use App\Services\MaintenanceAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use Livewire\Attributes\Validate;

class FuelLogManager extends Component
{
    use WithPagination;
    use WithFileUploads;
    use WithoutUrlPagination;

    protected string $paginationTheme = 'bootstrap';

    // invoice header fields
    #[Validate('nullable|exists:gas_stations,id')]
    public ?int $gas_station_id = null;

    #[Validate('required|string|max:255')]
    public string $numero_factura = '';

    #[Validate('required|string|max:255')]
    public string $nombre_cliente = '';

    #[Validate('required|exists:vehicles,id')]
    public ?int $vehicle_id = null;

    #[Validate('required|exists:drivers,id')]
    public ?int $driver_id = null;

    // detail fields (alias compat)
    #[Validate('required|date')]
    public ?string $fecha_emision = null;

    #[Validate('required|numeric|min:0')]
    public ?float $galones = null;

    #[Validate('required|numeric|min:0')]
    public ?float $precio_galon = null;

    public ?float $total_calculado = null;

    #[Validate('nullable|numeric|min:0')]
    public ?float $kilometraje = null;

    #[Validate('required|numeric|min:0')]
    public ?float $kilometraje_salida = null;

    public ?float $kilometraje_llegada = null;

    #[Validate('required|string|max:255')]
    public string $recorrido_inicio = '';
    public ?float $latitud_inicio = null;
    public ?float $logitud_inicio = null;

    #[Validate('required|string|max:255')]
    public string $recorrido_destino = '';
    public ?float $latitud_destino = null;
    public ?float $logitud_destino = null;

    public string $recibo = '';
    public string $qr_url = '';
    public ?float $cantidad_combustible = null;
    public string $razon_social_emisor = '';
    public string $nit_emisor = '';
    public string $direccion_emisor = '';
    public array $scannedInvoicePreview = [];
    public ?string $scannedInvoiceDocumentUrl = null;
    public ?string $scannedInvoiceRolloDocumentUrl = null;
    public ?string $scannedInvoiceSourceUrl = null;
    public ?string $invoicePhotoUrl = null;
    public $invoice_photo_file = null;

    public bool $isEdit = false;
    public ?int $editingLogId = null;
    public bool $isBitacoraEdit = false;
    public ?int $editingBitacoraId = null;
    public bool $showForm = false;
    public bool $showAntiFraudTable = false;
    public bool $showFraudReviewModal = false;
    public array $fraudReview = [];
    public string $formView = 'fuel';
    public string $tableView = 'fuel';
    public string $search = '';
    public ?string $fecha_desde = null;
    public ?string $fecha_hasta = null;
    public string $placa_filtro = '';
    public ?int $vehicle_filter_id = null;
    public ?int $driver_filter_id = null;
    public string $driverAssignmentMessage = '';
    public bool $driverAssigned = true;
    private bool $syncingAssignment = false;

    public $vehicles = [];
    public $drivers = [];
    public array $vehicleKmMap = [];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);
        $today = now()->toDateString();
        $this->fecha_desde = $today;
        $this->fecha_hasta = $today;
    }

    public function render()
    {
        if (auth()->user()?->role === 'conductor') {
            $driverId = (int) (auth()->user()->resolvedDriver()?->id ?? 0);
            if (!$driverId) {
                $this->vehicles = [];
                $this->vehicleKmMap = [];
                $this->drivers = [];
            }
            $vehicleIds = $this->allowedVehicleIdsForDriver($driverId);

            $vehicleRows = \App\Models\Vehicle::whereIn('id', $vehicleIds)
                ->orderBy('placa')
                ->get(['id', 'placa', 'kilometraje_actual']);

            $this->vehicles = $vehicleRows->pluck('placa', 'id')->toArray();
            $this->vehicleKmMap = $vehicleRows
                ->mapWithKeys(fn ($v) => [(int) $v->id => $v->kilometraje_actual !== null ? (float) $v->kilometraje_actual : null])
                ->toArray();
            $this->drivers = \App\Models\Driver::withTrashed()->whereKey($driverId)->pluck('nombre', 'id')->toArray();
            $this->driver_id = $driverId ?: null;

            if ($this->vehicle_id && !in_array((int) $this->vehicle_id, $vehicleIds, true)) {
                $this->vehicle_id = null;
            }
        } else {
            $vehicleRows = \App\Models\Vehicle::query()
                ->where('activo', true)
                ->orderBy('placa')
                ->get(['id', 'placa', 'kilometraje_actual']);
            $this->vehicles = $vehicleRows->pluck('placa', 'id')->toArray();
            $this->vehicleKmMap = $vehicleRows
                ->mapWithKeys(fn ($v) => [(int) $v->id => $v->kilometraje_actual !== null ? (float) $v->kilometraje_actual : null])
                ->toArray();
            $this->drivers = \App\Models\Driver::withTrashed()
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray();

            if (empty($this->drivers)) {
                $date = $this->assignmentDate();
                $activeAssignments = VehicleAssignment::query()
                    ->where('activo', true)
                    ->whereNotNull('driver_id')
                    ->where(function ($q) use ($date) {
                        $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $date);
                    })
                    ->where(function ($q) use ($date) {
                        $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $date);
                    });

                $driverIds = (clone $activeAssignments)
                    ->pluck('driver_id')
                    ->unique()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $this->drivers = \App\Models\Driver::withTrashed()
                    ->whereIn('id', $driverIds)
                    ->orderBy('nombre')
                    ->pluck('nombre', 'id')
                    ->toArray();
            }
        }

        $this->ensureVehicleInOptions($this->vehicle_id);
        $this->ensureDriverInOptions($this->driver_id);

        $query = FuelLog::query()
            ->active()
            ->with(['invoice', 'vehicle.brand', 'vehicle.vehicleClass', 'driver', 'vehicleLog.stageEvents'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (auth()->user()?->role === 'conductor') {
            $driver = auth()->user()->resolvedDriver();
            if (!$driver) {
                $query->whereRaw('1=0');
            } else {
                $ids = $driver
                    ->vehicleLogs()
                    ->whereNotNull('fuel_log_id')
                    ->pluck('fuel_log_id')
                    ->unique()
                    ->filter()
                    ->toArray();

                $query->whereIn('id', $ids);
                $this->driver_filter_id = (int) $driver->id;
            }
        }

        if ($this->vehicle_filter_id) {
            $query->whereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->whereKey((int) $this->vehicle_filter_id));
        }

        if ($this->driver_filter_id) {
            $query->whereHas('driver', fn ($driverQuery) => $driverQuery->whereKey((int) $this->driver_filter_id));
        }

        $placaFiltro = mb_strtoupper(trim($this->placa_filtro));
        if ($placaFiltro !== '') {
            $query->whereHas('vehicle', function ($q) use ($placaFiltro) {
                $q->whereRaw('UPPER(placa) LIKE ?', ['%' . $placaFiltro . '%']);
            });
        }

        if ($this->fecha_desde || $this->fecha_hasta) {
            $query->where(function ($main) {
                $main->whereHas('invoice', function ($invoiceQ) {
                    if ($this->fecha_desde) {
                        $invoiceQ->whereDate('fecha_emision', '>=', $this->fecha_desde);
                    }
                    if ($this->fecha_hasta) {
                        $invoiceQ->whereDate('fecha_emision', '<=', $this->fecha_hasta);
                    }
                })->orWhere(function ($fallbackQ) {
                    $fallbackQ->whereDoesntHave('invoice');
                    if ($this->fecha_desde) {
                        $fallbackQ->whereDate('created_at', '>=', $this->fecha_desde);
                    }
                    if ($this->fecha_hasta) {
                        $fallbackQ->whereDate('created_at', '<=', $this->fecha_hasta);
                    }
                });
            });
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(cantidad AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(precio_unitario AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(subtotal AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(created_at AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('numero_factura', 'like', "%{$search}%")
                            ->orWhere('nombre_cliente', 'like', "%{$search}%")
                            ->orWhereRaw('CAST(fecha_emision AS TEXT) ILIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('vehicleLog', function ($logQuery) use ($search) {
                        $logQuery->where('recorrido_inicio', 'like', "%{$search}%")
                            ->orWhere('recorrido_destino', 'like', "%{$search}%")
                            ->orWhereRaw('CAST(fecha AS TEXT) ILIKE ?', ["%{$search}%"]);
                    });
            });
        }

        $fuelTableQuery = clone $query;
        $fuelLogs = $this->paginateWithinBounds($query, 6, 'fuelPage');

        $vehicleLogQuery = VehicleLog::query()
            ->active()
            ->with(['vehicle.brand', 'vehicle.vehicleClass', 'driver', 'fuelLog', 'stageEvents'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if (auth()->user()?->role === 'conductor') {
            $driverId = (int) (auth()->user()->resolvedDriver()?->id ?? 0);
            if (!$driverId) {
                $vehicleLogQuery->whereRaw('1=0');
            } else {
                $vehicleLogQuery->where('drivers_id', $driverId);
            }
        }

        if ($this->vehicle_filter_id) {
            $vehicleLogQuery->where('vehicles_id', (int) $this->vehicle_filter_id);
        }

        if ($this->driver_filter_id) {
            $vehicleLogQuery->where('drivers_id', (int) $this->driver_filter_id);
        }

        if ($placaFiltro !== '') {
            $vehicleLogQuery->whereHas('vehicle', function ($q) use ($placaFiltro) {
                $q->whereRaw('UPPER(placa) LIKE ?', ['%' . $placaFiltro . '%']);
            });
        }

        if ($this->fecha_desde || $this->fecha_hasta) {
            $vehicleLogQuery->where(function ($q) {
                if ($this->fecha_desde) {
                    $q->whereDate('fecha', '>=', $this->fecha_desde);
                }
                if ($this->fecha_hasta) {
                    $q->whereDate('fecha', '<=', $this->fecha_hasta);
                }
            });
        }

        if ($search !== '') {
            $vehicleLogQuery->where(function ($q) use ($search) {
                $q->where('recorrido_inicio', 'like', "%{$search}%")
                    ->orWhere('recorrido_destino', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(fecha AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_salida AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_llegada AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        $vehicleLogCombinedQuery = clone $vehicleLogQuery;
        $vehicleLogs = $this->paginateWithinBounds($vehicleLogQuery, 6, 'bitacoraPage');

        $combinedFuelRows = $fuelTableQuery->get()->map(function (FuelLog $log) {
            $date = $log->invoice?->fecha_emision ?? $log->created_at;
            $recorridoInicio = trim((string) ($log->vehicleLog?->recorrido_inicio ?? ''));
            $recorridoDestino = trim((string) ($log->vehicleLog?->recorrido_destino ?? ''));
            $pauseSummary = $this->buildPauseSummaryForVehicleLog($log->vehicleLog);
            $meterStatus = !empty(data_get($log->invoice?->antifraud_payload_json, 'evidence.fuel_meter_photo_path'))
                ? 'Foto del medidor: registrada'
                : 'Foto del medidor: no registrada';

            return [
                'tipo' => 'vale',
                'fecha' => optional($date)->format('d/m/Y H:i') ?? '-',
                'fecha_sort' => $date?->timestamp ?? 0,
                'vehiculo' => $log->vehicle?->bitacora_display_name ?? '-',
                'conductor' => $log->driver?->nombre ?? '-',
                'detalle_titulo' => 'Factura',
                'detalle_principal' => (string) ($log->invoice?->numero_factura ?? '-'),
                'detalle_secundario' => 'Punto de salida: ' . ($recorridoInicio !== '' ? $recorridoInicio : '-'),
                'detalle_terciario' => 'Punto de llegada: ' . ($recorridoDestino !== '' ? $recorridoDestino : '-'),
                'detalle_cuaternario' => collect([$pauseSummary, $meterStatus])->filter()->implode(' | '),
                'total' => $log->total_calculado,
                'tiene_combustible' => true,
            ];
        })->toBase();

        $combinedBitacoraRows = $vehicleLogCombinedQuery->get()->map(function (VehicleLog $log) {
            $date = $log->fecha ?? $log->created_at;
            $pauseSummary = $this->buildPauseSummaryForVehicleLog($log);
            return [
                'tipo' => 'bitacora',
                'fecha' => optional($date)->format('d/m/Y H:i') ?? '-',
                'fecha_sort' => $date?->timestamp ?? 0,
                'vehiculo' => $log->vehicle?->display_name ?? '-',
                'conductor' => $log->driver?->nombre ?? '-',
                'detalle_titulo' => 'Recorrido',
                'detalle_principal' => (string) ($log->recorrido_inicio ?? '-'),
                'detalle_secundario' => 'Punto de llegada: ' . (string) ($log->recorrido_destino ?? '-'),
                'detalle_terciario' => $pauseSummary,
                'detalle_cuaternario' => $log->fuel_log_id ? 'Combustible vinculado: si' : null,
                'total' => null,
                'tiene_combustible' => (bool) $log->fuel_log_id,
            ];
        })->toBase();

        $combinedRows = $combinedFuelRows
            ->merge($combinedBitacoraRows)
            ->sortByDesc('fecha_sort')
            ->values();

        $combinedRows = $this->paginateCollection($combinedRows, 5, 'combinedPage');

        $antiFraudAlerts = collect();
        $antiFraudCases = collect();
        if (auth()->user()?->role !== 'conductor') {
            $this->syncExistingDuplicateInvoiceCases();

            $antiFraudCases = FuelAntifraudCase::query()
                ->active()
                ->with([
                    'invoice',
                    'conflictingInvoice',
                    'vehicle',
                    'conflictingVehicle',
                    'driver',
                    'conflictingDriver',
                ])
                ->where('type', FuelAntifraudCase::TYPE_DUPLICATE_INVOICE)
                ->latest()
                ->limit(8)
                ->get();

            $antiFraudAlerts = ActivityLog::query()
                ->where('module', 'combustible')
                ->whereIn('action', [
                    'FUEL_INVOICE_DUPLICATE_ALERT',
                    'FUEL_CAPACITY_EXCEEDED_ALERT',
                ])
                ->latestEvent()
                ->limit(6)
                ->get();
        }

        return view('livewire.fuel-log-manager', [
            'fuelLogs' => $fuelLogs,
            'vehicleLogs' => $vehicleLogs,
            'combinedRows' => $combinedRows,
            'antiFraudAlerts' => $antiFraudAlerts,
            'antiFraudCases' => $antiFraudCases,
        ]);
    }

    public function updatedVehicleId($value): void
    {
        if ($this->syncingAssignment) {
            return;
        }

        if (!$value) {
            $this->kilometraje_salida = null;
            $this->driverAssignmentMessage = '';
            $this->driverAssigned = true;
            $this->driver_id = null;
            return;
        }

        $assignment = $this->findVehicleAssignmentByVehicleId((int) $value);

        if ($assignment?->driver_id) {
            $this->ensureDriverInOptions((int) $assignment->driver_id);
            $this->syncingAssignment = true;
            $this->driver_id = (int) $assignment->driver_id;
            $this->syncingAssignment = false;
            $this->driverAssignmentMessage = '';
            $this->driverAssigned = true;
        } else {
            $this->syncingAssignment = true;
            $this->driver_id = null;
            $this->syncingAssignment = false;
            $this->driverAssignmentMessage = 'Falta asignar';
            $this->driverAssigned = false;
        }

        $this->syncKilometrajeFromVehicle((int) $value);
    }

    public function updatedDriverId($value): void
    {
        if ($this->syncingAssignment) {
            return;
        }

        if (!$value) {
            return;
        }

        $this->driverAssignmentMessage = '';
        $this->driverAssigned = true;

        $assignment = $this->findDriverAssignmentAtDate((int) $value);

        if ($assignment?->vehicle_id) {
            $this->syncingAssignment = true;
            $this->vehicle_id = (int) $assignment->vehicle_id;
            $this->syncingAssignment = false;
            $this->syncKilometrajeFromVehicle((int) $assignment->vehicle_id);
        }
    }

    public function updatedFechaEmision($value): void
    {
        if ($this->syncingAssignment || !$value) {
            return;
        }

        if ($this->vehicle_id) {
            $assignment = $this->findVehicleAssignmentByVehicleId((int) $this->vehicle_id);
            if ($assignment?->driver_id) {
                $this->ensureDriverInOptions((int) $assignment->driver_id);
                $this->syncingAssignment = true;
                $this->driver_id = (int) $assignment->driver_id;
                $this->syncingAssignment = false;
                $this->driverAssignmentMessage = '';
                $this->driverAssigned = true;
            } else {
                $this->syncingAssignment = true;
                $this->driver_id = null;
                $this->syncingAssignment = false;
                $this->driverAssignmentMessage = 'Falta asignar';
                $this->driverAssigned = false;
            }
            $this->syncKilometrajeFromVehicle((int) $this->vehicle_id);
        } elseif ($this->driver_id) {
            $assignment = $this->findDriverAssignmentAtDate((int) $this->driver_id);
            if ($assignment?->vehicle_id) {
                $this->syncingAssignment = true;
                $this->vehicle_id = (int) $assignment->vehicle_id;
                $this->syncingAssignment = false;
                $this->driverAssignmentMessage = '';
                $this->driverAssigned = true;
                $this->syncKilometrajeFromVehicle((int) $assignment->vehicle_id);
            }
        }
    }

    public function updatedGalonesOrPrecioGalon()
    {
        if ($this->galones && $this->precio_galon) {
            $this->total_calculado = $this->galones * $this->precio_galon;
        }
    }

    public function save()
    {
        $this->validate([
            'invoice_photo_file' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,webp',
        ]);

        if (auth()->user()?->role === 'conductor' && $this->isEdit) {
            session()->flash('error', 'No tiene permiso para editar vales de combustible.');
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            $driverId = (int) (auth()->user()->resolvedDriver()?->id ?? 0);
            if (!$driverId) {
                $this->addError('driver_id', 'El usuario conductor no tiene perfil de conductor asociado.');
                return;
            }
            $this->driver_id = $driverId;

            $allowedVehicles = $this->allowedVehicleIdsForDriver($driverId);
            if (!$this->vehicle_id || !in_array((int) $this->vehicle_id, $allowedVehicles, true)) {
                $this->addError('vehicle_id', 'Solo puede registrar combustible para su vehiculo asignado.');
                return;
            }
        }

        $this->validate();
        if (!$this->validateTripAndKmRules()) {
            return;
        }
        $this->numero_factura = trim($this->numero_factura);
        $this->gas_station_id = $this->resolveGasStationId();

        $vehicle = $this->vehicle_id ? Vehicle::query()->find((int) $this->vehicle_id) : null;
        $liters = (float) ($this->cantidad_combustible ?? $this->galones ?? 0);
        if ($vehicle && !is_null($vehicle->capacidad_tanque) && $liters > ((float) $vehicle->capacidad_tanque * 1.05)) {
            $this->logCapacityExceededAlert($vehicle, $liters, (float) $vehicle->capacidad_tanque);
            $this->addError('galones', 'La cantidad cargada excede la capacidad registrada del tanque del vehiculo.');
            return;
        }

        try {
            DB::transaction(function () {
                $existingInvoiceQuery = FuelInvoice::query()
                    ->where('numero_factura', $this->numero_factura);
                if (Schema::hasColumn('fuel_invoices', 'gas_station_id') && $this->gas_station_id) {
                    $existingInvoiceQuery->where('gas_station_id', $this->gas_station_id);
                }
                $existingInvoice = $existingInvoiceQuery->first();

                if ($this->isEdit && $this->editingLogId && $existingInvoice) {
                    $currentLog = FuelLog::find($this->editingLogId);
                    $currentInvoiceId = $currentLog?->fuel_invoice_id;
                        if ((int) $existingInvoice->id !== (int) $currentInvoiceId) {
                            $this->logDuplicateInvoiceAlert($this->numero_factura, $existingInvoice->id);
                            $this->addError('numero_factura', 'La factura ya esta registrada en otro vale.');
                            throw new \RuntimeException('duplicate_invoice_on_edit');
                        }
                }

                // first create or find invoice header (update attributes when editing)
                $invoiceData = [
                    'fecha_emision' => Carbon::parse($this->fecha_emision)->format('Y-m-d H:i'),
                    'nombre_cliente' => $this->nombre_cliente,
                    // Required by schema (no default in some DBs). Recomputed after detail save.
                    'monto_total' => 0,
                ];
                if (Schema::hasColumn('fuel_invoices', 'numero')) {
                    $invoiceData['numero'] = $this->numero_factura;
                }

                if ($this->isEdit && $this->editingLogId) {
                    $currentLog = FuelLog::find($this->editingLogId);
                    $invoice = $currentLog?->invoice;
                    if (!$invoice) {
                        $invoice = FuelInvoice::create(array_merge($invoiceData, [
                            'numero_factura' => $this->numero_factura,
                        ]));
                    } else {
                        $invoice->update(array_merge($invoiceData, [
                            'numero_factura' => $this->numero_factura,
                        ]));
                    }
                } else {
                    if ($existingInvoice) {
                        $invoiceHasFuelLog = FuelLog::query()
                            ->where('fuel_invoice_id', $existingInvoice->id)
                            ->exists();

                        if ($invoiceHasFuelLog) {
                            $this->logDuplicateInvoiceAlert($this->numero_factura, $existingInvoice->id);
                            $this->addError('numero_factura', 'La factura ya esta registrada en el sistema.');
                            throw new \RuntimeException('duplicate_invoice');
                        }

                        // Reutilizar factura huérfana (ej. creada en intento fallido anterior)
                        $existingInvoice->update($invoiceData);
                        $invoice = $existingInvoice;
                    } else {
                        $invoice = FuelInvoice::create(array_merge($invoiceData, [
                            'numero_factura' => $this->numero_factura,
                        ]));
                    }
                }

                $currentLog = $this->isEdit && $this->editingLogId ? FuelLog::find($this->editingLogId) : null;

                $data = [
                    'fuel_invoice_id' => $invoice->id,
                    'gas_station_id' => $this->gas_station_id,
                    'galones' => $this->cantidad_combustible ?? $this->galones,
                    'precio_galon' => $this->precio_galon,
                    'total_calculado' => $this->total_calculado ?? (($this->cantidad_combustible ?? $this->galones) * $this->precio_galon),
                    'estado' => $this->resolveFuelLogEstado($currentLog),
                ];

                if ($this->isEdit && $this->editingLogId) {
                    $log = $currentLog;
                    if ($log) {
                        $log->update($data);
                        $this->upsertVehicleLog($log->id);
                    }
                } else {
                    $newLog = FuelLog::create($data);
                    $this->upsertVehicleLog($newLog->id);
                }

                // update invoice total after detail saved
                $invoice->monto_total = $invoice->details()->sum('subtotal');
                $invoice->save();

                $snapshot = $this->buildScannedInvoiceSnapshot($invoice);
                if (!empty($snapshot)) {
                    $invoice = app(FuelInvoiceDocumentService::class)->persistFromSnapshot(
                        $invoice,
                        $snapshot,
                        $this->scannedInvoiceSourceUrl
                    );
                    $this->scannedInvoiceDocumentUrl = $invoice->siat_document_path
                        ? route('fuel-invoices.document', ['fuelInvoice' => $invoice->id])
                        : null;
                    $this->scannedInvoiceRolloDocumentUrl = $invoice->siat_rollo_document_path
                        ? route('fuel-invoices.rollo', ['fuelInvoice' => $invoice->id])
                        : null;
                }

                $invoice = $this->persistEditableEvidenceFiles($invoice);
                $this->invoicePhotoUrl = !empty($invoice->invoice_photo_path)
                    ? route('fuel-invoices.photo', ['fuelInvoice' => $invoice->id])
                    : null;
            });
        } catch (\RuntimeException $e) {
            if (in_array($e->getMessage(), ['duplicate_invoice', 'duplicate_invoice_on_edit'], true)) {
                return;
            }
            throw $e;
        }

        session()->flash('message', $this->isEdit ? 'Registro de combustible actualizado correctamente.' : 'Registro de combustible creado correctamente.');
        $this->resetForm();
    }

    public function edit(FuelLog $log)
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para editar vales de combustible.');
            return;
        }

        $this->showForm = true;
        $this->formView = 'fuel';
        $this->isEdit = true;
        $this->editingLogId = $log->id;

        // invoice header
        if($log->invoice) {
            $this->numero_factura = $log->invoice->numero_factura;
            $this->nombre_cliente = $log->invoice->nombre_cliente;
            $this->loadInvoicePreviewFromModel($log->invoice);
            $this->invoicePhotoUrl = !empty($log->invoice->invoice_photo_path)
                ? route('fuel-invoices.photo', ['fuelInvoice' => $log->invoice->id])
                : null;
        }

        // detail fields
        if ($log->gasStation) {
            $this->gas_station_id = $log->gasStation->id;
            $this->nit_emisor = (string) ($log->gasStation->nit_emisor ?? '');
            $this->razon_social_emisor = (string) ($log->gasStation->razon_social ?? '');
            $this->direccion_emisor = (string) ($log->gasStation->direccion ?? '');
        }
        $this->fecha_emision = optional($log->invoice?->fecha_emision)->format('Y-m-d\TH:i');
        $this->galones = $log->galones;
        $this->precio_galon = $log->precio_galon;
        $this->total_calculado = $log->total_calculado;

        $vehicleLog = VehicleLog::where('fuel_log_id', $log->id)->latest('id')->first();
        if ($vehicleLog) {
            $this->vehicle_id = $vehicleLog->vehicles_id;
            $this->driver_id = $vehicleLog->drivers_id;
            $this->kilometraje_salida = $vehicleLog->kilometraje_salida;
            $this->kilometraje_llegada = $vehicleLog->kilometraje_llegada;
            $this->recorrido_inicio = (string) $vehicleLog->recorrido_inicio;
            $this->recorrido_destino = (string) $vehicleLog->recorrido_destino;
            $this->latitud_inicio = $vehicleLog->latitud_inicio;
            $this->logitud_inicio = $vehicleLog->logitud_inicio;
            $this->latitud_destino = $vehicleLog->latitud_destino;
            $this->logitud_destino = $vehicleLog->logitud_destino;
        }
    }

    public function delete(FuelLog $log)
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para eliminar vales de combustible.');
            return;
        }

        $log->update(['activo' => false]);
        session()->flash('message', 'Registro de combustible inactivado correctamente.');
    }

    public function markAsVerificado(int $logId): void
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para verificar vales de combustible.');
            return;
        }

        $log = FuelLog::find($logId);
        if (!$log) {
            return;
        }

        $log->update(['estado' => 'Verificado']);
        session()->flash('message', 'Registro de combustible marcado como verificado.');
    }

    public function resetForm()
    {
        $this->gas_station_id = null;
        $this->numero_factura = '';
        $this->nombre_cliente = '';
        $this->gas_station_id = null;
        $this->numero_factura = '';
        $this->nombre_cliente = '';
        $this->vehicle_id = null;
        $this->driver_id = null;
        $this->fecha_emision = null;
        $this->galones = null;
        $this->precio_galon = null;
        $this->total_calculado = null;
        $this->cantidad_combustible = null;
        $this->qr_url = '';
        $this->razon_social_emisor = '';
        $this->nit_emisor = '';
        $this->direccion_emisor = '';
        $this->scannedInvoicePreview = [];
        $this->scannedInvoiceDocumentUrl = null;
        $this->scannedInvoiceRolloDocumentUrl = null;
        $this->scannedInvoiceSourceUrl = null;
        $this->invoicePhotoUrl = null;
        $this->invoice_photo_file = null;
        $this->kilometraje = null;
        $this->kilometraje_salida = null;
        $this->kilometraje_llegada = null;
        $this->recorrido_inicio = '';
        $this->recorrido_destino = '';
        $this->latitud_inicio = null;
        $this->logitud_inicio = null;
        $this->latitud_destino = null;
        $this->logitud_destino = null;
        $this->recibo = '';
        $this->isEdit = false;
        $this->editingLogId = null;
        $this->isBitacoraEdit = false;
        $this->editingBitacoraId = null;
        $this->driverAssignmentMessage = '';
        $this->driverAssigned = true;
        $this->showForm = false;
        $this->formView = 'fuel';
        $this->resetAllTablePages();
    }

    public function create()
    {
        $this->openFuelForm();
    }

    public function cancelForm()
    {
        $this->resetForm();
    }

    public function openFuelForm()
    {
        $this->resetForm();
        $this->formView = 'fuel';
        $this->showForm = true;
    }

    public function openBitacoraForm()
    {
        $this->resetForm();
        $this->formView = 'bitacora';
        $this->showForm = true;

        if (auth()->user()?->role === 'conductor') {
            $this->driver_id = (int) (auth()->user()->resolvedDriver()?->id ?? 0) ?: null;
        }
    }

    public function setTableView(string $view)
    {
        if (!in_array($view, ['fuel', 'bitacora', 'combined'], true)) {
            return;
        }

        $this->tableView = $view;
        $this->resetPage('fuelPage');
        $this->resetPage('bitacoraPage');
        $this->resetPage('combinedPage');
    }

    public function updatedFechaDesde(): void
    {
        $this->resetAllTablePages();
    }

    public function updatedFechaHasta(): void
    {
        $this->resetAllTablePages();
    }

    public function updatedSearch(): void
    {
        $this->resetAllTablePages();
    }

    public function updatedVehicleFilterId(): void
    {
        $this->resetAllTablePages();
    }

    public function updatedDriverFilterId(): void
    {
        $this->resetAllTablePages();
    }

    public function searchLogs(): void
    {
        $this->search = trim((string) $this->search);
        $this->resetAllTablePages();
    }

    public function limpiarFiltrosFecha(): void
    {
        $today = now()->toDateString();
        $this->fecha_desde = $today;
        $this->fecha_hasta = $today;
        $this->resetAllTablePages();
    }

    public function aplicarFiltroPlaca(): void
    {
        $this->placa_filtro = mb_strtoupper(trim($this->placa_filtro));
        $this->resetAllTablePages();
    }

    public function limpiarFiltroPlaca(): void
    {
        $this->placa_filtro = '';
        $this->resetAllTablePages();
    }

    public function limpiarFiltrosListado(): void
    {
        $today = now()->toDateString();
        $this->fecha_desde = $today;
        $this->fecha_hasta = $today;
        $this->placa_filtro = '';
        $this->vehicle_filter_id = null;
        $this->driver_filter_id = auth()->user()?->role === 'conductor'
            ? (int) (auth()->user()->resolvedDriver()?->id ?? 0) ?: null
            : null;
        $this->resetAllTablePages();
    }

    public function toggleAntiFraudTable(): void
    {
        $this->showAntiFraudTable = !$this->showAntiFraudTable;
    }

    public function filterByInvoiceNumber(string $invoiceNumber): void
    {
        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '') {
            return;
        }

        $this->search = $invoiceNumber;
        $this->tableView = 'fuel';
        $this->resetAllTablePages();
    }

    public function openFraudReviewCase(int $caseId): void
    {
        $case = FuelAntifraudCase::query()
            ->with([
                'invoice',
                'conflictingInvoice',
                'vehicle',
                'conflictingVehicle',
                'driver',
                'conflictingDriver',
            ])
            ->find($caseId);

        if (!$case) {
            session()->flash('error', 'No se encontro el caso antifraude seleccionado.');
            return;
        }

        $invoiceA = $case->invoice;
        $invoiceB = $case->conflictingInvoice;

        if (!$invoiceA && $case->fuel_invoice_id) {
            $invoiceA = FuelInvoice::query()->find((int) $case->fuel_invoice_id);
        }
        if (!$invoiceB && $case->conflicting_fuel_invoice_id) {
            $invoiceB = FuelInvoice::query()->find((int) $case->conflicting_fuel_invoice_id);
        }

        $this->fraudReview = [
            'type' => 'Factura duplicada',
            'summary' => (string) ($case->summary ?? 'Se detecto posible colision entre facturas.'),
            'invoice_number' => (string) ($case->invoice_number ?? ''),
            'detected_at' => optional($case->created_at)->format('d/m/Y H:i') ?: '-',
            'invoice_a' => $this->buildFraudInvoiceData($invoiceA, 'Factura A'),
            'invoice_b' => $this->buildFraudInvoiceData($invoiceB, 'Factura B'),
            'actions' => [
                'Comparar fecha, monto, vehiculo y conductor para confirmar si es duplicidad real.',
                'Revisar evidencia de ambas facturas (foto, PDF SIAT, PDF rollo, foto de medidor) antes de aprobar cualquier cambio.',
                'Si una factura es invalida, anular o desactivar el registro duplicado y dejar trazabilidad en observaciones.',
                'Si ambas son validas pero corresponden a operaciones distintas, documentar la justificacion y marcar el caso como revisado.',
            ],
        ];

        $this->showFraudReviewModal = true;
    }

    public function openFraudReviewAlert(int $alertId): void
    {
        $alert = ActivityLog::query()->find($alertId);
        if (!$alert) {
            session()->flash('error', 'No se encontro la alerta seleccionada.');
            return;
        }

        $changes = is_array($alert->changes_json) ? $alert->changes_json : [];
        $invoiceNumber = trim((string) ($changes['invoice_number'] ?? ''));
        $isDuplicate = (string) $alert->action === 'FUEL_INVOICE_DUPLICATE_ALERT';

        $invoiceMatches = collect();
        if ($invoiceNumber !== '') {
            $invoiceMatches = FuelInvoice::query()
                ->active()
                ->where('numero_factura', $invoiceNumber)
                ->orderByDesc('id')
                ->limit(2)
                ->get();
        }

        $this->fraudReview = [
            'type' => $isDuplicate ? 'Factura duplicada' : 'Supuesto fraude',
            'summary' => $isDuplicate
                ? 'Alerta por posible duplicidad detectada durante el registro.'
                : 'Alerta por posible exceso de capacidad o inconsistencia en carga.',
            'invoice_number' => $invoiceNumber,
            'detected_at' => optional($alert->fecha ?? $alert->created_at)->format('d/m/Y H:i') ?: '-',
            'invoice_a' => $this->buildFraudInvoiceData($invoiceMatches->get(0), 'Factura A'),
            'invoice_b' => $this->buildFraudInvoiceData($invoiceMatches->get(1), 'Factura B'),
            'actions' => $isDuplicate
                ? [
                    'Verificar si existen dos facturas con el mismo numero y confirmar cual corresponde a la operacion real.',
                    'Validar evidencia fotografica y PDF de cada factura antes de aceptar o descartar.',
                    'Si hay choque real de factura, corregir o anular la duplicada y dejar observacion de control.',
                ]
                : [
                    'Comparar litros cargados contra la capacidad del tanque y el historial del vehiculo.',
                    'Revisar foto del medidor y foto/PDF de factura para confirmar lectura y monto.',
                    'Si se confirma inconsistencia, bloquear aprobacion y escalar para auditoria interna.',
                ],
        ];

        $this->showFraudReviewModal = true;
    }

    public function closeFraudReviewModal(): void
    {
        $this->showFraudReviewModal = false;
        $this->fraudReview = [];
    }

    public function exportBitacoraPdf()
    {
        $params = [];
        if ($this->fecha_desde) {
            $params['fecha_desde'] = $this->fecha_desde;
        }
        if ($this->fecha_hasta) {
            $params['fecha_hasta'] = $this->fecha_hasta;
        }
        if (trim($this->placa_filtro) !== '') {
            $params['placa_filtro'] = trim($this->placa_filtro);
        }

        return redirect()->route('fuel-logs.bitacora.pdf', $params);
    }

    public function updatedPlacaFiltro($value): void
    {
        $this->placa_filtro = mb_strtoupper(trim((string) $value));
        $this->resetAllTablePages();
    }

    public function saveBitacora()
    {
        if (auth()->user()?->role === 'conductor' && $this->isBitacoraEdit) {
            session()->flash('error', 'No tiene permiso para editar registros de bitacora.');
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            $driverId = (int) (auth()->user()->resolvedDriver()?->id ?? 0);
            if (!$driverId) {
                $this->addError('driver_id', 'El usuario conductor no tiene perfil de conductor asociado.');
                return;
            }
            $this->driver_id = $driverId;

            $allowedVehicles = $this->allowedVehicleIdsForDriver($driverId);
            if (!$this->vehicle_id || !in_array((int) $this->vehicle_id, $allowedVehicles, true)) {
                $this->addError('vehicle_id', 'Solo puede registrar bitacora para su vehiculo asignado.');
                return;
            }
        }

        $this->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:drivers,id',
            'fecha_emision' => 'required|date',
            'kilometraje_salida' => 'required|numeric|min:0',
            'kilometraje_llegada' => 'nullable|numeric|min:0',
            'recorrido_inicio' => 'required|string|max:255',
            'recorrido_destino' => 'required|string|max:255',
            'latitud_inicio' => 'nullable|numeric',
            'logitud_inicio' => 'nullable|numeric',
            'latitud_destino' => 'nullable|numeric',
            'logitud_destino' => 'nullable|numeric',
        ]);

        if (!$this->validateTripAndKmRules()) {
            return;
        }

        $payload = [
            ...$this->resolveLocationPayload(),
            'drivers_id' => $this->driver_id,
            'vehicles_id' => $this->vehicle_id,
            'fecha' => Carbon::parse($this->fecha_emision)->toDateString(),
            'kilometraje_salida' => $this->kilometraje_salida,
            'kilometraje_llegada' => $this->kilometraje_llegada,
            'recorrido_inicio' => $this->recorrido_inicio,
            'recorrido_destino' => $this->recorrido_destino,
            'abastecimiento_combustible' => false,
        ];

        if ($this->isBitacoraEdit && $this->editingBitacoraId) {
            $log = VehicleLog::find($this->editingBitacoraId);
            if ($log) {
                $log->update($payload);
            }
        } else {
            VehicleLog::create([
                ...$payload,
                'fuel_log_id' => null,
            ]);
        }

        $kmUpdateStatus = $this->updateVehicleKilometraje(
            $this->vehicle_id,
            $this->kilometraje_llegada ?? $this->kilometraje_salida,
            $this->kilometraje_salida
        );

        session()->flash('message', ($this->isBitacoraEdit ? 'Registro de bitacora actualizado correctamente.' : 'Registro de bitacora creado correctamente.') . ($kmUpdateStatus === 'same' ? ' El kilometraje se mantuvo igual al anterior.' : ''));
        $this->resetForm();
    }

    public function editBitacora(VehicleLog $log): void
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para editar registros de bitacora.');
            return;
        }

        if ($log->fuel_log_id) {
            session()->flash('error', 'Esta bitacora pertenece a un vale de combustible. Edite el vale para modificarla.');
            return;
        }

        $this->resetForm();
        $this->showForm = true;
        $this->formView = 'bitacora';
        $this->isBitacoraEdit = true;
        $this->editingBitacoraId = $log->id;

        $this->vehicle_id = $log->vehicles_id ? (int) $log->vehicles_id : null;
        $this->driver_id = $log->drivers_id ? (int) $log->drivers_id : null;
        $this->fecha_emision = optional($log->fecha)->format('Y-m-d\T00:00');
        $this->kilometraje_salida = $log->kilometraje_salida !== null ? (float) $log->kilometraje_salida : null;
        $this->kilometraje_llegada = $log->kilometraje_llegada !== null ? (float) $log->kilometraje_llegada : null;
        $this->recorrido_inicio = (string) ($log->recorrido_inicio ?? '');
        $this->recorrido_destino = (string) ($log->recorrido_destino ?? '');
        $this->latitud_inicio = $log->latitud_inicio !== null ? (float) $log->latitud_inicio : null;
        $this->logitud_inicio = $log->logitud_inicio !== null ? (float) $log->logitud_inicio : null;
        $this->latitud_destino = $log->latitud_destino !== null ? (float) $log->latitud_destino : null;
        $this->logitud_destino = $log->logitud_destino !== null ? (float) $log->logitud_destino : null;
    }

    public function deleteBitacora(VehicleLog $log): void
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para eliminar registros de bitacora.');
            return;
        }

        if ($log->fuel_log_id) {
            session()->flash('error', 'Esta bitacora pertenece a un vale de combustible y no se puede eliminar desde esta vista.');
            return;
        }

        $log->update(['activo' => false]);
        session()->flash('message', 'Registro de bitacora inactivado correctamente.');
    }

    public function procesarQR(string $url): array
    {
        $this->resetErrorBag('qr_url');
        $this->qr_url = trim($url);

        if (!filter_var($this->qr_url, FILTER_VALIDATE_URL)) {
            $message = 'El QR no contiene una URL valida.';
            $this->addError('qr_url', $message);
            return ['ok' => false, 'message' => $message];
        }

        if (!$this->isSiatQrPayload($this->qr_url)) {
            $message = $this->verificationMessageFromQr($this->qr_url);
            $this->scannedInvoicePreview = [
                'estado' => 'Falta verificar',
                'numero_factura' => '',
                'fecha_emision' => '',
                'nombre_cliente' => '',
                'monto_total' => null,
                'nit_emisor' => '',
                'razon_social_emisor' => '',
                'direccion_emisor' => '',
                'cantidad' => null,
                'precio_unitario' => null,
                'cuf' => '',
                'producto_codigo' => '',
                'producto_descripcion' => '',
            ];
            $this->scannedInvoiceSourceUrl = $this->qr_url;
            $this->scannedInvoiceDocumentUrl = null;
            $this->scannedInvoiceRolloDocumentUrl = null;
            session()->flash('message', $message);

            return [
                'ok' => true,
                'message' => $message,
                'verification_status' => 'Falta verificar',
                'fields' => [],
            ];
        }

        $parsedUrl = parse_url($this->qr_url);
        parse_str($parsedUrl['query'] ?? '', $query);

        $nitEmisor = $this->pickQueryValue($query, ['nitEmisor', 'nit', 'nit_emisor']);
        $cuf = $this->pickQueryValue($query, ['cuf', 'codigo_unico_factura']);
        $numeroFactura = $this->pickQueryValue($query, ['numeroFactura', 'numero_factura', 'nro_factura', 'factura', 'nf', 'numero']);

        if (!$nitEmisor || !$cuf || !$numeroFactura) {
            $message = 'QR SIAT detectado, pero no se pudieron extraer todos los datos automaticos. El registro quedara como Verificado.';
            $this->scannedInvoicePreview = [
                'estado' => 'Verificado',
                'numero_factura' => '',
                'fecha_emision' => '',
                'nombre_cliente' => '',
                'monto_total' => null,
                'nit_emisor' => (string) ($nitEmisor ?? ''),
                'razon_social_emisor' => '',
                'direccion_emisor' => '',
                'cantidad' => null,
                'precio_unitario' => null,
                'cuf' => (string) ($cuf ?? ''),
                'producto_codigo' => '',
                'producto_descripcion' => '',
            ];
            $this->scannedInvoiceSourceUrl = $this->qr_url;
            $this->scannedInvoiceDocumentUrl = null;
            $this->scannedInvoiceRolloDocumentUrl = null;
            session()->flash('message', $message);
            return ['ok' => true, 'message' => $message, 'verification_status' => 'Verificado', 'fields' => []];
        }

        $payload = [
            'nitEmisor' => (string) $nitEmisor,
            'cuf' => (string) $cuf,
            'numeroFactura' => (string) $numeroFactura,
        ];

        try {
            Log::info('Iniciando consulta SIAT desde Livewire', ['payload' => $payload]);

            $response = Http::withOptions(['verify' => false])
                ->acceptJson()
                ->timeout(15)
                ->put('https://siatrest.impuestos.gob.bo/sre-sfe-shared-v2-rest/consulta/factura', $payload);

            if (!$response->successful()) {
                $message = 'SIAT no devolvio una respuesta valida para esta factura.';
                $this->addError('qr_url', $message);
                Log::warning('SIAT consulta/factura fallida desde Livewire', [
                    'status' => $response->status(),
                    'payload' => $payload,
                    'body' => $response->body(),
                ]);
                return ['ok' => false, 'message' => $message];
            }

            $json = $response->json();
            if (!is_array($json) || data_get($json, 'transaccion') === false) {
                $message = 'SIAT devolvio una respuesta sin transaccion valida.';
                $this->addError('qr_url', $message);
                Log::warning('SIAT respuesta invalida (transaccion=false o estructura no valida)', [
                    'payload' => $payload,
                    'body' => $response->body(),
                ]);
                return ['ok' => false, 'message' => $message];
            }

            $factura = data_get($json, 'objeto');
            if (!is_array($factura)) {
                $message = 'SIAT no devolvio el nodo objeto esperado.';
                $this->addError('qr_url', $message);
                Log::warning('SIAT sin nodo objeto', [
                    'payload' => $payload,
                    'body' => $response->body(),
                ]);
                return ['ok' => false, 'message' => $message];
            }

            $this->numero_factura = (string) (data_get($factura, 'numeroFactura') ?? $numeroFactura);
            $this->fecha_emision = $this->normalizeDatetime(data_get($factura, 'fechaEmision'));
            $this->total_calculado = $this->castFloat(data_get($factura, 'montoTotal'));
            $this->razon_social_emisor = (string) (data_get($factura, 'razonSocialEmisor') ?? '');
            $this->nit_emisor = (string) (data_get($factura, 'nitEmisor') ?? '');
            $this->direccion_emisor = (string) (data_get($factura, 'direccion') ?? '');

            // Mantener cliente con el valor fiscal del receptor para no romper validacion actual.
            $this->nombre_cliente = (string) (data_get($factura, 'nombreRazonSocial') ?? data_get($factura, 'nombreCliente') ?? $this->nombre_cliente);

            $detalle = data_get($factura, 'listaDetalle.0', []);
            $cantidad = $this->castFloat(data_get($detalle, 'cantidad'));
            $precioUnitario = $this->castFloat(data_get($detalle, 'precioUnitario'));

            if ($cantidad !== null) {
                $this->cantidad_combustible = $cantidad;
                $this->galones = $cantidad;
            }

            if ($precioUnitario !== null) {
                $this->precio_galon = $precioUnitario;
            } elseif ($this->total_calculado !== null && $this->galones && $this->galones > 0) {
                $this->precio_galon = round($this->total_calculado / $this->galones, 2);
            }

            $this->loadInvoicePreviewFromSiatResponse($factura, 'Verificado');

            $message = 'Factura consultada correctamente desde SIAT. El registro quedara como Verificado.';
            session()->flash('message', $message);
            Log::info('Consulta SIAT exitosa desde Livewire', [
                'numero_factura' => $this->numero_factura,
                'nombre_cliente' => $this->nombre_cliente,
            ]);

            return [
                'ok' => true,
                'message' => $message,
                'verification_status' => 'Verificado',
                'fields' => [
                    'numero_factura' => $this->numero_factura,
                    'nombre_cliente' => $this->nombre_cliente,
                    'fecha_emision' => $this->fecha_emision,
                    'galones' => $this->galones,
                    'precio_galon' => $this->precio_galon,
                    'total_calculado' => $this->total_calculado,
                    'razon_social_emisor' => $this->razon_social_emisor,
                    'nit_emisor' => $this->nit_emisor,
                    'direccion_emisor' => $this->direccion_emisor,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Error en consulta SIAT desde Livewire', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            $message = 'No fue posible consultar SIAT en este momento.';
            $this->addError('qr_url', $message);
            return ['ok' => false, 'message' => $message];
        }
    }

    private function pickQueryValue(array $query, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $query)) {
                continue;
            }

            $value = $query[$key];
            if (is_array($value)) {
                $value = reset($value);
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeDatetime(mixed $value): ?string
    {
        if (!$value) {
            return $this->fecha_emision;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return $this->fecha_emision;
        }
    }

    private function castFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function assignmentDate(): string
    {
        if (!$this->fecha_emision) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($this->fecha_emision)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function findVehicleAssignmentAtDate(int $vehicleId): ?VehicleAssignment
    {
        $date = $this->assignmentDate();
        $base = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('driver_id')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');

        // 1) Asignacion activa por fecha del formulario
        $matchByDate = (clone $base)
            ->where('activo', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $date);
            })
            ->first();

        if ($matchByDate) {
            return $matchByDate;
        }

        // 2) Cualquier asignacion activa del vehiculo
        $active = (clone $base)
            ->where('activo', true)
            ->first();

        if ($active) {
            return $active;
        }

        // 3) Ultima asignacion historica con conductor
        return (clone $base)->first();
    }

    private function findVehicleAssignmentByVehicleId(int $vehicleId): ?VehicleAssignment
    {
        $date = $this->assignmentDate();
        $base = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');

        // 1) Asignacion activa por fecha del formulario.
        $matchByDate = (clone $base)
            ->whereNotNull('driver_id')
            ->where('activo', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $date);
            })
            ->first();

        if ($matchByDate) {
            return $matchByDate;
        }

        // 2) Cualquier asignacion activa con conductor.
        $activeWithDriver = (clone $base)
            ->whereNotNull('driver_id')
            ->where('activo', true)
            ->first();

        if ($activeWithDriver) {
            return $activeWithDriver;
        }

        // 3) Ultima asignacion historica con conductor.
        $historicWithDriver = (clone $base)
            ->whereNotNull('driver_id')
            ->first();

        if ($historicWithDriver) {
            return $historicWithDriver;
        }

        // 4) Si existe el vehiculo en asignaciones pero sin conductor, devolver ese registro.
        return (clone $base)->first();
    }

    private function findDriverAssignmentAtDate(int $driverId): ?VehicleAssignment
    {
        $date = $this->assignmentDate();

        return VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $date);
            })
            ->orderByDesc('fecha_inicio')
            ->first();
    }

    private function upsertVehicleLog(int $fuelLogId): void
    {
        if (!$this->vehicle_id || !$this->driver_id) {
            return;
        }

        $payload = [
            ...$this->resolveLocationPayload(),
            'drivers_id' => $this->driver_id,
            'vehicles_id' => $this->vehicle_id,
            'fuel_log_id' => $fuelLogId,
            'fecha' => Carbon::parse($this->fecha_emision)->toDateString(),
            'kilometraje_salida' => $this->kilometraje_salida,
            'kilometraje_llegada' => $this->kilometraje_llegada,
            'recorrido_inicio' => $this->recorrido_inicio,
            'recorrido_destino' => $this->recorrido_destino,
            'abastecimiento_combustible' => true,
        ];

        if ($this->isEdit && $this->editingLogId) {
            $existing = VehicleLog::where('fuel_log_id', $fuelLogId)->latest('id')->first();
            if ($existing) {
                $existing->update($payload);
                $this->updateVehicleKilometraje($this->vehicle_id, $this->kilometraje_llegada ?? $this->kilometraje_salida, $this->kilometraje_salida);
                return;
            }
        }

        VehicleLog::create($payload);
        $this->updateVehicleKilometraje($this->vehicle_id, $this->kilometraje_llegada ?? $this->kilometraje_salida, $this->kilometraje_salida);
    }

    private function resolveFuelLogEstado(?FuelLog $existing = null): string
    {
        $currentEstado = trim((string) ($existing?->estado ?? ''));
        if ($this->isSiatQrPayload($this->qr_url)) {
            return 'Verificado';
        }

        if ($this->qr_url !== '') {
            return 'Falta verificar';
        }

        if (in_array($currentEstado, ['Verificado', 'Falta verificar', 'No verificar'], true)) {
            return $currentEstado === 'No verificar' ? 'Falta verificar' : $currentEstado;
        }

        return 'Falta verificar';
    }

    private function isSiatQrPayload(?string $payload): bool
    {
        $value = trim((string) $payload);
        if ($value === '') {
            return false;
        }

        $normalized = mb_strtolower($value);
        if (!str_contains($normalized, 'siat.impuestos.gob.bo/consulta')) {
            return false;
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return str_contains($normalized, 'siat.impuestos.gob.bo/consulta');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        return $host === 'siat.impuestos.gob.bo' && str_starts_with($path, '/consulta');
    }

    private function verificationMessageFromQr(string $payload): string
    {
        if ($this->isSiatQrPayload($payload)) {
            return 'QR SIAT detectado. El registro quedara como Verificado.';
        }

        return 'QR detectado, pero no corresponde a SIAT. El registro quedara como Falta verificar.';
    }

    private function loadInvoicePreviewFromModel(FuelInvoice $invoice): void
    {
        $snapshot = is_array($invoice->siat_snapshot_json) ? $invoice->siat_snapshot_json : [];
        $details = data_get($snapshot, 'details', []);
        $firstDetail = is_array($details) ? ($details[0] ?? []) : [];

        $this->scannedInvoicePreview = [
            'estado' => 'Verificado',
            'numero_factura' => (string) ($invoice->numero_factura ?? ''),
            'fecha_emision' => optional($invoice->fecha_emision)->format('Y-m-d H:i'),
            'nombre_cliente' => (string) ($invoice->nombre_cliente ?? ''),
            'monto_total' => $invoice->monto_total,
            'nit_emisor' => (string) (data_get($snapshot, 'nit_emisor') ?? $this->nit_emisor),
            'razon_social_emisor' => (string) (data_get($snapshot, 'gas_station.razon_social') ?? data_get($snapshot, 'razon_social_emisor') ?? $this->razon_social_emisor),
            'direccion_emisor' => (string) (data_get($snapshot, 'gas_station.direccion') ?? data_get($snapshot, 'direccion_emisor') ?? $this->direccion_emisor),
            'cantidad' => data_get($snapshot, 'cantidad') ?? $this->galones,
            'precio_unitario' => data_get($snapshot, 'precio_unitario') ?? $this->precio_galon,
            'cuf' => (string) (data_get($snapshot, 'cuf') ?? ''),
            'producto_codigo' => (string) (data_get($firstDetail, 'codigoProducto') ?? data_get($firstDetail, 'codigo') ?? ''),
            'producto_descripcion' => (string) (data_get($firstDetail, 'descripcion') ?? ''),
        ];
        $this->scannedInvoiceSourceUrl = $invoice->siat_source_url ? (string) $invoice->siat_source_url : null;
        $this->scannedInvoiceDocumentUrl = $invoice->siat_document_path
            ? route('fuel-invoices.document', ['fuelInvoice' => $invoice->id])
            : null;
        $this->scannedInvoiceRolloDocumentUrl = $invoice->siat_rollo_document_path
            ? route('fuel-invoices.rollo', ['fuelInvoice' => $invoice->id])
            : null;
        $this->invoicePhotoUrl = $invoice->invoice_photo_path
            ? route('fuel-invoices.photo', ['fuelInvoice' => $invoice->id])
            : null;
    }

    private function loadInvoicePreviewFromSiatResponse(array $factura, string $verificationStatus): void
    {
        $detalle = data_get($factura, 'listaDetalle.0', []);

        $this->scannedInvoicePreview = [
            'estado' => $verificationStatus,
            'numero_factura' => (string) (data_get($factura, 'numeroFactura') ?? $this->numero_factura),
            'fecha_emision' => (string) ($this->fecha_emision ?? ''),
            'nombre_cliente' => (string) (data_get($factura, 'nombreRazonSocial') ?? data_get($factura, 'nombreCliente') ?? $this->nombre_cliente),
            'monto_total' => $this->total_calculado,
            'nit_emisor' => (string) (data_get($factura, 'nitEmisor') ?? ''),
            'razon_social_emisor' => (string) (data_get($factura, 'razonSocialEmisor') ?? ''),
            'direccion_emisor' => (string) (data_get($factura, 'direccion') ?? ''),
            'cantidad' => data_get($detalle, 'cantidad') ?? $this->galones,
            'precio_unitario' => data_get($detalle, 'precioUnitario') ?? $this->precio_galon,
            'cuf' => (string) (data_get($factura, 'cuf') ?? ''),
            'producto_codigo' => (string) (data_get($detalle, 'codigoProducto') ?? ''),
            'producto_descripcion' => (string) (data_get($detalle, 'descripcion') ?? ''),
        ];
        $this->scannedInvoiceSourceUrl = $this->qr_url !== '' ? $this->qr_url : null;
        $this->scannedInvoiceDocumentUrl = null;
        $this->scannedInvoiceRolloDocumentUrl = null;
    }

    private function buildScannedInvoiceSnapshot(FuelInvoice $invoice): array
    {
        if (empty($this->scannedInvoicePreview)) {
            return is_array($invoice->siat_snapshot_json) ? $invoice->siat_snapshot_json : [];
        }

        return [
            'numero_factura' => (string) ($this->scannedInvoicePreview['numero_factura'] ?? $invoice->numero_factura ?? ''),
            'fecha_emision' => (string) ($this->scannedInvoicePreview['fecha_emision'] ?? optional($invoice->fecha_emision)->format('d/m/Y H:i:s') ?? ''),
            'nombre_cliente' => (string) ($this->scannedInvoicePreview['nombre_cliente'] ?? $invoice->nombre_cliente ?? ''),
            'monto_total' => $this->scannedInvoicePreview['monto_total'] ?? $invoice->monto_total,
            'cuf' => (string) ($this->scannedInvoicePreview['cuf'] ?? ''),
            'nit_emisor' => (string) ($this->scannedInvoicePreview['nit_emisor'] ?? ''),
            'razon_social_emisor' => (string) ($this->scannedInvoicePreview['razon_social_emisor'] ?? ''),
            'direccion_emisor' => (string) ($this->scannedInvoicePreview['direccion_emisor'] ?? ''),
            'cantidad' => $this->scannedInvoicePreview['cantidad'] ?? null,
            'precio_unitario' => $this->scannedInvoicePreview['precio_unitario'] ?? null,
            'details' => [[
                'codigo' => (string) ($this->scannedInvoicePreview['producto_codigo'] ?? ''),
                'descripcion' => (string) ($this->scannedInvoicePreview['producto_descripcion'] ?? 'Combustible'),
                'cantidad' => $this->scannedInvoicePreview['cantidad'] ?? null,
                'precio_unitario' => $this->scannedInvoicePreview['precio_unitario'] ?? null,
                'subtotal' => $this->scannedInvoicePreview['monto_total'] ?? $invoice->monto_total,
            ]],
            'siat_source_url' => (string) ($this->scannedInvoiceSourceUrl ?? ''),
        ];
    }

    private function persistEditableEvidenceFiles(FuelInvoice $invoice): FuelInvoice
    {
        $updates = [];

        if ($this->invoice_photo_file && Schema::hasColumn('fuel_invoices', 'invoice_photo_path')) {
            $updates['invoice_photo_path'] = $this->replaceStoredEvidenceFile(
                $invoice->invoice_photo_path,
                $this->invoice_photo_file,
                'fuel-invoices/photos/manual-factura'
            );
        }

        if (!empty($updates)) {
            $invoice->forceFill($updates)->save();
        }

        return $invoice->fresh() ?? $invoice;
    }

    private function replaceStoredEvidenceFile(?string $existingPath, $uploadedFile, string $directory): string
    {
        $newPath = (string) $uploadedFile->store($directory, 'public');
        $existingPath = trim((string) $existingPath);

        if ($existingPath !== '' && Storage::disk('public')->exists($existingPath)) {
            Storage::disk('public')->delete($existingPath);
        }

        return $newPath;
    }

    private function resolveGasStationId(): ?int
    {
        $nit = trim($this->nit_emisor);
        $razon = trim($this->razon_social_emisor);
        $direccion = trim($this->direccion_emisor);
        $nombreBase = $razon !== '' ? $razon : ($nit !== '' ? ('NIT ' . $nit) : 'SIN NOMBRE');

        if ($nit === '' && $razon === '' && $direccion === '') {
            return $this->gas_station_id;
        }

        if ($nit !== '') {
            $station = GasStation::firstOrCreate(
                ['nit_emisor' => $nit],
                [
                    'nombre' => $nombreBase,
                    'razon_social' => $razon !== '' ? $razon : ('NIT ' . $nit),
                    'direccion' => $direccion !== '' ? $direccion : null,
                    'ubicacion' => $direccion !== '' ? $direccion : null,
                    'activa' => true,
                ]
            );

            $updates = [];
            if (trim((string) $station->nombre) === '' || $station->nombre !== $nombreBase) {
                $updates['nombre'] = $nombreBase;
            }
            if ($razon !== '' && $station->razon_social !== $razon) {
                $updates['razon_social'] = $razon;
            }
            if ($direccion !== '' && $station->direccion !== $direccion) {
                $updates['direccion'] = $direccion;
            }
            if ($direccion !== '' && $station->ubicacion !== $direccion) {
                $updates['ubicacion'] = $direccion;
            }
            if (!empty($updates)) {
                $station->update($updates);
            }

            return $station->id;
        }

        $station = GasStation::create([
            'nombre' => $nombreBase,
            'nit_emisor' => null,
            'razon_social' => $razon !== '' ? $razon : 'Sin razon social',
            'direccion' => $direccion !== '' ? $direccion : null,
            'ubicacion' => $direccion !== '' ? $direccion : null,
            'activa' => true,
        ]);

        return $station->id;
    }

    private function resolveLocationPayload(): array
    {
        [$latInicio, $lngInicio] = $this->resolveCoordinates($this->latitud_inicio, $this->logitud_inicio, $this->recorrido_inicio);
        [$latDestino, $lngDestino] = $this->resolveCoordinates($this->latitud_destino, $this->logitud_destino, $this->recorrido_destino);

        $this->latitud_inicio = $latInicio;
        $this->logitud_inicio = $lngInicio;
        $this->latitud_destino = $latDestino;
        $this->logitud_destino = $lngDestino;

        return [
            'latitud_inicio' => $latInicio,
            'logitud_inicio' => $lngInicio,
            'latitud_destino' => $latDestino,
            'logitud_destino' => $lngDestino,
        ];
    }

    private function resolveCoordinates(?float $lat, ?float $lng, ?string $sourceText): array
    {
        if ($lat !== null && $lng !== null) {
            $safeLat = $this->normalizeLatitude((float) $lat);
            $safeLng = $this->normalizeLongitude((float) $lng);
            if ($safeLat !== null && $safeLng !== null) {
                return [$safeLat, $safeLng];
            }
        }

        if ($sourceText) {
            if (preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $sourceText, $m)) {
                $safeLat = $this->normalizeLatitude((float) $m[1]);
                $safeLng = $this->normalizeLongitude((float) $m[2]);
                if ($safeLat !== null && $safeLng !== null) {
                    return [$safeLat, $safeLng];
                }
            }
        }

        return [0.0, 0.0];
    }

    private function normalizeLatitude(float $value): ?float
    {
        if ($value < -90 || $value > 90) {
            return null;
        }
        return $value;
    }

    private function normalizeLongitude(float $value): ?float
    {
        if ($value < -180 || $value > 180) {
            return null;
        }
        return $value;
    }

    private function validateTripAndKmRules(): bool
    {
        $kmSalida = $this->kilometraje_salida !== null ? (float) $this->kilometraje_salida : null;
        $kmLlegada = $this->kilometraje_llegada !== null ? (float) $this->kilometraje_llegada : null;

        if ($kmSalida !== null && $kmLlegada !== null && $kmLlegada <= $kmSalida) {
            $this->addError('kilometraje_llegada', 'El kilometraje de llegada debe ser mayor al kilometraje de salida.');
            return false;
        }

        if ($kmSalida === null || $kmSalida <= 0) {
            $this->addError('kilometraje_salida', 'El kilometraje de salida debe ser mayor a 0.');
            return false;
        }

        if ($kmLlegada !== null && $kmLlegada <= 0) {
            $this->addError('kilometraje_llegada', 'El kilometraje de llegada debe ser mayor a 0.');
            return false;
        }

        [$latInicio, $lngInicio] = $this->resolveCoordinates($this->latitud_inicio, $this->logitud_inicio, $this->recorrido_inicio);
        [$latDestino, $lngDestino] = $this->resolveCoordinates($this->latitud_destino, $this->logitud_destino, $this->recorrido_destino);

        $this->latitud_inicio = $latInicio;
        $this->logitud_inicio = $lngInicio;
        $this->latitud_destino = $latDestino;
        $this->logitud_destino = $lngDestino;

        if ($this->isZeroCoordinate($latInicio, $lngInicio)) {
            $this->addError('recorrido_inicio', 'El punto de salida no puede ser 0,0. Seleccione una ubicacion valida.');
            return false;
        }

        if ($this->isZeroCoordinate($latDestino, $lngDestino)) {
            $this->addError('recorrido_destino', 'El punto de llegada no puede ser 0,0. Seleccione una ubicacion valida.');
            return false;
        }

        if ($this->sameCoordinatePair($latInicio, $lngInicio, $latDestino, $lngDestino)) {
            $this->addError('recorrido_destino', 'El punto de llegada debe ser diferente al punto de salida.');
            return false;
        }

        return true;
    }

    private function isZeroCoordinate(float $lat, float $lng): bool
    {
        return abs($lat) < 0.0000001 && abs($lng) < 0.0000001;
    }

    private function sameCoordinatePair(float $latA, float $lngA, float $latB, float $lngB): bool
    {
        return abs($latA - $latB) < 0.0000001 && abs($lngA - $lngB) < 0.0000001;
    }

    private function logDuplicateInvoiceAlert(string $invoiceNumber, int $invoiceId): void
    {
        try {
            $existingLog = FuelLog::query()
                ->with(['vehicleLog.vehicle', 'vehicleLog.driver'])
                ->where('fuel_invoice_id', $invoiceId)
                ->latest('id')
                ->first();

            $this->registerDuplicateInvoiceCase(
                $invoiceNumber,
                $invoiceId,
                null,
                $existingLog,
                null,
                'web_livewire',
                [
                    'incoming_vehicle_id' => $this->vehicle_id,
                    'incoming_driver_id' => $this->driver_id,
                    'incoming_fecha_emision' => $this->fecha_emision,
                    'incoming_total' => $this->total_calculado,
                ]
            );

            ActivityLog::create(ActivityLog::prepareAttributes([
                'user_id' => (int) (auth()->id() ?? 0) ?: null,
                'action' => 'FUEL_INVOICE_DUPLICATE_ALERT',
                'model' => 'FuelInvoice',
                'module' => 'combustible',
                'record_id' => $invoiceId,
                'changes_json' => [
                    'invoice_number' => $invoiceNumber,
                    'source' => 'web_livewire',
                ],
                'details' => 'Intento de registro duplicado de factura en gestion web.',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'fecha' => now(),
            ]));
        } catch (\Throwable) {
            // Ignorar errores de auditoria.
        }
    }

    private function registerDuplicateInvoiceCase(
        string $invoiceNumber,
        ?int $invoiceId,
        ?int $conflictingInvoiceId,
        ?FuelLog $fuelLog,
        ?FuelLog $conflictingFuelLog,
        string $source,
        array $evidence = []
    ): void {
        if (!Schema::hasTable('fuel_antifraud_cases')) {
            return;
        }

        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '') {
            return;
        }

        $caseKey = FuelAntifraudCase::buildDuplicateKey($invoiceNumber, $invoiceId, $conflictingInvoiceId, $source);
        $vehicleLog = $fuelLog?->vehicleLog;
        $conflictingVehicleLog = $conflictingFuelLog?->vehicleLog;

        FuelAntifraudCase::query()->updateOrCreate(
            ['case_key' => $caseKey],
            [
                'type' => FuelAntifraudCase::TYPE_DUPLICATE_INVOICE,
                'status' => FuelAntifraudCase::STATUS_PENDING,
                'invoice_number' => $invoiceNumber,
                'fuel_invoice_id' => $invoiceId,
                'conflicting_fuel_invoice_id' => $conflictingInvoiceId,
                'fuel_log_id' => $fuelLog?->id,
                'conflicting_fuel_log_id' => $conflictingFuelLog?->id,
                'vehicle_id' => $vehicleLog?->vehicles_id ?? $fuelLog?->vehicle_id,
                'driver_id' => $vehicleLog?->drivers_id ?? $fuelLog?->driver_id,
                'conflicting_vehicle_id' => $conflictingVehicleLog?->vehicles_id ?? $conflictingFuelLog?->vehicle_id,
                'conflicting_driver_id' => $conflictingVehicleLog?->drivers_id ?? $conflictingFuelLog?->driver_id,
                'detected_source' => $source,
                'summary' => $conflictingInvoiceId
                    ? 'Dos facturas registradas comparten el mismo numero.'
                    : 'Intento bloqueado porque la factura ya existe en el sistema.',
                'evidence_json' => array_filter($evidence, fn ($value) => $value !== null && $value !== ''),
                'activo' => true,
            ]
        );
    }

    private function syncExistingDuplicateInvoiceCases(): void
    {
        if (!Schema::hasTable('fuel_antifraud_cases')) {
            return;
        }

        $duplicateNumbers = FuelInvoice::query()
            ->active()
            ->whereNotNull('numero_factura')
            ->whereRaw("TRIM(numero_factura) <> ''")
            ->select('numero_factura')
            ->groupBy('numero_factura')
            ->havingRaw('COUNT(*) > 1')
            ->limit(20)
            ->pluck('numero_factura');

        foreach ($duplicateNumbers as $number) {
            $invoices = FuelInvoice::query()
                ->active()
                ->where('numero_factura', $number)
                ->orderBy('id')
                ->limit(6)
                ->get();

            $firstInvoice = $invoices->first();
            if (!$firstInvoice) {
                continue;
            }

            $firstLog = FuelLog::query()
                ->with(['vehicleLog.vehicle', 'vehicleLog.driver'])
                ->where('fuel_invoice_id', $firstInvoice->id)
                ->oldest('id')
                ->first();

            foreach ($invoices->skip(1) as $conflictingInvoice) {
                $conflictingLog = FuelLog::query()
                    ->with(['vehicleLog.vehicle', 'vehicleLog.driver'])
                    ->where('fuel_invoice_id', $conflictingInvoice->id)
                    ->oldest('id')
                    ->first();

                $this->registerDuplicateInvoiceCase(
                    (string) $number,
                    (int) $firstInvoice->id,
                    (int) $conflictingInvoice->id,
                    $firstLog,
                    $conflictingLog,
                    'database_scan',
                    [
                        'first_invoice_id' => (int) $firstInvoice->id,
                        'second_invoice_id' => (int) $conflictingInvoice->id,
                    ]
                );
            }
        }
    }

    private function logCapacityExceededAlert(Vehicle $vehicle, float $liters, float $capacity): void
    {
        try {
            ActivityLog::create(ActivityLog::prepareAttributes([
                'user_id' => (int) (auth()->id() ?? 0) ?: null,
                'action' => 'FUEL_CAPACITY_EXCEEDED_ALERT',
                'model' => 'FuelInvoice',
                'module' => 'combustible',
                'record_id' => (int) $vehicle->id ?: null,
                'changes_json' => [
                    'vehicle_id' => (int) $vehicle->id,
                    'vehicle_plate' => (string) ($vehicle->placa ?? ''),
                    'liters_attempted' => round($liters, 3),
                    'tank_capacity' => round($capacity, 3),
                    'source' => 'web_livewire',
                ],
                'details' => 'Intento de carga que excede la capacidad del tanque del vehiculo.',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'fecha' => now(),
            ]));
        } catch (\Throwable) {
            // Ignorar errores de auditoria.
        }
    }

    private function assignedVehicleIdsForDriver(int $driverId, string $date): array
    {
        return VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->where('activo', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $date);
            })
            ->pluck('vehicle_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    private function allowedVehicleIdsForDriver(int $driverId): array
    {
        if ($driverId <= 0) {
            return [];
        }

        // 1) Intentar por la fecha del formulario/QR
        $ids = $this->assignedVehicleIdsForDriver($driverId, $this->assignmentDate());
        if (!empty($ids)) {
            return $ids;
        }

        // 2) Fallback a asignacion vigente hoy
        $ids = $this->assignedVehicleIdsForDriver($driverId, now()->toDateString());
        if (!empty($ids)) {
            return $ids;
        }

        // 3) Ultimo fallback: cualquier vehiculo historicamente asignado al conductor
        return VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->pluck('vehicle_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    private function syncKilometrajeFromVehicle(int $vehicleId): void
    {
        $vehicle = Vehicle::query()->find($vehicleId);
        if (!$vehicle) {
            return;
        }

        $km = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        if ($km !== null) {
            $this->kilometraje_salida = (float) $km;
        }
    }

    private function ensureDriverInOptions(?int $driverId): void
    {
        if (!$driverId) {
            return;
        }

        if (array_key_exists($driverId, $this->drivers)) {
            return;
        }

        $driver = \App\Models\Driver::withTrashed()->find($driverId);
        if (!$driver) {
            return;
        }

        $this->drivers[$driverId] = (string) $driver->nombre;
    }

    private function ensureVehicleInOptions(?int $vehicleId): void
    {
        if (!$vehicleId) {
            return;
        }

        if (array_key_exists($vehicleId, $this->vehicles)) {
            return;
        }

        $vehicle = \App\Models\Vehicle::withTrashed()->find($vehicleId);
        if (!$vehicle) {
            return;
        }

        $this->vehicles[$vehicleId] = (string) $vehicle->placa;
        $this->vehicleKmMap[$vehicleId] = $vehicle->kilometraje_actual !== null ? (float) $vehicle->kilometraje_actual : null;
    }

    private function updateVehicleKilometraje(?int $vehicleId, ?float $kmActual, ?float $kmInicial): string
    {
        if (!$vehicleId) {
            return 'skipped';
        }

        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle) {
            return 'skipped';
        }

        if ((bool) ($vehicle->tacometro_danado ?? false)) {
            return 'skipped';
        }

        $kmCols = $this->resolveVehicleKilometrajeColumns();
        $updates = [];
        $status = 'skipped';

        if ($kmCols['has_inicial'] && $kmInicial !== null && $vehicle->kilometraje_inicial === null) {
            $updates['kilometraje_inicial'] = $kmInicial;
        }

        if ($kmCols['has_actual'] && $kmActual !== null) {
            $prev = $vehicle->kilometraje_actual !== null ? (float) $vehicle->kilometraje_actual : null;
            if ($prev === null || $kmActual >= $prev) {
                $updates['kilometraje_actual'] = $kmActual;
                $status = $prev !== null && abs($kmActual - $prev) < 0.000001 ? 'same' : 'updated';
            }
        }

        if ($kmCols['has_legacy'] && $kmActual !== null) {
            $prevLegacy = $vehicle->kilometraje !== null ? (float) $vehicle->kilometraje : null;
            if ($prevLegacy === null || $kmActual >= $prevLegacy) {
                $updates['kilometraje'] = $kmActual;
                if ($status === 'skipped') {
                    $status = $prevLegacy !== null && abs($kmActual - $prevLegacy) < 0.000001 ? 'same' : 'updated';
                }
            }
        }

        if (!empty($updates)) {
            $vehicle->update($updates);
        }

        MaintenanceAlertService::evaluateVehicleByKilometraje((int) $vehicleId);
        return $status;
    }

    private function resolveVehicleKilometrajeColumns(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $cache = [
            'has_inicial' => Schema::hasColumn('vehicles', 'kilometraje_inicial'),
            'has_actual' => Schema::hasColumn('vehicles', 'kilometraje_actual'),
            'has_legacy' => Schema::hasColumn('vehicles', 'kilometraje'),
        ];

        return $cache;
    }

    private function resetAllTablePages(): void
    {
        $this->resetPage('fuelPage');
        $this->resetPage('bitacoraPage');
        $this->resetPage('combinedPage');
    }

    private function buildFraudInvoiceData(?FuelInvoice $invoice, string $label): array
    {
        if (!$invoice) {
            return [
                'label' => $label,
                'exists' => false,
                'id' => null,
                'number' => '-',
                'date' => '-',
                'client' => '-',
                'total' => '-',
                'document_url' => null,
                'rollo_url' => null,
                'photo_url' => null,
                'meter_photo_url' => null,
            ];
        }

        return [
            'label' => $label,
            'exists' => true,
            'id' => (int) $invoice->id,
            'number' => (string) ($invoice->numero_factura ?? $invoice->numero ?? '-'),
            'date' => optional($invoice->fecha_emision)->format('d/m/Y H:i') ?: '-',
            'client' => (string) ($invoice->nombre_cliente ?? '-'),
            'total' => $invoice->monto_total !== null ? number_format((float) $invoice->monto_total, 2) : '-',
            'document_url' => !empty($invoice->siat_document_path) ? route('fuel-invoices.document', ['fuelInvoice' => $invoice->id]) : null,
            'rollo_url' => !empty($invoice->siat_rollo_document_path) ? route('fuel-invoices.rollo', ['fuelInvoice' => $invoice->id]) : null,
            'photo_url' => !empty($invoice->invoice_photo_path) ? route('fuel-invoices.photo', ['fuelInvoice' => $invoice->id]) : null,
            'meter_photo_url' => !empty(data_get($invoice->antifraud_payload_json, 'evidence.fuel_meter_photo_path'))
                ? route('fuel-invoices.meter-photo', ['fuelInvoice' => $invoice->id])
                : null,
        ];
    }

    private function paginateCollection($rows, int $perPage, string $pageName): LengthAwarePaginator
    {
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, (int) LengthAwarePaginator::resolveCurrentPage($pageName));
        if ($page > $lastPage) {
            $page = $lastPage;
            $this->setPage($lastPage, $pageName);
        }
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $pageName,
            ]
        );
    }

    private function paginateWithinBounds($query, int $perPage, string $pageName = 'page')
    {
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, (int) $this->getPage($pageName));

        if ($currentPage > $lastPage) {
            $this->setPage($lastPage, $pageName);
        }

        return $query->paginate($perPage, ['*'], $pageName);
    }

    private function buildPauseSummaryForVehicleLog(?VehicleLog $log): ?string
    {
        if (!$log) {
            return null;
        }

        $totals = [
            'CARGA' => 0,
            'ESPERA' => 0,
        ];

        /** @var VehicleLogStageEvent $event */
        foreach ($log->stageEvents ?? [] as $event) {
            if (mb_strtoupper((string) $event->stage_name) !== 'CONTINUAR') {
                continue;
            }

            $payload = is_array($event->payload_json) ? $event->payload_json : [];
            $pausedStage = mb_strtoupper(trim((string) data_get($payload, 'paused_stage', '')));
            if (!array_key_exists($pausedStage, $totals)) {
                continue;
            }

            $seconds = (int) round((float) data_get($payload, 'paused_duration_seconds', 0));
            if ($seconds <= 0) {
                $startedAt = data_get($payload, 'paused_started_at');
                $resumedAt = data_get($payload, 'resumed_at');
                if ($startedAt && $resumedAt) {
                    $seconds = max(0, Carbon::parse((string) $resumedAt)->diffInSeconds(Carbon::parse((string) $startedAt)));
                }
            }

            if ($seconds > 0) {
                $totals[$pausedStage] += $seconds;
            }
        }

        $parts = [];
        if ($totals['CARGA'] > 0) {
            $parts[] = 'Carga detenida: ' . $this->formatDurationHours($totals['CARGA']);
        }
        if ($totals['ESPERA'] > 0) {
            $parts[] = 'Espera detenida: ' . $this->formatDurationHours($totals['ESPERA']);
        }

        return empty($parts) ? null : implode(' | ', $parts);
    }

    private function formatDurationHours(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 min';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh %02dmin', $hours, $minutes);
        }

        if ($hours > 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dmin', max(1, $minutes));
    }
}
