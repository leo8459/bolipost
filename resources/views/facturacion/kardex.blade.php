@extends('adminlte::page')

@section('title', 'Kardex de facturacion')

@section('content_header')
@stop

@section('content')
    @php
        $baseParams = [
            'q' => $filters['q'] ?? '',
            'estado' => $filters['estado'] ?? 'all',
            'estado_emision' => $filters['estado_emision'] ?? 'all',
            'from' => $filters['from'] ?? now()->toDateString(),
            'to' => $filters['to'] ?? now()->toDateString(),
            'per_page' => $filters['per_page'] ?? 20,
        ];
    @endphp

    <div class="kardex-page">
        @if(!empty($kardexError))
            <div class="alert alert-warning">{{ $kardexError }}</div>
        @endif

        <form method="GET" action="{{ route('mis-ventas.kardex') }}" id="kardexReportForm" class="card kardex-filter-card">
            <input type="hidden" name="kardex_filters" value="1">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3"><label for="from">Desde</label><input type="date" id="from" name="from" value="{{ $baseParams['from'] }}" class="form-control"></div>
                    <div class="col-lg-3 col-md-6 mb-3"><label for="to">Hasta</label><input type="date" id="to" name="to" value="{{ $baseParams['to'] }}" class="form-control"></div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="estado_emision">Estado factura</label>
                        <select id="estado_emision" name="estado_emision" class="form-control">
                            @foreach(['all' => 'Todos', 'FACTURADA' => 'Facturada', 'PENDIENTE' => 'Pendiente', 'ANULADA' => 'Anulada', 'RECHAZADA' => 'Rechazada', 'ERROR' => 'Error'] as $value => $label)
                                <option value="{{ $value }}" @selected($baseParams['estado_emision'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3"><label for="q">Buscar</label><input type="search" id="q" name="q" value="{{ $baseParams['q'] }}" class="form-control" placeholder="Factura, guia, cliente"></div>
                </div>

                <div class="kardex-option-panel compact">
                    @if($availableOrigins->isNotEmpty())
                        <div class="kardex-filter-group" data-check-group>
                            <div class="kardex-filter-title"><span><i class="fas fa-map-marker-alt mr-1"></i>Regional</span><div class="kardex-mini-actions"><button type="button" data-check-all>Todos</button><button type="button" data-uncheck-all>Ninguno</button></div></div>
                            <div class="kardex-chip-list">
                                @foreach($availableOrigins as $origin)
                                    <label class="kardex-chip"><input type="checkbox" name="origenes[]" value="{{ $origin }}" @checked($selectedOrigins->contains($origin))><span>{{ $origin }}</span></label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($availableServices->isNotEmpty())
                        <div class="kardex-filter-group" data-check-group>
                            <div class="kardex-filter-title"><span><i class="fas fa-layer-group mr-1"></i>Servicios</span><div class="kardex-mini-actions"><button type="button" data-check-all>Todos</button><button type="button" data-uncheck-all>Ninguno</button></div></div>
                            <div class="kardex-chip-list kardex-chip-list--services">
                                @foreach($availableServices as $serviceName)
                                    <label class="kardex-chip"><input type="checkbox" name="servicios[]" value="{{ $serviceName }}" @checked($selectedServices->contains($serviceName))><span>{{ $serviceName }}</span></label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                    <div class="kardex-downloads"><button type="submit" formaction="{{ route('mis-ventas.kardex.export.excel') }}" class="btn btn-success"><i class="fas fa-file-excel mr-1"></i> Descargar Excel</button> <button type="submit" formaction="{{ route('mis-ventas.kardex.export.pdf') }}" class="btn btn-danger"><i class="fas fa-file-pdf mr-1"></i> Descargar PDF</button></div>
                    <div class="mt-2 mt-md-0"><a href="{{ route('mis-ventas.kardex') }}" class="btn btn-outline-secondary">Limpiar</a> <button type="submit" formaction="{{ route('mis-ventas.kardex') }}" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Filtrar</button></div>
                </div>
            </div>
        </form>

        <div class="row kardex-summary-row">
            <div class="col-md-3 col-6 mb-3"><div class="kardex-summary-card"><span>Filas visibles</span><strong>{{ number_format($visibleRows->count()) }}</strong></div></div>
            <div class="col-md-3 col-6 mb-3"><div class="kardex-summary-card"><span>Peso total</span><strong>{{ number_format($totalPeso, 3, ',', '.') }} kg</strong></div></div>
            <div class="col-md-3 col-6 mb-3"><div class="kardex-summary-card"><span>Anuladas</span><strong class="text-danger">{{ number_format($totalAnuladas) }}</strong></div></div>
            <div class="col-md-3 col-6 mb-3"><div class="kardex-summary-card"><span>Total</span><strong>Bs {{ number_format($totalImporte, 2, ',', '.') }}</strong></div></div>
        </div>

        <div class="card kardex-report-card"><div class="card-body p-0"><div class="table-responsive kardex-table-wrap"><table class="table table-sm table-hover kardex-table mb-0">
            <thead><tr><th>N&deg;</th><th>FECHA</th><th>CANTIDAD</th><th>REGIONAL</th><th>TIPO DE CORRESPONDENCIA</th><th>CODIGO DE ENVIO</th><th>PESO</th><th>PAIS/CIUDAD DE DESTINO</th><th>N&deg; FACTURA</th><th>IMPORTE</th></tr></thead>
            <tbody>
                @forelse($visibleRows as $row)
                    <tr class="{{ data_get($row, 'es_anulada') ? 'kardex-table__row-annulled' : '' }}">
                        <td class="text-center">{{ data_get($row, 'nro') }}</td><td class="text-center">{{ data_get($row, 'fecha') }}</td><td class="text-center">{{ number_format((int) data_get($row, 'cantidad', 0)) }}</td><td class="text-center">{{ data_get($row, 'origen') }}</td>
                        @if(data_get($row, 'es_casilla'))
                            <td colspan="4" class="text-center kardex-table__casilla">{{ data_get($row, 'tipo_correspondencia') }}</td>
                        @else
                            <td>{{ data_get($row, 'tipo_correspondencia') }}</td><td class="kardex-code">{{ data_get($row, 'guia_casilla') }}</td><td class="text-right">{{ data_get($row, 'peso') !== null && data_get($row, 'peso') !== '' ? number_format((float) data_get($row, 'peso'), 3, ',', '.') : '' }}</td><td>{{ data_get($row, 'pais_ciudad') }}</td>
                        @endif
                        <td class="text-center {{ data_get($row, 'es_anulada') ? 'kardex-table__annulled' : '' }}">{{ data_get($row, 'numero_factura') }}</td><td class="text-right"><span class="kardex-currency">Bs</span> {{ number_format((float) data_get($row, 'importe', 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No se encontraron datos para el Kardex con los filtros aplicados.</td></tr>
                @endforelse
            </tbody>
            @if($visibleRows->isNotEmpty())<tfoot><tr><th colspan="9" class="text-right">TOTAL PARCIAL</th><th class="text-right"><span class="kardex-currency">Bs</span> {{ number_format($totalImporte, 2, ',', '.') }}</th></tr><tr><th colspan="9" class="text-right">TOTAL GENERAL</th><th class="text-right"><span class="kardex-currency">Bs</span> {{ number_format($totalImporte, 2, ',', '.') }}</th></tr></tfoot>@endif
        </table></div></div></div>
    </div>
@stop

@section('css')
    <style>
        .kardex-hero{padding:.35rem .15rem .75rem}.kardex-hero h1{color:#113b68;font-weight:800;font-size:2rem}.kardex-eyebrow{color:#1f5da8;font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.kardex-filter-card,.kardex-report-card{border:0;border-top:4px solid #ffc107;border-radius:18px;box-shadow:0 .45rem 1.35rem rgba(17,59,104,.1);overflow:hidden}.kardex-filter-card .card-body{padding:1.1rem 1.25rem}.kardex-filter-card label{color:#143b6b;font-weight:800}.kardex-option-panel.compact{display:grid;grid-template-columns:1fr;gap:.65rem}.kardex-filter-group{border:1px solid #d8e5f5;border-radius:14px;background:linear-gradient(180deg,#f9fcff,#fff);padding:.72rem .85rem}.kardex-filter-title{display:flex;justify-content:space-between;align-items:center;color:#143b6b;font-weight:900;margin-bottom:.45rem}.kardex-mini-actions{display:flex;gap:.35rem}.kardex-mini-actions button{border:0;background:#edf5ff;color:#1f5da8;border-radius:999px;padding:.16rem .55rem;font-size:.76rem;font-weight:800}.kardex-mini-actions button:hover{background:#dbeafe}.kardex-chip-list{display:flex;flex-wrap:wrap;gap:.35rem}.kardex-chip-list--services{max-height:96px;overflow:auto;padding-right:.2rem}.kardex-chip{display:inline-flex;gap:.28rem;align-items:center;margin:0;padding:.22rem .52rem;border:1px solid #d7e3f1;border-radius:999px;background:#fff;color:#1f2937;font-size:.76rem;font-weight:800;cursor:pointer;transition:.16s ease;line-height:1.25}.kardex-chip input{width:13px;height:13px}.kardex-chip:has(input:checked){border-color:#2b64ad;background:#eaf3ff;color:#123e70;box-shadow:inset 0 0 0 1px rgba(43,100,173,.08)}.kardex-downloads .btn,.kardex-actions-right .btn{border-radius:10px;font-weight:800}.kardex-summary-row{margin-top:1rem}.kardex-summary-card{min-height:74px;border:1px solid #d8e5f5;border-radius:16px;background:linear-gradient(135deg,#fff,#eef6ff);padding:.85rem 1rem;box-shadow:0 .18rem .75rem rgba(17,59,104,.06)}.kardex-summary-card span{display:block;color:#64748b;font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.kardex-summary-card strong{display:block;color:#123e70;font-size:1.25rem;margin-top:.18rem}.kardex-table-wrap{max-height:calc(100vh - 315px)}.kardex-table{border:1px solid #dee6f0;color:#1f2937;font-size:.79rem}.kardex-table thead th{position:sticky;top:0;z-index:2;border-bottom:1px solid #cfd9e8;background:#e8f1fb;color:#063b70;font-size:.73rem;font-weight:900;letter-spacing:.03em;text-align:center;white-space:nowrap}.kardex-table th,.kardex-table td{border-color:#dee6f0!important;padding:.43rem .52rem;vertical-align:middle}.kardex-table tbody tr:nth-child(even) td{background:#fbfdff}.kardex-table tbody tr:hover td{background:#fff8df}.kardex-table tbody tr.kardex-table__row-annulled td{background:#fff1f2!important;color:#8f1231;font-weight:700}.kardex-table tbody tr.kardex-table__row-annulled:hover td{background:#ffe4e8!important}.kardex-table tfoot th{background:#f3f7fc;color:#143b6b;font-weight:900}.kardex-code{color:#173b66;font-family:Consolas,Monaco,monospace;font-size:.77rem;text-transform:uppercase;white-space:nowrap}.kardex-currency{float:left;font-weight:900}.kardex-table__annulled{background:#ffc7ce!important;color:#b00020!important;font-weight:900}.kardex-table__casilla{background:#fff6dc!important;color:#7a4b00;font-weight:900;white-space:nowrap;text-transform:uppercase}.kardex-soft-button{border-radius:10px;font-weight:800}
    </style>
@stop

@section('js')
    <script>
        document.querySelectorAll('[data-check-group]').forEach((group)=>{const boxes=()=>Array.from(group.querySelectorAll('input[type="checkbox"]'));group.querySelector('[data-check-all]')?.addEventListener('click',()=>boxes().forEach((box)=>box.checked=true));group.querySelector('[data-uncheck-all]')?.addEventListener('click',()=>boxes().forEach((box)=>box.checked=false));});
    </script>
@stop