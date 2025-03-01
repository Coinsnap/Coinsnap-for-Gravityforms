<?php
/*
 * Plugin Name:     Bitcoin payment for Gravity Forms
 * Plugin URI:      https://www.coinsnap.io
 * Description:     With this Bitcoin payment plugin for Gravity Forms you can now offer products, downloads, bookings or get donations in Bitcoin right in your forms!
 * Version:         1.0.0
 * Author:          Coinsnap
 * Author URI:      https://coinsnap.io/
 * Text Domain:     coinsnap-for-gravity-forms
 * Domain Path:     /languages
 * Requires PHP:    7.4
 * Tested up to:    6.7
 * Requires at least: 5.2
 * GF requires at least: 1.9.3
 * GF tested up to: 2.9.3
 * License:         GPL2
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:         true
 */ 

if (!defined( 'ABSPATH' )){
    exit;
}

if(!defined('COINSNAP_GF_PHP_VERSION')){ define( 'COINSNAP_GF_PHP_VERSION', '7.4' ); }
if(!defined('COINSNAP_GF_MIN_VERSION')){ define( 'COINSNAP_GF_MIN_VERSION', '1.9.3' ); }
if(!defined('COINSNAP_GF_VERSION')){ define( 'COINSNAP_GF_VERSION', '1.0.0' ); }
if(!defined('COINSNAP_GF_REFERRAL_CODE')){define( 'COINSNAP_GF_REFERRAL_CODE', 'D19826' );}
if(!defined('COINSNAP_GF_PLUGIN_SLUG')){define( 'COINSNAP_GF_PLUGIN_SLUG', 'coinsnap-for-gravity-forms' );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}

add_action('gform_loaded', array('Coinsnap_GF', 'load'), 5);

add_action('admin_init', 'check_gf_dependency');

function check_gf_dependency(){
    if (!is_plugin_active('gravityforms/gravityforms.php') || !method_exists( 'GFForms', 'include_payment_addon_framework'  )) {
        add_action('admin_notices', 'gf_dependency_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
    
function gf_dependency_notice(){?>
    <div class="notice notice-error">
        <p><?php echo esc_html_e('Bitcoin payment for Gravity Forms plugin requires Gravity Forms Pro '.COINSNAP_GF_MIN_VERSION.'+ to be installed and activated.','coinsnap-for-gravity-forms');?></p>
    </div>
    <?php        
    }

class Coinsnap_GF {
    public static function load(){
        if ( ! method_exists('GFForms', 'include_payment_addon_framework')) {
            return;
        }
        require_once (plugin_dir_path(__FILE__) . '/library/loader.php');	
        require_once('class-gf-coinsnap.php');

        GFAddOn::register('CoinsnapGF');
    }
}

function gf_coinsnap() {
    return CoinsnapGF::get_instance();
}