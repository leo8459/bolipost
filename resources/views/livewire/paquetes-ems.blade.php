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
            flex:0 1 auto;
            gap:16px;
            align-items:stretch;
            width:100%;
            min-width:0;
        }
        .header-app.is-almacen .header-app-shell{
            flex-direction:column;
            align-items:stretch;
            gap:18px;
        }
        .header-app.is-almacen .header-app-main{
            width:100%;
            flex:0 0 auto;
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
            justify-content:stretch;
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
            display:grid;
            grid-template-columns:minmax(0, 1fr) auto;
            justify-content:stretch;
            align-items:stretch;
            width:100%;
        }
        .header-app.is-almacen .header-search-form{
            flex:1 1 auto;
            width:auto;
            min-width:0;
            max-width:none;
        }
        .ems-action-groups{
            display:grid;
            grid-template-columns:repeat(3, minmax(0, 1fr));
            align-items:stretch;
            flex:1 1 100%;
            gap:12px;
            width:100%;
        }
        .ems-action-groups--compact{
            grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
        }
        .ems-action-group{
            min-width:0;
            padding:13px;
            border:1px solid rgba(255,255,255,.2);
            border-radius:14px;
            background:rgba(255,255,255,.09);
        }
        .ems-action-heading{
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom:11px;
        }
        .ems-action-heading-icon{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:34px;
            height:34px;
            flex:0 0 34px;
            border-radius:10px;
            color:#fff;
            background:rgba(255,255,255,.15);
        }
        .ems-action-heading strong{
            display:block;
            color:#fff;
            font-size:12px;
            line-height:1.2;
            letter-spacing:.045em;
            text-transform:uppercase;
        }
        .ems-action-heading small{
            display:block;
            margin-top:3px;
            color:rgba(255,255,255,.72);
            font-size:11px;
            line-height:1.3;
        }
        .ems-action-list{
            display:flex;
            flex-wrap:wrap;
            align-items:stretch;
            gap:8px;
        }
        .ems-action-button{
            display:inline-flex;
            align-items:center;
            justify-content:flex-start;
            gap:8px;
            flex:1 1 auto;
            min-width:0;
            min-height:42px;
            padding:9px 11px;
            border:1px solid rgba(255,255,255,.75);
            border-radius:10px;
            background:#fff;
            color:#20539A;
            font-size:12px;
            font-weight:800;
            line-height:1.25;
            cursor:pointer;
            text-align:left;
            text-decoration:none;
            box-shadow:0 3px 8px rgba(10,31,68,.08);
            transition:background-color .15s ease, border-color .15s ease, transform .15s ease;
        }
        .ems-action-button:hover,
        .ems-action-button:focus{
            border-color:#FECC36;
            background:#fff9e6;
            color:#173f78;
            text-decoration:none;
            transform:translateY(-1px);
        }
        .ems-action-button:focus-visible{
            outline:3px solid rgba(254,204,54,.7);
            outline-offset:2px;
        }
        .ems-action-button i{
            flex:0 0 auto;
            width:15px;
            color:#6b83a8;
            text-align:center;
        }
        .ems-action-button--primary{
            border-color:rgba(254,204,54,.9);
            background:#FECC36;
            color:#173f78;
        }
        .ems-action-button--primary i{ color:#173f78; }
        .ems-action-button--primary:hover,
        .ems-action-button--primary:focus{
            border-color:#f2bd1c;
            background:#f8c526;
            color:#173f78;
        }
        .ems-action-button--danger,
        .ems-action-button--danger i{
            color:#a52834;
        }
        .ems-action-button--danger:hover,
        .ems-action-button--danger:focus{
            border-color:#efb3b8;
            background:#fff1f2;
            color:#842029;
        }
        .ems-primary-action{
            min-height:44px;
            padding:10px 18px;
            border:1px solid rgba(254,204,54,.9);
            border-radius:10px;
            white-space:nowrap;
            background:#FECC36;
            color:#173f78;
            font-weight:800;
            box-shadow:0 5px 14px rgba(15,40,82,.18);
        }
        .ems-primary-action:hover,
        .ems-primary-action:focus{
            border-color:#f2bd1c;
            background:#f8c526;
            color:#173f78;
        }
        .ems-search-button{
            min-height:44px;
            padding:10px 18px;
            border:1px solid rgba(255,255,255,.75);
            border-radius:10px;
            background:#fff;
            color:#20539A;
            font-weight:800;
            box-shadow:0 3px 8px rgba(10,31,68,.08);
        }
        .ems-search-button:hover,
        .ems-search-button:focus{
            border-color:#FECC36;
            background:#fff9e6;
            color:#173f78;
        }
        .ems-header-back{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            min-height:42px;
            padding:9px 14px;
            border:1px solid rgba(255,255,255,.75);
            border-radius:10px;
            background:#fff;
            color:#20539A;
            font-size:12px;
            font-weight:800;
            text-decoration:none;
        }
        .ems-header-back:hover,
        .ems-header-back:focus{
            border-color:#FECC36;
            background:#fff9e6;
            color:#173f78;
            text-decoration:none;
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
        .cod-especial-btn{
            border:0;
            background:rgba(32, 83, 154, .10);
            color:var(--azul);
            font-weight:900;
            border-radius:999px;
            padding:6px 12px;
            cursor:pointer;
            white-space:nowrap;
        }
        .cod-especial-btn:hover{
            background:rgba(32, 83, 154, .18);
            color:var(--azul);
        }
        .cod-especial-summary{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            border:1px solid #dbe2f2;
            background:#f8fafc;
            border-radius:14px;
            padding:12px 14px;
            margin-bottom:14px;
        }
        .cod-especial-summary .pill-id{ font-size:15px; }
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
        .form-option-card{
            min-height:74px;
            display:flex;
            align-items:center;
            padding:12px 14px;
            border:1px solid #dbe2ea;
            border-radius:10px;
            background:#fff;
        }
        .form-option-card .form-check{
            margin:0;
            padding-left:1.35rem;
        }
        .form-option-card .form-text{
            line-height:1.35;
        }
        .confirm-summary{
            display:grid;
            gap:14px;
        }
        .confirm-summary-group{
            padding:16px;
            border:1px solid #dfe5ec;
            border-radius:12px;
            background:#fff;
        }
        .confirm-summary-group-title{
            margin:0 0 12px;
            color:var(--azul);
            font-size:13px;
            font-weight:900;
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .confirm-summary-grid{
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:12px 20px;
        }
        .confirm-summary-item{
            min-width:0;
            padding-bottom:9px;
            border-bottom:1px solid #edf0f4;
        }
        .confirm-summary-item.is-wide{
            grid-column:1 / -1;
        }
        .confirm-summary-label{
            display:block;
            margin-bottom:3px;
            color:var(--muted);
            font-size:11px;
            font-weight:800;
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .confirm-summary-value{
            display:block;
            color:#172033;
            font-size:14px;
            font-weight:700;
            line-height:1.4;
            overflow-wrap:anywhere;
        }
        @media (max-width: 767.98px){
            .confirm-summary-grid{ grid-template-columns:1fr; }
            .confirm-summary-item.is-wide{ grid-column:auto; }
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
            width: 128px;
            min-width: 128px;
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
            .ems-action-groups:not(.ems-action-groups--compact){
                grid-template-columns:repeat(2, minmax(0, 1fr));
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
            .header-app.is-almacen .header-search-cluster{
                grid-template-columns:1fr;
                gap:9px;
            }
            .ems-action-groups{
                grid-template-columns:1fr;
                gap:9px;
            }
            .ems-action-group{
                padding:12px;
            }
            .ems-action-list{
                display:grid;
                grid-template-columns:1fr;
            }
            .ems-action-button{
                width:100%;
            }
            .ems-primary-action,
            .ems-search-button,
            .ems-header-back{
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
        .header-app.is-devolucion{
            background:linear-gradient(135deg,#24589b 0%,#1b4a86 100%);
            padding:22px clamp(16px,2.2vw,30px);
        }
        .header-app.is-devolucion .header-app-shell{
            display:grid;
            grid-template-columns:minmax(0,1fr);
            gap:18px;
        }
        .header-app.is-devolucion .header-app-main{
            min-width:0;
            width:100%;
        }
        .header-app.is-devolucion .header-app-main h4{
            font-size:clamp(1.35rem,2vw,1.75rem);
            letter-spacing:-.02em;
        }
        .header-app.is-devolucion .header-chip{
            background:rgba(255,255,255,.16);
            border-color:rgba(255,255,255,.42);
        }
        .header-app.is-devolucion .header-app-tools{
            display:grid;
            grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);
            align-items:stretch;
            gap:16px;
            width:100%;
            min-width:0;
        }
        .header-app.is-devolucion .header-search-row,
        .header-app.is-devolucion .header-search-cluster,
        .header-app.is-devolucion .header-search-form{
            width:100%;
            max-width:none;
            min-width:0;
        }
        .header-app.is-devolucion .header-search-row,
        .header-app.is-devolucion .header-search-cluster{
            justify-content:stretch;
        }
        .header-app.is-devolucion .header-search-form .search-input{
            min-width:0;
            box-shadow:0 5px 16px rgba(10,31,68,.13);
        }
        .header-app.is-devolucion .header-actions-row{
            display:block;
            width:100%;
        }
        .header-app.is-devolucion .ems-action-groups--compact{
            display:block;
            width:100%;
        }
        .header-app.is-devolucion .ems-action-group{
            height:100%;
            padding:15px 16px;
            border-color:rgba(255,255,255,.28);
            background:rgba(255,255,255,.12);
            box-shadow:inset 0 1px rgba(255,255,255,.08),0 8px 20px rgba(10,31,68,.12);
        }
        .header-app.is-devolucion .ems-action-heading{
            margin-bottom:12px;
        }
        .header-app.is-devolucion .ems-action-heading-icon{
            background:rgba(255,255,255,.2);
        }
        .header-app.is-devolucion .ems-action-heading small{
            color:rgba(255,255,255,.86);
        }
        .header-app.is-devolucion .ems-action-list--devolucion{
            display:block;
        }
        .header-app.is-devolucion .ems-action-primary-stack{
            display:grid;
            gap:9px;
        }
        .header-app.is-devolucion .ems-action-primary-stack .ems-action-button{
            justify-content:center;
            width:100%;
            min-height:46px;
        }
        .header-app.is-devolucion .ems-action-help{
            display:flex;
            align-items:flex-start;
            gap:7px;
            padding:0 3px;
            color:rgba(255,255,255,.92);
            font-size:11px;
            line-height:1.4;
        }
        .header-app.is-devolucion .ems-action-help i{
            flex:0 0 auto;
            margin-top:1px;
            color:#ffda64;
        }
        @media (max-width: 991.98px){
            .header-app.is-devolucion .header-app-shell{gap:16px;}
        }
        @media (max-width: 767.98px){
            .header-app.is-devolucion{padding:18px 16px;}
            .header-app.is-devolucion .header-app-tools{grid-template-columns:minmax(0,1fr);gap:12px;}
            .header-app.is-devolucion .header-actions-row{padding:0;}
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
        .table-scroll-wrap--devolucion{
            max-height: 62vh;
        }
        .table-devolucion-ems{
            width:100%;
            min-width:1432px;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing:0;
        }
        .table-devolucion-ems th,
        .table-devolucion-ems td{
            padding:.75rem .55rem;
            overflow-wrap:normal;
            word-break:normal;
        }
        .table-devolucion-ems thead th{
            background:#eef1f7;
            color:#20539A;
            border-bottom:1px solid #d7deea;
            text-align:center;
        }
        .table-devolucion-ems tbody td{
            background:#f8f9fb;
            border-top:1px solid #e2e6ed;
            color:#344054;
        }
        .table-devolucion-ems.table-hover tbody tr:hover td{
            background:#f0f5ff;
        }
        .table-devolucion-ems tbody td:first-child,
        .table-devolucion-ems .action-cell{
            text-align:center;
        }
        .table-devolucion-ems th:nth-child(1){width:42px;}
        .table-devolucion-ems th:nth-child(2){width:150px;}
        .table-devolucion-ems th:nth-child(3){width:150px;}
        .table-devolucion-ems th:nth-child(4){width:245px;}
        .table-devolucion-ems th:nth-child(5){width:325px;}
        .table-devolucion-ems th:nth-child(6){width:72px;}
        .table-devolucion-ems th:nth-child(7){width:170px;}
        .table-devolucion-ems th:nth-child(8){width:150px;}
        .table-devolucion-ems th:nth-child(9){width:128px;}
        .table-devolucion-ems td:nth-child(2),
        .table-devolucion-ems td:nth-child(3),
        .table-devolucion-ems td:nth-child(6),
        .table-devolucion-ems td:nth-child(8),
        .table-devolucion-ems td:nth-child(9){white-space:nowrap;}
        .table-devolucion-ems td:nth-child(2) .pill-id{white-space:nowrap;}
        .table-ems-admision{
            width:100%;
            min-width:1407px;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing:0;
        }
        .table-ems-admision th,
        .table-ems-admision td{
            padding:.75rem .55rem;
            overflow-wrap:normal;
            word-break:normal;
        }
        .table-ems-admision thead th{
            background:#eef1f7;
            color:#20539A;
            border-bottom:1px solid #d7deea;
            text-align:center;
        }
        .table-ems-admision tbody td{
            background:#f8f9fb;
            border-top:1px solid #e2e6ed;
            color:#344054;
        }
        .table-ems-admision.table-hover tbody tr:hover td{background:#f0f5ff;}
        .table-ems-admision th:nth-child(1){width:42px;}
        .table-ems-admision th:nth-child(2){width:150px;}
        .table-ems-admision th:nth-child(3){width:95px;}
        .table-ems-admision th:nth-child(4){width:130px;}
        .table-ems-admision th:nth-child(5){width:215px;}
        .table-ems-admision th:nth-child(6){width:290px;}
        .table-ems-admision th:nth-child(7){width:72px;}
        .table-ems-admision th:nth-child(8){width:145px;}
        .table-ems-admision th:nth-child(9){width:140px;}
        .table-ems-admision th:nth-child(10){width:128px;}
        .table-ems-admision td:nth-child(2),
        .table-ems-admision td:nth-child(3),
        .table-ems-admision td:nth-child(4),
        .table-ems-admision td:nth-child(7),
        .table-ems-admision td:nth-child(9),
        .table-ems-admision td:nth-child(10){white-space:nowrap;}
        .table-ems-admision .pill-id,
        .table-ems-admision .ems-admision-type{white-space:nowrap;}
        .table-ems-admision .ems-admision-type{
            display:inline-block;
            padding:5px 9px;
            border-radius:999px;
            background:#edf1f7;
            color:#475467;
            font-size:11px;
            font-weight:800;
        }
        .table-ems-admision .ems-admision-route{
            display:grid;
            grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);
            align-items:center;
            gap:8px;
        }
        .table-ems-admision .ems-admision-route span,
        .table-ems-admision .ems-admision-address span{
            display:block;
            color:#718096;
            font-size:10px;
            font-weight:800;
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .table-ems-admision .ems-admision-route strong{
            display:block;
            color:#243b64;
            font-size:12px;
            line-height:1.35;
        }
        .table-ems-admision .ems-admision-address div{
            font-size:12px;
            line-height:1.45;
        }
        .table-ems-admision .ems-admision-address div + span{margin-top:8px;}
        .table-ems-admision .action-cell{width:128px;min-width:128px;}
        @media (max-width: 767.98px){
            .table-scroll-wrap--admision{
                max-height:none;
                overflow:visible;
                border:0;
                background:transparent;
            }
            .table-scroll-wrap--admision > .table-responsive{overflow:visible;}
            .table-ems-admision{
                min-width:0;
                table-layout:auto;
                border-spacing:0 12px;
                margin-top:-12px;
            }
            .table-ems-admision thead{display:none;}
            .table-ems-admision tbody{display:grid;grid-template-columns:1fr;gap:12px;}
            .table-ems-admision tbody tr{
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                overflow:hidden;
                border:1px solid #e2e8f0;
                border-radius:14px;
                background:#fff;
                box-shadow:0 5px 16px rgba(15,23,42,.06);
            }
            .table-ems-admision tbody tr:hover{background:#fff;}
            .table-ems-admision tbody td{
                display:block;
                min-width:0;
                width:auto;
                padding:9px 12px;
                border:0;
                background:#fff;
                white-space:normal !important;
                overflow-wrap:anywhere;
            }
            .table-ems-admision tbody td::before{
                content:attr(data-label);
                display:block;
                margin-bottom:4px;
                color:#718096;
                font-size:10px;
                font-weight:800;
                letter-spacing:.04em;
                text-transform:uppercase;
            }
            .table-ems-admision tbody td:first-child{
                grid-column:1 / -1;
                display:flex;
                align-items:center;
                justify-content:space-between;
                padding-bottom:0;
            }
            .table-ems-admision tbody td:nth-child(2),
            .table-ems-admision tbody td:nth-child(4),
            .table-ems-admision tbody td:nth-child(5),
            .table-ems-admision tbody td:nth-child(6),
            .table-ems-admision tbody td:nth-child(9),
            .table-ems-admision tbody td:nth-child(10),
            .table-ems-admision tbody td[colspan]{grid-column:1 / -1;}
            .table-ems-admision .action-cell{width:auto;min-width:0;border-top:1px solid #edf0f4;}
            .table-ems-admision .action-stack{flex-direction:row;justify-content:flex-start;flex-wrap:wrap;gap:8px;}
        }
        .devolucion-direcciones__line + .devolucion-direcciones__line{margin-top:8px;}
        .devolucion-direcciones__label{
            display:block;
            color:#7b8797;
            font-size:10px;
            font-weight:800;
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .devolucion-direcciones__value{
            color:#344054;
            font-size:12px;
            line-height:1.45;
        }
        .devolucion-route{
            display:grid;
            grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);
            align-items:center;
            gap:8px;
        }
        .devolucion-route__point{min-width:0;}
        .devolucion-route__label{
            display:block;
            margin-bottom:2px;
            color:#718096;
            font-size:10px;
            font-weight:800;
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .devolucion-route__value{
            display:block;
            color:#243b64;
            font-size:12px;
            font-weight:800;
            line-height:1.3;
        }
        .devolucion-route__arrow{color:#8091ad;font-size:12px;}
        .ems-almacen-route{
            display:grid;
            grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);
            align-items:center;
            gap:8px;
        }
        .ems-almacen-route__point{min-width:0;}
        .ems-almacen-route__point:last-child{text-align:right;}
        .ems-almacen-route__point span{
            display:block;
            color:#718096;
            font-size:10px;
            font-weight:800;
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .ems-almacen-route__point strong{
            display:block;
            color:#243b64;
            font-size:12px;
            line-height:1.35;
        }
        .ems-almacen-route__arrow{color:#8091ad;font-size:12px;}
        .ems-almacen-mobile-code{display:none;}
        @media (max-width: 767.98px){
            .table-scroll-wrap--almacen{
                max-height:none;
                overflow:visible;
                padding:0;
                border:0;
                background:transparent;
            }
            .table-scroll-wrap--almacen > .table-responsive{overflow:visible;}
            .table-ems-almacen,
            .table-ems-almacen.responsive-card-table{
                display:block !important;
                width:100% !important;
                min-width:0 !important;
                max-width:100% !important;
                margin:0;
                border:0 !important;
                border-spacing:0;
                background:transparent !important;
            }
            .table-ems-almacen > thead{display:none !important;}
            .table-ems-almacen > tbody{
                display:grid !important;
                grid-template-columns:minmax(0,1fr);
                gap:12px;
                width:100%;
                padding:2px;
            }
            .table-ems-almacen > tbody > tr{
                display:grid !important;
                grid-template-columns:repeat(2,minmax(0,1fr));
                width:100% !important;
                max-width:100% !important;
                overflow:hidden;
                border:1px solid #dfe6f0 !important;
                border-radius:16px;
                background:#fff !important;
                box-shadow:0 5px 16px rgba(20,45,82,.07);
            }
            .table-ems-almacen > tbody > tr > td:not([colspan]){
                display:block !important;
                width:auto !important;
                min-width:0 !important;
                max-width:100% !important;
                min-height:0;
                padding:10px 12px !important;
                border:0 !important;
                border-bottom:1px solid #edf1f6 !important;
                background:#fff !important;
                color:#344054;
                text-align:left !important;
                white-space:normal !important;
                overflow-wrap:anywhere;
                word-break:normal;
            }
            .table-ems-almacen > tbody > tr > td::before{
                display:block;
                margin:0 0 4px;
                padding:0;
                color:#718096;
                font-size:10px;
                font-weight:800;
                line-height:1.3;
                letter-spacing:.04em;
                text-align:left;
                text-transform:uppercase;
                overflow-wrap:normal;
            }
            .table-ems-almacen > tbody > tr > td:first-child{
                display:flex !important;
                grid-column:1 / -1;
                align-items:center;
                justify-content:flex-start;
                gap:10px;
                padding:12px 14px !important;
                border-bottom:1px solid #edf1f6 !important;
            }
            .table-ems-almacen > tbody > tr > td:first-child::before,
            .table-ems-almacen > tbody > tr > td:nth-child(2)::before{content:none !important;}
            .table-ems-almacen > tbody > tr > td:nth-child(2){display:none !important;}
            .table-ems-almacen .ems-almacen-mobile-code{
                display:inline-block;
                max-width:100%;
                padding:7px 11px;
                border-radius:999px;
                background:#e9effa;
                color:#20539a;
                font-size:12px;
                font-weight:800;
                line-height:1.25;
                white-space:normal;
                overflow-wrap:anywhere;
            }
            .table-ems-almacen > tbody > tr > td:nth-child(3),
            .table-ems-almacen > tbody > tr > td:nth-child(5),
            .table-ems-almacen > tbody > tr > td:nth-child(4),
            .table-ems-almacen > tbody > tr > td:nth-child(8),
            .table-ems-almacen > tbody > tr > td:nth-child(9),
            .table-ems-almacen > tbody > tr > td[colspan]{grid-column:1 / -1;}
            .table-ems-almacen > tbody > tr > td:nth-child(3) > span{
                color:#344054;
                font-size:13px;
                font-weight:700;
                line-height:1.4;
            }
            .table-ems-almacen > tbody > tr > td:nth-child(3) > small{display:block;margin-top:3px;}
            .table-ems-almacen .ems-almacen-route{
                grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);
                gap:10px;
                padding:10px;
                border-radius:11px;
                background:#f6f8fc;
            }
            .table-ems-almacen .ems-almacen-route__point strong{overflow-wrap:anywhere;}
            .table-ems-almacen > tbody > tr > td:nth-child(5) > small{
                display:block;
                margin:4px 0 2px;
                color:#718096 !important;
                font-size:10px;
                font-weight:800;
                letter-spacing:.04em;
                text-transform:uppercase;
            }
            .table-ems-almacen > tbody > tr > td:nth-child(5) > span{
                display:block;
                padding:8px 9px;
                border-radius:9px;
                background:#f7f9fc;
                font-size:12px;
                line-height:1.5;
                overflow-wrap:anywhere;
            }
            .table-ems-almacen > tbody > tr > td:nth-child(7),
            .table-ems-almacen > tbody > tr > td:nth-child(8){grid-column:span 1;}
            .table-ems-almacen > tbody > tr > td:nth-child(9){font-weight:600;}
            .table-ems-almacen > tbody > tr > td.action-cell{
                display:flex !important;
                align-items:center;
                flex-wrap:wrap;
                gap:8px;
                border-bottom:0 !important;
            }
            .table-ems-almacen .action-stack{
                display:flex;
                flex-direction:row;
                align-items:center;
                justify-content:flex-start;
                flex-wrap:wrap;
                gap:8px;
                width:100%;
            }
            .table-ems-almacen .action-stack .btn,
            .table-ems-almacen .action-stack a{
                flex:0 0 auto;
                width:auto !important;
                max-width:100%;
                min-height:40px;
            }
            .table-ems-almacen > tbody > tr > td[colspan]{
                padding:18px 14px !important;
                border:0 !important;
            }

            .table-scroll-wrap--devolucion{
                max-height:none;
                overflow:visible;
                padding:0;
                border:0;
                background:transparent;
            }
            .table-scroll-wrap--devolucion > .table-responsive{overflow:visible;}
            .table-devolucion-ems,
            .table-devolucion-ems.responsive-card-table{
                display:block !important;
                width:100% !important;
                min-width:0 !important;
                max-width:100% !important;
                margin:0;
                border:0 !important;
                border-spacing:0;
                background:transparent !important;
            }
            .table-devolucion-ems > thead{display:none !important;}
            .table-devolucion-ems > tbody{
                display:grid !important;
                grid-template-columns:minmax(0,1fr);
                gap:12px;
                width:100%;
                padding:2px;
            }
            .table-devolucion-ems > tbody > tr{
                display:grid !important;
                grid-template-columns:52px minmax(0,1fr);
                width:100% !important;
                max-width:100% !important;
                overflow:hidden;
                border:1px solid #dfe6f0 !important;
                border-radius:16px;
                background:#fff !important;
                box-shadow:0 5px 16px rgba(20,45,82,.07);
            }
            .table-devolucion-ems > tbody > tr > td:not([colspan]){
                display:block !important;
                width:auto !important;
                min-width:0 !important;
                max-width:100% !important;
                min-height:0;
                padding:10px 12px !important;
                border:0 !important;
                border-bottom:1px solid #edf1f6 !important;
                background:#fff !important;
                color:#344054;
                text-align:left !important;
                white-space:normal !important;
                overflow-wrap:anywhere;
                word-break:normal;
            }
            .table-devolucion-ems > tbody > tr > td::before{
                display:block;
                margin:0 0 4px;
                padding:0;
                color:#718096;
                font-size:10px;
                font-weight:800;
                line-height:1.3;
                letter-spacing:.04em;
                text-align:left;
                text-transform:uppercase;
                overflow-wrap:normal;
            }
            .table-devolucion-ems > tbody > tr > td:nth-child(1){
                display:flex !important;
                grid-column:1;
                grid-row:1;
                align-items:center;
                justify-content:center;
                padding:12px 4px !important;
                border-bottom:1px solid #edf1f6 !important;
            }
            .table-devolucion-ems > tbody > tr > td:nth-child(1)::before,
            .table-devolucion-ems > tbody > tr > td:nth-child(2)::before{content:none !important;}
            .table-devolucion-ems > tbody > tr > td:nth-child(2){
                display:flex !important;
                grid-column:2;
                grid-row:1;
                align-items:center;
                padding:9px 12px 9px 4px !important;
                border-bottom:1px solid #edf1f6 !important;
            }
            .table-devolucion-ems td:nth-child(2) .pill-id{
                display:inline-block;
                max-width:100%;
                padding:7px 11px;
                border-radius:999px;
                background:#e9effa;
                color:#20539a;
                font-size:12px;
                font-weight:800;
                line-height:1.25;
                white-space:normal;
                overflow-wrap:anywhere;
            }
            .table-devolucion-ems > tbody > tr > td:nth-child(3),
            .table-devolucion-ems > tbody > tr > td:nth-child(4),
            .table-devolucion-ems > tbody > tr > td:nth-child(5),
            .table-devolucion-ems > tbody > tr > td:nth-child(8),
            .table-devolucion-ems > tbody > tr > td:nth-child(9),
            .table-devolucion-ems > tbody > tr > td[colspan]{grid-column:1 / -1;}
            .table-devolucion-ems > tbody > tr > td:nth-child(3) > strong{
                color:#344054;
                font-size:14px;
                line-height:1.4;
            }
            .table-devolucion-ems > tbody > tr > td:nth-child(3) > small{margin-top:2px;}
            .table-devolucion-ems .devolucion-route{
                grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);
                gap:10px;
                padding:10px;
                border-radius:11px;
                background:#f6f8fc;
            }
            .table-devolucion-ems .devolucion-route__point:last-child{text-align:right;}
            .table-devolucion-ems .devolucion-route__value{overflow-wrap:anywhere;}
            .table-devolucion-ems .devolucion-direcciones__line{
                padding:9px 10px;
                border-radius:10px;
                background:#f7f9fc;
            }
            .table-devolucion-ems .devolucion-direcciones__line + .devolucion-direcciones__line{margin-top:8px;}
            .table-devolucion-ems .devolucion-direcciones__label{margin-bottom:3px;}
            .table-devolucion-ems .devolucion-direcciones__value{
                font-size:12px;
                line-height:1.5;
                overflow-wrap:anywhere;
            }
            .table-devolucion-ems > tbody > tr > td:nth-child(6),
            .table-devolucion-ems > tbody > tr > td:nth-child(7){grid-column:span 1;}
            .table-devolucion-ems > tbody > tr > td:nth-child(8){
                color:#344054;
                font-weight:600;
            }
            .table-devolucion-ems > tbody > tr > td.action-cell{
                display:flex !important;
                align-items:center;
                flex-wrap:wrap;
                gap:8px;
                border-bottom:0 !important;
            }
            .table-devolucion-ems .action-stack{
                display:flex;
                flex-direction:row;
                align-items:center;
                justify-content:flex-start;
                flex-wrap:wrap;
                gap:8px;
                width:100%;
            }
            .table-devolucion-ems .action-stack .btn,
            .table-devolucion-ems .action-stack a{
                flex:0 0 auto;
                width:auto !important;
                max-width:100%;
                min-height:40px;
            }
            .table-devolucion-ems > tbody > tr > td[colspan]{
                grid-column:1 / -1;
                padding:18px 14px !important;
                border:0 !important;
            }
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
            border: 3px solid #20539A;
            border-radius: 18px;
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 58%);
            box-shadow: 0 16px 34px rgba(32,83,154,.18);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .prelist-shell::before{
            content:'';
            position:absolute;
            inset:0 0 auto 0;
            height:6px;
            background:#FECC36;
        }
        .prelist-top{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }
        .prelist-title{
            font-size:22px;
            font-weight:900;
            letter-spacing:.03em;
            text-transform:uppercase;
            color:#1f3f78;
            line-height:1.15;
        }
        .prelist-kpis{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:16px;
        }
        .prelist-kpi{
            border:2px solid #b9c9e8;
            background:#fff;
            border-radius:999px;
            padding:8px 16px;
            font-size:16px;
            color:#2d4b85;
            font-weight:900;
            box-shadow:0 6px 12px rgba(32,83,154,.08);
        }
        .prelist-controls{
            display:flex;
            gap:12px;
            align-items:center;
            flex-wrap:wrap;
            margin-bottom:14px;
        }
        .prelist-controls .form-control{
            min-height:48px;
            font-size:17px;
            border:2px solid #c9d6ec;
        }
        .prelist-table-wrap{
            max-height: 420px;
            overflow:auto;
            border:2px solid #d8e2f3;
            border-radius:12px;
        }
        .prelist-table{
            margin-bottom:0;
            font-size: 16px;
        }
        .prelist-table thead th{
            position: sticky;
            top: 0;
            z-index: 2;
            background:#e7efff;
            color:#1f3f78;
            font-weight:900;
            font-size:15px;
            padding:10px 8px;
            border-bottom:2px solid #c8d6ef;
        }
        .prelist-table tbody td{
            padding:10px 8px;
        }
        .prelist-table tbody tr:nth-child(odd){
            background:#fcfdff;
        }
        .prelist-table .pill-id{
            font-size:14px;
            padding:5px 11px;
        }

        #regionalMismatchModal .modal-content{
            border:0;
            border-radius:14px;
            overflow:hidden;
            box-shadow:0 18px 48px rgba(15, 23, 42, .22);
        }
        #regionalMismatchModal .modal-header{
            background:#20539A;
            color:#fff;
            border:0;
            padding:1.1rem 1.4rem;
        }
        #regionalMismatchModal .modal-title{
            font-weight:800;
        }
        #regionalMismatchModal .close{
            color:#fff;
            opacity:.85;
            text-shadow:none;
        }
        #regionalMismatchModal .modal-body{
            padding:1.35rem 1.5rem 1.25rem;
            background:#f8fafc;
        }
        .regional-warning-text,
        .regional-warning-subtitle{
            color:#111827;
            font-size:1rem;
        }
        .regional-mismatch-list{
            max-height:320px;
            overflow-y:auto;
            padding:10px;
            border:1px solid #dbe3ef;
            border-radius:12px;
            background:#fff;
        }
        .regional-mismatch-item{
            padding:12px;
            border:1px solid #e4e9f2;
            border-radius:10px;
            background:#fbfdff;
            display:grid;
            grid-template-columns:minmax(220px, 32%) 1fr;
            gap:12px;
            align-items:stretch;
        }
        .regional-mismatch-item + .regional-mismatch-item{
            margin-top:10px;
        }
        .regional-mismatch-row{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:12px;
            margin-bottom:0;
            flex-direction:column;
        }
        .regional-mismatch-code{
            color:#0f2f5f;
            font-weight:800;
            letter-spacing:.2px;
        }
        .regional-mismatch-label{
            color:#64748b;
            font-size:.78rem;
            line-height:1.1;
        }
        .regional-mismatch-destino{
            display:inline-flex;
            align-items:center;
            min-height:28px;
            padding:4px 10px;
            border-radius:999px;
            background:#eef4ff;
            color:#20539A;
            font-weight:800;
            white-space:nowrap;
            width:max-content;
        }
        .regional-mismatch-observation{
            min-height:76px;
            border-radius:10px;
            border-color:#cbd5e1;
            resize:vertical;
            background:#fff;
        }
        .regional-mismatch-observation:focus{
            border-color:#20539A;
            box-shadow:0 0 0 .18rem rgba(32, 83, 154, .15);
        }
        .regional-mismatch-alert{
            border-radius:10px;
            background:#c1121f;
            color:#fff;
            font-size:1.2rem;
            font-weight:800;
            line-height:1.55;
            padding:1rem 1.15rem;
            box-shadow:inset 4px 0 0 rgba(255, 255, 255, .28);
        }
        #regionalMismatchModal .modal-footer{
            border-top:1px solid #e5eaf2;
            background:#fff;
            padding:1rem 1.5rem;
        }
        .manifest-loading-overlay{
            position:fixed;
            inset:0;
            z-index:20000;
            align-items:center;
            justify-content:center;
            padding:20px;
            background:rgba(15, 23, 42, .62);
        }
        .manifest-loading-dialog{
            width:min(100%, 420px);
            padding:30px 28px;
            border-radius:16px;
            background:#fff;
            box-shadow:0 24px 70px rgba(15, 23, 42, .28);
            text-align:center;
            color:#18324d;
        }
        .manifest-loading-spinner{
            width:48px;
            height:48px;
            margin:0 auto 18px;
            border:5px solid #e4edf8;
            border-top-color:#20539A;
            border-radius:50%;
            animation:manifest-loading-spin .8s linear infinite;
        }
        .manifest-loading-title{
            display:block;
            font-size:1.15rem;
            font-weight:700;
        }
        .manifest-loading-message{
            margin:8px 0 0;
            color:#64748b;
        }
        @keyframes manifest-loading-spin{
            to{transform:rotate(360deg);}
        }
        @media (max-width: 767.98px){
            .regional-mismatch-item{
                grid-template-columns:1fr;
            }
        }

        .plantilla-wrap.ems-create-page{
            padding:0;
            border-radius:0;
            background:#f3f6fa;
            color:#102b53;
        }
        .ems-create-page .card-app{
            overflow:visible;
            border:0;
            border-radius:0;
            background:transparent;
            box-shadow:none;
        }
        .ems-create-page .card-body{ padding:0; }
        .ems-create-page .header-app.is-create{ display:none; }
        .ems-create-topbar{
            min-height:70px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            padding:10px clamp(18px, 5vw, 76px);
            border-top:7px solid #ffcf35;
            border-bottom:1px solid #dfe5ed;
            background:#fff;
        }
        .ems-create-brand{
            display:flex;
            align-items:center;
            gap:13px;
            color:#0b2b58;
            font-size:18px;
            font-weight:900;
            letter-spacing:.025em;
        }
        .ems-create-brand-icon,
        .ems-create-icon{
            width:44px;
            height:44px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            flex:0 0 auto;
            border-radius:13px;
            background:#ffcf35;
            color:#0b2b58;
            font-size:20px;
        }
        .ems-create-topbar-link{
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:#17365f;
            font-size:14px;
            font-weight:700;
            text-decoration:none;
        }
        .ems-create-topbar-link:hover{ color:#20539a; text-decoration:none; }
        .ems-create-content{
            max-width:1780px;
            margin:0 auto;
            padding:24px clamp(16px, 2.6vw, 44px) 40px;
        }
        .ems-create-heading{
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:22px;
            margin-bottom:20px;
        }
        .ems-create-breadcrumb{
            margin-bottom:7px;
            color:#7b8798;
            font-size:14px;
        }
        .ems-create-breadcrumb i{ margin:0 8px; font-size:11px; }
        .ems-create-heading h1{
            margin:0;
            color:#0b2b58;
            font-size:clamp(30px, 3vw, 42px);
            font-weight:900;
            letter-spacing:-.035em;
        }
        .ems-create-heading p{
            margin:5px 0 0;
            color:#66758a;
            font-size:16px;
        }
        .ems-create-preregistro{
            display:grid;
            grid-template-columns:minmax(180px, 1fr) minmax(260px, 440px);
            align-items:center;
            gap:12px 20px;
            margin:0 0 20px;
            padding:13px 16px;
            border:1px solid #dce5ef;
            border-radius:13px;
            background:#fff;
        }
        .ems-create-preregistro-label{ margin:0; color:#17365f; font-size:13px; font-weight:800; }
        .ems-create-preregistro-help{ display:block; margin-top:3px; color:#718096; font-size:12px; }
        .ems-create-preregistro .form-control{ min-height:42px; border-radius:9px; }
        .ems-create-preregistro-message{ grid-column:1 / -1; margin-top:-6px; color:#28724a; font-size:12px; font-weight:700; }
        .ems-create-preregistro-message.is-error{ color:#b42318; }
        .ems-create-steps{
            display:grid;
            grid-template-columns:minmax(0, 1fr);
            margin:0 0 20px;
            max-width:380px;
        }
        .ems-create-step{
            position:relative;
            display:flex;
            align-items:center;
            gap:12px;
            min-width:0;
            padding:0 15px;
            color:#19375e;
            text-decoration:none;
        }
        .ems-create-step:first-child{ padding-left:0; }
        .ems-create-step:last-child{ padding-right:0; }
        .ems-create-step:not(:last-child)::after{
            content:"";
            height:1px;
            position:absolute;
            left:calc(50% + 32px);
            right:-8px;
            top:21px;
            background:#cbd5e1;
        }
        .ems-create-step:hover{ color:#0b2b58; text-decoration:none; }
        .ems-create-step-number{
            position:relative;
            z-index:1;
            width:42px;
            height:42px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            flex:0 0 auto;
            border:1px solid #cbd5e1;
            border-radius:50%;
            background:#fff;
            color:#344c6a;
            font-size:16px;
            font-weight:800;
        }
        .ems-create-step.is-active .ems-create-step-number{ border-color:#0b4a8f; background:#0b4a8f; color:#fff; }
        .ems-create-step-copy{ position:relative; z-index:1; min-width:0; background:#f3f6fa; padding-right:10px; }
        .ems-create-step-copy strong{ display:block; color:#15345d; font-size:14px; }
        .ems-create-step-copy small{ display:block; margin-top:1px; color:#738096; font-size:12px; }
        .ems-create-grid{
            display:grid;
            grid-template-columns:minmax(0, 1.45fr) minmax(450px, 1fr);
            align-items:start;
            gap:20px;
        }
        .ems-create-main,
        .ems-create-summary-card,
        .ems-create-info-card{
            border:1px solid #dce3ec;
            border-radius:14px;
            background:#fff;
            box-shadow:0 5px 18px rgba(20, 43, 76, .045);
        }
        .ems-create-main{ min-width:0; padding:22px; }
        .ems-create-main .alert{ border-radius:10px; }
        .ems-create-form-heading{
            display:flex;
            align-items:center;
            gap:14px;
            margin:0 0 18px;
        }
        .ems-create-form-heading .ems-create-icon{ width:48px; height:48px; }
        .ems-create-form-heading h2{ margin:0; color:#0b2b58; font-size:22px; font-weight:900; }
        .ems-create-form-heading p{ margin:2px 0 0; color:#738096; font-size:13px; }
        .ems-create-required-note{ margin-left:auto; color:#718096; font-size:12px; white-space:nowrap; }
        .ems-create-page .required-note{ margin:0 0 14px; color:#6b7788; font-size:12px; }
        .ems-create-page .required-star{ color:#e2b321; }
        .ems-create-page .section-block{
            padding:18px;
            border-color:#e3e8ef;
            border-radius:12px;
            background:#fff;
            scroll-margin-top:24px;
        }
        .ems-create-page .section-title{
            margin-bottom:14px;
            color:#15345d;
            font-size:15px;
            letter-spacing:0;
            text-transform:none;
        }
        .ems-create-page .form-group label{ color:#183457; font-size:13px; font-weight:700; }
        .ems-create-page .form-control,
        .ems-create-page select.form-control{
            min-height:43px;
            border-color:#d4dce6;
            border-radius:9px;
            color:#1e3554;
        }
        .ems-create-page textarea.form-control{ min-height:78px; }
        .ems-create-page .form-option-card{ border-color:#e1e6ed; border-radius:9px; }
        .ems-create-summary-card{ position:sticky; top:18px; max-height:calc(100vh - 36px); overflow-y:auto; padding:21px; }
        .ems-create-summary-heading{ display:flex; align-items:center; gap:13px; margin-bottom:17px; }
        .ems-create-summary-heading .ems-create-icon{ width:44px; height:44px; }
        .ems-create-summary-heading h2{ margin:0; color:#0b2b58; font-size:19px; font-weight:900; }
        .ems-create-route{ display:grid; grid-template-columns:26px 1fr; gap:0 12px; margin:0 0 16px; }
        .ems-create-route-symbols{ display:flex; flex-direction:column; align-items:center; padding-top:3px; color:#0b4a8f; }
        .ems-create-route-symbols .route-line{ width:1px; min-height:31px; margin:5px 0; background:#bdcadd; }
        .ems-create-route-point{ padding:0 0 14px; }
        .ems-create-route-point:last-child{ padding-bottom:0; }
        .ems-create-route-label{ display:block; color:#738096; font-size:12px; }
        .ems-create-route-value{ display:block; color:#122f57; font-size:16px; font-weight:800; overflow-wrap:anywhere; }
        .ems-create-summary-list{
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            align-items:start;
            column-gap:18px;
            margin:0;
            padding:12px 0;
            border-top:1px solid #e2e7ee;
            border-bottom:1px solid #e2e7ee;
        }
        .ems-create-summary-section-title{ grid-column:1 / -1; margin:13px 0 4px; color:#17365f; font-size:12px; font-weight:900; }
        .ems-create-summary-section-title:first-child{ margin-top:0; }
        .ems-create-summary-row{ display:flex; min-width:0; flex-direction:column; gap:2px; padding:5px 0; border-bottom:1px solid #edf0f4; color:#68778d; font-size:12px; }
        .ems-create-summary-row strong{ max-width:100%; color:#1c3556; font-weight:700; text-align:left; overflow-wrap:anywhere; }
        .ems-create-summary-row.is-stack{ grid-column:1 / -1; display:block; }
        .ems-create-summary-row.is-stack strong{ display:block; max-width:none; margin-top:2px; text-align:left; }
        .ems-create-price{ padding-top:15px; }
        .ems-create-price-label{ display:block; color:#15345d; font-size:13px; font-weight:800; }
        .ems-create-price-value{ display:block; margin-top:4px; color:#0b2b58; font-size:28px; font-weight:900; letter-spacing:-.025em; }
        .ems-create-price-help{ display:block; margin-top:3px; color:#768397; font-size:12px; }
        .ems-create-info-card{ display:flex; gap:13px; margin-top:14px; padding:16px; background:#f8fafc; }
        .ems-create-info-icon{ width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; border-radius:11px; background:#e9eff7; color:#193d69; }
        .ems-create-info-card strong{ display:block; color:#17365f; font-size:13px; }
        .ems-create-info-card p{ margin:3px 0 0; color:#68778d; font-size:12px; line-height:1.45; }
        .ems-create-actions{
            position:sticky;
            bottom:12px;
            z-index:3;
            margin:18px -22px -22px;
            padding:14px 22px;
            border-top:1px solid #e1e7ef;
            border-radius:0 0 14px 14px;
            background:rgba(255,255,255,.97);
            box-shadow:0 -8px 20px rgba(15, 37, 68, .06);
        }
        .ems-create-actions .btn-dorado{ min-width:185px; border-radius:10px; font-weight:800; }
        .ems-create-actions .btn-outline-azul{ border-radius:10px; }
        @media (max-width: 1199.98px){
            .ems-create-grid{ grid-template-columns:minmax(0, 1fr); }
            .ems-create-summary-card{ position:static; grid-row:1; max-height:none; overflow:visible; }
            .ems-create-summary-column{ display:grid; grid-template-columns:minmax(0, 1.3fr) minmax(240px, .9fr); align-items:start; gap:14px; }
            .ems-create-info-card{ margin-top:0; }
        }
        @media (max-width: 767.98px){
            .ems-create-topbar{ min-height:62px; padding:8px 18px; }
            .ems-create-brand{ font-size:15px; }
            .ems-create-brand-icon{ width:38px; height:38px; }
            .ems-create-topbar-link{ font-size:0; }
            .ems-create-topbar-link i{ font-size:16px; }
            .ems-create-content{ padding:20px 14px 30px; }
            .ems-create-heading{ align-items:flex-start; }
            .ems-create-heading h1{ font-size:31px; }
            .ems-create-heading p{ font-size:14px; }
            .ems-create-preregistro{ grid-template-columns:minmax(0,1fr); gap:8px; }
            .ems-create-steps{ gap:7px; }
            .ems-create-step{ flex-direction:column; align-items:flex-start; gap:6px; padding:0 4px; }
            .ems-create-step:not(:last-child)::after{ left:48px; right:-6px; top:21px; }
            .ems-create-step-copy{ padding-right:3px; }
            .ems-create-step-copy strong{ font-size:12px; }
            .ems-create-step-copy small{ font-size:10px; }
            .ems-create-step-number{ width:38px; height:38px; font-size:14px; }
            .ems-create-main{ padding:16px; }
            .ems-create-form-heading{ align-items:flex-start; flex-wrap:wrap; }
            .ems-create-form-heading h2{ font-size:19px; }
            .ems-create-required-note{ width:100%; margin-left:62px; }
            .ems-create-page .section-block{ padding:14px; }
            .ems-create-summary-column{ grid-template-columns:1fr; }
            .ems-create-actions{ margin:16px -16px -16px; padding:12px 16px; }
            .ems-create-actions .ml-auto{ width:100%; }
            .ems-create-actions .btn-dorado{ min-width:0; flex:1 1 auto; }
        }
        @media (max-width: 575.98px){
            .ems-create-summary-list{ grid-template-columns:1fr; }
            .ems-create-summary-section-title{ grid-column:auto; }
            .ems-create-summary-row.is-stack{ grid-column:auto; }
        }
        @media (max-width: 420px){
            .ems-create-step-copy small{ display:none; }
            .ems-create-step-copy strong{ font-size:11px; }
        }
    </style>

    <div
        class="manifest-loading-overlay"
        wire:loading.flex
        wire:target="mandarSeleccionadosRegional,mandarSeleccionadosContratosRegional,confirmarEnvioRegionalConDestinoDiferente"
        role="status"
        aria-live="assertive"
        aria-label="Generando manifiesto">
        <div class="manifest-loading-dialog">
            <div class="manifest-loading-spinner" aria-hidden="true"></div>
            <strong class="manifest-loading-title">Generando manifiesto...</strong>
            <p class="manifest-loading-message">Por favor, espere mientras se prepara el PDF.</p>
        </div>
    </div>

    <div class="plantilla-wrap {{ $this->isCreateEms ? 'ems-create-page' : '' }}">
        @if ($this->isCreateEms)
            <div class="ems-create-topbar">
                <div class="ems-create-brand">
                    <span class="ems-create-brand-icon"><i class="fas fa-box" aria-hidden="true"></i></span>
                    <span>DELIVERY EXPRESS</span>
                </div>
                <a class="ems-create-topbar-link" href="{{ route('paquetes-ems.index') }}">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Volver a paquetes EMS
                </a>
            </div>
        @endif
        <div class="card card-app">
            <div class="header-app {{ $this->isCreateEms ? 'is-create' : '' }} {{ $this->isAlmacenEms ? 'is-almacen' : '' }} {{ $this->isVentanillaEms ? 'is-ventanilla' : '' }} {{ $this->isDevolucionEms ? 'is-devolucion' : '' }}">
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
                                    Origen: <strong>TODOS</strong>
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
                                <a class="ems-header-back" href="{{ route('paquetes-ems.index') }}">
                                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Volver a Paquetes EMS
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
                                        placeholder="{{ $this->isEnTransitoEms ? 'Buscar por codigo, origen, destino...' : 'Buscar en toda la tabla...' }}"
                                        wire:model.defer="search"
                                        wire:keydown.enter.prevent="searchPaquetes(true)"
                                    >
                                    <button class="btn ems-search-button" type="button" wire:click="searchPaquetes(true)">
                                        <i class="fas fa-search mr-1" aria-hidden="true"></i> Buscar
                                    </button>
                                </div>
                                @if ($this->isAlmacenEms && $canEmsCreate && $canEmsCreateRoute)
                                    <button class="btn ems-primary-action" type="button" wire:click="openEnvioOficialModal">
                                        <i class="fas fa-file-signature mr-1" aria-hidden="true"></i> Generar envío oficial
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="header-actions-row">
                            @if ($this->isEnTransitoEms)
                                <select wire:model.live="filtroOrigenTransito" class="form-control search-input" style="min-width:190px; flex:0 0 220px;">
                                    <option value="">Origen: todos</option>
                                    @foreach ($origenesTransito as $origenTransito)
                                        <option value="{{ $origenTransito }}">{{ $origenTransito }}</option>
                                    @endforeach
                                </select>
                                <select wire:model.live="filtroDestinoTransito" class="form-control search-input" style="min-width:190px; flex:0 0 220px;">
                                    <option value="">Destino: todos</option>
                                    @foreach ($destinosTransito as $destinoTransito)
                                        <option value="{{ $destinoTransito }}">{{ $destinoTransito }}</option>
                                    @endforeach
                                </select>
                                @if ($filtroOrigenTransito !== '' || $filtroDestinoTransito !== '' || $searchQuery !== '')
                                    <button class="btn ems-search-button" type="button" wire:click="limpiarFiltrosTransito">
                                        <i class="fas fa-eraser mr-1" aria-hidden="true"></i> Limpiar
                                    </button>
                                @endif
                            @endif
                            @if (!$this->isAlmacenEms && ((($this->isAdmision && $canEmsAdmisionCreate) || ($this->isAlmacenEms && $canEmsCreate)) && $canEmsCreateRoute) && \App\Support\ServiceAvailability::isEnabled('ems'))
                                <div class="header-primary-action">
                                    <button class="btn ems-primary-action" type="button" wire:click="openCreateModal">
                                        <i class="fas fa-plus mr-1" aria-hidden="true"></i> Nuevo
                                    </button>
                                </div>
                            @endif
                            @if ($this->isAlmacenEms)
                                <div class="ems-action-groups">
                                    @if ($canEmsRegisterContract || $canEmsWeighContract || $canEmsWeighTiktoker)
                                        <section class="ems-action-group" aria-labelledby="warehouseIngresoHeading">
                                            <div class="ems-action-heading">
                                                <span class="ems-action-heading-icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                                                <span>
                                                    <strong id="warehouseIngresoHeading">Ingreso y peso</strong>
                                                    <small>Contratos y registro de peso</small>
                                                </span>
                                            </div>
                                            <div class="ems-action-list">
                                                @if ($canEmsRegisterContract)
                                                    <a class="ems-action-button" href="{{ route('paquetes-ems.contrato-rapido.create') }}" target="_blank" rel="noopener">
                                                        <i class="fas fa-file-contract" aria-hidden="true"></i> Registrar contrato
                                                    </a>
                                                @endif
                                                @if ($canEmsWeighContract)
                                                    <button class="ems-action-button" type="button" wire:click="openContratoPesoModal">
                                                        <i class="fas fa-weight-hanging" aria-hidden="true"></i> Añadir peso al contrato
                                                    </button>
                                                @endif
                                                @if ($canEmsWeighTiktoker)
                                                    <button class="ems-action-button" type="button" wire:click="openTiktokerPesoModal">
                                                        <i class="fas fa-boxes" aria-hidden="true"></i> Asignar peso a Delivery Express
                                                    </button>
                                                @endif
                                            </div>
                                        </section>
                                    @endif

                                    @if ($canEmsSendVentanilla || $canEmsSendRegional)
                                        <section class="ems-action-group" aria-labelledby="warehouseDespachoHeading">
                                            <div class="ems-action-heading">
                                                <span class="ems-action-heading-icon" aria-hidden="true"><i class="fas fa-shipping-fast"></i></span>
                                                <span>
                                                    <strong id="warehouseDespachoHeading">Despacho</strong>
                                                    <small>Enviar a ventanilla o regional</small>
                                                </span>
                                            </div>
                                            <div class="ems-action-list">
                                                @if ($canEmsSendVentanilla)
                                                    <button class="ems-action-button" type="button" wire:click="openVentanillaPesoModal">
                                                        <i class="fas fa-store" aria-hidden="true"></i> Enviar a ventanilla EMS
                                                    </button>
                                                @endif
                                                @if ($canEmsSendRegional)
                                                    <button class="ems-action-button" type="button" wire:click="toggleCn33Assign">
                                                        <i class="fas fa-list-ol" aria-hidden="true"></i> Añadir a CN-33
                                                    </button>
                                                    <button class="ems-action-button ems-action-button--primary" type="button" wire:click="openRegionalModal">
                                                        <i class="fas fa-truck" aria-hidden="true"></i> Enviar a regional
                                                    </button>
                                                @endif
                                            </div>
                                        </section>
                                    @endif

                                    @if ($canEmsRestore || $canEmsReprintCn33)
                                        <section class="ems-action-group" aria-labelledby="warehouseDocumentosHeading">
                                            <div class="ems-action-heading">
                                                <span class="ems-action-heading-icon" aria-hidden="true"><i class="fas fa-folder-open"></i></span>
                                                <span>
                                                    <strong id="warehouseDocumentosHeading">Documentos y control</strong>
                                                    <small>Manifiestos, factura y bajas</small>
                                                </span>
                                            </div>
                                            <div class="ems-action-list">
                                                @if ($canEmsReprintCn33)
                                                    <button class="ems-action-button" type="button" wire:click="toggleCn33Reprint">
                                                        <i class="fas fa-print" aria-hidden="true"></i> Reimprimir CN-33
                                                    </button>
                                                    <button class="ems-action-button" type="button" wire:click="toggleCn38Generate">
                                                        <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Factura BOA
                                                    </button>
                                                @endif
                                                @if ($canEmsRestore)
                                                    <button
                                                        class="ems-action-button ems-action-button--danger"
                                                        type="button"
                                                        wire:click="darDeBajaOficialSeleccionados"
                                                        onclick="return confirm('Solo se daran de baja envios OFICIAL seleccionados. Deseas continuar?')"
                                                    >
                                                        <i class="fas fa-ban" aria-hidden="true"></i> Dar de baja oficial
                                                    </button>
                                                @endif
                                            </div>
                                        </section>
                                    @endif
                                </div>
                            @else
                                @if (($this->isAdmision && $canEmsAssign) || (($this->isVentanillaEms || $this->isDevolucionEms) && $canEmsDeliver) || ($this->isTransitoEms && $canEmsAssign))
                                    <div class="ems-action-groups ems-action-groups--compact">
                                        @if ($this->isAdmision && $canEmsAssign)
                                            <section class="ems-action-group" aria-labelledby="emsAdmisionActionsHeading">
                                                <div class="ems-action-heading">
                                                    <span class="ems-action-heading-icon" aria-hidden="true"><i class="fas fa-boxes"></i></span>
                                                    <span>
                                                        <strong id="emsAdmisionActionsHeading">Despacho</strong>
                                                        <small>Enviar paquetes admitidos</small>
                                                    </span>
                                                </div>
                                                <div class="ems-action-list">
                                                    <button class="ems-action-button ems-action-button--primary" type="button" wire:click="mandarSeleccionadosGeneradosHoy">
                                                        <i class="fas fa-calendar-day" aria-hidden="true"></i> Generados el día de hoy
                                                    </button>
                                                    <button class="ems-action-button" type="button" wire:click="mandarSeleccionadosSinFiltroFecha">
                                                        <i class="fas fa-paper-plane" aria-hidden="true"></i> Mandar seleccionados
                                                    </button>
                                                </div>
                                            </section>
                                        @elseif ($this->isVentanillaEms && $canEmsDeliver)
                                            <section class="ems-action-group" aria-labelledby="emsVentanillaActionsHeading">
                                                <div class="ems-action-heading">
                                                    <span class="ems-action-heading-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                                                    <span>
                                                        <strong id="emsVentanillaActionsHeading">Ventanilla EMS</strong>
                                                        <small>Entregar o devolver envíos</small>
                                                    </span>
                                                </div>
                                                <div class="ems-action-list">
                                                    <button class="ems-action-button ems-action-button--primary" type="button" wire:click="openEntregaVentanillaModal">
                                                        <i class="fas fa-box" aria-hidden="true"></i> Entregar seleccionados
                                                    </button>
                                                    <button
                                                        class="ems-action-button"
                                                        type="button"
                                                        wire:click="devolverSeleccionadosVentanillaAEstadoAnterior"
                                                        onclick="return confirm('Seguro que deseas devolver los seleccionados al estado anterior (ALMACEN o RECIBIDO)?')"
                                                    >
                                                        <i class="fas fa-undo" aria-hidden="true"></i> Devolver a almacén
                                                    </button>
                                                </div>
                                            </section>
                                        @elseif ($this->isDevolucionEms && $canEmsDeliver)
                                            <section class="ems-action-group" aria-labelledby="emsDevolucionActionsHeading">
                                                <div class="ems-action-heading">
                                                    <span class="ems-action-heading-icon" aria-hidden="true"><i class="fas fa-undo-alt"></i></span>
                                                    <span>
                                                        <strong id="emsDevolucionActionsHeading">Devoluciones</strong>
                                                        <small>Registrar la devolución de envíos</small>
                                                    </span>
                                                </div>
                                                <div class="ems-action-list ems-action-list--devolucion">
                                                    <div class="ems-action-primary-stack">
                                                        <button class="ems-action-button ems-action-button--primary" type="button" wire:click="openDevolucionEmsModal">
                                                            <i class="fas fa-undo" aria-hidden="true"></i> Devolver seleccionado
                                                        </button>
                                                        <small class="ems-action-help">
                                                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                                                            Elige un solo envío para registrar su devolución.
                                                        </small>
                                                    </div>
                                                </div>
                                            </section>
                                        @elseif ($this->isTransitoEms && $canEmsAssign)
                                            <section class="ems-action-group" aria-labelledby="emsRecepcionActionsHeading">
                                                <div class="ems-action-heading">
                                                    <span class="ems-action-heading-icon" aria-hidden="true"><i class="fas fa-truck-loading"></i></span>
                                                    <span>
                                                        <strong id="emsRecepcionActionsHeading">Recepción regional</strong>
                                                        <small>Registrar paquetes recibidos</small>
                                                    </span>
                                                </div>
                                                <div class="ems-action-list">
                                                    <button class="ems-action-button" type="button" wire:click="toggleRecibirRegionalCn33Input">
                                                        <i class="fas fa-list-ol" aria-hidden="true"></i> Recibir todos del CN-33
                                                    </button>
                                                    <button class="ems-action-button ems-action-button--primary" type="button" wire:click="openRecibirRegionalModal">
                                                        <i class="fas fa-check" aria-hidden="true"></i> Recibir
                                                    </button>
                                                </div>
                                            </section>
                                        @endif
                                    </div>
                                @endif
                            @endif
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
                    <div class="ems-create-content">
                        <div class="ems-create-heading">
                            <div>
                                <div class="ems-create-breadcrumb">Paquetes <i class="fas fa-chevron-right" aria-hidden="true"></i> Nuevo env&iacute;o</div>
                                <h1>Prepara tu env&iacute;o</h1>
                                <p>Completa los datos del paquete para continuar.</p>
                            </div>
                        </div>

                        <div class="ems-create-preregistro">
                            <div>
                                <label class="ems-create-preregistro-label" for="createPreregistroCodigo">Autocompletar con preregistro</label>
                                <span class="ems-create-preregistro-help">Pega el c&oacute;digo del preregistro para cargar sus datos.</span>
                            </div>
                            <input
                                id="createPreregistroCodigo"
                                type="text"
                                class="form-control"
                                placeholder="PRE00000001 o 00000001"
                                wire:model.live.debounce.400ms="preregistro_codigo"
                            >
                            @if($preregistroAutofillMessage)
                                <div class="ems-create-preregistro-message {{ str_contains(strtolower($preregistroAutofillMessage), 'no existe') || str_contains(strtolower($preregistroAutofillMessage), 'ya fue validado') ? 'is-error' : '' }}">
                                    {{ $preregistroAutofillMessage }}
                                </div>
                            @endif
                        </div>

                        <nav class="ems-create-steps" aria-label="Secciones del formulario">
                            <a class="ems-create-step is-active" href="#ems-create-service">
                                <span class="ems-create-step-number">1</span>
                                <span class="ems-create-step-copy"><strong>Servicio</strong><small>Paquete y ruta</small></span>
                            </a>
                        </nav>

                        <div class="ems-create-grid">
                            <main class="ems-create-main">
                    @if ($activeTarifarioVersion)
                        <div class="alert alert-success py-2 mb-3">
                            <strong>Tarifario vigente:</strong> {{ $activeTarifarioVersion->tarifarioPadre->nombre ?? 'Tarifario' }} ·
                            versión {{ $activeTarifarioVersion->codigo }} · {{ $activeTarifarioVersion->cantidad_items }} tramos ·
                            publicada {{ optional($activeTarifarioVersion->publicada_en)->format('d/m/Y H:i') }}.
                            No vence automáticamente; seguirá vigente hasta que se publique otra versión.
                        </div>
                    @else
                        <div class="alert alert-warning py-2 mb-3">
                            Aún no hay una versión publicada. Un administrador debe publicar el tarifario antes de registrar envíos facturables.
                        </div>
                    @endif
                    @if ($this->puedeConfigurarTarifario)
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" wire:click="abrirConfiguracionTarifario">
                                <i class="fas fa-cog mr-1"></i> Configurar tarifario padre
                            </button>
                            @if ($mostrarConfiguracionTarifario)
                                <div class="border rounded p-3 mt-2">
                                    <label for="ems-tarifario-padre">Tarifario padre para el registro EMS</label>
                                    <select id="ems-tarifario-padre" wire:model="tarifarioPadreSeleccionado" class="form-control @error('tarifarioPadreSeleccionado') is-invalid @enderror">
                                        <option value="">Seleccione un tarifario padre...</option>
                                        @foreach ($tarifariosPadre as $padre)
                                            <option value="{{ $padre->id }}">{{ $padre->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('tarifarioPadreSeleccionado') <small class="text-danger">{{ $message }}</small> @enderror
                                    <p class="text-muted mt-2 mb-2">Al publicar se congela una copia de los precios y tramos actuales. Los cambios futuros crearán otra versión y no modificarán las ventas anteriores.</p>
                                    <button type="button" class="btn btn-primary" wire:click="guardarConfiguracionTarifario" wire:loading.attr="disabled">Publicar nueva versión del tarifario</button>
                                    <button type="button" class="btn btn-secondary" wire:click="$set('mostrarConfiguracionTarifario', false)">Cancelar</button>
                                    @if ($tarifarioVersiones->isNotEmpty())
                                        <hr>
                                        <strong>Historial de versiones</strong>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Versión</th><th>Tarifario padre</th><th>Publicada</th><th>Tramos</th><th>Estado</th></tr></thead>
                                                <tbody>
                                                    @foreach ($tarifarioVersiones as $version)
                                                        <tr>
                                                            <td>{{ $version->codigo }}</td>
                                                            <td>{{ $version->tarifarioPadre->nombre ?? '-' }}</td>
                                                            <td>{{ optional($version->publicada_en)->format('d/m/Y H:i') }}</td>
                                                            <td>{{ $version->cantidad_items }}</td>
                                                            <td>{{ (int) ($activeTarifarioVersion->id ?? 0) === (int) $version->id ? 'ACTIVA' : 'PUBLICADA' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                    @if ($servicios->isEmpty())
                        <div class="alert alert-warning">No hay servicios asociados al tarifario padre vigente. Solicita al administrador que asocie servicios o seleccione otro tarifario.</div>
                    @endif
                    <form wire:submit.prevent="save">
                        <div class="ems-create-form-heading">
                            <span class="ems-create-icon"><i class="fas fa-box-open" aria-hidden="true"></i></span>
                            <div>
                                <h2>Datos del servicio</h2>
                                <p>Elige la ruta y completa la informaci&oacute;n del paquete.</p>
                            </div>
                            <span class="ems-create-required-note"><span class="required-star">*</span> Campos obligatorios</span>
                        </div>
                        <div class="required-note">Campos con <span class="required-star">*</span> son obligatorios.</div>

                        <div class="section-block" id="ems-create-service">
                            <div class="section-title">Paquete y ruta</div>
                            @php
                                $tipoCorrespondenciaActual = strtoupper(trim((string) $tipo_correspondencia));
                                $observacionOficialRequerida = $tipoCorrespondenciaActual !== '' && str_contains($tipoCorrespondenciaActual, 'OFICIAL');
                            @endphp
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Servicio<span class="required-star">*</span></label>
                                    <select wire:model.live="servicio_id" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($servicios as $servicio)
                                            <option value="{{ $servicio->id }}">{{ $servicio->nombre_servicio }}</option>
                                        @endforeach
                                    </select>
                                    @error('servicio_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Origen</label>
                                    <input type="text" wire:model.defer="origen" class="form-control" readonly>
                                    @error('origen') <small class="text-danger">{{ $message }}</small> @enderror
                                    <small class="text-muted">Asignado automaticamente.</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Destino<span class="required-star">*</span></label>
                                    <select wire:model.live="destino_id" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($destinos as $destino)
                                            <option value="{{ $destino->id }}">{{ $destino->nombre_ems }}</option>
                                        @endforeach
                                    </select>
                                    @error('destino_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Tipo de correspondencia</label>
                                    <input type="text" wire:model.live="tipo_correspondencia" class="form-control">
                                    @error('tipo_correspondencia') <small class="text-danger">{{ $message }}</small> @enderror
                                    <small class="text-muted">Los envios OFICIAL se registran directamente en facturacion.</small>
                                </div>
                                <div class="form-group col-md-8">
                                    <label>Observacion: que se esta mandando @if($observacionOficialRequerida)<span class="required-star">*</span>@endif</label>
                                    <input type="text" wire:model.live.debounce.400ms="observacion" class="form-control" placeholder="Describa brevemente que se esta enviando">
                                    @error('observacion') <small class="text-danger">{{ $message }}</small> @enderror
                                    <small class="text-muted">Obligatorio cuando el tipo de correspondencia es OFICIAL.</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Servicio especial</label>
                                    <select wire:model.live="servicio_especial" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="IDA">IDA</option>
                                        <option value="POR COBRAR">POR COBRAR</option>
                                        <option value="IDA Y VUELTA">IDA Y VUELTA</option>
                                    </select>
                                    @error('servicio_especial') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Correo electronico</label>
                                    <input type="email" wire:model.live.debounce.400ms="correo_electronico" class="form-control" maxlength="255" placeholder="cliente@ejemplo.com">
                                    @error('correo_electronico') <small class="text-danger">{{ $message }}</small> @enderror
                                    <small class="text-muted">Opcional. Se enviara la confirmacion de recepcion con la boleta EMS adjunta.</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-12">
                                    <label>Contenido<span class="required-star">*</span></label>
                                    <textarea wire:model.live.debounce.400ms="contenido" class="form-control" rows="2" placeholder="Detalle el contenido del paquete" required></textarea>
                                    @error('contenido') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Cantidad<span class="required-star">*</span></label>
                                    <input type="number" wire:model.live.debounce.400ms="cantidad" class="form-control" min="1" required>
                                    @error('cantidad') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <x-peso-qz-field
                                        model="peso"
                                        input-id="peso-create-ems"
                                        :required="true"
                                        :use-scale="true"
                                        :show-clear="true"
                                        :live="true"
                                    />
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Precio</label>
                                    <input type="number" wire:model.defer="precio" class="form-control" step="0.01" min="0" readonly>
                                    @error('precio') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Codigo<span class="required-star">*</span></label>
                                    <input type="text" wire:model.live.debounce.400ms="codigo" class="form-control" @if($auto_codigo) readonly @endif required>
                                    @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <div class="form-option-card">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="autoCodigoCreate" wire:model.live="auto_codigo">
                                            <label class="form-check-label font-weight-bold" for="autoCodigoCreate">
                                                Generar codigo automatico
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <div class="form-option-card">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="anadirRecargo30Create" wire:model.live="anadir_recargo_30">
                                            <label class="form-check-label font-weight-bold" for="anadirRecargo30Create">
                                                A&ntilde;adir 30 %
                                            </label>
                                            <small class="form-text text-muted">
                                                Para paquetes que superan 2 metros de alto o ancho.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-block" id="ems-create-sender">
                            <div class="section-title">Datos del remitente</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre remitente<span class="required-star">*</span></label>
                                    <input type="text" wire:model.live.debounce.400ms="nombre_remitente" class="form-control" required>
                                    @error('nombre_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Carnet remitente<span class="required-star">*</span></label>
                                    <input type="text" wire:model.live.debounce.400ms="carnet" class="form-control" required>
                                    @error('carnet') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Telefono remitente<span class="required-star">*</span></label>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        wire:model.live.debounce.300ms="telefono_remitente"
                                        class="form-control"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        required
                                    >
                                    @error('telefono_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <div class="form-option-card">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="mostrarEmpresaCreate" wire:model.live="mostrar_empresa">
                                            <label class="form-check-label font-weight-bold" for="mostrarEmpresaCreate">
                                                El remitente envia como empresa
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                @if ($mostrar_empresa)
                                    <div class="form-group col-md-8">
                                        <label>Nombre de la empresa</label>
                                        <input type="text" wire:model.live.debounce.400ms="nombre_envia" class="form-control" placeholder="Ingrese la razon social o nombre comercial">
                                        @error('nombre_envia') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                @endif
                            </div>

                        </div>

                        <div class="section-block" id="ems-create-recipient">
                            <div class="section-title">Datos del destinatario</div>

                            <div class="form-row">
                                <div class="form-group col-md-5">
                                    <label>Nombre destinatario<span class="required-star">*</span></label>
                                    <input type="text" wire:model.live.debounce.400ms="nombre_destinatario" class="form-control" required>
                                    @error('nombre_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Telefono destinatario</label>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        wire:model.live.debounce.300ms="telefono_destinatario"
                                        class="form-control"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    >
                                    @error('telefono_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-4">
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

                            <div class="form-row">
                                <div class="form-group col-md-8">
                                    <label>Direccion destinatario<span class="required-star">*</span></label>
                                    <input type="text" wire:model.live.debounce.400ms="direccion" class="form-control" required>
                                    @error('direccion') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Referencia</label>
                                    <input type="text" wire:model.live.debounce.400ms="referencia" class="form-control">
                                    @error('referencia') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="ems-create-actions d-flex justify-content-between align-items-center flex-wrap gap-2">
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
                            </main>

                            @php
                                $createSelectedService = $servicios->firstWhere('id', $servicio_id);
                                $createSelectedDestination = $destinos->firstWhere('id', $destino_id);
                            @endphp
                            <aside class="ems-create-summary-column" aria-label="Resumen del env&iacute;o">
                                <section class="ems-create-summary-card">
                                    <div class="ems-create-summary-heading">
                                        <span class="ems-create-icon"><i class="fas fa-cube" aria-hidden="true"></i></span>
                                        <h2>Resumen del env&iacute;o</h2>
                                    </div>
                                    <div class="ems-create-route">
                                        <div class="ems-create-route-symbols" aria-hidden="true">
                                            <i class="fas fa-circle" style="font-size:12px"></i>
                                            <span class="route-line"></span>
                                            <i class="fas fa-map-marker-alt" style="font-size:18px"></i>
                                        </div>
                                        <div>
                                            <div class="ems-create-route-point">
                                                <span class="ems-create-route-label">Origen</span>
                                                <strong class="ems-create-route-value">{{ $origen ?: 'Se asigna automaticamente' }}</strong>
                                            </div>
                                            <div class="ems-create-route-point">
                                                <span class="ems-create-route-label">Destino</span>
                                                <strong class="ems-create-route-value">{{ $createSelectedDestination->nombre_ems ?? 'Por seleccionar' }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ems-create-summary-list">
                                        <div class="ems-create-summary-section-title">Paquete y servicio</div>
                                        <div class="ems-create-summary-row"><span>Servicio</span><strong>{{ $createSelectedService->nombre_servicio ?? 'Por seleccionar' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Tipo de correspondencia</span><strong>{{ $tipo_correspondencia ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Servicio especial</span><strong>{{ $servicio_especial ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row is-stack"><span>Observaci&oacute;n</span><strong>{{ $observacion ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row is-stack"><span>Correo electr&oacute;nico</span><strong>{{ $correo_electronico ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row is-stack"><span>Contenido</span><strong>{{ $contenido ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Cantidad</span><strong>{{ $cantidad ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Peso</span><strong>{{ $peso !== '' ? $peso . ' kg' : '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>C&oacute;digo</span><strong>{{ $codigo ?: 'Automatico' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>C&oacute;digo autom&aacute;tico</span><strong>{{ $auto_codigo ? 'Si' : 'No' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Recargo por dimensiones</span><strong>{{ $anadir_recargo_30 ? '30 % aplicado' : 'No aplicado' }}</strong></div>

                                        <div class="ems-create-summary-section-title">Remitente</div>
                                        <div class="ems-create-summary-row is-stack"><span>Nombre</span><strong>{{ $nombre_remitente ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Carnet</span><strong>{{ $carnet ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Tel&eacute;fono</span><strong>{{ $telefono_remitente ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Env&iacute;a como empresa</span><strong>{{ $mostrar_empresa ? 'Si' : 'No' }}</strong></div>
                                        @if ($mostrar_empresa)
                                            <div class="ems-create-summary-row is-stack"><span>Empresa</span><strong>{{ $nombre_envia ?: '-' }}</strong></div>
                                        @endif

                                        <div class="ems-create-summary-section-title">Destinatario</div>
                                        <div class="ems-create-summary-row is-stack"><span>Nombre</span><strong>{{ $nombre_destinatario ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Tel&eacute;fono</span><strong>{{ $telefono_destinatario ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row"><span>Ciudad</span><strong>{{ $ciudad ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row is-stack"><span>Direcci&oacute;n</span><strong>{{ $direccion ?: '-' }}</strong></div>
                                        <div class="ems-create-summary-row is-stack"><span>Referencia</span><strong>{{ $referencia ?: '-' }}</strong></div>
                                    </div>
                                    <div class="ems-create-price">
                                        <span class="ems-create-price-label">Precio del env&iacute;o</span>
                                        @if (is_numeric($precio))
                                            <strong class="ems-create-price-value">Bs {{ number_format((float) $precio, 2, ',', '.') }}</strong>
                                            <span class="ems-create-price-help">Calculado con el peso y el tarifario vigente.</span>
                                        @else
                                            <strong class="ems-create-price-value">Por calcular</strong>
                                            <span class="ems-create-price-help">Completa los datos del paquete para conocer el precio.</span>
                                        @endif
                                    </div>
                                </section>
                                <section class="ems-create-info-card">
                                    <span class="ems-create-info-icon"><i class="fas fa-weight-hanging" aria-hidden="true"></i></span>
                                    <div>
                                        <strong>Revisa el peso del paquete</strong>
                                        <p>El precio se actualiza con el peso registrado. El recargo del 30 % aplica cuando el paquete supera 2 metros de alto o ancho.</p>
                                    </div>
                                </section>
                            </aside>
                        </div>
                    </div>
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
                                    class="form-control text-uppercase"
                                    placeholder="Ingresa cod_especial (ej: SRZ00001)"
                                    wire:model.live.debounce.300ms="cn33ManualCodigo"
                                    wire:keydown.enter.prevent="prepararAnadirSeleccionadosCn33"
                                >
                            </div>
                            <div class="form-group col-md-6 mb-2 d-flex gap-2">
                                <button
                                    class="btn btn-azul"
                                    type="button"
                                    wire:click="prepararAnadirSeleccionadosCn33"
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

                @if ($this->isAlmacenEms && $showCn38Generate && $canEmsReprintCn33)
                    <div class="section-block mb-3">
                        <div class="section-title">Factura BOA desde CN-33</div>
                        @php
                            $cn38SelectedTotal = count($selectedPaquetes) + count($selectedPaquetesInt) + count($selectedContratos) + count($selectedSolicitudes);
                            $cn38DispatchRows = collect($cn38DispatchSummaryRows ?? collect());
                            $cn38TotalPeso = (float) $cn38DispatchRows->sum(fn ($row) => (float) ($row->peso_total ?? 0));
                        @endphp
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-5 mb-2">
                                <label>Despacho CN-33</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa cod_especial (ej: SRZ00001)"
                                    wire:model.defer="cn38Despacho"
                                    wire:keydown.enter.prevent="cargarCn38DesdeCn33"
                                >
                                <small class="text-muted">Puedes marcar varios registros con los checkboxes o cargar automaticamente un despacho CN-33.</small>
                            </div>
                            <div class="form-group col-md-7 mb-2 d-flex gap-2 flex-wrap">
                                <button class="btn btn-outline-azul" type="button" wire:click="cargarCn38DesdeCn33">
                                    Autoseleccionar registros
                                </button>
                                <button class="btn btn-azul" type="button" wire:click="openCn38PrintOptionsModal">
                                    Generar PDF Factura BOA
                                </button>
                                <button class="btn btn-outline-azul" type="button" wire:click="toggleCn38Generate">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                        @if ($cn38SelectedTotal > 0 || trim((string) $cn38Despacho) !== '')
                            <div class="alert alert-light border mt-2 mb-0">
                                <strong>Despacho:</strong> {{ trim((string) $cn38Despacho) !== '' ? strtoupper(trim((string) $cn38Despacho)) : 'SELECCION MANUAL' }}
                                |
                                <strong>CN-33 seleccionados:</strong> {{ $cn38DispatchRows->count() }}
                                |
                                <strong>Peso total:</strong> {{ \App\Support\BolivianNumber::format($cn38TotalPeso, 3) }} Kg
                            </div>
                        @endif
                        @if ($cn38SelectedTotal > 0)
                            <div class="mt-3 border rounded p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <div class="font-weight-bold text-primary">
                                        Prelista Factura BOA por CN-33 ({{ $cn38DispatchRows->count() }})
                                    </div>
                                    <div class="small text-muted">
                                        Aqui solo se muestran numero de despacho CN-33 y peso total.
                                    </div>
                                </div>
                                <div class="table-responsive" style="max-height: 260px;">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Despacho CN-33</th>
                                                <th>Registros</th>
                                                <th>Peso total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($cn38DispatchRows as $item)
                                                <tr wire:key="cn38-cn33-{{ md5((string) ($item->despacho ?? '')) }}">
                                                    <td><span class="pill-id">{{ $item->despacho ?? '-' }}</span></td>
                                                    <td>{{ $item->registros ?? 0 }}</td>
                                                    <td>{{ \App\Support\BolivianNumber::format((float) ($item->peso_total ?? 0), 3) }} Kg</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">No hay CN-33 seleccionados.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
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
                                ? (count($selectedPaquetes) + count($selectedPaquetesInt) + count($selectedContratos) + count($selectedSolicitudes))
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

                @if ($this->isDevolucionEms && $seleccionadosTotal > 1)
                    <div class="alert alert-warning py-2 mb-3" role="alert">
                        Solo puedes hacer una devoluciÃ³n a la vez. Deja seleccionado un solo registro.
                    </div>
                @endif

                @php
                    $seleccionadosTotalGlobal = (count($selectedPaquetes) + count($selectedPaquetesInt) + count($selectedContratos) + count($selectedSolicitudes));
                @endphp
                @if ($this->canUseSelectedPreview && $seleccionadosTotalGlobal > 0)
                    <div class="prelist-shell mb-3">
                        @php
                            $selectedPreviewBase = collect($selectedPreviewRows ?? collect());
                            $selectedPreviewEms = $selectedPreviewBase->where('tipo', 'EMS')->count();
                            $selectedPreviewInt = $selectedPreviewBase->where('tipo', 'INT')->count();
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
                                        trim((string) ($row->origen ?? '')) . ' ' .
                                        trim((string) ($row->destino ?? '')) . ' ' .
                                        trim((string) ($row->direccion_r ?? '')) . ' ' .
                                        trim((string) ($row->direccion_d ?? ''))
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
                            <span class="prelist-kpi">INT: <strong>{{ $selectedPreviewInt }}</strong></span>
                            <span class="prelist-kpi">CONTRATO: <strong>{{ $selectedPreviewContratos }}</strong></span>
                            <span class="prelist-kpi">SOLICITUD: <strong>{{ $selectedPreviewSolicitudes }}</strong></span>
                        </div>
                        @if ($showSelectedPreview)
                            <div class="prelist-controls">
                                <select wire:model.live="selectedPreviewType" class="form-control" style="max-width: 230px;">
                                    <option value="TODOS">Todos</option>
                                    <option value="EMS">EMS</option>
                                    <option value="INT">INT</option>
                                    <option value="CONTRATO">Contrato</option>
                                    <option value="SOLICITUD">Solicitud</option>
                                </select>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="selectedPreviewSearch"
                                    class="form-control"
                                    style="max-width: 520px;"
                                    placeholder="Filtrar por código, destinatario, ruta o dirección"
                                >
                            </div>
                            <div class="prelist-table-wrap">
                                <table class="table table-sm table-hover align-middle prelist-table">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Codigo</th>
                                            <th>Destinatario</th>
                                            <th>Ruta</th>
                                            <th>Direcciones</th>
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
                                                <td>
                                                    <small class="d-block text-muted">Origen</small>
                                                    <strong>{{ $item->origen ?? '-' }}</strong>
                                                    <small class="d-block text-muted mt-1">Destino</small>
                                                    <strong>{{ $item->destino ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <small class="d-block text-muted">Remitente</small>
                                                    <span>{{ !empty($item->direccion_r) && $item->direccion_r !== '-' ? $item->direccion_r : 'Sin dirección registrada' }}</span>
                                                    <small class="d-block text-muted mt-1">Destinatario</small>
                                                    <span>{{ !empty($item->direccion_d) && $item->direccion_d !== '-' ? $item->direccion_d : 'Sin dirección registrada' }}</span>
                                                </td>
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
                                                <td colspan="7" class="text-center py-3 text-muted">No hay elementos para ese filtro.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="table-scroll-wrap {{ $this->isDevolucionEms ? 'table-scroll-wrap--devolucion' : ($this->isAlmacenEms ? 'table-scroll-wrap--almacen' : ($this->isAdmision ? 'table-scroll-wrap--admision' : '')) }}">
                    <div class="table-responsive">
                    <table class="table table-hover align-middle {{ $this->isDevolucionEms ? 'table-devolucion-ems' : ($this->isAlmacenEms ? 'table-ems-almacen' : ($this->isAdmision ? 'table-ems-admision' : '')) }}">
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
                                    @if (!$this->isDevolucionEms && !$this->isAlmacenEms)
                                        <th>Tipo</th>
                                    @endif
                                    @if ($this->isAlmacenEms)
                                        <th>Servicio</th>
                                        <th>Ruta</th>
                                        <th>Direcciones</th>
                                        <th>Peso</th>
                                        <th>Destinatario</th>
                                        <th>Creado</th>
                                    @elseif ($this->isDevolucionEms)
                                        <th>Servicio</th>
                                        <th>Ruta</th>
                                        <th>Direcciones</th>
                                        <th>Peso</th>
                                        <th>Destinatario</th>
                                        <th>Fecha de recojo</th>
                                    @else
                                        <th>Servicio</th>
                                        <th>Serv. especial</th>
                                        <th>Origen</th>
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
                                    @endif
                                    <th class="text-center action-cell">Acciones</th>
                                </tr>
                            @else
                                <tr>
                                    @if ($this->isAdmision)
                                        <th></th>
                                        <th>Codigo</th>
                                        <th>Tipo</th>
                                        <th>Servicio</th>
                                        <th>Ruta</th>
                                        <th>Direcciones</th>
                                        <th>Peso</th>
                                        <th>Destinatario</th>
                                        <th>Creado</th>
                                    @else
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
                                    @endif
                                    <th class="text-center action-cell">Acciones</th>
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @if ($this->isEnTransitoEms)
                                @forelse ($paquetes as $row)
                                    <tr wire:key="en-transito-row-{{ $row->record_type ?? 'row' }}-{{ $row->record_id ?? $loop->index }}">
                                        <td><span class="pill-id">{{ $row->codigo }}</span></td>
                                        <td>{{ $row->origen ?: '-' }}</td>
                                        <td>{{ $row->destino ?: '-' }}</td>
                                        <td>
                                            @if(!empty($row->cod_especial))
                                                <button
                                                    type="button"
                                                    class="cod-especial-btn"
                                                    wire:click="openCodEspecialDetalle(@js($row->cod_especial))"
                                                    title="Ver todos los paquetes de este codigo especial"
                                                >
                                                    {{ $row->cod_especial }}
                                                </button>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ !empty($row->created_at) ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}</td>
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
                                    <tr wire:key="almacen-row-{{ $row->record_type ?? 'row' }}-{{ $row->record_id ?? $loop->index }}">
                                        @if ($this->isAlmacenEms || $this->isTransitoEms || $this->isVentanillaEms || $this->isDevolucionEms)
                                            <td>
                                                @if ($this->isDevolucionEms)
                                                    @if (($row->record_type ?? '') === 'EMS')
                                                        <input type="checkbox" value="{{ $row->record_id }}" wire:model.live="selectedPaquetes" wire:key="check-devolucion-ems-{{ $row->record_id }}">
                                                    @elseif (($row->record_type ?? '') === 'CONTRATO')
                                                        <input type="checkbox" value="{{ $row->record_id }}" wire:model.live="selectedContratos" wire:key="check-devolucion-contrato-{{ $row->record_id }}">
                                                    @elseif (($row->record_type ?? '') === 'SOLICITUD')
                                                        <input type="checkbox" value="{{ $row->record_id }}" wire:model.live="selectedSolicitudes" wire:key="check-devolucion-solicitud-{{ $row->record_id }}">
                                                    @endif
                                                @elseif (($row->record_type ?? '') === 'EMS')
                                                    <input type="checkbox" value="{{ $row->record_id }}" wire:model="selectedPaquetes" wire:key="check-almacen-ems-{{ $row->record_id }}">
                                                @elseif (($row->record_type ?? '') === 'INT')
                                                    <input type="checkbox" value="{{ $row->record_id }}" wire:model="selectedPaquetesInt" wire:key="check-almacen-int-{{ $row->record_id }}">
                                                @elseif (($row->record_type ?? '') === 'CONTRATO')
                                                    <input type="checkbox" value="{{ $row->record_id }}" wire:model="selectedContratos" wire:key="check-almacen-contrato-{{ $row->record_id }}">
                                                @else
                                                    <input type="checkbox" value="{{ $row->record_id }}" wire:model="selectedSolicitudes" wire:key="check-almacen-solicitud-{{ $row->record_id }}">
                                                @endif
                                                @if ($this->isAlmacenEms)
                                                    <span class="ems-almacen-mobile-code pill-id">{{ $row->codigo }}</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td><span class="pill-id">{{ $row->codigo }}</span></td>
                                        @if (!$this->isDevolucionEms && !$this->isAlmacenEms)
                                            <td>
                                                {{ $row->tipo }}
                                            </td>
                                        @endif
                                        @if ($this->isAlmacenEms)
                                            <td>
                                                <span>{{ $row->servicio ?: '-' }}</span>
                                                @if (!empty($row->servicio_especial) && $row->servicio_especial !== '-')
                                                    <small class="d-block text-muted">{{ $row->servicio_especial }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="ems-almacen-route">
                                                    <div class="ems-almacen-route__point">
                                                        <span>Origen</span>
                                                        <strong>{{ $row->origen ?: '-' }}</strong>
                                                    </div>
                                                    <i class="fas fa-arrow-right ems-almacen-route__arrow" aria-hidden="true"></i>
                                                    <div class="ems-almacen-route__point">
                                                        <span>Destino</span>
                                                        <strong>{{ $row->destino ?: '-' }}</strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="d-block text-muted">Remitente</small>
                                                <span>{{ !empty($row->direccion_r) && $row->direccion_r !== '-' ? $row->direccion_r : 'Sin dirección registrada' }}</span>
                                                <small class="d-block text-muted mt-1">Destinatario</small>
                                                <span>{{ !empty($row->direccion_d) && $row->direccion_d !== '-' ? $row->direccion_d : 'Sin dirección registrada' }}</span>
                                            </td>
                                            <td>{{ $row->peso }}</td>
                                            <td>{{ $row->destinatario ?: '-' }}</td>
                                            <td>{{ !empty($row->created_at) ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}</td>
                                        @elseif ($this->isDevolucionEms)
                                            <td>
                                                <strong>{{ $row->servicio ?: '-' }}</strong>
                                                @if (!empty($row->servicio_especial) && $row->servicio_especial !== '-')
                                                    <small class="d-block text-muted">{{ $row->servicio_especial }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="devolucion-route">
                                                    <div class="devolucion-route__point">
                                                        <span class="devolucion-route__label">Origen</span>
                                                        <strong class="devolucion-route__value">{{ $row->origen ?: '-' }}</strong>
                                                    </div>
                                                    <i class="fas fa-arrow-right devolucion-route__arrow" aria-hidden="true"></i>
                                                    <div class="devolucion-route__point">
                                                        <span class="devolucion-route__label">Destino</span>
                                                        <strong class="devolucion-route__value">{{ $row->destino ?: '-' }}</strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="devolucion-direcciones">
                                                <div class="devolucion-direcciones__line">
                                                    <span class="devolucion-direcciones__label">Remitente</span>
                                                    <div class="devolucion-direcciones__value">{{ !empty($row->direccion_r) && $row->direccion_r !== '-' ? $row->direccion_r : 'Sin dirección registrada' }}</div>
                                                </div>
                                                <div class="devolucion-direcciones__line">
                                                    <span class="devolucion-direcciones__label">Destinatario</span>
                                                    <div class="devolucion-direcciones__value">{{ !empty($row->direccion_d) && $row->direccion_d !== '-' ? $row->direccion_d : 'Sin dirección registrada' }}</div>
                                                </div>
                                            </td>
                                            <td>{{ $row->peso ?? '-' }}</td>
                                            <td>{{ $row->destinatario ?: '-' }}</td>
                                            <td>{{ !empty($row->fecha_recojo ?? $row->created_at) ? \Illuminate\Support\Carbon::parse($row->fecha_recojo ?? $row->created_at)->format('d/m/Y H:i') : '-' }}</td>
                                        @else
                                            <td>{{ $row->servicio }}</td>
                                            <td>{{ $row->servicio_especial }}</td>
                                            <td>{{ $row->origen ?: '-' }}</td>
                                            <td>{{ $row->destino }}</td>
                                            <td>{{ $row->contenido }}</td>
                                            <td>{{ $row->cantidad }}</td>
                                            <td>{{ $row->peso }}</td>
                                            <td>{{ $row->remitente }}</td>
                                            <td>{{ $row->destinatario }}</td>
                                            <td>{{ $row->empresa }}</td>
                                            <td>{{ $row->telefono_r }}</td>
                                            <td>{{ $row->telefono_d }}</td>
                                            <td>{{ !empty($row->created_at) ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}</td>
                                            <td>{{ !empty($row->created_at) ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}</td>
                                        @endif
                                        <td class="action-cell">
                                            <div class="action-stack">
                                            @if (($row->record_type ?? '') !== 'INT')
                                            @include('partials.rastreo-eventos-button', [
                                                'tipo' => strtolower((string) ($row->record_type ?? 'EMS')),
                                                'codigo' => $row->codigo,
                                            ])
                                            @endif
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
                                                <button type="button"
                                                   wire:click="abrirOpcionesReimpresion({{ $row->record_id }})"
                                                   class="btn btn-sm btn-outline-azul action-btn"
                                                   title="Reimprimir boleta">
                                                    <i class="fas fa-print"></i>
                                                </button>
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
                                            @elseif (($row->record_type ?? '') === 'CONTRATO')
                                                <a href="{{ route('paquetes-contrato.reporte', $row->record_id, false) }}"
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-azul action-btn"
                                                   title="Reimprimir rotulo">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            @elseif (($row->record_type ?? '') === 'SOLICITUD')
                                                <a href="{{ route('paquetes-ems.solicitudes.ticket', $row->record_id) }}"
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-azul action-btn"
                                                   title="Imprimir ticket de solicitud">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            @else
                                                <span class="badge badge-warning">INT</span>
                                            @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($this->isAlmacenEms || $this->isDevolucionEms) ? 9 : (($this->isTransitoEms || $this->isVentanillaEms) ? 18 : 17) }}" class="text-center py-5">
                                            <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                            <div class="muted">Prueba con otro texto de busqueda.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            @else
                                @php
                                    $hayPaquetesAdmision = $paquetes->count() > 0;
                                    $hayPaquetesIntAdmision = $paquetesIntAdmision->count() > 0;
                                @endphp
                                @forelse ($paquetes as $paquete)
                                    @php
                                        $formulario = $paquete->formulario;
                                    @endphp
                                    <tr>
                                        @if ($this->canSelect)
                                            <td data-label="Seleccionar">
                                                <input type="checkbox" value="{{ $paquete->id }}" wire:model="selectedPaquetes" wire:key="check-admision-ems-{{ $paquete->id }}">
                                            </td>
                                        @endif
                                        <td data-label="Codigo"><span class="pill-id">{{ $paquete->codigo }}</span></td>
                                        <td data-label="Tipo">
                                            @if ($this->isAdmision)
                                                <span class="ems-admision-type">{{ $formulario->tipo_correspondencia ?? $paquete->tipo_correspondencia ?? '-' }}</span>
                                            @else
                                                {{ $formulario->tipo_correspondencia ?? $paquete->tipo_correspondencia }}
                                            @endif
                                        </td>
                                        @if ($this->isAdmision)
                                            <td data-label="Servicio">
                                                <strong>{{ $paquete->servicio_nombre ?: '-' }}</strong>
                                                @php $servicioEspecialAdmision = $formulario->servicio_especial ?? $paquete->servicio_especial; @endphp
                                                @if (!empty($servicioEspecialAdmision) && $servicioEspecialAdmision !== '-')
                                                    <small class="d-block text-muted">{{ $servicioEspecialAdmision }}</small>
                                                @endif
                                            </td>
                                            <td data-label="Ruta">
                                                <div class="ems-admision-route">
                                                    <div><span>Origen</span><strong>{{ $paquete->origen ?: '-' }}</strong></div>
                                                    <i class="fas fa-arrow-right text-muted" aria-hidden="true"></i>
                                                    <div><span>Destino</span><strong>{{ $paquete->destino_nombre ?: ($formulario->ciudad ?? $paquete->ciudad ?? '-') }}</strong></div>
                                                </div>
                                            </td>
                                            <td data-label="Direcciones" class="ems-admision-address">
                                                <span>Remitente</span>
                                                <div>Sin dirección registrada</div>
                                                <span>Destinatario</span>
                                                <div>{{ ($formulario->direccion ?? $paquete->direccion) ?: 'Sin dirección registrada' }}</div>
                                            </td>
                                            <td data-label="Peso">{{ $formulario->peso ?? $paquete->peso ?? '-' }}</td>
                                            <td data-label="Destinatario">{{ $formulario->nombre_destinatario ?? $paquete->nombre_destinatario ?? '-' }}</td>
                                            <td data-label="Creado">{{ optional($paquete->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                        @else
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
                                            <td>{{ optional($paquete->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                        @endif
                                        <td class="action-cell" data-label="Acciones">
                                            <div class="action-stack">
                                            @include('partials.rastreo-eventos-button', [
                                                'tipo' => 'ems',
                                                'codigo' => $paquete->codigo,
                                            ])
                                            @if ($canEmsEdit)
                                            <button wire:click="openEditModal({{ $paquete->id }})"
                                                class="btn btn-sm btn-azul action-btn"
                                                title="Editar">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            @endif
                                            @if ($canEmsPrint)
                                            <button type="button"
                                               wire:click="abrirOpcionesReimpresion({{ $paquete->id }})"
                                               class="btn btn-sm btn-outline-azul action-btn"
                                               title="Reimprimir boleta">
                                                <i class="fas fa-print"></i>
                                            </button>
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
                                @endforelse

                                @foreach ($paquetesIntAdmision as $paqueteInt)
                                    <tr>
                                        @if ($this->canSelect)
                                            <td data-label="Seleccionar"></td>
                                        @endif
                                        <td data-label="Codigo"><span class="pill-id">{{ $paqueteInt->codigo }}</span></td>
                                        <td data-label="Tipo"><span class="ems-admision-type">INT</span></td>
                                        @if ($this->isAdmision)
                                            <td data-label="Servicio">{{ optional($paqueteInt->servicio)->nombre_servicio ?? '-' }}</td>
                                            <td data-label="Ruta">
                                                <div class="ems-admision-route">
                                                    <div><span>Origen</span><strong>{{ $paqueteInt->origen ?: '-' }}</strong></div>
                                                    <i class="fas fa-arrow-right text-muted" aria-hidden="true"></i>
                                                    <div><span>Destino</span><strong>{{ $paqueteInt->destino ?: '-' }}</strong></div>
                                                </div>
                                            </td>
                                            <td data-label="Direcciones" class="ems-admision-address">
                                                <span>Remitente</span>
                                                <div>Sin dirección registrada</div>
                                                <span>Destinatario</span>
                                                <div>Sin dirección registrada</div>
                                            </td>
                                            <td data-label="Peso">{{ \App\Support\BolivianNumber::format((float) $paqueteInt->peso, 3) }}</td>
                                            <td data-label="Destinatario">-</td>
                                            <td data-label="Creado">{{ optional($paqueteInt->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                        @else
                                            <td>-</td>
                                            <td>{{ optional($paqueteInt->servicio)->nombre_servicio ?? '-' }}</td>
                                            <td>{{ $paqueteInt->destino ?: '-' }}</td>
                                            <td>PAQUETE INTERNACIONAL</td>
                                            <td>1</td>
                                            <td>{{ \App\Support\BolivianNumber::format((float) $paqueteInt->peso, 3) }}</td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>{{ $paqueteInt->origen ?: '-' }}</td>
                                            <td>{{ optional($paqueteInt->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                        @endif
                                        <td class="action-cell" data-label="Acciones">
                                            <div class="action-stack">
                                                <span class="badge badge-warning">INT</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach

                                @if (!$hayPaquetesAdmision && !$hayPaquetesIntAdmision)
                                    <tr>
                                        <td colspan="{{ $this->isAdmision ? 10 : ($this->canSelect ? 18 : 17) }}" class="text-center py-5">
                                            <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                            <div class="muted">Prueba con otro texto de busqueda.</div>
                                        </td>
                                    </tr>
                                @endif
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
                                                @include('partials.rastreo-eventos-button', [
                                                    'tipo' => 'contrato',
                                                    'codigo' => $contrato->codigo,
                                                ])
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
                                            <option value="{{ $destino->id }}">{{ $destino->nombre_ems }}</option>
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
                                        <small class="text-muted">Si es OFICIAL, se registra sin precio ni tarifario y se envia directo a facturacion como venta, sin pasar por el carrito.</small>
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

    @if ($this->isAlmacenEms && $canEmsSendRegional)
    @php
        $cn33SelectedTotal = count($selectedPaquetes)
            + count($selectedPaquetesInt)
            + count($selectedContratos)
            + count($selectedSolicitudes);
        $cn33CodigoConfirmacion = strtoupper(trim((string) $cn33ManualCodigo));
    @endphp
    <div class="modal fade" id="confirmarCn33Modal" tabindex="-1" aria-labelledby="confirmarCn33ModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmarCn33ModalLabel">
                        Confirmar actualización de paquete
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Se aplicarán los siguientes cambios a
                        <strong>{{ $cn33SelectedTotal }} {{ $cn33SelectedTotal === 1 ? 'registro seleccionado' : 'registros seleccionados' }}</strong>:
                    </p>

                    <div class="border rounded p-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Estado</span>
                            <strong>TRÁNSITO</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Se añadirá al CN-33</span>
                            <strong>{{ $cn33CodigoConfirmacion !== '' ? $cn33CodigoConfirmacion : 'SIN CÓDIGO' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Nuevo destino</span>
                            <strong>{{ $cn33DestinoConfirmacion !== '' ? $cn33DestinoConfirmacion : 'SIN DESTINO' }}</strong>
                        </div>
                    </div>

                    <p class="mb-0 mt-3 text-muted">
                        Verifica la información antes de continuar.
                    </p>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-dismiss="modal"
                        wire:loading.attr="disabled"
                        wire:target="anadirSeleccionadosCn33"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="btn btn-azul"
                        wire:click="anadirSeleccionadosCn33"
                        data-dismiss="modal"
                        wire:loading.attr="disabled"
                        wire:target="anadirSeleccionadosCn33"
                    >
                        <span wire:loading.remove wire:target="anadirSeleccionadosCn33">Sí, confirmar cambios</span>
                        <span wire:loading.inline-flex wire:target="anadirSeleccionadosCn33" class="align-items-center">
                            <span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cn33CodeErrorModal" tabindex="-1" aria-labelledby="cn33CodeErrorModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cn33CodeErrorModalLabel">Código incorrecto</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Por favor, coloque el código del CN-33, no el código del paquete.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    @endif

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
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Revisar y confirmar admision</h5>
                    <button type="button" class="close" wire:click="closePaqueteConfirmModal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @php
                        $servicioResumen = optional(collect($servicios)->firstWhere('id', (int) $servicio_id))->nombre_servicio;
                        $destinoResumenRow = collect($destinos)->firstWhere('id', (int) $destino_id);
                        $destinoResumen = $destinoResumenRow?->nombre_ems ?: $destinoResumenRow?->nombre_destino;
                    @endphp
                    <div class="confirm-summary">
                        <div class="confirm-summary-group">
                            <h6 class="confirm-summary-group-title">Datos del envio</h6>
                            <div class="confirm-summary-grid">
                                @if (!$this->isAlmacenEms)
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Servicio</span>
                                        <span class="confirm-summary-value">{{ $servicioResumen ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Codigo</span>
                                        <span class="confirm-summary-value">{{ $codigo ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Origen</span>
                                        <span class="confirm-summary-value">{{ $origen ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Destino</span>
                                        <span class="confirm-summary-value">{{ $destinoResumen ?: $ciudad ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Tipo de correspondencia</span>
                                        <span class="confirm-summary-value">{{ $tipo_correspondencia ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Servicio especial</span>
                                        <span class="confirm-summary-value">{{ $servicio_especial ?: '-' }}</span>
                                    </div>
                                @endif
                                <div class="confirm-summary-item is-wide">
                                    <span class="confirm-summary-label">Contenido</span>
                                    <span class="confirm-summary-value">{{ $contenido ?: '-' }}</span>
                                </div>
                                @if (trim((string) $observacion) !== '')
                                    <div class="confirm-summary-item is-wide">
                                        <span class="confirm-summary-label">Observacion</span>
                                        <span class="confirm-summary-value">{{ $observacion }}</span>
                                    </div>
                                @endif
                                <div class="confirm-summary-item">
                                    <span class="confirm-summary-label">Cantidad</span>
                                    <span class="confirm-summary-value">{{ $cantidad }}</span>
                                </div>
                                <div class="confirm-summary-item">
                                    <span class="confirm-summary-label">Peso</span>
                                    <span class="confirm-summary-value">{{ $peso }} kg</span>
                                </div>
                                @if (!$this->isAlmacenEms)
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Precio</span>
                                        <span class="confirm-summary-value">Bs {{ \App\Support\BolivianNumber::format((float) ($precio_confirm ?? $precio), 2, ',', '.') }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Recargo 30 %</span>
                                        <span class="confirm-summary-value">{{ $anadir_recargo_30 ? 'SI' : 'NO' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="confirm-summary-group">
                            <h6 class="confirm-summary-group-title">Remitente y destinatario</h6>
                            <div class="confirm-summary-grid">
                                <div class="confirm-summary-item">
                                    <span class="confirm-summary-label">Remitente</span>
                                    <span class="confirm-summary-value">{{ $nombre_remitente ?: '-' }}</span>
                                </div>
                                <div class="confirm-summary-item">
                                    <span class="confirm-summary-label">Destinatario</span>
                                    <span class="confirm-summary-value">{{ $nombre_destinatario ?: '-' }}</span>
                                </div>
                                @if (!$this->isAlmacenEms)
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Telefono remitente</span>
                                        <span class="confirm-summary-value">{{ $telefono_remitente ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Telefono destinatario</span>
                                        <span class="confirm-summary-value">{{ $telefono_destinatario ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item is-wide">
                                        <span class="confirm-summary-label">Correo electronico</span>
                                        <span class="confirm-summary-value">{{ $correo_electronico ?: 'No registrado' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if (!$this->isAlmacenEms)
                            <div class="confirm-summary-group">
                                <h6 class="confirm-summary-group-title">Datos de entrega</h6>
                                <div class="confirm-summary-grid">
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Ciudad</span>
                                        <span class="confirm-summary-value">{{ $ciudad ?: $destinoResumen ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item">
                                        <span class="confirm-summary-label">Referencia</span>
                                        <span class="confirm-summary-value">{{ $referencia ?: '-' }}</span>
                                    </div>
                                    <div class="confirm-summary-item is-wide">
                                        <span class="confirm-summary-label">Direccion del destinatario</span>
                                        <span class="confirm-summary-value">{{ $direccion ?: '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
                <div class="modal-footer">
                    <div class="submit-loader-note mr-auto" wire:loading.flex wire:target="saveConfirmed">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span>Confirmando y guardando la admision...</span>
                    </div>
                    <button
                        type="button"
                        class="btn btn-outline-azul"
                        wire:click="closePaqueteConfirmModal"
                        wire:loading.attr="disabled"
                        wire:target="saveConfirmed"
                    >
                        Cancelar
                    </button>
                    @if ($editingId ? $canEmsEdit : $canEmsCreate)
                    <button
                        type="button"
                        class="btn btn-dorado"
                        wire:click="saveConfirmed"
                        wire:loading.attr="disabled"
                        wire:target="saveConfirmed"
                    >
                        <span wire:loading.remove wire:target="saveConfirmed">
                            {{ $this->isCreateEms ? 'Confirmar registro' : 'Confirmar y guardar' }}
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

    <div
        class="modal fade @if($showPrintOptionsModal) show d-block @endif"
        id="printOptionsModal"
        tabindex="-1"
        aria-hidden="{{ $showPrintOptionsModal ? 'false' : 'true' }}"
        wire:ignore.self
        @if($showPrintOptionsModal)
            style="background: rgba(0, 0, 0, 0.5);"
        @endif
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom border-dark" style="background: #fff; color: #000;">
                    <h5 class="modal-title">Elegir formato de impresion</h5>
                    <button
                        type="button"
                        class="close text-dark"
                        wire:click="{{ $printOptionsShouldRedirect ? 'volverDespuesDeImprimir' : 'cerrarOpcionesImpresion' }}"
                        aria-label="Close"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        {{ $printOptionsMessage }}
                    </p>
                    <div class="form-group mb-3">
                        <label for="numeroCopiasImpresion">Numero de copias</label>
                        <select id="numeroCopiasImpresion" class="form-control" wire:model.live="numeroCopias">
                            @for ($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'copia' : 'copias' }}</option>
                            @endfor
                        </select>
                        <small class="form-text text-muted">Maximo 5 copias.</small>
                    </div>
                    <div class="d-flex flex-column flex-sm-row" style="gap: 10px;">
                        @if($printTermicaUrl)
                            <a href="{{ $printTermicaUrl }}" target="_blank" class="btn btn-outline-dark flex-fill" data-ems-copy-print-link>
                                <i class="fas fa-receipt mr-1"></i> Factura termica
                            </a>
                        @endif
                        @if($printCartaUrl)
                            <a href="{{ $printCartaUrl }}" target="_blank" class="btn btn-outline-dark flex-fill" data-ems-copy-print-link>
                                <i class="fas fa-file-alt mr-1"></i> Diseno carta
                            </a>
                        @endif
                    </div>
                </div>
                <div class="modal-footer bg-white border-top">
                    <button
                        type="button"
                        class="btn btn-dark"
                        wire:click="{{ $printOptionsShouldRedirect ? 'volverDespuesDeImprimir' : 'cerrarOpcionesImpresion' }}"
                    >
                        {{ $printOptionsShouldRedirect ? 'Volver a paquetes EMS' : 'Cerrar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        class="modal fade @if($showCn38PrintOptionsModal) show d-block @endif"
        id="cn38PrintOptionsModal"
        tabindex="-1"
        aria-hidden="{{ $showCn38PrintOptionsModal ? 'false' : 'true' }}"
        wire:ignore.self
        @if($showCn38PrintOptionsModal)
            style="background: rgba(0, 0, 0, 0.5);"
        @endif
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom border-dark" style="background: #fff; color: #000;">
                    <h5 class="modal-title">Preparar Factura BOA</h5>
                    <button
                        type="button"
                        class="close text-dark"
                        wire:click="cerrarCn38OpcionesImpresion"
                        aria-label="Close"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <strong>1. Revisa los datos.</strong> Luego elige el reporte y el formato. Cada botón descargará un PDF.
                    </div>
                    <div class="form-row align-items-end">
                        <div class="form-group col-sm-4 mb-2">
                            <label for="cn38BagCount">Cantidad de sacas</label>
                            <input
                                id="cn38BagCount"
                                type="number"
                                min="1"
                                class="form-control"
                                wire:model.live="cn38BagCount"
                            >
                        </div>
                        <div class="form-group col-sm-8 mb-2">
                            <label for="cn38TransportMode">Tipo de transporte</label>
                            <select id="cn38TransportMode" class="form-control" wire:model.live="cn38TransportMode">
                                <option value="TERRESTRE">TERRESTRE</option>
                                <option value="AEREO">AÉREO</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        @for ($i = 0; $i < max(1, (int) $cn38BagCount); $i++)
                            <div class="form-group col-md-4 col-sm-6 mb-2" wire:key="cn38-modal-bag-weight-{{ $i }}">
                                <label>
                                    Peso saca {{ str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT) }}
                                    @if ($i === max(1, (int) $cn38BagCount) - 1)
                                        (F)
                                    @endif
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    class="form-control"
                                    placeholder="0.000"
                                    wire:model.live="cn38BagWeights.{{ $i }}"
                                >
                            </div>
                        @endfor
                    </div>
                    <small class="text-muted d-block mb-3">
                        Verifica el peso de cada saca. La última se identificará con <strong>/F</strong>.
                        El despacho aparecerá como {{ strtoupper(trim((string) $cn38Despacho)) ?: 'CN33' }}/0001.
                    </small>
                    <h6 class="font-weight-bold mb-2">2. Elige el reporte y el papel</h6>
                    <p class="small text-muted mb-3">
                        Para la TMII 20, descarga el formato térmico y selecciona esa impresora al imprimir el PDF.
                    </p>
                    <div class="form-row">
                        <div class="form-group col-12 col-md-6 mb-3">
                            <div class="border rounded bg-light p-3 h-100">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge badge-primary mr-2">REPORTE 1</span>
                                    <div>
                                        <strong class="d-block">CN-38</strong>
                                        <small class="text-muted">Factura de entrega</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-primary btn-block" wire:click="generarCn38Lq590">
                                        <i class="fas fa-file-alt mr-1"></i> PDF para hoja bond (A4)
                                    </button>
                                    <small class="text-muted d-block mt-1">Imprimir en papel bond tamaño A4.</small>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-dark btn-block" wire:click="generarCn38Termica">
                                        <i class="fas fa-receipt mr-1"></i> PDF térmico (80 mm)
                                    </button>
                                    <small class="text-muted d-block mt-1">Al imprimir, selecciona la TMII 20.</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-12 col-md-6 mb-3">
                            <div class="border rounded bg-light p-3 h-100">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge badge-secondary mr-2">REPORTE 2</span>
                                    <div>
                                        <strong class="d-block">CN-41</strong>
                                        <small class="text-muted">Factura de entrega</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-primary btn-block" wire:click="generarCn41Lq590">
                                        <i class="fas fa-file-alt mr-1"></i> PDF para hoja bond (A4)
                                    </button>
                                    <small class="text-muted d-block mt-1">Imprimir en papel bond tamaño A4.</small>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-dark btn-block" wire:click="generarCn41Termica">
                                        <i class="fas fa-receipt mr-1"></i> PDF térmico (80 mm)
                                    </button>
                                    <small class="text-muted d-block mt-1">Al imprimir, selecciona la TMII 20.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        wire:click="cerrarCn38OpcionesImpresion"
                    >
                        Cerrar
                    </button>
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
                @php
                    $regionalPesoPendiente = collect($regionalPesoZeroItems ?? [])->contains(function ($item) use ($regionalPesoInputs) {
                        $key = (string) ($item['key'] ?? '');
                        $value = trim((string) ($regionalPesoInputs[$key] ?? ''));
                        return $key === '' || !is_numeric(str_replace(',', '.', $value)) || (float) str_replace(',', '.', $value) <= 0;
                    });
                @endphp
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if (!empty($regionalPesoZeroItems))
                        <div class="alert alert-warning">
                            <strong>Los paquetes con este codigo estan en peso 0.</strong>
                            Por favor asigne el peso aqui mismo para poder mandar a regional.
                        </div>

                        <div class="section-block mb-3">
                            <div class="section-title">Paquetes pendientes de peso</div>
                            @foreach ($regionalPesoZeroItems as $item)
                                <div class="border rounded p-2 mb-2">
                                    <div class="mb-2">
                                        <strong>{{ $item['codigo'] ?? 'SIN CODIGO' }}</strong>
                                        <span class="text-muted">| Tipo: {{ strtoupper($item['type'] ?? '-') }}</span>
                                    </div>
                                    <x-peso-qz-field
                                        :model="'regionalPesoInputs.' . ($item['key'] ?? '')"
                                        :input-id="'regional-peso-' . ($item['key'] ?? '')"
                                        min="0.001"
                                        :required="true"
                                        :use-scale="true"
                                        :show-clear="true"
                                        label="Peso"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif

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
                        <label>Provincia CN-33 (opcional)</label>
                        <input
                            type="text"
                            wire:model.defer="regionalProvincia"
                            class="form-control text-uppercase"
                            oninput="this.value = this.value.toUpperCase()"
                            placeholder="Escribe la provincia que saldra en el CN-33">
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

                    @if ($showRegionalIntSection)
                    <div class="section-block mt-3 mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="section-title mb-1">Paquetes INT</div>
                                <small class="text-muted">Solo se agregan al CN-33. No suman a estadisticas.</small>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 58%;">Codigo</th>
                                        <th style="width: 24%;">Peso</th>
                                        <th style="width: 18%;">Accion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($regionalIntRows as $index => $regionalIntRow)
                                        <tr wire:key="regional-int-row-{{ $index }}">
                                            <td>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    wire:model.defer="regionalIntRows.{{ $index }}.codigo"
                                                    placeholder="Codigo INT">
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    step="0.001"
                                                    min="0.001"
                                                    class="form-control"
                                                    wire:model.defer="regionalIntRows.{{ $index }}.peso"
                                                    placeholder="0.000">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeRegionalIntRow({{ $index }})">
                                                    Quitar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-2 text-right">
                            <button type="button" class="btn btn-link btn-sm p-0" wire:click="addRegionalIntRow">
                                Anadir otro
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsSendRegional && !empty($regionalPesoZeroItems))
                    <button type="button" class="btn btn-outline-success" wire:click="guardarPesosPendientesSeleccionados">
                        Guardar pesos
                    </button>
                    @endif
                    @if ($canEmsSendRegional)
                    <button type="button" class="btn btn-outline-primary" wire:click="toggleRegionalIntSection">
                        {{ $showRegionalIntSection ? 'Ocultar INT' : 'Anadir INT' }}
                    </button>
                    @endif
                    @if ($canEmsSendRegional)
                    <button type="button" class="btn btn-primary" wire:click="mandarSeleccionadosRegional" @disabled($regionalPesoPendiente)>
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
                @php
                    $regionalContratoPesoPendiente = collect($regionalPesoZeroItems ?? [])->contains(function ($item) use ($regionalPesoInputs) {
                        $key = (string) ($item['key'] ?? '');
                        $value = trim((string) ($regionalPesoInputs[$key] ?? ''));
                        return $key === '' || !is_numeric(str_replace(',', '.', $value)) || (float) str_replace(',', '.', $value) <= 0;
                    });
                @endphp
                <div class="modal-header">
                    <h5 class="modal-title">Enviar contratos a regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if (!empty($regionalPesoZeroItems))
                        <div class="alert alert-warning">
                            <strong>Los paquetes con este codigo estan en peso 0.</strong>
                            Por favor asigne el peso aqui mismo para poder mandar a regional.
                        </div>

                        <div class="section-block mb-3">
                            <div class="section-title">Paquetes pendientes de peso</div>
                            @foreach ($regionalPesoZeroItems as $item)
                                <div class="border rounded p-2 mb-2">
                                    <div class="mb-2">
                                        <strong>{{ $item['codigo'] ?? 'SIN CODIGO' }}</strong>
                                        <span class="text-muted">| Tipo: {{ strtoupper($item['type'] ?? '-') }}</span>
                                    </div>
                                    <x-peso-qz-field
                                        :model="'regionalPesoInputs.' . ($item['key'] ?? '')"
                                        :input-id="'regional-contrato-peso-' . ($item['key'] ?? '')"
                                        min="0.001"
                                        :required="true"
                                        :use-scale="true"
                                        :show-clear="true"
                                        label="Peso"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif

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
                        <label>Provincia CN-33 (opcional)</label>
                        <input
                            type="text"
                            wire:model.defer="regionalProvinciaContrato"
                            class="form-control text-uppercase"
                            oninput="this.value = this.value.toUpperCase()"
                            placeholder="Escribe la provincia que saldra en el CN-33">
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
                    @if ($canEmsSendRegional && !empty($regionalPesoZeroItems))
                    <button type="button" class="btn btn-outline-success" wire:click="guardarPesosPendientesSeleccionados">
                        Guardar pesos
                    </button>
                    @endif
                    @if ($canEmsSendRegional)
                    <button type="button" class="btn btn-primary" wire:click="mandarSeleccionadosContratosRegional" @disabled($regionalContratoPesoPendiente)>
                        Confirmar y generar manifiesto
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ventanillaPesoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                @php
                    $ventanillaPesoPendiente = collect($regionalPesoZeroItems ?? [])->contains(function ($item) use ($regionalPesoInputs) {
                        $key = (string) ($item['key'] ?? '');
                        $value = trim((string) ($regionalPesoInputs[$key] ?? ''));
                        return $key === '' || !is_numeric(str_replace(',', '.', $value)) || (float) str_replace(',', '.', $value) <= 0;
                    });
                @endphp
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a ventanilla EMS</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Hay registros seleccionados sin peso.</strong>
                        Completa el peso de todos para poder enviarlos a ventanilla.
                    </div>

                    <div class="section-block mb-0">
                        <div class="section-title">Registros pendientes de peso</div>
                        @forelse ($regionalPesoZeroItems as $item)
                            <div class="border rounded p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <div>
                                        <strong>{{ $item['codigo'] ?? 'SIN CODIGO' }}</strong>
                                        <span class="text-muted">| Tipo: {{ strtoupper($item['type'] ?? '-') }}</span>
                                    </div>
                                    <div class="small text-muted">
                                        Peso actual: {{ \App\Support\BolivianNumber::format((float) ($item['peso'] ?? 0), 3) }} Kg
                                    </div>
                                </div>
                                <x-peso-qz-field
                                    :model="'regionalPesoInputs.' . ($item['key'] ?? '')"
                                    :input-id="'ventanilla-peso-' . ($item['key'] ?? '')"
                                    min="0.001"
                                    :required="true"
                                    :use-scale="true"
                                    :show-clear="true"
                                    label="Nuevo peso"
                                />
                            </div>
                        @empty
                            <div class="text-muted">No hay registros pendientes de peso.</div>
                        @endforelse
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsSendVentanilla && !empty($regionalPesoZeroItems))
                    <button type="button" class="btn btn-outline-success" wire:click="guardarPesosPendientesSeleccionados">
                        Guardar pesos
                    </button>
                    @endif
                    @if ($canEmsSendVentanilla)
                    <button type="button" class="btn btn-primary" wire:click="mandarSeleccionadosVentanillaEms" @disabled($ventanillaPesoPendiente)>
                        Guardar pesos y enviar
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
                    <p class="mb-2 regional-warning-text">
                        Seguro que quieres mandar estos paquetes a <strong>{{ $regionalMismatchDestino ?: 'LA REGIONAL SELECCIONADA' }}</strong>.
                    </p>
                    <p class="mb-2 regional-warning-subtitle">
                        Su destino es:
                    </p>

                    <div class="regional-mismatch-list mb-3">
                        @forelse($regionalMismatchItems as $item)
                            <div class="regional-mismatch-item">
                                <div class="regional-mismatch-row">
                                    <div>
                                        <div class="regional-mismatch-code">{{ $item['codigo'] ?? 'SIN CODIGO' }}</div>
                                        <div class="regional-mismatch-label">Destino original</div>
                                    </div>
                                    <span class="regional-mismatch-destino">{{ $item['destino'] ?? 'SIN DESTINO' }}</span>
                                </div>
                                @if(!empty($item['key']))
                                    <textarea
                                        class="form-control regional-mismatch-observation"
                                        rows="3"
                                        wire:model.defer="regionalMismatchObservaciones.{{ $item['key'] }}"
                                        placeholder="Escribe la observacion que saldra en el CN-33..."
                                    ></textarea>
                                    @error('regionalMismatchObservaciones.' . $item['key'])
                                        <small class="text-danger font-weight-bold d-block mt-1">{{ $message }}</small>
                                    @enderror
                                @endif
                            </div>
                        @empty
                            <div class="text-muted">No hay diferencias de destino.</div>
                        @endforelse
                    </div>

                    <div class="regional-mismatch-alert">
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

    <div class="modal fade" id="envioOficialModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generar envio oficial</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Origen (automatico del usuario)</label>
                        <input type="text" class="form-control" wire:model="oficialOrigen" readonly>
                    </div>

                    <div class="form-group">
                        <label>Destino</label>
                        <select class="form-control" wire:model.defer="oficialDestino">
                            <option value="">Seleccione...</option>
                            @foreach($ciudades as $ciudadOpt)
                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                            @endforeach
                        </select>
                        @error('oficialDestino') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group">
                        <x-peso-qz-field
                            model="oficialPeso"
                            input-id="peso-envio-oficial-modal"
                            min="0.001"
                            :required="true"
                            :use-scale="true"
                            :show-clear="true"
                        />
                    </div>

                    <div class="form-group">
                        <label>Nombre del remitente</label>
                        <input type="text" class="form-control" wire:model.defer="oficialNombreRemitente" placeholder="Ej: Unidad de Administracion">
                        @error('oficialNombreRemitente') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group">
                        <label>Nombre destinatario</label>
                        <input type="text" class="form-control" wire:model.defer="oficialNombreDestinatario" placeholder="Ej: Juan Perez">
                        @error('oficialNombreDestinatario') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group">
                        <label>Direccion destinatario</label>
                        <input type="text" class="form-control" wire:model.defer="oficialDireccionDestinatario" placeholder="Ej: Av. Principal #123">
                        @error('oficialDireccionDestinatario') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group mb-0">
                        <label>Observacion: que se esta mandando <span class="required-star">*</span></label>
                        <input type="text" class="form-control" wire:model.defer="oficialObservacion" placeholder="Que se esta mandando">
                        @error('oficialObservacion') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsCreate)
                    <button type="button" class="btn btn-dorado" wire:click="guardarEnvioOficial">
                        Generar envio oficial
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="paqueteIntModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Anadir paquete INT</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Codigo</label>
                        <input type="text" class="form-control text-uppercase" wire:model.defer="paqueteIntCodigo" placeholder="Ej: INT001">
                        @error('paqueteIntCodigo') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group">
                        <label>Origen (automatico del usuario)</label>
                        <input type="text" class="form-control" wire:model="paqueteIntOrigen" readonly>
                    </div>
                    <div class="form-group">
                        <label>Destino</label>
                        <input type="text" class="form-control text-uppercase" wire:model.defer="paqueteIntDestino" placeholder="Escriba el destino">
                        @error('paqueteIntDestino') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group">
                        <label>Tramo</label>
                        <select class="form-control" wire:model.defer="paqueteIntTramo">
                            <option value="">Seleccione...</option>
                            @foreach($departamentos as $departamento)
                                <option value="{{ $departamento }}">{{ $departamento }}</option>
                            @endforeach
                        </select>
                        @error('paqueteIntTramo') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Peso</label>
                            <input type="number" step="0.001" min="0.001" class="form-control" wire:model.defer="paqueteIntPeso">
                            @error('paqueteIntPeso') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label>Precio</label>
                            <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="paqueteIntPrecio">
                            @error('paqueteIntPrecio') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="registrarPaqueteInt">
                        Guardar paquete INT
                    </button>
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
                    <h5 class="modal-title">Asignar peso a Delivery Express</h5>
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
                    <div class="form-group mt-3 mb-0">
                        <label>Foto de entrega</label>
                        <input type="file" class="form-control-file" wire:model="entregaImagen" accept="image/*">
                        <div wire:loading wire:target="entregaImagen" class="text-muted small mt-2">
                            Cargando imagen...
                        </div>
                        @error('entregaImagen') <small class="text-danger d-block">{{ $message }}</small> @enderror
                        @if ($entregaImagen)
                            <div class="mt-3">
                                <img src="{{ $entregaImagen->temporaryUrl() }}"
                                     alt="Vista previa de la foto de entrega"
                                     class="img-fluid rounded border"
                                     style="max-height: 220px; object-fit: contain;">
                            </div>
                        @endif
                        <small class="text-muted">La foto es obligatoria y se guardara en todos los registros seleccionados.</small>
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
                        <div wire:loading wire:target="devolucionImagen" class="text-muted small mt-2">
                            Cargando imagen...
                        </div>
                        @error('devolucionImagen') <small class="text-danger d-block">{{ $message }}</small> @enderror
                        @if ($devolucionImagen)
                            <div class="mt-3">
                                <img src="{{ $devolucionImagen->temporaryUrl() }}"
                                     alt="Vista previa de la imagen de devolución"
                                     class="img-fluid rounded border"
                                     style="max-height: 220px; object-fit: contain;">
                            </div>
                        @endif
                        <small class="text-muted">Se guardará esta imagen para el registro seleccionado.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    @if ($canEmsDeliver)
                    <button type="button" class="btn btn-primary" wire:click="confirmarDevolucionEms" wire:loading.attr="disabled" wire:target="confirmarDevolucionEms">
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
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="recibirSeleccionadosRegional"
                        wire:loading.attr="disabled"
                        wire:target="recibirSeleccionadosRegional"
                    >
                        <span wire:loading.remove wire:target="recibirSeleccionadosRegional">Confirmar recibido</span>
                        <span wire:loading wire:target="recibirSeleccionadosRegional">Procesando...</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="codEspecialDetalleModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Paquetes del codigo especial</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="cod-especial-summary">
                        <div>
                            <div class="muted small">Codigo especial seleccionado</div>
                            <span class="pill-id">{{ $codEspecialDetalle ?: '-' }}</span>
                        </div>
                        <div class="muted">
                            Total paquetes: <strong>{{ count($codEspecialDetalleRows) }}</strong>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Codigo</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Destinatario</th>
                                    <th>Peso</th>
                                    <th>Estado</th>
                                    <th>Creado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($codEspecialDetalleRows as $item)
                                    <tr>
                                        <td><span class="badge-estado">{{ $item['tipo'] ?? '-' }}</span></td>
                                        <td><span class="pill-id">{{ $item['codigo'] ?? '-' }}</span></td>
                                        <td>{{ $item['origen'] ?? '-' }}</td>
                                        <td>{{ $item['destino'] ?? '-' }}</td>
                                        <td>{{ $item['destinatario'] ?? '-' }}</td>
                                        <td>{{ $item['peso'] ?? '0' }}</td>
                                        <td>{{ $item['estado'] ?? '-' }}</td>
                                        <td>{{ !empty($item['created_at'] ?? null) ? \Illuminate\Support\Carbon::parse($item['created_at'])->format('d/m/Y H:i') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="fw-bold" style="color:var(--azul);">No hay paquetes para este codigo especial</div>
                                            <div class="muted">Selecciona otro codigo especial de la tabla.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    @if ($this->isEnTransitoEms && $canEmsReprintCn33 && count($codEspecialDetalleRows) > 0)
                        <button
                            type="button"
                            class="btn btn-primary"
                            wire:click="reimprimirCn33Detalle"
                            wire:loading.attr="disabled"
                            wire:target="reimprimirCn33Detalle"
                        >
                            <span wire:loading.remove wire:target="reimprimirCn33Detalle">
                                <i class="fas fa-print mr-1"></i> Reimprimir CN-33
                            </span>
                            <span wire:loading wire:target="reimprimirCn33Detalle">Generando...</span>
                        </button>
                    @endif
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
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
            openPrintOptionsModal: '#printOptionsModal',
            closePrintOptionsModal: '#printOptionsModal',
            openCn38PrintOptionsModal: '#cn38PrintOptionsModal',
            closeCn38PrintOptionsModal: '#cn38PrintOptionsModal',
            openGeneradosHoyModal: '#generadosHoyModal',
            closeGeneradosHoyModal: '#generadosHoyModal',
            openRegionalModal: '#regionalModal',
            closeRegionalModal: '#regionalModal',
            openRegionalContratoModal: '#regionalContratoModal',
            closeRegionalContratoModal: '#regionalContratoModal',
            openVentanillaPesoModal: '#ventanillaPesoModal',
            closeVentanillaPesoModal: '#ventanillaPesoModal',
            openRegionalMismatchModal: '#regionalMismatchModal',
            closeRegionalMismatchModal: '#regionalMismatchModal',
            openContratoRegistrarModal: '#contratoRegistrarModal',
            closeContratoRegistrarModal: '#contratoRegistrarModal',
            openEnvioOficialModal: '#envioOficialModal',
            closeEnvioOficialModal: '#envioOficialModal',
            openPaqueteIntModal: '#paqueteIntModal',
            closePaqueteIntModal: '#paqueteIntModal',
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
            openCodEspecialDetalleModal: '#codEspecialDetalleModal',
            closeCodEspecialDetalleModal: '#codEspecialDetalleModal',
            openConfirmarCn33Modal: '#confirmarCn33Modal',
            closeConfirmarCn33Modal: '#confirmarCn33Modal',
            openCn33CodeErrorModal: '#cn33CodeErrorModal',
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

        document.addEventListener('click', (event) => {
            const printLink = event.target.closest('[data-ems-copy-print-link]');
            if (!printLink) {
                return;
            }

            const copiesSelect = document.getElementById('numeroCopiasImpresion');
            const requestedCopies = Number.parseInt(copiesSelect?.value || '1', 10);
            const copies = Math.max(1, Math.min(5, Number.isFinite(requestedCopies) ? requestedCopies : 1));
            const printUrl = new URL(printLink.getAttribute('href'), window.location.origin);

            printUrl.searchParams.set('copias', String(copies));
            printLink.setAttribute('href', printUrl.toString());
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

