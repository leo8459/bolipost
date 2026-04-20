<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de incentivos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 24px; color: #163053; }
        .hero { background: #234887; color: #fff; border-radius: 20px 20px 0 0; padding: 24px; }
        .hero h1 { margin: 0 0 8px; font-size: 28px; }
        .badge { display: inline-block; padding: 10px 14px; border-radius: 14px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18); }
        .score { float: right; text-align: center; min-width: 100px; }
        .score .value { font-size: 34px; font-weight: 700; color: #ffd84a; }
        .card { border: 1px solid #e2e8f0; border-radius: 0 0 20px 20px; padding: 24px; }
        .section-title { font-size: 18px; font-weight: 700; margin: 0 0 14px; }
        .rank-grid { width: 100%; margin-bottom: 20px; }
        .rank-card { border: 1px solid #e5edf7; border-radius: 14px; padding: 12px; }
        .rank-card.primary { background: #111d35; color: #fff; border-color: #111d35; }
        .two-cols { width: 100%; margin-bottom: 20px; }
        .box { border-radius: 14px; padding: 14px; border: 1px solid #dbe7f6; margin-bottom: 12px; }
        .green { background: #eefcf1; border-color: #d7f0dc; }
        .blue { background: #eef4ff; border-color: #dbe7ff; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #e6edf7; font-size: 12px; text-align: left; }
        th { background: #f4f7fb; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        .muted { color: #6b7b92; }
    </style>
</head>
<body>
    <div class="hero">
        <div class="score">
            <div class="muted" style="color:#dbe6fb;">Score global</div>
            <div class="value">{{ number_format($scoreGlobal, 1) }}</div>
        </div>
        <div style="font-size:11px; letter-spacing:0.18em; text-transform:uppercase;">Inteligencia de flota</div>
        <h1>Incentivos {{ $from->translatedFormat('F Y') }}</h1>
        <div class="badge">Emitido el {{ $generatedAt->translatedFormat('d \\d\\e F, Y H:i') }}</div>
        <div style="margin-top:10px;">Periodo: {{ $from->translatedFormat('d/m/Y') }} al {{ $to->translatedFormat('d/m/Y') }}</div>
    </div>

    <div class="card">
        <div class="section-title">Cuadro de Honor</div>
        <table class="rank-grid">
            <tr>
                @forelse($rankingTop as $index => $report)
                    <td width="33.33%" style="padding-right:10px;">
                        <div class="rank-card {{ $index === 0 ? 'primary' : '' }}">
                            <div>#{{ $index + 1 }}</div>
                            <div style="font-size:18px; font-weight:700; margin:8px 0;">{{ $report->driver?->nombre ?? 'Sin conductor' }}</div>
                            <div>{{ str_repeat('★', (int) $report->stars_end) }}{{ str_repeat('☆', max($maxStars - (int) $report->stars_end, 0)) }}</div>
                            <div style="margin-top:8px;">{{ (int) $report->preventive_requests }} preventivos OK</div>
                        </div>
                    </td>
                @empty
                    <td class="muted">No hay ranking disponible para este periodo.</td>
                @endforelse
            </tr>
        </table>

        <table class="two-cols">
            <tr>
                <td width="50%" style="vertical-align:top; padding-right:12px;">
                    <div class="section-title">Impacto Economico</div>
                    <div class="box green">
                        <div class="muted">Ahorro estimado</div>
                        <div style="font-size:24px; font-weight:700; color:#159c57;">${{ number_format((float) $estimatedSavings, 0) }}</div>
                    </div>
                    <div class="box blue">
                        <div class="muted">Bonos a pagar</div>
                        <div style="font-size:24px; font-weight:700; color:#2458a6;">${{ number_format((float) $pendingBonuses, 0) }}</div>
                    </div>
                </td>
                <td width="50%" style="vertical-align:top;">
                    <div class="section-title">Detalle General</div>
                    <div class="box">
                        <div class="muted">Conductores evaluados</div>
                        <div style="font-size:24px; font-weight:700;">{{ $reports->count() }}</div>
                    </div>
                    <div class="box">
                        <div class="muted">Conductores excelentes</div>
                        <div style="font-size:24px; font-weight:700;">{{ $reports->where('stars_end', $maxStars)->count() }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="section-title">Resumen por Conductor</div>
        <table>
            <thead>
                <tr>
                    <th>Conductor</th>
                    <th>Estrellas</th>
                    <th>Preventivos OK</th>
                    <th>Correctivos</th>
                    <th>Total revisado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>{{ $report->driver?->nombre ?? 'Sin conductor' }}</td>
                        <td>{{ (int) $report->stars_end }} / {{ $maxStars }}</td>
                        <td>{{ (int) $report->preventive_requests }}</td>
                        <td>{{ (int) $report->non_preventive_requests }}</td>
                        <td>{{ (int) $report->total_requests }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No hay informacion para exportar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
