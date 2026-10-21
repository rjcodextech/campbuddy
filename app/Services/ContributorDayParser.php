<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads the Contributor Day teams and their table leads out of a WordCamp
 * page's HTML. Sites write this many ways; the ones handled:
 *   - a heading per team ("Polyglots"), with the leads in the lines under it
 *     ("Table lead: Asha Rao", or just the names);
 *   - one line per team: "Core – Asha Rao, Ravi Kumar", "Docs: Asha & Ravi",
 *     "Polyglots (Leads: Asha Rao)";
 *   - a table with the team in one column and the leads in another.
 * Only lines that name a known team count, and only name-shaped words are
 * taken as leads, so an ordinary page gives nothing rather than nonsense.
 */
class ContributorDayParser
{
    /** Words that mean a team (lower case) => the CampBuddy team key (config/contributor_teams). */
    private const ALIASES = [
        'core' => 'core', 'core team' => 'core',
        'test' => 'test', 'testing' => 'test', 'test team' => 'test',
        'docs' => 'docs', 'documentation' => 'docs',
        'support' => 'support', 'support forums' => 'support',
        'polyglots' => 'polyglots', 'translation' => 'polyglots', 'translations' => 'polyglots',
        'design' => 'design',
        'training' => 'training', 'learn' => 'training', 'learn wordpress' => 'training',
        'community' => 'community',
        'accessibility' => 'accessibility', 'a11y' => 'accessibility',
        'photography' => 'photography', 'photos' => 'photography', 'photo directory' => 'photography',
    ];

    /** Other Make WordPress teams: kept as "other" with their own name. */
    private const OTHER_TEAMS = ['meta', 'marketing', 'hosting', 'openverse', 'mobile', 'themes', 'theme review', 'plugins', 'plugin review', 'performance', 'cli', 'wp-cli', 'tide', 'sustainability', 'wordpress.tv', 'tv', 'security', 'privacy', 'ai'];

    /** Capitalised words that are not people. */
    private const NOT_NAMES = ['tba', 'tbd', 'lead', 'leads', 'table lead', 'table leads', 'contributor day', 'everyone', 'beginners', 'all', 'none', 'yes', 'no', 'coming soon', 'to be announced', 'wordpress', 'wordcamp', 'online', 'remote', 'room', 'hall'];

    /**
     * @return list<array{team: string, title: ?string, leads: list<string>}>
     */
    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $crawler = new Crawler('<div id="cb-root">'.$html.'</div>');
        $tables = [];
        $current = null;

        $crawler->filter('#cb-root')->children()->each(function (Crawler $node) use (&$tables, &$current) {
            $this->walk($node, $tables, $current);
        });

        // One table per team: leads of the same team put together.
        $byTeam = [];
        foreach ($tables as $t) {
            $key = $t['team'].'|'.($t['title'] ?? '');
            $byTeam[$key] ??= ['team' => $t['team'], 'title' => $t['title'], 'leads' => []];
            $byTeam[$key]['leads'] = array_values(array_unique([...$byTeam[$key]['leads'], ...$t['leads']]));
        }

        return array_values(array_filter($byTeam, fn ($t) => $t['leads'] !== [] || $t['team'] !== 'other'));
    }

    private function walk(Crawler $node, array &$tables, ?array &$current): void
    {
        $tag = strtolower($node->nodeName());

        if (in_array($tag, ['h2', 'h3', 'h4', 'h5', 'strong'], true)) {
            $team = $this->team($this->clean($node->text('')));
            $current = $team ? ['team' => $team[0], 'title' => $team[1], 'leads' => []] : null;
            if ($current) {
                $tables[] = $current;
            }

            return;
        }

        if ($tag === 'table') {
            $node->filter('tr')->each(function (Crawler $row) use (&$tables) {
                $cells = $row->filter('td,th')->each(fn (Crawler $c) => $this->clean($c->text('')));
                foreach ($cells as $i => $cell) {
                    if ($team = $this->team($cell)) {
                        $leads = [];
                        foreach (array_slice($cells, $i + 1) as $rest) {
                            $leads = [...$leads, ...$this->names($rest)];
                        }
                        $tables[] = ['team' => $team[0], 'title' => $team[1], 'leads' => $leads];
                        break;
                    }
                }
            });

            return;
        }

        if (in_array($tag, ['ul', 'ol', 'div', 'section', 'figure'], true)) {
            // A closure, not an arrow function: the found tables must come back out of it.
            $node->children()->each(function (Crawler $child) use (&$tables, &$current) {
                $this->walk($child, $tables, $current);
            });

            return;
        }

        if (in_array($tag, ['li', 'p'], true)) {
            $line = $this->clean($node->text(''));
            if ($line === '') {
                return;
            }
            // "Core – Asha Rao, Ravi Kumar" / "Polyglots (Leads: Asha)": the line names its team.
            if (preg_match('/^(.{2,40}?)\s*(?:[:–—|-]|\(|\s+[-–]\s+)\s*(.+)$/u', $line, $m) && ($team = $this->team($m[1]))) {
                $tables[] = ['team' => $team[0], 'title' => $team[1], 'leads' => $this->names($m[2])];
                $current = null;

                return;
            }
            if ($team = $this->team($line)) {
                $current = ['team' => $team[0], 'title' => $team[1], 'leads' => []];
                $tables[] = $current;

                return;
            }
            // Under a team's heading: the lines are its leads.
            if ($current !== null) {
                $tables[count($tables) - 1]['leads'] = [...$tables[count($tables) - 1]['leads'], ...$this->names($line)];
            }
        }
    }

    /**
     * The team a short text names: [key, title] — title only for "other" teams.
     *
     * @return array{0: string, 1: ?string}|null
     */
    private function team(string $text): ?array
    {
        $t = strtolower(trim(preg_replace('/\b(team|table|make\s+wordpress)\b/i', '', $text) ?? $text, " \t:–—-|()"));
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        if ($t === '' || mb_strlen($t) > 30) {
            return null;
        }
        if (isset(self::ALIASES[$t])) {
            return [self::ALIASES[$t], null];
        }
        if (in_array($t, self::OTHER_TEAMS, true)) {
            return ['other', ucwords($t)];
        }

        return null;
    }

    /** Name-shaped pieces of a text: "Leads: Asha Rao, Ravi Kumar & Neha" → three names. */
    private function names(string $text): array
    {
        $text = preg_replace('/^(?:table\s+)?leads?\s*[:\-–]?\s*/i', '', trim($text, " )(")) ?? $text;
        $parts = preg_split('/\s*(?:,|&|\band\b|\/|;|\|)\s*/iu', $text) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $part = trim($part, " .\t\"'()");
            // Two to four words, each starting with a capital letter (Indian and other names alike), no digits or links.
            if (preg_match('/^(?:\p{Lu}[\p{L}\'.-]*)(?:\s+\p{Lu}[\p{L}\'.-]*){0,3}$/u', $part) && ! preg_match('/https?:|@|\d/', $part) && mb_strlen($part) <= 60
                && $this->team($part) === null && ! in_array(strtolower($part), self::NOT_NAMES, true)) {
                $names[] = $part;
            }
        }

        return array_slice(array_values(array_unique($names)), 0, 8);
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)) ?? '');
    }
}
