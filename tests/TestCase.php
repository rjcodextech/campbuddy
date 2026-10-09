<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests empty and rebuild the database (RefreshDatabase). Stop before that
     * unless it is the in-memory sqlite from phpunit.xml: a cached config
     * (bootstrap/cache/config.php) once pointed a run at the real MySQL
     * database and wiped it (9 Oct 2026).
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException("Tests stopped: they would run on the \"{$default}\" database \"{$database}\", not sqlite :memory:. Run php artisan config:clear, then run the tests again.");
        }

        return $app;
    }

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
