@php
    $selectedPermissions = collect(old('permissions', $selectedPermissions ?? []))
        ->map(fn ($permission) => (string) $permission)
        ->all();
@endphp

<div class="box box-info padding-1">
    <div class="box-body">
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

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">Permisos por modulo y accion</h2>
            <button type="button" class="btn btn-outline-primary btn-sm" id="toggleAllPermissions">Marcar todo</button>
        </div>

        <p class="text-muted mb-3">
            Cada checkbox controla una ventana o accion concreta. Si desmarcas un permiso, se bloquea la ruta asociada.
        </p>

        @foreach ($permissionGroups as $group)
            @php
                $moduleId = 'module_' . \Illuminate\Support\Str::slug($group['module_key'], '_');
            @endphp
            <div class="card card-outline card-primary mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>{{ $group['module_label'] }}</strong>
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
                <div class="card-body">
                    <div class="row">
                        @foreach ($group['permissions'] as $permission)
                            @php
                                $permissionId = 'permission_' . \Illuminate\Support\Str::slug($permission['name'], '_');
                                $checked = in_array($permission['name'], $selectedPermissions, true);
                            @endphp
                            <div class="col-12 col-md-6 col-lg-4 mb-2">
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
                                        <small class="d-block text-muted">{{ $permission['name'] }}</small>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        @error('permissions')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
        @error('permissions.*')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
    </div>

    <div class="box-footer mt20">
        <div class="text-right">
            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const moduleToggles = Array.from(document.querySelectorAll('.js-module-toggle'));
        const permissionItems = Array.from(document.querySelectorAll('.js-permission-item'));
        const globalToggle = document.getElementById('toggleAllPermissions');

        const getModuleItems = (moduleKey) => permissionItems.filter((item) => item.dataset.module === moduleKey);

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

        refreshGlobalToggle();
    });
</script>
