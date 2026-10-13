<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\AppEventApi;
use App\Support\AppOrganizer;
use App\Support\DiscoveryReports;
use App\Support\EventTime;
use App\Support\ScheduleDelay;
use App\Support\ScheduleDelayForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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

        return response()->json(['data' => $query->orderByRaw('starts_on IS NULL')->orderByDesc('starts_on')->limit(200)->get()->map(fn (Event $e) => [
            'id' => $e->id,
            'slug' => $e->slug,
            'name' => $e->display_name,
            'status' => $e->status,
            'is_visible' => (bool) $e->is_visible,
            'starts_on' => $e->starts_on?->toDateString(),
            'ends_on' => $e->ends_on?->toDateString(),
            'timezone' => EventTime::zone($e)->getName(),
            'open_reports' => DiscoveryReport::where('event_id', $e->id)->where('status', 'open')->distinct('discovery_id')->count('discovery_id'),
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
