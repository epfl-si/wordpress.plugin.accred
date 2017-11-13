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

    function get_access_level($groups)
    {
        foreach ($this->settings->get_acls() as $role => $role_group) {
            if (in_array($role_group, $groups)) {
                return $role;
                break;
            }
        }
        return null;
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

    /**
     * @return One of the WordPress roles e.g. "administrator", "editor" etc.
     *         or null if the user designated by $tequila_data doesn't belong
     *         to any of the roles.
     */
    function get_access_level ($tequila_data)
    {
        $user_groups = explode(",", $tequila_data['group']);
        $config = $this->get();

        foreach (roles_plural() as $role => $unused_i18N) {
            $role_group = $config[$role . "_group"];
            if (($role_group === '' || $role_group === null)) continue;

            if (in_array($role_group, $user_groups)) {
                return $role;
            }
        }
        return null;
    }
}

Controller::getInstance()->hook();
