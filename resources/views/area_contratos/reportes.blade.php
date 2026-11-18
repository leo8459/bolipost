@extends('adminlte::page')
@section('title', 'Area Contratos - Reportes')
@section('template_title')
    Area Contratos - Reportes
@endsection

@section('content')
    <div class="area-contratos-wrap">
        <div class="card area-contratos-card report-filters-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-1">Reportes de contratos</h3>
                    <div class="area-header-meta">Total filtrado: <strong>{{ $totalReportes }}</strong></div>
                    @if ($sucursal)
                        <div class="area-header-meta">Sucursal <strong>{{ $sucursal->etiqueta }}</strong></div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('area-contratos.reportes') }}" class="row align-items-end mb-3" id="reportesContratosForm">
                    <div class="col-md-6 col-xl-3 mb-3">
                        <div class="small font-weight-bold mb-1" id="report-empresas-label">Empresas</div>
                        <details class="report-company-picker" id="report-empresas">
                            <summary class="form-control" aria-labelledby="report-empresas-label report-empresas-count">
                                <span id="report-empresas-count">{{ match (count($empresaIds)) {
                                    0 => 'Todas las empresas',
                                    1 => '1 empresa seleccionada',
                                    default => count($empresaIds).' empresas seleccionadas',
                                } }}</span>
                            </summary>
                            <div class="report-company-panel border rounded bg-white shadow p-2">
                                <input type="search" class="form-control form-control-sm mb-2"
                                    placeholder="Nombre, sigla o código" aria-label="Buscar empresas" id="report-empresas-search">
                                <div class="report-company-list" role="group" aria-labelledby="report-empresas-label">
                                    @foreach ($empresas as $grupo)
                                        <div data-company-group="{{ $grupo['codigo_cliente'] }}">
                                            @if (count($grupo['ids']) > 1)
                                                <label class="report-company-option small font-weight-bold mb-0">
                                                    <input type="checkbox" data-company-select-group
                                                        @checked(count(array_intersect($grupo['ids'], $empresaIds)) === count($grupo['ids']))>
                                                    <span>Todas las empresas del código {{ $grupo['codigo_cliente'] }}</span>
                                                </label>
                                            @elseif ($grupo['codigo_cliente'] !== '')
                                                <div class="small font-weight-bold text-muted mt-1">{{ $grupo['codigo_cliente'] }}</div>
                                            @endif
                                            @foreach ($grupo['empresas'] as $empresa)
                                                <label class="report-company-option font-weight-normal mb-0">
                                                    <input type="checkbox" name="empresa_ids[]" value="{{ $empresa['id'] }}"
                                                        data-company-choice
                                                        @checked(in_array($empresa['id'], $empresaIds, true))>
                                                    <span>{{ $empresa['nombre'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    <div class="small text-muted p-1" id="report-empresas-empty" @if ($empresas->isNotEmpty()) hidden @endif>
                                        No se encontraron empresas.
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>

                    @php($opcionesSucursal = $sucursalesPorEmpresa->only($empresaIds))
                    <div class="col-md-6 col-xl-2 mb-3">
                        <label for="report-sucursal" class="small font-weight-bold mb-1">Sucursal de empresa</label>
                        <select name="empresa_sucursal_id" id="report-sucursal" class="form-control @error('empresa_sucursal_id') is-invalid @enderror"
                            @disabled($opcionesSucursal->isEmpty())>
                            <option value="">{{ $opcionesSucursal->isNotEmpty() ? 'Todas las sucursales' : ($empresaIds ? 'Sin sucursales registradas' : 'Selecciona una empresa') }}</option>
                            @foreach ($opcionesSucursal as $opciones)
                                <optgroup label="{{ $opciones['nombre'] }}">
                                    @foreach ($opciones['sucursales'] as $opcion)
                                        <option value="{{ $opcion['id'] }}" @selected((int) $sucursal?->id === $opcion['id'])>{{ $opcion['nombre'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('empresa_sucursal_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 col-xl-2 mb-3">
                        <label class="small font-weight-bold mb-1">Fecha de recojo desde</label>
                        <input type="date" name="from" value="{{ $from }}" class="form-control">
                    </div>

                    <div class="col-md-6 col-xl-2 mb-3">
                        <label class="small font-weight-bold mb-1">Fecha de recojo hasta</label>
                        <input type="date" name="to" value="{{ $to }}" class="form-control">
                    </div>

                    <div class="col-md-6 col-xl-3 mb-3">
                        <label class="small font-weight-bold mb-1">Buscar</label>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Codigo, remitente, destinatario, origen, destino..."
                        >
                    </div>

                    <div class="col-md-auto ml-md-auto mb-2">
                        <div class="area-filter-actions">
                            <button type="submit" class="btn area-btn-primary">Filtrar</button>
                            <a href="{{ route('area-contratos.reportes') }}" class="btn area-btn-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>

                <div class="d-flex flex-wrap align-items-center justify-content-between report-toolbar">
                    <div class="text-muted mb-2 mb-md-0">
                        El Excel incluye solo contratos con fecha de recojo del cartero, con estado distinto de 0 y excepto CANCELADO, separados por hoja segun el departamento de origen.
                    </div>
                    <button
                        type="submit"
                        form="reportesContratosForm"
                        formaction="{{ route('area-contratos.reportes.excel') }}"
                        class="btn btn-success"
                        id="exportExcelContratosBtn"
                    >
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-3">
                <div class="card area-contratos-card h-100">
                    <div class="card-header">
                        <h3 class="card-title">Hojas que se generaran</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Origen</th>
                                        <th class="text-right">Registros</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($groupedSummary as $group)
                                        <tr>
                                            <td>{{ $group['origen'] }}</td>
                                            <td class="text-right">{{ $group['total'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center py-4 text-muted">
                                                No hay datos para generar hojas.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 mb-3">
                <div class="card area-contratos-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="card-title mb-1">Vista previa de contratos</h3>
                            <div class="area-header-meta">Mostrando: <strong>{{ $contratos->count() }}</strong></div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Origen</th>
                                        <th>Provincia</th>
                                        <th>Destino</th>
                                        <th>Remitente</th>
                                        <th>Destinatario</th>
                                        <th>Empresa</th>
                                        <th>Codigo cliente</th>
                                        <th>Cantidad</th>
                                        <th>Estado</th>
                                        <th>Fecha de recojo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($contratos as $contrato)
                                        <tr>
                                            <td>{{ $contrato->codigo }}</td>
                                            <td>{{ $contrato->origen ?: '-' }}</td>
                                            <td>{{ $contrato->provincia ?: '-' }}</td>
                                            <td>{{ $contrato->destino_registrado ?: '-' }}</td>
                                            <td>{{ $contrato->nombre_r ?: '-' }}</td>
                                            <td>{{ $contrato->nombre_d ?: '-' }}</td>
                                            <td>
                                                {{ optional($contrato->empresa)->nombre ?? '-' }}
                                                @if(!empty(optional($contrato->empresa)->sigla))
                                                    ({{ optional($contrato->empresa)->sigla }})
                                                @endif
                                            </td>
                                            <td>{{ optional($contrato->empresa)->codigo_cliente ?: '-' }}</td>
                                            <td>{{ $contrato->cantidad ?: '-' }}</td>
                                            <td>{{ optional($contrato->estadoRegistro)->nombre_estado ?: '-' }}</td>
                                            <td>{{ optional($contrato->fecha_recojo)->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center py-4 text-muted">
                                                No hay contratos con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        {{ $contratos->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .area-contratos-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .area-contratos-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .area-contratos-card .card-header {
            background: linear-gradient(95deg, #20539A 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 0.95rem 1.1rem;
        }

        .report-filters-card {
            overflow: visible;
        }

        .report-company-picker {
            position: relative;
        }

        .report-company-picker summary {
            display: list-item;
            cursor: pointer;
        }

        .report-company-panel {
            position: absolute;
            z-index: 10;
            top: calc(100% + 4px);
            inset-inline: 0;
        }

        .report-company-list {
            max-height: 15rem;
            overflow-y: auto;
        }

        .report-company-option {
            display: flex;
            align-items: baseline;
            gap: .4rem;
            padding: .25rem .35rem;
            cursor: pointer;
        }

        .report-company-option:hover {
            background: #edf1fb;
        }

        .report-company-option[hidden] {
            display: none;
        }

        .area-header-meta {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.88rem;
        }

        .area-btn-primary {
            background: #FECC36;
            color: #fff;
            font-weight: 800;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .area-btn-primary:hover {
            filter: brightness(.96);
            color: #fff;
        }

        .area-btn-secondary {
            border: 1px solid rgba(32, 83, 154, .22);
            background: #fff;
            color: #20539A;
            font-weight: 800;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .area-btn-secondary:hover {
            background: rgba(32, 83, 154, .05);
            color: #20539A;
        }

        .area-filter-actions {
            display: flex;
            gap: 10px;
        }

        .area-filter-actions .btn {
            flex: 1 1 0;
        }

        .area-contratos-card .table thead th {
            background: #edf1fb;
            color: #20539A;
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }

        .report-toolbar {
            gap: 12px;
            padding: 12px 14px;
            border: 1px dashed #d8e0f2;
            border-radius: 12px;
            background: #f8fbff;
        }

        @media (max-width: 767.98px) {
            .area-header-meta {
                margin-bottom: .35rem;
            }

            .area-filter-actions {
                flex-direction: column;
            }
        }
    </style>
@endsection

@section('js')
    <script>
        const companyPicker = document.getElementById('report-empresas');
        const companySearch = document.getElementById('report-empresas-search');
        const companyCount = document.getElementById('report-empresas-count');
        const companyEmpty = document.getElementById('report-empresas-empty');
        const companyGroups = companyPicker.querySelectorAll('[data-company-group]');
        const branchSelect = document.getElementById('report-sucursal');
        const branchesByCompany = @js($sucursalesPorEmpresa);
        const normalizeCompanyText = (text) => text.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

        const updateCompanyGroup = (group) => {
            const selector = group.querySelector('[data-company-select-group]');
            if (!selector) return;

            const companies = Array.from(group.querySelectorAll('[data-company-choice]'));
            const selected = companies.filter((company) => company.checked).length;
            selector.checked = selected === companies.length;
            selector.indeterminate = selected > 0 && selected < companies.length;
        };

        const updateBranches = (selectedCompanies) => {
            const previousBranch = branchSelect.value;
            const availableGroups = Object.entries(branchesByCompany)
                .filter(([companyId]) => selectedCompanies.includes(companyId));
            const placeholder = availableGroups.length > 0 ? 'Todas las sucursales' :
                selectedCompanies.length > 0 ? 'Sin sucursales registradas' : 'Selecciona una empresa';

            branchSelect.replaceChildren(new Option(placeholder, ''));
            availableGroups.forEach(([, company]) => {
                const group = document.createElement('optgroup');
                group.label = company.nombre;
                company.sucursales.forEach((branch) => group.append(new Option(branch.nombre, branch.id)));
                branchSelect.append(group);
            });
            if (Array.from(branchSelect.options).some((option) => option.value === previousBranch)) {
                branchSelect.value = previousBranch;
            }
            branchSelect.disabled = availableGroups.length === 0;
        };

        companySearch.addEventListener('input', () => {
            const term = normalizeCompanyText(companySearch.value);
            let hasMatches = false;

            companyGroups.forEach((group) => {
                let groupMatches = false;

                group.querySelectorAll('[data-company-choice]').forEach((company) => {
                    const option = company.closest('label');
                    const text = normalizeCompanyText(`${group.dataset.companyGroup} ${option.textContent.trim()}`);
                    option.hidden = !text.includes(term);
                    groupMatches ||= !option.hidden;
                });

                group.hidden = !groupMatches;
                hasMatches ||= groupMatches;
            });

            companyEmpty.hidden = hasMatches;
        });

        companyPicker.addEventListener('change', (event) => {
            if (event.target.type !== 'checkbox') return;

            const group = event.target.closest('[data-company-group]');
            if (event.target.hasAttribute('data-company-select-group')) {
                group.querySelectorAll('[data-company-choice]').forEach((company) => {
                    company.checked = event.target.checked;
                });
            }
            updateCompanyGroup(group);

            const selectedCompanies = Array.from(companyPicker.querySelectorAll('[data-company-choice]:checked'), (company) => company.value);
            const count = selectedCompanies.length;
            companyCount.textContent = count === 0 ? 'Todas las empresas' :
                count === 1 ? '1 empresa seleccionada' : `${count} empresas seleccionadas`;
            updateBranches(selectedCompanies);
        });

        companyGroups.forEach(updateCompanyGroup);

        document.addEventListener('click', (event) => {
            if (!companyPicker.contains(event.target)) companyPicker.open = false;
        });

        companyPicker.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && companyPicker.open) {
                companyPicker.open = false;
                companyPicker.querySelector('summary').focus();
            }
        });
    </script>
@endsection
