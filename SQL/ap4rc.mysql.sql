-- DROP TABLE IF EXISTS application_passwords;
CREATE TABLE IF NOT EXISTS `application_passwords` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application` varchar(128) NOT NULL,
  `username` varchar(128) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT (now()),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_applications` (`username`,`application`),
  KEY `speedup_dovecot_index` (`username`,`created`,`password`)
) ROW_FORMAT=DYNAMIC ENGINE=INNODB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
