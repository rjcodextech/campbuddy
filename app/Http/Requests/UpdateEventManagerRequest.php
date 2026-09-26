<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * An admin editing an event manager. Same as creating one, except the
 * password is optional (blank keeps the current one) and the manager may be
 * left with no events for now.
 */
class UpdateEventManagerRequest extends StoreEventManagerRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event_manager'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('event_managers', 'email')->ignore($this->route('event_manager'))],
            'phone' => $this->phoneRules(),
            'password' => ['nullable', 'string', Password::min(8)->max(72)],
            'is_active' => ['boolean'],
            'events' => ['nullable', 'array'],
            'events.*' => ['integer', 'distinct', Rule::exists('events', 'id')],
        ];
    }
}
