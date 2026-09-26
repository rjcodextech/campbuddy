<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Admin → Event managers: an admin creates people who may edit a few events.
 * There is no sign-up anywhere; this is the only door.
 */
class EventManagerAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function event(string $slug, array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => $slug,
            'display_name' => 'WordCamp '.ucfirst($slug),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'draft',
            'is_visible' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'phone' => '+91 98765 43210',
            'password' => 'a-strong-password',
            'is_active' => '1',
            'events' => [],
        ];
    }

    public function test_only_a_signed_in_admin_reaches_the_pages(): void
    {
        $manager = EventManager::factory()->create();

        $this->get(route('admin.event-managers.index'))->assertRedirect(route('login'));
        $this->get(route('admin.event-managers.create'))->assertRedirect(route('login'));
        $this->get(route('admin.event-managers.edit', $manager))->assertRedirect(route('login'));
        $this->post(route('admin.event-managers.store'), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('admin.event-managers.destroy', $manager))->assertRedirect(route('login'));
        $this->assertSame(1, EventManager::count());
    }

    public function test_an_event_manager_can_not_reach_them_either(): void
    {
        $manager = EventManager::factory()->create();

        // Signed in as a manager (its own guard) is not signed in as an admin. (Not actingAs():
        // that also makes the manager guard the default one, which the app never does.)
        Auth::guard('manager')->setUser($manager);

        $this->get(route('admin.event-managers.index'))->assertRedirect(route('login'));
        $this->post(route('admin.event-managers.store'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_code_uploaded_before_its_migration_says_what_to_run_instead_of_a_500(): void
    {
        $this->actingAs($this->admin());
        Schema::drop('event_event_manager');
        Schema::drop('event_managers');

        foreach ([route('admin.event-managers.index'), route('admin.event-managers.create')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee("The database isn't ready for event managers yet")
                ->assertSee('php artisan migrate --force')
                ->assertSee(route('admin.errors.index'), false);
        }

        // The rest of the admin panel is untouched by it.
        $this->get(route('admin.events.index'))->assertOk();
    }

    public function test_the_health_check_says_when_a_stale_cache_hides_the_manager_sign_in(): void
    {
        $this->actingAs($this->admin());
        \Illuminate\Support\Facades\Cache::forever(\App\Support\SystemHealth::HEARTBEAT_KEY, now()->toIso8601String());

        $titles = fn () => collect(\App\Support\SystemHealth::problems())->pluck('title');

        // Set up properly: nothing to say.
        $this->assertFalse($titles()->contains("Event manager sign-in isn't set up"));
        $this->get(route('admin.errors.index'))->assertDontSee('Event manager sign-in');

        // Code uploaded on top of a cached config from before the update: no guard.
        config(['auth.guards.manager' => null]);

        $problem = collect(\App\Support\SystemHealth::problems())->firstWhere('title', "Event manager sign-in isn't set up");
        $this->assertNotNull($problem);
        $this->assertSame('warning', $problem['level']);
        $this->assertSame('php artisan optimize:clear && php artisan optimize', $problem['fix']);
        $this->assertStringContainsString('cached config', $problem['detail']);

        $this->get(route('admin.errors.index'))->assertSee('Event manager sign-in')->assertSee('php artisan optimize:clear');

        // The public yes/no health endpoint is unchanged.
        config(['auth.guards.manager' => ['driver' => 'session', 'provider' => 'event_managers']]);
        $this->getJson(route('api.health'))->assertOk()->assertJsonMissingPath('problems')->assertJsonStructure(['status', 'checks' => ['migrations', 'scheduler', 'queue']]);
    }
    public function test_the_sidebar_links_to_event_managers(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('dashboard'))->assertSee(route('admin.event-managers.index'), false)->assertSee('Event managers');
    }

    public function test_the_list_shows_each_manager_with_their_events_and_status(): void
    {
        $this->actingAs($this->admin());
        $a = $this->event('alpha');
        $b = $this->event('beta');

        $active = EventManager::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com']);
        $active->events()->attach([$a->id, $b->id]);
        EventManager::factory()->disabled()->create(['name' => 'Ravi Kumar']);

        $this->get(route('admin.event-managers.index'))
            ->assertOk()
            ->assertSeeInOrder(['Asha Verma', 'WordCamp Alpha', 'WordCamp Beta', 'Active'])
            ->assertSee('Ravi Kumar')
            ->assertSee('Switched off')
            ->assertSee(route('manager.login'), false);
    }

    public function test_the_list_says_so_when_there_are_none(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.event-managers.index'))->assertOk()->assertSee('No event managers yet');
    }

    public function test_the_create_form_offers_every_event_soonest_first(): void
    {
        $this->actingAs($this->admin());
        $this->event('later', ['starts_on' => '2027-03-01']);
        $this->event('sooner', ['starts_on' => '2027-01-01']);

        $response = $this->get(route('admin.event-managers.create'))->assertOk();

        $response->assertSee('name="events[]"', false)
            ->assertSeeInOrder(['WordCamp Sooner', 'WordCamp Later'])
            ->assertSee('never its status');

        // A new manager is Active by default.
        $this->assertMatchesRegularExpression('/id="is_active"[^>]*\schecked/s', $response->getContent());
    }

    public function test_an_admin_creates_a_manager_with_events(): void
    {
        $this->actingAs($this->admin());
        $a = $this->event('alpha');
        $b = $this->event('beta');
        $this->event('gamma');

        $response = $this->post(route('admin.event-managers.store'), $this->payload([
            'email' => '  Asha@Example.COM ',
            'events' => [$a->id, $b->id],
        ]));

        $manager = EventManager::firstOrFail();
        $response->assertRedirect(route('admin.event-managers.edit', $manager));

        $this->assertSame('Asha Verma', $manager->name);
        $this->assertSame('asha@example.com', $manager->email, 'Emails are stored lower-cased and trimmed.');
        $this->assertSame('+91 98765 43210', $manager->phone);
        $this->assertTrue($manager->is_active);
        $this->assertNotSame('a-strong-password', $manager->password);
        $this->assertTrue(Hash::check('a-strong-password', $manager->password));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $manager->events()->pluck('events.id')->all());

        // The admin is told where the manager signs in.
        $this->get(route('admin.event-managers.edit', $manager))->assertSee(route('manager.login'), false)->assertSee('created');
    }

    public function test_a_manager_needs_a_name_email_phone_password_and_at_least_one_event(): void
    {
        $this->actingAs($this->admin());
        $this->event('alpha');

        $this->from(route('admin.event-managers.create'))
            ->post(route('admin.event-managers.store'), ['is_active' => '1'])
            ->assertRedirect(route('admin.event-managers.create'))
            ->assertSessionHasErrors(['name', 'email', 'phone', 'password', 'events']);

        $this->assertSame(0, EventManager::count());

        $this->get(route('admin.event-managers.create'))->assertSee('Pick at least one WordCamp for this manager.');
    }

    public function test_bad_details_are_refused(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event('alpha');
        EventManager::factory()->create(['email' => 'taken@example.com']);

        $post = fn (array $overrides) => $this->post(route('admin.event-managers.store'), $this->payload($overrides + ['events' => [$event->id]]));

        $post(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $post(['email' => 'TAKEN@example.com'])->assertSessionHasErrors('email');
        $post(['phone' => 'call me'])->assertSessionHasErrors('phone');
        $post(['phone' => '12'])->assertSessionHasErrors('phone');
        $post(['password' => 'short'])->assertSessionHasErrors('password');
        $post(['events' => [999999]])->assertSessionHasErrors('events.0');

        $this->assertSame(1, EventManager::count());
    }

    public function test_phone_numbers_in_common_formats_are_accepted(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event('alpha');

        foreach (['+91 98765 43210', '098765-43210', '(011) 2345 6789', '+880 1712-345678'] as $i => $phone) {
            $this->post(route('admin.event-managers.store'), $this->payload(['email' => "m{$i}@example.com", 'phone' => $phone, 'events' => [$event->id]]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(4, EventManager::count());
    }

    public function test_editing_keeps_the_password_when_left_blank_and_syncs_the_events(): void
    {
        $this->actingAs($this->admin());
        $a = $this->event('alpha');
        $b = $this->event('beta');
        $c = $this->event('gamma');

        $manager = EventManager::factory()->create(['password' => 'original-password', 'email' => 'asha@example.com']);
        $manager->events()->attach([$a->id, $b->id]);
        $hashBefore = $manager->password;

        $this->put(route('admin.event-managers.update', $manager), $this->payload([
            'name' => 'Asha V.',
            'phone' => '+91 90000 00000',
            'password' => '',
            'events' => [$b->id, $c->id],
        ]))->assertRedirect(route('admin.event-managers.edit', $manager));

        $manager->refresh();
        $this->assertSame('Asha V.', $manager->name);
        $this->assertSame('+91 90000 00000', $manager->phone);
        $this->assertSame($hashBefore, $manager->password, 'A blank password field keeps the current password.');
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $manager->events()->pluck('events.id')->all());
    }

    public function test_a_new_password_replaces_the_old_one_and_the_remember_cookie(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event('alpha');
        $manager = EventManager::factory()->create(['password' => 'original-password', 'email' => 'asha@example.com', 'remember_token' => 'old-token']);
        $manager->events()->attach($event);

        $this->put(route('admin.event-managers.update', $manager), $this->payload(['password' => 'brand-new-password', 'events' => [$event->id]]));

        $manager->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $manager->password));
        $this->assertNotSame('old-token', $manager->remember_token);
    }

    public function test_a_manager_can_be_switched_off_and_on(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event('alpha');
        $manager = EventManager::factory()->create(['email' => 'asha@example.com']);
        $manager->events()->attach($event);

        $off = $this->payload(['is_active' => '0', 'password' => '', 'events' => [$event->id]]);
        $this->put(route('admin.event-managers.update', $manager), $off);
        $this->assertFalse($manager->fresh()->is_active);

        $this->put(route('admin.event-managers.update', $manager), ['is_active' => '1'] + $off);
        $this->assertTrue($manager->fresh()->is_active);
    }

    public function test_a_manager_may_be_left_with_no_events_while_editing(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event('alpha');
        $manager = EventManager::factory()->create(['email' => 'asha@example.com']);
        $manager->events()->attach($event);

        $this->put(route('admin.event-managers.update', $manager), $this->payload(['password' => '', 'events' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $manager->events()->count());
    }

    public function test_the_email_may_stay_but_not_clash_with_another_manager(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event('alpha');
        $mine = EventManager::factory()->create(['email' => 'mine@example.com']);
        EventManager::factory()->create(['email' => 'other@example.com']);

        $base = $this->payload(['password' => '', 'events' => [$event->id]]);

        $this->put(route('admin.event-managers.update', $mine), ['email' => 'mine@example.com'] + $base)->assertSessionHasNoErrors();
        $this->put(route('admin.event-managers.update', $mine), ['email' => 'Other@example.com'] + $base)->assertSessionHasErrors('email');
    }

    public function test_the_edit_form_shows_the_current_events_ticked(): void
    {
        $this->actingAs($this->admin());
        $a = $this->event('alpha');
        $b = $this->event('beta');
        $manager = EventManager::factory()->create();
        $manager->events()->attach($a);

        $html = $this->get(route('admin.event-managers.edit', $manager))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="events\[\]" value="'.$a->id.'" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="events\[\]" value="'.$b->id.'" checked/', $html);
        // The password is never printed back.
        $this->assertStringNotContainsString($manager->password, $html);
    }

    public function test_deleting_a_manager_leaves_the_events_alone(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event('alpha');
        $manager = EventManager::factory()->create();
        $manager->events()->attach($event);

        $this->delete(route('admin.event-managers.destroy', $manager))->assertRedirect(route('admin.event-managers.index'));

        $this->assertSame(0, EventManager::count());
        $this->assertSame(0, DB::table('event_event_manager')->count());
        $this->assertNotNull($event->fresh());
    }

    public function test_deleting_an_event_only_unlinks_its_managers(): void
    {
        $event = $this->event('alpha');
        $manager = EventManager::factory()->create();
        $manager->events()->attach($event);

        $event->delete();

        $this->assertNotNull($manager->fresh());
        $this->assertSame(0, $manager->events()->count());
    }
}
