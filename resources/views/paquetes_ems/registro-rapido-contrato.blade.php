@extends('adminlte::page')
@section('title', 'Registro Rapido Contrato')
@section('template_title')
    Registro Rapido Contrato
@endsection

@section('content')
<section class="content container-fluid pt-3">
    <style>
        .quick-wrap {
            background: #f5f7fb;
            border-radius: 16px;
            padding: 12px;
        }
        .quick-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
            overflow: hidden;
        }
        .quick-header {
            background: linear-gradient(90deg, #20539A, #20539A);
            color: #fff;
            padding: 16px 18px;
        }
        .quick-subtitle {
            color: #dbe2ff;
            margin-bottom: 0;
            font-size: 13px;
        }
        .origin-input {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #1e3a8a;
            font-weight: 700;
        }
        .list-wrap {
            margin-top: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .list-head {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 12px;
            font-weight: 700;
            color: #334155;
        }
        .list-table {
            max-height: 48vh;
            overflow: auto;
        }
        .list-table .table {
            margin-bottom: 0;
        }
        .quick-form-row .form-group {
            margin-bottom: 10px;
        }
        .quick-form-row .form-group label {
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .quick-form-row .peso-cell .form-control {
            min-height: 42px;
        }
        .quick-form-row .peso-cell .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .quick-form-row .peso-cell .input-group-append .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            min-height: 42px;
        }
        .quick-form-row .peso-cell .form-group {
            position: relative;
        }
        .quick-form-row .peso-cell .peso-cas-toggle {
            position: relative;
            z-index: 4;
        }
        .quick-form-row .peso-cell .peso-cas-panel {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            width: min(430px, calc(100vw - 48px));
            margin-top: 0;
            min-height: 0;
            z-index: 30;
            box-shadow: 0 14px 26px rgba(15, 23, 42, .16);
        }
        .quick-actions {
            margin-top: 4px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .quick-actions .btn {
            border-radius: 10px;
            font-weight: 800;
            padding: 8px 14px;
        }
        @media (max-width: 991.98px) {
            .quick-form-row .peso-cell .peso-cas-panel {
                position: static;
                width: auto;
                margin-top: 8px;
                z-index: auto;
                box-shadow: none;
            }
            .quick-actions {
                justify-content: flex-start;
                margin-top: 0;
            }
        }
    </style>

    <div id="registroRapidoAjaxAlert"></div>

    @if (session('success'))
        <div class="alert alert-success" id="registroRapidoServerAlert">
            <p class="mb-0">{{ session('success') }}</p>
            @if (session('download_reporte_url'))
                <a href="{{ session('download_reporte_url') }}" target="_blank" class="btn btn-sm btn-outline-success mt-2">
                    Ver/Imprimir rotulo
                </a>
            @endif
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            <p class="mb-0">{{ session('error') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="quick-wrap">
        <div class="card quick-card">
            <div class="quick-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Registrar contrato rapido</h5>
                    <p class="quick-subtitle">Formulario horizontal para ALMACEN EMS</p>
                </div>
                <a href="{{ route('paquetes-ems.almacen') }}" class="btn btn-light btn-sm">Volver</a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('paquetes-ems.contrato-rapido.store') }}" id="registroRapidoForm">
                    @csrf
                    @php
                        $destinoPrefill = old('destino', '');
                        $provinciaPrefill = strtoupper(trim((string) old('provincia', '')));
                        $empresaPrefill = (int) old('empresa_id', 0);
                    @endphp
                    <div class="row align-items-start quick-form-row">
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label>Codigo</label>
                                <input type="text" name="codigo" id="registroRapidoCodigo" class="form-control" value="{{ old('codigo') }}" placeholder="Ej: C0007A02011BO" required>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 peso-cell">
                            <div class="form-group">
                                <x-peso-qz-field
                                    model="peso"
                                    name="peso"
                                    input-id="registroRapidoPeso"
                                    :value="old('peso')"
                                    min="0.001"
                                    :required="true"
                                    :use-scale="true"
                                    :show-clear="true"
                                    :livewire="false"
                                    :status-collapsed="true"
                                />
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <div class="form-group">
                                <label>Cantidad (opcional)</label>
                                <input type="text" name="cantidad" id="registroRapidoCantidad" class="form-control" value="{{ old('cantidad') }}" placeholder="Opcional: cajas, sobres, 3 paquetes...">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <div class="form-group">
                                <label>Origen (usuario logueado)</label>
                                <input type="text" id="registroRapidoOrigen" class="form-control origin-input" value="{{ $origen }}" readonly>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <div class="form-group">
                                <label>Destino *</label>
                                <select name="destino" id="registroRapidoDestino" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    @foreach ($ciudades as $ciudad)
                                        <option value="{{ $ciudad }}" @selected($destinoPrefill === $ciudad)>{{ $ciudad }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <div class="form-group">
                                <label>Provincia (opcional)</label>
                                <select name="provincia" id="registroRapidoProvincia" class="form-control">
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label>Empresa (opcional)</label>
                                <select name="empresa_id" id="registroRapidoEmpresa" class="form-control">
                                    <option value="">Sin empresa</option>
                                    @foreach (($empresas ?? []) as $empresa)
                                        <option value="{{ (int) $empresa->id }}" @selected($empresaPrefill === (int) $empresa->id)>
                                            {{ strtoupper((string) $empresa->nombre) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-1">Se usa solo si el codigo no detecta empresa automaticamente.</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0 d-flex gap-2">
                                @if (($canQuickContractCreate ?? false) || ($canQuickContractSave ?? false))
                                    @if ($canQuickContractCreate ?? false)
                                    <button type="button" class="btn btn-outline-primary" id="btnAgregarPrelista">Anadir a prelista</button>
                                    @endif
                                    @if ($canQuickContractSave ?? false)
                                    <button type="button" class="btn btn-primary" id="btnGuardarTodos">Guardar todos</button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </form>

                <div class="list-wrap">
                    <div class="list-head">
                        Prelista: <span id="registroRapidoListadoCount">{{ count($listado ?? []) }}</span>
                    </div>
                    <div class="list-table">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Codigo</th>
                                    <th>Cantidad</th>
                                    <th>Peso</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Provincia</th>
                                    <th>Empresa</th>
                                    <th>Accion</th>
                                </tr>
                            </thead>
                            <tbody id="registroRapidoListadoBody">
                                @forelse (($listado ?? []) as $idx => $item)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>{{ $item['codigo'] ?? '-' }}</td>
                                        <td>{{ !empty($item['cantidad']) ? $item['cantidad'] : '-' }}</td>
                                        <td>{{ $item['peso'] ?? '-' }}</td>
                                        <td>{{ $item['origen'] ?? '-' }}</td>
                                        <td>{{ $item['destino'] ?? '-' }}</td>
                                        <td>{{ $item['provincia'] ?? '-' }}</td>
                                        <td>{{ $item['empresa'] ?? '-' }}</td>
                                        <td>
                                            @if ($canQuickContractCreate ?? false)
                                                <button type="button" class="btn btn-xs btn-outline-secondary" disabled>Duplicar</button>
                                            @endif
                                            @if ($canQuickContractDelete ?? false)
                                                <button type="button" class="btn btn-xs btn-outline-danger" disabled>Quitar</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="registroRapidoListadoEmpty">
                                        <td colspan="9" class="text-center text-muted py-3">Aun no hay registros en la prelista.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    (function () {
        const form = document.getElementById('registroRapidoForm');
        if (!form) return;

        const codigoInput = document.getElementById('registroRapidoCodigo');
        const pesoInput = document.getElementById('registroRapidoPeso');
        const cantidadInput = document.getElementById('registroRapidoCantidad');
        const origenInput = document.getElementById('registroRapidoOrigen');
        const destinoInput = document.getElementById('registroRapidoDestino');
        const provinciaInput = document.getElementById('registroRapidoProvincia');
        const empresaInput = document.getElementById('registroRapidoEmpresa');
        const provinciasPorDestino = @json($provinciasPorDestino ?? []);
        const oldProvincia = @json($provinciaPrefill);
        const oldEmpresaId = @json($empresaPrefill);
        const alertWrap = document.getElementById('registroRapidoAjaxAlert');
        const serverAlert = document.getElementById('registroRapidoServerAlert');
        const listBody = document.getElementById('registroRapidoListadoBody');
        const listCount = document.getElementById('registroRapidoListadoCount');
        const btnAgregar = document.getElementById('btnAgregarPrelista');
        const btnGuardarTodos = document.getElementById('btnGuardarTodos');
        const canQuickContractCreate = @json((bool) ($canQuickContractCreate ?? false));
        const canQuickContractSave = @json((bool) ($canQuickContractSave ?? false));
        const canQuickContractDelete = @json((bool) ($canQuickContractDelete ?? false));
        const csrfToken = form.querySelector('input[name="_token"]')?.value || '';
        const prelista = [];

        function setButtonsDisabled(disabled) {
            if (btnAgregar) btnAgregar.disabled = disabled;
            if (btnGuardarTodos) btnGuardarTodos.disabled = disabled || !prelista.length;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showAlert(type, message, extraHtml = '') {
            alertWrap.innerHTML = `<div class="alert alert-${type}"><p class="mb-0">${escapeHtml(message)}</p>${extraHtml}</div>`;
            if (serverAlert) {
                serverAlert.remove();
            }
        }

        function normalizeCodigo(value) {
            return String(value || '').toUpperCase().replace(/\s+/g, '').trim();
        }

        function renderProvinciaOptions(destino, selected = '') {
            if (!provinciaInput) return;

            const key = String(destino || '').trim().toUpperCase();
            const provincias = Array.isArray(provinciasPorDestino[key]) ? provinciasPorDestino[key] : [];
            const selectedValue = String(selected || '').trim().toUpperCase();

            provinciaInput.innerHTML = '';
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Seleccione...';
            provinciaInput.appendChild(empty);

            provincias.forEach((provincia) => {
                const value = String(provincia || '').trim().toUpperCase();
                if (!value) return;
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                if (selectedValue !== '' && selectedValue === value) {
                    option.selected = true;
                }
                provinciaInput.appendChild(option);
            });
        }

        function renderPrelista() {
            if (!listBody) return;
            listBody.innerHTML = '';

            if (!prelista.length) {
                listBody.innerHTML = '<tr id="registroRapidoListadoEmpty"><td colspan="9" class="text-center text-muted py-3">Aun no hay registros en la prelista.</td></tr>';
                if (listCount) listCount.textContent = '0';
                return;
            }

            prelista.forEach((item, index) => {
                const row = document.createElement('tr');
                const actionButtons = [];

                if (canQuickContractCreate) {
                    actionButtons.push(`<button type="button" class="btn btn-xs btn-outline-secondary mr-1" data-duplicate-index="${index}">Duplicar</button>`);
                }

                if (canQuickContractDelete) {
                    actionButtons.push(`<button type="button" class="btn btn-xs btn-outline-danger" data-remove-index="${index}">Quitar</button>`);
                }

                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${escapeHtml(item.codigo)}</td>
                    <td>${escapeHtml(item.cantidad || '-')}</td>
                    <td>${escapeHtml(item.peso)}</td>
                    <td>${escapeHtml(item.origen)}</td>
                    <td>${escapeHtml(item.destino)}</td>
                    <td>${escapeHtml(item.provincia || '-')}</td>
                    <td>${escapeHtml(item.empresa || '-')}</td>
                    <td>${actionButtons.join('')}</td>
                `;
                listBody.appendChild(row);
            });

            if (listCount) {
                listCount.textContent = String(prelista.length);
            }

            setButtonsDisabled(false);
        }

        function addCurrentToPrelista() {
            const codigo = normalizeCodigo(codigoInput.value);
            const peso = String(pesoInput.value || '').trim();
            const cantidad = String(cantidadInput?.value || '').trim();
            const destino = String(destinoInput.value || '').trim().toUpperCase();
            const origen = String(origenInput.value || '').trim().toUpperCase();
            const provincia = String(provinciaInput?.value || '').trim().toUpperCase();
            const empresaIdValue = String(empresaInput?.value || '').trim();
            const empresaId = empresaIdValue !== '' ? Number(empresaIdValue) : null;
            const empresaNombre = empresaInput && empresaInput.selectedIndex >= 0
                ? String(empresaInput.options[empresaInput.selectedIndex].text || '').trim()
                : '';

            if (!codigo) {
                showAlert('danger', 'Ingresa un codigo valido.');
                codigoInput.focus();
                return;
            }

            if (!peso || Number(peso) <= 0) {
                showAlert('danger', 'Ingresa un peso valido.');
                pesoInput.focus();
                return;
            }

            if (!destino) {
                showAlert('danger', 'Selecciona un destino.');
                destinoInput.focus();
                return;
            }

            const existe = prelista.some((row) => row.codigo === codigo);
            if (existe) {
                showAlert('danger', 'Ese codigo ya esta en la prelista.');
                codigoInput.focus();
                return;
            }

            prelista.push({
                codigo: codigo,
                cantidad: cantidad,
                peso: Number(peso).toFixed(3),
                origen: origen,
                destino: destino,
                provincia: provincia,
                empresa_id: Number.isFinite(empresaId) ? empresaId : null,
                empresa: empresaIdValue !== '' ? empresaNombre : '',
            });

            renderPrelista();
            showAlert('success', 'Agregado a prelista.');

            codigoInput.value = '';
            pesoInput.value = '';
            if (provinciaInput) {
                provinciaInput.value = '';
            }
            codigoInput.focus();
        }

        function duplicateRow(index) {
            const item = prelista[index];
            if (!item) return;

            pesoInput.value = item.peso;
            if (cantidadInput) {
                    cantidadInput.value = item.cantidad || '';
            }
            destinoInput.value = item.destino;
            renderProvinciaOptions(item.destino, item.provincia || '');

            if (provinciaInput) {
                provinciaInput.value = item.provincia || '';
            }
            if (empresaInput) {
                empresaInput.value = item.empresa_id ? String(item.empresa_id) : '';
            }

            codigoInput.value = '';
            codigoInput.focus();
            showAlert('success', 'Fila cargada para duplicar. Ingresa un nuevo codigo y vuelve a anadirla.');
        }

        if (btnAgregar) {
            btnAgregar.addEventListener('click', function () {
                addCurrentToPrelista();
            });
        }

        if (destinoInput) {
            destinoInput.addEventListener('change', function () {
                renderProvinciaOptions(this.value, '');
            });
        }

        if (listBody) {
            listBody.addEventListener('click', function (event) {
                const duplicateTarget = event.target.closest('[data-duplicate-index]');
                if (duplicateTarget) {
                    const duplicateIndex = Number(duplicateTarget.getAttribute('data-duplicate-index'));
                    if (!Number.isNaN(duplicateIndex)) {
                        duplicateRow(duplicateIndex);
                    }
                    return;
                }

                const target = event.target.closest('[data-remove-index]');
                if (!target) return;
                const index = Number(target.getAttribute('data-remove-index'));
                if (Number.isNaN(index)) return;
                prelista.splice(index, 1);
                renderPrelista();
            });
        }

        async function guardarTodos() {
            if (!canQuickContractSave) {
                showAlert('danger', 'No tienes permiso para guardar la prelista.');
                return;
            }

            if (!prelista.length) {
                showAlert('danger', 'No hay elementos en la prelista.');
                return;
            }
            setButtonsDisabled(true);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: JSON.stringify({
                        items: prelista.map((item) => ({
                            codigo: item.codigo,
                            cantidad: item.cantidad || null,
                            peso: item.peso,
                            destino: item.destino,
                            provincia: item.provincia || null,
                            empresa_id: item.empresa_id || null,
                        })),
                    }),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    if (data && data.errors) {
                        const firstError = Object.values(data.errors).flat()[0] || 'Error de validacion.';
                        showAlert('danger', firstError);
                    } else {
                        showAlert('danger', (data && data.message) ? data.message : 'No se pudo guardar la prelista.');
                    }
                    return;
                }

                const links = Array.isArray(data.items)
                    ? data.items.map((item) => `<a href="${escapeHtml(item.reporte_url || '#')}" target="_blank" class="btn btn-sm btn-outline-success mt-2 mr-1">${escapeHtml(item.codigo || 'Imprimir')}</a>`).join('')
                    : '';
                showAlert('success', data.message || 'Prelista guardada correctamente.', links);

                prelista.length = 0;
                renderPrelista();
                if (codigoInput) {
                    codigoInput.value = '';
                }
                if (pesoInput) {
                    pesoInput.value = '';
                }
                if (cantidadInput) {
                    cantidadInput.value = '';
                }
                if (provinciaInput) {
                    provinciaInput.value = '';
                }
                if (empresaInput) {
                    empresaInput.value = '';
                }
                if (codigoInput) codigoInput.focus();
            } catch (e) {
                showAlert('danger', 'Error de conexion al guardar la prelista.');
            } finally {
                setButtonsDisabled(false);
            }
        }

        if (btnGuardarTodos) {
            btnGuardarTodos.addEventListener('click', function () {
                guardarTodos();
            });
        }

        renderPrelista();
        renderProvinciaOptions(destinoInput ? destinoInput.value : '', oldProvincia);
        if (empresaInput && oldEmpresaId > 0) {
            empresaInput.value = String(oldEmpresaId);
        }
        setButtonsDisabled(false);
    })();
</script>
@endsection

