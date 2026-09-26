<?php

namespace App\Http\Requests\Manager;

use App\Models\Event;
use App\Rules\NotPrivateNetworkUrl;
use App\Rules\WordCampUrl;
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
            'display_name' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F<>\[\]]/'],
            'short_name' => ['nullable', 'string', 'max:60', 'not_regex:/[\x00-\x1F\x7F<>\[\]]/'],
            // A manager may only point an event at a wordcamp.org site (an admin may use any public
            // address); an address an admin already set stays saveable as it is.
            'source_site_url' => ['required', 'url:http,https', 'max:500', new WordCampUrl(Event::whereKey($this->route('eventId'))->value('source_site_url')), new NotPrivateNetworkUrl],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.not_regex' => 'Use plain text in the display name: one line, without < > [ ] characters.',
            'short_name.not_regex' => 'Use plain text in the short name: one line, without < > [ ] characters.',
        ];
    }
}
