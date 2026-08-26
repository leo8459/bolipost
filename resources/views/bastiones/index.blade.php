@extends('adminlte::page')

@section('title', 'Bastiones')

@section('content_header')
    <div>
        <h1 class="mb-1">Bastiones</h1>
        <p class="text-muted mb-0">Paquetes resguardados disponibles para volver a su tabla operativa.</p>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->first() }}
        </div>
    @endif

    <div class="row">
        @foreach(['ems' => ['EMS', 'box'], 'contratos' => ['Contratos', 'file-contract'], 'certificados' => ['Certificados', 'certificate'], 'ordinarios' => ['Ordinarios', 'box-open']] as $clave => [$nombre, $icono])
            <div class="col-xl-3 col-md-6">
                <div class="small-box bg-white shadow-sm border">
                    <div class="inner"><h3>{{ number_format($totales[$clave]) }}</h3><p>{{ $nombre }}</p></div>
                    <div class="icon"><i class="fas fa-{{ $icono }} text-warning"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-header border-0">
            <form method="GET" action="{{ route('bastiones.index') }}" class="row align-items-end">
                <div class="col-lg-6 mb-2">
                    <label for="buscar">Buscar paquete</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                        <input id="buscar" name="buscar" value="{{ $busqueda }}" class="form-control"
                            placeholder="Código, código especial o destinatario...">
                    </div>
                </div>
                <div class="col-lg-3 mb-2">
                    <label for="tipo">Tipo de paquete</label>
                    <select id="tipo" name="tipo" class="form-control">
                        <option value="todos" @selected($tipo === 'todos')>Todos</option>
                        <option value="ems" @selected($tipo === 'ems')>EMS</option>
                        <option value="contratos" @selected($tipo === 'contratos')>Contratos</option>
                        <option value="certificados" @selected($tipo === 'certificados')>Certificados</option>
                        <option value="ordinarios" @selected($tipo === 'ordinarios')>Ordinarios</option>
                    </select>
                </div>
                <div class="col-lg-3 mb-2 d-flex">
                    <button class="btn btn-warning mr-2" type="submit"><i class="fas fa-search mr-1"></i> Buscar</button>
                    <a href="{{ route('bastiones.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr><th>Tipo</th><th>Código</th><th>Código especial</th><th>Destinatario</th><th>Ruta</th><th>Fecha</th><th class="text-right">Acción</th></tr>
                </thead>
                <tbody>
                    @forelse($paquetes as $paquete)
                        <tr>
                            <td><span class="badge badge-primary">{{ $paquete->tipo_etiqueta }}</span></td>
                            <td class="font-weight-bold">{{ $paquete->codigo ?: 'Sin código' }}</td>
                            <td>{{ $paquete->cod_especial ?: '—' }}</td>
                            <td>{{ $paquete->destinatario ?: '—' }}</td>
                            <td>{{ collect([$paquete->origen, $paquete->destino])->filter()->join(' → ') ?: '—' }}</td>
                            <td class="text-nowrap">{{ $paquete->created_at ? \Illuminate\Support\Carbon::parse($paquete->created_at)->format('d/m/Y H:i') : '—' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('bastiones.recuperar', [$paquete->tipo, $paquete->bastion_id]) }}"
                                    onsubmit="return confirm('¿Recuperar este paquete en su tabla correspondiente?');">
                                    @csrf
                                    <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-undo-alt mr-1"></i> Recuperar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-shield-alt fa-2x mb-2 d-block"></i>No se encontraron paquetes en el bastión.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($paquetes->hasPages())
            <div class="card-footer">{{ $paquetes->links() }}</div>
        @endif
    </div>
@endsection
