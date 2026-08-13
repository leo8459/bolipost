<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Preenv&iacute;o EMS registrado</title>
</head>
<body style="margin:0;padding:24px;font-family:Verdana,DejaVu Sans,sans-serif;background:#eef2f7;color:#172033;">
    @php
        $codigo = (string) ($preregistro->codigo_preregistro ?: $preregistro->codigo_generado);
        $destino = (string) ($preregistro->destino?->nombre_preregistro ?: $preregistro->ciudad);
    @endphp

    <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #d8e1ec;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(17,24,39,.08);">
        <div style="background:#20539a;color:#fff;padding:28px 32px;">
            <div style="font-size:12px;letter-spacing:1.2px;text-transform:uppercase;opacity:.88;">Correos de Bolivia</div>
            <h1 style="margin:8px 0 0;font-size:26px;line-height:1.2;">Preenv&iacute;o EMS registrado</h1>
            <p style="margin:10px 0 0;font-size:14px;line-height:1.6;opacity:.94;">Recibimos correctamente los datos de tu preregistro.</p>
        </div>

        <div style="padding:32px;">
            <p style="margin-top:0;font-size:15px;">Estimado/a {{ $preregistro->nombre_remitente }},</p>
            <p style="font-size:15px;line-height:1.7;">Tu preenv&iacute;o EMS fue registrado satisfactoriamente. Presenta el siguiente c&oacute;digo en admisi&oacute;n para que nuestro personal recupere tus datos y complete el env&iacute;o.</p>

            <div style="margin:24px 0;padding:22px;border:1px solid #d7e0eb;border-radius:16px;background:#f8fafc;text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#526075;">C&oacute;digo de preregistro</div>
                <div style="margin-top:10px;display:inline-block;padding:13px 20px;background:#f7c948;color:#172033;border-radius:10px;font-size:25px;font-weight:700;letter-spacing:1.5px;">{{ $codigo }}</div>
            </div>

            <div style="padding:22px;border:1px solid #d7e0eb;border-radius:16px;background:#fff;">
                <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#173f75;">Resumen del preenv&iacute;o</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:7px 0;color:#526075;width:170px;">Remitente</td><td style="padding:7px 0;font-weight:600;">{{ $preregistro->nombre_remitente }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Destinatario</td><td style="padding:7px 0;font-weight:600;">{{ $preregistro->nombre_destinatario }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Origen</td><td style="padding:7px 0;font-weight:600;">{{ $preregistro->origen }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Destino</td><td style="padding:7px 0;font-weight:600;">{{ $destino }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Cantidad</td><td style="padding:7px 0;font-weight:600;">{{ $preregistro->cantidad }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Contenido</td><td style="padding:7px 0;font-weight:600;">{{ $preregistro->contenido }}</td></tr>
                    <tr><td style="padding:7px 0;color:#526075;">Direcci&oacute;n</td><td style="padding:7px 0;font-weight:600;">{{ $preregistro->direccion }}</td></tr>
                    @if($preregistro->referencia)
                        <tr><td style="padding:7px 0;color:#526075;">Referencia</td><td style="padding:7px 0;font-weight:600;">{{ $preregistro->referencia }}</td></tr>
                    @endif
                </table>
            </div>

            <div style="margin-top:18px;padding:16px 18px;border-radius:14px;background:#fff8db;border:1px solid #f4d56b;color:#4a3b00;font-size:14px;line-height:1.65;">
                <strong>Importante:</strong> el peso se verificar&aacute; una vez que el paquete est&eacute; en Correos. El precio y las condiciones finales se confirmar&aacute;n durante la admisi&oacute;n.
            </div>

            <p style="margin:22px 0 0;font-size:14px;line-height:1.7;">Conserva este correo y presenta el c&oacute;digo de preregistro cuando entregues tu paquete.</p>
            <p style="margin:16px 0 0;font-size:14px;line-height:1.7;">Atentamente,<br><strong>Equipo de Correos de Bolivia</strong></p>
        </div>
    </div>
</body>
</html>
