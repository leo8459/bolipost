@extends('adminlte::page')
@section('title', 'Paquetes EMS - Devueltos')
@section('template_title')
    Paquetes EMS - Devueltos
@endsection

@section('content')
    <div class="ems-entregados-wrap">
        <div class="card ems-entregados-card">
            <div class="card-header">
                <div class="ems-header-top">
                    <div class="ems-devueltos-heading">
                        <span class="ems-devueltos-eyebrow">GESTIÓN POSTAL · EMS</span>
                        <h3 class="card-title mb-1">Paquetes devueltos</h3>
                        <p class="mb-0">Consulta la recepción, la evidencia y el seguimiento de cada devolución.</p>
                    </div>
                    <div class="ems-devueltos-total" aria-label="Total de devoluciones">
                        <span>Devoluciones</span>
                        <strong>{{ $paquetes->total() }}</strong>
                    </div>
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

                @if (!$estadoDevolucionDisponible)
                    <div class="alert alert-warning">
                        No existe el estado DEVOLUCION en la tabla estados.
                    </div>
                @endif

                <section class="ems-devueltos-toolbar" aria-label="Buscar paquetes devueltos">
                    <div class="ems-devueltos-toolbar-copy">
                        <span class="ems-devueltos-search-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                        <span>
                            <strong>Buscar devoluciones</strong>
                            <small>Por código, destinatario, ciudad o persona que recibió.</small>
                        </span>
                    </div>
                    <form method="GET" action="{{ route('paquetes-ems.devueltos') }}" class="ems-devueltos-search">
                        <input
                            id="devueltosSearch"
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Buscar por código, destinatario, teléfono, ciudad..."
                            aria-label="Buscar paquetes devueltos"
                        >
                        <button type="submit" class="btn ems-btn-primary">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <span>Buscar</span>
                        </button>
                        @if ($search !== '')
                            <a href="{{ route('paquetes-ems.devueltos') }}" class="ems-devueltos-clear">Limpiar</a>
                        @endif
                    </form>
                </section>

                <div class="ems-devueltos-results-head">
                    <div>
                        <h4>Registro de devoluciones</h4>
                        <p class="mb-0">{{ $paquetes->count() }} resultados en esta página</p>
                    </div>
                    @if ($search !== '')
                        <span class="ems-devueltos-filter"><i class="fas fa-filter" aria-hidden="true"></i> {{ $search }}</span>
                    @endif
                </div>

                <div class="ems-devueltos-table-wrap">
                    <div class="table-responsive ems-devueltos-responsive">
                    <table class="table table-hover mb-0 ems-devueltos-table">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Destinatario</th>
                                <th>Contacto y ciudad</th>
                                <th>Peso</th>
                                <th>Recepcion</th>
                                <th>Evidencia</th>
                                <th>Asignado a</th>
                                <th>Fecha de devolucion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                @php
                                    $imagenUrl = \App\Support\StoredImage::url($paquete->imagen ?? null);
                                    $imagenOpenUrl = route('delivery-images.package', [
                                        'type' => strtolower((string) ($paquete->tipo_paquete ?? 'ems')),
                                        'id' => $paquete->id,
                                        'kind' => 'devolucion',
                                    ]);
                                    $imagenDownloadUrl = route('delivery-images.package.download', [
                                        'type' => strtolower((string) ($paquete->tipo_paquete ?? 'ems')),
                                        'id' => $paquete->id,
                                        'kind' => 'devolucion',
                                    ]);
                                    $fechaDevolucion = !empty($paquete->fecha_devolucion)
                                        ? \Illuminate\Support\Carbon::parse($paquete->fecha_devolucion)
                                        : null;
                                @endphp
                                <tr>
                                    <td data-label="Código"><span class="ems-devueltos-code">{{ $paquete->codigo }}</span></td>
                                    <td data-label="Destinatario">
                                        <span class="ems-devueltos-recipient">{{ $paquete->destinatario ?: 'Sin destinatario' }}</span>
                                    </td>
                                    <td data-label="Contacto y ciudad">
                                        <div class="ems-devueltos-contact">
                                            <span><i class="fas fa-phone-alt" aria-hidden="true"></i> {{ $paquete->telefono ?: 'Sin teléfono' }}</span>
                                            <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> {{ $paquete->ciudad ?: 'Sin ciudad' }}</span>
                                        </div>
                                    </td>
                                    <td data-label="Peso"><span class="ems-devueltos-weight">{{ $paquete->peso ?? '-' }} <small>kg</small></span></td>
                                    <td data-label="Recepción">
                                        <div class="ems-devueltos-reception">
                                            <strong>{{ $paquete->recibido_por ?: 'Sin registro' }}</strong>
                                            @if (!empty($paquete->descripcion) && $paquete->descripcion !== '-')
                                                <p>{{ $paquete->descripcion }}</p>
                                            @else
                                                <small>Sin descripción</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-label="Evidencia">
                                        @if ($imagenUrl)
                                            <div class="ems-devueltos-image-actions">
                                                <a href="{{ $imagenOpenUrl }}" target="_blank" rel="noopener" class="ems-devueltos-image-link">
                                                    <i class="fas fa-eye" aria-hidden="true"></i><span>Ver</span>
                                                </a>
                                                <a href="{{ $imagenDownloadUrl }}" class="ems-devueltos-image-link is-download">
                                                    <i class="fas fa-download" aria-hidden="true"></i><span>Descargar</span>
                                                </a>
                                            </div>
                                        @else
                                            <span class="ems-devueltos-empty">Sin imagen</span>
                                        @endif
                                    </td>
                                    <td data-label="Asignado a">
                                        <span class="ems-devueltos-assignee"><i class="fas fa-user-circle" aria-hidden="true"></i>{{ $paquete->asignado_a ?: 'Sin asignar' }}</span>
                                    </td>
                                    <td data-label="Fecha de devolución">
                                        @if ($fechaDevolucion)
                                            <span class="ems-devueltos-date">
                                                <strong>{{ $fechaDevolucion->format('d/m/Y') }}</strong>
                                                <small>{{ $fechaDevolucion->format('H:i') }}</small>
                                            </span>
                                        @else
                                            <span class="ems-devueltos-empty">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ems-devueltos-empty-state">
                                        <span class="ems-devueltos-empty-icon"><i class="fas fa-box-open" aria-hidden="true"></i></span>
                                        <strong>No hay devoluciones para mostrar</strong>
                                        <small>Prueba con otro código, destinatario o ciudad.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-end ems-devueltos-pagination">
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
            padding: 1.25rem clamp(1rem, 2.4vw, 2rem);
        }

        .ems-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }

        .ems-devueltos-heading{min-width:0;}
        .ems-devueltos-eyebrow{
            display:block;
            margin-bottom:5px;
            color:rgba(255,255,255,.72);
            font-size:.68rem;
            font-weight:800;
            letter-spacing:.12em;
        }
        .ems-devueltos-heading .card-title{
            display:block;
            float:none;
            margin:0;
            color:#fff;
            font-size:clamp(1.35rem,2vw,1.8rem);
            font-weight:800;
        }
        .ems-devueltos-heading p{
            color:rgba(255,255,255,.84);
            font-size:.9rem;
        }
        .ems-devueltos-total{
            display:flex;
            align-items:center;
            gap:12px;
            padding:10px 14px;
            border:1px solid rgba(255,255,255,.2);
            border-radius:14px;
            background:rgba(255,255,255,.12);
            white-space:nowrap;
        }
        .ems-devueltos-total span{
            color:rgba(255,255,255,.88);
            font-size:.82rem;
            font-weight:700;
        }
        .ems-devueltos-total strong{
            display:grid;
            width:36px;
            height:36px;
            place-items:center;
            border-radius:11px;
            background:#fff;
            color:#20539A;
            font-size:1rem;
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

        .ems-btn-primary {
            min-height: 44px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            padding:10px 18px;
            border-radius: 12px;
            font-weight: 800;
            background: #FECC36;
            border: 0;
            color: #173f78;
            box-shadow:0 5px 12px rgba(32,83,154,.12);
        }

        .ems-btn-primary:hover {
            background: #f4c21d;
            color: #173f78;
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

        .ems-entregados-card .card-body{padding:clamp(16px,2vw,28px);}
        .ems-devueltos-toolbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
            margin-bottom:24px;
            padding:16px 18px;
            border:1px solid #e4e9f2;
            border-radius:16px;
            background:#f8faff;
        }
        .ems-devueltos-toolbar-copy{
            display:flex;
            align-items:center;
            gap:12px;
            min-width:230px;
        }
        .ems-devueltos-search-icon{
            display:grid;
            width:42px;
            height:42px;
            flex:0 0 42px;
            place-items:center;
            border-radius:13px;
            background:#e8effb;
            color:#20539A;
        }
        .ems-devueltos-toolbar-copy strong,
        .ems-devueltos-toolbar-copy small{display:block;}
        .ems-devueltos-toolbar-copy strong{color:#213b63;font-size:.95rem;}
        .ems-devueltos-toolbar-copy small{margin-top:3px;color:#758198;font-size:.78rem;}
        .ems-devueltos-search{
            display:flex;
            align-items:center;
            gap:10px;
            width:min(100%,800px);
            min-width:0;
        }
        .ems-devueltos-search .form-control{
            min-width:0;
            min-height:44px;
            border-radius:11px;
            background:#fff;
        }
        .ems-devueltos-clear{
            flex:0 0 auto;
            color:#5d6c82;
            font-size:.82rem;
            font-weight:700;
            text-decoration:none;
        }
        .ems-devueltos-clear:hover{color:#20539A;text-decoration:underline;}
        .ems-devueltos-results-head{
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:14px;
            margin-bottom:12px;
        }
        .ems-devueltos-results-head h4{
            margin:0;
            color:#1e3558;
            font-size:1rem;
            font-weight:800;
        }
        .ems-devueltos-results-head p{margin-top:4px;color:#7a879a;font-size:.8rem;}
        .ems-devueltos-filter{
            display:inline-flex;
            align-items:center;
            gap:7px;
            max-width:50%;
            padding:6px 10px;
            border:1px solid #d8e3f4;
            border-radius:999px;
            background:#f4f8ff;
            color:#315b91;
            font-size:.78rem;
            overflow-wrap:anywhere;
        }
        .ems-devueltos-table-wrap{
            overflow:hidden;
            border:1px solid #e1e7f0;
            border-radius:15px;
            background:#fff;
        }
        .ems-devueltos-table{
            width:100%;
            min-width:1370px;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing:0;
        }
        .ems-devueltos-table th,
        .ems-devueltos-table td{
            padding:13px 11px;
            vertical-align:middle;
            overflow-wrap:normal;
            word-break:normal;
        }
        .ems-devueltos-table thead th{
            position:sticky;
            top:0;
            z-index:2;
            background:#eef2f8;
            color:#31527e;
            border:0;
            border-bottom:1px solid #dce4ef;
            font-size:.7rem;
            font-weight:800;
            letter-spacing:.055em;
            text-transform:uppercase;
            white-space:nowrap;
        }
        .ems-devueltos-table tbody td{
            border-top:1px solid #edf0f5;
            background:#fff;
            color:#36445a;
            font-size:.84rem;
        }
        .ems-devueltos-table tbody tr:first-child td{border-top:0;}
        .ems-devueltos-table.table-hover tbody tr:hover td{background:#f7faff;}
        .ems-devueltos-table th:nth-child(1){width:150px;}
        .ems-devueltos-table th:nth-child(2){width:175px;}
        .ems-devueltos-table th:nth-child(3){width:190px;}
        .ems-devueltos-table th:nth-child(4){width:78px;}
        .ems-devueltos-table th:nth-child(5){width:245px;}
        .ems-devueltos-table th:nth-child(6){width:160px;}
        .ems-devueltos-table th:nth-child(7){width:135px;}
        .ems-devueltos-table th:nth-child(8){width:137px;}
        .ems-devueltos-code{
            display:inline-flex;
            align-items:center;
            max-width:100%;
            border-radius:999px;
            font-weight:800;
            white-space:nowrap;
        }
        .ems-devueltos-code{
            padding:6px 10px;
            background:#e9eef7;
            color:#20539A;
            font-size:.8rem;
        }
        .ems-devueltos-recipient{display:block;color:#273a59;font-weight:700;line-height:1.4;}
        .ems-devueltos-contact{display:grid;gap:6px;color:#59677c;font-size:.78rem;}
        .ems-devueltos-contact span{display:flex;align-items:flex-start;gap:7px;min-width:0;}
        .ems-devueltos-contact i{width:13px;margin-top:3px;color:#8292aa;text-align:center;}
        .ems-devueltos-weight{color:#243c60;font-weight:800;white-space:nowrap;}
        .ems-devueltos-weight small{color:#8190a4;font-size:.7rem;font-weight:700;}
        .ems-devueltos-reception strong{display:block;color:#344a69;font-size:.8rem;}
        .ems-devueltos-reception p{
            margin:5px 0 0;
            color:#68768b;
            font-size:.77rem;
            line-height:1.45;
            overflow-wrap:anywhere;
        }
        .ems-devueltos-reception small,
        .ems-devueltos-empty{color:#8b96a8;font-size:.76rem;}
        .ems-devueltos-image-actions{display:flex;flex-wrap:wrap;gap:6px;}
        .ems-devueltos-image-link{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            min-height:32px;
            padding:6px 9px;
            border:1px solid #d8e3f4;
            border-radius:9px;
            background:#f7faff;
            color:#24548e;
            font-size:.72rem;
            font-weight:800;
            text-decoration:none;
            white-space:nowrap;
        }
        .ems-devueltos-image-link:hover{border-color:#9eb8df;background:#edf4ff;color:#173f78;text-decoration:none;}
        .ems-devueltos-image-link.is-download{border-color:#d8e9df;background:#f4fbf6;color:#28714b;}
        .ems-devueltos-assignee{display:flex;align-items:center;gap:7px;color:#4c5f7c;font-size:.78rem;}
        .ems-devueltos-assignee i{color:#8798b1;}
        .ems-devueltos-date{display:grid;gap:3px;white-space:nowrap;}
        .ems-devueltos-date strong{color:#314969;font-size:.8rem;}
        .ems-devueltos-date small{color:#8491a5;font-size:.73rem;}
        .ems-devueltos-empty-state{
            padding:48px 16px !important;
            text-align:center;
        }
        .ems-devueltos-empty-state > *{display:block;margin:0 auto 7px;}
        .ems-devueltos-empty-state strong{color:#314969;}
        .ems-devueltos-empty-state small{color:#8290a4;}
        .ems-devueltos-empty-icon{
            display:grid !important;
            width:44px;
            height:44px;
            place-items:center;
            margin-bottom:12px !important;
            border-radius:14px;
            background:#edf3fc;
            color:#5877a4;
        }
        .ems-devueltos-pagination .pagination{margin-bottom:0;}

        @media (max-width: 991.98px){
            .ems-devueltos-toolbar{align-items:flex-start;flex-direction:column;gap:12px;}
            .ems-devueltos-search{width:100%;}
        }
        @media (max-width: 767.98px){
            .ems-entregados-wrap{padding:8px;border-radius:10px;}
            .ems-entregados-card .card-header{padding:16px;}
            .ems-entregados-card .card-body{padding:14px;}
            .ems-header-top{align-items:flex-start;flex-direction:column;}
            .ems-devueltos-total{align-self:stretch;justify-content:space-between;}
            .ems-devueltos-toolbar{padding:13px;border-radius:13px;}
            .ems-devueltos-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;}
            .ems-devueltos-search .form-control{grid-column:1 / -1;}
            .ems-devueltos-search .btn{width:100%;}
            .ems-devueltos-clear{align-self:center;justify-self:end;}
            .ems-devueltos-results-head{align-items:flex-start;flex-direction:column;}
            .ems-devueltos-filter{max-width:100%;}
            .ems-devueltos-table-wrap{overflow:visible;border:0;background:transparent;}
            .ems-devueltos-responsive{overflow:visible;}
            .ems-devueltos-table{min-width:0;table-layout:auto;border-spacing:0 11px;margin-top:-11px;}
            .ems-devueltos-table thead{display:none;}
            .ems-devueltos-table tbody{display:grid;grid-template-columns:minmax(0,1fr);gap:11px;}
            .ems-devueltos-table tbody tr{
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                overflow:hidden;
                border:1px solid #e3e9f1;
                border-radius:14px;
                background:#fff;
                box-shadow:0 5px 15px rgba(25,45,79,.06);
            }
            .ems-devueltos-table tbody tr:hover{background:#fff;}
            .ems-devueltos-table tbody td{
                display:block;
                min-width:0;
                width:auto;
                padding:10px 12px;
                border:0;
                background:#fff;
                white-space:normal;
                overflow-wrap:anywhere;
            }
            .ems-devueltos-table tbody td::before{
                content:attr(data-label);
                display:block;
                margin-bottom:5px;
                color:#8290a4;
                font-size:.65rem;
                font-weight:800;
                letter-spacing:.06em;
                text-transform:uppercase;
            }
            .ems-devueltos-table tbody td:nth-child(1),
            .ems-devueltos-table tbody td:nth-child(2),
            .ems-devueltos-table tbody td:nth-child(3),
            .ems-devueltos-table tbody td:nth-child(5),
            .ems-devueltos-table tbody td:nth-child(6),
            .ems-devueltos-table tbody td:nth-child(7){grid-column:1 / -1;}
            .ems-devueltos-table tbody td:nth-child(4){grid-column:1;}
            .ems-devueltos-table tbody td:nth-child(8){grid-column:2;}
            .ems-devueltos-table tbody td[colspan]{grid-column:1 / -1;}
            .ems-devueltos-table .ems-devueltos-empty-state::before{display:none;}
        }
        @media (max-width: 420px){
            .ems-devueltos-search{grid-template-columns:1fr;}
            .ems-devueltos-search .form-control{grid-column:auto;}
            .ems-devueltos-clear{justify-self:center;}
        }
    </style>
@endsection
