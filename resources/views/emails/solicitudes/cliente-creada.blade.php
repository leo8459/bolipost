<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud registrada</title>
</head>
<body style="margin:0;padding:24px;font-family:Arial,Helvetica,sans-serif;background:#eef2f7;color:#172033;">
    @php
        $codigo = (string) $solicitud->codigo_solicitud;
        $barcodePng = null;
        $barcodeCid = null;

        if ($codigo !== '' && class_exists('\DNS1D')) {
            try {
                $barcodePng = DNS1D::getBarcodePNG($codigo, 'C128', 1.8, 55);
            } catch (\Throwable $exception) {
                $barcodePng = null;
            }
        }

        if ($barcodePng && isset($message)) {
            $barcodeCid = $message->embedData(
                base64_decode($barcodePng),
                'barcode-' . $codigo . '.png',
                'image/png'
            );
        }
    @endphp

    <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #d8e1ec;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(17,24,39,0.08);">
        <div style="background:linear-gradient(135deg,#173f75 0%,#20539a 100%);color:#ffffff;padding:28px 32px;">
            <div style="font-size:12px;letter-spacing:1.2px;text-transform:uppercase;opacity:0.85;">Correos de Bolivia</div>
            <h1 style="margin:8px 0 0 0;font-size:26px;line-height:1.2;">Solicitud registrada</h1>
            <p style="margin:10px 0 0 0;font-size:14px;line-height:1.6;opacity:0.92;">
                Tu solicitud fue registrada correctamente en nuestro sistema.
            </p>
        </div>

        <div style="padding:32px;">
            <p style="margin-top:0;font-size:15px;">Estimado/a {{ $cliente->name }},</p>

            <p style="margin:0 0 18px 0;font-size:15px;line-height:1.7;">
                Te informamos que tu solicitud fue registrada satisfactoriamente en el sistema de <strong>Correos de Bolivia</strong>.
            </p>

            <div style="margin:0 0 24px 0;padding:22px;border:1px solid #d7e0eb;border-radius:16px;background:#f8fafc;text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#526075;">Codigo de seguimiento</div>
                <div style="margin-top:10px;display:inline-block;padding:12px 18px;background:#f7c948;color:#172033;border-radius:10px;font-size:24px;font-weight:700;letter-spacing:1.4px;">
                    {{ $codigo }}
                </div>

                @if($barcodeCid)
                    <div style="margin-top:18px;">
                        <img src="{{ $barcodeCid }}" alt="Codigo de barras {{ $codigo }}" style="max-width:100%;height:auto;">
                    </div>
                @endif
            </div>

            <div style="padding:22px;background:#ffffff;border-radius:16px;border:1px solid #d7e0eb;">
                <p style="margin:0 0 14px 0;font-size:16px;font-weight:700;color:#173f75;">Resumen de la solicitud</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                    <tr>
                        <td style="padding:8px 0;color:#526075;width:160px;">Remitente</td>
                        <td style="padding:8px 0;color:#172033;font-weight:600;">{{ $solicitud->nombre_remitente }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#526075;">Destinatario</td>
                        <td style="padding:8px 0;color:#172033;font-weight:600;">{{ $solicitud->nombre_destinatario }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#526075;">Destino</td>
                        <td style="padding:8px 0;color:#172033;font-weight:600;">{{ $solicitud->ciudad }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#526075;">Estado inicial</td>
                        <td style="padding:8px 0;color:#172033;font-weight:600;">{{ optional($solicitud->estadoRegistro)->nombre_estado ?: '-' }}</td>
                    </tr>
                </table>
            </div>

            <p style="margin:24px 0 0 0;font-size:14px;line-height:1.7;">
                Conserva este codigo para el seguimiento de tu solicitud y para cualquier consulta futura.
            </p>
            <p style="margin:16px 0 0 0;font-size:14px;line-height:1.7;">
                Atentamente,<br>
                <strong>Equipo de Correos de Bolivia</strong>
            </p>
        </div>
    </div>
</body>
</html>
