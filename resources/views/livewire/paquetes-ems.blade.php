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
            padding:18px 20px;
        }
        .header-app.is-almacen .header-app-tools{
            gap:16px;
            align-items:flex-end;
        }
        .header-app.is-create .header-app-main{
            padding-top:4px;
        }
        .header-app.is-create .header-app-tools{
            flex:0 0 430px;
            min-width:430px;
        }
        .header-app-shell{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:24px;
        }
        .header-app-main{
            flex:1 1 320px;
            min-width: 260px;
        }
        .header-app-tools{
            flex:1 1 560px;
            min-width: 320px;
            display:flex;
            flex-direction:column;
            gap:12px;
            align-items:stretch;
        }
        .header-tool-actions{
            display:flex;
            justify-content:flex-end;
        }
        .header-create-side{
            display:flex;
            flex-direction:column;
            gap:14px;
            padding:0;
        }
        .header-search-row{
            display:flex;
            justify-content:flex-end;
        }
        .header-search-box{
            width:min(100%, 760px);
        }
        .header-search-form{
            width:min(100%, 760px);
            display:flex;
            align-items:center;
            gap:10px;
        }
        .header-search-form .search-input{
            flex:1 1 auto;
        }
        .header-search-cluster{
            display:flex;
            justify-content:flex-end;
            gap:12px;
            width:100%;
            align-items:flex-start;
            flex-wrap:wrap;
        }
        .header-actions-row{
            display:flex;
            justify-content:flex-end;
            gap:12px;
            flex-wrap:wrap;
            align-items:flex-start;
        }
        .header-app:not(.is-almacen):not(.is-create) .header-actions-row{
            justify-content:flex-end;
            width:100%;
        }
        .header-actions-group{
            display:grid;
            grid-template-columns:repeat(3, minmax(180px, 1fr));
            gap:10px;
            align-items:stretch;
            width:min(100%, 760px);
        }
        .header-app:not(.is-almacen):not(.is-create) .header-actions-group{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            width:auto;
            justify-content:flex-end;
        }
        .header-app:not(.is-almacen):not(.is-create) .header-primary-action{
            flex:0 0 auto;
        }
        .header-app.is-almacen .header-actions-row{
            justify-content:flex-end;
            width:100%;
        }
        .header-app.is-almacen .header-actions-group{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            justify-content:flex-end;
            width:min(100%, 860px);
        }
        .header-app.is-almacen .header-search-cluster{
            justify-content:flex-end;
            width:min(100%, 980px);
        }
        .header-app.is-almacen .header-search-form{
            flex:1 1 auto;
            width:auto;
            min-width:420px;
        }
        .header-app.is-almacen .header-primary-action{
            flex:0 0 auto;
        }
        .header-app.is-ventanilla .header-app-main{
            flex:0 1 300px;
            max-width:320px;
            min-width:240px;
        }
        .header-app.is-ventanilla .header-app-tools{
            flex:1 1 860px;
            min-width:0;
            gap:16px;
            align-items:flex-end;
        }
        .header-app.is-ventanilla .header-search-row{
            width:100%;
        }
        .header-app.is-ventanilla .header-search-cluster{
            justify-content:flex-end;
            width:100%;
            flex-wrap:nowrap;
        }
        .header-app.is-ventanilla .header-search-form{
            flex:1 1 auto;
            width:auto;
            max-width:none;
            min-width:0;
        }
        .header-app.is-ventanilla .header-actions-row{
            justify-content:flex-end;
            width:100%;
        }
        .header-app.is-ventanilla .header-actions-group{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            justify-content:flex-end;
            width:auto;
            max-width:100%;
        }
        .header-app.is-ventanilla .header-actions-group > .btn,
        .header-app.is-ventanilla .header-actions-group > a{
            min-width:0;
            padding:10px 16px;
            flex:0 0 auto;
        }
        .header-primary-action{
            display:flex;
            align-items:center;
        }

        .search-input{
            border-radius:12px;
            border:1px solid rgba(255,255,255,.45);
            padding:10px 12px;
            background: rgba(255,255,255,.95);
        }
        .header-preregistro{
            width:100%;
            max-width: 470px;
        }
        .header-preregistro .header-preregistro-label{
            display:block;
            font-size:11px;
            font-weight:800;
            color: rgba(255,255,255,.88);
            margin-bottom:6px;
            letter-spacing:.02em;
        }
        .header-preregistro .header-preregistro-help{
            margin-top:6px;
            font-size:11px;
            color: rgba(255,255,255,.82);
            line-height:1.35;
        }
        .header-preregistro .header-preregistro-message{
            margin-top:6px;
            font-size:11px;
            font-weight:700;
            color:#d1fae5;
        }
        .header-preregistro .header-preregistro-message.is-error{
            color:#fee2e2;
        }
        .header-preregistro .search-input{
            box-shadow: 0 10px 24px rgba(15, 40, 82, .14);
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
        .header-actions-group > .btn,
        .header-actions-group > a{
            min-height:52px;
            justify-content:center;
            text-align:center;
            line-height:1.2;
        }
        .header-app:not(.is-almacen):not(.is-create) .header-actions-group > .btn,
        .header-app:not(.is-almacen):not(.is-create) .header-actions-group > a{
            min-width:0;
            flex:0 0 auto;
            padding:10px 16px;
        }
        .header-app.is-almacen .header-actions-group > .btn,
        .header-app.is-almacen .header-actions-group > a{
            min-height:46px;
            min-width:0;
            padding:10px 16px;
            flex:0 0 auto;
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
        .form-footer-actions{
            display:flex;
            justify-content:flex-end;
            gap:12px;
            flex-wrap:wrap;
        }
        .form-footer-cancel{
            min-width:160px;
            border-radius:12px;
            border:1px solid rgba(32, 83, 154, .22);
            background:#fff;
            color:var(--azul);
            font-weight:800;
            padding:10px 18px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            text-decoration:none;
        }
        .form-footer-cancel:hover{
            background:rgba(32, 83, 154, .05);
            color:var(--azul);
            text-decoration:none;
        }
        .form-footer-submit{
            min-width:190px;
            padding:10px 20px;
        }

        .table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .pill-id{
            background: rgba(52,68,124,.12);
            color: var(--azul);
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 999px;
            display:inline-block;
        }

        .badge-estado{
            background: rgba(185,156,70,.15);
            color: var(--dorado);
            border: 1px solid rgba(185,156,70,.35);
            font-weight: 800;
            padding: 6px 10px;
            border-radius: 999px;
        }

        .muted{ color:var(--muted); }

        .table td{ vertical-align: middle; }

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
        .form-control:focus, select.form-control:focus{
            border-color: var(--azul);
            box-shadow:0 0 0 0.15rem rgba(52,68,124,.15);
        }
        .section-block{
            border:1px solid var(--line);
            border-radius:14px;
            padding:16px;
            margin-bottom:16px;
            background:#f9fafb;
        }
        .section-title{
            font-size:12px;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:800;
            color:var(--muted);
            margin-bottom:12px;
        }
        .badge-pill{
            background: rgba(185,156,70,.15);
            color: var(--dorado);
            border: 1px solid rgba(185,156,70,.35);
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            font-size:11px;
        }
        .form-group label{
            font-weight:700;
            color:#1f2937;
        }
        .required-star{
            color:#dc2626;
            font-weight:900;
            margin-left:4px;
        }
        .required-note{
            background:#fff7ed;
            border:1px solid #fed7aa;
            color:#9a3412;
            border-radius:10px;
            padding:8px 12px;
            font-size:12px;
            font-weight:700;
            margin-bottom:12px;
            display:inline-block;
        }
        .header-meta{
            margin-top: 8px;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .header-meta-label{
            font-size: 12px;
            color: rgba(255,255,255,.8);
            font-weight: 700;
            letter-spacing: .02em;
        }
        .header-chip{
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(255,255,255,.45);
            color: #fff;
            font-weight: 800;
            font-size: 11px;
            border-radius: 999px;
            padding: 3px 10px;
            background: rgba(255,255,255,.1);
        }
        .header-city{
            font-size: 12px;
            color: rgba(255,255,255,.85);
            margin-left: 4px;
        }
        .action-cell{
            width: 78px;
            min-width: 78px;
            text-align: center;
        }
        .action-stack{
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:10px;
        }
        .action-btn{
            width:44px;
            height:44px;
            padding:0;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:12px;
            box-shadow:0 6px 16px rgba(32, 83, 154, .10);
        }
        .action-btn i{
            font-size:14px;
        }
        .action-btn.btn-azul{
            box-shadow:0 8px 18px rgba(32, 83, 154, .22);
        }
        .action-btn.btn-outline-azul{
            background:#fff;
            border-color:rgba(32, 83, 154, .22);
        }
        .action-btn.btn-outline-azul:hover{
            background:rgba(32, 83, 154, .06);
        }
        @media (max-width: 991.98px){
            .header-app.is-create .header-app-tools{
                flex:1 1 auto;
                min-width:0;
            }
            .header-app-shell{
                flex-direction:column;
            }
            .header-app-tools{
                width:100%;
                min-width:0;
            }
            .header-search-row,
            .header-actions-row{
                justify-content:flex-start;
            }
            .header-search-box{
                width:100%;
            }
            .header-search-form{
                width:100%;
            }
            .header-search-cluster{
                justify-content:flex-start;
            }
            .header-actions-group{
                grid-template-columns:repeat(2, minmax(180px, 1fr));
                width:100%;
            }
            .header-app:not(.is-almacen):not(.is-create) .header-actions-group{
                display:grid;
                grid-template-columns:repeat(2, minmax(180px, 1fr));
                width:100%;
                justify-content:stretch;
            }
            .header-app.is-almacen .header-actions-group{
                display:grid;
                grid-template-columns:repeat(2, minmax(180px, 1fr));
                width:100%;
                max-width:none;
                justify-content:stretch;
            }
            .header-app.is-almacen .header-search-cluster{ width:100%; }
            .header-app.is-almacen .header-search-form{ min-width:0; width:100%; }
            .header-app.is-ventanilla .header-search-cluster{ width:100%; }
            .header-app.is-ventanilla .header-search-form{ min-width:0; width:100%; }
            .header-app.is-ventanilla .header-actions-group{
                display:grid;
                grid-template-columns:repeat(2, minmax(180px, 1fr));
                width:100%;
                justify-content:stretch;
            }
        }
        @media (max-width: 575.98px){
            .header-tool-actions{
                justify-content:stretch;
            }
            .header-tool-actions > .btn,
            .header-search-cluster,
            .header-search-form{
                flex-direction:column;
                align-items:stretch;
            }
            .header-search-cluster > .btn,
            .header-search-cluster > a,
            .header-search-form > .btn,
            .header-actions-group,
            .header-primary-action{
                width:100%;
            }
            .header-actions-group{
                grid-template-columns:1fr;
            }
            .header-app:not(.is-almacen):not(.is-create) .header-actions-group{
                grid-template-columns:1fr;
            }
            .header-app.is-almacen .header-actions-group{
                grid-template-columns:1fr;
            }
            .header-app.is-ventanilla .header-actions-group{
                grid-template-columns:1fr;
            }
            .header-app.is-almacen .header-search-form{
                min-width:0;
                width:100%;
            }
            .header-actions-group > .btn,
            .header-actions-group > a,
            .header-primary-action > .btn,
            .header-primary-action > a{
                width:100%;
                justify-content:center;
            }
            .form-footer-actions{
                flex-direction:column;
            }
            .form-footer-cancel,
            .form-footer-submit{
                width:100%;
            }
        }
        .table-scroll-wrap{
            max-height: 56vh;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 12px;
            background:#fff;
        }
        .table-scroll-wrap .table{
            margin-bottom:0;
        }
        .table-scroll-wrap .table thead th{
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .table-scroll-wrap-sm{
            max-height: 36vh;
        }
        .autofill-input{
            border: 1px solid #f2c14e !important;
            background: linear-gradient(180deg, #fffdf7 0%, #ffffff 100%);
        }
        .submit-loader-note{
            display:flex;
            align-items:center;
            gap:10px;
            font-size:13px;
            font-weight:700;
            color:var(--azul);
            background:rgba(32, 83, 154, .08);
            border:1px solid rgba(32, 83, 154, .16);
            border-radius:12px;
            padding:10px 14px;
        }
        .submit-loader-note .spinner-border{
            width:1rem;
            height:1rem;
            border-width:.16em;
        }
        .autofill-input:focus{
            border-color: #d39c12 !important;
            box-shadow: 0 0 0 0.18rem rgba(242, 193, 78, .22) !important;
        }
        .autofill-helper{
            margin-top: 8px;
            font-size: 12px;
            line-height: 1.45;
            color: #74613a;
        }
        .autofill-panel{
            margin-top: 12px;
            border: 1px solid #f3dc93;
            border-radius: 14px;
            background: linear-gradient(135deg, #fff9e6 0%, #fffdf8 100%);
            padding: 14px 16px;
        }
        .autofill-panel-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .autofill-panel-title{
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #8a5a00;
        }
        .autofill-panel-badge{
            border: 1px solid #f3dc93;
            border-radius: 999px;
            background: #ffffff;
            color: #9a6700;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
        }
        .autofill-panel-text{
            font-size: 14px;
            color: #5f4a18;
            margin-bottom: 8px;
        }
        .autofill-panel-list{
            margin: 0;
            padding-left: 18px;
            color: #6b5721;
            font-size: 12px;
        }
        .autofill-panel-list li{
            margin-bottom: 2px;
        }
        .prelist-shell{
            border: 1px solid #dbe3f1;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(32,83,154,.08);
            padding: 12px;
        }
        .prelist-top{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:8px;
        }
        .prelist-title{
            font-size:13px;
            font-weight:900;
            letter-spacing:.04em;
            text-transform:uppercase;
            color:#1f3f78;
        }
        .prelist-kpis{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin-bottom:10px;
        }
        .prelist-kpi{
            border:1px solid #dbe3f1;
            background:#f7faff;
            border-radius:999px;
            padding:4px 10px;
            font-size:12px;
            color:#2d4b85;
            font-weight:700;
        }
        .prelist-controls{
            display:flex;
            gap:8px;
            align-items:center;
            flex-wrap:wrap;
            margin-bottom:10px;
        }
        .prelist-table-wrap{
            max-height: 340px;
            overflow:auto;
            border:1px solid #e5e7eb;
            border-radius:10px;
        }
        .prelist-table{
            margin-bottom:0;
            font-size: 12px;
        }
        .prelist-table thead th{
            position: sticky;
            top: 0;
            z-index: 2;
            background:#edf3ff;
            color:#1f3f78;
            font-weight:800;
        }
        .prelist-table tbody tr:nth-child(odd){
            background:#fcfdff;
        }
        .prelist-table .pill-id{
            font-size:11px;
            padding:3px 8px;
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app {{ $this->isCreateEms ? 'is-create' : '' }} {{ $this->isAlmacenEms ? 'is-almacen' : '' }} {{ $this->isVentanillaEms ? 'is-ventanilla' : '' }}">
                <div class="header-app-shell">
                <div class="header-app-main">
                    <h4 class="fw-bold mb-0">
                        @if ($this->isCreateEms)
                            Nuevo paquete EMS
                        @elseif ($this->isAlmacenEms)
                            Paquetes ALMACEN
                        @elseif ($this->isDevolucionEms)
                            Devolver paquetes
                        @elseif ($this->isEnTransitoEms)
                            Paquetes en transito
                        @elseif ($this->isVentanillaEms)
                            Ventanilla EMS
                        @elseif ($this->isTransitoEms)
                            Recibir de regional ({{ $this->regionalEstadoLabel }})
                        @else
                            Paquetes EMS
                        @endif
                    </h4>
                    @php
                        $ciudadUsuarioHeader = strtoupper(trim((string) optional(auth()->user())->ciudad));
                    @endphp
                    <div class="header-meta">
                        @if ($this->isCreateEms)
                            <span class="header-meta-label">Registro individual</span>
                            <span class="header-chip">ADMISIONES</span>
                            <span class="header-city">
                                Origen aplicado: <strong>{{ $ciudadUsuarioHeader !== '' ? $ciudadUsuarioHeader : 'SIN CIUDAD' }}</strong>
                            </span>
                        @else
                            <span class="header-meta-label">Estados visibles:</span>

                            @if ($this->isAlmacenEms)
                                @if ($this->almacenEstadoFiltro === 'ALMACEN')
                                    <span class="header-chip">ALMACEN</span>
                                @elseif ($this->almacenEstadoFiltro === 'RECIBIDO')
                                    <span class="header-chip">RECIBIDO</span>
                                @else
                                    <span class="header-chip">ALMACEN</span>
                                    <span class="header-chip">RECIBIDO</span>
                                @endif
                                <span class="header-city">
                                    Ciudad aplicada: <strong>{{ $ciudadUsuarioHeader !== '' ? $ciudadUsuarioHeader : 'SIN CIUDAD' }}</strong>
                                </span>
                            @elseif ($this->isEnTransitoEms)
                                <span class="header-chip">TRANSITO</span>
                                <span class="header-city">
                                    Origen aplicado: <strong>{{ $ciudadUsuarioHeader !== '' ? $ciudadUsuarioHeader : 'SIN CIUDAD' }}</strong>
                                </span>
                            @elseif ($this->isVentanillaEms)
                                <span class="header-chip">VENTANILLA EMS</span>
                            @elseif ($this->isDevolucionEms)
                                <span class="header-chip">ALMACEN</span>
                                <span class="header-chip">RECIBIDO</span>
                                <span class="header-chip">VENTANILLA EMS</span>
                                <span class="header-city">
                                    Ciudad aplicada: <strong>{{ $ciudadUsuarioHeader !== '' ? $ciudadUsuarioHeader : 'SIN CIUDAD' }}</strong>
                                </span>
                            @elseif ($this->isTransitoEms)
                                <span class="header-chip">{{ $this->regionalEstadoLabel }}</span>
                                @if (strtoupper(trim((string) $this->regionalEstadoLabel)) !== 'TRANSITO')
                                    <span class="header-chip">TRANSITO</span>
                                @endif
                            @else
                                <span class="header-chip">ADMISIONES</span>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="header-app-tools">
                    @if ($this->isCreateEms)
                        <div class="header-create-side">
                            <div class="header-tool-actions">
                                <a class="btn btn-outline-light2" href="{{ route('paquetes-ems.index') }}">
                                    Volver a Paquetes EMS
                                </a>
                            </div>
                            <div class="header-preregistro">
                                <label class="header-preregistro-label" for="headerPreregistroCodigo">Codigo de preregistro</label>
                                <input
                                    id="headerPreregistroCodigo"
                                    type="text"
                                    class="form-control search-input"
                                    placeholder="Pega PRE00000001 o 00000001"
                                    wire:model.live.debounce.400ms="preregistro_codigo"
                                >
                                <div class="header-preregistro-help">Pega aqui el codigo generado del preregistro y el formulario se autollenara.</div>
                                @if($preregistroAutofillMessage)
                                    <div class="header-preregistro-message {{ str_contains(strtolower($preregistroAutofillMessage), 'no existe') || str_contains(strtolower($preregistroAutofillMessage), 'ya fue validado') ? 'is-error' : '' }}">
                                        {{ $preregistroAutofillMessage }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="header-search-row">
                            <div class="header-search-cluster">
                                <div class="header-search-form">
                                    <input
                                        type="text"
                                        class="form-control search-input"
                                        placeholder="Buscar en toda la tabla..."
                                        wire:model="search"
                                        wire:keydown.enter.prevent="searchPaquetes(true)"
                                    >
                                    <button class="btn btn-outline-light2" type="button" wire:click="searchPaquetes(true)">Buscar</button>
                                </div>
                                @if ($this->isAlmacenEms && $canEmsCreate && $canEmsCreateRoute)
                                    <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                                @endif
                            </div>
                        </div>
                        <div class="header-actions-row">
                            @if (!$this->isAlmacenEms && ((($this->isAdmision && $canEmsAdmisionCreate) || ($this->isAlmacenEms && $canEmsCreate)) && $canEmsCreateRoute))
                                <div class="header-primary-action">
                                    <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                                </div>
                            @endif
                            <div class="header-actions-group">
                                @if ($this->isAdmision)
                                    @if ($canEmsAssign)
                                    <button class="btn btn-outline-light2" type="button" wire:click="mandarSeleccionadosGeneradosHoy">
                                        Generados hoy
                                    </button>

                                    <button class="btn btn-outline-light2" type="button" wire:click="mandarSeleccionadosSinFiltroFecha">
                                        Mandar seleccionados
                                    </button>
                                    @endif
                                @elseif ($this->isAlmacenEms)
                                    @if ($canEmsRegisterContract)
                                    <a class="btn btn-outline-light2" href="{{ route('paquetes-ems.contrato-rapido.create') }}" target="_blank" rel="noopener">
                                        Registrar contrato
                                    </a>
                                    @endif
                                    @if ($canEmsWeighContract)
                                    <button class="btn btn-outline-light2" type="button" wire:click="openContratoPesoModal">
                                        Anadir peso contrato
                                    </button>
                                    @endif
                                    @if ($canEmsWeighTiktoker)
                                    <button class="btn btn-outline-light2" type="button" wire:click="openTiktokerPesoModal">
                                        Asignar peso a TIKTOKEROS
                                    </button>
                                    @endif
                                    @if ($canEmsSendVentanilla)
                                    <button class="btn btn-outline-light2" type="button" wire:click="mandarSeleccionadosVentanillaEms">
                                        Enviar a ventanilla EMS
                                    </button>
                                    @endif
                                    @if ($canEmsSendRegional)
                                    <button class="btn btn-outline-light2" type="button" wire:click="toggleCn33Assign">
                                        Anadir a CN-33
                                    </button>
                                    @endif
                                    @if ($canEmsSendRegional)
                                    <button class="btn btn-outline-light2" type="button" wire:click="openRegionalModal">
                                        Manda a regional
                                    </button>
                                    @endif
                                    @if ($canEmsReprintCn33)
                                    <button class="btn btn-outline-light2" type="button" wire:click="toggleCn33Reprint">
                                        Reimprimir CN-33
                                    </button>
                                    @endif
                                @elseif ($this->isVentanillaEms)
                                    @if ($canEmsDeliver)
                                    <button class="btn btn-outline-light2" type="button" wire:click="openEntregaVentanillaModal">
                                        Entregar seleccionados
                                    </button>
                                    @endif
                                @elseif ($this->isDevolucionEms)
                                    @if ($canEmsDeliver)
                                    <button class="btn btn-outline-light2" type="button" wire:click="openDevolucionEmsModal">
                                        Devolver seleccionados
                                    </button>
                                    @endif
                                @elseif ($this->isTransitoEms)
                                    @if ($canEmsAssign)
                                    <button class="btn btn-outline-light2" type="button" wire:click="toggleRecibirRegionalCn33Input">
                                        Recibir todos del CN-33
                                    </button>
                                    <button class="btn btn-outline-light2" type="button" wire:click="openRecibirRegionalModal">
                                        Recibir
                                    </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3">
                    <p class="mb-0">{{ session('success') }}</p>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger m-3">
                    <p class="mb-0">{{ session('error') }}</p>
                </div>
            @endif

            @if ($this->isTransitoEms && $showRecibirRegionalCn33Input)
                <div class="m-3 mb-0 p-3 border rounded" style="background:#f8fbff; border-color:#d6e2ff !important;">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-7 mb-2 mb-md-0">
                            <label class="mb-1 fw-bold">Pegar codigo CN-33</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ej: TDD00003"
                                wire:model.defer="recibirRegionalCn33"
                                wire:keydown.enter.prevent="prepararRecibirRegionalPorCn33"
                            >
                            <small class="text-muted">Al confirmar, se cargan y seleccionan automaticamente todos los registros de ese CN-33.</small>
                        </div>
                        <div class="form-group col-md-5 mb-0 d-flex gap-2 justify-content-md-end">
                            <button class="btn btn-outline-secondary" type="button" wire:click="toggleRecibirRegionalCn33Input">
                                Cancelar
                            </button>
                            <button class="btn btn-primary" type="button" wire:click="prepararRecibirRegionalPorCn33">
                                Cargar CN-33
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card-body">
                @if ($this->isCreateEms)
                    <form wire:submit.prevent="save">
                        <div class="required-note">
                            Campos con <span class="required-star">*</span> son obligatorios.
                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos generales</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Servicio<span class="required-star">*</span></label>
                                    <select wire:model.live="servicio_id" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($servicios as $servicio)
                                            <option value="{{ $servicio->id }}">{{ $servicio->nombre_servicio }}</option>
                                        @endforeach
                                    </select>
                                    @error('servicio_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Destino<span class="required-star">*</span></label>
                                    <select wire:model.live="destino_id" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($destinos as $destino)
                                            <option value="{{ $destino->id }}">{{ $destino->nombre_destino }}</option>
                                        @endforeach
                                    </select>
                                    @error('destino_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Origen (automatico)</label>
                                    <input type="text" wire:model.defer="origen" class="form-control" readonly>
                                    @error('origen') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Tipo de correspondencia</label>
                                    <input type="text" wire:model.defer="tipo_correspondencia" class="form-control">
                                    @error('tipo_correspondencia') <small class="text-danger">{{ $message }}</small> @enderror
                                    <small class="text-muted">Si es OFICIAL, se guarda sin precio ni tarifario.</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Servicio especial</label>
                                    <select wire:model.defer="servicio_especial" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="IDA">IDA</option>
                                        <option value="POR COBRAR">POR COBRAR</option>
                                        <option value="IDA Y VUELTA">IDA Y VUELTA</option>
                                    </select>
                                    @error('servicio_especial') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label>Contenido<span class="required-star">*</span></label>
                                    <textarea wire:model.defer="contenido" class="form-control" rows="2" required></textarea>
                                    @error('contenido') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Cantidad<span class="required-star">*</span></label>
                                    <input type="number" wire:model.defer="cantidad" class="form-control" min="1" required>
                                    @error('cantidad') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <x-peso-qz-field
                                        model="peso"
                                        input-id="peso-create-ems"
                                        :required="true"
                                        :use-scale="true"
                                        :show-clear="true"
                                        :live="true"
                                    />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Precio</label>
                                    <input type="number" wire:model.defer="precio" class="form-control" step="0.01" min="0" readonly>
                                    @error('precio') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Codigo<span class="required-star">*</span></label>
                                    <input type="text" wire:model.defer="codigo" class="form-control" @if($auto_codigo) readonly @endif required>
                                    @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-8 d-flex align-items-center" style="padding-top:28px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="autoCodigoCreate" wire:model.live="auto_codigo">
                                        <label class="form-check-label" for="autoCodigoCreate">
                                            Generar codigo automatico
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos del remitente</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre remitente<span class="required-star">*</span></label>
                                    <input type="text" wire:model.defer="nombre_remitente" class="form-control" required>
                                    @error('nombre_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Telefono remitente<span class="required-star">*</span></label>
                                    <input type="text" wire:model.defer="telefono_remitente" class="form-control" required>
                                    @error('telefono_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Carnet remitente<span class="required-star">*</span></label>
                                    <input
                                        type="text"
                                        wire:model.defer="carnet"
                                        class="form-control"
                                        required
                                    >
                                    @error('carnet') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Empresa</label>
                                    <input type="text" wire:model.defer="nombre_envia" class="form-control">
                                    @error('nombre_envia') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos del destinatario</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre destinatario<span class="required-star">*</span></label>
                                    <input type="text" wire:model.defer="nombre_destinatario" class="form-control" required>
                                    @error('nombre_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Telefono destinatario</label>
                                    <input type="text" wire:model.defer="telefono_destinatario" class="form-control">
                                    @error('telefono_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Direccion destinatario<span class="required-star">*</span></label>
                                    <input type="text" wire:model.defer="direccion" class="form-control" required>
                                    @error('direccion') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Referencia</label>
                                    <input type="text" wire:model.defer="referencia" class="form-control">
                                    @error('referencia') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Ciudad destinatario</label>
                                    <select wire:model.defer="ciudad" class="form-control" disabled>
                                        <option value="">Seleccione...</option>
                                        @foreach($ciudades as $ciudadOpt)
                                            <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                                        @endforeach
                                    </select>
                                    @error('ciudad') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="submit-loader-note" wire:loading.flex wire:target="save, saveConfirmed">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span>Guardando admision EMS, espera un momento...</span>
                            </div>
                            <div class="d-flex justify-content-end gap-2 ml-auto">
                            <a href="{{ route('paquetes-ems.index') }}" class="btn btn-outline-azul">Cancelar</a>
                            @if ($canEmsCreate)
                            <button
                                type="button"
                                wire:click="save"
                                wire:loading.attr="disabled"
                                wire:target="save, saveConfirmed"
                                class="btn btn-dorado"
                            >
                                <span wire:loading.remove wire:target="save">Crear y continuar</span>
                                <span wire:loading.inline-flex wire:target="save" class="align-items-center">
                                    <span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
                                    Guardando...
                                </span>
                            </button>
                            @endif
                            </div>
                        </div>
                    </form>
                @else
                @if ($this->isAlmacenEms && $showCn33Reprint && $canEmsReprintCn33)
                    <div class="section-block mb-3">
                        <div class="section-title">Reimprimir CN-33</div>
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-6 mb-2">
                                <label>Despacho</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa cod_especial (ej: SRZ00001)"
                                    wire:model.defer="cn33Despacho"
                                >
                            </div>
                            <div class="form-group col-md-6 mb-2 d-flex gap-2">
                                @if ($canEmsReprintCn33)
                                <button class="btn btn-azul" type="button" wire:click="reimprimirCn33">
                                    Imprimir CN-33
                                </button>
                                <button class="btn btn-outline-azul" type="button" wire:click="toggleCn33Reprint">
                                    Cerrar
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($this->isAlmacenEms && $showCn33Assign && $canEmsSendRegional)
                    <div class="section-block mb-3">
                        <div class="section-title">Anadir a CN-33</div>
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-6 mb-2">
                                <label>Cod. especial (CN-33)</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa cod_especial (ej: SRZ00001)"
                                    wire:model.defer="cn33ManualCodigo"
                                >
                            </div>
                            <div class="form-group col-md-6 mb-2 d-flex gap-2">
                                <button
                                    class="btn btn-azul"
                                    type="button"
                                    wire:click="anadirSeleccionadosCn33"
                                    onclick="return confirm('Asignar este cod_especial a los seleccionados y cambiarlos a TRANSITO?')"
                                >
                                    Confirmar
                                </button>
                                <button class="btn btn-outline-azul" type="button" wire:click="toggleCn33Assign">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando todos los registros
                        @endif
                    </div>
                    @if ($this->canSelect)
                        @php
                            $seleccionadosTotal = ($this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms)
                                ? (count($selectedPaquetes) + count($selectedContratos) + count($selectedSolicitudes))
                                : count($selectedPaquetes);
                        @endphp
                        <div class="muted small">
                            Total en pagina: <strong>{{ $paquetes->count() }}</strong> |
                            Seleccionados: <strong>{{ $seleccionadosTotal }}</strong>
                        </div>
                    @else
                        <div class="muted small">
                            Total en pagina: <strong>{{ $paquetes->count() }}</strong>
                        </div>
                    @endif
                </div>

                @php
                    $seleccionadosTotalGlobal = (count($selectedPaquetes) + count($selectedContratos) + count($selectedSolicitudes));
                @endphp
                @if ($this->canUseSelectedPreview && $seleccionadosTotalGlobal > 0)
                    <div class="prelist-shell mb-3">
                        @php
                            $selectedPreviewBase = collect($selectedPreviewRows ?? collect());
                            $selectedPreviewEms = $selectedPreviewBase->where('tipo', 'EMS')->count();
                            $selectedPreviewContratos = $selectedPreviewBase->where('tipo', 'CONTRATO')->count();
                            $selectedPreviewSolicitudes = $selectedPreviewBase->where('tipo', 'SOLICITUD')->count();
                            $selectedPreviewFiltered = $selectedPreviewBase;
                            if (($selectedPreviewType ?? 'TODOS') !== 'TODOS') {
                                $selectedPreviewFiltered = $selectedPreviewFiltered->where('tipo', $selectedPreviewType);
                            }
                            if (!empty($selectedPreviewSearch ?? '')) {
                                $needle = mb_strtolower(trim((string) $selectedPreviewSearch));
                                $selectedPreviewFiltered = $selectedPreviewFiltered->filter(function ($row) use ($needle) {
                                    $haystack = mb_strtolower(
                                        trim((string) ($row->codigo ?? '')) . ' ' .
                                        trim((string) ($row->destinatario ?? '')) . ' ' .
                                        trim((string) ($row->destino ?? ''))
                                    );
                                    return str_contains($haystack, $needle);
                                });
                            }
                        @endphp
                        <div class="prelist-top">
                            <div class="prelist-title">Prelista de seleccionados ({{ $seleccionadosTotalGlobal }})</div>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="toggleSelectedPreview">
                                    {{ $showSelectedPreview ? 'Ocultar prelista' : 'Mostrar prelista' }}
                                </button>
                                <button class="btn btn-sm btn-outline-danger" type="button" wire:click="clearSelectedPreview"
                                    onclick="return confirm('Deseas limpiar toda la seleccion?')">
                                    Limpiar seleccion
                                </button>
                            </div>
                        </div>
                        <div class="prelist-kpis">
                            <span class="prelist-kpi">EMS: <strong>{{ $selectedPreviewEms }}</strong></span>
                            <span class="prelist-kpi">CONTRATO: <strong>{{ $selectedPreviewContratos }}</strong></span>
                            <span class="prelist-kpi">SOLICITUD: <strong>{{ $selectedPreviewSolicitudes }}</strong></span>
                        </div>
                        @if ($showSelectedPreview)
                            <div class="prelist-controls">
                                <select wire:model.live="selectedPreviewType" class="form-control" style="max-width: 180px;">
                                    <option value="TODOS">Todos</option>
                                    <option value="EMS">EMS</option>
                                    <option value="CONTRATO">Contrato</option>
                                    <option value="SOLICITUD">Solicitud</option>
                                </select>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="selectedPreviewSearch"
                                    class="form-control"
                                    style="max-width: 320px;"
                                    placeholder="Filtrar en prelista (codigo, destinatario, destino)"
                                >
                            </div>
                            <div class="prelist-table-wrap">
                                <table class="table table-sm table-hover align-middle prelist-table">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Codigo</th>
                                            <th>Destinatario</th>
                                            <th>Destino</th>
                                            <th>Peso</th>
                                            <th>Accion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($selectedPreviewFiltered as $item)
                                            <tr wire:key="prelista-item-{{ $item->tipo ?? 'X' }}-{{ $item->record_id ?? 0 }}">
                                                <td>{{ $item->tipo ?? '-' }}</td>
                                                <td><span class="pill-id">{{ $item->codigo ?? '-' }}</span></td>
                                                <td>{{ $item->destinatario ?? '-' }}</td>
                                                <td>{{ $item->destino ?? '-' }}</td>
                                                <td>{{ $item->peso ?? '-' }}</td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        wire:click="removeSelectedPreviewItem('{{ $item->tipo ?? '' }}', {{ (int) ($item->record_id ?? 0) }})"
                                                        title="Quitar de seleccion"
                                                    >
                                                        Quitar
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-3 text-muted">No hay elementos para ese filtro.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="table-scroll-wrap">
                    <div class="table-responsive">
                    @php
                        $fechaGeneracionReporte = '10/03/2026 18:36:44';
                    @endphp
                    <table class="table table-hover align-middle">
                        <thead>
                            @if ($this->isEnTransitoEms)
                                <tr>
                                    <th>Codigo</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Cod. especial</th>
                                    <th>Traspaso</th>
                                </tr>
                            @elseif ($this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms)
                                <tr>
                                    @if ($this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms)
                                        <th></th>
                                    @endif
                                    <th>Codigo</th>
                                    <th>Tipo</th>
                                    <th>Servicio</th>
                                    <th>Serv. especial</th>
                                    <th>Destino</th>
                                    <th>Contenido</th>
                                    <th>Cantidad</th>
                                    <th>Peso</th>
                                    <th>Remitente</th>
                                    <th>Destinatario</th>
                                    <th>Empresa</th>
                                    <th>Telefono R</th>
                                    <th>Telefono D</th>
                                    <th>Creado</th>
                                    <th>Traspaso</th>
                                    <th class="text-center action-cell">Acciones</th>
                                </tr>
                            @else
                                <tr>
                                    @if ($this->canSelect)
                                        <th></th>
                                    @endif
                                    <th>Codigo</th>
                                    <th>Tipo</th>
                                    <th>Serv. especial</th>
                                    <th>Servicio</th>
                                    <th>Destino</th>
                                    <th>Contenido</th>
                                    <th>Cantidad</th>
                                    <th>Peso</th>
                                    <th>Nombre remitente</th>
                                    <th>Empresa</th>
                                    <th>Carnet</th>
                                    <th>Telefono remitente</th>
                                    <th>Nombre destinatario</th>
                                    <th>Telefono destinatario</th>
                                    <th>Ciudad</th>
                                    <th>Traspaso</th>
                                    <th class="text-center action-cell">Acciones</th>
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @if ($this->isEnTransitoEms)
                                @forelse ($paquetes as $row)
                                    <tr>
                                        <td><span class="pill-id">{{ $row->codigo }}</span></td>
                                        <td>{{ $row->origen ?: '-' }}</td>
                                        <td>{{ $row->destino ?: '-' }}</td>
                                        <td>{{ $row->cod_especial ?: '-' }}</td>
                                        <td>{{ $fechaGeneracionReporte }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                            <div class="muted">Prueba con otro texto de busqueda.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            @elseif ($this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms)
                                @forelse ($paquetes as $row)
                                    <tr>
                                        @if ($this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms)
                                            <td>
                                                @if (($row->record_type ?? '') === 'EMS')
                                                    <input type="checkbox" value="{{ $row->record_id }}" wire:model="selectedPaquetes" wire:key="check-almacen-ems-{{ $row->record_id }}">
                                                @elseif (($row->record_type ?? '') === 'CONTRATO')
                                                    <input type="checkbox" value="{{ $row->record_id }}" wire:model="selectedContratos" wire:key="check-almacen-contrato-{{ $row->record_id }}">
                                                @else
                                                    <input type="checkbox" value="{{ $row->record_id }}" wire:model="selectedSolicitudes" wire:key="check-almacen-solicitud-{{ $row->record_id }}">
                                                @endif
                                            </td>
                                        @endif
                                        <td><span class="pill-id">{{ $row->codigo }}</span></td>
                                        <td>{{ $row->tipo }}</td>
                                        <td>{{ $row->servicio }}</td>
                                        <td>{{ $row->servicio_especial }}</td>
                                        <td>{{ $row->destino }}</td>
                                        <td>{{ $row->contenido }}</td>
                                        <td>{{ $row->cantidad }}</td>
                                        <td>{{ $row->peso }}</td>
                                        <td>{{ $row->remitente }}</td>
                                        <td>{{ $row->destinatario }}</td>
                                        <td>{{ $row->empresa }}</td>
                                        <td>{{ $row->telefono_r }}</td>
                                        <td>{{ $row->telefono_d }}</td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                                        <td>{{ $fechaGeneracionReporte }}</td>
                                        <td class="action-cell">
                                            <div class="action-stack">
                                            @if (($row->record_type ?? '') === 'EMS')
                                                @if ($this->isAlmacenEms || $this->isTransitoEms)
                                                    @if ($canEmsEdit)
                                                    <button wire:click="openEditModal({{ $row->record_id }})"
                                                        class="btn btn-sm btn-azul action-btn"
                                                        title="Editar">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    @endif
                                                @endif
                                                @if ($canEmsPrint)
                                                <a href="{{ route('paquetes-ems.boleta', $row->record_id, false) }}"
                                                   class="btn btn-sm btn-outline-azul action-btn"
                                                   target="_blank"
                                                   title="Reimprimir boleta">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                @endif
                                                @if ($this->isAlmacenEms)
                                                    @if ($canEmsRestore)
                                                    <button wire:click="devolverAAdmisiones({{ $row->record_id }})"
                                                        class="btn btn-sm btn-outline-azul action-btn"
                                                        title="Devolver a ADMISIONES"
                                                        onclick="return confirm('Seguro que deseas devolver este paquete a ADMISIONES?')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                    @endif
                                                @endif
                                            @else
                                                @if (($row->record_type ?? '') === 'CONTRATO')
                                                    <a href="{{ route('paquetes-contrato.reporte', $row->record_id, false) }}"
                                                       target="_blank"
                                                       class="btn btn-sm btn-outline-azul action-btn"
                                                       title="Reimprimir rotulo">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                @else
                                                    <a href="{{ route('paquetes-ems.solicitudes.ticket', $row->record_id) }}"
                                                       target="_blank"
                                                       class="btn btn-sm btn-outline-azul action-btn"
                                                       title="Imprimir ticket de solicitud">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                @endif
                                            @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms) ? 17 : 16 }}" class="text-center py-5">
                                            <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                            <div class="muted">Prueba con otro texto de busqueda.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            @else
                                @forelse ($paquetes as $paquete)
                                    @php
                                        $formulario = $paquete->formulario;
                                    @endphp
                                    <tr>
                                        @if ($this->canSelect)
                                            <td>
                                                <input type="checkbox" value="{{ $paquete->id }}" wire:model="selectedPaquetes" wire:key="check-admision-ems-{{ $paquete->id }}">
                                            </td>
                                        @endif
                                        <td><span class="pill-id">{{ $paquete->codigo }}</span></td>
                                        <td>{{ $formulario->tipo_correspondencia ?? $paquete->tipo_correspondencia }}</td>
                                        <td>{{ $formulario->servicio_especial ?? $paquete->servicio_especial }}</td>
                                        <td>{{ $paquete->servicio_nombre ?? '' }}</td>
                                        <td>{{ $paquete->destino_nombre ?? '' }}</td>
                                        <td>{{ $formulario->contenido ?? $paquete->contenido }}</td>
                                        <td>{{ $formulario->cantidad ?? $paquete->cantidad }}</td>
                                        <td>{{ $formulario->peso ?? $paquete->peso }}</td>
                                        <td>{{ $formulario->nombre_remitente ?? $paquete->nombre_remitente }}</td>
                                        <td>{{ $formulario->nombre_envia ?? $paquete->nombre_envia }}</td>
                                        <td>{{ $formulario->carnet ?? $paquete->carnet }}</td>
                                        <td>{{ $formulario->telefono_remitente ?? $paquete->telefono_remitente }}</td>
                                        <td>{{ $formulario->nombre_destinatario ?? $paquete->nombre_destinatario }}</td>
                                        <td>{{ $formulario->telefono_destinatario ?? $paquete->telefono_destinatario }}</td>
                                        <td>{{ $formulario->ciudad ?? $paquete->ciudad }}</td>
                                        <td>{{ $fechaGeneracionReporte }}</td>
                                        <td class="action-cell">
                                            <div class="action-stack">
                                            @if ($canEmsEdit)
                                            <button wire:click="openEditModal({{ $paquete->id }})"
                                                class="btn btn-sm btn-azul action-btn"
                                                title="Editar">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            @endif
                                            @if ($canEmsPrint)
                                            <a href="{{ route('paquetes-ems.boleta', $paquete->id, false) }}"
                                               class="btn btn-sm btn-outline-azul action-btn"
                                               target="_blank"
                                               title="Reimprimir boleta">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            @if ($this->isAdmision)
                                                @if ($canEmsDelete)
                                                <button wire:click="delete({{ $paquete->id }})"
                                                    class="btn btn-sm btn-outline-azul action-btn"
                                                    title="Cancelar"
                                                    onclick="return confirm('Seguro que deseas cancelar este paquete?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                @endif
                                            @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $this->canSelect ? 18 : 17 }}" class="text-center py-5">
                                            <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                            <div class="muted">Prueba con otro texto de busqueda.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                    </div>
                </div>

                @if (method_exists($paquetes, 'links'))
                    <div class="d-flex justify-content-end">
                        {{ $paquetes->links() }}
                    </div>
                @endif

                @if (false && $this->isAlmacenEms)
                    <div class="section-block mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold" style="color:var(--azul);">
                                Paquetes contrato en ALMACEN (misma ciudad)
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="muted small">Por pagina:</span>
                                    <select class="form-control form-control-sm" style="width:95px;" wire:model.live="perPageContratos">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="250">250</option>
                                        <option value="500">500</option>
                                        <option value="1000">1000</option>
                                    </select>
                                </div>
                                @if ($canEmsRegisterContract)
                                <button class="btn btn-outline-azul btn-sm" type="button" wire:click="openContratoRegistrarModal">
                                    Registrar
                                </button>
                                @endif
                                @if ($canEmsWeighContract)
                                <button class="btn btn-outline-azul btn-sm" type="button" wire:click="openContratoPesoModal">
                                    Anadir peso
                                </button>
                                @endif
                                @if ($canEmsSendRegional)
                                <button class="btn btn-outline-azul btn-sm" type="button" wire:click="openRegionalContratoModal">
                                    Manda contratos a regional
                                </button>
                                @endif
                                <div class="muted small">
                                    Total en pagina: <strong>{{ $contratosAlmacen ? $contratosAlmacen->count() : 0 }}</strong> |
                                    Seleccionados: <strong>{{ count($selectedContratos) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="table-scroll-wrap table-scroll-wrap-sm">
                            <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Codigo</th>
                                        <th>Cod. especial</th>
                                         <th>Estado</th>
                                         <th>Origen</th>
                                         <th>Destino</th>
                                         <th>Cantidad</th>
                                         <th>Remitente</th>
                                         <th>Destinatario</th>
                                         <th>Empresa</th>
                                        <th>Telefono R</th>
                                        <th>Telefono D</th>
                                        <th>Creado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($contratosAlmacen ?? [] as $contrato)
                                        <tr>
                                            <td>
                                                <input type="checkbox" value="{{ $contrato->id }}" wire:model="selectedContratos">
                                            </td>
                                            <td><span class="pill-id">{{ $contrato->codigo }}</span></td>
                                            <td>{{ $contrato->cod_especial ?: '-' }}</td>
                                             <td>{{ optional($contrato->estadoRegistro)->nombre_estado ?? '-' }}</td>
                                             <td>{{ $contrato->origen }}</td>
                                             <td>{{ $contrato->destino }}</td>
                                             <td>{{ $contrato->cantidad ?: '-' }}</td>
                                             <td>{{ $contrato->nombre_r }}</td>
                                             <td>{{ $contrato->nombre_d }}</td>
                                            <td>
                                                {{ optional($contrato->empresa)->nombre ?? optional(optional($contrato->user)->empresa)->nombre ?? '-' }}
                                                @if(!empty(optional($contrato->empresa)->sigla))
                                                    ({{ optional($contrato->empresa)->sigla }})
                                                @elseif(!empty(optional(optional($contrato->user)->empresa)->sigla))
                                                    ({{ optional(optional($contrato->user)->empresa)->sigla }})
                                                @endif
                                            </td>
                                            <td>{{ $contrato->telefono_r }}</td>
                                            <td>{{ $contrato->telefono_d ?: '-' }}</td>
                                            <td>{{ optional($contrato->created_at)->format('d/m/Y H:i') }}</td>
                                            <td>
                                                @if ($canContratoAlmacenPrint)
                                                <a href="{{ route('paquetes-contrato.reporte', $contrato->id, false) }}"
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-azul"
                                                   title="Reimprimir rotulo">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13" class="text-center py-4">
                                                <div class="fw-bold" style="color:var(--azul);">No hay contratos en ALMACEN</div>
                                                <div class="muted">Se muestran solo contratos en estado ALMACEN y origen de tu ciudad.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                        </div>

                        @if ($contratosAlmacen)
                            <div class="d-flex justify-content-end">
                                {{ $contratosAlmacen->links() }}
                            </div>
                        @endif
                    </div>
                @endif
                @endif
            </div>
        </div>
    </div>

    @unless($this->isCreateEms)
    <div class="modal fade" id="paqueteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingId ? 'Editar paquete' : 'Nuevo paquete' }}
                        </h5>
                        <span class="badge-pill">Formulario EMS</span>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="section-block">
                            <div class="section-title">Datos generales</div>

                            @if ($this->isAdmision && !$editingId)
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label>Codigo de preregistro</label>
                                        <input
                                            type="text"
                                            wire:model.live.debounce.400ms="preregistro_codigo"
                                            class="form-control autofill-input"
                                            placeholder="Ejemplo: PRE00000001 o 00000001"
                                        >
                                        <div class="autofill-helper">Pega el codigo del preregistro para autollenar los datos del envio. Acepta formato con PRE o solo el numero.</div>
                                        @if($preregistroAutofillMessage)
                                            <small class="{{ str_contains(strtolower($preregistroAutofillMessage), 'no existe') || str_contains(strtolower($preregistroAutofillMessage), 'ya fue validado') ? 'text-danger' : 'text-success' }}">
                                                {{ $preregistroAutofillMessage }}
                                            </small>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="form-row">
                                @if (!$this->isAlmacenEms)
                                    <div class="form-group col-md-6">
                                        <label>Servicio</label>
                                        <select wire:model.live="servicio_id" class="form-control">
                                            <option value="">Seleccione...</option>
                                            @foreach($servicios as $servicio)
                                                <option value="{{ $servicio->id }}">{{ $servicio->nombre_servicio }}</option>
                                            @endforeach
                                        </select>
                                        @error('servicio_id') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                @endif
                                <div class="form-group col-md-6">
                                    <label>Destino</label>
                                    <select wire:model.live="destino_id" class="form-control">
                                        <option value="">Seleccione...</option>
                                        @foreach($destinos as $destino)
                                            <option value="{{ $destino->id }}">{{ $destino->nombre_destino }}</option>
                                        @endforeach
                                    </select>
                                    @error('destino_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            @if (!$this->isAlmacenEms)
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Origen (automatico)</label>
                                        <input type="text" wire:model.defer="origen" class="form-control" readonly>
                                        @error('origen') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Tipo de correspondencia</label>
                                        <input
                                            type="text"
                                            wire:model.defer="tipo_correspondencia"
                                            class="form-control"
                                            @if($this->isAlmacenEms) readonly @endif
                                        >
                                        @error('tipo_correspondencia') <small class="text-danger">{{ $message }}</small> @enderror
                                        <small class="text-muted">Si es OFICIAL, se guarda sin precio ni tarifario.</small>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Servicio especial</label>
                                        <select wire:model.defer="servicio_especial" class="form-control">
                                            <option value="">Seleccione...</option>
                                            <option value="IDA">IDA</option>
                                            <option value="POR COBRAR">POR COBRAR</option>
                                            <option value="IDA Y VUELTA">IDA Y VUELTA</option>
                                        </select>
                                        @error('servicio_especial') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                </div>
                            @endif

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label>Contenido</label>
                                    <textarea wire:model.defer="contenido" class="form-control" rows="2"></textarea>
                                    @error('contenido') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Cantidad</label>
                                    <input type="number" wire:model.defer="cantidad" class="form-control" min="1">
                                    @error('cantidad') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Peso</label>
                                    <input
                                        type="number"
                                        id="peso-edit-ems"
                                        wire:model.live.debounce.300ms="peso"
                                        class="form-control"
                                        step="0.001"
                                        min="0"
                                    >
                                    @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            @if (!$this->isAlmacenEms)
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Precio</label>
                                        <input type="number" wire:model.defer="precio" class="form-control" step="0.01" min="0" readonly>
                                        @error('precio') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Codigo</label>
                                        <input type="text" wire:model.defer="codigo" class="form-control" @if($auto_codigo) readonly @endif>
                                        @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group col-md-8 d-flex align-items-center" style="padding-top:28px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="autoCodigo" wire:model.live="auto_codigo">
                                            <label class="form-check-label" for="autoCodigo">
                                                Generar codigo automatico
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos del remitente</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre remitente</label>
                                    <input type="text" wire:model.defer="nombre_remitente" class="form-control">
                                    @error('nombre_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                @if (!$this->isAlmacenEms)
                                    <div class="form-group col-md-6">
                                        <label>Telefono remitente<span class="required-star">*</span></label>
                                        <input type="text" wire:model.defer="telefono_remitente" class="form-control" required>
                                        @error('telefono_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                @endif
                            </div>

                            @if (!$this->isAlmacenEms)
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Carnet remitente</label>
                                        <input
                                            type="text"
                                            wire:model.defer="carnet"
                                            class="form-control"
                                        >
                                        @error('carnet') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Empresa</label>
                                        <input type="text" wire:model.defer="nombre_envia" class="form-control">
                                        @error('nombre_envia') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                </div>

                            @endif
                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos del destinatario</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre destinatario</label>
                                    <input type="text" wire:model.defer="nombre_destinatario" class="form-control">
                                    @error('nombre_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                @if (!$this->isAlmacenEms)
                                    <div class="form-group col-md-6">
                                        <label>Telefono destinatario</label>
                                        <input type="text" wire:model.defer="telefono_destinatario" class="form-control">
                                        @error('telefono_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                @endif
                            </div>

                            @if (!$this->isAlmacenEms)
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Direccion destinatario</label>
                                        <input type="text" wire:model.defer="direccion" class="form-control">
                                        @error('direccion') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Referencia</label>
                                        <input type="text" wire:model.defer="referencia" class="form-control">
                                        @error('referencia') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Ciudad destinatario</label>
                                        <select wire:model.defer="ciudad" class="form-control" disabled>
                                            <option value="">Seleccione...</option>
                                            @foreach($ciudades as $ciudadOpt)
                                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                                            @endforeach
                                        </select>
                                        @error('ciudad') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        @if ($editingId ? $canEmsEdit : $canEmsCreate)
                        <button type="submit" class="btn btn-primary">
                            {{ $editingId ? 'Guardar cambios' : 'Crear' }}
                        </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endunless

    <div
        class="modal fade @if($showPaqueteConfirmModal) show d-block @endif"
        id="paqueteConfirmModal"
        tabindex="-1"
        aria-hidden="{{ $showPaqueteConfirmModal ? 'false' : 'true' }}"
        wire:ignore.self
        @if($showPaqueteConfirmModal)
            style="background: rgba(0, 0, 0, 0.5);"
        @endif
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar datos</h5>
                    <button type="button" class="close" wire:click="closePaqueteConfirmModal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="section-block">
                        <div class="section-title">Resumen</div>
                        <div class="row">
                            <div class="col-md-6 mb-2"><strong>Destino:</strong>
                                {{ optional(collect($destinos)->firstWhere('id', (int) $destino_id))->nombre_destino }}
                            </div>
                            <div class="col-md-12 mb-2"><strong>Contenido:</strong> {{ $contenido }}</div>
                            <div class="col-md-4 mb-2"><strong>Cantidad:</strong> {{ $cantidad }}</div>
                            <div class="col-md-4 mb-2"><strong>Peso:</strong> {{ $peso }}</div>
                            <div class="col-md-6 mb-2"><strong>Remitente:</strong> {{ $nombre_remitente }}</div>
                            <div class="col-md-6 mb-2"><strong>Destinatario:</strong> {{ $nombre_destinatario }}</div>
                            @if (!$this->isAlmacenEms)
                                <div class="col-md-6 mb-2"><strong>Servicio:</strong>
                                    {{ optional(collect($servicios)->firstWhere('id', (int) $servicio_id))->nombre_servicio }}
                                </div>
                                <div class="col-md-6 mb-2"><strong>Origen:</strong> {{ $origen }}</div>
                                <div class="col-md-6 mb-2"><strong>Tipo:</strong> {{ $tipo_correspondencia }}</div>
                                <div class="col-md-4 mb-2"><strong>Precio:</strong> {{ $precio_confirm ?? $precio }}</div>
                                <div class="col-md-6 mb-2"><strong>Codigo:</strong> {{ $codigo }}</div>
                                <div class="col-md-6 mb-2"><strong>Ciudad:</strong> {{ $ciudad }}</div>
                                <div class="col-md-6 mb-2"><strong>Telefono remitente:</strong> {{ $telefono_remitente }}</div>
                                <div class="col-md-6 mb-2"><strong>Telefono destinatario:</strong> {{ $telefono_destinatario }}</div>
                                <div class="col-md-6 mb-2"><strong>Direccion destinatario:</strong> {{ $direccion }}</div>
                                <div class="col-md-6 mb-2"><strong>Referencia:</strong> {{ $referencia }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="submit-loader-note mr-auto" wire:loading.flex wire:target="saveConfirmed">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span>Confirmando y guardando la admision...</span>
                    </div>
                    <button
                        type="button"
                        class="btn btn-secondary"
                        wire:click="closePaqueteConfirmModal"
                        wire:loading.attr="disabled"
                        wire:target="saveConfirmed"
                    >
                        Cancelar
                    </button>
                    @if ($editingId ? $canEmsEdit : $canEmsCreate)
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="saveConfirmed"
                        wire:loading.attr="disabled"
                        wire:target="saveConfirmed"
                    >
                        <span wire:loading.remove wire:target="saveConfirmed">
                            {{ $this->isCreateEms ? 'Confirmar y volver' : 'Confirmar y guardar' }}
                        </span>
                        <span wire:loading.inline-flex wire:target="saveConfirmed" class="align-items-center">
                            <span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
                            Guardando...
                        </span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="generadosHoyModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar generados hoy</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if ($generadosHoyCount > 0)
                        <p class="mb-0">
                            Se enviaran <strong>{{ $generadosHoyCount }}</strong> paquete(s) generados hoy desde
                            <strong>ADMISIONES</strong> a <strong>ALMACEN EMS</strong>.
                        </p>
                    @else
                        <p class="mb-0 text-muted">
                            No hay paquetes generados hoy en ADMISIONES para enviar.
                        </p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsAssign)
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="confirmarMandarGeneradosHoy"
                        @if($generadosHoyCount <= 0) disabled @endif
                    >
                        Confirmar envio
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="regionalModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Ciudad destino regional</label>
                        <select wire:model.defer="regionalDestino" class="form-control">
                            <option value="">Seleccione...</option>
                            @foreach($ciudades as $ciudadOpt)
                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modo de transporte</label>
                        <select wire:model.defer="regionalTransportMode" class="form-control">
                            <option value="TERRESTRE">TERRESTRE</option>
                            <option value="AEREO">AEREO</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label>Nro de vuelo/transporte (opcional)</label>
                        <input type="text" wire:model.defer="regionalTransportNumber" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsSendRegional)
                    <button type="button" class="btn btn-primary" wire:click="mandarSeleccionadosRegional">
                        Confirmar y generar manifiesto
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="regionalContratoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar contratos a regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Ciudad destino regional</label>
                        <select wire:model.defer="regionalDestinoContrato" class="form-control">
                            <option value="">Seleccione...</option>
                            @foreach($ciudades as $ciudadOpt)
                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modo de transporte</label>
                        <select wire:model.defer="regionalTransportModeContrato" class="form-control">
                            <option value="TERRESTRE">TERRESTRE</option>
                            <option value="AEREO">AEREO</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label>Nro de vuelo/transporte (opcional)</label>
                        <input type="text" wire:model.defer="regionalTransportNumberContrato" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsSendRegional)
                    <button type="button" class="btn btn-primary" wire:click="mandarSeleccionadosContratosRegional">
                        Confirmar y generar manifiesto
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="regionalMismatchModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Advertencia de destino regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Seguro que quieres mandar estos paquetes a <strong>{{ $regionalMismatchDestino ?: 'LA REGIONAL SELECCIONADA' }}</strong>.
                    </p>
                    <p class="mb-2">
                        Su destino es:
                    </p>

                    <div class="border rounded p-2 mb-3" style="max-height: 280px; overflow-y: auto;">
                        @forelse($regionalMismatchItems as $item)
                            <div>
                                <strong>{{ $item['codigo'] ?? 'SIN CODIGO' }}</strong> - {{ $item['destino'] ?? 'SIN DESTINO' }}
                            </div>
                        @empty
                            <div class="text-muted">No hay diferencias de destino.</div>
                        @endforelse
                    </div>

                    <div class="alert mb-0 text-white border-0" style="background:#c1121f; font-size:1.45rem; font-weight:800; line-height:1.6; padding:1.35rem 1.5rem;">
                        ESTA REENCAMINANDO PAQUETES O USANDO CIUDAD INTERMEDIO SI NO ES ASI REVISALO POR FAVOR LOS PAQUETES QUE ESTAS MANDANDO A LA REGIONAL.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsSendRegional)
                    <button type="button" class="btn btn-warning" wire:click="confirmarEnvioRegionalConDestinoDiferente">
                        Si, mandar
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="contratoRegistrarModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar contrato rapido</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Codigo</label>
                        <input
                            type="text"
                            class="form-control"
                            wire:model.defer="registroContratoCodigo"
                            placeholder="Ej: C0007A02011BO"
                        >
                        @error('registroContratoCodigo') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group">
                        <label>Origen (usuario logueado)</label>
                        <input type="text" class="form-control" wire:model="registroContratoOrigen" readonly>
                    </div>

                    <div class="form-group">
                        <label>Destino</label>
                        <select class="form-control" wire:model.defer="registroContratoDestino">
                            <option value="">Seleccione...</option>
                            @foreach($ciudades as $ciudadOpt)
                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                            @endforeach
                        </select>
                        @error('registroContratoDestino') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group mb-0">
                        <x-peso-qz-field
                            model="registroContratoPeso"
                            input-id="peso-create-ems-contrato-rapido"
                            min="0.001"
                            :required="true"
                            :use-scale="true"
                            :show-clear="true"
                        />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsRegisterContract)
                    <button type="button" class="btn btn-primary" wire:click="registrarContratoRapido">
                        Registrar
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="contratoPesoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Anadir peso a contrato</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Codigo</label>
                        <div class="d-flex gap-2">
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Pega el codigo y presiona Enter"
                                wire:model.defer="contratoCodigoPeso"
                                wire:keydown.enter.prevent="buscarContratoParaPeso"
                            >
                            @if ($canEmsWeighContract)
                            <button type="button" class="btn btn-outline-azul" wire:click="buscarContratoParaPeso">
                                Detectar
                            </button>
                            @endif
                        </div>
                        @error('contratoCodigoPeso') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    @if (!empty($contratoPesoResumen))
                        <div class="section-block mb-3">
                            <div class="section-title">Contrato detectado</div>
                            <div><strong>Codigo:</strong> {{ $contratoPesoResumen['codigo'] ?? '-' }}</div>
                            <div><strong>Cod. especial:</strong> {{ ($contratoPesoResumen['cod_especial'] ?? '') !== '' ? $contratoPesoResumen['cod_especial'] : '-' }}</div>
                            <div><strong>Origen:</strong> {{ $contratoPesoResumen['origen'] ?? '-' }}</div>
                            <div><strong>Remitente:</strong> {{ $contratoPesoResumen['remitente'] ?? '-' }}</div>
                            <div><strong>Destinatario:</strong> {{ $contratoPesoResumen['destinatario'] ?? '-' }}</div>
                        </div>
                    @endif

                    <div class="form-group">
                        <x-peso-qz-field
                            model="contratoPeso"
                            input-id="peso-contrato-modal"
                            min="0.001"
                            :required="true"
                            :use-scale="true"
                            :show-clear="true"
                        />
                    </div>

                    <div class="form-group mb-0">
                        <label>Destino (opcional)</label>
                        <input type="text" class="form-control" wire:model.defer="contratoDestino" placeholder="Solo si deseas cambiar destino">
                        @error('contratoDestino') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsWeighContract)
                    <button type="button" class="btn btn-primary" wire:click="guardarPesoContratoPorCodigo">
                        Guardar peso
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tiktokerPesoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar peso a TIKTOKEROS</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Codigo de solicitud</label>
                        <div class="d-flex gap-2">
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Pega el codigo SOL y presiona Enter"
                                wire:model.defer="tiktokerCodigoPeso"
                                wire:keydown.enter.prevent="buscarSolicitudTiktokerParaPeso"
                            >
                            @if ($canEmsWeighTiktoker)
                            <button type="button" class="btn btn-outline-azul" wire:click="buscarSolicitudTiktokerParaPeso">
                                Detectar
                            </button>
                            @endif
                        </div>
                        @error('tiktokerCodigoPeso') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    @if (!empty($tiktokerPesoResumen))
                        <div class="section-block mb-3">
                            <div class="section-title">Solicitud detectada</div>
                            <div><strong>Codigo:</strong> {{ $tiktokerPesoResumen['codigo'] ?? '-' }}</div>
                            <div><strong>Origen:</strong> {{ $tiktokerPesoResumen['origen'] ?? '-' }}</div>
                            <div><strong>Destino:</strong> {{ $tiktokerPesoResumen['destino'] ?? '-' }}</div>
                            <div><strong>Remitente:</strong> {{ $tiktokerPesoResumen['remitente'] ?? '-' }}</div>
                            <div><strong>Destinatario:</strong> {{ $tiktokerPesoResumen['destinatario'] ?? '-' }}</div>
                            <div><strong>Precio actual:</strong> {{ ($tiktokerPesoResumen['precio'] ?? null) !== null ? 'Bs ' . $tiktokerPesoResumen['precio'] : '-' }}</div>
                        </div>
                    @endif

                    <div class="form-group mb-0">
                        <x-peso-qz-field
                            model="tiktokerPeso"
                            input-id="peso-tiktoker-modal"
                            min="0.001"
                            :required="true"
                            :use-scale="true"
                            :show-clear="true"
                        />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsWeighTiktoker)
                    <button type="button" class="btn btn-primary" wire:click="guardarPesoSolicitudTiktoker">
                        Guardar peso
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="entregaVentanillaModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar entrega</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Recibido por</label>
                        <input type="text" class="form-control" wire:model.defer="entregaRecibidoPor">
                        @error('entregaRecibidoPor') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group mb-0">
                        <label>Descripcion (opcional)</label>
                        <textarea class="form-control" rows="3" wire:model.defer="entregaDescripcion"></textarea>
                        @error('entregaDescripcion') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsDeliver)
                    <button type="button" class="btn btn-primary" wire:click="confirmarEntregaVentanilla">
                        Confirmar entrega
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="devolucionEmsModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar devolucion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Recibido por</label>
                        <input type="text" class="form-control" wire:model.defer="devolucionRecibidoPor">
                        @error('devolucionRecibidoPor') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group">
                        <label>Detalle de devolucion (opcional)</label>
                        <textarea class="form-control" rows="3" wire:model.defer="devolucionDescripcion"></textarea>
                        @error('devolucionDescripcion') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group mb-0">
                        <label>Imagen de la guia</label>
                        <input type="file" class="form-control-file" wire:model="devolucionImagen" accept="image/*">
                        @error('devolucionImagen') <small class="text-danger d-block">{{ $message }}</small> @enderror
                        <small class="text-muted">Se guardara la misma imagen para los registros seleccionados.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsDeliver)
                    <button type="button" class="btn btn-primary" wire:click="confirmarDevolucionEms">
                        Confirmar devolucion
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="recibirRegionalModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar recepcion regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        Registros a recibir: <strong>{{ count($recibirRegionalPreview) }}</strong>
                    </div>
                    <div class="alert alert-info py-2">
                        Revisa el peso de cada registro. Si esta correcto, solo confirma la recepcion.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tipo</th>
                                    <th>Codigo</th>
                                    <th>Remitente</th>
                                    <th>Destinatario</th>
                                    <th>Ciudad</th>
                                    <th>Peso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recibirRegionalPreview as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $item['tipo'] ?? '-' }}</td>
                                        <td><span class="pill-id">{{ $item['codigo'] ?: 'SIN CODIGO' }}</span></td>
                                        <td>{{ $item['nombre_remitente'] ?: '-' }}</td>
                                        <td>{{ $item['nombre_destinatario'] ?: '-' }}</td>
                                        <td>{{ $item['ciudad'] ?: '-' }}</td>
                                        <td style="min-width: 140px;">
                                            <input
                                                type="number"
                                                class="form-control form-control-sm"
                                                wire:model.defer="recibirRegionalPesos.{{ $item['peso_key'] }}"
                                                step="0.001"
                                                min="0"
                                            >
                                            @error('recibirRegionalPesos.' . ($item['peso_key'] ?? ''))
                                                <small class="text-danger d-block mt-1">{{ $message }}</small>
                                            @enderror
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No hay registros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsAssign)
                    <button type="button" class="btn btn-primary" wire:click="recibirSeleccionadosRegional">
                        Confirmar recibido
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<x-peso-qz-assets />

@once
<script>
    (() => {
        if (window.__emsPaquetesUiInit) {
            return;
        }
        window.__emsPaquetesUiInit = true;

        const modalMap = {
            openPaqueteModal: '#paqueteModal',
            closePaqueteModal: '#paqueteModal',
            openPaqueteConfirm: '#paqueteConfirmModal',
            closePaqueteConfirm: '#paqueteConfirmModal',
            openGeneradosHoyModal: '#generadosHoyModal',
            closeGeneradosHoyModal: '#generadosHoyModal',
            openRegionalModal: '#regionalModal',
            closeRegionalModal: '#regionalModal',
            openRegionalContratoModal: '#regionalContratoModal',
            closeRegionalContratoModal: '#regionalContratoModal',
            openRegionalMismatchModal: '#regionalMismatchModal',
            closeRegionalMismatchModal: '#regionalMismatchModal',
            openContratoRegistrarModal: '#contratoRegistrarModal',
            closeContratoRegistrarModal: '#contratoRegistrarModal',
            openContratoPesoModal: '#contratoPesoModal',
            closeContratoPesoModal: '#contratoPesoModal',
            openTiktokerPesoModal: '#tiktokerPesoModal',
            closeTiktokerPesoModal: '#tiktokerPesoModal',
            openEntregaVentanillaModal: '#entregaVentanillaModal',
            closeEntregaVentanillaModal: '#entregaVentanillaModal',
            openDevolucionEmsModal: '#devolucionEmsModal',
            closeDevolucionEmsModal: '#devolucionEmsModal',
            openRecibirRegionalModal: '#recibirRegionalModal',
            closeRecibirRegionalModal: '#recibirRegionalModal',
        };

        const handleModalEvent = (eventName, selector) => {
            if (!window.jQuery || !$(selector).length) {
                return;
            }

            const action = eventName.startsWith('open') ? 'show' : 'hide';
            $(selector).modal(action);
        };

        Object.entries(modalMap).forEach(([eventName, selector]) => {
            window.addEventListener(eventName, () => handleModalEvent(eventName, selector));
            document.addEventListener(eventName, () => handleModalEvent(eventName, selector));
        });

        document.addEventListener('livewire:init', () => {
            Object.entries(modalMap).forEach(([eventName, selector]) => {
                if (!window.Livewire || typeof window.Livewire.on !== 'function') {
                    return;
                }

                window.Livewire.on(eventName, () => handleModalEvent(eventName, selector));
            });
        });
    })();
</script>
@endonce

