<div class="box box-info padding-1">
    <div class="box-body">

        {{-- Nombre --}}
        <div class="form-group mb-3">
            <label for="name">Nombre Completo</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $user->name ?? '') }}"
                class="form-control @error('name') is-invalid @enderror"
                placeholder="Nombre Completo"
            >
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Email --}}
        <div class="form-group mb-3">
            <label for="alias">Alias de Acceso *</label>
            <input
                type="text"
                id="alias"
                name="alias"
                value="{{ old('alias', $user->alias ?? '') }}"
                class="form-control @error('alias') is-invalid @enderror"
                placeholder="Ej: juan.perez+lp"
                required
            >
            @error('alias')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">Se permiten caracteres especiales. Evita solo duplicados.</small>
        </div>

        {{-- Email --}}
        <div class="form-group mb-3">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $user->email ?? '') }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="Email"
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="form-group mb-3">
            <label for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                placeholder="Contraseña"
            >
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            {{-- Tip para edición --}}
            @if(!empty($user?->id))
                <small class="text-muted">Deja en blanco si no quieres cambiar la contraseña.</small>
            @endif
        </div>

        {{-- Regional (ciudad) --}}
        <div class="form-group mb-3">
            <label for="ciudad">Regional</label>
            <select
                id="ciudad"
                name="ciudad"
                class="form-control @error('ciudad') is-invalid @enderror"
            >
                <option value="" disabled {{ old('ciudad', $user->ciudad ?? '') === '' ? 'selected' : '' }}>
                    Seleccione la Regional
                </option>

                @php
                    $regionales = ['LA PAZ','COCHABAMBA','SANTA CRUZ','ORURO','POTOSI','TARIJA','SUCRE','TRINIDAD','COBIJA'];
                    $selectedRegional = old('ciudad', $user->ciudad ?? '');
                @endphp

                @foreach($regionales as $r)
                    <option value="{{ $r }}" {{ $selectedRegional === $r ? 'selected' : '' }}>
                        {{ $r }}
                    </option>
                @endforeach
            </select>

            @error('ciudad')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- CI --}}
        <div class="form-group mb-3">
            <label for="ci">Carnet de Identidad</label>
            <input
                type="text"
                id="ci"
                name="ci"
                value="{{ old('ci', $user->ci ?? '') }}"
                class="form-control @error('ci') is-invalid @enderror"
                placeholder="Carnet de Identidad"
            >
            @error('ci')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Empresa --}}
        @php
            $empresaSeleccionada = filter_var(old('empresa_id', $user->empresa_id ?? ''), FILTER_VALIDATE_INT);
            $sucursalSeleccionada = filter_var(old('empresa_sucursal_id', $user->empresa_sucursal_id ?? ''), FILTER_VALIDATE_INT);
            $opcionesSucursal = $sucursalesEmpresa[$empresaSeleccionada] ?? [];
        @endphp
        <div class="form-group mb-3">
            <label for="empresa_id">Empresa *</label>
            <select
                id="empresa_id"
                name="empresa_id"
                class="form-control @error('empresa_id') is-invalid @enderror"
                required
            >
                <option value="">Selecciona una empresa</option>
                @foreach(($empresas ?? collect()) as $empresa)
                    <option
                        value="{{ $empresa->id }}"
                        @selected($empresaSeleccionada === (int) $empresa->id)
                    >
                        {{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})
                    </option>
                @endforeach
            </select>
            @error('empresa_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-3">
            <label for="empresa_sucursal_id">Sucursal de empresa *</label>
            <select id="empresa_sucursal_id" name="empresa_sucursal_id"
                class="form-control @error('empresa_sucursal_id') is-invalid @enderror" required
                @disabled(! $empresaSeleccionada)>
                <option value="">{{ $empresaSeleccionada ? 'Selecciona una sucursal' : 'Selecciona una empresa primero' }}</option>
                @foreach($opcionesSucursal as $opcion)
                    <option value="{{ $opcion['id'] }}" @selected($sucursalSeleccionada === (int) $opcion['id'])>{{ $opcion['label'] }}</option>
                @endforeach
            </select>
            <small id="empresa_sucursal_ayuda" class="text-muted" @if(! $empresaSeleccionada || $opcionesSucursal) hidden @endif>
                Esta empresa aún no tiene sucursales registradas.
            </small>
            @error('empresa_sucursal_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-3">
            <label for="sucursal_id">Sucursal de Facturación (opcional)</label>
            <select
                id="sucursal_id"
                name="sucursal_id"
                class="form-control @error('sucursal_id') is-invalid @enderror"
            >
                <option value="">Sin sucursal</option>
                @foreach(($sucursales ?? collect()) as $sucursal)
                    <option
                        value="{{ $sucursal->id }}"
                        {{ (string) old('sucursal_id', $user->sucursal_id ?? '') === (string) $sucursal->id ? 'selected' : '' }}
                    >
                        Sucursal {{ $sucursal->codigoSucursal }} - Punto {{ $sucursal->puntoVenta }} - {{ $sucursal->municipio }}
                    </option>
                @endforeach
            </select>
            @error('sucursal_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">Este campo es independiente de la regional/ciudad.</small>
        </div>

        {{-- Roles --}}
        <h2 class="h5 mt-4">Listado de Roles</h2>

        @php
            // Para que funcione en create y edit:
            // - en create: $user puede ser null o nuevo
            // - en edit: $user existe
            $oldRoles = old('roles', null); // si viene de validación fallida
        @endphp

        @foreach ($roles as $role)
            @php
                $checked = false;

                // 1) Si hay old('roles'), respeta eso
                if (is_array($oldRoles)) {
                    $checked = in_array($role->id, $oldRoles);
                } else {
                    // 2) Si no hay old, usa roles del usuario (Spatie)
                    $checked = !empty($user?->id) ? $user->hasRole($role->name) : false;
                }
            @endphp

            <div class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="roles[]"
                    id="role_{{ $role->id }}"
                    value="{{ $role->id }}"
                    {{ $checked ? 'checked' : '' }}
                >
                <label class="form-check-label" for="role_{{ $role->id }}">
                    {{ $role->name }}
                </label>
            </div>
        @endforeach

        @error('roles')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror

        @if(!empty($canConfigureCardPayment))
            <div class="card border-primary mt-4 mb-0">
                <div class="card-body py-3">
                    <input type="hidden" name="facturacionTarjetaNfc" value="0">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="facturacionTarjetaNfc"
                            name="facturacionTarjetaNfc"
                            value="1"
                            {{ old('facturacionTarjetaNfc', $user->getDirectPermissions()->pluck('name')->contains(\App\Support\FacturacionModePolicy::CARD_NFC_USER_PERMISSION)) ? 'checked' : '' }}>
                        <label class="form-check-label font-weight-bold" for="facturacionTarjetaNfc">Habilitar Tarjeta NFC + factura para este facturador</label>
                    </div>
                    <small class="form-text text-muted">Solo este usuario verá el botón cuando el interruptor global esté activo y su rol permita Tarjeta NFC o QR + factura.</small>
                    @error('facturacionTarjetaNfc')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        @endif

    </div>

    <div class="box-footer mt20">
        <button type="submit" class="btn btn-primary">{{ __('Listo') }}</button>
    </div>
</div>

@push('js')
    <script>
        const empresaSelect = document.getElementById('empresa_id');
        const sucursalEmpresaSelect = document.getElementById('empresa_sucursal_id');
        const sucursalesPorEmpresa = @js($sucursalesEmpresa);
        const sucursalAyuda = document.getElementById('empresa_sucursal_ayuda');

        empresaSelect.addEventListener('change', () => {
            const empresaId = empresaSelect.value;
            const sucursales = sucursalesPorEmpresa[empresaId] || [];
            sucursalEmpresaSelect.replaceChildren(new Option(
                empresaId ? 'Selecciona una sucursal' : 'Selecciona una empresa primero',
                '',
            ));
            for (const sucursal of sucursales) {
                sucursalEmpresaSelect.add(new Option(sucursal.label, sucursal.id));
            }
            sucursalEmpresaSelect.disabled = !empresaId;
            sucursalAyuda.hidden = !empresaId || sucursales.length > 0;
        });
    </script>
@endpush
