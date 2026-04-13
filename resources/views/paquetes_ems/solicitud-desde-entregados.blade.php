@extends('adminlte::page')
@section('title', 'Crear Solicitud desde EMS Entregados')
@section('template_title')
    Crear Solicitud
@endsection

@section('content')
    <div class="ems-solicitud-wrap">
        <div class="card ems-solicitud-card">
            <div class="card-header ems-header-bar">
                <div>
                    <div class="ems-solicitud-title">Crear solicitud desde EMS entregados</div>
                </div>
                <a href="{{ route('paquetes-ems.entregados', array_filter(['q' => $returnQuery])) }}" class="ems-back-btn">
                    Volver
                </a>
            </div>
            <div class="card-body">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('paquetes-ems.entregados.solicitud.store') }}" class="row g-3 ems-form-grid">
                    @csrf
                    <input type="hidden" name="return_query" value="{{ $returnQuery }}">

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Empresa</label>
                        <select name="empresa_id" class="form-control" required>
                            <option value="">Selecciona una empresa</option>
                            @foreach ($empresas as $empresa)
                                <option value="{{ $empresa->id }}" @selected((int) old('empresa_id') === (int) $empresa->id)>
                                    {{ $empresa->nombre }}@if(!empty($empresa->sigla)) ({{ $empresa->sigla }})@endif - {{ $empresa->codigo_cliente }}
                                </option>
                            @endforeach
                        </select>
                        @error('empresa_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Origen</label>
                        <select name="origen" class="form-control" required>
                            <option value="">Selecciona origen</option>
                            @foreach ($ciudades as $ciudad)
                                <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                            @endforeach
                        </select>
                        @error('origen') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Destino</label>
                        <select name="destino" class="form-control" required>
                            <option value="">Selecciona destino</option>
                            @foreach ($ciudades as $ciudad)
                                <option value="{{ $ciudad }}" @selected(old('destino') === $ciudad)>{{ $ciudad }}</option>
                            @endforeach
                        </select>
                        @error('destino') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Origen direccion</label>
                        <input type="text" name="direccion_r" class="form-control" value="{{ old('direccion_r') }}" required>
                        @error('direccion_r') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Destino direccion</label>
                        <input type="text" name="direccion_d" class="form-control" value="{{ old('direccion_d') }}" required>
                        @error('direccion_d') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Peso</label>
                        <input type="number" step="0.001" min="0.001" name="peso" class="form-control" value="{{ old('peso') }}" required>
                        @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Observacion</label>
                        <textarea name="observacion" rows="4" class="form-control" placeholder="Escribe una observacion opcional...">{{ old('observacion') }}</textarea>
                        @error('observacion') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-12 ems-form-actions">
                        <a href="{{ route('paquetes-ems.entregados', array_filter(['q' => $returnQuery])) }}" class="ems-cancel-btn">
                            Cancelar
                        </a>
                        <button type="submit" class="ems-submit-btn">
                            Crear solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .ems-solicitud-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .ems-solicitud-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .ems-solicitud-card .card-header {
            background: linear-gradient(95deg, #20539A 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.2rem;
        }

        .ems-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            width: 100%;
            flex-wrap: nowrap;
        }

        .ems-solicitud-title {
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .ems-back-btn {
            margin-left: auto;
            align-self: flex-start;
            border: 1px solid rgba(255, 255, 255, 0.65);
            color: #fff;
            background: transparent;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 700;
            text-decoration: none;
        }

        .ems-back-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
            text-decoration: none;
        }

        .ems-solicitud-card .card-body {
            padding: 1.5rem;
        }

        .ems-solicitud-card .form-control,
        .ems-solicitud-card select.form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .ems-solicitud-card .form-control:focus,
        .ems-solicitud-card select.form-control:focus {
            border-color: #20539A;
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

        .ems-solicitud-card .form-label {
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 0.45rem;
        }

        .ems-form-grid {
            align-items: flex-start;
        }

        .ems-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .ems-cancel-btn,
        .ems-submit-btn {
            min-height: 44px;
            border-radius: 12px;
            padding: 10px 18px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .ems-cancel-btn {
            background: #fff;
            color: #20539A;
            border: 1px solid rgba(32, 83, 154, 0.22);
        }

        .ems-cancel-btn:hover {
            background: rgba(32, 83, 154, 0.05);
            color: #20539A;
            text-decoration: none;
        }

        .ems-submit-btn {
            background: #FECC36;
            color: #fff;
            border: 0;
        }

        .ems-submit-btn:hover {
            background: #f4c21d;
            color: #fff;
        }
    </style>
@endsection
