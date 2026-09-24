@extends('adminlte::page')

@section('title', 'Vinculacion IPS')

@section('content_header')
    <h1>Vinculacion IPS</h1>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('verification'))
        @php($verification = session('verification'))
        <div class="alert alert-info">
            <strong>Datos confirmados en IPS</strong>:
            PID {{ $verification['pid'] }} · {{ $verification['usuario'] }} ·
            Oficina: {{ $verification['oficina'] }} ·
            Verificado: {{ $verification['verificado'] }}
        </div>
    @endif
    @if($ipsError)
        <div class="alert alert-warning">{{ $ipsError }}</div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <strong>Usuarios Bolipost</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('users.ips-links.index') }}" class="row align-items-end">
                <div class="col-md-4 form-group">
                    <label for="q">Buscar usuario Bolipost</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nombre, alias, correo o regional">
                </div>
                <div class="col-md-3 form-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        @foreach(['todos' => 'Todos', 'vinculados' => 'Vinculados', 'pendientes' => 'Pendientes'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['estado'] ?? 'todos') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="ips_q">Buscar usuario IPS</label>
                    <input id="ips_q" name="ips_q" value="{{ $filters['ips_q'] ?? '' }}" class="form-control" placeholder="bindira, Indira, WEBCLIENT_NG">
                </div>
                <div class="col-md-1 form-group">
                    <button class="btn btn-primary btn-block" type="submit">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <strong>Bolipost</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Regional</th>
                                <th>Vinculo IPS</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                @php($link = $user->ipsLink)
                                @php($suggestedFid = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $user->alias))
                                <tr>
                                    <td>
                                        <strong>{{ $user->name }}</strong>
                                        <div class="text-muted small">#{{ $user->id }} · {{ $user->alias }} · {{ $user->email }}</div>
                                        @if($user->trashed())
                                            <span class="badge badge-secondary">Baja</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span>{{ $user->regionalesTexto() ?: '-' }}</span>
                                        @if($user->sucursal)
                                            <div class="text-muted small">Suc. {{ $user->sucursal->codigoSucursal }} / PV {{ $user->sucursal->puntoVenta }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($link)
                                            <span class="badge badge-success">Vinculado</span>
                                            <div><strong>{{ $link->label() }}</strong></div>
                                            <div class="text-muted small">PID {{ $link->ips_user_pid }} · {{ $link->ips_office_name ?: 'Sin oficina IPS' }}</div>
                                            <div class="text-muted small">Verificado {{ optional($link->last_verified_at)->format('Y-m-d H:i') ?: 'sin fecha' }}</div>
                                        @else
                                            <span class="badge badge-warning">Pendiente</span>
                                            <div class="text-muted small">Puede vincular un PID existente o crear usuario IPS tipo operador.</div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if($link)
                                            <form method="POST" action="{{ route('users.ips-links.verify', $user) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-info" type="submit">Verificar</button>
                                            </form>
                                            <form method="POST" action="{{ route('users.ips-links.destroy', $user) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('users.ips-links.store', $user) }}" class="d-inline-flex align-items-center">
                                                @csrf
                                                <input name="ips_user_pid" class="form-control form-control-sm mr-1" style="width: 90px" placeholder="PID IPS" title="Use el PID que aparece en Candidatos IPS">
                                                <button class="btn btn-sm btn-primary" type="submit">Vincular</button>
                                            </form>
                                            <form method="POST" action="{{ route('users.ips-links.create-user', $user) }}" class="mt-2 text-left border rounded p-2 bg-light">
                                                @csrf
                                                <div class="small font-weight-bold mb-1">Crear en IPS y vincular</div>
                                                <div class="form-row">
                                                    <div class="col-4 mb-1">
                                                        <label class="small mb-0">Dominio</label>
                                                        <input name="user_domain" class="form-control form-control-sm" value="AGBC" maxlength="20">
                                                    </div>
                                                    <div class="col-8 mb-1">
                                                        <label class="small mb-0">ID IPS</label>
                                                        <input name="user_fid" class="form-control form-control-sm" value="{{ $suggestedFid }}" maxlength="256">
                                                    </div>
                                                    <div class="col-12 mb-1">
                                                        <label class="small mb-0">Nombre IPS</label>
                                                        <input name="user_name" class="form-control form-control-sm" value="{{ $user->name }}" maxlength="256">
                                                    </div>
                                                    <div class="col-5 mb-1">
                                                        <label class="small mb-0">Código oficina IPS</label>
                                                        <input name="office_cd" class="form-control form-control-sm" placeholder="Ej. 12 (solo número)" inputmode="numeric">
                                                        <div class="small text-muted">No escriba el nombre de la sucursal; IPS requiere su código.</div>
                                                    </div>
                                                    <div class="col-7 mb-1">
                                                        <label class="small mb-0">Email</label>
                                                        <input name="email" class="form-control form-control-sm" value="{{ $user->email }}" maxlength="256">
                                                    </div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-success btn-block" type="submit">Crear usuario IPS</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No hay usuarios con esos filtros.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $users->links() }}
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <strong>Candidatos IPS</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>PID</th>
                                <th>Usuario IPS</th>
                                <th>Oficina</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ipsUsers as $ipsUser)
                                <tr>
                                    <td><strong>{{ $ipsUser['user_pid'] }}</strong></td>
                                    <td>
                                        <strong>{{ ($ipsUser['user_domain'] ?? '') !== '' ? $ipsUser['user_domain'].'\\' : '' }}{{ $ipsUser['user_fid'] }}</strong>
                                        <div class="text-muted small">{{ $ipsUser['user_name'] }}</div>
                                        <div class="small">
                                            @if($ipsUser['ipsweb'])
                                                <span class="badge badge-info">IPSWEB</span>
                                            @endif
                                            @if($ipsUser['restrict_user_offices'])
                                                <span class="badge badge-warning">Oficinas restringidas</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="small">{{ $ipsUser['office_name'] ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-4">Busca por alias, dominio o nombre para ver candidatos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
