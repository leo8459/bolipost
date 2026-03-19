<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Codigo de seguimiento</title>
</head>
<body style="margin:0;padding:24px;font-family:Arial,Helvetica,sans-serif;background:#f4f6f9;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <div style="background:#20539a;color:#ffffff;padding:24px;">
            <h1 style="margin:0;font-size:22px;">Solicitud registrada correctamente</h1>
        </div>

        <div style="padding:24px;">
            <p style="margin-top:0;">Hola {{ $cliente->name }},</p>

            <p>Tu solicitud fue registrada en el sistema de la Agencia Boliviana de Correos.</p>

            <p style="margin-bottom:8px;"><strong>Codigo de seguimiento:</strong></p>
            <div style="display:inline-block;padding:14px 18px;background:#facc15;color:#111827;border-radius:8px;font-size:22px;font-weight:700;letter-spacing:1px;">
                {{ $solicitud->codigo_solicitud }}
            </div>

            <div style="margin-top:24px;padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
                <p style="margin:0 0 8px 0;"><strong>Resumen de la solicitud</strong></p>
                <p style="margin:0 0 6px 0;">Remitente: {{ $solicitud->nombre_remitente }}</p>
                <p style="margin:0 0 6px 0;">Destinatario: {{ $solicitud->nombre_destinatario }}</p>
                <p style="margin:0 0 6px 0;">Destino: {{ $solicitud->ciudad }}</p>
                <p style="margin:0;">Estado inicial: {{ $solicitud->estado }}</p>
            </div>

            <p style="margin-top:24px;margin-bottom:0;">Guarda este codigo para consultar el estado de tu solicitud.</p>
        </div>
    </div>
</body>
</html>
