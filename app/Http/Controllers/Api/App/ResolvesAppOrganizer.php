<?php

namespace App\Http\Controllers\Api\App;

use App\Models\Event;
use App\Models\EventManager;
use App\Support\ManagerActivity;
use Illuminate\Http\Request;

/**
 * Who is signed in to the app's organizer side, and which events they may
 * touch: an admin every event, a manager only their own (anything else is a
 * plain 404, before any validation). Same rule as OrganizerController.
 */
trait ResolvesAppOrganizer
{
    private function isAdmin(Request $request): bool
    {
        return $request->attributes->get('app_role') === 'admin';
    }

    private function scope(Request $request)
    {
        return $this->isAdmin($request) ? Event::query() : $request->user()->events();
    }

    private function event(Request $request, int $id): Event
    {
        return $this->scope($request)->whereKey($id)->firstOrFail();
    }

    /** Admin-only parts of an event answer 403, like the admin-only routes. */
    private function adminOnly(Request $request): void
    {
        abort_unless($this->isAdmin($request), 403, 'This is for administrators.');
    }

    /** A manager's change goes to the activity log, as it does from the web panel. */
    private function record(Request $request, Event $event, string $section, string $action, string $summary): void
    {
        $user = $request->user();
        if ($user instanceof EventManager) {
            ManagerActivity::record($user, $event, $section, $action, $summary.' (app)');
        }
    }

    /** "Admin: Asha" / "manager: Ravi" for the places that keep who changed something. */
    private function who(Request $request): string
    {
        return ($this->isAdmin($request) ? 'admin: ' : 'manager: ').$request->user()->name;
    }

    /** @return array{page: int, last_page: int, total: int, per_page: int} */
    private function meta($paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }
}
