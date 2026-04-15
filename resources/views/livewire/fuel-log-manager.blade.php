<div>
    <style>
        .bp-select-like-vehicle {
            border-radius: 10px;
            min-height: calc(2.35rem + 2px);
            border: 1px solid #ced4da;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        .bp-select-like-vehicle:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }

        select.bp-select-like-vehicle {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.2rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16'%3E%3Cpath fill='%236c757d' d='M2.646 5.646a.5.5 0 0 1 .708 0L8 10.293l4.646-4.647a.5.5 0 0 1 .708.708l-5 5a.5.5 0 0 1-.708 0l-5-5a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            background-size: 14px;
        }

        .bp-switch {
            display: flex;
            align-items: center;
            gap: .55rem;
        }
        .bp-switch .form-check-input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 42px;
            min-width: 42px;
            height: 24px;
            margin-top: 0;
            border-radius: 999px;
            border: 2px solid #c8d2e1;
            background: #eef3f9;
            position: relative;
            cursor: pointer;
            transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
            box-shadow: none;
        }

        .bp-switch .form-check-input[type="checkbox"]::after {
            content: "";
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(22, 40, 74, .25);
            transition: transform .18s ease;
        }

        .bp-switch .form-check-input[type="checkbox"]:checked {
            background: #1e88ff;
            border-color: #1e88ff;
        }

        .bp-switch .form-check-input[type="checkbox"]:checked::after {
            transform: translateX(18px);
        }

        .bp-switch .form-check-input[type="checkbox"]:focus {
            box-shadow: 0 0 0 .2rem rgba(30, 136, 255, .18);
            outline: 0;
        }

        [x-cloak] {
            display: none !important;
        }

        #qr-reader-modal video {
            filter: contrast(1.15) brightness(1.08);
        }

        .fuel-table {
            min-width: 1300px;
        }

        .fuel-table th,
        .fuel-table td {
            white-space: nowrap;
        }

        .fuel-table--combined {
            min-width: 100%;
        }

        .fuel-table--combined th,
        .fuel-table--combined td {
            white-space: normal;
            word-break: break-word;
            vertical-align: top;
        }

        .fuel-tables-shell {
            margin-left: 0;
            margin-right: 0;
        }

        #location-map {
            height: 360px;
            border-radius: 8px;
        }

        #fuel-form-side-map {
            height: 420px;
            border-radius: 10px;
        }

        #locationPickerModal .modal-dialog {
            max-width: 900px;
        }

        .fuel-file-viewer {
            width: 100%;
            min-height: 70vh;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #fff;
        }
        .fuel-file-image {
            display: block;
            max-width: 100%;
            max-height: 70vh;
            margin: 0 auto;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            background: #fff;
        }
        .fuel-file-viewer-loading {
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-weight: 600;
        }

        .fuel-form-map-card {
            border: 1px solid #e8edf7;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
            height: 100%;
        }

        .fuel-form-map-overlay {
            position: fixed;
            top: 96px;
            left: 50%;
            transform: translateX(-50%);
            width: min(560px, calc(100vw - 32px));
            z-index: 5000;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.28);
        }

        .fuel-form-map-meta {
            color: #475467;
            font-size: 1.25rem;
            margin-bottom: 8px;
        }

        .fuel-form-map-help {
            color: #667085;
            font-size: 1.2rem;
            margin-top: 8px;
        }

        .bitacora-style {
            border: 1px solid #cfd6de;
        }

        .bitacora-style th,
        .bitacora-style td {
            border: 1px solid #dee3e8 !important;
            font-size: 12px;
            vertical-align: middle;
        }

        .bitacora-style thead th {
            background: #f3f5f7 !important;
            color: #374151;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .bitacora-style .subhead th {
            background: #f8f9fb !important;
            font-size: 10px;
        }

        .bitacora-cell-note {
            font-size: 11px;
            color: #5f6770;
        }

        .fuel-toolbar {
            background: #2f5ea2;
            border-radius: 20px 20px 0 0;
            padding: 20px 22px;
        }

        .fuel-toolbar__title {
            color: #fff;
            font-size: 1.15rem;
            font-weight: 800;
            margin: 0;
        }

        .fuel-toolbar__actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .fuel-toolbar__search {
            min-width: min(320px, 100%);
            max-width: 420px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.65);
            padding: 10px 14px;
            box-shadow: none;
        }

        .fuel-toolbar .btn {
            border-radius: 14px;
            padding-left: 16px;
            padding-right: 16px;
            font-weight: 700;
        }

        .fuel-toolbar .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.75);
            color: #fff;
        }

        .fuel-toolbar .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        .fuel-toolbar .btn-warning {
            color: #1f2937;
        }

        .fuel-toolbar__filters {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
        }

        .fuel-toolbar__filters .form-label {
            color: #eef4ff;
            font-weight: 700;
        }

        .fuel-toolbar__filters .form-control,
        .fuel-toolbar__filters .form-select {
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.65);
        }

        @media (max-width: 992px) {
            .fuel-tables-shell {
                margin-left: 0;
                margin-right: 0;
            }
        }

        @media (max-width: 768px) {
            .fuel-form-map-overlay {
                top: 72px;
                left: 8px;
                transform: none;
                width: auto;
            }
        }
    </style>

    <div class="page-title mb-4 fuel-toolbar">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h1 class="fuel-toolbar__title">
                Gestiones en gasolina
            </h1>
            @if (!$showForm)
            <div class="fuel-toolbar__actions">
                <input
                    type="text"
                    wire:model.live.debounce.350ms="search"
                    class="form-control fuel-toolbar__search"
                    placeholder="Buscar por cualquier campo">
                <button type="button" wire:click="searchLogs" class="btn btn-outline-light">
                    Buscar
                </button>
                @if(auth()->user()?->role !== 'conductor')
                <button
                    type="button"
                    id="fuel-document-trigger"
                    class="btn btn-outline-light">
                    <i class="fas fa-file-lines me-2"></i>Documento
                </button>
                @endif
                <button type="button" wire:click="openFuelForm" class="btn btn-warning">
                    Nuevo
                </button>
            </div>
            @endif
        </div>
        @if (!$showForm)
        @if(auth()->user()?->role !== 'conductor' && ($antiFraudAlerts->count() ?? 0) > 0)
        <div class="alert alert-warning mt-3 mb-0">
            <div class="fw-bold mb-2">
                <i class="fas fa-shield-halved me-2"></i>Control Anti-Fraude
            </div>
            <div class="small text-muted mb-2">Ultimas alertas detectadas por duplicidad de facturas o exceso de capacidad.</div>
            <div class="d-flex flex-column gap-2">
                @foreach($antiFraudAlerts as $alert)
                    @php
                        $changes = is_array($alert->changes_json) ? $alert->changes_json : [];
                        $isDuplicate = $alert->action === 'FUEL_INVOICE_DUPLICATE_ALERT';
                    @endphp
                    <div class="border rounded-3 bg-white p-2">
                        <div class="fw-semibold">
                            {{ $isDuplicate ? 'Factura duplicada detectada' : 'Exceso de capacidad detectado' }}
                        </div>
                        <div class="small text-muted">
                            @if($isDuplicate)
                                Factura: {{ $changes['invoice_number'] ?? '-' }}
                                | Vehiculo existente: {{ $changes['existing_vehicle_plate'] ?? '-' }}
                                | Conductor existente: {{ $changes['existing_driver_name'] ?? '-' }}
                            @else
                                Vehiculo: {{ $changes['vehicle_plate'] ?? '-' }}
                                | Litros intentados: {{ $changes['liters_attempted'] ?? '-' }}
                                | Capacidad tanque: {{ $changes['tank_capacity'] ?? '-' }}
                            @endif
                            | Fecha: {{ optional($alert->fecha ?? $alert->created_at)->format('d/m/Y H:i') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
        <div class="fuel-toolbar__filters">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Fecha desde</label>
                    <input type="date" wire:model.live="fecha_desde" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Fecha hasta</label>
                    <input type="date" wire:model.live="fecha_hasta" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Vehiculo</label>
                    <select wire:model.live="vehicle_filter_id" class="form-select bp-select-like-vehicle">
                        <option value="">Todos los vehiculos</option>
                        @foreach($vehicles as $id => $placa)
                            <option value="{{ $id }}">{{ $placa }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Conductor</label>
                    <select wire:model.live="driver_filter_id" class="form-select bp-select-like-vehicle">
                        <option value="">Todos los conductores</option>
                        @foreach($drivers as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 d-grid">
                    <button type="button" wire:click="limpiarFiltrosFecha" class="btn btn-outline-light">Limpiar filtro de fechas</button>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Filtrar por placa</label>
                    <select wire:model.live="placa_filtro" class="form-select bp-select-like-vehicle">
                        <option value="">Todas las placas</option>
                        @foreach(collect($vehicles)->values()->unique()->sort()->values() as $placa)
                        <option value="{{ $placa }}">{{ $placa }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="button" wire:click="aplicarFiltroPlaca" class="btn btn-warning">Filtrar placa</button>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="button" wire:click="limpiarFiltroPlaca" class="btn btn-outline-light">Limpiar placa</button>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="button" wire:click="limpiarFiltrosListado" class="btn btn-warning">Restaurar hoy</button>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if (session('message'))
    <div class="alert alert-success fade show js-auto-dismiss-alert" data-auto-dismiss="3000" role="alert">
        {{ session('message') }}
    </div>
    @endif

    @if (!$showForm)
    @endif

    @if ($showForm && $formView === 'bitacora')
    <div class="bp-gestiones-form-overlay">
        <div class="card shadow-sm mb-4 bp-gestiones-form-card">
            <div class="card-header">{{ $isBitacoraEdit ? 'Editar Registro de Bitacora' : 'Nuevo Registro de Bitacora' }}</div>
            <div class="card-body">
                <form wire:submit.prevent="saveBitacora">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Vehiculo *</label>
                            <select id="vehicle_id_bitacora" wire:model.live="vehicle_id" class="form-select bp-select-like-vehicle @error('vehicle_id') is-invalid @enderror">
                                <option value="">-- Ninguno --</option>
                                @foreach($vehicles as $id => $placa)
                                <option value="{{ $id }}" data-km-actual="{{ $vehicleKmMap[(int) $id] ?? '' }}">{{ $placa }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Conductor *</label>
                            <select wire:model="driver_id" class="form-select bp-select-like-vehicle @error('driver_id') is-invalid @enderror">
                                <option value="">{{ $vehicle_id && !$driverAssigned ? 'Falta asignar' : '-- Ninguno --' }}</option>
                                @foreach($drivers as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                            @error('driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($driverAssignmentMessage !== '')
                            <div class="form-text text-warning">{{ $driverAssignmentMessage }}</div>
                            @endif
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Fecha *</label>
                            <input type="datetime-local" wire:model="fecha_emision" class="form-control @error('fecha_emision') is-invalid @enderror">
                            @error('fecha_emision') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Km Salida *</label>
                            <input id="kilometraje_salida_bitacora" type="number" step="0.01" wire:model="kilometraje_salida" class="form-control @error('kilometraje_salida') is-invalid @enderror">
                            @error('kilometraje_salida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Km Llegada</label>
                            <input type="number" step="0.01" wire:model="kilometraje_llegada" class="form-control @error('kilometraje_llegada') is-invalid @enderror">
                            @error('kilometraje_llegada')
                            <div class="invalid-feedback d-block">Corrija este campo: {{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Recorrido Inicio *</label>
                            <div class="input-group">
                                <input id="recorrido_inicio_bitacora" type="text" wire:model="recorrido_inicio" class="form-control @error('recorrido_inicio') is-invalid @enderror">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary location-picker-btn"
                                    data-target-input="recorrido_inicio_bitacora"
                                    data-target-lat="latitud_inicio_bitacora"
                                    data-target-lng="logitud_inicio_bitacora">
                                    <i class="fas fa-map-marker-alt me-1"></i>Elegir en mapa
                                </button>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-6">
                                    <input id="latitud_inicio_bitacora" type="text" wire:model.live="latitud_inicio" class="form-control form-control-sm bg-light" placeholder="Latitud" readonly>
                                </div>
                                <div class="col-6">
                                    <input id="logitud_inicio_bitacora" type="text" wire:model.live="logitud_inicio" class="form-control form-control-sm bg-light" placeholder="Longitud" readonly>
                                </div>
                            </div>
                            @error('recorrido_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Recorrido Destino *</label>
                            <div class="input-group">
                                <input id="recorrido_destino_bitacora" type="text" wire:model="recorrido_destino" class="form-control @error('recorrido_destino') is-invalid @enderror">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary location-picker-btn"
                                    data-target-input="recorrido_destino_bitacora"
                                    data-target-lat="latitud_destino_bitacora"
                                    data-target-lng="logitud_destino_bitacora">
                                    <i class="fas fa-map-marker-alt me-1"></i>Elegir en mapa
                                </button>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-6">
                                    <input id="latitud_destino_bitacora" type="text" wire:model.live="latitud_destino" class="form-control form-control-sm bg-light" placeholder="Latitud" readonly>
                                </div>
                                <div class="col-6">
                                    <input id="logitud_destino_bitacora" type="text" wire:model.live="logitud_destino" class="form-control form-control-sm bg-light" placeholder="Longitud" readonly>
                                </div>
                            </div>
                            @error('recorrido_destino') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ $isBitacoraEdit ? 'Actualizar bitacora' : 'Guardar bitacora' }}</button>
                        <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @elseif ($showForm)
    <div class="bp-gestiones-form-overlay">
        <div class="bp-gestiones-form-stage">
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4 bp-gestiones-form-card">
                        <div class="card-header">{{ $isEdit ? 'Editar Registro de Combustible' : 'Nuevo Registro de Combustible' }}</div>
                        <div class="card-body">
                            <form wire:submit.prevent="save">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Vehiculo *</label>
                                        <select id="vehicle_id_fuel" wire:model.live="vehicle_id" class="form-select bp-select-like-vehicle @error('vehicle_id') is-invalid @enderror">
                                            <option value="">-- Ninguno --</option>
                                            @foreach($vehicles as $id => $placa)
                                            <option value="{{ $id }}" data-km-actual="{{ $vehicleKmMap[(int) $id] ?? '' }}">{{ $placa }}</option>
                                            @endforeach
                                        </select>
                                        @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Conductor *</label>
                                        <select wire:model="driver_id" class="form-select bp-select-like-vehicle @error('driver_id') is-invalid @enderror">
                                            <option value="">{{ $vehicle_id && !$driverAssigned ? 'Falta asignar' : '-- Ninguno --' }}</option>
                                            @foreach($drivers as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @if($driverAssignmentMessage !== '')
                                        <div class="form-text text-warning">{{ $driverAssignmentMessage }}</div>
                                        @endif
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Numero de Factura *</label>
                                        <input id="numero_factura" type="text" wire:model="numero_factura" class="form-control bg-light  @error('numero_factura') is-invalid @enderror" readonly>
                                        @error('numero_factura') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Cliente *</label>
                                        <input id="nombre_cliente" type="text" wire:model="nombre_cliente" class="form-control bg-light @error('nombre_cliente') is-invalid @enderror" readonly>
                                        @error('nombre_cliente') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Fecha Emision *</label>
                                        <input id="fecha_emision" type="datetime-local" wire:model="fecha_emision" class="form-control bg-light @error('fecha_emision') is-invalid @enderror" readonly>
                                        @error('fecha_emision') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Cantidad *</label>
                                        <input id="galones" type="number" step="0.00001" wire:model.live="galones" class="form-control bg-light @error('galones') is-invalid @enderror" readonly>
                                        @error('galones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Precio por Litro *</label>
                                        <input id="precio_galon" type="number" step="0.01" wire:model.live="precio_galon" class="form-control bg-light @error('precio_galon') is-invalid @enderror" readonly>
                                        @error('precio_galon') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Total Calculado</label>
                                        <input id="total_calculado" type="number" step="0.01" wire:model="total_calculado" class="form-control bg-light" readonly>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Razon Social (Gasolinera)</label>
                                        <input id="razon_social_emisor" type="text" wire:model="razon_social_emisor" class="form-control bg-light" readonly>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">NIT Emisor</label>
                                        <input id="nit_emisor" type="text" wire:model="nit_emisor" class="form-control bg-light" readonly>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold">Direccion</label>
                                        <input id="direccion_emisor" type="text" wire:model="direccion_emisor" class="form-control bg-light" readonly>
                                    </div>
                                    <div class="col-12">
                                        <div class="card border bg-light-subtle">
                                            <div class="card-body">
                                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                                    <div>
                                                        <h6 class="fw-bold mb-1">
                                                            <i class="fas fa-file-invoice me-2"></i>Factura obtenida del escaneo
                                                        </h6>
                                                        <div class="small text-muted">
                                                            Aqui se muestran los datos obtenidos del QR SIAT y el documento guardado cuando ya exista.
                                                        </div>
                                                    </div>
                                                    @if(($scannedInvoicePreview['estado'] ?? '') === 'Verificado')
                                                    <span class="badge bg-success">Verificado</span>
                                                    @elseif(!empty($scannedInvoicePreview))
                                                    <span class="badge bg-warning text-dark">Falta verificar</span>
                                                    @else
                                                    <span class="badge bg-secondary">Sin escaneo</span>
                                                    @endif
                                                </div>

                                                @if(!empty($scannedInvoicePreview))
                                                <div class="row g-3">
                                                    <div class="col-12 col-xl-7">
                                                        <div class="row g-3">
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Numero de factura</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['numero_factura'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Fecha de emision</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['fecha_emision'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Monto total</div>
                                                        <div class="fw-semibold">
                                                            {{ $scannedInvoicePreview['monto_total'] !== null && $scannedInvoicePreview['monto_total'] !== '' ? 'Bs ' . number_format((float) $scannedInvoicePreview['monto_total'], 2) : 'Sin dato' }}
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <div class="small text-muted">Cliente</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['nombre_cliente'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <div class="small text-muted">CUF</div>
                                                        <div class="fw-semibold text-break">{{ $scannedInvoicePreview['cuf'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Razon social emisor</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['razon_social_emisor'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">NIT emisor</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['nit_emisor'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Direccion emisor</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['direccion_emisor'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Cantidad</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['cantidad'] !== null && $scannedInvoicePreview['cantidad'] !== '' ? $scannedInvoicePreview['cantidad'] : 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Precio unitario</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['precio_unitario'] !== null && $scannedInvoicePreview['precio_unitario'] !== '' ? $scannedInvoicePreview['precio_unitario'] : 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="small text-muted">Codigo producto</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['producto_codigo'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="small text-muted">Descripcion producto</div>
                                                        <div class="fw-semibold">{{ $scannedInvoicePreview['producto_descripcion'] ?: 'Sin dato' }}</div>
                                                    </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-xl-5">
                                                        <div class="border rounded-4 bg-white p-3 h-100">
                                                            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                                                <div>
                                                                    <div class="fw-bold">Vista previa tipo rollo</div>
                                                                    <div class="small text-muted">Reconstruida con los datos extraidos del SIAT.</div>
                                                                </div>
                                                                @if($scannedInvoiceRolloDocumentUrl)
                                                                <button type="button" class="btn btn-sm btn-outline-dark fuel-view-file-btn" data-url="{{ $scannedInvoiceRolloDocumentUrl }}" data-kind="pdf" data-title="Factura tipo rollo">
                                                                    <i class="fas fa-receipt me-1"></i>Ver rollo PDF
                                                                </button>
                                                                @endif
                                                            </div>
                                                            <div class="border rounded-3 p-3 bg-light" style="max-width: 320px; margin: 0 auto; font-size: 12px; line-height: 1.35;">
                                                                <div class="text-center fw-bold">FACTURA</div>
                                                                <div class="text-center fw-bold">CON DERECHO A CREDITO FISCAL</div>
                                                                <div class="text-center">{{ $scannedInvoicePreview['razon_social_emisor'] ?: 'SIN EMISOR' }}</div>
                                                                <div class="text-center">{{ $scannedInvoicePreview['direccion_emisor'] ?: 'SIN DIRECCION' }}</div>
                                                                <hr class="my-2">
                                                                <div><strong>NIT:</strong> {{ $scannedInvoicePreview['nit_emisor'] ?: '-' }}</div>
                                                                <div><strong>FACTURA N:</strong> {{ $scannedInvoicePreview['numero_factura'] ?: '-' }}</div>
                                                                <div><strong>COD. AUTORIZACION:</strong> {{ $scannedInvoicePreview['cuf'] ?: '-' }}</div>
                                                                <div><strong>NOMBRE/RAZON SOCIAL:</strong> {{ $scannedInvoicePreview['nombre_cliente'] ?: '-' }}</div>
                                                                <div><strong>FECHA DE EMISION:</strong> {{ $scannedInvoicePreview['fecha_emision'] ?: '-' }}</div>
                                                                <hr class="my-2">
                                                                <div class="fw-bold text-center mb-1">DETALLE</div>
                                                                <div>{{ $scannedInvoicePreview['producto_codigo'] ?: '' }}{{ $scannedInvoicePreview['producto_codigo'] ? ' - ' : '' }}{{ $scannedInvoicePreview['producto_descripcion'] ?: 'Combustible' }}</div>
                                                                <div class="small text-muted">Unidad de Medida: Litro</div>
                                                                <div>{{ $scannedInvoicePreview['cantidad'] !== null && $scannedInvoicePreview['cantidad'] !== '' ? $scannedInvoicePreview['cantidad'] : '0' }} X {{ $scannedInvoicePreview['precio_unitario'] !== null && $scannedInvoicePreview['precio_unitario'] !== '' ? $scannedInvoicePreview['precio_unitario'] : '0' }} - {{ $scannedInvoicePreview['monto_total'] !== null && $scannedInvoicePreview['monto_total'] !== '' ? number_format((float) $scannedInvoicePreview['monto_total'], 2) : '0.00' }}</div>
                                                                <hr class="my-2">
                                                                <div><strong>TOTAL Bs:</strong> {{ $scannedInvoicePreview['monto_total'] !== null && $scannedInvoicePreview['monto_total'] !== '' ? number_format((float) $scannedInvoicePreview['monto_total'], 2) : '0.00' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-flex flex-wrap gap-2 mt-3">
                                                    @if($scannedInvoiceSourceUrl)
                                                    <a href="{{ $scannedInvoiceSourceUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-up-right-from-square me-1"></i>Ver QR origen
                                                    </a>
                                                    @endif
                                                    @if($scannedInvoiceDocumentUrl)
                                                    <button type="button" class="btn btn-sm btn-outline-secondary fuel-view-file-btn" data-url="{{ $scannedInvoiceDocumentUrl }}" data-kind="pdf" data-title="Factura guardada">
                                                        <i class="fas fa-file-pdf me-1"></i>Ver factura guardada
                                                    </button>
                                                    @else
                                                    <span class="small text-muted align-self-center">La factura PDF se mostrara aqui despues de guardar el registro.</span>
                                                    @endif
                                                    @if($scannedInvoiceRolloDocumentUrl)
                                                    <button type="button" class="btn btn-sm btn-outline-dark fuel-view-file-btn" data-url="{{ $scannedInvoiceRolloDocumentUrl }}" data-kind="pdf" data-title="Factura rollo">
                                                        <i class="fas fa-receipt me-1"></i>Ver factura rollo
                                                    </button>
                                                    @endif
                                                </div>
                                                @else
                                                <div class="small text-muted">Escanea un QR para ver aqui la informacion de la factura obtenida.</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="card border bg-light-subtle">
                                            <div class="card-body">
                                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                                    <div>
                                                        <h6 class="fw-bold mb-1">
                                                            <i class="fas fa-camera me-2"></i>Evidencia fotografica editable
                                                        </h6>
                                                        <div class="small text-muted">
                                                            Puedes conservar la evidencia que vino del movil o reemplazarla desde este formulario.
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row g-4">
                                                    <div class="col-12">
                                                        <label class="form-label fw-bold">Foto de la factura</label>
                                                        <input type="file" wire:model="invoice_photo_file" class="form-control" accept="image/*">
                                                        <div class="small text-muted mt-1">Sube una nueva imagen si quieres reemplazar la actual.</div>
                                                        <div class="mt-3">
                                                            @if($invoice_photo_file)
                                                                <img src="{{ $invoice_photo_file->temporaryUrl() }}" alt="Nueva foto factura" class="img-fluid rounded border" style="max-height: 220px;">
                                                            @elseif($invoicePhotoUrl)
                                                                <a href="{{ $invoicePhotoUrl }}" target="_blank" rel="noopener noreferrer">
                                                                    <img src="{{ $invoicePhotoUrl }}" alt="Foto factura" class="img-fluid rounded border" style="max-height: 220px;">
                                                                </a>
                                                            @else
                                                                <div class="small text-muted">Sin foto de factura guardada.</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Kilometraje Salida *</label>
                                        <input id="kilometraje_salida_fuel" type="number" step="0.01" wire:model="kilometraje_salida" class="form-control @error('kilometraje_salida') is-invalid @enderror">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Kilometraje Llegada</label>
                                        <input type="number" step="0.01" wire:model="kilometraje_llegada" class="form-control @error('kilometraje_llegada') is-invalid @enderror">
                                        @error('kilometraje_llegada')
                                        <div class="invalid-feedback d-block">Corrija este campo: {{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Recorrido Inicio *</label>
                                        <div class="input-group">
                                            <input id="recorrido_inicio" type="text" wire:model="recorrido_inicio" class="form-control @error('recorrido_inicio') is-invalid @enderror">
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary location-picker-btn"
                                                data-target-input="recorrido_inicio"
                                                data-target-lat="latitud_inicio"
                                                data-target-lng="logitud_inicio">
                                                <i class="fas fa-map-marker-alt me-1"></i>Elegir en mapa
                                            </button>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-6">
                                                <input id="latitud_inicio" type="text" wire:model.live="latitud_inicio" class="form-control form-control-sm bg-light" placeholder="Latitud" readonly>
                                            </div>
                                            <div class="col-6">
                                                <input id="logitud_inicio" type="text" wire:model.live="logitud_inicio" class="form-control form-control-sm bg-light" placeholder="Longitud" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Recorrido Destino *</label>
                                        <div class="input-group">
                                            <input id="recorrido_destino" type="text" wire:model="recorrido_destino" class="form-control @error('recorrido_destino') is-invalid @enderror">
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary location-picker-btn"
                                                data-target-input="recorrido_destino"
                                                data-target-lat="latitud_destino"
                                                data-target-lng="logitud_destino">
                                                <i class="fas fa-map-marker-alt me-1"></i>Elegir en mapa
                                            </button>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-6">
                                                <input id="latitud_destino" type="text" wire:model.live="latitud_destino" class="form-control form-control-sm bg-light" placeholder="Latitud" readonly>
                                            </div>
                                            <div class="col-6">
                                                <input id="logitud_destino" type="text" wire:model.live="logitud_destino" class="form-control form-control-sm bg-light" placeholder="Longitud" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}</button>
                                    <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4 bp-gestiones-form-card">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="fas fa-qrcode me-2"></i>Escaner QR Factura SIAT</h6>
                            <div class="mb-3">
                                <label for="camera-select" class="form-label fw-bold">Camaras disponibles</label>
                                <select id="camera-select" class="form-select form-select-sm bp-select-like-vehicle">
                                    <option value="">Detectando camaras...</option>
                                </select>
                            </div>
                            <div class="mb-3 d-flex flex-wrap gap-2">
                                <button id="activar-camara-btn" type="button" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-camera me-1"></i>Activar camara
                                </button>
                                <button id="seleccionar-imagen-btn" type="button" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-image me-1"></i>Seleccionar imagen
                                </button>
                                <input id="seleccionar-imagen-input" type="file" accept="image/*" class="d-none">
                            </div>
                            <div id="qr-reader-modal" class="w-100 rounded border bg-light" style="min-height: 320px;"></div>
                            <p id="qr-status" class="mt-3 mb-0">Pulsa "Activar camara" o selecciona una imagen.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xxl-4 d-none fuel-form-map-overlay" id="fuel-form-map-panel">
        <div class="fuel-form-map-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fuel-form-map-meta mb-0" id="fuel-form-map-meta">
                    Seleccion de ubicacion en mapa
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary fuel-form-map-close">
                    Cerrar
                </button>
            </div>
            <div id="fuel-form-side-map" wire:ignore></div>
            <div class="fuel-form-map-help" id="fuel-form-map-help">
                Pulsa "Elegir en mapa" en Inicio o Destino y luego haz clic en el mapa.
            </div>
        </div>
    </div>
    @else
    <div class="fuel-tables-shell">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Vehiculo</th>
                                <th>Conductor</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Km salida</th>
                                <th>Km llegada</th>
                                <th>Inicio</th>
                                <th>Destino</th>
                                <th>Gasolina</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($fuelLogs as $log)
                            <tr>
                                <td>{{ optional($log->vehicle)->display_name ?? '-' }}</td>
                                <td>{{ optional($log->driver)->nombre ?? '-' }}</td>
                                <td>{{ optional($log->invoice?->fecha_emision)->format('d/m/Y H:i') }}</td>
                                <td>
                                    @php
                                        $rowHasInvoicePhoto = !empty($log->invoice?->invoice_photo_path);
                                        $rowStatus = (string) ($log->estado ?? '');
                                        $showDeniedStatus = $rowHasInvoicePhoto && $rowStatus !== 'Verificado';
                                    @endphp
                                    @if(($log->estado ?? null) === 'Verificado')
                                    <span class="badge bg-success">Verificado</span>
                                    @elseif($showDeniedStatus)
                                    <span class="badge bg-danger">Denegado</span>
                                    @else
                                    <span class="badge bg-warning text-dark">Falta verificar</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $log->vehicleLog?->kilometraje_salida ?? '-' }}</td>
                                <td class="text-end">{{ $log->vehicleLog?->kilometraje_llegada ?? '-' }}</td>
                                <td>{{ $log->vehicleLog?->recorrido_inicio ?? '-' }}</td>
                                <td>{{ $log->vehicleLog?->recorrido_destino ?? '-' }}</td>
                                <td>
                                    @php
                                        $kmSalida = $log->vehicleLog?->kilometraje_salida;
                                        $kmLlegada = $log->vehicleLog?->kilometraje_llegada;
                                        $litros = (float) ($log->galones ?? 0);
                                        $kmPorLitro = ($kmSalida !== null && $kmLlegada !== null && $litros > 0 && $kmLlegada >= $kmSalida)
                                            ? round(((float) $kmLlegada - (float) $kmSalida) / $litros, 3)
                                            : null;
                                        $hasSiatPdf = !empty($log->invoice?->siat_document_path);
                                        $hasRolloPdf = !empty($log->invoice?->siat_rollo_document_path);
                                        $hasSiatSource = !empty($log->invoice?->siat_source_url) || !empty($log->invoice?->siat_snapshot_json);
                                        $canViewSiatPdf = $hasSiatPdf || $hasSiatSource;
                                        $canBuildRolloPdf = $hasRolloPdf
                                            || $hasSiatPdf
                                            || !empty($log->invoice?->siat_source_url)
                                            || !empty($log->invoice?->siat_snapshot_json);
                                        $hasInvoicePhoto = !empty($log->invoice?->invoice_photo_path);
                                        $showSiatActions = !$hasInvoicePhoto && ($canViewSiatPdf || $canBuildRolloPdf);
                                        $fuelMeterPhotoPath = data_get($log->invoice?->antifraud_payload_json, 'evidence.fuel_meter_photo_path');
                                        $hasFuelMeterPhoto = !empty($fuelMeterPhotoPath);
                                    @endphp
                                    <div>{{ number_format((float) ($log->galones ?? 0), 2) }} L</div>
                                    <div class="small text-muted">Factura: {{ optional($log->invoice)->numero_factura ?? '-' }}</div>
                                    <div class="small text-muted">Total: BOB{{ number_format((float) $log->total_calculado, 2) }}</div>
                                    <div class="small text-muted">Rendimiento: {{ $kmPorLitro !== null ? number_format($kmPorLitro, 3) . ' km/l' : '-' }}</div>
                                    <div class="small text-muted">Foto factura: {{ $hasInvoicePhoto ? 'registrada' : 'no registrada' }}</div>
                                    <div class="small text-muted">Foto medidor: {{ $hasFuelMeterPhoto ? 'registrada' : 'no registrada' }}</div>
                                    @if(!$hasInvoicePhoto && $canViewSiatPdf)
                                    <div class="mt-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary fuel-view-file-btn"
                                            data-url="{{ route('fuel-invoices.document', $log->invoice) }}"
                                            data-kind="pdf"
                                            data-title="Factura {{ optional($log->invoice)->numero_factura ?? '#' }}"
                                            title="Ver factura PDF SIAT"
                                        >
                                            <i class="fas fa-file-pdf me-1"></i>Ver factura
                                        </button>
                                    </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(!$hasInvoicePhoto && $canViewSiatPdf)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger fuel-view-file-btn"
                                        title="Ver factura PDF SIAT"
                                        data-url="{{ route('fuel-invoices.document', $log->invoice) }}"
                                        data-kind="pdf"
                                        data-title="Factura {{ optional($log->invoice)->numero_factura ?? '#' }}"
                                    >
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <a
                                        href="{{ route('fuel-invoices.document', ['fuelInvoice' => $log->invoice, 'download' => 1]) }}"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Descargar factura PDF SIAT"
                                    >
                                        <i class="fas fa-download"></i>
                                    </a>
                                    @endif
                                    @if(!$hasInvoicePhoto && $canBuildRolloPdf)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary fuel-view-file-btn"
                                        title="Ver factura rollo"
                                        data-url="{{ route('fuel-invoices.rollo', $log->invoice) }}"
                                        data-kind="pdf"
                                        data-title="Factura rollo {{ optional($log->invoice)->numero_factura ?? '#' }}"
                                    >
                                        <i class="fas fa-receipt"></i>
                                    </button>
                                    <a
                                        href="{{ route('fuel-invoices.rollo', ['fuelInvoice' => $log->invoice, 'download' => 1]) }}"
                                        class="btn btn-sm btn-outline-dark"
                                        title="Descargar factura rollo"
                                    >
                                        <i class="fas fa-file-download"></i>
                                    </a>
                                    @endif
                                    @if($hasInvoicePhoto)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary fuel-view-file-btn"
                                        title="Ver foto de factura"
                                        data-url="{{ route('fuel-invoices.photo', $log->invoice) }}"
                                        data-kind="image"
                                        data-title="Foto de factura {{ optional($log->invoice)->numero_factura ?? '#' }}"
                                    >
                                        <i class="fas fa-image"></i>
                                    </button>
                                    @endif
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-dark{{ $hasFuelMeterPhoto ? ' fuel-view-file-btn' : ' disabled' }}"
                                        title="{{ $hasFuelMeterPhoto ? 'Ver foto del medidor' : 'Sin foto del medidor' }}"
                                        data-url="{{ $hasFuelMeterPhoto ? route('fuel-invoices.meter-photo', $log->invoice) : '' }}"
                                        data-kind="image"
                                        data-title="Foto del medidor {{ optional($log->invoice)->numero_factura ?? '#' }}"
                                        {{ $hasFuelMeterPhoto ? '' : 'disabled aria-disabled=true' }}
                                    >
                                        <i class="fas fa-tachometer-alt"></i>
                                    </button>
                                    <a
                                        href="{{ $log->vehicleLog ? route('vehicle-logs.map', $log->vehicleLog->id) : '#' }}"
                                        class="btn btn-sm btn-outline-info{{ $log->vehicleLog ? '' : ' disabled' }}"
                                        title="{{ $log->vehicleLog ? 'Ver recorrido en mapa' : 'Sin bitacora vinculada' }}"
                                        {{ $log->vehicleLog ? '' : 'aria-disabled=true tabindex=-1' }}>
                                        <i class="fas fa-map-marked-alt"></i>
                                    </a>
                                    @if(auth()->user()?->role !== 'conductor' && ($log->estado ?? '') !== 'Verificado')
                                    <button wire:click="markAsVerificado({{ $log->id }})" class="btn btn-sm btn-outline-success" title="Marcar como verificado">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    @endif
                                    @if(auth()->user()?->role !== 'conductor')
                                    <button wire:click="edit({{ $log->id }})" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></button>
                                    <button wire:click="delete({{ $log->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">Sin registros de combustible</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                {{ $fuelLogs->links() }}
            </div>
        </div>
    </div>
    @endif

    <div class="modal fade" id="locationPickerModal" wire:ignore.self tabindex="-1" aria-labelledby="locationPickerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationPickerLabel">
                        <i class="fas fa-map-marked-alt me-2"></i>Seleccionar ubicacion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Haz clic en el mapa para seleccionar un punto.</p>
                    <div id="location-map" wire:ignore></div>
                    <div class="mt-3 small text-muted" id="selected-location-preview">Sin ubicacion seleccionada.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirm-location-btn">Usar ubicacion</button>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function() {
            if (window.__fuelKmSyncInitialized) return;
            window.__fuelKmSyncInitialized = true;

            function syncKmSalida(selectId, inputId) {
                const select = document.getElementById(selectId);
                const input = document.getElementById(inputId);
                if (!select || !input) return;

                const selected = select.options[select.selectedIndex];
                if (!selected) return;

                const km = selected.getAttribute('data-km-actual');
                input.value = km !== null && km !== '' ? km : '';
                input.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                input.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
            }

            function syncActiveForm() {
                syncKmSalida('vehicle_id_bitacora', 'kilometraje_salida_bitacora');
                syncKmSalida('vehicle_id_fuel', 'kilometraje_salida_fuel');
            }

            document.addEventListener('change', function(event) {
                const target = event.target;
                if (!(target instanceof HTMLSelectElement)) return;

                if (target.id === 'vehicle_id_bitacora') {
                    syncKmSalida('vehicle_id_bitacora', 'kilometraje_salida_bitacora');
                } else if (target.id === 'vehicle_id_fuel') {
                    syncKmSalida('vehicle_id_fuel', 'kilometraje_salida_fuel');
                }
            });

            document.addEventListener('livewire:navigated', syncActiveForm);
            document.addEventListener('livewire:initialized', syncActiveForm);
            setTimeout(syncActiveForm, 60);
        })();
    </script>
    <script>
        (function() {
            if (window.__fuelQrBindingsInitialized) return;
            window.__fuelQrBindingsInitialized = true;

            const componentId = '{{ $this->getId() }}';
            let scanner = null;
            let running = false;
            let isHandlingDecode = false;
            let cameraDevices = [];
            let cameraRefreshTimer = null;

            const getStatus = () => document.getElementById('qr-status');
            const getCameraBtn = () => document.getElementById('activar-camara-btn');
            const getCameraSelect = () => document.getElementById('camera-select');
            const getImageBtn = () => document.getElementById('seleccionar-imagen-btn');
            const getImageInput = () => document.getElementById('seleccionar-imagen-input');

            function setStatus(text) {
                const el = getStatus();
                if (el) el.textContent = text;
            }

            function setCameraButtonActive(active) {
                const button = getCameraBtn();
                if (!button) return;

                if (active) {
                    button.innerHTML = '<i class="fas fa-stop me-1"></i>Desactivar camara';
                    button.classList.remove('btn-outline-primary');
                    button.classList.add('btn-danger');
                    return;
                }

                button.innerHTML = '<i class="fas fa-camera me-1"></i>Activar camara';
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-primary');
            }

            function getLivewireComponent() {
                if (!window.Livewire) return null;
                return window.Livewire.find(componentId);
            }

            function applyFieldsToForm(fields) {
                if (!fields || typeof fields !== 'object') return;

                Object.entries(fields).forEach(([id, value]) => {
                    if (value === undefined || value === null) return;
                    const el = document.getElementById(id);
                    if (!el) return;

                    el.value = value;
                    el.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    el.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                });
            }

            async function stopScanner() {
                if (!scanner || !running) return;
                try {
                    await scanner.stop();
                    await scanner.clear();
                } catch (_) {} finally {
                    running = false;
                    setCameraButtonActive(false);
                }
            }

            async function ensureScanner() {
                if (!window.Html5Qrcode) {
                    setStatus('No se pudo cargar html5-qrcode.');
                    return false;
                }

                if (!document.getElementById('qr-reader-modal')) {
                    return false;
                }

                if (!scanner) {
                    scanner = new Html5Qrcode('qr-reader-modal');
                }

                return true;
            }

            async function loadCameras() {
                const select = getCameraSelect();
                if (!select || !window.Html5Qrcode) return;

                try {
                    cameraDevices = await Html5Qrcode.getCameras();
                } catch (_) {
                    cameraDevices = [];
                }

                select.innerHTML = '';
                if (!cameraDevices.length) {
                    select.innerHTML = '<option value="">No se detectaron camaras</option>';
                    select.disabled = true;
                    return;
                }

                cameraDevices.forEach((camera, index) => {
                    const option = document.createElement('option');
                    option.value = camera.id;
                    option.textContent = camera.label || ('Camara ' + (index + 1));
                    select.appendChild(option);
                });
                select.disabled = false;
            }

            function startCameraRefreshLoop() {
                if (cameraRefreshTimer) return;
                cameraRefreshTimer = setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        loadCameras();
                    }
                }, 8000);
            }

            function stopCameraRefreshLoop() {
                if (!cameraRefreshTimer) return;
                clearInterval(cameraRefreshTimer);
                cameraRefreshTimer = null;
            }

            async function procesarTextoQR(decodedText) {
                if (!/^https?:\/\//i.test(decodedText || '')) {
                    setStatus('QR invalido. Debe contener una URL.');
                    return false;
                }

                const component = getLivewireComponent();
                if (!component) {
                    setStatus('No se encontro el componente Livewire activo.');
                    return false;
                }

                setStatus('Consultando SIAT...');
                const result = await component.call('procesarQR', decodedText);
                if (result && result.ok) {
                    applyFieldsToForm(result.fields || {});
                    setStatus(result.message || 'Factura procesada correctamente.');
                    return true;
                }

                setStatus((result && result.message) ? result.message : 'No se pudo consultar SIAT.');
                return false;
            }

            async function iniciarCamara() {
                const canContinue = await ensureScanner();
                if (!canContinue || running) return;

                try {
                    isHandlingDecode = false;
                    setStatus('Camara activa. Escaneando QR...');
                    const selectedCameraId = getCameraSelect() ? getCameraSelect().value : '';

                    await scanner.start(
                        selectedCameraId || {
                            facingMode: 'environment'
                        }, {
                            fps: 10,
                            qrbox: {
                                width: 260,
                                height: 260
                            }
                        },
                        async (decodedText) => {
                                if (isHandlingDecode) return;
                                isHandlingDecode = true;
                                await procesarTextoQR(decodedText);
                                await stopScanner();
                            },
                            () => {}
                    );

                    running = true;
                    setCameraButtonActive(true);
                } catch (_) {
                    setStatus('No se pudo acceder a la camara.');
                }
            }

            async function procesarImagen(file) {
                const canContinue = await ensureScanner();
                if (!canContinue || !file) return;

                await stopScanner();
                setStatus('Analizando imagen...');

                const imageButton = getImageBtn();
                const imageInput = getImageInput();
                if (imageButton) imageButton.disabled = true;

                let decodedText = null;
                const decodeRoute = "{{ route('api.qr.decode') }}";
                try {
                    if (!scanner || typeof scanner.scanFile !== 'function') {
                        throw new Error('scanner_unavailable');
                    }

                    decodedText = await scanner.scanFile(file, true);
                } catch (scanError) {
                    try {
                        const formData = new FormData();
                        formData.append('image', file);

                        const response = await fetch(decodeRoute, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: formData
                        });

                        const rawBody = await response.text();
                        let payload = null;

                        try {
                            payload = rawBody ? JSON.parse(rawBody) : null;
                        } catch (_) {
                            payload = null;
                        }

                        if (response.ok && payload?.success && payload?.data?.qr_text) {
                            decodedText = payload.data.qr_text;
                        } else if (payload?.message) {
                            setStatus(payload.message);
                        } else if (!response.ok) {
                            setStatus(`No se pudo decodificar el QR desde el servidor (HTTP ${response.status}).`);
                        } else {
                            setStatus('La imagen no contiene un QR valido o el servidor devolvio una respuesta inesperada.');
                        }
                    } catch (serverDecodeError) {
                        if (scanError?.message === 'scanner_unavailable') {
                            setStatus('No se pudo inicializar el lector QR del navegador ni el decodificador del servidor.');
                        } else {
                            setStatus('No se pudo procesar la imagen ni localmente ni en el servidor.');
                        }
                    }
                } finally {
                    if (imageButton) imageButton.disabled = false;
                    if (imageInput) imageInput.value = '';
                }

                if (!decodedText) {
                    setStatus('No se pudo decodificar el QR de la imagen.');
                    return;
                }

                const ok = await procesarTextoQR(decodedText);
                if (ok) setStatus('QR procesado correctamente.');
            }

            document.addEventListener('click', async (event) => {
                const cameraButton = event.target.closest('#activar-camara-btn');
                if (cameraButton) {
                    if (running) {
                        await stopScanner();
                        setStatus('Camara desactivada.');
                        return;
                    }
                    await iniciarCamara();
                    return;
                }

                const imageButton = event.target.closest('#seleccionar-imagen-btn');
                if (imageButton) {
                    const input = getImageInput();
                    if (input) input.click();
                }
            });

            document.addEventListener('change', async (event) => {
                const input = event.target;
                if (!(input instanceof HTMLInputElement)) return;
                if (input.id !== 'seleccionar-imagen-input') return;

                const file = input.files && input.files[0];
                await procesarImagen(file);
            });

            window.addEventListener('beforeunload', async () => {
                stopCameraRefreshLoop();
                await stopScanner();
            });

            if (navigator.mediaDevices && typeof navigator.mediaDevices.addEventListener === 'function') {
                navigator.mediaDevices.addEventListener('devicechange', loadCameras);
            }

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    loadCameras();
                }
            });

            document.addEventListener('livewire:navigated', loadCameras);
            setTimeout(loadCameras, 300);
            startCameraRefreshLoop();
        })();
    </script>
    <script>
        (function() {
            if (!window.__fuelMapPickerState) {
                window.__fuelMapPickerState = {
                    map: null,
                    marker: null,
                    selected: null,
                    targetInputId: null,
                    targetLatId: null,
                    targetLngId: null,
                    activeTarget: null,
                    handlersBound: false,
                };
            }

            const state = window.__fuelMapPickerState;
            const componentId = '{{ $this->getId() }}';
            const BOLIVIA_BOUNDS = L.latLngBounds(
                L.latLng(-22.95, -69.75),
                L.latLng(-9.55, -57.35)
            );
            let detectedUserLocation = null;
            let detectedNearestPlace = null;
            let formSideMap = null;
            let formTargetMarkers = {};
            let formTargetLine = null;

            function getModalEl() {
                return document.getElementById('locationPickerModal');
            }

            function getPreviewEl() {
                return document.getElementById('selected-location-preview');
            }

            function getFormMapElements() {
                return {
                    panelEl: document.getElementById('fuel-form-map-panel'),
                    mapEl: document.getElementById('fuel-form-side-map'),
                    metaEl: document.getElementById('fuel-form-map-meta'),
                    helpEl: document.getElementById('fuel-form-map-help'),
                };
            }

            function getConfirmBtn() {
                return document.getElementById('confirm-location-btn');
            }

            function getMapLivewireComponent() {
                if (!window.Livewire || !componentId) return null;
                return window.Livewire.find(componentId);
            }

            function updatePreview(text) {
                const previewEl = getPreviewEl();
                if (previewEl) previewEl.textContent = text;
            }

            function getModalInstance(el) {
                if (!el) return null;

                if (window.bootstrap && window.bootstrap.Modal) {
                    if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                        return window.bootstrap.Modal.getOrCreateInstance(el);
                    }
                    if (typeof window.bootstrap.Modal.getInstance === 'function') {
                        return window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                    }
                    return new window.bootstrap.Modal(el);
                }

                if (window.jQuery) {
                    return {
                        show: () => window.jQuery(el).modal('show'),
                        hide: () => window.jQuery(el).modal('hide'),
                    };
                }

                return null;
            }

            function scheduleMapResize() {
                setTimeout(() => state.map && state.map.invalidateSize(), 80);
                setTimeout(() => state.map && state.map.invalidateSize(), 180);
                setTimeout(() => state.map && state.map.invalidateSize(), 320);
            }

            function scheduleFormSideMapResize() {
                setTimeout(() => formSideMap && formSideMap.invalidateSize(), 80);
                setTimeout(() => formSideMap && formSideMap.invalidateSize(), 180);
                setTimeout(() => formSideMap && formSideMap.invalidateSize(), 320);
            }

            function destroyMap() {
                if (!state.map) return;
                try {
                    state.map.off();
                    state.map.remove();
                } catch (_) {}
                state.map = null;
                state.marker = null;
            }

            function destroyFormSideMap() {
                if (!formSideMap) return;
                try {
                    formSideMap.off();
                    formSideMap.remove();
                } catch (_) {}
                formSideMap = null;
                formTargetMarkers = {};
                formTargetLine = null;
            }

            function resolveTargetLabel(inputId) {
                if (!inputId) return 'ubicacion';
                if (inputId.includes('inicio')) return 'Inicio';
                if (inputId.includes('destino')) return 'Destino';
                return 'ubicacion';
            }

            function buildTargetFromInputId(inputId) {
                if (!inputId) return null;
                if (inputId === 'recorrido_inicio') {
                    return {
                        inputId,
                        latId: 'latitud_inicio',
                        lngId: 'logitud_inicio'
                    };
                }
                if (inputId === 'recorrido_destino') {
                    return {
                        inputId,
                        latId: 'latitud_destino',
                        lngId: 'logitud_destino'
                    };
                }
                if (inputId === 'recorrido_inicio_bitacora') {
                    return {
                        inputId,
                        latId: 'latitud_inicio_bitacora',
                        lngId: 'logitud_inicio_bitacora'
                    };
                }
                if (inputId === 'recorrido_destino_bitacora') {
                    return {
                        inputId,
                        latId: 'latitud_destino_bitacora',
                        lngId: 'logitud_destino_bitacora'
                    };
                }
                return null;
            }

            function setActiveTarget(target) {
                if (!target?.inputId) return null;
                state.activeTarget = {
                    inputId: target.inputId,
                    latId: target.latId || null,
                    lngId: target.lngId || null,
                };
                state.targetInputId = state.activeTarget.inputId;
                state.targetLatId = state.activeTarget.latId;
                state.targetLngId = state.activeTarget.lngId;
                return state.activeTarget;
            }

            function fieldHasCoordinates(latId, lngId) {
                const lat = (document.getElementById(latId)?.value || '').trim();
                const lng = (document.getElementById(lngId)?.value || '').trim();
                return lat !== '' && lng !== '';
            }

            function resolveEffectiveTarget() {
                if (state.activeTarget?.inputId) {
                    const inputId = state.activeTarget.inputId;
                    if (inputId.includes('_bitacora')) {
                        const inicioFilled = fieldHasCoordinates('latitud_inicio_bitacora', 'logitud_inicio_bitacora');
                        const destinoFilled = fieldHasCoordinates('latitud_destino_bitacora', 'logitud_destino_bitacora');
                        if (!inicioFilled && destinoFilled) return setActiveTarget(buildTargetFromInputId('recorrido_inicio_bitacora'));
                        if (inicioFilled && !destinoFilled) return setActiveTarget(buildTargetFromInputId('recorrido_destino_bitacora'));
                    } else {
                        const inicioFilled = fieldHasCoordinates('latitud_inicio', 'logitud_inicio');
                        const destinoFilled = fieldHasCoordinates('latitud_destino', 'logitud_destino');
                        if (!inicioFilled && destinoFilled) return setActiveTarget(buildTargetFromInputId('recorrido_inicio'));
                        if (inicioFilled && !destinoFilled) return setActiveTarget(buildTargetFromInputId('recorrido_destino'));
                    }
                    return state.activeTarget;
                }
                return null;
            }

            function getNextTarget(currentInputId) {
                if (currentInputId === 'recorrido_inicio') return buildTargetFromInputId('recorrido_destino');
                if (currentInputId === 'recorrido_destino') return buildTargetFromInputId('recorrido_inicio');
                if (currentInputId === 'recorrido_inicio_bitacora') return buildTargetFromInputId('recorrido_destino_bitacora');
                if (currentInputId === 'recorrido_destino_bitacora') return buildTargetFromInputId('recorrido_inicio_bitacora');
                return null;
            }

            function updateFormMapStatus() {
                const {
                    metaEl,
                    helpEl
                } = getFormMapElements();
                const targetLabel = resolveTargetLabel(state.activeTarget?.inputId);
                if (metaEl) metaEl.textContent = `Seleccionando ubicacion para: ${targetLabel}`;
                if (helpEl) helpEl.textContent = `Haz clic en el mapa para asignar la ubicacion de ${targetLabel}.`;
            }

            function formatNearestPlaceFromNominatim(data) {
                if (!data || typeof data !== 'object') return null;
                const address = data.address || {};
                const road =
                    address.road ||
                    address.pedestrian ||
                    address.footway ||
                    address.path ||
                    address.cycleway ||
                    address.residential ||
                    '';
                const houseNumber = address.house_number || '';
                const neighbourhood =
                    address.neighbourhood ||
                    address.suburb ||
                    address.quarter ||
                    address.city_district ||
                    address.borough ||
                    '';
                const locality =
                    address.city ||
                    address.town ||
                    address.village ||
                    address.municipality ||
                    address.county ||
                    address.state_district ||
                    address.state ||
                    '';

                const primaryLine = [road, houseNumber].filter(Boolean).join(' ');
                const secondaryLine = [neighbourhood, locality].filter(Boolean).join(', ');
                const composed = [primaryLine, secondaryLine, 'Bolivia'].filter(Boolean).join(', ');

                if (primaryLine) return composed;
                if (secondaryLine) return `${secondaryLine}, Bolivia`;

                if (typeof data.display_name === 'string' && data.display_name.trim() !== '') {
                    const displayParts = data.display_name
                        .split(',')
                        .map((part) => part.trim())
                        .filter(Boolean);
                    return displayParts.slice(0, 4).join(', ');
                }

                return null;
            }

            async function reverseGeocode(lat, lng) {
                try {
                    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&countrycodes=bo&accept-language=es&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`;
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!response.ok) return null;
                    const data = await response.json();
                    return formatNearestPlaceFromNominatim(data);
                } catch (_) {
                    return null;
                }
            }

            function resolveUserLocation() {
                if (detectedUserLocation) {
                    return Promise.resolve(detectedUserLocation);
                }
                if (!navigator.geolocation) {
                    return Promise.resolve(null);
                }

                return new Promise((resolve) => {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const lat = Number.parseFloat(position.coords?.latitude);
                            const lng = Number.parseFloat(position.coords?.longitude);
                            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                                resolve(null);
                                return;
                            }
                            if (!BOLIVIA_BOUNDS.contains(L.latLng(lat, lng))) {
                                resolve(null);
                                return;
                            }
                            detectedUserLocation = {
                                lat,
                                lng
                            };
                            resolve(detectedUserLocation);
                        },
                        () => resolve(null), {
                            enableHighAccuracy: true,
                            timeout: 7000,
                            maximumAge: 300000
                        }
                    );
                });
            }

            function centerMapNearUser(targetMap, updateText) {
                if (!targetMap) return;
                resolveUserLocation().then(async (coords) => {
                    if (!coords || !targetMap) return;
                    targetMap.setView([coords.lat, coords.lng], 12);
                    const nearest = await reverseGeocode(coords.lat, coords.lng);
                    if (nearest) {
                        detectedNearestPlace = nearest;
                        if (typeof updateText === 'function') {
                            updateText(`Ubicacion actual detectada cerca de: ${nearest}`);
                        }
                    }
                });
            }


            function setInputValue(inputId, value) {
                const input = document.getElementById(inputId);
                if (!input) return;
                input.value = value;
                input.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                input.dispatchEvent(new Event('change', {
                    bubbles: true
                }));

                const component = getMapLivewireComponent();
                if (!component) return;

                const model =
                    input.getAttribute('wire:model') ||
                    input.getAttribute('wire:model.live') ||
                    input.getAttribute('wire:model.lazy') ||
                    input.getAttribute('wire:model.defer');

                if (model) component.set(model, value);
            }

            function pushCoordinatesToLivewire(lat, lng, latInputId, lngInputId) {
                const component = getMapLivewireComponent();
                if (!component) return;

                const latValue = Number.parseFloat(lat);
                const lngValue = Number.parseFloat(lng);
                if (!Number.isFinite(latValue) || !Number.isFinite(lngValue)) return;

                const latMap = {
                    latitud_inicio: 'latitud_inicio',
                    latitud_inicio_bitacora: 'latitud_inicio',
                    latitud_destino: 'latitud_destino',
                    latitud_destino_bitacora: 'latitud_destino',
                };
                const lngMap = {
                    logitud_inicio: 'logitud_inicio',
                    logitud_inicio_bitacora: 'logitud_inicio',
                    logitud_destino: 'logitud_destino',
                    logitud_destino_bitacora: 'logitud_destino',
                };

                if (latInputId && latMap[latInputId]) component.set(latMap[latInputId], latValue);
                if (lngInputId && lngMap[lngInputId]) component.set(lngMap[lngInputId], lngValue);
            }

            function initMapIfNeeded() {
                if (!window.L) return;
                const mapContainer = document.getElementById('location-map');
                if (!mapContainer) return;

                if (state.map) {
                    const currentContainer = state.map.getContainer ? state.map.getContainer() : null;
                    if (currentContainer !== mapContainer) {
                        destroyMap();
                    } else {
                        scheduleMapResize();
                        return;
                    }
                }

                state.map = L.map(mapContainer, {
                    maxBounds: BOLIVIA_BOUNDS,
                    maxBoundsViscosity: 1.0,
                }).setView([-16.5, -68.15], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(state.map);
                state.map.fitBounds(BOLIVIA_BOUNDS);
                centerMapNearUser(state.map, updatePreview);

                state.map.on('click', async function(e) {
                    if (!BOLIVIA_BOUNDS.contains(e.latlng)) {
                        updatePreview('Solo se permiten ubicaciones dentro de Bolivia.');
                        return;
                    }

                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;

                    if (!state.marker) state.marker = L.marker([lat, lng]).addTo(state.map);
                    else state.marker.setLatLng([lat, lng]);

                    updatePreview('Buscando nombre del lugar...');
                    const placeName = await reverseGeocode(lat, lng);
                    state.selected = {
                        lat: lat,
                        lng: lng,
                        name: placeName || 'Ubicacion seleccionada (Bolivia)'
                    };
                    updatePreview(`${state.selected.name} (${lat.toFixed(6)}, ${lng.toFixed(6)})`);
                });
            }

            function ensureFormSideMap() {
                const {
                    mapEl,
                    helpEl
                } = getFormMapElements();
                if (!window.L || !mapEl) return;

                if (formSideMap) {
                    const currentContainer = formSideMap.getContainer ? formSideMap.getContainer() : null;
                    if (currentContainer !== mapEl) {
                        destroyFormSideMap();
                    } else {
                        scheduleFormSideMapResize();
                        return;
                    }
                }

                formSideMap = L.map(mapEl, {
                    maxBounds: BOLIVIA_BOUNDS,
                    maxBoundsViscosity: 1.0,
                }).setView([-16.5, -68.15], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(formSideMap);
                formSideMap.fitBounds(BOLIVIA_BOUNDS);
                centerMapNearUser(formSideMap, (text) => {
                    if (helpEl) helpEl.textContent = text;
                });

                formSideMap.on('click', async function(e) {
                    const target = resolveEffectiveTarget() || state.activeTarget;
                    if (!target?.inputId) {
                        if (helpEl) helpEl.textContent = 'Primero pulsa "Elegir en mapa" en Inicio o Destino.';
                        return;
                    }
                    if (!BOLIVIA_BOUNDS.contains(e.latlng)) {
                        if (helpEl) helpEl.textContent = 'Solo se permiten ubicaciones dentro de Bolivia.';
                        return;
                    }

                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;
                    const markerKey = target.inputId;
                    if (!formTargetMarkers[markerKey]) {
                        formTargetMarkers[markerKey] = L.marker([lat, lng]).addTo(formSideMap);
                    } else {
                        formTargetMarkers[markerKey].setLatLng([lat, lng]);
                    }

                    const placeName = await reverseGeocode(lat, lng);
                    const name = placeName || 'Ubicacion seleccionada (Bolivia)';
                    setInputValue(target.inputId, name);
                    if (target.latId) setInputValue(target.latId, lat.toFixed(8));
                    if (target.lngId) setInputValue(target.lngId, lng.toFixed(8));
                    pushCoordinatesToLivewire(lat, lng, target.latId, target.lngId);

                    const startKey = target.inputId.includes('_bitacora') ? 'recorrido_inicio_bitacora' : 'recorrido_inicio';
                    const endKey = target.inputId.includes('_bitacora') ? 'recorrido_destino_bitacora' : 'recorrido_destino';
                    const startMarker = formTargetMarkers[startKey];
                    const endMarker = formTargetMarkers[endKey];
                    if (startMarker && endMarker) {
                        const points = [startMarker.getLatLng(), endMarker.getLatLng()];
                        if (!formTargetLine) {
                            formTargetLine = L.polyline(points, {
                                color: '#2563eb',
                                weight: 3,
                                opacity: 0.9
                            }).addTo(formSideMap);
                        } else {
                            formTargetLine.setLatLngs(points);
                        }
                    }

                    const nextTarget = getNextTarget(target.inputId);
                    if (nextTarget) setActiveTarget(nextTarget);

                    if (helpEl) {
                        const label = resolveTargetLabel(target.inputId);
                        const nextLabel = resolveTargetLabel(state.activeTarget?.inputId);
                        helpEl.textContent = `${label}: ${name} (${lat.toFixed(6)}, ${lng.toFixed(6)}). Siguiente: ${nextLabel}.`;
                    }
                    updateFormMapStatus();
                });

                scheduleFormSideMapResize();
            }

            function bindHandlersOnce() {
                if (state.handlersBound) return;
                state.handlersBound = true;

                const modalEl = getModalEl();
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }

                document.addEventListener('click', function(event) {
                    const btn = event.target.closest('.location-picker-btn');
                    if (!btn) return;

                    const target = {
                        inputId: btn.getAttribute('data-target-input'),
                        latId: btn.getAttribute('data-target-lat'),
                        lngId: btn.getAttribute('data-target-lng'),
                    };
                    setActiveTarget(target);
                    state.selected = null;
                    updatePreview(`Seleccionando ${resolveTargetLabel(state.activeTarget?.inputId)}. Haz clic en el mapa.`);

                    if (state.marker && state.map) {
                        state.map.removeLayer(state.marker);
                        state.marker = null;
                    }

                    const {
                        panelEl,
                        mapEl
                    } = getFormMapElements();
                    const fromForm = !!btn.closest('form');
                    if (fromForm && mapEl) {
                        if (panelEl) panelEl.classList.remove('d-none');
                        destroyFormSideMap();
                        ensureFormSideMap();
                        resolveEffectiveTarget();
                        updateFormMapStatus();
                        scheduleFormSideMapResize();
                        return;
                    }

                    // Recrear mapa en cada apertura evita contenedor "en blanco" tras re-render de Livewire.
                    destroyMap();

                    const modalEl = getModalEl();
                    if (!modalEl) return;
                    const modal = getModalInstance(modalEl);
                    if (!modal) return;
                    modal.show();
                });

                document.addEventListener('click', function(event) {
                    const closePanelBtn = event.target.closest('.fuel-form-map-close');
                    if (!closePanelBtn) return;
                    const {
                        panelEl,
                        metaEl,
                        helpEl
                    } = getFormMapElements();
                    if (panelEl) panelEl.classList.add('d-none');
                    if (metaEl) metaEl.textContent = 'Seleccion de ubicacion en mapa';
                    if (helpEl) helpEl.textContent = 'Pulsa "Elegir en mapa" en Inicio o Destino y luego haz clic en el mapa.';
                    state.activeTarget = null;
                });

                document.addEventListener('shown.bs.modal', function(event) {
                    if (event.target && event.target.id === 'locationPickerModal') {
                        initMapIfNeeded();
                        scheduleMapResize();
                        if (!window.L) {
                            updatePreview('No se pudo cargar el mapa (Leaflet). Recarga con Ctrl+F5.');
                        }
                    }
                });

                document.addEventListener('hidden.bs.modal', function(event) {
                    if (event.target && event.target.id === 'locationPickerModal') {
                        state.selected = null;
                    }
                });

                document.addEventListener('click', function(event) {
                    const btn = event.target.closest('#confirm-location-btn');
                    if (!btn) return;

                    const target = resolveEffectiveTarget() || state.activeTarget;
                    if (!state.selected || !target?.inputId) {
                        updatePreview('Selecciona un punto en el mapa primero.');
                        return;
                    }
                    if (!BOLIVIA_BOUNDS.contains(L.latLng(state.selected.lat, state.selected.lng))) {
                        updatePreview('Solo se permiten ubicaciones dentro de Bolivia.');
                        return;
                    }

                    const text = state.selected.name;
                    setInputValue(target.inputId, text);
                    if (target.latId) setInputValue(target.latId, state.selected.lat.toFixed(8));
                    if (target.lngId) setInputValue(target.lngId, state.selected.lng.toFixed(8));
                    pushCoordinatesToLivewire(state.selected.lat, state.selected.lng, target.latId, target.lngId);

                    const nextTarget = getNextTarget(target.inputId);
                    if (nextTarget) {
                        setActiveTarget(nextTarget);
                        updatePreview(`Seleccion guardada para ${resolveTargetLabel(target.inputId)}. Ahora puedes elegir ${resolveTargetLabel(nextTarget.inputId)}.`);
                    }

                    const modalEl = getModalEl();
                    if (!modalEl) return;
                    const modal = getModalInstance(modalEl);
                    modal.hide();
                });

                document.addEventListener('livewire:navigated', function() {
                    destroyMap();
                    destroyFormSideMap();
                    state.selected = null;
                    state.activeTarget = null;
                    state.targetInputId = null;
                    state.targetLatId = null;
                    state.targetLngId = null;
                });
            }

            bindHandlersOnce();
        })();
    </script>
    <script>
        (function() {
            if (window.__fuelPdfDownloadInit) return;
            window.__fuelPdfDownloadInit = true;

            function parseFilenameFromDisposition(disposition) {
                if (!disposition) return 'bitacora-combustible.pdf';
                const match = disposition.match(/filename="?([^";]+)"?/i);
                if (!match || !match[1]) return 'bitacora-combustible.pdf';
                return match[1];
            }

            async function handleDownload(button) {
                const url = button?.getAttribute('data-url');
                if (!url) return;

                button.disabled = true;
                try {
                    if (!('showSaveFilePicker' in window)) {
                        const anchor = document.createElement('a');
                        anchor.href = url;
                        anchor.download = 'bitacora-combustible.pdf';
                        anchor.rel = 'noopener';
                        anchor.style.display = 'none';
                        document.body.appendChild(anchor);
                        anchor.click();
                        anchor.remove();
                        return;
                    }

                    const response = await fetch(url, {
                        credentials: 'same-origin'
                    });
                    if (!response.ok) throw new Error('No se pudo descargar el PDF.');

                    const blob = await response.blob();
                    const filename = parseFilenameFromDisposition(response.headers.get('Content-Disposition'));

                    const fileHandle = await window.showSaveFilePicker({
                        suggestedName: filename,
                        types: [{
                            description: 'Archivo PDF',
                            accept: {
                                'application/pdf': ['.pdf']
                            }
                        }]
                    });

                    const writable = await fileHandle.createWritable();
                    await writable.write(blob);
                    await writable.close();
                } catch (error) {
                    if (error && (error.name === 'AbortError' || error.name === 'NotAllowedError')) {
                        return;
                    }
                    alert('No se pudo generar el PDF en este momento. Intenta nuevamente.');
                } finally {
                    button.disabled = false;
                }
            }

            document.addEventListener('click', function(event) {
                const button = event.target.closest('.download-pdf-btn');
                if (!button) return;
                event.preventDefault();
                handleDownload(button);
            });
        })();
    </script>
    <script>
        (function() {
            if (window.__fuelFileViewerInit) return;
            window.__fuelFileViewerInit = true;

            function getModalInstance(el) {
                if (!el) return null;

                if (window.bootstrap && window.bootstrap.Modal) {
                    if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                        return window.bootstrap.Modal.getOrCreateInstance(el);
                    }
                    if (typeof window.bootstrap.Modal.getInstance === 'function') {
                        return window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                    }
                    return new window.bootstrap.Modal(el);
                }

                if (window.jQuery) {
                    return {
                        show: () => window.jQuery(el).modal('show'),
                        hide: () => window.jQuery(el).modal('hide'),
                    };
                }

                return null;
            }

            function bootFuelFileViewer() {
                const modalEl = document.getElementById('fuelFileViewerModal');
                if (!modalEl || modalEl.dataset.viewerReady === '1') return;

                modalEl.dataset.viewerReady = '1';

                if (modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }

                const titleEl = document.getElementById('fuelFileViewerLabel');
                const imageEl = document.getElementById('fuel-file-viewer-image');
                const frameEl = document.getElementById('fuel-file-viewer-frame');
                const emptyEl = document.getElementById('fuel-file-viewer-empty');
                const loadingEl = document.getElementById('fuel-file-viewer-loading');

                function resetViewer(showLoading = false) {
                    imageEl?.classList.add('d-none');
                    frameEl?.classList.add('d-none');
                    emptyEl?.classList.add('d-none');
                    loadingEl?.classList.toggle('d-none', !showLoading);
                    imageEl?.removeAttribute('src');
                    frameEl?.removeAttribute('src');
                }

                imageEl?.addEventListener('load', function() {
                    loadingEl?.classList.add('d-none');
                    emptyEl?.classList.add('d-none');
                    imageEl.classList.remove('d-none');
                });

                imageEl?.addEventListener('error', function() {
                    loadingEl?.classList.add('d-none');
                    imageEl.classList.add('d-none');
                    emptyEl?.classList.remove('d-none');
                });

                frameEl?.addEventListener('load', function() {
                    loadingEl?.classList.add('d-none');
                    emptyEl?.classList.add('d-none');
                    frameEl.classList.remove('d-none');
                });

                frameEl?.addEventListener('error', function() {
                    loadingEl?.classList.add('d-none');
                    frameEl.classList.add('d-none');
                    emptyEl?.classList.remove('d-none');
                });

                modalEl.addEventListener('hidden.bs.modal', function() {
                    resetViewer(false);
                });

                document.addEventListener('click', function(event) {
                    const btn = event.target.closest('.fuel-view-file-btn');
                    if (!btn) return;

                    const url = btn.getAttribute('data-url') || '';
                    const kind = btn.getAttribute('data-kind') || 'image';
                    const title = btn.getAttribute('data-title') || 'Archivo';

                    if (titleEl) {
                        titleEl.textContent = title;
                    }

                    resetViewer(Boolean(url));

                    if (!url) {
                        loadingEl?.classList.add('d-none');
                        emptyEl?.classList.remove('d-none');
                    } else if (kind === 'pdf') {
                        if (frameEl) {
                            frameEl.src = url;
                        }
                    } else if (imageEl) {
                        imageEl.src = url;
                    }

                    const modal = getModalInstance(modalEl);
                    if (modal) {
                        modal.show();
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootFuelFileViewer, { once: true });
            } else {
                bootFuelFileViewer();
            }

            document.addEventListener('livewire:navigated', bootFuelFileViewer);
        })();
    </script>
    @if(auth()->user()?->role !== 'conductor')
    <div class="modal fade" id="fuelDocumentModal" tabindex="-1" aria-labelledby="fuelDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fuelDocumentModalLabel">Generar documento de gasolina</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Elige el tipo de documento, el rango de fechas y los campos exactos que quieres incluir para obtener solo la informacion que necesitas.
                    </p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Accion</label>
                            <select class="form-select bp-select-like-vehicle" id="fuel-report-action">
                                <option value="print_pdf">Imprimir PDF</option>
                                <option value="download_pdf">Descargar PDF</option>
                                <option value="download_excel">Descargar Excel</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Fecha desde</label>
                            <input type="date" class="form-control" id="fuel-report-fecha-desde" value="{{ $fecha_desde }}">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Fecha hasta</label>
                            <input type="date" class="form-control" id="fuel-report-fecha-hasta" value="{{ $fecha_hasta }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Filtrar por</label>
                            <select class="form-select bp-select-like-vehicle" id="fuel-report-scope">
                                <option value="all">Todos los registros</option>
                                <option value="vehicle" {{ $vehicle_filter_id ? 'selected' : '' }}>Vehiculo especifico</option>
                                <option value="driver" {{ !$vehicle_filter_id && $driver_filter_id ? 'selected' : '' }}>Conductor especifico</option>
                            </select>
                        </div>
                        <div class="col-12" id="fuel-report-vehicle-wrap">
                            <label class="form-label fw-bold">Vehiculo</label>
                            <select class="form-select bp-select-like-vehicle" id="fuel-report-vehicle-id">
                                <option value="">Todos los vehiculos</option>
                                @foreach($vehicles as $id => $placa)
                                    <option value="{{ $id }}" {{ (int) $vehicle_filter_id === (int) $id ? 'selected' : '' }}>{{ $placa }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12" id="fuel-report-driver-wrap">
                            <label class="form-label fw-bold">Conductor</label>
                            <select class="form-select bp-select-like-vehicle" id="fuel-report-driver-id">
                                <option value="">Todos los conductores</option>
                                @foreach($drivers as $id => $name)
                                    <option value="{{ $id }}" {{ (int) $driver_filter_id === (int) $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Placa o texto libre</label>
                            <input type="text" class="form-control" id="fuel-report-placa" value="{{ $placa_filtro }}" placeholder="Opcional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold d-block">Campos del documento</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="fuel-report-select-all">Todos</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="fuel-report-clear-all">Ninguno</button>
                            </div>
                            <div class="row g-2" id="fuel-report-columns">
                                @php
                                    $fuelDocumentColumns = [
                                        'station_name' => 'Estacion',
                                        'invoice_number' => 'Factura',
                                        'regional' => 'Regional',
                                        'fecha_carga' => 'Fecha',
                                        'litros' => 'Litros',
                                        'importe_bs' => 'Importe',
                                        'total_km' => 'KM',
                                        'placa' => 'Placa',
                                        'vehiculo' => 'Vehiculo',
                                        'driver_name' => 'Conductor',
                                    ];
                                @endphp
                                @foreach($fuelDocumentColumns as $value => $label)
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input fuel-report-column" type="checkbox" value="{{ $value }}" id="fuel-column-{{ $value }}" checked>
                                            <label class="form-check-label" for="fuel-column-{{ $value }}">{{ $label }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">Selecciona las columnas que quieres incluir en el documento.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        id="fuel-report-submit"
                        data-pdf-url="{{ route('fuel-logs.bitacora.pdf') }}"
                        data-excel-url="{{ route('fuel-logs.bitacora.excel') }}">
                        Generar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fuelFileViewerModal" tabindex="-1" aria-labelledby="fuelFileViewerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fuelFileViewerLabel">Archivo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="fuel-file-viewer-loading" class="fuel-file-viewer-loading d-none">
                        Cargando archivo...
                    </div>
                    <img id="fuel-file-viewer-image" class="fuel-file-image d-none" alt="Archivo">
                    <iframe id="fuel-file-viewer-frame" class="fuel-file-viewer d-none" title="Archivo"></iframe>
                    <div id="fuel-file-viewer-empty" class="text-muted text-center py-4">No se pudo cargar el archivo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    @endif
    <script>
        (function() {
            if (window.__fuelFlashAutoDismissInitialized) return;
            window.__fuelFlashAutoDismissInitialized = true;

            function scheduleDismiss(scope) {
                (scope || document).querySelectorAll('.js-auto-dismiss-alert').forEach((alertEl) => {
                    if (alertEl.dataset.dismissBound === '1') return;
                    alertEl.dataset.dismissBound = '1';

                    const delay = Number.parseInt(alertEl.getAttribute('data-auto-dismiss') || '3000', 10);
                    window.setTimeout(() => {
                        alertEl.classList.remove('show');
                        alertEl.classList.add('fade');
                        window.setTimeout(() => {
                            alertEl.remove();
                        }, 200);
                    }, Number.isFinite(delay) ? delay : 3000);
                });
            }

            scheduleDismiss(document);
            new MutationObserver(() => scheduleDismiss(document)).observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
    </script>
    @if(auth()->user()?->role !== 'conductor')
    <script>
        (function() {
            if (window.__fuelReportModalInitialized) return;
            window.__fuelReportModalInitialized = true;

            const scopeEl = document.getElementById('fuel-report-scope');
            const vehicleWrap = document.getElementById('fuel-report-vehicle-wrap');
            const driverWrap = document.getElementById('fuel-report-driver-wrap');
            const vehicleEl = document.getElementById('fuel-report-vehicle-id');
            const driverEl = document.getElementById('fuel-report-driver-id');
            const actionEl = document.getElementById('fuel-report-action');
            const fechaDesdeEl = document.getElementById('fuel-report-fecha-desde');
            const fechaHastaEl = document.getElementById('fuel-report-fecha-hasta');
            const placaEl = document.getElementById('fuel-report-placa');
            const submitEl = document.getElementById('fuel-report-submit');
            const triggerEl = document.getElementById('fuel-document-trigger');
            const modalEl = document.getElementById('fuelDocumentModal');
            const selectAllEl = document.getElementById('fuel-report-select-all');
            const clearAllEl = document.getElementById('fuel-report-clear-all');

            if (!scopeEl || !submitEl) return;

            function setFuelColumns(checked) {
                document.querySelectorAll('.fuel-report-column').forEach((checkbox) => {
                    checkbox.checked = checked;
                });
            }

            function getFuelBootstrapModalInstance(el) {
                if (!el || typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
                    return null;
                }

                if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                    return window.bootstrap.Modal.getOrCreateInstance(el);
                }

                if (typeof window.bootstrap.Modal.getInstance === 'function') {
                    return window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                }

                return new window.bootstrap.Modal(el);
            }

            function syncFuelModalFromFilters() {
                const pageDateInputs = Array.from(document.querySelectorAll('input[type="date"]'));
                if (pageDateInputs[0] && fechaDesdeEl && !fechaDesdeEl.value) fechaDesdeEl.value = pageDateInputs[0].value;
                if (pageDateInputs[1] && fechaHastaEl && !fechaHastaEl.value) fechaHastaEl.value = pageDateInputs[1].value;
            }

            function syncFuelScope() {
                const scope = scopeEl.value || 'all';
                if (vehicleWrap) vehicleWrap.style.display = scope === 'vehicle' ? '' : 'none';
                if (driverWrap) driverWrap.style.display = scope === 'driver' ? '' : 'none';
            }

            function buildFuelUrl() {
                const params = new URLSearchParams();
                const action = actionEl ? actionEl.value : 'print_pdf';
                const baseUrl = action === 'download_excel'
                    ? submitEl.getAttribute('data-excel-url')
                    : submitEl.getAttribute('data-pdf-url');

                if (!baseUrl) return null;
                if (fechaDesdeEl && fechaDesdeEl.value) params.set('fecha_desde', fechaDesdeEl.value);
                if (fechaHastaEl && fechaHastaEl.value) params.set('fecha_hasta', fechaHastaEl.value);

                const placa = placaEl ? placaEl.value.trim() : '';
                if (placa !== '') params.set('placa_filtro', placa);

                const scope = scopeEl.value || 'all';
                if (scope === 'vehicle') {
                    const vehicleId = vehicleEl ? vehicleEl.value : '';
                    if (!vehicleId) {
                        window.alert('Selecciona un vehiculo para generar el documento.');
                        return null;
                    }
                    params.set('vehicle_id', vehicleId);
                }

                if (scope === 'driver') {
                    const driverId = driverEl ? driverEl.value : '';
                    if (!driverId) {
                        window.alert('Selecciona un conductor para generar el documento.');
                        return null;
                    }
                    params.set('driver_id', driverId);
                }

                if (action === 'download_pdf') {
                    params.set('download', '1');
                }

                const selectedColumns = Array.from(document.querySelectorAll('.fuel-report-column:checked'))
                    .map((checkbox) => checkbox.value)
                    .filter(Boolean);

                if (selectedColumns.length === 0) {
                    window.alert('Selecciona al menos un campo para generar el documento.');
                    return null;
                }

                selectedColumns.forEach((column) => params.append('columns[]', column));

                const query = params.toString();
                return query ? `${baseUrl}?${query}` : baseUrl;
            }

            scopeEl.addEventListener('change', syncFuelScope);
            syncFuelScope();

            selectAllEl?.addEventListener('click', function() {
                setFuelColumns(true);
            });

            clearAllEl?.addEventListener('click', function() {
                setFuelColumns(false);
            });

            triggerEl?.addEventListener('click', function() {
                syncFuelModalFromFilters();
                const modalInstance = getFuelBootstrapModalInstance(modalEl);
                if (!modalInstance) return;
                modalInstance.show();
            });

            submitEl.addEventListener('click', function() {
                const url = buildFuelUrl();
                if (!url) return;

                const action = actionEl ? actionEl.value : 'print_pdf';
                if (action === 'print_pdf') {
                    window.open(url, '_blank', 'noopener');
                    return;
                }

                window.location.href = url;
            });
        })();
    </script>
    @endif
</div>
