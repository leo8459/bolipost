@extends('adminlte::page')
@section('title', 'Conceptos facturables')
@section('template_title')
    Conceptos facturables
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Conceptos Facturables</span>
                            <a href="{{ route('conceptos-facturacion.create') }}" class="btn btn-primary btn-sm">
                                Crear Nuevo
                            </a>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0"><p class="mb-0">{{ $message }}</p></div>
                    @endif
                    @if ($message = Session::get('error'))
                        <div class="alert alert-danger m-3 mb-0"><p class="mb-0">{{ $message }}</p></div>
                    @endif

                    <div class="card-body">
                        <div class="card card-outline card-warning mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                                <strong>Versión de referencia para cobros manuales</strong>
                                @if($versionManualActual)
                                    <span class="badge badge-success">Vigente: {{ $versionManualActual->codigo }} · {{ $versionManualActual->nombre }}</span>
                                @else
                                    <span class="badge badge-secondary">Sin versión vigente</span>
                                @endif
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">La versión se guarda como referencia de auditoría. El flujo actual de precios no se modifica.</p>
                                <form method="POST" action="{{ route('conceptos-facturacion.versiones.store') }}" class="row align-items-end">
                                    @csrf
                                    <div class="col-md-2">
                                        <label for="version_manual_codigo">Código</label>
                                        <input id="version_manual_codigo" name="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo') }}" maxlength="80" placeholder="TG-2026-02" required>
                                        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="version_manual_nombre">Nombre de referencia</label>
                                        <input id="version_manual_nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" maxlength="180" placeholder="Tarifario general octubre 2026" required>
                                        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="version_manual_observacion">Observación (opcional)</label>
                                        <input id="version_manual_observacion" name="observacion" class="form-control @error('observacion') is-invalid @enderror" value="{{ old('observacion') }}" maxlength="2000" placeholder="Referencia documental o resolución">
                                        @error('observacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-2 mt-2 mt-md-0">
                                        <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('¿Publicar esta versión desde ahora? El flujo actual de precios no se modificará.')">Publicar versión</button>
                                    </div>
                                </form>

                                @if($versionesManuales->isNotEmpty())
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr><th>Versión</th><th>Estado</th><th>Vigencia</th><th>Observación</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($versionesManuales as $versionManual)
                                                    <tr>
                                                        <td><strong>{{ $versionManual->codigo }}</strong> · {{ $versionManual->nombre }}</td>
                                                        <td>{{ $versionManual->estado === 'VIGENTE' ? 'Vigente' : 'Histórica' }}</td>
                                                        <td>{{ $versionManual->vigente_desde?->format('d/m/Y H:i') }}{{ $versionManual->vigente_hasta ? ' – ' . $versionManual->vigente_hasta->format('d/m/Y H:i') : ' – actual' }}</td>
                                                        <td>{{ $versionManual->observacion ?: '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <form method="GET" action="{{ route('conceptos-facturacion.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group mb-0">
                                        <label>Buscar</label>
                                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, codigo, SIN, actividad o descripcion...">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group mb-0 w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                                        <a href="{{ route('conceptos-facturacion.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Act. Economica</th>
                                        <th>Codigo SIN</th>
                                        <th>Codigo</th>
                                        <th>Unidad Medida</th>
                                        <th>Descripcion</th>
                                        <th>Precio Base</th>
                                        <th>Activo</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($conceptos as $concepto)
                                        <tr>
                                            <td>{{ $concepto->nombre }}</td>
                                            <td>{{ $concepto->actividad_economica }}</td>
                                            <td>{{ $concepto->codigo_sin }}</td>
                                            <td>{{ $concepto->codigo }}</td>
                                            <td>{{ $concepto->unidad_medida }}</td>
                                            <td>{{ $concepto->descripcion }}</td>
                                            <td>Bs {{ \App\Support\BolivianNumber::format((float) $concepto->precio_base, 2) }}</td>
                                            <td>{{ $concepto->activo ? 'Si' : 'No' }}</td>
                                            <td>
                                                <div class="d-flex" style="gap:8px;">
                                                    <a class="btn btn-sm btn-success" href="{{ route('conceptos-facturacion.edit', $concepto->id) }}" title="Editar">
                                                        <i class="fa fa-fw fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('conceptos-facturacion.destroy', $concepto->id) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('Seguro que deseas eliminar este concepto facturable?')">
                                                            <i class="fa fa-fw fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="text-center py-4">No hay registros</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $conceptos->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
