<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * What attendees said on the after-event thank-you card: per WordCamp, the
 * average rating and how the stars split, then the comments, newest first.
 * Anonymous by design — there's nothing to say who wrote what.
 */
class FeedbackController extends Controller
{
    public function __invoke(Request $request): View
    {
        $eventId = is_numeric($request->query('event')) ? (int) $request->query('event') : null;

        $summary = EventFeedback::query()
            ->select('event_id', DB::raw('COUNT(*) as answers'), DB::raw('AVG(rating) as average'))
            ->selectRaw('SUM(rating = 5) as r5, SUM(rating = 4) as r4, SUM(rating = 3) as r3, SUM(rating = 2) as r2, SUM(rating = 1) as r1')
            ->selectRaw('SUM(comment IS NOT NULL) as comments')
            ->groupBy('event_id')
            ->with('event:id,display_name,slug')
            ->get()
            ->sortByDesc('answers')
            ->values();

        $feedback = EventFeedback::with('event:id,display_name')
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.feedback.index', [
            'summary' => $summary,
            'feedback' => $feedback,
            'eventId' => $eventId,
            'events' => Event::whereIn('id', $summary->pluck('event_id'))->orderBy('display_name')->pluck('display_name', 'id'),
        ]);
    }
}
