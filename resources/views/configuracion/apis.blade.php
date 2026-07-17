@extends('adminlte::page')

@section('title', 'APIS')

@section('content_header')
    <h1>APIS</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if (session('new_token'))
                <div class="alert alert-warning">
                    <label class="d-block">Token JWT generado</label>
                    <textarea class="form-control" rows="4" readonly>{{ session('new_token') }}</textarea>
                </div>
            @endif

            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('configuracion.apis.manual') }}" class="btn btn-success">
                    <i class="fas fa-file-word mr-1"></i> Descargar manual Word
                </a>
            </div>

            <form method="POST" action="{{ route('configuracion.apis.store') }}" class="mb-4">
                @csrf
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Nombre del token</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', 'API 1') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Vence en</label>
                            <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                            <small class="text-muted">Dejar vacio para token sin vencimiento.</small>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-center">
                        <button type="submit" class="btn btn-primary mt-2">
                            <i class="fas fa-key mr-1"></i> Generar token
                        </button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Ultimo uso</th>
                            <th>Vence</th>
                            <th>Creado</th>
                            <th>Token JWT</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            <tr>
                                <td>{{ $token->name }}</td>
                                <td>
                                    @if ($token->isUsable())
                                        <span class="badge badge-success">Activo</span>
                                    @else
                                        <span class="badge badge-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td>{{ optional($token->last_used_at)->format('d/m/Y H:i') ?? 'Sin uso' }}</td>
                                <td>{{ optional($token->expires_at)->format('d/m/Y') ?? 'Sin vencimiento' }}</td>
                                <td>{{ optional($token->created_at)->format('d/m/Y H:i') }}</td>
                                <td style="min-width: 360px;">
                                    @if ($token->token_encrypted)
                                        @php
                                            try {
                                                $plainToken = \Illuminate\Support\Facades\Crypt::decryptString($token->token_encrypted);
                                            } catch (\Throwable $e) {
                                                $plainToken = null;
                                            }
                                        @endphp

                                        @if ($plainToken)
                                            <textarea class="form-control form-control-sm" rows="3" readonly>{{ $plainToken }}</textarea>
                                        @else
                                            <span class="text-muted">No se pudo leer el token cifrado.</span>
                                        @endif
                                    @else
                                        <span class="text-muted">Token antiguo sin copia visible. Genera uno nuevo para verlo aqui.</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($token->is_active)
                                        <form method="POST" action="{{ route('configuracion.apis.deactivate', $token) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-ban mr-1"></i> Dar de baja
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('configuracion.apis.activate', $token) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check mr-1"></i> Activar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">Aun no hay tokens.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <hr>

            <h5>Endpoints de prueba</h5>
            <pre class="bg-light border rounded p-3 mb-0">GET https://trackingbo.correos.gob.bo:8100/api/direcciones-destino/todo
GET https://trackingbo.correos.gob.bo:8100/api/direcciones-destino/cantidad?desde=1&hasta=500</pre>
        </div>
    </div>
@stop
