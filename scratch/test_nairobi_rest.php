<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = new \App\Services\WordCampRestClient('https://nairobi.wordcamp.org/2026');
$sponsors = $c->fetchSponsors();
$n = new \App\Services\WordCampNormalizer($c);
$norm = $n->normalizeSponsors($sponsors, []);
foreach ($norm as $s) {
    echo $s['name'] . ' -> logo: ' . ($s['logo_url'] ?? 'NULL') . "\n";
}
