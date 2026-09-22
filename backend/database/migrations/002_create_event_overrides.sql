CREATE TABLE IF NOT EXISTS event_overrides (
    id TINYINT UNSIGNED NOT NULL,
    data LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    updated_by VARCHAR(120) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-row table (id=1) of admin-set field overrides, applied on top of
-- the live-fetched event, mirroring Object.assign(EVENT, override) in app.js.
INSERT IGNORE INTO event_overrides (id, data, updated_at) VALUES (1, '{}', UTC_TIMESTAMP());
