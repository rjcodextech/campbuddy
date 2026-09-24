<?php

namespace App\Http\Requests;

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
            'source_site_url' => ['required', 'url:http,https', 'max:500', new NotPrivateNetworkUrl],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            // Blank = read it from the WordCamp site. Otherwise an IANA name
            // ("Asia/Kolkata") or a fixed offset ("+05:30").
            'timezone' => ['nullable', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail) {
                if (\App\Support\EventTime::normalize((string) $value) === null) {
                    $fail('Use a time zone like Asia/Kolkata or Europe/Berlin, or an offset like +05:30.');
                }
            }],
            'status' => ['required', Rule::in(['draft', 'approved', 'active', 'archived'])],
            'is_visible' => ['boolean'],
        ];
    }
}
