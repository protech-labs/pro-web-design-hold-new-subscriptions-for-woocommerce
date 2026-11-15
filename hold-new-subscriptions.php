<?php
/**
 * Plugin Name: Pro Web Design Hold New Subscriptions for WooCommerce
 * Description: Puts newly created WooCommerce Subscriptions on hold (configurable) until the parent order reaches selected statuses (e.g. Completed), then activates them.
 * Author: Pro Technologies Limited
 * Author URI: https://www.pro-webdesign.co.uk
 * Version: 1.4.6
 * License: GPL-2.0-or-later
 * Text Domain: pro-web-design-hold-new-subscriptions-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 *
 * @package Hold_New_Subscriptions
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HNSUB_PLUGIN_VERSION', '1.4.6' );
define( 'HNSUB_PLUGIN_FILE', __FILE__ );
define( 'HNSUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HNSUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS (custom order tables) compatibility.
 * Must run on before_woocommerce_init, before WooCommerce checks compatibility flags.
 */
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                HNSUB_PLUGIN_FILE,
                true
            );
        }
    }
);

if ( file_exists( HNSUB_PLUGIN_DIR . 'includes/class-hnsub-admin.php' ) ) {
    require_once HNSUB_PLUGIN_DIR . 'includes/class-hnsub-admin.php';
}

/**
 * One-time move from the pre-1.4.0 names.
 *
 * Up to 1.3.5 everything this plugin defined started with "hns". WordPress.org asks for a
 * prefix of at least four characters, so 1.4.0 renamed functions, classes, constants, hooks
 * and options to "hnsub". Settings saved by an older version are copied to the new option
 * names here, once. The old options are left in place on purpose: going back to 1.3.5 finds
 * its settings exactly as they were. They are removed when the plugin is deleted.
 *
 * 1.4.2 finished the rename: the two customer emails got the ids hnsub_subscription_hold /
 * hnsub_subscription_active (their subject/heading settings are copied here too), their
 * templates are emails/hnsub-subscription-*.php, and the subscription flags are stored as
 * _hnsub_* — see hnsub_get_flag() for how flags written by older versions are still honoured.
 */
function hnsub_maybe_migrate_from_hns() {
    $done = (string) get_option( 'hnsub_migrated_from_hns', '' );
    if ( '' !== $done && version_compare( $done, '1.4.2', '>=' ) ) { return; }

    $map = array(
        // Since 1.4.0.
        'hns_options'           => 'hnsub_options',
        'hns_pro_send_info'     => 'hnsub_pro_send_info',
        'hns_pro_product_rules' => 'hnsub_pro_product_rules',
        'hns_pro_escalation'    => 'hnsub_pro_escalation',
        'hns_pro_notifications' => 'hnsub_pro_notifications',
        // Since 1.4.2: WooCommerce keeps each email's settings under woocommerce_<email id>_settings.
        'woocommerce_hns_subscription_hold_settings'   => 'woocommerce_hnsub_subscription_hold_settings',
        'woocommerce_hns_subscription_active_settings' => 'woocommerce_hnsub_subscription_active_settings',
    );
    foreach ( $map as $old => $new ) {
        $value = get_option( $old, null );
        if ( null !== $value && null === get_option( $new, null ) ) {
            add_option( $new, $value );
        }
    }
    // The Pro escalation timer ran under the old hook name; the Pro module schedules the new one itself.
    wp_clear_scheduled_hook( 'hns_pro_escalation_check' );

    update_option( 'hnsub_migrated_from_hns', HNSUB_PLUGIN_VERSION, false );
}
add_action( 'plugins_loaded', 'hnsub_maybe_migrate_from_hns', 1 ); // before hnsub_boot() (priority 5).

/**
 * Flags this plugin keeps on a subscription: "hold_target" (set at checkout, applied and removed
 * on the thank-you page) and "activated" (set when the plugin activates the subscription).
 *
 * They are stored as _hnsub_<name>. Up to 1.4.1 they were _hns_<name>: a flag written by an older
 * version is still read, so a subscription that was mid-checkout or already activated during the
 * update behaves as before, and it is removed together with the new one.
 *
 * @param WC_Subscription $sub  Subscription.
 * @param string          $name Flag name without prefix.
 * @return mixed Empty string when the flag is not set.
 */
function hnsub_get_flag( $sub, $name ) {
    $value = $sub->get_meta( '_hnsub_' . $name );
    if ( '' === $value || null === $value || false === $value ) {
        $value = $sub->get_meta( '_hns_' . $name );
    }
    return $value;
}

/**
 * @param WC_Subscription $sub   Subscription.
 * @param string          $name  Flag name without prefix.
 * @param string          $value Value.
 */
function hnsub_set_flag( $sub, $name, $value ) {
    $sub->update_meta_data( '_hnsub_' . $name, $value );
    $sub->delete_meta_data( '_hns_' . $name );
    $sub->save_meta_data();
}

/**
 * @param WC_Subscription $sub  Subscription.
 * @param string          $name Flag name without prefix.
 */
function hnsub_clear_flag( $sub, $name ) {
    $sub->delete_meta_data( '_hnsub_' . $name );
    $sub->delete_meta_data( '_hns_' . $name );
    $sub->save_meta_data();
}

/**
 * Per-subscription options: lets a rule (e.g. a Pro per-product rule) override the globally
 * configured ones for one subscription. Without a filter the global options come back unchanged.
 *
 * @param array           $opts  Global options.
 * @param WC_Subscription $sub   Subscription.
 * @param WC_Order        $order Parent order.
 * @return array
 */
function hnsub_subscription_options( $opts, $sub, $order ) {
    $sub_opts = apply_filters( 'hnsub_subscription_options', $opts, $sub, $order );
    // Name used up to 1.3.5: still honoured, with a deprecation notice for whoever hooks it.
    $sub_opts = apply_filters_deprecated( 'hns_subscription_options', array( $sub_opts, $sub, $order ), '1.4.0', 'hnsub_subscription_options' );
    return is_array( $sub_opts ) ? $sub_opts : $opts;
}

/**
 * Whether Pro features are active.
 *
 * No Freemius integration yet (deliberately — monetization comes after the
 * free version is solid). This is a placeholder gate so Pro-only code can be
 * built and reviewed now, then switched on later by making this function
 * check the Freemius license instead, with no changes needed anywhere else.
 *
 * For local testing before Freemius is wired up:
 *   add_filter( 'hnsub_is_pro', '__return_true' );
 */
function hnsub_is_pro() {
    $is_pro = apply_filters( 'hnsub_is_pro', false );
    // Name used up to 1.3.5: still honoured, with a deprecation notice for whoever hooks it.
    $is_pro = apply_filters_deprecated( 'hns_is_pro', array( $is_pro ), '1.4.0', 'hnsub_is_pro' );
    return (bool) $is_pro;
}

/**
 * Load Pro-only modules.
 *
 * Each lives in includes/pro/class-hnsub-<feature>__premium_only.php and is
 * required only when Pro is active — the same "__premium_only" naming
 * convention used by Order Note Templates / Order Tags & Labels, so this
 * plugin is ready for the same Freemius free-build stripping process
 * whenever monetization is connected. Until then, with hnsub_is_pro() always
 * false, none of this code loads or runs.
 */
function hnsub_load_pro_modules() {
    if ( ! hnsub_is_pro() ) { return; }
    if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wcs_order_contains_subscription' ) ) { return; }

    $pro_dir = HNSUB_PLUGIN_DIR . 'includes/pro/';
    $modules = array(
        'class-hnsub-send-info__premium_only.php',
        'class-hnsub-product-rules__premium_only.php',
        'class-hnsub-escalation__premium_only.php',
        'class-hnsub-notifications__premium_only.php',
    );
    foreach ( $modules as $module ) {
        $path = $pro_dir . $module;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}
add_action( 'plugins_loaded', 'hnsub_load_pro_modules', 6 ); // after hnsub_boot() (priority 5).

/**
 * Clear the Pro escalation-timer cron event on deactivation. Safe to call
 * even when the Pro module was never active (wp_clear_scheduled_hook() is a
 * no-op if nothing was scheduled).
 */
register_deactivation_hook( HNSUB_PLUGIN_FILE, function () {
    wp_clear_scheduled_hook( 'hnsub_pro_escalation_check' );
} );

/**
 * Register custom email classes with WooCommerce.
 * require_once is inside the callback so WC_Email is guaranteed to exist.
 */
add_filter( 'woocommerce_email_classes', function( $email_classes ) {
    require_once HNSUB_PLUGIN_DIR . 'includes/class-hnsub-email-hold.php';
    require_once HNSUB_PLUGIN_DIR . 'includes/class-hnsub-email-active.php';
    $email_classes['HNSUB_Email_Hold']   = new HNSUB_Email_Hold();
    $email_classes['HNSUB_Email_Active'] = new HNSUB_Email_Active();
    return $email_classes;
} );

/**
 * Get plugin options with defaults.
 */
function hnsub_get_options() {
    $defaults = array(
        'enabled'              => 1,
        'initial_status'       => 'on-hold', // allowed: on-hold, pending
        'activate_on_statuses' => array( 'completed' ),
        'limit_gateways'       => 0,
        'allowed_gateways'     => array(),
        'add_order_notes'      => 1,
        'use_wc_logger'        => 0,
        'send_hold_email'      => 0,
        'send_active_email'    => 0,
    );
    $opts = get_option( 'hnsub_options', array() );
    if ( ! is_array( $opts ) ) { $opts = array(); }
    return wp_parse_args( $opts, $defaults );
}

/**
 * Simple dependency checks with admin notices.
 */
function hnsub_dependencies_ok() {
    $ok = true;

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Hold New Subscriptions', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ) . '</strong>: ' . esc_html__( 'WooCommerce must be active.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ) . '</p></div>';
        } );
        $ok = false;
    }

    if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Hold New Subscriptions', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ) . '</strong>: ' . esc_html__( 'WooCommerce Subscriptions plugin is required.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ) . '</p></div>';
        } );
        $ok = false;
    }

    return $ok;
}

/**
 * Add notes / logs helper.
 */
function hnsub_log( $message, $context = array() ) {
    $opts = hnsub_get_options();
    if ( ! empty( $opts['use_wc_logger'] ) && function_exists( 'wc_get_logger' ) ) {
        $logger = wc_get_logger();
        $logger->info( $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ), array( 'source' => 'hold-new-subscriptions' ) );
    }
}

/**
 * Strip the 'wc-' prefix from an order status slug if present.
 *
 * @param string $status
 * @return string
 */
function hnsub_strip_wc_prefix( $status ) {
    return ( 0 === strpos( $status, 'wc-' ) ) ? substr( $status, 3 ) : $status;
}

/**
 * Send an email to the customer when their subscription is placed on hold.
 *
 * @param WC_Order        $order
 * @param WC_Subscription $sub
 * @param array           $activate_statuses Slugs without wc- prefix.
 */
function hnsub_send_hold_email( $order, $sub, $activate_statuses ) {
    if ( ! $order instanceof WC_Order || ! $sub instanceof WC_Subscription ) {
        return;
    }
    if ( ! function_exists( 'WC' ) || ! WC() ) {
        return;
    }
    $mailer = WC()->mailer();
    if ( ! $mailer ) {
        return;
    }
    $emails      = $mailer->get_emails();
    $email_class = is_array( $emails ) ? ( $emails['HNSUB_Email_Hold'] ?? null ) : null;
    if ( $email_class ) {
        $email_class->trigger( $order, $sub, $activate_statuses );
        hnsub_log( 'Hold email sent', array( 'subscription' => $sub->get_id(), 'order' => $order->get_id() ) );
    } else {
        hnsub_log( 'Hold email class not found, email not sent', array( 'subscription' => $sub->get_id(), 'order' => $order->get_id() ) );
    }
}

/**
 * Send a confirmation email to the customer when their subscription is activated.
 *
 * @param WC_Order        $order
 * @param WC_Subscription $sub
 */
function hnsub_send_active_email( $order, $sub ) {
    if ( ! $order instanceof WC_Order || ! $sub instanceof WC_Subscription ) {
        return;
    }
    if ( ! function_exists( 'WC' ) || ! WC() ) {
        return;
    }
    $mailer = WC()->mailer();
    if ( ! $mailer ) {
        return;
    }
    $emails      = $mailer->get_emails();
    $email_class = is_array( $emails ) ? ( $emails['HNSUB_Email_Active'] ?? null ) : null;
    if ( $email_class ) {
        $email_class->trigger( $order, $sub );
        hnsub_log( 'Active email sent', array( 'subscription' => $sub->get_id(), 'order' => $order->get_id() ) );
    } else {
        hnsub_log( 'Active email class not found, email not sent', array( 'subscription' => $sub->get_id(), 'order' => $order->get_id() ) );
    }
}

/**
 * Activate a subscription that's on hold/pending, applying the same guard,
 * note, log and email behavior the automatic order-status trigger uses.
 *
 * Shared by the automatic path below and any Pro-only manual trigger (e.g.
 * the "send subscription info" action), so both stay consistent and neither
 * duplicates the HPOS-safe duplicate-activation guard.
 *
 * @param WC_Subscription $sub    Subscription to activate.
 * @param WC_Order        $order  Parent order, used for email/context only.
 * @param string          $reason Human-readable reason recorded on the subscription.
 * @return bool True if this call activated the subscription.
 */
function hnsub_activate_subscription( $sub, $order, $reason ) {
    if ( ! $sub instanceof WC_Subscription ) { return false; }
    if ( ! in_array( $sub->get_status(), array( 'pending', 'on-hold' ), true ) ) { return false; }
    if ( hnsub_get_flag( $sub, 'activated' ) ) { return false; }

    hnsub_set_flag( $sub, 'activated', '1' );
    $sub->update_status( 'active', $reason );

    hnsub_after_activation( $sub, $order, $reason );

    return true;
}

/**
 * Everything that follows an activation: note, customer email, log, hooks.
 *
 * Shared by hnsub_activate_subscription() and by the case where WooCommerce
 * Subscriptions has activated a held subscription in the very status change
 * that takes the order to an activation status (see the order status handler).
 *
 * @param WC_Subscription $sub    Subscription that is now active.
 * @param WC_Order        $order  Parent order, used for email/context only.
 * @param string          $reason Human-readable reason recorded on the subscription.
 */
function hnsub_after_activation( $sub, $order, $reason ) {
    $opts = hnsub_get_options();
    if ( ! empty( $opts['add_order_notes'] ) ) {
        /* translators: %s: reason the subscription was activated */
        $sub->add_order_note( sprintf( __( 'HNS: subscription activated. %s', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ), $reason ) );
    }
    if ( ! empty( $opts['send_active_email'] ) && $order instanceof WC_Order ) {
        hnsub_send_active_email( $order, $sub );
    }
    hnsub_log( 'Subscription activated', array( 'subscription' => $sub->get_id(), 'reason' => $reason ) );

    /**
     * Fires after HNS activates a subscription, however it was triggered.
     *
     * @param WC_Subscription $sub
     * @param WC_Order        $order
     * @param string          $reason
     */
    do_action( 'hnsub_subscription_activated', $sub, $order, $reason );
    // Name used up to 1.3.5.
    do_action_deprecated( 'hns_subscription_activated', array( $sub, $order, $reason ), '1.4.0', 'hnsub_subscription_activated' );
}

/**
 * Subscriptions this plugin was holding when an order status change began.
 *
 * WooCommerce Subscriptions activates the subscriptions of an order the moment
 * the order moves from an unpaid to a paid status (its handler runs at priority
 * 9 of `woocommerce_order_status_changed`). To tell "activated just now by that
 * handler" from "already active before" — an admin may have activated it by
 * hand — the held ones are noted at priority 1 and read back at priority 10.
 *
 * @param int        $order_id Order id.
 * @param array|null $set      When given: store this map of subscription id => held status.
 * @return array Map of subscription id => status it is held in ('on-hold' or 'pending').
 */
function hnsub_held_before( $order_id, $set = null ) {
    static $held = array();
    if ( null !== $set ) {
        $held[ $order_id ] = $set;
    }
    return isset( $held[ $order_id ] ) ? $held[ $order_id ] : array();
}

/**
 * Put a subscription back into the status it was held in.
 *
 * Used when a payment is confirmed after the hold was applied (bank transfer,
 * cheque, any gateway that confirms later): WooCommerce Subscriptions then
 * activates the subscription although the order has not reached the status the
 * store chose. No customer email is sent — the customer was told about the hold
 * when it started.
 *
 * @param WC_Subscription $sub               Subscription WooCommerce Subscriptions has just activated.
 * @param WC_Order        $order             Parent order.
 * @param string          $target            Status to return to: 'on-hold' or 'pending'.
 * @param string[]        $activate_statuses Order statuses that will activate it (without the wc- prefix).
 * @return bool True if the subscription is held again.
 */
function hnsub_hold_again( $sub, $order, $target, $activate_statuses ) {
    if ( ! $sub instanceof WC_Subscription ) { return false; }
    $target = in_array( $target, array( 'on-hold', 'pending' ), true ) ? $target : 'on-hold';

    $allow_filter = 'woocommerce_can_subscription_be_updated_to_' . $target;
    add_filter( $allow_filter, '__return_true', 100 );
    try {
        $sub->update_status( $target, __( 'Held again by Hold New Subscriptions: payment confirmed, order not yet at an activation status.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ) );
    } catch ( Exception $e ) {
        hnsub_log( 'Failed to hold subscription again', array( 'subscription' => $sub->get_id(), 'target' => $target, 'error' => $e->getMessage() ) );
        remove_filter( $allow_filter, '__return_true', 100 );
        return false;
    }
    remove_filter( $allow_filter, '__return_true', 100 );

    hnsub_set_flag( $sub, 'holding', $target );

    $opts = hnsub_get_options();
    if ( ! empty( $opts['add_order_notes'] ) ) {
        /* translators: 1: subscription status, 2: comma-separated order statuses that will trigger activation */
        $sub->add_order_note( sprintf( __( 'HNS: payment confirmed; subscription kept %1$s until parent order reaches: %2$s', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ), $target, implode( ', ', (array) $activate_statuses ) ) );
    }
    hnsub_log( 'Subscription held again after payment', array( 'subscription' => $sub->get_id(), 'target' => $target ) );

    /**
     * Fires after HNS returns a subscription to its held status because the
     * payment was confirmed before the order reached an activation status.
     *
     * @param WC_Subscription $sub
     * @param WC_Order        $order
     * @param string          $target Status the subscription was returned to.
     */
    do_action( 'hnsub_subscription_held_again', $sub, $order, $target );

    return true;
}

/**
 * Core: place subs on-hold after checkout, then activate when order hits desired statuses.
 */
function hnsub_boot() {
    // Translations: nothing to load here. WordPress loads them by itself from the
    // "Text Domain" header (language packs from translate.wordpress.org).

    if ( ! hnsub_dependencies_ok() ) { return; }

    // Admin UI (only when dependencies are met)
    HNSUB_Admin::init();

    $opts = hnsub_get_options();
    if ( empty( $opts['enabled'] ) ) {
        hnsub_log( 'Plugin disabled via settings.' );
        return;
    }

    // When a new subscription is created at checkout: mark it for a deferred hold.
    // We do NOT change the status here because this hook fires during payment processing —
    // calling update_status() at this point cancels WCS scheduled payments and breaks gateways.
    add_action( 'woocommerce_checkout_subscription_created', function( $sub, $order ) use ( $opts ) {
        if ( ! $order instanceof WC_Order ) return;
        $order_id = $order->get_id();

        // Gateways filter
        if ( ! empty( $opts['limit_gateways'] ) && ! empty( $opts['allowed_gateways'] ) ) {
            $pm = $order->get_payment_method();
            if ( ! in_array( $pm, (array) $opts['allowed_gateways'], true ) ) {
                hnsub_log( 'Order skipped due to gateway filter', array( 'order' => $order_id, 'gateway' => $pm ) );
                return;
            }
        }

        // The plugin is about new subscriptions only: a renewal order is never its business.
        if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
            hnsub_log( 'Renewal order skipped', array( 'order' => $order_id ) );
            return;
        }

        // Per-subscription options, so a Pro rule (e.g. per-product initial status)
        // can override the globally configured ones. Free installs get $opts back
        // unchanged, since no filter is registered.
        $sub_opts = hnsub_subscription_options( $opts, $sub, $order );

        $activate_statuses = array_map( 'hnsub_strip_wc_prefix', (array) $sub_opts['activate_on_statuses'] );
        if ( $order->has_status( $activate_statuses ) ) {
            return; // Order already at an activation status — no need to hold.
        }

        $target = in_array( $sub_opts['initial_status'], array( 'on-hold', 'pending' ), true ) ? $sub_opts['initial_status'] : 'on-hold';
        hnsub_set_flag( $sub, 'hold_target', $target );
        hnsub_log( 'Subscription marked for deferred hold', array( 'subscription' => $sub->get_id(), 'target' => $target ) );
    }, 10, 2 );

    // Apply the deferred hold after checkout is fully complete.
    // Fires on the thank-you page (covers all gateway types) and on payment_complete
    // (covers REST/API/headless flows). The "hold_target" flag acts as a one-time flag —
    // deleted on first processing so page refreshes are safe.
    $hnsub_apply_hold = function( $order_id ) use ( $opts ) {
        $order_id = absint( $order_id );
        if ( ! $order_id ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) return;
        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) return;

        $subs = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
        if ( ! is_array( $subs ) || empty( $subs ) ) return;

        foreach ( $subs as $sub ) {
            $target = hnsub_get_flag( $sub, 'hold_target' );
            if ( ! $target ) continue;

            // Remove flag immediately to prevent duplicate processing on refresh.
            hnsub_clear_flag( $sub, 'hold_target' );

            $sub_opts = hnsub_subscription_options( $opts, $sub, $order );
            $activate_statuses = array_map( 'hnsub_strip_wc_prefix', (array) $sub_opts['activate_on_statuses'] );

            if ( $order->has_status( $activate_statuses ) ) {
                hnsub_log( 'Hold skipped — order already at activation status', array( 'subscription' => $sub->get_id() ) );
                continue;
            }

            // A subscription that already has the target status (an unpaid order with "Pending"
            // as the initial status) needs no status change, but it is held all the same.
            $current = $sub->get_status();
            if ( $current !== $target ) {
                $allow_filter = 'woocommerce_can_subscription_be_updated_to_' . $target;
                add_filter( $allow_filter, '__return_true', 100 );
                try {
                    $sub->update_status( $target, __( 'Automatically set by Hold New Subscriptions.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ) );
                } catch ( Exception $e ) {
                    hnsub_log( 'Failed to set subscription status', array( 'subscription' => $sub->get_id(), 'target' => $target, 'error' => $e->getMessage() ) );
                    remove_filter( $allow_filter, '__return_true', 100 );
                    continue;
                }
                remove_filter( $allow_filter, '__return_true', 100 );
            }

            // Remembered until the subscription leaves the hold: lets the order status handler
            // see that a later activation by WooCommerce Subscriptions came too early.
            hnsub_set_flag( $sub, 'holding', $target );

            if ( ! empty( $opts['add_order_notes'] ) ) {
                /* translators: 1: initial subscription status, 2: comma-separated order statuses that will trigger activation */
                $sub->add_order_note( sprintf( __( 'HNS: subscription set to %1$s until parent order reaches: %2$s', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ), $target, implode( ', ', $activate_statuses ) ) );
            }
            if ( ! empty( $opts['send_hold_email'] ) ) {
                hnsub_send_hold_email( $order, $sub, $activate_statuses );
            }
            hnsub_log( 'Subscription set to initial status', array( 'subscription' => $sub->get_id(), 'target' => $target ) );

            /**
             * Fires after HNS puts a subscription on hold/pending.
             *
             * @param WC_Subscription $sub
             * @param WC_Order        $order
             * @param string          $target Status the subscription was set to.
             */
            do_action( 'hnsub_subscription_held', $sub, $order, $target );
            // Name used up to 1.3.5.
            do_action_deprecated( 'hns_subscription_held', array( $sub, $order, $target ), '1.4.0', 'hnsub_subscription_held' );
        }
    };
    add_action( 'woocommerce_thankyou', $hnsub_apply_hold, 10 );
    add_action( 'woocommerce_payment_complete', $hnsub_apply_hold, 20 );

    // Before WooCommerce Subscriptions reacts to an order status change (priority 9): note which
    // subscriptions of this order we are holding. See hnsub_held_before().
    add_action( 'woocommerce_order_status_changed', function( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) { return; }
        $held = array();
        $subs = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
        if ( is_array( $subs ) ) {
            foreach ( $subs as $sub ) {
                if ( ! $sub instanceof WC_Subscription || ! $sub->has_status( array( 'pending', 'on-hold' ) ) ) { continue; }
                $holding = hnsub_get_flag( $sub, 'holding' );
                if ( $holding ) {
                    $held[ $sub->get_id() ] = $holding;
                }
            }
        }
        hnsub_held_before( $order_id, $held );
    }, 1 );

    // When order status changes: if new status is one of activate_on_statuses -> activate related subs;
    // if it is not, and WooCommerce Subscriptions has just activated a subscription we hold -> hold it again.
    add_action( 'woocommerce_order_status_changed', function( $order_id, $old_status, $new_status ) use ( $opts ) {
        $order_id = absint( $order_id );
        if ( ! $order_id ) { return; }
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) return;

        $held_before = hnsub_held_before( $order_id );

        // Fast path: on free installs activate_on_statuses is the same for every
        // subscription, so we can bail before touching the database at all. Pro
        // installs may override activate_on_statuses per subscription/product via
        // the hnsub_subscription_options filter below, so they can't take this
        // shortcut — the real check happens per-subscription further down.
        // The shortcut does not apply while we hold a subscription of this order:
        // it may have to be held again (see below).
        if ( ! hnsub_is_pro() && empty( $held_before ) ) {
            $activate_statuses = array_map( 'hnsub_strip_wc_prefix', (array) $opts['activate_on_statuses'] );
            if ( ! in_array( $new_status, $activate_statuses, true ) ) {
                return;
            }
        }

        // Gateways filter
        if ( ! empty( $opts['limit_gateways'] ) && ! empty( $opts['allowed_gateways'] ) ) {
            $pm = $order->get_payment_method();
            if ( ! in_array( $pm, (array) $opts['allowed_gateways'], true ) ) {
                hnsub_log( 'Order skipped due to gateway filter', array( 'order' => $order_id, 'gateway' => $pm ) );
                return;
            }
        }

        // New subscriptions only: the status of a renewal order changes nothing here.
        if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
            hnsub_log( 'Renewal order skipped on status change', array( 'order' => $order_id ) );
            return;
        }

        if ( function_exists( 'wcs_order_contains_subscription' ) && function_exists( 'wcs_get_subscriptions_for_order' ) && wcs_order_contains_subscription( $order ) ) {
            $subs = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
            if ( is_wp_error( $subs ) || ! $subs || ! is_array( $subs ) ) { return; }
            foreach ( $subs as $sub ) {
                // Per-subscription options, so a Pro rule (e.g. per-product activation
                // statuses) can override the globally configured ones. Free installs get
                // $opts back unchanged, since no filter is registered.
                $sub_opts = hnsub_subscription_options( $opts, $sub, $order );
                $sub_activate_statuses = array_map( 'hnsub_strip_wc_prefix', (array) $sub_opts['activate_on_statuses'] );

                // Held by us when this status change began and active now: WooCommerce Subscriptions
                // has just activated it because the order became paid.
                $activated_by_wcs = $sub instanceof WC_Subscription && isset( $held_before[ $sub->get_id() ] ) && $sub->has_status( 'active' );

                if ( ! in_array( $new_status, $sub_activate_statuses, true ) ) {
                    // Paid, but not yet at the status the store chose: keep holding.
                    if ( $activated_by_wcs ) {
                        hnsub_hold_again( $sub, $order, $held_before[ $sub->get_id() ], $sub_activate_statuses );
                    }
                    continue;
                }

                $reason = sprintf(
                    /* translators: %s: order status that triggered activation */
                    __( 'Activated when parent order reached target status "%s".', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
                    (string) $new_status
                );

                if ( $activated_by_wcs ) {
                    // The order went from unpaid straight to an activation status, so the subscription
                    // is already active. Record it as the activation it is: flag, note, email, hooks.
                    if ( ! hnsub_get_flag( $sub, 'activated' ) ) {
                        hnsub_set_flag( $sub, 'activated', '1' );
                        hnsub_after_activation( $sub, $order, $reason );
                    }
                    continue;
                }

                // A parent order activates only what this plugin holds (noted when the status change
                // began) or has marked at checkout and not held yet. Anything else that happens to be
                // on-hold or pending is waiting for something else — typically a renewal payment —
                // and is not ours to activate.
                $ours = $sub instanceof WC_Subscription
                    && ( isset( $held_before[ $sub->get_id() ] ) || hnsub_get_flag( $sub, 'hold_target' ) );
                if ( ! $ours ) {
                    continue;
                }

                hnsub_activate_subscription( $sub, $order, $reason );
            }
        }
    }, 10, 3 );

    // The hold ends when the subscription leaves on-hold/pending, whoever moved it. (When it was
    // WooCommerce Subscriptions and too early, the order status handler above holds it again.)
    add_action( 'woocommerce_subscription_status_changed', function( $subscription_id, $old_status, $new_status, $subscription = null ) {
        if ( in_array( $new_status, array( 'on-hold', 'pending' ), true ) ) { return; }
        if ( ! $subscription instanceof WC_Subscription ) {
            $subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : false;
        }
        if ( $subscription instanceof WC_Subscription && hnsub_get_flag( $subscription, 'holding' ) ) {
            hnsub_clear_flag( $subscription, 'holding' );
        }
    }, 10, 4 );
}
add_action( 'plugins_loaded', 'hnsub_boot', 5 );

/**
 * Cleanup is handled via uninstall.php (WordPress will call it on plugin deletion).
 */
