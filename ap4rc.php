<?php
/**
 * Application Passwords handling for roundcube
 *
 * @author darix & jdsn
 *
 * @licstart
 *
 * Copyright (C) 2021 SUSE LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @licend
 */

class ap4rc extends rcube_plugin
{
    # public $task = '(login|settings)';

    private $expire_interval;
    private $warning_interval;
    private $new_password;
    private $new_application;
    private $password_save_success;
    private $password_save_error;
    private $application_name_characters;
    private $generated_password_length;

    public function init()
    {
        $this->load_config();
        $this->new_password = null;
        $this->new_application = null;
        $this->password_save_error = null;
        $this->password_save_success = null;
        $this->application_name_characters = null;
        $this->generated_password_length = 64;
        $this->add_hook('startup', array($this, 'startup'));
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();

        $this->application_name_characters = $rcmail->config->get('ap4rc_application_name_characters', "a-zA-Z0-9._+-");
        $this->generated_password_length   = $rcmail->config->get('ap4rc_generated_password_length', 64);

        // needs to be in SQL format ....
        // that means units are without the plural "s
        // ap4rc_warning_interval="1 WEEK"
        // ap4rc_expire_interval="2 MONTH"
        $this->warning_interval            = $rcmail->config->get('ap4rc_warning_interval', "1 WEEK");
        $this->expire_interval             = $rcmail->config->get('ap4rc_expire_interval',  "2 MONTH");

        if ($rcmail->get_dbh()->db_provider == 'postgres' ) {
          $this->warning_interval = "'" . $this->warning_interval . "'";
          $this->expire_interval      = "'" . $this->expire_interval . "'";
        }

        $this->add_texts('localization/', !$this->api->output->ajax_call);
        if ($args['task'] === 'settings') {
            $this->add_hook('settings_actions', array($this, 'settings_actions'));
            $this->register_action('plugin.ap4rc', array($this, 'settings_view'));
            $this->register_action('plugin.ap4rc-save', array($this, 'settings_save'));
            $this->register_action('plugin.ap4rc-delete', array($this, 'settings_delete'));
        }
        else {
            if ($this->has_passwords_expiring_soon()) {
                $this->api->output->show_message($this->gettext('popup_expire_soon'), 'error');
            }
       }
       return $args;
    }


    private function has_passwords_expiring_soon() {
        $rcmail = rcmail::get_instance();
        $db       = $rcmail->get_dbh();
        $db_table = $db->table_name('application_passwords', true);
        $result = $db->query( "
          SELECT
              (COUNT(*) > 0) AS has_soon_expiring_passwords
            FROM $db_table
            WHERE
              `username` = ? AND
              (`created` < NOW() - INTERVAL $this->expire_interval + INTERVAL $this->warning_interval )
",
        $rcmail->get_user_name());
        $record = $db->fetch_assoc($result);
        return (!!$record['has_soon_expiring_passwords']);
    }

    public function settings_list($attrib = array())
    {
        $rcmail = rcmail::get_instance();
        $application_passwords = array();

        $db       = $rcmail->get_dbh();
        $db_table = $db->table_name('application_passwords', true);
        $result = $db->query( "
          SELECT
              `id`,
              `application`,
              `created`,
              (`created` + INTERVAL $this->expire_interval) AS `expiry`,
              (`created` < NOW() - INTERVAL $this->expire_interval + INTERVAL $this->warning_interval) AS `soon_expired`,
              (`created` < NOW() - INTERVAL $this->expire_interval) AS `expired`
            FROM $db_table
            WHERE
              `username` = ?
            ORDER BY
              `created`
",
        $rcmail->get_user_name());
        $attrib['id'] = 'ap4rc-applications';

        $table = new html_table(array('cols' => 3));

        $table->add_header('name', $this->gettext('new_username'));
        // $table->add_header('creation_date', $this->gettext('creation_date'));
        $table->add_header('expiry_date', $this->gettext('expiry_date'));
        $table->add_header('actions', '');

        while ($record = $db->fetch_assoc($result)) {
           $table->add_row();

           $css_class = array('class' => 'created');
           if (!!$record['soon_expired']) {
             $css_class['class'] = 'soon_expired';
           }
           if (!!$record['expired']) {
             $css_class['class'] = 'expired';
           }

           $table->add(null,       $this->application_username($record['application']));
           // $table->add(null,       $record['created']);
           $table->add($css_class, $record['expiry']);
           $delete_link = html::tag('a',
             array(
               'class' => 'button icon delete',
               'rel'=>$record['id'],
               'href' => '#'
             ),
             html::tag('span', null, $this->gettext('remove'))
           );
           $table->add(array('class' => 'actions buttons-cell'), $delete_link);
           /*
           $application_passwords[$record['application']] = array(
              'name'         => $record['application'],
              'created'      => $record['created'],
              'expiry'       => $record['expiry'],
              'expired'      => !!$record['expired'],
              'soon_expired' => !!$record['soon_expired'],
              'active' => true
           );
           */
        }

        $this->api->output->set_env('application_passwords', !empty($application_passwords) ? $application_passwords : null);

        return $table->show($attrib);
    }

    private function hash_password($password) {
        // TODO: implement dovecot password hashing here
        // see PR from postfixadmin https://github.com/postfixadmin/postfixadmin/pull/491
        // for now we use the same method as the old plugin
        return hash('sha512', $password);
    }

    public function settings_save()
    {
        // We need this password for the plugin.new_password hook
        $new_password = $this->random_password();
        $hashed_password = $this->hash_password($new_password);
        $this->password_save_error = $this->gettext('popup_generic_save_error');

        $application  = rcube_utils::get_input_value("new_application_name", rcube_utils::INPUT_POST);
        if (!($this->verify_application_name($application))) {
           $this->api->output->show_message($this->gettext('popup_format_save_error'), 'error');
           return;
        }
        $rcmail = rcmail::get_instance();
        $db       = $rcmail->get_dbh();
        $db_table = $db->table_name('application_passwords', true);
        $result = $db->query( "
          INSERT INTO $db_table
              (
                `username`,
                `application`,
                `password`
              )
              VALUES (?,?,?)
        ",
        $rcmail->get_user_name(),
        $application,
        $hashed_password);

        // This code will only be reached if we did not see a duplicate entry exception
        if ($result && $db->affected_rows($result) > 0) {
          $this->new_password = $new_password;
          $this->new_application = $application;
          $this->password_save_error = null;
        }
        return $this->settings_view();
    }

    public function settings_delete()
    {
        $application_id = rcube_utils::get_input_value("remove_id", rcube_utils::INPUT_POST);
        $rcmail = rcmail::get_instance();
        $db       = $rcmail->get_dbh();
        $db_table = $db->table_name('application_passwords', true);
        $result = $db->query( "DELETE FROM $db_table WHERE id = ?", $application_id);
        if ($result && !$db->affected_rows($result)) {
          $this->password_save_error = $this->gettext('popup_generic_save_error');
        }
        else {
          $this->password_save_success = $this->gettext('popup_successful_deletion');
        }
        return $this->settings_view();
    }

    private function verify_application_name($application_name) {
      return preg_match('/[' . $this->application_name_characters . ']+/', $application_name);
    }

    public function settings_view()
    {
        $this->register_handler('plugin.settingslist', array($this, 'settings_list'));
        $this->register_handler('plugin.new_password', array($this, 'show_new_password'));
        $this->register_handler('plugin.apppassadder', array($this, 'settings_apppassadder'));

        $this->include_script('ap4rc.js');
        $this->include_stylesheet($this->local_skin_path() . '/ap4rc.css');

        $this->api->output->add_label('save','cancel');
        $this->api->output->set_pagetitle($this->gettext('settingstitle'));
        $this->api->output->send('ap4rc.config');
    }

    public function show_new_password () {
        if ($this->new_password) {
           $important_style = array('style' => 'font-weight: bold;');
           return html::tag('div', null,
               html::p($important_style,
                   $this->gettext('please_use_username') .
                   html::br() .
                   $this->gettext('new_application_copy') . " " .
                   $this->gettext('new_application_once')
               )
            ) .
            html::tag('div',  null,
               html::tag('dl', null,
                 html::tag('dt', $important_style, $this->gettext('new_username')) .
                   html::tag('dd', array('id'=>'new_username', 'class'=>'ap4rc-copy'), $this->application_username($this->new_application)) .
                 html::tag('dt', $important_style, $this->gettext('new_password')) .
                   html::tag('dd', array('id'=>'new_password', 'class'=>'ap4rc-copy'), $this->new_password)
               )
           );
        }
        if ($this->password_save_error) {
           return html::tag('div', array('id'=>'new_password_error'), $this->password_save_error);
        }
        if ($this->password_save_success) {
           $this->api->output->show_message($this->gettext('popup_successful_save'), 'error');
        }
    }

    private function application_username($appname) {
        $rcmail = rcmail::get_instance();
        return $rcmail->get_user_name() . '@' . $appname;
    }

    public function settings_apppassadder($attrib)
    {
        $rcmail = rcmail::get_instance();
        $method = "save";
        $attrib['id'] = 'ap4rc-add';

        $legend_description = html::tag('legend', null, rcmail::Q($this->gettext('settingstitle'))) .
                html::p(null, rcmail::Q($this->gettext('new_application_description')) . html::br() .
                              rcmail::Q($this->gettext('only_delete_one_password')) . html::br() .
                              rcmail::Q($this->gettext('only_valid_characters')) . " " . html::tag('code', null, $this->application_name_characters ));
        $form_label  = html::label('new_application_name', rcmail::Q($this->gettext('name_field')));
        $form_input  = html::tag('input', array('type' => 'text', 'id' => 'new_application_name', 'name' => 'new_application_name', 'size' => '36', 'value' => '', 'placeholder' => $this->gettext('only_use') . " " . $this->application_name_characters, 'pattern' => "[" . $this->application_name_characters . "]+", 'style' => "margin-right: 1em;"));
        $form_submit = html::tag('input', array('type' => 'submit', 'id' => 'ap4rc-prop-save-button', 'class' => 'button mainaction save', 'value' => rcmail::Q($this->gettext('create_password'))));
        $form = $legend_description . $form_label . $form_input . $form_submit;

        $fieldset = html::tag('fieldset', null, $form );


        // $input_id = new html_hiddenfield(array('name' => '_prop[id]', 'value' => ''));
        $out .= html::tag('form', array(
                    'method' => 'post',
                    'action' => '?_task=settings&_action=plugin.ap4rc-save',
                    'id'     => 'ap4rc-prop-' . $method,
                    'class'  => 'propform',
                ),
                $fieldset
            );

        return $out;
    }


    public function settings_actions($args)
    {
        // register as settings action
        $args['actions'][] = array(
            'action' => 'plugin.ap4rc',
            'class'  => 'ap4rc',
            'label'  => 'settingslist',
            'title'  => 'settingstitle',
            'domain' => 'ap4rc',
        );

        return $args;
    }

    private function random_password()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.,!?(){}[]\/*^+%@-';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $this->generated_password_length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
