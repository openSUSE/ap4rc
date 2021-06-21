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

        $application_passwords = array("hello world" => array('name' => "Hello world", 'created' => "2021-06-01", 'active'=>true));
        $this->api->output->set_env('application_passwords', !empty($application_passwords) ? $application_passwords : null);

        return $table->show($attrib);
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
        $form_label = html::label('name', rcmail::Q($this->gettext('name_field')));
        $form_input  = html::tag('input', array('type' => 'text', 'id' => 'new_application_name', 'name' => 'new_application_name', 'size' => '36', 'value' => '', 'placeholder' => 'only use a-zA-Z0-9._+-', 'pattern' => "[A-Za-z0-9._+-]+", 'style' => "margin-right: 1em;"));
        $form_submit = html::tag('input', array('type' => 'submit', 'id' => '', 'class' => 'button mainaction', 'value' => rcmail::Q($this->gettext('create_password'))));
        $form = $legend_description . $form_label . $form_input . $form_submit;

        $fieldset = html::tag('fieldset', null, $form );


        // $input_id = new html_hiddenfield(array('name' => '_prop[id]', 'value' => ''));
        $out .= html::tag('form', array(
                    'method' => 'post',
                    'action' => '#',
                    'id'     => 'ourfun-prop-' . $method,
                    'style'  => 'display:none',
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
}
