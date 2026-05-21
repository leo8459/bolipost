@extends('adminlte::page')

@section('title', 'Entregar envio no registrado')

@section('content')
    <div class="ems-delivery-wrap">
        <div class="card ems-delivery-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-1">Entregar envio no registrado</h3>
                    <p class="mb-0">Registra el contrato y lo marca directamente como ENTREGADO.</p>
                </div>
                <a href="{{ route('paquetes-ems.entregados') }}" class="btn btn-light ems-back-btn">Volver</a>
            </div>

            <form method="POST" action="{{ route('paquetes-ems.entregados.no-registrado.store') }}" class="card-body">
                @csrf

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Revisa los datos:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="ems-section">
                    <h4>Datos del envio</h4>
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="codigo">Codigo</label>
                                <input type="text" id="codigo" name="codigo" value="{{ old('codigo') }}"
                                    class="form-control text-uppercase" maxlength="50" required autofocus>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="peso">Peso</label>
                                <input type="number" id="peso" name="peso" value="{{ old('peso') }}"
                                    class="form-control" min="0.001" step="0.001" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ems-section">
                    <h4>Ruta y empresa</h4>
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="origen">Origen</label>
                                <select id="origen" name="origen" class="form-control" required>
                                    <option value="">Seleccione origen</option>
                                    @foreach ($ciudades as $ciudad)
                                        <option value="{{ $ciudad }}" @selected(old('origen', $origen) === $ciudad)>{{ $ciudad }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="destino">Destino</label>
                                <select id="destino" name="destino" class="form-control" required>
                                    <option value="">Seleccione destino</option>
                                    @foreach ($ciudades as $ciudad)
                                        <option value="{{ $ciudad }}" @selected(old('destino') === $ciudad)>{{ $ciudad }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="provincia">Provincia</label>
                                <select id="provincia" name="provincia" class="form-control" data-selected="{{ old('provincia') }}">
                                    <option value="">Sin provincia</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-group">
                                <label for="empresa_id">Empresa</label>
                                <select id="empresa_id" name="empresa_id" class="form-control">
                                    <option value="">Sin empresa</option>
                                    @foreach ($empresas as $empresa)
                                        <option value="{{ $empresa->id }}" @selected((string) old('empresa_id') === (string) $empresa->id)>
                                            {{ $empresa->nombre }}{{ $empresa->codigo_cliente ? ' - ' . $empresa->codigo_cliente : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Si el codigo ya identifica una empresa, se usara automaticamente.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ems-actions">
                    <a href="{{ route('paquetes-ems.entregados') }}" class="btn ems-btn-secondary">Cancelar</a>
                    <button type="submit" class="btn ems-btn-primary">Guardar como entregado</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .ems-delivery-wrap {
            background: #f5f8fc;
            border: 1px solid #dfe7f4;
            border-radius: 14px;
            padding: 16px;
        }

        .ems-delivery-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 14px 28px rgba(31, 56, 107, 0.12);
            overflow: hidden;
        }

        .ems-delivery-card .card-header {
            align-items: center;
            background: #20539A;
            border-bottom: 0;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 1.2rem 1.5rem;
        }

        .ems-delivery-card .card-title {
            font-weight: 800;
            font-size: 1.55rem;
        }

        .ems-delivery-card .card-header p {
            color: rgba(255, 255, 255, 0.86);
            font-weight: 600;
        }

        .ems-section {
            border: 1px solid #dfe5ef;
            border-radius: 10px;
            margin-bottom: 16px;
            padding: 18px;
        }

        .ems-section h4 {
            color: #0b376e;
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .ems-delivery-card label {
            color: #10233f;
            font-weight: 800;
        }

        .ems-delivery-card .form-control {
            border-color: #ccd5e2;
            border-radius: 10px;
            min-height: 44px;
        }

        .ems-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 4px;
        }

        .ems-btn-primary,
        .ems-btn-secondary,
        .ems-back-btn {
            border-radius: 10px;
            font-weight: 800;
            min-height: 42px;
            padding: 0.55rem 1.2rem;
        }

        .ems-btn-primary {
            background: #16a34a;
            border: 0;
            color: #fff;
        }

        .ems-btn-primary:hover {
            background: #15803d;
            color: #fff;
        }

        .ems-btn-secondary {
            background: #fff;
            border: 1px solid #b9c7da;
            color: #20539A;
        }

        .ems-btn-secondary:hover {
            background: #f3f6fb;
            color: #20539A;
        }
    </style>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const provinciasPorDestino = @json($provinciasPorDestino);
            const destino = document.getElementById('destino');
            const provincia = document.getElementById('provincia');
            const selectedProvincia = (provincia.dataset.selected || '').toUpperCase();

            function renderProvincias() {
                const key = (destino.value || '').toUpperCase();
                const provincias = provinciasPorDestino[key] || [];

                provincia.innerHTML = '<option value="">Sin provincia</option>';
                provincias.forEach(function (nombre) {
                    const option = document.createElement('option');
                    option.value = nombre;
                    option.textContent = nombre;
                    if (selectedProvincia === nombre) {
                        option.selected = true;
                    }
                    provincia.appendChild(option);
                });
            }

            destino.addEventListener('change', renderProvincias);
            renderProvincias();
        });
    </script>
@endsection
