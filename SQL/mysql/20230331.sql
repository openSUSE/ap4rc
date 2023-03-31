START TRANSACTION;

ALTER TABLE application_passwords  
  ADD COLUMN user_id INT UNSIGNED DEFAULT NULL;

UPDATE application_passwords
SET user_id =
    (
     SELECT users.user_id
     FROM users 
     WHERE users.username = application_passwords.username
    );

ALTER TABLE application_passwords
ADD CONSTRAINT user_id_fk_application_passwords
    FOREIGN KEY (user_id)
    REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE NO ACTION;

COMMIT;
