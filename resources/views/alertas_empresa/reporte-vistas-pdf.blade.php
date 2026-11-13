<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de vistas - Alerta {{ $alerta->id }}</title>
    <style>
        @page { margin: 30px 32px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #243247; }
        h1 { font-size: 19px; color: #20539a; margin: 0 0 6px; }
        h2 { font-size: 14px; margin: 0 0 8px; }
        .header { border-bottom: 2px solid #20539a; padding-bottom: 12px; margin-bottom: 14px; }
        .meta { margin: 4px 0; }
        .message { white-space: pre-line; overflow-wrap: break-word; }
        .summary { background: #eef3fa; padding: 10px; margin: 14px 0; }
        .note { color: #64748b; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #cbd5e1; padding: 7px; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        th { background: #f1f5f9; }
        .company { background: #e5edf8; color: #163d76; font-size: 12px; }
        .center { text-align: center; }
        .empty { padding: 24px; text-align: center; border: 1px solid #cbd5e1; }
        footer { position: fixed; bottom: -25px; left: 0; right: 0; font-size: 8px; color: #64748b; text-align: right; }
        .page:after { content: counter(page); }
    </style>
</head>
<body>
    <footer>BOLIPOST · Alerta #{{ $alerta->id }} · Página <span class="page"></span></footer>
    <div class="header">
        <h1>Reporte de vistas de alerta</h1>
        <h2>{{ $alerta->titulo }}</h2>
        <p class="meta"><strong>Alerta:</strong> #{{ $alerta->id }} &nbsp; | &nbsp; <strong>Publicación:</strong> {{ $alerta->publicada_at?->format('d/m/Y H:i') }}</p>
        <p class="meta"><strong>Aprobó:</strong> {{ $alerta->aprobador?->name ?: 'Usuario eliminado' }}</p>
        <p class="meta"><strong>Reporte generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</p>
        @if($alerta->mensaje)
            <p class="message">{{ $alerta->mensaje }}</p>
        @endif
    </div>

    <div class="summary">
        <strong>Total de usuarios que marcaron visto:</strong> {{ $alerta->lectores->count() }}
        &nbsp; | &nbsp;
        <strong>Empresas con vistas:</strong> {{ $gruposLectores->filter(fn ($lectores) => $lectores->first()->empresa !== null)->count() }}
    </div>
    <p class="note">Empresas en orden alfabético y usuarios en orden alfabético dentro de cada empresa. Los usuarios sin empresa se muestran al final. Se incluyen las confirmaciones de «visto» registradas por el sistema.</p>

    @forelse($gruposLectores as $lectoresEmpresa)
        @php($empresa = $lectoresEmpresa->first()->empresa)
        <table>
            <thead>
                <tr>
                    <th class="company" colspan="3">
                        {{ $empresa?->nombre ?: 'Sin empresa' }}{{ $empresa?->sigla ? ' ('.$empresa->sigla.')' : '' }}
                        — {{ $lectoresEmpresa->count() }} {{ $lectoresEmpresa->count() === 1 ? 'usuario' : 'usuarios' }}
                    </th>
                </tr>
                <tr>
                    <th style="width: 8%" class="center">N.º</th>
                    <th style="width: 59%">Usuario</th>
                    <th style="width: 33%">Fecha y hora de visto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lectoresEmpresa as $lector)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $lector->name }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($lector->pivot->leida_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div class="empty">Ningún usuario marcó esta alerta como vista hasta la fecha de generación del reporte.</div>
    @endforelse
</body>
</html>
