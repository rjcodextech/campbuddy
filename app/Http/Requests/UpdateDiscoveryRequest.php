<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tags' => ['required', 'array', 'min:1', 'max:5'],
            'tags.*' => [Rule::in(StoreDiscoveryRequest::TAGS)],
            'profession' => ['nullable', 'string', 'max:100'],
            'who_to_meet' => ['nullable', 'string', 'max:255'],
        ];
    }
}
