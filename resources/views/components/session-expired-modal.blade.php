@props([
    'show' => false,
    'redirectUrl' => null,
])

@if ($show)
    <div
        id="sessionExpiredModal"
        class="fixed inset-0 z-[10000] flex items-center justify-center bg-[#071a33]/60 px-4 py-6 backdrop-blur-[3px]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sessionExpiredTitle"
    >
        <div class="relative w-full max-w-[470px] overflow-hidden rounded-2xl bg-white shadow-[0_30px_90px_rgba(7,26,51,0.35)] ring-1 ring-white/70">
            <div class="absolute inset-x-0 top-0 h-1.5 bg-[linear-gradient(90deg,#FECC36_0%,#f7b928_40%,#20539A_40%,#20539A_100%)]"></div>

            <div class="px-7 pb-6 pt-8 text-center">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-[#fff7d6] shadow-[0_14px_30px_rgba(254,204,54,0.32)] ring-8 ring-[#fffcef]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#FECC36] text-[#20539A]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                        </svg>
                    </div>
                </div>

                <h2 id="sessionExpiredTitle" class="mt-6 text-2xl font-bold text-[#20539A]">Sesion vencida</h2>
                <p class="mx-auto mt-3 max-w-sm text-sm leading-6 text-slate-600">
                    Su sesion expiro por seguridad. Actualice la pagina para generar una nueva sesion y continuar trabajando.
                </p>
            </div>

            <div class="mx-7 rounded-xl border border-[#dbe6f5] bg-[#f7faff] px-4 py-3">
                <div class="flex items-start gap-3 text-left">
                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-[#20539A] shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-2.64-6.36" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 3v6h-6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-[#20539A]">Presione F5 o actualice desde aqui</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">La pagina se recargara y podra ingresar nuevamente.</p>
                    </div>
                </div>
            </div>

            <div class="px-7 pb-7 pt-5">
                <button
                    type="button"
                    id="sessionExpiredRefresh"
                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#20539A] px-5 text-sm font-bold text-white shadow-[0_12px_24px_rgba(32,83,154,0.28)] transition hover:bg-[#173f75] focus:outline-none focus:ring-4 focus:ring-[#20539A]/25"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-2.64-6.36" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 3v6h-6" />
                    </svg>
                    Actualizar pagina
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const refreshButton = document.getElementById('sessionExpiredRefresh');

            if (!refreshButton) return;

            refreshButton.focus();
            refreshButton.addEventListener('click', function () {
                @if ($redirectUrl)
                    window.location.href = @json($redirectUrl);
                @else
                    window.location.reload();
                @endif
            });
        });
    </script>
@endif
