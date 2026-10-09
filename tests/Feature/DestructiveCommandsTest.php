<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * On the live site no artisan command may empty the database.
 */
class DestructiveCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_commands_that_empty_the_database_refuse_to_run_in_production(): void
    {
        User::factory()->create();
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        try {
            foreach (['db:wipe', 'migrate:fresh', 'migrate:refresh', 'migrate:reset'] as $command) {
                $this->assertSame(1, Artisan::call($command, ['--force' => true]), $command);
            }
        } finally {
            DB::prohibitDestructiveCommands(false);
            $this->app['env'] = 'testing';
        }

        $this->assertSame(1, User::count(), 'nothing was emptied');
    }
}
