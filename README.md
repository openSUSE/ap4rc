# Application Passwords for roundcube

Heavily inspired by the code from kolab's 2FA plugin

## Settings:


### Intervals

Intervals are using the SQL syntax. So if you want to have a password expire in
2 months and get a warning 1 week before:

```php
$config['ap4rc_expire_interval'] = "2 MONTH";
$config['ap4rc_warning_interval'] = "1 WEEK";

`ap4rc_expire_interval`

How long an application password should be valid
Default: `2 MONTH`

`ap4rc_warning_interval`

The interval before the expiry date is reached that the roundcube webui
will warn you about expiring password:

Default: `1 WEEK`

### Other settings

`ap4rc_generated_password_length`

How long should generated passwords be?

Default: `64`

`ap4rc_application_name_characters`

Which characters are allowed in an application name.

Default: `a-zA-Z0-9._+-`
