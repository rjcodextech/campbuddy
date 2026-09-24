<?php

namespace App\Http\Controllers;

use App\Support\FirstTimerGuide;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The files crawlers and AI assistants look for: /robots.txt, /sitemap.xml,
 * /llms.txt, and the IndexNow key file. Built from live data so a new
 * WordCamp is discoverable the moment it goes live, with nobody editing a
 * file by hand.
 */
class SeoController extends Controller
{
    private const CACHE_SECONDS = 3600;

    public function robots(): Response
    {
        // Only production is for search engines; a staging copy must never
        // compete with (or leak ahead of) the real site.
        if (! app()->isProduction()) {
            return $this->text("User-agent: *\nDisallow: /\n");
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /event/*/roster-removal',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return $this->text(implode("\n", $lines));
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('seo:sitemap', self::CACHE_SECONDS, function () {
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach (Seo::urls() as $url) {
                $out .= '  <url><loc>'.e($url['loc']).'</loc>';
                if ($url['lastmod']) {
                    $out .= '<lastmod>'.e($url['lastmod']).'</lastmod>';
                }
                $out .= '<changefreq>'.$url['changefreq'].'</changefreq><priority>'.$url['priority'].'</priority></url>'."\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * llms.txt (llmstxt.org): a plain summary for AI assistants and answer
     * engines — what CampBuddy is, the WordCamps on it, and the answers
     * newcomers ask for most.
     */
    public function llms(): Response
    {
        $body = Cache::remember('seo:llms', self::CACHE_SECONDS, function () {
            $lines = [
                '# '.config('campbuddy.name'),
                '',
                '> '.config('campbuddy.pwa.description').' Free, no sign-up, works offline. Made for first-time WordCamp attendees, students and regulars.',
                '',
                'A WordCamp is a community-organized conference about WordPress, run by local volunteers. CampBuddy shows each WordCamp\'s schedule (read from that WordCamp\'s own website), what\'s happening now, sponsors, a Contributor Day team finder, and a friendly guide for first-timers.',
                '',
                '## Guides',
                '',
                '- ['.'Beginner\'s guide to WordCamp]('.route('guide').'): what happens during the day, the words people use, tips for students, what to bring, and FAQ.',
                '',
            ];

            $events = Seo::publicEvents();
            if ($events->isNotEmpty()) {
                $lines[] = '## WordCamps';
                $lines[] = '';
                foreach ($events as $event) {
                    $meta = collect([Seo::dates($event), Seo::venue($event)])->filter()->implode(', ');
                    $lines[] = '- ['.$event->display_name.']('.route('event.home', $event).')'.($meta ? ": {$meta}" : '')
                        .'. Schedule: '.route('event.my-day', $event)
                        .' · First-timer guide: '.route('event.guide', $event);
                }
                $lines[] = '';
            }

            $lines[] = '## Quick answers';
            $lines[] = '';
            foreach ([...FirstTimerGuide::quickQuestions(), ...FirstTimerGuide::faq()] as $qa) {
                $lines[] = '- **'.$qa['q'].'** '.$qa['a'];
            }
            $lines[] = '';

            return implode("\n", $lines);
        });

        return $this->text($body, 'text/markdown');
    }

    /** IndexNow proves we own the site with this file (see campbuddy:indexnow). */
    public function indexNowKey(string $key): Response
    {
        abort_unless(hash_equals(Seo::indexNowKey(), $key), 404);

        return $this->text($key);
    }

    private function text(string $body, string $type = 'text/plain'): Response
    {
        return response($body, 200, [
            'Content-Type' => "{$type}; charset=UTF-8",
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
