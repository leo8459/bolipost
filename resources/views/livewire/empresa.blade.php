<div>
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .plantilla-wrap{
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .card-app{
            border:0;
            border-radius:16px;
            box-shadow:0 12px 26px rgba(0,0,0,.08);
            overflow:hidden;
        }

        .header-app{
            background: linear-gradient(90deg, var(--azul), #20539A);
            color:#fff;
            padding:22px 24px;
            display:grid;
            grid-template-columns:minmax(210px, .7fr) minmax(620px, 2fr);
            gap:24px;
            align-items:center;
        }

        .empresas-title{
            display:flex;
            align-items:center;
            gap:14px;
        }
        .empresas-title-icon{
            width:46px;
            height:46px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:14px;
            color:var(--azul);
            background:var(--dorado);
            box-shadow:0 8px 18px rgba(0,0,0,.14);
            flex:0 0 auto;
        }
        .empresas-title h4{
            font-size:1.45rem;
            font-weight:800;
            margin:0;
        }
        .empresas-title p{
            color:rgba(255,255,255,.78);
            margin:3px 0 0;
            font-size:.88rem;
        }
        .empresas-toolbar{
            display:flex;
            justify-content:flex-end;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
        }
        .empresas-search{
            display:flex;
            flex:1 1 330px;
            min-width:280px;
            gap:8px;
        }

        .search-input{
            border-radius:12px;
            border:1px solid rgba(255,255,255,.45);
            padding:10px 12px;
            background: rgba(255,255,255,.95);
            min-width:0;
            height:42px;
        }

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .btn-dorado:hover{ filter:brightness(.95); color:#fff; }

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius: 12px;
            padding: 10px 14px;
            background: transparent;
        }
        .btn-outline-light2:hover{
            background: rgba(255,255,255,.12);
            color:#fff;
        }

        .btn-azul{
            background: var(--azul);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .btn-azul:hover{ filter:brightness(.95); color:#fff; }

        .btn-outline-azul{
            border:1px solid rgba(52,68,124,.35);
            color: var(--azul);
            font-weight: 800;
            border-radius: 12px;
            padding: 10px 14px;
            background:#fff;
        }
        .btn-outline-azul:hover{
            background: rgba(52,68,124,.06);
            color: var(--azul);
        }

        .table thead th{
            background:#eef3fa;
            color: var(--azul);
            font-weight: 800;
            font-size:.78rem;
            letter-spacing:.025em;
            text-transform:uppercase;
            border-bottom: 1px solid #d7e0ee;
            white-space: nowrap;
        }

        .pill-id{
            background:#eef3fa;
            color: var(--azul);
            font-weight:800;
            padding:7px 10px;
            border-radius:10px;
            display:block;
            line-height:1.25;
            max-width:250px;
        }

        .muted{ color:var(--muted); }

        .table td{ vertical-align: middle; }
        .table-empresa{
            font-family:inherit;
            font-size:.88rem;
            min-width:1050px;
            width:100%;
            table-layout:fixed;
            margin-bottom:0;
        }
        .table-empresa th,
        .table-empresa td{
            padding:.8rem .65rem;
        }
        .table-empresa tbody tr{
            border-bottom:1px solid #edf0f4;
            transition:background-color .15s ease;
        }
        .table-empresa tbody tr:hover{
            background:#f8fbff;
        }
        .table-shell{
            border:1px solid #e3e8f0;
            border-radius:14px;
            overflow-x:auto;
        }
        .company-meta{
            display:block;
            color:var(--muted);
            font-size:.75rem;
            margin-top:4px;
        }
        .data-badge{
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            padding:5px 9px;
            background:#f1f5f9;
            color:#475569;
            font-size:.75rem;
            font-weight:700;
            white-space:nowrap;
        }
        .data-badge.publica{ background:#e8f1ff; color:#174e94; }
        .data-badge.privada{ background:#f4ecff; color:#6b3fa0; }
        .date-cell,
        .budget-cell{
            white-space:nowrap;
            font-variant-numeric:tabular-nums;
        }
        .budget-cell{
            font-weight:700;
            color:#243b5a;
        }
        .coverage-cell{
            line-height:1.35;
            overflow-wrap:anywhere;
        }
        .empresa-actions{
            display:flex;
            align-items:center;
            justify-content:flex-start;
            gap:6px;
            min-width:0;
            white-space:nowrap;
        }
        .empresa-actions .btn{
            border-radius:9px;
            min-height:36px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:7px 10px;
            box-shadow:none;
        }
        .empresa-actions .action-icon{
            width:38px;
            padding:7px;
        }
        .btn-history{
            background:#e8f1ff;
            border:1px solid #b9d1f1;
            color:#174e94;
            font-weight:800;
        }
        .btn-history:hover{
            background:#d9e9ff;
            color:#123f78;
        }
        .empresas-summary{
            background:#f8fafc;
            border:1px solid #e5eaf1;
            border-radius:12px;
            padding:10px 13px;
        }
        .empresas-count{
            display:inline-flex;
            align-items:center;
            gap:7px;
            background:#eaf1fb;
            color:var(--azul);
            border-radius:999px;
            padding:6px 11px;
            font-weight:800;
            white-space:nowrap;
        }
        .col-company{ width:21%; }
        .col-contract{ width:12%; }
        .col-dates{ width:12%; }
        .col-coverage{ width:16%; }
        .col-budget{ width:10%; }
        .col-document{ width:11%; }
        .col-actions{ width:18%; }
        .contract-stack,
        .document-stack{
            display:flex;
            flex-direction:column;
            align-items:flex-start;
            gap:6px;
        }
        .date-range{
            display:grid;
            gap:4px;
            font-variant-numeric:tabular-nums;
            white-space:nowrap;
        }
        .date-range small{
            color:var(--muted);
            font-size:.7rem;
            font-weight:700;
            text-transform:uppercase;
        }
        .date-range i{
            color:#9aa8ba;
            font-size:.7rem;
            margin:0 5px;
        }
        .empresa-modal-grid{
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:14px 16px;
        }
        .empresa-modal-grid .form-group{
            margin-bottom:0;
        }
        .empresa-modal-grid .form-span-2{
            grid-column:1 / -1;
        }

        @media (max-width: 767.98px){
            .empresa-modal-grid{
                grid-template-columns:1fr;
            }
            .empresa-modal-grid .form-span-2{
                grid-column:auto;
            }
            .plantilla-wrap{ padding:10px; }
            .header-app{ padding:18px; }
            .empresas-search{ min-width:100%; }
            .empresas-toolbar .btn,
            .empresas-toolbar a{ flex:1 1 auto; }
        }

        @media (max-width: 1199.98px){
            .header-app{
                grid-template-columns:1fr;
                gap:16px;
            }
            .empresas-toolbar{ justify-content:flex-start; }
        }

        .modal-content{
            border:0;
            border-radius:18px;
            box-shadow:0 20px 50px rgba(0,0,0,.2);
        }
        .modal-header{
            background: linear-gradient(90deg, var(--azul), #20539A);
            color:#fff;
            border-bottom:0;
            padding:16px 20px;
        }
        .modal-title{ font-weight:800; }
        .modal-body{ padding:20px; background:#fff; }
        .modal-footer{
            border-top:1px solid var(--line);
            padding:14px 20px;
            background:#fafafa;
        }
        .form-control, .custom-select, select.form-control{
            border-radius:10px;
            border:1px solid #d1d5db;
            box-shadow:none;
        }
        .uppercase-input{
            text-transform: uppercase;
        }
        .form-control:focus, select.form-control:focus{
            border-color: var(--azul);
            box-shadow:0 0 0 0.15rem rgba(52,68,124,.15);
        }
        .form-group label{
            font-weight:700;
            color:#1f2937;
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app">
                <div class="empresas-title">
                    <span class="empresas-title-icon"><i class="fas fa-building"></i></span>
                    <div>
                        <h4>Empresas</h4>
                        <p>Contratos, documentos y vigencias empresariales</p>
                    </div>
                </div>

                <div class="empresas-toolbar">
                    <div class="empresas-search">
                        <input
                            type="text"
                            class="form-control search-input"
                            placeholder="Buscar empresa, sigla, codigo, NIT o cobertura..."
                            wire:model="search"
                            wire:keydown.enter="searchEmpresas"
                        >
                        <button class="btn btn-outline-light2" type="button" wire:click="searchEmpresas">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                    </div>
                    @can('empresas.historial.index')
                    <a class="btn btn-outline-light2" href="{{ route('empresas.historial.index') }}">
                        <i class="fas fa-history mr-1"></i> Historial
                    </a>
                    @endcan
                    @if (auth()->user()?->can('empresas.template-excel') || auth()->user()?->can('feature.empresas.export'))
                    <a class="btn btn-outline-light2" href="{{ route('empresas.pdf') }}" target="_blank" title="Generar reporte PDF">
                        <i class="fas fa-file-pdf mr-1"></i> Reporte
                    </a>
                    <a class="btn btn-outline-light2" href="{{ route('empresas.template-excel') }}" title="Descargar plantilla Excel">
                        <i class="fas fa-file-excel mr-1"></i> Plantilla
                    </a>
                    @endif
                    @if (auth()->user()?->can('empresas.import-form') || auth()->user()?->can('feature.empresas.import'))
                    <a class="btn btn-outline-light2" href="{{ route('empresas.import-form') }}">
                        <i class="fas fa-file-import mr-1"></i> Importar
                    </a>
                    @endif
                    @aclcan('create', $this)
                    <button class="btn btn-dorado" type="button" wire:click="openCreateModal">
                        <i class="fas fa-plus mr-1"></i> Nueva empresa
                    </button>
                    @endaclcan
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3">
                    <p class="mb-0">{{ session('success') }}</p>
                </div>
            @endif

            @if (session()->has('import_errors'))
                <div class="alert alert-warning m-3">
                    <p class="mb-2"><strong>Errores de importacion (primeros 20):</strong></p>
                    <ul class="mb-0 pl-3">
                        @foreach (session('import_errors', []) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card-body">
                <div class="empresas-summary d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            <i class="fas fa-filter mr-1"></i> Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            <i class="fas fa-list mr-1"></i> Mostrando todos los registros
                        @endif
                    </div>
                    <div class="empresas-count mt-2 mt-sm-0">
                        <i class="fas fa-building"></i>
                        {{ $empresas->count() }} en esta pagina
                    </div>
                </div>

                <div class="table-responsive table-shell">
                    <table class="table table-hover align-middle table-empresa">
                        <thead>
                            <tr>
                                <th class="col-company">Empresa</th>
                                <th class="col-contract">Contrato</th>
                                <th class="col-dates">Vigencia</th>
                                <th class="col-coverage">Cobertura</th>
                                <th class="col-budget">Presupuesto</th>
                                <th class="col-document">Documento</th>
                                <th class="col-actions">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($empresas as $empresa)
                                <tr>
                                    <td>
                                        <span class="pill-id">{{ $empresa->nombre }}</span>
                                        <small class="company-meta">
                                            <strong>{{ $empresa->sigla }}</strong>
                                            <span class="mx-1">•</span>
                                            Codigo {{ $empresa->codigo_cliente }}
                                            <span class="mx-1">•</span>
                                            NIT {{ $empresa->nit ?: '-' }}
                                            <span class="mx-1">&bull;</span>
                                            #{{ $empresa->id }}
                                        </small>
                                    </td>
                                    <td>
                                        <div class="contract-stack">
                                        @if($empresa->clasificacion)
                                            <span class="data-badge {{ strtolower($empresa->clasificacion) }}">{{ $empresa->clasificacion }}</span>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                        @if($empresa->documentacion_legal)
                                            <span class="data-badge">{{ $empresa->documentacion_legal }}</span>
                                        @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-range">
                                            <span><small>Inicio</small> {{ !empty($empresa->inicio_contrato) ? \Illuminate\Support\Carbon::parse($empresa->inicio_contrato)->format('d/m/Y') : '-' }}</span>
                                            <span><small>Fin</small><i class="fas fa-arrow-right"></i>{{ !empty($empresa->fin_contrato) ? \Illuminate\Support\Carbon::parse($empresa->fin_contrato)->format('d/m/Y') : '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="coverage-cell" title="{{ $empresa->cobertura }}">{{ $empresa->cobertura ?? '-' }}</td>
                                    <td class="budget-cell">{{ !is_null($empresa->presupuesto) ? number_format((float) $empresa->presupuesto, 2) : '-' }}</td>
                                    <td>
                                        <div class="document-stack">
                                        @if (!empty($empresa->documento_pdf_path))
                                            <a
                                                href="{{ asset('storage/' . $empresa->documento_pdf_path) }}"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-azul"
                                                title="Ver PDF"
                                            >
                                                <i class="fas fa-file-pdf mr-1"></i> Abrir
                                            </a>
                                        @else
                                            <span class="muted small">Sin PDF</span>
                                        @endif
                                        <small class="muted">
                                            Creado {{ optional($empresa->created_at)->format('d/m/Y H:i') ?: '-' }}
                                        </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="empresa-actions">
                                        @aclcan('edit', $this)
                                        <button wire:click="openEditModal({{ $empresa->id }})"
                                            class="btn btn-sm btn-azul action-icon"
                                            title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @endaclcan
                                        @aclcan('history', $this)
                                        <button wire:click="openHistoryModal({{ $empresa->id }})"
                                            class="btn btn-sm btn-history"
                                            title="Añadir a historial">
                                            <i class="fas fa-history mr-1"></i> Historial
                                        </button>
                                        @endaclcan
                                        @aclcan('delete', $this)
                                        <button wire:click="delete({{ $empresa->id }})"
                                            class="btn btn-sm btn-outline-danger action-icon"
                                            title="Eliminar"
                                            onclick="return confirm('Seguro que deseas eliminar esta empresa?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endaclcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">Prueba con otro texto de busqueda.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $empresas->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="empresaModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $archivingToHistory ? 'Añadir a historial y renovar empresa' : ($editingId ? 'Editar empresa' : 'Nueva empresa') }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        @if($archivingToHistory)
                            <div class="alert alert-warning">
                                Los datos y el PDF vigentes se respaldaran en el historial. Debes cambiar las dos fechas antes de guardar.
                            </div>
                        @endif
                        <div class="empresa-modal-grid">
                            <div class="form-group form-span-2">
                                <label>Nombre</label>
                                <input type="text" wire:model.defer="nombre" class="form-control uppercase-input">
                                @error('nombre') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>Sigla</label>
                                <input type="text" wire:model.defer="sigla" class="form-control uppercase-input">
                                @error('sigla') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>Codigo cliente</label>
                                <input type="text" wire:model.defer="codigo_cliente" class="form-control uppercase-input">
                                @error('codigo_cliente') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>NIT</label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="32" wire:model.defer="nit" class="form-control" placeholder="Ej. 123456789">
                                @error('nit') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>Clasificacion</label>
                                <select wire:model.defer="clasificacion" class="form-control">
                                    <option value="">Selecciona una opcion</option>
                                    <option value="PUBLICA">Publica</option>
                                    <option value="PRIVADA">Privada</option>
                                </select>
                                @error('clasificacion') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>Documentacion legal</label>
                                <select wire:model.defer="documentacion_legal" class="form-control">
                                    <option value="">Selecciona una opcion</option>
                                    <option value="CONTRATO">Contrato</option>
                                    <option value="CONVENIO">Convenio</option>
                                    <option value="ADENDA">Adenda</option>
                                </select>
                                @error('documentacion_legal') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>{{ $archivingToHistory ? 'Nueva fecha de inicio' : 'Inicio contrato' }}</label>
                                <input type="date" wire:model.defer="inicio_contrato" class="form-control">
                                @error('inicio_contrato') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>{{ $archivingToHistory ? 'Nueva fecha de finalizacion' : 'Fin contrato' }}</label>
                                <input type="date" wire:model.defer="fin_contrato" class="form-control">
                                @error('fin_contrato') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>Cobertura</label>
                                <input type="text" wire:model.defer="cobertura" class="form-control uppercase-input">
                                @error('cobertura') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label>Presupuesto</label>
                                <input type="number" step="0.01" min="0" wire:model.defer="presupuesto" class="form-control">
                                @error('presupuesto') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group form-span-2">
                                <label>{{ $archivingToHistory ? 'Nuevo documento PDF (opcional)' : 'Documento PDF' }}</label>
                                <input type="file" wire:model="documento_pdf_file" class="form-control" accept="application/pdf">
                                @error('documento_pdf_file') <small class="text-danger">{{ $message }}</small> @enderror
                                <div class="muted small mt-2">
                                    @if($archivingToHistory)
                                        El PDF actual sera copiado al historial. Si no eliges otro, continuara tambien como documento vigente.
                                    @else
                                        Puedes subir un PDF de contrato, convenio o adenda.
                                    @endif
                                    Tamano maximo: 50 MB.
                                </div>
                                @if ($documento_pdf_file)
                                    <div class="small text-info mt-1">PDF listo para guardar: {{ $documento_pdf_file->getClientOriginalName() }}</div>
                                @elseif (!empty($documento_pdf_path))
                                    <div class="mt-2">
                                        <a href="{{ asset('storage/' . $documento_pdf_path) }}" target="_blank" class="btn btn-sm btn-outline-azul">
                                            <i class="fas fa-file-pdf mr-1"></i> Abrir PDF actual
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            {{ $archivingToHistory ? 'Guardar en historial y actualizar' : ($editingId ? 'Guardar cambios' : 'Crear') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('input', (event) => {
        const target = event.target;
        if (target && target.classList && target.classList.contains('uppercase-input') && target.tagName === 'INPUT') {
            const start = target.selectionStart;
            const end = target.selectionEnd;
            target.value = target.value.toUpperCase();
            if (start !== null && end !== null) {
                target.setSelectionRange(start, end);
            }
        }
    });

    window.addEventListener('openEmpresaModal', () => {
        $('#empresaModal').modal('show');
    });

    window.addEventListener('closeEmpresaModal', () => {
        $('#empresaModal').modal('hide');
    });
</script>



