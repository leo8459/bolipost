

@section('title', $title)

@section('content')
<div class="container-fluid py-3 ips-receptacle-page">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($error)<div class="alert alert-warning" role="alert">{{ $error }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($isPending)<div class="alert alert-info" role="note">La respuesta está pendiente. Reintentar consulta la misma operación con la misma clave; no crea otro movimiento.</div>@endif

    @php($hasReceptacle = $detail && isset($detail['receptacle']))
    @if(! ($hasReceptacle))
        <section class="card border-0 shadow-sm ips-receptacle-search">
            <div class="card-body">
                <form method="GET" action="{{ route('internacional.sacas', ['paso' => $paso]) }}" class="ips-receptacle-search__form">
                    <label class="sr-only" for="sack-code">Marbete de la saca</label>
                    <input id="sack-code" name="q" class="form-control" maxlength="35" value="{{ $codigo }}" placeholder="Escanea o escribe el marbete de la saca" required autofocus>
                    <button class="btn btn-warning" type="submit"><i class="fas fa-search mr-1"></i>Buscar</button>
                </form>
            </div>
        </section>
    @endif

    @if($hasReceptacle)
        @php($receptacle = $detail['receptacle'])
        @php($items = $detail['items'] ?? [])
        @php($linkedItemsCount = (int) ($detail['linked_items_count'] ?? count($items)))
        @php($declaredItemsCount = $receptacle['items_count'] ?? null)
        @php($canReceiveHere = in_array('RECEPTACLE_RECEIVE', $receptacle['allowed_actions'] ?? [], true))
        @php($canOpenHere = in_array('RECEPTACLE_OPEN', $receptacle['allowed_actions'] ?? [], true))
        @php($isReceivedClosed = (int) $receptacle['state_cd'] === 22 && in_array((int) $receptacle['event_cd'], [130, 133], true))
        @php($isSentClosed = (int) $receptacle['state_cd'] === 22 && (int) $receptacle['event_cd'] === 132)
        @php($isPhysicallyOpened = (int) $receptacle['state_cd'] === 20 && (int) $receptacle['event_cd'] === 131)
        @php($isCurrentReceptacleOffice = (int) ($receptacle['office_cd'] ?? 0) === (int) $link->ips_office_cd)
        @php($operationStep = !$isPending && $paso === 'recibir' && $canOpenHere ? 'abrir' : $paso)
        @php($showOpenAction = !$isPending && $operationStep === 'abrir' && $isReceivedClosed && $isCurrentReceptacleOffice && $canOpenHere)
        @php($showReceiveAction = !$isPending && $operationStep === 'recibir' && $canReceiveHere)
        @php($showPackageAction = $isPhysicallyOpened && $isCurrentReceptacleOffice)
        @php($receiveExpectedEventCode = $isPending ? (int) $expectedReceptacleEventCode : 0)
        @php($receiveEventCode = $receiveExpectedEventCode === 132 || (int) $receptacle['event_cd'] === 132 ? 133 : 130)
        @php($operationEventCode = $operationStep === 'abrir' ? 131 : $receiveEventCode)
        @php($receptionRecorded = $isReceivedClosed || $isPhysicallyOpened)

        <div class="mt-3 ips-receptacle-layout">
            <main class="w-100">
                <section class="card border-0 shadow-sm ips-receptacle-summary">
                    <div class="card-body">
                        <div class="ips-receptacle-toolbar">
                            <div class="ips-receptacle-toolbar__title">
                                <small>GESTIÓN INTERNACIONAL</small>
                                <strong>Recibir y abrir saca</strong>
                            </div>
                            <form method="GET" action="{{ route('internacional.sacas', ['paso' => $paso]) }}" class="ips-receptacle-search__form ips-receptacle-search__form--inline ips-receptacle-toolbar__search">
                                <label class="sr-only" for="next-sack-code">Buscar otra saca</label>
                                <input id="next-sack-code" name="q" class="form-control" maxlength="35" value="{{ $codigo }}" placeholder="Escanea otra saca" autocomplete="off" onfocus="this.select()" autofocus required>
                                <button class="btn btn-warning" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i>Buscar</button>
                            </form>
                            <div class="ips-receptacle-toolbar__actions">
                                    @if(!$isPhysicallyOpened && $isReceivedClosed && $isCurrentReceptacleOffice)
                                        <details class="ips-preopen-damage" @if($errors->any()) open @endif>
                                            <summary class="ips-preopen-damage__trigger" title="Documentar una irregularidad de la saca" aria-label="Documentar una irregularidad de la saca">
                                                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                                                @if($incidents->isNotEmpty())<span>{{ $incidents->count() }}</span>@endif
                                            </summary>
                                            <div class="ips-preopen-damage__popover">
                                                <div class="ips-preopen-damage__heading">
                                                    <strong>¿La saca llegó abierta o dañada?</strong>
                                                    <small>Documenta el estado exterior antes de abrirla. Esto no genera un evento IPS.</small>
                                                </div>
                                                {!! $__env->make('paquetes_ips.partials.verification-notes', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render() !!}
                                            </div>
                                        </details>
                                    @endif
                                    @if(!$isReceivedClosed)
                                        <span class="badge badge-info p-2">{{ $isPhysicallyOpened ? 'Abierta en IPS' : ($isSentClosed ? 'Despachada cerrada' : 'Pendiente de recepción') }}</span>
                                    @endif
                            </div>
                        </div>
                        <div class="ips-receptacle-stats"><div class="row">
                            <div class="col-6 col-lg-3 ips-receptacle-stat"><small>Peso registrado</small><strong>{{ $receptacle['weight_kg'] !== null ? number_format($receptacle['weight_kg'], 3, ',', '.') . ' kg' : 'Sin dato' }}</strong></div>
                            <div class="col-6 col-lg-3 ips-receptacle-stat"><small>Envíos declarados por origen</small><strong>{{ $declaredItemsCount !== null ? $declaredItemsCount : 'Sin dato en IPS' }}</strong></div>
                            <div class="col-6 col-lg-3 ips-receptacle-stat"><small>Oficina</small><strong>{{ $receptacle['office_name'] ?: 'No informada' }}</strong></div>
                            <div class="col-6 col-lg-3 ips-receptacle-stat"><small>Último movimiento</small><strong>{{ \Illuminate\Support\Carbon::parse($receptacle['event_at'])->timezone('America/La_Paz')->format('d/m/Y H:i') }}</strong></div>
                        </div></div>
                        <ol class="ips-receptacle-flow" aria-label="Flujo de recepción de la saca">
                            <li class="{{ $receptionRecorded ? 'is-done' : (($showReceiveAction || ($isPending && $operationStep === 'recibir')) ? 'is-current' : '') }}">
                                <span>@if($receptionRecorded)<i class="fas fa-check" aria-hidden="true"></i>@else 1 @endif</span>
                                <div><strong>1. Recibir saca</strong></div>
                            </li>
                            <li class="{{ $isPhysicallyOpened ? 'is-done' : (($isReceivedClosed || ($isPending && $operationStep === 'abrir')) ? 'is-current' : '') }}">
                                <span>@if($isPhysicallyOpened)<i class="fas fa-check" aria-hidden="true"></i>@else 2 @endif</span>
                                <div><strong>2. Abrir físicamente</strong></div>
                            </li>
                            <li class="{{ $isPhysicallyOpened ? 'is-current' : '' }}">
                                <span>3</span><div><strong>3. Escanear paquetes</strong></div>
                            </li>
                        </ol>

                        @if($isPending || $showReceiveAction || $showOpenAction)
                            <div class="ips-receptacle-next-step {{ $operationStep === 'abrir' ? 'ips-receptacle-next-step--compact' : '' }}">
                                @if($operationStep !== 'abrir')
                                    <div class="ips-receptacle-next-step__copy">
                                        <small>{{ $isPending ? 'OPERACIÓN PENDIENTE · IPS ' . $operationEventCode : 'PASO 1 · IPS ' . $receiveEventCode . ' · RECEPCIÓN' }}</small>
                                        <strong>Confirma que la saca llegó a esta oficina</strong>
                                        <span>Mantén la saca cerrada y registra su recepción física en esta oficina.</span>
                                    </div>
                                @endif
                                <div class="ips-receptacle-next-step__action">
                                    <form method="POST" action="{{ route('internacional.sacas.operate', ['paso' => $operationStep]) }}">
                                        {!! csrf_field() !!}
                                        <input type="hidden" name="codigo" value="{{ $receptacle['codigo'] }}">
                                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                                        <input type="hidden" name="confirmed" value="1">
                                        @if($operationStep === 'recibir')<input type="hidden" name="physical_receipt_confirmed" value="1">@endif
                                        <button class="btn btn-warning btn-block" type="submit"><i class="{{ $operationStep === 'abrir' ? 'fas fa-box-open' : 'fas fa-check' }} mr-1" aria-hidden="true"></i>{{ $isPending ? 'Consultar operación pendiente' : ($operationStep === 'abrir' ? 'Abrir saca' : 'Recibir saca') }}</button>
                                    </form>
                                </div>
                            </div>
                        @elseif($showPackageAction)
                            <div class="ips-receptacle-next-step">
                                <div class="ips-receptacle-next-step__copy">
                                    <small>PASO 3 · RECEPCIÓN INDIVIDUAL</small>
                                    <strong>Escanea los paquetes encontrados</strong>
                                    <span>Registra cada envío que verificaste físicamente; IPS genera un movimiento 30 por paquete.</span>
                                </div>
                                <div class="ips-receptacle-next-step__action">
                                    <a class="btn btn-warning btn-block" href="{{ route('internacional.section', ['section' => 'recepcion-cambio', 'receptacle_pid' => $receptacle['id']]) }}"><i class="fas fa-barcode mr-1" aria-hidden="true"></i>Escanear paquetes</a>
                                </div>
                            </div>
                        @elseif($isReceivedClosed && !$isCurrentReceptacleOffice)
                            <div class="ips-receptacle-next-note"><strong>La saca ya fue recibida</strong><span>La apertura corresponde a {{ $receptacle['office_name'] ?: 'la oficina que la recibió' }}. No la recibas otra vez.</span></div>
                        @elseif($isSentClosed)
                            <div class="ips-receptacle-next-note"><strong>Saca enviada y cerrada</strong><span>Al llegar a la oficina nacional de destino, IPS habilitará la recepción (evento 133).</span></div>
                        @elseif(!$isPhysicallyOpened)
                            <div class="ips-receptacle-next-note"><strong>No hay un paso disponible</strong><span>IPS no habilita recepción ni apertura para el estado actual y la oficina vinculada.</span></div>
                        @endif

                    </div>
                </section>

                <section class="card border-0 shadow-sm mt-3 ips-receptacle-packages">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
                        <h2 class="h6 font-weight-bold mb-0">Paquetes asociados en IPS</h2>
                        <span class="badge badge-primary">{{ $linkedItemsCount }} {{ $linkedItemsCount === 1 ? 'paquete' : 'paquetes' }}</span>
                    </div>
                    <div class="card-body">
                        <div class="ips-receptacle-counts">
                            <div><small>Declarados por origen</small><strong>{{ $declaredItemsCount !== null ? $declaredItemsCount : 'Sin dato' }}</strong></div>
                            <div><small>Asociados en IPS</small><strong>{{ $linkedItemsCount }}</strong></div>
                            <p>
                                @if($declaredItemsCount === null)
                                    IPS no informó una cantidad declarada. La lista muestra asociaciones del sistema, no el contenido físico de la saca.
                                @elseif($linkedItemsCount === (int) $declaredItemsCount)
                                    Los datos de IPS coinciden. Aun así, contrasta los marbetes al abrir la saca.
                                @elseif($linkedItemsCount < (int) $declaredItemsCount)
                                    Hay {{ (int) $declaredItemsCount - $linkedItemsCount }} envíos declarados que todavía no aparecen asociados en IPS.
                                @else
                                    Hay {{ $linkedItemsCount - (int) $declaredItemsCount }} asociaciones más que la cantidad declarada por origen.
                                @endif
                            </p>
                        </div>

                        @if(count($items))
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 ips-receptacle-table">
                                    <thead><tr><th>Marbete del paquete</th><th>ID local</th><th>Peso</th><th>Clase</th><th>Último movimiento</th></tr></thead>
                                    <tbody>
                                        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr>
                                                <td class="font-weight-bold">{{ $item['MAILITM_FID'] ?: 'Sin código' }}</td>
                                                <td>{{ $item['MAILITM_LOCAL_ID'] ?: '—' }}</td>
                                                <td>{{ is_numeric($item['MAILITM_WEIGHT'] ?? null) ? number_format((float) $item['MAILITM_WEIGHT'], 3, ',', '.') . ' kg' : '—' }}</td>
                                                <td>{{ $item['MAIL_CLASS_NM'] ?: ($item['MAIL_CLASS_CD'] ?: '—') }}</td>
                                                <td>{{ $item['EVENT_NAME_ES'] ?: ($item['EVENT_NAME'] ?: 'Sin movimiento') }}</td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tbody>
                                </table>
                            </div>
                            @if($detail['items_truncated'] ?? false)
                                <p class="small text-muted mt-2 mb-0">Se muestran los primeros 1.000 paquetes; IPS registra {{ $linkedItemsCount }} asociados.</p>
                            @endif
                        @else
                            <div class="ips-receptacle-empty"><i class="fas fa-box-open" aria-hidden="true"></i><span>{{ $isPhysicallyOpened ? 'Aún no hay paquetes asociados en IPS.' : 'IPS aún no muestra paquetes asociados a esta saca.' }}</span></div>
                        @endif
                    </div>
                </section>


            </main>

        </div>

        @if($isPhysicallyOpened || ($errors->any() && !$isReceivedClosed))
            {!! $__env->make('paquetes_ips.partials.verification-notes', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render() !!}
        @endif

    @endif
</div>
@endsection

@section('css')
<style>
.ips-receptacle-page{color:#294c72}
.ips-receptacle-toolbar{display:flex;align-items:center;gap:14px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #edf1f5}
.ips-receptacle-toolbar__title{display:flex;flex:0 0 175px;flex-direction:column;gap:3px}
.ips-receptacle-toolbar__title small{color:#72839a;font-size:.62rem;font-weight:800;letter-spacing:.08em}
.ips-receptacle-toolbar__title strong{color:#24496f;font-size:.84rem}
.ips-receptacle-toolbar__search{flex:1;min-width:0}
.ips-receptacle-toolbar__actions{position:relative;z-index:5;display:flex;align-items:center;gap:8px;padding-left:12px;border-left:1px solid #e2eaf2}
.ips-preopen-damage{position:relative;display:inline-flex}
.ips-preopen-damage>summary{position:relative;display:grid;place-items:center;width:30px;height:30px;border:1px solid #f0d478;border-radius:50%;background:#fff8df;color:#a87700;cursor:pointer;list-style:none;transition:background .15s ease,box-shadow .15s ease}
.ips-preopen-damage>summary::-webkit-details-marker{display:none}
.ips-preopen-damage>summary:hover,.ips-preopen-damage[open]>summary{background:#ffefb5;box-shadow:0 0 0 3px rgba(231,183,49,.16)}
.ips-preopen-damage>summary:focus-visible{outline:2px solid #24538e;outline-offset:2px}
.ips-preopen-damage>summary>i{font-size:.86rem}
.ips-preopen-damage>summary>span{position:absolute;top:-5px;right:-5px;display:grid;place-items:center;min-width:16px;height:16px;padding:0 3px;border:1px solid #fff;border-radius:10px;background:#c23b32;color:#fff;font-size:.58rem;font-weight:700}
.ips-preopen-damage__popover{position:absolute;top:calc(100% + 9px);right:0;z-index:1060;width:min(760px,calc(100vw - 36px));max-height:78vh;overflow:auto;padding:12px;border:1px solid #dce6f1;border-radius:10px;background:#f7faff;box-shadow:0 12px 32px rgba(22,49,79,.22);text-align:left}
.ips-preopen-damage__heading{display:flex;flex-direction:column;gap:3px;padding:2px 3px 10px}
.ips-preopen-damage__heading strong{color:#355777;font-size:.84rem}
.ips-preopen-damage__heading small{color:#74879d;font-size:.72rem;line-height:1.4}
.ips-preopen-damage__popover .ips-verification-notes{margin:0!important}
.ips-preopen-damage__popover .ips-verification-notes>.card-header{padding:8px 10px}
.ips-preopen-damage__popover .ips-verification-notes>.card-header h2{font-size:.78rem}
.ips-preopen-damage__popover .ips-verification-notes>.card-header small{font-size:.68rem}
.ips-preopen-damage__popover .ips-verification-notes>.card-body{padding:10px}
.ips-receptacle-search .card-body{padding:10px 14px}
.ips-receptacle-search__form{display:flex;align-items:center;gap:8px}
.ips-receptacle-search__form--inline{margin-bottom:0;padding-bottom:0;border-bottom:0}
.ips-receptacle-search__form .form-control{flex:1 1 0%;width:auto;min-width:0}
.ips-receptacle-search__form .btn{flex:0 0 auto}
.ips-receptacle-summary h1{overflow-wrap:anywhere}
.ips-receptacle-stats{padding:10px 0;border:1px solid #e2eaf2;border-radius:8px;background:#f8fafd}
.ips-receptacle-stats>.row{margin:0}
.ips-receptacle-stats .ips-receptacle-stat{min-height:40px;padding:2px 13px}
.ips-receptacle-stat{display:flex;flex-direction:column;gap:2px;margin-top:0}
.ips-receptacle-stat small,.ips-receptacle-counts small{color:#72839a;font-size:.72rem}
.ips-receptacle-stat strong,.ips-receptacle-counts strong{color:#183c69;font-size:.95rem}
.ips-receptacle-counts{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px 16px;margin-bottom:14px;padding:12px 14px;border:1px solid #e2eaf2;border-radius:8px;background:#f8fafd}
.ips-receptacle-counts>div{display:flex;flex-direction:column;gap:2px}
.ips-receptacle-counts p{grid-column:1/-1;margin:0;padding-top:8px;border-top:1px solid #e2eaf2;color:#647991;font-size:.78rem}
.ips-receptacle-table{font-size:.82rem}
.ips-receptacle-table th{white-space:nowrap;color:#526b85}
.ips-receptacle-empty{display:flex;align-items:center;gap:10px;padding:14px;border:1px dashed #d5e0eb;border-radius:8px;color:#70839a;font-size:.84rem}
.ips-receptacle-empty i{color:#9aabba;font-size:1.15rem}
.ips-receptacle-layout>main{min-width:0}
.ips-receptacle-flow{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;list-style:none;margin:12px 0 0;padding:9px 11px;border:1px solid #e2eaf2;border-radius:9px;background:#f8fafd}
.ips-receptacle-flow li{display:flex;align-items:center;gap:9px;min-width:0}
.ips-receptacle-flow li>span{display:grid;place-items:center;flex:0 0 25px;height:25px;border-radius:50%;background:#e9eef4;color:#60758c;font-size:.7rem;font-weight:800}
.ips-receptacle-flow li.is-done>span{background:#dff2e8;color:#20834f}
.ips-receptacle-flow li.is-current>span{background:#24538e;color:#fff}
.ips-receptacle-flow li>div{display:flex;flex-direction:column;gap:2px;min-width:0}
.ips-receptacle-flow strong{color:#8292a5;font-size:.76rem}
.ips-receptacle-flow li.is-done strong{color:#315e4a}
.ips-receptacle-flow li.is-current strong{color:#183c69}
.ips-receptacle-next-step{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-top:10px;padding:11px 13px;border:1px solid #dce6f1;border-left:4px solid #24538e;border-radius:9px;background:#f7faff}
.ips-receptacle-next-step--compact{justify-content:flex-end;gap:0;padding:0;border:0;background:transparent}
.ips-receptacle-next-step--compact .ips-receptacle-next-step__action{flex:0 1 240px;max-width:260px}
.ips-receptacle-next-step__copy{display:flex;flex-direction:column;gap:3px;min-width:0}
.ips-receptacle-next-step__copy small{color:#7188a2;font-size:.65rem;font-weight:800;letter-spacing:.04em}
.ips-receptacle-next-step__copy strong{color:#24496f;font-size:.86rem}
.ips-receptacle-next-step__copy span{color:#6c8096;font-size:.73rem;line-height:1.4}
.ips-receptacle-next-step__action{flex:0 0 230px;max-width:280px}
.ips-receptacle-next-step__action .btn{white-space:normal}
.ips-receptacle-next-note{display:flex;flex-direction:column;gap:3px;margin-top:10px;padding:9px 12px;border-left:3px solid #90a9c4;border-radius:4px;background:#f6f8fb}
.ips-receptacle-next-note strong{color:#355777;font-size:.78rem}
.ips-receptacle-next-note span{color:#6c8096;font-size:.72rem;line-height:1.4}
@media(max-width:767.98px){.ips-receptacle-flow{grid-template-columns:1fr;gap:9px}.ips-receptacle-next-step{align-items:stretch;flex-direction:column}.ips-receptacle-next-step__action{flex:1 1 auto;max-width:none}}
@media(min-width:992px){.ips-receptacle-stats .ips-receptacle-stat+.ips-receptacle-stat{border-left:1px solid #e2eaf2}}
@media(max-width:991.98px){.ips-receptacle-stats .ips-receptacle-stat:nth-child(even){border-left:1px solid #e2eaf2}.ips-receptacle-stats .ips-receptacle-stat:nth-child(n+3){margin-top:8px;padding-top:8px;border-top:1px solid #e2eaf2}}
@media(max-width:767.98px){.ips-receptacle-toolbar{flex-wrap:wrap;gap:9px}.ips-receptacle-toolbar__title{flex:1 1 auto}.ips-receptacle-toolbar__search{order:3;flex:1 1 100%}.ips-receptacle-toolbar__actions{margin-left:auto}}
@media(max-width:575.98px){.ips-receptacle-search__form{align-items:stretch;flex-direction:column}.ips-receptacle-search__form .btn{width:100%}.ips-receptacle-toolbar .ips-receptacle-search__form--inline{align-items:center;flex-direction:row}.ips-receptacle-toolbar__search .btn{width:auto;white-space:nowrap}.ips-receptacle-toolbar__actions{padding-left:0;border-left:0}.ips-receptacle-stat strong{font-size:.84rem}}</style>
@endsection

{!! $__env->make('adminlte::page', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render() !!}
