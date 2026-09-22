CREATE TABLE IF NOT EXISTS rate_limit_hits (
    bucket_key VARCHAR(191) NOT NULL,
    window_start DATETIME NOT NULL,
    count INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
