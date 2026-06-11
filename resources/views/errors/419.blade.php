<x-guest-layout cardMaxWidth="460px">
    <div class="px-2 py-8 text-center">
        <h1 class="text-xl font-semibold text-[#20539A]">Sesion vencida</h1>
        <p class="mt-2 text-sm text-slate-600">Actualice la pagina para continuar.</p>
    </div>

    <x-session-expired-modal :show="true" :redirect-url="Route::has('login') ? route('login') : url('/')" />
</x-guest-layout>
