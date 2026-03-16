@extends('adminlte::page')
@section('title', 'Bitacoras')
@section('template_title')
    Bitacoras
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Bitacoras</span>
                            <a href="{{ route('bitacoras.create') }}" class="btn btn-primary btn-sm">Crear Nuevo</a>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    <div class="card-body">
                        <form method="GET" action="{{ route('bitacoras.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Busqueda general</label>
                                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cod especial, factura, usuario, codigo...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Usuario</label>
                                        <select name="user_id" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ (int) $userId === (int) $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Cod especial</label>
                                        <input type="text" name="cod_especial" value="{{ $codEspecial }}" class="form-control" placeholder="Ej: LPZ00001">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Provincia</label>
                                        <select name="provincia" class="form-control">
                                            <option value="">Todas</option>
                                            @foreach($provincias as $provinciaItem)
                                                <option value="{{ $provinciaItem }}" {{ strtoupper((string) $provincia) === strtoupper((string) $provinciaItem) ? 'selected' : '' }}>
                                                    {{ $provinciaItem }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex" style="gap:8px;">
                                <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                                <a href="{{ route('bitacoras.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>ID</th>
                                        <th>Cod Especial</th>
                                        <th>Usuario</th>
                                        <th>Paquete EMS</th>
                                        <th>Paquete Contrato</th>
                                        <th>Paquete Ordinario</th>
                                        <th>Paquete Certificado</th>
                                        <th>Transportadora</th>
                                        <th>Provincia</th>
                                        <th>Factura</th>
                                        <th>Precio Total</th>
                                        <th>Peso</th>
                                        <th>Imagen</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($bitacoras as $bitacora)
                                        <tr>
                                            <td>{{ $bitacora->id }}</td>
                                            <td>{{ $bitacora->cod_especial }}</td>
                                            <td>{{ $bitacora->user->name ?? '-' }}</td>
                                            <td>
                                                @if($bitacora->paqueteEms)
                                                    #{{ $bitacora->paqueteEms->id }} - {{ $bitacora->paqueteEms->codigo }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($bitacora->paqueteContrato)
                                                    #{{ $bitacora->paqueteContrato->id }} - {{ $bitacora->paqueteContrato->codigo }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($bitacora->paqueteOrdi)
                                                    #{{ $bitacora->paqueteOrdi->id }} - {{ $bitacora->paqueteOrdi->codigo }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($bitacora->paqueteCerti)
                                                    #{{ $bitacora->paqueteCerti->id }} - {{ $bitacora->paqueteCerti->codigo }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $bitacora->transportadora ?: '-' }}</td>
                                            <td>{{ $bitacora->provincia ?: '-' }}</td>
                                            <td>{{ $bitacora->factura ?: '-' }}</td>
                                            <td>{{ $bitacora->precio_total !== null ? number_format((float) $bitacora->precio_total, 2) : '-' }}</td>
                                            <td>{{ $bitacora->peso !== null ? number_format((float) $bitacora->peso, 3) : '-' }}</td>
                                            <td>
                                                @if($bitacora->imagen_factura)
                                                    <a href="{{ asset('storage/' . $bitacora->imagen_factura) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                                        Ver
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <form action="{{ route('bitacoras.destroy', $bitacora) }}" method="POST">
                                                    <a class="btn btn-sm btn-success" href="{{ route('bitacoras.edit', $bitacora) }}" title="Editar">
                                                        <i class="fa fa-fw fa-edit"></i>
                                                    </a>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                        title="Eliminar"
                                                        onclick="return confirm('Seguro que deseas eliminar esta bitacora?')"
                                                    >
                                                        <i class="fa fa-fw fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="14" class="text-center py-4">No hay registros</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $bitacoras->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
