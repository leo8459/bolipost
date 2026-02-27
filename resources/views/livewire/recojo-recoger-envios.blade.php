<div>
    <style>
        :root{
            --azul:#34447C;
            --dorado:#B99C46;
            --bg:#f5f7fb;
            --muted:#6b7280;
        }
        .plantilla-wrap{ background: var(--bg); padding: 18px; border-radius: 16px; }
        .card-app{ border:0; border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.08); overflow:hidden; }
        .header-app{ background: linear-gradient(90deg, var(--azul), #2c3766); color:#fff; padding:18px 20px; }
        .search-input{ border-radius:12px; border:1px solid rgba(255,255,255,.45); padding:10px 12px; background: rgba(255,255,255,.95); }
        .btn-outline-light2{ border:1px solid rgba(255,255,255,.7); color:#fff; font-weight:800; border-radius: 12px; padding: 10px 14px; background: transparent; }
        .btn-outline-light2:hover{ background: rgba(255,255,255,.12); color:#fff; }
        .btn-outline-azul{ border:1px solid rgba(52,68,124,.35); color: var(--azul); font-weight: 800; border-radius: 12px; padding: 8px 12px; background:#fff; }
        .btn-outline-azul:hover{ background: rgba(52,68,124,.06); color: var(--azul); }
        .table thead th{ background: rgba(52,68,124,.08); color: var(--azul); font-weight: 900; border-bottom: 2px solid rgba(52,68,124,.2); white-space: nowrap; }
        .muted{ color:var(--muted); }
        .pill-id{ background: rgba(52,68,124,.12); color: var(--azul); font-weight:900; padding:4px 10px; border-radius: 999px; display:inline-block; }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">Recoger envios contratos</h4>
                    <div class="small">
                        Origen filtrado por ciudad del usuario:
                        <strong>{{ $this->userCity !== '' ? $this->userCity : 'SIN CIUDAD CONFIGURADA' }}</strong>
                    </div>
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
                    <button class="btn btn-outline-light2" type="button" wire:click="mandarSeleccionadosAlmacen">
                        Mandar seleccionados a ALMACEN
                    </button>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3 mb-0">
                    <p class="mb-0">{{ session('success') }}</p>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger m-3 mb-0">
                    <p class="mb-0">{{ session('error') }}</p>
                </div>
            @endif

            @if ($this->userCity === '')
                <div class="alert alert-warning m-3 mb-0">
                    Tu usuario no tiene ciudad configurada. No se puede filtrar por origen.
                </div>
            @endif

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando todos los registros filtrados
                        @endif
                    </div>
                    <div class="muted small">Total en pagina: <strong>{{ $recojos->count() }}</strong></div>
                    <div class="muted small">Seleccionados: <strong>{{ count($selectedRecojos) }}</strong></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Codigo</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Empresa</th>
                                <th>Telefono R</th>
                                <th>Telefono D</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recojos as $recojo)
                                <tr>
                                    <td>
                                        <input type="checkbox" value="{{ $recojo->id }}" wire:model="selectedRecojos">
                                    </td>
                                    <td><span class="pill-id">{{ $recojo->codigo }}</span></td>
                                    <td>{{ optional($recojo->estadoRegistro)->nombre_estado ?? '-' }}</td>
                                    <td>{{ $recojo->origen }}</td>
                                    <td>{{ $recojo->destino }}</td>
                                    <td>{{ $recojo->nombre_r }}</td>
                                    <td>{{ $recojo->nombre_d }}</td>
                                    <td>
                                        {{ optional($recojo->empresa)->nombre ?? optional(optional($recojo->user)->empresa)->nombre ?? '-' }}
                                        @if(!empty(optional($recojo->empresa)->sigla))
                                            ({{ optional($recojo->empresa)->sigla }})
                                        @elseif(!empty(optional(optional($recojo->user)->empresa)->sigla))
                                            ({{ optional(optional($recojo->user)->empresa)->sigla }})
                                        @endif
                                    </td>
                                    <td>{{ $recojo->telefono_r }}</td>
                                    <td>{{ $recojo->telefono_d ?: '-' }}</td>
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
                                        <div class="fw-bold" style="color:var(--azul);">No hay envios para recoger</div>
                                        <div class="muted">El origen debe coincidir con tu ciudad de usuario.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $recojos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
