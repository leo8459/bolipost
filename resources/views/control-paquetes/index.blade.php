@extends('adminlte::page')

@section('title', 'Control de paquetes · '.$serviceLabel)

@section('content_header')
    <div class="delivery-control-heading d-flex flex-column flex-lg-row align-items-lg-center justify-content-between">
        <div class="d-flex align-items-center">
            <span class="delivery-control-heading-icon"><i class="{{ $serviceIcon }}"></i></span>
            <div>
                <h1 class="mb-1">Control de paquetes · {{ $serviceLabel }}</h1>
                <p class="text-muted mb-0">
                    Consulta y seguimiento de envíos ·
                    @if ($departmentScope === null)
                        Todos los departamentos
                    @else
                        Departamento: {{ $departmentScope !== [] ? implode(', ', $departmentScope) : 'sin asignar' }}
                    @endif
                </p>
            </div>
        </div>
        <div class="delivery-control-heading-actions">
            <span class="delivery-control-updated"><i class="far fa-clock mr-1"></i>Actualizado {{ now()->timezone('America/La_Paz')->format('d/m/Y H:i') }}</span>
        </div>
    </div>
@stop

@section('content')
    @include('control-paquetes.partials.styles')

    @php
        $cards = [
            ['total', 'Registros', 'blue', 'fas fa-file-alt'],
            ['packages', 'Paquetes', 'blue', 'fas fa-boxes'],
            ['transit', 'En tránsito', 'amber', 'fas fa-truck'],
            ['returned', 'Devueltos', 'red', 'fas fa-undo-alt'],
            ['delivered', 'Entregados', 'green', 'fas fa-check-circle'],
            ['not_delivered', 'No entregados', 'amber', 'fas fa-box-open'],
        ];
        $stateData = static function (string $state): array {
            $normalized = mb_strtolower(\Illuminate\Support\Str::ascii(trim($state)));
            if ($normalized === 'entregado') return ['badge-paid', 'fas fa-check-circle'];
            if (str_contains($normalized, 'devol') || str_contains($normalized, 'devuelt') || str_contains($normalized, 'rechaz') || str_contains($normalized, 'anulad') || str_contains($normalized, 'cancelad')) return ['badge-error', 'fas fa-exclamation-triangle'];
            if (str_contains($normalized, 'transit') || str_contains($normalized, 'distrib') || str_contains($normalized, 'cartero') || str_contains($normalized, 'en entrega')) return ['badge-transit', 'fas fa-truck'];
            if (str_contains($normalized, 'almacen') || str_contains($normalized, 'prepar')) return ['badge-preparing', 'fas fa-cube'];
            return ['badge-muted', 'fas fa-box'];
        };
        $formatDate = static fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y H:i') : '—';
    @endphp

    <div class="delivery-control-wrap">
        <div class="row mb-3">
            @foreach ($cards as [$key, $label, $color, $icon])
                <div class="col-6 col-xl-4 mb-2">
                    <div class="delivery-control-kpi delivery-control-kpi--{{ $color }}">
                        <span class="delivery-control-kpi-icon"><i class="{{ $icon }}"></i></span>
                        <div><div class="delivery-control-kpi-label">{{ $label }}</div><div class="delivery-control-kpi-value">{{ number_format($summary[$key]) }}</div></div>
                        <i class="fas fa-chevron-right delivery-control-kpi-arrow"></i>
                    </div>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route($routeName) }}" class="delivery-control-filters mb-3">
            <div class="form-row align-items-end">
                <div class="form-group col-12 col-lg-4 mb-2">
                    <label for="package-control-q">Buscar</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search"></i></span></div>
                        <input id="package-control-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Código, cliente, remitente o destinatario">
                    </div>
                </div>
                <div class="form-group col-6 col-lg-2 mb-2">
                    <label for="package-control-state">Estado del envío</label>
                    <select id="package-control-state" name="estado" class="form-control">
                        <option value="">Todos</option>
                        @foreach ($estados as $estado)
                            <option value="{{ $estado }}" @selected(mb_strtoupper($filters['estado']) === mb_strtoupper($estado))>{{ $estado }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-6 col-lg-2 mb-2">
                    <label for="package-control-delivery">Entrega</label>
                    <select id="package-control-delivery" name="entrega" class="form-control">
                        <option value="todos" @selected($filters['entrega'] === 'todos')>Todos</option>
                        <option value="entregados" @selected($filters['entrega'] === 'entregados')>Entregados</option>
                        <option value="no_entregados" @selected($filters['entrega'] === 'no_entregados')>No entregados</option>
                    </select>
                </div>
                <div class="form-group col-6 col-lg-1 mb-2">
                    <label for="package-control-from">Desde</label>
                    <input id="package-control-from" type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] }}" class="form-control">
                </div>
                <div class="form-group col-6 col-lg-1 mb-2">
                    <label for="package-control-to">Hasta</label>
                    <input id="package-control-to" type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] }}" class="form-control">
                </div>
                <div class="form-group col-12 col-lg-2 mb-2 d-flex">
                    <button type="submit" class="btn btn-warning flex-fill mr-2"><i class="fas fa-filter mr-1"></i>Buscar</button>
                    <a href="{{ route($routeName) }}" class="btn delivery-control-clear" title="Limpiar búsqueda y filtros" aria-label="Limpiar búsqueda y filtros"><i class="fas fa-undo"></i></a>
                </div>
            </div>
        </form>

        <div class="delivery-control-table-card">
            <div class="delivery-control-card-heading">
                <div>
                    <strong><i class="{{ $serviceIcon }} text-primary mr-2"></i>Envíos {{ $serviceLabel }}</strong>
                    <p>Consulta el estado y los datos de cada paquete.</p>
                </div>
                <span class="badge badge-light border">{{ number_format($paquetes->total()) }} registros</span>
            </div>
            @if ($paquetes->isEmpty())
                <div class="delivery-control-empty">
                    <div><i class="fas fa-box-open"></i></div>
                    <strong>No hay paquetes que coincidan con estos filtros.</strong>
                    <div class="small mt-1">Prueba con otro texto o limpia los filtros.</div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table delivery-control-table">
                        <thead>
                            <tr>
                                <th>Envío</th>
                                <th>{{ $service === 'contratos' ? 'Empresa / cliente' : 'Remitente' }}</th>
                                <th>Ruta</th>
                                <th>Destinatario</th>
                                <th>Paquetes</th>
                                <th>Estado del envío</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paquetes as $paquete)
                                @php
                                    [$stateClass, $stateIcon] = $stateData($paquete->estado_nombre);
                                    $amount = 'Bs '.\App\Support\BolivianNumber::format((float) ($paquete->precio ?? 0), 2, '.', '');
                                    $details = [
                                        'code' => $paquete->codigo ?: 'Sin código',
                                        'client' => $paquete->cliente ?: 'Sin dato',
                                        'client-code' => $paquete->cliente_codigo ?: '—',
                                        'created' => $formatDate($paquete->created_at),
                                        'service' => $serviceLabel,
                                        'route' => ($paquete->origen ?: '—').' → '.($paquete->destino ?: '—'),
                                        'sender' => $paquete->remitente ?: '—',
                                        'sender-phone' => $paquete->telefono_remitente ?: '—',
                                        'recipient' => $paquete->destinatario ?: '—',
                                        'recipient-phone' => $paquete->telefono_destinatario ?: '—',
                                        'address' => $paquete->direccion ?: '—',
                                        'content' => $paquete->contenido ?: '—',
                                        'quantity' => number_format((int) $paquete->cantidad),
                                        'weight' => \App\Support\BolivianNumber::format((float) ($paquete->peso ?? 0), 3, '.', '').' kg',
                                        'amount' => $amount,
                                        'state' => $paquete->estado_nombre,
                                        'pickup' => $formatDate($paquete->fecha_recojo),
                                        'parent-code' => $paquete->codigo_madre ?: '—',
                                        'special-code' => $paquete->cod_especial ?: '—',
                                        'observation' => $paquete->observacion ?: '—',
                                    ];
                                @endphp
                                <tr>
                                    <td>
                                        <span class="delivery-control-code">{{ $paquete->codigo ?: 'Sin código' }}</span>
                                        <span class="delivery-control-secondary">{{ $formatDate($paquete->created_at) }}</span>
                                    </td>
                                    <td><strong>{{ $paquete->cliente ?: 'Sin dato' }}</strong><span class="delivery-control-secondary">{{ $paquete->cliente_codigo ?: $paquete->telefono_remitente ?: '—' }}</span></td>
                                    <td class="delivery-control-route">{{ $paquete->origen ?: '—' }}<i class="fas fa-long-arrow-alt-right"></i>{{ $paquete->destino ?: '—' }}</td>
                                    <td><strong>{{ $paquete->destinatario ?: 'Sin dato' }}</strong><span class="delivery-control-secondary">{{ $paquete->telefono_destinatario ?: '—' }}</span></td>
                                    <td><strong>{{ number_format((int) $paquete->cantidad) }}</strong><span class="delivery-control-secondary">{{ $amount }}</span></td>
                                    <td><span class="delivery-control-badge {{ $stateClass }}"><i class="{{ $stateIcon }}"></i>{{ $paquete->estado_nombre }}</span></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary delivery-control-view-action" data-toggle="modal" data-target="#packageControlDetails" data-details="{{ json_encode($details, JSON_UNESCAPED_UNICODE) }}">
                                            <i class="fas fa-eye mr-1"></i>Ver
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2 border-top d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <small class="text-muted mb-2 mb-md-0">Mostrando {{ $paquetes->firstItem() }}–{{ $paquetes->lastItem() }} de {{ $paquetes->total() }} registros.</small>
                    {{ $paquetes->links() }}
                </div>
            @endif
        </div>

        <div class="modal fade delivery-control-modal" id="packageControlDetails" tabindex="-1" role="dialog" aria-labelledby="packageControlDetailsTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="packageControlDetailsTitle">Detalle del envío {{ $serviceLabel }}</h5>
                            <div class="delivery-control-secondary" data-detail="code">—</div>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="delivery-control-detail"><span>{{ $service === 'contratos' ? 'Empresa / cliente' : 'Cliente' }}</span><strong data-detail="client">—</strong><small data-detail="client-code"></small></div>
                        <div class="delivery-control-detail"><span>Fecha de registro</span><strong data-detail="created">—</strong></div>
                        <div class="delivery-control-detail"><span>Servicio</span><strong data-detail="service">—</strong></div>
                        <div class="delivery-control-detail"><span>Ruta</span><strong data-detail="route">—</strong></div>
                        <div class="delivery-control-detail"><span>Remitente</span><strong data-detail="sender">—</strong><small data-detail="sender-phone"></small></div>
                        <div class="delivery-control-detail"><span>Destinatario</span><strong data-detail="recipient">—</strong><small data-detail="recipient-phone"></small></div>
                        <div class="delivery-control-detail"><span>Dirección de destino</span><strong data-detail="address">—</strong></div>
                        <div class="delivery-control-detail"><span>Contenido</span><strong data-detail="content">—</strong></div>
                        <div class="delivery-control-detail"><span>Paquetes</span><strong data-detail="quantity">—</strong></div>
                        <div class="delivery-control-detail"><span>Peso</span><strong data-detail="weight">—</strong></div>
                        <div class="delivery-control-detail"><span>Importe</span><strong data-detail="amount">—</strong></div>
                        <div class="delivery-control-detail"><span>Estado del envío</span><strong data-detail="state">—</strong></div>
                        @if ($service === 'contratos')
                            <div class="delivery-control-detail"><span>Fecha de recojo</span><strong data-detail="pickup">—</strong></div>
                            <div class="delivery-control-detail"><span>Código madre</span><strong data-detail="parent-code">—</strong></div>
                        @endif
                        <div class="delivery-control-detail"><span>Código especial</span><strong data-detail="special-code">—</strong></div>
                        <div class="delivery-control-detail"><span>Observación</span><strong data-detail="observation">—</strong></div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        (function ($) {
            $('#packageControlDetails').on('show.bs.modal', function (event) {
                var details = JSON.parse($(event.relatedTarget).attr('data-details') || '{}');
                $(this).find('[data-detail]').each(function () {
                    $(this).text(details[$(this).attr('data-detail')] || '—');
                });
            });
        })(jQuery);
    </script>
@stop
