<?php

namespace App\Support;

use App\Http\Controllers\Api\RosterController as RosterApi;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\RosterMark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Roles set by hand on the attendee list, by an admin or the event's managers.
 *
 * The page shows each person's roles as found automatically (RosterRoles) and
 * lets them be ticked or unticked. Only the difference is stored: a ticked
 * role that wasn't found is an "add", an unticked one that was is a "remove";
 * agreeing with the automatic answer stores nothing. Each is kept by the
 * person's Gravatar hash (else their name), so it outlives the list row.
 */
class RosterMarks
{
    /** @return Collection<int, RosterMark> */
    public static function forEvent(Event $event): Collection
    {
        try {
            return RosterMark::where('event_id', $event->id)->get(['person_key', 'role', 'action']);
        } catch (Throwable) {
            // Before `php artisan migrate`: no hand marks yet.
            return collect();
        }
    }

    /** What a hand mark is kept by: the Gravatar hash, else the name. */
    public static function keyFor(AttendeeRoster $entry): string
    {
        $hash = RosterRoles::avatarHash($entry->gravatar_url);

        return $hash !== null ? "g:{$hash}" : 'n:'.mb_substr(RosterRoles::nameKey($entry->name), 0, 96);
    }

    /**
     * Sets this person's roles to exactly $wanted (any of RosterRoles::ROLES).
     *
     * @param  list<string>  $wanted
     * @return array{added: list<string>, removed: list<string>} what changed from before
     */
    public static function set(Event $event, AttendeeRoster $entry, array $wanted, string $by): array
    {
        $wanted = array_values(array_intersect(RosterRoles::ROLES, $wanted));
        $now = RosterRoles::withAutomatic($event);
        $before = $now['marked'][$entry->id]['roles'] ?? [];
        $auto = $now['auto'][$entry->id]['roles'] ?? [];
        $key = self::keyFor($entry);

        foreach (RosterRoles::ROLES as $role) {
            $on = in_array($role, $wanted, true);
            $isAuto = in_array($role, $auto, true);

            if ($on === $isAuto) {
                RosterMark::where(['event_id' => $event->id, 'person_key' => $key, 'role' => $role])->delete();

                continue;
            }

            RosterMark::updateOrCreate(
                ['event_id' => $event->id, 'person_key' => $key, 'role' => $role],
                ['action' => $on ? 'add' : 'remove', 'set_by' => mb_substr($by, 0, 120)]
            );
        }

        RosterRoles::forget($event);
        RosterApi::forget($event);

        return [
            'added' => array_values(array_diff($wanted, $before)),
            'removed' => array_values(array_diff($before, $wanted)),
        ];
    }

    public static function available(): bool
    {
        try {
            return Schema::hasTable('roster_marks');
        } catch (Throwable) {
            return false;
        }
    }
}
