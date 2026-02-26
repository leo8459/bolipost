@extends('adminlte::page')
@section('title', 'Eventos EMS')
@section('template_title')
    Eventos EMS
@endsection

@section('content')
    <div class="eventos-ems-wrap">
        <div class="card eventos-ems-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-2 mb-md-0">Eventos EMS</h3>
                <span class="eventos-ems-chip">Total: {{ $eventosEms->total() }}</span>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('eventos-ems.index') }}" class="row mb-3">
                    <div class="col-md-10 mb-2 mb-md-0">
                        <input type="text" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Buscar por codigo, evento o usuario...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Buscar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Codigo</th>
                                <th>Evento</th>
                                <th>Usuario</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($eventosEms as $registro)
                                <tr>
                                    <td>{{ $registro->id }}</td>
                                    <td>{{ $registro->codigo }}</td>
                                    <td>{{ $registro->evento_nombre ?? ('#' . $registro->evento_id) }}</td>
                                    <td>{{ $registro->usuario_nombre ?? ('#' . $registro->user_id) }}</td>
                                    <td>{{ optional($registro->created_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        No hay registros en eventos_ems.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-end">
                    {{ $eventosEms->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .eventos-ems-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .eventos-ems-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .eventos-ems-card .card-header {
            background: linear-gradient(95deg, #34447c 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 0.95rem 1.1rem;
        }

        .eventos-ems-chip {
            background: rgba(185, 156, 70, 0.2);
            color: #3f3514;
            border: 1px solid rgba(185, 156, 70, 0.35);
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.28rem 0.6rem;
        }

        .eventos-ems-card .table thead th {
            background: #edf1fb;
            color: #34447c;
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }
    </style>
@endsection
