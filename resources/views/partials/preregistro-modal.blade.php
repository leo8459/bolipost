<div class="preregistro-modal" id="preregistroModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="preregistroTitle">
    <div class="preregistro-panel">
        <button class="preregistro-close" type="button" id="preregistroClose" aria-label="Cerrar formulario">
            &times;
        </button>

        <div class="preregistro-head">
            <div>
                <p class="preregistro-kicker">Preregistro publico</p>
                <h3 id="preregistroTitle">Hacer envio desde casa</h3>
                <p>Completa tus datos, guarda tu codigo y presentalo en admision para recuperar tu envio de forma rapida.</p>
            </div>
        </div>

        <div class="preregistro-intro-grid">
            <article class="preregistro-intro-card">
                <span class="preregistro-intro-step">Paso 1</span>
                <strong>Llena tus datos</strong>
                <p>Registra remitente, destinatario, contenido y destino.</p>
            </article>
            <article class="preregistro-intro-card">
                <span class="preregistro-intro-step">Paso 2</span>
                <strong>Guarda tu codigo</strong>
                <p>Se genera un codigo unico para recuperar el preregistro.</p>
            </article>
            <article class="preregistro-intro-card">
                <span class="preregistro-intro-step">Paso 3</span>
                <strong>Presenta tu codigo en admision</strong>
                <p>Con ese codigo, admision recupera tus datos y completa el registro final.</p>
            </article>
        </div>

        @if (session('success'))
            <div class="preregistro-alert preregistro-alert-success">
                {{ session('success') }}
                @if (session('preregistro_codigo'))
                    <div class="preregistro-code-card mt-2">
                        <span class="preregistro-code-label">Tu codigo generado</span>
                        <strong class="preregistro-code-value">{{ session('preregistro_codigo') }}</strong>
                        <span class="preregistro-code-help">Presenta este codigo en admision para recuperar tus datos.</span>
                    </div>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="preregistro-alert preregistro-alert-error">
                {{ $errors->first('general') ?: 'Revisa los campos del preregistro y vuelve a intentar.' }}
            </div>
        @endif

        <form method="POST" action="{{ route('preregistros.public.store') }}" class="preregistro-form">
            @csrf

            <div class="preregistro-section-card">
                <div class="preregistro-section-head">
                    <span class="preregistro-section-pill">Datos del envio</span>
                    <p>Selecciona origen, servicio, destino y caracteristicas del paquete.</p>
                </div>
                <div class="preregistro-grid">
                    <div class="preregistro-field">
                        <label>Origen</label>
                        <select name="origen">
                            <option value="">Seleccione...</option>
                            @foreach($preregistroCiudades as $ciudad)
                                <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                            @endforeach
                        </select>
                        @error('origen') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Tipo de correspondencia</label>
                        <input type="text" name="tipo_correspondencia" value="{{ old('tipo_correspondencia') }}" placeholder="Ej: DOCUMENTO, OFICIAL">
                        @error('tipo_correspondencia') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Servicio</label>
                        <select name="servicio_id" class="preregistro-select-compact" title="Selecciona el servicio">
                            <option value="">Seleccione...</option>
                            @foreach($preregistroServicios as $servicio)
                                @php
                                    $serviceLabel = (string) \Illuminate\Support\Str::of($servicio->nombre_servicio)
                                        ->replace('_', ' ')
                                        ->squish()
                                        ->limit(28, '...');
                                @endphp
                                <option
                                    value="{{ $servicio->id }}"
                                    title="{{ $servicio->nombre_servicio }}"
                                    @selected((int) old('servicio_id') === (int) $servicio->id)
                                >{{ $serviceLabel }}</option>
                            @endforeach
                        </select>
                        @error('servicio_id') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Destino</label>
                        <select name="destino_id">
                            <option value="">Seleccione...</option>
                            @foreach($preregistroDestinos as $destino)
                                <option value="{{ $destino->id }}" @selected((int) old('destino_id') === (int) $destino->id)>{{ $destino->nombre_destino }}</option>
                            @endforeach
                        </select>
                        @error('destino_id') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Servicio especial</label>
                        <select name="servicio_especial">
                            <option value="">Seleccione...</option>
                            <option value="POR COBRAR" @selected(old('servicio_especial') === 'POR COBRAR')>POR COBRAR</option>
                            <option value="IDA Y VUELTA" @selected(old('servicio_especial') === 'IDA Y VUELTA')>IDA Y VUELTA</option>
                        </select>
                        @error('servicio_especial') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Cantidad</label>
                        <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}">
                        @error('cantidad') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field preregistro-field-full">
                        <label>Contenido</label>
                        <textarea name="contenido" rows="3">{{ old('contenido') }}</textarea>
                        @error('contenido') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Peso</label>
                        <input type="number" step="0.001" min="0.001" name="peso" value="{{ old('peso') }}">
                        @error('peso') <small>{{ $message }}</small> @enderror
                    </div>
                </div>
            </div>

            <div class="preregistro-section-card">
                <div class="preregistro-section-head">
                    <span class="preregistro-section-pill">Datos personales</span>
                    <p>Ingresa remitente, destinatario y direccion de entrega.</p>
                </div>
                <div class="preregistro-grid">
                    <div class="preregistro-field">
                        <label>Nombre remitente</label>
                        <input type="text" name="nombre_remitente" value="{{ old('nombre_remitente') }}">
                        @error('nombre_remitente') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Nombre envia</label>
                        <input type="text" name="nombre_envia" value="{{ old('nombre_envia') }}">
                        @error('nombre_envia') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Carnet</label>
                        <input type="text" name="carnet" value="{{ old('carnet') }}">
                        @error('carnet') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Telefono remitente</label>
                        <input type="text" name="telefono_remitente" value="{{ old('telefono_remitente') }}">
                        @error('telefono_remitente') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Nombre destinatario</label>
                        <input type="text" name="nombre_destinatario" value="{{ old('nombre_destinatario') }}">
                        @error('nombre_destinatario') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field">
                        <label>Telefono destinatario</label>
                        <input type="text" name="telefono_destinatario" value="{{ old('telefono_destinatario') }}">
                        @error('telefono_destinatario') <small>{{ $message }}</small> @enderror
                    </div>
                    <div class="preregistro-field preregistro-field-full">
                        <label>Direccion</label>
                        <input type="text" name="direccion" value="{{ old('direccion') }}">
                        @error('direccion') <small>{{ $message }}</small> @enderror
                    </div>
                </div>
            </div>

            <div class="preregistro-actions">
                <p>Guarda tu codigo. En admision lo usaran para recuperar tu preregistro.</p>
                <button type="submit" class="btn btn-home-shipping">Enviar preregistro</button>
            </div>
        </form>
    </div>
</div>

<div class="preregistro-success-modal" id="preregistroSuccessModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="preregistroSuccessTitle">
    <div class="preregistro-success-card" role="alertdialog" aria-live="assertive">
        <div class="preregistro-success-icon" aria-hidden="true">OK</div>
        <p class="preregistro-success-kicker">Preregistro listo</p>
        <h3 id="preregistroSuccessTitle">Su codigo es</h3>
        <div class="preregistro-success-code" id="preregistroSuccessCode">{{ session('preregistro_codigo') }}</div>
        <p class="preregistro-success-copy">Guarda este codigo. En admision lo usaran para recuperar tus datos.</p>
        <div class="preregistro-success-actions">
            <button class="btn btn-light" type="button" id="copyPreregistroCode">Copiar codigo</button>
            <a class="btn btn-light" href="{{ session('preregistro_ticket_url', '#') }}" id="downloadPreregistroTicket" @if(!session('preregistro_ticket_url')) style="display:none" @endif>
                Descargar ticket PDF
            </a>
            <button class="btn btn-home-shipping" type="button" id="closePreregistroSuccess">Entendido</button>
        </div>
    </div>
</div>
