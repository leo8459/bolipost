@php
    $selectedPermissions = collect(old('permissions', $selectedPermissions ?? []))
        ->map(fn ($permission) => (string) $permission)
        ->all();
@endphp

<div class="box box-info padding-1">
    <div class="box-body">
        <style>
            .acl-builder {
                --acl-ink: #16324f;
                --acl-blue: #20539a;
                --acl-gold: #fecc36;
                --acl-bg: #eef3f9;
                --acl-surface: #ffffff;
                --acl-line: #d6e0ee;
                --acl-muted: #60738b;
                --acl-shadow: 0 20px 45px rgba(16, 41, 74, 0.10);
                color: var(--acl-ink);
            }

            .acl-hero {
                background:
                    radial-gradient(circle at top right, rgba(254, 204, 54, 0.28), transparent 28%),
                    linear-gradient(135deg, #173d73 0%, #20539a 55%, #2c67ba 100%);
                color: #fff;
                border-radius: 1.25rem;
                padding: 1.35rem 1.4rem;
                box-shadow: var(--acl-shadow);
                margin-bottom: 1.25rem;
            }

            .acl-hero-title {
                font-size: 1.3rem;
                font-weight: 800;
                letter-spacing: 0.01em;
                margin-bottom: 0.35rem;
            }

            .acl-hero-copy {
                margin: 0;
                max-width: 780px;
                color: rgba(255, 255, 255, 0.86);
                line-height: 1.5;
            }

            .acl-meta-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1rem;
                margin-bottom: 1.25rem;
            }

            .acl-meta-card {
                background: var(--acl-surface);
                border: 1px solid var(--acl-line);
                border-radius: 1rem;
                padding: 1rem 1rem 0.95rem;
                box-shadow: 0 10px 24px rgba(25, 49, 79, 0.06);
            }

            .acl-meta-card label {
                display: block;
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--acl-muted);
                margin-bottom: 0.45rem;
            }

            .acl-meta-card input {
                border-radius: 0.85rem;
                border: 1px solid #cfd9e6;
                min-height: 48px;
            }

            .acl-section-title {
                margin: 0;
                font-size: 1.02rem;
                font-weight: 800;
            }

            .acl-section-copy {
                margin: 0.35rem 0 0;
                color: var(--acl-muted);
                line-height: 1.5;
            }

            .acl-legend {
                display: flex;
                flex-wrap: wrap;
                gap: 0.55rem;
                margin-top: 0.9rem;
            }

            .acl-legend-item {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                border-radius: 999px;
                padding: 0.38rem 0.7rem;
                font-size: 0.84rem;
                font-weight: 700;
                background: rgba(255, 255, 255, 0.16);
                border: 1px solid rgba(255, 255, 255, 0.22);
            }

            .acl-legend-swatch {
                width: 0.72rem;
                height: 0.72rem;
                border-radius: 999px;
                display: inline-block;
            }

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

            .permission-items {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.5rem;
                padding: 0.75rem;
            }

            .permission-items .list-group-item {
                border: 1px solid #e9ecef;
                border-radius: 0.4rem;
                margin: 0;
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

            .menu-map {
                border: 1px solid var(--acl-line);
                border-radius: 1.25rem;
                background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(244,248,252,0.98) 100%);
                padding: 1.1rem;
                box-shadow: var(--acl-shadow);
            }

            .menu-map-tab {
                border: 1px solid #d7e1ef;
                border-radius: 1rem;
                background: linear-gradient(180deg, #fff 0%, #f9fbfe 100%);
                padding: 0.9rem;
                box-shadow: 0 10px 24px rgba(18, 46, 80, 0.06);
            }

            .menu-map-tab + .menu-map-tab {
                margin-top: 1rem;
            }

            .menu-map-submenu {
                border-left: 3px solid #c7d2e3;
                padding-left: 1rem;
                margin-top: 0.75rem;
            }

            .menu-map-window {
                border: 1px solid #dbe4ef;
                border-radius: 1rem;
                padding: 0.95rem;
                margin-top: 0.75rem;
                background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(246,249,253,0.98) 100%);
            }

            .menu-node-toggle {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                border: 0;
                background: transparent;
                padding: 0;
                text-align: left;
                font-weight: 700;
                color: #19314f;
            }

            .menu-node-toggle span:last-child {
                color: #6b7280;
                font-size: 0.85rem;
            }

            .menu-node-toggle::after {
                content: "\25BE";
                font-size: 0.85rem;
                color: #20539A;
                transition: transform 0.15s ease;
            }

            .menu-node-toggle[aria-expanded="false"]::after {
                transform: rotate(-90deg);
            }

            .menu-node-body {
                margin-top: 0.85rem;
            }

            .menu-map-list {
                display: flex;
                flex-wrap: wrap;
                gap: 0.7rem;
                margin-top: 0.6rem;
            }

            .menu-map-check {
                display: block;
                border: 1px solid #d7e1ee;
                background: #fff;
                border-radius: 1rem;
                padding: 0.95rem 1rem;
                min-width: 270px;
                max-width: 380px;
                box-shadow: 0 8px 18px rgba(18, 46, 80, 0.05);
                transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
            }

            .menu-map-check:hover {
                transform: translateY(-1px);
                border-color: #b9cbe4;
                box-shadow: 0 12px 22px rgba(18, 46, 80, 0.08);
            }

            .menu-map-check input {
                margin-right: 0.5rem;
            }

            .menu-map-check-title {
                font-weight: 700;
                color: #1f2937;
            }

            .menu-map-check small {
                display: block;
                margin-left: 1.55rem;
            }

            .menu-map-access .menu-map-check {
                background: linear-gradient(180deg, #f5f9ff 0%, #ffffff 100%);
                border-color: #bfd3ee;
            }

            .menu-map-actions .menu-map-check {
                background: linear-gradient(180deg, #fffaf0 0%, #ffffff 100%);
                border-color: #ead9a4;
            }

            .menu-map-block-title {
                font-size: 0.8rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--acl-muted);
                margin-top: 0.35rem;
            }

            .technical-permissions {
                border-top: 1px solid #e5e7eb;
                margin-top: 1.25rem;
                padding-top: 1.25rem;
            }

            .technical-toggle {
                width: 100%;
                border: 1px solid #d9e2ef;
                background: #fff;
                border-radius: 0.75rem;
                padding: 0.8rem 1rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-weight: 700;
                color: #1f2937;
                text-align: left;
            }

            .technical-toggle::after {
                content: "\25BE";
                color: #20539A;
                transition: transform 0.15s ease;
            }

            .technical-toggle[aria-expanded="false"]::after {
                transform: rotate(-90deg);
            }

            @media (max-width: 575.98px) {
                .acl-meta-grid {
                    grid-template-columns: 1fr;
                }

                .permission-items {
                    grid-template-columns: 1fr;
                }

                .permission-save-inner {
                    justify-content: stretch;
                }

                .permission-save-inner .btn {
                    width: 100%;
                }
            }

            @media (min-width: 576px) and (max-width: 991.98px) {
                .permission-items {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
        </style>

        <div class="acl-builder">
            <section class="acl-hero">
                <div class="acl-hero-title">Constructor visual de permisos</div>
                <p class="acl-hero-copy">
                    Configura este rol por contexto real del sistema: abre una pestaña, entra a una ventana y activa solo los botones o acciones que necesita.
                </p>
                <div class="acl-legend">
                    <span class="acl-legend-item"><span class="acl-legend-swatch" style="background:#b8d2f5;"></span>Acceso a ventana</span>
                    <span class="acl-legend-item"><span class="acl-legend-swatch" style="background:#fecc36;"></span>Botones y acciones</span>
                    <span class="acl-legend-item"><span class="acl-legend-swatch" style="background:#d9e2ef;"></span>Vista técnica opcional</span>
                </div>
            </section>

            <div class="acl-meta-grid">
                <div class="acl-meta-card">
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
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="acl-meta-card">
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
            </div>

        @if (!empty($menuPermissionSummary))
            <div class="menu-map mb-4">
                <div>
                    <h2 class="acl-section-title">Menu principal, ventanas y acciones</h2>
                    <p class="acl-section-copy">
                        La zona azul controla si puede entrar a la ventana. La zona dorada controla botones independientes solo de esa ventana, sin mezclar permisos de otras pestañas.
                    </p>
                </div>

                @php
                    $isSelectedPermission = fn (?string $permissionName): bool => is_string($permissionName) && in_array($permissionName, $selectedPermissions, true);
                    $menuNodeCounter = 0;
                    $nodeHasSelection = function (array $node) use (&$nodeHasSelection, $isSelectedPermission): bool {
                        if (($node['level'] ?? null) === 'window') {
                            $route = $node['route'] ?? null;
                            $routeName = is_array($route) ? ($route['name'] ?? ($node['route_name'] ?? '')) : ($node['route_name'] ?? '');
                            if ($isSelectedPermission($routeName)) {
                                return true;
                            }

                            foreach (($node['actions'] ?? []) as $action) {
                                if ($isSelectedPermission((string) ($action['name'] ?? ''))) {
                                    return true;
                                }
                            }

                            return false;
                        }

                        foreach (($node['children'] ?? []) as $child) {
                            if ($nodeHasSelection($child)) {
                                return true;
                            }
                        }

                        return false;
                    };
                    $renderMenuNode = function (array $node, int $depth = 0) use (&$renderMenuNode, $isSelectedPermission, &$menuNodeCounter, $nodeHasSelection) {
                        $menuNodeCounter++;
                        $nodeId = 'menu_node_'.$menuNodeCounter;
                        $isOpen = $nodeHasSelection($node);

                        if (($node['level'] ?? null) === 'tab') {
                            echo '<section class="menu-map-tab">';
                            echo '<button type="button" class="menu-node-toggle js-menu-node-toggle" data-target="'.$nodeId.'" aria-expanded="'.($isOpen ? 'true' : 'false').'">';
                            echo '<span>Pestana: '.e($node['label'] ?? 'Menu').'</span>';
                            echo '<span>Ver submenu y ventanas</span>';
                            echo '</button>';
                            echo '<div id="'.$nodeId.'" class="menu-node-body '.($isOpen ? '' : 'd-none').'">';
                            foreach (($node['children'] ?? []) as $child) {
                                $renderMenuNode($child, $depth + 1);
                            }
                            echo '</div>';
                            echo '</section>';
                            return;
                        }

                        if (($node['level'] ?? null) === 'submenu') {
                            echo '<div class="menu-map-submenu">';
                            echo '<button type="button" class="menu-node-toggle js-menu-node-toggle" data-target="'.$nodeId.'" aria-expanded="'.($isOpen ? 'true' : 'false').'">';
                            echo '<span>Submenu: '.e($node['label'] ?? 'Submenu').'</span>';
                            echo '<span>Ver ventanas</span>';
                            echo '</button>';
                            echo '<div id="'.$nodeId.'" class="menu-node-body '.($isOpen ? '' : 'd-none').'">';
                            foreach (($node['children'] ?? []) as $child) {
                                $renderMenuNode($child, $depth + 1);
                            }
                            echo '</div>';
                            echo '</div>';
                            return;
                        }

                        $route = $node['route'] ?? null;
                        $actions = $node['actions'] ?? [];
                        $routeName = is_array($route) ? ($route['name'] ?? ($node['route_name'] ?? '')) : ($node['route_name'] ?? '');
                        $routeLabel = is_array($route) ? ($route['action_label'] ?? 'Acceso a la ventana') : 'Acceso a la ventana';
                        $routeHint = is_array($route) ? ($route['hint'] ?? null) : null;

                        echo '<div class="menu-map-window">';
                        echo '<button type="button" class="menu-node-toggle js-menu-node-toggle" data-target="'.$nodeId.'" aria-expanded="'.($isOpen ? 'true' : 'false').'">';
                        echo '<span>Ventana: '.e($node['label'] ?? 'Ventana').'</span>';
                        echo '<span>'.e($node['module_label'] ?? ($node['module_key'] ?? 'sin modulo')).'</span>';
                        echo '</button>';
                        echo '<div id="'.$nodeId.'" class="menu-node-body '.($isOpen ? '' : 'd-none').'">';

                        echo '<div class="menu-map-block-title">Acceso a la ventana</div>';
                        echo '<div class="menu-map-list menu-map-access">';
                        echo '<label class="menu-map-check">';
                        echo '<input type="checkbox" class="js-permission-proxy" data-target-permission="'.e($routeName).'" '.($isSelectedPermission($routeName) ? 'checked' : '').'>';
                        echo '<span class="menu-map-check-title">'.e($routeLabel).'</span>';
                        if (!empty($routeHint)) {
                            echo '<small class="text-primary">'.e($routeHint).'</small>';
                        }
                        echo '<small class="text-muted">'.e($routeName).'</small>';
                        echo '</label>';
                        echo '</div>';

                        if ($actions !== []) {
                            echo '<div class="menu-map-block-title">Botones y acciones dentro de esta ventana</div>';
                            echo '<div class="menu-map-list menu-map-actions">';
                            foreach ($actions as $action) {
                                $hint = trim((string) ($action['hint'] ?? ''));
                                $permissionName = (string) ($action['name'] ?? '');
                                echo '<label class="menu-map-check">';
                                echo '<input type="checkbox" class="js-permission-proxy" data-target-permission="'.e($permissionName).'" '.($isSelectedPermission($permissionName) ? 'checked' : '').'>';
                                echo '<span class="menu-map-check-title">'.e($action['action_label'] ?? $permissionName).'</span>';
                                if ($hint !== '') {
                                    echo '<small class="text-primary">'.e($hint).'</small>';
                                }
                                echo '<small class="text-muted">'.e($permissionName).'</small>';
                                echo '</label>';
                            }
                            echo '</div>';
                        }

                        echo '</div>';
                        echo '</div>';
                    };
                @endphp

                @foreach ($menuPermissionSummary as $tabNode)
                    @php $renderMenuNode($tabNode); @endphp
                @endforeach
            </div>
        @endif

        <div class="technical-permissions">
            <button type="button" class="technical-toggle js-menu-node-toggle mb-3" data-target="technical_permissions_body" aria-expanded="false">
                <span>Vista tecnica de permisos</span>
            </button>

            <div id="technical_permissions_body" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                    <div class="text-muted">
                        Esta vista muestra el nombre tecnico exacto del permiso. La seccion superior es la forma recomendada para editar.
                    </div>
                    <div class="d-flex align-items-center" style="gap: .5rem;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleAllLists">Abrir listas</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="toggleAllPermissions">Marcar todo</button>
                    </div>
                </div>

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
                                                data-permission-name="{{ $permission['name'] }}"
                                                {{ $checked ? 'checked' : '' }}
                                            >
                                            <label class="custom-control-label" for="{{ $permissionId }}">
                                                {{ $permission['action_label'] }}
                                                <span class="badge badge-light border ml-1">{{ $permission['type_label'] ?? 'Permiso' }}</span>
                                                @if (!empty($permission['hint']))
                                                    <small class="d-block text-primary">{{ $permission['hint'] }}</small>
                                                @endif
                                                <small class="d-block text-muted">{{ $permission['name'] }}</small>
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>

        @error('permissions')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
        @error('permissions.*')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
        </div>
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
        const permissionProxies = Array.from(document.querySelectorAll('.js-permission-proxy'));
        const menuNodeToggles = Array.from(document.querySelectorAll('.js-menu-node-toggle'));

        const getModuleItems = (moduleKey) => permissionItems.filter((item) => item.dataset.module === moduleKey);
        const getModuleBody = (targetId) => document.getElementById(targetId);
        const moduleHasCheckedItems = (moduleKey) => getModuleItems(moduleKey).some((item) => item.checked);
        const getTechnicalPermissionItem = (permissionName) => permissionItems.find((item) => item.dataset.permissionName === permissionName);
        const syncProxyFromTechnical = (permissionName) => {
            const technicalItem = getTechnicalPermissionItem(permissionName);
            if (!technicalItem) {
                return;
            }

            permissionProxies
                .filter((proxy) => proxy.dataset.targetPermission === permissionName)
                .forEach((proxy) => {
                    proxy.checked = technicalItem.checked;
                });
        };

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
        const setMenuNodeOpen = (button, isOpen) => {
            const targetId = button.dataset.target;
            const body = getModuleBody(targetId);
            if (!body) {
                return;
            }

            body.classList.toggle('d-none', !isOpen);
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
                syncProxyFromTechnical(permissionItem.dataset.permissionName);
                refreshGlobalToggle();
            });
        });

        permissionProxies.forEach((proxy) => {
            proxy.addEventListener('change', function () {
                const technicalItem = getTechnicalPermissionItem(proxy.dataset.targetPermission);

                if (!technicalItem) {
                    return;
                }

                technicalItem.checked = proxy.checked;
                technicalItem.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        if (globalToggle) {
            globalToggle.addEventListener('click', function () {
                const shouldCheck = globalToggle.dataset.checked !== '1';

                permissionItems.forEach((item) => {
                    item.checked = shouldCheck;
                });

                permissionProxies.forEach((proxy) => {
                    proxy.checked = shouldCheck;
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

        menuNodeToggles.forEach((button) => {
            button.addEventListener('click', function () {
                const isOpen = button.getAttribute('aria-expanded') === 'true';
                setMenuNodeOpen(button, !isOpen);
            });
        });

        refreshGlobalToggle();
        refreshListToggle();
        permissionItems.forEach((permissionItem) => syncProxyFromTechnical(permissionItem.dataset.permissionName));
    });
</script>
