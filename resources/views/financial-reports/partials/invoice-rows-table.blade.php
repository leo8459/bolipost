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
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No se encontraron facturas de contratos para el período seleccionado.</td></tr>
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
