<?php

namespace Tests\Feature;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Services\AttendeeRosterScraper;
use App\Support\EventData;
use App\Support\RosterRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Organizers, speakers, volunteers and microsponsors marked on the attendee
 * list: matched by Gravatar hash, else by a name that appears once; the
 * microsponsor block read off the Attendees page; sent with the roster API.
 */
class RosterRolesTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    private function event(string $slug = 'wc-test'): Event
    {
        return Event::create(['slug' => $slug, 'display_name' => 'WordCamp Test', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
    }

    private function entry(Event $event, string $name, ?string $hash = null, bool $micro = false): AttendeeRoster
    {
        return AttendeeRoster::create([
            'event_id' => $event->id, 'name' => $name, 'links' => [],
            'gravatar_url' => $hash ? "https://secure.gravatar.com/avatar/{$hash}?s=96&d=mm&r=g" : null,
            'content_hash' => hash('sha256', $name.$hash), 'is_suppressed' => false, 'is_microsponsor' => $micro,
        ]);
    }

    private function li(string $first, string $last): string
    {
        return '<li><img class="avatar" src="https://secure.gravatar.com/avatar/'.md5($first).'"><div class="tix-field tix-attendee-name"><span class="tix-first">'.$first.'</span> <span class="tix-last">'.$last.'</span></div></li>';
    }

    public function test_the_scraper_marks_a_microsponsor_block_by_its_class_or_heading(): void
    {
        $scraper = new AttendeeRosterScraper;

        $byClass = '<div class="wp-block-group microsponsor"><div id="tix-attendees"><ul class="tix-attendee-list">'.$this->li('Mia', 'Sponsor').'</ul></div></div>'
            .'<div class="wp-block-group"><div id="tix-attendees"><ul class="tix-attendee-list">'.$this->li('Ada', 'Lovelace').'</ul></div></div>';
        $this->assertSame(['Mia Sponsor' => true, 'Ada Lovelace' => false], collect($scraper->parse($byClass))->pluck('microsponsor', 'name')->all());

        $byHeading = '<section><h2>Our Micro-Sponsors</h2><ul class="tix-attendee-list">'.$this->li('Mia', 'Sponsor').'</ul></section>'
            .'<section><h2>Attendees</h2><ul class="tix-attendee-list">'.$this->li('Ada', 'Lovelace').'</ul></section>';
        $this->assertSame(['Mia Sponsor' => true, 'Ada Lovelace' => false], collect($scraper->parse($byHeading))->pluck('microsponsor', 'name')->all());

        $plain = '<ul class="tix-attendee-list">'.$this->li('Ada', 'Lovelace').$this->li('Alan', 'Turing').'</ul>';
        $this->assertSame([false, false], collect($scraper->parse($plain))->pluck('microsponsor')->all(), 'a plain CampTix page marks nobody');
    }

    public function test_the_roster_job_stores_the_microsponsor_mark(): void
    {
        $event = $this->event();
        Http::fake([self::SITE.'/attendees/' => Http::response(
            '<div class="microsponsor"><ul class="tix-attendee-list">'.$this->li('Mia', 'Sponsor').'</ul></div><div><ul class="tix-attendee-list">'.$this->li('Ada', 'Lovelace').'</ul></div>'
        )]);

        ParseAttendeeRosterJob::dispatchSync($event);

        $this->assertSame(['Ada Lovelace' => false, 'Mia Sponsor' => true], AttendeeRoster::orderBy('name')->pluck('is_microsponsor', 'name')->all());
    }

    public function test_roles_match_by_gravatar_first_then_by_a_name_that_appears_once(): void
    {
        $event = $this->event();
        $org = $this->entry($event, 'Mitali M', 'aaa111aaa111aaa111aaa111aaa111aa');
        $speaker = $this->entry($event, 'Rahul Speaker', 'bbb222bbb222bbb222bbb222bbb222bb');
        $volunteer = $this->entry($event, 'Pooja Srivastava');
        $twinA = $this->entry($event, 'Same Name', 'ccc333ccc333ccc333ccc333ccc333cc');
        $twinB = $this->entry($event, 'Same Name', 'ddd444ddd444ddd444ddd444ddd444dd');
        $micro = $this->entry($event, 'Mia Sponsor', null, true);
        $plain = $this->entry($event, 'Just Attending');

        EventData::put($event->id, 'organizers', [['id' => 1, 'name' => 'Mitali (Design team)', 'avatar_url' => 'https://secure.gravatar.com/avatar/aaa111aaa111aaa111aaa111aaa111aa?s=96']]);
        EventData::put($event->id, 'speakers', [
            ['id' => 7, 'name' => 'Rahul S.', 'avatar_url' => 'https://secure.gravatar.com/avatar/bbb222bbb222bbb222bbb222bbb222bb?s=96'],
            ['id' => 8, 'name' => 'Same Name', 'avatar_url' => 'https://secure.gravatar.com/avatar/eee555eee555eee555eee555eee555ee?s=96'],
        ]);
        EventData::put($event->id, 'sessions', [['id' => 50, 'title' => 'Blocks for everyone', 'speaker_ids' => [7], 'starts_at' => '2026-10-03T05:00:00Z']]);
        EventData::put($event->id, 'volunteers', [['id' => 9, 'name' => '  pooja   SRIVASTAVA ']]);

        $roles = RosterRoles::forEvent($event);

        $this->assertSame(['organizer'], $roles[$org->id]['roles'], 'same picture, different spelling of the name');
        $this->assertSame(['speaker'], $roles[$speaker->id]['roles']);
        $this->assertSame(['Blocks for everyone'], $roles[$speaker->id]['talks']);
        $this->assertSame(['volunteer'], $roles[$volunteer->id]['roles'], 'name match ignores case and spaces');
        $this->assertSame(['microsponsor'], $roles[$micro->id]['roles']);
        $this->assertArrayNotHasKey($twinA->id, $roles, 'a name on the list twice is never guessed');
        $this->assertArrayNotHasKey($twinB->id, $roles);
        $this->assertArrayNotHasKey($plain->id, $roles);
    }

    public function test_a_name_spelled_a_little_differently_matches_when_it_is_one_person_on_the_list(): void
    {
        $event = $this->event();
        $stephane = $this->entry($event, 'Stephane Boisvert', 'aaa111aaa111aaa111aaa111aaa111aa');
        $priyanka = $this->entry($event, 'Priyanka Sida');
        $mahmud = $this->entry($event, 'Md. Mahmudul Hasan');
        $ticketA = $this->entry($event, 'Mayank Kumar', 'ccc333ccc333ccc333ccc333ccc333cc');
        $ticketB = AttendeeRoster::create([
            'event_id' => $event->id, 'name' => 'Mayank Kumar', 'links' => [], 'content_hash' => 'second-ticket', 'is_suppressed' => false,
            'gravatar_url' => 'https://secure.gravatar.com/avatar/ccc333ccc333ccc333ccc333ccc333cc?s=96&d=mm&r=g',
        ]);
        $arunA = $this->entry($event, 'Arun R Shenoy', 'ddd444ddd444ddd444ddd444ddd444dd');
        $arunB = $this->entry($event, 'Arun S. Shenoy', 'eee555eee555eee555eee555eee555ee');

        $other = 'https://secure.gravatar.com/avatar/fff666fff666fff666fff666fff666ff?s=96';
        EventData::put($event->id, 'organizers', [['id' => 1, 'name' => 'Mahmudul Hasan', 'gravatar_url' => $other]]);
        EventData::put($event->id, 'speakers', [
            ['id' => 7, 'name' => 'Stéphane Boisvert', 'gravatar_url' => $other],
            ['id' => 8, 'name' => 'Priyanka P Sida'],
            ['id' => 9, 'name' => 'Mayank Kumar', 'gravatar_url' => $other],
            ['id' => 10, 'name' => 'Arun Shenoy', 'gravatar_url' => $other],
        ]);

        $roles = RosterRoles::forEvent($event);

        $this->assertSame(['speaker'], $roles[$stephane->id]['roles'], 'accents off');
        $this->assertSame(['speaker'], $roles[$priyanka->id]['roles'], 'a middle initial dropped');
        $this->assertSame(['organizer'], $roles[$mahmud->id]['roles'], 'a title dropped');
        $this->assertSame(['speaker'], $roles[$ticketA->id]['roles'], 'two tickets with one Gravatar are one person');
        $this->assertSame(['speaker'], $roles[$ticketB->id]['roles']);
        $this->assertArrayNotHasKey($arunA->id, $roles, 'two people the name could be: never guessed');
        $this->assertArrayNotHasKey($arunB->id, $roles);
        $this->assertSame(['Arun Shenoy'], array_map(fn ($u) => $u['person']['name'], RosterRoles::unlisted($event)));
    }

    public function test_site_people_not_on_the_list_are_kept_apart_except_who_left_the_list(): void
    {
        $event = $this->event();
        $this->entry($event, 'Asha Rao', 'aaa111aaa111aaa111aaa111aaa111aa');
        $left = $this->entry($event, 'Left Person', 'bbb222bbb222bbb222bbb222bbb222bb');
        $left->suppress();

        EventData::put($event->id, 'organizers', [
            ['id' => 1, 'name' => 'Asha Rao', 'gravatar_url' => 'https://secure.gravatar.com/avatar/aaa111aaa111aaa111aaa111aaa111aa?s=96'],
            ['id' => 2, 'name' => 'Troy Chaplin', 'gravatar_url' => 'https://secure.gravatar.com/avatar/ccc333ccc333ccc333ccc333ccc333cc?s=96'],
            ['id' => 3, 'name' => 'Left Person', 'gravatar_url' => 'https://secure.gravatar.com/avatar/ddd444ddd444ddd444ddd444ddd444dd?s=96'],
        ]);
        EventData::put($event->id, 'speakers', [['id' => 7, 'name' => 'Robbie Adair']]);
        EventData::put($event->id, 'sessions', [['id' => 50, 'title' => 'Beyond Launch', 'speaker_ids' => [7]]]);
        EventData::put($event->id, 'volunteers', [['id' => 9, 'name' => 'Joel Lobo']]);

        $unlisted = collect(RosterRoles::unlisted($event))->map(fn ($u) => [$u['role'], $u['person']['name'], $u['talks']])->all();

        $this->assertSame([
            ['organizer', 'Troy Chaplin', []],
            ['speaker', 'Robbie Adair', ['Beyond Launch']],
            ['volunteer', 'Joel Lobo', []],
        ], $unlisted, 'the one on the list is not here, nor the one who asked to be taken off it (by name)');
    }

    public function test_the_roster_api_sends_roles_and_talks(): void
    {
        $event = $this->event();
        $this->entry($event, 'Rahul Speaker', 'bbb222bbb222bbb222bbb222bbb222bb');
        $this->entry($event, 'Just Attending');
        EventData::put($event->id, 'speakers', [['id' => 7, 'name' => 'Rahul', 'avatar_url' => 'https://secure.gravatar.com/avatar/bbb222bbb222bbb222bbb222bbb222bb']]);
        EventData::put($event->id, 'sessions', [['id' => 50, 'title' => 'Blocks', 'speaker_ids' => [7]]]);

        $data = collect($this->getJson('/api/v1/events/wc-test/roster')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(['speaker'], $data['Rahul Speaker']['roles']);
        $this->assertSame(['Blocks'], $data['Rahul Speaker']['talks']);
        $this->assertSame([], $data['Just Attending']['roles']);
    }

    public function test_volunteers_are_fetched_and_a_site_without_them_is_not_a_problem(): void
    {
        $json = fn ($body) => Http::response($body, 200, ['X-WP-TotalPages' => 1]);
        $volunteers = $json([['id' => 9, 'title' => ['rendered' => 'Pooja &amp; Co']]]);
        Http::fake(function ($request) use ($json, &$volunteers) {
            $url = $request->url();
            if (str_contains($url, '/wp/v2/wcb_volunteer')) {
                return $volunteers;
            }

            return str_contains($url, '/wp/v2/') ? $json([]) : $json(['timezone_string' => 'Asia/Kolkata']);
        });

        $event = $this->event();
        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);
        $this->assertSame([['id' => 9, 'name' => 'Pooja & Co']], EventData::get($event->id, 'volunteers'));

        $other = $this->event('wc-old');
        $volunteers = Http::response(['code' => 'rest_no_route'], 404);
        FetchSpeakersSponsorsSessionsJob::dispatchSync($other);
        $log = FetchLog::where('event_id', $other->id)->latest('id')->first();
        $this->assertSame('ok', $log->status);
        $this->assertStringNotContainsString('volunteer', $log->message);
    }
}
