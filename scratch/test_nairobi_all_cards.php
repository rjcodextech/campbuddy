<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$res = Http::get('https://nairobi.wordcamp.org/2026/speakers/');
$crawler = new Crawler($res->body());

$cards = $crawler->filter('div[class*="spk-card"]');
echo "Found " . $cards->count() . " speaker cards on Nairobi!\n";

$cards->each(function (Crawler $card, $i) {
    $title = $card->filter('h2, h3, h4')->first();
    $name = $title->count() > 0 ? trim($title->text()) : '';
    $img = $card->filter('img')->first();
    $src = $img->count() > 0 ? ($img->attr('data-orig-file') ?: $img->attr('src')) : 'NONE';
    $role = $card->filter('p[class*="role"]')->first();
    $roleText = $role->count() > 0 ? trim($role->text()) : '';
    $session = $card->filter('p[class*="session"]')->first();
    $sessionText = $session->count() > 0 ? trim($session->text()) : '';
    
    echo sprintf("[%2d] %-22s | Role: %-25s | Session: %-30s | Img: %s\n", $i + 1, $name, substr($roleText, 0, 25), substr($sessionText, 0, 30), $src);
});
