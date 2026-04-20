<div class="bp-livewire-skin">
    @include('livewire.partials.button-theme')
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

        #vehicle-location-map {
            height: 360px;
            border-radius: 8px;
        }

        #vehicleLocationPickerModal .modal-dialog {
            max-width: 900px;
        }

        #vehicle-view-map {
            height: 420px;
            border-radius: 8px;
        }

        #vehicleViewMapModal .modal-dialog {
            max-width: 980px;
        }

        #vehicle-form-side-map {
            height: 420px;
            border-radius: 10px;
        }

        #vehicle-side-map {
            height: 430px;
            border-radius: 10px;
        }

        .bitacora-shell {
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid #e8edf7;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(25, 46, 86, 0.08);
        }

        .bitacora-shell__header {
            background: #20539a;
            color: #ffffff;
            padding: 18px 20px;
        }

        .bitacora-shell__title {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .bitacora-shell__toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bitacora-shell__search {
            min-width: 220px;
            border-radius: 12px;
            border: 1px solid #d3def2;
            height: 42px;
        }

        .bitacora-shell__btn-find {
            height: 42px;
            border-radius: 12px;
            border-color: rgba(255, 255, 255, 0.8);
            color: #fff;
            font-weight: 700;
            padding-inline: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            white-space: nowrap;
            text-decoration: none;
        }

        .bitacora-shell__btn-new {
            height: 42px;
            border-radius: 12px;
            background: #fecc36;
            border-color: #fecc36;
            color: #20539a;
            font-weight: 700;
            padding-inline: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            white-space: nowrap;
        }

        .bitacora-shell__body {
            padding: 22px 20px 16px;
            background: #f7f8fb;
        }

        .bitacora-shell__meta {
            color: #667085;
            font-size: 1.5rem;
        }

        .bitacora-shell__count {
            color: #667085;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .bitacora-shell__table-wrap {
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e8edf7;
            overflow: hidden;
        }

        .bitacora-shell .table thead th {
            color: #20539a;
            font-weight: 700;
            background: #f4f7fd;
            border-bottom: 1px solid #d8e2f4;
            white-space: nowrap;
        }

        .bitacora-shell__empty {
            min-height: 220px;
        }

        .bitacora-side-map-card {
            border: 1px solid #e8edf7;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
            height: 100%;
        }

        .bitacora-side-map-meta {
            color: #475467;
            font-size: 1.25rem;
            margin-bottom: 8px;
        }

        .bitacora-side-map-help {
            color: #667085;
            font-size: 1.2rem;
            margin-top: 8px;
        }

        .vehicle-form-map-card {
            border: 1px solid #e8edf7;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
            height: 100%;
        }

        .vehicle-form-map-overlay {
            position: fixed;
            top: 96px;
            left: 50%;
            transform: translateX(-50%);
            width: min(560px, calc(100vw - 32px));
            z-index: 1080;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.28);
        }

        .vehicle-form-map-meta {
            color: #475467;
            font-size: 1.25rem;
            margin-bottom: 8px;
        }

        .vehicle-form-map-help {
            color: #667085;
            font-size: 1.2rem;
            margin-top: 8px;
        }

        .odometro-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #d8e2f4;
            background: #fff;
        }

        .odometro-modal-image {
            display: block;
            max-width: 100%;
            max-height: 70vh;
            margin: 0 auto;
            border-radius: 10px;
            border: 1px solid #d8e2f4;
            background: #fff;
        }

        .bitacora-stage-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #d8e2f4;
            background: #fff;
        }

        .bitacora-stage-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .bitacora-stage-card {
            border: 1px solid #d8e2f4;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }

        .bitacora-stage-card__title {
            color: #20539a;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .bitacora-stage-card__meta {
            color: #667085;
            font-size: 1.15rem;
            margin-top: 8px;
        }

        .vehicle-log-select {
            border-radius: 10px;
            min-height: calc(2.35rem + 2px);
            border: 1px solid #ced4da;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        .vehicle-log-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }

        select.vehicle-log-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.2rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16'%3E%3Cpath fill='%236c757d' d='M2.646 5.646a.5.5 0 0 1 .708 0L8 10.293l4.646-4.647a.5.5 0 0 1 .708.708l-5 5a.5.5 0 0 1-.708 0l-5-5a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            background-size: 14px;
        }

        @media (max-width: 768px) {
            .bitacora-shell__toolbar {
                width: 100%;
                margin-top: 12px;
            }

            .bitacora-shell__search {
                min-width: 0;
                flex: 1;
            }

            .bitacora-shell__title {
                font-size: 1.8rem;
            }

            .vehicle-form-map-overlay {
                top: 72px;
                left: 8px;
                transform: none;
                width: auto;
            }
        }
    </style>

    @if (session('message'))
    <div class="alert alert-success fade show js-auto-dismiss-alert" data-auto-dismiss="3000" role="alert">
        {{ session('message') }}
    </div>
    @endif

    @if (session('error'))
    <div class="alert alert-danger fade show" role="alert">
        {{ session('error') }}
    </div>
    @endif

    @if ($showForm)
    <div class="bp-gestiones-form-overlay">
        <div class="bp-gestiones-form-stage">
            <div class="row g-3 align-items-start">
                <div class="col-12 col-xxl-8">
                    <div class="card shadow-sm mb-0 bp-gestiones-form-card">
                        <div class="card-header">
                            {{ $isEdit ? 'Editar Registro de Bitacora' : 'Nuevo Registro de Bitacora' }}
                        </div>
                        <div class="card-body">
                            <form wire:submit.prevent="save">
                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label for="vehicles_id" class="form-label fw-bold">Vehiculo <span class="text-danger">*</span></label>
                                        <select id="vehicles_id" wire:model.live="vehicles_id" wire:change="onVehicleChanged($event.target.value)" class="form-control vehicle-log-select @error('vehicles_id') is-invalid @enderror" required>
                                            <option value="">Seleccionar vehiculo...</option>
                                            @foreach ($vehicles as $vehicle)
                                            <option value="{{ $vehicle->id }}" data-km-actual="{{ $vehicle->kilometraje_actual ?? '' }}">{{ $vehicle->placa }}</option>
                                            @endforeach
                                        </select>
                                        @if($vehicles->isEmpty())
                                        <div class="form-text text-warning">No hay vehiculos con conductor asignado para la fecha seleccionada.</div>
                                        @endif
                                        @error('vehicles_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="drivers_id" class="form-label fw-bold">Conductor <span class="text-danger">*</span></label>
                                        <select id="drivers_id" wire:model="drivers_id" class="form-control vehicle-log-select @error('drivers_id') is-invalid @enderror" required>
                                            <option value="">Seleccionar conductor...</option>
                                            @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('drivers_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label for="fecha" class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                                        <input type="date" id="fecha" wire:model="fecha" class="form-control @error('fecha') is-invalid @enderror" max="{{ now()->toDateString() }}" required>
                                        @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="kilometraje_salida" class="form-label fw-bold">Km Salida <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" id="kilometraje_salida" wire:model="kilometraje_salida" class="form-control @error('kilometraje_salida') is-invalid @enderror bg-light" readonly required>
                                        @error('kilometraje_salida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label for="kilometraje_recorrido" class="form-label fw-bold">Km Recorrido <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" id="kilometraje_recorrido" wire:model.live="kilometraje_recorrido" class="form-control @error('kilometraje_recorrido') is-invalid @enderror" required>
                                        @error('kilometraje_recorrido') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">El sistema sumara este valor al Km Salida para calcular el kilometraje final.</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="recorrido_inicio" class="form-label fw-bold">Recorrido Inicio <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" id="recorrido_inicio" wire:model="recorrido_inicio" class="form-control @error('recorrido_inicio') is-invalid @enderror" required>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary vehicle-location-picker-btn"
                                                data-target-input="recorrido_inicio"
                                                data-target-lat="latitud_inicio_vehicle"
                                                data-target-lng="logitud_inicio_vehicle">
                                                <i class="fas fa-map-marker-alt me-1"></i>Elegir en mapa
                                            </button>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-6">
                                                <input id="latitud_inicio_vehicle" type="text" wire:model.live="latitud_inicio" class="form-control form-control-sm bg-light" placeholder="Latitud" readonly>
                                            </div>
                                            <div class="col-6">
                                                <input id="logitud_inicio_vehicle" type="text" wire:model.live="logitud_inicio" class="form-control form-control-sm bg-light" placeholder="Longitud" readonly>
                                            </div>
                                        </div>
                                        @error('recorrido_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold">Km Final Calculado</label>
                                        <input type="number" step="0.01" class="form-control bg-light" value="{{ $kilometraje_llegada ?? '' }}" readonly>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="odometro_photo" class="form-label fw-bold">Foto de odometro <span class="text-danger">*</span></label>
                                        <input type="file" id="odometro_photo" wire:model="odometro_photo" class="form-control @error('odometro_photo') is-invalid @enderror" accept="image/*" @if(!$isEdit || !$currentOdometroPhotoPath) required @endif>
                                        @error('odometro_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">Formatos de imagen permitidos. Tamano maximo: 5 MB.</div>
                                        <div class="d-flex align-items-center gap-2 mt-2">
                                            @if ($odometro_photo)
                                            <img src="{{ $odometro_photo->temporaryUrl() }}" alt="Vista previa odometro" class="odometro-thumb">
                                            @elseif ($currentOdometroPhotoUrl)
                                            <img src="{{ $currentOdometroPhotoUrl }}" alt="Foto actual odometro" class="odometro-thumb">
                                            @endif
                                            @if ($odometro_photo || $currentOdometroPhotoUrl)
                                            <span class="small text-muted">Vista previa 50x50</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">

                                    <div class="col-12 col-md-6">
                                        <label for="recorrido_destino" class="form-label fw-bold">Recorrido Destino <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" id="recorrido_destino" wire:model="recorrido_destino" class="form-control @error('recorrido_destino') is-invalid @enderror" required>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary vehicle-location-picker-btn"
                                                data-target-input="recorrido_destino"
                                                data-target-lat="latitud_destino_vehicle"
                                                data-target-lng="logitud_destino_vehicle">
                                                <i class="fas fa-map-marker-alt me-1"></i>Elegir en mapa
                                            </button>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-6">
                                                <input id="latitud_destino_vehicle" type="text" wire:model.live="latitud_destino" class="form-control form-control-sm bg-light" placeholder="Latitud" readonly>
                                            </div>
                                            <div class="col-6">
                                                <input id="logitud_destino_vehicle" type="text" wire:model.live="logitud_destino" class="form-control form-control-sm bg-light" placeholder="Longitud" readonly>
                                            </div>
                                        </div>
                                        @error('recorrido_destino') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6 d-flex align-items-center">
                                        <div class="form-check bp-switch">
                                            <input class="form-check-input" type="checkbox" id="abastecimiento" wire:model="abastecimiento_combustible">
                                            <label class="form-check-label" for="abastecimiento">
                                                Abastecimiento combustible?
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}
                                    </button>
                                    <button type="button" wire:click="cancelForm" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Volver al listado
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xxl-4 d-none vehicle-form-map-overlay" id="vehicle-form-map-panel">
                    <div class="vehicle-form-map-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="vehicle-form-map-meta mb-0" id="vehicle-form-map-meta">
                                Seleccion de ubicacion en mapa
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary vehicle-form-map-close">
                                Cerrar
                            </button>
                        </div>
                        <div id="vehicle-form-side-map" wire:ignore></div>
                        <div class="vehicle-form-map-help" id="vehicle-form-map-help">
                            Pulsa "Elegir en mapa" en Inicio o Destino y luego haz clic en el mapa.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div class="bitacora-shell mb-4">
        <div class="bitacora-shell__header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="bitacora-shell__title">Gestiones en bitacoras</div>
            <div class="bitacora-shell__toolbar">
                <input
                    type="text"
                    class="form-control bitacora-shell__search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Buscar por cualquier campo">
                <button type="button" wire:click="searchLogs" class="btn btn-outline-light bitacora-shell__btn-find">Buscar</button>
                @if(auth()->user()?->role !== 'conductor')
                <button
                    type="button"
                    id="vehicle-document-trigger"
                    class="btn btn-outline-light bitacora-shell__btn-find">
                    <i class="fas fa-file-lines me-2"></i>Documento
                </button>
                @endif
                @if(auth()->user()?->role !== 'conductor')
                <button type="button" wire:click="create" class="btn bitacora-shell__btn-new">Nuevo</button>
                @endif
            </div>
        </div>

        <div class="bitacora-shell__body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="bitacora-shell__count">Total en pagina: {{ $logs->count() }}</div>
            </div>

            <div class="row g-2 mb-3 align-items-end">
                <div class="col-12 col-lg-2 d-flex flex-column gap-2">
                    <div class="btn-group" role="group" aria-label="Cambiar tabla">
                        <button
                            type="button"
                            wire:click="showLogsTable"
                            class="btn {{ $table_view === 'logs' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Bitacoras
                        </button>
                        <button
                            type="button"
                            wire:click="showOperationalAlertsTable"
                            class="btn {{ $table_view === 'alerts' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Alertas operativas
                        </button>
                    </div>
                    <button type="button" wire:click="limpiarFiltrosListado" class="btn btn-outline-secondary">
                        Limpiar filtros
                    </button>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-bold mb-1">Fecha desde</label>
                    <input type="date" wire:model.live="fecha_desde" class="form-control @error('fecha_desde') is-invalid @enderror" max="{{ now()->toDateString() }}">
                    @error('fecha_desde') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-bold mb-1">Fecha hasta</label>
                    <input type="date" wire:model.live="fecha_hasta" class="form-control @error('fecha_hasta') is-invalid @enderror" max="{{ now()->toDateString() }}">
                    @error('fecha_hasta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label fw-bold mb-1">Vehiculo</label>
                    <select wire:model.live="vehicle_filter_id" class="form-control vehicle-log-select">
                        <option value="">Todos los vehiculos</option>
                        @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->placa }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label fw-bold mb-1">Conductor</label>
                    <select wire:model.live="driver_filter_id" class="form-control vehicle-log-select">
                        <option value="">Todos los conductores</option>
                        @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="bitacora-shell__table-wrap">
                        <div class="table-responsive">
                            @if($table_view === 'alerts')
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Vehiculo</th>
                                        <th>Conductor</th>
                                        <th>Alerta</th>
                                        <th>Estado en ruta</th>
                                        <th>Detectada</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($operationAlerts as $alert)
                                    <tr>
                                        <td>{{ $alert->vehicle?->placa ?? 'N/A' }}</td>
                                        <td>{{ $alert->session?->driver?->nombre ?? 'N/A' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $alert->title ?: 'Alerta operativa' }}</div>
                                            <div class="text-muted small">{{ $alert->message ?: '-' }}</div>
                                        </td>
                                        <td>{{ $alert->current_stage ?: '-' }}</td>
                                        <td>{{ optional($alert->detected_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                        <td class="text-center">
                                            <button
                                                type="button"
                                                wire:click="markOperationalAlertReviewed({{ $alert->id }})"
                                                class="btn btn-sm btn-outline-success"
                                                title="Marcar como revisado">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5 bitacora-shell__empty">
                                            No hay alertas operativas activas.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            @else
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Vehiculo</th>
                                        <th>Conductor</th>
                                        <th>Fecha</th>
                                        <th>Km salida</th>
                                        <th>Km recorrido</th>
                                        <th>Inicio</th>
                                        <th>Destino</th>
                                        <th>Gasolina</th>
                                        <th>Paquetes</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($logs as $log)
                                    <tr>
                                        <td>{{ $log->vehicle?->bitacora_display_name ?? 'N/A' }}</td>
                                        <td>{{ $log->driver?->nombre ?? 'N/A' }}</td>
                                        <td>{{ optional($log->fecha)->format('d/m/Y') }}</td>
                                        <td>{{ $log->kilometraje_salida }}</td>
                                        <td>{{ $log->kilometraje_recorrido ?? (($log->kilometraje_llegada !== null && $log->kilometraje_salida !== null) ? number_format((float) $log->kilometraje_llegada - (float) $log->kilometraje_salida, 2) : '-') }}</td>
                                        <td>{{ $log->recorrido_inicio ?: '-' }}</td>
                                        <td>{{ $log->recorrido_destino ?: '-' }}</td>
                                        <td>{{ $log->fuel_log_id ? 'Si' : 'No' }}</td>
                                        <td>{{ $log->package_count ?? 0 }}</td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                @if((($log->latitud_inicio !== null) && ($log->logitud_inicio !== null)) || (($log->latitud_destino !== null) && ($log->logitud_destino !== null)) || count($log->ruta_json ?? $log->points_json ?? []) > 0)
                                                <a
                                                    href="{{ route('vehicle-logs.map', $log->id) }}"
                                                    class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-map-marked-alt"></i>
                                                </a>
                                                @endif
                                                @if($log->odometro_photo_path)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary vehicle-view-odometro-btn"
                                                    data-photo-url="{{ route('vehicle-logs.odometro.photo', $log->id) }}"
                                                    data-vehicle="{{ $log->vehicle?->placa ?? 'N/A' }}"
                                                    data-date="{{ optional($log->fecha)->format('d/m/Y') }}">
                                                    <i class="fas fa-camera"></i>
                                                </button>
                                                @endif
                                                @if(auth()->user()?->role !== 'conductor')
                                                <button wire:click="edit({{ $log->id }})" class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="delete({{ $log->id }})" onclick="return confirm('Confirmar?')" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-5 bitacora-shell__empty">
                                            No hay registros<br>Prueba con otro texto de busqueda.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="mt-3">
                        @if($table_view === 'alerts')
                        {{ $operationAlerts->links('pagination::bootstrap-4') }}
                        @else
                        {{ $logs->links('pagination::bootstrap-4') }}
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>




    <div class="modal fade" id="vehicleLocationPickerModal" wire:ignore tabindex="-1" aria-labelledby="vehicleLocationPickerLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehicleLocationPickerLabel">
                        <i class="fas fa-map-marked-alt me-2"></i>Seleccionar ubicacion
                    </h5>
                    <button type="button" class="btn-close vehicle-map-modal-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Haz clic en el mapa para seleccionar un punto.</p>
                    <div id="vehicle-location-map" wire:ignore></div>
                    <div class="mt-3 small text-muted" id="vehicle-selected-location-preview">Sin ubicacion seleccionada.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary vehicle-map-modal-close" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="vehicle-confirm-location-btn">Usar ubicacion</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vehicleViewMapModal" wire:ignore tabindex="-1" aria-labelledby="vehicleViewMapLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehicleViewMapLabel">
                        <i class="fas fa-map-marked-alt me-2"></i>Recorrido de bitacora
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="vehicle-view-map-meta">Sin datos</div>
                    <div class="small text-primary mb-2 d-none" id="vehicle-view-map-status">Cargando mapa...</div>
                    <div id="vehicle-view-map" wire:ignore></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="vehicle-reload-points-btn">
                        <i class="fas fa-map-pin me-1"></i>Cargar puntos
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="vehicle-reload-map-btn">
                        <i class="fas fa-sync-alt me-1"></i>Recargar mapa
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vehicleOdometroModal" wire:ignore tabindex="-1" aria-labelledby="vehicleOdometroLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehicleOdometroLabel">
                        <i class="fas fa-camera me-2"></i>Foto de odometro
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3" id="vehicle-odometro-meta">Sin datos</div>
                    <img id="vehicle-odometro-image" class="odometro-modal-image d-none" alt="Foto de odometro">
                    <div id="vehicle-odometro-empty" class="text-muted text-center py-4">No hay imagen disponible.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            function ensureLeafletAssets() {
                if (window.L) {
                    return Promise.resolve(window.L);
                }

                if (window.__vehicleLeafletAssetsPromise) {
                    return window.__vehicleLeafletAssetsPromise;
                }

                window.__vehicleLeafletAssetsPromise = new Promise((resolve, reject) => {
                    const headEl = document.head || document.getElementsByTagName('head')[0];

                    if (!document.querySelector('link[data-vehicle-leaflet-css="1"]')) {
                        const cssEl = document.createElement('link');
                        cssEl.rel = 'stylesheet';
                        cssEl.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                        cssEl.setAttribute('data-vehicle-leaflet-css', '1');
                        headEl?.appendChild(cssEl);
                    }

                    const existingScript = document.querySelector('script[data-vehicle-leaflet-js="1"]');
                    if (existingScript) {
                        existingScript.addEventListener('load', () => resolve(window.L));
                        existingScript.addEventListener('error', () => reject(new Error('No se pudo cargar Leaflet.')));
                        return;
                    }

                    const scriptEl = document.createElement('script');
                    scriptEl.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    scriptEl.async = true;
                    scriptEl.setAttribute('data-vehicle-leaflet-js', '1');
                    scriptEl.onload = () => resolve(window.L);
                    scriptEl.onerror = () => reject(new Error('No se pudo cargar Leaflet.'));
                    headEl?.appendChild(scriptEl);
                });

                return window.__vehicleLeafletAssetsPromise;
            }

            ensureLeafletAssets().catch(() => {});

            if (!window.__vehicleKmSyncInitialized) {
                window.__vehicleKmSyncInitialized = true;

                const syncKmSalida = () => {
                    const vehicleSelect = document.getElementById('vehicles_id');
                    const kmSalidaInput = document.getElementById('kilometraje_salida');
                    if (!vehicleSelect || !kmSalidaInput) return;

                    const selected = vehicleSelect.options[vehicleSelect.selectedIndex];
                    if (!selected) return;

                    const km = selected.getAttribute('data-km-actual');
                    if (km === null || km === '') {
                        kmSalidaInput.value = '';
                    } else {
                        kmSalidaInput.value = km;
                    }

                    kmSalidaInput.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    kmSalidaInput.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                };

                document.addEventListener('change', function(event) {
                    const target = event.target;
                    if (!(target instanceof HTMLSelectElement)) return;
                    if (target.id !== 'vehicles_id') return;
                    syncKmSalida();
                });

                document.addEventListener('livewire:navigated', syncKmSalida);
                document.addEventListener('livewire:initialized', syncKmSalida);
                setTimeout(syncKmSalida, 50);
            }

            if (window.__vehicleMapPickerInitialized) return;
            window.__vehicleMapPickerInitialized = true;
            let BOLIVIA_BOUNDS = null;

            function resolveBoliviaBounds() {
                if (BOLIVIA_BOUNDS) return BOLIVIA_BOUNDS;
                if (!window.L) return null;

                BOLIVIA_BOUNDS = L.latLngBounds(
                    L.latLng(-22.95, -69.75),
                    L.latLng(-9.55, -57.35)
                );

                return BOLIVIA_BOUNDS;
            }

            let map = null;
            let marker = null;
            let selected = null;
            let activeTarget = null;
            let detectedUserLocation = null;
            let detectedNearestPlace = null;

            const modalEl = document.getElementById('vehicleLocationPickerModal');
            if (!modalEl) return;
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }

            const previewEl = document.getElementById('vehicle-selected-location-preview');
            const confirmBtn = document.getElementById('vehicle-confirm-location-btn');

            function getFormMapElements() {
                return {
                    panelEl: document.getElementById('vehicle-form-map-panel'),
                    mapEl: document.getElementById('vehicle-form-side-map'),
                    metaEl: document.getElementById('vehicle-form-map-meta'),
                    helpEl: document.getElementById('vehicle-form-map-help'),
                };
            }

            function updatePreview(text) {
                if (previewEl) previewEl.textContent = text;
            }

            function resolveTargetLabel(inputId) {
                if (inputId === 'recorrido_inicio') return 'Inicio';
                if (inputId === 'recorrido_destino') return 'Destino';
                return 'Ubicacion';
            }

            function buildTarget(inputId) {
                if (inputId === 'recorrido_inicio') {
                    return {
                        inputId: 'recorrido_inicio',
                        latId: 'latitud_inicio_vehicle',
                        lngId: 'logitud_inicio_vehicle',
                    };
                }
                if (inputId === 'recorrido_destino') {
                    return {
                        inputId: 'recorrido_destino',
                        latId: 'latitud_destino_vehicle',
                        lngId: 'logitud_destino_vehicle',
                    };
                }
                return null;
            }

            function setActiveTargetByInputId(inputId) {
                const target = buildTarget(inputId);
                if (target) activeTarget = target;
                return target;
            }

            function fieldHasCoordinates(latId, lngId) {
                const latValue = (document.getElementById(latId)?.value || '').trim();
                const lngValue = (document.getElementById(lngId)?.value || '').trim();
                return latValue !== '' && lngValue !== '';
            }

            function resolveEffectiveTarget() {
                const inicioFilled = fieldHasCoordinates('latitud_inicio_vehicle', 'logitud_inicio_vehicle');
                const destinoFilled = fieldHasCoordinates('latitud_destino_vehicle', 'logitud_destino_vehicle');

                if (!inicioFilled && destinoFilled) {
                    return setActiveTargetByInputId('recorrido_inicio');
                }
                if (inicioFilled && !destinoFilled) {
                    return setActiveTargetByInputId('recorrido_destino');
                }
                if (activeTarget?.inputId) {
                    return activeTarget;
                }
                return setActiveTargetByInputId('recorrido_inicio');
            }

            function updateFormMapStatus() {
                const {
                    metaEl,
                    helpEl
                } = getFormMapElements();
                const targetLabel = resolveTargetLabel(activeTarget?.inputId);
                if (metaEl) metaEl.textContent = `Seleccionando ubicacion para: ${targetLabel}`;
                if (helpEl) helpEl.textContent = `Haz clic en el mapa para asignar la ubicacion de ${targetLabel}.`;
            }

            function scheduleFormSideMapResize() {
                setTimeout(() => formSideMap && formSideMap.invalidateSize(), 60);
                setTimeout(() => formSideMap && formSideMap.invalidateSize(), 180);
                setTimeout(() => formSideMap && formSideMap.invalidateSize(), 360);
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

            function forceHideModalEl(el) {
                if (!el) return;

                el.classList.remove('show');
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
                el.removeAttribute('aria-modal');
                el.removeAttribute('role');
                document.body.classList.remove('modal-open');

                document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
            }

            function forceShowModalEl(el) {
                if (!el) return;

                el.classList.add('show');
                el.style.display = 'block';
                el.removeAttribute('aria-hidden');
                el.setAttribute('aria-modal', 'true');
                el.setAttribute('role', 'dialog');
                document.body.classList.add('modal-open');

                if (!document.querySelector('.modal-backdrop')) {
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.dataset.vehicleModalOwner = el.id || '';
                    backdrop.addEventListener('click', function() {
                        if (el.id === 'vehicleViewMapModal') {
                            resetViewMapState();
                            if (viewMetaEl) {
                                viewMetaEl.textContent = 'Sin datos';
                            }
                            currentViewMapUrl = '';
                            setViewMapStatus('');
                        }

                        if (el.id === 'vehicleOdometroModal') {
                            odometroImageEl?.classList.add('d-none');
                            odometroImageEl?.removeAttribute('src');
                            odometroEmptyEl?.classList.remove('d-none');
                        }

                        forceHideModalEl(el);
                    });
                    document.body.appendChild(backdrop);
                }
            }

            function destroyLeafletMap(instance) {
                if (!instance) return null;
                try {
                    instance.off();
                    instance.remove();
                } catch (_) {}
                return null;
            }

            function formatNearestPlaceFromNominatim(data) {
                if (!data || typeof data !== 'object') return null;
                const address = data.address || {};
                const streetLine = [
                    address.road,
                    address.house_number,
                ].filter(Boolean).join(' ').trim();
                const areaLine =
                    address.neighbourhood ||
                    address.suburb ||
                    address.city_district ||
                    address.quarter ||
                    address.hamlet ||
                    null;
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
                const parts = [streetLine || null, areaLine, city, country].filter(Boolean);
                if (parts.length > 0) return parts.join(', ');
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
                            const bounds = resolveBoliviaBounds();
                            if (window.L && bounds && !bounds.contains(L.latLng(lat, lng))) {
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

                const component = window.Livewire ? window.Livewire.find('{{ $this->getId() }}') : null;
                if (!component) return;

                const model =
                    input.getAttribute('wire:model') ||
                    input.getAttribute('wire:model.live') ||
                    input.getAttribute('wire:model.lazy') ||
                    input.getAttribute('wire:model.defer');

                if (model) {
                    component.set(model, value);
                }
            }

            function pushCoordinatesToLivewire(lat, lng, latInputId, lngInputId) {
                const component = window.Livewire ? window.Livewire.find('{{ $this->getId() }}') : null;
                if (!component) return;

                const latValue = Number.parseFloat(lat);
                const lngValue = Number.parseFloat(lng);
                if (!Number.isFinite(latValue) || !Number.isFinite(lngValue)) return;

                const latMap = {
                    latitud_inicio_vehicle: 'latitud_inicio',
                    latitud_destino_vehicle: 'latitud_destino',
                };
                const lngMap = {
                    logitud_inicio_vehicle: 'logitud_inicio',
                    logitud_destino_vehicle: 'logitud_destino',
                };

                if (latInputId && latMap[latInputId]) {
                    component.set(latMap[latInputId], latValue);
                }
                if (lngInputId && lngMap[lngInputId]) {
                    component.set(lngMap[lngInputId], lngValue);
                }
            }

            function initMap() {
                if (!window.L) return;

                const container = document.getElementById('vehicle-location-map');
                if (!container) return;

                if (map) {
                    const currentContainer = map.getContainer ? map.getContainer() : null;
                    if (currentContainer !== container) {
                        map = destroyLeafletMap(map);
                        marker = null;
                    } else {
                        return;
                    }
                }

                const bounds = resolveBoliviaBounds();
                if (!bounds) {
                    updatePreview('No se pudo cargar el mapa. Recarga la pagina e intentalo de nuevo.');
                    return;
                }

                map = L.map(container, {
                    maxBounds: bounds,
                    maxBoundsViscosity: 1.0,
                }).setView([-16.5, -68.15], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                map.fitBounds(bounds);
                centerMapNearUser(map, updatePreview);

                map.on('click', async function(e) {
                    if (!bounds.contains(e.latlng)) {
                        updatePreview('Solo se permiten ubicaciones dentro de Bolivia.');
                        return;
                    }

                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;

                    if (!marker) marker = L.marker([lat, lng]).addTo(map);
                    else marker.setLatLng([lat, lng]);

                    updatePreview('Buscando nombre del lugar...');
                    const placeName = await reverseGeocode(lat, lng);
                    selected = {
                        lat,
                        lng,
                        name: placeName || 'Ubicacion seleccionada (Bolivia)'
                    };
                    updatePreview(`${selected.name} (${lat.toFixed(6)}, ${lng.toFixed(6)})`);
                });
            }

            modalEl.addEventListener('shown.bs.modal', function() {
                ensureLeafletAssets().then(() => {
                    initMap();
                    if (map) {
                        setTimeout(() => map.invalidateSize(), 100);
                        setTimeout(() => map.invalidateSize(), 260);
                    }
                }).catch(() => {
                    updatePreview('No se pudo cargar Leaflet (mapa). Verifica internet y recarga con Ctrl+F5.');
                });
            });

            document.addEventListener('click', function(event) {
                const btn = event.target.closest('.vehicle-location-picker-btn');
                if (!btn) return;

                activeTarget = {
                    inputId: btn.getAttribute('data-target-input'),
                    latId: btn.getAttribute('data-target-lat'),
                    lngId: btn.getAttribute('data-target-lng'),
                };
                selected = null;
                updatePreview('Sin ubicacion seleccionada.');

                if (marker && map) {
                    map.removeLayer(marker);
                    marker = null;
                }

                const {
                    panelEl,
                    mapEl,
                    metaEl,
                    helpEl
                } = getFormMapElements();
                const fromForm = !!btn.closest('form');
                if (mapEl && fromForm) {
                    if (panelEl) {
                        panelEl.classList.remove('d-none');
                    }
                    ensureFormSideMap();
                    resolveEffectiveTarget();
                    updateFormMapStatus();
                    scheduleFormSideMapResize();
                    return;
                }

                ensureLeafletAssets().catch(() => {
                    updatePreview('No se pudo cargar Leaflet (mapa). Verifica internet y recarga con Ctrl+F5.');
                });

                const modal = getModalInstance(modalEl);
                if (modal) modal.show();
            });

            confirmBtn?.addEventListener('click', function() {
                if (!selected || !activeTarget?.inputId) {
                    updatePreview('Selecciona un punto en el mapa primero.');
                    return;
                }
                const bounds = resolveBoliviaBounds();
                if (!window.L || !bounds || !bounds.contains(L.latLng(selected.lat, selected.lng))) {
                    updatePreview('Solo se permiten ubicaciones dentro de Bolivia.');
                    return;
                }

                setInputValue(activeTarget.inputId, selected.name);
                if (activeTarget.latId) setInputValue(activeTarget.latId, selected.lat.toFixed(8));
                if (activeTarget.lngId) setInputValue(activeTarget.lngId, selected.lng.toFixed(8));
                pushCoordinatesToLivewire(selected.lat, selected.lng, activeTarget.latId, activeTarget.lngId);

                const modal = getModalInstance(modalEl);
                if (modal) modal.hide();
            });

            document.addEventListener('click', function(event) {
                const closeBtn = event.target.closest('.vehicle-map-modal-close');
                if (!closeBtn) return;
                const modal = getModalInstance(modalEl);
                if (modal) modal.hide();
                forceHideModalEl(modalEl);
            });

            document.addEventListener('click', function(event) {
                const closePanelBtn = event.target.closest('.vehicle-form-map-close');
                if (!closePanelBtn) return;
                const {
                    panelEl,
                    metaEl,
                    helpEl
                } = getFormMapElements();
                if (panelEl) panelEl.classList.add('d-none');
                activeTarget = null;
                if (metaEl) metaEl.textContent = 'Seleccion de ubicacion en mapa';
                if (helpEl) helpEl.textContent = 'Pulsa "Elegir en mapa" en Inicio o Destino y luego haz clic en el mapa.';
            });

            const viewModalEl = document.getElementById('vehicleViewMapModal');
            if (viewModalEl && viewModalEl.parentElement !== document.body) {
                document.body.appendChild(viewModalEl);
            }
            const viewMetaEl = document.getElementById('vehicle-view-map-meta');
            const viewStatusEl = document.getElementById('vehicle-view-map-status');
            const reloadViewPointsBtn = document.getElementById('vehicle-reload-points-btn');
            const reloadViewMapBtn = document.getElementById('vehicle-reload-map-btn');
            const odometroModalEl = document.getElementById('vehicleOdometroModal');
            if (odometroModalEl && odometroModalEl.parentElement !== document.body) {
                document.body.appendChild(odometroModalEl);
            }
            const odometroMetaEl = document.getElementById('vehicle-odometro-meta');
            const odometroImageEl = document.getElementById('vehicle-odometro-image');
            const odometroEmptyEl = document.getElementById('vehicle-odometro-empty');
            const sideMapEl = document.getElementById('vehicle-side-map');
            const sideMetaEl = document.getElementById('vehicle-side-map-meta');
            const sideHelpEl = document.getElementById('vehicle-side-map-help');
            let viewMap = null;
            let viewLayer = null;
            let pendingViewPayload = null;
            let currentViewMapUrl = '';
            let currentViewMapRequest = 0;
            let currentViewMapFetchController = null;
            let sideMap = null;
            let sideLayer = null;
            let formSideMap = null;
            let formTargetMarkers = {};
            let formTargetLine = null;

            function ensureViewMap() {
                const container = document.getElementById('vehicle-view-map');
                if (!window.L || !viewModalEl || !container) return;

                if (viewMap && viewMap.getContainer && viewMap.getContainer() === container) {
                    return;
                }

                viewMap = L.map(container).setView([-16.5, -68.15], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(viewMap);
                viewLayer = L.layerGroup().addTo(viewMap);
                setTimeout(() => viewMap && viewMap.invalidateSize(), 80);
                setTimeout(() => viewMap && viewMap.invalidateSize(), 220);
                setTimeout(() => viewMap && viewMap.invalidateSize(), 420);
            }

            function recreateViewMap() {
                if (viewMap) {
                    viewMap = destroyLeafletMap(viewMap);
                    viewLayer = null;
                }

                ensureViewMap();
            }

            function setViewMapStatus(text = '', tone = 'primary') {
                if (!viewStatusEl) return;
                viewStatusEl.textContent = text;
                viewStatusEl.classList.remove('d-none', 'text-primary', 'text-danger', 'text-success', 'text-muted');
                viewStatusEl.classList.add(`text-${tone}`);
                if (!text) {
                    viewStatusEl.classList.add('d-none');
                }
            }

            function payloadHasDrawableData(payload) {
                if (!payload) return false;
                const hasRoute = Array.isArray(payload.route) && payload.route.length > 0;
                const hasStart = payload.startLat !== null && payload.startLng !== null;
                const hasEnd = payload.endLat !== null && payload.endLng !== null;

                return hasRoute || hasStart || hasEnd;
            }

            function ensureSideMap() {
                if (!window.L || !sideMapEl || sideMap) return;
                sideMap = L.map('vehicle-side-map').setView([-16.5, -68.15], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(sideMap);
                setTimeout(() => sideMap && sideMap.invalidateSize(), 100);
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
                        formSideMap = destroyLeafletMap(formSideMap);
                        formTargetMarkers = {};
                        formTargetLine = null;
                    } else {
                        return;
                    }
                }

                const bounds = resolveBoliviaBounds();
                if (!bounds) return;

                formSideMap = L.map(mapEl, {
                    maxBounds: bounds,
                    maxBoundsViscosity: 1.0,
                }).setView([-16.5, -68.15], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(formSideMap);
                formSideMap.fitBounds(bounds);
                centerMapNearUser(formSideMap, (text) => {
                    if (helpEl) helpEl.textContent = text;
                });

                formSideMap.on('click', async function(e) {
                    const target = resolveEffectiveTarget();
                    if (!target?.inputId) {
                        if (helpEl) {
                            helpEl.textContent = 'Primero pulsa "Elegir en mapa" en Inicio o Destino.';
                        }
                        return;
                    }

                    if (!bounds.contains(e.latlng)) {
                        if (helpEl) {
                            helpEl.textContent = 'Solo se permiten ubicaciones dentro de Bolivia.';
                        }
                        return;
                    }

                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;
                    const targetLabel = resolveTargetLabel(target.inputId);
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

                    const startMarker = formTargetMarkers.recorrido_inicio;
                    const endMarker = formTargetMarkers.recorrido_destino;
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

                    if (helpEl) {
                        helpEl.textContent = `${targetLabel}: ${name} (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                    }

                    if (target.inputId === 'recorrido_inicio') {
                        setActiveTargetByInputId('recorrido_destino');
                    } else {
                        setActiveTargetByInputId('recorrido_inicio');
                    }
                    setTimeout(() => updateFormMapStatus(), 120);
                });

                scheduleFormSideMapResize();
            }

            document.addEventListener('livewire:navigated', function() {
                map = destroyLeafletMap(map);
                marker = null;
                formSideMap = destroyLeafletMap(formSideMap);
                formTargetMarkers = {};
                formTargetLine = null;
                activeTarget = null;
            });

            function parseCoord(value) {
                const n = Number.parseFloat(value);
                return Number.isFinite(n) ? n : null;
            }

            function parseRoute(value) {
                if (!value) return [];
                try {
                    const parsed = JSON.parse(value);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (_) {
                    return [];
                }
            }

            function normalizeRoutePoints(route) {
                if (!Array.isArray(route)) return [];

                return route
                    .map((point) => {
                        if (!point || typeof point !== 'object') return null;

                        const lat = parseCoord(point.lat ?? point.latitude);
                        const lng = parseCoord(point.lng ?? point.longitude);
                        if (lat === null || lng === null) return null;

                        return {
                            lat,
                            lng,
                            address: point.address || point.label || point.point_label || '',
                        };
                    })
                    .filter(Boolean);
            }

            function drawTripOnViewMap(payload) {
                if (!viewMap) return;
                if (viewLayer) {
                    viewLayer.clearLayers();
                }

                const bounds = [];
                const routePoints = normalizeRoutePoints(payload.route);
                let start = null;
                let end = null;

                routePoints.forEach((point) => {
                    const coords = [point.lat, point.lng];
                    bounds.push(coords);
                });

                if (payload.startLat !== null && payload.startLng !== null) {
                    start = [payload.startLat, payload.startLng];
                    bounds.push(start);
                }
                if (payload.endLat !== null && payload.endLng !== null) {
                    end = [payload.endLat, payload.endLng];
                    bounds.push(end);
                }

                if (routePoints.length > 1) {
                    L.polyline(
                        routePoints.map((point) => [point.lat, point.lng]), {
                            color: '#2563eb',
                            weight: 4,
                            opacity: 0.9
                        }
                    ).addTo(viewLayer);
                } else if (start && end) {
                    L.polyline([start, end], {
                        color: '#2563eb',
                        weight: 4,
                        opacity: 0.9
                    }).addTo(viewLayer);
                }

                routePoints.forEach((point, index) => {
                    const coords = [point.lat, point.lng];
                    const popupText = point.address || point.label || `Punto ${index + 1}`;
                    L.circleMarker(coords, {
                        radius: 5,
                        color: '#1d4ed8',
                        weight: 2,
                        fillColor: '#93c5fd',
                        fillOpacity: 0.95,
                    }).addTo(viewLayer).bindPopup(popupText);
                });

                if (start) {
                    L.circleMarker(start, {
                        radius: 11,
                        color: '#14532d',
                        weight: 4,
                        fillColor: '#22c55e',
                        fillOpacity: 1,
                    }).addTo(viewLayer).bindPopup(`<strong>Inicio</strong><br>${payload.startName || '-'}`).bringToFront();
                }

                if (end) {
                    L.circleMarker(end, {
                        radius: 11,
                        color: '#7f1d1d',
                        weight: 4,
                        fillColor: '#ef4444',
                        fillOpacity: 1,
                    }).addTo(viewLayer).bindPopup(`<strong>Destino</strong><br>${payload.endName || '-'}`).bringToFront();
                }
                if (bounds.length > 0) {
                    const latLngBounds = L.latLngBounds(bounds).pad(0.2);
                    viewMap.fitBounds(latLngBounds, {
                        padding: [90, 90],
                        maxZoom: 15
                    });
                } else {
                    centerMapNearUser(viewMap);
                }
            }

            function refreshViewMapLayout(payload = null) {
                const render = () => {
                    ensureViewMap();
                    if (!viewMap) return;
                    viewMap.invalidateSize();
                    window.dispatchEvent(new Event('resize'));
                    if (payload) {
                        drawTripOnViewMap(payload);
                    }
                };

                setTimeout(render, 80);
                setTimeout(render, 220);
                setTimeout(render, 420);
                setTimeout(render, 700);
            }

            function forceReloadViewMap() {
                if (!pendingViewPayload || !payloadHasDrawableData(pendingViewPayload)) {
                    setViewMapStatus('Esta bitacora no tiene coordenadas suficientes para mostrar el mapa.', 'danger');
                    return;
                }

                recreateViewMap();

                const render = () => {
                    if (!viewMap) return;
                    viewMap.invalidateSize();
                    drawTripOnViewMap(pendingViewPayload);
                };

                window.setTimeout(render, 120);
                window.setTimeout(render, 320);
                window.setTimeout(render, 620);

                window.setTimeout(() => {
                    const layerCount = viewLayer && typeof viewLayer.getLayers === 'function' ?
                        viewLayer.getLayers().length :
                        0;

                    if (layerCount <= 0) {
                        setViewMapStatus('No se pudo dibujar el mapa. Usa "Recargar mapa" para intentarlo otra vez.', 'danger');
                        return;
                    }

                    setViewMapStatus('Mapa cargado correctamente.', 'success');
                    window.setTimeout(() => setViewMapStatus(''), 1200);
                }, 900);
            }

            function resetViewMapState() {
                if (currentViewMapFetchController) {
                    try {
                        currentViewMapFetchController.abort();
                    } catch (_) {}
                    currentViewMapFetchController = null;
                }

                if (viewLayer) {
                    viewLayer.clearLayers();
                }
                pendingViewPayload = null;
                currentViewMapRequest++;

                if (viewMap) {
                    viewMap = destroyLeafletMap(viewMap);
                    viewLayer = null;
                }
            }

            async function fetchViewMapPayload(mapUrl, fallbackPayload = null) {
                const requestId = ++currentViewMapRequest;
                currentViewMapUrl = mapUrl || currentViewMapUrl || '';

                if (viewMetaEl) {
                    viewMetaEl.textContent = 'Cargando recorrido...';
                }
                setViewMapStatus('Consultando datos de la bitacora...', 'primary');

                if (!currentViewMapUrl) {
                    return fallbackPayload;
                }

                try {
                    currentViewMapFetchController = typeof AbortController !== 'undefined' ?
                        new AbortController() :
                        null;

                    const response = await fetch(currentViewMapUrl, {
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: currentViewMapFetchController?.signal,
                    });

                    if (requestId !== currentViewMapRequest) return null;

                    if (!response.ok) {
                        setViewMapStatus('No se pudieron obtener los datos del mapa.', 'danger');
                        return fallbackPayload;
                    }

                    const data = await response.json();

                    return {
                        startLat: parseCoord(data.startLat),
                        startLng: parseCoord(data.startLng),
                        startName: data.startName || '',
                        endLat: parseCoord(data.endLat),
                        endLng: parseCoord(data.endLng),
                        endName: data.endName || '',
                        date: data.date || '',
                        vehicle: data.vehicle || '',
                        route: Array.isArray(data.route) ? data.route : [],
                    };
                } catch (_) {
                    if (requestId !== currentViewMapRequest) return null;
                    setViewMapStatus('Fallo la carga de la bitacora. Intenta nuevamente.', 'danger');
                    return fallbackPayload;
                } finally {
                    if (requestId === currentViewMapRequest) {
                        currentViewMapFetchController = null;
                    }
                }
            }

            function drawTripOnSideMap(payload) {
                if (!sideMap) return;
                if (sideLayer) {
                    sideLayer.clearLayers();
                } else {
                    sideLayer = L.layerGroup().addTo(sideMap);
                }

                const bounds = [];
                if (payload.startLat !== null && payload.startLng !== null) {
                    const start = [payload.startLat, payload.startLng];
                    L.marker(start).addTo(sideLayer).bindPopup(`<strong>Inicio</strong><br>${payload.startName || '-'}`);
                    bounds.push(start);
                }
                if (payload.endLat !== null && payload.endLng !== null) {
                    const end = [payload.endLat, payload.endLng];
                    L.marker(end).addTo(sideLayer).bindPopup(`<strong>Destino</strong><br>${payload.endName || '-'}`);
                    bounds.push(end);
                }
                if (bounds.length === 2) {
                    L.polyline(bounds, {
                        color: '#2563eb',
                        weight: 4,
                        opacity: 0.9
                    }).addTo(sideLayer);
                }
                if (bounds.length > 0) {
                    sideMap.fitBounds(bounds, {
                        padding: [24, 24]
                    });
                } else {
                    sideMap.setView([-16.5, -68.15], 6);
                }
            }

            if (viewModalEl) {
                viewModalEl.addEventListener('hidden.bs.modal', function() {
                    resetViewMapState();
                    if (viewMetaEl) {
                        viewMetaEl.textContent = 'Sin datos';
                    }
                    currentViewMapUrl = '';
                    setViewMapStatus('');
                });
            }

            document.addEventListener('click', function(event) {
                const closeBtn = event.target.closest("#vehicleViewMapModal .btn-close, #vehicleViewMapModal [data-bs-dismiss='modal'], #vehicleViewMapModal [data-dismiss='modal']");
                if (!closeBtn || !viewModalEl) return;
                event.preventDefault();
                resetViewMapState();
                if (viewMetaEl) {
                    viewMetaEl.textContent = 'Sin datos';
                }
                currentViewMapUrl = '';
                setViewMapStatus('');
                forceHideModalEl(viewModalEl);
            });

            document.addEventListener('click', function(event) {
                const closeBtn = event.target.closest("#vehicleOdometroModal .btn-close, #vehicleOdometroModal [data-bs-dismiss='modal'], #vehicleOdometroModal [data-dismiss='modal']");
                if (!closeBtn || !odometroModalEl) return;
                event.preventDefault();
                odometroImageEl?.classList.add('d-none');
                odometroImageEl?.removeAttribute('src');
                odometroEmptyEl?.classList.remove('d-none');
                forceHideModalEl(odometroModalEl);
            });

            document.addEventListener('click', function(event) {
                if (!viewModalEl || !odometroModalEl) return;

                if (event.target === viewModalEl) {
                    resetViewMapState();
                    if (viewMetaEl) {
                        viewMetaEl.textContent = 'Sin datos';
                    }
                    currentViewMapUrl = '';
                    setViewMapStatus('');
                    forceHideModalEl(viewModalEl);
                    return;
                }

                if (event.target === odometroModalEl) {
                    odometroImageEl?.classList.add('d-none');
                    odometroImageEl?.removeAttribute('src');
                    odometroEmptyEl?.classList.remove('d-none');
                    forceHideModalEl(odometroModalEl);
                }
            });

            reloadViewMapBtn?.addEventListener('click', function() {
                forceReloadViewMap();
            });

            reloadViewPointsBtn?.addEventListener('click', async function() {
                if (!currentViewMapUrl) {
                    setViewMapStatus('No hay una bitacora seleccionada para recargar.', 'danger');
                    return;
                }

                const payload = await fetchViewMapPayload(currentViewMapUrl, pendingViewPayload);
                if (!payload) return;

                pendingViewPayload = payload;
                if (viewMetaEl) {
                    viewMetaEl.textContent = `Vehiculo: ${payload.vehicle} | Fecha: ${payload.date} | Inicio: ${payload.startName || '-'} | Destino: ${payload.endName || '-'}`;
                }
                setViewMapStatus('Cargando puntos de la bitacora...', 'primary');
                forceReloadViewMap();
            });

            function buildMapPayloadFromButton(btn) {
                return {
                    startLat: parseCoord(btn.getAttribute('data-start-lat')),
                    startLng: parseCoord(btn.getAttribute('data-start-lng')),
                    startName: btn.getAttribute('data-start-name') || '',
                    endLat: parseCoord(btn.getAttribute('data-end-lat')),
                    endLng: parseCoord(btn.getAttribute('data-end-lng')),
                    endName: btn.getAttribute('data-end-name') || '',
                    date: btn.getAttribute('data-date') || '',
                    vehicle: btn.getAttribute('data-vehicle') || '',
                    route: [],
                };
            }

            document.addEventListener('click', async function(event) {
                const btn = event.target.closest('.vehicle-view-map-btn');
                if (!btn) return;

                let payload = buildMapPayloadFromButton(btn);
                const mapUrl = btn.getAttribute('data-map-url') || '';

                if (!viewModalEl) return;
                resetViewMapState();
                currentViewMapUrl = mapUrl;

                try {
                    await ensureLeafletAssets();
                } catch (_) {
                    setViewMapStatus('No se pudo cargar Leaflet (mapa). Verifica internet y recarga con Ctrl+F5.', 'danger');
                }

                forceShowModalEl(viewModalEl);

                payload = await fetchViewMapPayload(mapUrl, payload);
                if (!payload) return;

                if (sideMapEl) {
                    ensureSideMap();

                    if (sideMetaEl) {
                        sideMetaEl.textContent = `Vehiculo: ${payload.vehicle} | Fecha: ${payload.date}`;
                    }
                    if (sideHelpEl) {
                        sideHelpEl.textContent = `Inicio: ${payload.startName || '-'} | Destino: ${payload.endName || '-'}`;
                    }

                    setTimeout(() => {
                        ensureSideMap();
                        drawTripOnSideMap(payload);
                    }, 80);

                    return;
                }

                if (viewMetaEl) {
                    viewMetaEl.textContent = `Vehiculo: ${payload.vehicle} | Fecha: ${payload.date} | Inicio: ${payload.startName || '-'} | Destino: ${payload.endName || '-'}`;
                }
                pendingViewPayload = payload;
                if (!payloadHasDrawableData(payload)) {
                    setViewMapStatus('Esta bitacora no tiene coordenadas para mostrar en el mapa.', 'danger');
                    return;
                }
                setViewMapStatus('Construyendo mapa...', 'primary');
                forceReloadViewMap();
            });

            document.addEventListener('click', function(event) {
                const btn = event.target.closest('.vehicle-view-odometro-btn');
                if (!btn || !odometroModalEl) return;

                const photoUrl = btn.getAttribute('data-photo-url') || '';
                const vehicle = btn.getAttribute('data-vehicle') || 'N/A';
                const date = btn.getAttribute('data-date') || '';

                if (odometroMetaEl) {
                    odometroMetaEl.textContent = `Vehiculo: ${vehicle} | Fecha: ${date}`;
                }

                if (photoUrl && odometroImageEl) {
                    odometroImageEl.src = photoUrl;
                    odometroImageEl.classList.remove('d-none');
                    odometroEmptyEl?.classList.add('d-none');
                    odometroImageEl.onerror = function() {
                        odometroImageEl.classList.add('d-none');
                        odometroImageEl.removeAttribute('src');
                        odometroEmptyEl?.classList.remove('d-none');
                    };
                } else {
                    odometroImageEl?.classList.add('d-none');
                    odometroImageEl?.removeAttribute('src');
                    odometroEmptyEl?.classList.remove('d-none');
                }

                forceShowModalEl(odometroModalEl);
            });

        })();
    </script>
    <script>
        (function() {
            if (window.__vehicleFlashAutoDismissInitialized) return;
            window.__vehicleFlashAutoDismissInitialized = true;

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
                        }, 220);
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
    <div class="modal fade" id="vehicleDocumentModal" tabindex="-1" aria-labelledby="vehicleDocumentModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehicleDocumentModalLabel">Generar documento de bitacoras</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Elige si quieres imprimir, generar PDF o Excel, define el rango de fechas y marca exactamente los campos que deseas incluir.
                    </p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Accion</label>
                            <select class="form-control vehicle-log-select" id="vehicle-report-action">
                                <option value="print_pdf">Imprimir PDF</option>
                                <option value="download_pdf">Descargar PDF</option>
                                <option value="download_excel">Descargar Excel</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Fecha desde</label>
                            <input type="date" class="form-control" id="vehicle-report-fecha-desde" value="{{ $fecha_desde }}">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Fecha hasta</label>
                            <input type="date" class="form-control" id="vehicle-report-fecha-hasta" value="{{ $fecha_hasta }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Filtrar por</label>
                            <select class="form-control vehicle-log-select" id="vehicle-report-scope">
                                <option value="all">Todos los registros</option>
                                <option value="vehicle" {{ $vehicle_filter_id ? 'selected' : '' }}>Vehiculo especifico</option>
                                <option value="driver" {{ !$vehicle_filter_id && $driver_filter_id ? 'selected' : '' }}>Conductor especifico</option>
                            </select>
                        </div>
                        <div class="col-12" id="vehicle-report-vehicle-wrap">
                            <label class="form-label fw-bold">Vehiculo</label>
                            <select class="form-control vehicle-log-select" id="vehicle-report-vehicle-id">
                                <option value="">Todos los vehiculos</option>
                                @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" {{ (int) $vehicle_filter_id === (int) $vehicle->id ? 'selected' : '' }}>{{ $vehicle->placa }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12" id="vehicle-report-driver-wrap">
                            <label class="form-label fw-bold">Conductor</label>
                            <select class="form-control vehicle-log-select" id="vehicle-report-driver-id">
                                <option value="">Todos los conductores</option>
                                @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ (int) $driver_filter_id === (int) $driver->id ? 'selected' : '' }}>{{ $driver->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Busqueda libre</label>
                            <input type="text" class="form-control" id="vehicle-report-search" value="{{ trim($search) }}" placeholder="Opcional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold d-block">Campos del documento</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="vehicle-report-select-all">Todos</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="vehicle-report-clear-all">Ninguno</button>
                            </div>
                            <div class="row g-2" id="vehicle-report-columns">
                                @php
                                $vehicleDocumentColumns = [
                                'fecha' => 'Fecha',
                                'placa' => 'Placa',
                                'vehiculo' => 'Vehiculo',
                                'driver_name' => 'Conductor',
                                'kilometraje_salida' => 'KM salida',
                                'kilometraje_recorrido' => 'KM recorrido',
                                'kilometraje_llegada' => 'KM llegada',
                                'recorrido' => 'Recorrido',
                                'combustible' => 'Combustible',
                                ];
                                @endphp
                                @foreach($vehicleDocumentColumns as $value => $label)
                                <div class="col-6 col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input vehicle-report-column" type="checkbox" value="{{ $value }}" id="vehicle-column-{{ $value }}" checked>
                                        <label class="form-check-label" for="vehicle-column-{{ $value }}">{{ $label }}</label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <small class="text-muted">Puedes decidir exactamente que columnas quieres ver antes de imprimir o exportar.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        id="vehicle-report-submit"
                        data-pdf-url="{{ route('vehicle-logs.pdf') }}"
                        data-excel-url="{{ route('vehicle-logs.excel') }}">
                        Generar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function() {
            if (window.__vehicleReportModalInitialized) return;
            window.__vehicleReportModalInitialized = true;

            const scopeEl = document.getElementById('vehicle-report-scope');
            const vehicleWrap = document.getElementById('vehicle-report-vehicle-wrap');
            const driverWrap = document.getElementById('vehicle-report-driver-wrap');
            const vehicleEl = document.getElementById('vehicle-report-vehicle-id');
            const driverEl = document.getElementById('vehicle-report-driver-id');
            const actionEl = document.getElementById('vehicle-report-action');
            const fechaDesdeEl = document.getElementById('vehicle-report-fecha-desde');
            const fechaHastaEl = document.getElementById('vehicle-report-fecha-hasta');
            const searchEl = document.getElementById('vehicle-report-search');
            const submitEl = document.getElementById('vehicle-report-submit');
            const triggerEl = document.getElementById('vehicle-document-trigger');
            const modalEl = document.getElementById('vehicleDocumentModal');
            const selectAllEl = document.getElementById('vehicle-report-select-all');
            const clearAllEl = document.getElementById('vehicle-report-clear-all');

            if (!scopeEl || !submitEl) return;

            function setVehicleColumns(checked) {
                document.querySelectorAll('.vehicle-report-column').forEach((checkbox) => {
                    checkbox.checked = checked;
                });
            }

            function getVehicleBootstrapModalInstance(el) {
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

            function syncVehicleModalFromFilters() {
                const pageDateInputs = Array.from(document.querySelectorAll('input[type="date"]'));
                if (pageDateInputs[0] && fechaDesdeEl && !fechaDesdeEl.value) fechaDesdeEl.value = pageDateInputs[0].value;
                if (pageDateInputs[1] && fechaHastaEl && !fechaHastaEl.value) fechaHastaEl.value = pageDateInputs[1].value;
            }

            function syncVehicleScope() {
                const scope = scopeEl.value || 'all';
                if (vehicleWrap) vehicleWrap.style.display = scope === 'vehicle' ? '' : 'none';
                if (driverWrap) driverWrap.style.display = scope === 'driver' ? '' : 'none';
            }

            function buildVehicleUrl() {
                const params = new URLSearchParams();
                const action = actionEl ? actionEl.value : 'print_pdf';
                const baseUrl = action === 'download_excel' ?
                    submitEl.getAttribute('data-excel-url') :
                    submitEl.getAttribute('data-pdf-url');

                if (!baseUrl) return null;
                if (fechaDesdeEl && fechaDesdeEl.value) params.set('fecha_desde', fechaDesdeEl.value);
                if (fechaHastaEl && fechaHastaEl.value) params.set('fecha_hasta', fechaHastaEl.value);

                const search = searchEl ? searchEl.value.trim() : '';
                if (search !== '') params.set('q', search);

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

                const selectedColumns = Array.from(document.querySelectorAll('.vehicle-report-column:checked'))
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

            scopeEl.addEventListener('change', syncVehicleScope);
            syncVehicleScope();

            selectAllEl?.addEventListener('click', function() {
                setVehicleColumns(true);
            });

            clearAllEl?.addEventListener('click', function() {
                setVehicleColumns(false);
            });

            triggerEl?.addEventListener('click', function() {
                syncVehicleModalFromFilters();
                const modalInstance = getVehicleBootstrapModalInstance(modalEl);
                if (!modalInstance) return;
                modalInstance.show();
            });

            submitEl.addEventListener('click', function() {
                const url = buildVehicleUrl();
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
    <script>
        (function() {
            if (window.__vehicleModalCloseFallbackInit) return;
            window.__vehicleModalCloseFallbackInit = true;

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
                        hide: () => window.jQuery(el).modal('hide'),
                    };
                }
                return null;
            }

            const modalIds = [
                'vehicleLocationPickerModal',
                'vehicleViewMapModal',
                'vehicleOdometroModal',
                'vehicleDocumentModal',
            ];

            document.addEventListener('click', function(event) {
                modalIds.forEach(function(id) {
                    const modalEl = document.getElementById(id);
                    if (!modalEl) return;

                    const closeBtn = event.target.closest(
                        '#' + id + " .btn-close, #" + id + " [data-bs-dismiss='modal'], #" + id + " [data-dismiss='modal']"
                    );

                    if (closeBtn) {
                        const modal = getModalInstance(modalEl);
                        if (modal) modal.hide();
                        return;
                    }

                    if (event.target === modalEl) {
                        const modal = getModalInstance(modalEl);
                        if (modal) modal.hide();
                    }
                });
            });
        })();
    </script>
</div>
