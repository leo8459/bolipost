@extends('adminlte::page')
@section('title', 'Paquetes EMS - Entregados')
@section('template_title')
    Paquetes EMS - Entregados
@endsection

@section('content')
    <div class="ems-entregados-wrap">
        <div class="card ems-entregados-card">
            <div class="card-header">
                <div class="ems-header-top">
                    <h3 class="card-title mb-0">Entregados</h3>
                </div>
                <div class="ems-header-meta">
                    <span>Total: <strong>{{ $paquetes->total() }}</strong></span>
                </div>
            </div>
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                @if (!$estadoEntregadoDisponible)
                    <div class="alert alert-warning">
                        No existe el estado ENTREGADO en la tabla estados.
                    </div>
                @endif

                <form method="GET" action="{{ route('paquetes-ems.entregados') }}" class="row ems-toolbar">
                    <div class="col-lg-8 col-md-12 mb-2 mb-lg-0">
                        <input type="text" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Buscar EMS/Contrato/Solicitud por codigo, CN-33, destinatario, telefono, ciudad, recibido por o descripcion...">
                    </div>
                    <div class="col-lg-2 col-md-6 mb-2 mb-md-0">
                        <button type="submit" class="btn ems-btn-primary btn-block">Buscar</button>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <a href="{{ route('paquetes-ems.entregados.solicitud.create', ['q' => $search]) }}"
                           class="btn ems-btn-secondary btn-block">
                            Generar Planilla de Entrega
                        </a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Codigo</th>
                                <th>CN-33</th>
                                <th>Destinatario</th>
                                <th>Telefono</th>
                                <th>Ciudad</th>
                                <th>Peso</th>
                                <th>Recibido por</th>
                                <th>Descripcion</th>
                                <th>Imagen</th>
                                <th>Asignado a</th>
                                <th>Fecha entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                <tr>
                                    <td>{{ $paquete->tipo_paquete }}</td>
                                    <td>{{ $paquete->codigo }}</td>
                                    <td>{{ $paquete->cod_especial ?: '-' }}</td>
                                    <td>{{ $paquete->destinatario }}</td>
                                    <td>{{ $paquete->telefono ?: '-' }}</td>
                                    <td>{{ $paquete->ciudad }}</td>
                                    <td>{{ $paquete->peso }}</td>
                                    <td>{{ $paquete->recibido_por ?: '-' }}</td>
                                    <td>{{ $paquete->descripcion ?: '-' }}</td>
                                    <td>
                                        @if (!empty($paquete->imagen))
                                            <a href="{{ asset('storage/' . $paquete->imagen) }}"
                                               class="btn btn-sm btn-outline-primary"
                                               download>
                                                Descargar
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $paquete->asignado_a ?: '-' }}</td>
                                    <td>
                                        {{ !empty($paquete->fecha_entrega) ? \Illuminate\Support\Carbon::parse($paquete->fecha_entrega)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-4">
                                        No hay registros en estado ENTREGADO.
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
            background: linear-gradient(95deg, #20539A 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.2rem;
        }

        .ems-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .ems-header-meta {
            margin-top: 8px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.92rem;
            font-weight: 600;
        }

        .ems-header-meta strong {
            color: #fff;
        }

        .ems-toolbar {
            align-items: stretch;
            margin-bottom: 1rem;
        }

        .ems-btn-primary,
        .ems-btn-secondary {
            min-height: 44px;
            border-radius: 12px;
            font-weight: 800;
        }

        .ems-btn-primary {
            background: #FECC36;
            border: 0;
            color: #fff;
        }

        .ems-btn-primary:hover {
            background: #f4c21d;
            color: #fff;
        }

        .ems-btn-secondary {
            background: #fff;
            border: 1px solid rgba(32, 83, 154, 0.22);
            color: #20539A;
        }

        .ems-btn-secondary:hover {
            background: rgba(32, 83, 154, 0.05);
            color: #20539A;
        }

        .ems-entregados-card .table thead th {
            background: #edf1fb;
            color: #20539A;
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }

        .btn-block {
            min-height: 38px;
        }

        .ems-entregados-card .form-control {
            min-height: 44px;
            border-radius: 12px;
            border-color: #d1d5db;
        }

        .ems-entregados-card .form-control:focus {
            border-color: #20539A;
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

    </style>
@endsection
