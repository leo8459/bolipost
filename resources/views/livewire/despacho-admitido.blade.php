<div>
    <style>
        :root{
            --azul:#B99C46;
            --dorado:#34447C;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .plantilla-wrap{
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .card-app{
            border:0;
            border-radius:16px;
            box-shadow:0 12px 26px rgba(0,0,0,.08);
            overflow:hidden;
        }

        .header-app{
            background: linear-gradient(90deg, var(--azul), #2c3766);
            color:#fff;
            padding:18px 20px;
        }

        .search-input{
            border-radius:12px;
            border:1px solid rgba(255,255,255,.45);
            padding:10px 12px;
            background: rgba(255,255,255,.95);
        }

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .btn-dorado:hover{ filter:brightness(.95); color:#fff; }

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius: 12px;
            padding: 10px 14px;
            background: transparent;
        }
        .btn-outline-light2:hover{
            background: rgba(255,255,255,.12);
            color:#fff;
        }

        .table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .muted{ color:var(--muted); }

        .table td{ vertical-align: middle; white-space: nowrap; }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">Despachos admitidos</h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar..."
                        wire:model="search"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchDespachos">Buscar</button>
                    <button class="btn btn-dorado" type="button" wire:click="openAdmitirModal">Admitir despachos</button>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3 mb-0">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger m-3 mb-0">{{ $errors->first() }}</div>
            @endif

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Identificador</th>
                                <th>Nro despacho</th>
                                <th>Oforigen</th>
                                <th>Ofdestino</th>
                                <th>Categoria</th>
                                <th>Subclase</th>
                                <th>Estado</th>
                                <th>Sacas recibidos / sacas totales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($despachos as $despacho)
                                <tr>
                                    <td>{{ $despacho->identificador }}</td>
                                    <td>{{ $despacho->nro_despacho }}</td>
                                    <td>{{ $despacho->oforigen }}</td>
                                    <td>{{ $despacho->ofdestino }}</td>
                                    <td>{{ $despacho->categoria }}</td>
                                    <td>{{ $despacho->subclase }}</td>
                                    <td>{{ optional($despacho->estado)->nombre_estado }}</td>
                                    <td>{{ $despacho->sacas_recibidas }} / {{ $despacho->sacas_totales }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">No se encontraron despachos admitidos.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $despachos->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="admitirDespachoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="admitirDespachos">
                    <div class="modal-header">
                        <h5 class="modal-title">Admitir despachos por receptaculo</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label>Receptaculos (uno por linea, presiona Enter por cada escaneo)</label>
                            <textarea
                                class="form-control"
                                rows="4"
                                wire:model.defer="receptaculosInput"
                                placeholder="Ejemplo:\nBOLPZBOTJACUN6003002133\nBOLPZBOTJACUN6003004043"></textarea>
                        </div>

                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-info" wire:click="previewAdmitir">Buscar sacas</button>
                        </div>

                        @if($receptaculosEscaneadosCount > 0)
                            <div class="alert alert-info py-2">
                                Escaneados: {{ $receptaculosEscaneadosCount }} | Encontrados: {{ $receptaculosEncontradosCount }}
                            </div>
                        @endif

                        @if(!empty($receptaculosResultado))
                            <div class="mb-2 fw-bold">Lista escaneada</div>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Receptaculo</th>
                                            <th>Resultado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($receptaculosResultado as $item)
                                            <tr>
                                                <td>{{ $item['codigo'] }}</td>
                                                <td>
                                                    @if($item['ok'])
                                                        <span class="text-success fw-bold">{{ $item['detalle'] }}</span>
                                                    @else
                                                        <span class="text-danger fw-bold">{{ $item['detalle'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if(!empty($previewSacas))
                            <div class="mb-2 fw-bold">Sacas a recibir (saca estado 15 y despacho estado 19)</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Receptaculo</th>
                                            <th>Identificador saca</th>
                                            <th>Despacho</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($previewSacas as $saca)
                                            <tr>
                                                <td>{{ $saca['receptaculo'] }}</td>
                                                <td>{{ $saca['identificador'] }}</td>
                                                <td>{{ $saca['despacho'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @php
                                $despachosPreview = collect($previewSacas)
                                    ->groupBy('fk_despacho')
                                    ->map(function ($items) {
                                        return [
                                            'despacho' => $items->first()['despacho'] ?? '',
                                            'cantidad_sacas' => $items->count(),
                                        ];
                                    })
                                    ->values();
                            @endphp

                            @if($despachosPreview->isNotEmpty())
                                <div class="mb-2 mt-3 fw-bold">Despachos a recibir</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Despacho</th>
                                                <th>Cantidad de sacas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($despachosPreview as $row)
                                                <tr>
                                                    <td>{{ $row['despacho'] }}</td>
                                                    <td>{{ $row['cantidad_sacas'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif

                        @if(!empty($receptaculosNoEncontrados))
                            <div class="alert alert-warning mt-3 mb-0">
                                No encontrados o no validos por estado: {{ implode(', ', $receptaculosNoEncontrados) }}
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Recibir despachos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('openAdmitirDespachoModal', () => {
        $('#admitirDespachoModal').modal('show');
    });

    window.addEventListener('closeAdmitirDespachoModal', () => {
        $('#admitirDespachoModal').modal('hide');
    });
</script>
