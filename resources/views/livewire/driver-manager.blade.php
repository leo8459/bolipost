<div>
    <style>
        .profile-card {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
        }
        .profile-card .card-header {
            background: linear-gradient(120deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff;
            border: 0;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .profile-field {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fff;
        }
        .profile-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 2px;
            letter-spacing: 0.04em;
        }
        .profile-value {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }
        .memorandum-viewer {
            width: 100%;
            min-height: 70vh;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #fff;
        }
        .memorandum-image {
            display: block;
            max-width: 100%;
            max-height: 70vh;
            margin: 0 auto;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            background: #fff;
        }
        .driver-form-field {
            border-radius: 10px;
            min-height: calc(2.35rem + 2px);
            border: 1px solid #ced4da;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        .driver-form-field:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }
        select.driver-form-field {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.2rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16'%3E%3Cpath fill='%236c757d' d='M2.646 5.646a.5.5 0 0 1 .708 0L8 10.293l4.646-4.647a.5.5 0 0 1 .708.708l-5 5a.5.5 0 0 1-.708 0l-5-5a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            background-size: 14px;
        }
        .bp-switch {
            display: flex;
            align-items: center;
            gap: .55rem;
        }
        .bp-switch .form-check-input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 42px;
            min-width: 42px;
            height: 24px;
            margin-top: 0;
            border-radius: 999px;
            border: 2px solid #c8d2e1;
            background: #eef3f9;
            position: relative;
            cursor: pointer;
            transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
            box-shadow: none;
        }
        .bp-switch .form-check-input[type="checkbox"]::after {
            content: "";
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(22, 40, 74, .25);
            transition: transform .18s ease;
        }
        .bp-switch .form-check-input[type="checkbox"]:checked {
            background: #1e88ff;
            border-color: #1e88ff;
        }
        .bp-switch .form-check-input[type="checkbox"]:checked::after {
            transform: translateX(18px);
        }
        .bp-switch .form-check-input[type="checkbox"]:focus {
            border-color: #9ec5fe;
            box-shadow: 0 0 0 .18rem rgba(13, 110, 253, .18);
            outline: none;
        }
        .bp-switch .form-check-label {
            margin-bottom: 0;
        }
    </style>

    <div class="page-title mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-users me-2 text-primary"></i>{{ auth()->user()?->role === 'conductor' ? 'Mi Perfil de Conductor' : 'Gestion de Conductores' }}
        </h1>
        @if(!$showForm && auth()->user()?->role !== 'conductor')
            <button type="button" wire:click="create" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nuevo Conductor
            </button>
        @endif
    </div>

    @if (session('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(auth()->user()?->role === 'conductor' && !$showForm)
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card profile-card shadow-sm h-100">
                    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-id-card me-2"></i>Mi Perfil de Conductor</span>
                        <span class="badge bg-light text-primary px-3 py-2">
                            {{ ($driverProfile?->activo ?? false) ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="profile-grid">
                            <div class="profile-field">
                                <span class="profile-label">Nombre</span>
                                <span class="profile-value">{{ $driverProfile?->nombre ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Email</span>
                                <span class="profile-value">{{ $driverProfile?->email ?? $driverProfile?->user?->email ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Telefono</span>
                                <span class="profile-value">{{ $driverProfile?->telefono ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Licencia</span>
                                <span class="profile-value">{{ $driverProfile?->licencia ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Tipo Licencia</span>
                                <span class="profile-value">{{ $driverProfile?->tipo_licencia ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Vencimiento</span>
                                <span class="profile-value">{{ optional($driverProfile?->fecha_vencimiento_licencia)->format('d/m/Y') ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Memorandum</span>
                                <span class="profile-value">
                                    @if($driverProfile?->memorandum_path)
                                        @php
                                            $memoExt = strtolower(pathinfo((string) $driverProfile->memorandum_path, PATHINFO_EXTENSION));
                                            $memoKind = $memoExt === 'pdf' ? 'pdf' : 'image';
                                        @endphp
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary driver-view-memorandum-btn"
                                            data-url="{{ route('drivers.memorandum.download', $driverProfile->id) }}"
                                            data-kind="{{ $memoKind }}"
                                            data-name="Memorandum de {{ $driverProfile->nombre }}"
                                        >
                                            Ver memorandum
                                        </button>
                                    @else
                                        -
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showForm)
        <div class="bp-gestiones-form-overlay">
        <div class="card shadow-sm mb-4 bp-gestiones-form-card">
            <div class="card-header">
                {{ $isEdit ? 'Editar Conductor' : 'Nuevo Conductor' }}
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="nombre" class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre" wire:model="nombre" class="form-control driver-form-field @error('nombre') is-invalid @enderror" required>
                            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="user_id" class="form-label fw-bold">Usuario <span class="text-danger">*</span></label>
                            <select id="user_id" wire:model="user_id" class="form-control driver-form-field @error('user_id') is-invalid @enderror" required>
                                <option value="">Seleccionar usuario</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="licencia" class="form-label fw-bold">Licencia <span class="text-danger">*</span></label>
                            <input type="text" id="licencia" wire:model.live="licencia" class="form-control driver-form-field @error('licencia') is-invalid @enderror" maxlength="50" required>
                            @error('licencia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="tipo_licencia" class="form-label fw-bold">Tipo Licencia <span class="text-danger">*</span></label>
                            <select id="tipo_licencia" wire:model="tipo_licencia" class="form-control driver-form-field @error('tipo_licencia') is-invalid @enderror" required>
                                <option value="">Seleccionar tipo</option>
                                @foreach ($licenseTypes as $licenseType)
                                    <option value="{{ $licenseType }}">{{ $licenseType }}</option>
                                @endforeach
                            </select>
                            @error('tipo_licencia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="fecha_vencimiento_licencia" class="form-label fw-bold">Vencimiento Licencia <span class="text-danger">*</span></label>
                            <input type="date" id="fecha_vencimiento_licencia" wire:model="fecha_vencimiento_licencia" class="form-control driver-form-field @error('fecha_vencimiento_licencia') is-invalid @enderror" required min="{{ now()->toDateString() }}">
                            @error('fecha_vencimiento_licencia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="telefono" class="form-label fw-bold">Telefono <span class="text-danger">*</span></label>
                            <input type="text" id="telefono" wire:model.live="telefono" class="form-control driver-form-field @error('telefono') is-invalid @enderror" inputmode="numeric" maxlength="20" required>
                            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="email" class="form-label fw-bold">Email institucional <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" id="email" wire:model.live="email" class="form-control driver-form-field @error('email') is-invalid @enderror" maxlength="100" autocomplete="off" placeholder="usuario" required>
                                <span class="input-group-text">@correos.gob.bo</span>
                                @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">Solo escribe la primera parte del correo. El dominio se agrega automaticamente.</div>
                        </div>
                        <div class="col-12 col-md-8">
                            <label for="memorandum_file" class="form-label fw-bold">Memorandum (imagen o PDF) <span class="text-danger">*</span></label>
                            <input type="file" id="memorandum_file" wire:model="memorandum_file" class="form-control driver-form-field @error('memorandum_file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp" @if(!$memorandum_path) required @endif>
                            <div class="form-text">Tamano maximo: 10 MB.</div>
                            <div class="form-text text-primary" wire:loading wire:target="memorandum_file">
                                Subiendo archivo, espere un momento antes de guardar...
                            </div>
                            @error('memorandum_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Se aceptan PDF o imagenes. El limite final depende de la configuracion del servidor.</div>
                            @if($memorandum_path)
                                <div class="mt-2">
                                    <label class="form-label fw-bold mb-1">Ruta almacenada (BD)</label>
                                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $memorandum_path }}" readonly>
                                </div>
                                <div class="form-text mt-1">
                                    Archivo actual:
                                    @if($editingDriverId)
                                        @php
                                            $editMemoExt = strtolower(pathinfo((string) $memorandum_path, PATHINFO_EXTENSION));
                                            $editMemoKind = $editMemoExt === 'pdf' ? 'pdf' : 'image';
                                        @endphp
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary driver-view-memorandum-btn"
                                            data-url="{{ route('drivers.memorandum.download', $editingDriverId) }}"
                                            data-kind="{{ $editMemoKind }}"
                                            data-name="Memorandum actual"
                                        >
                                            abrir memorandum
                                        </button>
                                    @else
                                        <span class="text-muted">guarda primero para habilitar apertura</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="col-12 col-md-4 d-flex align-items-center">
                            <div class="form-check bp-switch mt-md-4">
                                <input class="form-check-input" type="checkbox" id="activo" wire:model="activo">
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled" wire:target="memorandum_file,save">
                            <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}
                        </button>
                        <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    @elseif(auth()->user()?->role !== 'conductor')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                </div>
                @if($drivers->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Licencia</th>
                                    <th>Memorandum</th>
                                    <th>Telefono</th>
                                    <th>Estado</th>
                                    @if(auth()->user()?->role !== 'conductor')
                                        <th class="text-center">Acciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($drivers as $driver)
                                    <tr>
                                        <td>{{ $driver->nombre }}</td>
                                        <td>{{ $driver->licencia }}</td>
                                        <td>
                                            @if($driver->memorandum_path)
                                                @php
                                                    $rowMemoExt = strtolower(pathinfo((string) $driver->memorandum_path, PATHINFO_EXTENSION));
                                                    $rowMemoKind = $rowMemoExt === 'pdf' ? 'pdf' : 'image';
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary driver-view-memorandum-btn"
                                                    data-url="{{ route('drivers.memorandum.download', $driver->id) }}"
                                                    data-kind="{{ $rowMemoKind }}"
                                                    data-name="Memorandum de {{ $driver->nombre }}"
                                                >
                                                    Ver memorandum
                                                </button>
                                            @else
                                                <span class="text-muted">Sin archivo</span>
                                            @endif
                                        </td>
                                        <td>{{ $driver->telefono }}</td>
                                        <td>
                                            <span class="badge {{ $driver->activo ? 'bg-success' : 'bg-danger' }}">
                                                {{ $driver->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        @if(auth()->user()?->role !== 'conductor')
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button wire:click="edit({{ $driver->id }})" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button wire:click="delete({{ $driver->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                        <h5>Sin conductores registrados</h5>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-4">
            {{ $drivers->links() }}
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-muted">
                Esta vista muestra solo su perfil personal de conductor.
            </div>
        </div>
    @endif

    <div class="modal fade" id="driverMemorandumModal" tabindex="-1" aria-labelledby="driverMemorandumLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="driverMemorandumLabel">Memorandum</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <img id="driver-memorandum-image" class="memorandum-image d-none" alt="Memorandum">
                    <iframe id="driver-memorandum-frame" class="memorandum-viewer d-none" title="Memorandum"></iframe>
                    <div id="driver-memorandum-empty" class="text-muted text-center py-4">No se pudo cargar el memorandum.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            if (window.__driverMemorandumModalInitialized) return;
            window.__driverMemorandumModalInitialized = true;
            const memorandumMaxBytes = 10 * 1024 * 1024;

            function getModalInstance(el) {
                if (!el) return null;

                if (window.bootstrap && window.bootstrap.Modal) {
                    if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                        return window.bootstrap.Modal.getOrCreateInstance(el);
                    }
                    if (typeof window.bootstrap.Modal.getInstance === 'function') {
                        return window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                    }
                    return new window.bootstrap.Modal(el);
                }

                if (window.jQuery) {
                    return {
                        show: () => window.jQuery(el).modal('show'),
                        hide: () => window.jQuery(el).modal('hide'),
                    };
                }

                return null;
            }

            const modalEl = document.getElementById('driverMemorandumModal');
            if (!modalEl) return;
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }

            const titleEl = document.getElementById('driverMemorandumLabel');
            const imageEl = document.getElementById('driver-memorandum-image');
            const frameEl = document.getElementById('driver-memorandum-frame');
            const emptyEl = document.getElementById('driver-memorandum-empty');

            document.addEventListener('click', function (event) {
                const btn = event.target.closest('.driver-view-memorandum-btn');
                if (!btn) return;

                const url = btn.getAttribute('data-url') || '';
                const kind = btn.getAttribute('data-kind') || 'image';
                const name = btn.getAttribute('data-name') || 'Memorandum';

                if (titleEl) {
                    titleEl.textContent = name;
                }

                imageEl?.classList.add('d-none');
                frameEl?.classList.add('d-none');
                emptyEl?.classList.add('d-none');
                imageEl?.removeAttribute('src');
                frameEl?.removeAttribute('src');

                if (!url) {
                    emptyEl?.classList.remove('d-none');
                } else if (kind === 'pdf') {
                    if (frameEl) {
                        frameEl.src = url;
                        frameEl.classList.remove('d-none');
                    }
                } else {
                    if (imageEl) {
                        imageEl.src = url;
                        imageEl.classList.remove('d-none');
                    }
                }

                const modal = getModalInstance(modalEl);
                if (modal) modal.show();
            });

            document.addEventListener('change', function (event) {
                const input = event.target;
                if (!(input instanceof HTMLInputElement) || input.id !== 'memorandum_file') {
                    return;
                }

                const file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    return;
                }

                if (file.size > memorandumMaxBytes) {
                    input.value = '';
                    input.setCustomValidity('El memorandum no debe superar 10 MB.');
                    input.reportValidity();
                    input.setCustomValidity('');
                }
            });
        })();
    </script>
</div>
