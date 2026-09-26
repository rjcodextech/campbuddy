<?php

namespace App\Http\Requests\Manager;

/**
 * An event manager's edit of "Event information" — the fields the admin's
 * Event information card has, with the same limits.
 */
class UpdateManagedEventInfoRequest extends ManagerEventRequest
{
    public const FIELDS = ['venue', 'important_links', 'wifi', 'social_event_info', 'registration_info', 'contributor_day_location', 'code_of_conduct_url', 'emergency_contact', 'nearby_venue_info'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'venue' => ['nullable', 'string', 'max:255'],
            'important_links' => ['nullable', 'string', 'max:2000'],
            'wifi' => ['nullable', 'string', 'max:500'],
            'social_event_info' => ['nullable', 'string', 'max:1000'],
            'registration_info' => ['nullable', 'string', 'max:1000'],
            'contributor_day_location' => ['nullable', 'string', 'max:255'],
            'code_of_conduct_url' => ['nullable', 'url:http,https', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:500'],
            'nearby_venue_info' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
