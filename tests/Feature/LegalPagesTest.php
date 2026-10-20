<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The DPDP Act needs a plain-language notice and a way to ask for access, correction, erasure and to complain.
 * These pages are that, and the places people look for them must link to them.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_privacy_notice_names_who_is_responsible_and_how_to_complain(): void
    {
        $page = $this->get('/privacy')->assertOk();

        $page->assertSee('Privacy notice');
        $page->assertSee(config('campbuddy.legal.entity'));
        $page->assertSee(config('campbuddy.legal.grievance_email'));
        $page->assertSee('Data Protection Board of India');
        $page->assertSee('Withdraw consent', false);
        $page->assertSee('We reply within '.config('campbuddy.legal.reply_days').' days', false);
        // The things we really keep are all listed.
        foreach (['discovery profile', 'Shared Camp Card', 'Deal forms', 'Notifications', 'Feedback', 'Reports', 'public attendee list', 'Technical records', 'Website visit statistics'] as $thing) {
            $page->assertSee($thing, false);
        }
        $page->assertSee('contain no analytics', false);
    }

    public function test_the_terms_page_exists_and_points_to_the_privacy_notice(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms of use')->assertSee(route('privacy'), false);
    }

    public function test_the_pages_run_without_a_session_or_cookies_like_every_attendee_page(): void
    {
        foreach (['/privacy', '/terms'] as $url) {
            $this->assertSame([], $this->get($url)->headers->getCookies(), "{$url} sets no cookie");
        }
    }

    public function test_the_home_page_and_the_guide_link_to_them(): void
    {
        foreach (['/', '/guide'] as $url) {
            $this->get($url)->assertOk()->assertSee(route('privacy'), false)->assertSee(route('terms'), false)
                ->assertSee('mailto:'.config('campbuddy.legal.grievance_email'), false);
        }
    }

    public function test_every_table_the_erasure_job_empties_is_described_in_the_notice(): void
    {
        // If a new kind of shared data is added to the job, the notice has to say so: keep the two in step.
        $notice = $this->get('/privacy')->getContent();
        foreach (['push', 'bookmark'] as $word) {
            $this->assertStringContainsString($word === 'push' ? 'Notifications' : 'sessions you asked to be reminded about', $notice);
        }
        $this->assertStringContainsString('deleted about 90 days after the event', $notice);
        $this->assertSame(60, \App\Support\PersonalDataErasure::ERASE_AFTER_DAYS);
        $this->assertSame(30, \App\Support\EventTime::RETENTION_DAYS);
    }

    public function test_guest_cannot_access_admin_legal(): void
    {
        $this->get(route('admin.legal.index'))->assertRedirect(route('login'));
        $this->get(route('admin.legal.show', 'privacy'))->assertRedirect(route('login'));
        $this->get(route('admin.legal.edit', 'privacy'))->assertRedirect(route('login'));
        $this->put(route('admin.legal.update', 'privacy'), ['title' => 'Test', 'content' => 'Custom'])->assertRedirect(route('login'));
        $this->post(route('admin.legal.update', 'privacy'), ['title' => 'Test', 'content' => 'Custom'])->assertRedirect(route('login'));
    }

    public function test_admin_can_view_legal_index_and_edit_page(): void
    {
        $admin = \App\Models\User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.legal.index'))
            ->assertOk()
            ->assertSee('Legal documents')
            ->assertSee('Privacy notice')
            ->assertSee('Terms of use')
            ->assertSee('Default template');

        // Direct GET to /admin/legal/{slug} opens the editor instead of 405 Method Not Allowed
        $this->actingAs($admin)
            ->get(route('admin.legal.show', 'privacy'))
            ->assertOk()
            ->assertSee('Edit Privacy notice');

        $this->actingAs($admin)
            ->get(route('admin.legal.edit', 'privacy'))
            ->assertOk()
            ->assertSee('Edit Privacy notice')
            ->assertSee('Save changes');

        // Unknown legal slug returns 404
        $this->actingAs($admin)
            ->get('/admin/legal/unknown-slug')
            ->assertNotFound();
    }

    public function test_admin_can_customize_and_reset_legal_document(): void
    {
        $admin = \App\Models\User::factory()->create();

        // 1. Update privacy policy
        $this->actingAs($admin)
            ->put(route('admin.legal.update', 'privacy'), [
                'title' => 'Custom WordCamp Privacy Notice',
                'meta_description' => 'Customized legal terms for this event',
                'content' => '<p>This is a custom privacy disclosure approved by local organizers.</p>',
            ])
            ->assertRedirect(route('admin.legal.index'))
            ->assertSessionHas('status', 'legal-updated');

        // 2. Verify on public web page
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Custom WordCamp Privacy Notice')
            ->assertSee('This is a custom privacy disclosure approved by local organizers.', false);

        // 3. Verify on API endpoint
        $response = $this->getJson(route('api.legal.show', 'privacy'))->assertOk();
        $response->assertJson([
            'slug' => 'privacy',
            'title' => 'Custom WordCamp Privacy Notice',
            'has_custom_content' => true,
        ]);
        $this->assertStringContainsString('approved by local organizers', $response->json('content'));

        // 4. Also verify updating via standard POST without method spoofing works seamlessly
        $this->actingAs($admin)
            ->post(route('admin.legal.update', 'terms'), [
                'title' => 'Custom Terms of Use',
                'meta_description' => 'Custom terms for our attendees',
                'content' => '<p>Please abide by the code of conduct.</p>',
            ])
            ->assertRedirect(route('admin.legal.index'))
            ->assertSessionHas('status', 'legal-updated');

        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('Custom Terms of Use')
            ->assertSee('Please abide by the code of conduct.', false);

        // 5. Reset document back to default
        $this->actingAs($admin)
            ->post(route('admin.legal.reset', 'privacy'))
            ->assertRedirect(route('admin.legal.index'))
            ->assertSessionHas('status', 'legal-reset');

        // 6. Verify restored default
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Privacy notice')
            ->assertSee('The short version');

        $apiReset = $this->getJson(route('api.legal.show', 'privacy'))->assertOk();
        $apiReset->assertJson([
            'slug' => 'privacy',
            'has_custom_content' => false,
            'content' => null,
        ]);
    }

    public function test_api_legal_returns_404_for_invalid_slug(): void
    {
        $this->getJson('/api/v1/legal/unknown-slug')->assertNotFound();
        $this->getJson('/api/legal/unknown-slug')->assertNotFound();
    }
}
