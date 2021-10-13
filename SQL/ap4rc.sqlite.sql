DROP TABLE IF EXISTS application_passwords;
CREATE TABLE IF NOT EXISTS application_passwords (
  id          serial,
  application VARCHAR(128) NOT NULL,
  username    VARCHAR(128) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  created     TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (username,application)
);
CREATE INDEX speedup_dovecot_index ON application_passwords(username, created, password);
