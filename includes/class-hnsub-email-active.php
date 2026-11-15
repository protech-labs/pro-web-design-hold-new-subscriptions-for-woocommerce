<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce email: subscription activated.
 */
class HNSUB_Email_Active extends WC_Email {

    /** @var WC_Subscription */
    public $subscription;

    public function __construct() {
        $this->id             = 'hnsub_subscription_active';
        $this->title          = __( 'Subscription Activated (HNS)', 'pro-web-design-hold-new-subscriptions-for-woocommerce' );
        $this->description    = __( 'Sent when a held subscription is activated after order approval.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' );
        $this->customer_email = true;
        $this->heading        = __( 'Your subscription is now active', 'pro-web-design-hold-new-subscriptions-for-woocommerce' );
        $this->subject        = __( 'Your subscription #{subscription_id} is now active', 'pro-web-design-hold-new-subscriptions-for-woocommerce' );

        $this->template_html  = 'hnsub-subscription-active.php';
        $this->template_plain = 'plain/hnsub-subscription-active.php';
        $this->template_base  = HNSUB_PLUGIN_DIR . 'emails/';

        $this->placeholders = array(
            '{subscription_id}' => '',
            '{order_id}'        => '',
        );

        parent::__construct();
    }

    /**
     * Trigger the email send.
     *
     * @param WC_Order        $order
     * @param WC_Subscription $subscription
     */
    public function trigger( $order, $subscription ) {
        if ( ! $order instanceof WC_Order || ! $subscription instanceof WC_Subscription ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->warning(
                    'HNS: active email trigger() called with invalid order or subscription.',
                    array( 'source' => 'hold-new-subscriptions' )
                );
            }
            return;
        }

        $this->object       = $order;
        $this->subscription = $subscription;
        $this->recipient    = $order->get_billing_email();

        $this->placeholders['{subscription_id}'] = $subscription->get_id();
        $this->placeholders['{order_id}']        = $order->get_id();

        if ( $this->is_enabled() && $this->get_recipient() ) {
            $this->setup_locale();
            $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );
            $this->restore_locale();
        }
    }

    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'         => $this->object,
                'subscription'  => $this->subscription,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
            ),
            '',
            $this->template_base
        );
    }

    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'         => $this->object,
                'subscription'  => $this->subscription,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
            ),
            '',
            $this->template_base
        );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
                'default' => 'yes',
            ),
            'subject' => array(
                'title'       => __( 'Subject', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
                'type'        => 'text',
                /* translators: %s: list of placeholder tags available in this field */
                'description' => sprintf( __( 'Available placeholders: %s', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ), '<code>{subscription_id}, {order_id}</code>' ),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'heading' => array(
                'title'       => __( 'Email Heading', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
                'type'        => 'text',
                /* translators: %s: list of placeholder tags available in this field */
                'description' => sprintf( __( 'Available placeholders: %s', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ), '<code>{subscription_id}, {order_id}</code>' ),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'email_type' => array(
                'title'       => __( 'Email type', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'pro-web-design-hold-new-subscriptions-for-woocommerce' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }
}
