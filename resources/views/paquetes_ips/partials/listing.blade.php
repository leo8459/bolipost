@if($error)<div class="alert alert-warning" role="alert">{{ $error }}</div>@endif
<section class="ips-panel">
                <div class="ips-panel-header"><h2>Paquetes de tu oficina</h2><span>{{ $error ? 'Consulta no disponible' : $packages->total().' resultados' }}</span></div>
                <form method="GET" action="{{ route('ips.index') }}" class="ips-search">
                    <label for="ips-code" class="sr-only">Código de seguimiento o identificador local exacto</label>
                    <input id="ips-code" type="search" name="q" maxlength="35" value="{{ $search }}" placeholder="Escanea o escribe un código…" class="form-control" autocomplete="off">
                    <button class="btn btn-primary"><i class="fas fa-search mr-1"></i>Buscar</button>
                    @if($search)<a href="{{ route('ips.index') }}" class="btn btn-light">Limpiar</a>@endif
                </form>
                <nav class="ips-tabs" aria-label="Filtrar paquetes">
                    @foreach(['all'=>'Todos', 'reception'=>'Por recibir', 'pending'=>'Para retiro / reparto', 'returns'=>'Devoluciones IPS', 'delivered'=>'Entregados'] as $value=>$label)
                        <a class="{{ $stage===$value?'is-active':'' }}" href="{{ route('ips.index', ['stage'=>$value,'q'=>$search]) }}" @if($stage===$value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="table-responsive">
                    <table class="table ips-table">
                        <thead><tr><th>Código / ID local</th><th>Destinatario</th><th>Teléfono</th><th>Ciudad / dirección</th><th>Oficina actual → destino</th><th>Peso / clase</th><th>Tributable</th><th>Etapa / último movimiento</th><th>Acción</th></tr></thead>
                        <tbody>
                        @forelse($packages as $package)
                            <tr>
                                <td><strong class="ips-code">{{ $package['codigo'] }}</strong><small>{{ $package['local_id'] ?: 'Sin ID local' }}</small></td>
                                <td>{{ $package['recipient'] ?: 'No registrado' }}</td>
                                <td>{{ $package['phone'] ?: 'No registrado' }}</td>
                                <td>{{ $package['city'] ?: 'No registrada' }}<small>{{ $package['address'] ?: 'Sin dirección registrada' }}</small></td>
                                <td>{{ $package['office_name'] ?: 'Sin oficina' }}@if($package['next_office_name'])<small>→ {{ $package['next_office_name'] }}</small>@endif</td>
                                <td>{{ $package['weight_kg'] !== null ? number_format($package['weight_kg'], 3, ',', '.') . ' kg' : 'Sin peso' }}<small>Clase {{ $package['mail_class'] ?: 'no registrada' }}</small></td>
                                <td>{{ match((string)($package['dutiable_ind'] ?? '')) {'Y','1'=>'Sí','N','0'=>'No',default=>'No registrado'} }}</td>
                                <td><span class="badge badge-{{ $package['stage']['tone'] }}">{{ $package['stage']['label'] }}</span><small>{{ $package['event_at'] ? \Illuminate\Support\Carbon::parse($package['event_at'])->timezone('America/La_Paz')->format('d/m/Y H:i') : 'Sin fecha' }}</small><small>Evento {{ $package['operational_event_cd'] }}</small></td>
                                <td>
                                    @if(isset($selection[$package['codigo']]))<span class="text-success"><i class="fas fa-check"></i> En bandeja</span>
                                    @elseif(empty($package['allowed_actions']) && strtoupper($search) !== strtoupper($package['codigo']))<span class="text-muted" title="Este paquete no tiene movimientos pendientes">Histórico</span>
                                    @else<form class="ips-selection-add" method="POST" action="{{ route('ips.selection.add') }}">@csrf<input type="hidden" name="codigo" value="{{ $package['codigo'] }}"><button class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> {{ empty($package['allowed_actions']) ? 'Agregar consulta' : 'Agregar' }}</button></form>@endif
                                </td>
                            </tr>
                        @empty<tr><td colspan="9" class="ips-empty"><i class="fas fa-search"></i><p>{{ $error ? 'Listado no disponible.' : 'No hay paquetes que coincidan con esta búsqueda y oficina.' }}</p></td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
                @unless($error)<div class="ips-pagination"><span>Página {{ $packages->currentPage() }} de {{ $packages->lastPage() }}</span>{{ $packages->links() }}</div>@endunless
            </section>
            <p class="ips-footnote">La etapa se obtiene del historial IPS. Un paquete retornado de aduana aún no equivale a un despacho. La oficina de destino se muestra cuando IPS la registra.</p>
