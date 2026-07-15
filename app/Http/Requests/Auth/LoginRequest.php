<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\EmpresaContractUserSyncService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alias' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        app(EmpresaContractUserSyncService::class)->syncExpiredUsers();

        $normalizedAlias = Str::lower(trim((string) $this->input('alias')));
        $credentials = [
            'alias' => $normalizedAlias,
            'password' => (string) $this->input('password'),
        ];
        $matchedUser = User::withTrashed()
            ->whereRaw('LOWER(alias) = ?', [$normalizedAlias])
            ->first();

        Log::info('Intento de autenticacion en panel interno.', [
            'alias' => $normalizedAlias,
            'ip' => $this->ip(),
            'user_agent' => (string) $this->userAgent(),
            'remember' => $this->boolean('remember'),
            'user_found' => $matchedUser !== null,
            'user_id' => $matchedUser?->id,
            'user_alias_db' => $matchedUser?->alias,
            'user_deleted_at' => optional($matchedUser?->deleted_at)?->toDateTimeString(),
            'user_auto_baja_empresa_at' => optional($matchedUser?->auto_baja_empresa_at)?->toDateTimeString(),
            'password_hash_present' => filled($matchedUser?->password),
            'password_hash_prefix' => $matchedUser && is_string($matchedUser->password)
                ? substr($matchedUser->password, 0, 12)
                : null,
            'password_check' => $matchedUser && is_string($matchedUser->password)
                ? Hash::check((string) $this->input('password'), $matchedUser->password)
                : false,
        ]);

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 900);
            RateLimiter::hit($this->ipThrottleKey(), 900);

            Log::warning('Fallo de autenticacion en panel interno.', [
                'alias' => $credentials['alias'],
                'ip' => $this->ip(),
                'user_agent' => (string) $this->userAgent(),
                'user_found' => $matchedUser !== null,
                'user_id' => $matchedUser?->id,
                'user_deleted_at' => optional($matchedUser?->deleted_at)?->toDateTimeString(),
                'password_check' => $matchedUser && is_string($matchedUser->password)
                    ? Hash::check((string) $this->input('password'), $matchedUser->password)
                    : false,
            ]);

            throw ValidationException::withMessages([
                'alias' => $this->failedAuthenticationMessage($credentials['alias']),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipThrottleKey());

        Log::info('Autenticacion correcta en panel interno.', [
            'alias' => $normalizedAlias,
            'user_id' => Auth::id(),
            'session_id_before_regenerate' => $this->session()->getId(),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $tooManyAliasAttempts = RateLimiter::tooManyAttempts($this->throttleKey(), 5);
        $tooManyIpAttempts = RateLimiter::tooManyAttempts($this->ipThrottleKey(), 25);

        if (! $tooManyAliasAttempts && ! $tooManyIpAttempts) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey())
        );

        Log::warning('Bloqueo temporal por demasiados intentos en panel interno.', [
            'alias' => Str::lower(trim((string) $this->input('alias'))),
            'ip' => $this->ip(),
            'user_agent' => (string) $this->userAgent(),
            'seconds_remaining' => $seconds,
        ]);

        throw ValidationException::withMessages([
            'alias' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('alias')).'|'.$this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip:'.Str::transliterate((string) $this->ip());
    }

    private function failedAuthenticationMessage(string $alias): string
    {
        $user = User::withTrashed()
            ->whereRaw('LOWER(alias) = ?', [$alias])
            ->first();

        if ($user && $user->trashed() && !empty($user->auto_baja_empresa_at)) {
            return 'Su contrato vencio. Por favor comunicarse con su parte administrativa para confirmar la informacion.';
        }

        return trans('auth.failed');
    }
}
