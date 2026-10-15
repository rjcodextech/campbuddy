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
}
