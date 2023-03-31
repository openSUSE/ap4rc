BEGIN TRANSACTION;

ALTER TABLE "application_passwords" ADD COLUMN user_id integer DEFAULT NULL;

UPDATE application_passwords
SET user_id =
    (
     SELECT users.user_id
     FROM users 
     WHERE users.username = application_passwords.username
    );

ALTER TABLE "application_passwords" ADD FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

COMMIT;
