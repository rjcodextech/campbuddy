<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Services\WordCampRestClient;
use App\Services\WordCampNormalizer;

$delhi = Event::where('slug', 'wordcamp-delhi-2026')->first();
$client = new WordCampRestClient($delhi->source_site_url);
$normalizer = new WordCampNormalizer($client);

$sponsors = $client->fetchSponsors();
$tiers = $client->fetchTaxonomyNames('sponsor_level');

// Fetch logos from /sponsors/
$res = \Illuminate\Support\Facades\Http::get('https://delhi.wordcamp.org/2026/sponsors/');
$crawler = new \Symfony\Component\DomCrawler\Crawler($res->body());

$pageLogos = [];
$crawler->filter('img')->each(function (\Symfony\Component\DomCrawler\Crawler $node) use (&$pageLogos) {
    $src = $node->attr('data-orig-file') ?: $node->attr('src');
    if (! $src || str_contains($src, 'Logo-1') || str_contains($src, 'Light-Logo') || str_contains($src, 'cropped-')) return;
    
    $parentLink = $node->closest('a');
    $href = $parentLink ? $parentLink->attr('href') : null;
    $alt = trim($node->attr('alt') ?? '');
    
    if ($href && preg_match('#/sponsor/([^/]+)/?#i', $href, $m)) {
        $pageLogos[$m[1]] = $src;
    }
    if ($alt) {
        $cleanName = strtolower(trim(preg_replace('/\s+logo$/i', '', $alt)));
        $pageLogos[$cleanName] = $src;
    }
});

echo "Total raw sponsors: " . count($sponsors) . "\n";
foreach ($sponsors as $sp) {
    $slug = $sp['slug'] ?? '';
    $name = strtolower(trim($sp['title']['rendered'] ?? ''));
    $logo = $pageLogos[$slug] ?? $pageLogos[$name] ?? null;
    echo "Sponsor: {$sp['title']['rendered']} | Slug: {$slug} | Found Logo: " . ($logo ?? 'NONE') . "\n";
}
