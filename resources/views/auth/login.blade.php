<x-guest-layout cardMaxWidth="980px" cardClasses="p-0">
    <div class="grid md:grid-cols-[1.05fr_1fr]">
        <aside class="hidden md:flex flex-col justify-between bg-[linear-gradient(145deg,#ffde73_0%,#fecb34_54%,#e6b111_100%)] text-[#123c76] p-8 lg:p-10">
            <div>
                <p class="inline-flex items-center rounded-full border border-[#123c76]/20 bg-white/45 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#123c76]">Acceso Institucional</p>
                <h1 class="mt-4 text-2xl lg:text-3xl font-bold leading-tight">
                    Portal corporativo
                </h1>
                <p class="mt-4 text-sm text-[#123c76]/85 max-w-xs lg:max-w-sm">
                    Gestiona operaciones postales, trazabilidad y reportes desde una plataforma central.
                </p>
            </div>

            <div class="space-y-3">
                <div class="rounded-xl border border-[#123c76]/20 bg-white/45 p-4 backdrop-blur-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-[#123c76]">Seguridad</p>
                    <p class="mt-2 text-sm text-[#123c76]/85">Manten tus credenciales privadas y cierra sesion al finalizar tu jornada.</p>
                </div>
                <ul class="grid gap-2 text-xs text-[#123c76]/90">
                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[#123c76]"></span>Acceso con credenciales autorizadas</li>
                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[#123c76]"></span>Conexion cifrada para autenticacion</li>
                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[#123c76]"></span>Registro de actividad y control operativo</li>
                </ul>
            </div>
        </aside>

        <section class="px-6 py-7 sm:px-8 sm:py-8">
            <div class="rounded-xl border border-[#e8edf7] bg-[#f9fbff] p-4 mb-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#9f7a08]">Plataforma oficial</p>
                <h2 class="mt-2 text-2xl font-bold text-[#123c76]">Iniciar sesion</h2>
                <p class="mt-1 text-sm text-[#123c76]/75">Ingresa con tu cuenta institucional.</p>
            </div>

            <x-auth-session-status class="mb-4 rounded-lg border border-[#eab312]/40 bg-[#fff7dd] px-3 py-2" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Correo electronico')" />
                    <x-text-input id="email" class="block mt-1 w-full h-11 rounded-lg bg-white" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Contrasena')" />
                    <x-text-input id="password" class="block mt-1 w-full h-11 rounded-lg bg-white"
                                    type="password"
                                    name="password"
                                    required autocomplete="current-password" />

                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-4 pt-1">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded border-[#dcb15f] text-[#c79706] shadow-sm focus:ring-[#fecb34]/35" name="remember">
                        <span class="ms-2 text-sm text-[#123c76]/80">{{ __('Recordarme') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm font-medium text-[#9f7a08] hover:text-[#7c5d02] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#fecb34]" href="{{ route('password.request') }}">
                            {{ __('Olvidaste tu contrasena?') }}
                        </a>
                    @endif
                </div>

                <x-primary-button class="w-full h-11 justify-center rounded-lg text-sm shadow-[0_8px_16px_rgba(234,179,18,0.35)]">
                    {{ __('Ingresar al sistema') }}
                </x-primary-button>

                <p class="pt-1 text-[11px] text-center text-[#123c76]/65">
                    Uso exclusivo de personal autorizado.
                </p>
            </form>
        </section>
    </div>
</x-guest-layout>
