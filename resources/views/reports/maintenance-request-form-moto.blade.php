<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; margin: 22px 28px; }
        .title { text-align: center; font-weight: 700; font-size: 17px; margin-top: 12px; }
        .subtitle { text-align: center; font-size: 13px; line-height: 1.35; margin-top: 8px; }
        .row { width: 100%; margin-top: 12px; }
        .field { display: inline-block; vertical-align: top; margin-right: 12px; }
        .label { font-weight: 700; }
        .line { display: inline-block; border-bottom: 1px solid #111827; min-height: 14px; padding: 0 4px 2px; }
        .w-sm { width: 34px; }
        .w-md { width: 104px; }
        .w-lg { width: 182px; }
        .w-xl { width: 270px; }
        .section { margin-top: 12px; background: #d9d9d9; text-align: center; font-weight: 700; padding: 4px 0; }
        .inventory { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .inventory td { padding: 2px 4px; vertical-align: top; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #111827; margin-left: 8px; }
        .line-block { border-bottom: 1px solid #111827; height: 18px; margin-top: 8px; }
        .signature-row { width: 100%; margin-top: 30px; }
        .signature { display: inline-block; width: 31%; text-align: center; }
        .signature .sign-line { border-top: 1px solid #111827; margin: 0 12px 6px; }
        .small-note { font-size: 10px; color: #374151; margin-top: 14px; }
        .moto-assets { width: 100%; margin-top: 18px; }
        .moto-assets td { vertical-align: bottom; text-align: center; }
        .moto-gauge { width: 88px; height: auto; }
        .moto-bike { width: 215px; height: auto; }
    </style>
</head>
<body>
    <div class="title">SOLICITUD DE MANTENIMIENTO</div>
    <div class="subtitle">Mantenimiento y Reparación<br>De Motocicletas AGBC</div>

    <div class="row">
        <div class="field"><span class="label">MARCA:</span> <span class="line w-md">{{ $brand }}</span></div>
        <div class="field"><span class="label">AÑO:</span> <span class="line w-md">{{ $year }}</span></div>
        <div class="field"><span class="label">INGRESO:</span> <span class="line w-sm">{{ $entryDay }}</span> / <span class="line w-sm">{{ $entryMonth }}</span> / <span class="line w-md">{{ $entryYear }}</span></div>
    </div>
    <div class="row">
        <div class="field"><span class="label">MODELO:</span> <span class="line w-md">{{ $model }}</span></div>
        <div class="field"><span class="label">COLOR:</span> <span class="line w-md">{{ $color }}</span></div>
        <div class="field"><span class="label">PLACA:</span> <span class="line w-md">{{ $plate }}</span></div>
        <div class="field"><span class="label">SALIDA:</span> <span class="line w-sm">&nbsp;</span> / <span class="line w-sm">&nbsp;</span> / <span class="line w-md">&nbsp;</span></div>
    </div>
    <div class="row">
        <div class="field"><span class="label">KILOMETRAJE:</span> <span class="line w-md">{{ $odometer }}</span></div>
        <div class="field"><span class="label">CONDUCTOR:</span> <span class="line w-xl">{{ $driverName }}</span></div>
    </div>

    <div class="section">INVENTARIO</div>
    <table class="inventory">
        <tr><td>ESPEJOS <span class="box"></span><span class="box"></span></td><td>FAROL <span class="box"></span></td><td>BATERIA <span class="box"></span></td></tr>
        <tr><td>DIRECCIONALES <span class="box"></span><span class="box"></span></td><td>TAPA DE GASOLINA <span class="box"></span></td><td>FILTRO DE AIRE <span class="box"></span></td></tr>
        <tr><td>PEDALES CAJA Y FRENO <span class="box"></span><span class="box"></span></td><td>CLAXON <span class="box"></span></td><td>TACOMETRO <span class="box"></span></td></tr>
        <tr><td>CUBIERTAS PLASTICOS <span class="box"></span><span class="box"></span></td><td>LUZ STOP <span class="box"></span></td><td>LLAVES <span class="box"></span></td></tr>
        <tr><td>MALETERO <span class="box"></span></td><td>PARRILLAS DE CARGA <span class="box"></span></td><td>DEFENZA <span class="box"></span></td></tr>
    </table>

    <div class="section">OBSERVACIONES</div>
    <div class="line-block"></div>
    <div class="section">TRABAJO A REALIZAR</div>
    <div class="line-block"></div>
    <div class="line-block"></div>
    <div class="line-block"></div>

    <div class="row" style="margin-top: 20px;">
        <div class="field"><span class="label">Gasolina Actual</span></div>
        <div class="field" style="float: right;"><span class="label">PROXIMO MANTENIMIENTO EN KM.</span> <span class="line w-md">&nbsp;</span></div>
    </div>

    @if(!empty($assets['gauge']) || !empty($assets['left']) || !empty($assets['right']))
        <table class="moto-assets">
            <tr>
                <td style="width: 16%;">
                    @if(!empty($assets['gauge']))
                        <img src="{{ $assets['gauge'] }}" alt="Gasolina actual" class="moto-gauge">
                    @endif
                </td>
                <td style="width: 42%;">
                    @if(!empty($assets['left']))
                        <img src="{{ $assets['left'] }}" alt="Moto lateral izquierda" class="moto-bike">
                    @endif
                </td>
                <td style="width: 42%;">
                    @if(!empty($assets['right']))
                        <img src="{{ $assets['right'] }}" alt="Moto lateral derecha" class="moto-bike">
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <div class="small-note">Formulario prellenado con datos del vehículo y del conductor. Complete el resto manualmente.</div>
    <div class="signature-row">
        <div class="signature"><div class="sign-line"></div>Conductor</div>
        <div class="signature"><div class="sign-line"></div>Operaciones Postales</div>
        <div class="signature"><div class="sign-line"></div>Servicios Generales</div>
    </div>
</body>
</html>
