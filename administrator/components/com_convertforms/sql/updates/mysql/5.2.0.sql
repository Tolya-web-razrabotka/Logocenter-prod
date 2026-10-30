ALTER TABLE `#__convertforms_conversions`
  ADD COLUMN `ip`           varchar(45)   COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `state`,
  ADD COLUMN `country_code` varchar(5)    COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `ip`,
  ADD COLUMN `user_agent`   varchar(512)  COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `country_code`,
  ADD COLUMN `page_title`   varchar(512)  COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_agent`,
  ADD COLUMN `source_url`   varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `page_title`,
  ADD COLUMN `referrer_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `source_url`,
  ADD COLUMN `device`       varchar(20)   COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `referrer_url`,
  ADD COLUMN `os`           varchar(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `device`,
  ADD COLUMN `browser`      varchar(100)  COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `os`;

ALTER TABLE `#__convertforms_conversions`
  ADD KEY `ip`           (`ip`),
  ADD KEY `country_code` (`country_code`),
  ADD KEY `device`       (`device`),
  ADD KEY `os`           (`os`),
  ADD KEY `browser`      (`browser`);
