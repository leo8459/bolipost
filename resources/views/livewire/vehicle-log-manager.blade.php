<div>
    <style>
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
    <div class="alert alert-success fade show vehicle-log-auto-dismiss" role="alert" data-timeout="3000" wire:key="vehicle-log-message-{{ md5(session('message')) }}">
        {{ session('message') }}
    </div>
    @endif

    @if (session('error'))
    <div class="alert alert-danger fade show vehicle-log-auto-dismiss" role="alert" data-timeout="3000" wire:key="vehicle-log-error-{{ md5(session('error')) }}">
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
                                        <select id="vehicles_id" wire:model.live="vehicles_id" wire:change="onVehicleChanged($event.target.value)" class="form-select @error('vehicles_id') is-invalid @enderror" required>
                                            <option value="">Seleccionar vehiculo...</option>
                                            @foreach ($vehicles as $vehicle)
                                            <option value="{{ $vehicle->id }}" data-km-actual="{{ $vehicle->kilometraje_actual ?? '' }}">{{ $vehicle->placa }}</option>
                                            @endforeach
                                        </select>
                                        @error('vehicles_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="drivers_id" class="form-label fw-bold">Conductor</label>
                                        <select id="drivers_id" wire:model="drivers_id" class="form-select">
                                            <option value="">Sin conductor</option>
                                            @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label for="fecha" class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                                        <input type="date" id="fecha" wire:model="fecha" class="form-control @error('fecha') is-invalid @enderror" required>
                                        @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="kilometraje_salida" class="form-label fw-bold">Km Salida <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" id="kilometraje_salida" wire:model="kilometraje_salida" class="form-control @error('kilometraje_salida') is-invalid @enderror" required>
                                        @error('kilometraje_salida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label for="kilometraje_llegada" class="form-label fw-bold">Km Llegada</label>
                                        <input type="number" step="0.01" id="kilometraje_llegada" wire:model="kilometraje_llegada" class="form-control">
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
                                        <div class="form-check">
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
                <a
                    class="btn btn-outline-light bitacora-shell__btn-find"
                    href="{{ route('vehicle-logs.pdf', array_filter([
                                'q' => trim($search) !== '' ? trim($search) : null,
                            ])) }}"
                    target="_blank"
                    rel="noopener noreferrer">
                    <i class="fas fa-print me-2"></i>Imprimir PDF
                </a>
                <a
                    class="btn btn-outline-light bitacora-shell__btn-find"
                    href="{{ route('vehicle-logs.pdf', array_filter([
                                'q' => trim($search) !== '' ? trim($search) : null,
                                'download' => 1,
                            ])) }}">
                    <i class="fas fa-file-arrow-down me-2"></i>Descargar PDF
                </a>
                @endif
                @if(auth()->user()?->role !== 'conductor')
                <button type="button" wire:click="create" class="btn bitacora-shell__btn-new">Nuevo</button>
                @endif
            </div>
        </div>

        <div class="bitacora-shell__body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="bitacora-shell__meta">
                    @if (trim($search) === '')
                    Mostrando todos los registros
                    @else
                    Resultados para: "{{ $search }}"
                    @endif
                </div>
                <div class="bitacora-shell__count">Total en pagina: {{ $logs->count() }}</div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="bitacora-shell__table-wrap">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Vehiculo</th>
                                        <th>Conductor</th>
                                        <th>Fecha</th>
                                        <th>Km salida</th>
                                        <th>Km llegada</th>
                                        <th>Inicio</th>
                                        <th>Destino</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($logs as $log)
                                    <tr>
                                        <td>{{ $log->vehicle?->placa ?? 'N/A' }}</td>
                                        <td>{{ $log->driver?->nombre ?? 'N/A' }}</td>
                                        <td>{{ optional($log->fecha)->format('d/m/Y') }}</td>
                                        <td>{{ $log->kilometraje_salida }}</td>
                                        <td>{{ $log->kilometraje_llegada ?? '-' }}</td>
                                        <td>{{ $log->recorrido_inicio ?: '-' }}</td>
                                        <td>{{ $log->recorrido_destino ?: '-' }}</td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                @if(($log->latitud_inicio && $log->logitud_inicio) || ($log->latitud_destino && $log->logitud_destino))
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-info vehicle-view-map-btn"
                                                    data-start-lat="{{ $log->latitud_inicio }}"
                                                    data-start-lng="{{ $log->logitud_inicio }}"
                                                    data-start-name="{{ $log->recorrido_inicio }}"
                                                    data-end-lat="{{ $log->latitud_destino }}"
                                                    data-end-lng="{{ $log->logitud_destino }}"
                                                    data-end-name="{{ $log->recorrido_destino }}"
                                                    data-date="{{ optional($log->fecha)->format('d/m/Y') }}"
                                                    data-vehicle="{{ $log->vehicle?->placa ?? 'N/A' }}"
                                                    data-km-salida="{{ $log->kilometraje_salida }}"
                                                    data-km-llegada="{{ $log->kilometraje_llegada }}"
                                                    data-route="{{ e(json_encode($log->points_json ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}">
                                                    <i class="fas fa-map-marked-alt"></i>
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
                                        <td colspan="8" class="text-center text-muted py-5 bitacora-shell__empty">
                                            No hay registros<br>Prueba con otro texto de busqueda.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="modal fade" id="vehicleLocationPickerModal" tabindex="-1" aria-labelledby="vehicleLocationPickerLabel" aria-hidden="true" wire:ignore>
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
                    <div id="vehicle-location-map"></div>
                    <div class="mt-3 small text-muted" id="vehicle-selected-location-preview">Sin ubicacion seleccionada.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary vehicle-map-modal-close" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="vehicle-confirm-location-btn">Usar ubicacion</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vehicleViewMapModal" tabindex="-1" aria-labelledby="vehicleViewMapLabel" aria-hidden="true" wire:ignore>
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
                    <div id="vehicle-view-map"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function() {
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

            const modalEl = ensureSingleModalInstance('vehicleLocationPickerModal');
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
                const streetParts = [
                    address.road,
                    address.house_number,
                    address.neighbourhood,
                    address.suburb,
                    address.city_district,
                    address.city || address.town || address.village || address.municipality,
                    address.state,
                    address.country || 'Bolivia',
                ].filter((part, index, array) => {
                    const value = typeof part === 'string' ? part.trim() : '';
                    return value !== '' && array.findIndex((item) => item === part) === index;
                });

                if (streetParts.length > 0) {
                    return streetParts.join(', ');
                }

                return data.display_name || null;
            }

            function initAutoDismissAlerts() {
                document.querySelectorAll('.vehicle-log-auto-dismiss').forEach((alertEl) => {
                    if (alertEl.dataset.dismissBound === '1') return;
                    alertEl.dataset.dismissBound = '1';

                    const timeout = Number.parseInt(alertEl.getAttribute('data-timeout') || '3000', 10);
                    window.setTimeout(() => {
                        if (!alertEl.isConnected) return;
                        alertEl.classList.remove('show');
                        alertEl.style.transition = 'opacity 0.25s ease';
                        alertEl.style.opacity = '0';
                        window.setTimeout(() => {
                            if (alertEl.isConnected) {
                                alertEl.remove();
                            }
                        }, 260);
                    }, Number.isFinite(timeout) ? timeout : 3000);
                });
            }

            function ensureSingleModalInstance(modalId) {
                const nodes = Array.from(document.querySelectorAll(`#${modalId}`));
                if (nodes.length <= 1) {
                    return nodes[0] || null;
                }

                const survivor = nodes[nodes.length - 1];
                nodes.slice(0, -1).forEach((node) => {
                    try {
                        node.remove();
                    } catch (_) {}
                });

                return survivor;
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

            initAutoDismissAlerts();
            document.addEventListener('livewire:navigated', initAutoDismissAlerts);

            if (!window.__vehicleLogAlertObserverInitialized) {
                window.__vehicleLogAlertObserverInitialized = true;
                const alertObserver = new MutationObserver(() => initAutoDismissAlerts());
                alertObserver.observe(document.body, {
                    childList: true,
                    subtree: true,
                });
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
                    targetMap.setView([coords.lat, coords.lng], 16);
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
                initMap();
                if (map) {
                    setTimeout(() => map.invalidateSize(), 100);
                    setTimeout(() => map.invalidateSize(), 260);
                }
                if (!window.L) {
                    updatePreview('No se pudo cargar Leaflet (mapa). Verifica internet y recarga con Ctrl+F5.');
                }
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

                const modal = getModalInstance(modalEl);
                if (modal) modal.show();

                if (!window.L) {
                    updatePreview('No se pudo cargar Leaflet (mapa). Verifica internet y recarga con Ctrl+F5.');
                }
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

            const viewModalEl = ensureSingleModalInstance('vehicleViewMapModal');
            if (viewModalEl && viewModalEl.parentElement !== document.body) {
                document.body.appendChild(viewModalEl);
            }
            const viewMetaEl = document.getElementById('vehicle-view-map-meta');
            const sideMapEl = document.getElementById('vehicle-side-map');
            const sideMetaEl = document.getElementById('vehicle-side-map-meta');
            const sideHelpEl = document.getElementById('vehicle-side-map-help');
            let viewMap = null;
            let viewLayer = null;
            let pendingViewPayload = null;
            let sideMap = null;
            let sideLayer = null;
            let formSideMap = null;
            let formTargetMarkers = {};
            let formTargetLine = null;

            function ensureViewMap() {
                if (!window.L || !viewModalEl || viewMap) return;
                viewMap = L.map('vehicle-view-map').setView([-16.5, -68.15], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(viewMap);
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
                            label: point.label || point.point_type || '',
                        };
                    })
                    .filter(Boolean);
            }

            function calculateRouteDistanceKm(routePoints) {
                if (!Array.isArray(routePoints) || routePoints.length < 2) return 0;

                const toRad = (value) => (value * Math.PI) / 180;
                let total = 0;

                for (let i = 0; i < routePoints.length - 1; i += 1) {
                    const start = routePoints[i];
                    const end = routePoints[i + 1];
                    const earthRadiusKm = 6371;
                    const dLat = toRad(end.lat - start.lat);
                    const dLng = toRad(end.lng - start.lng);
                    const a =
                        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(toRad(start.lat)) * Math.cos(toRad(end.lat)) *
                        Math.sin(dLng / 2) * Math.sin(dLng / 2);
                    total += 2 * earthRadiusKm * Math.asin(Math.min(1, Math.sqrt(a)));
                }

                return total;
            }

            function drawTripOnViewMap(payload) {
                if (!viewMap) return;
                if (viewLayer) {
                    viewLayer.clearLayers();
                } else {
                    viewLayer = L.layerGroup().addTo(viewMap);
                }

                const routePoints = normalizeRoutePoints(payload.route);
                const bounds = [];
                const linePoints = routePoints.length > 1
                    ? routePoints.map((point) => {
                        const latLng = [point.lat, point.lng];
                        bounds.push(latLng);
                        return latLng;
                    })
                    : [];

                if (payload.startLat !== null && payload.startLng !== null) {
                    const start = [payload.startLat, payload.startLng];
                    L.marker(start).addTo(viewLayer).bindPopup(`<strong>Inicio</strong><br>${payload.startName || '-'}`);
                    if (linePoints.length === 0) bounds.push(start);
                }
                if (payload.endLat !== null && payload.endLng !== null) {
                    const end = [payload.endLat, payload.endLng];
                    L.marker(end).addTo(viewLayer).bindPopup(`<strong>Destino</strong><br>${payload.endName || '-'}`);
                    if (linePoints.length === 0) bounds.push(end);
                }
                if (linePoints.length > 1) {
                    L.polyline(linePoints, {
                        color: '#2563eb',
                        weight: 4,
                        opacity: 0.9
                    }).addTo(viewLayer);
                } else if (bounds.length === 2) {
                    L.polyline(bounds, {
                        color: '#2563eb',
                        weight: 4,
                        opacity: 0.9
                    }).addTo(viewLayer);
                }
                if (bounds.length > 0) {
                    viewMap.fitBounds(bounds, {
                        padding: [30, 30]
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
                    if (payload) {
                        drawTripOnViewMap(payload);
                    }
                };

                setTimeout(render, 80);
                setTimeout(render, 220);
                setTimeout(render, 420);
            }

            function drawTripOnSideMap(payload) {
                if (!sideMap) return;
                if (sideLayer) {
                    sideLayer.clearLayers();
                } else {
                    sideLayer = L.layerGroup().addTo(sideMap);
                }

                const routePoints = normalizeRoutePoints(payload.route);
                const bounds = [];
                const linePoints = routePoints.length > 1
                    ? routePoints.map((point) => {
                        const latLng = [point.lat, point.lng];
                        bounds.push(latLng);
                        return latLng;
                    })
                    : [];

                if (payload.startLat !== null && payload.startLng !== null) {
                    const start = [payload.startLat, payload.startLng];
                    L.marker(start).addTo(sideLayer).bindPopup(`<strong>Inicio</strong><br>${payload.startName || '-'}`);
                    if (linePoints.length === 0) bounds.push(start);
                }
                if (payload.endLat !== null && payload.endLng !== null) {
                    const end = [payload.endLat, payload.endLng];
                    L.marker(end).addTo(sideLayer).bindPopup(`<strong>Destino</strong><br>${payload.endName || '-'}`);
                    if (linePoints.length === 0) bounds.push(end);
                }
                if (linePoints.length > 1) {
                    L.polyline(linePoints, {
                        color: '#2563eb',
                        weight: 4,
                        opacity: 0.9
                    }).addTo(sideLayer);
                } else if (bounds.length === 2) {
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
                    centerMapNearUser(sideMap, (text) => {
                        if (sideHelpEl) sideHelpEl.textContent = text;
                    });
                }
            }

            if (viewModalEl) {
                viewModalEl.addEventListener('shown.bs.modal', function() {
                    ensureViewMap();
                    refreshViewMapLayout(pendingViewPayload);
                });

                if (window.jQuery) {
                    window.jQuery(viewModalEl).on('shown.bs.modal', function() {
                        ensureViewMap();
                        refreshViewMapLayout(pendingViewPayload);
                    });
                }
            }

            document.addEventListener('click', function(event) {
                const btn = event.target.closest('.vehicle-view-map-btn');
                if (!btn) return;

                const payload = {
                    startLat: parseCoord(btn.getAttribute('data-start-lat')),
                    startLng: parseCoord(btn.getAttribute('data-start-lng')),
                    startName: btn.getAttribute('data-start-name') || '',
                    endLat: parseCoord(btn.getAttribute('data-end-lat')),
                    endLng: parseCoord(btn.getAttribute('data-end-lng')),
                    endName: btn.getAttribute('data-end-name') || '',
                    date: btn.getAttribute('data-date') || '',
                    vehicle: btn.getAttribute('data-vehicle') || '',
                    kmSalida: parseCoord(btn.getAttribute('data-km-salida')),
                    kmLlegada: parseCoord(btn.getAttribute('data-km-llegada')),
                    route: parseRoute(btn.getAttribute('data-route')),
                };

                const routeDistance = calculateRouteDistanceKm(normalizeRoutePoints(payload.route));
                const odometerDistance =
                    payload.kmSalida !== null && payload.kmLlegada !== null
                        ? Math.max(0, payload.kmLlegada - payload.kmSalida)
                        : null;
                const distanceParts = [];
                if (odometerDistance !== null) {
                    distanceParts.push(`Km recorridos: ${odometerDistance.toFixed(2)}`);
                }
                if (routeDistance > 0) {
                    distanceParts.push(`Distancia del mapa: ${routeDistance.toFixed(2)} km`);
                }

                if (sideMapEl) {
                    ensureSideMap();

                    if (sideMetaEl) {
                        sideMetaEl.textContent = `Vehiculo: ${payload.vehicle} | Fecha: ${payload.date}`;
                    }
                    if (sideHelpEl) {
                        sideHelpEl.textContent = `Inicio: ${payload.startName || '-'} | Destino: ${payload.endName || '-'}${distanceParts.length ? ` | ${distanceParts.join(' | ')}` : ''}`;
                    }

                    setTimeout(() => {
                        ensureSideMap();
                        drawTripOnSideMap(payload);
                    }, 80);

                    return;
                }

                if (!viewModalEl) return;
                if (viewMetaEl) {
                    viewMetaEl.textContent = `Vehiculo: ${payload.vehicle} | Fecha: ${payload.date}${distanceParts.length ? ` | ${distanceParts.join(' | ')}` : ''}`;
                }
                pendingViewPayload = payload;
                const modal = getModalInstance(viewModalEl);
                if (modal) modal.show();
                refreshViewMapLayout(payload);
            });
        })();
    </script>
</div>
