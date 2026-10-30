<?php

namespace App\Jobs;

use App\Models\ContributorTable;
use App\Models\Event;
use App\Models\FetchLog;
use App\Services\ContributorDayParser;
use App\Support\ContributorTables;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads the Contributor Day teams and table leads from the WordCamp site, once
 * a day while the event is live (routes/console.php): the site's own search
 * (REST, /wp/v2/search) finds pages and posts about Contributor Day, and
 * ContributorDayParser reads teams and leads out of them. The tables found
 * replace the ones read before ("site"); tables typed in by an admin or
 * manager ("manual") are never touched, and a team that already has a manual
 * table is skipped. Finding nothing changes nothing.
 */
class FetchContributorTablesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly Event $event) {}

    public function handle(): void
    {
        if (! Schema::hasTable('contributor_tables') || ! Schema::hasColumn('contributor_tables', 'source')) {
            return;
        }

        try {
            [$found, $pages] = $this->read();
        } catch (Throwable $e) {
            $this->log('error', 'Could not read the site: '.mb_substr($e->getMessage(), 0, 300));

            return;
        }

        if ($found === []) {
            $this->log('ok', $pages === 0 ? 'No Contributor Day page on the site yet' : "Read {$pages} Contributor Day page(s): no teams with leads found");

            return;
        }

        $manualTeams = ContributorTable::where('event_id', $this->event->id)->where('source', 'manual')->get()
            ->map(fn ($t) => $t->team.'|'.($t->team === 'other' ? mb_strtolower((string) $t->title) : ''))->all();

        ContributorTable::where('event_id', $this->event->id)->where('source', 'site')->delete();
        $made = 0;
        foreach ($found as $i => $t) {
            if (in_array($t['team'].'|'.($t['team'] === 'other' ? mb_strtolower((string) $t['title']) : ''), $manualTeams, true)) {
                continue;
            }
            ContributorTable::create(['event_id' => $this->event->id, 'source' => 'site'] + ContributorTables::fields($this->event, [
                'team' => $t['team'],
                'title' => $t['title'],
                'leads' => implode(', ', $t['leads']),
                'sort_order' => 100 + $i,
            ]));
            $made++;
        }
        ContributorTables::changed($this->event);

        $this->log('ok', "Read {$pages} Contributor Day page(s): {$made} tables from the site".(count($found) > $made ? ', '.(count($found) - $made).' skipped (a table typed in by hand already exists)' : ''));
    }

    /**
     * The teams on the site's Contributor Day pages, and how many pages were read.
     *
     * @return array{0: list<array{team: string, title: ?string, leads: list<string>}>, 1: int}
     */
    private function read(): array
    {
        $base = rtrim($this->event->source_site_url, '/');
        $hits = Http::timeout(15)->acceptJson()->get("{$base}/wp-json/wp/v2/search", ['search' => 'contributor day', 'per_page' => 10, 'type' => 'post'])->throw()->json();
        $hits = is_array($hits) && array_is_list($hits) ? $hits : [];

        $parser = new ContributorDayParser;
        $found = [];
        $pages = 0;
        foreach ($hits as $hit) {
            $title = strtolower(strip_tags((string) ($hit['title'] ?? '')));
            $subtype = (string) ($hit['subtype'] ?? '');
            if (! str_contains($title, 'contributor') || ! in_array($subtype, ['page', 'post'], true) || ! is_int($hit['id'] ?? null)) {
                continue;
            }
            $post = Http::timeout(15)->acceptJson()->get("{$base}/wp-json/wp/v2/{$subtype}s/{$hit['id']}", ['_fields' => 'content'])->throw()->json();
            $pages++;
            $found = [...$found, ...$parser->parse((string) ($post['content']['rendered'] ?? ''))];
            if ($pages >= 3) {
                break;
            }
        }

        return [$found, $pages];
    }

    private function log(string $status, string $message): void
    {
        FetchLog::create([
            'event_id' => $this->event->id,
            'source' => 'site_search',
            'job_type' => 'tables',
            'status' => $status,
            'message' => $message,
            'fetched_at' => now(),
        ]);
    }
}
