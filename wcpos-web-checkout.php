<?php
/**
 * Plugin Name: WooCommerce POS Web Checkout Gateway
 * Plugin URI: https://github.com/wcpos/web-checkout-gateway
 * Description: Open a web checkout page for the customer to pay for the order.
 * Version: 0.0.1
 * Author: kilbot
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-pos-web-checkout-gateway
 */

add_action( 'plugins_loaded', 'woocommerce_pos_web_checkout_gateway_init', 0 );

function woocommerce_pos_web_checkout_gateway_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'woocommerce-pos-web-checkout-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
	 * Gateway class
	 */
	class WCPOS_Web_Checkout extends WC_Payment_Gateway {
		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			$this->id                 = 'wcpos_web_checkout';
			$this->icon               = '';
			$this->has_fields         = false;
			$this->method_title       = 'Web Checkout Gateway';
			$this->method_description = 'Open a web checkout page for the customer to pay for the order.';

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			// only allow in the POS
			$this->enabled = false;

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'title'       => array(
					'title'       => __( 'Title', 'woocommerce-pos-web-checkout-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-pos-web-checkout-gateway' ),
					'default'     => __( 'Web Checkout', 'woocommerce-pos-web-checkout-gateway' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce-pos-web-checkout-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-pos-web-checkout-gateway' ),
					'default'     => __( 'Go to the checkout page to pay for the order. After payment, click the Process Payment button to proceed to the receipt.', 'woocommerce-pos-web-checkout-gateway' ),
				),
			);
		}

		public function payment_fields() {
			global $wp;
			echo wpautop( wptexturize( $this->description ) );

			$order_id      = isset( $wp->query_vars['order-pay'] ) ? $wp->query_vars['order-pay'] : null;
			$order         = $order_id ? wc_get_order( $order_id ) : null;
			$order_pay_url = $order ? esc_url( $order->get_checkout_payment_url() ) : '#';

			echo '<a href="' . $order_pay_url . '" target="_blank" rel="noopener noreferrer">' . __( 'Pay for this order', 'woocommerce-pos-web-checkout-gateway' ) . '</a>';
		}

		public function process_payment( $order_id ) {
			// Logic for processing payment (if needed)
		}
	}

	/**
	 * Check if the order needs payment and possibly redirect
	 */
	add_filter(
		'woocommerce_order_needs_payment',
		function ( $needs_payment, $order ) {
			$posted_payment_method = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '';
			$created_via           = $order->get_created_via();
			if ( ! $needs_payment && $posted_payment_method === 'wcpos_web_checkout' && $created_via === 'woocommerce-pos' ) {
				add_action(
					'woocommerce_before_pay_action',
					function ( $order ) {
						wp_redirect( $order->get_checkout_order_received_url() );
						exit;
					}
				);
				return true;
			}
			return $needs_payment;
		},
		10,
		2
	);

	/**
	 * Add the Gateway to WooCommerce
	 */
	add_filter(
		'woocommerce_payment_gateways',
		function ( $methods ) {
			$methods[] = 'WCPOS_Web_Checkout';
			return $methods;
		}
	);
}
