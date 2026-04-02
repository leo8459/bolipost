@php
    $selectedPermissions = collect(old('permissions', $selectedPermissions ?? []))
        ->map(fn ($permission) => (string) $permission)
        ->all();
@endphp

<div class="card card-outline card-warning">
    <div class="card-body">
        <div class="form-group">
            <label for="name">Nombre del rol de cliente</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $role->name ?? '') }}"
                class="form-control @error('name') is-invalid @enderror"
                placeholder="Ej: cliente_vip"
                required
            >
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="alert alert-warning">
            Este modulo controla solo vistas y acciones del portal cliente. No mezcla permisos del personal interno.
        </div>

        @foreach ($permissionGroups as $group)
            <div class="card card-outline card-secondary mb-3">
                <div class="card-header">
                    <strong>{{ $group['module_label'] }}</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach ($group['permissions'] as $permission)
                            <div class="col-md-6">
                                <div class="custom-control custom-checkbox mb-3">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="permission_{{ \Illuminate\Support\Str::slug($permission['name'], '_') }}"
                                        name="permissions[]"
                                        value="{{ $permission['name'] }}"
                                        {{ in_array($permission['name'], $selectedPermissions, true) ? 'checked' : '' }}
                                    >
                                    <label
                                        class="custom-control-label"
                                        for="permission_{{ \Illuminate\Support\Str::slug($permission['name'], '_') }}"
                                    >
                                        {{ $permission['action_label'] }}
                                        <small class="d-block text-muted">{{ $permission['name'] }}</small>
                                        @if (! empty($permission['hint']))
                                            <small class="d-block text-primary">{{ $permission['hint'] }}</small>
                                        @endif
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="card-footer d-flex justify-content-end">
        <button type="submit" class="btn btn-warning">Guardar rol cliente</button>
    </div>
</div>
