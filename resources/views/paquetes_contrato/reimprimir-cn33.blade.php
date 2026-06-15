@extends('adminlte::page')

@section('title', 'Reimprimir CN-33')
@section('template_title')
    Reimprimir CN-33
@endsection

@section('content')
    <div class="cn33-wrap">
        <div class="card cn33-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-1">Reimprimir CN-33</h3>
                    <div class="text-muted small">Ingresa el codigo de despacho igual que en Almacen EMS para reimprimir el CN-33.</div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.reimprimir-cn33') }}" class="row align-items-end">
                    <div class="col-lg-4 col-md-6 mb-2">
                        <label class="small font-weight-bold mb-1">Codigo de despacho</label>
                        <input
                            type="text"
                            name="despacho"
                            value="{{ $despacho }}"
                            class="form-control form-control-lg text-uppercase"
                            placeholder="Ej: SRZ00001"
                            autofocus
                        >
                    </div>
                    <div class="col-lg-3 col-md-6 mb-2">
                        <label class="small font-weight-bold mb-1">Origen</label>
                        <select name="origen" class="form-control">
                            <option value="">Origen: todos</option>
                            @foreach($origenes as $option)
                                <option value="{{ $option }}" {{ $origen === $option ? 'selected' : '' }}>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-2">
                        <label class="small font-weight-bold mb-1">Destino</label>
                        <select name="destino" class="form-control">
                            <option value="">Destino: todos</option>
                            @foreach($destinos as $option)
                                <option value="{{ $option }}" {{ $destino === $option ? 'selected' : '' }}>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-2 d-flex">
                        <button type="submit" class="btn btn-primary flex-fill mr-2">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                        <a href="{{ route('dashboard.reimprimir-cn33') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-eraser"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card cn33-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-0">Resultados</h3>
                <div class="text-muted small">
                    @if($despacho !== '')
                        Despacho: <strong>{{ $despacho }}</strong> | Registros: <strong>{{ $paquetes->count() }}</strong>
                    @else
                        Ingresa un codigo de despacho para buscar.
                    @endif
                </div>
            </div>
            @if(session('error') || session('success'))
                <div class="card-body pb-0">
                    @if(session('error'))
                        <div class="alert alert-danger mb-0">{{ session('error') }}</div>
                    @endif
                    @if(session('success'))
                        <div class="alert alert-success mb-0">{{ session('success') }}</div>
                    @endif
                </div>
            @endif
            @if($paquetes->isNotEmpty())
                <div class="card-body border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div class="text-muted mb-2 mb-md-0">
                            Total cantidad: <strong>{{ $totalCantidad }}</strong> |
                            Peso total: <strong>{{ number_format($totalPeso, 3) }} Kg</strong>
                        </div>
                        <div class="d-flex flex-wrap">
                            <a
                                href="{{ route('dashboard.reimprimir-cn33.despacho-excel', ['despacho' => $despacho, 'origen' => $origen, 'destino' => $destino]) }}"
                                class="btn btn-success mr-2 mb-2 mb-md-0"
                            >
                                <i class="fas fa-file-excel mr-1"></i> Exportar CN-33 Excel
                            </a>
                            <a
                                href="{{ route('dashboard.reimprimir-cn33.pdf', ['despacho' => $despacho, 'origen' => $origen, 'destino' => $destino]) }}"
                                class="btn btn-danger"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="fas fa-file-pdf mr-1"></i> Imprimir PDF
                            </a>
                        </div>
                    </div>
                </div>
            @endif
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Codigo</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Cantidad</th>
                                <th>Peso</th>
                                <th>Remitente</th>
                                <th>Observacion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                <tr>
                                    <td><span class="badge badge-info">{{ $paquete->tipo ?? '-' }}</span></td>
                                    <td><strong>{{ $paquete->codigo ?? '-' }}</strong></td>
                                    <td>{{ $paquete->origen ?: '-' }}</td>
                                    <td>{{ $paquete->destino ?: '-' }}</td>
                                    <td>{{ $paquete->cantidad ?: 1 }}</td>
                                    <td>{{ number_format((float) ($paquete->peso ?? 0), 3) }}</td>
                                    <td>{{ $paquete->nombre_remitente ?: '-' }}</td>
                                    <td>{{ $paquete->observacion ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        @if($despacho === '')
                                            Ingresa el codigo de despacho para empezar.
                                        @else
                                            No se encontraron paquetes/contratos/solicitudes para este despacho.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .cn33-wrap {
            background: #f6f8fb;
            border: 1px solid #e1e7f0;
            border-radius: 8px;
            padding: 14px;
        }

        .cn33-card {
            border: 1px solid #dce4ef;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }

        .cn33-card .card-header {
            background: #fff;
            border-bottom-color: #e5edf6;
        }

        .cn33-card .table thead th {
            background: #0f4c81;
            color: #fff;
            border-color: #0f4c81;
            vertical-align: middle;
        }
    </style>
@endsection
