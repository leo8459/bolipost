@extends('adminlte::page')

@section('title', 'Historial de empresas')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-1">Historial de empresas</h1>
            <p class="text-muted mb-0">Contratos anteriores respaldados con todos sus datos y documentos PDF.</p>
        </div>
        @can('empresas.index')
            <a href="{{ route('empresas.index') }}" class="btn btn-outline-primary mt-3 mt-md-0">
                <i class="fas fa-building mr-1"></i> Volver a empresas
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="card shadow-sm">
        @can('feature.empresas.historial.search')
            <div class="card-header bg-white">
                <form method="GET" action="{{ route('empresas.historial.index') }}" class="row align-items-end">
                    <div class="col-md-8 col-lg-6">
                        <label for="historySearch">Buscar en el historial</label>
                        <input
                            id="historySearch"
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Empresa, sigla, codigo, clasificacion o cobertura"
                        >
                    </div>
                    <div class="col-md-4 col-lg-6 mt-2 mt-md-0">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                        @if($search !== '')
                            <a href="{{ route('empresas.historial.index') }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        @endcan

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Empresa</th>
                            <th>Codigo cliente</th>
                            <th>Clasificacion</th>
                            <th>Doc. legal</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Cobertura</th>
                            <th>Presupuesto</th>
                            <th>PDF respaldado</th>
                            <th>Guardado por</th>
                            <th>Fecha de archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historiales as $historial)
                            <tr>
                                <td>
                                    <strong>{{ $historial->nombre }}</strong>
                                    <small class="d-block text-muted">{{ $historial->sigla ?: 'Sin sigla' }}</small>
                                </td>
                                <td>{{ $historial->codigo_cliente ?: '-' }}</td>
                                <td>{{ $historial->clasificacion ?: '-' }}</td>
                                <td>{{ $historial->documentacion_legal ?: '-' }}</td>
                                <td>{{ optional($historial->inicio_contrato)->format('d/m/Y') ?: '-' }}</td>
                                <td>{{ optional($historial->fin_contrato)->format('d/m/Y') ?: '-' }}</td>
                                <td>{{ $historial->cobertura ?: '-' }}</td>
                                <td>{{ $historial->presupuesto !== null ? number_format((float) $historial->presupuesto, 2) : '-' }}</td>
                                <td>
                                    @if($historial->documento_pdf_path)
                                        @can('feature.empresas.historial.view-pdf')
                                            <a
                                                href="{{ route('empresas.historial.pdf', $historial) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="btn btn-sm btn-outline-danger"
                                            >
                                                <i class="fas fa-file-pdf mr-1"></i> Ver PDF
                                            </a>
                                        @else
                                            <span class="text-muted small">PDF protegido</span>
                                        @endcan
                                    @else
                                        <span class="text-muted small">Sin PDF</span>
                                    @endif
                                </td>
                                <td>{{ $historial->archivadoPor?->name ?: 'Usuario no disponible' }}</td>
                                <td class="text-muted small">{{ optional($historial->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <strong>No hay contratos guardados en el historial.</strong>
                                    <div class="text-muted mt-1">Usa el boton “Añadir a historial” en la vista Empresas.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($historiales->hasPages())
            <div class="card-footer bg-white d-flex justify-content-end">
                {{ $historiales->links() }}
            </div>
        @endif
    </div>
@endsection
