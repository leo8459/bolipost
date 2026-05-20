@once
    <style>
        .bp-livewire-skin .btn:not(.btn-close),
        .bp-livewire-skin a.btn {
            border-radius: 14px;
            font-weight: 700;
            letter-spacing: .01em;
            transition: transform .14s ease, box-shadow .14s ease, background-color .14s ease, border-color .14s ease, color .14s ease;
            box-shadow: 0 2px 8px rgba(16, 44, 84, .08);
        }

        .bp-livewire-skin .btn:not(.btn-close):hover,
        .bp-livewire-skin a.btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(16, 44, 84, .15);
        }

        .bp-livewire-skin .btn-primary {
            background-color: #1f56a5;
            border-color: #1f56a5;
            color: #ffffff;
        }

        .bp-livewire-skin .btn-outline-primary {
            border-color: #96b4df;
            color: #1f56a5;
            background-color: #ffffff;
        }

        .bp-livewire-skin .btn-warning {
            background-color: #f4c62f;
            border-color: #f4c62f;
            color: #1f2f49;
        }

        .bp-livewire-skin .btn-outline-warning {
            border-color: #f4c62f;
            color: #8a6800;
            background-color: #fffaf0;
        }

        .bp-livewire-skin .btn-success {
            background-color: #17a34a;
            border-color: #17a34a;
            color: #ffffff;
        }

        .bp-livewire-skin .btn-outline-success {
            border-color: #5ebd80;
            color: #0d6e34;
            background-color: #f4fff8;
        }

        .bp-livewire-skin .btn-danger {
            background-color: #e33d4b;
            border-color: #e33d4b;
            color: #ffffff;
        }

        .bp-livewire-skin .btn-outline-danger {
            border-color: #e79ba1;
            color: #a3202b;
            background-color: #fff7f8;
        }

        .bp-livewire-skin .btn-secondary {
            background-color: #66758a;
            border-color: #66758a;
            color: #ffffff;
        }

        .bp-livewire-skin .btn-outline-secondary {
            border-color: #b8c3d0;
            color: #4a5a70;
            background-color: #ffffff;
        }

        .bp-livewire-skin .btn.btn-sm:not(.btn-close),
        .bp-livewire-skin a.btn.btn-sm {
            min-height: 34px;
            min-width: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .bp-livewire-skin .bp-icon-btn {
            width: 38px;
            height: 38px;
            padding: 0;
            border-radius: 10px;
        }

        .bp-livewire-skin .bp-action-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .bp-livewire-skin .bp-reactivate-btn {
            border-color: #41ad67 !important;
            color: #107337 !important;
            background-color: #eefbf2 !important;
        }

        .bp-livewire-skin .bp-reactivate-btn:hover {
            border-color: #1f8f4a !important;
            color: #0d5f2f !important;
            background-color: #ddf6e5 !important;
        }
    </style>
@endonce
