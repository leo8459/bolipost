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
                    <div class="ems-header-copy">
                        <span class="ems-header-eyebrow"><i class="fas fa-check-circle" aria-hidden="true"></i> EMS - seguimiento</span>
                        <h3 class="card-title mb-0">Paquetes entregados</h3>
                    </div>
                    <span class="ems-total-pill">
                        <i class="fas fa-box-open" aria-hidden="true"></i>
                        <strong>{{ $paquetes->total() }}</strong>
                        <span>envios</span>
                    </span>
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

                <form method="GET" action="{{ route('paquetes-ems.entregados') }}" class="ems-toolbar">
                    <div class="ems-search-col">
                        <label class="sr-only" for="emsDeliveredSearch">Buscar paquetes entregados</label>
                        <div class="ems-search-field">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input id="emsDeliveredSearch" type="text" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Buscar por codigo, CN-33, destinatario, telefono, ciudad o recibido por...">
                        </div>
                    </div>
                    <div class="ems-toolbar-action">
                        <button type="submit" class="btn ems-btn-primary">
                            <i class="fas fa-search" aria-hidden="true"></i><span>Buscar</span>
                        </button>
                    </div>
                    <div class="ems-toolbar-action">
                        <a href="{{ route('paquetes-ems.entregados.solicitud.create', ['q' => $search]) }}"
                           class="btn ems-btn-secondary">
                            <i class="fas fa-file-alt" aria-hidden="true"></i><span>Generar planilla de entrega</span>
                        </a>
                    </div>
                    @if ($canEntregaNoRegistrada ?? false)
                        <div class="ems-toolbar-action">
                            <a href="{{ route('paquetes-ems.entregados.no-registrado.create') }}"
                               class="btn ems-btn-accent">
                                <i class="fas fa-plus-circle" aria-hidden="true"></i><span>Entrega no registrada</span>
                            </a>
                        </div>
                    @endif
                </form>

                <div class="table-responsive ems-entregados-responsive">
                    <table class="table table-hover mb-0 ems-entregados-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Codigo</th>
                                <th>CN-33</th>
                                <th>Destinatario y contacto</th>
                                <th>Peso</th>
                                <th>Recepcion</th>
                                <th>Evidencia</th>
                                <th>Asignado a</th>
                                <th>Fecha entrega</th>
                                <th class="ems-action-col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                @php
                                    $fechaEntrega = !empty($paquete->fecha_entrega)
                                        ? \Illuminate\Support\Carbon::parse($paquete->fecha_entrega)
                                        : null;
                                @endphp
                                <tr>
                                    <td data-label="Tipo">
                                        <span class="ems-delivered-type ems-delivered-type--{{ strtolower((string) $paquete->tipo_paquete) }}">
                                            {{ $paquete->tipo_paquete }}
                                        </span>
                                    </td>
                                    <td data-label="Codigo"><span class="ems-delivered-code">{{ $paquete->codigo }}</span></td>
                                    <td data-label="CN-33">
                                        @if ($paquete->cod_especial)
                                            <span class="ems-delivered-cn33"><i class="fas fa-barcode" aria-hidden="true"></i>{{ $paquete->cod_especial }}</span>
                                        @else
                                            <span class="ems-delivered-muted">-</span>
                                        @endif
                                    </td>
                                    <td data-label="Destinatario y contacto">
                                        <div class="ems-delivered-recipient-card">
                                            <strong>{{ $paquete->destinatario ?: 'Sin destinatario' }}</strong>
                                            <div class="ems-delivered-contact">
                                                <span><i class="fas fa-phone-alt" aria-hidden="true"></i>{{ $paquete->telefono ?: 'Sin telefono' }}</span>
                                                <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ $paquete->ciudad ?: 'Sin ciudad' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Peso"><span class="ems-delivered-weight">{{ $paquete->peso ?? '-' }} <small>kg</small></span></td>
                                    <td data-label="Recepcion">
                                        <div class="ems-delivered-reception">
                                            <strong><i class="fas fa-user-check" aria-hidden="true"></i>{{ $paquete->recibido_por ?: 'Sin registro' }}</strong>
                                            <span>{{ $paquete->descripcion ?: 'Sin descripcion' }}</span>
                                        </div>
                                    </td>
                                    <td data-label="Evidencia">
                                        @php
                                            $imagenUrl = \App\Support\StoredImage::url($paquete->imagen ?? null);
                                            $imagenOpenUrl = route('delivery-images.package', [
                                                'type' => strtolower((string) ($paquete->tipo_paquete ?? 'ems')),
                                                'id' => $paquete->id,
                                                'kind' => 'entrega',
                                            ]);
                                            $imagenDownloadUrl = route('delivery-images.package.download', [
                                                'type' => strtolower((string) ($paquete->tipo_paquete ?? 'ems')),
                                                'id' => $paquete->id,
                                                'kind' => 'entrega',
                                            ]);
                                        @endphp
                                        @if ($imagenUrl)
                                            <div class="ems-delivered-evidence">
                                                <a href="{{ $imagenOpenUrl }}" target="_blank" rel="noopener" class="ems-delivered-evidence-btn">
                                                    <i class="fas fa-eye" aria-hidden="true"></i><span>Ver</span>
                                                </a>
                                                <a href="{{ $imagenDownloadUrl }}" class="ems-delivered-evidence-btn is-download">
                                                    <i class="fas fa-download" aria-hidden="true"></i><span>Descargar</span>
                                                </a>
                                            </div>
                                        @else
                                            <span class="ems-delivered-muted">Sin imagen</span>
                                        @endif
                                    </td>
                                    <td data-label="Asignado a">
                                        <span class="ems-delivered-assignee"><i class="fas fa-user-circle" aria-hidden="true"></i>{{ $paquete->asignado_a ?: 'Sin asignar' }}</span>
                                    </td>
                                    <td data-label="Fecha entrega">
                                        @if ($fechaEntrega)
                                            <span class="ems-delivered-date">
                                                <strong>{{ $fechaEntrega->format('d/m/Y') }}</strong>
                                                <small>{{ $fechaEntrega->format('H:i') }}</small>
                                            </span>
                                        @else
                                            <span class="ems-delivered-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="ems-action-col" data-label="Acciones">
                                        <div class="ems-delivered-actions">
                                        @include('partials.rastreo-eventos-button', [
                                            'tipo' => strtolower((string) ($paquete->tipo_paquete ?? 'ems')),
                                            'codigo' => $paquete->codigo,
                                            'class' => 'btn btn-sm rastreo-action-btn ems-delivered-icon-btn is-tracking',
                                        ])
                                        @if (($paquete->tipo_paquete ?? '') === 'EMS' && ($canEmsEntregadosPrint ?? false))
                                            <button type="button"
                                               class="btn btn-sm ems-action-btn ems-delivered-icon-btn is-print js-ems-print-options"
                                               data-termica="{{ route('paquetes-ems.boleta', $paquete->id, false) }}"
                                               data-carta="{{ route('paquetes-ems.boleta', ['paquete' => $paquete->id, 'formato' => 'carta'], false) }}"
                                               title="Reimprimir boleta">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 ems-delivered-empty">
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

    <div class="modal fade" id="emsPrintOptionsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content ems-print-modal">
                <div class="modal-header ems-print-modal-header">
                    <h5 class="modal-title">Elegir formato de impresion</h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Elige el formato para reimprimir la boleta EMS.</p>
                    <div class="form-group mb-3">
                        <label for="emsPrintCopies">Numero de copias</label>
                        <select id="emsPrintCopies" class="form-control">
                            @for ($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'copia' : 'copias' }}</option>
                            @endfor
                        </select>
                        <small class="form-text text-muted">Maximo 5 copias.</small>
                    </div>
                    <div class="d-flex flex-column flex-sm-row" style="gap: 10px;">
                        <a href="#" target="_blank" class="btn ems-btn-secondary ems-print-option-btn flex-fill" id="emsPrintTermicaLink">
                            <i class="fas fa-receipt mr-1"></i> Factura termica
                        </a>
                        <a href="#" target="_blank" class="btn ems-btn-secondary ems-print-option-btn flex-fill" id="emsPrintCartaLink">
                            <i class="fas fa-file-alt mr-1"></i> Diseno carta
                        </a>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn ems-btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.js-ems-print-options');

            if (!button) {
                return;
            }

            event.preventDefault();

            const termicaLink = document.getElementById('emsPrintTermicaLink');
            const cartaLink = document.getElementById('emsPrintCartaLink');
            const copiesSelect = document.getElementById('emsPrintCopies');
            const termicaUrl = button.dataset.termica || '#';
            const cartaUrl = button.dataset.carta || '#';

            const updatePrintLinks = function () {
                const copies = Math.max(1, Math.min(5, Number.parseInt(copiesSelect?.value || '1', 10) || 1));
                const addCopies = function (url) {
                    if (url === '#') return url;
                    const separator = url.includes('?') ? '&' : '?';
                    return `${url}${separator}copias=${copies}`;
                };

                if (termicaLink) termicaLink.href = addCopies(termicaUrl);
                if (cartaLink) cartaLink.href = addCopies(cartaUrl);
            };

            if (copiesSelect) {
                copiesSelect.value = '1';
                copiesSelect.onchange = updatePrintLinks;
            }
            updatePrintLinks();

            if (window.jQuery) {
                $('#emsPrintOptionsModal').modal('show');
            }
        });
    </script>
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
        .ems-btn-secondary,
        .ems-btn-accent {
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

        .ems-btn-accent {
            background: #16a34a;
            border: 0;
            color: #fff;
        }

        .ems-btn-accent:hover {
            background: #15803d;
            color: #fff;
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

        .ems-action-col {
            width: 128px;
            min-width: 128px;
            text-align: center;
            white-space: nowrap;
        }

        .ems-action-btn {
            width: 40px;
            height: 40px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
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

        .ems-entregados-wrap{padding:clamp(10px,1.5vw,18px);border-radius:18px;}
        .ems-entregados-card{border-radius:16px;}
        .ems-entregados-card .card-header{padding:18px clamp(16px,2.2vw,28px);}
        .ems-header-top{align-items:center;}
        .ems-header-copy{min-width:0;}
        .ems-header-eyebrow{
            display:flex;
            align-items:center;
            gap:7px;
            margin-bottom:5px;
            color:rgba(255,255,255,.78);
            font-size:11px;
            font-weight:800;
            letter-spacing:.08em;
            text-transform:uppercase;
        }
        .ems-entregados-card .card-title{color:#fff;font-size:clamp(1.25rem,2vw,1.65rem);font-weight:800;}
        .ems-total-pill{
            display:inline-flex;
            align-items:center;
            gap:8px;
            flex:0 0 auto;
            padding:9px 13px;
            border:1px solid rgba(255,255,255,.22);
            border-radius:999px;
            background:rgba(255,255,255,.12);
            color:#fff;
            font-size:12px;
        }
        .ems-total-pill i{color:#ffda64;}
        .ems-total-pill strong{font-size:15px;}
        .ems-entregados-card .card-body{padding:clamp(14px,2vw,26px);}
        .ems-entregados-card .alert{border:0;border-radius:12px;}
        .ems-toolbar{display:flex;align-items:stretch;flex-wrap:wrap;gap:10px;margin-bottom:18px;}
        .ems-search-col{flex:2 1 340px;min-width:260px;}
        .ems-toolbar-action{flex:1 1 180px;min-width:150px;}
        .ems-search-field{position:relative;height:100%;}
        .ems-search-field > i{
            position:absolute;
            top:50%;
            left:14px;
            z-index:1;
            color:#8292aa;
            transform:translateY(-50%);
        }
        .ems-search-field .form-control{height:100%;min-height:46px;padding-left:40px;background:#fff;}
        .ems-btn-primary,.ems-btn-secondary,.ems-btn-accent{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            width:100%;
            min-height:46px;
            padding:10px 14px;
            border-radius:12px;
            font-size:13px;
            font-weight:800;
            line-height:1.3;
            text-align:center;
            white-space:normal;
            transition:transform .15s ease,box-shadow .15s ease,background .15s ease;
        }
        .ems-btn-primary{background:#ffcf35;border:1px solid #f4c21d;color:#17457f;}
        .ems-btn-primary:hover{background:#f4c21d;border-color:#eab80f;color:#123b70;box-shadow:0 5px 12px rgba(23,69,127,.14);transform:translateY(-1px);}
        .ems-btn-secondary{background:#fff;border:1px solid #d5deeb;color:#20539A;}
        .ems-btn-secondary:hover{background:#f2f6fc;border-color:#aebfda;color:#173f78;box-shadow:0 5px 12px rgba(23,69,127,.1);transform:translateY(-1px);}
        .ems-btn-accent{background:#e9f6ee;border:1px solid #b9dfc5;color:#267247;}
        .ems-btn-accent:hover{background:#d8efdf;border-color:#91cba3;color:#1c5e38;box-shadow:0 5px 12px rgba(38,114,71,.12);transform:translateY(-1px);}
        .ems-entregados-responsive{
            max-width:100%;
            overflow:auto;
            border:1px solid #e2e8f1;
            border-radius:14px;
            background:#fff;
            -webkit-overflow-scrolling:touch;
        }
        .ems-entregados-table{
            width:100%;
            min-width:1254px;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing:0;
        }
        .ems-entregados-table thead th{
            position:sticky;
            top:0;
            z-index:2;
            padding:12px 10px;
            background:#eef2f8;
            color:#20539A;
            border-bottom:1px solid #dce4ef;
            text-align:left;
            vertical-align:middle;
        }
        .ems-entregados-table tbody td{
            padding:12px 10px;
            border-top:1px solid #edf1f6;
            background:#fff;
            color:#344054;
            vertical-align:middle;
            line-height:1.4;
            overflow-wrap:anywhere;
        }
        .ems-entregados-table tbody tr:first-child td{border-top:0;}
        .ems-entregados-table.table-hover tbody tr:hover td{background:#f7faff;}
        .ems-entregados-table th:nth-child(1){width:78px;}
        .ems-entregados-table th:nth-child(2){width:132px;}
        .ems-entregados-table th:nth-child(3){width:96px;}
        .ems-entregados-table th:nth-child(4){width:205px;}
        .ems-entregados-table th:nth-child(5){width:65px;}
        .ems-entregados-table th:nth-child(6){width:190px;}
        .ems-entregados-table th:nth-child(7){width:150px;}
        .ems-entregados-table th:nth-child(8){width:115px;}
        .ems-entregados-table th:nth-child(9){width:115px;}
        .ems-entregados-table th:nth-child(10){width:108px;}
        .ems-delivered-type,.ems-delivered-code,.ems-delivered-cn33{
            display:inline-flex;
            align-items:center;
            gap:6px;
            max-width:100%;
            border-radius:999px;
            font-weight:800;
            line-height:1.3;
            overflow-wrap:anywhere;
        }
        .ems-delivered-type{padding:5px 9px;background:#edf2fa;color:#3e587b;font-size:10px;letter-spacing:.04em;}
        .ems-delivered-type--ems{background:#e8f2ff;color:#20539A;}
        .ems-delivered-type--contrato{background:#f1ebff;color:#6547a4;}
        .ems-delivered-type--solicitud{background:#e7f7f1;color:#20765b;}
        .ems-delivered-code{padding:6px 10px;background:#e9eef7;color:#20539A;font-size:12px;}
        .ems-delivered-cn33{padding:5px 8px;background:#f1f5fb;color:#526782;font-size:11px;}
        .ems-delivered-recipient-card > strong{display:block;color:#273a59;font-size:13px;line-height:1.35;}
        .ems-delivered-contact{display:grid;gap:4px;margin-top:6px;color:#718096;font-size:11px;}
        .ems-delivered-contact span{display:flex;align-items:flex-start;gap:7px;min-width:0;}
        .ems-delivered-contact i{width:13px;margin-top:3px;color:#91a0b5;text-align:center;}
        .ems-delivered-weight{color:#243c60;font-size:12px;font-weight:800;white-space:nowrap;}
        .ems-delivered-weight small{color:#8190a4;font-size:10px;font-weight:700;}
        .ems-delivered-reception strong{display:flex;align-items:center;gap:6px;color:#344a69;font-size:11px;}
        .ems-delivered-reception strong i{color:#43835c;}
        .ems-delivered-reception span{display:-webkit-box;margin-top:5px;color:#738097;font-size:11px;line-height:1.45;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:3;}
        .ems-delivered-evidence{display:flex;flex-wrap:wrap;gap:6px;}
        .ems-delivered-evidence-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            min-height:34px;
            padding:6px 9px;
            border:1px solid #d8e3f4;
            border-radius:9px;
            background:#f7faff;
            color:#24548e;
            font-size:11px;
            font-weight:800;
            text-decoration:none;
            white-space:nowrap;
        }
        .ems-delivered-evidence-btn:hover{background:#edf4ff;border-color:#9eb8df;color:#173f78;text-decoration:none;}
        .ems-delivered-evidence-btn.is-download{border-color:#d8e9df;background:#f4fbf6;color:#28714b;}
        .ems-delivered-evidence-btn.is-download:hover{background:#e7f5eb;border-color:#a9d3b7;color:#1d5e3c;}
        .ems-delivered-assignee{display:flex;align-items:center;gap:6px;color:#52647e;font-size:11px;}
        .ems-delivered-assignee i{color:#91a0b5;}
        .ems-delivered-date{display:grid;gap:2px;white-space:nowrap;}
        .ems-delivered-date strong{color:#314969;font-size:12px;}
        .ems-delivered-date small{color:#8491a5;font-size:11px;}
        .ems-delivered-muted{color:#98a2b3;font-size:11px;}
        .ems-action-col{width:auto;min-width:0;text-align:center;white-space:normal;}
        .ems-delivered-actions{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:6px;}
        .ems-delivered-icon-btn{
            display:inline-flex !important;
            align-items:center;
            justify-content:center;
            gap:6px;
            min-width:38px;
            min-height:38px;
            padding:8px 10px !important;
            border:1px solid #d3deed !important;
            border-radius:10px !important;
            background:#fff !important;
            color:#20539A !important;
            box-shadow:0 2px 6px rgba(28,45,94,.05);
            text-decoration:none !important;
        }
        .ems-delivered-icon-btn:hover{background:#f2f6fc !important;border-color:#aebfda !important;color:#173f78 !important;}
        .ems-delivered-icon-btn.is-print{border-color:#f1d77b !important;background:#fff9e6 !important;color:#7b5a00 !important;}
        .ems-delivered-icon-btn.is-print:hover{background:#fff1bd !important;border-color:#e4c13b !important;color:#634900 !important;}
        .ems-delivered-empty{padding:32px 14px !important;color:#78869b !important;text-align:center;}
        .ems-print-modal{overflow:hidden;border:0;border-radius:18px;box-shadow:0 18px 48px rgba(13,36,73,.22);}
        .ems-print-modal-header{align-items:center;background:linear-gradient(120deg,#20539A,#183f76);border:0;color:#fff;}
        .ems-print-modal-header .modal-title{font-weight:800;}
        .ems-print-modal-header .close{color:#fff;opacity:.9;text-shadow:none;}
        .ems-print-modal .modal-body{padding:22px;}
        .ems-print-modal .modal-footer{border-color:#edf1f6 !important;}
        .ems-print-option-btn{width:auto;}

        @media (max-width: 991.98px){
            .ems-toolbar-action{flex-basis:200px;}
            .ems-search-col{flex-basis:100%;}
        }
        @media (max-width: 767.98px){
            .ems-entregados-wrap{padding:8px;border-radius:10px;}
            .ems-entregados-card{border-radius:12px;}
            .ems-entregados-card .card-header{padding:16px;}
            .ems-header-top{align-items:flex-start;flex-direction:column;}
            .ems-total-pill{align-self:flex-start;}
            .ems-entregados-card .card-body{padding:14px;}
            .ems-toolbar{display:grid;grid-template-columns:minmax(0,1fr);gap:9px;margin-bottom:16px;}
            .ems-search-col,.ems-toolbar-action{width:100%;min-width:0;}
            .ems-search-field{height:auto;}
            .ems-toolbar-action .btn{min-height:44px;}
            .ems-entregados-responsive{overflow:visible !important;border:0;border-radius:0;background:transparent;}
            .ems-entregados-table,
            .ems-entregados-table.responsive-card-table{
                display:block !important;
                width:100% !important;
                min-width:0 !important;
                max-width:100% !important;
                border:0 !important;
                background:transparent !important;
            }
            .ems-entregados-table > thead{display:none !important;}
            .ems-entregados-table > tbody{display:grid !important;grid-template-columns:minmax(0,1fr);gap:11px;width:100%;padding:2px;}
            .ems-entregados-table > tbody > tr{
                display:grid !important;
                grid-template-columns:repeat(2,minmax(0,1fr));
                width:100% !important;
                max-width:100% !important;
                overflow:hidden;
                border:1px solid #e1e8f2 !important;
                border-radius:15px;
                background:#fff !important;
                box-shadow:0 5px 15px rgba(25,45,79,.06);
            }
            .ems-entregados-table > tbody > tr:hover{background:#fff;}
            .ems-entregados-table > tbody > tr > td{
                display:block !important;
                width:auto !important;
                min-width:0 !important;
                max-width:100% !important;
                padding:10px 12px !important;
                border:0 !important;
                border-bottom:1px solid #edf1f6 !important;
                background:#fff !important;
                color:#344054;
                text-align:left !important;
                white-space:normal !important;
                overflow-wrap:anywhere;
            }
            .ems-entregados-table > tbody > tr > td::before{
                content:attr(data-label);
                display:block;
                margin:0 0 5px;
                color:#8290a4;
                font-size:10px;
                font-weight:800;
                letter-spacing:.05em;
                line-height:1.3;
                text-align:left;
                text-transform:uppercase;
            }
            .ems-entregados-table > tbody > tr > td:nth-child(1){grid-column:1;}
            .ems-entregados-table > tbody > tr > td:nth-child(2){grid-column:2;}
            .ems-entregados-table > tbody > tr > td:nth-child(3),
            .ems-entregados-table > tbody > tr > td:nth-child(5){grid-column:span 1;}
            .ems-entregados-table > tbody > tr > td:nth-child(4),
            .ems-entregados-table > tbody > tr > td:nth-child(6),
            .ems-entregados-table > tbody > tr > td:nth-child(7),
            .ems-entregados-table > tbody > tr > td:nth-child(10){grid-column:1 / -1;}
            .ems-entregados-table > tbody > tr > td:nth-child(8){grid-column:1;}
            .ems-entregados-table > tbody > tr > td:nth-child(9){grid-column:2;}
            .ems-entregados-table .ems-delivered-contact{font-size:11px;}
            .ems-entregados-table .ems-delivered-reception span{-webkit-line-clamp:unset;}
            .ems-entregados-table > tbody > tr > td.ems-action-col{
                display:block !important;
                border-bottom:0 !important;
            }
            .ems-entregados-table .ems-delivered-actions{justify-content:flex-start;}
            .ems-entregados-table .ems-delivered-icon-btn{min-height:42px;}
            .ems-entregados-table > tbody > tr > td[colspan]{grid-column:1 / -1;border:0 !important;}
            .ems-print-modal .modal-body{padding:18px;}
        }

    </style>
@endsection
