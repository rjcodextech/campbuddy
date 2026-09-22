<?php

declare(strict_types=1);

// CLI to create/reset the single admin account.
// Usage: php bin/create-admin.php <username>
// It will prompt for a password (not echoed) rather than taking it as an
// argument, so it never ends up in shell history.

require dirname(__DIR__) . '/vendor/autoload.php';

use CampBuddy\Repository\AdminUserRepository;
use CampBuddy\Settings;
use CampBuddy\Support\Database;

$username = trim((string) ($argv[1] ?? ''));
if ($username === '') {
    fwrite(STDERR, "Usage: php bin/create-admin.php <username>\n");
    exit(1);
}

fwrite(STDOUT, "Password: ");
if (stripos(PHP_OS, 'WIN') === 0) {
    // No portable "hide input" on Windows CLI without extra tooling —
    // warn instead of silently echoing.
    fwrite(STDOUT, "(warning: input will be visible on Windows)\n");
    $password = trim((string) fgets(STDIN));
} else {
    system('stty -echo');
    $password = trim((string) fgets(STDIN));
    system('stty echo');
    fwrite(STDOUT, "\n");
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$settings = Settings::fromEnv(dirname(__DIR__));
$pdo = Database::connect($settings);
(new AdminUserRepository($pdo))->create($username, $password);

echo "Admin user '{$username}' created/updated.\n";
