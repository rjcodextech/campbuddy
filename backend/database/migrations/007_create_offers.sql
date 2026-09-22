CREATE TABLE IF NOT EXISTS offers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    icon VARCHAR(10) NOT NULL DEFAULT '🏷',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_offers_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO offers (title, description, url, icon, sort_order, is_active, created_at, updated_at) VALUES
('WordPress.com', 'Host your site with the creators of WordPress', 'https://automattic.pxf.io/gOzQ4B', '🌐', 10, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('Pressable', 'High-performance WordPress hosting', 'https://automattic.pxf.io/gOzQ4B', '⚡', 20, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('WooCommerce', 'Build your online store with ease', 'https://automattic.pxf.io/gOzQ4B', '🛒', 30, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('Jetpack', 'Essential tools for WordPress sites', 'https://automattic.pxf.io/gOzQ4B', '🚀', 40, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('Hostinger', 'Affordable, reliable web hosting', 'https://www.hostinger.com/in?REFERRALCODE=1RJCODEX35', '🖥', 50, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
