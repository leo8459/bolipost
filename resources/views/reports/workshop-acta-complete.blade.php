<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar datos del acta</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
        }

        .card {
            width: 100%;
            max-width: 720px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .12);
            overflow: hidden;
        }

        .header {
            padding: 24px 28px;
            background: linear-gradient(135deg, #0f4f88, #1677c5);
            color: #fff;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        .header p {
            margin: 0;
            opacity: .9;
        }

        .body {
            padding: 28px;
        }

        .vehicle-box {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            padding: 16px;
            border: 1px solid #dbe4ef;
            border-radius: 14px;
            background: #f8fbff;
            margin-bottom: 22px;
        }

        .label {
            display: block;
            color: #607086;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .value {
            font-weight: bold;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #cfd9e6;
            border-radius: 12px;
            font-size: 16px;
            padding: 12px 14px;
            text-transform: uppercase;
        }

        input:focus {
            outline: 0;
            border-color: #1677c5;
            box-shadow: 0 0 0 4px rgba(22, 119, 197, .15);
        }

        .error {
            color: #b91c1c;
            font-size: 13px;
            margin-top: 6px;
        }

        .hint {
            color: #64748b;
            font-size: 13px;
            margin-top: 10px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 26px;
        }

        .btn {
            border: 0;
            border-radius: 999px;
            padding: 11px 18px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            color: #fff;
            background: #1677c5;
        }

        .btn-secondary {
            color: #334155;
            background: #e8eef6;
        }

        @media (max-width: 640px) {
            .vehicle-box,
            .grid {
                grid-template-columns: 1fr;
            }

            .actions {
                justify-content: stretch;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="header">
            <h1>Completar datos para el acta</h1>
            <p>Antes de generar el acta de conformidad, registra el numero de chasis y motor del vehiculo.</p>
        </div>

        <div class="body">
            <div class="vehicle-box">
                <div>
                    <span class="label">Placa</span>
                    <span class="value">{{ $vehicle->placa ?? '-' }}</span>
                </div>
                <div>
                    <span class="label">Marca</span>
                    <span class="value">{{ $vehicle->brand?->nombre ?? $vehicle->marca ?? '-' }}</span>
                </div>
                <div>
                    <span class="label">Modelo</span>
                    <span class="value">{{ $vehicle->modelo ?? '-' }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('workshops.acta.store', ['workshop' => $workshop->id]) }}">
                @csrf

                <div class="grid">
                    <div>
                        <label class="label" for="chasis">Numero de chasis</label>
                        <input
                            id="chasis"
                            name="chasis"
                            type="text"
                            value="{{ old('chasis', $vehicleChasis) }}"
                            maxlength="80"
                            autocomplete="off"
                            placeholder="Ej. LDSDS777DS8D"
                            required>
                        @error('chasis')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="motor">Numero de motor</label>
                        <input
                            id="motor"
                            name="motor"
                            type="text"
                            value="{{ old('motor', $vehicleMotor) }}"
                            maxlength="80"
                            autocomplete="off"
                            placeholder="Ej. LDSDS777DS8D"
                            required>
                        @error('motor')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="hint">Se guardara en Registro de vehiculos y luego se abrira el acta con esos datos llenos.</div>

                <div class="actions">
                    <a class="btn btn-secondary" href="{{ route('livewire.maintenance-logs') }}">
                        Volver
                    </a>
                    <button class="btn btn-primary" type="submit">
                        Guardar y generar acta
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
