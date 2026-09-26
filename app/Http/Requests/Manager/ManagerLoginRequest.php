<?php

namespace App\Http\Requests\Manager;

use App\Http\Middleware\EnsureEventManager;
use App\Models\EventManager;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The event manager sign-in form. Same protections as the admin's: a limit on
 * wrong guesses per email + address, and one deliberately vague message for
 * "no such account", "wrong password" and "switched off" alike — and the same
 * amount of work for all three, so the time the answer takes doesn't say which
 * it was.
 */
class ManagerLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $manager = EventManager::where('email', $this->string('email')->toString())->first();

        if ($manager === null || ! $manager->is_active) {
            // No password to check — so hash one, which costs the same as checking would have.
            Hash::make($this->string('password')->toString());

            $this->fail();
        }

        if (! Auth::guard(EnsureEventManager::GUARD)->attempt($this->only('email', 'password') + ['is_active' => true], $this->boolean('remember'))) {
            $this->fail();
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function fail(): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages(['email' => trans('auth.failed')]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate('manager|'.Str::lower($this->string('email')).'|'.$this->ip());
    }
}
