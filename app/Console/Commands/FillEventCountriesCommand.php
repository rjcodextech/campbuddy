<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\WordCampCentralDirectory;
use App\Support\EventCountry;
use Illuminate\Console\Command;

/**
 * Fills events.country_code for the picker's country filter: the venue
 * country from central.wordcamp.org where the event has a record there,
 * otherwise its time zone / venue line (EventCountry). Only empty rows are
 * touched unless --refresh; a code already set (e.g. by an admin) is kept.
 *
 *   php artisan campbuddy:countries             # events with no country yet
 *   php artisan campbuddy:countries --refresh   # re-read every event
 *   php artisan campbuddy:countries --offline   # no network: time zone / venue only
 */
class FillEventCountriesCommand extends Command
{
    protected $signature = 'campbuddy:countries
        {--refresh : Re-read events that already have a country}
        {--offline : Skip central.wordcamp.org}';

    protected $description = 'Fill in the country of each WordCamp (for the home page country filter)';

    public function handle(WordCampCentralDirectory $directory): int
    {
        $events = Event::query()
            ->when(! $this->option('refresh'), fn ($q) => $q->whereNull('country_code'))
            ->orderBy('id')
            ->get();

        $filled = 0;

        foreach ($events as $event) {
            $code = null;

            if (! $this->option('offline') && $event->source_site_url) {
                $record = $directory->findRecord($event->source_site_url, $event->display_name);
                $central = strtoupper(trim((string) ($record['_venue_country_code'] ?? '')));
                $code = preg_match('/^[A-Z]{2}$/', $central) ? $central : null;
            }

            $code ??= EventCountry::fromTimezone($event->timezone) ?? EventCountry::fromText($event->info['venue'] ?? null);

            if ($code === null) {
                $this->line("  ? {$event->slug}: not found");

                continue;
            }

            if ($code !== $event->country_code) {
                // Straight to the row: a country is not an edit that should
                // re-run the event's fetch jobs (EventObserver).
                Event::whereKey($event->id)->update(['country_code' => $code]);
                $filled++;
            }

            $this->line("  {$code} {$event->slug}");
        }

        $this->info("{$filled} event(s) updated, ".$events->count().' checked.');

        return self::SUCCESS;
    }
}
