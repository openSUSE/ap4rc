-- DROP TABLE IF EXISTS application_passwords;
CREATE TABLE IF NOT EXISTS application_passwords (
   id          INT AUTO_INCREMENT NOT NULL,
   application VARCHAR(128) NOT NULL,
   username    VARCHAR(128) NOT NULL,
   password    VARCHAR(255) NOT NULL,
   created     DATETIME DEFAULT(NOW()) NOT NULL,
   PRIMARY KEY (id),
   CONSTRAINT unique_applications UNIQUE (`username`,`application`)
) engine=InnoDB default CHARSET utf8mb4;
CREATE OR REPLACE INDEX speedup_dovecot_index ON application_passwords(`username`, `created`, `password`);
