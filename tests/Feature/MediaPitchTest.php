<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\MediaPitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Events → Media Partner Pitch: one source of words (App\Support\MediaPitch) for the page, the
 * copied email and the printable proposal; event details filled from the database; nothing it says that
 * CampBuddy doesn't do.
 */
class MediaPitchTest extends TestCase
{
    use RefreshDatabase;

    private function delhi(array $info = ['venue' => 'Dr. Ambedkar Intl. Centre, New Delhi']): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => 'wordcamp-delhi-2026',
            'display_name' => 'WordCamp Delhi 2026',
            'starts_on' => '2026-12-12',
            'ends_on' => '2026-12-13',
            'status' => 'active',
            'is_visible' => true,
            'source_site_url' => 'https://delhi.wordcamp.org/2026/',
            'info' => $info,
        ]));
    }

    public function test_admin_can_access_media_pitch_page(): void
    {
        $this->delhi();

        $this->actingAs(User::factory()->create())->get(route('admin.media-pitch.index'))
            ->assertOk()
            ->assertSee('WordCamp Delhi 2026')
            ->assertSee('CampBuddy is a free companion app for WordCamp attendees')
            ->assertSee('campbuddy-wpsimplified-logo-clear.svg')
            ->assertDontSee('open-source (GPL-2.0)');
    }

    public function test_the_event_list_is_filled_from_the_database(): void
    {
        // The bug this guards: the page asked for columns events doesn't have (city, venue_name…); the
        // error was swallowed and the event list was always empty, so nothing was ever auto-filled.
        $this->delhi();

        $this->actingAs(User::factory()->create())->get(route('admin.media-pitch.index'))
            ->assertOk()
            ->assertSee('<option value="wordcamp-delhi-2026">WordCamp Delhi 2026 (12–13 Dec 2026)</option>', false)
            ->assertViewHas('selected', fn (array $event) => $event === [
                'slug' => 'wordcamp-delhi-2026',
                'name' => 'WordCamp Delhi 2026',
                'dates' => '12–13 Dec 2026',
                'venue' => 'Dr. Ambedkar Intl. Centre, New Delhi',
                'email' => 'delhi@wordcamp.org',
                'link' => route('event.home', 'wordcamp-delhi-2026'),
            ]);
    }

    public function test_the_organizers_address_is_the_published_one_or_the_city_address_never_the_slug(): void
    {
        $this->assertSame('delhi@wordcamp.org', MediaPitch::organizerEmail($this->delhi()), 'from delhi.wordcamp.org');

        $event = Event::withoutEvents(fn () => Event::create(['slug' => 'wordcamp-asia-2027', 'display_name' => 'WordCamp Asia 2027', 'status' => 'active', 'is_visible' => true, 'source_site_url' => 'https://asia.wordcamp.org/2027', 'info' => ['emergency_contact' => 'Asia@WordCamp.org']]));
        $this->assertSame('asia@wordcamp.org', MediaPitch::organizerEmail($event), 'the published address wins');

        $hidden = Event::withoutEvents(fn () => Event::create(['slug' => 'wordcamp-x', 'display_name' => 'WordCamp X', 'status' => 'active', 'is_visible' => false, 'source_site_url' => 'https://example.com']));
        $this->assertSame('', MediaPitch::organizerEmail($hidden));
        $this->assertNull(MediaPitch::eventFields($hidden)['link'], 'no public link for an event attendees cannot open');
    }

    public function test_the_venue_is_its_name_not_the_street_address(): void
    {
        $event = $this->delhi(['venue' => 'PWCC - Penang Waterfront Convention Centre — 1B, The Light City, Penang']);

        $this->assertSame('PWCC - Penang Waterfront Convention Centre', MediaPitch::eventFields($event)['venue']);
    }

    public function test_the_words_claim_nothing_campbuddy_does_not_do(): void
    {
        $everything = MediaPitch::emailTemplate(10).json_encode(MediaPitch::bios());

        foreach (['collects no personal data', '100% offline', 'complete on-device privacy', 'premier', 'powerhouse', 'unmatched', 'dynamic LinkedIn QR'] as $claim) {
            $this->assertStringNotContainsStringIgnoringCase($claim, $everything, "\"{$claim}\" is not true or not credible");
        }
    }

    public function test_the_email_is_plain_text_that_reads_well_in_any_mail_app(): void
    {
        $email = MediaPitch::emailTemplate(10);

        $this->assertStringNotContainsString('###', $email);
        $this->assertDoesNotMatchRegularExpression('/^\* /m', $email, 'no Markdown bullets');
        $this->assertStringContainsString('WHAT WE ASK FOR', $email);
        $this->assertStringContainsString('It is ready for 10 upcoming WordCamps.', $email);
        $this->assertStringContainsString('{ready}', $email, 'the event line is filled in by the page');
        $this->assertStringContainsString(MediaPitch::bios()['joint']['short'], $email, 'the listing bio travels with the email');
    }

    public function test_every_bio_has_a_short_listing_version(): void
    {
        foreach (MediaPitch::bios() as $key => $bio) {
            $this->assertLessThanOrEqual(70, str_word_count($bio['short']), "{$key} short bio fits a listing");
            $this->assertGreaterThan(str_word_count($bio['short']), str_word_count($bio['full']), "{$key} full bio is the longer one");
        }
    }

    public function test_admin_can_access_print_proposal(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.media-pitch.print', [
            'event_name' => 'WordCamp Delhi 2026',
            'event_dates' => '12–13 Dec 2026',
            'event_venue' => 'Dr. Ambedkar International Centre',
            'event_email' => 'delhi@wordcamp.org',
            'event_link' => 'https://campbuddy.club/event/wordcamp-delhi-2026',
            'applicant_name' => 'Sunil Kumar Sharma',
        ]))
            ->assertOk()
            ->assertSee('WordCamp Delhi 2026 is already set up on CampBuddy, so you can try it now: https://campbuddy.club/event/wordcamp-delhi-2026')
            ->assertSee('Lead, WPSimplified &amp; CampBuddy', false)
            ->assertSee('CampBuddy × WPSimplified')
            ->assertSee('campbuddy-wpsimplified-logo.svg')
            ->assertDontSee('table-links')
            ->assertDontSee('open-source (GPL-2.0)');
    }

    public function test_the_print_page_never_links_a_script_address(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.media-pitch.print', ['event_link' => 'javascript:alert(1)']))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false)
            ->assertSee('We will set WordCamp Delhi 2026 up on CampBuddy');
    }

    public function test_an_edited_bio_is_printed_instead_of_the_default(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.media-pitch.print', ['sunil_bio' => 'A bio the admin wrote.']))
            ->assertOk()
            ->assertSee('A bio the admin wrote.')
            ->assertSee(MediaPitch::bios()['campbuddy']['short']);
    }

    public function test_the_logo_comes_with_the_guideline_clear_space(): void
    {
        foreach (['campbuddy-wpsimplified-logo-clear.png', 'campbuddy-wpsimplified-logo-clear-900dpi.png'] as $file) {
            $path = public_path("media/{$file}");
            $this->assertFileExists($path);
            [$w, $h] = getimagesize($path);
            $img = imagecreatefrompng($path);
            // The corners are empty (the artwork is inset by 28% of its height).
            foreach ([[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1], [(int) ($w / 2), 2]] as [$x, $y]) {
                $this->assertSame(127, (imagecolorat($img, $x, $y) >> 24) & 0x7F, "{$file} is transparent at {$x},{$y}");
            }
        }
        $this->assertStringContainsString('viewBox="-84 -84 2199 468"', file_get_contents(public_path('media/campbuddy-wpsimplified-logo-clear.svg')));
    }
}
