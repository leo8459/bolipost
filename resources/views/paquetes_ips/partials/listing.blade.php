@if($error)<div class="alert alert-warning" role="alert">{{ $error }}</div>@endif
@php
    $pendingDelivery = ($warehouseView ?? null) === 'por-entregar';
    $searchPurpose = ($deliveryOnly ?? false) ? 'entregar' : (in_array($workflowAction ?? '', ['EMR', 'EMG', 'CUSTOMS_RETURN'], true) ? 'recibir' : '');
@endphp
<section class="ips-panel">
                <div class="ips-panel-header"><h2>{{ $pageTitle ?? 'Paquetes de tu oficina' }}</h2><span>{{ $error ? 'Consulta no disponible' : $packages->total().' resultados' }}</span></div>
                <form method="GET" action="{{ $listingUrl ?? route('ips.index', \App\Support\IpsOfficeContext::parameters(request(), [])) }}" class="ips-search {{ $searchPurpose ? 'ips-search--operation' : '' }}">
                    @if(empty($combinedOffices) && $link?->ips_office_cd !== null)<input type="hidden" name="office_cd" value="{{ $link->ips_office_cd }}">@endif
                    @if($pendingDelivery)
                        <label for="ips-code" class="ips-search-label">Escanea un código o busca por destinatario o dirección</label>
                        <input id="ips-code" type="search" name="recipient_address_q" minlength="2" maxlength="100" value="{{ $recipientSearch ?? '' }}" placeholder="Código, destinatario o dirección…" class="form-control" autocomplete="off" data-search-on-paste>
                    @else
                        <label for="ips-code" class="{{ $searchPurpose ? 'ips-search-label' : 'sr-only' }}">{{ $searchPurpose ? 'Escanea o escribe el código del paquete para '.$searchPurpose : 'Código de seguimiento o identificador local exacto' }}</label>
                        <input id="ips-code" type="search" name="q" maxlength="35" value="{{ $search }}" placeholder="Escanea o escribe un código…" class="form-control" autocomplete="off">
                    @endif
                    @if($preferredReceptaclePid ?? null)<input type="hidden" name="receptacle_pid" value="{{ $preferredReceptaclePid }}">@endif
                    <button type="submit" class="btn btn-primary ips-search-submit"><i class="fas fa-search mr-2" aria-hidden="true"></i><span>{{ $pendingDelivery ? 'Buscar' : ($searchPurpose ? 'Buscar para '.$searchPurpose : 'Buscar') }}</span></button>
                    @if($pendingDelivery ? ($recipientSearch ?? false) : $search)<a href="{{ $listingClearUrl ?? $listingUrl ?? route('ips.index', \App\Support\IpsOfficeContext::parameters(request(), [])) }}" class="btn btn-light">Limpiar</a>@endif
                </form>
                @if($pendingDelivery)
                    <p class="ips-footnote px-3 pt-0" role="note">Buscar solo consulta: destinatario o dirección en SIOP y código en IPS. Para agregar un resultado, usa la acción de su fila; el escaneo rápido con lector y Enter lo agrega directamente.</p>
                @endif
                @if($workflowHint ?? null)<p class="ips-footnote px-3 pt-0" role="note">{{ $workflowHint }}</p>@endif
                @unless($isInternationalPage ?? false)
                <nav class="ips-tabs" aria-label="Filtrar paquetes">
                    @foreach(['all'=>'Todos', 'reception'=>'Por recibir', 'pending'=>'Para retiro / reparto', 'returns'=>'Devoluciones IPS', 'delivered'=>'Entregados'] as $value=>$label)
                        <a class="{{ $stage===$value?'is-active':'' }}" href="{{ route('ips.index', \App\Support\IpsOfficeContext::parameters(request(), ['stage'=>$value,'q'=>$search])) }}" @if($stage===$value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                @endunless
                <div class="table-responsive">
                    <table @class(['table ips-table', 'ips-table--pending-delivery' => $pendingDelivery])>
                        <thead>
                            <tr>
                                <th scope="col">Código / ID local</th>
                                <th scope="col">Destinatario</th>
                                <th scope="col">Teléfono</th>
                                @if($pendingDelivery)
                                    <th scope="col">Ciudad</th>
                                    <th scope="col" class="ips-address-column">Dirección</th>
                                @else
                                    <th scope="col">Ciudad / dirección</th>
                                @endif
                                <th scope="col">{{ ($workflowAction ?? '') === 'EMG' ? 'Oficina de origen → entrega' : 'Oficina actual → destino' }}</th>
                                <th scope="col">Peso / clase</th>
                                <th scope="col">Tributable</th>
                                <th scope="col">Etapa / último movimiento</th>
                                <th scope="col">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($packages as $package)
                            @php
                                $address = $package['address'] ?? null;
                                $packageEvent = (int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0);
                                $displayStageLabel = ($workflowAction ?? '') === 'EMG'
                                    ? match ($packageEvent) {
                                        13 => 'En saca nacional · en tránsito',
                                        35, 72 => 'Despachado hacia esta oficina',
                                        default => $package['stage']['label'],
                                    }
                                    : $package['stage']['label'];
                            @endphp
                            <tr>
                                <td><strong class="ips-code">{{ $package['codigo'] }}</strong><small>{{ $package['local_id'] ?: 'Sin ID local' }}</small></td>
                                <td>{{ $package['recipient'] ?: 'No registrado' }}</td>
                                <td>{{ $package['phone'] ?: 'No registrado' }}</td>
                                @if($pendingDelivery)
                                    <td>{{ $package['city'] ?: 'No registrada' }}</td>
                                    <td class="ips-address-column">
                                        @if(filled($address))
                                            <div class="ips-address">
                                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                                <span>{{ $address }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted">Sin dirección registrada</span>
                                        @endif
                                    </td>
                                @else
                                    <td>{{ $package['city'] ?: 'No registrada' }}<small>{{ filled($address) ? $address : 'Sin dirección registrada' }}</small></td>
                                @endif
                                <td>{{ $package['office_name'] ?: 'Sin oficina' }}@if($package['next_office_name'])<small>→ {{ $package['next_office_name'] }}</small>@endif @if(!empty($package['workbench_office_name']))<small class="font-weight-bold">{{ $package['workbench_office_name'] }} · {{ $package['workbench_office_fcd'] ?? '' }}</small>@endif</td>
                                <td>{{ $package['weight_kg'] !== null ? number_format($package['weight_kg'], 3, ',', '.') . ' kg' : 'Sin peso' }}<small>Clase {{ $package['mail_class'] ?: 'no registrada' }}</small></td>
                                <td>{{ match((string)($package['dutiable_ind'] ?? '')) {'Y','1'=>'Sí','N','0'=>'No',default=>'No registrado'} }}</td>
                                <td><span class="badge badge-{{ $package['stage']['tone'] }}">{{ $displayStageLabel }}</span><small>{{ !empty($package['event_at_local']) ? \Illuminate\Support\Carbon::parse($package['event_at_local'])->format('d/m/Y H:i') : 'Hora local no informada' }}</small>@if(!empty($package['reception_block_reason']))<small>Evento {{ $package['operational_event_cd'] ?? $package['event_cd'] }} · estado IPS {{ $package['state_cd'] ?? 'no informado' }}</small>@endif</td>
                                <td>
                                    @if($readOnly ?? false)<span class="text-muted">Solo consulta</span>
                                    @elseif(($workflowAction ?? '') === 'EMG' && $packageEvent === 32)<button type="button" class="btn btn-sm btn-outline-success ips-already-received" data-code="{{ $package['codigo'] }}"><i class="fas fa-check"></i> Ya recibido</button>
                                    @elseif(isset($selection[$package['codigo']]))<span class="text-success"><i class="fas fa-check"></i> En bandeja</span>
                                    @elseif(($workflowAction ?? '') === 'EMG' && !empty($package['receipt_office_options']))
                                        <form class="ips-selection-add" method="POST" action="{{ $selectionAddUrl }}">@csrf
                                            <input type="hidden" name="codigo" value="{{ $package['codigo'] }}">
                                            <button class="btn btn-sm btn-outline-primary">Agregar a recepción</button>
                                        </form>
                                        <small class="text-warning">{{ $package['receipt_office_note'] }}</small>
                                    @elseif(($workflowAction ?? '') === 'EMG' && !empty($package['reception_block_reason']))<span class="text-muted">No recepcionable en esta oficina</span><small class="text-warning" role="status">{{ $package['reception_block_reason'] }}</small>
                                    @elseif(($workflowAction ?? '') === 'EMG' && !empty($package['route_mismatch_receipt']))<form class="ips-selection-add" method="POST" action="{{ $selectionAddUrl ?? route('ips.selection.add', \App\Support\IpsOfficeContext::parameters(request(), [])) }}">@csrf<input type="hidden" name="codigo" value="{{ $package['codigo'] }}"><button class="btn btn-sm btn-outline-warning"><i class="fas fa-exclamation-triangle"></i> Revisar recepción aquí</button></form><small class="text-warning" role="status">{{ $package['route_mismatch_note'] }}</small>
                                    @elseif(($deliveryOnly ?? false) && empty(array_intersect(array_keys($deliveryActionLabels), $package['allowed_actions'] ?? [])))<span class="text-muted">Sin paso de distribución habilitado</span>@if(!empty($package['delivery_block_reason']))<small class="text-warning">{{ $package['delivery_block_reason'] }}</small>@endif
                                    @elseif(!empty($smartReception) ? empty(array_intersect(['CUSTOMS_INFO', 'CUSTOMS_RETURN', 'EMG'], $package['allowed_actions'] ?? [])) : (($workflowAction ?? null) && !in_array($workflowAction, $package['allowed_actions'] ?? [], true)))<span class="text-muted">No habilitado para el siguiente paso</span>
                                    @elseif(!($workflowAction ?? null) && !($deliveryOnly ?? false) && !empty($package['allowed_actions']) && empty(array_intersect($package['allowed_actions'], array_keys($actions))))<span class="text-muted">Continúa desde Internacional</span>
                                    @elseif(empty($package['allowed_actions']) && strtoupper($search) !== strtoupper($package['codigo']))<span class="text-muted" title="Este paquete no tiene movimientos pendientes">Histórico</span>
                                    @else<form class="ips-selection-add" method="POST" action="{{ $selectionAddUrl ?? route('ips.selection.add', \App\Support\IpsOfficeContext::parameters(request(), [])) }}">@csrf<input type="hidden" name="codigo" value="{{ $package['codigo'] }}">@if(($workflowAction ?? '') === 'CUSTOMS_RETURN' && $preferredReceptaclePid)<input type="hidden" name="receptacle_pid" value="{{ $preferredReceptaclePid }}">@endif @if($deliveryOnly ?? false)<input type="hidden" name="workflow" value="delivery">@endif<button class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> {{ ($workflowAction ?? null) ? 'Agregar al paso' : (($deliveryOnly ?? false) ? 'Agregar a entrega' : (empty($package['allowed_actions']) ? 'Agregar consulta' : 'Agregar')) }}</button></form>@endif
                                </td>
                            </tr>
                        @empty<tr><td colspan="{{ $pendingDelivery ? 10 : 9 }}" class="ips-empty"><i class="fas fa-search"></i><p>{{ $error ? 'Listado no disponible.' : ($emptyMessage ?? 'No hay paquetes que coincidan con esta búsqueda y oficina.') }}</p></td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
                @unless($error)<div class="ips-pagination"><span>Página {{ $packages->currentPage() }} de {{ $packages->lastPage() }}</span>{{ $packages->links() }}</div>@endunless
            </section>
            @if($workflowAction ?? null)
                <p class="ips-footnote">Cada paquete conserva su propio evento e historial IPS; trabaja en lotes compatibles.</p>
            @elseif($deliveryOnly ?? false)
                <p class="ips-footnote">Primero recibe el paquete en esta oficina (evento 32). Después podrás registrar la baja. La devolución de Aduana se registra en su paso correspondiente y requiere recepción antes de la entrega.</p>
            @else
                <p class="ips-footnote">La etapa se obtiene del historial IPS. Un paquete retornado de aduana aún no equivale a un despacho. La oficina de destino se muestra cuando IPS la registra.</p>
            @endif
