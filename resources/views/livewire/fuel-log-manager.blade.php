<div>
    <style>
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

        #bitacora-view-map {
            height: 420px;
            border-radius: 8px;
        }

        #bitacoraMapModal .modal-dialog {
            max-width: 980px;
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

    <div class="page-title mb-4 d-flex justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-gas-pump me-2 text-primary"></i>Gestion de Combustible
        </h1>
        @if (!$showForm)
        <div class="d-flex flex-wrap gap-2">
            <button type="button" wire:click="openFuelForm" class="btn btn-primary">
                <i class="fas fa-receipt me-2"></i>Nuevo
            </button>
        </div>
        @endif
    </div>

    @if (session('message'))
    <div class="alert alert-success fade show js-auto-dismiss-alert" data-auto-dismiss="3000" role="alert">
        {{ session('message') }}
    </div>
    @endif

    @if (!$showForm)
    <div class="d-flex flex-wrap gap-2 mb-3 justify-content-between align-items-center">
        <div class="d-flex flex-wrap gap-2">
            <button type="button" wire:click="setTableView('fuel')" class="btn {{ $tableView === 'fuel' ? 'btn-primary' : 'btn-outline-primary' }}">Vales de combustible</button>
            <button type="button" wire:click="setTableView('bitacora')" class="btn {{ $tableView === 'bitacora' ? 'btn-primary' : 'btn-outline-primary' }}">Bitacora</button>
            <button type="button" wire:click="setTableView('combined')" class="btn {{ $tableView === 'combined' ? 'btn-primary' : 'btn-outline-primary' }}">Tablas combinadas</button>
        </div>
        @if(auth()->user()?->role !== 'conductor')
        <div class="d-flex gap-2">
            <a
                class="btn btn-outline-dark"
                href="{{ route('fuel-logs.bitacora.pdf', array_filter([
                            'fecha_desde' => $fecha_desde,
                            'fecha_hasta' => $fecha_hasta,
                            'placa_filtro' => $placa_filtro !== '' ? $placa_filtro : null,
                        ])) }}"
                target="_blank"
                rel="noopener noreferrer">
                <i class="fas fa-print me-2"></i>Imprimir PDF
            </a>
            <button
                type="button"
                class="btn btn-outline-secondary download-pdf-btn"
                data-url="{{ route('fuel-logs.bitacora.pdf', array_filter([
                            'fecha_desde' => $fecha_desde,
                            'fecha_hasta' => $fecha_hasta,
                            'placa_filtro' => $placa_filtro !== '' ? $placa_filtro : null,
                            'download' => 1,
                        ])) }}">
                <i class="fas fa-file-arrow-down me-2"></i>Descargar PDF
            </button>
        </div>
        @endif
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-9">
                    <label class="form-label fw-bold mb-1">Buscar</label>
                    <input
                        type="text"
                        wire:model.live.debounce.350ms="search"
                        class="form-control"
                        placeholder="Buscar por cualquier campo">
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="button" wire:click="searchLogs" class="btn btn-outline-primary">Buscar</button>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold mb-1">Fecha desde</label>
                    <input type="date" wire:model.live="fecha_desde" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold mb-1">Fecha hasta</label>
                    <input type="date" wire:model.live="fecha_hasta" class="form-control">
                </div>
                <div class="col-12 col-md-4 d-grid">
                    <button type="button" wire:click="limpiarFiltrosFecha" class="btn btn-outline-secondary">Limpiar filtro de fechas</button>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold mb-1">Filtrar por placa</label>
                    <select wire:model.live="placa_filtro" class="form-select">
                        <option value="">Todas las placas</option>
                        @foreach(collect($vehicles)->values()->unique()->sort()->values() as $placa)
                        <option value="{{ $placa }}">{{ $placa }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="button" wire:click="aplicarFiltroPlaca" class="btn btn-outline-primary">Filtrar placa</button>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="button" wire:click="limpiarFiltroPlaca" class="btn btn-outline-secondary">Limpiar placa</button>
                </div>
            </div>
        </div>
    </div>
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
                            <select id="vehicle_id_bitacora" wire:model.live="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror">
                                <option value="">-- Ninguno --</option>
                                @foreach($vehicles as $id => $placa)
                                <option value="{{ $id }}" data-km-actual="{{ $vehicleKmMap[(int) $id] ?? '' }}">{{ $placa }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Conductor *</label>
                            <select wire:model="driver_id" class="form-select @error('driver_id') is-invalid @enderror">
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
                                        <select id="vehicle_id_fuel" wire:model.live="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror">
                                            <option value="">-- Ninguno --</option>
                                            @foreach($vehicles as $id => $placa)
                                            <option value="{{ $id }}" data-km-actual="{{ $vehicleKmMap[(int) $id] ?? '' }}">{{ $placa }}</option>
                                            @endforeach
                                        </select>
                                        @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Conductor *</label>
                                        <select wire:model="driver_id" class="form-select @error('driver_id') is-invalid @enderror">
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
                                <select id="camera-select" class="form-select form-select-sm">
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
                @if ($tableView === 'fuel')
                <div class="table-responsive">
                    <table class="table align-middle mb-0 fuel-table fuel-table--combined bitacora-style">
                        <thead>
                            <tr>
                                <th rowspan="2">Fecha</th>
                                <th colspan="2" class="text-center">Kilometraje</th>
                                <th rowspan="2" class="text-center">Total recorrido (Km)</th>
                                <th colspan="2" class="text-center">Recorrido</th>
                                <th rowspan="2">Abastecimiento de combustible</th>
                                <th rowspan="2">Vehiculo</th>
                                <th rowspan="2">Conductor</th>
                                <th rowspan="2" class="text-center">Acciones</th>
                            </tr>
                            <tr class="subhead">
                                <th class="text-end">Salida</th>
                                <th class="text-end">Llegada</th>
                                <th>Inicio</th>
                                <th>Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="10" class="bitacora-cell-note">
                                    Formato bitacora | Factura/Ref se muestra dentro de abastecimiento.
                                </td>
                            </tr>
                            @forelse ($fuelLogs as $log)
                            <tr>
                                <td>{{ optional($log->invoice?->fecha_emision)->format('d/m/Y H:i') }}</td>
                                <td class="text-end">{{ $log->vehicleLog?->kilometraje_salida ?? '-' }}</td>
                                <td class="text-end">{{ $log->vehicleLog?->kilometraje_llegada ?? '-' }}</td>
                                <td class="text-end">
                                    @if($log->vehicleLog?->kilometraje_salida !== null && $log->vehicleLog?->kilometraje_llegada !== null)
                                    {{ number_format((float) $log->vehicleLog->kilometraje_llegada - (float) $log->vehicleLog->kilometraje_salida, 2) }}
                                    @else
                                    -
                                    @endif
                                </td>
                                <td>{{ $log->vehicleLog?->recorrido_inicio ?? '-' }}</td>
                                <td>{{ $log->vehicleLog?->recorrido_destino ?? '-' }}</td>
                                <td>
                                    <div>{{ number_format((float) ($log->galones ?? 0), 2) }} L</div>
                                    <div class="bitacora-cell-note">Factura: {{ optional($log->invoice)->numero_factura ?? '-' }}</div>
                                    <div class="bitacora-cell-note">Total: BOB{{ number_format((float) $log->total_calculado, 2) }}</div>
                                </td>
                                <td>{{ optional($log->vehicle)->placa ?? '-' }}</td>
                                <td>{{ optional($log->driver)->nombre ?? '-' }}</td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary bitacora-map-view-btn"
                                        title="{{ $log->vehicleLog ? 'Ver recorrido en mapa' : 'Sin bitacora vinculada' }}"
                                        {{ $log->vehicleLog ? '' : 'disabled' }}
                                        data-log-id="{{ $log->vehicleLog?->id ?? $log->id }}"
                                        data-fecha="{{ optional($log->vehicleLog?->fecha ?? $log->invoice?->fecha_emision)->format('d/m/Y') }}"
                                        data-placa="{{ $log->vehicle?->placa ?? '-' }}"
                                        data-conductor="{{ $log->driver?->nombre ?? '-' }}"
                                        data-recorrido-inicio="{{ $log->vehicleLog?->recorrido_inicio ?? '-' }}"
                                        data-recorrido-destino="{{ $log->vehicleLog?->recorrido_destino ?? '-' }}"
                                        data-lat-inicio="{{ $log->vehicleLog?->latitud_inicio }}"
                                        data-lng-inicio="{{ $log->vehicleLog?->logitud_inicio }}"
                                        data-lat-destino="{{ $log->vehicleLog?->latitud_destino }}"
                                        data-lng-destino="{{ $log->vehicleLog?->logitud_destino }}"
                                        data-route-points='@json($log->vehicleLog?->points_json ?? [])'>
                                        <i class="fas fa-eye"></i>
                                    </button>
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
                @elseif ($tableView === 'bitacora')
                <div class="table-responsive">
                    <table class="table align-middle mb-0 fuel-table bitacora-style">
                        <thead>
                            <tr>
                                <th rowspan="2">Fecha</th>
                                <th colspan="2" class="text-center">Kilometraje</th>
                                <th rowspan="2" class="text-center">Total recorrido (Km)</th>
                                <th colspan="2" class="text-center">Recorrido</th>
                                <th rowspan="2">Abastecimiento de combustible</th>
                                <th rowspan="2">Vehiculo</th>
                                <th rowspan="2">Conductor</th>
                                <th rowspan="2" class="text-center">Acciones</th>
                            </tr>
                            <tr class="subhead">
                                <th class="text-end">Salida</th>
                                <th class="text-end">Llegada</th>
                                <th>Inicio</th>
                                <th>Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vehicleLogs as $log)
                            <tr>
                                <td>{{ optional($log->fecha)->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $log->kilometraje_salida }}</td>
                                <td class="text-end">{{ $log->kilometraje_llegada ?? '-' }}</td>
                                <td class="text-end">
                                    @if($log->kilometraje_salida !== null && $log->kilometraje_llegada !== null)
                                    {{ number_format((float) $log->kilometraje_llegada - (float) $log->kilometraje_salida, 2) }}
                                    @else
                                    -
                                    @endif
                                </td>
                                <td>{{ $log->recorrido_inicio ?? '-' }}</td>
                                <td>{{ $log->recorrido_destino ?? '-' }}</td>
                                <td>{{ $log->fuelLog?->galones ? number_format((float) $log->fuelLog->galones, 2) . ' L' : '-' }}</td>
                                <td>{{ $log->vehicle?->placa ?? '-' }}</td>
                                <td>{{ $log->driver?->nombre ?? '-' }}</td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary bitacora-map-view-btn"
                                        title="Ver recorrido en mapa"
                                        data-log-id="{{ $log->id }}"
                                        data-fecha="{{ optional($log->fecha)->format('d/m/Y') }}"
                                        data-placa="{{ $log->vehicle?->placa ?? '-' }}"
                                        data-conductor="{{ $log->driver?->nombre ?? '-' }}"
                                        data-recorrido-inicio="{{ $log->recorrido_inicio ?? '-' }}"
                                        data-recorrido-destino="{{ $log->recorrido_destino ?? '-' }}"
                                        data-lat-inicio="{{ $log->latitud_inicio }}"
                                        data-lng-inicio="{{ $log->logitud_inicio }}"
                                        data-lat-destino="{{ $log->latitud_destino }}"
                                        data-lng-destino="{{ $log->logitud_destino }}"
                                        data-route-points='@json($log->points_json ?? [])'>
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @if(auth()->user()?->role !== 'conductor')
                                    @if($log->fuel_log_id)
                                    <span class="text-muted small">Desde vale</span>
                                    @else
                                    <button wire:click="editBitacora({{ $log->id }})" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></button>
                                    <button wire:click="deleteBitacora({{ $log->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    @endif
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Sin registros de bitacora</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0 fuel-table bitacora-style">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Fecha</th>
                                <th>Vehiculo</th>
                                <th>Conductor</th>
                                <th>Detalle</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($combinedRows as $row)
                            <tr>
                                <td>{{ ucfirst($row['tipo']) }} @if($row['tiene_combustible'])<i class="fas fa-gas-pump text-success ms-1"></i>@endif</td>
                                <td>{{ $row['fecha'] }}</td>
                                <td>{{ $row['vehiculo'] }}</td>
                                <td>{{ $row['conductor'] }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $row['detalle_titulo'] ?? 'Detalle' }}</div>
                                    <div>{{ $row['detalle_principal'] ?? '-' }}</div>
                                    @if(!empty($row['detalle_secundario']))
                                    <div class="text-muted small">{{ $row['detalle_secundario'] }}</div>
                                    @endif
                                    @if(!empty($row['detalle_terciario']))
                                    <div class="text-muted small">{{ $row['detalle_terciario'] }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ $row['total'] !== null ? 'BOB' . number_format((float) $row['total'], 2) : '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Sin datos para combinar</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="modal fade" id="bitacoraMapModal" wire:ignore.self tabindex="-1" aria-labelledby="bitacoraMapLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bitacoraMapLabel">
                        <i class="fas fa-map-marked-alt me-2"></i>Recorrido de bitacora
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="bitacora-map-summary">Sin datos</div>
                    <div class="small text-muted mb-3" id="bitacora-map-detail">Sin detalles de recorrido.</div>
                    <div id="bitacora-view-map" wire:ignore></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

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
            if (window.__fuelBitacoraMapViewerInitialized) return;
            window.__fuelBitacoraMapViewerInitialized = true;

            const modalEl = document.getElementById('bitacoraMapModal');
            if (!modalEl || !window.L) return;
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }

            const summaryEl = document.getElementById('bitacora-map-summary');
            const detailEl = document.getElementById('bitacora-map-detail');
            const mapContainerId = 'bitacora-view-map';
            let map = null;
            let layers = [];
            let pendingPayload = null;

            function getBsModal(el) {
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

            function toNumber(value) {
                const n = Number(value);
                return Number.isFinite(n) ? n : null;
            }

            function extractCoordinates(text) {
                if (!text) return [null, null];
                const m = String(text).match(/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/);
                if (!m) return [null, null];
                return [toNumber(m[1]), toNumber(m[2])];
            }

            function validLatLng(lat, lng) {
                return lat !== null && lng !== null && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
            }

            function parseRoutePoints(raw) {
                if (!raw) return [];

                try {
                    const parsed = JSON.parse(raw);
                    if (!Array.isArray(parsed)) return [];

                    return parsed
                        .map((point) => ({
                            lat: toNumber(point?.lat ?? point?.latitude),
                            lng: toNumber(point?.lng ?? point?.longitude),
                            address: typeof point?.address === 'string' ? point.address : '',
                            label: typeof point?.point_label === 'string' ?
                                point.point_label :
                                (typeof point?.label === 'string' ? point.label : ''),
                            marked: Boolean(point?.is_marked ?? point?.marked ?? point?.isMarked),
                        }))
                        .filter((point) => validLatLng(point.lat, point.lng));
                } catch (_) {
                    return [];
                }
            }

            function ensureMap() {
                const container = document.getElementById(mapContainerId);
                if (!container) return;

                if (map) {
                    const currentContainer = map.getContainer ? map.getContainer() : null;
                    if (currentContainer === container && document.body.contains(container)) {
                        return;
                    }

                    try {
                        map.off();
                        map.remove();
                    } catch (_) {}
                    map = null;
                    layers = [];
                }

                map = L.map(container).setView([-16.5, -68.15], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
            }

            function clearLayers() {
                if (!map) return;
                layers.forEach((layer) => map.removeLayer(layer));
                layers = [];
            }

            function renderRoute(data) {
                ensureMap();
                clearLayers();

                const fromText = data.recorridoInicio || '-';
                const toText = data.recorridoDestino || '-';
                if (summaryEl) {
                    summaryEl.textContent = `Bitacora #${data.logId} | Fecha: ${data.fecha} | Vehiculo: ${data.placa} | Conductor: ${data.conductor}`;
                }
                if (detailEl) {
                    detailEl.textContent = `Inicio: ${fromText} | Destino: ${toText}`;
                }

                let fromLat = toNumber(data.latInicio);
                let fromLng = toNumber(data.lngInicio);
                let toLat = toNumber(data.latDestino);
                let toLng = toNumber(data.lngDestino);
                const routePoints = parseRoutePoints(data.routePoints);

                if (!validLatLng(fromLat, fromLng)) {
                    const coords = extractCoordinates(fromText);
                    fromLat = coords[0];
                    fromLng = coords[1];
                }
                if (!validLatLng(toLat, toLng)) {
                    const coords = extractCoordinates(toText);
                    toLat = coords[0];
                    toLng = coords[1];
                }

                const points = routePoints.map((point) => [point.lat, point.lng]);
                if (!points.length) {
                    if (validLatLng(fromLat, fromLng)) points.push([fromLat, fromLng]);
                    if (validLatLng(toLat, toLng)) points.push([toLat, toLng]);
                }

                if (!points.length) {
                    if (detailEl) {
                        detailEl.textContent += ' | No hay coordenadas validas para mostrar en el mapa.';
                    }
                    map.setView([-16.5, -68.15], 11);
                    return;
                }

                if (validLatLng(fromLat, fromLng)) {
                    const start = L.marker([fromLat, fromLng]).addTo(map)
                        .bindPopup(`<strong>Inicio</strong><br>${fromText}`);
                    layers.push(start);
                }

                if (validLatLng(toLat, toLng)) {
                    const end = L.marker([toLat, toLng]).addTo(map)
                        .bindPopup(`<strong>Destino</strong><br>${toText}`);
                    layers.push(end);
                }

                if (routePoints.length > 0) {
                    routePoints.forEach((point, index) => {
                        const isStart = validLatLng(fromLat, fromLng) && point.lat === fromLat && point.lng === fromLng;
                        const isEnd = validLatLng(toLat, toLng) && point.lat === toLat && point.lng === toLng;
                        if (isStart || isEnd) {
                            return;
                        }

                        const popupText = point.address || point.label || `Punto ${index + 1}`;
                        const marker = L.circleMarker([point.lat, point.lng], {
                            radius: point.marked ? 7 : 5,
                            color: point.marked ? '#9a3412' : '#2563eb',
                            weight: 2,
                            fillColor: point.marked ? '#facc15' : '#93c5fd',
                            fillOpacity: 0.95,
                        }).addTo(map).bindPopup(`<strong>${point.marked ? 'Punto marcado' : 'Punto de ruta'}</strong><br>${popupText}`);
                        layers.push(marker);
                    });
                }

                if (points.length >= 2) {
                    const line = L.polyline(points, {
                        color: '#00509d',
                        weight: 4,
                        opacity: 0.8,
                    }).addTo(map);
                    layers.push(line);
                }

                map.fitBounds(L.latLngBounds(points), {
                    padding: [40, 40],
                    maxZoom: 16
                });
            }

            document.addEventListener('click', function(event) {
                const btn = event.target.closest('.bitacora-map-view-btn');
                if (!btn) return;

                pendingPayload = {
                    logId: btn.getAttribute('data-log-id') || '-',
                    fecha: btn.getAttribute('data-fecha') || '-',
                    placa: btn.getAttribute('data-placa') || '-',
                    conductor: btn.getAttribute('data-conductor') || '-',
                    recorridoInicio: btn.getAttribute('data-recorrido-inicio') || '-',
                    recorridoDestino: btn.getAttribute('data-recorrido-destino') || '-',
                    latInicio: btn.getAttribute('data-lat-inicio'),
                    lngInicio: btn.getAttribute('data-lng-inicio'),
                    latDestino: btn.getAttribute('data-lat-destino'),
                    lngDestino: btn.getAttribute('data-lng-destino'),
                    routePoints: btn.getAttribute('data-route-points') || '[]',
                };

                const modal = getBsModal(modalEl);
                if (!modal) return;
                modal.show();
            });

            modalEl.addEventListener('shown.bs.modal', function() {
                if (!pendingPayload) return;
                renderRoute(pendingPayload);
                if (map) {
                    map.invalidateSize();
                    setTimeout(() => map.invalidateSize(), 120);
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function() {
                clearLayers();
                pendingPayload = null;
            });
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
                const city =
                    address.city ||
                    address.town ||
                    address.village ||
                    address.municipality ||
                    address.county ||
                    address.state_district ||
                    address.state ||
                    null;
                const country = address.country || 'Bolivia';
                if (city) return `${city}, ${country}`;
                return data.display_name || null;
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
</div>