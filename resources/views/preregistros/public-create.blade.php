<x-guest-layout card-max-width="1120px" card-classes="!p-0 overflow-hidden">
    <div class="px-8 py-6 border-b border-[#20539A]/10 bg-[linear-gradient(135deg,#20539A_0%,#2f6cc5_100%)] text-white">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Hacer envio desde casa</h1>
                <p class="text-sm text-white/85">Completa tus datos, guarda tu codigo y presentalo en admision para recuperar tu envio de forma rapida.</p>
            </div>
        </div>
    </div>

    <div class="px-8 py-6">
        @if (session('success'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                {{ session('success') }}
                @if (session('preregistro_codigo'))
                    <div class="mt-2"><strong>Tu codigo generado es:</strong> {{ session('preregistro_codigo') }}</div>
                    <div><strong>Correlativo:</strong> {{ session('preregistro_codigo_numerico') }}</div>
                    <div>Presenta este codigo en admision para recuperar tus datos.</div>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">
                Revisa los campos marcados y vuelve a enviar el preregistro.
            </div>
        @endif

        <form method="POST" action="{{ route('preregistros.public.store') }}" class="space-y-8">
            @csrf

            <section class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Origen</label>
                    <select name="origen" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                        <option value="">Seleccione...</option>
                        @foreach($ciudades as $ciudad)
                            <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                        @endforeach
                    </select>
                    @error('origen') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Tipo de correspondencia</label>
                    <input type="text" name="tipo_correspondencia" value="{{ old('tipo_correspondencia') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Ej: DOCUMENTO, OFICIAL">
                    @error('tipo_correspondencia') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Servicio</label>
                    <select name="servicio_id" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                        <option value="">Seleccione...</option>
                        @foreach($servicios as $servicio)
                            <option value="{{ $servicio->id }}" @selected((int) old('servicio_id') === (int) $servicio->id)>{{ $servicio->nombre_servicio }}</option>
                        @endforeach
                    </select>
                    @error('servicio_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Destino</label>
                    <select name="destino_id" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                        <option value="">Seleccione...</option>
                        @foreach($destinos as $destino)
                            <option value="{{ $destino->id }}" @selected((int) old('destino_id') === (int) $destino->id)>{{ $destino->nombre_destino }}</option>
                        @endforeach
                    </select>
                    @error('destino_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Servicio especial</label>
                    <select name="servicio_especial" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                        <option value="">Seleccione...</option>
                        <option value="POR COBRAR" @selected(old('servicio_especial') === 'POR COBRAR')>POR COBRAR</option>
                        <option value="IDA Y VUELTA" @selected(old('servicio_especial') === 'IDA Y VUELTA')>IDA Y VUELTA</option>
                    </select>
                    @error('servicio_especial') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Cantidad</label>
                    <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('cantidad') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Contenido</label>
                    <textarea name="contenido" rows="3" class="w-full rounded-xl border border-slate-300 px-4 py-3">{{ old('contenido') }}</textarea>
                    @error('contenido') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Peso</label>
                    <input type="number" step="0.001" min="0.001" name="peso" value="{{ old('peso') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('peso') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Nombre remitente</label>
                    <input type="text" name="nombre_remitente" value="{{ old('nombre_remitente') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('nombre_remitente') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Nombre envia</label>
                    <input type="text" name="nombre_envia" value="{{ old('nombre_envia') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('nombre_envia') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Carnet</label>
                    <input type="text" name="carnet" value="{{ old('carnet') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('carnet') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Telefono remitente</label>
                    <input type="text" name="telefono_remitente" value="{{ old('telefono_remitente') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('telefono_remitente') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Nombre destinatario</label>
                    <input type="text" name="nombre_destinatario" value="{{ old('nombre_destinatario') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('nombre_destinatario') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Telefono destinatario</label>
                    <input type="text" name="telefono_destinatario" value="{{ old('telefono_destinatario') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('telefono_destinatario') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-[#12325f]">Direccion</label>
                    <input type="text" name="direccion" value="{{ old('direccion') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('direccion') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </section>

            <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 md:flex-row md:items-center md:justify-between">
                <p class="text-sm text-slate-500">Guarda tu codigo. En admision lo usaran para recuperar tu preregistro.</p>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#20539A] px-6 py-3 font-semibold text-white transition hover:bg-[#173d72]">
                    Enviar preregistro
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
