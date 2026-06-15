@extends('adminlte::page')

@section('title', 'Global Nivel Nacional (Ingreso)')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Global Nivel Nacional (Ingreso)</h1>
            <small class="text-muted">Reporte rapido: todos los registros menos cancelados.</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>
    </div>
@stop

@section('content')
    @php
        $modulesMap = [
            'contrato' => 'CONTRATOS',
            'ems' => 'EMS',
            'certi' => 'CERTIFICADOS',
            'ordi' => 'ORDINARIOS',
        ];
        $selectedMonthFilters = $selectedMonths ?? [];
        $selectedServiceFilters = $selectedServices ?? [];
    @endphp

    <div id="exportLoadingOverlay" class="export-loading-overlay" aria-live="polite" aria-hidden="true">
        <div class="export-loading-box">
            <div class="export-spinner"></div>
            <h2>Espere por favor</h2>
            <p id="exportLoadingTitle">Preparando reporte...</p>
            <div class="export-progress">
                <div class="export-progress-bar"></div>
            </div>
            <small id="exportLoadingDetail">Recolectando los datos seleccionados.</small>
            <small id="exportLoadingTime" class="d-block mt-2">Tiempo transcurrido: 0s</small>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <strong><i class="fas fa-filter mr-1"></i> Filtros rapidos</strong>
        </div>
        <form method="GET" action="{{ route('dashboard.global-ingreso') }}" id="globalIngresoForm">
            <input type="hidden" name="range" id="reportRange" value="{{ $range ?? 'all' }}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>Fecha desde</label>
                        <input type="date" class="form-control" name="from" id="reportFrom" value="{{ $from }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Fecha hasta</label>
                        <input type="date" class="form-control" name="to" id="reportTo" value="{{ $to }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Meses</label>
                        <div class="filter-box">
                            @foreach(($monthOptions ?? []) as $monthOption)
                                <label class="filter-check">
                                    <input
                                        type="checkbox"
                                        name="months[]"
                                        value="{{ $monthOption['value'] }}"
                                        {{ in_array($monthOption['value'], $selectedMonthFilters, true) ? 'checked' : '' }}
                                    >
                                    <span>{{ $monthOption['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label>Modulo / EMS</label>
                        <div class="filter-box">
                            @foreach($modulesMap as $moduleKey => $moduleLabel)
                                <label class="filter-check">
                                    <input
                                        type="checkbox"
                                        name="modules[]"
                                        value="{{ $moduleKey }}"
                                        {{ in_array($moduleKey, $selectedModules ?? [], true) ? 'checked' : '' }}
                                    >
                                    <span>{{ $moduleLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-7 mb-3">
                        <label>Servicios</label>
                        <div class="filter-box filter-box-services">
                            @forelse(($serviceOptions ?? []) as $serviceOption)
                                <label class="filter-check">
                                    <input
                                        type="checkbox"
                                        name="servicios[]"
                                        value="{{ $serviceOption }}"
                                        {{ in_array($serviceOption, $selectedServiceFilters, true) ? 'checked' : '' }}
                                    >
                                    <span>{{ $serviceOption }}</span>
                                </label>
                            @empty
                                <span class="text-muted small">Sin servicios para los filtros actuales.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center">
                <div class="mb-2 mb-md-0">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                    <a href="{{ route('dashboard.global-ingreso') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-undo mr-1"></i> Limpiar
                    </a>
                </div>
                <div>
                    <a
                        href="{{ route('dashboard.global-ingreso.excel') }}"
                        class="btn btn-success mr-2"
                        data-export-url="{{ route('dashboard.global-ingreso.excel') }}"
                        data-export-kind="Excel completo"
                        data-export-total="{{ $summary['total_filtrado'] ?? ($summary['total'] ?? 0) }}"
                    >
                        <i class="fas fa-file-excel mr-1"></i> Excel completo
                    </a>
                    <a
                        href="{{ route('dashboard.global-ingreso.pdf') }}"
                        class="btn btn-danger"
                        data-export-url="{{ route('dashboard.global-ingreso.pdf') }}"
                        data-export-kind="PDF imprimible"
                        data-export-total="{{ $summary['total_filtrado'] ?? ($summary['total'] ?? 0) }}"
                        data-export-limit="1000"
                    >
                        <i class="fas fa-file-pdf mr-1"></i> PDF imprimible
                    </a>
                    <div class="text-muted small mt-2 text-md-right">
                        Excel contiene todo el detalle. PDF imprime totales completos y una muestra de detalle para evitar errores.
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-primary"><i class="fas fa-hashtag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Cantidad</span>
                    <span class="info-box-number">{{ number_format($summary['total_filtrado'] ?? ($summary['total'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-info"><i class="fas fa-weight-hanging"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Peso total</span>
                    <span class="info-box-number">{{ number_format((float) ($totals['peso_total'] ?? 0), 3) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Bs total</span>
                    <span class="info-box-number">Bs {{ number_format((float) ($totals['precio_total'] ?? 0), 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-success">
        <div class="card-header">
            <strong>Resumen por grupo</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Grupo</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Peso</th>
                            <th class="text-right">Bs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($moduleSummary ?? []) as $moduleRow)
                            <tr>
                                <td class="font-weight-bold">{{ $moduleRow['label'] }}</td>
                                <td class="text-right">{{ number_format((int) $moduleRow['total']) }}</td>
                                <td class="text-right">{{ number_format((float) $moduleRow['peso'], 3) }}</td>
                                <td class="text-right">Bs {{ number_format((float) $moduleRow['precio'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Sin datos por grupo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Detalle</strong>
            <span class="text-muted small">Codigo, cantidad, peso y Bs</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Codigo</th>
                            <th>Modulo</th>
                            <th>Servicio</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Destinatario</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Peso</th>
                            <th class="text-right">Bs</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
                                <td class="font-weight-bold">{{ $row['codigo'] }}</td>
                                <td>{{ $row['modulo_label'] }}</td>
                                <td>{{ $row['servicio'] }}</td>
                                <td>{{ $row['origen'] }}</td>
                                <td>{{ $row['destino'] }}</td>
                                <td>{{ $row['destinatario'] }}</td>
                                <td class="text-right">1</td>
                                <td class="text-right">{{ number_format((float) $row['peso'], 3) }}</td>
                                <td class="text-right">Bs {{ number_format((float) $row['precio'], 2) }}</td>
                                <td>{{ $row['created_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">Sin resultados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $rows->appends(request()->except('page'))->links('pagination::bootstrap-4') }}
        </div>
    </div>

    @include('footer')
@stop

@section('css')
    <style>
        .filter-box {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 42px;
            max-height: 116px;
            overflow: auto;
            padding: 8px;
            border: 1px solid #d7dde5;
            border-radius: 6px;
            background: #fff;
        }
        .filter-box-services {
            max-height: 154px;
        }
        .filter-check {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            padding: 5px 8px;
            border: 1px solid #d7dde5;
            border-radius: 6px;
            font-size: .84rem;
            background: #f8fafc;
            cursor: pointer;
        }
        .filter-check input {
            margin: 0;
        }
        .info-box {
            min-height: 78px;
        }
        .export-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, .68);
            backdrop-filter: blur(2px);
        }
        .export-loading-overlay.is-visible {
            display: flex;
        }
        .export-loading-box {
            width: min(430px, calc(100vw - 32px));
            padding: 24px;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .28);
            text-align: center;
        }
        .export-loading-box h2 {
            margin: 12px 0 6px;
            font-size: 1.35rem;
            font-weight: 700;
        }
        .export-loading-box p {
            margin-bottom: 12px;
            color: #334155;
            font-weight: 600;
        }
        .export-spinner {
            width: 46px;
            height: 46px;
            margin: 0 auto;
            border: 5px solid #e2e8f0;
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: exportSpin .8s linear infinite;
        }
        .export-progress {
            height: 10px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
            margin: 14px 0 10px;
        }
        .export-progress-bar {
            width: 38%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0d6efd, #20c997);
            animation: exportProgress 1.25s ease-in-out infinite;
        }
        .export-loading-box small {
            color: #475569;
        }
        @keyframes exportSpin {
            to { transform: rotate(360deg); }
        }
        @keyframes exportProgress {
            0% { transform: translateX(-105%); }
            50% { transform: translateX(80%); }
            100% { transform: translateX(260%); }
        }
    </style>
@stop

@section('js')
    <script>
        (() => {
            const form = document.getElementById('globalIngresoForm');
            const rangeInput = document.getElementById('reportRange');
            const from = document.getElementById('reportFrom');
            const to = document.getElementById('reportTo');
            const overlay = document.getElementById('exportLoadingOverlay');
            const overlayTitle = document.getElementById('exportLoadingTitle');
            const overlayDetail = document.getElementById('exportLoadingDetail');
            const overlayTime = document.getElementById('exportLoadingTime');
            let overlayTimer = null;
            let exporting = false;
            const syncRange = () => {
                if (!rangeInput) {
                    return;
                }
                rangeInput.value = (from?.value || to?.value) ? 'custom' : 'all';
            };
            const buildUrl = (baseUrl) => {
                if (!form) {
                    return baseUrl;
                }
                syncRange();
                const params = new URLSearchParams(new FormData(form));
                params.delete('page');
                const query = params.toString();
                return query ? `${baseUrl}?${query}` : baseUrl;
            };
            const showExportLoading = (kind, total, detailLimit) => {
                if (!overlay) {
                    return;
                }
                const totalText = Number(total || 0).toLocaleString('es-BO');
                const limit = Number(detailLimit || 0);
                const startedAt = Date.now();

                overlayTitle.textContent = `Generando ${kind}`;
                overlayDetail.textContent = limit > 0
                    ? `Espere por favor, se esta creando el PDF con totales de ${totalText} registros y detalle imprimible.`
                    : `Espere por favor, se esta creando el archivo con ${totalText} registros.`;
                overlayTime.textContent = 'Tiempo transcurrido: 0s';
                overlay.classList.add('is-visible');
                overlay.setAttribute('aria-hidden', 'false');

                clearInterval(overlayTimer);
                overlayTimer = setInterval(() => {
                    const seconds = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
                    overlayTime.textContent = `Tiempo transcurrido: ${seconds}s`;
                    if (seconds >= 20) {
                        overlayDetail.textContent = `Sigue generando ${kind}. No cierre esta ventana.`;
                    }
                }, 1000);
            };
            const hideExportLoading = () => {
                if (overlay) {
                    overlay.classList.remove('is-visible');
                    overlay.setAttribute('aria-hidden', 'true');
                }
                clearInterval(overlayTimer);
                exporting = false;
            };
            const filenameFromResponse = (response, fallback) => {
                const disposition = response.headers.get('Content-Disposition') || '';
                const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                if (utfMatch?.[1]) {
                    return decodeURIComponent(utfMatch[1].replace(/"/g, ''));
                }

                const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
                return plainMatch?.[1] || fallback;
            };
            const downloadExport = async (url, kind) => {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`No se pudo generar ${kind}. Codigo ${response.status}.`);
                }

                const blob = await response.blob();
                const extension = kind.toLowerCase().includes('pdf') ? 'pdf' : 'xlsx';
                const filename = filenameFromResponse(response, `global-nivel-nacional-ingreso.${extension}`);
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');

                link.href = objectUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            };

            document.querySelectorAll('[data-export-url]').forEach((link) => {
                link.addEventListener('click', async (event) => {
                    event.preventDefault();
                    if (exporting) {
                        return;
                    }
                    exporting = true;
                    const kind = link.dataset.exportKind || 'reporte';
                    showExportLoading(
                        kind,
                        link.dataset.exportTotal || 0,
                        link.dataset.exportLimit || 0
                    );

                    try {
                        await downloadExport(buildUrl(link.dataset.exportUrl), kind);
                    } catch (error) {
                        alert(error.message || 'No se pudo generar el archivo.');
                    } finally {
                        hideExportLoading();
                    }
                });
            });

            window.addEventListener('pageshow', () => {
                hideExportLoading();
            });

            form?.addEventListener('submit', syncRange);
        })();
    </script>
@stop
