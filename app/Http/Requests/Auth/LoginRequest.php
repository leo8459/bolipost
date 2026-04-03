<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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

        $credentials = [
            'alias' => Str::lower(trim((string) $this->input('alias'))),
            'password' => (string) $this->input('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 900);
            RateLimiter::hit($this->ipThrottleKey(), 900);

            Log::warning('Fallo de autenticacion en panel interno.', [
                'alias' => $credentials['alias'],
                'ip' => $this->ip(),
                'user_agent' => (string) $this->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'alias' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipThrottleKey());
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
}
