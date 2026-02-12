<style>
    :root {
        --carteros-primary: #34447c;
        --carteros-secondary: #b99c46;
        --carteros-bg: #f3f6fc;
        --carteros-text: #1f2937;
    }

    .carteros-wrap {
        background: linear-gradient(180deg, #f8faff 0%, var(--carteros-bg) 100%);
        border: 1px solid #e4e8f2;
        border-radius: 14px;
        padding: 14px;
    }

    .card-carteros {
        border: 0;
        border-radius: 12px;
        box-shadow: 0 12px 28px rgba(21, 36, 75, 0.1);
        overflow: hidden;
    }

    .card-carteros .card-header {
        background: linear-gradient(95deg, var(--carteros-primary) 0%, #43538f 100%);
        color: #fff;
        border-bottom: 0;
        padding: 0.95rem 1.1rem;
    }

    .card-carteros .card-title {
        font-weight: 700;
        letter-spacing: 0.2px;
    }

    .card-carteros .card-body {
        color: var(--carteros-text);
    }

    .card-carteros .table thead th {
        background: #edf1fb;
        color: var(--carteros-primary);
        border-bottom: 1px solid #dbe2f2;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        white-space: nowrap;
    }

    .card-carteros .table td {
        vertical-align: middle;
    }

    .card-carteros .card-footer {
        background: #f8faff;
        border-top: 1px solid #e4e8f2;
    }

    .btn-carteros-primary {
        background: var(--carteros-primary);
        border-color: var(--carteros-primary);
        color: #fff;
        font-weight: 600;
    }

    .btn-carteros-primary:hover {
        background: #2b3967;
        border-color: #2b3967;
        color: #fff;
    }

    .carteros-chip {
        background: rgba(185, 156, 70, 0.2);
        color: #3f3514;
        border: 1px solid rgba(185, 156, 70, 0.35);
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.25rem 0.55rem;
    }

    .carteros-meta {
        color: #5e6b86;
        font-size: 0.82rem;
    }
</style>
