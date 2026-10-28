<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Events → Media Partner Pitch.
 * Prepares, customizes, and exports (Email / PDF) media partnership pitches
 * for any WordCamp event on behalf of CampBuddy × WPSimplified.
 */
class MediaPitchController extends Controller
{
    public function index(Request $request): View
    {
        $events = rescue(
            fn () => Event::query()
                ->orderByRaw('starts_on IS NULL, starts_on DESC')
                ->get([
                    'id',
                    'display_name',
                    'slug',
                    'city',
                    'country',
                    'starts_on',
                    'ends_on',
                    'venue_name',
                    'source_site_url',
                ]),
            collect()
        );

        $selectedSlug = (string) $request->query('event', '');
        $selectedEvent = $events->firstWhere('slug', $selectedSlug)
            ?: $events->firstWhere('id', (int) $selectedSlug);

        // Pre-formatted default values
        $defaults = [
            'applicant_name' => 'Sunil Kumar Sharma',
            'applicant_role' => 'Lead — WPSimplified & CampBuddy',
            'applicant_email' => 'hello@wpsimplified.in',
            'applicant_phone' => '+91-7877994704',
            'event_name' => $selectedEvent?->display_name ?? 'WordCamp Delhi 2026',
            'event_dates' => $selectedEvent && $selectedEvent->starts_on
                ? ($selectedEvent->starts_on->format('j M Y').($selectedEvent->ends_on && ! $selectedEvent->ends_on->isSameDay($selectedEvent->starts_on) ? ' – '.$selectedEvent->ends_on->format('j M Y') : ''))
                : '12–13 December 2026',
            'event_venue' => $selectedEvent?->venue_name ?? 'Dr. Ambedkar International Centre, New Delhi',
            'event_email' => $selectedEvent?->slug ? $selectedEvent->slug.'@wordcamp.org' : 'delhi@wordcamp.org',
            'campbuddy' => [
                'website' => 'https://campbuddy.club/',
                'youtube' => 'https://www.youtube.com/@CampBuddyClub',
                'instagram' => 'https://www.instagram.com/campbuddyclub/',
                'x' => 'https://x.com/CampBuddyClub',
            ],
            'wpsimplified' => [
                'website' => 'https://wpsimplified.in/',
                'youtube' => 'https://www.youtube.com/@WPSimplifiedBySunil',
                'instagram' => 'https://www.instagram.com/wpsimplified/',
                'x' => 'https://x.com/iwpsimplified',
                'linkedin' => 'https://www.linkedin.com/company/wpsimplified/',
            ],
        ];

        return view('admin.media-pitch.index', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'defaults' => $defaults,
        ]);
    }

    public function print(Request $request): View
    {
        $data = [
            'applicant_name' => (string) $request->query('applicant_name', 'Sunil Kumar Sharma'),
            'applicant_role' => (string) $request->query('applicant_role', 'Lead — WPSimplified & CampBuddy'),
            'applicant_email' => (string) $request->query('applicant_email', 'hello@wpsimplified.in'),
            'applicant_phone' => (string) $request->query('applicant_phone', '+91-7877994704'),
            'show_phone' => filter_var($request->query('show_phone', true), FILTER_VALIDATE_BOOLEAN),
            'event_name' => (string) $request->query('event_name', 'WordCamp Delhi 2026'),
            'event_dates' => (string) $request->query('event_dates', '12–13 December 2026'),
            'event_venue' => (string) $request->query('event_venue', 'Dr. Ambedkar International Centre, New Delhi'),
            'event_email' => (string) $request->query('event_email', 'delhi@wordcamp.org'),
            'subject' => (string) $request->query('subject', 'Media Partnership Proposal: WordCamp Delhi 2026 × CampBuddy & WPSimplified'),
            'body' => (string) $request->query('body', ''),
            'bio' => (string) $request->query('bio', ''),
        ];

        return view('admin.media-pitch.print', $data);
    }
}
