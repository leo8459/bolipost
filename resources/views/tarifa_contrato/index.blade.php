@extends('adminlte::page')
@section('title', 'Tarifa Contrato')
@section('template_title')
    Tarifa Contrato
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Tarifas de Contrato</span>
                            <div class="d-flex" style="gap:8px;">
                                <a href="{{ route('tarifa-contrato.import-form') }}" class="btn btn-info btn-sm">
                                    Importar Excel
                                </a>
                                <a href="{{ route('tarifa-contrato.create') }}" class="btn btn-primary btn-sm">
                                    Crear Nuevo
                                </a>
                            </div>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif
                    @if ($message = Session::get('warning'))
                        <div class="alert alert-warning m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif
                    @if (session()->has('import_errors'))
                        <div class="alert alert-danger m-3 mb-0">
                            <p class="mb-2"><strong>Errores de importacion (primeros 20):</strong></p>
                            <ul class="mb-0">
                                @foreach (session('import_errors', []) as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="card-body">
                        @php
                            $empresaSeleccionada = ($empresasFiltro ?? collect())->firstWhere('id', (int) $empresaId);
                        @endphp

                        <form method="GET" action="{{ route('tarifa-contrato.index') }}" class="mb-3" id="tarifaFiltersForm">
                            <div class="alert alert-light border d-flex flex-column flex-md-row justify-content-between align-items-md-center py-2">
                                <div>
                                    <strong>Filtro dinamico activado:</strong>
                                    cambia empresa, servicio, origen, destino o busqueda y se actualiza solo.
                                </div>
                                <span class="badge badge-success mt-2 mt-md-0" id="tarifaAutoStatus">Listo</span>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Empresa</label>
                                        <select name="empresa_id" id="filterEmpresa" class="form-control">
                                            <option value="">Todas</option>
                                            @foreach($empresasFiltro as $empresa)
                                                <option value="{{ $empresa->id }}" {{ (int) $empresaId === (int) $empresa->id ? 'selected' : '' }}>
                                                    {{ $empresa->nombre }}@if(!empty($empresa->sigla)) ({{ $empresa->sigla }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Servicio</label>
                                        <select name="servicio" id="filterServicio" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($serviciosFiltro as $itemServicio)
                                                <option value="{{ $itemServicio }}" {{ strtoupper(trim((string) $servicio)) === strtoupper(trim((string) $itemServicio)) ? 'selected' : '' }}>
                                                    {{ $itemServicio }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Origen</label>
                                        <select name="origen" id="filterOrigen" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($origenesFiltro as $itemOrigen)
                                                <option value="{{ $itemOrigen }}" {{ strtoupper(trim((string) $origen)) === strtoupper(trim((string) $itemOrigen)) ? 'selected' : '' }}>
                                                    {{ $itemOrigen }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Destino</label>
                                        <select name="destino" id="filterDestino" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($destinosFiltro as $itemDestino)
                                                <option value="{{ $itemDestino }}" {{ strtoupper(trim((string) $destino)) === strtoupper(trim((string) $itemDestino)) ? 'selected' : '' }}>
                                                    {{ $itemDestino }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="form-group mb-0">
                                        <label>Busqueda general (opcional)</label>
                                        <input
                                            type="text"
                                            name="q"
                                            id="filterSearch"
                                            value="{{ $q }}"
                                            class="form-control"
                                            placeholder="Empresa, provincia, horas de entrega..."
                                        >
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-group mb-0 w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar ahora</button>
                                        <a href="{{ route('tarifa-contrato.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>

                            <div class="small text-muted mt-2">
                                Filtros activos:
                                @if($empresaSeleccionada)
                                    <span class="badge badge-info mr-1">Empresa: {{ $empresaSeleccionada->nombre }}</span>
                                @endif
                                @if(trim((string) $servicio) !== '')
                                    <span class="badge badge-primary mr-1">Servicio: {{ $servicio }}</span>
                                @endif
                                @if(trim((string) $origen) !== '')
                                    <span class="badge badge-secondary mr-1">Origen: {{ $origen }}</span>
                                @endif
                                @if(trim((string) $destino) !== '')
                                    <span class="badge badge-dark mr-1">Destino: {{ $destino }}</span>
                                @endif
                                @if(trim((string) $q) !== '')
                                    <span class="badge badge-warning mr-1">Busqueda: {{ $q }}</span>
                                @endif
                                @if(!$empresaSeleccionada && trim((string) $servicio) === '' && trim((string) $origen) === '' && trim((string) $destino) === '' && trim((string) $q) === '')
                                    <span class="badge badge-light border">Ninguno</span>
                                @endif
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>ID</th>
                                        <th>Empresa</th>
                                        <th>Servicio</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Kilo</th>
                                        <th>Kilo Extra</th>
                                        <th>Provincia</th>
                                        <th>Retencion</th>
                                        <th>Horas Entrega</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tarifas as $tarifa)
                                        <tr>
                                            <td>{{ $tarifa->id }}</td>
                                            <td>{{ $tarifa->empresa->nombre ?? '' }}</td>
                                            <td>{{ $tarifa->servicio }}</td>
                                            <td>{{ $tarifa->origen }}</td>
                                            <td>{{ $tarifa->destino }}</td>
                                            <td>{{ $tarifa->kilo }}</td>
                                            <td>{{ $tarifa->kilo_extra }}</td>
                                            <td>{{ $tarifa->provincia }}</td>
                                            <td>{{ number_format((float) $tarifa->retencion, 2) }}%</td>
                                            <td>{{ $tarifa->horas_entrega }}</td>
                                            <td>
                                                <form action="{{ route('tarifa-contrato.destroy', $tarifa->id) }}" method="POST">
                                                    <a
                                                        class="btn btn-sm btn-info"
                                                        href="{{ route('tarifa-contrato.create', ['copy_id' => $tarifa->id]) }}"
                                                        title="Duplicar"
                                                    >
                                                        <i class="fa fa-fw fa-copy"></i>
                                                    </a>
                                                    <a class="btn btn-sm btn-success" href="{{ route('tarifa-contrato.edit', $tarifa->id) }}" title="Editar">
                                                        <i class="fa fa-fw fa-edit"></i>
                                                    </a>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                        title="Eliminar"
                                                        onclick="return confirm('Seguro que deseas eliminar esta tarifa de contrato?')"
                                                    >
                                                        <i class="fa fa-fw fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center py-4">No hay registros</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $tarifas->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection

@section('js')
<script>
    (() => {
        const form = document.getElementById('tarifaFiltersForm');
        const statusBadge = document.getElementById('tarifaAutoStatus');

        if (!form || !statusBadge) {
            return;
        }

        const autoFields = form.querySelectorAll(
            'select[name="empresa_id"], select[name="servicio"], select[name="origen"], select[name="destino"]'
        );
        const searchField = document.getElementById('filterSearch');

        let timer = null;
        let submitting = false;

        const setStatus = (text, cls) => {
            statusBadge.textContent = text;
            statusBadge.className = `badge ${cls} mt-2 mt-md-0`;
        };

        const scheduleSubmit = (delay = 220) => {
            if (submitting) {
                return;
            }
            if (timer) {
                clearTimeout(timer);
            }
            setStatus('Cambio detectado', 'badge-info');
            timer = setTimeout(() => {
                submitting = true;
                setStatus('Filtrando...', 'badge-warning');
                form.submit();
            }, delay);
        };

        autoFields.forEach((field) => {
            field.addEventListener('change', () => scheduleSubmit(180));
        });

        if (searchField) {
            searchField.addEventListener('input', () => scheduleSubmit(650));
            searchField.addEventListener('change', () => scheduleSubmit(250));
        }

        form.addEventListener('submit', () => {
            submitting = true;
            setStatus('Filtrando...', 'badge-primary');
        });
    })();
</script>
@endsection
