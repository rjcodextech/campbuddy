<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaPitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_media_pitch_page(): void
    {
        $admin = User::factory()->create();
        Event::create([
            'slug' => 'wordcamp-delhi-2026',
            'display_name' => 'WordCamp Delhi 2026',
            'starts_on' => '2026-12-12',
            'ends_on' => '2026-12-13',
            'venue_name' => 'Dr. Ambedkar International Centre',
            'city' => 'New Delhi',
            'status' => 'approved',
            'source_site_url' => 'https://delhi.wordcamp.org/2026/',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.media-pitch.index'));

        $response->assertOk();
        $response->assertSee('WordCamp Delhi 2026');
        $response->assertSee('CampBuddy (by WPSimplified)');
        $response->assertSee('campbuddy-wpsimplified-logo.svg');
        $response->assertDontSee('open-source (GPL-2.0)');
    }

    public function test_admin_can_access_print_proposal(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.media-pitch.print', [
            'event_name' => 'WordCamp Delhi 2026',
            'event_dates' => '12–13 December 2026',
            'event_venue' => 'Dr. Ambedkar International Centre',
            'event_email' => 'delhi@wordcamp.org',
            'applicant_name' => 'Sunil Kumar Sharma',
            'applicant_role' => 'Lead — WPSimplified & CampBuddy',
        ]));

        $response->assertOk();
        $response->assertSee('WordCamp Delhi 2026');
        $response->assertSee('Lead — WPSimplified & CampBuddy');
        $response->assertSee('campbuddy-wpsimplified-logo.svg');
        $response->assertDontSee('table-links');
        $response->assertDontSee('open-source (GPL-2.0)');
    }
}
