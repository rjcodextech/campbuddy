<?php

declare(strict_types=1);

// CLI entry point for the cron job (see README for the cPanel cron setup).
// Fetches event + media data from the upstream API and writes it into the
// cache_store table, exactly like the read-path self-heal does — this is
// just the scheduled version of the same refresh.

require dirname(__DIR__) . '/vendor/autoload.php';

use CampBuddy\Cache\DbCache;
use CampBuddy\Domain\Event\EventFetcher;
use CampBuddy\Domain\Media\MediaFetcher;
use CampBuddy\Repository\AppSettingsRepository;
use CampBuddy\Repository\EventRepository;
use CampBuddy\Repository\FetchLogRepository;
use CampBuddy\Repository\MediaRepository;
use CampBuddy\Settings;
use CampBuddy\Support\Database;
use CampBuddy\Support\UpstreamClient;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$settings = Settings::fromEnv(dirname(__DIR__));

$logger = new Logger('campbuddy-cron');
$logger->pushHandler(new StreamHandler($settings->basePath . '/' . ltrim($settings->logPath, '/'), Level::Info));

$pdo = Database::connect($settings);
$client = new UpstreamClient($logger);
$fetchLog = new FetchLogRepository($pdo);

$eventRepo = new EventRepository(
    new DbCache($pdo),
    new EventFetcher($client, $settings),
    $fetchLog,
    $pdo,
    $settings,
    new AppSettingsRepository($pdo),
);
$mediaRepo = new MediaRepository(
    new DbCache($pdo),
    new MediaFetcher($client, $settings),
    $fetchLog,
    $settings,
);

$eventOk = $eventRepo->refreshNow() !== null;
$mediaOk = $mediaRepo->refreshNow() !== null;

echo 'Event refresh: ' . ($eventOk ? 'OK' : 'FAILED') . "\n";
echo 'Media refresh: ' . ($mediaOk ? 'OK' : 'FAILED') . "\n";

exit($eventOk && $mediaOk ? 0 : 1);
