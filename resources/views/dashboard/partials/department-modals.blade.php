    @foreach(($rankingDepartamentos ?? collect()) as $item)
        @php($totalesModulo = $item->entregados_por_modulo ?? [])
        @php($totalesTransitoModulo = $item->transito_por_modulo ?? [])
        @php($totalesPendientesModulo = $item->pendientes_por_modulo ?? [])
        @php($transitoAgrupado = $item->transito_grupos ?? [])
        @php($pendientesAgrupado = $item->pendientes_grupos ?? [])
        <div class="modal fade" id="departamentoTransitoModal{{ $item->puesto }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title mb-0">
                            {{ $item->departamento }} - paquetes en transito
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            @foreach(['EMS', 'CONTRATOS'] as $moduloDetalle)
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">{{ $moduloDetalle }}</div>
                                        <div class="h4 mb-0 text-info">{{ \App\Support\BolivianNumber::format((int) ($totalesTransitoModulo[$moduloDetalle] ?? 0)) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="alert alert-light border">
                            <strong>Total en transito:</strong> {{ \App\Support\BolivianNumber::format((int) ($item->transito ?? 0)) }}
                            <span class="mx-2">|</span>
                            <strong>Criterio:</strong> se toma el origen del envio
                            <span class="mx-2">|</span>
                            <strong>Rango:</strong> {{ $rangoLabel }}
                            <span class="mx-2">|</span>
                            <strong>Codigos filtrados:</strong>
                            <span data-transit-filter-count>{{ \App\Support\BolivianNumber::format(collect($transitoAgrupado)->count()) }}</span>
                        </div>

                        <div class="border rounded p-3 mb-3" data-transit-filter-panel>
                            <div class="form-row align-items-end">
                                <div class="form-group col-12 col-md-2 mb-2">
                                    <label class="small text-muted mb-1">Desde</label>
                                    <input type="date" class="form-control form-control-sm" data-transit-filter-from>
                                </div>
                                <div class="form-group col-12 col-md-2 mb-2">
                                    <label class="small text-muted mb-1">Hasta</label>
                                    <input type="date" class="form-control form-control-sm" data-transit-filter-to>
                                </div>
                                <div class="form-group col-12 col-md-3 mb-2">
                                    <label class="small text-muted mb-1">Servicio o categoria</label>
                                    <select class="form-control form-control-sm" data-transit-filter-module>
                                        <option value="">Todos</option>
                                        @foreach(['EMS', 'CONTRATOS'] as $moduloDetalle)
                                            <option value="{{ $moduloDetalle }}">{{ $moduloDetalle }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-12 col-md-3 mb-2">
                                    <label class="small text-muted mb-1">Cod. especial</label>
                                    <input type="text" class="form-control form-control-sm" data-transit-filter-code placeholder="Ej: TJA0001">
                                </div>
                                <div class="form-group col-12 col-md-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-primary mr-1" data-transit-filter-search>
                                        <i class="fas fa-search mr-1"></i> Buscar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-transit-filter-clear>
                                        <i class="fas fa-eraser mr-1"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 430px; overflow:auto;">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Origen</th>
                                        <th>Cod especial</th>
                                        <th>Modulos</th>
                                        <th>Total paquetes</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transitoAgrupado as $indiceGrupo => $grupo)
                                        @php($detalleModalId = 'departamentoTransitoDetalleModal' . $item->puesto . '-' . $indiceGrupo)
                                        @php($modulosGrupo = collect($grupo['modulos'] ?? [])->filter(fn ($total) => (int) $total > 0))
                                        <tr
                                            data-transit-row
                                            data-transit-module="{{ $modulosGrupo->keys()->implode(',') }}"
                                            data-transit-date-min="{{ !empty($grupo['fecha_min'] ?? '') ? \Illuminate\Support\Carbon::parse($grupo['fecha_min'])->format('Y-m-d') : '' }}"
                                            data-transit-date-max="{{ !empty($grupo['fecha_max'] ?? '') ? \Illuminate\Support\Carbon::parse($grupo['fecha_max'])->format('Y-m-d') : '' }}"
                                            data-transit-code="{{ strtoupper(trim((string) ($grupo['cod_especial'] ?? ''))) }}"
                                        >
                                            <td>{{ $grupo['origen'] ?? $item->departamento }}</td>
                                            <td>
                                                <span
                                                    class="d-inline-block px-3 py-2 font-weight-bold"
                                                    style="background:#20539A; color:#fff; border-radius:12px; font-size:1rem; letter-spacing:.4px; box-shadow:0 6px 16px rgba(32,83,154,.2);"
                                                >
                                                    {{ $grupo['cod_especial'] ?? 'SIN CODIGO ESPECIAL' }}
                                                </span>
                                            </td>
                                            <td>{{ $grupo['modulos_label'] ?? '-' }}</td>
                                            <td class="font-weight-bold text-info">{{ \App\Support\BolivianNumber::format((int) ($grupo['total'] ?? 0)) }}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-info"
                                                    data-toggle="modal"
                                                    data-target="#{{ $detalleModalId }}"
                                                >
                                                    <i class="fas fa-list mr-1"></i> Ver paquetes
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No hay paquetes en transito para este departamento.</td>
                                        </tr>
                                    @endforelse
                                    <tr class="d-none" data-transit-empty-filter>
                                        <td colspan="5" class="text-center text-muted py-4">No hay codigos especiales en transito con los filtros seleccionados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        @foreach($transitoAgrupado as $indiceGrupo => $grupo)
            @php($detalleModalId = 'departamentoTransitoDetalleModal' . $item->puesto . '-' . $indiceGrupo)
            <div class="modal fade" id="{{ $detalleModalId }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-secondary text-white">
                            <h5 class="modal-title mb-0">
                                {{ $grupo['origen'] ?? $item->departamento }} - {{ $grupo['cod_especial'] ?? 'SIN CODIGO ESPECIAL' }}
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border">
                                <strong>Total paquetes:</strong> {{ \App\Support\BolivianNumber::format((int) ($grupo['total'] ?? 0)) }}
                                <span class="mx-2">|</span>
                                <strong>Modulos:</strong> {{ $grupo['modulos_label'] ?? '-' }}
                            </div>

                            <div class="table-responsive" style="max-height: 430px; overflow:auto;">
                                <table class="table table-sm table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Modulo</th>
                                            <th>Codigo</th>
                                            <th>Estado</th>
                                            <th>Origen</th>
                                            <th>Destino</th>
                                            <th>Destinatario</th>
                                            <th>Creado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($grupo['paquetes'] ?? []) as $detalle)
                                            @php($detalleCreadoAt = !empty($detalle['creado_at'] ?? '') ? \Illuminate\Support\Carbon::parse($detalle['creado_at']) : null)
                                            <tr>
                                                <td>{{ $detalle['modulo'] ?? '-' }}</td>
                                                <td><span class="badge badge-light">{{ $detalle['codigo'] ?? '-' }}</span></td>
                                                <td>{{ $detalle['estado'] ?? '-' }}</td>
                                                <td>{{ $detalle['origen'] ?? '-' }}</td>
                                                <td>{{ $detalle['destino'] ?? '-' }}</td>
                                                <td>{{ $detalle['destinatario'] ?? '-' }}</td>
                                                <td>{{ $detalleCreadoAt ? $detalleCreadoAt->format('d/m/Y H:i') : '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">No hay paquetes para este codigo especial.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="modal fade" id="departamentoPendientesModal{{ $item->puesto }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title mb-0">
                            {{ $item->departamento }} - paquetes pendientes
                        </h5>
                        <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            @foreach(['EMS', 'CONTRATOS'] as $moduloDetalle)
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">{{ $moduloDetalle }}</div>
                                        <div class="h4 mb-0 text-warning">{{ \App\Support\BolivianNumber::format((int) ($totalesPendientesModulo[$moduloDetalle] ?? 0)) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="alert alert-light border">
                            <strong>Total pendiente:</strong> {{ \App\Support\BolivianNumber::format((int) $item->pendientes) }}
                            <span class="mx-2">|</span>
                            <strong>En transito:</strong> no incluidos
                            <span class="mx-2">|</span>
                            <strong>Cancelados:</strong> no incluidos
                            <span class="mx-2">|</span>
                            <strong>Rango:</strong> {{ $rangoLabel }}
                            <span class="mx-2">|</span>
                            <strong>Estados filtrados:</strong>
                            <span data-pending-filter-count>{{ \App\Support\BolivianNumber::format(collect($pendientesAgrupado)->count()) }}</span>
                        </div>

                        <div class="border rounded p-3 mb-3" data-pending-filter-panel>
                            <div class="form-row align-items-end">
                                <div class="form-group col-12 col-md-2 mb-2">
                                    <label class="small text-muted mb-1">Desde</label>
                                    <input type="date" class="form-control form-control-sm" data-pending-filter-from>
                                </div>
                                <div class="form-group col-12 col-md-2 mb-2">
                                    <label class="small text-muted mb-1">Hasta</label>
                                    <input type="date" class="form-control form-control-sm" data-pending-filter-to>
                                </div>
                                <div class="form-group col-12 col-md-3 mb-2">
                                    <label class="small text-muted mb-1">Servicio o categoria</label>
                                    <select class="form-control form-control-sm" data-pending-filter-module>
                                        <option value="">Todos</option>
                                        @foreach(['EMS', 'CONTRATOS'] as $moduloDetalle)
                                            <option value="{{ $moduloDetalle }}">{{ $moduloDetalle }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-12 col-md-3 mb-2">
                                    <label class="small text-muted mb-1">Estado</label>
                                    <input type="text" class="form-control form-control-sm" data-pending-filter-state placeholder="Ej: CARTERO">
                                </div>
                                <div class="form-group col-12 col-md-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-primary mr-1" data-pending-filter-search>
                                        <i class="fas fa-search mr-1"></i> Buscar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-pending-filter-clear>
                                        <i class="fas fa-eraser mr-1"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 430px; overflow:auto;">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Modulos</th>
                                        <th>Total paquetes</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendientesAgrupado as $indiceGrupo => $grupo)
                                        @php($detalleModalId = 'departamentoPendienteDetalleModal' . $item->puesto . '-' . $indiceGrupo)
                                        @php($modulosGrupo = collect($grupo['modulos'] ?? [])->filter(fn ($total) => (int) $total > 0))
                                        <tr
                                            data-pending-row
                                            data-pending-module="{{ $modulosGrupo->keys()->implode(',') }}"
                                            data-pending-date-min="{{ !empty($grupo['fecha_min'] ?? '') ? \Illuminate\Support\Carbon::parse($grupo['fecha_min'])->format('Y-m-d') : '' }}"
                                            data-pending-date-max="{{ !empty($grupo['fecha_max'] ?? '') ? \Illuminate\Support\Carbon::parse($grupo['fecha_max'])->format('Y-m-d') : '' }}"
                                            data-pending-state="{{ strtoupper(trim((string) ($grupo['estado'] ?? ''))) }}"
                                        >
                                            <td>
                                                <span
                                                    class="d-inline-block px-3 py-2 font-weight-bold"
                                                    style="background:#f4b400; color:#1f2937; border-radius:12px; font-size:1rem; letter-spacing:.4px; box-shadow:0 6px 16px rgba(244,180,0,.2);"
                                                >
                                                    {{ $grupo['estado'] ?? 'SIN ESTADO' }}
                                                </span>
                                            </td>
                                            <td>{{ $grupo['modulos_label'] ?? '-' }}</td>
                                            <td class="font-weight-bold text-warning">{{ \App\Support\BolivianNumber::format((int) ($grupo['total'] ?? 0)) }}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-warning"
                                                    data-toggle="modal"
                                                    data-target="#{{ $detalleModalId }}"
                                                >
                                                    <i class="fas fa-list mr-1"></i> Ver paquetes
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No hay paquetes pendientes para este departamento.</td>
                                        </tr>
                                    @endforelse
                                    <tr class="d-none" data-pending-empty-filter>
                                        <td colspan="4" class="text-center text-muted py-4">No hay estados pendientes con los filtros seleccionados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        @foreach($pendientesAgrupado as $indiceGrupo => $grupo)
            @php($detalleModalId = 'departamentoPendienteDetalleModal' . $item->puesto . '-' . $indiceGrupo)
            <div class="modal fade" id="{{ $detalleModalId }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title mb-0">
                                {{ $item->departamento }} - estado {{ $grupo['estado'] ?? 'SIN ESTADO' }}
                            </h5>
                            <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border">
                                <strong>Total paquetes:</strong> {{ \App\Support\BolivianNumber::format((int) ($grupo['total'] ?? 0)) }}
                                <span class="mx-2">|</span>
                                <strong>Modulos:</strong> {{ $grupo['modulos_label'] ?? '-' }}
                            </div>

                            <div class="table-responsive" style="max-height: 430px; overflow:auto;">
                                <table class="table table-sm table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Modulo</th>
                                            <th>Codigo</th>
                                            <th>Estado</th>
                                            <th>Origen</th>
                                            <th>Destino</th>
                                            <th>Destinatario</th>
                                            <th>Creado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($grupo['paquetes'] ?? []) as $detalle)
                                            @php($detalleCreadoAt = !empty($detalle['creado_at'] ?? '') ? \Illuminate\Support\Carbon::parse($detalle['creado_at']) : null)
                                            <tr>
                                                <td>{{ $detalle['modulo'] ?? '-' }}</td>
                                                <td><span class="badge badge-light">{{ $detalle['codigo'] ?? '-' }}</span></td>
                                                <td>{{ $detalle['estado'] ?? '-' }}</td>
                                                <td>{{ $detalle['origen'] ?? '-' }}</td>
                                                <td>{{ $detalle['destino'] ?? '-' }}</td>
                                                <td>{{ $detalle['destinatario'] ?? '-' }}</td>
                                                <td>{{ $detalleCreadoAt ? $detalleCreadoAt->format('d/m/Y H:i') : '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">No hay paquetes para este estado.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="modal fade" id="departamentoDetalleModal{{ $item->puesto }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title mb-0">
                            {{ $item->departamento }} - paquetes entregados
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            @foreach(['EMS', 'CONTRATOS'] as $moduloDetalle)
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">{{ $moduloDetalle }}</div>
                                        <div class="h4 mb-0 text-primary">{{ \App\Support\BolivianNumber::format((int) ($totalesModulo[$moduloDetalle] ?? 0)) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="alert alert-light border">
                            <strong>Total entregado:</strong> {{ \App\Support\BolivianNumber::format((int) $item->entregados) }}
                            <span class="mx-2">|</span>
                            <strong>Mejor entregador:</strong> {{ $item->top_entregador }}
                            <span class="mx-2">|</span>
                            <strong>Cumplimiento:</strong> {{ \App\Support\BolivianNumber::format((float) $item->cumplimiento, 1) }}%
                        </div>

                        <div class="table-responsive" style="max-height: 430px; overflow:auto;">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Modulo</th>
                                        <th>Codigo</th>
                                        <th>Entregado por</th>
                                        <th>Fecha entrega</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($item->entregados_detalle ?? []) as $detalle)
                                        <tr>
                                            <td>{{ $detalle['modulo'] ?? '-' }}</td>
                                            <td><span class="badge badge-light">{{ $detalle['codigo'] ?? '-' }}</span></td>
                                            <td>{{ $detalle['usuario'] ?? '-' }}</td>
                                            <td>
                                                {{ !empty($detalle['entregado_at'] ?? '') ? \Illuminate\Support\Carbon::parse($detalle['entregado_at'])->format('d/m/Y H:i') : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No hay entregas registradas para este departamento.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a
                            href="{{ route('dashboard.export.pdf', array_merge(request()->query(), ['departamento_reporte' => $item->departamento])) }}"
                            class="btn btn-danger"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="fas fa-file-pdf mr-1"></i> Reporte PDF
                        </a>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
