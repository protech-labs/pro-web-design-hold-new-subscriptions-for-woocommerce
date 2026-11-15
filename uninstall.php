<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes plugin options from the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove the plugin's options and the transient meta flags it uses
 * ('_hnsub_activated', '_hnsub_hold_target', plus '_hnsub_held_at' and
 * '_hnsub_escalated' used only by the Pro escalation timer, and the same four as '_hns_*' from versions before 1.4.2 — harmless to
 * remove even if Pro was never active). HPOS stores subscription/order meta
 * in a custom table (wp_wc_orders_meta) instead of wp_postmeta, so
 * delete_post_meta_by_key() alone would silently miss it on HPOS sites.
 */
function hnsub_uninstall_cleanup_site() {
    global $wpdb;

    // Current names, and the pre-1.4.0 names kept as a way back to 1.3.5.
    foreach ( array( 'hnsub', 'hns' ) as $prefix ) {
        delete_option( $prefix . '_options' );
        delete_option( $prefix . '_pro_send_info' );
        delete_option( $prefix . '_pro_product_rules' );
        delete_option( $prefix . '_pro_escalation' );
        delete_option( $prefix . '_pro_notifications' );
    }
    delete_option( 'hnsub_migrated_from_hns' );
    // Subject/heading settings of the two customer emails (WooCommerce stores them per email id).
    foreach ( array( 'hnsub', 'hns' ) as $prefix ) {
        delete_option( 'woocommerce_' . $prefix . '_subscription_hold_settings' );
        delete_option( 'woocommerce_' . $prefix . '_subscription_active_settings' );
    }

    $meta_keys = array(
        '_hnsub_activated', '_hnsub_hold_target', '_hnsub_holding', '_hnsub_held_at', '_hnsub_escalated',
        '_hns_activated', '_hns_hold_target', '_hns_held_at', '_hns_escalated', // written by versions before 1.4.2.
    );

    $hpos_enabled = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

    if ( $hpos_enabled ) {
        $table = $wpdb->prefix . 'wc_orders_meta';
        foreach ( $meta_keys as $meta_key ) {
            // 'meta_key' here is a literal column name for $wpdb->delete()'s WHERE
            // clause, not a WP_Query/WC_Order_Query meta_key argument.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            $wpdb->delete( $table, array( 'meta_key' => $meta_key ) );
        }
    } else {
        foreach ( $meta_keys as $meta_key ) {
            delete_post_meta_by_key( $meta_key );
        }
    }
}

if ( is_multisite() ) {
    $hnsub_sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
    foreach ( $hnsub_sites as $hnsub_site_id ) {
        switch_to_blog( $hnsub_site_id );
        hnsub_uninstall_cleanup_site();
        restore_current_blog();
    }
} else {
    hnsub_uninstall_cleanup_site();
}
