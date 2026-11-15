<?php
/**
 * HNS: Subscription activated — plain text email template.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

echo "= " . esc_html( wp_strip_all_tags( isset( $email_heading ) ? $email_heading : '' ) ) . " =\n\n";

printf(
    /* translators: %s: customer first name */
    esc_html__( 'Hello %s,', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
    esc_html( wp_strip_all_tags( isset( $order ) ? $order->get_billing_first_name() : '' ) )
);
echo "\n\n";

printf(
    /* translators: %d: subscription ID */
    esc_html__( 'Your subscription #%d has been activated.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
    isset( $subscription ) ? absint( $subscription->get_id() ) : 0
);
echo "\n";
esc_html_e( 'Thank you for your purchase!', 'pro-web-design-hold-new-subscriptions-for-woocommerce' );
echo "\n\n";

echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
