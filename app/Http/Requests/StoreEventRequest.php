<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Rules\NotPrivateNetworkUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', Rule::unique('events', 'slug')],
            'display_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'source_site_url' => ['required', 'url', 'max:500', new NotPrivateNetworkUrl],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['draft', 'approved', 'active', 'archived'])],
            'is_visible' => ['boolean'],
        ];
    }
}
