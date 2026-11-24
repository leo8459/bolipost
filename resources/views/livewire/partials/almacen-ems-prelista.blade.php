<div>
    <script type="application/json" data-ems-preview>@json($selectedPreviewRows->values())</script>
    <p wire:offline class="alert alert-warning py-2" role="status">
        Sin conexión. Puedes seleccionar registros de esta página. Para buscar o enviar, espera a recuperar la conexión.
    </p>
    <div wire:ignore>
        <p class="alert alert-warning py-2" role="status" x-show="storageError" style="display:none">
            El navegador no pudo guardar la prelista. Mantenla abierta para conservar la selección.
        </p>
        <div class="prelist-shell mb-3" x-show="total" style="display:none">
            <div class="prelist-top">
                <div>
                    <div class="prelist-title">Prelista de seleccionados (<span x-text="total"></span>)</div>
                    <small class="text-muted">Tu selección se guarda en este navegador sin fecha de vencimiento.</small>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="expanded = !expanded"
                        :aria-expanded="expanded" x-text="expanded ? 'Ocultar prelista' : 'Mostrar prelista'"></button>
                    <button class="btn btn-sm btn-outline-danger" type="button"
                        @click="if (confirm('¿Limpiar toda la selección?')) clear()">Limpiar selección</button>
                </div>
            </div>
            <div class="prelist-kpis">
                @foreach (['EMS', 'INT', 'CONTRATO', 'SOLICITUD'] as $type)
                    <span class="prelist-kpi">{{ $type }}: <strong x-text="counts['{{ $type }}']"></strong></span>
                @endforeach
            </div>
            <div x-show="expanded">
                <div class="prelist-controls">
                    <select x-model="type" class="form-control" style="max-width:230px" aria-label="Tipo de registro">
                        <option value="TODOS">Todos</option>
                        <option value="EMS">EMS</option>
                        <option value="INT">INT</option>
                        <option value="CONTRATO">Contrato</option>
                        <option value="SOLICITUD">Solicitud</option>
                    </select>
                    <input x-model.debounce.150ms="search" type="search" class="form-control" style="max-width:520px"
                        aria-label="Filtrar prelista" placeholder="Filtrar por código, destinatario, ruta o dirección">
                </div>
                <div class="prelist-table-wrap">
                    <table class="table table-sm table-hover align-middle prelist-table">
                        <thead><tr>
                            <th>Tipo</th><th>Código</th><th>Destinatario</th><th>Ruta</th>
                            <th>Direcciones</th><th>Peso</th><th>Acción</th>
                        </tr></thead>
                        <tbody>
                            <template x-for="row in filteredItems" :key="row.tipo + '-' + row.record_id">
                                <tr>
                                    <td x-text="row.tipo"></td>
                                    <td><span class="pill-id" x-text="row.codigo || '#' + row.record_id"></span></td>
                                    <td x-text="row.destinatario || '-'"></td>
                                    <td>
                                        <small class="d-block text-muted">Origen</small><strong x-text="row.origen || '-'"></strong>
                                        <small class="d-block text-muted mt-1">Destino</small><strong x-text="row.destino || '-'"></strong>
                                    </td>
                                    <td>
                                        <small class="d-block text-muted">Remitente</small>
                                        <span x-text="row.direccion_r && row.direccion_r !== '-' ? row.direccion_r : 'Sin dirección registrada'"></span>
                                        <small class="d-block text-muted mt-1">Destinatario</small>
                                        <span x-text="row.direccion_d && row.direccion_d !== '-' ? row.direccion_d : 'Sin dirección registrada'"></span>
                                    </td>
                                    <td x-text="row.peso ?? '-'"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" @click="remove(row)">Quitar</button></td>
                                </tr>
                            </template>
                            <tr x-show="!filteredItems.length">
                                <td colspan="7" class="text-center py-3 text-muted">No hay elementos para ese filtro.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
