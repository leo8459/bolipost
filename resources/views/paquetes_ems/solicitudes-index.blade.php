@extends('adminlte::page')

@section('title', 'Solicitudes EMS')

@section('content')
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .solicitudes-shell{
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .solicitudes-card{
            border:0;
            border-radius:16px;
            box-shadow:0 12px 26px rgba(0,0,0,.08);
            overflow:hidden;
            background:#fff;
        }

        .solicitudes-hero{
            background: linear-gradient(90deg, var(--azul), #20539A);
            color:#fff;
            padding:18px 20px;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:18px;
            flex-wrap:wrap;
        }

        .solicitudes-hero h1{
            margin:0;
            font-size:2rem;
            font-weight:800;
        }

        .solicitudes-hero p{
            margin:6px 0 0;
            color:rgba(255,255,255,.82);
        }

        .solicitudes-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            align-items:center;
        }

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight:800;
            border:none;
            border-radius:12px;
            padding:10px 14px;
        }

        .btn-dorado:hover{
            filter:brightness(.95);
            color:#fff;
        }

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius:12px;
            padding:10px 14px;
            background:transparent;
        }

        .btn-outline-light2:hover{
            background: rgba(255,255,255,.12);
            color:#fff;
        }

        .solicitudes-meta{
            padding:16px 20px 0;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            color:var(--muted);
        }

        .solicitudes-chip{
            display:inline-flex;
            align-items:center;
            border:1px solid rgba(32,83,154,.18);
            background:rgba(32,83,154,.06);
            color:var(--azul);
            border-radius:999px;
            padding:6px 12px;
            font-size:12px;
            font-weight:800;
        }

        .solicitudes-table-wrap{
            padding:16px 20px 20px;
        }

        .solicitudes-table-card{
            border:1px solid var(--line);
            border-radius:14px;
            overflow:hidden;
            background:#fff;
        }

        .solicitudes-table-head{
            padding:16px 18px;
            border-bottom:1px solid var(--line);
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .solicitudes-table-head h3{
            margin:0;
            font-size:1.05rem;
            font-weight:800;
            color:#163b6c;
        }

        .solicitudes-empty{
            padding:18px;
            color:var(--muted);
        }

        .solicitudes-table{
            margin-bottom:0;
        }

        .solicitudes-table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .solicitudes-pill{
            background: rgba(52,68,124,.12);
            color: var(--azul);
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 999px;
            display:inline-block;
        }

        .solicitudes-footer{
            padding:0 20px 20px;
        }

        @media (max-width: 767.98px){
            .solicitudes-shell{
                padding:12px;
            }

            .solicitudes-hero,
            .solicitudes-meta,
            .solicitudes-table-head{
                flex-direction:column;
                align-items:flex-start;
            }

            .solicitudes-actions{
                width:100%;
            }

            .solicitudes-actions > .btn{
                width:100%;
                justify-content:center;
            }
        }
    </style>

    <div class="solicitudes-shell">
        <div class="solicitudes-card">
            <div class="solicitudes-hero">
                <div>
                    <h1>Solicitudes EMS</h1>
                    <p>Consulta todas las solicitudes registradas desde cliente y desde Admisiones.</p>
                </div>
                <div class="solicitudes-actions">
                    <a href="{{ route('paquetes-ems.solicitudes.create') }}" class="btn btn-dorado">
                        Nuevo
                    </a>
                    <a href="{{ route('paquetes-ems.index') }}" class="btn btn-outline-light2">
                        Volver a admisiones
                    </a>
                </div>
            </div>

            <div class="solicitudes-meta">
                <div>
                    Estado visible:
                    <span class="solicitudes-chip">SOLICITUD</span>
                </div>
                <div>
                    Total en pagina: <strong>{{ $solicitudes->count() }}</strong>
                </div>
            </div>

    @if (session('success'))
                <div class="alert alert-success mx-3 mt-3 mb-0">
                    {{ session('success') }}
                </div>
    @endif

    @if (session('error'))
                <div class="alert alert-danger mx-3 mt-3 mb-0">
                    {{ session('error') }}
                </div>
    @endif

            <div class="solicitudes-table-wrap">
                <div class="solicitudes-table-card">
                    <div class="solicitudes-table-head">
                        <h3>Listado de solicitudes en estado SOLICITUD</h3>
                        @if ($solicitudes->isNotEmpty())
                            <button type="submit" form="solicitudesAlmacenForm" class="btn btn-primary btn-sm">
                                Mandar a ALMACEN
                            </button>
                        @endif
                    </div>
            @if ($solicitudes->isEmpty())
                        <div class="solicitudes-empty">No hay solicitudes en estado SOLICITUD.</div>
            @else
                <form id="solicitudesAlmacenForm" method="POST" action="{{ route('paquetes-ems.solicitudes.send-almacen') }}">
                    @csrf
                    <div class="table-responsive">
                                <table class="table table-hover solicitudes-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Codigo</th>
                                    <th>Canal</th>
                                    <th>Servicio</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Remitente</th>
                                    <th>Destinatario</th>
                                    <th>Peso</th>
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($solicitudes as $solicitud)
                                    @php($estadoNombre = (string) optional($solicitud->estadoRegistro)->nombre_estado)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="solicitud_ids[]" value="{{ $solicitud->id }}">
                                        </td>
                                                <td><span class="solicitudes-pill">{{ $solicitud->codigo_solicitud ?: 'SIN CODIGO' }}</span></td>
                                        <td>{{ $solicitud->cliente_id ? 'Cliente' : 'Admisiones' }}</td>
                                        <td>{{ $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? '-') }}</td>
                                        <td>{{ $solicitud->origen ?: '-' }}</td>
                                        <td>{{ $solicitud->destino?->nombre_destino ?: ($solicitud->ciudad ?: '-') }}</td>
                                        <td>{{ $solicitud->nombre_remitente ?: '-' }}</td>
                                        <td>{{ $solicitud->nombre_destinatario ?: '-' }}</td>
                                        <td>{{ $solicitud->peso !== null ? number_format((float) $solicitud->peso, 3, '.', '') : '-' }}</td>
                                        <td>{{ $solicitud->precio !== null ? number_format((float) $solicitud->precio, 2, '.', '') : '-' }}</td>
                                        <td>
                                            <span class="badge badge-warning">
                                                {{ $estadoNombre !== '' ? $estadoNombre : '-' }}
                                            </span>
                                        </td>
                                        <td>{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('paquetes-ems.solicitudes.ticket', $solicitud) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                Ticket
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
            @endif
                </div>
            </div>
        @if ($solicitudes->hasPages())
                <div class="solicitudes-footer d-flex justify-content-end">
                    {{ $solicitudes->links() }}
                </div>
        @endif
            </div>
        </div>
    </div>
@endsection
