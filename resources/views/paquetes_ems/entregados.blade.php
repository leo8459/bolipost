@extends('adminlte::page')
@section('title', 'Paquetes EMS - Entregados')
@section('template_title')
    Paquetes EMS - Entregados
@endsection

@section('content')
    <div class="ems-entregados-wrap">
        <div class="card ems-entregados-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-2 mb-md-0">Entregados</h3>
                <span class="ems-badge">Total: {{ $paquetes->total() }}</span>
            </div>
            <div class="card-body">
                @if (!$estadoDomicilioDisponible)
                    <div class="alert alert-warning">
                        No existe el estado DOMICILIO en la tabla estados.
                    </div>
                @endif

                <form method="GET" action="{{ route('paquetes-ems.entregados') }}" class="row mb-3">
                    <div class="col-md-10 mb-2 mb-md-0">
                        <input type="text" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Buscar EMS/Contrato por codigo, destinatario, telefono, ciudad, recibido por o descripcion...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Buscar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Codigo</th>
                                <th>Destinatario</th>
                                <th>Telefono</th>
                                <th>Ciudad</th>
                                <th>Peso</th>
                                <th>Recibido por</th>
                                <th>Descripcion</th>
                                <th>Asignado a</th>
                                <th>Fecha entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                <tr>
                                    <td>{{ $paquete->tipo_paquete }}</td>
                                    <td>{{ $paquete->codigo }}</td>
                                    <td>{{ $paquete->destinatario }}</td>
                                    <td>{{ $paquete->telefono ?: '-' }}</td>
                                    <td>{{ $paquete->ciudad }}</td>
                                    <td>{{ $paquete->peso }}</td>
                                    <td>{{ $paquete->recibido_por ?: '-' }}</td>
                                    <td>{{ $paquete->descripcion ?: '-' }}</td>
                                    <td>{{ $paquete->asignado_a ?: '-' }}</td>
                                    <td>
                                        {{ !empty($paquete->fecha_entrega) ? \Illuminate\Support\Carbon::parse($paquete->fecha_entrega)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        No hay registros en estado DOMICILIO.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-end">
                    {{ $paquetes->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .ems-entregados-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .ems-entregados-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .ems-entregados-card .card-header {
            background: linear-gradient(95deg, #34447c 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 0.95rem 1.1rem;
        }

        .ems-badge {
            background: rgba(185, 156, 70, 0.2);
            color: #3f3514;
            border: 1px solid rgba(185, 156, 70, 0.35);
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.28rem 0.6rem;
        }

        .ems-entregados-card .table thead th {
            background: #edf1fb;
            color: #34447c;
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }
    </style>
@endsection
