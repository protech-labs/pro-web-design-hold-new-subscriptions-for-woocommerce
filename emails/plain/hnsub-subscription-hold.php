<?php
/**
 * HNS: Subscription on hold — plain text email template.
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
    /* translators: 1: subscription ID, 2: order ID */
    esc_html__( 'Your subscription #%1$d has been placed on hold while we process your order #%2$d.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
    isset( $subscription ) ? absint( $subscription->get_id() ) : 0,
    isset( $order ) ? absint( $order->get_id() ) : 0
);
echo "\n\n";

esc_html_e( 'It will be activated automatically once our specialists have reviewed and approved your order.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' );
echo "\n";
esc_html_e( 'You will receive an additional confirmation once your subscription is activated.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' );
echo "\n\n";

echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
