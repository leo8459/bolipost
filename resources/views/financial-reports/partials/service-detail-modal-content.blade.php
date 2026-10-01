@if($errors->isNotEmpty())
    <div class="alert alert-warning" role="alert">
        <strong>Algunas consultas no pudieron cargarse.</strong>
        <ul class="mb-0 mt-2 pl-4">
            @foreach($errors as $reportError)
                <li>{{ $reportError }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="flow-detail-summary" tabindex="-1" data-flow-detail-start aria-label="Resumen del servicio">
    @if($showCollectedOnly)
        <div><span>Movimientos cobrados</span><strong>{{ \App\Support\BolivianNumber::format((float) ($service['cantidadMovimientosCobrados'] ?? 0)) }}</strong></div>
        <div><span>Ventas con cobro</span><strong>{{ \App\Support\BolivianNumber::format((float) ($service['cantidadVentasCobradas'] ?? 0)) }}</strong></div>
        <div><span>Importe cobrado</span><strong>Bs {{ \App\Support\BolivianNumber::format((float) ($service['totalMontoCobrado'] ?? 0), 2) }}</strong></div>
    @else
        <div><span>Ventas realizadas</span><strong>{{ \App\Support\BolivianNumber::format((float) ($service['cantidadVentas'] ?? 0)) }}</strong></div>
        <div><span>Lineas de detalle</span><strong>{{ \App\Support\BolivianNumber::format((float) ($service['cantidadDetalles'] ?? 0)) }}</strong></div>
        <div><span>Cantidad de paquetes</span><strong>{{ \App\Support\BolivianNumber::format((float) ($service['totalCantidad'] ?? 0), 2) }}</strong></div>
        <div><span>Ingresos de ventanilla</span><strong>Bs {{ \App\Support\BolivianNumber::format((float) ($service['totalMonto'] ?? 0), 2) }}</strong></div>
    @endif
    @if($canManageReceivables && ! $showCollectedOnly)
        <div><span>Cobros realizados</span><strong>Bs {{ \App\Support\BolivianNumber::format((float) ($service['totalMontoCobrado'] ?? 0), 2) }}</strong></div>
        <div><span>Pendiente por cobrar</span><strong>Bs {{ \App\Support\BolivianNumber::format((float) ($service['totalMontoPendiente'] ?? $service['totalMonto'] ?? 0), 2) }}</strong></div>
    @endif
</div>

@if(($cashierFlowContext['department'] ?? '') !== '' && request()->boolean('flujo_cajero'))
    <div class="alert alert-info py-2">Para registrar o devolver cobros, quita el filtro de departamento: el detalle muestra movimientos de todas las regiones.</div>
@endif
@if($showCollectedOnly)
    <div class="alert alert-success py-2"><i class="fas fa-check-circle mr-1"></i>Solo se muestran movimientos cobrados. Usa <strong>Devolver a por cobrar</strong> para quitar uno del total.</div>
@endif

<form class="flow-detail-search-form" data-flow-detail-search-form role="search">
    <label for="flow-detail-search">{{ $showCollectedOnly ? 'Buscar en los movimientos cobrados' : 'Buscar en todos los movimientos' }}</label>
    <div class="input-group input-group-lg">
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
        </div>
        <input type="search" id="flow-detail-search" class="form-control" data-flow-detail-search value="{{ $searchTerm }}" maxlength="180" placeholder="Venta, detalle, descripción, código..." aria-describedby="flow-detail-search-help">
        <div class="input-group-append">
            <button type="button" class="btn btn-outline-secondary" data-flow-detail-clear>Limpiar</button>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
    </div>
    <div class="flow-detail-search-help" id="flow-detail-search-help">Busca por servicio, número de venta o detalle, descripción, código de orden, código de seguimiento, fecha o monto.</div>
</form>

<div class="flow-detail-list-heading">
    <h5 class="mb-0">{{ $showCollectedOnly ? 'Movimientos cobrados' : 'Movimientos individuales' }}</h5>
    <span>{{ \App\Support\BolivianNumber::format($rows->firstItem() ?? 0) }}–{{ \App\Support\BolivianNumber::format($rows->lastItem() ?? 0) }} de {{ \App\Support\BolivianNumber::format($rows->total()) }}</span>
</div>

@php($monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'])
@forelse($rows as $row)
    <article class="flow-detail-item" aria-label="Movimiento {{ $rows->firstItem() + $loop->index }}">
        <div class="flow-detail-item-heading">
            <div>
                <span class="flow-detail-label">Servicio</span>
                <strong>{{ $row['_servicio'] ?? '-' }}</strong>
            </div>
            <strong class="flow-detail-amount">Bs {{ \App\Support\BolivianNumber::format((float) ($row['totalLinea'] ?? 0), 2) }}</strong>
        </div>
        <div class="flow-detail-fields">
            <div><span class="flow-detail-label">{{ $showCollectedOnly ? 'Fecha de cobro' : 'Fecha' }}</span><strong>{{ $showCollectedOnly ? ($row['_fechaCobro'] ?? '-') : ($row['fecha'] ?? '-') }}</strong></div>
            @if($showCollectedOnly)
                <div><span class="flow-detail-label">Fecha de factura</span><strong>{{ $row['fecha'] ?? '-' }}</strong></div>
                <div><span class="flow-detail-label">Facturó</span><strong>{{ $row['_facturadoPor'] ?: 'Sin dato' }}</strong></div>
                <div><span class="flow-detail-label">Cantidad de paquetes</span><strong>{{ \App\Support\BolivianNumber::format((float) ($row['_cantidadPaquetesCobrados'] ?? 0), 2) }}</strong></div>
            @endif
            <div><span class="flow-detail-label">Mes</span><strong>{{ $monthNames[$row['_mes'] ?? 0] ?? '-' }}</strong></div>
            <div><span class="flow-detail-label">Venta</span><strong>{{ $row['ventaId'] ?? '-' }}</strong></div>
            <div><span class="flow-detail-label">Detalle</span><strong>{{ $row['detalleId'] ?? '-' }}</strong></div>
            <div><span class="flow-detail-label">Código de orden</span><strong>{{ $row['codigoOrden'] ?? '-' }}</strong></div>
            <div><span class="flow-detail-label">Código de seguimiento</span><strong>{{ $row['codigoSeguimiento'] ?? '-' }}</strong></div>
        </div>
        <div class="flow-detail-description">
            <span class="flow-detail-label">Descripción</span>
            <p class="mb-0">{{ $row['descripcion'] ?? '-' }}</p>
        </div>
        @if($canManageReceivables)
            <div class="d-flex align-items-center justify-content-between flex-wrap mt-3 pt-3 border-top">
                @if($row['_cobroRealizado'] ?? false)
                    <span class="badge badge-success py-2 px-3 mb-2"><i class="fas fa-check mr-1"></i> Cobro realizado</span>
                    <form method="POST" action="{{ route('dashboard.financiera.flujo-cajero.movimiento.devolver-por-cobrar') }}" class="mb-2" data-confirm-receivable-payment="return">
                        @csrf
                        <input type="hidden" name="movement_key" value="{{ $row['_movementKey'] }}">
                        <input type="hidden" name="servicio_cobro" value="{{ $row['_servicio'] }}">
                        <input type="hidden" name="mes_cobro" value="{{ $row['_mes'] }}">
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        @foreach($cashierFlowContext['months'] ?: [$row['_mes']] as $filterMonth)
                            <input type="hidden" name="filtro_meses[]" value="{{ $filterMonth }}">
                        @endforeach
                        <input type="hidden" name="filtro_anio" value="{{ $cashierFlowContext['year'] }}">
                        <input type="hidden" name="filtro_limite" value="{{ $cashierFlowContext['limit'] }}">
                        <input type="hidden" name="filtro_departamento" value="{{ $cashierFlowContext['department'] }}">
                        @foreach($cashierFlowContext['services'] as $filterService)
                            <input type="hidden" name="filtro_servicios[]" value="{{ $filterService }}">
                        @endforeach
                        <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-undo-alt mr-1"></i> Devolver a por cobrar</button>
                    </form>
                @elseif(! $showCollectedOnly)
                    <span class="badge badge-warning py-2 px-3 mb-2">Por cobrar</span>
                    <form method="POST" action="{{ route('dashboard.financiera.flujo-cajero.movimiento.cobro-realizado') }}" class="mb-2" data-confirm-receivable-payment="collect">
                        @csrf
                        <input type="hidden" name="movement_key" value="{{ $row['_movementKey'] }}">
                        <input type="hidden" name="servicio_cobro" value="{{ $row['_servicio'] }}">
                        <input type="hidden" name="mes_cobro" value="{{ $row['_mes'] }}">
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        @foreach($cashierFlowContext['months'] ?: [$row['_mes']] as $filterMonth)
                            <input type="hidden" name="filtro_meses[]" value="{{ $filterMonth }}">
                        @endforeach
                        <input type="hidden" name="filtro_anio" value="{{ $cashierFlowContext['year'] }}">
                        <input type="hidden" name="filtro_limite" value="{{ $cashierFlowContext['limit'] }}">
                        <input type="hidden" name="filtro_departamento" value="{{ $cashierFlowContext['department'] }}">
                        @foreach($cashierFlowContext['services'] as $filterService)
                            <input type="hidden" name="filtro_servicios[]" value="{{ $filterService }}">
                        @endforeach
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-hand-holding-usd mr-1"></i> Cobro realizado</button>
                    </form>
                @endif
            </div>
        @endif
    </article>
@empty
    <div class="flow-detail-empty">
        @if($searchTerm !== '')
            No hay movimientos que coincidan con la búsqueda "{{ $searchTerm }}".
        @elseif($showCollectedOnly)
            Todavía no hay movimientos cobrados para los servicios y meses seleccionados.
        @else
            No se encontraron movimientos para los servicios y meses seleccionados.
        @endif
    </div>
@endforelse

@if($rows->hasPages())
    <nav class="flow-detail-pagination" aria-label="Páginas de movimientos">
        {{ $rows->onEachSide(1)->links() }}
    </nav>
@endif
