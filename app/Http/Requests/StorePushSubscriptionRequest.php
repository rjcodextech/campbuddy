<?php

namespace App\Http\Requests;

use App\Rules\NotPrivateNetworkUrl;
use Illuminate\Foundation\Http\FormRequest;

class StorePushSubscriptionRequest extends FormRequest
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
            // The server later POSTs to this address, so it has to be a real
            // https push service — never a private or internal one (SSRF).
            'endpoint' => ['required', 'url:https', 'max:500', new NotPrivateNetworkUrl],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }
}
