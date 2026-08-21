@extends('adminlte::page')

@section('title', 'Solicitudes EMS')

@section('content')
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .solicitudes-shell{
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .solicitudes-card{
            border:0;
            border-radius:16px;
            box-shadow:0 12px 26px rgba(0,0,0,.08);
            overflow:hidden;
            background:#fff;
        }

        .solicitudes-hero{
            background: linear-gradient(90deg, var(--azul), #20539A);
            color:#fff;
            padding:18px 20px;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:18px;
            flex-wrap:wrap;
        }

        .solicitudes-hero h1{
            margin:0;
            font-size:2rem;
            font-weight:800;
        }

        .solicitudes-hero p{
            margin:6px 0 0;
            color:rgba(255,255,255,.82);
        }

        .solicitudes-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            align-items:center;
        }

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight:800;
            border:none;
            border-radius:12px;
            padding:10px 14px;
        }

        .btn-dorado:hover{
            filter:brightness(.95);
            color:#fff;
        }

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius:12px;
            padding:10px 14px;
            background:transparent;
        }

        .btn-outline-light2:hover{
            background: rgba(255,255,255,.12);
            color:#fff;
        }

        .solicitudes-meta{
            padding:16px 20px 0;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            color:var(--muted);
        }

        .solicitudes-chip{
            display:inline-flex;
            align-items:center;
            border:1px solid rgba(32,83,154,.18);
            background:rgba(32,83,154,.06);
            color:var(--azul);
            border-radius:999px;
            padding:6px 12px;
            font-size:12px;
            font-weight:800;
        }

        .solicitudes-table-wrap{
            padding:16px 20px 20px;
        }

        .solicitudes-search{
            padding:0 20px 16px;
        }

        .solicitudes-search-form{
            display:flex;
            gap:12px;
            align-items:flex-end;
            flex-wrap:wrap;
        }

        .solicitudes-search-field{
            flex:1 1 360px;
        }

        .solicitudes-search-field label{
            display:block;
            margin-bottom:6px;
            color:var(--azul);
            font-weight:800;
        }

        .solicitudes-search-field input{
            width:100%;
            border:1px solid var(--line);
            border-radius:12px;
            padding:11px 14px;
            outline:none;
            box-shadow:none;
        }

        .solicitudes-search-field input:focus{
            border-color:rgba(32,83,154,.55);
            box-shadow:0 0 0 4px rgba(32,83,154,.08);
        }

        .solicitudes-search-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .solicitudes-search-help{
            margin-top:8px;
            color:var(--muted);
            font-size:.9rem;
        }

        .solicitudes-capture-status{
            min-height:22px;
            margin-top:8px;
            font-size:.9rem;
            font-weight:700;
        }

        .solicitudes-capture-status.is-success{ color:#18733b; }
        .solicitudes-capture-status.is-error{ color:#b42318; }

        .solicitudes-prelist{
            margin:0 20px 16px;
            border:1px solid rgba(32,83,154,.22);
            border-radius:14px;
            overflow:hidden;
            background:#f8fbff;
        }

        .solicitudes-prelist-head{
            padding:14px 16px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            border-bottom:1px solid rgba(32,83,154,.14);
        }

        .solicitudes-prelist-head h3{
            margin:0;
            color:#163b6c;
            font-size:1.05rem;
            font-weight:900;
        }

        .solicitudes-prelist-empty{
            padding:16px;
            color:var(--muted);
        }

        .solicitudes-prelist-table{
            margin:0;
            background:#fff;
        }

        .solicitudes-prelist-table td{
            vertical-align:middle;
        }

        .solicitudes-large-check{
            display:flex;
            align-items:flex-start;
            gap:8px;
            min-width:260px;
            margin:0;
            font-size:.86rem;
            line-height:1.25;
            color:#163b6c;
            cursor:pointer;
        }

        .solicitudes-large-check input{
            margin-top:2px;
            flex:0 0 auto;
        }

        .solicitudes-table-card{
            border:1px solid var(--line);
            border-radius:14px;
            overflow:hidden;
            background:#fff;
        }

        .solicitudes-table-head{
            padding:16px 18px;
            border-bottom:1px solid var(--line);
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .solicitudes-table-head h3{
            margin:0;
            font-size:1.05rem;
            font-weight:800;
            color:#163b6c;
        }

        .solicitudes-empty{
            padding:18px;
            color:var(--muted);
        }

        .solicitudes-table{
            margin-bottom:0;
        }

        .solicitudes-table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .solicitudes-pill{
            background: rgba(52,68,124,.12);
            color: var(--azul);
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 999px;
            display:inline-block;
        }

        .solicitudes-contact-name{
            display:block;
        }

        .solicitudes-contact-phone{
            display:block;
            margin-top:4px;
            color:var(--muted);
            font-size:.86rem;
            line-height:1.2;
            white-space:nowrap;
        }

        .solicitudes-footer{
            padding:0 20px 20px;
        }

        @media (max-width: 767.98px){
            .solicitudes-shell{
                padding:12px;
            }

            .solicitudes-hero,
            .solicitudes-meta,
            .solicitudes-table-head{
                flex-direction:column;
                align-items:flex-start;
            }

            .solicitudes-actions{
                width:100%;
            }

            .solicitudes-actions > .btn{
                width:100%;
                justify-content:center;
            }

            .solicitudes-search-actions{
                width:100%;
            }

            .solicitudes-search-actions .btn{
                width:100%;
            }

            .solicitudes-prelist{
                margin-right:12px;
                margin-left:12px;
            }
        }
    </style>

    <div class="solicitudes-shell">
        <div class="solicitudes-card">
            <div class="solicitudes-hero">
                <div>
                    <h1>Solicitudes EMS</h1>
                    <p>Consulta todas las solicitudes registradas desde cliente y desde Admisiones.</p>
                </div>
                <div class="solicitudes-actions">
                    <a href="{{ route('paquetes-ems.solicitudes.create') }}" class="btn btn-dorado">
                        Nuevo
                    </a>
                    <a href="{{ route('paquetes-ems.index') }}" class="btn btn-outline-light2">
                        Volver a admisiones
                    </a>
                </div>
            </div>

            <div class="solicitudes-meta">
                <div>
                    Estado visible:
                    <span class="solicitudes-chip">SOLICITUD</span>
                </div>
                <div>
                    Total en pagina: <strong>{{ $solicitudes->count() }}</strong>
                </div>
            </div>

            <div class="solicitudes-search">
                <form method="GET" action="{{ route('paquetes-ems.solicitudes.index') }}" class="solicitudes-search-form" id="solicitudesSearchForm" autocomplete="off">
                    <div class="solicitudes-search-field">
                        <label for="solicitudesSearchInput">Buscar solicitud</label>
                        <input
                            type="text"
                            id="solicitudesSearchInput"
                            name="q"
                            list="solicitudesSearchSuggestions"
                            value="{{ $search ?? '' }}"
                            placeholder="Codigo, destinatario, remitente, telefono, origen o destino"
                        >
                        <datalist id="solicitudesSearchSuggestions"></datalist>
                        <div class="solicitudes-search-help">
                            Pega o escanea un codigo. Al encontrarlo se seleccionara automaticamente y quedara acumulado en la prelista.
                        </div>
                        <div id="solicitudesCaptureStatus" class="solicitudes-capture-status" role="status" aria-live="polite"></div>
                    </div>
                    <div class="solicitudes-search-actions">
                        <button type="submit" class="btn btn-primary">Agregar</button>
                        @if (!empty($search))
                            <a href="{{ route('paquetes-ems.solicitudes.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        @endif
                    </div>
                </form>
            </div>

    @if (session('success'))
                <div class="alert alert-success mx-3 mt-3 mb-0">
                    {{ session('success') }}
                </div>
    @endif

    @php
        $solicitudTicketUrl = session('solicitud_ticket_url');
    @endphp

    @if (session('error'))
                <div class="alert alert-danger mx-3 mt-3 mb-0">
                    {{ session('error') }}
                </div>
    @endif

            <form id="solicitudesAlmacenForm" method="POST" action="{{ route('paquetes-ems.solicitudes.send-almacen') }}">
                @csrf
                <div id="solicitudesSelectedInputs"></div>
                <div class="solicitudes-prelist">
                    <div class="solicitudes-prelist-head">
                        <h3>Prelista para enviar a ALMACEN (<span id="solicitudesPrelistCount">0</span>)</h3>
                        <button type="submit" id="solicitudesSendButton" class="btn btn-primary btn-sm" disabled>
                            Mandar prelista a ALMACEN
                        </button>
                    </div>
                    <div id="solicitudesPrelistEmpty" class="solicitudes-prelist-empty">
                        Todavia no agregaste solicitudes. Pega o escanea el primer codigo arriba.
                    </div>
                    <div id="solicitudesPrelistTableWrap" class="table-responsive d-none">
                        <table class="table table-sm solicitudes-prelist-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Destinatario</th>
                                    <th>Telefono</th>
                                    <th>Destino</th>
                                    <th>Precio</th>
                                    <th>Paquete grande</th>
                                    <th style="width:90px;"></th>
                                </tr>
                            </thead>
                            <tbody id="solicitudesPrelistBody"></tbody>
                        </table>
                    </div>
                </div>

            <div class="solicitudes-table-wrap">
                <div class="solicitudes-table-card">
                    <div class="solicitudes-table-head">
                        <h3>Listado de solicitudes en estado SOLICITUD</h3>
                    </div>
            @if ($solicitudes->isEmpty())
                        <div class="solicitudes-empty">No hay solicitudes en estado SOLICITUD.</div>
            @else
                    <div class="table-responsive">
                                <table class="table table-hover solicitudes-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Codigo</th>
                                    <th>Canal</th>
                                    <th>Servicio</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Remitente</th>
                                    <th>Destinatario</th>
                                    <th>Peso</th>
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($solicitudes as $solicitud)
                                    @php
                                        $estadoNombre = (string) optional($solicitud->estadoRegistro)->nombre_estado;
                                    @endphp
                                    <tr>
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="solicitud-row-checkbox"
                                                value="{{ $solicitud->id }}"
                                                aria-label="Agregar {{ $solicitud->codigo_solicitud ?: $solicitud->barcode }} a la prelista"
                                            >
                                        </td>
                                                <td><span class="solicitudes-pill">{{ $solicitud->codigo_solicitud ?: 'SIN CODIGO' }}</span></td>
                                        <td>{{ $solicitud->cliente_id ? 'Cliente' : 'Admisiones' }}</td>
                                        <td>{{ $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? '-') }}</td>
                                        <td>{{ $solicitud->origen ?: '-' }}</td>
                                        <td>{{ $solicitud->destino?->nombre_destino ?: ($solicitud->ciudad ?: '-') }}</td>
                                        <td>
                                            <span class="solicitudes-contact-name">{{ $solicitud->nombre_remitente ?: '-' }}</span>
                                            <span class="solicitudes-contact-phone">{{ $solicitud->telefono_remitente ?: 'Sin telefono' }}</span>
                                        </td>
                                        <td>
                                            <span class="solicitudes-contact-name">{{ $solicitud->nombre_destinatario ?: '-' }}</span>
                                            <span class="solicitudes-contact-phone">{{ $solicitud->telefono_destinatario ?: 'Sin telefono' }}</span>
                                        </td>
                                        <td>{{ $solicitud->peso !== null ? number_format((float) $solicitud->peso, 3, '.', '') : '-' }}</td>
                                        <td>{{ $solicitud->precio !== null ? number_format((float) $solicitud->precio, 2, '.', '') : '-' }}</td>
                                        <td>
                                            <span class="badge badge-warning">
                                                {{ $estadoNombre !== '' ? $estadoNombre : '-' }}
                                            </span>
                                        </td>
                                        <td>{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('paquetes-ems.solicitudes.ticket', $solicitud) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                Ticket
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
            @endif
                </div>
            </div>
            </form>
        @if ($solicitudes->hasPages())
                <div class="solicitudes-footer d-flex justify-content-end">
                    {{ $solicitudes->links() }}
                </div>
        @endif
            </div>
        </div>
    </div>
@endsection

@section('js')
@php
    $solicitudesVisibleItems = $solicitudes->map(function ($solicitud) {
        return [
            'id' => (int) $solicitud->id,
            'value' => trim((string) ($solicitud->codigo_solicitud ?: $solicitud->barcode ?: '')),
            'codigo_solicitud' => $solicitud->codigo_solicitud,
            'barcode' => $solicitud->barcode,
            'destinatario' => $solicitud->nombre_destinatario,
            'telefono_destinatario' => $solicitud->telefono_destinatario,
            'ciudad' => $solicitud->destino?->nombre_destino ?: $solicitud->ciudad,
            'origen' => $solicitud->origen,
            'precio_base' => (float) ($solicitud->tarifarioTiktoker?->peso1 ?? $solicitud->precio ?? 0),
            'paquete_muy_grande' => (bool) $solicitud->paquete_muy_grande,
        ];
    })->values();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ticketUrl = @json($solicitudTicketUrl);
    const searchForm = document.getElementById('solicitudesSearchForm');
    const searchInput = document.getElementById('solicitudesSearchInput');
    const datalist = document.getElementById('solicitudesSearchSuggestions');
    const captureStatus = document.getElementById('solicitudesCaptureStatus');
    const almacenForm = document.getElementById('solicitudesAlmacenForm');
    const selectedInputs = document.getElementById('solicitudesSelectedInputs');
    const prelistBody = document.getElementById('solicitudesPrelistBody');
    const prelistCount = document.getElementById('solicitudesPrelistCount');
    const prelistEmpty = document.getElementById('solicitudesPrelistEmpty');
    const prelistTableWrap = document.getElementById('solicitudesPrelistTableWrap');
    const sendButton = document.getElementById('solicitudesSendButton');
    const suggestionsUrl = @json(route('paquetes-ems.solicitudes.index'));
    const initialSearch = @json($search ?? '');
    const visibleItems = @json($solicitudesVisibleItems);

    if (ticketUrl) {
        window.setTimeout(function () {
            window.open(ticketUrl, '_blank', 'noopener');
        }, 150);
    }

    if (!searchForm || !searchInput || !datalist || !almacenForm) {
        return;
    }

    let abortController = null;
    let debounceTimer = null;
    let lastSuggestionValues = [];
    let lastSuggestions = [];
    const selectedItems = new Map();

    const normalize = function (value) {
        return String(value || '').trim().toUpperCase();
    };

    const setStatus = function (message, type) {
        captureStatus.textContent = message || '';
        captureStatus.classList.toggle('is-success', type === 'success');
        captureStatus.classList.toggle('is-error', type === 'error');
    };

    const itemMatches = function (item, term) {
        const normalizedTerm = normalize(term);
        return [item.value, item.codigo_solicitud, item.barcode]
            .some(function (value) { return normalize(value) === normalizedTerm; });
    };

    const renderPrelist = function () {
        const items = Array.from(selectedItems.values());
        prelistBody.innerHTML = '';
        selectedInputs.innerHTML = '';
        prelistCount.textContent = String(items.length);
        sendButton.disabled = items.length === 0;
        prelistEmpty.classList.toggle('d-none', items.length > 0);
        prelistTableWrap.classList.toggle('d-none', items.length === 0);

        items.forEach(function (item) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'solicitud_ids[]';
            hidden.value = item.id;
            selectedInputs.appendChild(hidden);

            const largeHidden = document.createElement('input');
            largeHidden.type = 'hidden';
            largeHidden.name = 'paquetes_muy_grandes[' + item.id + ']';
            largeHidden.value = item.paquete_muy_grande ? '1' : '0';
            selectedInputs.appendChild(largeHidden);

            const row = document.createElement('tr');
            [item.value || item.codigo_solicitud || item.barcode || 'SIN CODIGO', item.destinatario || '-', item.telefono_destinatario || '-', item.ciudad || '-']
                .forEach(function (value, index) {
                    const cell = document.createElement('td');
                    if (index === 0) {
                        const pill = document.createElement('span');
                        pill.className = 'solicitudes-pill';
                        pill.textContent = value;
                        cell.appendChild(pill);
                    } else {
                        cell.textContent = value;
                    }
                    row.appendChild(cell);
                });

            const mismoDestino = normalize(item.origen) !== '' && normalize(item.origen) === normalize(item.ciudad);
            const precioBase = Number(item.precio_base || 0);
            const recargo = item.paquete_muy_grande ? (mismoDestino ? 5 : 10) : 0;
            const priceCell = document.createElement('td');
            priceCell.textContent = 'Bs ' + (precioBase + recargo).toFixed(2);
            row.appendChild(priceCell);

            const largeCell = document.createElement('td');
            const largeLabel = document.createElement('label');
            largeLabel.className = 'solicitudes-large-check';
            const largeCheckbox = document.createElement('input');
            largeCheckbox.type = 'checkbox';
            largeCheckbox.checked = Boolean(item.paquete_muy_grande);
            const largeText = document.createElement('span');
            largeText.textContent = 'Si el paquete es muy grande se aumenta el precio en base al tarifario';
            largeCheckbox.addEventListener('change', function () {
                item.paquete_muy_grande = largeCheckbox.checked;
                renderPrelist();
            });
            largeLabel.appendChild(largeCheckbox);
            largeLabel.appendChild(largeText);
            largeCell.appendChild(largeLabel);
            row.appendChild(largeCell);

            const actionCell = document.createElement('td');
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-sm btn-outline-danger';
            removeButton.textContent = 'Quitar';
            removeButton.addEventListener('click', function () {
                selectedItems.delete(String(item.id));
                renderPrelist();
            });
            actionCell.appendChild(removeButton);
            row.appendChild(actionCell);
            prelistBody.appendChild(row);
        });

        document.querySelectorAll('.solicitud-row-checkbox').forEach(function (checkbox) {
            checkbox.checked = selectedItems.has(String(checkbox.value));
        });
    };

    const addItem = function (item) {
        const key = String(item.id);
        const wasSelected = selectedItems.has(key);
        selectedItems.set(key, item);
        renderPrelist();
        searchInput.value = '';
        renderSuggestions([]);
        setStatus(
            wasSelected
                ? (item.value + ' ya estaba en la prelista.')
                : (item.value + ' agregado. Puedes pegar el siguiente codigo.'),
            'success'
        );
        searchInput.focus();
    };

    const renderSuggestions = function (items) {
        datalist.innerHTML = '';
        lastSuggestions = items;
        lastSuggestionValues = items.map(function (item) {
            const option = document.createElement('option');
            option.value = item.value || '';
            option.label = item.label || item.value || '';
            datalist.appendChild(option);

            return (item.value || '').toUpperCase();
        });
    };

    const fetchSuggestions = function (term, showNotFound) {
        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();
        const url = new URL(suggestionsUrl, window.location.origin);
        url.searchParams.set('autocomplete', '1');
        url.searchParams.set('q', term);

        return fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: abortController.signal,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo cargar la prelista.');
                }

                return response.json();
            })
            .then(function (payload) {
                const items = Array.isArray(payload.data) ? payload.data : [];
                renderSuggestions(items);
                const exactItem = items.find(function (item) { return itemMatches(item, term); });

                if (exactItem) {
                    addItem(exactItem);
                } else if (showNotFound) {
                    setStatus('No se encontro una solicitud en estado SOLICITUD con ese codigo.', 'error');
                }

                return items;
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                renderSuggestions([]);
                setStatus('No se pudo consultar la solicitud. Intenta nuevamente.', 'error');
                return [];
            });
    };

    searchInput.addEventListener('input', function () {
        const term = searchInput.value.trim();

        window.clearTimeout(debounceTimer);
        if (abortController) {
            abortController.abort();
        }

        if (term.length < 2) {
            renderSuggestions([]);
            return;
        }

        debounceTimer = window.setTimeout(function () {
            fetchSuggestions(term, false);
        }, 220);
    });

    searchInput.addEventListener('change', function () {
        const normalizedValue = searchInput.value.trim().toUpperCase();
        if (normalizedValue !== '' && lastSuggestionValues.includes(normalizedValue)) {
            const selectedItem = lastSuggestions.find(function (item) {
                return normalize(item.value) === normalizedValue;
            });
            if (selectedItem) {
                addItem(selectedItem);
            }
        }
    });

    searchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        window.clearTimeout(debounceTimer);
        const term = searchInput.value.trim();
        if (term.length < 2) {
            setStatus('Ingresa un codigo de al menos 2 caracteres.', 'error');
            searchInput.focus();
            return;
        }
        fetchSuggestions(term, true);
    });

    document.querySelectorAll('.solicitud-row-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const item = visibleItems.find(function (candidate) {
                return String(candidate.id) === String(checkbox.value);
            });
            if (!item) {
                return;
            }
            if (checkbox.checked) {
                selectedItems.set(String(item.id), item);
            } else {
                selectedItems.delete(String(item.id));
            }
            renderPrelist();
        });
    });

    const initialItem = visibleItems.find(function (item) {
        return itemMatches(item, initialSearch);
    });
    if (initialItem) {
        addItem(initialItem);
    } else {
        renderPrelist();
        searchInput.focus();
    }
});
</script>
@endsection
