<div>
    <style>
        :root{
            --azul:#20539A;
            --bg:#f5f7fb;
            --muted:#6b7280;
            --line:#e5e7eb;
        }
        .plantilla-wrap{ background: var(--bg); padding: 18px; border-radius: 16px; }
        .card-app{ border:0; border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.08); overflow:hidden; }
        .header-app{ background: linear-gradient(90deg, var(--azul), #20539A); color:#fff; padding:18px 20px; }
        .header-shell{ display:flex; align-items:flex-start; justify-content:space-between; gap:24px; }
        .header-main{ flex:1 1 320px; min-width:260px; }
        .header-tools{ flex:1 1 620px; min-width:320px; display:flex; flex-direction:column; gap:12px; align-items:stretch; }
        .header-search-row{ display:flex; justify-content:flex-end; }
        .header-search-form{ width:min(100%, 760px); display:flex; align-items:center; gap:10px; }
        .header-search-form .search-input{ flex:1 1 auto; }
        .search-input{ border-radius:12px; border:1px solid rgba(255,255,255,.45); padding:10px 12px; background: rgba(255,255,255,.95); }
        .btn-outline-light2{ border:1px solid rgba(255,255,255,.7); color:#fff; font-weight:800; border-radius:12px; padding:10px 14px; background:transparent; }
        .btn-outline-light2:hover{ background: rgba(255,255,255,.12); color:#fff; }
        .action-col{ width: 92px; min-width: 92px; text-align:center; }
        .action-stack{ display:flex; flex-direction:column; align-items:center; gap:8px; }
        .action-btn{
            width:48px;
            height:48px;
            padding:0;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:14px;
            box-shadow:0 8px 18px rgba(32, 83, 154, .10);
        }
        .action-btn i{ font-size:16px; }
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

        @media (max-width: 991.98px){
            .header-shell{ flex-direction:column; }
            .header-tools{ width:100%; min-width:0; }
            .header-search-row{ justify-content:flex-start; }
            .header-search-form{ width:100%; }
        }

        @media (max-width: 575.98px){
            .header-search-form{ flex-direction:column; }
            .header-search-form > .btn{ width:100%; justify-content:center; }
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app">
                <div class="header-shell">
                <div class="header-main">
                    <h4 class="fw-bold mb-0">Contratos en CARTERO</h4>
                    <small class="text-white-50">
                        Empresa aplicada: <strong>{{ optional(auth()->user()->empresa)->nombre ?? 'SIN EMPRESA' }}</strong>
                    </small>
                </div>

                <div class="header-tools">
                    <div class="header-search-row">
                        <div class="header-search-form">
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
                </div>
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
                                    <th class="action-col">Acciones</th>
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
                                        <td class="action-col">
                                            <div class="action-stack">
                                            @if ($canContratoCarteroPrint)
                                            <a href="{{ route('paquetes-contrato.reporte', $recojo->id, false) }}"
                                               target="_blank"
                                               class="btn btn-sm btn-outline-azul action-btn"
                                               title="Reimprimir rotulo">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            </div>
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


