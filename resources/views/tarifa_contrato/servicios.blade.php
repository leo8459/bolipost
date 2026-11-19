@extends('adminlte::page')
@section('title', 'Servicios de contrato por año')
@section('content')
@php
    $puedeGuardar = \Illuminate\Support\Facades\Blade::check('aclcan', 'save', null, 'tarifa-contrato');
    $puedeEliminar = \Illuminate\Support\Facades\Blade::check('aclcan', 'delete', null, 'tarifa-contrato');
    $anioFiltro = $anio ?? 'sin-anio';
    $aniosDisponibles = collect([$anio, ...$anios])->filter()->unique()->sortDesc();
    $formularioServicio = old('formulario_servicio');
@endphp
<section class="content container-fluid pt-3 tarifario-pagina">
    <header class="tarifario-encabezado">
        <div>
            <h1>Servicios de contrato por año</h1>
            <p>Administra los servicios que aparecen en los tarifarios y en las plantillas de Excel.</p>
        </div>
        <nav class="tarifario-encabezado-acciones" aria-label="Administración de tarifas">
            <a href="{{ route('tarifa-contrato.index') }}" class="btn tarifario-nav-secundario">Ver tarifas</a>
            <a href="{{ route('tarifa-contrato.tarifarios') }}#tarifarios-registrados" class="btn tarifario-nav-principal">Tarifarios por empresa y año</a>
        </nav>
    </header>

    @if(session('success'))<div class="alert tarifario-aviso" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <details class="tarifario-panel" id="guia-servicios" data-tarifario-panel>
        <summary class="tarifario-panel-cabecera">
            <h2 id="guia-servicios-titulo"><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-list-ol"></i></span>Cómo usar el catálogo</h2>
        </summary>
        <div class="tarifario-panel-contenido">
            <ol class="tarifario-guia list-unstyled mb-0" role="list" aria-labelledby="guia-servicios-titulo">
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">1</span>
                    <div><h3 class="h6 mb-1 font-weight-bold">Elige el año</h3><p class="small text-muted mb-0">Selecciona el año de tu tarifario. Puedes buscar un servicio por nombre.</p></div>
                </li>
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">2</span>
                    <div>
                        @if($puedeGuardar)
                            <h3 class="h6 mb-1 font-weight-bold">Completa el catálogo</h3><p class="small text-muted mb-0">Añade un servicio o copia los nombres que usaste en otro año.</p>
                        @else
                            <h3 class="h6 mb-1 font-weight-bold">Consulta los servicios</h3><p class="small text-muted mb-0">La tabla muestra cuántas tarifas están relacionadas con cada servicio.</p>
                        @endif
                    </div>
                </li>
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">3</span>
                    <div><h3 class="h6 mb-1 font-weight-bold">Usa los servicios en tus tarifas</h3><p class="small text-muted mb-0">Elige un servicio del mismo año al cargar tarifas. Los servicios asociados a tarifas quedan protegidos para conservar el historial.</p></div>
                </li>
            </ol>
        </div>
    </details>

    <details class="tarifario-panel" id="filtros-servicios" data-tarifario-panel open>
        <summary class="tarifario-panel-cabecera">
            <h2><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-filter"></i></span>Buscar servicios</h2>
        </summary>
        <form method="GET" action="{{ route('tarifa-contrato.servicios') }}" class="tarifario-panel-contenido">
            <div class="tarifario-filtros tarifario-servicios-filtros">
                <div>
                    <label for="filtro-anio">Año</label>
                    <select id="filtro-anio" name="anio" class="form-control">
                        @foreach($aniosDisponibles as $year)
                            <option value="{{ $year }}" @selected($anio === (int) $year)>{{ $year }}</option>
                        @endforeach
                        <option value="sin-anio" @selected($anio === null)>Año por definir</option>
                    </select>
                </div>
                <div>
                    <label for="buscar-servicio">Nombre del servicio</label>
                    <input type="search" class="form-control" id="buscar-servicio" name="q" maxlength="255" value="{{ $q }}" placeholder="Ej. Servicio nacional">
                </div>
                <div class="tarifario-filtros-acciones align-self-end mt-0">
                    <button class="btn tarifario-boton-azul" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i>Buscar</button>
                    @if($q !== '')
                        <a class="btn tarifario-boton-borde" href="{{ route('tarifa-contrato.servicios', ['anio' => $anioFiltro]) }}">Limpiar búsqueda</a>
                    @endif
                </div>
            </div>
        </form>
    </details>

    <section class="tarifario-panel" aria-labelledby="catalogo-servicios-titulo">
        <div class="tarifario-panel-contenido tarifario-resultados-cabecera">
            <div>
                <h2 id="catalogo-servicios-titulo">Catálogo de servicios · {{ $anio ?? 'Año por definir' }}</h2>
                <p class="tarifario-resultados-contexto">{{ $servicios->count() }} {{ $servicios->count() === 1 ? 'servicio' : 'servicios' }}{{ $q === '' ? ' en el catálogo' : ' encontrados' }}</p>
            </div>
            @if($puedeGuardar)
                <div class="tarifario-reportes">
                    <a class="btn btn-sm tarifario-boton-borde" href="#copiar-servicios"><i class="far fa-copy mr-1" aria-hidden="true"></i>Copiar a otro año</a>
                    <a class="btn btn-sm tarifario-boton-amarillo" href="#anadir-servicio"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Añadir servicio</a>
                </div>
            @endif
        </div>
        <div class="table-responsive tarifario-tabla-contenedor">
            <table class="table table-hover tarifario-tabla" aria-labelledby="catalogo-servicios-titulo">
                <caption class="sr-only">Servicios y tarifas relacionadas por año</caption>
                <thead>
                    <tr><th scope="col">Servicio</th><th scope="col">Año</th><th scope="col">Tarifas relacionadas</th><th scope="col">Acciones</th></tr>
                </thead>
                <tbody>
                    @forelse($servicios as $servicio)
                        <tr>
                            <th scope="row" class="tarifario-fila-titulo">{{ $servicio->nombre }}</th>
                            <td><span>{{ $servicio->anio ?? 'Por definir' }}</span></td>
                            <td class="tarifario-cantidad"><span>{{ number_format($servicio->tarifas_count, 0, ',', '.') }}</span></td>
                            <td class="tarifario-columna-acciones">
                                @if($servicio->tarifas_count)
                                    <span class="tarifario-estado"><i class="fas fa-lock mr-1 align-self-center" aria-hidden="true"></i>Protegido</span>
                                @elseif($puedeGuardar || $puedeEliminar)
                                    <button type="button" class="btn btn-sm tarifario-boton-azul" data-abrir-acciones="servicio-acciones-{{ $servicio->id }}" aria-haspopup="dialog" aria-controls="servicio-acciones-{{ $servicio->id }}" aria-label="Acciones para {{ $servicio->nombre }}">
                                        <i class="fas fa-ellipsis-h mr-1" aria-hidden="true"></i>Acciones
                                    </button>
                                @else
                                    <span class="small text-muted">Solo consulta</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4">
                            <strong>No se encontraron servicios.</strong>
                            <p class="small text-muted mb-0">{{ $q !== '' ? 'Prueba con otro nombre o limpia la búsqueda.' : 'Selecciona otro año para consultar su catálogo.' }}</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($puedeGuardar)
        <div class="tarifario-servicios-gestion">
            <details class="tarifario-panel" id="anadir-servicio" data-tarifario-panel @if($formularioServicio === 'crear') open @endif>
                <summary class="tarifario-panel-cabecera">
                    <h2><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-plus"></i></span>Añadir servicio</h2>
                </summary>
                <form method="POST" action="{{ route('tarifa-contrato.servicios-store') }}" class="tarifario-panel-contenido">
                    @csrf
                    <input type="hidden" name="formulario_servicio" value="crear">
                    <div class="form-group">
                        <label for="nombre">Nombre del servicio *</label>
                        <input id="nombre" name="nombre" class="form-control" maxlength="255" value="{{ $formularioServicio === 'crear' ? old('nombre') : '' }}" placeholder="Ej. ENVIO NACIONAL (REGULAR)" required>
                    </div>
                    <div class="form-group">
                        <label for="anio-nuevo">Año *</label>
                        <input type="number" id="anio-nuevo" name="anio" class="form-control" min="2000" max="2100" value="{{ $formularioServicio === 'crear' ? old('anio') : ($anio ?? now()->year) }}" required>
                    </div>
                    <button type="submit" class="btn tarifario-boton-amarillo">Añadir servicio</button>
                </form>
            </details>
            <details class="tarifario-panel" id="copiar-servicios" data-tarifario-panel @if($formularioServicio === 'copiar') open @endif>
                <summary class="tarifario-panel-cabecera">
                    <h2><span class="tarifario-panel-icono" aria-hidden="true"><i class="far fa-copy"></i></span>Copiar servicios a otro año</h2>
                </summary>
                <form method="POST" action="{{ route('tarifa-contrato.servicios-copy') }}" class="tarifario-panel-contenido">
                    @csrf
                    <input type="hidden" name="formulario_servicio" value="copiar">
                    <div class="form-group">
                        <label for="copiar-de">Año de origen *</label>
                        <select id="copiar-de" name="copiar_de_anio" class="form-control" required>
                            @php $origenCopia = (string) old('copiar_de_anio', $anioFiltro); @endphp
                            @foreach($aniosDisponibles as $year)
                                <option value="{{ $year }}" @selected($origenCopia === (string) $year)>{{ $year }}</option>
                            @endforeach
                            <option value="sin-anio" @selected($origenCopia === 'sin-anio')>Año por definir</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="copiar-a">Año de destino *</label>
                        <input type="number" id="copiar-a" name="anio" class="form-control" min="2000" max="2100" value="{{ $formularioServicio === 'copiar' ? old('anio') : ($anio && $anio < 2100 ? $anio + 1 : now()->year) }}" required>
                    </div>
                    <div class="tarifario-reportes">
                        <button type="submit" class="btn tarifario-boton-azul">Copiar servicios</button>
                        <small class="text-muted">Los servicios existentes se conservan. Solo se añaden los nombres que faltan en el destino.</small>
                    </div>
                </form>
            </details>
        </div>
    @endif

    @if($puedeGuardar || $puedeEliminar)
        @foreach($servicios->where('tarifas_count', 0) as $servicio)
            @include('tarifa_contrato.servicio-acciones')
        @endforeach
    @endif
</section>
@include('footer')
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/tarifarios-contrato.css') }}?v={{ filemtime(public_path('css/tarifarios-contrato.css')) }}">
@endpush

@push('js')
    <script src="{{ asset('js/tarifarios-contrato.js') }}?v={{ filemtime(public_path('js/tarifarios-contrato.js')) }}" defer></script>
@endpush
