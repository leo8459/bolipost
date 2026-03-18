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
        </style>
    @endpush
@endonce

<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title mb-0">
            <i class="fas fa-map-marked-alt me-2"></i>Mapa de Vehiculos
        </h1>
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Modo mapa">
                <button type="button" id="mode-online" class="btn btn-primary">Tiempo Real</button>
                <button type="button" id="mode-offline" class="btn btn-outline-secondary">Trayectoria</button>
            </div>
            <input
                type="date"
                id="offline-date"
                class="form-control form-control-sm"
                value="{{ now()->toDateString() }}"
                style="min-width: 170px;"
            >
            <select id="vehicle-filter" class="form-select form-select-sm" style="min-width: 220px;">
                <option value="">Todos los vehiculos</option>
            </select>
            <div class="text-muted small">
                Actualizacion cada <strong>20s</strong> |
                Ultima carga: <span id="last-update">-</span>
            </div>
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
            <div class="card map-panel shadow-sm">
                <div class="card-header fw-bold">Vehiculos en mapa</div>
                <div class="card-body vehicle-list" id="vehicle-list"></div>
            </div>
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
                const lastUpdateEl = document.getElementById('last-update');
                const btnOnline = document.getElementById('mode-online');
                const btnOffline = document.getElementById('mode-offline');
                const offlineDateEl = document.getElementById('offline-date');
                const vehicleFilterEl = document.getElementById('vehicle-filter');
                const overlays = new Map();
                const queryParams = new URLSearchParams(window.location.search);
                const initialMode = queryParams.get('mode') === 'offline' ? 'offline' : 'online';
                let currentMode = initialMode;
                let effectiveMode = initialMode;
                let selectedVehicleId = null;
                let selectedLastPoint = null;
                let filteredVehicleId = '';
                let currentOfflineDate = queryParams.get('date') || @json(now()->toDateString());
                const refreshIntervalMs = 20000;

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                function createVehicleIcon(isStale) {
                    return L.divIcon({
                        html: `<div class="map-vehicle-icon ${isStale ? 'stale' : 'live'}"><i class="fas fa-car-side"></i></div>`,
                        className: '',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15],
                        popupAnchor: [0, -14]
                    });
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
                    return `${speed.toFixed(1)} km/h`;
                }

                function clearOverlays() {
                    overlays.forEach((group) => {
                        if (group.marker) map.removeLayer(group.marker);
                        if (group.path) map.removeLayer(group.path);
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
                        div.className = `vehicle-item${isSelected ? ' selected' : ''}`;
                        div.innerHTML = `
                            <div class="fw-bold">${item.placa || 'SIN PLACA'}</div>
                            <div class="small text-muted">${item.marca || ''} ${item.modelo || ''}</div>
                            <div class="small">Conductor: ${item.driver_name || 'N/A'}</div>
                            <div class="small">Destino: ${item.recorrido_destino || 'N/A'}</div>
                            ${effectiveMode === 'online'
                                ? `<div class="small ${item.is_stale ? 'text-danger' : 'text-success'}">${item.is_stale ? 'Ultima ubicacion' : 'Direccion actual'}: ${item.current_address || 'Sin direccion'}</div>`
                                : `<div class="small text-success">Ruta del dia: ${item.recorrido_inicio || 'Sin origen'} -> ${item.recorrido_destino || 'Sin destino'}</div>
                                   <div class="small text-muted">Bitacoras unidas: ${item.trip_count || (Array.isArray(item.trip_summaries) ? item.trip_summaries.length : 0)}</div>
                                   <div class="small text-primary">Puntos recorridos: ${item.points_count || 0}</div>`
                            }
                            <div class="small ${item.is_stale ? 'text-danger' : 'text-success'}">
                                ${item.is_stale ? 'Sin señal' : 'En línea'} (${formatAge(item.seconds_since_update)})
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

                function renderMap(vehicles) {
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

                        const popup = `
                            <div>
                                <strong>${item.placa || 'SIN PLACA'}</strong><br>
                                Conductor: ${item.driver_name || 'N/A'}<br>
                                ${item.is_stale ? 'Ultima ubicacion' : 'Direccion actual'}: ${item.current_address || 'Sin direccion'}<br>
                                Estado: ${item.is_stale ? 'Sin señal' : 'En línea'} (${formatAge(item.seconds_since_update)})<br>
                                Velocidad: ${formatSpeed(item.current_speed_kmh)}<br>
                                Origen del dia: ${item.recorrido_inicio || 'N/A'}<br>
                                Destino del dia: ${item.recorrido_destino || 'N/A'}<br>
                                Bitacoras del dia: ${item.trip_count || (Array.isArray(item.trip_summaries) ? item.trip_summaries.length : 0)}
                            </div>
                        `;

                        const marker = L.marker([lat, lng], { icon: createVehicleIcon(Boolean(item.is_stale)) }).addTo(map).bindPopup(popup);
                        let path = null;
                        const segmentPaths = [];
                        const marked = [];
                        const allPoints = [];
                        let lastKnown = null;

                        if (effectiveMode === 'offline' && Array.isArray(item.points) && item.points.length > 1) {
                            const segmentPalette = ['#00509d', '#0f766e', '#9a3412', '#7c3aed', '#be123c'];
                            const offlineSegments = Array.isArray(item.offline_segments) ? item.offline_segments : [];

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
                                    path = L.polyline(route, { color: '#00509d', weight: 4, opacity: 0.7 }).addTo(map);
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

                        if (effectiveMode === 'offline' && Array.isArray(item.marked_points)) {
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

                    if (selectedItem && selectedItem.last_point) {
                        const lat = Number(selectedItem.last_point.lat);
                        const lng = Number(selectedItem.last_point.lng);
                        if (Number.isFinite(lat) && Number.isFinite(lng)) {
                            selectedLastPoint = [lat, lng];
                            map.setView(selectedLastPoint, 16);
                        }
                        const selectedOverlay = overlays.get(selectedItem.vehicle_id);
                        if (selectedOverlay && selectedOverlay.marker) {
                            selectedOverlay.marker.openPopup();
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
                }

                async function refreshData() {
                    try {
                        effectiveMode = currentMode;
                        const params = new URLSearchParams({ mode: effectiveMode });
                        if (effectiveMode === 'offline' && currentOfflineDate) {
                            params.set('date', currentOfflineDate);
                        }
                        const url = `${dataUrl}?${params.toString()}`;
                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        const vehicles = Array.isArray(payload.vehicles) ? payload.vehicles : [];
                        if (effectiveMode === 'offline' && payload.selected_date && offlineDateEl) {
                            offlineDateEl.value = String(payload.selected_date);
                            currentOfflineDate = String(payload.selected_date);
                        }
                        syncVehicleFilterOptions(vehicles);
                        const filtered = applyVehicleFilter(vehicles);
                        renderList(filtered);
                        renderMap(filtered);

                        if (payload.updated_at) {
                            const dt = new Date(payload.updated_at);
                            lastUpdateEl.textContent = isNaN(dt.getTime())
                                ? payload.updated_at
                                : dt.toLocaleTimeString();
                        }
                    } catch (_) {
                        // ignore transient polling errors
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
                    refreshData();
                }

                function applyVehicleFilter(vehicles) {
                    if (!filteredVehicleId) {
                        return vehicles;
                    }

                    return vehicles.filter((v) => String(v.vehicle_id) === String(filteredVehicleId));
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
                        currentOfflineDate = String(event.target.value || @json(now()->toDateString()));
                        if (currentMode === 'offline') {
                            selectedVehicleId = null;
                            selectedLastPoint = null;
                            refreshData();
                        }
                    });
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

                setMode(initialMode);
                setInterval(refreshData, refreshIntervalMs);
            })();
        </script>
    @endpush
@endonce
