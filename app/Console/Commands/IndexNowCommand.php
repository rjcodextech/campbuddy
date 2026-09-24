<?php

namespace App\Console\Commands;

use App\Support\EventTime;
use App\Support\Seo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tells search engines about new and changed pages right away through
 * IndexNow (Bing, Yandex, Seznam, Naver…), instead of waiting for their next
 * crawl. Google reads /sitemap.xml, which robots.txt points to.
 *
 * Runs daily from the scheduler; by hand:
 *
 *   php artisan campbuddy:indexnow            # pages changed since the last run
 *   php artisan campbuddy:indexnow --all      # every page in the sitemap
 *   php artisan campbuddy:indexnow --dry-run  # list them, send nothing
 */
class IndexNowCommand extends Command
{
    public const LAST_RUN_KEY = 'seo:indexnow-last';

    protected $signature = 'campbuddy:indexnow
        {--all : Submit every URL in the sitemap}
        {--dry-run : Show the URLs without sending them}';

    protected $description = 'Submit new and changed CampBuddy pages to search engines (IndexNow)';

    public function handle(): int
    {
        if (! app()->isProduction() && ! $this->option('dry-run')) {
            $this->info('Skipped: only production is submitted to search engines (use --dry-run to preview).');

            return self::SUCCESS;
        }

        $urls = $this->urls();

        if ($urls === []) {
            $this->info('Nothing changed since the last submission.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line(implode("\n", $urls));
            $this->info(count($urls).' URL(s) would be submitted.');

            return self::SUCCESS;
        }

        $key = Seo::indexNowKey();

        try {
            $response = Http::timeout(15)->acceptJson()->post(config('services.indexnow.endpoint'), [
                'host' => parse_url(route('home'), PHP_URL_HOST),
                'key' => $key,
                'keyLocation' => route('indexnow.key', $key),
                'urlList' => array_slice($urls, 0, 10000),
            ]);
        } catch (Throwable $e) {
            $this->error('IndexNow could not be reached: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("IndexNow answered {$response->status()}: ".mb_substr($response->body(), 0, 200));

            return self::FAILURE;
        }

        Cache::forever(self::LAST_RUN_KEY, now()->toIso8601String());
        $this->info('Submitted '.count($urls).' URL(s) to IndexNow.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function urls(): array
    {
        $last = Cache::get(self::LAST_RUN_KEY);

        // First run, or asked for everything.
        if ($this->option('all') || ! $last) {
            return array_column(Seo::urls(), 'loc');
        }

        $since = strtotime($last);
        $urls = [];

        foreach (Seo::publicEvents() as $event) {
            $fetchedAt = Cache::get("event:{$event->id}:fetched-at");
            $fetched = $fetchedAt ? strtotime((string) $fetchedAt) : 0;
            $upcoming = ! EventTime::isOver($event, now()->subDay());

            // Details edited, or (for an event still to come) its schedule refreshed.
            if ($event->updated_at?->getTimestamp() > $since || ($upcoming && $fetched > $since)) {
                foreach (array_keys(Seo::EVENT_PAGES) as $route) {
                    $urls[] = route($route, $event);
                }
            }
        }

        return $urls;
    }
}
