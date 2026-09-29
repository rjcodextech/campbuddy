<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** One Free Steal, from Admin → Free Steals. */
class StoreFreeStealRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:300'],
            'maker' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
