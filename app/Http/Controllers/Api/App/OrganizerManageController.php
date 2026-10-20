<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Api\RosterController as RosterApi;
use App\Http\Controllers\Controller;
use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Models\EventManagerChange;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\SharedCampCard;
use App\Rules\NotPrivateNetworkUrl;
use App\Rules\WordCampUrl;
use App\Support\ContributorTables;
use App\Support\DataVersion;
use App\Support\EventData;
use App\Support\EventEdits;
use App\Support\EventListing;
use App\Support\EventNotifications;
use App\Support\EventTime;
use App\Support\ManagerActivity;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The rest of an event's pages for the native app's organizer side — what the
 * web panel's event tabs do (details, information, quests, Contributor Day
 * tables, notifications, deals, deal leads, activity, the fetch buttons),
 * with the same rules: a manager only reaches their own events and never the
 * lifecycle status; deals, leads, the attendee takedowns and the fetch
 * buttons are the admin's. Every manager change is written to the activity log.
 */
class OrganizerManageController extends Controller
{
    use ResolvesAppOrganizer;

    // ---- event details -------------------------------------------------------------------------

    public function details(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);

        return response()->json(['data' => $this->detailsData($request, $event)]);
    }

    public function updateDetails(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $admin = $this->isAdmin($request);
        $plain = ['not_regex:/[\x00-\x1F\x7F<>\[\]]/'];

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', Rule::unique('events', 'slug')->ignore($event->id)],
            'display_name' => ['required', 'string', 'max:255', ...($admin ? [] : $plain)],
            'short_name' => ['nullable', 'string', 'max:60', ...($admin ? [] : $plain)],
            // A manager may only point an event at a wordcamp.org site, as on the web panel.
            'source_site_url' => ['required', 'url:http,https', 'max:500', ...($admin ? [] : [new WordCampUrl($event->source_site_url)]), new NotPrivateNetworkUrl],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'timezone' => ['nullable', 'string', 'max:64', function (string $attribute, mixed $value, Closure $fail) {
                if (EventTime::normalize((string) $value) === null) {
                    $fail('Use a time zone like Asia/Kolkata or Europe/Berlin, or an offset like +05:30.');
                }
            }],
            'is_visible' => ['boolean'],
            // The lifecycle status is the admin's alone: a manager's request never carries it.
            ...($admin ? ['status' => ['sometimes', Rule::in(EventListing::STATUSES)]] : []),
        ], [
            'display_name.not_regex' => 'Use plain text in the display name: one line, without < > [ ] characters.',
            'short_name.not_regex' => 'Use plain text in the short name: one line, without < > [ ] characters.',
        ]);

        $zoneBefore = $event->timezone;
        $before = ManagerActivity::detailsSnapshot($event);

        $event->update(EventEdits::details($data, $event));

        if (! $admin && ($change = ManagerActivity::detailsChange($before, ManagerActivity::detailsSnapshot($event)))) {
            $this->record($request, $event, 'details', 'updated', $change);
        }

        // Session times depend on the zone: re-read them in the new one (at most once in five minutes for a manager).
        if ($event->timezone !== $zoneBefore && $event->status === 'active' && ($admin || Cache::add("manager-refetch:{$event->id}", 1, now()->addMinutes(5)))) {
            try {
                FetchSpeakersSponsorsSessionsJob::dispatch($event);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json(['data' => $this->detailsData($request, $event->fresh()), 'message' => 'Event details saved.']);
    }

    /** Deleting a whole event is the admin's, and the app asks for the slug to be typed first. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $request->validate(['confirm' => ['required', 'string', Rule::in([$event->slug])]], ['confirm.in' => 'Type the event slug exactly to delete it.']);

        $event->delete();

        return response()->json(['message' => "Event \"{$event->display_name}\" deleted."]);
    }

    /** Refresh now / Fetch latest information / Re-fetch branding: run right away and say what happened. */
    public function fetch(Request $request, int $id, string $job): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);

        [$type, $run, $success, $failure] = match ($job) {
            'schedule' => ['sessions_speakers_sponsors', fn () => FetchSpeakersSponsorsSessionsJob::dispatchSync($event), 'Refreshed', 'Couldn\'t refresh sessions, speakers and sponsors'],
            'info' => ['event_info', fn () => FetchEventInfoJob::dispatchSync($event), 'Event information updated', 'Couldn\'t fetch event information'],
            'branding' => ['branding', fn () => FetchBrandingAssetsJob::dispatchSync($event), 'Branding re-fetched', 'Couldn\'t re-fetch branding'],
        };

        @set_time_limit(120);
        $startedAt = now()->subSecond();

        try {
            $run();
        } catch (Throwable) {
            // Already written to the fetch log by the job itself.
        }

        $log = $event->fetchLogs()->where('job_type', $type)->where('fetched_at', '>=', $startedAt)->latest('fetched_at')->latest('id')->first();

        [$level, $message] = match ($log?->status) {
            'ok' => ['ok', "{$success} — {$log->message}"],
            'partial' => ['warning', "{$success}, with problems — {$log->message}"],
            null => ['error', "{$failure} — nothing was recorded. Check the application log for details."],
            default => ['error', "{$failure} — {$log->message}"],
        };

        return response()->json(['data' => ['level' => $level, 'message' => $message]]);
    }

    // ---- event information (all fields) --------------------------------------------------------

    public function information(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);

        return response()->json(['data' => $this->infoData($event)]);
    }

    /** The whole form, as on the web panel: a blank field is removed. */
    public function updateInformation(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $fields = $request->validate([
            'venue' => ['nullable', 'string', 'max:255'],
            'important_links' => ['nullable', 'string', 'max:2000'],
            'wifi' => ['nullable', 'string', 'max:500'],
            'social_event_info' => ['nullable', 'string', 'max:1000'],
            'registration_info' => ['nullable', 'string', 'max:1000'],
            'contributor_day_location' => ['nullable', 'string', 'max:255'],
            'code_of_conduct_url' => ['nullable', 'url:http,https', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:500'],
            'nearby_venue_info' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $event->info ?? [];
        $event->update(['info' => EventEdits::info($fields)]);

        if ($change = ManagerActivity::infoChange($before, $event->info ?? [])) {
            $this->record($request, $event, 'information', 'updated', $change);
        }

        return response()->json(['data' => $this->infoData($event->fresh()), 'message' => 'Event information saved.']);
    }

    // ---- quests & checklist --------------------------------------------------------------------

    public function quests(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);

        return response()->json(['data' => $event->quests()->orderBy('sort_order')->orderBy('id')->get()->map(fn ($q) => $this->questData($q))->values()]);
    }

    public function storeQuest(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $data = $request->validate($this->questRules());
        $data['sort_order'] ??= ($event->quests()->where('source', 'event')->max('sort_order') ?? 0) + 10;

        $quest = $event->quests()->create($data + ['source' => 'event']);
        $this->record($request, $event, 'quests', 'added', 'Added '.ManagerActivity::quote($quest->title));
        DataVersion::forget($event->id);

        return response()->json(['data' => $this->questData($quest), 'message' => 'Item added.'], 201);
    }

    public function updateQuest(Request $request, int $id, int $itemId): JsonResponse
    {
        $event = $this->event($request, $id);
        $quest = $event->quests()->findOrFail($itemId);
        $before = $quest->replicate();

        // An emptied order box keeps the item where it is.
        $quest->update(array_filter($request->validate($this->questRules(partial: true)), fn ($v, $k) => $k !== 'sort_order' || $v !== null, ARRAY_FILTER_USE_BOTH));

        if ($change = ManagerActivity::questChange($before, $quest)) {
            $this->record($request, $event, 'quests', 'updated', $change);
            DataVersion::forget($event->id);
        }

        return response()->json(['data' => $this->questData($quest), 'message' => 'Item updated.']);
    }

    public function destroyQuest(Request $request, int $id, int $itemId): JsonResponse
    {
        $event = $this->event($request, $id);
        $quest = $event->quests()->findOrFail($itemId);
        $quest->delete();

        $this->record($request, $event, 'quests', 'removed', 'Removed '.ManagerActivity::quote($quest->title));
        DataVersion::forget($event->id);

        return response()->json(['message' => 'Item removed.']);
    }

    // ---- Contributor Day tables ----------------------------------------------------------------

    public function tables(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);

        return response()->json(['data' => [
            'tables' => collect(ContributorTable::forEvent($event))->map(fn (ContributorTable $t) => $this->tableData($t))->values(),
            'teams' => collect(config('contributor_teams'))->map(fn ($name, $key) => ['key' => $key, 'name' => $name])->values(),
            // Names to suggest as table leads (the web form's datalist).
            'names' => $event->attendeeRoster()->where('is_suppressed', false)->orderBy('name')->limit(3000)->pluck('name')->unique()->values(),
        ]]);
    }

    public function storeTable(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $table = ContributorTable::create(['event_id' => $event->id] + ContributorTables::fields($event, $request->validate(ContributorTables::rules())));
        ContributorTables::changed($event);
        $this->record($request, $event, 'tables', 'added', 'Added table '.ManagerActivity::quote(ContributorTables::describe($table)));

        return response()->json(['data' => $this->tableData($table), 'message' => ContributorTables::describe($table).' added.'], 201);
    }

    public function updateTable(Request $request, int $id, int $itemId): JsonResponse
    {
        $event = $this->event($request, $id);
        $table = ContributorTable::where('event_id', $event->id)->findOrFail($itemId);
        $table->update(ContributorTables::fields($event, $request->validate(ContributorTables::rules())));
        ContributorTables::changed($event);
        $this->record($request, $event, 'tables', 'updated', 'Changed table '.ManagerActivity::quote(ContributorTables::describe($table)));

        return response()->json(['data' => $this->tableData($table), 'message' => ContributorTables::describe($table).' saved.']);
    }

    public function destroyTable(Request $request, int $id, int $itemId): JsonResponse
    {
        $event = $this->event($request, $id);
        $table = ContributorTable::where('event_id', $event->id)->findOrFail($itemId);
        $table->delete();
        ContributorTables::changed($event);
        $this->record($request, $event, 'tables', 'removed', 'Removed table '.ManagerActivity::quote(ContributorTables::describe($table)));

        return response()->json(['message' => ContributorTables::describe($table).' removed.']);
    }

    // ---- attendee takedowns (admin) ------------------------------------------------------------

    public function attendeeAction(Request $request, int $id, int $entryId, string $action): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $entry = AttendeeRoster::where('event_id', $event->id)->findOrFail($entryId);

        // The same four as the admin's attendee page (Admin\RosterController).
        if ($action === 'suppress') {
            $entry->suppress();
            $message = "{$entry->name} suppressed from the public roster.";
        } elseif ($action === 'unsuppress') {
            $entry->update(['is_suppressed' => false]);
            $message = "{$entry->name} restored to the public roster.";
        } elseif ($action === 'release-claim') {
            DiscoveryProfile::where('attendee_roster_id', $entry->id)->update(['attendee_roster_id' => null]);
            $message = "{$entry->name} is no longer linked to a discovery profile — they can now pick their own name.";
        } else {
            SharedCampCard::where('attendee_roster_id', $entry->id)->delete();
            RosterApi::forget($event);
            $message = "{$entry->name}'s Camp Card was taken off the attendee list.";
        }

        return response()->json(['message' => $message]);
    }

    // ---- notifications, activity ---------------------------------------------------------------

    public function notifications(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => EventNotifications::rows($this->event($request, $id))]);
    }

    /** What managers changed on this event, newest first (the admin's activity page, for one event). */
    public function activity(Request $request, int $id): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);

        if (! Schema::hasTable('event_manager_changes')) {
            return response()->json(['data' => [], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 30]]);
        }

        $changes = EventManagerChange::where('event_id', $event->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(30);

        return response()->json(['data' => collect($changes->items())->map(fn ($c) => [
            'id' => $c->id, 'manager' => $c->manager_name, 'event' => $event->display_name, 'section' => $c->section,
            'action' => $c->action, 'summary' => $c->summary, 'at' => $c->created_at?->toIso8601String(),
        ])->values(), 'meta' => $this->meta($changes)]);
    }

    // ---- deals & leads (admin) -----------------------------------------------------------------

    public function deals(Request $request, int $id): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $leads = OfferLead::where('event_id', $event->id)->selectRaw('offer_id, count(*) as n')->groupBy('offer_id')->pluck('n', 'offer_id');

        return response()->json(['data' => [
            'own' => $event->offers()->orderBy('sort_order')->orderBy('id')->get()->map(fn (Offer $o) => $this->dealData($o, (int) ($leads[$o->id] ?? 0)))->values(),
            'defaults' => Offer::defaultsFor($event)->map(fn (Offer $o) => $this->dealData($o, (int) ($leads[$o->id] ?? 0)) + [
                'hidden_here' => (bool) $o->hidden_here,
                'countries' => $o->countriesLabel(),
            ])->values(),
        ]]);
    }

    public function storeDeal(Request $request, int $id): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $data = $request->validate($this->dealRules());
        if (($data['sort_order'] ?? null) === null) {
            $data['sort_order'] = ($event->offers()->max('sort_order') ?? 0) + 10;
        }

        $offer = $event->offers()->create($data);
        DataVersion::forget($event->id);

        return response()->json(['data' => $this->dealData($offer, 0), 'message' => 'Deal added.'], 201);
    }

    public function updateDeal(Request $request, int $id, int $itemId): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $offer = $event->offers()->findOrFail($itemId);
        $data = $request->validate($this->dealRules());
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            unset($data['sort_order']);
        }

        $offer->update($data);
        DataVersion::forget($event->id);

        return response()->json(['data' => $this->dealData($offer, $offer->leads()->where('event_id', $event->id)->count()), 'message' => 'Deal updated.']);
    }

    public function destroyDeal(Request $request, int $id, int $itemId): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $event->offers()->findOrFail($itemId)->delete();
        DataVersion::forget($event->id);

        return response()->json(['message' => 'Deal removed.']);
    }

    /** Hides a default deal at this event (or shows it again). */
    public function dealVisibility(Request $request, int $id, int $itemId): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $offer = Offer::defaults()->findOrFail($itemId);
        abort_unless($offer->coversCountryOf($event), 404);
        $hidden = $request->boolean('hidden');

        $hidden ? $offer->hiddenAtEvents()->syncWithoutDetaching([$event->id]) : $offer->hiddenAtEvents()->detach($event->id);
        DataVersion::forget($event->id);

        return response()->json(['message' => '“'.$offer->displayName().'” is '.($hidden ? 'hidden at this event.' : 'shown at this event again.')]);
    }

    public function leads(Request $request, int $id): JsonResponse
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $request->validate(['offer_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $leads = OfferLead::where('event_id', $event->id)
            ->with('offer')
            ->when($request->filled('offer_id'), fn ($q) => $q->where('offer_id', $request->integer('offer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(50);

        $offers = $event->offers()->get()->concat(Offer::defaultsFor($event))
            ->sortBy(fn (Offer $o) => mb_strtolower($o->displayName()))
            ->map(fn (Offer $o) => ['id' => $o->id, 'name' => $o->displayName()])->values();

        return response()->json(['data' => collect($leads->items())->map(fn (OfferLead $l) => [
            'id' => $l->id,
            'deal' => $l->offer?->displayName(),
            'name' => $l->name,
            'company' => $l->company,
            'email' => $l->email,
            'mobile' => $l->mobile,
            'choices' => array_values((array) ($l->choices ?? [])),
            'at' => $l->created_at?->toIso8601String(),
        ])->values(), 'offers' => $offers, 'meta' => $this->meta($leads)]);
    }

    /** The same leads as a CSV file (the web page's Export, with the same filters). */
    public function exportLeads(Request $request, int $id)
    {
        $this->adminOnly($request);
        $event = $this->event($request, $id);
        $request->validate(['offer_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $leads = OfferLead::where('event_id', $event->id)
            ->with(['offer', 'event:id,display_name'])
            ->when($request->filled('offer_id'), fn ($q) => $q->where('offer_id', $request->integer('offer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->get();

        return \App\Support\DealLeadCsv::download($leads, 'deal-leads-'.$event->slug.'-'.now()->format('Y-m-d').'.csv');
    }

    // ---- shapes ------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function detailsData(Request $request, Event $event): array
    {
        $data = [
            'id' => $event->id,
            'slug' => $event->slug,
            'display_name' => $event->display_name,
            'short_name' => $event->short_name,
            'source_site_url' => $event->source_site_url,
            'status' => $event->status,
            'is_visible' => (bool) $event->is_visible,
            'starts_on' => $event->starts_on?->toDateString(),
            'ends_on' => $event->ends_on?->toDateString(),
            'timezone' => $event->timezone_locked ? $event->timezone : null,
            'timezone_used' => EventTime::zone($event)->getName(),
            'timezone_locked' => (bool) $event->timezone_locked,
            'dates_locked' => (bool) $event->dates_locked,
            'timing' => EventListing::timing($event),
            'public_url' => url('/event/'.$event->slug),
        ];

        if (! $this->isAdmin($request)) {
            return $data;
        }

        $labels = ['sessions_speakers_sponsors' => 'Schedule', 'roster' => 'Attendee list', 'event_info' => 'Event information', 'branding' => 'Branding'];

        return $data + [
            'data_counts' => [
                'sessions' => EventData::count($event->id, 'sessions'),
                'speakers' => EventData::count($event->id, 'speakers'),
                'sponsors' => EventData::count($event->id, 'sponsors'),
                'organizers' => EventData::count($event->id, 'organizers'),
                'attendees' => $event->attendeeRoster()->where('is_suppressed', false)->count(),
            ],
            'recent_fetches' => $event->fetchLogs()->latest('fetched_at')->latest('id')->limit(10)->get()->map(fn ($log) => [
                'job' => $labels[$log->job_type] ?? $log->job_type,
                'status' => $log->status,
                'message' => $log->message,
                'at' => $log->fetched_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** @return array<string, ?string> */
    private function infoData(Event $event): array
    {
        $info = $event->info ?? [];

        return collect(['venue', 'wifi', 'registration_info', 'emergency_contact', 'contributor_day_location', 'social_event_info', 'nearby_venue_info', 'important_links', 'code_of_conduct_url'])
            ->mapWithKeys(fn ($k) => [$k => $info[$k] ?? null])->all();
    }

    /** @return array<string, mixed> */
    private function questRules(bool $partial = false): array
    {
        return [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function questData($quest): array
    {
        return [
            'id' => $quest->id, 'title' => $quest->title, 'description' => $quest->description,
            'sort_order' => (int) $quest->sort_order, 'is_active' => (bool) $quest->is_active, 'source' => $quest->source,
        ];
    }

    /** @return array<string, mixed> */
    private function tableData(ContributorTable $t): array
    {
        return [
            'id' => $t->id, 'team' => $t->team, 'title' => $t->title, 'track' => $t->track, 'floor' => $t->floor,
            'table_no' => $t->table_no, 'leads' => implode(', ', $t->leadNames()), 'note' => $t->note,
            'sort_order' => (int) $t->sort_order, 'name' => $t->teamName(), 'place' => $t->place(),
        ];
    }

    /** The fields the app's deal form edits; the contact form's details and the logo stay as set on the web. */
    private function dealRules(): array
    {
        return [
            'brand' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'highlight' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'opens_in_app' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'capture_leads' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function dealData(Offer $o, int $leads): array
    {
        return [
            'id' => $o->id, 'name' => $o->displayName(), 'brand' => $o->brand, 'website' => $o->website, 'title' => $o->title,
            'highlight' => $o->highlight, 'description' => $o->description, 'terms' => $o->terms, 'coupon_code' => $o->coupon_code,
            'url' => $o->url, 'cta_label' => $o->cta_label, 'opens_in_app' => (bool) $o->opens_in_app, 'icon' => $o->icon,
            'sort_order' => (int) $o->sort_order, 'is_active' => (bool) $o->is_active, 'capture_leads' => (bool) $o->capture_leads,
            'has_logo' => $o->media_asset_id !== null, 'leads_count' => $leads,
        ];
    }
}
