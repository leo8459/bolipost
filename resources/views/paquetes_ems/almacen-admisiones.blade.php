@extends('adminlte::page')
@section('title', 'Almacen admisiones EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .almacen-wrap{
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .almacen-card{
            border:0;
            border-radius:16px;
            box-shadow:0 12px 26px rgba(0,0,0,.08);
            overflow:hidden;
        }

        .almacen-header{
            background: linear-gradient(90deg, var(--azul), #20539A);
            color:#fff;
            padding:18px 20px;
        }

        .search-input{
            border-radius:12px;
            border:1px solid rgba(255,255,255,.45);
            padding:10px 12px;
            background: rgba(255,255,255,.96);
        }

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight:800;
            border:none;
            border-radius:12px;
            padding:10px 14px;
        }
        .btn-dorado:hover{ filter:brightness(.95); color:#fff; }

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius:12px;
            padding:10px 14px;
            background: transparent;
        }
        .btn-outline-light2:hover{
            background: rgba(255,255,255,.12);
            color:#fff;
        }

        .header-chip{
            display:inline-flex;
            align-items:center;
            border:1px solid rgba(255,255,255,.45);
            color:#fff;
            font-weight:800;
            font-size:11px;
            border-radius:999px;
            padding:4px 10px;
            background: rgba(255,255,255,.10);
        }

        .muted{ color:var(--muted); }

        .table-wrap{
            border:1px solid var(--line);
            border-radius:14px;
            overflow:hidden;
            background:#fff;
        }

        .table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .table td{
            vertical-align: middle;
        }

        .pill-id{
            background: rgba(52,68,124,.12);
            color: var(--azul);
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 999px;
            display:inline-block;
        }

        .stat-box{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:14px;
            padding:12px 14px;
        }

        .stat-label{
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.06em;
            color:var(--muted);
            font-weight:800;
        }

        .stat-value{
            font-size:22px;
            font-weight:900;
            color:var(--azul);
            line-height:1.1;
            margin-top:4px;
        }
    </style>

    <div class="almacen-wrap">
        <div class="card almacen-card">
            <div class="almacen-header d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-1">Almacen admisiones</h4>
                    <div class="small" style="color:rgba(255,255,255,.86);">
                        Paquetes creados en admisiones y enviados a ALMACEN EMS con su usuario.
                    </div>
                    <div class="mt-2">
                        <span class="header-chip">EMS</span>
                        <span class="header-chip">ALMACEN ADMISIONES</span>
                    </div>
                </div>

                <form method="GET" action="{{ route('paquetes-ems.almacen-admisiones') }}" class="w-100" style="max-width: 980px;">
                    <div class="d-flex flex-column flex-lg-row gap-2 align-items-stretch">
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control search-input"
                            placeholder="Buscar por codigo, remitente, destinatario, destino o usuario..."
                        >
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-outline-light2">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body">
                @if (!$estadoAlmacenDisponible)
                    <div class="alert alert-warning">
                        No existe el estado ALMACEN en la tabla estados.
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div class="muted">
                        @if (!empty($search))
                            Resultados para: <strong>{{ $search }}</strong>
                        @else
                            Mostrando todos los paquetes enviados a ALMACEN EMS
                        @endif
                    </div>

                    <div class="stat-box">
                        <div class="stat-label">Total en pagina</div>
                        <div class="stat-value">{{ $paquetes->count() }}</div>
                    </div>
                </div>

                <div class="table-responsive table-wrap">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Codigo</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Destino</th>
                                <th class="text-right">Peso</th>
                                <th>Usuario</th>
                                <th>Fecha creado</th>
                                <th>Fecha enviado almacen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $item)
                                <tr>
                                    <td>{{ ($paquetes->currentPage() - 1) * $paquetes->perPage() + $loop->iteration }}</td>
                                    <td><span class="pill-id">{{ $item->codigo ?: '-' }}</span></td>
                                    <td>{{ $item->remitente ?: '-' }}</td>
                                    <td>{{ $item->destinatario ?: '-' }}</td>
                                    <td>{{ $item->destino ?: '-' }}</td>
                                    <td class="text-right">{{ number_format((float) $item->peso, 3) }}</td>
                                    <td>{{ $item->usuario ?: 'Sin usuario' }}</td>
                                    <td class="muted small">{{ optional($item->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="muted small">{{ optional($item->updated_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay paquetes enviados a ALMACEN EMS</div>
                                        <div class="muted">Prueba con otro criterio de busqueda.</div>
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
