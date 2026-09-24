<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** The public front door — the WordCamp picker — renders even with no events yet. */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('No WordCamp is live yet');
    }
}
