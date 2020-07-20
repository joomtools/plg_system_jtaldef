CREATE TABLE IF NOT EXISTS `#__jtaldef` (
  `original_url_id` varchar(32) NOT NULL COMMENT 'MD5 hash from Url of original CSS file.',
  `cache_url` varchar(2048) NOT NULL COMMENT 'Url to CSS file.',
  UNIQUE KEY `original_url_id` (`original_url_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

