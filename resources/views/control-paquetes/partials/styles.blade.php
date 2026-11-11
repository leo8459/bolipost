    <style>
        .delivery-control-wrap { --delivery-ink: #112f61; --delivery-muted: #617a9e; --delivery-line: #dfe8f3; color: var(--delivery-ink); }
        .content-header { padding-top: 18px; padding-bottom: 17px; }
        .delivery-control-heading-icon { width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 60px; margin-right: 17px; border-radius: 17px; color: #fff; background: linear-gradient(145deg, #1c5799, #123b78); font-size: 1.45rem; box-shadow: 0 8px 18px rgba(24, 74, 136, .2); }
        .delivery-control-heading h1 { color: #102e5e; font-size: 1.65rem; font-weight: 800; letter-spacing: -.035em; }
        .delivery-control-heading p { color: #4d6992 !important; font-size: .92rem; font-weight: 600; }
        .delivery-control-heading-actions { display: flex; align-items: center; gap: 20px; }
        .delivery-control-billing-link { border: 1px solid #b7c9e3; border-radius: 13px; padding: 10px 18px; color: #143d78; background: rgba(255,255,255,.7); font-weight: 800; white-space: nowrap; }
        .delivery-control-billing-link:hover { border-color: #6b91c4; background: #fff; color: #0d2e62; }
        .delivery-control-wrap .delivery-control-kpi { position: relative; display: flex; align-items: center; gap: 15px; min-height: 100px; border: 1px solid var(--delivery-line); border-radius: 15px; padding: 15px 18px; height: 100%; box-shadow: 0 7px 20px rgba(32, 68, 112, .045); overflow: hidden; }
        .delivery-control-wrap .delivery-control-kpi--blue { border-color: #c9defb; background: linear-gradient(115deg, #f0f6ff, #fbfdff); color: #1557a8; }
        .delivery-control-wrap .delivery-control-kpi--green { border-color: #c7ebd8; background: linear-gradient(115deg, #effbf4, #fbfefc); color: #147744; }
        .delivery-control-wrap .delivery-control-kpi--amber { border-color: #ffe2a4; background: linear-gradient(115deg, #fff8e9, #fffdf8); color: #8c5200; }
        .delivery-control-wrap .delivery-control-kpi--red { border-color: #ffcbd0; background: linear-gradient(115deg, #fff0f1, #fffafa); color: #a71f27; }
        .delivery-control-wrap .delivery-control-kpi-icon { width: 64px; height: 64px; display: inline-flex; flex: 0 0 64px; align-items: center; justify-content: center; border-radius: 15px; background: rgba(255,255,255,.68); font-size: 1.6rem; }
        .delivery-control-wrap .delivery-control-kpi--blue .delivery-control-kpi-icon { background: #dceaff; }
        .delivery-control-wrap .delivery-control-kpi--green .delivery-control-kpi-icon { background: #d8f5e5; }
        .delivery-control-wrap .delivery-control-kpi--amber .delivery-control-kpi-icon { background: #ffedbf; }
        .delivery-control-wrap .delivery-control-kpi--red .delivery-control-kpi-icon { background: #ffdcdf; }
        .delivery-control-wrap .delivery-control-kpi-label { color: inherit; font-size: .88rem; font-weight: 750; }
        .delivery-control-wrap .delivery-control-kpi-value { color: #112f61; font-size: 1.85rem; line-height: 1.12; font-weight: 850; margin-top: 3px; }
        .delivery-control-wrap .delivery-control-kpi-arrow { margin-left: auto; color: #2b66b2; font-size: 1.1rem; }
        .delivery-control-wrap .delivery-control-filters { border: 1px solid var(--delivery-line); border-radius: 15px; background: #fff; padding: 17px 18px 10px; box-shadow: 0 7px 20px rgba(32, 68, 112, .035); }
        .delivery-control-wrap .delivery-control-filters label { color: #193b70; font-size: .79rem; font-weight: 750; margin-bottom: 6px; }
        .delivery-control-wrap .delivery-control-filters .form-control { height: 43px; border-radius: 9px; border-color: #d4e0ee; color: #15345f; box-shadow: inset 0 1px 2px rgba(18, 53, 98, .025); }
        .delivery-control-wrap .delivery-control-filters .form-control:focus { border-color: #80a8da; box-shadow: 0 0 0 .16rem rgba(36, 103, 183, .12); }
        .delivery-control-wrap .delivery-control-filters .input-group-text { min-width: 43px; justify-content: center; border-color: #d4e0ee; border-radius: 9px 0 0 9px; color: #194b8d; }
        .delivery-control-wrap .delivery-control-filters .input-group .form-control { border-radius: 0 9px 9px 0; }
        .delivery-control-wrap .delivery-control-filters .btn { height: 43px; border-radius: 9px; font-weight: 800; }
        .delivery-control-wrap .delivery-control-filters .btn-warning { color: #102f60; background: #ffc928; border-color: #ffc928; }
        .delivery-control-wrap .delivery-control-filters .btn-warning:hover { background: #f5b900; border-color: #f5b900; }
        .delivery-control-wrap .delivery-control-clear { width: 46px; border: 1px solid #5382bf; color: #174b8c; background: #fff; }
        .delivery-control-wrap .delivery-control-table-card { border: 1px solid var(--delivery-line); border-radius: 15px; overflow: hidden; background: #fff; box-shadow: 0 7px 20px rgba(32, 68, 112, .045); }
        .delivery-control-wrap .delivery-control-table { margin: 0; min-width: 1020px; }
        .delivery-control-wrap .delivery-control-table thead th { background: #f3f7fc; border-top: 0; border-bottom: 1px solid var(--delivery-line); color: #536d93; font-size: .71rem; letter-spacing: .025em; text-transform: uppercase; white-space: nowrap; padding: 12px 13px; }
        .delivery-control-wrap .delivery-control-table td { border-top: 1px solid #e8eef6; color: #112f61; font-size: .82rem; padding: 10px 13px; vertical-align: middle; }
        .delivery-control-wrap .delivery-control-table tbody tr { transition: background-color .15s ease; }
        .delivery-control-wrap .delivery-control-table tbody tr:hover { background: #f8fbff; }
        .delivery-control-wrap .delivery-control-card-heading { padding: 16px 20px; border-bottom: 1px solid var(--delivery-line); display: flex; align-items: center; justify-content: space-between; }
        .delivery-control-wrap .delivery-control-card-heading strong { color: var(--delivery-ink); font-size: 1.03rem; font-weight: 800; }
        .delivery-control-wrap .delivery-control-card-heading p { color: var(--delivery-muted); font-size: .77rem; margin: 3px 0 0; }
        .delivery-control-wrap .delivery-control-card-heading .badge { color: #193b70; background: #f3f7fc; border: 1px solid #e1e9f3 !important; border-radius: 8px; padding: 7px 10px; font-weight: 750; }
        .delivery-control-wrap .delivery-control-code { color: #102f61; font-weight: 850; white-space: nowrap; }
        .delivery-control-wrap .delivery-control-secondary { display: block; color: var(--delivery-muted); font-size: .72rem; margin-top: 3px; }
        .delivery-control-wrap .delivery-control-route { min-width: 165px; font-size: .77rem !important; font-weight: 800; white-space: nowrap; text-transform: uppercase; }
        .delivery-control-wrap .delivery-control-route i { color: #4779b7; margin: 0 7px; }
        .delivery-control-wrap .delivery-control-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 7px 11px; font-size: .72rem; line-height: 1.2; font-weight: 800; white-space: nowrap; }
        .delivery-control-wrap .badge-paid { background: #dff7ea; color: #08733d; }
        .delivery-control-wrap .badge-destination { background: #fff0c9; color: #885000; }
        .delivery-control-wrap .badge-process { background: #e3efff; color: #155cad; }
        .delivery-control-wrap .badge-error { background: #ffe2e4; color: #ab171e; }
        .delivery-control-wrap .badge-muted { background: #edf1f6; color: #566e91; }
        .delivery-control-wrap .badge-transit { background: #e0efff; color: #155cad; }
        .delivery-control-wrap .badge-preparing { background: #edf1f6; color: #566e91; }
        .delivery-control-wrap .delivery-control-actions { display: flex; justify-content: flex-end; align-items: center; gap: 6px; white-space: nowrap; }
        .delivery-control-wrap .delivery-control-invoice-action, .delivery-control-wrap .delivery-control-view-action { border-radius: 8px; font-size: .75rem; font-weight: 750; }
        .delivery-control-wrap .delivery-control-view-action { min-width: 77px; border-color: #6e9bd3; color: #174b8c; }
        .delivery-control-wrap .delivery-control-empty { padding: 46px 20px; text-align: center; color: var(--delivery-muted); }
        .delivery-control-wrap .delivery-control-empty i { color: #9eb4d0; font-size: 2rem; margin-bottom: 10px; }
        .delivery-control-wrap .pagination .page-link { color: #174b8c; border-color: #e0e9f4; }
        .delivery-control-wrap .pagination .page-item.active .page-link { background: #174b8c; border-color: #174b8c; }
        .delivery-control-updated { color: #547097; font-size: .8rem; white-space: nowrap; }
        .delivery-control-modal .modal-content { border: 0; border-radius: 16px; box-shadow: 0 18px 50px rgba(14, 39, 78, .2); }
        .delivery-control-modal .modal-header { border-bottom-color: #e5edf7; }
        .delivery-control-modal .modal-title { color: #102f61; font-weight: 800; }
        .delivery-control-modal .modal-body { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 13px; }
        .delivery-control-detail { padding: 11px 13px; border: 1px solid #e4ebf4; border-radius: 10px; background: #f8fafd; }
        .delivery-control-detail span { display: block; color: #667f9f; font-size: .7rem; font-weight: 750; text-transform: uppercase; }
        .delivery-control-detail strong { display: block; color: #163965; margin-top: 3px; overflow-wrap: anywhere; }
        @media (max-width: 991.98px) {
            .delivery-control-heading-actions { margin-top: 13px; width: 100%; justify-content: space-between; }
        }
        @media (max-width: 767.98px) {
            .delivery-control-heading { align-items: flex-start !important; }
            .delivery-control-heading h1 { font-size: 1.25rem; }
            .delivery-control-heading p { font-size: .8rem; }
            .delivery-control-heading-icon { width: 48px; height: 48px; flex-basis: 48px; border-radius: 14px; margin-right: 11px; }
            .delivery-control-heading-actions { align-items: flex-start; gap: 10px; flex-direction: column; }
            .delivery-control-billing-link { padding: 8px 13px; }
            .delivery-control-wrap .delivery-control-kpi { min-height: 82px; padding: 11px; gap: 9px; border-radius: 12px; }
            .delivery-control-wrap .delivery-control-kpi-icon { width: 43px; height: 43px; flex-basis: 43px; border-radius: 11px; font-size: 1.15rem; }
            .delivery-control-wrap .delivery-control-kpi-label { font-size: .67rem; }
            .delivery-control-wrap .delivery-control-kpi-value { font-size: 1.45rem; }
            .delivery-control-wrap .delivery-control-kpi-arrow { display: none; }
            .delivery-control-wrap .delivery-control-filters { padding: 14px 14px 5px; }
            .delivery-control-wrap .delivery-control-table-card { border-radius: 12px; }
            .delivery-control-wrap .delivery-control-card-heading { padding: 13px 14px; gap: 8px; }
            .delivery-control-wrap .delivery-control-card-heading strong { font-size: .91rem; }
            .delivery-control-wrap .delivery-control-card-heading p { max-width: 220px; }
            .delivery-control-modal .modal-body { grid-template-columns: 1fr; }
        }
    </style>
