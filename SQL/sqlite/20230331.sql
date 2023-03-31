PRAGMA foreign_keys = OFF;

BEGIN TRANSACTION;

CREATE TABLE temp_application_passwords (
  id          serial,
  application VARCHAR(128) NOT NULL,
  username    VARCHAR(128) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  created     TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  user_id     integer DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE (username,application)
);

INSERT INTO temp_application_passwords (id, application, username, password, created)
  SELECT id, application, username, password, created FROM application_passwords;
 
UPDATE temp_application_passwords
   SET user_id = users.user_id
  FROM (SELECT users.user_id FROM users)
 WHERE users.username = temp_application_passwords.username;

DELETE FROM temp_application_passwords WHERE user_id NOT IN (SELECT user_id FROM users);

CREATE TABLE application_passwords_new (
  id          serial,
  application VARCHAR(128) NOT NULL,
  username    VARCHAR(128) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  created     TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  user_id     integer DEFAULT NULL
    REFERENCES users (user_id) ON DELETE CASCADE,
  PRIMARY KEY (id),
  UNIQUE (username,application)
);

INSERT INTO application_passwords_new SELECT * FROM temp_application_passwords;
DROP INDEX speedup_dovecot_index;
DROP TABLE application_passwords;

ALTER TABLE application_passwords_new RENAME TO application_passwords;
CREATE INDEX speedup_dovecot_index ON application_passwords(username, created, password);

COMMIT;

PRAGMA foreign_keys = ON;