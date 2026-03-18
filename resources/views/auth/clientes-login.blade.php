<x-guest-layout cardMaxWidth="620px" cardClasses="p-0">
    <div>
        <section class="px-6 py-7 sm:px-8 sm:py-8">
            <div class="mb-5 rounded-xl border border-[#e8edf7] bg-[#f9fbff] p-4">
                <h2 class="mt-2 text-2xl font-bold text-[#20539A]">Login publico</h2>
                <p class="mt-1 text-sm text-[#20539A]/75">Accede como cliente desde la tabla clientes.</p>
            </div>

            <x-auth-session-status class="mb-4 rounded-lg border border-[#FECC36]/40 bg-[#fff7dd] px-3 py-2" :status="session('status')" />

            @if ($errors->has('google'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first('google') }}
                </div>
            @endif

            <div class="space-y-4">
                <a
                    href="{{ route('auth.google.redirect') }}"
                    class="flex h-11 w-full items-center justify-center rounded-lg border border-[#20539A]/20 bg-white text-sm font-semibold text-[#20539A] transition hover:border-[#20539A] hover:bg-[#f7faff]"
                >
                    Ingresar con Google
                </a>

                <p class="text-center text-sm text-[#20539A]/75">
                    Google nos entrega el correo autenticado de tu cuenta, por eso este acceso si valida que el correo sea real.
                </p>

                <a
                    href="{{ route('clientes.register') }}"
                    class="flex h-11 w-full items-center justify-center rounded-lg border border-[#20539A]/20 bg-white text-sm font-semibold text-[#20539A] transition hover:border-[#20539A] hover:bg-[#f7faff]"
                >
                    Registrarme con Google
                </a>
            </div>
        </section>
    </div>
</x-guest-layout>
