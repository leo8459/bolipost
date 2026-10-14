@extends('adminlte::page')

@section('title', 'Mandar solicitud de llenado a empresas')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">Mandar solicitud de llenado a empresas</h1>
            <small class="text-muted">Solicita a los usuarios que registren si su entidad está en un municipio o provincia.</small>
        </div>
    </div>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if ($errors->responseEdit->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>No se pudo guardar la respuesta.</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->responseEdit->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-sm-4 col-lg-3 mb-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted text-uppercase small font-weight-bold">Usuarios de empresa</div>
                <div class="h3 mb-0 text-primary">{{ number_format($totalUsers, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-sm-4 col-lg-3 mb-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted text-uppercase small font-weight-bold">Solicitudes pendientes</div>
                <div class="h3 mb-0 text-warning">{{ number_format($pendingUsers, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-sm-4 col-lg-3 mb-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted text-uppercase small font-weight-bold">Respuestas recibidas</div>
                <div class="h3 mb-0 text-success">{{ number_format($answeredUsers, 0, ',', '.') }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <form method="GET" action="{{ route('empresa.solicitud-llenado.index') }}" class="form-inline">
                <label for="buscar" class="sr-only">Buscar empresa o usuario</label>
                <input id="buscar" type="search" name="buscar" value="{{ $search }}" class="form-control mr-2 mb-2 mb-sm-0"
                    placeholder="Empresa, nombre, alias o correo" style="min-width: 280px">
                <label for="empresa_id" class="sr-only">Filtrar por empresa</label>
                <select id="empresa_id" name="empresa_id" class="form-control mr-2 mb-2 mb-sm-0" style="min-width: 220px">
                    <option value="">Todas las empresas</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected($companyId === (int) $company->id)>
                            {{ $company->nombre }}{{ $company->sigla ? ' · '.$company->sigla : '' }}
                        </option>
                    @endforeach
                </select>
                <label for="respuesta" class="sr-only">Filtrar por respuesta</label>
                <select id="respuesta" name="respuesta" class="form-control mr-2 mb-2 mb-sm-0">
                    <option value="">Todos los estados</option>
                    <option value="respondidas" @selected($responseStatus === 'respondidas')>Respondidas</option>
                    <option value="pendientes" @selected($responseStatus === 'pendientes')>Pendientes</option>
                    <option value="sin_solicitud" @selected($responseStatus === 'sin_solicitud')>Sin solicitud</option>
                    <option value="ubicacion_registrada" @selected($responseStatus === 'ubicacion_registrada')>Ubicación registrada</option>
                </select>
                <button class="btn btn-outline-primary mr-2" type="submit"><i class="fas fa-search mr-1"></i>Buscar</button>
                @if ($search !== '' || $companyId > 0 || $responseStatus !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('empresa.solicitud-llenado.index') }}">Limpiar</a>
                @endif
            </form>
            <form method="POST" action="{{ route('empresa.solicitud-llenado.send', request()->only('buscar', 'empresa_id', 'respuesta')) }}" id="send-location-request-form">
                @csrf
            </form>
            <form method="POST" action="{{ route('empresa.solicitud-llenado.update-responses', request()->only('buscar', 'empresa_id', 'respuesta')) }}" id="save-all-responses-form">
                @csrf
                @method('PATCH')
            </form>
            <div class="d-flex flex-wrap mt-3">
                <button type="submit" form="save-all-responses-form" class="btn btn-success mr-2 mb-2 mb-sm-0" id="save-all-responses-button">
                    <i class="fas fa-save mr-1"></i>Guardar todos los cambios
                    <span class="badge badge-light ml-1" id="unsaved-response-count">0</span>
                </button>
                <button type="submit" form="send-location-request-form" class="btn btn-primary mr-2 mb-2 mb-sm-0" id="send-location-request-button" @disabled($users->isEmpty())>
                    <i class="fas fa-paper-plane mr-1"></i>Enviar solicitud a seleccionados
                </button>
                <button type="submit" form="send-location-request-form" name="todos" value="1" class="btn btn-outline-primary" @disabled($eligibleUsers === 0)>
                    <i class="fas fa-users mr-1"></i>Enviar a todos sin provincia origen
                </button>
            </div>
        </div>

        <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 46px"><input type="checkbox" id="select-visible-users" aria-label="Seleccionar usuarios visibles"></th>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th>Municipio / provincia</th>
                            <th>Respuesta / solicitud</th>
                            <th>Guardar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $hasProvince = trim((string) $user->provincia_origen) !== '';
                                $responded = (bool) $user->empresa_ubicacion_respondida_at;
                                $canRequest = ! $hasProvince && ! $responded;
                                $pending = (bool) $user->empresa_ubicacion_solicitada_at && ! $responded;
                                $defaultResponseType = $hasProvince ? 'ubicacion' : ($responded ? 'capital' : 'sin_respuesta');
                                $oldUpdate = old('updates.'.$user->id);
                                $restoreInlineEdit = $errors->responseEdit->any() && (
                                    (string) old('user_id') === (string) $user->id || is_array($oldUpdate)
                                );
                                $responseType = $restoreInlineEdit
                                    ? old('updates.'.$user->id.'.respuesta_tipo', old('respuesta_tipo', $defaultResponseType))
                                    : $defaultResponseType;
                                $locationValue = $restoreInlineEdit
                                    ? old('updates.'.$user->id.'.ubicacion', old('ubicacion', ''))
                                    : $user->provincia_origen;
                                $editFormId = 'edit-response-form-'.$user->id;
                            @endphp
                            <tr data-user-id="{{ $user->id }}">
                                <td class="text-center align-middle">
                                    <input type="checkbox" form="send-location-request-form" name="user_ids[]" value="{{ $user->id }}" class="user-select"
                                        aria-label="Seleccionar {{ $user->name }}" @disabled(! $canRequest)
                                        title="{{ $hasProvince ? 'Ya tiene provincia origen registrada' : ($responded ? 'Ya respondió la solicitud' : '') }}">
                                </td>
                                <td class="align-middle">
                                    <strong>{{ $user->name }}</strong><br>
                                    <small class="text-muted">{{ $user->alias }} · {{ $user->email }}</small>
                                </td>
                                <td class="align-middle">
                                    @if ($user->empresa)
                                        <strong>{{ $user->empresa->nombre }}</strong><br>
                                        <small class="text-muted">{{ $user->empresa->codigo_cliente }} · {{ $user->empresa->sigla }}</small>
                                    @else
                                        <span class="text-muted">Empresa no disponible</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <input type="text" class="form-control form-control-sm text-uppercase inline-location-input"
                                        form="{{ $editFormId }}" name="ubicacion" maxlength="255" autocomplete="off"
                                        value="{{ $locationValue }}" data-original-location="{{ $user->provincia_origen }}"
                                        aria-label="Municipio o provincia de {{ $user->name }}"
                                        placeholder="Municipio o provincia">
                                </td>
                                <td class="align-middle">
                                    <select class="form-control form-control-sm inline-response-type" form="{{ $editFormId }}"
                                        name="respuesta_tipo" required data-original-type="{{ $defaultResponseType }}"
                                        aria-label="Respuesta de {{ $user->name }}">
                                        <option value="sin_respuesta" @selected($responseType === 'sin_respuesta')>Sin respuesta</option>
                                        <option value="ubicacion" @selected($responseType === 'ubicacion')>Municipio / provincia</option>
                                        <option value="capital" @selected($responseType === 'capital')>Ciudad capital</option>
                                    </select>
                                    @if ($hasProvince)
                                        <span class="badge badge-info">Provincia registrada</span>
                                    @elseif ($responded)
                                        <span class="badge badge-success">Respondió</span>
                                        @if ($user->empresa_ubicacion_capital)
                                            <small class="d-block text-muted mt-1">Entidad en ciudad capital</small>
                                        @endif
                                    @elseif ($pending)
                                        <span class="badge badge-warning">Pendiente</span>
                                        <small class="d-block text-muted mt-1">Enviada {{ $user->empresa_ubicacion_solicitada_at->format('d/m/Y H:i') }}</small>
                                    @else
                                        <span class="badge badge-secondary">Sin solicitud</span>
                                    @endif
                                </td>
                                <td class="align-middle text-nowrap">
                                    <form method="POST" id="{{ $editFormId }}"
                                        action="{{ route('empresa.solicitud-llenado.update-response', array_merge(['user' => $user->id], request()->only('buscar', 'empresa_id', 'respuesta'))) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="fas fa-save mr-1"></i>Guardar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron usuarios de empresa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between">
            <small class="text-muted">
                Mostrando {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} de {{ number_format($users->total(), 0, ',', '.') }} usuarios
                (100 por página)
            </small>
            <div class="ml-auto">{{ $users->links() }}</div>
        </div>
    </div>

    <div class="request-confirmation" id="request-confirmation" aria-hidden="true">
        <div class="request-confirmation__dialog" role="dialog" aria-modal="true" aria-labelledby="request-confirmation-title" aria-describedby="request-confirmation-message" tabindex="-1">
            <button type="button" class="request-confirmation__close" data-confirmation-close aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
            <div class="request-confirmation__icon" id="request-confirmation-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <span class="request-confirmation__eyebrow" id="request-confirmation-eyebrow">Confirmar envío</span>
            <h2 class="request-confirmation__title" id="request-confirmation-title">¿Enviar solicitud?</h2>
            <p class="request-confirmation__message" id="request-confirmation-message"></p>
            <div class="request-confirmation__count" id="request-confirmation-count">
                <strong id="request-confirmation-count-number">0</strong>
                <span id="request-confirmation-count-label">usuarios recibirán la solicitud</span>
            </div>
            <div class="request-confirmation__actions">
                <button type="button" class="btn request-confirmation__cancel" data-confirmation-close>Cancelar</button>
                <button type="button" class="btn request-confirmation__submit" id="request-confirmation-submit">
                    <i class="fas fa-paper-plane mr-2"></i>Sí, enviar solicitud
                </button>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        body.request-confirmation-open { overflow: hidden; }

        .request-confirmation {
            position: fixed;
            inset: 0;
            z-index: 2500;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(13, 31, 55, .62);
            backdrop-filter: blur(5px);
            opacity: 0;
            visibility: hidden;
            transition: opacity .22s ease, visibility .22s ease;
        }

        .request-confirmation.is-open {
            opacity: 1;
            visibility: visible;
        }

        .request-confirmation__dialog {
            position: relative;
            width: min(100%, 480px);
            padding: 34px 32px 28px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .78);
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(7, 25, 49, .32);
            text-align: center;
            transform: translateY(18px) scale(.96);
            transition: transform .28s cubic-bezier(.2, .8, .2, 1);
        }

        .request-confirmation.is-open .request-confirmation__dialog {
            transform: translateY(0) scale(1);
        }

        .request-confirmation__dialog::before {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 6px;
            background: linear-gradient(90deg, #ffc928, #16a6bd, #20539a);
            content: '';
        }

        .request-confirmation__close {
            position: absolute;
            top: 14px;
            right: 14px;
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 0;
            border-radius: 50%;
            background: #f1f4f8;
            color: #64748b;
            transition: background .18s ease, color .18s ease, transform .18s ease;
        }

        .request-confirmation__close:hover {
            background: #e5ebf2;
            color: #173f70;
            transform: rotate(90deg);
        }

        .request-confirmation__icon {
            display: grid;
            width: 68px;
            height: 68px;
            margin: 0 auto 16px;
            place-items: center;
            border-radius: 21px;
            background: linear-gradient(145deg, #e9f3ff, #f2f8ff);
            color: #20539a;
            font-size: 25px;
            animation: request-icon-float 2.4s ease-in-out infinite;
        }

        .request-confirmation__eyebrow {
            color: #16899d;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .request-confirmation__title {
            margin: 7px 0 10px;
            color: #17385f;
            font-size: 1.55rem;
            font-weight: 800;
        }

        .request-confirmation__message {
            max-width: 370px;
            margin: 0 auto 18px;
            color: #627187;
            font-size: .96rem;
            line-height: 1.55;
        }

        .request-confirmation__count {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            padding: 10px 16px;
            border: 1px solid #dce8f5;
            border-radius: 14px;
            background: #f5f9fe;
            color: #52647b;
            text-align: left;
        }

        .request-confirmation__count strong {
            color: #20539a;
            font-size: 1.45rem;
            line-height: 1;
        }

        .request-confirmation__count span {
            max-width: 190px;
            font-size: .83rem;
            line-height: 1.3;
        }

        .request-confirmation__actions {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .request-confirmation__actions .btn {
            min-height: 44px;
            padding: 10px 17px;
            border-radius: 11px;
            font-weight: 700;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .request-confirmation__cancel {
            border: 1px solid #d8e0e9;
            background: #fff;
            color: #52647b;
        }

        .request-confirmation__submit {
            border: 0;
            background: linear-gradient(135deg, #20539a, #176f9d);
            box-shadow: 0 8px 18px rgba(32, 83, 154, .22);
            color: #fff;
        }

        .request-confirmation__actions .btn:hover {
            transform: translateY(-2px);
        }

        .request-confirmation__submit:hover {
            box-shadow: 0 11px 22px rgba(32, 83, 154, .3);
            color: #fff;
        }

        .request-confirmation.is-empty .request-confirmation__icon {
            background: #fff6df;
            color: #d29000;
        }

        .request-confirmation.is-empty .request-confirmation__eyebrow {
            color: #b77b00;
        }

        @keyframes request-icon-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        @media (max-width: 575.98px) {
            .request-confirmation__dialog { padding: 32px 20px 22px; }
            .request-confirmation__actions { flex-direction: column-reverse; }
            .request-confirmation__actions .btn { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .request-confirmation *,
            .request-confirmation *::before,
            .request-confirmation *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const saveAllForm = document.getElementById('save-all-responses-form');
            const saveAllButton = document.getElementById('save-all-responses-button');
            const unsavedResponseCount = document.getElementById('unsaved-response-count');
            const responseRows = Array.from(document.querySelectorAll('tbody tr[data-user-id]'));

            const rowHasUnsavedChanges = (row) => {
                const responseType = row.querySelector('.inline-response-type');
                const locationInput = row.querySelector('.inline-location-input');
                if (!responseType || !locationInput) return false;

                const originalType = responseType.dataset.originalType || 'sin_respuesta';
                const originalLocation = (locationInput.dataset.originalLocation || '').trim();
                return responseType.value !== originalType
                    || (responseType.value === 'ubicacion' && locationInput.value.trim() !== originalLocation);
            };

            const changedResponseRows = () => responseRows.filter(rowHasUnsavedChanges);
            const refreshUnsavedResponseCount = () => {
                const count = changedResponseRows().length;
                if (unsavedResponseCount) unsavedResponseCount.textContent = String(count);
                if (saveAllButton) saveAllButton.disabled = count === 0;
            };

            document.querySelectorAll('.inline-response-type').forEach((select) => {
                const syncLocationField = () => {
                    const row = select.closest('tr');
                    const locationInput = row?.querySelector('.inline-location-input');
                    const needsLocation = select.value === 'ubicacion';
                    if (locationInput) {
                        locationInput.disabled = !needsLocation;
                        locationInput.required = needsLocation;
                    }
                };

                select.addEventListener('change', () => {
                    syncLocationField();
                    refreshUnsavedResponseCount();
                });
                syncLocationField();
            });

            document.querySelectorAll('.inline-location-input').forEach((input) => {
                input.addEventListener('input', refreshUnsavedResponseCount);
            });

            refreshUnsavedResponseCount();

            saveAllForm?.addEventListener('submit', function (event) {
                const changedRows = changedResponseRows();
                if (changedRows.length === 0) {
                    event.preventDefault();
                    return;
                }

                saveAllForm.querySelectorAll('.generated-response-field').forEach((field) => field.remove());
                changedRows.forEach((row) => {
                    const userId = row.dataset.userId;
                    const responseType = row.querySelector('.inline-response-type')?.value;
                    const location = row.querySelector('.inline-location-input')?.value ?? '';
                    const addHiddenField = (name, value) => {
                        const field = document.createElement('input');
                        field.type = 'hidden';
                        field.name = name;
                        field.value = value;
                        field.className = 'generated-response-field';
                        saveAllForm.appendChild(field);
                    };

                    addHiddenField(`updates[${userId}][respuesta_tipo]`, responseType);
                    if (responseType === 'ubicacion') {
                        addHiddenField(`updates[${userId}][ubicacion]`, location);
                    }
                });
            });

            const selectAll = document.getElementById('select-visible-users');
            const checkboxes = Array.from(document.querySelectorAll('.user-select:not(:disabled)'));
            const form = document.getElementById('send-location-request-form');
            const modal = document.getElementById('request-confirmation');
            const modalDialog = modal?.querySelector('.request-confirmation__dialog');
            const title = document.getElementById('request-confirmation-title');
            const message = document.getElementById('request-confirmation-message');
            const count = document.getElementById('request-confirmation-count');
            const countNumber = document.getElementById('request-confirmation-count-number');
            const countLabel = document.getElementById('request-confirmation-count-label');
            const eyebrow = document.getElementById('request-confirmation-eyebrow');
            const icon = document.getElementById('request-confirmation-icon');
            const confirmButton = document.getElementById('request-confirmation-submit');
            let pendingSubmitter = null;
            let previouslyFocused = null;

            selectAll?.addEventListener('change', function () {
                checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    if (selectAll) {
                        selectAll.checked = checkboxes.length > 0 && checkboxes.every((item) => item.checked);
                    }
                });
            });

            const closeModal = () => {
                modal?.classList.remove('is-open', 'is-empty');
                modal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('request-confirmation-open');
                previouslyFocused?.focus();
            };

            const openModal = ({ empty = false, isAll = false, selected = 0 } = {}) => {
                if (!modal) return;

                previouslyFocused = document.activeElement;
                modal.classList.toggle('is-empty', empty);
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('request-confirmation-open');

                if (empty) {
                    pendingSubmitter = null;
                    eyebrow.textContent = 'Falta seleccionar';
                    title.textContent = 'Selecciona usuarios';
                    message.textContent = 'Marca al menos un usuario de empresa en la tabla para enviarle la solicitud.';
                    count.hidden = true;
                    icon.innerHTML = '<i class="fas fa-hand-pointer"></i>';
                    confirmButton.hidden = true;
                } else {
                    const recipients = isAll ? {{ (int) $eligibleUsers }} : selected;
                    eyebrow.textContent = 'Confirmar envío';
                    title.textContent = isAll ? '¿Enviar a todos?' : '¿Enviar solicitud?';
                    message.textContent = isAll
                        ? 'La solicitud aparecerá cuando estos usuarios inicien sesión. Solo se incluirá a quienes aún no tienen provincia origen.'
                        : 'La solicitud aparecerá cuando los usuarios seleccionados inicien sesión y tengan pendiente indicar su municipio o provincia.';
                    countNumber.textContent = recipients.toLocaleString('es-BO');
                    countLabel.textContent = isAll
                        ? 'usuarios sin provincia origen recibirán la solicitud'
                        : 'usuarios seleccionados recibirán la solicitud';
                    count.hidden = false;
                    icon.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    confirmButton.hidden = false;
                }

                window.setTimeout(() => modalDialog?.focus(), 30);
            };

            modal?.addEventListener('click', function (event) {
                if (event.target === modal || event.target.closest('[data-confirmation-close]')) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (! modal?.classList.contains('is-open')) return;

                if (event.key === 'Escape') {
                    closeModal();
                    return;
                }

                if (event.key === 'Tab') {
                    const focusable = Array.from(modal.querySelectorAll('button:not([hidden]):not([disabled])'));
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (event.shiftKey && (document.activeElement === first || document.activeElement === modalDialog)) {
                        event.preventDefault();
                        last?.focus();
                    } else if (! event.shiftKey && (document.activeElement === last || document.activeElement === modalDialog)) {
                        event.preventDefault();
                        first?.focus();
                    }
                }
            });

            form?.addEventListener('submit', function (event) {
                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;
                    return;
                }

                const sendToAll = event.submitter?.name === 'todos';
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                event.preventDefault();

                if (sendToAll) {
                    pendingSubmitter = event.submitter;
                    openModal({ isAll: true });
                } else if (selected === 0) {
                    openModal({ empty: true });
                } else {
                    pendingSubmitter = event.submitter;
                    openModal({ selected });
                }
            });

            confirmButton?.addEventListener('click', function () {
                if (!form || !pendingSubmitter) return;

                form.dataset.confirmed = 'true';
                closeModal();
                form.requestSubmit(pendingSubmitter);
            });
        });
    </script>
@stop
