<?php
/*
 * Plugin Name: EPFL Accred
 * Description: Access control from EPFL Accred
 * Version:     0.1
 * Author:      Dominique Quatravaux
 * Author URI:  mailto:dominique.quatravaux@epfl.ch
 */

namespace AccredAccessControl;
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}
Controller::getInstance()->hook();

class Controller {
	static $instance = false;
    var $settings = null;

    public function __construct () {
        $this->settings = new Settings();
    }

	public static function getInstance () {
		if ( !self::$instance ) {
            self::$instance = new self;
		}
		return self::$instance;
	}

    function hook() {
        $this->settings->hook();
    }
}

class Settings {
    function hook() {
        add_action('admin_init', array($this, 'action_admin_init') );
        add_action('admin_menu', array($this, 'action_admin_menu') );
    }

    public function get($name, $default = false, $use_cache = true) {
        if ( $this->is_network_version() ) {
            return get_site_option($name, $default, $use_cache);
        } else {
            return get_option($name, $default);
        }
    }

	function action_admin_init () {
        // Use the settings API rather than writing our own <form>s and
        // validators therefor.
        // More at https://wordpress.stackexchange.com/a/100137
        $option_name   = 'plugin:epfl-accred';

        // Fetch existing options.
        $option_values = $this->get( $option_name );

        $default_values = array (
            'number' => 500,
            'color'  => 'blue',
            'long'   => ''
        );

        // Parse option values into predefined keys, throw the rest away.
        $data = shortcode_atts( $default_values, $option_values );

        register_setting(
            'plugin:epfl-accred-optiongroup', // group, used for settings_fields()
            $option_name,  // option name, used as key in database
            array($this, 'validate_option')      // validation callback
        );

        /* No argument has any relation to the prvious register_setting(). */
        add_settings_section(
            'section_1', // ID
            'Some text fields', // Title
            array($this, 'render_section_1'), // print output
            'epfl_accred_slug' // menu slug, see action_admin_menu()
        );

        add_settings_field(
            'section_1_field_1',
            'A Number',
            array($this, 'render_section_1_field_1'),
            'epfl_accred_slug',  // menu slug, see action_admin_menu()
            'section_1',
            array (
                'label_for'   => 'label1', // makes the field name clickable,
                'name'        => 'number', // value for 'name' attribute
                'value'       => esc_attr( $data['number'] ),
                'option_name' => $option_name
            )
        );
        add_settings_field(
            'section_1_field_2',
            'Select',
            array($this, 'render_section_1_field_2'),
            'epfl_accred_slug',  // menu slug, see action_admin_menu()
            'section_1',
            array (
                'label_for'   => 'label2', // makes the field name clickable,
                'name'        => 'color', // value for 'name' attribute
                'value'       => esc_attr( $data['color'] ),
                'options'     => array (
                    'blue'  => 'Blue',
                    'red'   => 'Red',
                    'black' => 'Black'
                ),
                'option_name' => $option_name
            )
        );

        add_settings_section(
            'section_2', // ID
            'Textarea', // Title
            array($this, 'render_section_2'), // print output
            'epfl_accred_slug' // menu slug, see action_admin_menu()
        );

        add_settings_field(
            'section_2_field_1',
            'Notes',
            array($this, 'render_section_2_field_1'),
            'epfl_accred_slug',  // menu slug, see action_admin_menu()
            'section_2',
            array (
                'label_for'   => 'label3', // makes the field name clickable,
                'name'        => 'long', // value for 'name' attribute
                'value'       => esc_textarea( $data['long'] ),
                'option_name' => $option_name
            )
        );
    }

    function render_section_1()
    {
        print '<p>Pick a number between 1 and 1000, and choose a color.</p>';
    }
    function render_section_1_field_1( $args )
    {
        /* Creates this markup:
           /* <input name="plugin:option_name[number]"
        */
        printf(
            '<input name="%1$s[%2$s]" id="%3$s" value="%4$s" class="regular-text">',
            $args['option_name'],
            $args['name'],
            $args['label_for'],
            $args['value']
        );
    }
    function render_section_1_field_2( $args )
    {
        printf(
            '<select name="%1$s[%2$s]" id="%3$s">',
            $args['option_name'],
            $args['name'],
            $args['label_for']
        );

        foreach ( $args['options'] as $val => $title )
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                $val,
                selected( $val, $args['value'], FALSE ),
                $title
            );

        print '</select>';
    }
    function render_section_2()
    {
        print '<p>Makes some notes.</p>';
    }

    function render_section_2_field_1( $args )
    {
        printf(
            '<textarea name="%1$s[%2$s]" id="%3$s" rows="10" cols="30" class="code">%4$s</textarea>',
            $args['option_name'],
            $args['name'],
            $args['label_for'],
            $args['value']
        );
    }

    // Spit out every knob previously registered with action_admin_init
    // https://wordpress.stackexchange.com/questions/100023/settings-api-with-arrays-example
    function action_admin_menu() {
        add_options_page(
            __('Contrôle d\'accès Accred', 'epfl-accred'), // $page_title,
            __('Contrôle d\'accès Accred', 'epfl-accred'), // $menu_title,
            'manage_options',          // $capability,
            'epfl_accred_slug',       // $menu_slug
            array($this, 'render')       // Callback
        );
    }

    function render() {
        $title = $GLOBALS['title'];
        echo("<div class=\"wrap\">
        <h2>$title</h2>
        <form action=\"options.php\" method=\"POST\">\n");
        settings_fields( 'plugin:epfl-accred-optiongroup' );
        do_settings_sections( 'epfl_accred_slug' );
        submit_button();
        echo "        </form>\n";
    }

	/**
	 * Returns whether this plugin is currently network activated
	 */
	var $_is_network_version = null;
	function is_network_version() {
		if ( $this->_is_network_version === null) {
            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
            }

            $this->_is_network_version = (bool) is_plugin_active_for_network( plugin_basename(__FILE__) );
		}

		return $this->_is_network_version;
	}
}

