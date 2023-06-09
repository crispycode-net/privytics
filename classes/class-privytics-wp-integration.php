<?php

class PrivyticsWPIntegration
{
    const ID = 'privytics';

    private $current_page = '';

    protected $views = array(
        'sankey' => 'views/sankey',
        'settings' => 'views/settings',
        'not-found' => 'views/not-found'
    );

    public function get_id()
    {
        return self::ID;
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_menu_page'), 20);
    }

    function add_menu_page()
    {
        add_menu_page(
            'Privytics',
            'Privytics',
            'Privytics',
            $this->get_id(),
            array(&$this, 'load_view'),
            'dashicons-admin-page'
        );

        add_submenu_page(
            $this->get_id(),
            'User Flow',
            'User Flow',
            'manage_options',
            $this->get_id() . '_sankey',
            array(&$this, 'load_view')
        );

        add_submenu_page(
            $this->get_id(),
            'Settings',
            'Settings',
            'manage_options',
            $this->get_id() . '_settings',
            array(&$this, 'load_view')
        );
    }

    function load_view()
    {
        $this->current_page = ct_admin_current_view();
        
        $current_views = isset($this->views[$this->current_page]) ? $this->views[$this->current_page] : $this->views['not-found'];

        $step_data_func_name = $this->current_page . '_data';

        $args = [];
        /**
         * prepare data for view
         */
        if (method_exists($this, $step_data_func_name)) {
            $args = $this->$step_data_func_name();
        }
        /**
         * Default Admin Form Template
         */

        echo '<div class="ct-admin-forms ' . $this->current_page . '">';

        echo '<div class="container container1">';
        echo '<div class="inner">';

        $this->includeWithVariables(ct_admin_template_server_path('views/alerts', false));

        $this->includeWithVariables(ct_admin_template_server_path($current_views, false), $args);

        echo '</div>';
        echo '</div>';

        echo '</div> <!-- / ct-admin-forms -->';
    }

    function includeWithVariables($filePath, $variables = array(), $print = true)
    {
        $output = NULL;
        if (file_exists($filePath)) {
            // Extract the variables to a local namespace
            extract($variables);

            // Start output buffering
            ob_start();

            // Include the template file
            include $filePath;

            // End buffering and return its contents
            $output = ob_get_clean();
        }
        if ($print) {
            print $output;
        }
        return $output;

    }
}
