<?php

namespace App\Http\Requests\Manager;

use App\Rules\NotPrivateNetworkUrl;
use App\Support\EventTime;
use Closure;
use Illuminate\Validation\Rule;

/**
 * An event manager's edit of "Event details". The same fields as the admin's
 * form (UpdateEventRequest) except the lifecycle status — which is simply not
 * a rule here, so it can't be sent in: `validated()` only ever contains the
 * fields below.
 */
class UpdateManagedEventRequest extends ManagerEventRequest
{
    /** Every field a manager may change, in one place (the controller and the tests read it). */
    public const FIELDS = ['slug', 'display_name', 'short_name', 'source_site_url', 'starts_on', 'ends_on', 'timezone', 'is_visible'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', Rule::unique('events', 'slug')->ignore($this->route('eventId'))],
            'display_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'source_site_url' => ['required', 'url:http,https', 'max:500', new NotPrivateNetworkUrl],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'timezone' => ['nullable', 'string', 'max:64', function (string $attribute, mixed $value, Closure $fail) {
                if (EventTime::normalize((string) $value) === null) {
                    $fail('Use a time zone like Asia/Kolkata or Europe/Berlin, or an offset like +05:30.');
                }
            }],
            'is_visible' => ['boolean'],
        ];
    }
}
