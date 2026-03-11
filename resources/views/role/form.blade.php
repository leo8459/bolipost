@php
    $selectedPermissions = collect(old('permissions', $selectedPermissions ?? []))
        ->map(fn ($permission) => (string) $permission)
        ->all();
@endphp

<div class="box box-info padding-1">
    <div class="box-body">
        <style>
            .permission-module {
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                background: #fff;
                overflow: hidden;
            }

            .permission-module-header {
                padding: 0.75rem 1rem;
                background: #f8f9fa;
                border-bottom: 1px solid #e9ecef;
            }

            .permission-module-title {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .permission-collapse-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                border-radius: 999px;
                border: 1px solid #ced4da;
                background: #fff;
                color: #495057;
                font-weight: 600;
                padding: 0.2rem 0.65rem;
                line-height: 1.2;
                transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
            }

            .permission-collapse-btn::before {
                content: "\25B8";
                font-size: 0.75rem;
                transition: transform 0.15s ease;
            }

            .permission-collapse-btn[aria-expanded="true"]::before {
                transform: rotate(90deg);
            }

            .permission-collapse-btn:hover {
                background: #f1f3f5;
                border-color: #adb5bd;
            }

            .permission-collapse-btn:focus {
                outline: none;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.2);
            }

            .permission-items .list-group-item {
                padding: 0.65rem 1rem;
            }

            .permission-save-bar {
                position: sticky;
                bottom: 1rem;
                z-index: 20;
            }

            .permission-save-inner {
                display: flex;
                justify-content: flex-end;
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                padding: 0.75rem 1rem;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            }

            @media (max-width: 575.98px) {
                .permission-save-inner {
                    justify-content: stretch;
                }

                .permission-save-inner .btn {
                    width: 100%;
                }
            }
        </style>

        <div class="form-group mb-3">
            <label for="name">Nombre del rol</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $role->name ?? '') }}"
                class="form-control @error('name') is-invalid @enderror"
                placeholder="Ej: administrador"
                required
            >
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4">
            <label for="guard_name">Guard</label>
            <input
                type="text"
                id="guard_name"
                value="web"
                class="form-control"
                readonly
                disabled
            >
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
            <h2 class="h5 mb-0">Permisos por modulo y accion</h2>
            <div class="d-flex align-items-center" style="gap: .5rem;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleAllLists">Abrir listas</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="toggleAllPermissions">Marcar todo</button>
            </div>
        </div>

        <p class="text-muted mb-3">
            Cada checkbox controla una ventana o accion concreta. Si desmarcas un permiso, se bloquea la ruta asociada.
        </p>

        @foreach ($permissionGroups as $group)
            @php
                $moduleId = 'module_' . \Illuminate\Support\Str::slug($group['module_key'], '_');
                $moduleBodyId = $moduleId . '_body';
            @endphp
            <section class="permission-module mb-3">
                <div class="permission-module-header d-flex justify-content-between align-items-center">
                    <div class="permission-module-title">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm permission-collapse-btn js-module-collapse"
                            data-target="{{ $moduleBodyId }}"
                            data-module="{{ $group['module_key'] }}"
                            aria-expanded="false"
                        >
                            Ver permisos
                        </button>
                        <strong>{{ $group['module_label'] }}</strong>
                    </div>
                    <div class="custom-control custom-checkbox m-0">
                        <input
                            type="checkbox"
                            class="custom-control-input js-module-toggle"
                            id="{{ $moduleId }}"
                            data-module="{{ $group['module_key'] }}"
                        >
                        <label class="custom-control-label" for="{{ $moduleId }}">Todo el modulo</label>
                    </div>
                </div>
                <div id="{{ $moduleBodyId }}" class="d-none js-module-body">
                    <ul class="list-group list-group-flush permission-items">
                        @foreach ($group['permissions'] as $permission)
                            @php
                                $permissionId = 'permission_' . \Illuminate\Support\Str::slug($permission['name'], '_');
                                $checked = in_array($permission['name'], $selectedPermissions, true);
                            @endphp
                            <li class="list-group-item">
                                <div class="custom-control custom-checkbox">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission['name'] }}"
                                        id="{{ $permissionId }}"
                                        class="custom-control-input js-permission-item"
                                        data-module="{{ $group['module_key'] }}"
                                        {{ $checked ? 'checked' : '' }}
                                    >
                                    <label class="custom-control-label" for="{{ $permissionId }}">
                                        {{ $permission['action_label'] }}
                                        <span class="badge badge-light border ml-1">{{ $permission['type_label'] ?? 'Permiso' }}</span>
                                        <small class="d-block text-muted">{{ $permission['name'] }}</small>
                                    </label>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endforeach

        @error('permissions')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
        @error('permissions.*')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
    </div>

    <div class="box-footer mt-3 permission-save-bar">
        <div class="permission-save-inner">
            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const moduleToggles = Array.from(document.querySelectorAll('.js-module-toggle'));
        const permissionItems = Array.from(document.querySelectorAll('.js-permission-item'));
        const globalToggle = document.getElementById('toggleAllPermissions');
        const listToggle = document.getElementById('toggleAllLists');
        const moduleCollapseButtons = Array.from(document.querySelectorAll('.js-module-collapse'));

        const getModuleItems = (moduleKey) => permissionItems.filter((item) => item.dataset.module === moduleKey);
        const getModuleBody = (targetId) => document.getElementById(targetId);
        const moduleHasCheckedItems = (moduleKey) => getModuleItems(moduleKey).some((item) => item.checked);

        const setModuleOpen = (button, isOpen) => {
            const targetId = button.dataset.target;
            const body = getModuleBody(targetId);
            if (!body) {
                return;
            }

            body.classList.toggle('d-none', !isOpen);
            button.textContent = isOpen ? 'Ocultar' : 'Ver permisos';
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        const refreshModuleToggle = (moduleToggle) => {
            const moduleItems = getModuleItems(moduleToggle.dataset.module);
            const allChecked = moduleItems.length > 0 && moduleItems.every((item) => item.checked);
            moduleToggle.checked = allChecked;
        };

        const refreshGlobalToggle = () => {
            if (!globalToggle) {
                return;
            }

            const allChecked = permissionItems.length > 0 && permissionItems.every((item) => item.checked);
            globalToggle.textContent = allChecked ? 'Desmarcar todo' : 'Marcar todo';
            globalToggle.dataset.checked = allChecked ? '1' : '0';
        };

        const refreshListToggle = () => {
            if (!listToggle || moduleCollapseButtons.length === 0) {
                return;
            }

            const allOpen = moduleCollapseButtons.every((button) => button.getAttribute('aria-expanded') === 'true');
            listToggle.textContent = allOpen ? 'Cerrar listas' : 'Abrir listas';
            listToggle.dataset.open = allOpen ? '1' : '0';
        };

        moduleToggles.forEach((moduleToggle) => {
            refreshModuleToggle(moduleToggle);

            moduleToggle.addEventListener('change', function () {
                const moduleItems = getModuleItems(moduleToggle.dataset.module);
                moduleItems.forEach((item) => {
                    item.checked = moduleToggle.checked;
                });
                refreshGlobalToggle();
            });
        });

        permissionItems.forEach((permissionItem) => {
            permissionItem.addEventListener('change', function () {
                const moduleToggle = moduleToggles.find((toggle) => toggle.dataset.module === permissionItem.dataset.module);
                if (moduleToggle) {
                    refreshModuleToggle(moduleToggle);
                }
                refreshGlobalToggle();
            });
        });

        if (globalToggle) {
            globalToggle.addEventListener('click', function () {
                const shouldCheck = globalToggle.dataset.checked !== '1';

                permissionItems.forEach((item) => {
                    item.checked = shouldCheck;
                });

                moduleToggles.forEach((toggle) => {
                    toggle.checked = shouldCheck;
                });

                refreshGlobalToggle();
            });
        }

        moduleCollapseButtons.forEach((button) => {
            const shouldOpenByDefault = moduleHasCheckedItems(button.dataset.module);
            setModuleOpen(button, shouldOpenByDefault);

            button.addEventListener('click', function () {
                const isOpen = button.getAttribute('aria-expanded') === 'true';
                setModuleOpen(button, !isOpen);
                refreshListToggle();
            });
        });

        if (listToggle) {
            listToggle.addEventListener('click', function () {
                const shouldOpen = listToggle.dataset.open !== '1';
                moduleCollapseButtons.forEach((button) => {
                    setModuleOpen(button, shouldOpen);
                });
                refreshListToggle();
            });
        }

        refreshGlobalToggle();
        refreshListToggle();
    });
</script>
