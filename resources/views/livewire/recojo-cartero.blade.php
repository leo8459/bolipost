<div>
    <style>
        :root{
            --azul:#34447C;
            --bg:#f5f7fb;
            --muted:#6b7280;
            --line:#e5e7eb;
        }
        .plantilla-wrap{ background: var(--bg); padding: 18px; border-radius: 16px; }
        .card-app{ border:0; border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.08); overflow:hidden; }
        .header-app{ background: linear-gradient(90deg, var(--azul), #2c3766); color:#fff; padding:18px 20px; }
        .search-input{ border-radius:12px; border:1px solid rgba(255,255,255,.45); padding:10px 12px; background: rgba(255,255,255,.95); }
        .btn-outline-light2{ border:1px solid rgba(255,255,255,.7); color:#fff; font-weight:800; border-radius:12px; padding:10px 14px; background:transparent; }
        .btn-outline-light2:hover{ background: rgba(255,255,255,.12); color:#fff; }
        .table thead th{ background: rgba(52,68,124,.08); color: var(--azul); font-weight:900; border-bottom:2px solid rgba(52,68,124,.2); white-space: nowrap; }
        .pill-id{ background: rgba(52,68,124,.12); color: var(--azul); font-weight:900; padding:4px 10px; border-radius: 999px; display:inline-block; }
        .muted{ color:var(--muted); }
        .table td{ vertical-align: middle; }
        .table-scroll-wrap{
            max-height: 58vh;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 12px;
            background:#fff;
        }
        .table-scroll-wrap .table{ margin-bottom: 0; }
        .table-scroll-wrap .table thead th{ position: sticky; top: 0; z-index: 2; }
        .btn-outline-azul{
            border:1px solid rgba(52,68,124,.35);
            color: var(--azul);
            font-weight: 800;
            border-radius: 10px;
            background:#fff;
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">Contratos en CARTERO</h4>
                    <small class="text-white-50">
                        Empresa aplicada: <strong>{{ optional(auth()->user()->empresa)->nombre ?? 'SIN EMPRESA' }}</strong>
                    </small>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar o pegar codigo..."
                        wire:model="search"
                        wire:keydown.enter.prevent="searchRecojos(true)"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchRecojos(true)">Buscar</button>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3 mb-0"><p class="mb-0">{{ session('success') }}</p></div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger m-3 mb-0"><p class="mb-0">{{ session('error') }}</p></div>
            @endif

            @if ($this->userEmpresaId <= 0)
                <div class="alert alert-warning m-3 mb-0">
                    Tu usuario no tiene empresa asignada. No se pueden listar contratos CARTERO.
                </div>
            @endif

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para codigo: <strong>{{ strtoupper($searchQuery) }}</strong>
                        @else
                            Mostrando todos los registros
                        @endif
                    </div>
                    <div class="muted small">Total en pagina: <strong>{{ $recojos->count() }}</strong></div>
                </div>

                <div class="table-scroll-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Cod. especial</th>
                                    <th>Estado</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Remitente</th>
                                    <th>Destinatario</th>
                                    <th>Telefono R</th>
                                    <th>Telefono D</th>
                                    <th>Peso</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recojos as $recojo)
                                    <tr>
                                        <td><span class="pill-id">{{ $recojo->codigo }}</span></td>
                                        <td>{{ $recojo->cod_especial ?: '-' }}</td>
                                        <td>{{ optional($recojo->estadoRegistro)->nombre_estado ?? '-' }}</td>
                                        <td>{{ $recojo->origen }}</td>
                                        <td>{{ $recojo->destino }}</td>
                                        <td>{{ $recojo->nombre_r }}</td>
                                        <td>{{ $recojo->nombre_d }}</td>
                                        <td>{{ $recojo->telefono_r }}</td>
                                        <td>{{ $recojo->telefono_d ?: '-' }}</td>
                                        <td>{{ $recojo->peso }}</td>
                                        <td>{{ optional($recojo->created_at)->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('paquetes-contrato.reporte', $recojo->id) }}"
                                               target="_blank"
                                               class="btn btn-sm btn-outline-azul"
                                               title="Reimprimir rotulo">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center py-5">
                                            <div class="fw-bold" style="color:var(--azul);">No hay contratos en estado CARTERO</div>
                                            <div class="muted">Solo se muestran contratos de la empresa del usuario logueado.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $recojos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

