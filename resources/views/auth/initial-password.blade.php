<x-guest-layout title="Cambiar contraseña inicial" cardMaxWidth="520px">
    <h1 class="text-2xl font-semibold tracking-tight">Cambiar contraseña inicial</h1>
    <p class="mt-1 text-sm text-slate-600">Crea tu contraseña personal para acceder al sistema.</p>

    <form id="initial-password-form" method="POST" action="{{ route('password.initial.store') }}" class="mt-6">
        @csrf

        @if ($errors->has('password'))
            <div id="password-errors" role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5">
                <p class="mb-1 text-sm font-semibold text-red-800">Revisa los datos antes de guardar.</p>
                <x-input-error :messages="$errors->get('password')" />
            </div>
        @endif

        <ol class="m-0 list-none space-y-4 p-0 sm:space-y-5" role="list">
            <li class="flex items-start gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#20539A] text-sm font-semibold text-white" aria-hidden="true">1</span>
                <div class="min-w-0 flex-1">
                    <label for="password" class="block pt-0.5 text-base font-semibold">Escribe tu nueva contraseña</label>
                    <div id="password-requirements" class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                        <p>Elige una contraseña distinta de la temporal.</p>
                        <ul class="mt-1.5 list-disc space-y-0.5 pl-5">
                            <li>Al menos 8 caracteres</li>
                            <li>Una letra mayúscula y una minúscula</li>
                            <li>Un número</li>
                        </ul>
                    </div>
                    <div class="relative mt-2">
                        <x-text-input id="password" name="password" type="password" class="block min-h-11 w-full pr-12"
                            required autofocus autocomplete="new-password" autocapitalize="none" spellcheck="false" minlength="8" maxlength="72"
                            aria-describedby="{{ $errors->has('password') ? 'password-requirements password-errors' : 'password-requirements' }}" />
                        <x-password-visibility-button target="password" description="la nueva contraseña" />
                    </div>
                </div>
            </li>

            <li class="flex items-start gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#20539A] text-sm font-semibold text-white" aria-hidden="true">2</span>
                <div class="min-w-0 flex-1">
                    <label for="password_confirmation" class="block pt-0.5 text-base font-semibold">Confirma tu nueva contraseña</label>
                    <p id="password-confirmation-hint" class="mt-1 text-sm text-slate-600">Vuelve a escribir la misma contraseña.</p>
                    <div class="relative mt-2">
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="block min-h-11 w-full pr-12"
                            required autocomplete="new-password" autocapitalize="none" spellcheck="false" minlength="8" maxlength="72"
                            aria-describedby="password-confirmation-hint" />
                        <x-password-visibility-button target="password_confirmation" description="la confirmación de contraseña" />
                    </div>
                </div>
            </li>

            <li class="flex items-start gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#20539A] text-sm font-semibold text-white" aria-hidden="true">3</span>
                <div class="min-w-0 flex-1">
                    <h2 class="pt-0.5 text-base font-semibold">Guarda e inicia sesión</h2>
                    <p class="mt-1 text-sm text-slate-600">Después de guardar, inicia sesión con tu nueva contraseña.</p>
                    <x-primary-button class="mt-3 min-h-11 w-full justify-center !text-sm !normal-case !tracking-normal">Guardar contraseña</x-primary-button>
                </div>
            </li>
        </ol>

        <div class="mt-5 border-t border-slate-200 pt-3 text-center">
            <a href="{{ route('logout.get') }}" class="text-sm font-medium text-[#20539A] underline underline-offset-2">Volver al inicio de sesión</a>
        </div>
    </form>

    <script>
        document.querySelectorAll('#initial-password-form [data-password-toggle]').forEach((button) => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            const eyeOpen = button.querySelector('[data-eye-open]');
            const eyeClosed = button.querySelector('[data-eye-closed]');

            button.addEventListener('click', () => {
                const showPassword = input.type === 'password';
                input.type = showPassword ? 'text' : 'password';
                button.setAttribute('aria-label', showPassword ? button.dataset.hideLabel : button.dataset.showLabel);
                eyeOpen.classList.toggle('hidden', showPassword);
                eyeClosed.classList.toggle('hidden', !showPassword);
            });
        });
    </script>
</x-guest-layout>
