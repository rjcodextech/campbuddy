CREATE TABLE IF NOT EXISTS app_settings (
    name VARCHAR(60) NOT NULL,
    value TEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
