<?php

/**
 * Plugin Name: Pro Web Design Hold New Subscriptions for WooCommerce
 * Plugin URI: https://www.pro-webdesign.co.uk/plugins/hold-new-subscriptions
 * Description: Puts newly created WooCommerce Subscriptions on hold (configurable) until the parent order reaches selected statuses (e.g. Completed), then activates them.
 * Author: Pro Technologies Limited
 * Author URI: https://www.pro-webdesign.co.uk
 * Version: 1.5.1
 * License: GPL-2.0-or-later
 * Text Domain: pro-web-design-hold-new-subscriptions-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 * WC tested up to: 11.3
 *
 *
 * @package Hold_New_Subscriptions
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'hnsub_fs' ) ) {
    // The other edition of the plugin (free or Professional) is already running: this copy steps
    // aside. Nothing else in this file or in includes/ is loaded twice.
    hnsub_fs()->set_basename( false, __FILE__ );
} else {
    /**
     * DO NOT REMOVE THIS IF. IT IS ESSENTIAL FOR THE
     * `function_exists` CALL ABOVE TO PROPERLY WORK.
     */
    if ( !function_exists( 'hnsub_fs' ) ) {
        /**
         * Freemius SDK: licensing and updates of the Professional version, opt-in usage data.
         */
        function hnsub_fs() {
            global $hnsub_fs;
            if ( !isset( $hnsub_fs ) ) {
                require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
                $hnsub_fs = fs_dynamic_init( array(
                    'id'               => '45027',
                    'slug'             => 'pro-web-design-hold-new-subscriptions-for-woocommerce',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_1e392e078e0ce6dac708ca13379ac',
                    'is_premium'       => false,
                    'premium_suffix'   => 'Professional',
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'trial'            => array(
                        'days'               => 14,
                        'is_require_payment' => false,
                    ),
                    'menu'             => array(
                        'slug'    => 'hnsub-settings',
                        'account' => true,
                        'support' => false,
                        'contact' => false,
                        'parent'  => array(
                            'slug' => 'woocommerce',
                        ),
                    ),
                    'is_live'          => true,
                ) );
            }
            return $hnsub_fs;
        }

        hnsub_fs();
        do_action( 'hnsub_fs_loaded' );
    }
    define( 'HNSUB_PLUGIN_VERSION', '1.5.1' );
    define( 'HNSUB_PLUGIN_FILE', __FILE__ );
    define( 'HNSUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    define( 'HNSUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    require_once HNSUB_PLUGIN_DIR . 'includes/hnsub-core.php';
    // Data is removed on this action, not from an uninstall.php file — see hnsub_uninstall_cleanup().
    hnsub_fs()->add_action( 'after_uninstall', 'hnsub_uninstall_cleanup' );
}