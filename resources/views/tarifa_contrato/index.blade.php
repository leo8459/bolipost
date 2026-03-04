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
                        <form method="GET" action="{{ route('tarifa-contrato.index') }}" class="mb-3">
                            <div class="input-group">
                                <input
                                    type="text"
                                    name="q"
                                    value="{{ $q }}"
                                    class="form-control"
                                    placeholder="Buscar por empresa, origen, destino, servicio o provincia"
                                >
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-outline-primary">Buscar</button>
                                </div>
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
