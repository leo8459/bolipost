@extends('adminlte::page')
@section('title', 'Paquetes EMS - Encargado')
@section('template_title')
    Paquetes EMS - Encargado
@endsection

@section('content')
    <div class="ems-encargado-wrap">
        <div class="card ems-encargado-card">
            <div class="card-header">
                <div class="ems-encargado-header">
                    <div>
                        <div class="ems-header-kicker">Panel de control</div>
                        <h3 class="card-title mb-0">Encargado EMS</h3>
                        <div class="ems-encargado-subtitle">Consulta envios de CONTRATO, EMS, CERTI, ORDI y SOLICITUD con filtros rapidos y acciones mas entendibles.</div>
                    </div>
                    <div class="ems-encargado-total">
                        <span>Registros encontrados</span>
                        <strong>{{ number_format($paquetes->total()) }}</strong>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('paquetes-ems.encargado') }}" class="ems-filters-shell">
                    <div class="row ems-encargado-toolbar">
                        <div class="col-xl-2 col-lg-3 col-md-6 mb-3">
                            <label for="servicio" class="ems-filter-label">Servicio</label>
                            <select name="servicio" id="servicio" class="form-control ems-filter-control">
                                <option value="">Todos</option>
                                @foreach ($servicios as $servicioItem)
                                    <option value="{{ $servicioItem }}" {{ $servicio === $servicioItem ? 'selected' : '' }}>
                                        {{ $servicioItem }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-6 mb-3">
                            <label for="q" class="ems-filter-label">Buscar por codigo</label>
                            <input
                                type="text"
                                name="q"
                                id="q"
                                value="{{ $search }}"
                                class="form-control ems-filter-control"
                                placeholder="Codigo, cod especial o referencia"
                            >
                        </div>
                        <div class="col-xl-2 col-lg-2 col-md-6 mb-3">
                            <label for="from" class="ems-filter-label">Desde</label>
                            <input type="date" name="from" id="from" value="{{ $fechaDesde }}" class="form-control ems-filter-control">
                        </div>
                        <div class="col-xl-2 col-lg-2 col-md-6 mb-3">
                            <label for="to" class="ems-filter-label">Hasta</label>
                            <input type="date" name="to" id="to" value="{{ $fechaHasta }}" class="form-control ems-filter-control">
                        </div>
                        <div class="col-xl-2 col-lg-12 col-md-12 mb-3 d-flex align-items-end">
                            <div class="ems-filter-actions w-100">
                                <button type="submit" class="btn ems-btn-primary">Filtrar</button>
                                <a href="{{ route('paquetes-ems.encargado') }}" class="btn ems-btn-secondary">Limpiar</a>
                            </div>
                        </div>
                    </div>
                    <div class="ems-filter-summary">
                        <span class="ems-filter-chip">{{ $servicio !== '' ? $servicio : 'TODOS LOS SERVICIOS' }}</span>
                        <span class="ems-filter-chip">{{ $search !== '' ? $search : 'SIN BUSQUEDA' }}</span>
                        <span class="ems-filter-chip">{{ $fechaDesde !== '' ? $fechaDesde : 'SIN FECHA INICIO' }}</span>
                        <span class="ems-filter-chip">{{ $fechaHasta !== '' ? $fechaHasta : 'SIN FECHA FIN' }}</span>
                    </div>
                </form>

                <div class="table-responsive ems-table-shell">
                    <table class="table ems-encargado-table mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Detalle</th>
                                <th>Ruta</th>
                                <th>Valores</th>
                                <th>Seguimiento</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                <tr>
                                    <td>
                                        <div class="ems-code-stack">
                                            <span class="pill-id">{{ $paquete->codigo ?: 'SIN CODIGO' }}</span>
                                            <span class="ems-code-caption">Cod. especial: {{ $paquete->cod_especial ?: 'N/A' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ems-detail-stack">
                                            <div class="ems-person-name">{{ $paquete->nombre_destinatario ?: 'SIN DESTINATARIO' }}</div>
                                            <div class="ems-person-meta">{{ $paquete->telefono_destinatario ?: 'Sin telefono' }}</div>
                                            <div class="ems-badge-row">
                                                <span class="ems-badge tipo-{{ strtolower($paquete->servicio ?: 'na') }}">{{ $paquete->servicio ?: '-' }}</span>
                                                <span class="ems-soft-pill">{{ $paquete->servicio_especial ?: 'Sin detalle' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ems-route-stack">
                                            <div><span class="ems-route-label">Origen</span> {{ $paquete->origen ?: '-' }}</div>
                                            <div><span class="ems-route-label">Destino</span> {{ $paquete->ciudad ?: '-' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ems-value-stack">
                                            <div><span class="ems-value-label">Peso</span> {{ $paquete->peso !== null ? number_format((float) $paquete->peso, 3) . ' kg' : '-' }}</div>
                                            <form method="POST" action="{{ route('paquetes-ems.encargado.actualizar-peso') }}" class="ems-weight-form">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $paquete->id }}">
                                                <input type="hidden" name="servicio" value="{{ $paquete->servicio }}">
                                                <input type="hidden" name="current_servicio" value="{{ $servicio }}">
                                                <input type="hidden" name="q" value="{{ $search }}">
                                                <input type="hidden" name="from" value="{{ $fechaDesde }}">
                                                <input type="hidden" name="to" value="{{ $fechaHasta }}">
                                                <input type="hidden" name="page" value="{{ $paquetes->currentPage() }}">
                                                <div class="ems-weight-form__row">
                                                    <input
                                                        type="number"
                                                        name="peso"
                                                        step="0.001"
                                                        min="0"
                                                        value="{{ $paquete->peso !== null ? number_format((float) $paquete->peso, 3, '.', '') : '' }}"
                                                        class="form-control ems-weight-input"
                                                        placeholder="Peso"
                                                    >
                                                    <button type="submit" class="btn btn-sm ems-btn-save-weight">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ems-tracking-stack">
                                            <span class="ems-status-badge">{{ $paquete->estado_nombre }}</span>
                                            <div class="ems-date-text">{{ optional($paquete->created_at)->format('d/m/Y H:i') }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ems-action-stack">
                                            <form method="POST" action="{{ route('paquetes-ems.encargado.cancelar-envio') }}" onsubmit="return confirm('Seguro que deseas cancelar este envio y ponerlo en estado 0?');" class="ems-action-form">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $paquete->id }}">
                                                <input type="hidden" name="servicio" value="{{ $paquete->servicio }}">
                                                <input type="hidden" name="current_servicio" value="{{ $servicio }}">
                                                <input type="hidden" name="q" value="{{ $search }}">
                                                <input type="hidden" name="from" value="{{ $fechaDesde }}">
                                                <input type="hidden" name="to" value="{{ $fechaHasta }}">
                                                <input type="hidden" name="page" value="{{ $paquetes->currentPage() }}">
                                                <button type="submit" class="btn btn-sm ems-btn-danger ems-action-btn">
                                                    <span>Cancelar</span>
                                                    <small>Estado 0</small>
                                                </button>
                                            </form>

                                            @if (in_array($paquete->servicio, ['EMS', 'CONTRATO', 'SOLICITUD'], true))
                                                <form method="POST" action="{{ route('paquetes-ems.encargado.devolver-envio') }}" onsubmit="return confirm('Seguro que deseas devolver este envio a ALMACEN de origen?');" class="ems-action-form">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $paquete->id }}">
                                                    <input type="hidden" name="servicio" value="{{ $paquete->servicio }}">
                                                    <input type="hidden" name="destino_accion" value="origen">
                                                    <input type="hidden" name="current_servicio" value="{{ $servicio }}">
                                                    <input type="hidden" name="q" value="{{ $search }}">
                                                    <input type="hidden" name="from" value="{{ $fechaDesde }}">
                                                    <input type="hidden" name="to" value="{{ $fechaHasta }}">
                                                    <input type="hidden" name="page" value="{{ $paquetes->currentPage() }}">
                                                    <button type="submit" class="btn btn-sm ems-btn-warning ems-action-btn">
                                                        <span>Devolver origen</span>
                                                        <small>Va a almacen</small>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('paquetes-ems.encargado.devolver-envio') }}" onsubmit="return confirm('Seguro que deseas devolver este envio a ALMACEN de destino en estado RECIBIDO?');" class="ems-action-form">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $paquete->id }}">
                                                    <input type="hidden" name="servicio" value="{{ $paquete->servicio }}">
                                                    <input type="hidden" name="destino_accion" value="destino">
                                                    <input type="hidden" name="current_servicio" value="{{ $servicio }}">
                                                    <input type="hidden" name="q" value="{{ $search }}">
                                                    <input type="hidden" name="from" value="{{ $fechaDesde }}">
                                                    <input type="hidden" name="to" value="{{ $fechaHasta }}">
                                                    <input type="hidden" name="page" value="{{ $paquetes->currentPage() }}">
                                                    <button type="submit" class="btn btn-sm ems-btn-info ems-action-btn">
                                                        <span>Devolver destino</span>
                                                        <small>Va a recibido</small>
                                                    </button>
                                                </form>
                                            @endif

                                            @if (in_array($paquete->servicio, ['CERTI', 'ORDI'], true))
                                                <form method="POST" action="{{ route('paquetes-ems.encargado.devolver-envio') }}" onsubmit="return confirm('Seguro que deseas devolver este envio a VENTANILLA?');" class="ems-action-form">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $paquete->id }}">
                                                    <input type="hidden" name="servicio" value="{{ $paquete->servicio }}">
                                                    <input type="hidden" name="destino_accion" value="ventanilla">
                                                    <input type="hidden" name="current_servicio" value="{{ $servicio }}">
                                                    <input type="hidden" name="q" value="{{ $search }}">
                                                    <input type="hidden" name="from" value="{{ $fechaDesde }}">
                                                    <input type="hidden" name="to" value="{{ $fechaHasta }}">
                                                    <input type="hidden" name="page" value="{{ $paquetes->currentPage() }}">
                                                    <button type="submit" class="btn btn-sm ems-btn-warning ems-action-btn">
                                                        <span>Devolver ventanilla</span>
                                                        <small>Retorna a punto de entrega</small>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        No se encontraron paquetes con esos filtros.
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
        .ems-encargado-wrap,
        .ems-encargado-card,
        .ems-encargado-card .card-header,
        .ems-filters-shell,
        .ems-table-shell,
        .pill-id,
        .ems-badge,
        .ems-soft-pill,
        .ems-status-badge,
        .ems-action-btn,
        .ems-filter-chip {
            box-sizing: border-box;
        }

        .ems-encargado-wrap {
            background:
                radial-gradient(circle at top right, rgba(254, 204, 54, 0.18), transparent 24%),
                linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
            border: 1px solid #dbe5f5;
            border-radius: 20px;
            padding: 18px;
        }

        .ems-encargado-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 20px 40px rgba(28, 45, 94, 0.12);
            overflow: hidden;
        }

        .ems-encargado-card .card-header {
            background: linear-gradient(100deg, #214e95 0%, #395ca8 55%, #4b6bb7 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1.25rem 1.4rem;
        }

        .ems-encargado-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .ems-header-kicker {
            display: inline-flex;
            align-items: center;
            padding: 0.22rem 0.62rem;
            margin-bottom: 0.55rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .ems-encargado-subtitle {
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.95rem;
            max-width: 760px;
        }

        .ems-encargado-total {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
            min-width: 170px;
            padding: 0.8rem 1rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.9);
            font-weight: 700;
        }

        .ems-encargado-total strong {
            font-size: 1.65rem;
            line-height: 1;
            margin-top: 0.15rem;
        }

        .ems-filters-shell {
            background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
            border: 1px solid #e3ebf8;
            border-radius: 18px;
            padding: 1rem 1rem 0.55rem;
            margin-bottom: 1.15rem;
        }

        .ems-encargado-toolbar {
            align-items: end;
            margin-bottom: 0;
        }

        .ems-filter-label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 700;
            color: #1f2a44;
        }

        .ems-filter-control {
            min-height: 48px;
            border-radius: 14px;
            border: 1px solid #d4ddef;
            box-shadow: none;
        }

        .ems-filter-control:focus {
            border-color: #3f6ec0;
            box-shadow: 0 0 0 0.16rem rgba(63, 110, 192, 0.14);
        }

        .ems-filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
        }

        .ems-filter-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 0.2rem 0 0.7rem;
        }

        .ems-filter-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            background: #eef3ff;
            color: #34508f;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .ems-btn-primary,
        .ems-btn-secondary,
        .ems-btn-danger {
            min-height: 44px;
            border-radius: 14px;
            font-weight: 800;
        }

        .ems-btn-primary {
            background: #FECC36;
            border: 0;
            color: #173b77;
        }

        .ems-btn-primary:hover {
            color: #173b77;
            filter: brightness(.96);
        }

        .ems-btn-secondary {
            background: #fff;
            border: 1px solid rgba(32, 83, 154, 0.22);
            color: #20539A;
        }

        .ems-btn-secondary:hover {
            color: #20539A;
            background: rgba(32, 83, 154, 0.05);
        }

        .ems-btn-warning {
            min-height: auto;
            padding: 0.45rem 0.8rem;
            background: #f0ad4e;
            border: 0;
            color: #fff;
        }

        .ems-btn-warning:hover {
            color: #fff;
            filter: brightness(.96);
        }

        .ems-btn-info {
            min-height: auto;
            padding: 0.45rem 0.8rem;
            background: #2d89ef;
            border: 0;
            color: #fff;
        }

        .ems-btn-info:hover {
            color: #fff;
            filter: brightness(.96);
        }

        .ems-btn-danger {
            min-height: auto;
            padding: 0.45rem 0.8rem;
            background: #d64545;
            border: 0;
            color: #fff;
        }

        .ems-btn-danger:hover {
            color: #fff;
            filter: brightness(.96);
        }

        .ems-table-shell {
            border: 1px solid #e5ebf7;
            border-radius: 18px;
            background: #fff;
            overflow: auto;
        }

        .ems-encargado-table {
            margin: 0;
            min-width: 1120px;
        }

        .ems-encargado-card .table thead th {
            border-top: 0;
            color: #20539A;
            font-size: 0.74rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
            background: #f6f8fc;
            border-bottom: 1px solid #dfe8f5;
            padding: 1rem 0.9rem;
        }

        .ems-encargado-table tbody td {
            padding: 1rem 0.9rem;
            vertical-align: top;
            border-color: #eef2f9;
            background: #fff;
        }

        .ems-encargado-table tbody tr:nth-child(even) td {
            background: #fbfcff;
        }

        .pill-id {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.82rem;
            border-radius: 999px;
            background: rgba(52, 68, 124, 0.1);
            color: #214e95;
            font-weight: 900;
            font-size: 0.86rem;
            line-height: 1.1;
        }

        .ems-code-stack,
        .ems-detail-stack,
        .ems-route-stack,
        .ems-value-stack,
        .ems-tracking-stack {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .ems-code-caption,
        .ems-person-meta,
        .ems-date-text {
            color: #6c7a92;
            font-size: 0.82rem;
        }

        .ems-person-name {
            font-weight: 800;
            color: #203253;
            line-height: 1.35;
        }

        .ems-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 0.2rem;
        }

        .ems-badge,
        .ems-soft-pill,
        .ems-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.38rem 0.75rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1;
        }

        .ems-soft-pill {
            background: #f2f4f8;
            color: #5c6679;
            max-width: 280px;
        }

        .ems-badge.tipo-ems { background: #dbeafe; color: #1d4ed8; }
        .ems-badge.tipo-contrato { background: #dcfce7; color: #166534; }
        .ems-badge.tipo-certi { background: #fef3c7; color: #92400e; }
        .ems-badge.tipo-ordi { background: #fce7f3; color: #9d174d; }
        .ems-badge.tipo-solicitud { background: #ede9fe; color: #6d28d9; }

        .ems-route-label,
        .ems-value-label {
            display: inline-block;
            min-width: 62px;
            color: #6e7b90;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .ems-status-badge {
            width: fit-content;
            background: #e8edf8;
            color: #28406d;
        }

        .ems-action-stack {
            display: grid;
            gap: 10px;
            min-width: 220px;
        }

        .ems-action-form {
            margin: 0;
        }

        .ems-action-btn {
            width: 100%;
            min-height: 0;
            padding: 0.6rem 0.8rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            border-radius: 14px;
        }

        .ems-action-btn small {
            font-size: 0.72rem;
            opacity: 0.9;
        }

        .ems-weight-form {
            margin-top: 0.35rem;
        }

        .ems-weight-form__row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .ems-weight-input {
            min-width: 110px;
            height: 38px;
            border-radius: 12px;
            border: 1px solid #d4ddef;
            font-size: 0.84rem;
        }

        .ems-btn-save-weight {
            height: 38px;
            padding: 0.45rem 0.8rem;
            border-radius: 12px;
            border: 0;
            background: #1f7a5a;
            color: #fff;
            font-weight: 800;
            white-space: nowrap;
        }

        .ems-btn-save-weight:hover {
            color: #fff;
            filter: brightness(.96);
        }

        @media (max-width: 991.98px) {
            .ems-encargado-total {
                align-items: flex-start;
            }

            .ems-filter-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .ems-encargado-wrap {
                padding: 10px;
                border-radius: 16px;
            }

            .ems-encargado-card .card-header,
            .ems-filters-shell {
                padding: 1rem;
            }

            .ems-encargado-subtitle {
                font-size: 0.88rem;
            }

            .ems-filter-summary {
                gap: 8px;
            }
        }
    </style>
@endsection
