@extends('adminlte::page')

@section('title', 'Mandar alertas')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-1">Alertas para empresas</h1>
            <p class="text-muted mb-0">Publica comunicados que aparecerán como modal en los perfiles seleccionados.</p>
        </div>
        @aclcan('create', null, 'alertas-empresa')
            <button class="btn btn-primary mt-3 mt-md-0" type="button" data-toggle="modal" data-target="#createCompanyAlertModal">
                <i class="fas fa-paper-plane mr-1"></i> Mandar alerta
            </button>
        @endaclcan
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h3 class="card-title font-weight-bold"><i class="fas fa-bell text-warning mr-2"></i>Alertas enviadas</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr><th>Portada</th><th>Comunicado</th><th>Empresas</th><th>Vistas</th><th>Enviada</th><th class="text-right">Acciones</th></tr>
                    </thead>
                    <tbody>
                        @forelse($alertas as $alerta)
                            <tr>
                                <td style="width:110px"><img src="{{ route('alertas-empresa.portada', $alerta, false) }}" alt="" class="rounded" style="width:92px;height:58px;object-fit:cover"></td>
                                <td>
                                    <strong class="d-block">{{ $alerta->titulo }}</strong>
                                    <small class="text-muted">{{ \Illuminate\Support\Str::limit($alerta->mensaje ?: 'Solo imagen'.($alerta->pdf_path ? ' y PDF' : ''), 85) }}</small>
                                </td>
                                <td style="min-width:230px">
                                    @foreach($alerta->empresas->take(3) as $empresa)
                                        <span class="badge badge-primary mb-1">{{ $empresa->sigla ?: $empresa->nombre }}</span>
                                    @endforeach
                                    @if($alerta->empresas->count() > 3)<span class="badge badge-secondary">+{{ $alerta->empresas->count() - 3 }}</span>@endif
                                </td>
                                <td><span class="badge badge-success">{{ $alerta->lectores_count }}</span></td>
                                <td><span class="d-block">{{ optional($alerta->publicada_at)->format('d/m/Y H:i') }}</span><small class="text-muted">{{ $alerta->creador?->name }}</small></td>
                                <td class="text-right text-nowrap">
                                    @aclcan('export', null, 'alertas-empresa')
                                        @if($alerta->pdf_path)
                                            <a class="btn btn-sm btn-outline-danger" href="{{ route('alertas-empresa.pdf', $alerta, false) }}" target="_blank" title="Ver PDF"><i class="fas fa-file-pdf"></i></a>
                                        @endif
                                    @endaclcan
                                    @aclcan('delete', null, 'alertas-empresa')
                                        <form class="d-inline" method="POST" action="{{ route('alertas-empresa.destroy', $alerta, false) }}" onsubmit="return confirm('¿Eliminar esta alerta definitivamente?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-secondary" type="submit" title="Eliminar"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endaclcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">Todavía no se enviaron alertas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($alertas->hasPages())<div class="card-footer bg-white">{{ $alertas->links() }}</div>@endif
    </div>

    @aclcan('create', null, 'alertas-empresa')
    <div class="modal fade" id="createCompanyAlertModal" tabindex="-1" role="dialog" aria-labelledby="createCompanyAlertTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="{{ route('alertas-empresa.store', [], false) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <div><h5 class="modal-title font-weight-bold" id="createCompanyAlertTitle">Nueva alerta</h5><small>La portada es obligatoria; el texto y el PDF son opcionales.</small></div>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @if($errors->any())
                            <div class="alert alert-danger"><strong>Revisa los datos:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif
                        <div class="row">
                            <div class="col-lg-7">
                                <div class="form-group">
                                    <label for="alertTitle">Título <span class="text-danger">*</span></label>
                                    <input class="form-control" id="alertTitle" name="titulo" maxlength="150" required value="{{ old('titulo') }}" placeholder="Ej.: Actualización importante del servicio">
                                </div>
                                <div class="form-group">
                                    <label for="alertMessage">Texto del comunicado <span class="text-muted font-weight-normal">(opcional)</span></label>
                                    <textarea class="form-control" id="alertMessage" name="mensaje" rows="6" maxlength="10000" placeholder="Escribe aquí el mensaje para la empresa...">{{ old('mensaje') }}</textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label for="alertCover">Imagen de portada <span class="text-danger">*</span></label>
                                        <input class="form-control-file" id="alertCover" name="portada" type="file" accept="image/jpeg,image/png,image/webp" required>
                                        <small class="text-muted">JPG, PNG o WEBP. Máximo 10 MB.</small>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label for="alertPdf">Documento PDF <span class="text-muted font-weight-normal">(opcional)</span></label>
                                        <input class="form-control-file" id="alertPdf" name="pdf" type="file" accept="application/pdf">
                                        <small class="text-muted">Máximo 50 MB.</small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="alertExpires">Mostrar hasta <span class="text-muted font-weight-normal">(opcional)</span></label>
                                    <input class="form-control" id="alertExpires" name="vence_at" type="datetime-local" value="{{ old('vence_at') }}">
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="mb-0">Perfiles de empresa <span class="text-danger">*</span></label>
                                        <button class="btn btn-link btn-sm p-0" type="button" id="toggleAllCompanies">Seleccionar todas</button>
                                    </div>
                                    <input class="form-control form-control-sm mb-2" id="companyAlertSearch" type="search" placeholder="Buscar empresa...">
                                    <div style="max-height:345px;overflow-y:auto">
                                        @foreach($empresas as $empresa)
                                            <label class="d-flex align-items-start bg-white border rounded p-2 mb-2 company-alert-option" data-search="{{ mb_strtolower($empresa->nombre.' '.$empresa->sigla.' '.$empresa->codigo_cliente) }}">
                                                <input class="mt-1 mr-2 company-alert-checkbox" type="checkbox" name="empresa_ids[]" value="{{ $empresa->id }}" {{ in_array((string) $empresa->id, array_map('strval', old('empresa_ids', [])), true) ? 'checked' : '' }}>
                                                <span><strong class="d-block">{{ $empresa->nombre }}</strong><small class="text-muted">{{ $empresa->sigla }} · {{ $empresa->codigo_cliente }}</small></span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <small class="text-muted"><span id="selectedCompanyCount">0</span> empresa(s) seleccionada(s).</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Enviar alerta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endaclcan
@stop

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.getElementById('companyAlertSearch');
            const options = Array.from(document.querySelectorAll('.company-alert-option'));
            const checks = Array.from(document.querySelectorAll('.company-alert-checkbox'));
            const counter = document.getElementById('selectedCompanyCount');
            const toggle = document.getElementById('toggleAllCompanies');
            const updateCount = () => { counter.textContent = checks.filter(item => item.checked).length; };
            checks.forEach(item => item.addEventListener('change', updateCount));
            updateCount();
            search?.addEventListener('input', function () {
                const term = this.value.trim().toLocaleLowerCase('es');
                options.forEach(option => { option.style.display = option.dataset.search.includes(term) ? '' : 'none'; });
            });
            toggle?.addEventListener('click', function () {
                const visible = checks.filter(item => item.closest('.company-alert-option').style.display !== 'none');
                const select = visible.some(item => !item.checked);
                visible.forEach(item => { item.checked = select; });
                this.textContent = select ? 'Quitar selección' : 'Seleccionar todas';
                updateCount();
            });
            @if($errors->any()) $('#createCompanyAlertModal').modal('show'); @endif
        });
    </script>
@endpush
