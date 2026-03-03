@extends('adminlte::page')
@section('title', 'Area Contratos - Todos')
@section('template_title')
    Area Contratos - Todos
@endsection

@section('content')
    <div class="area-contratos-wrap">
        <div class="card area-contratos-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-2 mb-md-0">Todos los contratos</h3>
                <span class="area-badge">Total: {{ $contratos->total() }}</span>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('area-contratos.todos') }}" class="row mb-3">
                    <div class="col-md-7 mb-2 mb-md-0">
                        <input type="text" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Buscar por codigo, estado, remitente, origen, destino, direcciones o empresa...">
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <select name="estado_id" class="form-control">
                            <option value="0">Todos los estados</option>
                            @foreach ($estados as $estado)
                                <option value="{{ $estado->id }}" {{ (int) $estadoId === (int) $estado->id ? 'selected' : '' }}>
                                    {{ $estado->nombre_estado }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Buscar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Estado</th>
                                <th>Remitente</th>
                                <th>Origen</th>
                                <th>Direccion remitente</th>
                                <th>Destinatario</th>
                                <th>Destino</th>
                                <th>Direccion destinatario</th>
                                <th>Empresa</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contratos as $contrato)
                                <tr>
                                    <td>{{ $contrato->codigo }}</td>
                                    <td>{{ optional($contrato->estadoRegistro)->nombre_estado ?? '-' }}</td>
                                    <td>{{ $contrato->nombre_r ?: '-' }}</td>
                                    <td>{{ $contrato->origen }}</td>
                                    <td>{{ $contrato->direccion_r ?: '-' }}</td>
                                    <td>{{ $contrato->nombre_d ?: '-' }}</td>
                                    <td>{{ $contrato->destino }}</td>
                                    <td>{{ $contrato->direccion_d ?: '-' }}</td>
                                    <td>
                                        {{ optional($contrato->empresa)->nombre ?? '-' }}
                                        @if(!empty(optional($contrato->empresa)->sigla))
                                            ({{ optional($contrato->empresa)->sigla }})
                                        @endif
                                    </td>
                                    <td>{{ optional($contrato->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('paquetes-contrato.reporte', $contrato->id) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-primary">
                                            Reporte
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        No hay contratos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-end">
                    {{ $contratos->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .area-contratos-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .area-contratos-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .area-contratos-card .card-header {
            background: linear-gradient(95deg, #20539A 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 0.95rem 1.1rem;
        }

        .area-badge {
            background: rgba(185, 156, 70, 0.2);
            color: #3f3514;
            border: 1px solid rgba(185, 156, 70, 0.35);
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.28rem 0.6rem;
        }

        .area-contratos-card .table thead th {
            background: #edf1fb;
            color: #20539A;
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }
    </style>
@endsection
