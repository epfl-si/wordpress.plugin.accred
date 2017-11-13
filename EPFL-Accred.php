<?php
/*
 * Plugin Name: EPFL Accred
 * Description: Automatically sync access rights to WordPress from EPFL's institutional data repositories
 * Version:     0.1
 * Author:      Dominique Quatravaux
 * Author URI:  mailto:dominique.quatravaux@epfl.ch
 */

namespace EPFL\Accred;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

if (! class_exists("EPFL\\SettingsBase") )
    require_once(dirname(__FILE__) . "/inc/settings.php");

function ___($text)
{
    return __($text, "epfl-accred");
}

function roles_plural()
{
    return array(
        "administrator" => ___("Administrateurs"),
        "editor"        => ___("Éditeurs"),
        "author"        => ___("Auteurs"),
        "contributor"   => ___("Contributeurs"),
        "subscriber"    => ___("Abonnés")
    );
}

class Controller
{
    static $instance = false;
    var $settings = null;

    public function __construct ()
    {
        $this->settings = new Settings();
    }

    public static function getInstance ()
    {
        if ( !self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    function hook()
    {
        $this->settings->hook();
    }
}

class Settings extends \EPFL\SettingsBase {
    const SLUG = "epfl_accred";

    function hook() {
        parent::hook();
        $this->add_options_page(
            ___('Réglages de Accred'),                  // $page_title,
            ___('Accred (contrôle d\'accès)'),          // $menu_title,
            'manage_options');                          // $capability
        add_action('admin_init', array($this, 'setup_options_page'));
    }

    /**
     * Prepare the admin menu with settings and their values
     */
    function setup_options_page()
    {
        $defaults = array('school' => 'STI');
        foreach (roles_plural() as $key => $unused_i18N) {
            $defaults[$key . "_group"] = '';
        }

        $data = $this->get_with_defaults($defaults);

        $this->add_settings_section('section_about', ___('À propos'));
        $this->add_settings_section('section_help', ___('Aide'));
        $this->add_settings_section('section_settings', ___('Paramètres'));

        $this->add_settings_field(
            'section_settings', 'field_school', ___('Faculté'),
            array(
                'type'        => 'select',
                'label_for'   => 'school', // makes the field name clickable,
                'name'        => 'school', // value for 'name' attribute
                'value'       => $data['school'],
                'options'     => array(
                    'ENAC'      => ___('Architecture, Civil and Environmental Engineering — ENAC'),
                    'SB'        => ___('Basic Sciences — SB'),
                    'STI'       => ___('Engineering — STI'),
                    'IC'        => ___('Computer and Communication Sciences — IC'),
                    'SV'        => ___('Life Sciences — SV'),
                    'CDM'       => ___('Management of Technology — CDM'),
                    'CDH'       => ___('College of Humanities — CDH')
                ),
                'help' => 'Permet de sélectionner les accès par défaut (droit wordpress.faculté).'
            )
        );

        $this->add_settings_field(
            'section_settings', 'field_admin_groups', ___("Contrôle d'accès par groupe"),
            array(
                'help' => 'Groupe permettant l’accès administrateur.'
            )
        );
    }

    function validate_settings( $settings )
    {
        /* This is just a demo implementation that does nothing of use */
        if (false) {
            $this->add_settings_error(
                'number-too-low',
                ___('Number must be between 1 and 1000.')
            );
        }
        return $settings;
    }

    function render_section_about()
    {
        echo __('<p><a href="https://github.com/epfl-sti/wordpress.plugin.tequila">EPFL-tequila</a>
    permet l’utilisation de <a href="https://tequila.epfl.ch/">Tequila</a>
    (Tequila est un système fédéré de gestion d’identité. Il fournit les moyens
    d’authentifier des personnes dans un réseau d’organisations) avec
    Wordpress.</p>', 'epfl-tequila');
    }

    function render_section_help()
    {
        echo __('<p>En cas de problème avec EPFL-tequila veuillez créer une
    <a href="https://github.com/epfl-sti/wordpress.plugin.tequila/issues/new"
    target="_blank">issue</a> sur le dépôt
    <a href="https://github.com/epfl-sti/wordpress.plugin.tequila/issues">
    GitHub</a>.</p>', 'epfl-tequila');
    }

    function render_section_settings()
    {
        // Nothing — The fields in this section speak for themselves
    }
    function render_field_admin_groups ()
    {
        $role_column_head  = ___("Rôle");
        $group_column_head = ___("Groupe");
        echo <<<TABLE_HEADER
            <table>
              <tr><th>$role_column_head</th>
                  <th>$group_column_head</th></tr>
TABLE_HEADER;
        foreach (roles_plural() as $role => $access_level) {
            $settings_key = $role . "_group";
            $input_name = sprintf('%1$s[%2$s]', $this->option_name(), $settings_key);
            $input_value = $this->get()[$settings_key];
            echo <<<TABLE_BODY
              <tr><th>$access_level</th><td><input type="text" name="$input_name" value="$input_value" class="regular-text"/></td></tr>
TABLE_BODY;
        }
        echo <<<TABLE_FOOTER
            </table>
TABLE_FOOTER;
    }

}

Controller::getInstance()->hook();
