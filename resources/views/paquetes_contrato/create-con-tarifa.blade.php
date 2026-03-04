@extends('adminlte::page')
@section('title', 'Nuevo Contrato con Tarifa')
@section('template_title')
    Nuevo Contrato con Tarifa
@endsection

@section('content')
<section class="content container-fluid pt-3">
    <style>
        .contrato-wrap {
            background: #f5f7fb;
            border-radius: 16px;
            padding: 12px;
        }
        .contrato-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
            overflow: hidden;
        }
        .contrato-header {
            background: linear-gradient(90deg, #20539A, #20539A);
            color: #fff;
            padding: 16px 18px;
        }
        .section-block {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 14px;
            background: #fff;
        }
        .section-title {
            color: #20539A;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: .03em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .required-star {
            color: #dc3545;
            margin-left: 3px;
        }
        .origin-input {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #1e3a8a;
            font-weight: 700;
        }
        .hint-box {
            background: #f0f7ff;
            border: 1px dashed #9ac5ff;
            color: #234;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 14px;
        }
    </style>

    @if (session()->has('error'))
        <div class="alert alert-danger">
            <p class="mb-0">{{ session('error') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(($serviciosTarifa ?? collect())->isEmpty())
        <div class="alert alert-warning">
            No hay tarifas registradas para tu empresa. Primero registra/importa tarifas en "IMPORTAR TARIFAS CONTRATOS".
        </div>
    @endif

    <div class="contrato-wrap">
        <div class="card contrato-card">
            <div class="contrato-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Crear contrato con tarifa</h5>
                    <small>Asigna la tarifa ahora y calcula el precio cuando se registre el peso.</small>
                </div>
                <div class="d-flex" style="gap:8px;">
                    <a href="{{ route('paquetes-contrato.create') }}" class="btn btn-light btn-sm">Crear normal</a>
                    <a href="{{ route('paquetes-contrato.index') }}" class="btn btn-light btn-sm">Volver</a>
                </div>
            </div>

            <div class="card-body">
                <div class="hint-box">
                    El contrato se crea sin peso y sin precio.
                    Cuando se registre el peso (Anadir peso contrato), el sistema calcula automatico:
                    de 0.001 a 1.000 kg usa <strong>kilo</strong>, y desde 1.001 suma <strong>kilo_extra</strong> por cada kg adicional.
                </div>

                <form method="POST" action="{{ route('paquetes-contrato.store-con-tarifa') }}">
                    @csrf

                    <div class="section-block">
                        <div class="section-title">Tarifa</div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Origen (usuario logueado)</label>
                                    <input type="text" class="form-control origin-input" value="{{ $origen }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Servicio<span class="required-star">*</span></label>
                                    <select name="servicio" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($serviciosTarifa as $servicio)
                                            <option value="{{ $servicio }}" {{ old('servicio') === $servicio ? 'selected' : '' }}>
                                                {{ $servicio }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-block">
                        <div class="section-title">Remitente</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre remitente<span class="required-star">*</span></label>
                                    <input type="text" name="nombre_r" class="form-control" value="{{ old('nombre_r') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Telefono remitente<span class="required-star">*</span></label>
                                    <input type="text" name="telefono_r" class="form-control" value="{{ old('telefono_r') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Contenido<span class="required-star">*</span></label>
                                    <textarea name="contenido" rows="2" class="form-control" required>{{ old('contenido') }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Direccion remitente<span class="required-star">*</span></label>
                                    <input type="text" name="direccion_r" class="form-control" value="{{ old('direccion_r') }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-block">
                        <div class="section-title">Destinatario</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre destinatario<span class="required-star">*</span></label>
                                    <input type="text" name="nombre_d" class="form-control" value="{{ old('nombre_d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Telefono destinatario (opcional)</label>
                                    <input type="text" name="telefono_d" class="form-control" value="{{ old('telefono_d') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Destino (departamento)<span class="required-star">*</span></label>
                                    <select id="destinoSelect" name="destino" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($departamentos as $dep)
                                            <option value="{{ $dep }}" {{ old('destino') === $dep ? 'selected' : '' }}>
                                                {{ $dep }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Provincia (opcional)</label>
                                    <select id="provinciaSelect" name="provincia" class="form-control">
                                        <option value="">Seleccione...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Direccion<span class="required-star">*</span></label>
                                    <input type="text" name="direccion" class="form-control" value="{{ old('direccion') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label>Mapa (opcional)</label>
                                    <input type="text" name="mapa" class="form-control" value="{{ old('mapa') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" {{ ($serviciosTarifa ?? collect())->isEmpty() ? 'disabled' : '' }}>
                        Guardar contrato con tarifa
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    (function () {
        const provinciasPorDestino = @json($provinciasPorDestino ?? []);
        const destino = document.getElementById('destinoSelect');
        const provincia = document.getElementById('provinciaSelect');
        const oldProvincia = @json(old('provincia', ''));

        if (!destino || !provincia) return;

        const renderProvincias = (destinoValue, selectedValue = '') => {
            const key = String(destinoValue || '').toUpperCase().trim();
            const provincias = provinciasPorDestino[key] || [];
            const selected = String(selectedValue || '').toUpperCase().trim();

            provincia.innerHTML = '';
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Seleccione...';
            provincia.appendChild(empty);

            provincias.forEach((nombre) => {
                const option = document.createElement('option');
                option.value = nombre;
                option.textContent = nombre;
                if (selected !== '' && nombre === selected) {
                    option.selected = true;
                }
                provincia.appendChild(option);
            });

            if (selected !== '' && !provincias.includes(selected)) {
                const option = document.createElement('option');
                option.value = selected;
                option.textContent = selected;
                option.selected = true;
                provincia.appendChild(option);
            }
        };

        destino.addEventListener('change', () => renderProvincias(destino.value, ''));
        renderProvincias(destino.value, oldProvincia);
    })();
</script>
@endsection
