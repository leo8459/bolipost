@extends('adminlte::page')

@section('title', 'Resumen Ejecutivo')

@section('content_header')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h1 class="mb-0">Resumen Ejecutivo</h1>
            <small class="text-muted">Paquetes generados por servicio, responsable de registro y responsable de entrega.</small>
        </div>
        <a
            href="{{ route('reportes.resumen-administrativo.pdf', request()->query()) }}"
            class="btn btn-danger mt-2 mt-lg-0"
            id="adminSummaryPdfBtn"
        >
            <i class="fas fa-file-pdf mr-1"></i> Descargar PDF presentacion
        </a>
    </div>
@stop

@section('content')
    @php
        $adminRanking = collect($administrativeSummary['ranking'] ?? []);
        $eficienciaServicios = collect($administrativeSummary['eficiencia_servicios'] ?? []);
        $topOrigen = $administrativeSummary['top_origen'] ?? null;
        $topDestino = $administrativeSummary['top_destino'] ?? null;
        $rankingOrigenes = collect($administrativeSummary['ranking_origenes'] ?? []);
        $rankingDestinos = collect($administrativeSummary['ranking_destinos'] ?? []);
        $pesoPorModulo = collect($administrativeSummary['peso_por_modulo'] ?? []);
        $ventanillaPorModulo = collect($administrativeSummary['ventanilla_por_modulo'] ?? []);
        $topVentanilla = $administrativeSummary['top_ventanilla'] ?? null;
        $entregasVentanillaTop = collect($administrativeSummary['entregas_ventanilla_top'] ?? []);
        $entregasCarteroTop = collect($administrativeSummary['entregas_cartero_top'] ?? []);
        $malencaminados = $administrativeSummary['malencaminados'] ?? [];
        $malencaminadosPorModulo = collect($malencaminados['por_modulo'] ?? []);
        $ultimosMalencaminados = collect($malencaminados['ultimos'] ?? []);
        $departamentoOrigenSeleccionado = $departamentoOrigen ?? '';
        $departamentoDestinoSeleccionado = $departamentoDestino ?? ($departamento ?? '');
        $mesesSeleccionados = $selectedMonths ?? [];
    @endphp

    <div class="card card-outline card-primary">
        <div class="card-header">
            <strong><i class="fas fa-filter mr-1"></i> Filtros del resumen</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reportes.resumen-administrativo') }}" id="adminSummaryForm">
                <input type="hidden" name="range" value="{{ $range ?? 'all' }}">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Buscar paquete o persona</label>
                        <input type="text" class="form-control" name="q" value="{{ $search }}" placeholder="Codigo, usuario, ciudad, remitente">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="font-weight-bold">Fecha desde</label>
                        <input type="date" class="form-control" name="from" value="{{ $from }}">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="font-weight-bold">Fecha hasta</label>
                        <input type="date" class="form-control" name="to" value="{{ $to }}">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Meses completos</label>
                        <div class="month-filter-grid border rounded p-2">
                            @foreach(($monthOptions ?? []) as $monthOption)
                                <div class="custom-control custom-checkbox">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="month_{{ str_replace('-', '_', $monthOption['value']) }}"
                                        name="months[]"
                                        value="{{ $monthOption['value'] }}"
                                        {{ in_array($monthOption['value'], $mesesSeleccionados, true) ? 'checked' : '' }}
                                    >
                                    <label class="custom-control-label" for="month_{{ str_replace('-', '_', $monthOption['value']) }}">
                                        {{ $monthOption['label'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">Puedes seleccionar abril y mayo. Si eliges meses, se ignoran las fechas.</small>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Departamento origen</label>
                        <select class="form-control" name="departamento_origen">
                            <option value="">Todos</option>
                            @foreach(($departamentosDisponibles ?? []) as $departamentoDisponible)
                                <option value="{{ $departamentoDisponible }}" {{ $departamentoOrigenSeleccionado === $departamentoDisponible ? 'selected' : '' }}>
                                    {{ $departamentoDisponible }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="font-weight-bold">Departamento destino</label>
                        <select class="form-control" name="departamento_destino">
                            <option value="">Todos</option>
                            @foreach(($departamentosDisponibles ?? []) as $departamentoDisponible)
                                <option value="{{ $departamentoDisponible }}" {{ $departamentoDestinoSeleccionado === $departamentoDisponible ? 'selected' : '' }}>
                                    {{ $departamentoDisponible }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-end">
                    <div>
                        <button type="submit" class="btn btn-primary mb-2">
                            <i class="fas fa-search mr-1"></i> Ver resumen
                        </button>
                        <a href="{{ route('reportes.resumen-administrativo') }}" class="btn btn-outline-secondary mb-2">
                            <i class="fas fa-undo mr-1"></i> Reiniciar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-outline card-secondary executive-index-card" id="indice-ejecutivo">
        <div class="card-header">
            <strong><i class="fas fa-list-ul mr-1"></i> Indice ejecutivo</strong>
        </div>
        <div class="card-body">
            <div class="executive-index-grid">
                <a href="#resumen-general"><i class="fas fa-chart-pie"></i><span>Resumen general</span></a>
                <a href="#peso-servicio"><i class="fas fa-weight-hanging"></i><span>Peso por servicio</span></a>
                <a href="#top-entregas"><i class="fas fa-hand-holding"></i><span>Top entregas</span></a>
                <a href="#ventanilla-almacen"><i class="fas fa-store"></i><span>Ventanilla / almacen</span></a>
                <a href="#malencaminados-servicio"><i class="fas fa-random"></i><span>Malencaminados</span></a>
                <a href="#ultimos-malencaminados"><i class="fas fa-exchange-alt"></i><span>Ultimos cambios</span></a>
                <a href="#origenes-destinos"><i class="fas fa-map-marked-alt"></i><span>Origenes y destinos</span></a>
                <a href="#eficiencia-servicios"><i class="fas fa-stopwatch"></i><span>Eficiencia</span></a>
                <a href="#responsables-registro"><i class="fas fa-users"></i><span>Responsables</span></a>
            </div>
        </div>
    </div>

    <div class="card card-outline card-info administrative-summary-card" id="resumen-general">
        <div class="card-header">
            <strong><i class="fas fa-clipboard-list mr-1"></i> Resumen ejecutivo de paquetes</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-kpi">
                        <span>Total paquetes</span>
                        <strong>{{ number_format($administrativeSummary['total_admisiones'] ?? 0) }}</strong>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-kpi">
                        <span>Usuarios activos</span>
                        <strong>{{ number_format($administrativeSummary['usuarios_activos'] ?? 0) }}</strong>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-kpi">
                        <span>Peso total</span>
                        <strong>{{ number_format((float) ($administrativeSummary['peso_total'] ?? 0), 3) }}</strong>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-kpi">
                        <span>Costo total Bs</span>
                        <strong>{{ number_format((float) ($administrativeSummary['costo_total'] ?? 0), 2) }}</strong>
                        <small class="price-note">Nota: contratos no sumados por tema tarifario.</small>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="admin-top">
                        <div>
                            <span class="text-uppercase">Departamento que genero mas</span>
                            <strong>{{ $topOrigen['nombre'] ?? 'SIN ORIGEN' }}</strong>
                            <small>Contratos, EMS, certificadas y ordinarias</small>
                        </div>
                        <div class="text-right">
                            <span>Paquetes</span>
                            <strong>{{ number_format($topOrigen['total'] ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="admin-bottom">
                        <div>
                            <span class="text-uppercase">Departamento que recibio mas</span>
                            <strong>{{ $topDestino['nombre'] ?? 'SIN DESTINO' }}</strong>
                            <small>Destino del envio</small>
                        </div>
                        <div class="text-right">
                            <span>Paquetes</span>
                            <strong>{{ number_format($topDestino['total'] ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4" id="peso-servicio">
                <div class="ranking-title">
                    <span>Peso total por servicio</span>
                    <small>Contratos, EMS, certificados y ordinarios</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover admin-ranking-table mb-0">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th class="text-right">Paquetes</th>
                                <th class="text-right">Peso total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pesoPorModulo as $pesoModulo)
                                <tr>
                                    <td class="font-weight-bold">{{ $pesoModulo['servicio'] }}</td>
                                    <td class="text-right">{{ number_format((int) $pesoModulo['total']) }}</td>
                                    <td class="text-right">{{ number_format((float) $pesoModulo['peso'], 3) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Sin peso registrado para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row mb-4" id="top-entregas">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="ranking-title">
                        <span>Top entregas por ventanilla</span>
                        <small>Separado del registro</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover admin-ranking-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Entrego</th>
                                    <th>Servicios</th>
                                    <th class="text-right">Entregas</th>
                                    <th class="text-right">Peso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entregasVentanillaTop as $entregaRow)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="font-weight-bold">{{ $entregaRow['usuario'] }}</td>
                                        <td>{{ $entregaRow['servicio'] }}</td>
                                        <td class="text-right">{{ number_format((int) $entregaRow['total']) }}</td>
                                        <td class="text-right">{{ number_format((float) $entregaRow['peso'], 3) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Sin entregas por ventanilla para los filtros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="ranking-title">
                        <span>Top entregas por cartero</span>
                        <small>Usuarios con rol cartero</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover admin-ranking-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Entrego</th>
                                    <th>Servicios</th>
                                    <th class="text-right">Entregas</th>
                                    <th class="text-right">Peso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entregasCarteroTop as $entregaRow)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="font-weight-bold">{{ $entregaRow['usuario'] }}</td>
                                        <td>{{ $entregaRow['servicio'] }}</td>
                                        <td class="text-right">{{ number_format((int) $entregaRow['total']) }}</td>
                                        <td class="text-right">{{ number_format((float) $entregaRow['peso'], 3) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Sin entregas por cartero para los filtros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mb-4" id="ventanilla-almacen">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <div class="admin-kpi">
                        <span>Mayor en ventanilla</span>
                        <strong>{{ $topVentanilla['servicio'] ?? 'SIN DATOS' }}</strong>
                        <small class="price-note">{{ number_format((int) ($topVentanilla['total'] ?? 0)) }} paquetes en ventanilla/almacen.</small>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="ranking-title">
                        <span>Paquetes en ventanilla/almacen por servicio</span>
                        <small>Ordinarios toma los recibidos de almacen</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover admin-ranking-table mb-0">
                            <thead>
                                <tr>
                                    <th>Servicio</th>
                                    <th class="text-right">En ventanilla/almacen</th>
                                    <th class="text-right">Peso total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ventanillaPorModulo as $ventanillaModulo)
                                    <tr>
                                        <td class="font-weight-bold">{{ $ventanillaModulo['servicio'] }}</td>
                                        <td class="text-right">{{ number_format((int) $ventanillaModulo['total']) }}</td>
                                        <td class="text-right">{{ number_format((float) $ventanillaModulo['peso'], 3) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Sin paquetes en ventanilla para los filtros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mb-4" id="malencaminados-servicio">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <div class="admin-kpi">
                        <span>Malencaminados corregidos</span>
                        <strong>{{ number_format((int) ($malencaminados['total'] ?? 0)) }}</strong>
                        <small class="price-note">Cambios de destino registrados.</small>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="ranking-title">
                        <span>Malencaminados por servicio</span>
                        <small>Registros donde se hizo cambio de destino</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover admin-ranking-table mb-0">
                            <thead>
                                <tr>
                                    <th>Servicio</th>
                                    <th class="text-right">Cambios</th>
                                    <th class="text-right">Malencaminamientos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($malencaminadosPorModulo as $malModulo)
                                    <tr>
                                        <td class="font-weight-bold">{{ $malModulo['servicio'] }}</td>
                                        <td class="text-right">{{ number_format((int) $malModulo['total']) }}</td>
                                        <td class="text-right">{{ number_format((int) $malModulo['malencaminamientos']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Sin malencaminados para los filtros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mb-4" id="ultimos-malencaminados">
                <div class="ranking-title">
                    <span>Ultimos cambios malencaminados</span>
                    <small>Destino anterior y destino nuevo</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover admin-ranking-table mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Codigo</th>
                                <th>Servicio</th>
                                <th>Origen</th>
                                <th>Destino anterior</th>
                                <th>Destino nuevo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ultimosMalencaminados as $malRow)
                                <tr>
                                    <td>{{ $malRow['created_at'] }}</td>
                                    <td class="font-weight-bold">{{ $malRow['codigo'] }}</td>
                                    <td>{{ $malRow['servicio'] }}</td>
                                    <td>{{ $malRow['departamento_origen'] }}</td>
                                    <td>{{ $malRow['destino_anterior'] }}</td>
                                    <td class="font-weight-bold">{{ $malRow['destino_nuevo'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Sin cambios malencaminados para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row mb-4" id="origenes-destinos">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="ranking-title">
                        <span>Top origenes que registran mas</span>
                        <small>Contratos, EMS, certificadas y ordinarias</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover admin-ranking-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Origen</th>
                                    <th class="text-right">Paquetes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rankingOrigenes as $origenRow)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="font-weight-bold">{{ $origenRow['nombre'] }}</td>
                                        <td class="text-right">{{ number_format((int) $origenRow['total']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Sin origenes para los filtros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="ranking-title">
                        <span>Top destinos que reciben mas</span>
                        <small>Segun destino del paquete</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover admin-ranking-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Destino</th>
                                    <th class="text-right">Paquetes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rankingDestinos as $destinoRow)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="font-weight-bold">{{ $destinoRow['nombre'] }}</td>
                                        <td class="text-right">{{ number_format((int) $destinoRow['total']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Sin destinos para los filtros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="efficiency-panel mb-4" id="eficiencia-servicios">
                <div class="ranking-title">
                    <span>Servicios mas eficientes en ventanilla</span>
                    <small>Ordenado de mejor a peor por promedio de entrega</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover admin-ranking-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Servicio</th>
                                <th class="text-right">Paquetes entregados</th>
                                <th class="text-right">Promedio entrega</th>
                                <th class="text-right">Mejor tiempo</th>
                                <th class="text-right">Mayor tiempo</th>
                                <th class="text-right">Peso total</th>
                                <th class="text-right">Costo Bs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($eficienciaServicios as $servicioRow)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td class="font-weight-bold">{{ $servicioRow['servicio'] }}</td>
                                    <td class="text-right">{{ number_format((int) $servicioRow['total']) }}</td>
                                    <td class="text-right">
                                        <span class="efficiency-pill">{{ $servicioRow['promedio'] }}</span>
                                    </td>
                                    <td class="text-right">{{ $servicioRow['mejor_tiempo'] }}</td>
                                    <td class="text-right">{{ $servicioRow['mayor_tiempo'] }}</td>
                                    <td class="text-right">{{ number_format((float) $servicioRow['peso'], 3) }}</td>
                                    <td class="text-right">{{ number_format((float) $servicioRow['costo'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Sin paquetes entregados para calcular eficiencia.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-responsive" id="responsables-registro">
                <div class="ranking-title">
                    <span>Listado de paquetes por responsable</span>
                    <small>Ordenado de quien genero mas a quien genero menos</small>
                </div>
                <table class="table table-hover admin-ranking-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Registro</th>
                            <th>Regional</th>
                            <th>Servicio</th>
                            <th>Entrega</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th class="text-right">Paquetes</th>
                            <th class="text-right">Peso total</th>
                            <th class="text-right">Costo Bs</th>
                            <th>Primer paquete</th>
                            <th>Ultimo paquete</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($adminRanking as $adminRow)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="font-weight-bold">{{ $adminRow['usuario'] }}</td>
                                <td>{{ $adminRow['regional'] }}</td>
                                <td>
                                    <div class="service-badges">
                                        @foreach(($adminRow['servicios'] ?? []) as $servicioItem)
                                            <span class="service-badge">
                                                <span>{{ $servicioItem['nombre'] }}</span>
                                                <strong>{{ number_format((int) $servicioItem['cantidad']) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <div class="summary-badges">
                                        @foreach(($adminRow['entregadores'] ?? []) as $entregadorItem)
                                            <span class="summary-badge delivery-badge">
                                                <span>{{ $entregadorItem['nombre'] }}</span>
                                                <strong>{{ number_format((int) $entregadorItem['cantidad']) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $adminRow['origen'] }}</td>
                                <td>
                                    <div class="summary-badges summary-badges-destination">
                                        @foreach(($adminRow['destinos'] ?? []) as $destinoItem)
                                            <span class="summary-badge destination-badge">
                                                <span>{{ $destinoItem['nombre'] }}</span>
                                                <strong>{{ number_format((int) $destinoItem['cantidad']) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-right">{{ number_format($adminRow['total']) }}</td>
                                <td class="text-right">{{ number_format((float) $adminRow['peso'], 3) }}</td>
                                <td class="text-right">{{ number_format((float) $adminRow['precio'], 2) }}</td>
                                <td>{{ $adminRow['primera_admision'] }}</td>
                                <td>{{ $adminRow['ultima_admision'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">Sin paquetes para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('footer')
@stop

@section('css')
    <style>
        html {
            scroll-behavior: smooth;
        }
        #resumen-general,
        #peso-servicio,
        #top-entregas,
        #ventanilla-almacen,
        #malencaminados-servicio,
        #ultimos-malencaminados,
        #origenes-destinos,
        #eficiencia-servicios,
        #responsables-registro {
            scroll-margin-top: 86px;
        }
        .executive-index-card {
            border-top: 3px solid #64748b;
        }
        .executive-index-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 10px;
        }
        .executive-index-grid a {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            min-height: 42px;
            border: 1px solid #d7e2f0;
            border-radius: 8px;
            background: #f8fbff;
            color: #20539a;
            font-size: .84rem;
            font-weight: 800;
            padding: 9px 11px;
            text-decoration: none;
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }
        .executive-index-grid a:hover {
            background: #eef5ff;
            border-color: #b9cbe2;
            color: #173f75;
            transform: translateY(-1px);
        }
        .executive-index-grid i {
            width: 18px;
            text-align: center;
            color: #64748b;
        }
        .administrative-summary-card {
            border-top: 3px solid #17a2b8;
        }
        .admin-kpi,
        .admin-top,
        .admin-bottom {
            min-height: 92px;
            border: 1px solid #d9e7f4;
            border-radius: 10px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            padding: 16px 18px;
            box-shadow: 0 8px 20px rgba(32, 83, 154, .06);
        }
        .admin-kpi span,
        .admin-top span,
        .admin-bottom span {
            display: block;
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .admin-kpi strong {
            display: block;
            margin-top: 8px;
            color: #20539a;
            font-size: 2rem;
            line-height: 1;
        }
        .price-note {
            display: block;
            margin-top: 8px;
            color: #9a5b00;
            font-size: .76rem;
            font-weight: 700;
            line-height: 1.25;
        }
        .admin-top,
        .admin-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            color: #ffffff;
        }
        .admin-top {
            background: linear-gradient(135deg, #20539a 0%, #173f75 100%);
            border-color: #20539a;
        }
        .admin-bottom {
            background: linear-gradient(135deg, #64748b 0%, #334155 100%);
            border-color: #475569;
        }
        .admin-top span {
            color: rgba(255, 255, 255, .76);
        }
        .admin-bottom span {
            color: rgba(255, 255, 255, .76);
        }
        .admin-top strong,
        .admin-bottom strong {
            display: block;
            margin-top: 5px;
            font-size: 1.35rem;
            color: #ffffff;
        }
        .admin-top small,
        .admin-bottom small {
            display: block;
            margin-top: 4px;
            color: rgba(255, 255, 255, .78);
            font-weight: 600;
        }
        .admin-ranking-table {
            border: 1px solid #d7e2f0;
            font-size: .86rem;
        }
        .ranking-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border: 1px solid #d7e2f0;
            border-bottom: 0;
            background: #f8fbff;
            color: #20539a;
            padding: 10px 12px;
            font-weight: 800;
        }
        .ranking-title small {
            color: #64748b;
            font-weight: 700;
        }
        .admin-ranking-table thead th {
            border-top: 0;
            border-bottom: 2px solid #b9cbe2;
            background: #eef5ff;
            color: #20539a;
            text-transform: uppercase;
            font-size: .75rem;
            letter-spacing: .02em;
            white-space: nowrap;
        }
        .admin-ranking-table tbody td {
            border-color: #e2eaf5;
            vertical-align: middle;
        }
        .admin-ranking-table tbody tr:nth-child(even) {
            background: #fbfdff;
        }
        .admin-ranking-table tbody tr:hover {
            background: #f2f7ff;
        }
        .summary-badges,
        .service-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            min-width: 180px;
        }
        .summary-badge,
        .service-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid #d9e7f4;
            border-radius: 999px;
            background: #f8fbff;
            color: #20539a;
            font-size: .76rem;
            font-weight: 700;
            padding: 4px 8px 4px 10px;
            white-space: nowrap;
        }
        .summary-badge strong,
        .service-badge strong {
            display: inline-flex;
            min-width: 24px;
            height: 22px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #20539a;
            color: #ffffff;
            font-size: .72rem;
            padding: 0 7px;
        }
        .destination-badge {
            border-color: #d7eadf;
            background: #f5fff8;
            color: #177245;
        }
        .destination-badge strong {
            background: #1f9d55;
        }
        .efficiency-panel {
            border: 1px solid #d7e2f0;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
        }
        .efficiency-panel .ranking-title {
            border: 0;
            border-bottom: 1px solid #d7e2f0;
        }
        .efficiency-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            border-radius: 999px;
            background: #e8f5ee;
            color: #177245;
            font-weight: 800;
            padding: 4px 10px;
        }
        .delivery-badge {
            border-color: #f4d9a6;
            background: #fffaf0;
            color: #9a5b00;
        }
        .delivery-badge strong {
            background: #d97706;
        }
        .month-filter-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px 10px;
            max-height: 132px;
            overflow-y: auto;
            background: #ffffff;
            font-size: .82rem;
        }
    </style>
@stop

@section('js')
    <script>
        (() => {
            const form = document.getElementById('adminSummaryForm');
            const pdfButton = document.getElementById('adminSummaryPdfBtn');

            if (!form || !pdfButton) {
                return;
            }

            const syncPdfUrl = () => {
                const params = new URLSearchParams(new FormData(form));
                pdfButton.href = `${@json(route('reportes.resumen-administrativo.pdf'))}?${params.toString()}`;
            };

            form.querySelectorAll('input, select').forEach((field) => {
                field.addEventListener('change', syncPdfUrl);
                field.addEventListener('input', syncPdfUrl);
            });

            syncPdfUrl();
        })();
    </script>
@stop
