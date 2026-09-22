<?php

namespace App\Http\Requests;

use App\Rules\MeetsColorContrast;
use App\Rules\NotPrivateNetworkUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', Rule::unique('events', 'slug')->ignore($this->route('event'))],
            'display_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'source_site_url' => ['required', 'url', 'max:500', new NotPrivateNetworkUrl],
            'primary_color' => ['nullable', 'string', new MeetsColorContrast],
            'accent_color' => ['nullable', 'string', new MeetsColorContrast],
            'status' => ['required', Rule::in(['draft', 'approved', 'active', 'archived'])],
            'is_visible' => ['boolean'],
        ];
    }
}
