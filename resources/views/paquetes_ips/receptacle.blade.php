

@section('title', $title)

@section('content')
<div class="container-fluid py-3 ips-receptacle-page">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($error)<div class="alert alert-warning" role="alert">{{ $error }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($isPending)<div class="alert alert-info" role="note">La respuesta está pendiente. Reintentar consulta la misma operación con la misma clave; no crea otro movimiento.</div>@endif

    @php
        $hasReceptacle = $detail && isset($detail['receptacle']);
    @endphp
    @if(! ($hasReceptacle))
        @php
            $queueTitle = match ($activeReceptacleQueue) {
                'opening' => 'Sacas recibidas pendientes de apertura',
                'registration' => 'Sacas abiertas para registrar paquetes',
                default => 'Sacas previstas pendientes de recepción',
            };
            $queueDescription = match ($activeReceptacleQueue) {
                'opening' => 'La recepción ya está registrada en esta oficina. Falta abrir físicamente cada saca.',
                'registration' => 'Sacas abiertas en esta oficina. Compara los paquetes asociados con la cantidad esperada y continúa su registro.',
                default => 'Prealertas PREDES dirigidas a esta oficina que aún no tienen registrada la recepción física.',
            };
            $queueRows = $receptacleQueueData;
            $queueTotal = (int) ($receptacleQueueMeta['total'] ?? count($queueRows));
            $queuePage = max(1, (int) ($receptacleQueueMeta['page'] ?? 1));
            $queuePerPage = max(1, (int) ($receptacleQueueMeta['per_page'] ?? 25));
            $queueLastPage = max(1, (int) ceil($queueTotal / $queuePerPage));
            $queueFirstItem = $queueTotal > 0 ? (($queuePage - 1) * $queuePerPage) + 1 : 0;
            $queueLastItem = min($queuePage * $queuePerPage, $queueTotal);
            $queuePageItems = [];
            $searchStep = in_array($activeReceptacleQueue, ['opening', 'registration'], true) ? 'abrir' : 'recibir';
            if ($queueLastPage <= 12 && $queueLastPage > 0) {
                $queuePageItems = range(1, $queueLastPage);
            } elseif ($queueLastPage > 12) {
                $queueWindowStart = $queuePage <= 6 ? 1 : ($queuePage >= $queueLastPage - 5 ? $queueLastPage - 9 : $queuePage - 4);
                $queueWindowEnd = min($queueLastPage - 2, $queueWindowStart + 9);
                if ($queueWindowStart > 1) {
                    $queuePageItems = [1, 2];
                    if ($queueWindowStart > 3) $queuePageItems[] = '…';
                }
                $queuePageItems = array_merge($queuePageItems, range($queueWindowStart, $queueWindowEnd));
                if ($queueWindowEnd < $queueLastPage - 2) $queuePageItems[] = '…';
                $queuePageItems = array_merge($queuePageItems, [$queueLastPage - 1, $queueLastPage]);
            }
        @endphp
        <section class="card border-0 shadow-sm ips-receptacle-empty-workbench">
            <header class="ips-receptacle-hero">
                <div class="ips-receptacle-hero__title">
                    <small>GESTIÓN INTERNACIONAL</small>
                    <h1>Recepción de sacas</h1>
                    <p>{{ $queueDescription }}</p>
                    <div class="ips-receptacle-hero__office">
                        <span>Oficina IPS</span>
                        <strong>{{ $link->ips_office_name ?: 'Oficina asignada' }}</strong>
                    </div>
                </div>
                @if($activeReceptacleQueue === 'registration')
                    <div class="ips-receptacle-hero-search ips-receptacle-registration-link">
                        <strong>¿Tienes una saca abierta?</strong>
                        <span>Escanea cada paquete para registrarlo y asociarlo a su saca.</span>
                        <a class="btn btn-warning" href="{{ route('internacional.section', ['section' => 'recepcion-cambio']) }}"><i class="fas fa-barcode mr-1" aria-hidden="true"></i>Ir al registro de paquetes</a>
                    </div>
                @else
                    <form method="GET" action="{{ route('internacional.sacas', ['paso' => $searchStep, 'queue' => $activeReceptacleQueue]) }}" class="ips-receptacle-hero-search">
                        <label for="sack-code">{{ $activeReceptacleQueue === 'opening' ? 'Busca una saca recibida' : 'Escanea la saca que llegó a la oficina' }}</label>
                        <div class="ips-receptacle-hero-search__controls">
                            <input id="sack-code" name="q" class="form-control" maxlength="35" value="{{ $codigo }}" placeholder="Marbete de la saca" autocomplete="off" required autofocus>
                            <button class="btn btn-warning" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i>Buscar</button>
                        </div>
                        <small>{{ $activeReceptacleQueue === 'opening' ? 'IPS confirmará si esta oficina puede registrar la apertura.' : 'IPS confirmará si esta oficina puede registrar la recepción.' }}</small>
                    </form>
                @endif
            </header>

            <div class="card-body ips-receptacle-empty-body">
                <nav class="ips-receptacle-queues" aria-label="Etapas de recepción de sacas">
                    <a class="{{ $activeReceptacleQueue === 'expected' ? 'is-active' : '' }}" href="{{ route('internacional.sacas', ['paso' => 'recibir', 'queue' => 'expected']) }}" @if($activeReceptacleQueue === 'expected') aria-current="page" @endif>
                        <span>1</span><strong>Por recibir</strong>
                    </a>
                    <a class="{{ $activeReceptacleQueue === 'opening' ? 'is-active' : '' }}" href="{{ route('internacional.sacas', ['paso' => 'abrir', 'queue' => 'opening']) }}" @if($activeReceptacleQueue === 'opening') aria-current="page" @endif>
                        <span>2</span><strong>Pendientes de apertura</strong>
                    </a>
                    <a class="{{ $activeReceptacleQueue === 'registration' ? 'is-active' : '' }}" href="{{ route('internacional.sacas', ['paso' => 'abrir', 'queue' => 'registration']) }}" @if($activeReceptacleQueue === 'registration') aria-current="page" @endif>
                        <span>3</span><strong>Registrar paquetes</strong>
                    </a>
                </nav>

                <section class="ips-pending-receptacles" aria-labelledby="pending-receptacles-title">
                        <div class="ips-pending-receptacles__heading">
                            <div>
                                <h2 id="pending-receptacles-title">{{ $queueTitle }} en {{ $pendingReceptacleRegionalLabel }}</h2>
                                <p>{{ $queueDescription }} Oficina IPS: {{ $link->ips_office_name ?: 'Oficina asignada' }}.</p>
                            </div>
                            <span class="badge badge-primary">{{ $queueTotal }} sacas</span>
                        </div>

                        @if($receptacleQueueError)
                            <div class="alert alert-warning mb-0" role="status">{{ $receptacleQueueError }}</div>
                        @endif
                        @if(count($queueRows))
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 ips-pending-receptacles__table">
                                    @if($activeReceptacleQueue === 'expected')
                                        <thead><tr><th>Marbete</th><th>Despacho</th><th>Origen</th><th>Paquetes en prealerta</th><th>Acción</th></tr></thead>
                                    @elseif($activeReceptacleQueue === 'opening')
                                        <thead><tr><th>Marbete</th><th>Recepción registrada</th><th>Espera</th><th>Peso</th><th>Paquetes declarados</th><th>Acción</th></tr></thead>
                                    @else
                                        <thead><tr><th>Marbete</th><th>Apertura registrada</th><th>Peso</th><th>Paquetes registrados / esperados</th><th>Estado</th><th>Acción</th></tr></thead>
                                    @endif
                                    <tbody>
                                        @foreach($queueRows as $receptacleRow)
                                            <tr>
                                                <td><strong>{{ $receptacleRow['codigo'] ?? 'Sin código' }}</strong></td>
                                                @if($activeReceptacleQueue === 'expected')
                                                    <td>{{ $receptacleRow['despacho'] ?: 'Sin despacho' }}
                                                        @if(!empty($receptacleRow['dispatch_closed_at']))
                                                            <small class="ips-pending-receptacles__count-source">Prealerta cerrada el {{ \Illuminate\Support\Carbon::parse($receptacleRow['dispatch_closed_at'])->timezone('America/La_Paz')->format('d/m/Y') }} · hace {{ \Illuminate\Support\Carbon::parse($receptacleRow['dispatch_closed_at'])->diffInDays(now()) }} días</small>
                                                        @endif
                                                    </td>
                                                    <td>{{ $receptacleRow['origin_ctci'] ?: '—' }}</td>
                                                    <td><strong>{{ $receptacleRow['items_count'] ?? 'No informados' }}</strong>
                                                        @if(!empty($receptacleRow['items_count_source']))<small class="ips-pending-receptacles__count-source">{{ $receptacleRow['items_count_source'] }}</small>@endif
                                                    </td>
                                                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('internacional.sacas', ['paso' => 'recibir', 'queue' => 'expected', 'q' => $receptacleRow['codigo']]) }}"><i class="fas fa-inbox mr-1" aria-hidden="true"></i>Registrar recepción</a></td>
                                                @elseif($activeReceptacleQueue === 'opening')
                                                    <td>{{ !empty($receptacleRow['event_at']) ? \Illuminate\Support\Carbon::parse($receptacleRow['event_at'])->timezone('America/La_Paz')->format('d/m/Y H:i') : '—' }}</td>
                                                    <td>{{ !empty($receptacleRow['event_at']) ? \Illuminate\Support\Carbon::parse($receptacleRow['event_at'])->diffInDays(now()) . ' días' : '—' }}</td>
                                                    <td>{{ is_numeric($receptacleRow['weight_kg'] ?? null) ? number_format((float) $receptacleRow['weight_kg'], 3, ',', '.') . ' kg' : '—' }}</td>
                                                    <td><strong>{{ $receptacleRow['items_count'] ?? 'No informados' }}</strong>
                                                        @if(!empty($receptacleRow['items_count_source']))<small class="ips-pending-receptacles__count-source">{{ $receptacleRow['items_count_source'] }}</small>@endif
                                                    </td>
                                                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('internacional.sacas', ['paso' => 'abrir', 'queue' => 'opening', 'q' => $receptacleRow['codigo']]) }}"><i class="fas fa-box-open mr-1" aria-hidden="true"></i>Revisar y abrir</a></td>
                                                @else
                                                    <td>{{ !empty($receptacleRow['event_at']) ? \Illuminate\Support\Carbon::parse($receptacleRow['event_at'])->timezone('America/La_Paz')->format('d/m/Y H:i') : '—' }}</td>
                                                    <td>{{ is_numeric($receptacleRow['weight_kg'] ?? null) ? number_format((float) $receptacleRow['weight_kg'], 3, ',', '.') . ' kg' : '—' }}</td>
                                                    <td><strong>{{ $receptacleRow['registered_items_count'] }}</strong> / {{ $receptacleRow['declared_items_count'] ?? 'Sin cantidad esperada' }}
                                                        @if(!empty($receptacleRow['items_count_source']))<small class="ips-pending-receptacles__count-source">{{ $receptacleRow['items_count_source'] }}</small>@endif
                                                    </td>
                                                    <td><span class="badge {{ $receptacleRow['registration_status'] === 'Conteo alcanzado' ? 'badge-success' : ($receptacleRow['registration_status'] === 'Revisar diferencia' ? 'badge-warning' : 'badge-info') }}">{{ $receptacleRow['registration_status'] }}</span>
                                                        @if($receptacleRow['remaining_items_count'] !== null && $receptacleRow['remaining_items_count'] > 0)<small class="ips-pending-receptacles__count-source">Faltan {{ $receptacleRow['remaining_items_count'] }}</small>@endif
                                                    </td>
                                                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('internacional.section', ['section' => 'recepcion-cambio', 'receptacle_pid' => $receptacleRow['id']]) }}"><i class="fas fa-barcode mr-1" aria-hidden="true"></i>Registrar paquetes</a></td>
                                                    @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <nav class="ips-pending-receptacles__pagination" aria-label="Paginación de sacas pendientes">
                                <span>Mostrando {{ $queueFirstItem }} a {{ $queueLastItem }} de {{ $queueTotal }} sacas</span>
                                @if($queueLastPage > 1)
                                    <div class="ips-pending-receptacles__page-links">
                                        @if($queuePage > 1)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('internacional.sacas', ['paso' => $searchStep, 'queue' => $activeReceptacleQueue, 'page' => $queuePage - 1]) }}" aria-label="Página anterior">&lsaquo;</a>
                                        @endif
                                        @if($queuePage <= 1)
                                            <span class="btn btn-sm btn-outline-primary disabled" aria-disabled="true" aria-label="Página anterior">&lsaquo;</span>
                                        @endif
                                        @foreach($queuePageItems as $queuePageItem)
                                            @if($queuePageItem === '…')
                                                <span class="ips-pending-receptacles__ellipsis" aria-hidden="true">…</span>
                                            @endif
                                            @if($queuePageItem !== '…')
                                                <a class="btn btn-sm {{ $queuePageItem === $queuePage ? 'btn-warning' : 'btn-outline-primary' }}" href="{{ route('internacional.sacas', ['paso' => $searchStep, 'queue' => $activeReceptacleQueue, 'page' => $queuePageItem]) }}" @if($queuePageItem === $queuePage) aria-current="page" @endif>{{ $queuePageItem }}</a>
                                            @endif
                                        @endforeach
                                        @if($queuePage < $queueLastPage)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('internacional.sacas', ['paso' => $searchStep, 'queue' => $activeReceptacleQueue, 'page' => $queuePage + 1]) }}" aria-label="Página siguiente">&rsaquo;</a>
                                        @endif
                                        @if($queuePage >= $queueLastPage)
                                            <span class="btn btn-sm btn-outline-primary disabled" aria-disabled="true" aria-label="Página siguiente">&rsaquo;</span>
                                        @endif
                                    </div>
                                @endif
                            </nav>
                        @endif
                        @if(!$receptacleQueueError && !count($queueRows))
                            <div class="ips-receptacle-empty-state" role="status">
                                <span aria-hidden="true"><i class="fas fa-check"></i></span>
                                <div>
                                    @if($activeReceptacleQueue === 'expected')
                                        <strong>No hay sacas PREDES pendientes de recepción</strong>
                                        <p>Las sacas dirigidas a esta oficina aparecerán cuando IPS tenga una prealerta PREDES sin evento de recepción local.</p>
                                    @elseif($activeReceptacleQueue === 'opening')
                                        <strong>No hay sacas recibidas pendientes de apertura</strong>
                                        <p>Las sacas recibidas en esta oficina aparecerán aquí hasta que registres su apertura física.</p>
                                    @else
                                        <strong>No hay sacas abiertas disponibles</strong>
                                        <p>Al abrir una saca en esta oficina, aparecerá aquí el avance de paquetes asociados y esperados.</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                </section>
            </div>
        </section>
    @endif

    @if($hasReceptacle)
        @php
            $receptacle = $detail['receptacle'];
            $items = $detail['items'] ?? [];
            $linkedItemsCount = (int) ($detail['linked_items_count'] ?? count($items));
            $declaredItemsCount = $receptacle['items_count'] ?? null;
            $canReceiveHere = in_array('RECEPTACLE_RECEIVE', $receptacle['allowed_actions'] ?? [], true);
            $canOpenHere = in_array('RECEPTACLE_OPEN', $receptacle['allowed_actions'] ?? [], true);
            $isReceivedClosed = (int) $receptacle['state_cd'] === 22 && in_array((int) $receptacle['event_cd'], [130, 133], true);
            $isSentClosed = (int) $receptacle['state_cd'] === 22 && (int) $receptacle['event_cd'] === 132;
            $isPhysicallyOpened = (int) $receptacle['state_cd'] === 20 && (int) $receptacle['event_cd'] === 131;
            $isCurrentReceptacleOffice = (int) ($receptacle['office_cd'] ?? 0) === (int) $link->ips_office_cd;
            $operationStep = !$isPending && $paso === 'recibir' && $canOpenHere ? 'abrir' : $paso;
            $showOpenAction = !$isPending && $operationStep === 'abrir' && $isReceivedClosed && $isCurrentReceptacleOffice && $canOpenHere;
            $showReceiveAction = !$isPending && $operationStep === 'recibir' && $canReceiveHere;
            $showPackageAction = $isPhysicallyOpened && $isCurrentReceptacleOffice;
            $receiveExpectedEventCode = $isPending ? (int) $expectedReceptacleEventCode : 0;
            $receiveEventCode = $receiveExpectedEventCode === 132 || (int) $receptacle['event_cd'] === 132 ? 133 : 130;
            $operationEventCode = $operationStep === 'abrir' ? 131 : $receiveEventCode;
            $receptionRecorded = $isReceivedClosed || $isPhysicallyOpened;
        @endphp

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
.ips-receptacle-empty-workbench{overflow:hidden;border:1px solid #dce6f1;border-radius:12px}
.ips-receptacle-hero{display:grid;grid-template-columns:minmax(260px,.85fr) minmax(420px,1.15fr);align-items:center;gap:28px;padding:22px 26px;background:#24538e;color:#fff}
.ips-receptacle-hero__title{min-width:0}
.ips-receptacle-hero__title>small{display:block;margin-bottom:4px;color:rgba(255,255,255,.78);font-size:.68rem;font-weight:800;letter-spacing:.09em}
.ips-receptacle-hero__title h1{margin:0;color:#fff;font-size:1.35rem;font-weight:800}
.ips-receptacle-hero__title p{margin:5px 0 0;color:rgba(255,255,255,.86);font-size:.82rem}
.ips-receptacle-hero__office{display:inline-flex;align-items:center;gap:8px;max-width:100%;margin-top:12px;padding:6px 10px;border:1px solid rgba(255,255,255,.3);border-radius:18px;background:rgba(255,255,255,.08)}
.ips-receptacle-hero__office span{color:rgba(255,255,255,.76);font-size:.68rem}
.ips-receptacle-hero__office strong{max-width:280px;overflow:hidden;color:#fff;font-size:.75rem;text-overflow:ellipsis;white-space:nowrap}
.ips-receptacle-hero-search{min-width:0}
.ips-receptacle-hero-search>label{display:block;margin:0 0 7px;color:#fff;font-size:.8rem;font-weight:700}
.ips-receptacle-hero-search__controls{display:flex;align-items:stretch;gap:8px}
.ips-receptacle-hero-search__controls .form-control{flex:1 1 auto;width:auto;min-width:0;height:44px;padding:9px 13px;border:1px solid #d9e2ed;border-radius:8px;font-size:.9rem}
.ips-receptacle-hero-search__controls .btn{flex:0 0 auto;min-height:44px;padding:9px 18px;font-weight:700;white-space:nowrap}
.ips-receptacle-hero-search>small{display:block;margin-top:7px;color:rgba(255,255,255,.78);font-size:.72rem}
.ips-receptacle-empty-body{padding:16px 20px 18px}
.ips-receptacle-empty-flow{margin:0 0 12px}
.ips-receptacle-empty-flow li.is-current strong{font-weight:800}
.ips-receptacle-empty-state{display:flex;align-items:center;gap:11px;padding:12px 14px;border:1px dashed #cbd9e8;border-radius:8px;background:#fff;color:#71839a}
.ips-receptacle-empty-state>span{display:grid;flex:0 0 34px;height:34px;place-items:center;border-radius:8px;background:#eef3f8;color:#7890a9}
.ips-receptacle-empty-state strong{display:block;color:#526b85;font-size:.8rem}
.ips-receptacle-empty-state p{margin:2px 0 0;font-size:.74rem}
.ips-receptacle-registration-link{display:flex;flex-direction:column;align-items:flex-start;gap:6px}
.ips-receptacle-registration-link>strong{color:#fff;font-size:.9rem}
.ips-receptacle-registration-link>span{color:rgba(255,255,255,.82);font-size:.76rem}
.ips-receptacle-queues{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:0 0 15px}
.ips-receptacle-queues a{display:flex;align-items:center;gap:9px;min-width:0;padding:10px 12px;border:1px solid #dce6f1;border-radius:8px;background:#fff;color:#526b85;text-decoration:none}
.ips-receptacle-queues a>span{display:grid;flex:0 0 26px;height:26px;place-items:center;border-radius:50%;background:#edf2f7;color:#627c98;font-size:.72rem;font-weight:800}
.ips-receptacle-queues a>strong{font-size:.78rem}
.ips-receptacle-queues a.is-active{border-color:#24538e;background:#24538e;color:#fff}
.ips-receptacle-queues a.is-active>span{background:#fff;color:#24538e}
.ips-pending-receptacles{padding-top:4px}
.ips-pending-receptacles__heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
.ips-pending-receptacles__heading h2{margin:0;color:#24496f;font-size:.95rem;font-weight:800}
.ips-pending-receptacles__heading p{margin:3px 0 0;color:#71839a;font-size:.75rem}
.ips-pending-receptacles__heading .badge{flex:0 0 auto;padding:7px 9px}
.ips-pending-receptacles__table{font-size:.82rem}
.ips-pending-receptacles__table thead th{border-top:0;background:#eef2f7;color:#526b85;font-size:.72rem;white-space:nowrap}
.ips-pending-receptacles__table td{vertical-align:middle}
.ips-pending-receptacles__table td strong{color:#24538e}
.ips-pending-receptacles__count-source{display:block;color:#71839a;font-size:.7rem;margin-top:2px}
.ips-pending-receptacles__pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:10px;color:#71839a;font-size:.74rem}
.ips-pending-receptacles__page-links{display:flex;align-items:center;flex-wrap:wrap;gap:6px}
.ips-pending-receptacles__page-links .btn{min-width:34px;padding:.25rem .55rem}
.ips-pending-receptacles__page-links .btn-warning{color:#173f70}
.ips-pending-receptacles__ellipsis{padding:0 3px;color:#71839a}
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
@media(max-width:991.98px){.ips-receptacle-hero{grid-template-columns:1fr;gap:18px}}
@media(max-width:575.98px){.ips-receptacle-hero{padding:18px 16px}.ips-receptacle-hero-search__controls{align-items:stretch;flex-direction:column}.ips-receptacle-hero-search__controls .btn{width:100%}.ips-receptacle-empty-body{padding:13px}.ips-receptacle-queues{grid-template-columns:1fr}.ips-receptacle-queues a{padding:8px 10px}}
@media(max-width:575.98px){.ips-pending-receptacles__heading{align-items:flex-start;flex-direction:column}.ips-pending-receptacles__pagination{align-items:flex-start;flex-direction:column}}
@media(min-width:992px){.ips-receptacle-stats .ips-receptacle-stat+.ips-receptacle-stat{border-left:1px solid #e2eaf2}}
@media(max-width:991.98px){.ips-receptacle-stats .ips-receptacle-stat:nth-child(even){border-left:1px solid #e2eaf2}.ips-receptacle-stats .ips-receptacle-stat:nth-child(n+3){margin-top:8px;padding-top:8px;border-top:1px solid #e2eaf2}}
@media(max-width:767.98px){.ips-receptacle-toolbar{flex-wrap:wrap;gap:9px}.ips-receptacle-toolbar__title{flex:1 1 auto}.ips-receptacle-toolbar__search{order:3;flex:1 1 100%}.ips-receptacle-toolbar__actions{margin-left:auto}}
@media(max-width:575.98px){.ips-receptacle-search__form{align-items:stretch;flex-direction:column}.ips-receptacle-search__form .btn{width:100%}.ips-receptacle-toolbar .ips-receptacle-search__form--inline{align-items:center;flex-direction:row}.ips-receptacle-toolbar__search .btn{width:auto;white-space:nowrap}.ips-receptacle-toolbar__actions{padding-left:0;border-left:0}.ips-receptacle-stat strong{font-size:.84rem}}</style>
@endsection

{!! $__env->make('adminlte::page', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render() !!}
