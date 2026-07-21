@extends('adminlte::page')

@section('title', 'Gestor de contratos')
@section('template_title')
    Gestor de contratos
@endsection

@section('content')
    <div class="gestor-wrap">
        <div class="gestor-hero">
            <div>
                <span class="gestor-kicker">Paquetes Contratos</span>
                <h3>Gestor</h3>
                <p>Consulta todos los paquetes registrados para tu empresa.</p>
            </div>
            <div class="gestor-company">
                <span>Empresa</span>
                <strong>
                    @if($empresa)
                        {{ $empresa->nombre }}{{ $empresa->sigla ? ' (' . $empresa->sigla . ')' : '' }}
                    @else
                        Sin empresa asignada
                    @endif
                </strong>
                @if($empresa && $empresa->codigo_cliente)
                    <small>Codigo cliente: {{ $empresa->codigo_cliente }}</small>
                @endif
            </div>
        </div>

        @if(!$empresa)
            <div class="alert alert-warning mt-3 mb-0">
                Tu usuario no tiene una empresa asignada. Asigna una empresa al usuario para ver sus paquetes.
            </div>
        @endif

        <div class="gestor-stats">
            <div class="gestor-stat">
                <span>Total paquetes</span>
                <strong>{{ number_format((int) $totalContratos) }}</strong>
            </div>
            <div class="gestor-stat">
                <span>Peso total</span>
                <strong>{{ number_format((float) $totalPeso, 3) }} kg</strong>
            </div>
            <div class="gestor-stat">
                <span>Mostrando</span>
                <strong>{{ number_format((int) $contratos->total()) }}</strong>
            </div>
        </div>

        <div class="card gestor-card">
            <div class="card-body">
                <form method="GET" action="{{ route('paquetes-contrato.gestor') }}" class="gestor-toolbar">
                    <div class="gestor-search">
                        <label for="q">Buscar</label>
                        <input type="text" id="q" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Codigo, remitente, destinatario, telefono, origen, destino...">
                    </div>
                    <div class="gestor-filter">
                        <label for="estado_id">Estado</label>
                        <select id="estado_id" name="estado_id" class="form-control">
                            <option value="0">Todos los estados</option>
                            @foreach ($estados as $estado)
                                <option value="{{ $estado->id }}" @selected((int) $estadoId === (int) $estado->id)>
                                    {{ $estado->nombre_estado }}
                                    @if(isset($estadoCounts[$estado->id]))
                                        ({{ $estadoCounts[$estado->id] }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gestor-actions">
                        <button type="submit" class="btn gestor-btn-primary">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                        <a href="{{ route('paquetes-contrato.gestor') }}" class="btn gestor-btn-secondary">
                            Limpiar
                        </a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover gestor-table mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Estado</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Ruta</th>
                                <th>Peso</th>
                                <th>Contenido</th>
                                <th>Fecha</th>
                                <th class="text-center gestor-action-col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contratos as $contrato)
                                <tr>
                                    <td>
                                        <span class="gestor-code">{{ $contrato->codigo }}</span>
                                        @if($contrato->cod_especial)
                                            <small class="d-block text-muted">{{ $contrato->cod_especial }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="gestor-state">
                                            {{ optional($contrato->estadoRegistro)->nombre_estado ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ $contrato->nombre_r ?: '-' }}</strong>
                                        <small class="d-block text-muted">{{ $contrato->telefono_r ?: '-' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $contrato->nombre_d ?: '-' }}</strong>
                                        <small class="d-block text-muted">{{ $contrato->telefono_d ?: '-' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $contrato->origen ?: '-' }}</strong>
                                        <small class="d-block text-muted">a {{ $contrato->destino ?: '-' }}</small>
                                        @if($contrato->provincia)
                                            <small class="d-block text-muted">{{ $contrato->provincia }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $contrato->peso !== null ? number_format((float) $contrato->peso, 3) : '-' }}</td>
                                    <td>{{ $contrato->contenido ?: '-' }}</td>
                                    <td>{{ optional($contrato->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="text-center gestor-action-col">
                                        @include('partials.rastreo-eventos-button', [
                                            'tipo' => 'contrato',
                                            'codigo' => $contrato->codigo,
                                            'class' => 'btn btn-sm btn-outline-primary rastreo-action-btn',
                                        ])
                                        @if($canContratoGestorPrint)
                                            <a href="{{ route('paquetes-contrato.reporte', $contrato->id) }}"
                                                target="_blank"
                                                class="gestor-action-btn"
                                                title="Ver reporte">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        @endif
                                        @if (!empty($contrato->imagen))
                                            <a href="{{ route('delivery-images.show', ['source' => 'contrato', 'id' => $contrato->id], false) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="gestor-action-btn"
                                                title="Ver imagen">
                                                <i class="fas fa-image"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <strong>No hay paquetes para mostrar.</strong>
                                        <div class="text-muted">Prueba con otro filtro o verifica que tu usuario tenga empresa asignada.</div>
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
        .gestor-wrap {
            background: #eef4fb;
            border: 1px solid #dbe6f4;
            border-radius: 18px;
            padding: 18px;
        }

        .gestor-hero {
            align-items: center;
            background: #20539A;
            border-radius: 16px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 22px;
            padding: 1.55rem 1.8rem;
        }

        .gestor-kicker {
            background: rgba(254, 204, 54, 0.18);
            border: 1px solid rgba(254, 204, 54, 0.38);
            border-radius: 999px;
            display: inline-flex;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin-bottom: 9px;
            padding: 5px 10px;
            text-transform: uppercase;
        }

        .gestor-hero h3 {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0;
        }

        .gestor-hero p {
            color: rgba(255, 255, 255, 0.86);
            font-weight: 700;
            margin: 8px 0 0;
        }

        .gestor-company {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            min-width: 280px;
            padding: 14px 16px;
        }

        .gestor-company span,
        .gestor-company small {
            color: rgba(255, 255, 255, 0.78);
            display: block;
            font-weight: 700;
        }

        .gestor-company strong {
            display: block;
            font-size: 1rem;
            margin: 3px 0;
        }

        .gestor-stats {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin: 14px 0;
        }

        .gestor-stat {
            background: #fff;
            border: 1px solid #dce6f2;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .gestor-stat span {
            color: #667085;
            display: block;
            font-size: 0.86rem;
            font-weight: 800;
            margin-bottom: 3px;
        }

        .gestor-stat strong {
            color: #072f61;
            font-size: 1.25rem;
            font-weight: 800;
        }

        .gestor-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(31, 56, 107, 0.12);
            overflow: hidden;
        }

        .gestor-toolbar {
            align-items: flex-end;
            background: #fbfdff;
            border: 1px solid #dce6f2;
            border-radius: 14px;
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(260px, 1fr) 260px auto;
            margin-bottom: 16px;
            padding: 14px;
        }

        .gestor-toolbar label {
            color: #10233f;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .gestor-toolbar .form-control {
            border-color: #ccd5e2;
            border-radius: 10px;
            min-height: 44px;
        }

        .gestor-actions {
            display: flex;
            gap: 10px;
        }

        .gestor-btn-primary,
        .gestor-btn-secondary {
            border-radius: 10px;
            font-weight: 800;
            min-height: 44px;
            padding: 0.55rem 1rem;
            white-space: nowrap;
        }

        .gestor-btn-primary {
            background: #FECC36;
            border: 0;
            color: #20539A;
        }

        .gestor-btn-secondary {
            background: #fff;
            border: 1px solid #b9c7da;
            color: #20539A;
        }

        .gestor-table thead th {
            background: #edf1fb;
            border-bottom: 1px solid #dbe2f2;
            color: #20539A;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .gestor-code,
        .gestor-state {
            background: #f1f6ff;
            border: 1px solid #dbe6f5;
            border-radius: 999px;
            color: #20539A;
            display: inline-flex;
            font-size: 0.82rem;
            font-weight: 800;
            padding: 5px 9px;
        }

        .gestor-action-col {
            min-width: 190px;
            width: 190px;
        }

        .gestor-action-btn {
            align-items: center;
            background: #fff;
            border: 1px solid rgba(32, 83, 154, .18);
            border-radius: 10px;
            color: #20539A;
            display: inline-flex;
            height: 38px;
            justify-content: center;
            margin-left: 4px;
            text-decoration: none;
            width: 38px;
        }

        .gestor-action-btn:hover {
            background: rgba(32, 83, 154, .06);
            color: #1b4a8a;
            text-decoration: none;
        }

        @media (max-width: 991.98px) {
            .gestor-hero {
                align-items: flex-start;
                flex-direction: column;
            }

            .gestor-company {
                min-width: 0;
                width: 100%;
            }

            .gestor-stats,
            .gestor-toolbar {
                grid-template-columns: 1fr;
            }

            .gestor-actions {
                flex-direction: column;
            }
        }
    </style>
@endsection
