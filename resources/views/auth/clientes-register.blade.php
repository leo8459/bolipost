<x-guest-layout cardMaxWidth="620px" cardClasses="p-0">
    <div>
        <section class="px-6 py-7 sm:px-8 sm:py-8">
            <div class="mb-5 rounded-xl border border-[#e8edf7] bg-[#f9fbff] p-4">
                <h2 class="mt-2 text-2xl font-bold text-[#20539A]">Registro publico</h2>
                <p class="mt-1 text-sm text-[#20539A]/75">Tu cuenta se guardara en la tabla clientes usando el correo verificado por Google. Despues del ingreso te pediremos completar tus datos.</p>
            </div>

            <div class="space-y-4">
                <form method="POST" action="{{ route('clientes.register.store') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex h-11 w-full items-center justify-center rounded-lg border border-[#20539A]/20 bg-[#20539A] text-sm font-semibold text-white transition hover:bg-[#173f75]"
                    >
                        Continuar con Google
                    </button>
                </form>

                <p class="text-center text-sm text-[#20539A]/75">
                    El correo no se escribe manualmente. Se usara el correo autentico devuelto por Google.
                </p>

                <a
                    href="{{ route('clientes.login') }}"
                    class="flex h-11 w-full items-center justify-center rounded-lg border border-[#20539A]/20 bg-white text-sm font-semibold text-[#20539A] transition hover:border-[#20539A] hover:bg-[#f7faff]"
                >
                    Ya tengo acceso
                </a>
            </div>
        </section>
    </div>
</x-guest-layout>
