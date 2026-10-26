@extends('adminlte::page')
@section('title', 'Crear Bitacora')
@section('template_title')
    Bitacoras
@endsection

@section('css')
    <style>
        :root {
            --bitacora-primary: #20539A;
            --bitacora-secondary: #FECC36;
            --bitacora-bg: #f3f6fc;
            --bitacora-border: #e4e8f2;
        }

        .bitacora-create-wrap {
            max-width: 1500px;
            margin: 0 auto;
            background: linear-gradient(180deg, #f8faff 0%, var(--bitacora-bg) 100%);
            padding: 14px 0 24px;
        }

        .bitacora-create-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 28px rgba(21, 36, 75, 0.1);
            overflow: hidden;
            margin-bottom: 0;
        }

        .bitacora-create-card .card-header {
            background: linear-gradient(105deg, #173f75 0%, #315b99 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .bitacora-create-card .card-title {
            float: none;
            display: block;
            margin: 0;
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.02em;
        }

        .bitacora-create-subtitle {
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.9rem;
        }

        .bitacora-create-eyebrow {
            margin-bottom: 0.3rem;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .bitacora-create-badge {
            flex: 0 0 auto;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            padding: 0.45rem 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .bitacora-create-card .card-body {
            padding: 1.25rem 1.5rem;
            background: #fff;
        }

        .bitacora-create-panel {
            border: 0;
            border-radius: 0;
            background: #fff;
            overflow: visible;
        }

        .bitacora-create-errors {
            border-radius: 12px;
            border: 0;
            border-left: 5px solid #c82333;
            box-shadow: 0 5px 16px rgba(127, 29, 29, 0.08);
        }

        .bitacora-create-pending-alert {
            border: 0;
            border-left: 5px solid #d39100;
            border-radius: 12px;
            background: #fff8e8;
            color: #684900;
            box-shadow: 0 5px 16px rgba(130, 91, 0, 0.08);
        }

        @media (max-width: 767.98px) {
            .bitacora-create-wrap {
                padding-top: 8px;
            }

            .bitacora-create-card .card-body {
                padding: 0.8rem;
            }

            .bitacora-create-card .card-header {
                padding: 0.9rem 1rem;
            }

            .bitacora-create-card .card-title {
                font-size: 1.25rem;
            }

            .bitacora-create-badge {
                align-self: flex-start;
                font-size: 0.62rem;
            }
        }
    </style>
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="bitacora-create-wrap">
            <div class="row">
                <div class="col-md-12">
                    @if ($errors->any())
                        <div class="alert alert-danger bitacora-create-errors">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(((int) data_get($pendingCn33Alert ?? [], 'count', 0)) > 0)
                        <div class="alert alert-warning bitacora-create-pending-alert">
                            <strong>Registrar bitacora de envio nacional.</strong>
                            Hay {{ \App\Support\BolivianNumber::format((int) data_get($pendingCn33Alert, 'count', 0)) }} CN-33 pendientes por mas de {{ (int) data_get($pendingCn33Alert, 'grace_hours', 24) }} horas desde {{ optional(data_get($pendingCn33Alert, 'alert_start_date'))->format('d/m/Y') ?? '17/07/2026' }}.
                            @if((string) data_get($pendingCn33Alert, 'regional', '') !== '')
                                Solo se muestran registros de {{ data_get($pendingCn33Alert, 'regional') }}.
                            @else
                                Se muestran registros a nivel nacional.
                            @endif
                            Retraso maximo: {{ \App\Support\BolivianNumber::format((int) data_get($pendingCn33Alert, 'max_days_delay', 0)) }} dia(s).
                        </div>
                    @endif

                    <div class="card bitacora-create-card">
                        <div class="card-header">
                            <div>
                                <div class="bitacora-create-eyebrow">Operaciones · Bitácoras</div>
                                <h3 class="card-title">Nueva bitácora</h3>
                                <div class="bitacora-create-subtitle">Completa la información del envío y adjunta su evidencia.</div>
                            </div>
                            <span class="bitacora-create-badge">Registro manual</span>
                        </div>
                        <div class="card-body">
                            <div class="bitacora-create-panel">
                                <form method="POST" action="{{ route('bitacoras.store') }}" role="form" enctype="multipart/form-data">
                                    @csrf
                                    @include('bitacoras.form', ['editOnlyPhoto' => false])
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
