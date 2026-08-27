@extends('adminlte::page')

@section('title', 'Mandar alertas')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-1">Alertas para empresas</h1>
            <p class="text-muted mb-0">Prepara comunicados, revísalos y apruébalos antes de mostrarlos a las empresas.</p>
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
            <h3 class="card-title font-weight-bold"><i class="fas fa-bell text-warning mr-2"></i>Noticias y alertas</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr><th>Portada</th><th>Comunicado</th><th>Empresas</th><th>Estado</th><th>Vistas</th><th>Publicación</th><th class="text-right">Acciones</th></tr>
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
                                <td>
                                    @if($alerta->aprobada_at)
                                        <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Publicada</span>
                                    @else
                                        <span class="badge badge-warning"><i class="fas fa-clock mr-1"></i>Pendiente de aprobación</span>
                                    @endif
                                </td>
                                <td>
                                    @aclcan('readers', null, 'alertas-empresa')
                                        <button class="btn btn-sm btn-outline-success" type="button" data-toggle="modal" data-target="#readersModal{{ $alerta->id }}" title="Ver usuarios que marcaron visto">
                                            <i class="fas fa-eye mr-1"></i>{{ $alerta->lectores_count }}
                                        </button>
                                    @endaclcan
                                </td>
                                <td>
                                    @if($alerta->aprobada_at)
                                        <span class="d-block">{{ $alerta->publicada_at?->format('d/m/Y H:i') }}</span>
                                        <small class="text-muted">Aprobó: {{ $alerta->aprobador?->name ?: 'Usuario eliminado' }}</small>
                                    @else
                                        <span class="text-muted">Aún no visible</span>
                                        <small class="d-block text-muted">Creó: {{ $alerta->creador?->name }}</small>
                                    @endif
                                </td>
                                <td class="text-right text-nowrap">
                                    @aclcan('approve', null, 'alertas-empresa')
                                        @if(!$alerta->aprobada_at)
                                            <button class="btn btn-sm btn-success" type="button" data-toggle="modal" data-target="#approveAlertModal{{ $alerta->id }}" title="Corregir y aprobar publicación">
                                                <i class="fas fa-check mr-1"></i>Aprobar
                                            </button>
                                        @endif
                                    @endaclcan
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
                            <tr><td colspan="7" class="text-center text-muted py-5">Todavía no se enviaron alertas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($alertas->hasPages())<div class="card-footer bg-white">{{ $alertas->links() }}</div>@endif
    </div>

    @foreach($alertas as $alerta)
        @aclcan('readers', null, 'alertas-empresa')
        <div class="modal fade" id="readersModal{{ $alerta->id }}" tabindex="-1" role="dialog" aria-labelledby="readersModalTitle{{ $alerta->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title font-weight-bold" id="readersModalTitle{{ $alerta->id }}">Usuarios que marcaron visto</h5>
                            <small class="text-muted">{{ $alerta->titulo }}</small>
                        </div>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body p-0">
                        @if($alerta->lectores->isEmpty())
                            <div class="text-center text-muted py-5"><i class="far fa-eye-slash fa-2x mb-2 d-block"></i>Ningún usuario marcó esta noticia como vista.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light"><tr><th>Usuario</th><th>Empresa</th><th>Fecha y hora</th></tr></thead>
                                    <tbody>
                                        @foreach($alerta->lectores->sortByDesc(fn ($lector) => $lector->pivot->leida_at) as $lector)
                                            <tr>
                                                <td>{{ $lector->name }}</td>
                                                <td>{{ $lector->empresa?->sigla ?: ($lector->empresa?->nombre ?: 'Sin empresa') }}</td>
                                                <td>{{ \Illuminate\Support\Carbon::parse($lector->pivot->leida_at)->format('d/m/Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button></div>
                </div>
            </div>
        </div>
        @endaclcan

        @aclcan('approve', null, 'alertas-empresa')
        @if(!$alerta->aprobada_at)
            <div class="modal fade" id="approveAlertModal{{ $alerta->id }}" tabindex="-1" role="dialog" aria-labelledby="approveAlertTitle{{ $alerta->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <form method="POST" action="{{ route('alertas-empresa.approve', $alerta, false) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="_approval_alert_id" value="{{ $alerta->id }}">
                            <div class="modal-header bg-success text-white">
                                <div><h5 class="modal-title font-weight-bold" id="approveAlertTitle{{ $alerta->id }}">Revisar y aprobar noticia</h5><small>Corrige el texto antes de hacerlo visible para las empresas.</small></div>
                                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                                @if((string) old('_approval_alert_id') === (string) $alerta->id && $errors->approveAlert->any())
                                    <div class="alert alert-danger"><strong>Revisa los datos:</strong><ul class="mb-0 mt-1">@foreach($errors->approveAlert->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                                @endif
                                <div class="row">
                                    <div class="col-md-4 mb-3 mb-md-0">
                                        <img src="{{ route('alertas-empresa.portada', $alerta, false) }}" alt="Portada de {{ $alerta->titulo }}" class="img-fluid rounded border">
                                        <small class="text-muted d-block mt-2">La noticia todavía no es visible para las empresas.</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="approveTitle{{ $alerta->id }}">Título <span class="text-danger">*</span></label>
                                            <input class="form-control" id="approveTitle{{ $alerta->id }}" name="titulo" maxlength="150" required value="{{ (string) old('_approval_alert_id') === (string) $alerta->id ? old('titulo', $alerta->titulo) : $alerta->titulo }}">
                                        </div>
                                        <div class="form-group mb-0">
                                            <label for="approveMessage{{ $alerta->id }}">Texto del comunicado</label>
                                            <textarea class="form-control" id="approveMessage{{ $alerta->id }}" name="mensaje" rows="8" maxlength="10000">{{ (string) old('_approval_alert_id') === (string) $alerta->id ? old('mensaje', $alerta->mensaje) : $alerta->mensaje }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Seguir pendiente</button>
                                <button type="submit" class="btn btn-success" onclick="return confirm('¿Confirmas que la noticia está corregida y lista para las empresas?')"><i class="fas fa-check-circle mr-1"></i>Confirmar y publicar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
        @endaclcan
    @endforeach

    @aclcan('create', null, 'alertas-empresa')
    <div class="modal fade" id="createCompanyAlertModal" tabindex="-1" role="dialog" aria-labelledby="createCompanyAlertTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="{{ route('alertas-empresa.store', [], false) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <div><h5 class="modal-title font-weight-bold" id="createCompanyAlertTitle">Nueva alerta</h5><small>Se guardará pendiente hasta que sea revisada y aprobada.</small></div>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @if($errors->createAlert->any())
                            <div class="alert alert-danger"><strong>Revisa los datos:</strong><ul class="mb-0 mt-1">@foreach($errors->createAlert->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
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
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar para revisión</button>
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
            @if($errors->createAlert->any()) $('#createCompanyAlertModal').modal('show'); @endif
            @if($errors->approveAlert->any() && old('_approval_alert_id'))
                const approvalAlertId = @json((string) old('_approval_alert_id'));
                if (/^\d+$/.test(approvalAlertId)) $('#approveAlertModal' + approvalAlertId).modal('show');
            @endif
        });
    </script>
@endpush
