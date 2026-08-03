@extends('adminlte::page')

@section('title', 'Contratos escaneados')
@section('template_title')
    Contratos escaneados
@endsection

@section('content')
    <div class="scanned-contracts-wrap">
        <div class="card scanned-contracts-card">
            <div class="scanned-contracts-header">
                <div>
                    <h3 class="mb-1">Contratos escaneados</h3>
                    <p class="mb-0">Documentos PDF registrados para las empresas.</p>
                </div>

                <form method="GET" action="{{ route('area-contratos.contratos-escaneados') }}" class="scanned-search">
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Buscar empresa, sigla o codigo..."
                        aria-label="Buscar contratos escaneados"
                    >
                    <button type="submit" class="btn btn-light font-weight-bold">Buscar</button>
                    @if ($search !== '')
                        <a href="{{ route('area-contratos.contratos-escaneados') }}" class="btn btn-outline-light font-weight-bold">Limpiar</a>
                    @endif
                </form>
            </div>

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 text-muted">
                    <div>
                        @if ($search !== '')
                            Resultados para: <strong>{{ $search }}</strong>
                        @else
                            Mostrando todos los contratos PDF disponibles
                        @endif
                    </div>
                    <div>Total: <strong>{{ $empresas->total() }}</strong></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover scanned-contracts-table mb-0">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Sigla</th>
                                <th>Codigo cliente</th>
                                <th>Documento</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Actualizado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($empresas as $empresa)
                                <tr>
                                    <td><span class="company-pill">{{ $empresa->nombre }}</span></td>
                                    <td>{{ $empresa->sigla ?: '-' }}</td>
                                    <td>{{ $empresa->codigo_cliente ?: '-' }}</td>
                                    <td>{{ $empresa->documentacion_legal ?: 'CONTRATO' }}</td>
                                    <td>{{ $empresa->inicio_contrato ? \Illuminate\Support\Carbon::parse($empresa->inicio_contrato)->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $empresa->fin_contrato ? \Illuminate\Support\Carbon::parse($empresa->fin_contrato)->format('d/m/Y') : '-' }}</td>
                                    <td>{{ optional($empresa->updated_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="text-center text-nowrap">
                                        <a
                                            href="{{ route('area-contratos.contratos-escaneados.view', $empresa) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-sm action-view"
                                            title="Ver PDF"
                                        >
                                            <i class="fas fa-eye mr-1"></i> Ver
                                        </a>
                                        <a
                                            href="{{ route('area-contratos.contratos-escaneados.download', $empresa) }}"
                                            class="btn btn-sm action-download"
                                            title="Descargar PDF"
                                        >
                                            <i class="fas fa-download mr-1"></i> Descargar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="far fa-file-pdf empty-icon mb-2"></i>
                                        <div class="font-weight-bold">No se encontraron contratos escaneados</div>
                                        <div class="text-muted">Los PDF cargados desde Empresas apareceran aqui.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($empresas->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $empresas->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@section('css')
    <style>
        .scanned-contracts-wrap {
            padding: 18px;
            border-radius: 16px;
            background: #f5f7fb;
        }
        .scanned-contracts-card {
            overflow: hidden;
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 26px rgba(0, 0, 0, .08);
        }
        .scanned-contracts-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px;
            color: #fff;
            background: #20539a;
        }
        .scanned-contracts-header h3 { font-weight: 800; }
        .scanned-contracts-header p { color: rgba(255, 255, 255, .8); }
        .scanned-search {
            display: flex;
            flex: 1 1 580px;
            justify-content: flex-end;
            gap: 8px;
        }
        .scanned-search .form-control { max-width: 520px; border-radius: 10px; }
        .scanned-search .btn { border-radius: 10px; }
        .scanned-contracts-table thead th {
            border-bottom: 2px solid rgba(32, 83, 154, .2);
            color: #174886;
            background: rgba(32, 83, 154, .08);
            font-weight: 800;
            white-space: nowrap;
        }
        .scanned-contracts-table td { vertical-align: middle; }
        .company-pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            color: #174886;
            background: rgba(32, 83, 154, .12);
            font-weight: 800;
        }
        .action-view, .action-download {
            margin: 2px;
            border-radius: 9px;
            font-weight: 700;
        }
        .action-view { border: 1px solid #20539a; color: #20539a; background: #fff; }
        .action-view:hover { color: #fff; background: #20539a; }
        .action-download { color: #fff; background: #20539a; }
        .action-download:hover { color: #fff; background: #173f76; }
        .empty-icon { display: block; color: #20539a; font-size: 2rem; }
        @media (max-width: 767.98px) {
            .scanned-contracts-header, .scanned-search { align-items: stretch; flex-direction: column; }
            .scanned-search { flex-basis: auto; }
            .scanned-search .form-control { max-width: none; }
        }
    </style>
@endsection
