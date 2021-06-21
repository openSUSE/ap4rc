<?php
class ourfun extends rcube_plugin
{
    # public $task = '(login|settings)';
    public function init()
    {
        $this->load_config();
        $this->add_texts('localization/', !$this->api->output->ajax_call);
        $this->add_hook('settings_actions', array($this, 'settings_actions'));
        $this->register_action('plugin.ourfun', array($this, 'settings_view'));
    }

    public function settings_list($attrib = array())
    {
        $attrib['id'] = 'ourfun-factors';
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
