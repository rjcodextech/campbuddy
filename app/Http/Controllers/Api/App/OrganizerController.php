<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\AppEventApi;
use App\Support\AppOrganizer;
use App\Support\DiscoveryReports;
use App\Support\EventListing;
use App\Support\EventMemories;
use App\Support\EventTime;
use App\Support\ManagerActivity;
use App\Support\RosterMarks;
use App\Support\RosterRoles;
use App\Support\ScheduleDelay;
use App\Support\ScheduleDelayForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;


/**
 * The native app organizer endpoints: sign in with a bearer token, then the
 * everyday jobs - the events you run, reports on profiles, "running late".
 * Managers only ever see their own events (a plain 404 for anyone else's);
 * admins see all. Same rules as the web panel, another front end.
 */
class OrganizerController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,manager'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        // Five wrong tries a minute per e-mail and address, like the web sign-in.
        $key = 'app-login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['error' => ['code' => 'throttled', 'message' => 'Too many tries. Wait a minute.']], 429);
        }

        $user = $data['role'] === 'admin'
            ? User::where('email', $data['email'])->first()
            : EventManager::where('email', $data['email'])->where('is_active', true)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return response()->json(['error' => ['code' => 'invalid_credentials', 'message' => 'Wrong e-mail or password.']], 422);
        }

        RateLimiter::clear($key);

        return response()->json(AppOrganizer::issue($data['role'], $user->id, $data['device_name'] ?? null) + $this->person($data['role'], $user));
    }

    public function logout(Request $request): JsonResponse
    {
        AppOrganizer::revoke((string) $request->bearerToken());

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->person($request->attributes->get('app_role'), $request->user()));
    }

    public function events(Request $request): JsonResponse
    {
        $query = $this->scope($request);

        // The app's events list asks for the web panel's filters and order (EventListing); without them, the old answer.
        if ($request->hasAny(['q', 'status', 'visibility', 'when', 'order'])) {
            $today = EventListing::today();
            $base = $request->attributes->get('app_role') === 'admin'
                ? Event::query()
                : Event::query()->whereIn('id', $request->user()->events()->select('events.id'));
            $query = EventListing::ordered(EventListing::filtered($base, EventListing::filters($request), $today), $today);
        } else {
            $query = $query->orderByRaw('starts_on IS NULL')->orderByDesc('starts_on');
        }

        return response()->json(['data' => $query->withCount(['attendeeRoster as roster_count' => fn ($q) => $q->where('is_suppressed', false)])->limit(200)->get()->map(fn (Event $e) => [
            'id' => $e->id,
            'slug' => $e->slug,
            'name' => $e->display_name,
            'status' => $e->status,
            'is_visible' => (bool) $e->is_visible,
            'starts_on' => $e->starts_on?->toDateString(),
            'ends_on' => $e->ends_on?->toDateString(),
            'timezone' => EventTime::zone($e)->getName(),
            'open_reports' => DiscoveryReport::where('event_id', $e->id)->where('status', 'open')->distinct('discovery_id')->count('discovery_id'),
            'short_name' => $e->short_name,
            'roster_count' => (int) $e->roster_count,
            'timing' => EventListing::timing($e),
        ])->values()]);
    }

    public function reports(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $data = DiscoveryReports::forEvent($event);

        return response()->json(['data' => [
            'open' => $data['open']->map(fn ($reports, $discoveryId) => [
                'discovery_id' => $discoveryId,
                'profile_still_there' => in_array($discoveryId, $data['stillThere'], true),
                'profile' => $reports->first()->snapshot,
                'reports' => $reports->map(fn ($r) => ['reason' => $r->reason, 'label' => DiscoveryReport::REASONS[$r->reason] ?? $r->reason, 'note' => $r->note, 'at' => $r->created_at->toIso8601String()])->values(),
            ])->values(),
            'handled' => $data['closed']->map(fn ($r) => ['reason' => $r->reason, 'status' => $r->status, 'profile' => $r->snapshot, 'at' => $r->resolved_at?->toIso8601String()])->values(),
        ]]);
    }

    public function removeReported(Request $request, int $id, string $discoveryId): JsonResponse
    {
        DiscoveryReports::remove($this->event($request, $id), $discoveryId);

        return response()->json(null, 204);
    }

    public function dismissReported(Request $request, int $id, string $discoveryId): JsonResponse
    {
        DiscoveryReports::dismiss($this->event($request, $id), $discoveryId);

        return response()->json(null, 204);
    }

    public function delay(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);

        return response()->json(['data' => ['active' => AppEventApi::delay($event), 'tracks' => ScheduleDelay::tracks($event), 'timezone' => EventTime::zone($event)->getName(), 'max_minutes' => ScheduleDelay::MAX_MINUTES]]);
    }

    public function setDelay(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $form = ScheduleDelayForm::read($request, $event);
        $role = $request->attributes->get('app_role');
        ScheduleDelay::set($event, $form['track'], $form['minutes'], $form['from'], $form['note'], $role.': '.$request->user()->name);

        return response()->json(['data' => ['summary' => $form['summary'], 'active' => AppEventApi::delay($event->fresh())]]);
    }

    public function clearDelay(Request $request, int $id): JsonResponse
    {
        ScheduleDelay::clear($this->event($request, $id));

        return response()->json(null, 204);
    }

    public function overview(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $info = $event->info ?? [];

        return response()->json(['data' => [
            'id' => $event->id,
            'slug' => $event->slug,
            'name' => $event->display_name,
            'status' => $event->status,
            'is_visible' => (bool) $event->is_visible,
            'starts_on' => $event->starts_on?->toDateString(),
            'ends_on' => $event->ends_on?->toDateString(),
            'timezone' => EventTime::zone($event)->getName(),
            'counts' => [
                'roster' => $event->attendeeRoster()->where('is_suppressed', false)->count(),
                'discovery' => DiscoveryProfile::where('event_id', $event->id)->count(),
                'sessions' => count(AppEventApi::sessions($event)),
                'speakers' => count(AppEventApi::speakers($event)),
                'sponsors' => count(AppEventApi::sponsors($event)),
                'tables' => count(AppEventApi::tables($event)),
                'open_reports' => DiscoveryReport::where('event_id', $event->id)->where('status', 'open')->distinct('discovery_id')->count('discovery_id'),
                'quests' => $event->quests()->count(),
                'deals' => $event->offers()->count(),
            ],
            'ends_on' => $event->ends_on?->toDateString(),
            'timing' => EventListing::timing($event),
            'role' => $request->attributes->get('app_role'),
            'delay' => AppEventApi::delay($event),
            'info' => [
                'wifi' => $info['wifi'] ?? null,
                'venue' => $info['venue'] ?? null,
                'emergency_contact' => $info['emergency_contact'] ?? null,
                'registration_info' => $info['registration_info'] ?? null,
                'contributor_day_location' => $info['contributor_day_location'] ?? null,
                'social_event_info' => $info['social_event_info'] ?? null,
                'nearby_venue_info' => $info['nearby_venue_info'] ?? null,
                'important_links' => $info['important_links'] ?? null,
                'code_of_conduct_url' => $info['code_of_conduct_url'] ?? null,
            ],
            'memories' => [
                'photo_album_url' => $event->photo_album_url,
                'photo_album_notified_at' => $event->photo_album_notified_at?->toIso8601String(),
                'wordpress_tv_url' => $event->wordpress_tv_url,
                'wordpress_tv_notified_at' => $event->wordpress_tv_notified_at?->toIso8601String(),
                'subscribers_count' => EventMemories::recipients($event),
            ],
        ]]);
    }

    public function updateInfo(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $data = $request->validate([
            'wifi' => ['nullable', 'string', 'max:500'],
            'venue' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:500'],
            'registration_info' => ['nullable', 'string', 'max:1000'],
            'contributor_day_location' => ['nullable', 'string', 'max:255'],
        ]);

        $before = $event->info ?? [];
        $info = $before;
        foreach ($data as $k => $v) {
            if ($v === null || $v === '') {
                unset($info[$k]);
            } else {
                $info[$k] = $v;
            }
        }
        $event->update(['info' => $info]);

        $role = $request->attributes->get('app_role');
        if ($role === 'manager') {
            ManagerActivity::record($request->user(), $event, 'information', 'updated', 'Updated event information via mobile app');
        }

        return response()->json(['data' => $event->fresh()->info]);
    }

    public function updateMemories(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $summary = EventMemories::save($request, $event);

        $role = $request->attributes->get('app_role');
        if ($role === 'manager' && $summary !== 'Nothing changed') {
            ManagerActivity::record($request->user(), $event, 'memories', 'updated', $summary . ' via mobile app');
        }

        return response()->json([
            'data' => [
                'summary' => $summary,
                'photo_album_url' => $event->photo_album_url,
                'photo_album_notified_at' => $event->photo_album_notified_at?->toIso8601String(),
                'wordpress_tv_url' => $event->wordpress_tv_url,
                'wordpress_tv_notified_at' => $event->wordpress_tv_notified_at?->toIso8601String(),
            ]
        ]);
    }

    public function notifyMemories(Request $request, int $id, string $kind): JsonResponse
    {
        $event = $this->event($request, $id);
        abort_unless(array_key_exists($kind, EventMemories::KINDS), 404);
        EventMemories::notify($event, $kind);
        $label = EventMemories::KINDS[$kind]['label'];

        $role = $request->attributes->get('app_role');
        if ($role === 'manager') {
            ManagerActivity::record($request->user(), $event, 'memories', 'notified', "Sent {$label} notification via mobile app");
        }

        return response()->json([
            'data' => [
                'sent' => true,
                'message' => "{$label} notification sent to " . EventMemories::recipients($event) . ' phones.',
            ]
        ]);
    }

    public function attendees(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $q = $request->string('q')->trim()->toString();
        $role = $request->string('role')->toString();
        // Suppressed entries are the admin's to see (and restore); a manager never gets them.
        $status = $request->attributes->get('app_role') === 'admin' && in_array($request->query('status'), ['suppressed', 'all'], true) ? $request->query('status') : 'listed';
        $both = RosterRoles::withAutomatic($event);
        $marked = $both['marked'];
        $auto = $both['auto'];

        $query = $event->attendeeRoster()
            ->when($status === 'listed', fn ($qry) => $qry->where('is_suppressed', false))
            ->when($status === 'suppressed', fn ($qry) => $qry->where('is_suppressed', true))
            ->when($q !== '', fn ($qry) => $qry->where('name', 'like', '%'.$q.'%'))
            ->when(in_array($role, RosterRoles::ROLES, true), fn ($qry) => $qry->whereIn('id', collect($marked)->filter(fn ($r) => in_array($role, $r['roles'], true))->keys()->all()))
            ->when($role === 'none', fn ($qry) => $qry->whereNotIn('id', collect($marked)->filter(fn ($r) => $r['roles'] !== [])->keys()->all()))
            ->withExists('discoveryProfile as in_discovery')
            ->withExists('sharedCampCard as has_camp_card')
            ->orderBy('name');

        $page = $query->paginate(100);

        $list = collect($page->items())->map(function ($entry) use ($marked, $auto) {
            return [
                'id' => $entry->id,
                'name' => $entry->name,
                'gravatar_url' => $entry->photo(),
                'roles' => $marked[$entry->id]['roles'] ?? [],
                'auto_roles' => $auto[$entry->id]['roles'] ?? [],
                'talks' => $marked[$entry->id]['talks'] ?? [],
                'is_suppressed' => (bool) $entry->is_suppressed,
                'in_discovery' => (bool) $entry->in_discovery,
                'has_camp_card' => (bool) $entry->has_camp_card,
            ];
        });

        return response()->json(['data' => $list, 'meta' => [
            'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage(),
            'roles' => collect(RosterRoles::LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]]);
    }

    public function setAttendeeRoles(Request $request, int $id, int $entryId): JsonResponse
    {
        $event = $this->event($request, $id);
        $entry = AttendeeRoster::where('event_id', $event->id)->where('is_suppressed', false)->findOrFail($entryId);
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(RosterRoles::ROLES)],
        ]);

        $roles = $data['roles'];
        $role = $request->attributes->get('app_role');
        $user = $request->user();
        $who = ($role === 'admin' ? 'Admin: ' : 'Manager: ') . $user->name;

        RosterMarks::set($event, $entry, $roles, $who);

        if ($role === 'manager') {
            ManagerActivity::record($user, $event, 'attendees', 'roles', "Updated roles for {$entry->name} via mobile app");
        }

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'name' => $entry->name,
                'roles' => $roles,
            ]
        ]);
    }


    // ---- helpers ----------------------------------------------------------------------------------

    /** @return array{role: string, user: array{id: int, name: string, email: string}} */
    private function person(string $role, $user): array
    {
        return ['role' => $role, 'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]];
    }

    private function scope(Request $request)
    {
        return $request->attributes->get('app_role') === 'admin'
            ? Event::query()
            : $request->user()->events();
    }

    private function event(Request $request, int $id): Event
    {
        return $this->scope($request)->whereKey($id)->firstOrFail();
    }
}
