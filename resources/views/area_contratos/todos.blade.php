@extends('adminlte::page')
@section('title', 'Area Contratos - Todos')
@section('template_title')
    Area Contratos - Todos
@endsection

@section('content')
    <div class="area-contratos-wrap">
        <div class="card area-contratos-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-1">Todos los contratos</h3>
                    <div class="area-header-meta">Total en registros: <strong>{{ $contratos->total() }}</strong></div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('area-contratos.todos') }}" class="row mb-3">
                    <div class="col-md-4 mb-2 mb-md-0">
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
                    <div class="col-md-3 mb-2 mb-md-0">
                        <select name="empresa_id" class="form-control">
                            <option value="0">Todas las empresas</option>
                            @foreach ($empresas as $empresa)
                                <option value="{{ $empresa->id }}" {{ (int) $empresaId === (int) $empresa->id ? 'selected' : '' }}>
                                    {{ $empresa->nombre }}@if(!empty($empresa->sigla)) ({{ $empresa->sigla }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn area-btn-primary btn-block">Buscar</button>
                    </div>
                    <div class="col-md-1">
                        <a href="{{ route('area-contratos.todos') }}" class="btn area-btn-secondary btn-block">Limpiar</a>
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
                                <th>Cantidad</th>
                                <th>Imagen</th>
                                <th>Fecha</th>
                                <th class="text-center area-action-col">Acciones</th>
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
                                    <td>{{ $contrato->cantidad ?: '-' }}</td>
                                    <td>
                                        @if (!empty($contrato->imagen))
                                            <a href="{{ asset('storage/' . $contrato->imagen) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="btn btn-sm btn-outline-info">
                                                Ver imagen
                                            </a>
                                        @else
                                            <span class="text-muted">Sin imagen</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($contrato->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="text-center area-action-col">
                                        <a href="{{ route('paquetes-contrato.reporte', $contrato->id) }}"
                                            target="_blank"
                                            class="area-action-btn"
                                            title="Ver reporte">
                                            <i class="fas fa-print" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center py-4">
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

        .area-header-meta {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.88rem;
        }

        .area-btn-primary {
            background: #FECC36;
            color: #fff;
            font-weight: 800;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .area-btn-primary:hover {
            filter: brightness(.96);
            color: #fff;
        }

        .area-btn-secondary {
            border: 1px solid rgba(32, 83, 154, .22);
            background: #fff;
            color: #20539A;
            font-weight: 800;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .area-btn-secondary:hover {
            background: rgba(32, 83, 154, .05);
            color: #20539A;
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

        .area-action-col {
            width: 120px;
            min-width: 120px;
        }

        .area-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            padding: 0;
            border-radius: 14px;
            border: 1px solid rgba(32, 83, 154, .18);
            background: #fff;
            color: #20539A;
            font-weight: 800;
            line-height: 1;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(32, 83, 154, .10);
            transition: background .18s ease, transform .18s ease, box-shadow .18s ease, color .18s ease;
        }

        .area-action-btn i {
            font-size: 16px;
        }

        .area-action-btn:hover {
            background: rgba(32, 83, 154, .06);
            color: #1b4a8a;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(32, 83, 154, .16);
        }

        @media (max-width: 767.98px) {
            .area-header-meta {
                margin-bottom: .35rem;
            }
        }
    </style>
@endsection
