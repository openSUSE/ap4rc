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

class ourfun extends rcube_plugin
{
    # public $task = '(login|settings)';

    private $expire_interval;
    private $soon_expire_interval;
    private $new_password;
    private $password_save_success;
    private $password_save_error;

    public function init()
    {
        $this->load_config();
        $this->new_password = null;
        $this->password_save_error = null;
        $this->password_save_success = null;
        $this->add_hook('startup', array($this, 'startup'));
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();

        // needs to be in SQL format ....
        // that means units are without the plural "s
        // application_passwords_soon_expire_interval="7 WEEK"
        // application_passwords_expire_interval="2 MONTH"
        $this->soon_expire_interval = $rcmail->config->get('application_passwords_soon_expire_interval', "300 SECOND");
        $this->expire_interval      = $rcmail->config->get('application_passwords_expire_interval',      "3600 SECOND");
        if ($rcmail->get_dbh()->db_provider == 'postgres' ) {
          $this->soon_expire_interval = "'" . $this->soon_expire_interval . "'";
          $this->expire_interval      = "'" . $this->expire_interval . "'";
        }

        if ($args['task'] === 'settings') {
            $this->add_texts('localization/', !$this->api->output->ajax_call);
            $this->add_hook('settings_actions', array($this, 'settings_actions'));
            $this->register_action('plugin.ourfun', array($this, 'settings_view'));
            $this->register_action('plugin.ourfun-save', array($this, 'settings_save'));
            $this->register_action('plugin.ourfun-delete', array($this, 'settings_delete'));
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
              (COUNT(*) > 0) has_soon_expiring_passwords
            FROM $db_table
            WHERE
              `username` = ? AND
              (`created` < NOW() - INTERVAL $this->soon_expire_interval)
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
              (`created` < NOW() - INTERVAL $this->soon_expire_interval) AS `soon_expired`,
              (`created` < NOW() - INTERVAL $this->expire_interval) AS `expired`
            FROM $db_table
            WHERE
              `username` = ?
            ORDER BY
              `created`
",
        $rcmail->get_user_name());
        $attrib['id'] = 'ourfun-applications';

        $table = new html_table(array('cols' => 3));

        $table->add_header('name', $this->gettext('application'));
        $table->add_header('creation_date', $this->gettext('creation_date'));
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

           $table->add(null,       $record['application']);
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
        // TODO: implement password hashing here
        return $password;
    }

    public function settings_save()
    {
        // We need this password for the plugin.new_password hook
        $this->new_password = $this->random_password();
        $new_password = $this->hash_password($this->new_password);
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
        $new_password);
        // TODO: this will not catch the duplicate error. no idea yet.
        if ($result && !$db->affected_rows($result)) {
          $this->new_password = null;
          $this->password_save_error = $this->gettext('popup_generic_save_error');
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
      return preg_match('/[A-Za-z0-9._+-]+/', $application_name);
    }

    public function settings_view()
    {
        $this->register_handler('plugin.settingslist', array($this, 'settings_list'));
        $this->register_handler('plugin.new_password', array($this, 'show_new_password'));
        $this->register_handler('plugin.apppassadder', array($this, 'settings_apppassadder'));

        $this->include_script('ourfun.js');
        $this->include_stylesheet($this->local_skin_path() . '/ourfun.css');

        $this->api->output->add_label('save','cancel');
        $this->api->output->set_pagetitle($this->gettext('settingstitle'));
        $this->api->output->send('ourfun.config');
    }

    public function show_new_password () {
        if ($this->new_password) {
           return html::tag('div', array('id'=>'new_password'), $this->new_password);
        }
        if ($this->password_save_error) {
           return html::tag('div', array('id'=>'new_password_error'), $this->password_save_error);
        }
        if ($this->password_save_success) {
           $this->api->output->show_message($this->gettext('popup_successful_save'), 'error');
        }
    }

    public function settings_apppassadder($attrib)
    {
        $rcmail = rcmail::get_instance();
        $method = "save";
        $attrib['id'] = 'ourfun-add';

        $legend_description = html::tag('legend', null, rcmail::Q($this->gettext('new_application_step1_legend'))) . html::p(null, rcmail::Q($this->gettext('new_application_step1_description')));
        $form_label  = html::label('new_application_name', rcmail::Q($this->gettext('name_field')));
        $form_input  = html::tag('input', array('type' => 'text', 'id' => 'new_application_name', 'name' => 'new_application_name', 'size' => '36', 'value' => '', 'placeholder' => 'only use a-zA-Z0-9._+-', 'pattern' => "[A-Za-z0-9._+-]+", 'style' => "margin-right: 1em;"));
        $form_submit = html::tag('input', array('type' => 'submit', 'id' => 'ourfun-prop-save-button', 'class' => 'button mainaction save', 'value' => rcmail::Q($this->gettext('create_password'))));
        $form = $legend_description . $form_label . $form_input . $form_submit;

        $fieldset = html::tag('fieldset', null, $form );


        // $input_id = new html_hiddenfield(array('name' => '_prop[id]', 'value' => ''));
        $out .= html::tag('form', array(
                    'method' => 'post',
                    'action' => '?_task=settings&_action=plugin.ourfun-save',
                    'id'     => 'ourfun-prop-' . $method,
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
            'action' => 'plugin.ourfun',
            'class'  => 'ourfun',
            'label'  => 'settingslist',
            'title'  => 'settingstitle',
            'domain' => 'ourfun',
        );

        return $args;
    }

    private function random_password()
    {
        $length = 64;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.,!?(){}[]\/*^+%@-';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
