<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** `campbuddy:countries` fills events.country_code for the picker's country filter. */
class EventCountriesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $slug, array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => $slug,
            'display_name' => $slug,
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'draft',
            'is_visible' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_central_venue_country_first_then_time_zone_and_set_codes_are_kept(): void
    {
        Http::fake([
            'central.wordcamp.org/*' => Http::response([
                ['URL' => 'https://pune.wordcamp.org/2026/', '_venue_country_code' => 'IN'],
            ]),
        ]);

        $pune = $this->event('pune', ['timezone' => 'Europe/London']);
        $sofia = $this->event('sofia', ['timezone' => 'Europe/Sofia']);
        $kept = $this->event('kept', ['country_code' => 'GB', 'timezone' => 'Asia/Kolkata']);
        $none = $this->event('none');

        $this->artisan('campbuddy:countries')->assertSuccessful();

        $this->assertSame('IN', $pune->fresh()->country_code, 'central wins over the time zone');
        $this->assertSame('BG', $sofia->fresh()->country_code);
        $this->assertSame('GB', $kept->fresh()->country_code, 'already set: not touched');
        $this->assertNull($none->fresh()->country_code);
    }

    public function test_offline_uses_no_network(): void
    {
        Http::preventStrayRequests();
        $event = $this->event('jaipur', ['timezone' => 'Asia/Kolkata']);

        $this->artisan('campbuddy:countries --offline')->assertSuccessful();

        $this->assertSame('IN', $event->fresh()->country_code);
    }
}
