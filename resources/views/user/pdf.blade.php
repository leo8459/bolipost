<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de usuarios</title>
    <style>
        body {
            font-family: Verdana, DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        h1 {
            margin: 0 0 4px;
            color: #20539a;
            font-size: 20px;
        }

        .meta {
            margin-bottom: 14px;
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #20539a;
            color: #fff;
            font-weight: bold;
            text-align: left;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 5px;
            vertical-align: top;
        }

        tr:nth-child(even) td {
            background: #f8fafc;
        }

        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Reporte de usuarios</h1>
    <div class="meta">
        Generado: {{ $generatedAt->format('d/m/Y H:i') }} | Total: {{ $users->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="center">#</th>
                <th>Nombre</th>
                <th>Alias</th>
                <th>Email</th>
                <th>Regional</th>
                <th>CI</th>
                <th>Empresa</th>
                <th>Sucursal</th>
                <th>Roles</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->alias ?: '-' }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->regionalesTexto() ?: '-' }}</td>
                    <td>{{ $user->ci ?: '-' }}</td>
                    <td>
                        @if ($user->empresa)
                            {{ $user->empresa->codigo_cliente }} - {{ $user->empresa->nombre }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if ($user->sucursal)
                            Suc. {{ $user->sucursal->codigoSucursal }} / PV {{ $user->sucursal->puntoVenta }} - {{ $user->sucursal->municipio }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $user->roles->pluck('name')->implode(', ') ?: '-' }}</td>
                    <td>{{ $user->trashed() ? 'Inactivo' : 'Activo' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center">No existen usuarios registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
