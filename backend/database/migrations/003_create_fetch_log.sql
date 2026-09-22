CREATE TABLE IF NOT EXISTS fetch_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source VARCHAR(20) NOT NULL,
    status VARCHAR(10) NOT NULL,
    message VARCHAR(500) NULL,
    fetched_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fetch_log_source_fetched_at (source, fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
