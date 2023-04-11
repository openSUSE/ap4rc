-- DROP TABLE IF EXISTS application_passwords;
CREATE TABLE `application_passwords` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application` varchar(128) NOT NULL,
  `username` varchar(128) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT (now()),
  `user_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_applications` (`username`,`application`),
  KEY `speedup_dovecot_index` (`username`,`created`,`password`),
  KEY `user_id_fk_application_passwords` (`user_id`),
  CONSTRAINT `user_id_fk_application_passwords` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ROW_FORMAT=DYNAMIC ENGINE=INNODB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
