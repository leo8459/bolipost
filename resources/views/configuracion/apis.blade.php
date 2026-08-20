@extends('adminlte::page')

@section('title', 'Credenciales API')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">Credenciales API</h1>
            <p class="text-muted mb-0">Administra qué servicios puede utilizar cada integración externa.</p>
        </div>
        <div class="mt-2 mt-md-0">
            @if ($createMode)
                <button type="button" class="btn btn-outline-secondary mr-1" onclick="window.close()">
                    <i class="fas fa-times-circle mr-1"></i> Cerrar ventana
                </button>
            @else
                <a
                    href="{{ route('configuracion.apis.index', ['nueva' => 1]) }}"
                    class="btn btn-primary mr-1"
                    id="newApiButton"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="fas fa-external-link-alt mr-1"></i> Nueva API
                </a>
            @endif
            <a href="{{ route('configuracion.apis.manual') }}" class="btn btn-outline-success">
                <i class="fas fa-file-word mr-1"></i> Descargar manual
            </a>
        </div>
    </div>
@stop

@section('css')
    <style>
        .api-hero {
            border-radius: 16px;
            padding: 22px 24px;
            color: #fff;
            background: linear-gradient(120deg, #173b6c, #20539a);
            box-shadow: 0 12px 28px rgba(32, 83, 154, .18);
        }

        .api-hero-icon {
            width: 54px;
            height: 54px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(255, 255, 255, .14);
            font-size: 1.5rem;
        }

        .api-form-card,
        .api-token-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .07);
        }

        .api-catalog-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .api-option {
            position: relative;
            display: block;
            height: 100%;
            margin: 0;
            padding: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .api-option:hover {
            border-color: #93b4df;
            box-shadow: 0 8px 18px rgba(32, 83, 154, .09);
            transform: translateY(-1px);
        }

        .api-option.is-selected {
            border-color: #20539a;
            background: #f7faff;
            box-shadow: 0 0 0 3px rgba(32, 83, 154, .09);
        }

        .api-option-check {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 22px;
            height: 22px;
            accent-color: #20539a;
        }

        .api-option-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: #eaf2ff;
            color: #20539a;
            font-size: 1.05rem;
        }

        .api-option-title {
            margin: 12px 34px 5px 0;
            color: #183b68;
            font-size: 1rem;
            font-weight: 800;
        }

        .api-option-description {
            min-height: 42px;
            margin-bottom: 10px;
            color: #667085;
            font-size: .88rem;
            font-weight: 400;
        }

        .api-endpoint {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            padding: 7px 0;
            border-top: 1px solid #edf0f4;
            font-size: .78rem;
            font-weight: 400;
        }

        .api-method {
            min-width: 48px;
            padding: 2px 6px;
            border-radius: 5px;
            color: #fff;
            background: #16815d;
            text-align: center;
            font-size: .69rem;
            font-weight: 900;
        }

        .api-method.is-write {
            background: #c67a08;
        }

        .api-endpoint code {
            color: #344054;
            word-break: break-all;
        }

        .api-example summary {
            color: #20539a;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
        }

        .api-example pre {
            margin: 8px 0 0;
            padding: 10px;
            border-radius: 8px;
            color: #e5e7eb;
            background: #172033;
            font-size: .72rem;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .api-scope-badge {
            display: inline-flex;
            align-items: center;
            margin: 2px 3px 2px 0;
            padding: 5px 8px;
            border-radius: 999px;
            color: #24466f;
            background: #edf4ff;
            font-size: .72rem;
            font-weight: 700;
        }

        .api-token-value {
            min-width: 330px;
            font-family: Consolas, monospace;
            font-size: .73rem;
        }

        .api-method-section {
            margin-bottom: 24px;
        }

        .api-method-heading {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e7ebf0;
            color: #183b68;
            font-size: 1rem;
            font-weight: 800;
        }

        .api-method-heading .api-method {
            display: inline-block;
            min-width: 82px;
        }

        .api-inventory-shell {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .api-inventory-tabs .nav-link {
            margin: 0 6px 6px 0;
            border-radius: 9px;
            color: #344054;
            background: #f1f4f8;
            font-weight: 800;
        }

        .api-inventory-tabs .nav-link.active {
            color: #fff;
            background: #20539a;
        }

        .api-inventory-table td,
        .api-inventory-table th {
            vertical-align: middle;
        }

        .js-api-route-row {
            cursor: pointer;
        }

        .js-api-route-row.is-selected td {
            background: #eaf7ef;
        }

        .api-inventory-name {
            min-width: 240px;
            color: #25364d;
            font-weight: 700;
        }

        .api-inventory-uri {
            min-width: 300px;
            color: #344054;
            font-size: .78rem;
            word-break: break-all;
        }

        .api-empty-method {
            padding: 18px;
            border: 1px dashed #cdd5df;
            border-radius: 12px;
            color: #667085;
            background: #fafbfc;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .api-catalog-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@stop

@section('content')
    <div class="api-hero mb-4">
        <div class="d-flex align-items-center">
            <span class="api-hero-icon mr-3"><i class="fas fa-plug"></i></span>
            <div>
                <h3 class="mb-1 font-weight-bold">Integraciones seguras y fáciles de entender</h3>
                <p class="mb-0 text-white-50">
                    Elige varias APIs, genera una credencial y úsala como Bearer Token en Postman o en otro sistema.
                </p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo crear la credencial.</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('new_token'))
        <div class="alert alert-warning api-token-card p-3">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-key mr-2"></i>
                <strong>Token JWT generado. Cópialo y guárdalo en un lugar seguro.</strong>
            </div>
            <textarea id="new-token" class="form-control api-token-value mb-2" rows="4" readonly>{{ session('new_token') }}</textarea>
            <button type="button" class="btn btn-sm btn-dark js-copy-token" data-target="new-token">
                <i class="fas fa-copy mr-1"></i> Copiar token
            </button>
        </div>
    @endif

    <div class="collapse {{ $errors->any() || $createMode ? 'show' : '' }}" id="newApiPanel">
    <div class="card api-form-card mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h4 class="mb-1 font-weight-bold text-primary">Crear nueva credencial API</h4>
            <p class="text-muted mb-0">Ponle un nombre reconocible y selecciona una o varias APIs.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <form method="POST" action="{{ route('configuracion.apis.store') }}" id="apiCredentialForm">
                @csrf
                <div class="row">
                    <div class="col-md-7">
                        <div class="form-group">
                            <label for="api-name">Nombre de la integración</label>
                            <input type="text" id="api-name" name="name" class="form-control" value="{{ old('name', 'Integración Postman') }}" placeholder="Ejemplo: Sistema de seguimiento externo" required>
                            <small class="text-muted">Este nombre te ayudará a identificar quién utiliza el token.</small>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="api-expires">Fecha de vencimiento</label>
                            <input type="date" id="api-expires" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                            <small class="text-muted">Opcional. Sin fecha, el token no vence hasta que lo desactives.</small>
                        </div>
                    </div>
                </div>

            <section class="api-inventory-shell">
                <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
                    <div>
                        <h5 class="font-weight-bold mb-1">Selecciona las APIs</h5>
                        <p class="text-muted mb-0">
                            <strong id="selectedApiCount">0</strong> seleccionadas de {{ $routeInventoryCount }} disponibles.
                        </p>
                    </div>
                    <div class="mt-2 mx-md-2" style="min-width: 280px;">
                        <label for="apiRouteSearch" class="sr-only">Buscar una API</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                            <input type="search" id="apiRouteSearch" class="form-control" placeholder="Buscar nombre o URL...">
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllApis">Seleccionar todas</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllApis">Limpiar</button>
                    </div>
                </div>

                <div class="alert alert-light border">
                    <strong>Solo APIs externas.</strong> Estas rutas están preparadas para funcionar con el Bearer Token generado aquí.
                    Las nuevas APIs externas aparecerán automáticamente en esta lista cuando se incorporen al catálogo.
                </div>

                <ul class="nav nav-pills api-inventory-tabs" role="tablist">
                    @foreach ($apiSelectionGroups as $methodGroup => $routes)
                        @php
                            $tabId = 'api-inventory-'.\Illuminate\Support\Str::slug($methodGroup);
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-toggle="pill" href="#{{ $tabId }}" role="tab">
                                {{ $methodGroup }} <span class="badge badge-light ml-1">{{ count($routes) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content mt-2">
                    @foreach ($apiSelectionGroups as $methodGroup => $routes)
                        @php
                            $tabId = 'api-inventory-'.\Illuminate\Support\Str::slug($methodGroup);
                        @endphp
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $tabId }}" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered api-inventory-table mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 54px;" class="text-center">Elegir</th>
                                            <th style="width: 90px;">Método</th>
                                            <th>Nombre comprensible</th>
                                            <th>URL</th>
                                            <th>Acceso</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($routes as $apiRoute)
                                            @php
                                                $methodColor = match ($apiRoute['method']) {
                                                    'GET' => 'success',
                                                    'POST' => 'primary',
                                                    'PUT', 'PATCH' => 'warning',
                                                    'DELETE' => 'danger',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <tr class="js-api-route-row">
                                                <td class="text-center">
                                                    <input
                                                        type="checkbox"
                                                        class="js-api-ability js-route-ability"
                                                        name="abilities[]"
                                                        value="{{ $apiRoute['ability'] }}"
                                                        aria-label="Seleccionar {{ $apiRoute['name'] }}"
                                                        @checked(in_array($apiRoute['ability'], old('abilities', []), true))
                                                    >
                                                </td>
                                                <td><span class="badge badge-{{ $methodColor }}">{{ $apiRoute['method'] }}</span></td>
                                                <td class="api-inventory-name">
                                                    {{ $apiRoute['name'] }}
                                                    @if (! empty($apiRoute['description']))
                                                        <div class="small text-muted font-weight-normal">{{ $apiRoute['description'] }}</div>
                                                    @endif
                                                </td>
                                                <td><code class="api-inventory-uri">{{ $apiRoute['uri'] }}</code></td>
                                                <td>
                                                    <span class="badge badge-{{ $apiRoute['access_color'] }}">{{ $apiRoute['access'] }}</span>
                                                    <div class="small text-success mt-1"><i class="fas fa-check-circle mr-1"></i>Seleccionable</div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center text-muted py-3">No hay APIs con este método.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="text-right mt-4">
                    <button type="submit" class="btn btn-primary px-4 js-create-api-token" disabled>
                        <i class="fas fa-key mr-1"></i> Crear credencial con APIs seleccionadas
                    </button>
                </div>
            </section>
            </form>
        </div>
    </div>
    </div>

    <div class="card api-form-card">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h4 class="mb-1 font-weight-bold">Credenciales creadas</h4>
            <p class="text-muted mb-0">Revisa sus APIs autorizadas, copia el token o desactiva el acceso.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th>Integración</th>
                            <th>APIs autorizadas</th>
                            <th>URLs para probar</th>
                            <th>Estado y uso</th>
                            <th>Token JWT</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            <tr>
                                <td>
                                    <strong>{{ $token->name }}</strong>
                                    <div class="small text-muted">Creado: {{ optional($token->created_at)->format('d/m/Y H:i') }}</div>
                                    <div class="small text-muted">Vence: {{ optional($token->expires_at)->format('d/m/Y') ?? 'Sin vencimiento' }}</div>
                                </td>
                                <td style="min-width: 260px;">
                                    @forelse (($token->abilities ?? []) as $ability)
                                        <span class="api-scope-badge">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            {{ $apiCatalog[$ability]['name'] ?? $routeAbilityNames[$ability] ?? $legacyAbilityNames[$ability] ?? $ability }}
                                        </span>
                                    @empty
                                        <span class="text-muted">Sin APIs asignadas</span>
                                    @endforelse
                                </td>
                                <td style="min-width: 360px;">
                                    @php
                                        $tokenEndpoints = $tokenEndpointMap[$token->id] ?? [];
                                        $apiBaseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');
                                    @endphp

                                    @forelse ($tokenEndpoints as $endpoint)
                                        @php
                                            $fullEndpointUrl = $apiBaseUrl.$endpoint['path'];
                                        @endphp
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="api-method {{ $endpoint['method'] !== 'GET' ? 'is-write' : '' }} mr-1">{{ $endpoint['method'] }}</span>
                                            <code class="flex-grow-1 text-break">{{ $fullEndpointUrl }}</code>
                                            <button
                                                type="button"
                                                class="btn btn-xs btn-outline-primary ml-1 js-copy-url text-nowrap"
                                                data-copy-text="{{ $fullEndpointUrl }}"
                                                title="Copiar URL"
                                            ><i class="fas fa-copy mr-1"></i>Copiar URL</button>
                                        </div>
                                    @empty
                                        <span class="text-muted">No hay URL documentada.</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($token->isUsable())
                                        <span class="badge badge-success mb-1">Activo</span>
                                    @else
                                        <span class="badge badge-secondary mb-1">Inactivo</span>
                                    @endif
                                    <div class="small text-muted">Último uso: {{ optional($token->last_used_at)->format('d/m/Y H:i') ?? 'Sin uso' }}</div>
                                </td>
                                <td>
                                    @php
                                        $plainToken = $token->token_plain;

                                        if (! $plainToken && $token->token_encrypted) {
                                            try {
                                                $plainToken = \Illuminate\Support\Facades\Crypt::decryptString($token->token_encrypted);
                                            } catch (\Throwable $e) {
                                                $plainToken = null;
                                            }
                                        }
                                    @endphp

                                    @if ($plainToken)
                                        <textarea id="token-{{ $token->id }}" class="form-control api-token-value mb-2" rows="3" readonly>{{ $plainToken }}</textarea>
                                        <button type="button" class="btn btn-sm btn-outline-primary js-copy-token" data-target="token-{{ $token->id }}">
                                            <i class="fas fa-copy mr-1"></i> Copiar
                                        </button>
                                    @else
                                        <span class="text-muted">Regenera el token para volver a verlo.</span>
                                    @endif
                                </td>
                                <td class="text-right" style="min-width: 145px;">
                                    <form method="POST" action="{{ route('configuracion.apis.regenerate', $token) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-warning mb-1"><i class="fas fa-sync-alt mr-1"></i> Regenerar</button>
                                    </form>

                                    @if ($token->is_active)
                                        <form method="POST" action="{{ route('configuracion.apis.deactivate', $token) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban mr-1"></i> Desactivar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('configuracion.apis.activate', $token) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-check mr-1"></i> Activar</button>
                                        </form>
                                    @endif
                                    <form
                                        method="POST"
                                        action="{{ route('configuracion.apis.destroy', $token) }}"
                                        class="mt-1"
                                        onsubmit="return confirm('¿Seguro que deseas borrar esta credencial? El token dejará de funcionar y no se podrá recuperar.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash-alt mr-1"></i> Borrar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Aún no hay credenciales API.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const abilityInputs = Array.from(document.querySelectorAll('.js-api-ability'));
            const selectedCount = document.getElementById('selectedApiCount');
            const submitButtons = Array.from(document.querySelectorAll('#createApiToken, .js-create-api-token'));
            const routeSearch = document.getElementById('apiRouteSearch');

            const refreshSelection = () => {
                const checkedValues = new Set(abilityInputs.filter((input) => input.checked).map((input) => input.value));
                const checkedCount = checkedValues.size;
                selectedCount.textContent = checkedCount;
                submitButtons.forEach((button) => { button.disabled = checkedCount === 0; });

                abilityInputs.forEach((input) => {
                    input.closest('.api-option')?.classList.toggle('is-selected', input.checked);
                    input.closest('.js-api-route-row')?.classList.toggle('is-selected', input.checked);
                });
            };

            abilityInputs.forEach((input) => input.addEventListener('change', () => {
                abilityInputs
                    .filter((candidate) => candidate !== input && candidate.value === input.value)
                    .forEach((candidate) => { candidate.checked = input.checked; });
                refreshSelection();
            }));

            document.querySelectorAll('.api-option').forEach((card) => {
                card.addEventListener('click', (event) => {
                    if (event.target.closest('input, label, details, summary, pre, code')) {
                        return;
                    }

                    const input = card.querySelector('.js-api-ability');
                    input.checked = ! input.checked;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            document.querySelectorAll('.js-api-route-row').forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('input, a, button')) {
                        return;
                    }

                    const input = row.querySelector('.js-route-ability');
                    input.checked = ! input.checked;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            document.getElementById('selectAllApis')?.addEventListener('click', () => {
                abilityInputs.forEach((input) => { input.checked = true; });
                refreshSelection();
            });

            document.getElementById('clearAllApis')?.addEventListener('click', () => {
                abilityInputs.forEach((input) => { input.checked = false; });
                refreshSelection();
            });

            routeSearch?.addEventListener('input', () => {
                const query = routeSearch.value.trim().toLocaleLowerCase('es');

                document.querySelectorAll('.js-api-route-row').forEach((row) => {
                    row.hidden = query !== '' && ! row.textContent.toLocaleLowerCase('es').includes(query);
                });
            });

            document.querySelectorAll('.js-copy-token').forEach((button) => {
                button.addEventListener('click', async () => {
                    const target = document.getElementById(button.dataset.target);
                    if (! target) {
                        return;
                    }

                    const originalText = button.innerHTML;

                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(target.value);
                        } else {
                            target.select();
                            document.execCommand('copy');
                            window.getSelection()?.removeAllRanges();
                        }

                        button.innerHTML = '<i class="fas fa-check mr-1"></i> Copiado';
                        setTimeout(() => { button.innerHTML = originalText; }, 1800);
                    } catch (error) {
                        target.select();
                    }
                });
            });

            document.querySelectorAll('.js-copy-url').forEach((button) => {
                button.addEventListener('click', async () => {
                    const originalText = button.innerHTML;
                    const value = button.dataset.copyText;

                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(value);
                        } else {
                            const temporary = document.createElement('textarea');
                            temporary.value = value;
                            temporary.style.position = 'fixed';
                            temporary.style.opacity = '0';
                            document.body.appendChild(temporary);
                            temporary.select();
                            document.execCommand('copy');
                            temporary.remove();
                        }

                        button.innerHTML = '<i class="fas fa-check"></i>';
                        setTimeout(() => { button.innerHTML = originalText; }, 1800);
                    } catch (error) {
                        button.title = 'No se pudo copiar la URL';
                    }
                });
            });

            refreshSelection();
        });
    </script>
@stop
