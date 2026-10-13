<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNativePushTokenRequest extends FormRequest
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
            'device_id' => ['required', 'string', 'max:64'],
            'platform' => ['required', 'in:android,ios'],
            // FCM tokens are url-safe text of roughly 150-200 characters.
            'token' => ['required', 'string', 'min:20', 'max:512', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
            'app_version' => ['nullable', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/'],
        ];
    }
}
