<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The admin panel end to end: sign-in, every page rendering for a signed-in
 * admin, and the behaviours the redesign changed (feedback on bad input,
 * switching things off, ordering).
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'draft',
            'is_visible' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    // ---- Sign-in ---------------------------------------------------------

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_the_login_screen_is_branded_and_accessible(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('Forgot your password?')
            ->assertSee('Keep me signed in')
            ->assertSee('Show password') // the reveal toggle
            ->assertSee('noindex, nofollow', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_an_admin_can_sign_in_and_lands_on_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'a-strong-password']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'a-strong-password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_shows_an_error_and_keeps_the_email(): void
    {
        $user = User::factory()->create(['password' => 'a-strong-password']);

        $this->from(route('login'))
            ->post(route('login'), ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->get(route('login'))
            ->assertSee('These credentials do not match our records.')
            ->assertSee('value="'.$user->email.'"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_the_recovery_screens_render(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Reset your password');

        $this->get(route('password.reset', 'a-token'))->assertOk()->assertSee('Choose a new password');
    }

    // ---- Every page renders ---------------------------------------------

    public function test_every_admin_page_renders_for_a_signed_in_admin(): void
    {
        $event = $this->event(['status' => 'active']);
        $this->actingAs($this->admin());

        $offer = Offer::create(['event_id' => $event->id, 'title' => 'Hosting deal', 'description' => '20% off', 'url' => 'https://host.example', 'icon' => '🏷', 'is_active' => true]);
        OfferLead::create(['event_id' => $event->id, 'offer_id' => $offer->id, 'name' => 'Priya Sharma', 'email' => 'priya@example.com']);
        AttendeeRoster::create(['event_id' => $event->id, 'name' => 'Jamie Rivera', 'links' => [['type' => 'twitter', 'url' => 'https://x.com/j']], 'content_hash' => 'abc', 'is_suppressed' => false]);
        FetchLog::create(['event_id' => $event->id, 'source' => 'wordcamp', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'error', 'message' => 'Upstream timed out', 'fetched_at' => now()]);

        $pages = [
            route('dashboard') => ['Dashboard', 'Recently updated events', 'View errors'],
            route('admin.errors.index') => ['Errors', 'System checks', 'Fetch problems', 'Upstream timed out'],
            route('admin.events.index') => ['Events', 'WordCamp Test', 'Manage'],
            route('admin.events.create') => ['Add event', 'Create event'],
            route('admin.events.edit', $event) => ['WordCamp Test', 'Event information', 'Branding', 'Event data', 'View in app'],
            route('admin.events.quests.index', $event) => ['Quests &amp; checklist', 'Save venue directions', 'Add a checklist item'],
            route('admin.events.offers.index', $event) => ['Deals', 'Hosting deal', 'Add a deal'],
            route('admin.events.roster.index', $event) => ['Roster', 'Jamie Rivera', 'Suppress'],
            route('admin.events.deal-leads.index', $event) => ['Deal leads', 'Priya Sharma', 'Export CSV'],
            route('admin.media.index') => ['Media Library', 'Upload an image'],
            route('profile.edit') => ['Account', 'Profile information', 'Update password', 'Delete account'],
        ];

        foreach ($pages as $url => $expected) {
            $response = $this->get($url)->assertOk();

            foreach ($expected as $text) {
                $response->assertSee($text, false);
            }

            // Shell present on every page: mobile drawer, skip link, sign-out.
            $response->assertSee('Open menu')->assertSee('Skip to content')->assertSee('Sign out');
        }
    }

    public function test_the_event_pages_share_the_section_tabs(): void
    {
        $event = $this->event();
        $this->actingAs($this->admin());

        $this->get(route('admin.events.roster.index', $event))
            ->assertSee('aria-label="Event sections"', false)
            ->assertSee(route('admin.events.quests.index', $event), false)
            ->assertSee(route('admin.events.offers.index', $event), false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_only_draft_events_offer_deletion(): void
    {
        $this->actingAs($this->admin());

        $draft = $this->event();
        $this->get(route('admin.events.edit', $draft))->assertSee('Delete draft event');

        $live = $this->event(['slug' => 'wc-live', 'source_site_url' => 'https://live.wordcamp.org', 'status' => 'active']);
        $this->get(route('admin.events.edit', $live))->assertDontSee('Delete draft event');
    }

    public function test_flash_messages_and_breeze_status_codes_read_as_sentences(): void
    {
        $this->actingAs($this->admin());

        $this->withSession(['status' => 'profile-updated'])->get(route('profile.edit'))
            ->assertSee('Your profile has been updated.')
            ->assertDontSee('profile-updated');
    }

    // ---- Behaviour the redesign fixed -----------------------------------

    public function test_bad_input_is_reported_at_the_top_of_the_page(): void
    {
        $event = $this->event();
        $this->actingAs($this->admin());

        $this->from(route('admin.events.quests.index', $event))
            ->post(route('admin.events.quests.store', $event), ['title' => ''])
            ->assertRedirect(route('admin.events.quests.index', $event))
            ->assertSessionHasErrors('title');

        // Before, this failed silently — the quest/offer forms showed no errors at all.
        $this->get(route('admin.events.quests.index', $event))
            ->assertSee('Please fix the following and try again.')
            ->assertSee('The title field is required.');
    }

    public function test_an_item_can_be_switched_off_and_back_on(): void
    {
        $event = $this->event();
        $this->actingAs($this->admin());
        $quest = $event->quests()->firstOrFail();

        // The form sends is_active=0 (hidden input) when the box is unticked…
        $this->put(route('admin.events.quests.update', [$event, $quest]), ['title' => $quest->title, 'sort_order' => $quest->sort_order, 'is_active' => '0']);
        $this->assertFalse($quest->fresh()->is_active);

        $this->put(route('admin.events.quests.update', [$event, $quest]), ['title' => $quest->title, 'sort_order' => $quest->sort_order, 'is_active' => '1']);
        $this->assertTrue($quest->fresh()->is_active);

        // …and the rendered form really does include that hidden input.
        $this->get(route('admin.events.quests.index', $event))
            ->assertSee('type="hidden" name="is_active" value="0"', false);
    }

    public function test_new_offers_are_added_after_existing_ones(): void
    {
        $event = $this->event();
        $this->actingAs($this->admin());

        $event->offers()->create(['title' => 'First', 'description' => 'd', 'url' => 'https://a.example', 'sort_order' => 30]);

        $this->post(route('admin.events.offers.store', $event), ['title' => 'Second', 'description' => 'd', 'url' => 'https://b.example']);

        $this->assertSame(['First', 'Second'], $event->offers()->orderBy('sort_order')->pluck('title')->all());
        $this->assertSame(40, $event->offers()->where('title', 'Second')->value('sort_order'));
    }

    public function test_the_ticked_state_of_a_failed_event_form_is_remembered(): void
    {
        $this->actingAs($this->admin());

        $this->from(route('admin.events.create'))
            ->post(route('admin.events.store'), ['slug' => '', 'display_name' => 'X', 'source_site_url' => 'https://x.example', 'status' => 'draft', 'is_visible' => '0'])
            ->assertSessionHasErrors('slug');

        $html = $this->get(route('admin.events.create'))->getContent();

        $this->assertMatchesRegularExpression('/id="is_visible"(?![^>]*\schecked)/', $html, 'Unticked stays unticked after a failed submit.');
        $this->assertStringContainsString('value="X"', $html);
    }

    public function test_a_failed_info_form_does_not_untick_the_events_visibility(): void
    {
        $event = $this->event(['is_visible' => true]);
        $this->actingAs($this->admin());

        // The Event-information form fails validation (bad URL)…
        $this->from(route('admin.events.edit', $event))
            ->put(route('admin.events.update-info', $event), ['code_of_conduct_url' => 'not a url'])
            ->assertSessionHasErrors('code_of_conduct_url');

        // …and the *details* form on the same page must still show the saved
        // state, or saving it would silently hide the event.
        $html = $this->get(route('admin.events.edit', $event))->getContent();

        $this->assertMatchesRegularExpression('/id="is_visible"[^>]*\schecked/s', $html);
    }

    public function test_the_default_checklist_is_visible_to_the_admin_for_a_new_event(): void
    {
        $event = $this->event();
        $this->actingAs($this->admin());

        $response = $this->get(route('admin.events.quests.index', $event))->assertOk();

        foreach (Quest::DEFAULT_CHECKLIST as $title) {
            $response->assertSee(e($title), false);
        }
    }

    // ---- Design-system guard --------------------------------------------

    public function test_every_button_variant_is_spelled_out_where_tailwind_can_see_it(): void
    {
        // Tailwind drops component classes whose full name never appears in a
        // scanned file, so a class assembled as 'cb-btn-'.$variant ships
        // unstyled buttons — with no error anywhere.
        preg_match_all('/\.(cb-btn-[a-z-]+)/', file_get_contents(resource_path('css/app.css')), $m);
        $views = collect(File::allFiles(resource_path('views')))->map->getContents()->implode("\n");

        $this->assertNotEmpty($m[1]);

        foreach (array_unique($m[1]) as $class) {
            $this->assertStringContainsString($class, $views, "{$class} is defined in app.css but never written out in a view.");
        }
    }

    public function test_admin_views_use_brand_tokens_not_stock_breeze_colours(): void
    {
        $root = resource_path('views');
        $files = collect(File::allFiles($root))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'))
            ->reject(fn ($f) => str_starts_with($f->getRelativePathname(), 'attendee')
                || in_array($f->getRelativePathname(), ['welcome.blade.php', 'layouts\\attendee.blade.php', 'layouts/attendee.blade.php'], true));

        $offenders = [];

        foreach ($files as $file) {
            if (preg_match('/\b(?:text|bg|border|ring|from|to)-(?:gray|indigo|blue|green|red|slate|zinc|neutral)-\d{2,3}\b/', $file->getContents(), $m)) {
                $offenders[] = $file->getRelativePathname().' → '.$m[0];
            }
        }

        $this->assertSame([], $offenders, 'Use the tokens in tailwind.config.js (ink, muted, line, maroon, danger, teal…).');
    }
}
