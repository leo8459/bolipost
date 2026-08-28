@extends('adminlte::page')

@section('title', 'Conciliaciones')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h1 class="mb-1">Conciliaciones</h1>
            <p class="text-muted mb-0">Seguimiento anual de la conciliación de cada empresa.</p>
        </div>
        <form method="GET" action="{{ route('dashboard.conciliacion.conciliaciones') }}" class="form-inline mt-2 mt-md-0">
            <label for="anio" class="mr-2 mb-0">Año</label>
            <input id="anio" name="anio" type="number" min="2000" max="{{ now()->year + 1 }}"
                value="{{ $anio }}" class="form-control mr-2 @error('anio') is-invalid @enderror">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search mr-1"></i> Ver</button>
        </form>
    </div>
@endsection

@section('content')
    <div class="conciliaciones-page">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
            </div>
        @endif

        <div class="card months-card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-calendar-alt mr-2"></i>Los 12 meses de {{ $anio }}</h3>
            </div>
            <div class="card-body">
                <div class="months-grid">
                    @foreach($resumenMeses as $numero => $resumen)
                        <a href="{{ route('dashboard.conciliacion.conciliaciones', ['anio' => $anio, 'mes' => $numero]) }}"
                            class="month-card {{ $mes === $numero ? 'active' : '' }}">
                            <span class="month-name">{{ $resumen['nombre'] }}</span>
                            <span class="month-progress">
                                {{ $resumen['documentos'] }}/{{ $resumen['total'] }} documentos
                            </span>
                            <span class="progress mt-2"><span class="progress-bar" style="width: {{ $resumen['total'] ? ($resumen['documentos'] / $resumen['total']) * 100 : 0 }}%"></span></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @php($actual = $resumenMeses[$mes])
        <div class="row mb-1">
            <div class="col-xl-3 col-md-6">
                <button type="button" class="info-box shadow-sm summary-filter" data-summary-filter="conciliados" data-summary-label="Conciliaciones completadas">
                    <span class="info-box-icon bg-success"><i class="fas fa-file-upload"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Conciliaciones completadas</span><span class="info-box-number">{{ $actual['conciliados'] }} de {{ $actual['total'] }} empresas conciliadas</span></div>
                </button>
            </div>
            <div class="col-xl-3 col-md-6">
                <button type="button" class="info-box shadow-sm summary-filter" data-summary-filter="porCobrar" data-summary-label="Empresas por cobrar">
                    <span class="info-box-icon bg-warning"><i class="fas fa-hand-holding-usd"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Por cobrar</span><span class="info-box-number">{{ $actual['por_cobrar'] }} de {{ $actual['total'] }} empresas facturadas</span></div>
                </button>
            </div>
            <div class="col-xl-3 col-md-6">
                <button type="button" class="info-box shadow-sm summary-filter" data-summary-filter="pagosRecibidos" data-summary-label="Empresas con pago recibido">
                    <span class="info-box-icon bg-primary"><i class="fas fa-money-check-alt"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Pagos recibidos</span><span class="info-box-number">{{ $actual['pagos_recibidos'] }} de {{ $actual['total'] }} empresas pagadas</span></div>
                </button>
            </div>
            <div class="col-xl-3 col-md-6">
                <button type="button" class="info-box shadow-sm summary-filter" data-summary-filter="pagosConfirmados" data-summary-label="Empresas con pago confirmado">
                    <span class="info-box-icon bg-success"><i class="fas fa-clipboard-check"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Pagos confirmados</span><span class="info-box-number">{{ $actual['pagos_confirmados'] }} de {{ $actual['total'] }} confirmados</span></div>
                </button>
            </div>
        </div>

        <div class="card empresas-card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                <h3 class="card-title mb-2 mb-md-0"><i class="fas fa-building mr-2"></i>Empresas · {{ $actual['nombre'] }} {{ $anio }}</h3>
                <div class="input-group search-box">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                    <input id="buscarEmpresa" type="search" class="form-control" placeholder="Buscar empresa o código...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Pasos</th>
                                <th>Documento</th>
                                <th>Factura asociada</th>
                                <th>Pago recibido</th>
                                <th class="text-center">Editar</th>
                            </tr>
                        </thead>
                        <tbody id="tablaEmpresas">
                            @forelse($empresas as $empresa)
                                @php($item = $empresa->getRelation('conciliacionActual'))
                                <tr data-search="{{ mb_strtolower($empresa->nombre.' '.$empresa->sigla.' '.$empresa->codigo_cliente) }}"
                                    data-conciliados="{{ $item?->conciliado_at ? 1 : 0 }}"
                                    data-por-cobrar="{{ $item?->factura_venta_id ? 1 : 0 }}"
                                    data-pagos-recibidos="{{ $item?->pago_comprobante_path ? 1 : 0 }}"
                                    data-pagos-confirmados="{{ $item?->confirmacion_pago_at ? 1 : 0 }}">
                                    <td class="empresa-cell">
                                        <strong>{{ $empresa->nombre }}</strong>
                                        <small class="d-block text-muted">
                                            {{ collect([$empresa->codigo_cliente, $empresa->sigla])->filter()->implode(' · ') ?: 'Sin código' }}
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        @if($item?->confirmacion_pago_at)
                                            <span class="badge badge-success status-badge"><i class="fas fa-clipboard-check mr-1"></i>Pago confirmado</span>
                                        @elseif($item?->pago_comprobante_path)
                                            <span class="badge badge-primary status-badge"><i class="fas fa-check-double mr-1"></i>Pago recibido</span>
                                        @elseif($item?->factura_venta_id)
                                            <span class="badge badge-warning status-badge"><i class="fas fa-hand-holding-usd mr-1"></i>Por cobrar</span>
                                        @elseif($item?->conciliado_at)
                                            <span class="badge badge-success status-badge"><i class="fas fa-check mr-1"></i>Conciliado</span>
                                        @elseif($item?->documento_path)
                                            <span class="badge badge-info status-badge"><i class="fas fa-file-upload mr-1"></i>Documento cargado</span>
                                        @else
                                            <span class="badge badge-light status-badge">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center flex-wrap" style="gap:.4rem;min-width:260px">
                                            @can('feature.conciliacion.conciliaciones.conciliar')
                                            @if($item?->conciliado_at)
                                                <button type="button" class="btn btn-sm btn-success" disabled title="El archivo quedó cerrado al marcar Conciliado">
                                                    <strong class="mr-1">1</strong> Archivo cerrado <i class="fas fa-lock ml-1"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm {{ $item?->documento_path ? 'btn-success' : 'btn-primary' }} upload-trigger"
                                                    data-toggle="modal" data-target="#documentoModal" data-empresa-id="{{ $empresa->id }}" data-empresa="{{ $empresa->nombre }}"
                                                    data-documento="{{ $item?->documento_nombre }}">
                                                    <strong class="mr-1">1</strong> {{ $item?->documento_path ? 'Editar Excel/PDF' : 'Conciliación' }}
                                                </button>
                                            @endif
                                            @endcan
                                            @can('feature.conciliacion.conciliaciones.conciliado')
                                            @if($item?->conciliado_at)
                                                <button type="button" class="btn btn-sm btn-success" disabled><strong class="mr-1">2</strong> Conciliado <i class="fas fa-check ml-1"></i></button>
                                            @elseif($item?->documento_path)
                                                <form method="POST" action="{{ route('dashboard.conciliacion.conciliaciones.conciliado', $item) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success"><strong class="mr-1">2</strong> Conciliado</button>
                                                </form>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-success" disabled><strong class="mr-1">2</strong> Conciliado</button>
                                            @endif
                                            @endcan
                                            @can('feature.conciliacion.conciliaciones.por-cobrar')
                                            @if($item?->conciliado_at)
                                                <button type="button" class="btn btn-sm {{ $item?->factura_venta_id ? 'btn-warning' : 'btn-outline-warning' }} cobrar-trigger"
                                                    data-toggle="modal" data-target="#porCobrarModal"
                                                    data-empresa-id="{{ $empresa->id }}" data-empresa="{{ $empresa->nombre }}"
                                                    data-factura="{{ $item?->factura_venta_id }}"
                                                    data-formato-cobranza="{{ $item?->formato_nota_cobranza }}"
                                                    data-nombre-cobranza="{{ $item?->nombre_empresa_cobranza }}">
                                                    <strong class="mr-1">3</strong> Por cobrar @if($item?->factura_venta_id)<i class="fas fa-check ml-1"></i>@endif
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-warning" disabled><strong class="mr-1">3</strong> Por cobrar</button>
                                            @endif
                                            @endcan
                                            @can('feature.conciliacion.conciliaciones.pago-recibido')
                                            @if($item?->pago_comprobante_path)
                                                <button type="button" class="btn btn-sm btn-primary" disabled><strong class="mr-1">4</strong> Pago recibido <i class="fas fa-check ml-1"></i></button>
                                            @elseif($item?->factura_venta_id)
                                                <button type="button" class="btn btn-sm btn-outline-primary pago-trigger"
                                                    data-toggle="modal" data-target="#pagoRecibidoModal"
                                                    data-action="{{ route('dashboard.conciliacion.conciliaciones.pago-recibido', $item) }}"
                                                    data-empresa="{{ $empresa->nombre }}"
                                                    data-factura="{{ $item->factura_codigo_orden ?: $item->factura_venta_id }}"
                                                    data-monto="{{ number_format((float) $item->factura_monto, 2) }}">
                                                    <strong class="mr-1">4</strong> Pago recibido
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-primary" disabled><strong class="mr-1">4</strong> Pago recibido</button>
                                            @endif
                                            @endcan
                                            @can('feature.conciliacion.conciliaciones.confirmacion-pago')
                                            @if($item?->confirmacion_pago_at)
                                                <button type="button" class="btn btn-sm btn-success" disabled><strong class="mr-1">5</strong> Confirmación de pago <i class="fas fa-check ml-1"></i></button>
                                            @elseif($item?->pago_comprobante_path)
                                                <button type="button" class="btn btn-sm btn-outline-success confirmacion-trigger"
                                                    data-toggle="modal" data-target="#confirmacionPagoModal"
                                                    data-action="{{ route('dashboard.conciliacion.conciliaciones.confirmacion-pago', $item) }}"
                                                    data-empresa="{{ $empresa->nombre }}"
                                                    data-factura="{{ $item->factura_codigo_orden ?: $item->factura_venta_id }}"
                                                    data-monto="{{ number_format((float) $item->factura_monto, 2) }}">
                                                    <strong class="mr-1">5</strong> Confirmación de pago
                                                </button>
                                            @endif
                                            @endcan
                                        </div>
                                    </td>
                                    <td class="document-cell">
                                        @if($item?->documento_path)
                                            <a href="{{ route('dashboard.conciliacion.conciliaciones.descargar', $item) }}" class="document-link" title="Descargar documento">
                                                <i class="{{ str_ends_with(strtolower($item->documento_nombre), '.pdf') ? 'fas fa-file-pdf text-danger' : 'fas fa-file-excel text-success' }} mr-2"></i>
                                                <span>{{ $item->documento_nombre }}</span>
                                            </a>
                                            <small class="d-block text-muted mt-1">{{ $item->documento_at?->format('d/m/Y H:i') }}</small>
                                        @else
                                            <span class="text-muted small">Sin documento</span>
                                        @endif
                                    </td>
                                    <td class="document-cell">
                                        @if($item?->factura_venta_id)
                                            <strong>{{ $item->factura_codigo_orden ?: $item->factura_venta_id }}</strong>
                                            <small class="d-block text-muted">Bs {{ number_format((float) $item->factura_monto, 2) }} · {{ $item->factura_fecha?->format('d/m/Y') }}</small>
                                            @if(filled($item->factura_razon_social))
                                                <small class="d-block mt-2"><strong>Razón social:</strong> {{ $item->factura_razon_social }}</small>
                                                <small class="d-block"><strong>Código cliente:</strong> {{ $item->factura_codigo_cliente ?: '-' }}</small>
                                                <small class="d-block"><strong>NIT/CI/CEX:</strong> {{ $item->factura_numero_documento ?: '-' }}</small>
                                            @endif
                                            @if(filled($item?->formato_nota_cobranza))
                                                <small class="d-block mt-2">
                                                    <strong>Formato:</strong>
                                                    {{ $item->formato_nota_cobranza === 'cuenta_personal' ? 'Depósito por cuenta personal' : 'Depósito por libreta' }}
                                                </small>
                                                <small class="d-block"><strong>Empresa en la nota:</strong> {{ $item->nombre_empresa_cobranza }}</small>
                                                <a href="{{ route('dashboard.conciliacion.conciliaciones.nota-cobranza', $item) }}" class="btn btn-xs btn-outline-primary mt-2">
                                                    <i class="fas fa-file-download mr-1"></i> Descargar nota de cobranza
                                                </a>
                                            @endif
                                            <a href="{{ route('dashboard.conciliacion.conciliaciones.factura-pdf', $item) }}" class="btn btn-xs btn-outline-danger mt-2">
                                                <i class="fas fa-file-pdf mr-1"></i> Descargar PDF
                                            </a>
                                        @else
                                            <span class="text-muted small">Sin factura asociada</span>
                                        @endif
                                    </td>
                                    <td class="document-cell">
                                        @if($item?->pago_comprobante_path)
                                            <strong class="d-block text-primary"><i class="fas fa-check-circle mr-1"></i>Pago recibido</strong>
                                            <small class="d-block text-muted">{{ $item->pago_recibido_at?->format('d/m/Y H:i') }}</small>
                                            <a href="{{ route('dashboard.conciliacion.conciliaciones.comprobante-pago', $item) }}" class="btn btn-xs btn-outline-danger mt-2">
                                                <i class="fas fa-file-pdf mr-1"></i> Descargar confirmación
                                            </a>
                                            @if($item?->confirmacion_pago_at)
                                                <small class="d-block text-success mt-2"><i class="fas fa-clipboard-check mr-1"></i>Confirmado {{ $item->confirmacion_pago_at->format('d/m/Y H:i') }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted small">Sin confirmación</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @can('feature.conciliacion.conciliaciones.editar')
                                            <button type="button" class="btn btn-sm btn-outline-secondary editar-trigger"
                                                data-toggle="modal" data-target="#editarConciliacionModal"
                                                data-empresa-id="{{ $empresa->id }}"
                                                data-empresa="{{ $empresa->nombre }}"
                                                data-documento="{{ $item?->documento_nombre }}"
                                                data-tiene-documento="{{ $item?->documento_path ? 1 : 0 }}"
                                                data-tiene-conciliado="{{ $item?->conciliado_at ? 1 : 0 }}"
                                                data-factura="{{ $item?->factura_venta_id }}"
                                                data-factura-label="{{ $item?->factura_codigo_orden ?: $item?->factura_venta_id }}"
                                                data-formato-cobranza="{{ $item?->formato_nota_cobranza }}"
                                                data-nombre-cobranza="{{ $item?->nombre_empresa_cobranza }}"
                                                data-monto="{{ number_format((float) ($item?->factura_monto ?? 0), 2) }}"
                                                data-tiene-pago="{{ $item?->pago_comprobante_path ? 1 : 0 }}"
                                                data-pago-action="{{ $item ? route('dashboard.conciliacion.conciliaciones.pago-recibido', $item) : '' }}">
                                                <i class="fas fa-edit mr-1"></i> Editar
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-5">No existen empresas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div id="sinResultados" class="text-center text-muted py-5 d-none">No se encontraron empresas.</div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="documentoModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form id="documentoForm" method="POST" enctype="multipart/form-data" class="modal-content">
                @csrf
                <input type="hidden" name="anio" value="{{ $anio }}"><input type="hidden" name="mes" value="{{ $mes }}">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-cloud-upload-alt mr-2"></i>Subir documento</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <p class="mb-1">Empresa: <strong id="documentoEmpresa"></strong></p>
                    <p id="documentoActual" class="small text-muted mb-3 d-none"></p>
                    <div class="custom-file">
                        <input type="file" name="documento" id="documento" class="custom-file-input" accept=".pdf,.xls,.xlsx" required>
                        <label class="custom-file-label" for="documento">Seleccionar PDF o Excel</label>
                    </div>
                    <small class="form-text text-muted mt-2">Formatos permitidos: PDF, XLS y XLSX. Tamaño máximo: 20 MB.</small>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar documento</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="porCobrarModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <form method="POST" action="{{ route('dashboard.conciliacion.conciliaciones.por-cobrar') }}" class="modal-content">
                @csrf
                <input type="hidden" name="empresa_id" id="cobrarEmpresaId">
                <input type="hidden" name="anio" value="{{ $anio }}">
                <input type="hidden" name="mes" value="{{ $mes }}">
                <input type="hidden" name="origen" value="conciliaciones">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-hand-holding-usd mr-2"></i>Por cobrar</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Empresa: <strong id="cobrarEmpresa"></strong></p>
                    <div class="form-group">
                        <label>Formato de la nota de cobranza <span class="text-danger">*</span></label>
                        <div class="cobranza-format-grid">
                            <label class="cobranza-format-option" for="formatoCuentaPersonal">
                                <input id="formatoCuentaPersonal" type="radio" name="formato_nota_cobranza" value="cuenta_personal" required>
                                <span class="cobranza-format-icon bg-primary"><i class="fas fa-university"></i></span>
                                <span>
                                    <strong>Depósito por cuenta personal</strong>
                                    <small>Banco Unión S.A. · Cuenta personal autorizada</small>
                                </span>
                            </label>
                            <label class="cobranza-format-option" for="formatoLibreta">
                                <input id="formatoLibreta" type="radio" name="formato_nota_cobranza" value="libreta" required>
                                <span class="cobranza-format-icon bg-success"><i class="fas fa-book"></i></span>
                                <span>
                                    <strong>Depósito por libreta</strong>
                                    <small>Cuenta Única del Tesoro (CUT) · Libreta</small>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cobrarNombreEmpresa">Nombre de la empresa que aparecerá <span class="text-danger">*</span></label>
                        <input id="cobrarNombreEmpresa" name="nombre_empresa_cobranza" type="text" maxlength="255" class="form-control" required>
                        <small class="form-text text-muted">Puedes corregir la razón social antes de guardar.</small>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-4 form-group">
                            <label for="cobrarFacturadoMes">Mes facturado</label>
                            <select id="cobrarFacturadoMes" name="facturado_mes" class="form-control">
                                @foreach([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $numero => $nombre)
                                    <option value="{{ $numero }}" @selected($numero === now()->month)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="cobrarFacturadoAnio">Año facturado</label>
                            <input id="cobrarFacturadoAnio" name="facturado_anio" type="number" min="2000" max="{{ now()->year + 1 }}" value="{{ now()->year }}" class="form-control">
                        </div>
                        <div class="col-md-5 form-group">
                            <button id="buscarFacturas" type="button" class="btn btn-info btn-block"><i class="fas fa-search mr-1"></i> Buscar facturas en la API</button>
                        </div>
                    </div>
                    <div class="form-group mb-1">
                        <label for="cobrarFactura">Factura</label>
                        <select id="cobrarFactura" name="factura_venta_id" class="form-control" required disabled>
                            <option value="">Primero consulta las facturas...</option>
                        </select>
                    </div>
                    <div id="cobrarEstado" class="small text-muted mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button id="guardarPorCobrar" type="submit" class="btn btn-warning" disabled><i class="fas fa-link mr-1"></i> Asociar factura</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="pagoRecibidoModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form id="pagoRecibidoForm" method="POST" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-check-alt mr-2"></i>Confirmar pago recibido</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Empresa: <strong id="pagoEmpresa"></strong></p>
                    <p class="mb-2">Factura: <strong id="pagoFactura"></strong></p>
                    <p class="mb-0">Monto facturado: <strong class="text-success">Bs <span id="pagoMonto"></span></strong></p>
                    <div class="form-group mt-3 mb-2">
                        <label for="comprobantePago">PDF de confirmación <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="comprobante_pago" id="comprobantePago" class="custom-file-input" accept=".pdf,application/pdf" required>
                            <label class="custom-file-label" for="comprobantePago">Seleccionar PDF...</label>
                        </div>
                        <small class="form-text text-muted">Archivo PDF, máximo 20 MB.</small>
                    </div>
                    <div class="alert alert-info mt-3 mb-0"><i class="fas fa-info-circle mr-1"></i>Se registrarán la fecha, hora y usuario que confirma el pago.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Confirmar pago recibido</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="confirmacionPagoModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form id="confirmacionPagoForm" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check mr-2"></i>Confirmación de pago</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Empresa: <strong id="confirmacionEmpresa"></strong></p>
                    <p class="mb-2">Factura: <strong id="confirmacionFactura"></strong></p>
                    <p class="mb-0">Monto recibido: <strong class="text-success">Bs <span id="confirmacionMonto"></span></strong></p>
                    <div class="alert alert-success mt-3 mb-0"><i class="fas fa-check-circle mr-1"></i>Confirma que el pago y su PDF de respaldo fueron revisados correctamente.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Confirmar pago</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="resumenEmpresasModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-building mr-2"></i><span id="resumenModalTitulo"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr><th>Empresa</th><th>Código</th><th>Estado</th></tr>
                            </thead>
                            <tbody id="resumenModalEmpresas"></tbody>
                        </table>
                    </div>
                    <div id="resumenModalVacio" class="text-center text-muted py-5 d-none">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No existen empresas en este resumen.
                    </div>
                </div>
                <div class="modal-footer">
                    <span id="resumenModalTotal" class="mr-auto text-muted"></span>
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarConciliacionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Editar conciliación</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Empresa: <strong id="editarEmpresa"></strong></p>
                    <div class="list-group">
                        <button id="editarDocumento" type="button" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-upload text-primary mr-2"></i><strong>Documento de conciliación</strong>
                            <small id="editarDocumentoEstado" class="d-block text-muted ml-4"></small>
                        </button>
                        <button id="editarFactura" type="button" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-invoice-dollar text-warning mr-2"></i><strong>Factura asociada</strong>
                            <small id="editarFacturaEstado" class="d-block text-muted ml-4"></small>
                        </button>
                        <button id="editarPago" type="button" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-pdf text-danger mr-2"></i><strong>PDF de pago recibido</strong>
                            <small id="editarPagoEstado" class="d-block text-muted ml-4"></small>
                        </button>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 small"><i class="fas fa-exclamation-triangle mr-1"></i>Al cambiar la factura o el PDF del pago, la confirmación final volverá a quedar pendiente.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button></div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .conciliaciones-page .card, .conciliaciones-page .info-box { border: 0; border-radius: 12px; box-shadow: 0 8px 24px rgba(32,83,154,.10); overflow: hidden; }
        .conciliaciones-page .card-header { background: #fff; border-bottom: 1px solid #e8edf5; padding: 1rem 1.25rem; }
        .conciliaciones-page .card-title { color: #173d73; font-weight: 700; }
        .months-grid { display: grid; grid-template-columns: repeat(6,minmax(130px,1fr)); gap: .65rem; }
        .month-card { color: #34445d; border: 1px solid #d8e0ec; border-radius: 10px; padding: .7rem .8rem; text-decoration: none!important; transition: .15s ease; }
        .month-card:hover { color: #20539a; border-color: #7fa5d9; transform: translateY(-1px); }
        .month-card.active { color: #fff; border-color: #20539a; background: #20539a; box-shadow: 0 5px 14px rgba(32,83,154,.25); }
        .month-name { display: block; font-weight: 700; }.month-progress { display: block; font-size: .72rem; opacity: .85; margin-top: .15rem; }
        .month-card .progress { height: 4px; background: rgba(32,83,154,.15); }.month-card .progress-bar { background: #27ae60; }.month-card.active .progress { background: rgba(255,255,255,.25); }.month-card.active .progress-bar { background: #fff; }
        .info-box-icon { border-radius: 12px 0 0 12px; }.search-box { max-width: 310px; }
        .summary-filter { width: 100%; border: 2px solid transparent; padding: 0; text-align: left; background: #fff; cursor: pointer; transition: .15s ease; }
        .summary-filter:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(32,83,154,.18)!important; }
        .summary-filter:focus { outline: none; }
        .empresas-card thead th { background: #edf2f9; color: #20539a; border: 0; font-size: .76rem; letter-spacing: .3px; text-transform: uppercase; white-space: nowrap; }
        .empresas-card tbody td { vertical-align: middle; }.empresa-cell { min-width: 220px; }.status-badge { border-radius: 999px; padding: .48rem .7rem; white-space: nowrap; }
        .document-cell { min-width: 220px; max-width: 300px; }.document-link { display: flex; align-items: center; font-weight: 600; }.document-link span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cobranza-format-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .75rem; }
        .cobranza-format-option { position: relative; display: flex; align-items: center; gap: .75rem; min-height: 86px; margin: 0; padding: .9rem; border: 2px solid #dce4ef; border-radius: 10px; cursor: pointer; transition: .15s ease; }
        .cobranza-format-option:hover { border-color: #7fa5d9; background: #f8fbff; }
        .cobranza-format-option:focus-within { border-color: #20539a; box-shadow: 0 0 0 3px rgba(32,83,154,.16); }
        .cobranza-format-option:has(input:checked) { border-color: #20539a; background: #edf4ff; box-shadow: 0 0 0 2px rgba(32,83,154,.08); }
        .cobranza-format-option input { position: absolute; opacity: 0; pointer-events: none; }
        .cobranza-format-icon { display: inline-flex; flex: 0 0 38px; width: 38px; height: 38px; align-items: center; justify-content: center; border-radius: 50%; color: #fff; }
        .cobranza-format-option strong, .cobranza-format-option small { display: block; }
        .cobranza-format-option small { margin-top: .2rem; color: #6c757d; font-weight: 400; }
        @media (max-width: 1199.98px) { .months-grid { grid-template-columns: repeat(4,1fr); } }
        @media (max-width: 767.98px) { .months-grid { grid-template-columns: repeat(2,1fr); }.search-box { width: 100%; max-width: none; }.cobranza-format-grid { grid-template-columns: 1fr; } }
    </style>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('documentoForm');
            const baseAction = @json(url('/conciliacion/conciliaciones/empresa'));
            document.querySelectorAll('.upload-trigger').forEach(function (button) {
                button.addEventListener('click', function () {
                    form.action = baseAction + '/' + this.dataset.empresaId + '/documento';
                    document.getElementById('documentoEmpresa').textContent = this.dataset.empresa;
                    const documentoActual = document.getElementById('documentoActual');
                    documentoActual.textContent = this.dataset.documento ? 'Documento actual: ' + this.dataset.documento + '. Al guardar se reemplazará.' : '';
                    documentoActual.classList.toggle('d-none', !this.dataset.documento);
                    form.querySelector('.custom-file-label').textContent = 'Seleccionar PDF o Excel';
                    form.querySelector('[name="documento"]').value = '';
                });
            });
            document.getElementById('documento')?.addEventListener('change', function () {
                this.nextElementSibling.textContent = this.files[0]?.name || 'Seleccionar PDF o Excel';
            });

            const facturasUrl = @json(route('dashboard.conciliacion.conciliaciones.facturas-disponibles'));
            const facturaSelect = document.getElementById('cobrarFactura');
            const guardarCobro = document.getElementById('guardarPorCobrar');
            const cobrarEstado = document.getElementById('cobrarEstado');
            let empresaCobroId = '';
            let facturaActual = '';

            function prepararDatosCobranza(empresa, formato, nombre) {
                document.getElementById('cobrarNombreEmpresa').value = nombre || empresa;
                document.querySelectorAll('[name="formato_nota_cobranza"]').forEach(function (option) {
                    option.checked = option.value === formato;
                });
            }

            async function cargarFacturas() {
                const mes = document.getElementById('cobrarFacturadoMes').value;
                const anio = document.getElementById('cobrarFacturadoAnio').value;
                facturaSelect.disabled = true;
                guardarCobro.disabled = true;
                facturaSelect.innerHTML = '<option value="">Consultando la API...</option>';
                cobrarEstado.className = 'small text-info mt-2';
                cobrarEstado.textContent = 'Buscando facturas de contratos...';

                try {
                    const response = await fetch(facturasUrl + '?mes=' + encodeURIComponent(mes) + '&anio=' + encodeURIComponent(anio), {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'No se pudieron cargar las facturas.');

                    facturaSelect.innerHTML = '<option value="">Selecciona una factura...</option>';
                    data.facturas.forEach(function (factura) {
                        const option = document.createElement('option');
                        option.value = factura.ventaId;
                        option.textContent = 'Bs ' + Number(factura.totalLinea).toFixed(2)
                            + ' · Razón social: ' + (factura.razonSocial || 'Sin dato')
                            + ' · Código cliente: ' + (factura.codigoCliente || 'Sin dato')
                            + ' · NIT/CI/CEX: ' + (factura.numeroDocumento || 'Sin dato');
                        const perteneceAOtra = factura.asociadaEmpresaId && String(factura.asociadaEmpresaId) !== String(empresaCobroId);
                        option.disabled = perteneceAOtra;
                        if (perteneceAOtra) option.textContent += ' · Asociada a ' + factura.asociadaEmpresa;
                        if (factura.ventaId === facturaActual) option.selected = true;
                        facturaSelect.appendChild(option);
                    });
                    facturaSelect.disabled = data.facturas.length === 0;
                    guardarCobro.disabled = !facturaSelect.value;
                    cobrarEstado.className = 'small text-muted mt-2';
                    cobrarEstado.textContent = data.facturas.length + ' factura(s) encontrada(s).';
                } catch (error) {
                    facturaSelect.innerHTML = '<option value="">No se pudieron cargar las facturas</option>';
                    cobrarEstado.className = 'small text-danger mt-2';
                    cobrarEstado.textContent = error.message;
                }
            }

            document.querySelectorAll('.cobrar-trigger').forEach(function (button) {
                button.addEventListener('click', function () {
                    empresaCobroId = this.dataset.empresaId;
                    facturaActual = this.dataset.factura || '';
                    document.getElementById('cobrarEmpresaId').value = empresaCobroId;
                    document.getElementById('cobrarEmpresa').textContent = this.dataset.empresa;
                    prepararDatosCobranza(this.dataset.empresa, this.dataset.formatoCobranza, this.dataset.nombreCobranza);
                    cargarFacturas();
                });
            });
            document.getElementById('buscarFacturas')?.addEventListener('click', cargarFacturas);
            facturaSelect?.addEventListener('change', function () { guardarCobro.disabled = !this.value; });

            document.querySelectorAll('.pago-trigger').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('pagoRecibidoForm').action = this.dataset.action;
                    document.getElementById('pagoEmpresa').textContent = this.dataset.empresa;
                    document.getElementById('pagoFactura').textContent = this.dataset.factura;
                    document.getElementById('pagoMonto').textContent = this.dataset.monto;
                    const comprobante = document.getElementById('comprobantePago');
                    comprobante.value = '';
                    comprobante.nextElementSibling.textContent = 'Seleccionar PDF...';
                });
            });
            document.getElementById('comprobantePago')?.addEventListener('change', function () {
                this.nextElementSibling.textContent = this.files[0]?.name || 'Seleccionar PDF...';
            });

            document.querySelectorAll('.confirmacion-trigger').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('confirmacionPagoForm').action = this.dataset.action;
                    document.getElementById('confirmacionEmpresa').textContent = this.dataset.empresa;
                    document.getElementById('confirmacionFactura').textContent = this.dataset.factura;
                    document.getElementById('confirmacionMonto').textContent = this.dataset.monto;
                });
            });

            let edicionActual = null;
            function cambiarModal(destino, preparar) {
                $('#editarConciliacionModal').modal('hide');
                setTimeout(function () { preparar(); $(destino).modal('show'); }, 250);
            }
            document.querySelectorAll('.editar-trigger').forEach(function (button) {
                button.addEventListener('click', function () {
                    edicionActual = this.dataset;
                    document.getElementById('editarEmpresa').textContent = edicionActual.empresa;
                    const documentoBloqueado = edicionActual.tieneConciliado === '1';
                    document.getElementById('editarDocumentoEstado').textContent = documentoBloqueado
                        ? 'Archivo bloqueado porque ya fue marcado como Conciliado.'
                        : (edicionActual.documento ? 'Actual: ' + edicionActual.documento : 'Todavía no tiene documento.');
                    document.getElementById('editarFacturaEstado').textContent = edicionActual.facturaLabel ? 'Actual: ' + edicionActual.facturaLabel : 'Todavía no tiene factura.';
                    document.getElementById('editarPagoEstado').textContent = edicionActual.tienePago === '1' ? 'Reemplazar el PDF actual.' : 'Todavía no tiene PDF de pago.';
                    document.getElementById('editarDocumento').disabled = documentoBloqueado;
                    document.getElementById('editarFactura').disabled = edicionActual.tieneConciliado !== '1';
                    document.getElementById('editarPago').disabled = !edicionActual.factura;
                });
            });
            document.getElementById('editarDocumento')?.addEventListener('click', function () {
                cambiarModal('#documentoModal', function () {
                    form.action = baseAction + '/' + edicionActual.empresaId + '/documento';
                    document.getElementById('documentoEmpresa').textContent = edicionActual.empresa;
                    const actual = document.getElementById('documentoActual');
                    actual.textContent = edicionActual.documento ? 'Documento actual: ' + edicionActual.documento + '. Al guardar se reemplazará.' : '';
                    actual.classList.toggle('d-none', !edicionActual.documento);
                    form.querySelector('[name="documento"]').value = '';
                    form.querySelector('.custom-file-label').textContent = 'Seleccionar PDF o Excel';
                });
            });
            document.getElementById('editarFactura')?.addEventListener('click', function () {
                cambiarModal('#porCobrarModal', function () {
                    empresaCobroId = edicionActual.empresaId;
                    facturaActual = edicionActual.factura || '';
                    document.getElementById('cobrarEmpresaId').value = empresaCobroId;
                    document.getElementById('cobrarEmpresa').textContent = edicionActual.empresa;
                    prepararDatosCobranza(edicionActual.empresa, edicionActual.formatoCobranza, edicionActual.nombreCobranza);
                    cargarFacturas();
                });
            });
            document.getElementById('editarPago')?.addEventListener('click', function () {
                cambiarModal('#pagoRecibidoModal', function () {
                    document.getElementById('pagoRecibidoForm').action = edicionActual.pagoAction;
                    document.getElementById('pagoEmpresa').textContent = edicionActual.empresa;
                    document.getElementById('pagoFactura').textContent = edicionActual.facturaLabel;
                    document.getElementById('pagoMonto').textContent = edicionActual.monto;
                    const comprobante = document.getElementById('comprobantePago');
                    comprobante.value = '';
                    comprobante.nextElementSibling.textContent = 'Seleccionar PDF...';
                });
            });

            const search = document.getElementById('buscarEmpresa');
            const rows = Array.from(document.querySelectorAll('#tablaEmpresas tr[data-search]'));
            const summaryButtons = Array.from(document.querySelectorAll('.summary-filter'));
            const modalCompanies = document.getElementById('resumenModalEmpresas');
            const modalEmpty = document.getElementById('resumenModalVacio');
            const modalTotal = document.getElementById('resumenModalTotal');
            const modalTitle = document.getElementById('resumenModalTitulo');

            summaryButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const filter = this.dataset.summaryFilter;
                    const matches = rows.filter(function (row) {
                        return row.dataset[filter] === '1';
                    });

                    modalCompanies.replaceChildren();
                    matches.forEach(function (row) {
                        const company = row.querySelector('.empresa-cell strong')?.textContent.trim() || '';
                        const code = row.querySelector('.empresa-cell small')?.textContent.trim() || 'Sin código';
                        const stateBadge = row.querySelector('.status-badge');
                        const modalRow = document.createElement('tr');
                        const companyCell = document.createElement('td');
                        const codeCell = document.createElement('td');
                        const stateCell = document.createElement('td');

                        companyCell.textContent = company;
                        companyCell.className = 'font-weight-bold';
                        codeCell.textContent = code;
                        stateCell.textContent = stateBadge?.textContent.trim() || '';
                        modalRow.append(companyCell, codeCell, stateCell);
                        modalCompanies.appendChild(modalRow);
                    });

                    modalTitle.textContent = this.dataset.summaryLabel + ' · {{ $actual['nombre'] }} {{ $anio }}';
                    modalTotal.textContent = matches.length + (matches.length === 1 ? ' empresa encontrada' : ' empresas encontradas');
                    modalEmpty.classList.toggle('d-none', matches.length > 0);
                    $('#resumenEmpresasModal').modal('show');
                });
            });
            search?.addEventListener('input', function () {
                const term = this.value.trim().toLocaleLowerCase();
                let visibles = 0;
                rows.forEach(function (row) {
                    const show = row.dataset.search.includes(term);
                    row.classList.toggle('d-none', !show);
                    if (show) visibles++;
                });
                document.getElementById('sinResultados').classList.toggle('d-none', visibles > 0);
            });
        });
    </script>
@endsection
