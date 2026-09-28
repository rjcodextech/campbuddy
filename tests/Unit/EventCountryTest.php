<?php

namespace Tests\Unit;

use App\Support\EventCountry;
use Tests\TestCase;

class EventCountryTest extends TestCase
{
    public function test_a_country_from_a_time_zone(): void
    {
        $this->assertSame('IN', EventCountry::fromTimezone('Asia/Kolkata'));
        $this->assertSame('GB', EventCountry::fromTimezone('Europe/London'));
        $this->assertNull(EventCountry::fromTimezone('+05:30'));
        $this->assertNull(EventCountry::fromTimezone('UTC'));
        $this->assertNull(EventCountry::fromTimezone('Nowhere/Land'));
        $this->assertNull(EventCountry::fromTimezone(null));
    }

    public function test_a_country_from_the_end_of_a_venue_line(): void
    {
        $this->assertSame('ES', EventCountry::fromText('A Coruña, Galicia, Spain'));
        $this->assertSame('NL', EventCountry::fromText('Madurodam — Den Haag, The Netherlands'));
        $this->assertSame('US', EventCountry::fromText('Portland, OR, USA'));
        $this->assertNull(EventCountry::fromText('Whitworth Locke — 74 Princess Street, Manchester, M1 6JD'));
        $this->assertNull(EventCountry::fromText(null));
    }

    public function test_time_zones_of_listed_countries_only(): void
    {
        $zones = EventCountry::timezones(['IN']);

        $this->assertSame('IN', $zones['Asia/Kolkata']);
        $this->assertSame(['IN'], array_values(array_unique($zones)));
    }
}
