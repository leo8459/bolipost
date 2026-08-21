<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Paquete en departamento de destino</title>
</head>
<body style="margin:0;padding:24px;font-family:Verdana,DejaVu Sans,sans-serif;background:#eef2f7;color:#172033;">
    @php
        $logoCid = null;
        $logoPath = public_path('images/AGBClogo2.png');
        $fechaRecepcionTexto = $fechaRecepcion instanceof \DateTimeInterface
            ? $fechaRecepcion->format('d/m/Y H:i')
            : (string) $fechaRecepcion;

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
            <h1 style="margin:0;font-size:26px;line-height:1.25;">Paquete en departamento de destino</h1>
            <p style="margin:10px 0 0;font-size:15px;line-height:1.6;opacity:.94;">
                Tu paquete EMS ya fue recibido en {{ $destino ?: 'el departamento de destino' }}.
            </p>
        </div>

        <div style="padding:32px;">
            <p style="margin-top:0;font-size:15px;">Estimado/a {{ $paquete->nombre_remitente }},</p>
            <p style="font-size:15px;line-height:1.7;">
                Te confirmamos que el paquete con c&oacute;digo <strong>{{ $paquete->codigo }}</strong>
                lleg&oacute; y fue recibido en el departamento de destino <strong>{{ $destino }}</strong>.
            </p>

            <div style="margin:24px 0;padding:22px;border:1px solid #d7e0eb;border-radius:16px;background:#f8fafc;text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#526075;">Ubicaci&oacute;n actual</div>
                <div style="margin-top:10px;display:inline-block;padding:13px 20px;background:#f7c948;color:#172033;border-radius:10px;font-size:23px;font-weight:700;letter-spacing:1px;">{{ $destino ?: 'DESTINO' }}</div>
                <div style="margin-top:12px;color:#20539a;font-size:14px;font-weight:700;">RECIBIDO</div>
            </div>

            <div style="padding:22px;border:1px solid #d7e0eb;border-radius:16px;background:#fff;">
                <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#173f75;">Detalle de la recepci&oacute;n</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:7px 0;color:#526075;width:190px;">C&oacute;digo del paquete</td><td style="padding:7px 0;font-weight:600;">{{ $paquete->codigo }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Origen</td><td style="padding:7px 0;font-weight:600;">{{ $paquete->origen ?: '-' }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Departamento de destino</td><td style="padding:7px 0;font-weight:600;">{{ $destino ?: '-' }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Fecha de recepci&oacute;n</td><td style="padding:7px 0;font-weight:600;">{{ $fechaRecepcionTexto }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Manifiesto CN-33</td><td style="padding:7px 0;font-weight:600;">{{ $paquete->cod_especial ?: '-' }}</td></tr>
                </table>
            </div>

            <p style="margin:22px 0 0;font-size:14px;line-height:1.7;">El paquete continuar&aacute; con el proceso correspondiente para su entrega.</p>
            <p style="margin:16px 0 0;font-size:14px;line-height:1.7;">Atentamente,<br><strong>Equipo de Correos de Bolivia</strong></p>
        </div>
    </div>
</body>
</html>
