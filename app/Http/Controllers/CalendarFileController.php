<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /calendar.ics — hands a calendar file built on the attendee's phone
 * (calendar.js) straight back to it, with the headers iPhone Safari needs to
 * offer "Add to Calendar" (it won't for a file made in the page itself).
 *
 * Stateless: nothing is stored or logged, and only something shaped like a
 * calendar is accepted — never served as anything but text/calendar.
 */
class CalendarFileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'ics' => ['required', 'string', 'max:200000'],
            'filename' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+\.ics$/'],
        ]);

        // The request's trimming middleware drops the file's final line break; put it back.
        $ics = rtrim(str_replace("\0", '', $data['ics']))."\r\n";

        abort_unless(
            str_starts_with($ics, "BEGIN:VCALENDAR\r\n") && str_ends_with($ics, "END:VCALENDAR\r\n"),
            422,
            'That is not a calendar file.'
        );

        $filename = $data['filename'] ?? 'campbuddy.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
