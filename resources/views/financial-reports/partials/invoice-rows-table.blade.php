<div class="card card-outline card-secondary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong><i class="fas fa-table mr-1"></i> {{ $tableTitle ?? 'Detalle facturado' }}</strong>
            @if(filled($tableHelp ?? null))
                <div class="text-muted small">{{ $tableHelp }}</div>
            @endif
        </div>
        <div class="text-right">
            @if(isset($salesCount))
                <span class="badge badge-success mr-1">{{ number_format((int) $salesCount) }} ventas</span>
            @endif
            <span class="badge badge-primary">{{ number_format($rows->total()) }} registros</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0 financial-table">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Servicio</th>
                        <th>Mes</th>
                        <th>Venta</th>
                        <th>Detalle</th>
                        <th>Descripción</th>
                        <th>Código de orden</th>
                        <th>Código de seguimiento</th>
                        <th>Fecha</th>
                        <th class="text-right">Total línea</th>
                        @if($showReceivableActions ?? false)<th class="text-center">Por cobrar</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="font-weight-bold service-cell">{{ $row['_servicio'] ?? '-' }}</td>
                            <td>{{ [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'][$row['_mes'] ?? 0] ?? '-' }}</td>
                            <td>{{ $row['ventaId'] ?? '-' }}</td>
                            <td>{{ $row['detalleId'] ?? '-' }}</td>
                            <td class="description-cell">{{ $row['descripcion'] ?? '-' }}</td>
                            <td class="code-cell">{{ $row['codigoOrden'] ?? '-' }}</td>
                            <td class="font-weight-bold code-cell">{{ $row['codigoSeguimiento'] ?? '-' }}</td>
                            <td class="text-nowrap">{{ $row['fecha'] ?? '-' }}</td>
                            <td class="text-right font-weight-bold">Bs {{ number_format((float) ($row['totalLinea'] ?? 0), 2) }}</td>
                            @if($showReceivableActions ?? false)
                                @php($asociacion = $facturasAsociadas->get((string) ($row['ventaId'] ?? '')))
                                <td class="text-center text-nowrap">
                                    @if($asociacion)
                                        <span class="badge badge-success d-block mb-1"><i class="fas fa-link mr-1"></i>Asociada</span>
                                        <small class="text-muted">{{ $asociacion->empresa?->nombre }}</small>
                                        @if(filled($asociacion->factura_razon_social))
                                            <div class="text-left border-top mt-2 pt-2" style="min-width:260px;white-space:normal">
                                                <small class="d-block"><strong>Razón social:</strong> {{ $asociacion->factura_razon_social }}</small>
                                                <small class="d-block"><strong>Código cliente:</strong> {{ $asociacion->factura_codigo_cliente ?: '-' }}</small>
                                                <small class="d-block"><strong>NIT/CI/CEX:</strong> {{ $asociacion->factura_numero_documento ?: '-' }}</small>
                                            </div>
                                        @endif
                                        <a href="{{ route('dashboard.conciliacion.conciliaciones.factura-pdf', $asociacion) }}" class="btn btn-xs btn-outline-danger d-block mt-1">
                                            <i class="fas fa-file-pdf mr-1"></i> PDF
                                        </a>
                                    @else
                                        @can('feature.conciliacion.conciliaciones.por-cobrar')
                                        <button type="button" class="btn btn-sm btn-warning receivable-trigger"
                                            data-toggle="modal" data-target="#porCobrarModal"
                                            data-venta-id="{{ $row['ventaId'] ?? '' }}"
                                            data-orden="{{ $row['codigoOrden'] ?? '' }}"
                                            data-descripcion="{{ $row['descripcion'] ?? '' }}"
                                            data-monto="{{ number_format((float) ($row['totalLinea'] ?? 0), 2, '.', '') }}"
                                            data-facturado-mes="{{ $row['_mes'] ?? $mes }}"
                                            data-mes-sugerido="{{ $row['_mes_servicio'] ?? $row['_mes'] ?? $mes }}">
                                            <i class="fas fa-link mr-1"></i> Asociar
                                        </button>
                                        @endcan
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($showReceivableActions ?? false) ? 11 : 10 }}" class="text-center text-muted py-4">No se encontraron facturas de contratos para el período seleccionado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($rows->hasPages())
        <div class="card-footer bg-white">
            {{ $rows->onEachSide(1)->links() }}
        </div>
    @endif
</div>

@if($showReceivableActions ?? false)
    <div class="modal fade" id="porCobrarModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <form method="POST" action="{{ route('dashboard.conciliacion.conciliaciones.por-cobrar') }}" class="modal-content">
                @csrf
                <input type="hidden" name="factura_venta_id" id="porCobrarVentaId">
                <input type="hidden" name="facturado_anio" value="{{ $anio }}">
                <input type="hidden" name="facturado_mes" id="porCobrarFacturadoMes">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-hand-holding-usd mr-2"></i>Asociar factura a Por cobrar</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border">
                        <div><strong id="porCobrarOrden"></strong> · Venta <span id="porCobrarVenta"></span></div>
                        <div class="small text-muted" id="porCobrarDescripcion"></div>
                        <div class="font-weight-bold text-success mt-1">Bs <span id="porCobrarMonto"></span></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="porCobrarEmpresa">Empresa</label>
                            <select id="porCobrarEmpresa" name="empresa_id" class="form-control" required>
                                <option value="">Selecciona una empresa...</option>
                                @foreach($empresas as $empresa)
                                    <option value="{{ $empresa->id }}">{{ $empresa->nombre }}{{ filled($empresa->codigo_cliente) ? ' · '.$empresa->codigo_cliente : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="porCobrarMes">Mes conciliado</label>
                            <select id="porCobrarMes" name="mes" class="form-control" required>
                                @foreach([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $numero => $nombre)
                                    <option value="{{ $numero }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="porCobrarAnio">Año conciliado</label>
                            <input id="porCobrarAnio" type="number" name="anio" value="{{ $anio }}" min="2000" max="{{ now()->year + 1 }}" class="form-control" required>
                        </div>
                    </div>
                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>La empresa debe tener previamente su documento de conciliación cargado para el mes elegido.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-link mr-1"></i> Asociar por cobrar</button>
                </div>
            </form>
        </div>
    </div>

    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.receivable-trigger').forEach(function (button) {
                    button.addEventListener('click', function () {
                        document.getElementById('porCobrarVentaId').value = this.dataset.ventaId;
                        document.getElementById('porCobrarFacturadoMes').value = this.dataset.facturadoMes;
                        document.getElementById('porCobrarVenta').textContent = this.dataset.ventaId;
                        document.getElementById('porCobrarOrden').textContent = this.dataset.orden || 'Sin código de orden';
                        document.getElementById('porCobrarDescripcion').textContent = this.dataset.descripcion;
                        document.getElementById('porCobrarMonto').textContent = this.dataset.monto;
                        document.getElementById('porCobrarMes').value = this.dataset.mesSugerido;
                        document.getElementById('porCobrarEmpresa').value = '';
                    });
                });
            });
        </script>
    @endpush
@endif
