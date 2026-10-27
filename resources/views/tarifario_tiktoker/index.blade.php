@extends('adminlte::page')
@section('title', 'Tarifario Delivery Express')
@section('template_title')
    Tarifario Delivery Express
@endsection

@section('content')
    @php
        $regionalNameMap = $regionalNameMap ?? [];
        $regionalLabel = fn ($name) => $regionalNameMap[strtoupper(trim((string) $name))] ?? strtoupper(trim((string) $name));
        $priceLabels = [
            'peso1' => 'Peso 1',
            'peso2' => 'Peso 2',
            'peso3' => 'Peso 3',
            'peso_extra' => 'Peso Extra',
        ];
    @endphp
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Tarifario Delivery Express</span>
                            <div class="d-flex" style="gap:8px;">
                                <a href="{{ route('tarifario-tiktoker.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm" target="_blank">
                                    Reporte PDF
                                </a>
                                <a href="{{ route('tarifario-tiktoker.excel', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                    Reporte Excel
                                </a>
                                <a href="{{ route('tarifario-tiktoker.import-form') }}" class="btn btn-info btn-sm">
                                    Importar Excel
                                </a>
                                <a href="{{ route('tarifario-tiktoker.create') }}" class="btn btn-primary btn-sm">
                                    Crear Nuevo
                                </a>
                            </div>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif
                    @if ($message = Session::get('warning'))
                        <div class="alert alert-warning m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger m-3 mb-0">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (session()->has('import_errors'))
                        <div class="alert alert-danger m-3 mb-0">
                            <p class="mb-2"><strong>Errores de importacion (primeros 20):</strong></p>
                            <ul class="mb-0">
                                @foreach (session('import_errors', []) as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="card-body">
                        <form method="GET" action="{{ route('tarifario-tiktoker.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Origen</label>
                                        <select name="origen_id" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($origenes as $origen)
                                                <option value="{{ $origen->id }}" {{ (int) $origenId === (int) $origen->id ? 'selected' : '' }}>
                                                    {{ $regionalLabel($origen->nombre_origen) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Destino</label>
                                        <select name="destino_id" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($destinos as $destino)
                                                <option value="{{ $destino->id }}" {{ (int) $destinoId === (int) $destino->id ? 'selected' : '' }}>
                                                    {{ $regionalLabel($destino->nombre_destino) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Busqueda general</label>
                                        <input
                                            type="text"
                                            name="q"
                                            value="{{ $q }}"
                                            class="form-control"
                                            placeholder="Origen, destino, peso, tiempo..."
                                        >
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                                        <a href="{{ route('tarifario-tiktoker.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="filter-precio-peso1">Precio Peso 1 (hasta 3 kg, Bs)</label>
                                        <input id="filter-precio-peso1" type="number" min="0" step="0.01" name="precio_peso1" value="{{ $precioPeso1 !== null ? number_format($precioPeso1, 2, '.', '') : '' }}" class="form-control" placeholder="Ej. 15,00">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="filter-precio-peso2">Precio Peso 2 (Bs)</label>
                                        <input id="filter-precio-peso2" type="number" min="0" step="0.01" name="precio_peso2" value="{{ $precioPeso2 !== null ? number_format($precioPeso2, 2, '.', '') : '' }}" class="form-control" placeholder="Ej. 20,00">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="filter-precio-peso3">Precio Peso 3 (Bs)</label>
                                        <input id="filter-precio-peso3" type="number" min="0" step="0.01" name="precio_peso3" value="{{ $precioPeso3 !== null ? number_format($precioPeso3, 2, '.', '') : '' }}" class="form-control" placeholder="Ej. 25,00">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="filter-precio-peso-extra">Precio Peso Extra (Bs)</label>
                                        <input id="filter-precio-peso-extra" type="number" min="0" step="0.01" name="precio_peso_extra" value="{{ $precioPesoExtra !== null ? number_format($precioPesoExtra, 2, '.', '') : '' }}" class="form-control" placeholder="Ej. 5,00">
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                            <div>
                                <strong>Reportes por departamento.</strong>
                                El PDF se agrupa por origen y el Excel incluye una hoja resumen y una hoja por departamento.
                                <span class="d-block text-muted small mt-1">Haz clic en cualquier precio para editar solo ese valor.</span>
                            </div>
                            <div>
                                Total actual: <strong>{{ $tarifas->total() }}</strong> registro(s)
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="tarifario-inline-table" class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>ID</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Servicio Extra</th>
                                        <th>Peso 1<br><small>hasta 3 kg</small></th>
                                        <th>Peso 2<br><small>hasta 5 kg</small></th>
                                        <th>Peso 3<br><small>opcional</small></th>
                                        <th>Peso Extra<br><small>+ de 5 kg</small></th>
                                        <th>Tiempo Entrega</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tarifas as $tarifa)
                                        @php($updateFormId = 'tarifario-inline-update-' . $tarifa->id)
                                        <tr data-tarifario-row>
                                            <td>
                                                {{ $tarifa->id }}
                                                <form id="{{ $updateFormId }}" action="{{ route('tarifario-tiktoker.update', $tarifa->id) }}" method="POST" class="d-none">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="peso1" value="{{ $tarifa->peso1 }}">
                                                </form>
                                            </td>
                                            <td>
                                                <span data-row-display>{{ $regionalLabel($tarifa->origen?->nombre_origen) }}</span>
                                                <select class="form-control form-control-sm d-none" name="origen_id" form="{{ $updateFormId }}" required>
                                                    @foreach($origenes as $origen)
                                                        <option value="{{ $origen->id }}" {{ (int) $tarifa->origen_id === (int) $origen->id ? 'selected' : '' }}>{{ $regionalLabel($origen->nombre_origen) }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <span data-row-display>{{ $regionalLabel($tarifa->destino?->nombre_destino) }}</span>
                                                <select class="form-control form-control-sm d-none" name="destino_id" form="{{ $updateFormId }}" required>
                                                    @foreach($destinos as $destino)
                                                        <option value="{{ $destino->id }}" {{ (int) $tarifa->destino_id === (int) $destino->id ? 'selected' : '' }}>{{ $regionalLabel($destino->nombre_destino) }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <span data-row-display>{{ $tarifa->servicioExtra?->nombre ?? '-' }}</span>
                                                <select class="form-control form-control-sm d-none" name="servicio_extra_id" form="{{ $updateFormId }}">
                                                    <option value="">-</option>
                                                    @foreach($servicioExtras as $extra)
                                                        <option value="{{ $extra->id }}" {{ (int) $tarifa->servicio_extra_id === (int) $extra->id ? 'selected' : '' }}>{{ $extra->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            @include('tarifario_tiktoker.partials.price-cell', ['tarifa' => $tarifa, 'field' => 'peso1', 'priceLabels' => $priceLabels])
                                            @include('tarifario_tiktoker.partials.price-cell', ['tarifa' => $tarifa, 'field' => 'peso2', 'priceLabels' => $priceLabels])
                                            @include('tarifario_tiktoker.partials.price-cell', ['tarifa' => $tarifa, 'field' => 'peso3', 'priceLabels' => $priceLabels])
                                            @include('tarifario_tiktoker.partials.price-cell', ['tarifa' => $tarifa, 'field' => 'peso_extra', 'priceLabels' => $priceLabels])
                                            <td>
                                                <span data-row-display>{{ $tarifa->tiempo_entrega }} h</span>
                                                <input class="form-control form-control-sm d-none" type="number" min="0" step="1" name="tiempo_entrega" value="{{ $tarifa->tiempo_entrega }}" form="{{ $updateFormId }}" required>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-nowrap" style="gap:4px;">
                                                    <button type="button" class="btn btn-sm btn-success" data-row-edit title="Editar en la tabla" aria-label="Editar tarifario {{ $tarifa->id }}">
                                                        <i class="fa fa-fw fa-edit"></i>
                                                    </button>
                                                    <button type="submit" form="{{ $updateFormId }}" class="btn btn-sm btn-primary d-none" data-row-save title="Guardar cambios" aria-label="Guardar tarifario {{ $tarifa->id }}">
                                                        <i class="fa fa-fw fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary d-none" data-row-cancel title="Cancelar edicion" aria-label="Cancelar edicion del tarifario {{ $tarifa->id }}">
                                                        <i class="fa fa-fw fa-times"></i>
                                                    </button>
                                                    <form action="{{ route('tarifario-tiktoker.destroy', $tarifa->id) }}" method="POST" class="d-inline" data-row-delete>
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm"
                                                            title="Eliminar"
                                                            onclick="return confirm('Seguro que deseas eliminar este tarifario Delivery Express?')"
                                                        >
                                                            <i class="fa fa-fw fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-4">No hay registros</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $tarifas->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection

@section('js')
    <script>
        (function () {
            const table = document.getElementById('tarifario-inline-table');
            if (!table) return;
            const csrfToken = @json(csrf_token());

            function setEditing(row, editing) {
                row.dataset.rowEditing = editing ? 'true' : 'false';
                row.querySelectorAll('[data-row-display]').forEach(function (element) {
                    element.classList.toggle('d-none', editing);
                });
                row.querySelectorAll('select[form], input[form]').forEach(function (element) {
                    element.classList.toggle('d-none', !editing);
                });
                row.querySelectorAll('[data-price-editor]').forEach(function (element) {
                    element.classList.toggle('d-none', editing);
                });
                row.querySelector('[data-row-edit]')?.classList.toggle('d-none', editing);
                row.querySelector('[data-row-save]')?.classList.toggle('d-none', !editing);
                row.querySelector('[data-row-cancel]')?.classList.toggle('d-none', !editing);
                row.querySelector('[data-row-delete]')?.classList.toggle('d-none', editing);
                row.classList.toggle('table-warning', editing);
            }

            function openPriceEditor(cell) {
                const row = cell.closest('[data-tarifario-row]');
                if (!row || row.dataset.rowEditing === 'true' || row.dataset.priceEditing === 'true') return;

                row.dataset.priceEditing = 'true';
                row.querySelectorAll('[data-price-edit]').forEach(function (button) {
                    button.disabled = true;
                });

                cell.querySelector('[data-price-edit]').classList.add('d-none');
                cell.querySelector('[data-price-controls]').classList.remove('d-none');
                const error = cell.querySelector('[data-price-error]');
                error.textContent = '';
                error.classList.add('d-none');
                const input = cell.querySelector('[data-price-input]');
                input.value = cell.dataset.priceValue || '';
                input.focus();
                input.select();
            }

            function closePriceEditor(cell, cancel) {
                const row = cell.closest('[data-tarifario-row]');
                const input = cell.querySelector('[data-price-input]');
                const saveButton = cell.querySelector('[data-price-save]');
                const error = cell.querySelector('[data-price-error]');
                if (cancel) input.value = cell.dataset.priceValue || '';
                input.disabled = false;
                saveButton.disabled = false;
                error.textContent = '';
                error.classList.add('d-none');
                cell.querySelector('[data-price-controls]').classList.add('d-none');
                cell.querySelector('[data-price-edit]').classList.remove('d-none');
                row.dataset.priceEditing = 'false';
                row.querySelectorAll('[data-price-edit]').forEach(function (button) {
                    button.disabled = false;
                });
            }

            async function savePrice(cell) {
                const input = cell.querySelector('[data-price-input]');
                if (!input.reportValidity()) return;

                const saveButton = cell.querySelector('[data-price-save]');
                const error = cell.querySelector('[data-price-error]');
                saveButton.disabled = true;
                input.disabled = true;

                try {
                    const response = await fetch(cell.dataset.updateUrl, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            field: cell.dataset.field,
                            value: input.value,
                        }),
                    });
                    const payload = await response.json().catch(function () { return {}; });
                    if (!response.ok) {
                        const validationMessage = payload.errors
                            ? Object.values(payload.errors).flat()[0]
                            : null;
                        throw new Error(validationMessage || payload.message || 'No se pudo guardar el precio.');
                    }

                    cell.dataset.priceValue = String(payload.value);
                    input.value = String(payload.value);
                    cell.querySelector('[data-price-display]').textContent = payload.formatted;

                    const row = cell.closest('[data-tarifario-row]');
                    const peso1FormInput = row.querySelector('form[id^="tarifario-inline-update-"] input[name="peso1"]');
                    if (peso1FormInput && cell.dataset.field === 'peso1') {
                        peso1FormInput.value = String(payload.value);
                        peso1FormInput.defaultValue = String(payload.value);
                    }

                    closePriceEditor(cell, false);
                } catch (exception) {
                    error.textContent = exception.message || 'No se pudo guardar el precio.';
                    error.classList.remove('d-none');
                    input.disabled = false;
                    saveButton.disabled = false;
                }
            }

            table.addEventListener('click', async function (event) {
                const priceEditButton = event.target.closest('[data-price-edit]');
                const priceSaveButton = event.target.closest('[data-price-save]');
                const priceCancelButton = event.target.closest('[data-price-cancel]');
                const editButton = event.target.closest('[data-row-edit]');
                const cancelButton = event.target.closest('[data-row-cancel]');

                if (priceEditButton) {
                    openPriceEditor(priceEditButton.closest('[data-price-cell]'));
                    return;
                }

                if (priceSaveButton) {
                    await savePrice(priceSaveButton.closest('[data-price-cell]'));
                    return;
                }

                if (priceCancelButton) {
                    closePriceEditor(priceCancelButton.closest('[data-price-cell]'), true);
                    return;
                }

                if (editButton) {
                    const row = editButton.closest('[data-tarifario-row]');
                    if (row.dataset.priceEditing !== 'true') setEditing(row, true);
                    return;
                }

                if (cancelButton) {
                    const row = cancelButton.closest('[data-tarifario-row]');
                    const form = row.querySelector('form[id^="tarifario-inline-update-"]');
                    if (form) form.reset();
                    setEditing(row, false);
                }
            });

            table.addEventListener('keydown', function (event) {
                const input = event.target.closest('[data-price-input]');
                if (!input) return;

                if (event.key === 'Enter') {
                    event.preventDefault();
                    savePrice(input.closest('[data-price-cell]'));
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closePriceEditor(input.closest('[data-price-cell]'), true);
                }
            });
        })();
    </script>
@endsection

@section('css')
    <style>
        .tarifario-price-trigger {
            align-items: center;
            background: transparent;
            border: 0;
            color: inherit;
            cursor: pointer;
            display: inline-flex;
            font: inherit;
            gap: 5px;
            justify-content: center;
            padding: 2px 4px;
            min-width: 64px;
        }

        .tarifario-price-trigger:hover,
        .tarifario-price-trigger:focus {
            background: #fff8dc;
            border-radius: 4px;
            outline: 1px solid #e0b526;
        }

        .tarifario-price-pencil {
            color: #1769aa;
            font-size: 11px;
            opacity: 0;
        }

        .tarifario-price-trigger:hover .tarifario-price-pencil,
        .tarifario-price-trigger:focus .tarifario-price-pencil {
            opacity: 1;
        }

        .tarifario-price-input {
            max-width: 88px;
        }
    </style>
@endsection
