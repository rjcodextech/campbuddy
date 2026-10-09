<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The "Running late" form, shared by the admin and an event's managers
 * (Admin\ and Manager\ScheduleDelayController). Times are typed in the
 * venue's time zone.
 */
class ScheduleDelayForm
{
    /** @return array{track: ?string, minutes: int, from: CarbonImmutable, note: ?string, summary: string} */
    public static function read(Request $request, Event $event): array
    {
        $tracks = ScheduleDelay::tracks($event);
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:0', 'max:'.ScheduleDelay::MAX_MINUTES],
            'track' => ['nullable', 'string', Rule::in(['', ...$tracks])],
            'from' => ['required', 'date_format:Y-m-d\TH:i'],
            'note' => ['nullable', 'string', 'max:140'],
        ]);

        $track = ($data['track'] ?? '') === '' ? null : $data['track'];
        $from = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['from'], EventTime::zone($event));
        $minutes = (int) $data['minutes'];

        return [
            'track' => $track,
            'minutes' => $minutes,
            'from' => $from,
            'note' => $data['note'] ?? null,
            'summary' => $minutes > 0
                ? "{$minutes} min late · ".($track ?? 'Whole event').' · from '.$from->format('j M g:i A')
                : 'Cleared the delay for '.($track ?? 'the whole event'),
        ];
    }

    /** The form's starting "from": now at the venue, to the minute. */
    public static function defaultFrom(Event $event): string
    {
        return CarbonImmutable::now(EventTime::zone($event))->format('Y-m-d\TH:i');
    }
}
