@extends('adminlte::page')
@section('title', 'Bitacoras')
@section('template_title')
    Bitacoras
@endsection

@section('css')
    <style>
        :root {
            --bitacora-primary: #20539A;
            --bitacora-secondary: #FECC36;
            --bitacora-bg: #f3f6fc;
            --bitacora-border: #e4e8f2;
            --bitacora-text: #1f2937;
        }

        .bitacoras-wrap {
            background: linear-gradient(180deg, #f8faff 0%, var(--bitacora-bg) 100%);
            padding: 14px 0 0;
        }

        .card-bitacoras {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 12px 28px rgba(21, 36, 75, 0.1);
            overflow: hidden;
        }

        .card-bitacoras .card-header {
            background: linear-gradient(95deg, var(--bitacora-primary) 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.2rem;
        }

        .card-bitacoras .card-title {
            float: none;
            display: block;
            font-weight: 800;
            font-size: 1.35rem;
            margin: 0;
        }

        .bitacoras-subtitle {
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.76);
            font-size: 0.92rem;
            line-height: 1.45;
            max-width: 640px;
        }

        .bitacoras-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-dorado {
            background: var(--bitacora-secondary);
            border-color: var(--bitacora-secondary);
            color: #fff;
            min-height: 42px;
            border-radius: 12px;
            font-weight: 800;
            padding-inline: 1rem;
            border: none;
        }

        .btn-dorado:hover {
            filter: brightness(.95);
            color: #fff;
        }

        .card-bitacoras .card-body {
            padding: 1.25rem;
            color: var(--bitacora-text);
        }

        .bitacoras-panel {
            border: 1px solid var(--bitacora-border);
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }

        .bitacoras-filters {
            padding: 18px;
            border-bottom: 1px solid var(--bitacora-border);
            background: linear-gradient(180deg, #fbfcff 0%, #f7faff 100%);
        }

        .bitacoras-filters-title {
            color: var(--bitacora-primary);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
        }

        .bitacoras-filters-subtitle {
            color: #5e6b86;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .bitacoras-filters label {
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 0.45rem;
        }

        .bitacoras-filters .form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .bitacoras-filters .form-control:focus {
            border-color: var(--bitacora-primary);
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

        .bitacoras-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 0.25rem;
        }

        .btn-bitacoras-filter,
        .btn-bitacoras-clear {
            min-height: 44px;
            border-radius: 12px;
            font-weight: 800;
            padding-inline: 1rem;
        }

        .btn-bitacoras-filter {
            background: var(--bitacora-primary);
            border-color: var(--bitacora-primary);
            color: #fff;
        }

        .btn-bitacoras-filter:hover {
            background: #1a4682;
            border-color: #1a4682;
            color: #fff;
        }

        .btn-bitacoras-clear {
            background: #fff;
            border: 1px solid rgba(32, 83, 154, 0.28);
            color: var(--bitacora-primary);
        }

        .btn-bitacoras-clear:hover {
            background: rgba(32, 83, 154, 0.05);
            color: var(--bitacora-primary);
        }

        .bitacoras-table-wrap {
            padding: 0 18px 18px;
        }

        .bitacoras-table-wrap .table {
            margin-bottom: 0;
        }

        .bitacoras-table-wrap .table thead th {
            background: #edf1fb;
            color: var(--bitacora-primary);
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            vertical-align: middle;
        }

        .bitacoras-table-wrap .table td {
            vertical-align: middle;
            border-top: 1px solid rgba(32, 83, 154, 0.08);
        }

        .bitacoras-table-wrap .btn {
            border-radius: 10px;
            font-weight: 700;
        }

        .bitacoras-footer {
            padding: 16px 18px 0;
        }

        .bitacoras-footer .pagination {
            margin-bottom: 0;
        }

        @media (max-width: 991.98px) {
            .bitacoras-table-wrap {
                padding-inline: 0;
            }
        }
    </style>
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="bitacoras-wrap">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card card-bitacoras">
                    <div class="card-header">
                        <div class="bitacoras-header-top">
                            <div>
                                <h3 class="card-title" id="card_title">Administracion de Bitacoras</h3>
                                <div class="bitacoras-subtitle">Gestiona bitacoras, filtros operativos y evidencias asociadas a paquetes provinciales.</div>
                            </div>
                            <a href="{{ route('bitacoras.create') }}" class="btn btn-dorado">Crear Nuevo</a>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    <div class="card-body">
                        <div class="bitacoras-panel">
                            <div class="bitacoras-filters">
                                <div class="bitacoras-filters-title">Busqueda y filtros</div>
                                <div class="bitacoras-filters-subtitle">Refina la lista por usuario, codigo especial o provincia.</div>

                                <form method="GET" action="{{ route('bitacoras.index') }}">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Busqueda general</label>
                                                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cod especial, factura, usuario, codigo...">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Usuario</label>
                                                <select name="user_id" class="form-control">
                                                    <option value="">Todos</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}" {{ (int) $userId === (int) $user->id ? 'selected' : '' }}>
                                                            {{ $user->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Cod especial</label>
                                                <input type="text" name="cod_especial" value="{{ $codEspecial }}" class="form-control" placeholder="Ej: LPZ00001">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Provincia</label>
                                                <select name="provincia" class="form-control">
                                                    <option value="">Todas</option>
                                                    @foreach($provincias as $provinciaItem)
                                                        <option value="{{ $provinciaItem }}" {{ strtoupper((string) $provincia) === strtoupper((string) $provinciaItem) ? 'selected' : '' }}>
                                                            {{ $provinciaItem }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bitacoras-actions">
                                        <button type="submit" class="btn btn-bitacoras-filter">Filtrar</button>
                                        <a href="{{ route('bitacoras.index') }}" class="btn btn-bitacoras-clear">Limpiar</a>
                                    </div>
                                </form>
                            </div>

                            <div class="bitacoras-table-wrap">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead">
                                            <tr>
                                                <th>ID</th>
                                                <th>Cod Especial</th>
                                                <th>Usuario</th>
                                                <th>Paquete EMS</th>
                                                <th>Paquete Contrato</th>
                                                <th>Paquete Ordinario</th>
                                                <th>Paquete Certificado</th>
                                                <th>Transportadora</th>
                                                <th>Provincia</th>
                                                <th>Factura</th>
                                                <th>Precio Total</th>
                                                <th>Peso</th>
                                                <th>Imagen</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($bitacoras as $bitacora)
                                                <tr>
                                                    <td>{{ $bitacora->id }}</td>
                                                    <td>{{ $bitacora->cod_especial }}</td>
                                                    <td>{{ $bitacora->user->name ?? '-' }}</td>
                                                    <td>
                                                        @if($bitacora->paqueteEms)
                                                            #{{ $bitacora->paqueteEms->id }} - {{ $bitacora->paqueteEms->codigo }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($bitacora->paqueteContrato)
                                                            #{{ $bitacora->paqueteContrato->id }} - {{ $bitacora->paqueteContrato->codigo }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($bitacora->paqueteOrdi)
                                                            #{{ $bitacora->paqueteOrdi->id }} - {{ $bitacora->paqueteOrdi->codigo }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($bitacora->paqueteCerti)
                                                            #{{ $bitacora->paqueteCerti->id }} - {{ $bitacora->paqueteCerti->codigo }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>{{ $bitacora->transportadora ?: '-' }}</td>
                                                    <td>{{ $bitacora->provincia ?: '-' }}</td>
                                                    <td>{{ $bitacora->factura ?: '-' }}</td>
                                                    <td>{{ $bitacora->precio_total !== null ? number_format((float) $bitacora->precio_total, 2) : '-' }}</td>
                                                    <td>{{ $bitacora->peso !== null ? number_format((float) $bitacora->peso, 3) : '-' }}</td>
                                                    <td>
                                                        @if($bitacora->imagen_factura)
                                                            <a href="{{ asset('storage/' . $bitacora->imagen_factura) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                                                Ver
                                                            </a>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <form action="{{ route('bitacoras.destroy', $bitacora) }}" method="POST">
                                                            <a class="btn btn-sm btn-success" href="{{ route('bitacoras.edit', $bitacora) }}" title="Editar">
                                                                <i class="fa fa-fw fa-edit"></i>
                                                            </a>
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger btn-sm"
                                                                title="Eliminar"
                                                                onclick="return confirm('Seguro que deseas eliminar esta bitacora?')"
                                                            >
                                                                <i class="fa fa-fw fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="14" class="text-center py-4">No hay registros</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="bitacoras-footer">
                                    {!! $bitacoras->links() !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
