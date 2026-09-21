@extends('adminlte::page')

@section('title', 'Todos mis paquetes')
@section('template_title', 'Todos mis paquetes')

@section('content')
    <div class="mis-paquetes-wrap">
        <div class="card mis-paquetes-card">
            <div class="mis-paquetes-header">
                <div>
                    <h3 class="mb-1">Todos mis paquetes</h3>
                    <p class="mb-0">Paquetes generados por {{ auth()->user()->name }}.</p>
                </div>
                <div class="mis-paquetes-total">
                    <span>Total</span>
                    <strong>{{ $paquetes->total() }}</strong>
                </div>
            </div>

            <div class="card-body">
                <form method="GET" action="{{ route('paquetes-contrato.mis-paquetes') }}" class="filter-grid mb-4">
                    <div class="filter-search">
                        <label for="q">Buscar paquete</label>
                        <input id="q" type="search" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Código, remitente, destinatario, origen o destino">
                    </div>
                    <div>
                        <label for="estado_id">Estado</label>
                        <select id="estado_id" name="estado_id" class="form-control">
                            <option value="">Todos los estados</option>
                            @foreach ($estados as $estado)
                                <option value="{{ $estado->id }}" @selected((int) $estadoId === (int) $estado->id)>
                                    {{ $estado->nombre_estado }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fecha_desde">Desde</label>
                        <input id="fecha_desde" type="date" name="fecha_desde" value="{{ $fechaDesde }}" class="form-control">
                    </div>
                    <div>
                        <label for="fecha_hasta">Hasta</label>
                        <input id="fecha_hasta" type="date" name="fecha_hasta" value="{{ $fechaHasta }}" class="form-control">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Buscar</button>
                        <a href="{{ route('paquetes-contrato.mis-paquetes') }}" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Destinatario</th>
                                <th>Peso</th>
                                <th>Generado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                <tr>
                                    <td><span class="package-code">{{ $paquete->codigo ?: '-' }}</span></td>
                                    <td><span class="status-pill">{{ optional($paquete->estadoRegistro)->nombre_estado ?? 'Sin estado' }}</span></td>
                                    <td>{{ $paquete->origen ?: '-' }}</td>
                                    <td>{{ $paquete->destinoParaMostrar() }}</td>
                                    <td>{{ $paquete->nombre_d ?: '-' }}</td>
                                    <td>{{ App\Support\BolivianNumber::format((float) ($paquete->peso ?? 0), 3) }} kg</td>
                                    <td>{{ optional($paquete->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="text-center">
                                        @include('partials.rastreo-eventos-button', [
                                            'tipo' => 'contrato',
                                            'codigo' => $paquete->codigo,
                                            'class' => 'btn btn-sm btn-track',
                                            'text' => 'Rastrear',
                                        ])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="fas fa-box-open"></i>
                                        <strong>No se encontraron paquetes</strong>
                                        <span>Aún no generaste paquetes o no hay resultados con estos filtros.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none mobile-packages">
                    @forelse ($paquetes as $paquete)
                        <article class="mobile-package-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="package-code">{{ $paquete->codigo ?: '-' }}</span>
                                <span class="status-pill">{{ optional($paquete->estadoRegistro)->nombre_estado ?? 'Sin estado' }}</span>
                            </div>
                            <dl>
                                <div><dt>Ruta</dt><dd>{{ $paquete->origen ?: '-' }} → {{ $paquete->destinoParaMostrar() }}</dd></div>
                                <div><dt>Destinatario</dt><dd>{{ $paquete->nombre_d ?: '-' }}</dd></div>
                                <div><dt>Peso</dt><dd>{{ App\Support\BolivianNumber::format((float) ($paquete->peso ?? 0), 3) }} kg</dd></div>
                                <div><dt>Generado</dt><dd>{{ optional($paquete->created_at)->format('d/m/Y H:i') ?: '-' }}</dd></div>
                            </dl>
                            @include('partials.rastreo-eventos-button', [
                                'tipo' => 'contrato',
                                'codigo' => $paquete->codigo,
                                'class' => 'btn btn-track btn-block',
                                'text' => 'Rastrear paquete',
                            ])
                        </article>
                    @empty
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <strong>No se encontraron paquetes</strong>
                            <span>Aún no generaste paquetes o no hay resultados con estos filtros.</span>
                        </div>
                    @endforelse
                </div>

                @if ($paquetes->hasPages())
                    <div class="d-flex justify-content-end mt-4">
                        {{ $paquetes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .mis-paquetes-wrap { padding: 18px; background: #f4f7fb; border-radius: 16px; }
        .mis-paquetes-card { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 28px rgba(32, 83, 154, .10); }
        .mis-paquetes-header { display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; padding: 20px 24px; color: #fff; background: linear-gradient(110deg, #20539a, #34447c); }
        .mis-paquetes-header h3 { font-weight: 800; }
        .mis-paquetes-header p { color: rgba(255, 255, 255, .78); }
        .mis-paquetes-total { min-width: 100px; padding: 10px 18px; border: 1px solid rgba(255,255,255,.28); border-radius: 14px; text-align: center; background: rgba(255,255,255,.1); }
        .mis-paquetes-total span { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; }
        .mis-paquetes-total strong { display: block; font-size: 1.65rem; line-height: 1.1; }
        .filter-grid { display: grid; grid-template-columns: minmax(240px, 2fr) repeat(3, minmax(150px, 1fr)) auto; gap: 12px; align-items: end; }
        .filter-grid label { display: block; margin-bottom: 6px; color: #475569; font-size: .8rem; font-weight: 800; }
        .filter-grid .form-control, .filter-grid .btn { min-height: 42px; border-radius: 10px; }
        .filter-actions { display: flex; gap: 8px; }
        .filter-actions .btn-primary { border-color: #20539a; background: #20539a; }
        .table thead th { border-top: 0; border-bottom: 2px solid #dce5f1; color: #20539a; background: #f6f8fc; white-space: nowrap; }
        .table td { vertical-align: middle; }
        .package-code { display: inline-block; padding: 5px 10px; border-radius: 999px; color: #20539a; background: rgba(32,83,154,.1); font-weight: 800; white-space: nowrap; }
        .status-pill { display: inline-block; padding: 5px 10px; border-radius: 999px; color: #755700; background: #fff4c7; font-size: .78rem; font-weight: 800; white-space: nowrap; }
        .btn-track { border: 1px solid #20539a; border-radius: 10px; color: #20539a; background: #fff; font-weight: 800; }
        .btn-track:hover { color: #fff; background: #20539a; }
        .empty-state { padding: 48px 20px !important; color: #64748b; text-align: center; }
        .empty-state i, .empty-state strong, .empty-state span { display: block; }
        .empty-state i { margin-bottom: 10px; color: #94a3b8; font-size: 2rem; }
        .empty-state strong { margin-bottom: 4px; color: #334155; }
        .mobile-package-card { margin-bottom: 14px; padding: 16px; border: 1px solid #dce5f1; border-radius: 14px; background: #fff; box-shadow: 0 6px 16px rgba(32,83,154,.06); }
        .mobile-package-card dl { margin-bottom: 14px; }
        .mobile-package-card dl div { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid #eef2f7; }
        .mobile-package-card dt { color: #64748b; font-size: .8rem; }
        .mobile-package-card dd { margin: 0; color: #1e293b; text-align: right; font-weight: 600; }
        @media (max-width: 1199.98px) { .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .filter-search { grid-column: span 2; } }
        @media (max-width: 575.98px) { .mis-paquetes-wrap { padding: 8px; } .mis-paquetes-header { padding: 18px; } .filter-grid { grid-template-columns: 1fr; } .filter-search { grid-column: auto; } .filter-actions { flex-wrap: wrap; } }
    </style>
@endsection
