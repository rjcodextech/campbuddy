<?php

namespace App\Http\Requests;

use App\Models\EventManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * An admin creating an event manager: who they are, their password, and the
 * events they may edit (at least one).
 */
class StoreEventManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EventManager::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('event_managers', 'email')],
            'phone' => $this->phoneRules(),
            'password' => ['required', 'string', Password::min(8)->max(72)],
            'is_active' => ['boolean'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['integer', 'distinct', Rule::exists('events', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'events.required' => 'Pick at least one WordCamp for this manager.',
            'events.min' => 'Pick at least one WordCamp for this manager.',
            'phone.regex' => 'Enter a phone number using digits, spaces, + or -.',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function phoneRules(): array
    {
        return ['required', 'string', 'max:20', 'regex:/^\+?[0-9(][0-9\s\-().]{5,19}$/'];
    }
}
