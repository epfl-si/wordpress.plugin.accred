<?php
/*
 * Plugin Name: EPFL Accred
 * Description: Automatically sync access rights to WordPress from EPFL's institutional data repositories
 * Version:     0.3
 * Author:      Dominique Quatravaux
 * Author URI:  mailto:dominique.quatravaux@epfl.ch
 */

namespace EPFL\Accred;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

if (! class_exists("EPFL\\SettingsBase") ) {
    require_once(dirname(__FILE__) . "/inc/settings.php");
}
require_once(dirname(__FILE__) . "/inc/cli.php");

function ___($text)
{
    return __($text, "epfl-accred");
}

class Roles
{
    private static function _allroles ()
    {
        return array(
            "administrator" => ___("Administrateurs"),
            "editor"        => ___("Éditeurs"),
            "author"        => ___("Auteurs"),
            "contributor"   => ___("Contributeurs"),
            "subscriber"    => ___("Abonnés")
        );
    }

    static function plural ($role)
    {
        return Roles::_allroles()[$role];
    }

    static function keys ()
    {
        return array_keys(Roles::_allroles());
    }
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
        (new CLI($this))->hook();
        add_action('tequila_save_user', array($this, 'tequila_save_user'));
    }

    /**
     * Create or update the Wordpress user from the Tequila data
     */
    function tequila_save_user($tequila_data)
    {
        $user = get_user_by("login", $tequila_data["username"]);
        $user_role = $this->settings->get_access_level($tequila_data);

        if ($user_role === null && $user === false) {
            // User unknown and has no role: die() early (don't create it)
            echo ___("Utilisateur inconnu");
            die();
        }

        $userdata = array(
            'user_nicename'  => $tequila_data['uniqueid'],  // Their "slug"
            'nickname'       => $tequila_data['uniqueid'],
            'user_email'     => $tequila_data['email'],
            'user_login'     => $tequila_data['username'],
            'first_name'     => $tequila_data['firstname'],
            'last_name'      => $tequila_data['name'],
            'role'           => $user_role,
            'user_pass'      => null);
        if ($user === false) {
            $new_user_id = wp_insert_user($userdata);
            if ( ! is_wp_error( $new_user_id ) ) {
                $user = new \WP_User($new_user_id);
            } else {
                echo $new_user_id->get_error_message();
                die();
            }
        } else {  // User is already known to WordPress
            $userdata['ID'] = $user->ID;
            $user_id = wp_update_user($userdata);
        }

        if ($user_role === null) {
            // User with no role, but exists in database: die late
            // (*after* invalidating their rights in the WP database)
            echo ___("Accès refusé");
            die();
        }
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

        $this->add_settings_section('section_about', ___('À propos'));
        $this->add_settings_section('section_help', ___('Aide'));
        $this->add_settings_section('section_settings', ___('Paramètres'));

        $this->register_setting('school', array(
            'type'    => 'string',
            'default' => 'STI'
        ));
        $this->add_settings_field(
            'section_settings', 'school', ___('Faculté'),
            array(
                'type'        => 'select',
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

        foreach ($this->role_settings() as $role => $role_setting) {
            $this->register_setting($role_setting, array(
                'type'    => 'string',
                'default' => ''
            ));
        }

        // Not really a "field", but use the rendering callback mechanisms
        // we have:
        $this->add_settings_field(
            'section_settings', 'admin_groups', ___("Contrôle d'accès par groupe"),
            array(
                'help' => 'Groupe permettant l’accès administrateur.'
            )
        );
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
            <table id="admin_groups">
              <tr><th>$role_column_head</th>
                  <th>$group_column_head</th></tr>
TABLE_HEADER;
        foreach ($this->role_settings() as $role => $role_setting) {
            $input_name = $this->option_name($role_setting);
            $input_value = $this->get($role_setting);
            $access_level = Roles::plural($role);
            echo <<<TABLE_BODY
              <tr><th>$access_level</th><td><input type="text" name="$input_name" value="$input_value" class="regular-text"/></td></tr>
TABLE_BODY;
        }
        echo <<<TABLE_FOOTER
            </table>
TABLE_FOOTER;
    }

    /**
     * @return One of the WordPress roles e.g. "administrator", "editor" etc.
     *         or null if the user designated by $tequila_data doesn't belong
     *         to any of the roles.
     */
    function get_access_level ($tequila_data)
    {
        $user_groups = explode(",", $tequila_data['group']);

        foreach ($this->role_settings() as $role => $role_setting) {
            $role_group = $this->get($role_setting);
            if (($role_group === '' || $role_group === null)) continue;

            if (in_array($role_group, $user_groups)) {
                return $role;
            }
        }
        return null;
    }

    function role_settings () {
        $retval = array();
        foreach (Roles::keys() as $role) {
            $retval[$role] = $role . "_group";
        }
        return $retval;
    }
}

Controller::getInstance()->hook();
