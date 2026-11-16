@extends('adminlte::page')
@section('title', 'Servicios de contrato por año')
@section('content')
<section class="content container-fluid pt-3">
    <div class="d-flex flex-wrap justify-content-between mb-3" style="gap:12px">
        <div><h1 class="h4">Servicios de contrato por año</h1><p class="text-muted mb-0">Los servicios de cada año se usan en sus tarifarios y en la plantilla Excel.</p></div>
        <a class="btn btn-secondary align-self-start" href="{{ route('tarifa-contrato.tarifarios') }}">Tarifarios por año</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @aclcan('save', null, 'tarifa-contrato')
    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-success">
                <div class="card-header"><h2 class="card-title">Añadir servicio</h2></div>
                <form method="POST" action="{{ route('tarifa-contrato.servicios-store') }}" class="card-body">
                    @csrf
                    <div class="form-group"><label for="nombre">Nombre del servicio *</label><input id="nombre" name="nombre" class="form-control" maxlength="255" value="{{ old('nombre') }}" placeholder="Ej. ENVIO NACIONAL (REGULAR)" required></div>
                    <div class="form-group"><label for="anio-nuevo">Año *</label><input type="number" id="anio-nuevo" name="anio" class="form-control" min="2000" max="2100" value="{{ old('anio', $anio ?? now()->year) }}" required></div>
                    <button type="submit" class="btn btn-success">Añadir servicio</button>
                </form>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Copiar servicios a otro año</h2></div>
                <form method="POST" action="{{ route('tarifa-contrato.servicios-copy') }}" class="card-body">
                    @csrf
                    <div class="form-group"><label for="copiar-de">Año de origen *</label><select id="copiar-de" name="copiar_de_anio" class="form-control" required>
                        @foreach($anios as $year)<option value="{{ $year }}" @selected((string) old('copiar_de_anio', $anio) === (string) $year)>{{ $year }}</option>@endforeach
                        <option value="sin-anio" @selected($anio === null)>Año por definir</option>
                    </select></div>
                    <div class="form-group"><label for="copiar-a">Año de destino *</label><input type="number" id="copiar-a" name="anio" class="form-control" min="2000" max="2100" value="{{ $anio ? $anio + 1 : now()->year }}" required></div>
                    <button type="submit" class="btn btn-outline-primary">Copiar servicios</button>
                    <small class="d-block text-muted mt-2">Conserva los del año anterior y evita duplicar nombres que ya existen en el destino.</small>
                </form>
            </div>
        </div>
    </div>
    @endaclcan
    <div class="card">
        <div class="card-header"><h2 class="card-title">Catálogo de servicios</h2></div>
        <div class="card-body">
            <form method="GET" action="{{ route('tarifa-contrato.servicios') }}" class="row mb-3">
                <div class="col-md-3 form-group"><label for="filtro-anio">Año</label><select id="filtro-anio" name="anio" class="form-control">
                    @foreach($anios as $year)<option value="{{ $year }}" @selected($anio === (int) $year)>{{ $year }}</option>@endforeach
                    <option value="sin-anio" @selected($anio === null)>Año por definir</option>
                </select></div>
                <div class="col-md-6 form-group"><label for="buscar-servicio">Buscar servicio</label><input class="form-control" id="buscar-servicio" name="q" value="{{ $q }}" placeholder="Nombre del servicio"></div>
                <div class="col-md-3 form-group d-flex align-items-end"><button class="btn btn-primary">Filtrar</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead><tr><th>Servicio</th><th>Año</th><th>Tarifas relacionadas</th><th>Acciones</th></tr></thead>
                    <tbody>
                        @forelse($servicios as $servicio)
                        <tr>
                            <td>{{ $servicio->nombre }}</td><td>{{ $servicio->anio ?? 'Por definir' }}</td><td>{{ $servicio->tarifas_count }}</td>
                            <td>
                                @if($servicio->tarifas_count)
                                    <span class="text-muted small">En uso: se conserva para el historial.</span>
                                @else
                                    @aclcan('save', null, 'tarifa-contrato')
                                    <details><summary>Editar</summary><form method="POST" action="{{ route('tarifa-contrato.servicios-update', $servicio) }}" class="mt-2">
                                        @csrf @method('PUT')
                                        <label class="sr-only" for="nombre-{{ $servicio->id }}">Nombre</label><input class="form-control form-control-sm mb-2" id="nombre-{{ $servicio->id }}" name="nombre" value="{{ $servicio->nombre }}" maxlength="255" required>
                                        <label class="sr-only" for="anio-{{ $servicio->id }}">Año</label><input type="number" class="form-control form-control-sm mb-2" id="anio-{{ $servicio->id }}" name="anio" value="{{ $servicio->anio }}" min="2000" max="2100" required>
                                        <button class="btn btn-sm btn-success">Guardar</button>
                                    </form></details>
                                    @endaclcan
                                    @aclcan('delete', null, 'tarifa-contrato')
                                    <form method="POST" action="{{ route('tarifa-contrato.servicios-destroy', $servicio) }}" class="mt-2" onsubmit="return confirm('¿Eliminar este servicio sin tarifas relacionadas?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
                                    @endaclcan
                                @endif
                            </td>
                        </tr>
                        @empty <tr><td colspan="4" class="text-center text-muted py-4">No hay servicios para este año y búsqueda. Puedes añadir uno o copiar los de otro año.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@include('footer')
@endsection
