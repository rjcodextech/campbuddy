<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * One Contributor Day table: its team (config/contributor_teams.php), where
 * it is, and who leads it. `leads` is a list of {name, roster_id?} — a lead
 * picked from the attendee list keeps that entry's id, so the list can show
 * them as a Table Lead.
 */
class ContributorTable extends Model
{
    protected $fillable = ['event_id', 'team', 'title', 'track', 'floor', 'table_no', 'leads', 'note', 'sort_order'];

    protected $casts = [
        'leads' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** "Polyglots", or the table's own title for "Other". */
    public function teamName(): string
    {
        $name = config('contributor_teams')[$this->team] ?? $this->team;

        return $this->team === 'other' && $this->title ? $this->title : ($this->title ? "{$name}: {$this->title}" : $name);
    }

    /** "Floor 2 · Hall B · Table 5" — whatever is filled in. */
    public function place(): string
    {
        return implode(' · ', array_filter([
            $this->floor ? 'Floor '.$this->floor : null,
            $this->track,
            $this->table_no ? 'Table '.$this->table_no : null,
        ]));
    }

    /** @return list<string> */
    public function leadNames(): array
    {
        return array_values(array_filter(array_map(fn ($lead) => $lead['name'] ?? null, $this->leads ?? [])));
    }

    /**
     * What the attendee app's Contribute tab shows.
     *
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'team' => $this->team,
            'name' => $this->teamName(),
            'place' => $this->place(),
            'leads' => $this->leadNames(),
            'note' => $this->note,
        ];
    }

    /** @return iterable<int, self> this event's tables, in order — none before the migration has run */
    public static function forEvent(Event $event): iterable
    {
        try {
            return self::where('event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * Leads of this event's tables as RosterRoles needs them: attendee-list
     * ids of leads picked from the list, and names of leads typed in.
     *
     * @return array{ids: list<int>, names: list<string>}
     */
    public static function leadsOf(Event $event): array
    {
        $ids = [];
        $names = [];
        foreach (self::forEvent($event) as $table) {
            foreach ($table->leads ?? [] as $lead) {
                if (! empty($lead['roster_id'])) {
                    $ids[] = (int) $lead['roster_id'];
                } elseif (! empty($lead['name'])) {
                    $names[] = $lead['name'];
                }
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'names' => array_values(array_unique($names))];
    }
}
