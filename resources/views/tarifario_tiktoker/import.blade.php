@extends('adminlte::page')
@section('title', 'Importar Tarifario Tiktoker')
@section('template_title')
    Importar Tarifario Tiktoker
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($message = Session::get('warning'))
                    <div class="alert alert-warning">
                        <p class="mb-0">{{ $message }}</p>
                    </div>
                @endif
                @if (session()->has('import_errors'))
                    <div class="alert alert-danger">
                        <p class="mb-2"><strong>Filas no guardadas:</strong></p>
                        <ul class="mb-0">
                            @foreach (session('import_errors', []) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card card-default">
                    <div class="card-header">
                        <span class="card-title">Importacion Masiva de Tarifario Tiktoker (Excel)</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <a href="{{ route('tarifario-tiktoker.template-excel') }}" class="btn btn-outline-primary btn-sm">
                                Descargar Plantilla Excel
                            </a>
                        </div>

                        <form id="tiktoker-import-form" method="POST" action="{{ route('tarifario-tiktoker.import') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="archivo">Archivo Excel</label>
                                <input type="file" id="archivo" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".xlsx,.xls" required>
                                @error('archivo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Columnas requeridas en el Excel (fila 1):</strong></p>
                                <code>{{ implode(', ', $columnas) }}</code>
                                <p class="mt-2 mb-2">
                                    La plantilla incluye las hojas <strong>Origenes</strong> y <strong>Destinos</strong>
                                    para que uses los nombres correctos de los departamentos.
                                </p>
                                <p class="mt-2 mb-2">
                                    <strong>servicio_extra</strong> es opcional y debe coincidir con un nombre de la hoja <strong>ServiciosExtras</strong>.
                                </p>
                                <p class="mb-0">
                                    <strong>tiempo_entrega</strong> se importa en horas.
                                </p>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('tarifario-tiktoker.index') }}" class="btn btn-secondary">Volver</a>
                                <button type="submit" class="btn btn-primary">Importar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div id="tiktoker-import-loader" class="tiktoker-import-loader" hidden>
        <div class="tiktoker-import-loader-card">
            <div class="tiktoker-import-loader-title">Importando tarifario tiktoker</div>
            <div id="tiktoker-import-loader-subtitle" class="tiktoker-import-loader-subtitle">Subiendo archivo...</div>
            <div class="tiktoker-import-progress">
                <div id="tiktoker-import-progress-bar" class="tiktoker-import-progress-bar"></div>
            </div>
            <div id="tiktoker-import-progress-text" class="tiktoker-import-progress-text">0%</div>
        </div>
    </div>
    @include('footer')
@endsection

@section('css')
    <style>
        .tiktoker-import-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(10, 24, 44, 0.58);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .tiktoker-import-loader[hidden] {
            display: none;
        }

        .tiktoker-import-loader-card {
            width: min(100%, 460px);
            border-radius: 12px;
            background: #fff;
            padding: 26px;
            box-shadow: 0 24px 60px rgba(10, 24, 44, 0.28);
            text-align: center;
            font-family: Verdana, Arial, sans-serif;
        }

        .tiktoker-import-loader-title {
            color: #0f3f7a;
            font-size: 1.08rem;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .tiktoker-import-loader-subtitle {
            color: #637083;
            font-size: 0.9rem;
            margin-bottom: 18px;
        }

        .tiktoker-import-progress {
            height: 14px;
            border-radius: 999px;
            background: #e7edf7;
            overflow: hidden;
            border: 1px solid #d4deee;
        }

        .tiktoker-import-progress-bar {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: #20539A;
            transition: width 160ms ease;
        }

        .tiktoker-import-progress-text {
            margin-top: 10px;
            color: #0f3f7a;
            font-weight: 800;
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            const form = document.getElementById('tiktoker-import-form');
            const loader = document.getElementById('tiktoker-import-loader');
            const progressBar = document.getElementById('tiktoker-import-progress-bar');
            const progressText = document.getElementById('tiktoker-import-progress-text');
            const subtitle = document.getElementById('tiktoker-import-loader-subtitle');

            if (!form || !loader || !progressBar || !progressText || !subtitle) {
                return;
            }

            function setProgress(value) {
                const safe = Math.max(0, Math.min(100, Math.round(value)));
                progressBar.style.width = safe + '%';
                progressText.textContent = safe + '%';
            }

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                const fileInput = form.querySelector('input[type="file"][name="archivo"]');
                if (!fileInput || !fileInput.files.length) {
                    form.submit();
                    return;
                }

                const request = new XMLHttpRequest();
                const data = new FormData(form);

                loader.hidden = false;
                setProgress(0);
                subtitle.textContent = 'Subiendo archivo...';

                request.upload.addEventListener('progress', function(event) {
                    if (!event.lengthComputable) {
                        return;
                    }

                    setProgress((event.loaded / event.total) * 100);
                });

                request.addEventListener('load', function() {
                    setProgress(100);
                    subtitle.textContent = 'Validando y guardando datos...';

                    window.history.replaceState({}, '', request.responseURL || window.location.href);
                    document.open();
                    document.write(request.responseText);
                    document.close();
                });

                request.addEventListener('error', function() {
                    loader.hidden = true;
                    alert('No se pudo subir el archivo. Revisa tu conexion e intenta nuevamente.');
                });

                request.open('POST', form.action);
                request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                request.send(data);
            });
        })();
    </script>
@endsection
