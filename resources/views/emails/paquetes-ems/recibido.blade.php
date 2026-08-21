<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Paquete EMS recibido</title>
</head>
<body style="margin:0;padding:24px;font-family:Verdana,DejaVu Sans,sans-serif;background:#eef2f7;color:#172033;">
    @php
        $destino = (string) ($paquete->formulario?->ciudad ?: $paquete->ciudad);
        $logoCid = null;
        $logoPath = public_path('images/AGBClogo2.png');

        if (isset($message) && is_file($logoPath)) {
            try {
                $logoCid = $message->embedData(
                    file_get_contents($logoPath),
                    'AGBClogo2.png',
                    'image/png'
                );
            } catch (\Throwable $exception) {
                $logoCid = null;
            }
        }
    @endphp

    <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #d8e1ec;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(17,24,39,.08);">
        <div style="background:#fecc36;padding:16px 32px;text-align:center;">
            @if($logoCid)
                <img src="{{ $logoCid }}" alt="Correos de Bolivia" width="250" style="display:block;width:250px;max-width:100%;height:auto;margin:0 auto;border:0;">
            @else
                <strong style="font-size:20px;color:#20539a;">Correos de Bolivia</strong>
            @endif
        </div>
        <div style="background:#20539a;color:#fff;padding:28px 32px;">
            <h1 style="margin:8px 0 0;font-size:26px;line-height:1.2;">Tu paquete EMS fue recibido</h1>
            <p style="margin:10px 0 0;font-size:14px;line-height:1.6;opacity:.94;">La admisi&oacute;n se registr&oacute; correctamente en nuestro sistema.</p>
        </div>

        <div style="padding:32px;">
            <p style="margin-top:0;font-size:15px;">Estimado/a {{ $paquete->nombre_remitente }},</p>
            <p style="font-size:15px;line-height:1.7;">Confirmamos que recibimos tu paquete. Adjuntamos la boleta EMS en formato PDF para tu respaldo.</p>

            <div style="margin:24px 0;padding:22px;border:1px solid #d7e0eb;border-radius:16px;background:#f8fafc;text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#526075;">C&oacute;digo de env&iacute;o</div>
                <div style="margin-top:10px;display:inline-block;padding:13px 20px;background:#f7c948;color:#172033;border-radius:10px;font-size:25px;font-weight:700;letter-spacing:1.5px;">{{ $paquete->codigo }}</div>
            </div>

            <div style="padding:22px;border:1px solid #d7e0eb;border-radius:16px;background:#fff;">
                <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#173f75;">Resumen del env&iacute;o</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:7px 0;color:#526075;width:170px;">Remitente</td><td style="padding:7px 0;font-weight:600;">{{ $paquete->nombre_remitente }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Destinatario</td><td style="padding:7px 0;font-weight:600;">{{ $paquete->nombre_destinatario }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Origen</td><td style="padding:7px 0;font-weight:600;">{{ $paquete->origen }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Destino</td><td style="padding:7px 0;font-weight:600;">{{ $destino }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Contenido</td><td style="padding:7px 0;font-weight:600;">{{ $paquete->contenido }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Peso</td><td style="padding:7px 0;font-weight:600;">{{ number_format((float) $paquete->peso, 3, ',', '.') }} kg</td></tr>
                </table>
            </div>

            <p style="margin:22px 0 0;font-size:14px;line-height:1.7;">Conserva la boleta adjunta para futuras consultas y seguimiento.</p>
            <p style="margin:16px 0 0;font-size:14px;line-height:1.7;">Atentamente,<br><strong>Equipo de Correos de Bolivia</strong></p>
        </div>
    </div>
</body>
</html>
