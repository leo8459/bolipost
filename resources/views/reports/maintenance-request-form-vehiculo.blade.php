<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16px 20px 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; margin: 0; }
        .page { width: 100%; }
        .title { text-align: center; font-weight: 700; font-size: 16px; margin-top: 35px; }
        .subtitle { text-align: center; font-size: 12px; line-height: 1.2; margin-top: 4px; }
        .row { width: 100%; margin-top: 8px; }
        .field { display: inline-block; vertical-align: top; margin-right: 12px; }
        .label { font-weight: 700; }
        .line { display: inline-block; border-bottom: 1px solid #111827; min-height: 12px; padding: 0 4px 1px; }
        .w-sm { width: 34px; }
        .w-md { width: 104px; }
        .w-lg { width: 172px; }
        .w-xl { width: 250px; }
        .section { margin-top: 8px; background: #d9d9d9; text-align: center; font-weight: 700; padding: 3px 0; }
        .inventory { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .inventory td { padding: 1px 4px; vertical-align: top; }
        .box { display: inline-block; width: 9px; height: 9px; border: 1px solid #111827; margin-right: 6px; }
        .line-block { border-bottom: 1px solid #111827; height: 14px; margin-top: 6px; }
        .signature-row { width: 100%; margin-top: 18px; page-break-inside: avoid; }
        .signature { display: inline-block; width: 31%; text-align: center; margin-top: 50px;}
        .signature .sign-line { border-top: 1px solid #111827; margin: 0 12px 6px; }
        .check-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .check-grid td { width: 25%; padding: 2px 4px; vertical-align: top; }
        .vehicle-assets { width: 100%; margin-top: 10px; page-break-inside: avoid; }
        .vehicle-assets td { vertical-align: middle; text-align: center; }
        .vehicle-top { width: 200px; height: auto; }
        .vehicle-small { width: 150px; height: auto; }
        .vehicle-side { width: 200px; height: auto; }
        .vehicle-gauge { width: 200px; height: auto; }
    </style>
</head>
<body>
    <div class="page">
    <div class="title">SOLICITUD DE MANTENIMIENTO</div>
    <div class="subtitle">Mantenimiento y Reparación<br>de vehículos AGBC</div>

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
        <tr><td><span class="box"></span>Limpiaparabrisas</td><td><span class="box"></span>Control de alarma</td><td><span class="box"></span>Gata</td></tr>
        <tr><td><span class="box"></span>Espejos</td><td><span class="box"></span>Tapetes</td><td><span class="box"></span>Llave de ruedas</td></tr>
        <tr><td><span class="box"></span>Luces</td><td><span class="box"></span>A/C</td><td><span class="box"></span>Extintor</td></tr>
        <tr><td><span class="box"></span>Placas</td><td><span class="box"></span>Matrícula</td><td><span class="box"></span>Encendedor</td></tr>
        <tr><td><span class="box"></span>Emblemas</td><td><span class="box"></span>Herramientas</td><td><span class="box"></span>Antena</td></tr>
        <tr><td><span class="box"></span>Radio</td><td><span class="box"></span>Tuerca Seg.</td><td><span class="box"></span>Llanta de Emergencia</td></tr>
    </table>

    <table class="check-grid">
        <tr><td>Limpieza de Inyectores <span class="box"></span></td><td>Limpieza Cuerpo de Aceleración <span class="box"></span></td><td>Servicio de frenos completo <span class="box"></span></td><td>Escaneo computarizado <span class="box"></span></td></tr>
        <tr><td>Cambio de Aceite y Filtro <span class="box"></span></td><td>Limpieza Válvula IAC <span class="box"></span></td><td>Cambio de bomba de gasolina <span class="box"></span></td><td>Medición de compresión <span class="box"></span></td></tr>
        <tr><td>Cambio de Aceite de Caja <span class="box"></span></td><td>Cambio de Kit de Distribución <span class="box"></span></td><td>Cambio de filtro y pre filtro de gas. <span class="box"></span></td><td>Cambio de Bujías <span class="box"></span></td></tr>
    </table>

    <div class="section">TRABAJO A REALIZAR</div>
    <div class="line-block"></div>
    <div class="line-block"></div>
    <div class="line-block"></div>
    <div class="line-block"></div>

    <div class="row" style="margin-top: 12px;">
        <div class="field" style="float: right;"><span class="label">PROXIMO MANTENIMIENTO EN KM.</span> <span class="line w-lg">&nbsp;</span></div>
    </div>

    @if(!empty($assets['top']) || !empty($assets['front']) || !empty($assets['side_upper']) || !empty($assets['rear']) || !empty($assets['side_lower']) || !empty($assets['gauge']))
        <table class="vehicle-assets">
            <tr>
                <td colspan="2" style="text-align: left;">
                    @if(!empty($assets['top']))
                        <img src="{{ $assets['top'] }}" alt="Vehículo superior" class="vehicle-top">
                    @endif
                </td>
                <td rowspan="3" style="width: 35%;">
                    @if(!empty($assets['gauge']))
                        <img src="{{ $assets['gauge'] }}" alt="Gasolina actual" class="vehicle-gauge">
                    @endif
                </td>
            </tr>
            <tr>
                <td style="width: 18%;">
                    @if(!empty($assets['front']))
                        <img src="{{ $assets['front'] }}" alt="Vehículo frontal" class="vehicle-small">
                    @endif
                </td>
                <td style="width: 47%;">
                    @if(!empty($assets['side_upper']))
                        <img src="{{ $assets['side_upper'] }}" alt="Vehículo lateral superior" class="vehicle-side">
                    @endif
                </td>
            </tr>
            <tr>
                <td>
                    @if(!empty($assets['rear']))
                        <img src="{{ $assets['rear'] }}" alt="Vehículo trasero" class="vehicle-small">
                    @endif
                </td>
                <td>
                    @if(!empty($assets['side_lower']))
                        <img src="{{ $assets['side_lower'] }}" alt="Vehículo lateral inferior" class="vehicle-side">
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <div class="signature-row">
        <div class="signature"><div class="sign-line"></div>Conductor</div>
        <div class="signature"><div class="sign-line"></div>Operaciones Postales</div>
        <div class="signature"><div class="sign-line"></div>Servicios Generales</div>
    </div>
    </div>
</body>
</html>
