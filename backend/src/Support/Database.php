<?php

declare(strict_types=1);

namespace CampBuddy\Support;

use CampBuddy\Settings;
use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connect(Settings $settings): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $settings->dbHost,
            $settings->dbPort,
            $settings->dbDatabase,
            $settings->dbCharset
        );

        self::$connection = new PDO($dsn, $settings->dbUsername, $settings->dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
