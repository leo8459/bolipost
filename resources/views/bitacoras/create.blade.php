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
            background: linear-gradient(180deg, #f8faff 0%, var(--bitacora-bg) 100%);
            padding-top: 14px;
        }

        .bitacora-create-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 28px rgba(21, 36, 75, 0.1);
            overflow: hidden;
        }

        .bitacora-create-card .card-header {
            background: linear-gradient(95deg, var(--bitacora-primary) 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.2rem;
        }

        .bitacora-create-card .card-title {
            float: none;
            display: block;
            margin: 0;
            font-weight: 800;
            font-size: 1.45rem;
        }

        .bitacora-create-subtitle {
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.76);
            font-size: 0.92rem;
        }

        .bitacora-create-card .card-body {
            padding: 1.25rem;
            background: #fff;
        }

        .bitacora-create-panel {
            border: 1px solid var(--bitacora-border);
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }

        .bitacora-create-errors {
            border-radius: 12px;
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

                    <div class="card bitacora-create-card">
                        <div class="card-header">
                            <h3 class="card-title">Crear Bitacora</h3>
                            <div class="bitacora-create-subtitle">Registra una nueva bitacora provincial con datos operativos y evidencia adjunta.</div>
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
