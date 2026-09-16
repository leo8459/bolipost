@once
    @push('css')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
            #vehicle-map {
                width: 100%;
                min-height: 68vh;
                border-radius: 10px;
                border: 1px solid #dbe3ee;
            }
            .map-panel {
                border-radius: 10px;
            }
            .vehicle-list {
                max-height: 68vh;
                overflow-y: auto;
            }
            .operation-alerts {
                max-height: 24vh;
                overflow-y: auto;
            }
            .mobile-device-list {
                max-height: 24vh;
                overflow-y: auto;
            }
            .vehicle-item {
                border: 1px solid #e7edf5;
                border-radius: 8px;
                padding: 10px;
                margin-bottom: 8px;
                cursor: pointer;
            }
            .vehicle-item:hover {
                border-color: #bfd3ec;
                background: #f6faff;
            }
            .vehicle-item.selected {
                border-color: #00509d;
                background: #eaf3ff;
                box-shadow: inset 0 0 0 1px #00509d;
            }
            .map-vehicle-icon {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                background: #00509d;
                color: #ffcc00;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #ffcc00;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
                font-size: 14px;
            }
            .map-vehicle-icon.stale {
                background: #b91c1c;
                border-color: #fee2e2;
                color: #fee2e2;
            }
            .map-vehicle-icon.live {
                background: #065f46;
                border-color: #bbf7d0;
                color: #dcfce7;
            }
            .map-vehicle-icon.status-en_ruta,
            .map-vehicle-icon.status-espera,
            .map-vehicle-icon.status-inicio,
            .map-vehicle-icon.status-continuar {
                background: #16a34a;
                border-color: #dcfce7;
                color: #f0fdf4;
            }
            .map-vehicle-icon.status-carga {
                background: #2563eb;
                border-color: #dbeafe;
                color: #eff6ff;
            }
            .map-vehicle-icon.status-entrega {
                background: #eab308;
                border-color: #fef3c7;
                color: #422006;
            }
            .map-device-icon {
                width: 72px;
                height: 54px;
                object-fit: contain;
                filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.35));
            }
            .map-device-icon.moving {
                transform: scale(1.08);
            }
            .map-device-icon.stale {
                filter: grayscale(1) opacity(0.7) drop-shadow(0 2px 3px rgba(0, 0, 0, 0.3));
            }
            .chasqui-list-icon {
                width: 44px;
                height: 34px;
                object-fit: contain;
                flex: 0 0 auto;
            }
            .operation-alert-item {
                border: 1px solid #e7edf5;
                border-radius: 8px;
                padding: 10px;
                margin-bottom: 8px;
            }
            .operation-alert-item.danger {
                background: #fef2f2;
                border-color: #fecaca;
            }
            .operation-alert-item.warning {
                background: #fffbeb;
                border-color: #fde68a;
            }
            .operation-alert-item.info {
                background: #eff6ff;
                border-color: #bfdbfe;
            }
            .operation-alert-item.success {
                background: #f0fdf4;
                border-color: #bbf7d0;
            }
            .operation-alert-item.secondary {
                background: #f8fafc;
                border-color: #cbd5e1;
            }
            .map-panel-toggle {
                border: 0;
                background: transparent;
                color: #0d3b77;
                font-size: 0.95rem;
                padding: 0.15rem 0.35rem;
                line-height: 1;
            }
            .map-panel-toggle:hover {
                color: #072a55;
            }
            .map-panel-collapsed {
                display: none;
            }
            .trajectory-controls {
                border: 1px solid #c7d7ec;
                border-left: 5px solid #00509d;
                border-radius: 10px;
                background: #f7fbff;
                padding: 10px 12px;
                margin-bottom: 12px;
            }
            .trajectory-date-label {
                color: #00509d;
                font-weight: 700;
                min-width: 230px;
            }
            .route-loading-modal {
                position: fixed;
                inset: 0;
                z-index: 10050;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: rgba(5, 30, 58, 0.58);
                backdrop-filter: blur(3px);
            }
            .route-loading-modal.show {
                display: flex;
                animation: routeBackdropIn 180ms ease-out;
            }
            .route-loading-dialog {
                width: min(92vw, 430px);
                border: 2px solid #ffcc00;
                border-radius: 18px;
                background: #ffffff;
                box-shadow: 0 18px 55px rgba(0, 0, 0, 0.35);
                padding: 24px;
                text-align: center;
                animation: routeDialogIn 260ms ease-out;
            }
            .route-loading-rider {
                width: 120px;
                height: 90px;
                object-fit: contain;
                animation: routeRiderMove 900ms ease-in-out infinite alternate;
            }
            .route-loading-progress {
                height: 7px;
                overflow: hidden;
                border-radius: 999px;
                background: #e7edf5;
            }
            .route-loading-progress::after {
                content: '';
                display: block;
                width: 42%;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #00509d, #ffcc00);
                animation: routeProgress 1.1s ease-in-out infinite;
            }
            @keyframes routeBackdropIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes routeDialogIn {
                from { opacity: 0; transform: translateY(14px) scale(0.96); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            @keyframes routeRiderMove {
                from { transform: translateX(-12px); }
                to { transform: translateX(12px); }
            }
            @keyframes routeProgress {
                from { transform: translateX(-110%); }
                to { transform: translateX(240%); }
            }
        </style>
    @endpush
@endonce

<div class="bp-livewire-skin">
    @include('livewire.partials.button-theme')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="page-title mb-0">
            <i class="fas fa-map-marked-alt me-2"></i>Mapa de Vehiculos y Chasquis
        </h1>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Modo mapa">
                <button type="button" id="mode-online" class="btn btn-primary">Tiempo Real</button>
                <button type="button" id="mode-offline" class="btn btn-outline-secondary">Trayectoria</button>
            </div>
            <div id="realtime-filter-controls" class="d-flex flex-wrap align-items-center gap-2">
                <select id="realtime-chasqui-filter" class="form-select form-select-sm" style="min-width: 250px;" aria-label="Filtrar cartero en tiempo real">
                    <option value="">Todos los carteros en vivo</option>
                </select>
                <select id="vehicle-filter" class="form-select form-select-sm" style="min-width: 220px;" aria-label="Filtrar vehiculo en tiempo real">
                    <option value="">Todos los vehiculos</option>
                </select>
            </div>
            <div class="text-muted small">
                <span id="map-refresh-status">Actualizacion cada <strong>2s</strong></span> |
                Ultima carga: <span id="last-update">-</span>
            </div>
        </div>
    </div>

    <div id="trajectory-controls" class="trajectory-controls d-none">
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="offline-date" class="form-label small fw-bold mb-1">Día del recorrido</label>
                <div class="input-group input-group-sm">
                    <button type="button" id="previous-route-day" class="btn btn-outline-primary" title="Día anterior">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <input
                        type="date"
                        id="offline-date"
                        class="form-control"
                        value="{{ now()->toDateString() }}"
                        style="min-width: 155px;"
                    >
                    <button type="button" id="next-route-day" class="btn btn-outline-primary" title="Día siguiente">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div>
                <label for="chasqui-filter" class="form-label small fw-bold mb-1">Cartero</label>
                <select id="chasqui-filter" class="form-select form-select-sm" style="min-width: 280px;">
                    <option value="">Todos los carteros</option>
                </select>
            </div>
            <button type="button" id="show-chasqui-route" class="btn btn-sm btn-warning text-nowrap">
                <i class="fas fa-route me-1"></i>Mostrar trayectoria
            </button>
            <div id="trajectory-date-label" class="trajectory-date-label pb-1"></div>
        </div>
        <div class="small text-muted mt-2">
            La trayectoria permanece fija. Pasa el cursor o haz clic sobre los puntos para consultar la hora.
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card map-panel shadow-sm">
                <div class="card-body p-2">
                    <div id="vehicle-map" wire:ignore></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div id="alerts-card" class="card map-panel shadow-sm mb-3">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span>Alertas operativas</span>
                    <button
                        type="button"
                        class="map-panel-toggle"
                        id="toggle-alerts-panel"
                        data-target="operation-alerts-panel"
                        aria-expanded="true"
                        title="Contraer alertas operativas"
                    >
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <div id="operation-alerts-panel">
                    <div class="card-body operation-alerts" id="operation-alerts"></div>
                </div>
            </div>
            <div id="vehicles-card" class="card map-panel shadow-sm">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span>Vehiculos en mapa</span>
                    <button
                        type="button"
                        class="map-panel-toggle"
                        id="toggle-vehicles-panel"
                        data-target="vehicle-list-panel"
                        aria-expanded="true"
                        title="Contraer vehiculos en mapa"
                    >
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <div id="vehicle-list-panel">
                    <div class="card-body vehicle-list" id="vehicle-list"></div>
                </div>
            </div>
            <div class="card map-panel shadow-sm mt-3">
                <div id="chasqui-panel-title" class="card-header fw-bold">Carteros ChasquiApp</div>
                <div class="card-body mobile-device-list" id="mobile-device-list">
                    <div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Cargando carteros...</div>
                </div>
            </div>
        </div>
    </div>

    <div id="route-loading-modal" class="route-loading-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="route-loading-title">
        <div class="route-loading-dialog">
            <img
                src="{{ asset('images/map/chasqui-correos-marker.png') }}"
                class="route-loading-rider"
                alt="Cargando recorrido del cartero"
            >
            <h3 id="route-loading-title" class="h5 fw-bold text-primary mb-2">Cargando trayectoria</h3>
            <div id="route-loading-detail" class="text-muted mb-3">Consultando puntos y horarios...</div>
            <div class="route-loading-progress" aria-hidden="true"></div>
            <div class="small text-muted mt-3">Estamos preparando la ruta sobre el mapa.</div>
        </div>
    </div>
</div>

@once
    @push('js')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function () {
                if (window.__livewireMapTrackerInitialized) return;
                window.__livewireMapTrackerInitialized = true;

                const map = L.map('vehicle-map').setView([-16.5, -68.15], 12);
                const dataUrl = @json(route('map.data'));
                const listEl = document.getElementById('vehicle-list');
                const mobileDeviceListEl = document.getElementById('mobile-device-list');
                const alertsEl = document.getElementById('operation-alerts');
                const alertsPanelEl = document.getElementById('operation-alerts-panel');
                const vehicleListPanelEl = document.getElementById('vehicle-list-panel');
                const alertsCardEl = document.getElementById('alerts-card');
                const vehiclesCardEl = document.getElementById('vehicles-card');
                const chasquiPanelTitleEl = document.getElementById('chasqui-panel-title');
                const lastUpdateEl = document.getElementById('last-update');
                const refreshStatusEl = document.getElementById('map-refresh-status');
                const btnOnline = document.getElementById('mode-online');
                const btnOffline = document.getElementById('mode-offline');
                const offlineDateEl = document.getElementById('offline-date');
                const vehicleFilterEl = document.getElementById('vehicle-filter');
                const realtimeChasquiFilterEl = document.getElementById('realtime-chasqui-filter');
                const chasquiFilterEl = document.getElementById('chasqui-filter');
                const showChasquiRouteBtn = document.getElementById('show-chasqui-route');
                const trajectoryControlsEl = document.getElementById('trajectory-controls');
                const realtimeFilterControlsEl = document.getElementById('realtime-filter-controls');
                const previousRouteDayBtn = document.getElementById('previous-route-day');
                const nextRouteDayBtn = document.getElementById('next-route-day');
                const trajectoryDateLabelEl = document.getElementById('trajectory-date-label');
                const routeLoadingModalEl = document.getElementById('route-loading-modal');
                const routeLoadingDetailEl = document.getElementById('route-loading-detail');
                const panelStoragePrefix = 'bolipost-map-panel-';
                const overlays = new Map();
                const queryParams = new URLSearchParams(window.location.search);
                const initialMode = queryParams.get('mode') === 'offline' ? 'offline' : 'online';
                let currentMode = initialMode;
                let effectiveMode = initialMode;
                let selectedVehicleId = null;
                let selectedLastPoint = null;
                let filteredVehicleId = '';
                let filteredChasquiUserId = '';
                let filteredRealtimeChasquiUserId = '';
                let loadedRealtimeRouteUserId = '';
                let currentOfflineDate = queryParams.get('date') || @json(now()->toDateString());
                let currentMobileDevices = [];
                let hasInitializedViewport = false;
                let refreshRequestId = 0;
                let activeRefreshController = null;
                const liveRoutePointsByLocation = new Map();
                const refreshIntervalMs = 2000;
                const chasquiMarkerUrl = @json(asset('images/map/chasqui-correos-marker.png'));
                const showRouteButtonHtml = showChasquiRouteBtn?.innerHTML || '';

                function showRouteLoading() {
                    if (!routeLoadingModalEl) return;

                    const courierName = chasquiFilterEl?.selectedOptions?.[0]?.textContent?.trim()
                        || 'Todos los carteros';
                    if (routeLoadingDetailEl) {
                        routeLoadingDetailEl.textContent = `${courierName} · ${formatSelectedDate(currentOfflineDate)}`;
                    }
                    routeLoadingModalEl.classList.add('show');
                    routeLoadingModalEl.setAttribute('aria-hidden', 'false');

                    if (showChasquiRouteBtn) {
                        showChasquiRouteBtn.disabled = true;
                        showChasquiRouteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cargando ruta...';
                    }
                }

                function hideRouteLoading() {
                    if (routeLoadingModalEl) {
                        routeLoadingModalEl.classList.remove('show');
                        routeLoadingModalEl.setAttribute('aria-hidden', 'true');
                    }

                    if (showChasquiRouteBtn) {
                        showChasquiRouteBtn.disabled = false;
                        showChasquiRouteBtn.innerHTML = showRouteButtonHtml;
                    }
                }

                function calculateRouteDistanceKm(points) {
                    let distanceKm = 0;
                    const earthRadiusKm = 6371;
                    const radians = (degrees) => degrees * Math.PI / 180;

                    for (let index = 1; index < points.length; index += 1) {
                        const previous = points[index - 1];
                        const current = points[index];
                        const deltaLat = radians(current.lat - previous.lat);
                        const deltaLng = radians(current.lng - previous.lng);
                        const lat1 = radians(previous.lat);
                        const lat2 = radians(current.lat);
                        const a = Math.sin(deltaLat / 2) ** 2
                            + Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLng / 2) ** 2;
                        distanceKm += earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
                    }

                    return Math.round(distanceKm * 1000) / 1000;
                }

                function mergeRealtimeDevices(devices, routeIncluded) {
                    if (!filteredRealtimeChasquiUserId) {
                        return devices;
                    }

                    return devices.map((device) => {
                        const locationId = String(device.location_id || `user:${device.user_id || ''}`);
                        const incomingPoints = (Array.isArray(device.points) ? device.points : [])
                            .map((point) => ({
                                ...point,
                                lat: Number(point.lat),
                                lng: Number(point.lng),
                                t: point.t ? String(point.t) : '',
                            }))
                            .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));
                        const existingPoints = routeIncluded
                            ? []
                            : (liveRoutePointsByLocation.get(locationId) || []);
                        const uniquePoints = new Map();

                        [...existingPoints, ...incomingPoints].forEach((point) => {
                            uniquePoints.set(`${point.t}|${point.lat}|${point.lng}`, point);
                        });

                        const mergedPoints = Array.from(uniquePoints.values())
                            .sort((left, right) => String(left.t).localeCompare(String(right.t)))
                            .slice(-10000);
                        liveRoutePointsByLocation.set(locationId, mergedPoints);

                        return {
                            ...device,
                            points: mergedPoints,
                            points_count: mergedPoints.length,
                            distance_km: calculateRouteDistanceKm(mergedPoints),
                        };
                    });
                }

                function setPanelState(buttonEl, panelEl, collapsed) {
                    if (!buttonEl || !panelEl) return;
                    panelEl.classList.toggle('map-panel-collapsed', collapsed);
                    buttonEl.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    buttonEl.title = collapsed ? 'Desplegar panel' : 'Contraer panel';
                    buttonEl.innerHTML = `<i class="fas fa-chevron-${collapsed ? 'down' : 'up'}"></i>`;
                    const targetName = buttonEl.dataset.target || buttonEl.id || 'panel';
                    window.localStorage.setItem(`${panelStoragePrefix}${targetName}`, collapsed ? 'collapsed' : 'expanded');
                }

                function initPanelToggle(buttonId, panelEl) {
                    const buttonEl = document.getElementById(buttonId);
                    if (!buttonEl || !panelEl) return;
                    const targetName = buttonEl.dataset.target || buttonId;
                    const savedState = window.localStorage.getItem(`${panelStoragePrefix}${targetName}`) === 'collapsed';
                    setPanelState(buttonEl, panelEl, savedState);
                    buttonEl.addEventListener('click', () => {
                        const collapsed = buttonEl.getAttribute('aria-expanded') === 'true';
                        setPanelState(buttonEl, panelEl, collapsed);
                    });
                }

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                initPanelToggle('toggle-alerts-panel', alertsPanelEl);
                initPanelToggle('toggle-vehicles-panel', vehicleListPanelEl);

                function normalizeVehicleStatus(rawStatus) {
                    const normalized = String(rawStatus || '').trim().toUpperCase();
                    if (!normalized) return 'EN_RUTA';
                    if (['CARGA', 'ENTREGA', 'ESPERA', 'INICIO', 'CONTINUAR', 'EN_RUTA'].includes(normalized)) {
                        return normalized;
                    }
                    return 'EN_RUTA';
                }

                function resolveStatusBadgeClass(status) {
                    return `status-${normalizeVehicleStatus(status).toLowerCase()}`;
                }

                function createVehicleIcon(isStale, status) {
                    return L.divIcon({
                        html: `<div class="map-vehicle-icon ${isStale ? 'stale' : `live ${resolveStatusBadgeClass(status)}`}"><i class="fas fa-car-side"></i></div>`,
                        className: '',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15],
                        popupAnchor: [0, -14]
                    });
                }

                function createMobileDeviceIcon(item) {
                    const stateClass = item.is_stale ? 'stale' : (item.is_moving ? 'moving' : '');
                    return L.divIcon({
                        html: `<img class="map-device-icon ${stateClass}" src="${chasquiMarkerUrl}" alt="Chasqui de Correos de Bolivia">`,
                        className: '',
                        iconSize: [72, 54],
                        iconAnchor: [51, 50],
                        popupAnchor: [0, -44]
                    });
                }

                function escapeHtml(value) {
                    const div = document.createElement('div');
                    div.textContent = String(value ?? '');
                    return div.innerHTML;
                }

                function formatAge(seconds) {
                    const secs = Number(seconds);
                    if (!Number.isFinite(secs) || secs < 0) return 'sin dato';
                    if (secs < 60) return `${Math.floor(secs)}s`;
                    const mins = Math.floor(secs / 60);
                    const rem = Math.floor(secs % 60);
                    return `${mins}m ${rem}s`;
                }

                function formatSpeed(speedKmh) {
                    const speed = Number(speedKmh);
                    if (!Number.isFinite(speed) || speed < 0) return '-';
                    return `${window.BolivianNumber.format(speed, 1)} km/h`;
                }

                function formatDistance(distanceKm) {
                    const distance = Number(distanceKm);
                    if (!Number.isFinite(distance) || distance < 0) return '-';
                    return `${window.BolivianNumber.format(distance, 2)} km`;
                }

                function formatRouteTime(value) {
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return '-';
                    return date.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' });
                }

                function formatRouteDateTime(value) {
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return '-';
                    return date.toLocaleString('es-BO', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                }

                function formatSelectedDate(value) {
                    const date = new Date(`${value}T12:00:00`);
                    if (Number.isNaN(date.getTime())) return value || '-';
                    return date.toLocaleDateString('es-BO', {
                        weekday: 'long',
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric',
                    });
                }

                function updateTrajectoryDateLabel() {
                    if (!trajectoryDateLabelEl) return;
                    trajectoryDateLabelEl.textContent = `Viendo: ${formatSelectedDate(currentOfflineDate)}`;
                }

                function changeRouteDay(dayOffset) {
                    const date = new Date(`${currentOfflineDate}T12:00:00`);
                    if (Number.isNaN(date.getTime())) return;
                    date.setDate(date.getDate() + dayOffset);
                    currentOfflineDate = [
                        date.getFullYear(),
                        String(date.getMonth() + 1).padStart(2, '0'),
                        String(date.getDate()).padStart(2, '0'),
                    ].join('-');
                    if (offlineDateEl) offlineDateEl.value = currentOfflineDate;
                    updateTrajectoryDateLabel();
                    hasInitializedViewport = false;
                    refreshData(true);
                }

                function clearOverlays() {
                    overlays.forEach((group) => {
                        if (group.marker) map.removeLayer(group.marker);
                        if (group.path) map.removeLayer(group.path);
                        if (Array.isArray(group.timeMarkers)) {
                            group.timeMarkers.forEach((timeMarker) => map.removeLayer(timeMarker));
                        }
                        if (Array.isArray(group.segmentPaths)) {
                            group.segmentPaths.forEach((pathLayer) => map.removeLayer(pathLayer));
                        }
                        if (group.lastKnown) map.removeLayer(group.lastKnown);
                        if (Array.isArray(group.marked)) {
                            group.marked.forEach((m) => map.removeLayer(m));
                        }
                        if (Array.isArray(group.allPoints)) {
                            group.allPoints.forEach((m) => map.removeLayer(m));
                        }
                    });
                    overlays.clear();
                }

                function renderList(vehicles) {
                    listEl.innerHTML = '';
                    if (!vehicles.length) {
                        listEl.innerHTML = '<div class="text-muted">Sin datos de ubicacion.</div>';
                        return;
                    }

                    vehicles.forEach((item) => {
                        const div = document.createElement('div');
                        const isSelected = selectedVehicleId !== null && Number(selectedVehicleId) === Number(item.vehicle_id);
                        const currentStatus = normalizeVehicleStatus(item.current_status || item.last_point?.point_label);
                        div.className = `vehicle-item${isSelected ? ' selected' : ''}`;
                        div.innerHTML = `
                            <div class="fw-bold">${item.placa || 'SIN PLACA'}</div>
                            <div class="small text-muted">${item.marca || ''} ${item.modelo || ''}</div>
                            <div class="small">Conductor: ${item.driver_name || 'N/A'}</div>
                            <div class="small">Destino: ${item.recorrido_destino || 'N/A'}</div>
                            <div class="small fw-semibold">Estado actual: ${currentStatus}</div>
                            ${effectiveMode === 'online'
                                ? `<div class="small ${item.is_stale ? 'text-danger' : 'text-success'}">${item.is_stale ? 'Ultima ubicacion' : 'Direccion actual'}: ${item.current_address || 'Sin direccion'}</div>`
                                : `<div class="small text-success">Ruta del dia: ${item.recorrido_inicio || 'Sin origen'} -> ${item.recorrido_destino || 'Sin destino'}</div>
                                   <div class="small text-muted">Bitacoras unidas: ${item.trip_count || (Array.isArray(item.trip_summaries) ? item.trip_summaries.length : 0)}</div>
                                   <div class="small text-primary">Puntos recorridos: ${item.points_count || 0}</div>`
                            }
                            <div class="small ${item.is_stale ? 'text-danger' : 'text-success'}">
                                ${item.is_stale ? 'Sin se�al' : 'En l�nea'} (${formatAge(item.seconds_since_update)})
                            </div>
                            <div class="small text-muted">Velocidad: ${formatSpeed(item.current_speed_kmh)}</div>
                            <div class="small text-primary">Puntos marcados: ${(item.marked_points || []).length}</div>
                            <div class="small text-muted">Fuente: ${effectiveMode.toUpperCase()}</div>
                        `;
                        div.addEventListener('click', () => {
                            selectedVehicleId = Number(item.vehicle_id);
                            filteredVehicleId = String(item.vehicle_id);
                            if (vehicleFilterEl) {
                                vehicleFilterEl.value = filteredVehicleId;
                            }
                            if (item.last_point) {
                                const lat = Number(item.last_point.lat);
                                const lng = Number(item.last_point.lng);
                                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                                    selectedLastPoint = [lat, lng];
                                    map.setView(selectedLastPoint, 16);
                                }
                                const o = overlays.get(item.vehicle_id);
                                if (o && o.marker) {
                                    o.marker.openPopup();
                                }
                            }
                            const onlySelected = applyVehicleFilter(vehicles);
                            renderList(onlySelected);
                            renderMap(onlySelected);
                        });
                        listEl.appendChild(div);
                    });
                }

                function renderAlerts(alerts) {
                    if (!alertsEl) return;
                    alertsEl.innerHTML = '';
                    if (!Array.isArray(alerts) || alerts.length === 0) {
                        alertsEl.innerHTML = '<div class="text-muted">Sin alertas operativas activas.</div>';
                        return;
                    }

                    alerts.forEach((item) => {
                        const severity = String(item.severity || 'secondary').toLowerCase();
                        const div = document.createElement('div');
                        div.className = `operation-alert-item ${severity}`;
                        const stage = item.current_stage ? `<div class="small fw-semibold">Estado: ${item.current_stage}</div>` : '';
                        const heartbeat = item.last_heartbeat_at ? `<div class="small text-muted">Ultimo heartbeat: ${item.last_heartbeat_at}</div>` : '';
                        div.innerHTML = `
                            <div class="fw-bold">${item.title || 'Alerta operativa'}</div>
                            <div class="small">${item.message || ''}</div>
                            ${stage}
                            ${heartbeat}
                        `;
                        alertsEl.appendChild(div);
                    });
                }

                function renderMobileDevices(devices) {
                    if (!mobileDeviceListEl) return;
                    mobileDeviceListEl.innerHTML = '';

                    if (!Array.isArray(devices) || devices.length === 0) {
                        mobileDeviceListEl.innerHTML = `<div class="text-muted">Sin recorrido de Chasquis ${effectiveMode === 'offline' ? 'para esta fecha' : 'durante el día'}.</div>`;
                        return;
                    }

                    devices.forEach((item) => {
                        const div = document.createElement('div');
                        div.className = 'vehicle-item';
                        div.innerHTML = `
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <img class="chasqui-list-icon" src="${chasquiMarkerUrl}" alt="Chasqui">
                                <div>
                                    <div class="fw-bold">${escapeHtml(item.user_name || item.alias || 'Chasqui')}</div>
                                    <div class="small text-muted">${escapeHtml(item.alias || '')}</div>
                                </div>
                            </div>
                            <div class="small fw-semibold ${item.is_moving ? 'text-info' : 'text-secondary'}">${effectiveMode === 'offline' ? 'Recorrido guardado' : (item.is_moving ? 'En movimiento' : 'Detenido')}</div>
                            <div class="small ${item.is_stale ? 'text-danger' : 'text-success'}">${effectiveMode === 'offline' ? escapeHtml(currentOfflineDate) : `${item.is_stale ? 'Sin señal' : 'En línea'} (${formatAge(item.seconds_since_update)})`}</div>
                            <div class="small text-primary">Puntos GPS: ${Number(item.points_count || 0)}</div>
                            <div class="small text-muted">Distancia aproximada: ${formatDistance(item.distance_km)}</div>
                            <div class="small text-muted">Horario: ${formatRouteTime(item.points?.[0]?.t)} - ${formatRouteTime(item.points?.[item.points.length - 1]?.t)}</div>
                            ${item.is_simulated ? '<div class="small"><span class="badge bg-purple">Ruta ficticia</span></div>' : ''}
                        `;
                        div.addEventListener('click', () => {
                            const lat = Number(item.latitude);
                            const lng = Number(item.longitude);
                            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                            map.setView([lat, lng], 17);
                            const overlay = overlays.get(`device:${item.location_id}`);
                            if (overlay && overlay.marker) overlay.marker.openPopup();
                        });
                        mobileDeviceListEl.appendChild(div);
                    });
                }

                function renderMap(vehicles, devices = currentMobileDevices) {
                    clearOverlays();
                    const bounds = [];
                    let selectedItem = null;

                    vehicles.forEach((item) => {
                        if (!item.last_point) return;
                        if (selectedVehicleId !== null && Number(selectedVehicleId) === Number(item.vehicle_id)) {
                            selectedItem = item;
                        }

                        const lat = Number(item.last_point.lat);
                        const lng = Number(item.last_point.lng);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                        const currentStatus = normalizeVehicleStatus(item.current_status || item.last_point?.point_label);
                        const popup = `
                            <div>
                                <strong>${item.placa || 'SIN PLACA'}</strong><br>
                                Conductor: ${item.driver_name || 'N/A'}<br>
                                Estado de bitacora: ${currentStatus}<br>
                                ${item.is_stale ? 'Ultima ubicacion' : 'Direccion actual'}: ${item.current_address || 'Sin direccion'}<br>
                                Estado: ${item.is_stale ? 'Sin se�al' : 'En l�nea'} (${formatAge(item.seconds_since_update)})<br>
                                Velocidad: ${formatSpeed(item.current_speed_kmh)}<br>
                                Origen del dia: ${item.recorrido_inicio || 'N/A'}<br>
                                Destino del dia: ${item.recorrido_destino || 'N/A'}<br>
                                Bitacoras del dia: ${item.trip_count || (Array.isArray(item.trip_summaries) ? item.trip_summaries.length : 0)}
                            </div>
                        `;

                        const marker = L.marker([lat, lng], {
                            icon: createVehicleIcon(Boolean(item.is_stale), currentStatus)
                        }).addTo(map).bindPopup(popup);
                        let path = null;
                        const segmentPaths = [];
                        const marked = [];
                        const allPoints = [];
                        let lastKnown = null;

                        if (Array.isArray(item.points) && item.points.length > 1) {
                            const segmentPalette = ['#00509d', '#0f766e', '#9a3412', '#7c3aed', '#be123c'];
                            const offlineSegments = effectiveMode === 'offline' && Array.isArray(item.offline_segments)
                                ? item.offline_segments
                                : [];

                            if (offlineSegments.length > 0) {
                                offlineSegments.forEach((segment, segmentIndex) => {
                                    const seenPoints = new Set();
                                    const segmentRoute = (Array.isArray(segment.points) ? segment.points : [])
                                        .map((p) => ({
                                            lat: Number(p.lat),
                                            lng: Number(p.lng),
                                            t: p && p.t ? String(p.t) : '',
                                        }))
                                        .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng))
                                        .filter((p) => {
                                            const key = `${p.lat}|${p.lng}|${p.t}`;
                                            if (seenPoints.has(key)) return false;
                                            seenPoints.add(key);
                                            return true;
                                        })
                                        .map((p) => [p.lat, p.lng]);

                                    if (segmentRoute.length > 1) {
                                        const color = segmentPalette[segmentIndex % segmentPalette.length];
                                        const segmentLayer = L.polyline(segmentRoute, {
                                            color,
                                            weight: 5,
                                            opacity: 0.78,
                                        }).addTo(map);
                                        segmentPaths.push(segmentLayer);
                                        segmentRoute.forEach((p) => bounds.push(p));
                                    }
                                });
                            }

                            if (segmentPaths.length === 0) {
                                const seenPoints = new Set();
                                const route = item.points
                                    .map((p) => ({
                                        lat: Number(p.lat),
                                        lng: Number(p.lng),
                                        t: p && p.t ? String(p.t) : '',
                                    }))
                                    .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng))
                                    .filter((p) => {
                                        const key = `${p.lat}|${p.lng}|${p.t}`;
                                        if (seenPoints.has(key)) return false;
                                        seenPoints.add(key);
                                        return true;
                                    })
                                    .map((p) => [p.lat, p.lng]);

                                if (route.length > 1) {
                                    path = L.polyline(route, {
                                        color: effectiveMode === 'offline' ? '#00509d' : '#2563eb',
                                        weight: effectiveMode === 'offline' ? 4 : 5,
                                        opacity: effectiveMode === 'offline' ? 0.7 : 0.82
                                    }).addTo(map);
                                    route.forEach((p) => bounds.push(p));
                                } else {
                                    bounds.push([lat, lng]);
                                }
                            }
                        } else {
                            bounds.push([lat, lng]);
                        }

                        if (item.is_stale) {
                            lastKnown = L.circle([lat, lng], {
                                radius: 45,
                                color: '#dc2626',
                                weight: 2,
                                fillColor: '#dc2626',
                                fillOpacity: 0.12,
                            }).addTo(map);
                        }

                        if (Array.isArray(item.marked_points)) {
                            item.marked_points.forEach((p, idx) => {
                                const mLat = Number(p.lat);
                                const mLng = Number(p.lng);
                                if (!Number.isFinite(mLat) || !Number.isFinite(mLng)) return;

                                const label = p.point_label ? String(p.point_label) : `Punto marcado ${idx + 1}`;
                                const address = p.address ? String(p.address) : 'Sin direccion';

                                const markedLayer = L.circleMarker([mLat, mLng], {
                                    radius: 7,
                                    color: '#9a3412',
                                    weight: 2,
                                    fillColor: '#ffcc00',
                                    fillOpacity: 0.95
                                }).addTo(map).bindPopup(`
                                    <div>
                                        <strong>${item.placa || 'Vehiculo'}</strong><br>
                                        ${label}<br>
                                        <small>${address}</small>
                                    </div>
                                `);

                                marked.push(markedLayer);
                                bounds.push([mLat, mLng]);
                            });
                        }

                        overlays.set(item.vehicle_id, { marker, path, segmentPaths, marked, allPoints, lastKnown });
                    });

                    if (Array.isArray(devices)) {
                        devices.forEach((item) => {
                            const lat = Number(item.latitude);
                            const lng = Number(item.longitude);
                            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                            const popup = `
                                <div>
                                    <strong>${escapeHtml(item.user_name || item.alias || 'Chasqui')}</strong><br>
                                    Aplicación: ${escapeHtml(item.device_name || 'ChasquiApp')}<br>
                                    Estado: ${effectiveMode === 'offline' ? 'Historial guardado' : `${item.is_stale ? 'Sin señal' : 'En línea'} (${formatAge(item.seconds_since_update)})`}<br>
                                    Movimiento: ${item.is_moving ? 'En movimiento' : 'Detenido'}<br>
                                    Velocidad: ${formatSpeed(item.speed_kmh)}<br>
                                    Puntos del día: ${Number(item.points_count || 0)}<br>
                                    Distancia aproximada: ${formatDistance(item.distance_km)}<br>
                                    Precisión: ${Number.isFinite(Number(item.accuracy_m)) ? `${window.BolivianNumber.format(Number(item.accuracy_m), 1)} m` : '-'}
                                </div>
                            `;
                            const marker = L.marker([lat, lng], {
                                icon: createMobileDeviceIcon(item),
                                zIndexOffset: 500,
                            }).addTo(map).bindPopup(popup);

                            let path = null;
                            const timeMarkers = [];
                            const routePoints = (Array.isArray(item.points) ? item.points : [])
                                .map((point) => ({
                                    lat: Number(point.lat),
                                    lng: Number(point.lng),
                                    t: point.t ? String(point.t) : '',
                                }))
                                .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));
                            const route = routePoints.map((point) => [point.lat, point.lng]);

                            if (route.length > 1) {
                                path = L.polyline(route, {
                                    color: item.is_simulated ? '#7c3aed' : '#f59e0b',
                                    weight: 6,
                                    opacity: 0.9,
                                    dashArray: item.is_simulated ? '12 8' : null,
                                    lineCap: 'round',
                                    lineJoin: 'round',
                                }).addTo(map);

                                path.on('click', (event) => {
                                    const nearest = routePoints.reduce((best, point) => {
                                        const distance = event.latlng.distanceTo(L.latLng(point.lat, point.lng));
                                        return !best || distance < best.distance ? { point, distance } : best;
                                    }, null);

                                    if (!nearest) return;
                                    L.popup()
                                        .setLatLng([nearest.point.lat, nearest.point.lng])
                                        .setContent(`
                                            <strong>${escapeHtml(item.user_name || item.alias || 'Chasqui')}</strong><br>
                                            Paso registrado: ${formatRouteDateTime(nearest.point.t)}<br>
                                            <small>${item.is_simulated ? 'Ruta ficticia' : 'Registro GPS'}</small>
                                        `)
                                        .openOn(map);
                                });

                                const markerInterval = Math.max(1, Math.ceil(routePoints.length / 10));
                                routePoints.forEach((point, index) => {
                                    const isEndpoint = index === 0 || index === routePoints.length - 1;
                                    if (!isEndpoint && index % markerInterval !== 0) return;

                                    const timeMarker = L.circleMarker([point.lat, point.lng], {
                                        radius: isEndpoint ? 6 : 4,
                                        color: '#4c1d95',
                                        weight: 2,
                                        fillColor: '#ffffff',
                                        fillOpacity: 1,
                                    }).addTo(map);
                                    timeMarker.bindTooltip(formatRouteTime(point.t), {
                                        permanent: false,
                                        direction: 'top',
                                        offset: [0, -5],
                                    });
                                    timeMarker.bindPopup(`
                                        <strong>${escapeHtml(item.user_name || item.alias || 'Chasqui')}</strong><br>
                                        Paso registrado: ${formatRouteDateTime(point.t)}<br>
                                        <small>${item.is_simulated ? 'Ruta ficticia' : 'Registro GPS'}</small>
                                    `);
                                    timeMarkers.push(timeMarker);
                                });
                                route.forEach((point) => bounds.push(point));
                            }

                            overlays.set(`device:${item.location_id}`, { marker, path, timeMarkers });
                            bounds.push([lat, lng]);
                        });
                    }

                    if (!hasInitializedViewport) {
                        if (selectedItem && selectedItem.last_point) {
                            const lat = Number(selectedItem.last_point.lat);
                            const lng = Number(selectedItem.last_point.lng);
                            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                                selectedLastPoint = [lat, lng];
                                map.setView(selectedLastPoint, 16);
                            }
                        } else if (
                            selectedVehicleId !== null &&
                            Array.isArray(selectedLastPoint) &&
                            Number.isFinite(Number(selectedLastPoint[0])) &&
                            Number.isFinite(Number(selectedLastPoint[1]))
                        ) {
                            map.setView(selectedLastPoint, 16);
                        } else if (bounds.length) {
                            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
                        }

                        if (bounds.length || selectedItem || selectedLastPoint) {
                            hasInitializedViewport = true;
                        }
                    }
                }

                async function refreshData(force = false) {
                    if (activeRefreshController && !force) {
                        return;
                    }
                    if (activeRefreshController && force) {
                        activeRefreshController.abort();
                    }

                    const refreshController = new AbortController();
                    activeRefreshController = refreshController;
                    const requestId = ++refreshRequestId;
                    const requestedMode = currentMode;
                    const requestedDate = currentOfflineDate;
                    const requestedChasquiUserId = requestedMode === 'online'
                        ? filteredRealtimeChasquiUserId
                        : filteredChasquiUserId;
                    const shouldIncludeRealtimeRoute = requestedMode === 'online'
                        && requestedChasquiUserId !== ''
                        && loadedRealtimeRouteUserId !== requestedChasquiUserId;

                    try {
                        if (mobileDeviceListEl && currentMobileDevices.length === 0) {
                            mobileDeviceListEl.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Cargando carteros y recorridos...</div>';
                        }
                        const params = new URLSearchParams({ mode: requestedMode });
                        if (requestedMode === 'offline' && requestedDate) {
                            params.set('date', requestedDate);
                        }
                        if (requestedMode === 'online' && requestedChasquiUserId) {
                            params.set('chasqui_user_id', requestedChasquiUserId);
                            if (shouldIncludeRealtimeRoute) {
                                params.set('include_route', '1');
                            }
                        }
                        const url = `${dataUrl}?${params.toString()}`;
                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            signal: refreshController.signal,
                        });

                        if (!response.ok) {
                            if (mobileDeviceListEl) {
                                mobileDeviceListEl.innerHTML = '<div class="text-danger">No se pudieron cargar los carteros. Intenta nuevamente.</div>';
                            }
                            return;
                        }

                        const payload = await response.json();
                        if (requestId !== refreshRequestId || requestedMode !== currentMode) {
                            return;
                        }

                        effectiveMode = requestedMode;
                        const vehicles = Array.isArray(payload.vehicles) ? payload.vehicles : [];
                        const incomingMobileDevices = Array.isArray(payload.mobile_devices)
                            ? payload.mobile_devices
                            : [];
                        currentMobileDevices = requestedMode === 'online'
                            ? mergeRealtimeDevices(incomingMobileDevices, Boolean(payload.route_included))
                            : incomingMobileDevices;
                        const alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
                        if (requestedMode === 'online' && payload.route_included && requestedChasquiUserId) {
                            loadedRealtimeRouteUserId = requestedChasquiUserId;
                        }
                        if (
                            requestedMode === 'offline' &&
                            payload.selected_date &&
                            String(payload.selected_date) === requestedDate &&
                            offlineDateEl
                        ) {
                            offlineDateEl.value = requestedDate;
                            currentOfflineDate = requestedDate;
                            updateTrajectoryDateLabel();
                        }
                        syncVehicleFilterOptions(vehicles);
                        if (requestedMode === 'online') {
                            syncRealtimeChasquiFilterOptions(
                                Array.isArray(payload.mobile_device_options)
                                    ? payload.mobile_device_options
                                    : currentMobileDevices
                            );
                        } else {
                            syncChasquiFilterOptions(currentMobileDevices);
                        }
                        const filtered = applyVehicleFilter(vehicles);
                        const filteredChasquis = applyChasquiFilter(currentMobileDevices);
                        renderAlerts(alerts);
                        renderList(filtered);
                        renderMobileDevices(filteredChasquis);
                        renderMap(filtered, filteredChasquis);

                        if (payload.updated_at) {
                            const dt = new Date(payload.updated_at);
                            lastUpdateEl.textContent = isNaN(dt.getTime())
                                ? payload.updated_at
                                : dt.toLocaleTimeString();
                        }
                    } catch (error) {
                        if (error?.name !== 'AbortError') {
                            lastUpdateEl.textContent = 'Error de conexión';
                            if (mobileDeviceListEl) {
                                mobileDeviceListEl.innerHTML = '<div class="text-danger">Error de conexión al cargar los carteros.</div>';
                            }
                        }
                    } finally {
                        if (activeRefreshController === refreshController) {
                            activeRefreshController = null;
                            hideRouteLoading();
                        }
                    }
                }

                function setMode(mode) {
                    currentMode = mode === 'offline' ? 'offline' : 'online';
                    if (btnOnline && btnOffline) {
                        if (currentMode === 'online') {
                            btnOnline.className = 'btn btn-primary';
                            btnOffline.className = 'btn btn-outline-secondary';
                        } else {
                            btnOnline.className = 'btn btn-outline-secondary';
                            btnOffline.className = 'btn btn-primary';
                        }
                    }
                    if (offlineDateEl) {
                        offlineDateEl.disabled = currentMode !== 'offline';
                    }
                    if (trajectoryControlsEl) {
                        trajectoryControlsEl.classList.toggle('d-none', currentMode !== 'offline');
                    }
                    if (realtimeFilterControlsEl) {
                        realtimeFilterControlsEl.classList.toggle('d-none', currentMode !== 'online');
                    }
                    if (alertsCardEl) {
                        alertsCardEl.classList.toggle('d-none', currentMode === 'offline');
                    }
                    if (vehiclesCardEl) {
                        vehiclesCardEl.classList.toggle('d-none', currentMode === 'offline');
                    }
                    if (chasquiPanelTitleEl) {
                        chasquiPanelTitleEl.textContent = currentMode === 'offline'
                            ? 'Trayectoria seleccionada'
                            : 'Carteros ChasquiApp';
                    }
                    if (refreshStatusEl) {
                        refreshStatusEl.innerHTML = currentMode === 'online'
                            ? 'Actualizacion cada <strong>2s</strong>'
                            : '<strong>Trayectoria fija</strong>';
                    }
                    updateTrajectoryDateLabel();
                    refreshData(true);
                }

                function applyVehicleFilter(vehicles) {
                    if (!filteredVehicleId) {
                        return vehicles;
                    }

                    return vehicles.filter((v) => String(v.vehicle_id) === String(filteredVehicleId));
                }

                function applyChasquiFilter(devices) {
                    const activeChasquiUserId = effectiveMode === 'online'
                        ? filteredRealtimeChasquiUserId
                        : filteredChasquiUserId;
                    if (!activeChasquiUserId) {
                        return devices;
                    }

                    return devices.filter((device) => String(device.user_id) === String(activeChasquiUserId));
                }

                function syncRealtimeChasquiFilterOptions(devices) {
                    if (!realtimeChasquiFilterEl) return;

                    const previous = String(filteredRealtimeChasquiUserId || realtimeChasquiFilterEl.value || '');
                    const carteros = new Map();
                    devices.forEach((device) => {
                        const userId = String(device.user_id ?? '');
                        if (!userId || carteros.has(userId)) return;
                        const name = escapeHtml(device.user_name || device.alias || 'Chasqui');
                        const state = device.is_stale ? 'sin señal' : 'en línea';
                        carteros.set(userId, `${name} (${state})`);
                    });

                    realtimeChasquiFilterEl.innerHTML = '<option value="">Todos los carteros en vivo</option>';
                    carteros.forEach((label, userId) => {
                        realtimeChasquiFilterEl.insertAdjacentHTML('beforeend', `<option value="${userId}">${label}</option>`);
                    });

                    if (previous && carteros.has(previous)) {
                        realtimeChasquiFilterEl.value = previous;
                    } else if (previous) {
                        realtimeChasquiFilterEl.insertAdjacentHTML(
                            'beforeend',
                            `<option value="${escapeHtml(previous)}">Cartero seleccionado</option>`
                        );
                        realtimeChasquiFilterEl.value = previous;
                    }
                }

                function syncChasquiFilterOptions(devices) {
                    if (!chasquiFilterEl) return;

                    const previous = String(filteredChasquiUserId || chasquiFilterEl.value || '');
                    const carteros = new Map();
                    devices.forEach((device) => {
                        const userId = String(device.user_id ?? '');
                        if (!userId || carteros.has(userId)) return;
                        carteros.set(userId, escapeHtml(device.user_name || device.alias || 'Chasqui'));
                    });

                    chasquiFilterEl.innerHTML = '<option value="">Todos los carteros</option>';
                    carteros.forEach((name, userId) => {
                        chasquiFilterEl.insertAdjacentHTML('beforeend', `<option value="${userId}">${name}</option>`);
                    });

                    if (previous && carteros.has(previous)) {
                        chasquiFilterEl.value = previous;
                    } else if (previous) {
                        filteredChasquiUserId = '';
                    }
                }

                function syncVehicleFilterOptions(vehicles) {
                    if (!vehicleFilterEl) return;

                    const previous = String(filteredVehicleId || vehicleFilterEl.value || '');
                    const uniqueVehicles = new Map();
                    vehicles.forEach((v) => {
                        const id = String(v.vehicle_id ?? '');
                        if (!id) return;
                        if (!uniqueVehicles.has(id)) {
                            uniqueVehicles.set(id, {
                                id,
                                label: `${v.placa || 'SIN PLACA'} - ${v.driver_name || 'N/A'}`,
                            });
                        }
                    });

                    const options = ['<option value="">Todos los vehiculos</option>'];
                    uniqueVehicles.forEach((v) => {
                        options.push(`<option value="${v.id}">${v.label}</option>`);
                    });
                    vehicleFilterEl.innerHTML = options.join('');

                    if (previous && uniqueVehicles.has(previous)) {
                        vehicleFilterEl.value = previous;
                        filteredVehicleId = previous;
                    } else {
                        vehicleFilterEl.value = '';
                        filteredVehicleId = '';
                    }
                }

                if (btnOnline) btnOnline.addEventListener('click', () => setMode('online'));
                if (btnOffline) btnOffline.addEventListener('click', () => setMode('offline'));
                if (offlineDateEl) {
                    offlineDateEl.value = currentOfflineDate;
                    offlineDateEl.disabled = currentMode !== 'offline';
                    offlineDateEl.addEventListener('change', (event) => {
                        const selectedDate = String(event.target.value || '');
                        if (!/^\d{4}-\d{2}-\d{2}$/.test(selectedDate)) return;
                        currentOfflineDate = selectedDate;
                        updateTrajectoryDateLabel();
                        if (currentMode === 'offline') {
                            selectedVehicleId = null;
                            selectedLastPoint = null;
                            hasInitializedViewport = false;
                            refreshData(true);
                        }
                    });
                }
                if (previousRouteDayBtn) {
                    previousRouteDayBtn.addEventListener('click', () => changeRouteDay(-1));
                }
                if (nextRouteDayBtn) {
                    nextRouteDayBtn.addEventListener('click', () => changeRouteDay(1));
                }
                if (vehicleFilterEl) {
                    vehicleFilterEl.addEventListener('change', (event) => {
                        filteredVehicleId = String(event.target.value || '');
                        selectedVehicleId = filteredVehicleId ? Number(filteredVehicleId) : null;
                        if (!filteredVehicleId) {
                            selectedLastPoint = null;
                        }
                        refreshData();
                    });
                }
                if (realtimeChasquiFilterEl) {
                    realtimeChasquiFilterEl.addEventListener('change', (event) => {
                        filteredRealtimeChasquiUserId = String(event.target.value || '');
                        loadedRealtimeRouteUserId = '';
                        liveRoutePointsByLocation.clear();
                        selectedVehicleId = null;
                        selectedLastPoint = null;
                        hasInitializedViewport = false;
                        refreshData(true);
                    });
                }
                if (showChasquiRouteBtn) {
                    showChasquiRouteBtn.addEventListener('click', () => {
                        filteredChasquiUserId = String(chasquiFilterEl?.value || '');
                        selectedVehicleId = null;
                        selectedLastPoint = null;
                        hasInitializedViewport = false;
                        showRouteLoading();
                        setMode('offline');
                    });
                }

                setMode(initialMode);
                setInterval(() => {
                    if (currentMode === 'online') {
                        refreshData();
                    }
                }, refreshIntervalMs);
            })();
        </script>
    @endpush
@endonce
