<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadEventBrandingRequest extends FormRequest
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
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,ico,svg', 'max:512'],
        ];
    }
}
