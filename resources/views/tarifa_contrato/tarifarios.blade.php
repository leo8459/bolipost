@extends('adminlte::page')
@section('title', 'Tarifarios de contrato por año')

@section('content')
<section class="content container-fluid pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap:12px">
        <div>
            <h1 class="h4 mb-1">Tarifarios de contrato por año</h1>
            <p class="text-muted mb-0">Crea el tarifario, carga sus precios y asígnalo a una o varias empresas.</p>
        </div>
        <div><a href="{{ route('tarifa-contrato.servicios') }}" class="btn btn-outline-success">Servicios por año</a> <a href="{{ route('tarifa-contrato.index') }}" class="btn btn-secondary">Ver tarifas</a></div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @php $copy = $tarifarios->firstWhere('id', (int) request('copiar_de_id')); @endphp
    @aclcan('save', null, 'tarifa-contrato')
    <div class="card card-outline card-success" id="nuevo-tarifario">
        <div class="card-header"><h2 class="card-title">Crear o renovar un tarifario</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('tarifa-contrato.tarifarios-store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="nombre">Nombre *</label>
                        <input class="form-control" id="nombre" name="nombre" maxlength="180" value="{{ old('nombre', $copy?->nombre) }}" placeholder="Ej. Tarifario A" required>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="anio">Año *</label>
                        <input type="number" class="form-control" id="anio" name="anio" min="2000" max="2100" value="{{ old('anio', $copy?->anio ? $copy->anio + 1 : now()->year) }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="copiar_de_id">Copiar tarifas de (opcional)</label>
                        <select class="form-control" id="copiar_de_id" name="copiar_de_id">
                            <option value="">Empezar vacío</option>
                            @foreach($tarifarios as $item)
                                <option value="{{ $item->id }}" @selected((string) old('copiar_de_id', $copy?->id) === (string) $item->id)>{{ $item->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end"><button class="btn btn-success" type="submit">Crear tarifario</button></div>
                </div>
                <p class="small text-muted mb-0">La copia es independiente: puedes actualizar sus precios sin cambiar los del año anterior.</p>
            </form>
        </div>
    </div>
    @endaclcan
    <div class="card">
        <div class="card-header"><h2 class="card-title">Tarifarios registrados</h2></div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Tarifario</th><th>Año</th><th>Tarifas</th><th>Estado</th><th>Empresas que lo usan</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse($tarifarios as $item)
                        <tr>
                            <td>{{ $item->nombre }}</td><td>{{ $item->anio ?? 'Por definir' }}</td><td>{{ $item->tarifas_count }}</td>
                            <td><span class="badge badge-{{ !$item->publicado_en ? 'warning' : ($item->empresas_activas_count ? 'success' : 'secondary') }}">{{ !$item->publicado_en ? 'Borrador' : ($item->empresas_activas_count ? 'En uso' : 'Histórico') }}</span></td>
                            <td>{{ $item->empresasActivas->pluck('nombre')->join(', ') ?: 'Sin asignar' }}</td>
                            <td style="min-width:240px">
                                <a class="btn btn-sm btn-outline-primary mb-1" href="{{ route('tarifa-contrato.index', ['tarifario_id' => $item->id]) }}">Ver tarifas</a>
                                @if(!$item->publicado_en)
                                    @aclcan('create', null, 'tarifa-contrato')
                                    <a class="btn btn-sm btn-success mb-1" href="{{ route('tarifa-contrato.create', ['tarifario_id' => $item->id]) }}">Cargar tarifas</a>
                                    @endaclcan
                                @endif
                                @aclcan('save', null, 'tarifa-contrato')
                                <a class="btn btn-sm btn-outline-secondary mb-1" href="{{ route('tarifa-contrato.tarifarios', ['copiar_de_id' => $item->id]) }}#nuevo-tarifario">Renovar / copiar</a>
                                <details class="mt-2">
                                    <summary>Nombre y año</summary>
                                    <form method="POST" class="mt-2" action="{{ route('tarifa-contrato.tarifarios-update', $item) }}">
                                        @csrf @method('PUT')
                                        <label class="sr-only" for="nombre-{{ $item->id }}">Nombre</label>
                                        <input class="form-control form-control-sm mb-2" id="nombre-{{ $item->id }}" name="nombre" value="{{ $item->nombre }}" maxlength="180" required>
                                        <label class="sr-only" for="anio-{{ $item->id }}">Año</label>
                                        <input type="number" class="form-control form-control-sm mb-2" id="anio-{{ $item->id }}" name="anio" min="2000" max="2100" value="{{ $item->anio }}" placeholder="Año" required>
                                        <button class="btn btn-sm btn-outline-primary">Actualizar nombre/año</button>
                                    </form>
                                </details>
                                @if($item->tarifas_count)
                                <details class="mt-2">
                                    <summary>Asignar a empresas</summary>
                                    <form method="POST" class="mt-2" action="{{ route('tarifa-contrato.tarifarios-asignar', $item) }}">
                                        @csrf
                                        <label for="empresas-{{ $item->id }}">Empresas *</label>
                                        <select id="empresas-{{ $item->id }}" name="empresas[]" class="form-control" multiple size="6" required>
                                            @foreach($empresas as $empresa)<option value="{{ $empresa->id }}">{{ $empresa->codigo_cliente }} — {{ $empresa->nombre }}</option>@endforeach
                                        </select>
                                        <p class="small text-muted mt-1">Usa Ctrl o Mayús para seleccionar varias. Las seleccionadas pasan a este tarifario; las demás conservan el suyo. Al asignarlo, sus precios quedan protegidos.</p>
                                        <button class="btn btn-sm btn-success">Asignar tarifario</button>
                                    </form>
                                </details>
                                @endif
                                @endaclcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Crea tu primer tarifario por año para cargar sus precios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Tarifario asignado e historial por empresa</h2></div>
        <div class="card-body table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Empresa</th><th>Tarifario actual</th><th>Tarifarios anteriores</th></tr></thead>
                <tbody>
                    @foreach($empresas as $empresa)
                        @php $actual = $empresa->tarifariosContrato->first(fn ($item) => $item->pivot->activo); @endphp
                        <tr>
                            <td>{{ $empresa->codigo_cliente }} — {{ $empresa->nombre }}</td><td>{{ $actual?->etiqueta ?? 'Sin tarifario asignado' }}</td>
                            <td>
                                @forelse($empresa->tarifariosContrato->filter(fn ($item) => !$item->pivot->activo) as $anterior)
                                    <div><a href="{{ route('tarifa-contrato.index', ['tarifario_id' => $anterior->id]) }}">{{ $anterior->etiqueta }}</a> <small class="text-muted">Hasta {{ \Illuminate\Support\Carbon::parse($anterior->pivot->finalizado_en)->format('d/m/Y') }}</small></div>
                                @empty <span class="text-muted">Sin anteriores</span> @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted">Las tarifas existentes se conservan con el año por definir, sin alterar los envíos registrados.</p>
</section>
@include('footer')
@endsection
