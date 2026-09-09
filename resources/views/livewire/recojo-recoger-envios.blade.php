<div>
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --muted:#6b7280;
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
        .header-action-row{ display:flex; justify-content:flex-end; gap:12px; flex-wrap:wrap; }
        .search-input{ border-radius:12px; border:1px solid rgba(255,255,255,.45); padding:10px 12px; background: rgba(255,255,255,.95); }
        .btn-outline-light2{ border:1px solid rgba(255,255,255,.7); color:#fff; font-weight:800; border-radius: 12px; padding: 10px 14px; background: transparent; }
        .btn-outline-light2:hover{ background: rgba(255,255,255,.12); color:#fff; }
        .btn-outline-azul{ border:1px solid rgba(52,68,124,.35); color: var(--azul); font-weight: 800; border-radius: 12px; padding: 8px 12px; background:#fff; }
        .btn-outline-azul:hover{ background: rgba(52,68,124,.06); color: var(--azul); }
        .action-col{ width: 128px; min-width: 128px; text-align:center; }
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
        .table thead th{ background: rgba(52,68,124,.08); color: var(--azul); font-weight: 900; border-bottom: 2px solid rgba(52,68,124,.2); white-space: nowrap; }
        .muted{ color:var(--muted); }
        .pill-id{ background: rgba(52,68,124,.12); color: var(--azul); font-weight:900; padding:4px 10px; border-radius: 999px; display:inline-block; }
        .preview-card{
            border:1px solid rgba(32, 83, 154, .14);
            border-radius:14px;
            background:linear-gradient(180deg, rgba(32, 83, 154, .04), rgba(254, 204, 54, .08));
            padding:12px 14px;
            margin-bottom:14px;
        }
        .preview-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
            gap:10px;
        }
        .preview-item{
            background:#fff;
            border:1px solid rgba(32, 83, 154, .10);
            border-radius:12px;
            padding:10px 12px;
            box-shadow:0 6px 14px rgba(32, 83, 154, .05);
        }
        .preview-pill{
            background: rgba(52,68,124,.12);
            color: var(--azul);
            font-weight:900;
            padding:3px 10px;
            border-radius:999px;
            display:inline-block;
            font-size:1rem;
            line-height:1.15;
        }
        .preview-item-line{
            font-size:.8rem;
            color:#374151;
            line-height:1.25;
            margin-bottom:2px;
        }
        .preview-item-line strong{
            color:var(--azul);
        }
        .preview-meta{
            font-size:.8rem;
        }
        .pickup-modal-code{
            color:var(--azul);
            font-weight:900;
            white-space:nowrap;
        }
        .pickup-weight-input{
            min-width:150px;
        }

        @media (max-width: 991.98px){
            .header-shell{ flex-direction:column; }
            .header-tools{ width:100%; min-width:0; }
            .header-search-row,
            .header-action-row{ justify-content:flex-start; }
            .header-search-form{ width:100%; }
        }

        @media (max-width: 575.98px){
            .header-search-form,
            .header-action-row{ flex-direction:column; }
            .header-search-form > .btn,
            .header-action-row > .btn,
            .header-action-row > a{ width:100%; justify-content:center; }
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app">
                <div class="header-shell">
                <div class="header-main">
                    <h4 class="fw-bold mb-0">Recoger envios contratos</h4>
                    <div class="small">
                        Origen filtrado por ciudad del usuario:
                        <strong>{{ $this->userCity !== '' ? $this->userCity : 'SIN CIUDAD CONFIGURADA' }}</strong>
                    </div>
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
                    <div class="header-action-row">
                        @if ($canContratoRecogerAssign)
                        <button class="btn btn-outline-light2" type="button" wire:click="abrirModalRecojo">
                            Mandar seleccionados a ALMACEN
                        </button>
                        @endif
                    </div>
                </div>
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
                @if ($selectedPreview->isNotEmpty())
                    <div class="preview-card">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                            <div>
                                <div class="fw-bold" style="color:var(--azul);">Prelista de envios a recoger</div>
                                <div class="muted small preview-meta">Se enviaran a <strong>ALMACEN</strong> al confirmar.</div>
                            </div>
                            <div class="muted small">Total seleccionados validos: <strong>{{ $selectedPreview->count() }}</strong></div>
                        </div>

                        <div class="preview-grid">
                            @foreach ($selectedPreview as $item)
                                <div class="preview-item">
                                    <div class="mb-1">
                                        <span class="preview-pill">{{ $item->codigo }}</span>
                                    </div>
                                    <div class="preview-item-line"><strong>Estado:</strong> {{ optional($item->estadoRegistro)->nombre_estado ?? '-' }}</div>
                                    <div class="preview-item-line"><strong>Origen:</strong> {{ $item->origen ?: '-' }}</div>
                                    <div class="preview-item-line"><strong>Destino:</strong> {{ $item->destino ?: '-' }}</div>
                                    <div class="preview-item-line"><strong>Remitente:</strong> {{ $item->nombre_r ?: '-' }}</div>
                                    <div class="preview-item-line"><strong>Destinatario:</strong> {{ $item->nombre_d ?: '-' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

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
                                <th class="action-col">Acciones</th>
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
                                    <td class="action-col">
                                        <div class="action-stack">
                                        @include('partials.rastreo-eventos-button', [
                                            'tipo' => 'contrato',
                                            'codigo' => $recojo->codigo,
                                        ])
                                        @if ($canContratoRecogerPrint)
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

    <div class="modal fade" id="pickupConfirmationModal" tabindex="-1" aria-labelledby="pickupConfirmationModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold" id="pickupConfirmationModalLabel">Confirmar recojo de envios</h5>
                        <div class="text-muted small">Revisa todos los paquetes e ingresa su peso en kilogramos (maximo 700,000 kg).</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    @if (!empty($missingWeightCodes))
                        <div class="alert alert-warning" role="alert">
                            <div class="font-weight-bold mb-2">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Por favor ingrese el peso de los paquetes:
                            </div>
                            <div>
                                @foreach ($missingWeightCodes as $codigo)
                                    <span class="badge badge-warning border mr-1 mb-1">{{ $codigo }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                        <div class="font-weight-bold" style="color:var(--azul);">Paquetes que se recogeran</div>
                        <div class="text-muted">Total: <strong>{{ count($pickupRows) }}</strong></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Codigo</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Remitente</th>
                                    <th>Destinatario</th>
                                    <th style="min-width:180px;">Peso (kg) <span class="text-danger">*</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pickupRows as $index => $row)
                                    @php
                                        $rowId = (int) ($row['id'] ?? 0);
                                        $rowCode = (string) ($row['codigo'] ?? 'SIN CODIGO');
                                        $weightMissing = in_array($rowCode, $missingWeightCodes, true);
                                    @endphp
                                    <tr wire:key="pickup-row-{{ $rowId }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td><span class="pickup-modal-code">{{ $rowCode }}</span></td>
                                        <td>{{ $row['origen'] ?: '-' }}</td>
                                        <td>{{ $row['destino'] ?: '-' }}</td>
                                        <td>{{ $row['remitente'] ?: '-' }}</td>
                                        <td>{{ $row['destinatario'] ?: '-' }}</td>
                                        <td>
                                            <div class="input-group input-group-sm pickup-weight-input">
                                                <input
                                                    type="text"
                                                    class="form-control @if($weightMissing) is-invalid @endif"
                                                    wire:model.defer="pickupWeights.{{ $rowId }}"
                                                    inputmode="decimal"
                                                    placeholder="Ej. 1,250"
                                                    maxlength="7"
                                                    data-fixed-weight-input
                                                    data-weight-min="0.001"
                                                    data-weight-max="700"
                                                    required
                                                    aria-label="Peso del paquete {{ $rowCode }} en kilogramos"
                                                >
                                                <div class="input-group-append">
                                                    <span class="input-group-text">kg</span>
                                                </div>
                                            </div>
                                            @if ($weightMissing)
                                                <small class="text-danger font-weight-bold">Ingrese un peso entre 0,001 y 700,000 kg</small>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No hay paquetes seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canContratoRecogerAssign)
                        <button
                            type="button"
                            class="btn btn-primary"
                            wire:click="mandarSeleccionadosAlmacen"
                            wire:loading.attr="disabled"
                            wire:target="mandarSeleccionadosAlmacen"
                        >
                            <span wire:loading.remove wire:target="mandarSeleccionadosAlmacen">
                                <i class="fas fa-box-open mr-1"></i> Confirmar recojo
                            </span>
                            <span wire:loading wire:target="mandarSeleccionadosAlmacen">Procesando...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
    (() => {
        if (window.__pickupConfirmationModalInit) {
            return;
        }
        window.__pickupConfirmationModalInit = true;

        const togglePickupModal = (action) => {
            if (window.jQuery && $('#pickupConfirmationModal').length) {
                $('#pickupConfirmationModal').modal(action);
            }
        };

        const registerPickupModalEvents = () => {
            window.addEventListener('openPickupConfirmationModal', () => togglePickupModal('show'));
            window.addEventListener('closePickupConfirmationModal', () => togglePickupModal('hide'));
            document.addEventListener('openPickupConfirmationModal', () => togglePickupModal('show'));
            document.addEventListener('closePickupConfirmationModal', () => togglePickupModal('hide'));

            if (window.Livewire && typeof window.Livewire.on === 'function') {
                window.Livewire.on('openPickupConfirmationModal', () => togglePickupModal('show'));
                window.Livewire.on('closePickupConfirmationModal', () => togglePickupModal('hide'));
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', registerPickupModalEvents, { once: true });
        } else {
            registerPickupModalEvents();
        }

    })();
</script>
@endonce

@once
    @php($fixedWeightJsVersion = file_exists(public_path('js/fixed-weight-input.js')) ? filemtime(public_path('js/fixed-weight-input.js')) : time())
    <script src="{{ asset('js/fixed-weight-input.js') }}?v={{ $fixedWeightJsVersion }}" defer></script>
@endonce

