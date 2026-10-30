<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MediaPitch;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Events → Media Partner Pitch.
 * Prepares, customizes, and exports (Email / PDF) media partnership pitches
 * for any WordCamp event on behalf of CampBuddy × WPSimplified. The words live in
 * App\Support\MediaPitch, so the page, the copied email and the PDF always agree.
 */
class MediaPitchController extends Controller
{
    public function index(Request $request): View
    {
        $events = MediaPitch::events();
        $wanted = (string) $request->query('event', '');
        $selected = $events->firstWhere('slug', $wanted)
            ?? $events->firstWhere('slug', 'wordcamp-delhi-2026')
            ?? $events->first();

        return view('admin.media-pitch.index', [
            'events' => $events,
            'selected' => $selected,
            'applicant' => MediaPitch::APPLICANT,
            'bios' => MediaPitch::bios(),
            'emailTemplate' => MediaPitch::emailTemplate(MediaPitch::upcomingCount()),
            'subjectTemplate' => MediaPitch::subjectTemplate(),
            'readyLines' => ['link' => MediaPitch::readyLine(true), 'none' => MediaPitch::readyLine(false)],
        ]);
    }

    public function print(Request $request): View
    {
        $text = fn (string $key, string $default = '') => trim((string) $request->query($key, $default));
        $event = $text('event_name', 'WordCamp Delhi 2026');
        $dates = $text('event_dates');
        $venue = $text('event_venue');
        $details = implode(', ', array_filter([$dates, $venue]));
        $bios = MediaPitch::bios();
        // An edited bio from the page replaces the full one; otherwise the full one is printed.
        foreach (array_keys($bios) as $key) {
            $edited = $text("{$key}_bio");
            if ($edited !== '') {
                $bios[$key]['full'] = $edited;
            }
        }

        $data = [
            'applicant_name' => $text('applicant_name', MediaPitch::APPLICANT['name']),
            'applicant_role' => $text('applicant_role', MediaPitch::APPLICANT['role']),
            'applicant_email' => $text('applicant_email', MediaPitch::APPLICANT['email']),
            'applicant_phone' => $text('applicant_phone', MediaPitch::APPLICANT['phone']),
            'show_phone' => filter_var($request->query('show_phone', true), FILTER_VALIDATE_BOOLEAN),
            'event_name' => $event,
            'event_dates' => $dates,
            'event_venue' => $venue,
            'event_email' => $text('event_email'),
            // Shown as a link on the page: only an http(s) address.
            'event_link' => (string) SafeUrl::web($text('event_link')),
            'subject' => $text('subject', str_replace('{event}', $event, MediaPitch::subjectTemplate())),
            'bios' => $bios,
        ];
        $data['sections'] = MediaPitch::sections([
            'event' => $event,
            'details' => $details !== '' ? " ({$details})" : '',
            'link' => $data['event_link'],
            'name' => $data['applicant_name'],
            'role' => $data['applicant_role'],
            'email' => $data['applicant_email'],
            'phone' => '',
            'count' => (string) MediaPitch::upcomingCount(),
        ]);

        return view('admin.media-pitch.print', $data);
    }
}
