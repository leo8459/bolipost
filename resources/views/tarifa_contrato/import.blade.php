@extends('adminlte::page')
@section('title', 'Importar Tarifas Contrato')
@section('template_title')
    Importar Tarifas Contrato
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                @include('tarifa_contrato.import-feedback')

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card card-default">
                    <div class="card-header">
                        <span class="card-title">Importacion Masiva de Tarifas (Excel)</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            @aclcan('export', null, 'tarifa-contrato')
                            <a href="{{ route('tarifa-contrato.template-excel') }}" data-template-link class="btn btn-outline-primary btn-sm">
                                Descargar Plantilla Excel
                            </a>
                            @endaclcan
                        </div>

                        <form id="tarifaImportForm" method="POST" action="{{ route('tarifa-contrato.import') }}" enctype="multipart/form-data">
                            @csrf
                            @include('tarifa_contrato.tarifario-select')
                            <div class="form-group mb-3">
                                <label for="archivo">Archivo Excel</label>
                                <input
                                    type="file"
                                    id="archivo"
                                    name="archivo"
                                    class="form-control @error('archivo') is-invalid @enderror"
                                    accept=".xlsx,.xls"
                                    required
                                >
                                @error('archivo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Columnas requeridas en el Excel (fila 1):</strong></p>
                                <code>{{ implode(', ', $columnas) }}</code>
                                <p class="mt-2 mb-2">
                                    Selecciona el año y el tarifario de la empresa antes de importar. El archivo carga precios en ese borrador.
                                    Cuando estén listos, aprueba su uso en <a href="{{ route('tarifa-contrato.tarifarios') }}#tarifarios-registrados">Tarifarios registrados</a>.
                                </p>
                                <p class="mt-2 mb-2">
                                    <strong>Obligatorios:</strong> Origen, Destino, Servicio, Primer kilogramo: precio (Bs), Kilogramo extra: precio (Bs), Entrega (horas).
                                </p>
                                <p class="mt-2 mb-2">
                                    <strong>Opcionales:</strong> Dirección, Zona, Provincia origen, Provincia destino y Retención (%) (si se omite se guarda 0).
                                </p>
                                <p class="mb-2">Para precios locales por zona, usa una fila por zona para la misma ruta. La columna Zona distingue esos precios al importar.</p>
                                <p class="mt-2 mb-2">
                                    En la plantilla, <strong>Provincia origen</strong> y <strong>Provincia destino</strong>
                                    son campos libres. Puedes escribir cualquier provincia directamente.
                                </p>
                                <p class="mt-2 mb-2">
                                    La plantilla <strong>ya no incluye fila de ejemplo</strong> para evitar cargas equivocadas.
                                </p>
                                <hr>
                                <p class="mb-1"><strong>Servicios permitidos:</strong></p>
                                <ul class="mb-2" id="year-services-list"></ul>
                                <p class="mb-1"><strong>Origen y Destino permitidos</strong></p>
                                <p class="mb-1">Usa la lista de la plantilla para ambos campos.</p>
                                <p class="mb-0">{{ implode(', ', $departamentos) }}</p>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('tarifa-contrato.index') }}" class="btn btn-secondary">Volver</a>
                                @aclcan('import', null, 'tarifa-contrato')
                                <button id="tarifaImportSubmitBtn" type="submit" class="btn btn-primary">Importar</button>
                                @endaclcan
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="tarifaImportLoader" class="tarifa-import-loader d-none" aria-hidden="true">
        <div class="tarifa-import-loader-card">
            <div class="tarifa-import-loader-title">Subiendo tarifarios...</div>
            <div class="tarifa-import-loader-subtitle">Por favor espera mientras se procesa el archivo.</div>
            <div class="tarifa-import-progress">
                <div id="tarifaImportProgressBar" class="tarifa-import-progress-bar"></div>
            </div>
            <div id="tarifaImportProgressText" class="tarifa-import-progress-text">0%</div>
        </div>
    </div>

    <style>
        .tarifa-import-loader {
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(15, 23, 42, 0.38);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .tarifa-import-loader.d-none {
            display: none !important;
        }

        .tarifa-import-loader-card {
            width: min(100%, 460px);
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dbe5f1;
            box-shadow: 0 20px 45px rgba(2, 6, 23, 0.24);
            padding: 18px 18px 16px;
        }

        .tarifa-import-loader-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f3b77;
            margin-bottom: 4px;
        }

        .tarifa-import-loader-subtitle {
            font-size: 0.9rem;
            color: #4a648f;
            margin-bottom: 12px;
        }

        .tarifa-import-progress {
            width: 100%;
            height: 12px;
            border-radius: 999px;
            background: #eaf1fb;
            overflow: hidden;
            border: 1px solid #d3e0f3;
        }

        .tarifa-import-progress-bar {
            width: 0%;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #1f4f96 0%, #2d6cc2 100%);
            transition: width 0.35s ease;
        }

        .tarifa-import-progress-text {
            margin-top: 8px;
            text-align: right;
            font-size: 0.86rem;
            color: #355d96;
            font-weight: 700;
        }
    </style>

    <script>
        (function () {
            const form = document.getElementById('tarifaImportForm');
            const loader = document.getElementById('tarifaImportLoader');
            const progressBar = document.getElementById('tarifaImportProgressBar');
            const progressText = document.getElementById('tarifaImportProgressText');
            const submitBtn = document.getElementById('tarifaImportSubmitBtn');

            if (!form || !loader || !progressBar || !progressText) {
                return;
            }

            let intervalId = null;

            const setProgress = (value) => {
                const safe = Math.max(0, Math.min(100, Math.round(value)));
                progressBar.style.width = safe + '%';
                progressText.textContent = safe + '%';
            };

            form.addEventListener('submit', function () {
                loader.classList.remove('d-none');
                loader.setAttribute('aria-hidden', 'false');
                setProgress(6);

                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                let progress = 6;
                intervalId = window.setInterval(function () {
                    // Progreso visual mientras el servidor procesa la importacion.
                    if (progress < 90) {
                        progress += Math.random() * 8;
                    } else if (progress < 97) {
                        progress += Math.random() * 1.2;
                    }
                    setProgress(progress);
                }, 280);
            });

            window.addEventListener('beforeunload', function () {
                if (intervalId) {
                    window.clearInterval(intervalId);
                }
                setProgress(100);
            });
        })();
    </script>

    @include('footer')
@endsection
