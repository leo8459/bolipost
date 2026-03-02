@extends('adminlte::page')
@section('title', 'Contratos Entregados')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Contratos Entregados</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Receptor</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contratos as $c)
                                <tr>
                                    <td>{{ $c->codigo }}</td>
                                    <td>{{ $c->origen }}</td>
                                    <td>{{ $c->destino }}</td>
                                    <td>{{ $c->nombre_r }}</td>
                                    <td>{{ optional($c->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('paquetes-contrato.reporte', $c->id) }}" class="btn btn-sm btn-outline-primary" target="_blank">Reporte</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No hay contratos entregados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer clearfix">
                {{ $contratos->links() }}
            </div>
        </div>
    </div>
@endsection
