@extends('adminlte::page')
@section('title', 'Carteros - Entrega')
@section('template_title')
    Entregar Correspondencia
@endsection

@section('content')
    <div class="carteros-wrap entrega-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0">Entregar Correspondencia</h3>
                    <span class="carteros-chip">{{ $tipo_paquete }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row entrega-metadata mb-4">
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Tipo</span>
                            <strong>{{ $tipo_paquete }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Codigo</span>
                            <strong>{{ $paquete['codigo'] ?? '' }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Destinatario</span>
                            <strong>{{ $paquete['destinatario'] ?? '' }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Ciudad</span>
                            <strong>{{ $paquete['ciudad'] ?? '' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="entrega-actions-panel">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                        <h5 class="mb-2 mb-md-0">Acciones de entrega</h5>
                        <span class="badge badge-pill badge-light entrega-badge">
                            Intentos: {{ (int) ($asignacion->intento ?? 0) }}
                        </span>
                    </div>

                    <div class="form-group mb-3">
                        <label for="descripcion_unica">Descripcion (opcional)</label>
                        <textarea id="descripcion_unica" rows="3" class="form-control"
                            placeholder="Escribe una sola descripcion para entrega o intento..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-lg-7">
                            <form method="POST" action="{{ route('carteros.entrega.store') }}" class="mb-3 mb-lg-0">
                                @csrf
                                <input type="hidden" name="tipo_paquete" value="{{ $tipo_paquete }}">
                                <input type="hidden" name="id" value="{{ $id }}">
                                <input type="hidden" name="descripcion" id="descripcion_entrega" value="">

                                <div class="form-group mb-3">
                                    <label for="recibido_por">Recibido por</label>
                                    <input type="text" name="recibido_por" id="recibido_por" class="form-control"
                                        required>
                                </div>

                                <button type="submit" class="btn btn-success px-4">Confirmar entrega</button>
                            </form>
                        </div>
                        <div class="col-lg-5 d-flex align-items-end">
                            <form method="POST" action="{{ route('carteros.entrega.intento') }}" class="w-100">
                                @csrf
                                <input type="hidden" name="tipo_paquete" value="{{ $tipo_paquete }}">
                                <input type="hidden" name="id" value="{{ $id }}">
                                <input type="hidden" name="descripcion" id="descripcion_intento" value="">
                                <button type="submit" class="btn btn-warning px-4">Agregar intento</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    @include('carteros.partials.theme')
    <style>
        .entrega-wrap .card-body {
            background: #f8faff;
        }

        .entrega-meta-box {
            background: #fff;
            border: 1px solid #e3e9f8;
            border-radius: 10px;
            padding: 0.7rem 0.85rem;
            min-height: 72px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 6px 14px rgba(34, 56, 106, 0.06);
        }

        .entrega-meta-label {
            color: #5e6b86;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .entrega-actions-panel {
            background: #fff;
            border: 1px solid #e3e9f8;
            border-radius: 12px;
            padding: 1rem;
        }

        .entrega-badge {
            background: #edf2ff;
            color: #2b3967;
            border: 1px solid #d3ddf4;
            font-size: 0.82rem;
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            const descripcion = document.getElementById('descripcion_unica');
            const entregaInput = document.getElementById('descripcion_entrega');
            const intentoInput = document.getElementById('descripcion_intento');

            if (!descripcion || !entregaInput || !intentoInput) return;

            const syncDescripcion = () => {
                const value = descripcion.value || '';
                entregaInput.value = value;
                intentoInput.value = value;
            };

            descripcion.addEventListener('input', syncDescripcion);
            syncDescripcion();
        })();
    </script>
@endsection
