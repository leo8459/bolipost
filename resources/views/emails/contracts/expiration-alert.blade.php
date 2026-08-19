<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de vencimiento de contratos</title>
</head>
<body style="margin:0;padding:24px;background:#eef2f7;color:#172033;font-family:Arial,Verdana,sans-serif;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #d8e1ec;border-radius:16px;overflow:hidden;">
        <div style="padding:28px 32px;background:#173f75;color:#ffffff;">
            <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">Correos de Bolivia</div>
            <h1 style="margin:8px 0 0;font-size:25px;">Aviso de vencimiento de contratos</h1>
        </div>
        <div style="padding:30px 32px;">
            <p style="margin-top:0;line-height:1.6;">
                Los siguientes contratos empresariales vencen dentro de los proximos 90 dias. Por favor, realice el seguimiento correspondiente.
            </p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:22px;font-size:14px;">
                <thead>
                    <tr style="background:#f7c948;color:#172033;">
                        <th align="left" style="padding:12px;border:1px solid #e0b733;">Empresa</th>
                        <th align="left" style="padding:12px;border:1px solid #e0b733;">Vencimiento</th>
                        <th align="center" style="padding:12px;border:1px solid #e0b733;">Dias</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($alerts as $alert)
                        <tr>
                            <td style="padding:12px;border:1px solid #d8e1ec;font-weight:700;">{{ $alert['empresa'] }}</td>
                            <td style="padding:12px;border:1px solid #d8e1ec;">{{ $alert['fin_contrato'] }}</td>
                            <td align="center" style="padding:12px;border:1px solid #d8e1ec;font-weight:700;color:{{ $alert['days_left'] <= 30 ? '#b42318' : '#7a5500' }};">
                                {{ $alert['days_left'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin:24px 0 0;color:#526075;font-size:13px;line-height:1.6;">
                Este es un aviso automatico generado por el sistema TrackingBO.
            </p>
        </div>
    </div>
</body>
</html>
