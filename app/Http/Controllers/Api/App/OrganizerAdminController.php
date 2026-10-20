<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Jobs\DiscoverWordCampsJob;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\FetchLog;
use App\Models\OfferLead;
use App\Rules\NotPrivateNetworkUrl;
use App\Services\CachePurger;
use App\Services\DataRefresher;
use App\Support\CacheVersion;
use App\Support\ErrorReport;
use App\Support\EventEdits;
use App\Support\EventListing;
use App\Support\EventTime;
use App\Support\ManagerActivity;
use App\Support\SystemHealth;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * The admin's platform pages in the native app (routes behind
 * AppOrganizerAuth:admin): the dashboard, Errors, Feedback, Event managers
 * and their activity, a new event, and the three maintenance buttons — the
 * same data and the same cooldowns as the web panel.
 */
class OrganizerAdminController extends Controller
{
    use ResolvesAppOrganizer;

    public function dashboard(): JsonResponse
    {
        $today = now()->toDateString();
        $upcoming = Event::query()->whereIn('status', ['approved', 'active'])->whereNotNull('starts_on')
            ->whereRaw('COALESCE(ends_on, starts_on) >= ?', [$today])->orderBy('starts_on');
        $lastFetch = FetchLog::with('event:id,display_name')->where('job_type', 'sessions_speakers_sponsors')->latest('fetched_at')->first();
        $lastPurge = CacheVersion::last();

        return response()->json(['data' => [
            'errors' => ErrorReport::summary(),
            'draft_count' => Event::where('status', 'draft')->count(),
            'drafts' => Event::where('status', 'draft')->latest()->limit(5)->get()->map(fn (Event $e) => $this->eventRow($e))->values(),
            'upcoming_count' => (clone $upcoming)->count(),
            'next_events' => (clone $upcoming)->withCount(['attendeeRoster as roster_count' => fn ($q) => $q->where('is_suppressed', false)])
                ->limit(4)->get()->map(fn (Event $e) => $this->eventRow($e) + ['roster_count' => (int) $e->roster_count])->values(),
            'events_total' => Event::count(),
            'roster_count' => AttendeeRoster::where('is_suppressed', false)->count(),
            'lead_count' => OfferLead::count(),
            'recent_lead_count' => OfferLead::where('created_at', '>=', now()->subDays(7))->count(),
            'manager_count' => Schema::hasTable('event_managers') ? EventManager::count() : 0,
            'feedback_count' => EventFeedback::count(),
            'last_purge' => $lastPurge ? ['at' => $lastPurge['purged_at'] ?? null, 'by' => $lastPurge['by'] ?? null] : null,
            'last_data_fetch' => $lastFetch ? [
                'event' => $lastFetch->event?->display_name, 'status' => $lastFetch->status,
                'message' => $lastFetch->message, 'at' => $lastFetch->fetched_at?->toIso8601String(),
            ] : null,
        ]]);
    }

    public function errors(Request $request): JsonResponse
    {
        $filters = ErrorReport::filters($request);
        $problems = ErrorReport::fetchProblems($filters);

        return response()->json(['data' => [
            'system' => SystemHealth::problems(),
            'fetch' => collect($problems->items())->map(fn ($row) => [
                'event_id' => $row->event_id,
                'event' => $row->event?->display_name,
                'job' => ErrorReport::JOB_LABELS[$row->job_type] ?? $row->job_type,
                'status' => $row->status,
                'message' => $row->message,
                'times' => (int) $row->times,
                'last_at' => $row->last_at ? Carbon::parse($row->last_at)->toIso8601String() : null,
            ])->values(),
            'filters' => $filters,
            'periods' => collect(ErrorReport::PERIODS)->map(fn ($label, $key) => ['key' => (string) $key, 'label' => $label])->values(),
            'jobs' => collect(ErrorReport::JOB_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ], 'meta' => $this->meta($problems)]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $eventId = is_numeric($request->query('event')) ? (int) $request->query('event') : null;

        $summary = EventFeedback::query()
            ->select('event_id', DB::raw('COUNT(*) as answers'), DB::raw('AVG(rating) as average'))
            ->selectRaw('SUM(rating = 5) as r5, SUM(rating = 4) as r4, SUM(rating = 3) as r3, SUM(rating = 2) as r2, SUM(rating = 1) as r1')
            ->selectRaw('SUM(comment IS NOT NULL) as comments')
            ->groupBy('event_id')
            ->with('event:id,display_name')
            ->get()
            ->sortByDesc('answers')
            ->values();

        $feedback = EventFeedback::with('event:id,display_name')
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->latest()
            ->paginate(25);

        return response()->json(['data' => [
            'summary' => $summary->map(fn ($s) => [
                'event_id' => $s->event_id, 'event' => $s->event?->display_name, 'answers' => (int) $s->answers,
                'average' => round((float) $s->average, 2), 'comments' => (int) $s->comments,
                'stars' => [(int) $s->r5, (int) $s->r4, (int) $s->r3, (int) $s->r2, (int) $s->r1],
            ])->values(),
            'items' => collect($feedback->items())->map(fn ($f) => [
                'id' => $f->id, 'event' => $f->event?->display_name, 'rating' => (int) $f->rating,
                'comment' => $f->comment, 'at' => $f->created_at?->toIso8601String(),
            ])->values(),
        ], 'meta' => $this->meta($feedback)]);
    }

    // ---- event managers ------------------------------------------------------------------------

    public function managers(Request $request): JsonResponse
    {
        if (! Schema::hasTable('event_managers')) {
            return response()->json(['data' => []]);
        }
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return response()->json(['data' => EventManager::with(['events' => fn ($e) => $e->select('events.id', 'events.display_name')->orderBy('events.display_name')])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (EventManager $m) => $this->managerData($m))
            ->values()]);
    }

    public function storeManager(Request $request): JsonResponse
    {
        $data = $this->validateManager($request, null);
        $manager = EventManager::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
            'password' => $data['password'], 'is_active' => $data['is_active'] ?? true,
        ]);
        $manager->events()->sync($data['events']);

        return response()->json(['data' => $this->managerData($manager->load('events')), 'message' => "Event manager “{$manager->name}” created. They sign in with this e-mail and password."], 201);
    }

    public function updateManager(Request $request, int $itemId): JsonResponse
    {
        $manager = EventManager::findOrFail($itemId);
        $data = $this->validateManager($request, $manager);

        $manager->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'is_active' => $data['is_active'] ?? $manager->is_active]);
        if (filled($data['password'] ?? null)) {
            $manager->password = $data['password'];
            $manager->setRememberToken(Str::random(60));
        }
        $manager->save();
        $manager->events()->sync($data['events'] ?? []);

        // Switched off or a new password: the app sign-ins of that manager stop working too.
        if (! $manager->is_active || filled($data['password'] ?? null)) {
            DB::table('app_api_tokens')->where('role', 'manager')->where('user_id', $manager->id)->delete();
        }

        return response()->json(['data' => $this->managerData($manager->load('events')), 'message' => "Event manager “{$manager->name}” updated."]);
    }

    public function destroyManager(int $itemId): JsonResponse
    {
        $manager = EventManager::findOrFail($itemId);
        $manager->delete();
        DB::table('app_api_tokens')->where('role', 'manager')->where('user_id', $itemId)->delete();

        return response()->json(['message' => "Event manager “{$manager->name}” deleted."]);
    }

    public function activity(Request $request): JsonResponse
    {
        if (! Schema::hasTable('event_manager_changes')) {
            return response()->json(['data' => [], 'managers' => [], 'events' => [], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 30]]);
        }
        $id = fn (string $key): ?int => ctype_digit((string) $request->query($key)) && (int) $request->query($key) > 0 ? (int) $request->query($key) : null;

        $changes = EventManagerChange::with('event:id,display_name')
            ->when($id('manager'), fn ($q, int $m) => $q->where('event_manager_id', $m))
            ->when($id('event'), fn ($q, int $e) => $q->where('event_id', $e))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => collect($changes->items())->map(fn ($c) => [
                'id' => $c->id, 'manager' => $c->manager_name, 'event' => $c->event?->display_name, 'section' => $c->section,
                'action' => $c->action, 'summary' => $c->summary, 'at' => $c->created_at?->toIso8601String(),
            ])->values(),
            'managers' => EventManager::orderBy('name')->get(['id', 'name'])->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values(),
            'events' => Event::whereIn('id', EventManagerChange::query()->select('event_id'))->orderBy('display_name')->get(['id', 'display_name'])->map(fn ($e) => ['id' => $e->id, 'name' => $e->display_name])->values(),
            'meta' => $this->meta($changes),
            'keep_days' => ManagerActivity::KEEP_DAYS,
        ]);
    }

    // ---- events --------------------------------------------------------------------------------

    /** Every event as id + name, for pickers (a manager's events, the filters). */
    public function eventOptions(): JsonResponse
    {
        return response()->json(['data' => EventListing::ordered(Event::query())->get(['id', 'display_name', 'status', 'starts_on', 'ends_on'])
            ->map(fn (Event $e) => ['id' => $e->id, 'name' => $e->display_name, 'status' => $e->status, 'starts_on' => $e->starts_on?->toDateString()])->values()]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', Rule::unique('events', 'slug')],
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
            'status' => ['required', Rule::in(EventListing::STATUSES)],
            'is_visible' => ['boolean'],
        ]);

        // As the admin's web form: typed dates and a typed zone are kept (locked); a blank zone is read from the site.
        $data = EventEdits::dates($data, null);
        if (array_key_exists('timezone', $data)) {
            $zone = EventTime::normalize($data['timezone']);
            $data['timezone'] = $zone;
            $data['timezone_locked'] = $zone !== null;
        }

        $event = Event::create($data);

        return response()->json(['data' => $this->eventRow($event), 'message' => "Event \"{$event->display_name}\" created."], 201);
    }

    public function discover(): JsonResponse
    {
        try {
            DiscoverWordCampsJob::dispatch();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => ['code' => 'queue', 'message' => 'Discovery couldn\'t be queued — the job queue isn\'t reachable.']], 503);
        }

        return response()->json(['message' => 'Discovery queued — new WordCamps will appear as drafts shortly.']);
    }

    /** "Clear cache", with the web button's cooldown and lock (see CachePurgeController). */
    public function purge(Request $request, CachePurger $purger): JsonResponse
    {
        $last = CacheVersion::last();
        if ($last && Carbon::parse($last['purged_at'])->gt(now()->subSeconds(10))) {
            return response()->json(['error' => ['code' => 'cooldown', 'message' => 'The cache was cleared a few seconds ago — give it a moment.']], 429);
        }

        $lock = Cache::lock('admin:cache-purge', 180);
        if (! $lock->get()) {
            return response()->json(['error' => ['code' => 'busy', 'message' => 'The cache is already being cleared — it will finish in a moment.']], 409);
        }

        try {
            $report = $purger->purge($request->user()?->email ?? $request->user()?->name);
        } finally {
            $lock->release();
        }

        return response()->json(['message' => $report['message']]);
    }

    /** "Refresh event data", with the web button's cooldown and lock (see DataRefreshController). */
    public function refreshData(DataRefresher $refresher): JsonResponse
    {
        if (Cache::has('admin:data-refresh:recent')) {
            return response()->json(['error' => ['code' => 'cooldown', 'message' => 'Event data was refreshed a moment ago — give it half a minute.']], 429);
        }

        $lock = Cache::lock('admin:data-refresh', 180);
        if (! $lock->get()) {
            return response()->json(['error' => ['code' => 'busy', 'message' => 'A refresh is already running — it will finish in a moment.']], 409);
        }

        try {
            @set_time_limit(300);
            $report = $refresher->refresh();
            Cache::put('admin:data-refresh:recent', true, 30);
        } finally {
            $lock->release();
        }

        return response()->json(['message' => $report['message']]);
    }

    // ---- shapes ------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function eventRow(Event $e): array
    {
        return [
            'id' => $e->id, 'slug' => $e->slug, 'name' => $e->display_name, 'status' => $e->status,
            'is_visible' => (bool) $e->is_visible, 'starts_on' => $e->starts_on?->toDateString(), 'ends_on' => $e->ends_on?->toDateString(),
            'timezone' => EventTime::zone($e)->getName(), 'timing' => EventListing::timing($e),
        ];
    }

    /** @return array<string, mixed> */
    private function managerData(EventManager $m): array
    {
        return [
            'id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'phone' => $m->phone, 'is_active' => (bool) $m->is_active,
            'last_login_at' => $m->last_login_at?->toIso8601String(),
            'events' => $m->events->map(fn ($e) => ['id' => $e->id, 'name' => $e->display_name])->values(),
        ];
    }

    /** The web form's rules (Store/UpdateEventManagerRequest): a new manager needs a password and an event. */
    private function validateManager(Request $request, ?EventManager $manager): array
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $creating = $manager === null;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('event_managers', 'email')->ignore($manager?->id)],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9(][0-9\s\-().]{5,19}$/'],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::min(8)->max(72)],
            'is_active' => ['boolean'],
            'events' => [$creating ? 'required' : 'nullable', 'array', ...($creating ? ['min:1'] : [])],
            'events.*' => ['integer', 'distinct', Rule::exists('events', 'id')],
        ], [
            'events.required' => 'Pick at least one WordCamp for this manager.',
            'events.min' => 'Pick at least one WordCamp for this manager.',
            'phone.regex' => 'Enter a phone number using digits, spaces, + or -.',
        ]);
    }
}
