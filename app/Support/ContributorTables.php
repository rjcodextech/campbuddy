<?php

namespace App\Support;

use App\Http\Controllers\Api\RosterController as RosterApi;
use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\Event;
use Illuminate\Validation\Rule;

/**
 * Adding, changing and removing Contributor Day tables — the same for the
 * admin and an event's managers (Admin\ and Manager\ContributorTableController).
 */
class ContributorTables
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'team' => ['required', 'string', Rule::in(array_keys(config('contributor_teams')))],
            'title' => ['nullable', 'string', 'max:80', 'required_if:team,other'],
            'track' => ['nullable', 'string', 'max:80'],
            'floor' => ['nullable', 'string', 'max:40'],
            'table_no' => ['nullable', 'string', 'max:20'],
            'leads' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * The form's fields as stored. Leads are typed comma-separated (the form
     * suggests names from the attendee list); a name that is on the list
     * exactly once keeps that entry's id, so it shows as a Table Lead.
     *
     * @param  array<string, mixed>  $input  validated
     * @return array<string, mixed>
     */
    public static function fields(Event $event, array $input): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            fn ($name) => trim((string) preg_replace('/\s+/u', ' ', $name)),
            explode(',', (string) ($input['leads'] ?? ''))
        ))));

        $onList = AttendeeRoster::where('event_id', $event->id)->where('is_suppressed', false)->get(['id', 'name'])
            ->groupBy(fn ($entry) => RosterRoles::nameKey($entry->name));

        $leads = array_map(function (string $name) use ($onList) {
            $matches = $onList->get(RosterRoles::nameKey($name));

            return $matches && $matches->count() === 1
                ? ['name' => $matches->first()->name, 'roster_id' => $matches->first()->id]
                : ['name' => mb_substr($name, 0, 80)];
        }, array_slice($names, 0, 8));

        return [
            'team' => $input['team'],
            'title' => $input['title'] ?? null,
            'track' => $input['track'] ?? null,
            'floor' => $input['floor'] ?? null,
            'table_no' => $input['table_no'] ?? null,
            'leads' => $leads,
            'note' => $input['note'] ?? null,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ];
    }

    /** After any change: the Contribute tab and the Table Lead badges follow. */
    public static function changed(Event $event): void
    {
        DataVersion::forget($event->id);
        RosterRoles::forget($event);
        RosterApi::forget($event);
    }

    /** "Polyglots (Floor 2 · Table 5)" — for the activity log. */
    public static function describe(ContributorTable $table): string
    {
        return $table->teamName().($table->place() !== '' ? ' ('.$table->place().')' : '');
    }
}
