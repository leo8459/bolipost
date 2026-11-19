@extends('adminlte::page')
@section('title', 'Tarifarios de contrato por año')
@section('plugins.Select2', true)

@section('content')
<section class="content container-fluid pt-3 tarifario-pagina">
    <header class="tarifario-encabezado">
        <h1>Tarifarios de contrato por año</h1>
        <div class="tarifario-encabezado-acciones">
            <a href="{{ route('tarifa-contrato.servicios') }}" class="btn tarifario-nav-secundario">Servicios por año</a>
            <a href="{{ route('tarifa-contrato.index') }}" class="btn tarifario-nav-principal">Ver tarifas</a>
        </div>
    </header>
    @if(session('success'))<div class="alert tarifario-aviso" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <details class="tarifario-panel" id="guia-tarifarios" data-tarifario-panel open>
        <summary class="tarifario-panel-cabecera">
            <h2 id="guia-tarifarios-titulo"><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-list-ol"></i></span>Pasos para poner un tarifario en uso</h2>
        </summary>
        <div class="tarifario-panel-contenido">
            <ol class="tarifario-guia list-unstyled mb-0" role="list" aria-labelledby="guia-tarifarios-titulo">
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">1</span>
                    <div>
                        <h3 class="h6 mb-1 font-weight-bold">Crea o copia un tarifario</h3>
                        <p class="small text-muted mb-1">Selecciona una empresa registrada y el año. Puedes reutilizar precios con <strong>Copiar tarifas de</strong>. Pulsa <strong>Crear tarifario</strong> para guardar el borrador vinculado a la empresa.</p>
                        @aclcan('save', null, 'tarifa-contrato')
                            <a class="small" href="#nuevo-tarifario">Ir a Crear o renovar</a>
                        @endaclcan
                    </div>
                </li>
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">2</span>
                    <div>
                        <h3 class="h6 mb-1 font-weight-bold">Carga y revisa los precios</h3>
                        <p class="small text-muted mb-1">En el borrador, abre <strong>Acciones</strong> y elige <strong>Cargar tarifas</strong>. Completa la hoja o importa tu Excel desde esa pantalla.</p>
                        <a class="small" href="#tarifarios-registrados">Ver tarifarios registrados</a>
                    </div>
                </li>
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">3</span>
                    <div>
                        <h3 class="h6 mb-1 font-weight-bold">Aprueba el tarifario</h3>
                        <p class="small text-muted mb-0">Cuando los precios estén listos, pulsa <strong>Aprobar uso</strong> en <strong>Acciones</strong>. El tarifario quedará <strong>En uso</strong> para las nuevas guías de la empresa titular.</p>
                    </div>
                </li>
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">4</span>
                    <div>
                        <h3 class="h6 mb-1 font-weight-bold">Comparte el tarifario (opcional)</h3>
                        <p class="small text-muted mb-1">Si otras empresas usarán esos precios, elige <strong>Asignar a empresas</strong> en <strong>Acciones</strong>.</p>
                        <a class="small" href="#historial-tarifarios">Consultar el historial por empresa</a>
                    </div>
                </li>
            </ol>
        </div>
    </details>
    @php $copy = $tarifarios->firstWhere('id', (int) request('copiar_de_id')); @endphp
    @aclcan('save', null, 'tarifa-contrato')
    <details class="tarifario-panel" id="nuevo-tarifario" data-tarifario-panel @if($copy || old('formulario_tarifario') === 'crear') open @endif>
        <summary class="tarifario-panel-cabecera">
            <h2><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-plus"></i></span>Crear o renovar un tarifario</h2>
        </summary>
        <div class="tarifario-panel-contenido">
            <form method="POST" action="{{ route('tarifa-contrato.tarifarios-store') }}">
                @csrf
                <input type="hidden" name="formulario_tarifario" value="crear">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="empresa_titular_id">Nombre de la empresa *</label>
                        <select class="form-control @error('empresa_titular_id') is-invalid @enderror" id="empresa_titular_id" name="empresa_titular_id" aria-describedby="empresa-tarifario-ayuda" required>
                            <option value="">Selecciona una empresa registrada…</option>
                            @foreach($empresas as $empresa)
                                <option value="{{ $empresa->id }}" @selected((string) old('empresa_titular_id', $copy?->empresa_titular_id) === (string) $empresa->id)>{{ $empresa->nombre }}@if(filled($empresa->codigo_cliente)) · {{ $empresa->codigo_cliente }}@endif</option>
                            @endforeach
                        </select>
                        @error('empresa_titular_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                                <option value="{{ $item->id }}" @selected((string) old('copiar_de_id', $copy?->id) === (string) $item->id)>{{ $item->etiquetaSeleccion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end"><button class="btn tarifario-boton-amarillo" type="submit">Crear tarifario</button></div>
                </div>
                <p class="small text-muted mb-0" id="empresa-tarifario-ayuda">El tarifario se guarda con el nombre de la empresa seleccionada y queda asociado a ella.</p>
            </form>
        </div>
    </details>
    @endaclcan
    <details class="tarifario-panel" id="tarifarios-registrados" data-tarifario-panel data-tarifario-lista open>
        <summary class="tarifario-panel-cabecera">
            <h2 id="tarifarios-registrados-titulo"><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-table"></i></span>Tarifarios registrados</h2>
        </summary>
        @aclcan('save', null, 'tarifa-contrato')
            <div class="tarifario-panel-contenido py-2">
                <a class="btn btn-sm tarifario-boton-borde" href="#nuevo-tarifario"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Crear tarifario para una empresa</a>
            </div>
        @endaclcan
        @if($tarifarios->isNotEmpty())
            <div class="tarifario-lista-controles" data-tarifario-controles hidden>
                <div class="tarifario-buscador">
                    <label class="small mb-1" for="buscar-tarifario">Buscar tarifario</label>
                    <input type="search" id="buscar-tarifario" class="form-control" placeholder="Nombre, empresa, año o estado" data-tarifario-busqueda>
                </div>
                <p class="small text-muted mb-0" role="status" data-tarifario-resultados></p>
            </div>
        @endif
        <div class="table-responsive tarifario-tabla-contenedor">
            <table class="table table-hover tarifario-tabla" aria-labelledby="tarifarios-registrados-titulo">
                <caption class="sr-only">Tarifarios registrados</caption>
                <thead><tr><th scope="col">Empresa titular / Tarifario</th><th scope="col">Año</th><th scope="col">Tarifas</th><th scope="col">Empresas que lo usan</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                    @forelse($tarifarios as $item)
                        @php
                            [$estado, $color] = match (true) {
                                !$item->publicado_en => ['Borrador', 'borrador'],
                                (bool) $item->empresas_activas_count => ['En uso', 'uso'],
                                (bool) $item->empresas_exists => ['Histórico', 'historico'],
                                default => ['Aprobado', 'aprobado'],
                            };
                            $empresasActivas = $item->empresasActivas->pluck('nombre')->join(', ');
                        @endphp
                        <tr data-tarifario-fila="{{ $item->etiquetaSeleccion }} {{ $estado }} {{ $empresasActivas ?: 'Sin asignar' }}">
                            <th scope="row" class="tarifario-fila-titulo">
                                {{ $item->empresaTitular?->nombre ?? $item->nombre }}
                                @if(filled($item->empresaTitular?->codigo_cliente))<small class="d-block text-muted">Código {{ $item->empresaTitular->codigo_cliente }}</small>@endif
                            </th>
                            <td>{{ $item->anio ?? 'Por definir' }}</td>
                            <td class="tarifario-cantidad">{{ $item->tarifas_count }}</td>
                            <td>
                                @if($empresasActivas)
                                    {{ $empresasActivas }}
                                @elseif($item->empresaTitular && !$item->publicado_en)
                                    <span class="text-muted">Pendiente de aprobación</span>
                                @else
                                    <span class="text-muted">Sin asignar</span>
                                @endif
                            </td>
                            <td class="tarifario-columna-acciones">
                                <div class="tarifario-acciones">
                                    <span class="tarifario-estado tarifario-estado--{{ $color }}">{{ $estado }}</span>
                                    <button class="btn btn-sm tarifario-boton-azul" type="button" data-abrir-acciones="tarifario-acciones-{{ $item->id }}" aria-haspopup="dialog" aria-controls="tarifario-acciones-{{ $item->id }}" aria-label="Acciones para {{ $item->etiquetaSeleccion }}">
                                        <i class="fas fa-ellipsis-h mr-1" aria-hidden="true"></i>Acciones
                                    </button>
                                </div>
                                <noscript><a href="{{ route('tarifa-contrato.index', ['tarifario_id' => $item->id]) }}">Ver tarifas</a></noscript>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Crea tu primer tarifario por año para cargar sus precios.</td></tr>
                    @endforelse
                    <tr data-tarifario-sin-resultados hidden><td colspan="5" class="text-center text-muted py-4">No se encontraron tarifarios con esa búsqueda.</td></tr>
                </tbody>
            </table>
        </div>
    </details>
    <details class="tarifario-panel" id="historial-tarifarios" data-tarifario-panel data-tarifario-lista>
        <summary class="tarifario-panel-cabecera">
            <h2 id="historial-tarifarios-titulo"><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-history"></i></span>Tarifario asignado e historial por empresa</h2>
        </summary>
        @if($empresas->isNotEmpty())
            <div class="tarifario-lista-controles" data-tarifario-controles hidden>
                <div class="tarifario-buscador">
                    <label class="small mb-1" for="buscar-historial-empresa">Buscar empresa</label>
                    <input type="search" id="buscar-historial-empresa" class="form-control" placeholder="Empresa, código o tarifario" data-tarifario-busqueda>
                </div>
                <p class="small text-muted mb-0" role="status" data-tarifario-resultados></p>
            </div>
        @endif
        <div class="table-responsive tarifario-tabla-contenedor">
            <table class="table table-hover tarifario-tabla" aria-labelledby="historial-tarifarios-titulo">
                <caption class="sr-only">Historial de tarifarios por empresa</caption>
                <thead><tr><th scope="col">Empresa</th><th scope="col">Tarifario actual</th><th scope="col">Tarifarios anteriores</th></tr></thead>
                <tbody>
                    @forelse($empresas as $empresa)
                        @php
                            $actual = $empresa->tarifariosContrato->first(fn ($item) => $item->pivot->activo);
                            $anteriores = $empresa->tarifariosContrato->filter(fn ($item) => !$item->pivot->activo);
                        @endphp
                        <tr data-tarifario-fila="{{ $empresa->nombre }} {{ $empresa->codigo_cliente }} {{ $actual?->etiqueta ?? 'Sin tarifario asignado' }} {{ $anteriores->pluck('etiqueta')->join(' ') }}">
                            <th scope="row" class="tarifario-fila-titulo">
                                {{ $empresa->nombre }}
                                @if(filled($empresa->codigo_cliente))<small class="d-block text-muted">Código {{ $empresa->codigo_cliente }}</small>@endif
                            </th>
                            <td>
                                @if($actual)
                                    <a class="font-weight-bold" href="{{ route('tarifa-contrato.index', ['tarifario_id' => $actual->id]) }}">{{ $actual->etiqueta }}</a>
                                @else
                                    <span class="text-muted">Sin tarifario asignado</span>
                                @endif
                            </td>
                            <td class="tarifario-columna-historial">
                                @if($anteriores->isNotEmpty())
                                    <details class="tarifario-desplegable">
                                        <summary>Ver anteriores ({{ $anteriores->count() }})</summary>
                                        <ul class="list-unstyled mt-2 mb-0">
                                            @foreach($anteriores as $anterior)
                                                <li class="mb-2">
                                                    <a href="{{ route('tarifa-contrato.index', ['tarifario_id' => $anterior->id]) }}">{{ $anterior->etiqueta }}</a>
                                                    @if($anterior->pivot->finalizado_en)
                                                        <small class="d-block text-muted">Hasta {{ \Illuminate\Support\Carbon::parse($anterior->pivot->finalizado_en)->format('d/m/Y') }}</small>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <span class="text-muted">Sin anteriores</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">No hay empresas registradas.</td></tr>
                    @endforelse
                    <tr data-tarifario-sin-resultados hidden><td colspan="3" class="text-center text-muted py-4">No se encontraron empresas con esa búsqueda.</td></tr>
                </tbody>
            </table>
        </div>
    </details>
    @foreach($tarifarios as $item)
        @include('tarifa_contrato.tarifario-acciones', ['item' => $item])
    @endforeach
    @aclcan('save', null, 'tarifa-contrato')
        <dialog class="tarifario-modal tarifario-aprobacion" id="tarifario-aprobacion" aria-labelledby="tarifario-aprobacion-titulo" aria-describedby="tarifario-aprobacion-aviso">
            <header class="tarifario-aprobacion-cabecera">
                <span class="tarifario-aprobacion-icono" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>
                <h2 id="tarifario-aprobacion-titulo">¿Seguro que quieres aprobar este tarifario para su uso oficial?</h2>
            </header>
            <div class="tarifario-aprobacion-contenido">
                <p id="tarifario-aprobacion-aviso">Antes de confirmar, revisa los servicios, destinos, precios y plazos de entrega.</p>
                <dl class="tarifario-aprobacion-datos">
                    <div><dt>Tarifario</dt><dd data-aprobacion-nombre></dd></div>
                    <div><dt>Año</dt><dd data-aprobacion-anio></dd></div>
                    <div><dt>Tarifas</dt><dd data-aprobacion-cantidad></dd></div>
                </dl>
                <p class="tarifario-aprobacion-efecto" data-aprobacion-efecto></p>
            </div>
            <form method="dialog" class="tarifario-aprobacion-acciones">
                <a class="btn tarifario-boton-borde" data-aprobacion-revisar><i class="fas fa-search mr-1" aria-hidden="true"></i>Revisar tarifas</a>
                <button class="btn tarifario-boton-borde" type="submit" autofocus>Cancelar</button>
                <button class="btn tarifario-boton-amarillo" type="button" data-aprobacion-confirmar><i class="fas fa-check mr-1" aria-hidden="true"></i>Sí, aprobar tarifario</button>
            </form>
        </dialog>
    @endaclcan
</section>
@include('footer')
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/tarifarios-contrato.css') }}?v={{ filemtime(public_path('css/tarifarios-contrato.css')) }}">
@endpush

@push('js')
    <script src="{{ asset('js/tarifarios-contrato.js') }}?v={{ filemtime(public_path('js/tarifarios-contrato.js')) }}" defer></script>
@endpush
