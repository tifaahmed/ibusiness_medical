<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
            'email' => ['required', 'string', 'email'],
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
        $email = $this->input('email');
        Log::info('LoginRequest: Starting authentication', [
            'email' => $email,
            'ip_address' => $this->ip(),
        ]);

        try {
            Log::info('LoginRequest: Checking rate limit', [
                'email' => $email,
                'throttle_key' => $this->throttleKey(),
            ]);
            $this->ensureIsNotRateLimited();
            Log::info('LoginRequest: Rate limit check passed', [
                'email' => $email,
            ]);
        } catch (ValidationException $e) {
            Log::warning('LoginRequest: Rate limit exceeded', [
                'email' => $email,
                'throttle_key' => $this->throttleKey(),
            ]);
            throw $e;
        }

        $credentials = $this->only('email', 'password');
        $remember = $this->boolean('remember');
        
        Log::info('LoginRequest: Attempting Auth::attempt', [
            'email' => $credentials['email'],
            'has_password' => !empty($credentials['password']),
            'remember' => $remember,
        ]);

        if (! Auth::attempt($credentials, $remember)) {
            Log::warning('LoginRequest: Authentication failed', [
                'email' => $credentials['email'],
                'ip_address' => $this->ip(),
            ]);
            
            RateLimiter::hit($this->throttleKey());
            
            Log::info('LoginRequest: Rate limiter hit (failed attempt)', [
                'email' => $credentials['email'],
                'throttle_key' => $this->throttleKey(),
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        Log::info('LoginRequest: Authentication successful', [
            'email' => $credentials['email'],
            'user_id' => Auth::id(),
        ]);

        RateLimiter::clear($this->throttleKey());
        Log::info('LoginRequest: Rate limiter cleared (success)', [
            'email' => $credentials['email'],
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
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
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
