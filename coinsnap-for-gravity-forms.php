<?php
/*
 * Plugin Name:     Bitcoin payment for Gravity Forms
 * Plugin URI:      https://www.coinsnap.io
 * Description:     With this Bitcoin payment plugin for Gravity Forms you can now offer products, downloads, bookings or get donations in Bitcoin right in your forms!
 * Version:         1.0.1
 * Author:          Coinsnap
 * Author URI:      https://coinsnap.io/
 * Text Domain:     coinsnap-for-gravity-forms
 * Domain Path:     /languages
 * Requires PHP:    7.4
 * Tested up to:    6.8
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

if(!defined('COINSNAPGF_PHP_VERSION')){ define( 'COINSNAPGF_PHP_VERSION', '7.4' ); }
if(!defined('COINSNAPGF_MIN_VERSION')){ define( 'COINSNAPGF_MIN_VERSION', '1.9.3' ); }
if(!defined('COINSNAPGF_VERSION')){ define( 'COINSNAPGF_VERSION', '1.0.1' ); }
if(!defined('COINSNAPGF_REFERRAL_CODE')){define( 'COINSNAPGF_REFERRAL_CODE', 'D19826' );}
if(!defined('COINSNAPGF_PLUGIN_SLUG')){define( 'COINSNAPGF_PLUGIN_SLUG', 'coinsnap-for-gravity-forms' );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_CURRENCIES')){define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") );}

add_action('admin_init', 'coinsnapgf_dependency_check');
add_action('gform_loaded', array('CoinsnapGForm', 'load'), 5);

function coinsnapgf_dependency_check(){
    if (!is_plugin_active('gravityforms/gravityforms.php') || !method_exists( 'GFForms', 'include_payment_addon_framework'  )) {
        add_action('admin_notices', 'coinsnapgf_dependency_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
    
function coinsnapgf_dependency_notice(){?>
    <div class="notice notice-error">
        <p><?php echo sprintf( 
            /* translators: 1: Gravity Forms version */
            esc_html__( 'Bitcoin payment for Gravity Forms plugin requires Gravity Forms Pro %1$s to be installed and activated.', 'coinsnap-for-gravity-forms' ), esc_html(COINSNAPGF_MIN_VERSION));?></p>
    </div>
    <?php        
    }

class CoinsnapGForm {
    public static function load(){
        if ( ! method_exists('GFForms', 'include_payment_addon_framework')) {
            return;
        }
        require_once (plugin_dir_path(__FILE__) . '/library/loader.php');	
        require_once('class-gf-coinsnap.php');

        GFAddOn::register('CoinsnapGF');
    }
}
/*
function gf_coinsnap() {
    return CoinsnapGF::get_instance();
}*/