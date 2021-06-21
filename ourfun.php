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
    public function init()
    {
        $this->load_config();
        $this->add_hook('startup', array($this, 'startup'));
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();
        if ($args['task'] === 'settings') {
            $this->add_texts('localization/', !$this->api->output->ajax_call);
            $this->add_hook('settings_actions', array($this, 'settings_actions'));
            $this->register_action('plugin.ourfun', array($this, 'settings_view'));
        }
        else {
            // TODO: register handler here to show the warning about passwords that will expire soon
            // $this->api->output->show_message("MFA is enforced you need to have at least one 2nd factor configured. Current number of configured MFA tokens: " . $factors_count, 'error');

       }
       return $args;
    }

    public function settings_list($attrib = array())
    {
        $attrib['id'] = 'ourfun-applications';
        $table = new html_table(array('cols' => 3));

        $table->add_header('name', $this->gettext('application'));
        $table->add_header('created', $this->gettext('created'));
        $table->add_header('actions', '');

        return $table->show($attrib);
    }


    public function settings_view()
    {
        $this->register_handler('plugin.settingsform', array($this, 'settings_form'));
        $this->register_handler('plugin.settingslist', array($this, 'settings_list'));
        $this->register_handler('plugin.apppassadder', array($this, 'settings_appppassadder'));

        $this->include_script('ourfun.js');
        $this->include_stylesheet($this->local_skin_path() . '/ourfun.css');

        $this->api->output->add_label('save','cancel');
        $this->api->output->set_pagetitle($this->gettext('settingstitle'));
        $this->api->output->send('ourfun.config');
    }

    public function settings_apppassadder($attrib)
    {
        $rcmail = rcmail::get_instance();

        $attrib['id'] = 'ourfun-add';


        return html::div(array('id' => 'ourfunpropform'), "Here shall add button");
    }

    public function settings_form($attrib = array())
    {
        $rcmail = rcmail::get_instance();
        // TODO: feed the passwords in
        $application_passwords = array('application' => "Hello world", 'created' => "2021-06-01");
        $this->api->output->set_env('application_passwords', !empty($application_passwords) ? $application_passwords : null);

        return html::div(array('id' => 'ourfunpropform'), "Here shall be form");

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
}
