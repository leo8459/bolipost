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
            background: linear-gradient(90deg, #34447C, #2c3766);
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
                    @endphp
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Codigo</label>
                                <input type="text" name="codigo" id="registroRapidoCodigo" class="form-control" value="{{ old('codigo') }}" placeholder="Ej: C0007A02011BO" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Peso</label>
                                <input type="number" name="peso" id="registroRapidoPeso" class="form-control" value="{{ old('peso') }}" step="0.001" min="0.001" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Origen (usuario logueado)</label>
                                <input type="text" id="registroRapidoOrigen" class="form-control origin-input" value="{{ $origen }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Destino</label>
                                <select name="destino" id="registroRapidoDestino" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    @foreach ($ciudades as $ciudad)
                                        <option value="{{ $ciudad }}" @selected($destinoPrefill === $ciudad)>{{ $ciudad }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0 d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" id="btnAgregarPrelista">Anadir a prelista</button>
                                <button type="button" class="btn btn-primary" id="btnGuardarTodos">Guardar todos</button>
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
                                    <th>Peso</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Accion</th>
                                </tr>
                            </thead>
                            <tbody id="registroRapidoListadoBody">
                                @forelse (($listado ?? []) as $idx => $item)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>{{ $item['codigo'] ?? '-' }}</td>
                                        <td>{{ $item['peso'] ?? '-' }}</td>
                                        <td>{{ $item['origen'] ?? '-' }}</td>
                                        <td>{{ $item['destino'] ?? '-' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-outline-danger" disabled>Quitar</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="registroRapidoListadoEmpty">
                                        <td colspan="6" class="text-center text-muted py-3">Aun no hay registros en la prelista.</td>
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
        const origenInput = document.getElementById('registroRapidoOrigen');
        const destinoInput = document.getElementById('registroRapidoDestino');
        const alertWrap = document.getElementById('registroRapidoAjaxAlert');
        const serverAlert = document.getElementById('registroRapidoServerAlert');
        const listBody = document.getElementById('registroRapidoListadoBody');
        const listCount = document.getElementById('registroRapidoListadoCount');
        const btnAgregar = document.getElementById('btnAgregarPrelista');
        const btnGuardarTodos = document.getElementById('btnGuardarTodos');
        const csrfToken = form.querySelector('input[name="_token"]')?.value || '';
        const prelista = [];

        function setButtonsDisabled(disabled) {
            if (btnAgregar) btnAgregar.disabled = disabled;
            if (btnGuardarTodos) btnGuardarTodos.disabled = disabled;
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

        function renderPrelista() {
            if (!listBody) return;
            listBody.innerHTML = '';

            if (!prelista.length) {
                listBody.innerHTML = '<tr id="registroRapidoListadoEmpty"><td colspan="6" class="text-center text-muted py-3">Aun no hay registros en la prelista.</td></tr>';
                if (listCount) listCount.textContent = '0';
                return;
            }

            prelista.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${escapeHtml(item.codigo)}</td>
                    <td>${escapeHtml(item.peso)}</td>
                    <td>${escapeHtml(item.origen)}</td>
                    <td>${escapeHtml(item.destino)}</td>
                    <td><button type="button" class="btn btn-xs btn-outline-danger" data-remove-index="${index}">Quitar</button></td>
                `;
                listBody.appendChild(row);
            });

            if (listCount) {
                listCount.textContent = String(prelista.length);
            }
        }

        function addCurrentToPrelista() {
            const codigo = normalizeCodigo(codigoInput.value);
            const peso = String(pesoInput.value || '').trim();
            const destino = String(destinoInput.value || '').trim().toUpperCase();
            const origen = String(origenInput.value || '').trim().toUpperCase();

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
                peso: Number(peso).toFixed(3),
                origen: origen,
                destino: destino,
            });

            renderPrelista();
            showAlert('success', 'Agregado a prelista.');

            codigoInput.value = '';
            pesoInput.value = '';
            codigoInput.focus();
        }

        if (btnAgregar) {
            btnAgregar.addEventListener('click', function () {
                addCurrentToPrelista();
            });
        }

        if (listBody) {
            listBody.addEventListener('click', function (event) {
                const target = event.target.closest('[data-remove-index]');
                if (!target) return;
                const index = Number(target.getAttribute('data-remove-index'));
                if (Number.isNaN(index)) return;
                prelista.splice(index, 1);
                renderPrelista();
            });
        }

        async function guardarTodos() {
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
                            peso: item.peso,
                            destino: item.destino,
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
    })();
</script>
@endsection
