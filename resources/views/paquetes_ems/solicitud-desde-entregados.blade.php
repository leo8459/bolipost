@extends('adminlte::page')
@section('title', 'Crear Solicitud desde EMS Entregados')
@section('template_title')
    Crear Solicitud
@endsection

@section('content')
    <div class="ems-solicitud-wrap">
        <div class="card ems-solicitud-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-2 mb-md-0">Crear solicitud desde EMS entregados</h3>
                <a href="{{ route('paquetes-ems.entregados', array_filter(['q' => $returnQuery])) }}" class="btn btn-sm btn-outline-light">
                    Volver
                </a>
            </div>
            <div class="card-body">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('paquetes-ems.entregados.solicitud.store') }}" class="row g-3">
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

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('paquetes-ems.entregados', array_filter(['q' => $returnQuery])) }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-success">
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
            padding: 0.95rem 1.1rem;
        }
    </style>
@endsection
