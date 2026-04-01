@extends('adminlte::page')

@section('title', 'Mapa de Bitacora')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-2">
    <style>
        .vehicle-log-page {
            max-width: 100%;
            margin: 0;
        }
        .vehicle-log-map-shell {
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #e8edf7;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(25, 46, 86, 0.08);
        }
        .vehicle-log-map-shell__header {
            background: #20539a;
            color: #ffffff;
            padding: 18px 22px;
        }
        .vehicle-log-map-shell__title {
            font-size: 2.05rem;
            font-weight: 700;
        }
        .vehicle-log-map-shell__meta {
            opacity: 0.92;
            font-size: 1rem;
        }
        .vehicle-log-map-shell__body {
            padding: 18px;
            background: #f7f8fb;
        }
        .vehicle-log-info-card {
            border: 1px solid #e3ebfb;
            border-radius: 14px;
            background: #fff;
            padding: 14px 16px;
            height: 100%;
        }
        .vehicle-log-info-card__label {
            color: #667085;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .vehicle-log-info-card__value {
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
            margin-top: 6px;
            word-break: break-word;
        }
        .vehicle-log-map-panel {
            border: 1px solid #e3ebfb;
            border-radius: 16px;
            background: #fff;
            padding: 16px;
        }
        .vehicle-log-map-panel--full {
            height: 100%;
        }
        .vehicle-log-map-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(320px, 1fr);
            gap: 16px;
            align-items: stretch;
        }
        .vehicle-log-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .vehicle-log-info-card--wide {
            grid-column: 1 / -1;
        }
        .vehicle-log-photos-panel {
            margin-top: 16px;
        }
        .vehicle-log-stage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }
        .vehicle-log-stage-card {
            border: 1px solid #e3ebfb;
            border-radius: 14px;
            background: #fff;
            padding: 12px;
        }
        .vehicle-log-stage-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #d8e2f4;
            background: #fff;
        }
        .vehicle-log-stage-card__title {
            color: #20539a;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .vehicle-log-stage-card__meta {
            color: #667085;
            font-size: 0.9rem;
            margin-top: 8px;
        }
        .vehicle-log-map-back-btn {
            border-radius: 12px;
            padding-inline: 18px;
            font-weight: 600;
        }
        @media (min-width: 1400px) {
            .vehicle-log-page {
                padding-right: 6px;
            }
        }
        @media (max-width: 768px) {
            .vehicle-log-map-shell__header {
                padding: 16px;
            }
            .vehicle-log-map-shell__title {
                font-size: 1.7rem;
            }
            .vehicle-log-map-shell__body {
                padding: 14px;
            }
        }
        @media (max-width: 1200px) {
            .vehicle-log-map-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .vehicle-log-info-grid {
                grid-template-columns: 1fr;
            }
            .vehicle-log-info-card--wide {
                grid-column: auto;
            }
        }
    </style>

    <div class="vehicle-log-page">
    <div class="vehicle-log-map-shell">
        <div class="vehicle-log-map-shell__header d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="vehicle-log-map-shell__title">Recorrido de bitacora</div>
                <div class="vehicle-log-map-shell__meta">
                    Vehiculo: {{ $mapPayload['vehicle'] }} |
                    Fecha: {{ $mapPayload['date'] }} |
                    Inicio: {{ $mapPayload['startName'] ?: '-' }} |
                    Destino: {{ $mapPayload['endName'] ?: '-' }}
                </div>
            </div>
            <a href="{{ route('livewire.vehicle-logs') }}" class="btn btn-light vehicle-log-map-back-btn">Volver al listado</a>
        </div>

        <div class="vehicle-log-map-shell__body">
            <div class="vehicle-log-map-grid">
                <div class="vehicle-log-map-panel vehicle-log-map-panel--full">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="fw-bold text-primary">Mapa del recorrido</div>
                        <button type="button" id="vehicle-log-map-recenter" class="btn btn-outline-primary btn-sm">Recentrar mapa</button>
                    </div>
                    <div id="vehicle-log-map" style="height: 70vh; border-radius: 10px;"></div>
                    <div id="vehicle-log-map-status" class="small text-muted mt-3">Cargando mapa...</div>
                </div>

                <div class="vehicle-log-map-panel vehicle-log-map-panel--full">
                    <div class="fw-bold text-primary mb-3">Informacion de la bitacora</div>
                    <div class="vehicle-log-info-grid">
                        <div class="vehicle-log-info-card">
                            <div class="vehicle-log-info-card__label">Conductor</div>
                            <div class="vehicle-log-info-card__value">{{ $vehicleLog->driver?->nombre ?? 'Sin conductor' }}</div>
                        </div>
                        <div class="vehicle-log-info-card">
                            <div class="vehicle-log-info-card__label">Bitacora</div>
                            <div class="vehicle-log-info-card__value">#{{ $vehicleLog->id }}</div>
                        </div>
                        <div class="vehicle-log-info-card">
                            <div class="vehicle-log-info-card__label">Km salida</div>
                            <div class="vehicle-log-info-card__value">{{ number_format((float) ($vehicleLog->kilometraje_salida ?? 0), 2) }}</div>
                        </div>
                        <div class="vehicle-log-info-card">
                            <div class="vehicle-log-info-card__label">Km recorrido</div>
                            <div class="vehicle-log-info-card__value">{{ number_format((float) ($vehicleLog->kilometraje_recorrido ?? 0), 2) }}</div>
                        </div>
                        <div class="vehicle-log-info-card">
                            <div class="vehicle-log-info-card__label">Km llegada</div>
                            <div class="vehicle-log-info-card__value">{{ number_format((float) ($vehicleLog->kilometraje_llegada ?? 0), 2) }}</div>
                        </div>
                        <div class="vehicle-log-info-card">
                            <div class="vehicle-log-info-card__label">Gasolina</div>
                            <div class="vehicle-log-info-card__value">{{ $vehicleLog->fuel_log_id ? 'Si' : 'No' }}</div>
                        </div>
                        <div class="vehicle-log-info-card vehicle-log-info-card--wide">
                            <div class="vehicle-log-info-card__label">Inicio</div>
                            <div class="vehicle-log-info-card__value">{{ $mapPayload['startName'] ?: '-' }}</div>
                        </div>
                        <div class="vehicle-log-info-card vehicle-log-info-card--wide">
                            <div class="vehicle-log-info-card__label">Destino</div>
                            <div class="vehicle-log-info-card__value">{{ $mapPayload['endName'] ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="vehicle-log-map-panel vehicle-log-photos-panel">
                <div class="fw-bold text-primary mb-3">Fotos tomadas</div>
                @if(($stagePhotos ?? collect())->isNotEmpty())
                    <div class="vehicle-log-stage-grid">
                        @foreach($stagePhotos as $photo)
                            <div class="vehicle-log-stage-card">
                                <div class="vehicle-log-stage-card__title">{{ $photo['stage_name'] }}</div>
                                <a href="{{ $photo['photo_url'] }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $photo['photo_url'] }}" alt="Foto {{ $photo['stage_name'] }}">
                                </a>
                                <div class="vehicle-log-stage-card__meta">
                                    {{ $photo['event_at'] ?: 'Sin fecha' }}<br>
                                    {{ $photo['address'] ?: 'Sin ubicacion registrada' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted">No hay fotos registradas para esta bitacora.</div>
                @endif
            </div>

        </div>
    </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (function () {
        const payload = @json($mapPayload);
        const mapEl = document.getElementById('vehicle-log-map');
        const statusEl = document.getElementById('vehicle-log-map-status');
        const recenterBtn = document.getElementById('vehicle-log-map-recenter');

        if (!mapEl || !window.L) {
            if (statusEl) statusEl.textContent = 'No se pudo inicializar el mapa.';
            return;
        }

        const map = L.map(mapEl).setView([-16.5, -68.15], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const layer = L.layerGroup().addTo(map);
        const route = Array.isArray(payload.route) ? payload.route : [];
        const bounds = [];
        let fittedBounds = null;

        let routeCoords = route
            .map((point) => {
                const lat = Number.parseFloat(point.lat ?? point.latitude);
                const lng = Number.parseFloat(point.lng ?? point.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
                const coords = [lat, lng];
                bounds.push(coords);
                return {
                    lat,
                    lng,
                    label: point.address || point.label || point.point_label || '',
                };
            })
            .filter(Boolean);

        const startLat = Number.parseFloat(payload.startLat);
        const startLng = Number.parseFloat(payload.startLng);
        const endLat = Number.parseFloat(payload.endLat);
        const endLng = Number.parseFloat(payload.endLng);

        if (routeCoords.length < 2 && Number.isFinite(startLat) && Number.isFinite(startLng) && Number.isFinite(endLat) && Number.isFinite(endLng)) {
            routeCoords = [
                { lat: startLat, lng: startLng, label: payload.startName || 'Inicio' },
                { lat: endLat, lng: endLng, label: payload.endName || 'Destino' },
            ];
        }

        if (routeCoords.length > 1) {
            L.polyline(routeCoords.map((point) => [point.lat, point.lng]), {
                color: '#2563eb',
                weight: 4,
                opacity: 0.9,
                lineCap: 'round',
                lineJoin: 'round',
                smoothFactor: 1.4,
            }).addTo(layer);
        }

        routeCoords.forEach((point, index) => {
            L.circleMarker([point.lat, point.lng], {
                radius: 5,
                color: '#1d4ed8',
                weight: 2,
                fillColor: '#93c5fd',
                fillOpacity: 0.95,
            }).addTo(layer).bindPopup(point.label || `Punto ${index + 1}`);
        });

        if (Number.isFinite(startLat) && Number.isFinite(startLng)) {
            const start = [startLat, startLng];
            bounds.push(start);
            L.circleMarker(start, {
                radius: 11,
                color: '#14532d',
                weight: 4,
                fillColor: '#22c55e',
                fillOpacity: 1,
            }).addTo(layer).bindPopup(`<strong>Inicio</strong><br>${payload.startName || '-'}`).bringToFront();
        }

        if (Number.isFinite(endLat) && Number.isFinite(endLng)) {
            const end = [endLat, endLng];
            bounds.push(end);
            L.circleMarker(end, {
                radius: 11,
                color: '#7f1d1d',
                weight: 4,
                fillColor: '#ef4444',
                fillOpacity: 1,
            }).addTo(layer).bindPopup(`<strong>Destino</strong><br>${payload.endName || '-'}`).bringToFront();
        }

        if (bounds.length > 1) {
            fittedBounds = L.latLngBounds(bounds).pad(0.2);
            map.fitBounds(fittedBounds, { padding: [80, 80], maxZoom: 15 });
            if (statusEl) statusEl.textContent = 'Mapa cargado correctamente.';
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 15);
            if (statusEl) statusEl.textContent = 'Mapa cargado con un solo punto disponible.';
        } else {
            if (statusEl) statusEl.textContent = 'Esta bitacora no tiene coordenadas para mostrar.';
        }

        recenterBtn?.addEventListener('click', function () {
            if (fittedBounds) {
                map.fitBounds(fittedBounds, { padding: [80, 80], maxZoom: 15 });
                return;
            }

            if (bounds.length === 1) {
                map.setView(bounds[0], 15);
            }
        });

        setTimeout(() => map.invalidateSize(), 120);
        setTimeout(() => map.invalidateSize(), 320);
    })();
</script>
@endsection
