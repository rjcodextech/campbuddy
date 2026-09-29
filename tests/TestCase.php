<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Outbound fetches back off between retries; tests shouldn't wait for it.
        Sleep::fake();

        // No test reaches a real WordCamp site: an unfaked request fails like a
        // dead network, instead of hanging the suite on a live one.
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        // Admin "Refresh now" and DataRefresher call set_time_limit(), which in
        // one PHPUnit process would otherwise cap the rest of the whole run.
        @set_time_limit(0);

        parent::tearDown();
    }
}
