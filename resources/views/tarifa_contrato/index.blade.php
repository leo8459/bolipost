@extends('adminlte::page')
@section('title', 'Tarifas de contrato')
@section('template_title', 'Tarifas de contrato')

@section('content')
@php
    $tarifarioSeleccionado = $tarifarios->firstWhere('id', $tarifarioId);
    $empresaSeleccionada = $empresasFiltro->firstWhere('id', $empresaId);
    $empresasEnUso = $tarifarioSeleccionado ? $tarifas->first()?->tarifario?->empresasActivas->pluck('nombre')->join(', ') : '';
    $destinoEditable = $tarifarioSeleccionado && !$tarifarioSeleccionado->publicado_en ? $tarifarioSeleccionado->id : null;
    $tieneFiltros = $tarifarioId || $empresaId || $servicio !== '' || $origen !== '' || $destino !== '' || $q !== '';
    $tarifaReportUser = auth()->user();
    $tarifaReportSuperRole = (string) config('acl.super_admin_role', 'administrador');
    $canTarifaReport = $tarifaReportUser && (
        ($tarifaReportSuperRole !== '' && method_exists($tarifaReportUser, 'hasRole') && $tarifaReportUser->hasRole($tarifaReportSuperRole))
        || $tarifaReportUser->can('feature.tarifa-contrato.report')
        || $tarifaReportUser->can('feature.tarifa-contrato.export')
    );
@endphp
<section class="content container-fluid pt-3 tarifario-pagina">
    <header class="tarifario-encabezado">
        <div>
            <h1>Tarifas de contrato</h1>
            <p>Consulta los precios por tarifario, servicio y destino.</p>
        </div>
        <nav class="tarifario-encabezado-acciones" aria-label="Administración de tarifas">
            <a href="{{ route('tarifa-contrato.servicios') }}" class="btn tarifario-nav-secundario">Servicios por año</a>
            <a href="{{ route('tarifa-contrato.tarifarios') }}#tarifarios-registrados" class="btn tarifario-nav-principal">Tarifarios por empresa y año</a>
        </nav>
    </header>

    @include('tarifa_contrato.import-feedback', ['successClass' => 'tarifario-aviso'])
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <details class="tarifario-panel" id="guia-consulta-tarifas" data-tarifario-panel>
        <summary class="tarifario-panel-cabecera">
            <h2 id="guia-consulta-titulo"><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-list-ol"></i></span>Cómo consultar las tarifas</h2>
        </summary>
        <div class="tarifario-panel-contenido">
            <ol class="tarifario-guia list-unstyled mb-0" role="list" aria-labelledby="guia-consulta-titulo">
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">1</span>
                    <div><h3 class="h6 mb-1 font-weight-bold">Elige un tarifario</h3><p class="small text-muted mb-0">Selecciona el nombre y el año, o elige una empresa para consultar su tarifario en uso.</p></div>
                </li>
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">2</span>
                    <div><h3 class="h6 mb-1 font-weight-bold">Busca la ruta</h3><p class="small text-muted mb-0">Usa los filtros de servicio, origen y destino. Abre <strong>Ver detalles</strong> para consultar provincias, retención y otros datos.</p></div>
                </li>
                <li>
                    <span class="tarifario-guia-numero" aria-hidden="true">3</span>
                    <div><h3 class="h6 mb-1 font-weight-bold">Descarga el tarifario</h3><p class="small text-muted mb-0">Con un tarifario o una empresa seleccionados, descarga el Excel completo, separado por servicio y ordenado por destino.</p></div>
                </li>
            </ol>
        </div>
    </details>

    <details class="tarifario-panel" id="filtros-tarifas" data-tarifario-panel open>
        <summary class="tarifario-panel-cabecera">
            <h2><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-filter"></i></span>Buscar y filtrar tarifas</h2>
        </summary>
        <form method="GET" action="{{ route('tarifa-contrato.index') }}" class="tarifario-panel-contenido" id="tarifaFiltersForm">
            <div class="tarifario-filtros">
                <div>
                    <label for="filterTarifario">Tarifario y año</label>
                    <select name="tarifario_id" id="filterTarifario" class="form-control">
                        <option value="">Todos los tarifarios</option>
                        @foreach($tarifarios as $item)
                            <option value="{{ $item->id }}" @selected($tarifarioId === $item->id)>{{ $item->etiquetaSeleccion }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filterEmpresa">Empresa con tarifario en uso</label>
                    <select name="empresa_id" id="filterEmpresa" class="form-control">
                        <option value="">Todas las empresas</option>
                        @foreach($empresasFiltro as $empresa)
                            <option value="{{ $empresa->id }}" @selected($empresaId === $empresa->id)>{{ $empresa->nombre }}@if(filled($empresa->sigla)) ({{ $empresa->sigla }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filterServicio">Servicio</label>
                    <select name="servicio" id="filterServicio" class="form-control">
                        <option value="">Todos los servicios</option>
                        @foreach($serviciosFiltro as $itemServicio)
                            <option value="{{ $itemServicio }}" @selected($servicio === strtoupper(trim($itemServicio)))>{{ $itemServicio }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filterOrigen">Origen</label>
                    <select name="origen" id="filterOrigen" class="form-control">
                        <option value="">Todos los orígenes</option>
                        @foreach($origenesFiltro as $itemOrigen)
                            <option value="{{ $itemOrigen }}" @selected($origen === $itemOrigen)>{{ $itemOrigen }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filterDestino">Destino</label>
                    <select name="destino" id="filterDestino" class="form-control">
                        <option value="">Todos los destinos</option>
                        @foreach($destinosFiltro as $itemDestino)
                            <option value="{{ $itemDestino }}" @selected($destino === $itemDestino)>{{ $itemDestino }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filterSearch">Búsqueda general</label>
                    <input type="search" name="q" id="filterSearch" value="{{ $q }}" class="form-control" placeholder="Empresa, provincia o plazo de entrega">
                </div>
            </div>
            <div class="tarifario-filtros-acciones">
                <button type="submit" class="btn btn-sm tarifario-boton-azul"><i class="fas fa-search mr-1" aria-hidden="true"></i>Aplicar filtros</button>
                @if($tieneFiltros)<a href="{{ route('tarifa-contrato.index') }}" class="btn btn-sm tarifario-boton-borde">Limpiar filtros</a>@endif
                <span class="small text-muted" id="tarifaAutoStatus" role="status" hidden>Actualizando resultados…</span>
            </div>
        </form>
    </details>

    <section class="tarifario-panel" id="resultados-tarifas" aria-labelledby="resultados-tarifas-titulo">
        <div class="tarifario-panel-contenido tarifario-resultados-cabecera">
            <div>
                <h2 id="resultados-tarifas-titulo">{{ $tarifarioSeleccionado?->etiquetaSeleccion ?? $empresaSeleccionada?->nombre ?? 'Tarifas registradas' }}</h2>
                @if($tarifas->count())
                    <p class="tarifario-resultados-contexto">Mostrando {{ $tarifas->firstItem() }}–{{ $tarifas->lastItem() }} de {{ number_format($tarifas->total(), 0, ',', '.') }} {{ $tarifas->total() === 1 ? 'tarifa' : 'tarifas' }}</p>
                @endif
                @if($tarifarioSeleccionado)
                    <p class="tarifario-resultados-contexto">
                        @if($tarifarioSeleccionado->publicado_en)
                            @if($empresasEnUso)<strong>En uso por {{ $empresasEnUso }}.</strong>@endif
                            Precios protegidos. Renueva el tarifario desde <a href="{{ route('tarifa-contrato.tarifarios') }}#tarifarios-registrados">Tarifarios registrados</a>.
                        @else
                            <span class="tarifario-estado tarifario-estado--borrador">Borrador</span>
                            Aprueba los precios desde <a href="{{ route('tarifa-contrato.tarifarios') }}#tarifarios-registrados">Tarifarios registrados</a>.
                        @endif
                    </p>
                @endif
            </div>
            <div class="tarifario-reportes">
                @aclcan('export', null, 'tarifa-contrato')
                    @if($tarifarioId || $empresaId)
                        <a href="{{ route('tarifa-contrato.export', ['tarifario_id' => $tarifarioId ?: null, 'empresa_id' => $empresaId ?: null]) }}" class="btn btn-sm tarifario-boton-amarillo" title="Tarifario completo por servicio y destino">
                            <i class="fas fa-file-excel mr-1" aria-hidden="true"></i>Descargar Excel
                        </a>
                    @else
                        <a href="#filtros-tarifas" class="btn btn-sm tarifario-boton-borde" title="Elige un tarifario o una empresa para descargar"><i class="fas fa-file-excel mr-1" aria-hidden="true"></i>Elegir tarifario para Excel</a>
                    @endif
                @endaclcan
                @if($canTarifaReport)
                    <a href="{{ route('tarifa-contrato.reporte-detalle', ['tarifario_id' => $tarifarioId ?: null]) }}" target="_blank" rel="noopener" class="btn btn-sm tarifario-boton-borde"><i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>Detalle PDF</a>
                    <a href="{{ route('tarifa-contrato.reporte') }}" target="_blank" rel="noopener" class="btn btn-sm tarifario-boton-borde">Reporte general</a>
                @endif
                @aclcan('import', null, 'tarifa-contrato')
                    <a href="{{ route('tarifa-contrato.import-form', ['tarifario_id' => $destinoEditable]) }}" class="btn btn-sm tarifario-boton-borde">Importar Excel</a>
                @endaclcan
                @aclcan('create', null, 'tarifa-contrato')
                    <a href="{{ route('tarifa-contrato.create', ['tarifario_id' => $destinoEditable]) }}" class="btn btn-sm tarifario-boton-azul"><i class="fas fa-plus mr-1" aria-hidden="true"></i>{{ $destinoEditable ? 'Cargar tarifas' : 'Registrar tarifas' }}</a>
                @endaclcan
            </div>
        </div>
        <div class="table-responsive tarifario-tabla-contenedor">
            <table class="table table-hover tarifario-tabla" aria-labelledby="resultados-tarifas-titulo">
                <caption class="sr-only">Precios de contrato por servicio y ruta</caption>
                <thead>
                    <tr>
                        @unless($tarifarioSeleccionado)<th scope="col">Tarifario</th>@endunless
                        <th scope="col">Servicio</th>
                        <th scope="col">Origen → Destino</th>
                        <th scope="col">Hasta 1 kg</th>
                        <th scope="col">Kg adicional</th>
                        <th scope="col">Plazo de entrega</th>
                        <th scope="col">Detalles y acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tarifas as $tarifa)
                        @php $zona = $tarifa->zonaLocal() ?? $tarifa->zona; @endphp
                        <tr>
                            @unless($tarifarioSeleccionado)
                                <td>
                                    <strong>{{ $tarifa->tarifario?->etiquetaSeleccion ?? 'Sin tarifario' }}</strong>
                                    @if($empresasActivas = $tarifa->tarifario?->empresasActivas->pluck('nombre')->join(', '))
                                        <small class="d-block text-muted">{{ $empresasActivas }}</small>
                                    @endif
                                </td>
                            @endunless
                            <th scope="row" class="tarifario-fila-titulo">
                                <div>{{ $tarifa->servicio }}@if(filled($zona))<br><span class="tarifario-zona">{{ $zona }}</span>@endif</div>
                            </th>
                            <td class="tarifario-ruta"><span>{{ $tarifa->origen }} <i class="fas fa-arrow-right" aria-hidden="true"></i><span class="sr-only">a</span> {{ $tarifa->destino }}</span></td>
                            <td class="tarifario-precio"><strong>Bs {{ \App\Support\BolivianNumber::format($tarifa->kilo, 2) }}</strong></td>
                            <td class="tarifario-precio"><span>Bs {{ \App\Support\BolivianNumber::format($tarifa->kilo_extra, 2) }}</span></td>
                            <td><span>{{ $tarifa->horas_entrega }} h</span></td>
                            <td class="tarifario-detalle-tarifa">
                                <details class="tarifario-desplegable">
                                    <summary>Ver detalles</summary>
                                    <dl>
                                        <dt>Código</dt><dd>{{ $tarifa->id }}</dd>
                                        <dt>Dirección</dt><dd>{{ $tarifa->direccion ?: '—' }}</dd>
                                        <dt>Provincia de origen</dt><dd>{{ $tarifa->provincia_origen ?: '—' }}</dd>
                                        <dt>Provincia de destino</dt><dd>{{ $tarifa->provincia ?: '—' }}</dd>
                                        <dt>Peso de referencia</dt><dd>{{ $tarifa->peso !== null ? \App\Support\BolivianNumber::format($tarifa->peso, 2).' kg' : '—' }}</dd>
                                        <dt>Retención</dt><dd>{{ \App\Support\BolivianNumber::format($tarifa->retencion, 2) }} %</dd>
                                    </dl>
                                    <div class="tarifario-fila-acciones">
                                        @aclcan('duplicate', null, 'tarifa-contrato')
                                            <a class="btn btn-sm tarifario-boton-borde" href="{{ route('tarifa-contrato.create', ['copy_id' => $tarifa->id]) }}">Duplicar</a>
                                        @endaclcan
                                        @if(!$tarifa->tarifario?->publicado_en)
                                            @aclcan('edit', null, 'tarifa-contrato')
                                                <a class="btn btn-sm tarifario-boton-borde" href="{{ route('tarifa-contrato.edit', $tarifa->id) }}">Editar</a>
                                            @endaclcan
                                            @aclcan('delete', null, 'tarifa-contrato')
                                                <form action="{{ route('tarifa-contrato.destroy', $tarifa->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta tarifa de contrato?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                                </form>
                                            @endaclcan
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $tarifarioSeleccionado ? 6 : 7 }}" class="text-center py-4">
                            <strong>No se encontraron tarifas.</strong>
                            @if($tieneFiltros)<p class="small text-muted mb-0">Cambia los filtros para ver otras rutas o servicios.</p>@endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tarifas->hasPages())
            <div class="tarifario-paginacion">{{ $tarifas->fragment('resultados-tarifas')->links() }}</div>
        @endif
    </section>
</section>
@include('footer')
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/tarifarios-contrato.css') }}?v={{ filemtime(public_path('css/tarifarios-contrato.css')) }}">
@endpush
@push('js')
    <script src="{{ asset('js/tarifarios-contrato.js') }}?v={{ filemtime(public_path('js/tarifarios-contrato.js')) }}" defer></script>
@endpush
