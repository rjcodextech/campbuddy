<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use CampBuddy\Settings;
use CampBuddy\Support\Database;

$settings = Settings::fromEnv(dirname(__DIR__));
$pdo = Database::connect($settings);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename VARCHAR(191) NOT NULL,
        applied_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_filename (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$dir = dirname(__DIR__) . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Could not read {$name}\n");
        exit(1);
    }

    // MySQL DDL statements (CREATE TABLE, etc.) implicitly commit, so a
    // wrapping transaction can't atomically roll them back — migrations are
    // applied statement-by-statement instead, each one independently safe
    // to re-run (IF NOT EXISTS / INSERT IGNORE).
    try {
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
        $stmt = $pdo->prepare('INSERT INTO migrations (filename, applied_at) VALUES (:filename, UTC_TIMESTAMP())');
        $stmt->execute(['filename' => $name]);
        echo "Applied {$name}\n";
        $ran++;
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed on {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo $ran > 0 ? "Done, {$ran} migration(s) applied.\n" : "Already up to date.\n";
