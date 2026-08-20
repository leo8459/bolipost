<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $solicitud->codigo_solicitud }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Verdana, DejaVu Sans, sans-serif;
            color: #000;
            background: #fff;
        }

        .ticket {
            width: 72mm;
            margin: 6px auto;
            overflow: hidden;
            border-radius: 0;
            background: #fff;
            box-shadow: none;
            font-size: 10px;
            line-height: 1.25;
            position: relative;
        }

        .ticket-watermark {
            position: absolute;
            inset: 55px 0 35px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-evenly;
            overflow: hidden;
            color: rgba(0, 0, 0, .16);
            pointer-events: none;
            user-select: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .ticket-watermark span {
            display: block;
            width: 100%;
            transform: rotate(-22deg);
            font-size: 17px;
            font-weight: 900;
            line-height: 1.15;
            letter-spacing: 1px;
            text-align: center;
            white-space: nowrap;
        }

        .ticket-head {
            padding: 6px 0;
            color: #000;
            background: #fff;
            text-align: center;
            border-bottom: 1px dashed #000;
        }

        .ticket-logo {
            display: block;
            width: 30mm;
            max-width: 100%;
            height: auto;
            margin: 0 auto 4px;
            filter: grayscale(100%) contrast(140%);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .subtitle {
            margin: 2px 0 0;
            color: #000;
            font-size: 9px;
        }

        .ticket-body {
            padding: 8px;
        }

        .code-box {
            padding: 6px;
            border: 1px dashed #000;
            border-radius: 6px;
            background: #fff;
            text-align: center;
        }

        .code {
            margin: 0 0 3px;
            color: #000;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1px;
            word-break: break-word;
        }

        .barcode {
            margin: 4px 0 0;
            text-align: center;
        }

        .barcode img {
            max-width: 100%;
            height: auto;
        }

        .section {
            margin-top: 6px;
            padding: 6px;
            border: 1px dashed #000;
            border-radius: 0;
            background: #fff;
        }

        .section-title {
            margin: 0 0 5px;
            color: #000;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .generation-date {
            margin-top: 5px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .route-grid,
        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .field {
            min-width: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            word-break: break-word;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .label {
            display: block;
            margin-bottom: 1px;
            color: #000;
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .value {
            color: #000;
            font-weight: 700;
        }

        .total-box {
            margin-top: 6px;
            padding: 6px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            border-radius: 0;
            background: #fff;
            color: #000;
            text-align: center;
        }

        .total-label {
            display: block;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .price {
            display: block;
            margin-top: 1px;
            font-size: 17px;
            font-weight: 900;
        }

        .declaration {
            margin-top: 6px;
            padding: 6px;
            border: 1px dashed #000;
            border-radius: 0;
            background: #fff;
            color: #000;
            font-size: 8.7px;
            text-align: justify;
        }

        .pickup-notice {
            margin-top: 6px;
            padding: 7px;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            font-size: 9px;
            line-height: 1.35;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }

        .province-notice {
            margin-top: 6px;
            padding: 7px;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            font-size: 9px;
            line-height: 1.35;
            font-weight: 900;
            text-align: center;
            text-transform: uppercase;
        }

        .signature {
            margin: 24px 8px 0;
            padding-top: 18px;
            border-top: 1px solid #000;
            text-align: center;
            color: #000;
            font-size: 10px;
            font-weight: 700;
        }

        .footer {
            margin-top: 6px;
            color: #000;
            text-align: center;
            font-size: 8.8px;
        }

        .actions {
            width: 72mm;
            margin: 8px auto 14px;
            display: flex;
            gap: 6px;
            justify-content: center;
        }

        .actions button {
            border: 0;
            border-radius: 8px;
            padding: 8px 10px;
            color: #fff;
            background: #000;
            font-weight: 800;
            cursor: pointer;
        }

        .actions button.secondary {
            color: #000;
            background: #fff;
            border: 1px solid #000;
        }

        @media print {
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .ticket {
                margin: 0 auto;
                box-shadow: none;
                border-radius: 0;
            }

            .actions {
                display: none;
            }

            .ticket-watermark {
                color: rgba(0, 0, 0, .16);
            }
        }
    </style>
</head>
<body>
    @php
        $codigo = (string) $solicitud->codigo_solicitud;
        $barcodePng = null;
        $destinoNombre = $solicitud->destino?->nombre_destino ?: $solicitud->ciudad;
        $fechaTicket = optional($solicitud->created_at)->format('d/m/Y H:i');

        if ($codigo !== '' && class_exists('\DNS1D')) {
            try {
                $barcodePng = DNS1D::getBarcodePNG($codigo, 'C128', 1.5, 40);
            } catch (\Throwable $exception) {
                $barcodePng = null;
            }
        }
    @endphp

    <main class="ticket">
        <div class="ticket-watermark" aria-hidden="true">
            <span>SOLICITUD<br>DELIVERY EXPRESS</span>
            <span>SOLICITUD<br>DELIVERY EXPRESS</span>
            <span>SOLICITUD<br>DELIVERY EXPRESS</span>
        </div>

        <header class="ticket-head">
            <img
                src="{{ asset('images/logo-delivery-express.jpeg') }}"
                alt="Delivery Express"
                class="ticket-logo"
            >
            <p class="subtitle">Comprobante de solicitud</p>
        </header>

        <section class="ticket-body">
            <div class="code-box">
                <p class="code">{{ $codigo }}</p>
                @if($barcodePng)
                    <div class="barcode">
                        <img src="data:image/png;base64,{{ $barcodePng }}" alt="Codigo de barras {{ $codigo }}">
                    </div>
                @elseif($codigo !== '' && class_exists('\DNS1D'))
                    <div class="barcode">{!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.1, 35) !!}</div>
                @endif
                <div class="generation-date">Fecha de generacion: {{ $fechaTicket ?: '-' }}</div>
            </div>

            <section class="section">
                <h2 class="section-title">Ruta</h2>
                <div class="route-grid">
                    <div class="field">
                        <span class="label">Origen</span>
                        <span class="value">{{ $solicitud->origen ?: '-' }}</span>
                    </div>
                    <div class="field">
                        <span class="label">Destino</span>
                        <span class="value">{{ $destinoNombre ?: '-' }}</span>
                    </div>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title">Remitente</h2>
                <div class="data-grid">
                    <div class="field full">
                        <span class="label">Nombre</span>
                        <span class="value">{{ $solicitud->nombre_remitente ?: '-' }}</span>
                    </div>
                    <div class="field full">
                        <span class="label">Telefono</span>
                        <span class="value">{{ $solicitud->telefono_remitente ?: '-' }}</span>
                    </div>
                    <div class="field full">
                        <span class="label">Direccion de recojo</span>
                        <span class="value">{{ $solicitud->direccion_recojo ?: '-' }}</span>
                    </div>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title">Destinatario</h2>
                <div class="data-grid">
                    <div class="field full">
                        <span class="label">Nombre</span>
                        <span class="value">{{ $solicitud->nombre_destinatario ?: '-' }}</span>
                    </div>
                    <div class="field full">
                        <span class="label">Telefono</span>
                        <span class="value">{{ $solicitud->telefono_destinatario ?: '-' }}</span>
                    </div>
                    <div class="field full">
                        <span class="label">Direccion de entrega</span>
                        <span class="value">{{ $solicitud->direccion ?: '-' }}</span>
                    </div>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title">Datos del envio</h2>
                <div class="data-grid">
                    <div class="field">
                        <span class="label">Pago destino</span>
                        <span class="value">{{ $solicitud->pago_destinatario ? 'SI' : 'NO' }}</span>
                    </div>
                    <div class="field">
                        <span class="label">Peso</span>
                        <span class="value">{{ $solicitud->peso !== null ? number_format((float) $solicitud->peso, 3, '.', '') . ' kg' : '-' }}</span>
                    </div>
                    <div class="field full">
                        <span class="label">Contenido</span>
                        <span class="value">{{ $solicitud->contenido ?: '-' }}</span>
                    </div>
                </div>
            </section>

            <div class="total-box">
                <span class="total-label">Total a cobrar</span>
                <span class="price">Bs {{ $solicitud->precio !== null ? number_format((float) $solicitud->precio, 2, '.', '') : '0.00' }}</span>
            </div>

            <div class="pickup-notice">
                Se comunicar&aacute; un operario dentro de las pr&oacute;ximas 24 horas para confirmar el recojo. Si nadie se comunica, por favor cont&aacute;ctese al 71522163.
            </div>

            <div class="province-notice">
                Importante: Si registr&oacute; una provincia para el env&iacute;o, la solicitud no ser&aacute; recogida ni validada.
            </div>

            <div class="declaration">
                El cliente declara que los datos proporcionados son ciertos; y que el contenido cumple con las normas de seguridad postal, bajo su &uacute;nica y exclusiva responsabilidad.
            </div>

            <div class="signature">Firma de recepcion</div>

            <div class="footer">
                Impresion para Epson TM-T20II
            </div>
        </section>
    </main>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
        <button type="button" class="secondary" onclick="window.close()">Cerrar</button>
    </div>

    <script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 250);
    });
    </script>
</body>
</html>
