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

    public function init()
    {
        $this->load_config();
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
        $this->expire_interval      = $rcmail->config->get('application_passwords_expire_interval',      "900 SECOND");

        if ($args['task'] === 'settings') {
            $this->add_texts('localization/', !$this->api->output->ajax_call);
            $this->add_hook('settings_actions', array($this, 'settings_actions'));
            $this->register_action('plugin.ourfun', array($this, 'settings_view'));
            $this->register_action('plugin.ourfun-save', array($this, 'settings_save'));
        }
        else {
            // TODO: register handler here to show the warning about passwords that will expire soon
            // $this->api->output->show_message("MFA is enforced you need to have at least one 2nd factor configured. Current number of configured MFA tokens: " . $factors_count, 'error');

       }
       return $args;
    }

    public function settings_list($attrib = array())
    {
        $rcmail = rcmail::get_instance();
        $attrib['id'] = 'ourfun-applications';
        $table = new html_table(array('cols' => 3));

        $table->add_header('name', $this->gettext('application'));
        $table->add_header('created', $this->gettext('created'));
        $table->add_header('actions', '');

        $application_passwords = array();

        $db       = $rcmail->get_dbh();
        $db_table = $db->table_name('application_passwords', true);
        $result = $db->query( "
          SELECT
              `application`,
              `created`,
              (`created` >= NOW() - INTERVAL '$this->soon_expire_interval') AS `soon_expired`,
              (`created` >= NOW() - INTERVAL '$this->expire_interval') AS `expired`
            FROM $db_table
            WHERE
              `username` = ?
            ORDER BY
              `created`
",
        $rcmail->get_user_name());
        while ($record = $db->fetch_assoc($result)) {
           $application_passwords[$record['application']] = array(
              'name'         => $record['application'],
              'created'      => $record['created'],
              'expired'      => $record['expired']      != 1,
              'soon_expired' => $record['soon_expired'] != 1,
              'active' => true
           );
        }

        $this->api->output->set_env('application_passwords', !empty($application_passwords) ? $application_passwords : null);

        return $table->show($attrib);
    }

    public function settings_save()
    {
        $new_password = $this->random_password();
        $application  = rcube_utils::get_input_value("new_application_name", rcube_utils::INPUT_POST);
        if (!($this->verify_application_name($application))) {
           $this->api->output->show_message("Error while saving your password", 'error');
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
        if ( $db->affected_rows($insert) === 1) {
           $this->api->output->show_message("Your new password is: " . $new_password, 'error');
        }
        else {
           $this->api->output->show_message("Error while saving your password", 'error');
        }
    }

    private function verify_application_name($application_name) {
      return preg_match('/[A-Za-z0-9._+-]+/', $application_name);
    }

    public function settings_view()
    {
        $this->register_handler('plugin.settingslist', array($this, 'settings_list'));
        $this->register_handler('plugin.apppassadder', array($this, 'settings_apppassadder'));

        $this->include_script('ourfun.js');
        $this->include_stylesheet($this->local_skin_path() . '/ourfun.css');

        $this->api->output->add_label('save','cancel');
        $this->api->output->set_pagetitle($this->gettext('settingstitle'));
        $this->api->output->send('ourfun.config');
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
                    // 'action' => '#',
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
        // TODO: implement password hashing
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
