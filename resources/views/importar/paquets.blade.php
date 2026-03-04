@extends('adminlte::page')
@section('title', 'Importar Paquets')
@section('template_title')
    Importar Paquets
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

                <div class="card card-default">
                    <div class="card-header">
                        <span class="card-title">IMPORTAR PAQUETS</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <a href="{{ route('importar.paquets.template-excel') }}" class="btn btn-outline-primary btn-sm">
                                Descargar Plantilla Excel
                            </a>
                        </div>

                        <form method="POST" action="{{ route('importar.paquets.store') }}" enctype="multipart/form-data">
                            @csrf
                            @php
                                $estadoDefaultInicial = $estadoDefaultPorDestino[$tipoDestinoPorDefecto] ?? '';
                            @endphp
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label for="tipo_destino">Destino de importacion</label>
                                    <select
                                        id="tipo_destino"
                                        name="tipo_destino"
                                        class="form-control @error('tipo_destino') is-invalid @enderror"
                                        required
                                    >
                                        @foreach($tiposDestino as $codigoDestino => $nombreDestino)
                                            <option value="{{ $codigoDestino }}" @selected(old('tipo_destino', $tipoDestinoPorDefecto) == $codigoDestino)>
                                                {{ $nombreDestino }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('tipo_destino')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="ciudad">Ciudad (para todos los registros)</label>
                                    <select
                                        id="ciudad"
                                        name="ciudad"
                                        class="form-control @error('ciudad') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Seleccione</option>
                                        @foreach($ciudades as $ciudad)
                                            <option value="{{ $ciudad }}" @selected(old('ciudad', $ciudadPorDefecto) == $ciudad)>
                                                {{ $ciudad }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ciudad')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="fk_estado">Estado</label>
                                    <select
                                        id="fk_estado"
                                        name="fk_estado"
                                        class="form-control @error('fk_estado') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Seleccione</option>
                                        @foreach($estados as $estado)
                                            <option value="{{ $estado->id }}" @selected((string) old('fk_estado', $estadoDefaultInicial) === (string) $estado->id)>
                                                {{ $estado->nombre_estado }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fk_estado')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="fk_ventanilla">Ventanilla</label>
                                    <select
                                        id="fk_ventanilla"
                                        name="fk_ventanilla"
                                        class="form-control @error('fk_ventanilla') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Seleccione</option>
                                        @foreach($ventanillas as $ventanilla)
                                            <option value="{{ $ventanilla->id }}" @selected(old('fk_ventanilla') == $ventanilla->id)>
                                                {{ $ventanilla->nombre_ventanilla }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fk_ventanilla')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group col-md-4">
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
                            </div>

                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Columnas requeridas en la fila 1 del Excel:</strong></p>
                                <code>{{ implode(', ', $columnas) }}</code>
                                <hr>
                                <p class="mb-0">
                                    Si eliges <strong>PAQUETES ORDINARIOS</strong>, la columna <strong>TIPO</strong> se guarda en
                                    <strong>observaciones</strong>.
                                </p>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">Importar</button>
                            </div>
                        </form>

                        @if(session('success'))
                            <div class="alert alert-success mt-3 mb-0">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('warning'))
                            <div class="alert alert-warning mt-3">
                                {{ session('warning') }}
                            </div>
                        @endif

                        @if(session('import_errors'))
                            <div class="alert alert-danger mt-3 mb-0">
                                <p class="mb-2"><strong>Detalle de filas con error:</strong></p>
                                <ul class="mb-0">
                                    @foreach(session('import_errors') as $importError)
                                        <li>{{ $importError }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tipoDestino = document.getElementById('tipo_destino');
            const estado = document.getElementById('fk_estado');
            const defaults = @json($estadoDefaultPorDestino);

            if (!tipoDestino || !estado) {
                return;
            }

            const applyDefaultEstado = () => {
                const current = String(estado.value || '').trim();
                if (current !== '') {
                    return;
                }

                const defaultEstado = defaults[tipoDestino.value];
                if (defaultEstado) {
                    estado.value = String(defaultEstado);
                }
            };

            tipoDestino.addEventListener('change', function () {
                const defaultEstado = defaults[tipoDestino.value];
                if (defaultEstado) {
                    estado.value = String(defaultEstado);
                }
            });

            applyDefaultEstado();
        });
    </script>
@endsection

