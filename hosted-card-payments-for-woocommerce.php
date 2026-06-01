<?php
/**
 * Plugin Name:       Hosted Card Payments for WooCommerce
 * Plugin URI:        https://github.com/anojdinuranga/hosted-card-payments-for-woocommerce
 * Description:       WooCommerce payment gateway for CyberSource Secure Acceptance (Hosted Checkout). Works with any CyberSource-backed acquirer. Signed request + verified (HMAC-SHA256) response.
 * Version:           2.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            Anoj Dinuranga
 * Author URI:        https://www.anojdinuranga.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       hosted-card-payments-for-woocommerce
 * Domain Path:       /languages
 * Tested up to:      6.8
 * WC requires at least: 7.0
 * WC tested up to:   9.8
 *
 * @package HostedCardPaymentsForWooCommerce
 *
 * Hosted Card Payments for WooCommerce is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'HCP_WC_VERSION', '2.0.0' );
define( 'HCP_WC_FILE', __FILE__ );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
 * and the Cart & Checkout Blocks. Runs before WooCommerce boots.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HCP_WC_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', HCP_WC_FILE, true );
		}
	}
);

/**
 * Show an admin notice if WooCommerce is not active. The `Requires Plugins`
 * header handles this on WP 6.5+, but this keeps older sites informed.
 */
add_action(
	'admin_notices',
	static function () {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Hosted Card Payments for WooCommerce requires WooCommerce to be installed and active.', 'hosted-card-payments-for-woocommerce' );
		echo '</p></div>';
	}
);

/**
 * Add a "Settings" link on the plugins screen.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url   = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=hosted_card_payments' );
		$links = array_merge(
			array( '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'hosted-card-payments-for-woocommerce' ) . '</a>' ),
			$links
		);
		return $links;
	}
);

add_action( 'plugins_loaded', 'hcp_wc_init', 11 );

/**
 * Register the gateway once WooCommerce's gateway base class is available.
 */
function hcp_wc_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/**
	 * CyberSource Secure Acceptance (Hosted Checkout) payment gateway.
	 */
	class WC_Gateway_Hosted_Card_Payments extends WC_Payment_Gateway {

		/** Test (sandbox) Secure Acceptance endpoint. */
		const ENDPOINT_TEST = 'https://testsecureacceptance.cybersource.com/pay';

		/** Production Secure Acceptance endpoint. */
		const ENDPOINT_LIVE = 'https://secureacceptance.cybersource.com/pay';

		/** @var string Secure Acceptance Profile ID. */
		public $profile_id;

		/** @var string Secure Acceptance Access Key. */
		public $access_key;

		/** @var string Secure Acceptance Secret Key (kept server-side only). */
		public $secret_key;

		/** @var string Resolved Secure Acceptance POST endpoint. */
		public $endpoint;

		/** @var string sale|authorization */
		public $transaction_type;

		/** @var string Customer-facing success message. */
		public $success_message;

		/** @var string Customer-facing failure message. */
		public $failed_message;

		/** @var bool Whether debug logging is enabled. */
		public $debug;

		/** @var string CyberSource posts the result back to this URL. */
		public $callback;

		public function __construct() {
			$this->id                 = 'hosted_card_payments';
			$this->method_title       = __( 'Hosted Card Payments', 'hosted-card-payments-for-woocommerce' );
			$this->method_description = __( 'Accept card payments through CyberSource Secure Acceptance Hosted Checkout (any CyberSource acquirer).', 'hosted-card-payments-for-woocommerce' );
			$this->has_fields         = false;
			$this->supports           = array( 'products' );

			// Only set an icon if the image actually ships with the plugin.
			$icon_file = plugin_dir_path( __FILE__ ) . 'assets/images/card-logo.png';
			if ( file_exists( $icon_file ) ) {
				$this->icon = plugins_url( 'assets/images/card-logo.png', __FILE__ );
			}

			$this->init_form_fields();
			$this->init_settings();

			$this->title            = $this->get_option( 'title' );
			$this->description      = $this->get_option( 'description' );
			$this->profile_id       = $this->get_option( 'profile_id' );
			$this->access_key       = $this->get_option( 'access_key' );
			$this->secret_key       = $this->get_option( 'secret_key' );
			$this->endpoint         = $this->get_option( 'endpoint', self::ENDPOINT_TEST );
			$this->transaction_type = $this->get_option( 'transaction_type', 'sale' );
			$this->success_message  = $this->get_option( 'thank_you_msg' );
			$this->failed_message   = $this->get_option( 'transaction_failed_msg' );
			$this->debug            = 'yes' === $this->get_option( 'debug', 'no' );

			// Optional manual endpoint override: only used when the checkbox is on AND a URL is set.
			$custom_endpoint = trim( (string) $this->get_option( 'custom_endpoint' ) );
			if ( 'yes' === $this->get_option( 'override_endpoint' ) && '' !== $custom_endpoint ) {
				$this->endpoint = $custom_endpoint;
			}

			/**
			 * Filter the resolved Secure Acceptance endpoint.
			 *
			 * @param string           $endpoint Endpoint URL.
			 * @param WC_Gateway_Hosted_Card_Payments $gateway  The gateway instance.
			 */
			$this->endpoint = apply_filters( 'hcp_wc_endpoint', $this->endpoint, $this );

			// CyberSource posts the result back here (woocommerce_api_{lowercased id}).
			$this->callback = home_url( '/wc-api/' . $this->id );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_api_' . $this->id, array( $this, 'check_response' ) );
		}

		/* ------------------------------------------------------------------ */
		/*  Admin settings                                                    */
		/* ------------------------------------------------------------------ */

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'                => array(
					'title'   => __( 'Enable/Disable', 'hosted-card-payments-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Hosted Card Payments.', 'hosted-card-payments-for-woocommerce' ),
					'default' => 'no',
				),
				'title'                  => array(
					'title'       => __( 'Title', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Title shown to the customer during checkout.', 'hosted-card-payments-for-woocommerce' ),
					'default'     => __( 'Credit/Debit Card', 'hosted-card-payments-for-woocommerce' ),
				),
				'description'            => array(
					'title'       => __( 'Description', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'textarea',
					'desc_tip'    => true,
					'description' => __( 'Description shown to the customer during checkout.', 'hosted-card-payments-for-woocommerce' ),
					'default'     => __( 'Pay securely by Credit/Debit Card.', 'hosted-card-payments-for-woocommerce' ),
				),
				'profile_id'             => array(
					'title'       => __( 'Profile ID', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Secure Acceptance Profile ID (Business Center > Payment Configuration > Secure Acceptance Settings). This is NOT your merchant/organization ID.', 'hosted-card-payments-for-woocommerce' ),
				),
				'access_key'             => array(
					'title'       => __( 'Access Key', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Access Key of the Secure Acceptance profile.', 'hosted-card-payments-for-woocommerce' ),
				),
				'secret_key'             => array(
					'title'       => __( 'Secret Key', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'password',
					'desc_tip'    => true,
					'description' => __( 'Secret Key of the Secure Acceptance profile. Keep this confidential.', 'hosted-card-payments-for-woocommerce' ),
				),
				'endpoint'               => array(
					'title'       => __( 'Environment', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'select',
					'desc_tip'    => true,
					'description' => __( 'Use Test while integrating, Production when live.', 'hosted-card-payments-for-woocommerce' ),
					'default'     => self::ENDPOINT_TEST,
					'options'     => array(
						self::ENDPOINT_TEST => __( 'Test (sandbox)', 'hosted-card-payments-for-woocommerce' ),
						self::ENDPOINT_LIVE => __( 'Production (live)', 'hosted-card-payments-for-woocommerce' ),
					),
				),
				'override_endpoint'      => array(
					'title'       => __( 'Override Endpoint', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Use a custom Secure Acceptance URL instead of the option above.', 'hosted-card-payments-for-woocommerce' ),
					'default'     => 'no',
					'description' => __( 'When enabled, the Custom Endpoint URL below is used.', 'hosted-card-payments-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'custom_endpoint'        => array(
					'title'       => __( 'Custom Endpoint URL', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'placeholder' => self::ENDPOINT_LIVE,
					'description' => __( 'Only used when "Override Endpoint" is enabled. Leave blank to use the Test/Production option above.', 'hosted-card-payments-for-woocommerce' ),
				),
				'transaction_type'       => array(
					'title'   => __( 'Transaction Type', 'hosted-card-payments-for-woocommerce' ),
					'type'    => 'select',
					'default' => 'sale',
					'options' => array(
						'sale'          => __( 'Sale (authorize + capture)', 'hosted-card-payments-for-woocommerce' ),
						'authorization' => __( 'Authorization only', 'hosted-card-payments-for-woocommerce' ),
					),
				),
				'thank_you_msg'          => array(
					'title'       => __( 'Transaction Success Message', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'textarea',
					'desc_tip'    => true,
					'description' => __( 'Message displayed after a successful transaction.', 'hosted-card-payments-for-woocommerce' ),
					'default'     => __( 'Thank you for shopping with us. Your payment was successful.', 'hosted-card-payments-for-woocommerce' ),
				),
				'transaction_failed_msg' => array(
					'title'       => __( 'Transaction Failed Message', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'textarea',
					'desc_tip'    => true,
					'description' => __( 'Message displayed after a failed transaction.', 'hosted-card-payments-for-woocommerce' ),
					'default'     => __( 'Thank you for shopping with us. However, the transaction has been declined.', 'hosted-card-payments-for-woocommerce' ),
				),
				'debug'                  => array(
					'title'       => __( 'Debug Log', 'hosted-card-payments-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Log gateway events to WooCommerce > Status > Logs.', 'hosted-card-payments-for-woocommerce' ),
					'default'     => 'no',
					'description' => __( 'Logs request/response metadata (never card data or the secret key) to help debugging.', 'hosted-card-payments-for-woocommerce' ),
					'desc_tip'    => true,
				),
			);
		}

		public function admin_options() {
			echo '<h3>' . esc_html__( 'Hosted Card Payments', 'hosted-card-payments-for-woocommerce' ) . '</h3>';
			echo '<p>';
			printf(
				/* translators: %s: response callback URL */
				esc_html__( 'In your Secure Acceptance profile, allow this response/receipt URL: %s', 'hosted-card-payments-for-woocommerce' ),
				'<code>' . esc_html( home_url( '/wc-api/' . $this->id ) ) . '</code>'
			);
			echo '</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		public function payment_fields() {
			if ( $this->description ) {
				echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
			}
		}

		/* ------------------------------------------------------------------ */
		/*  Checkout -> redirect to receipt page                              */
		/* ------------------------------------------------------------------ */

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}

		public function receipt_page( $order_id ) {
			echo '<p>' . esc_html__( 'Thank you for your order. Please click below to pay securely.', 'hosted-card-payments-for-woocommerce' ) . '</p>';
			// generate_payment_form() builds escaped, hidden inputs only.
			echo $this->generate_payment_form( $order_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/* ------------------------------------------------------------------ */
		/*  Build the signed Secure Acceptance form                           */
		/* ------------------------------------------------------------------ */

		public function generate_payment_form( $order_id ) {

			$order = wc_get_order( $order_id );

			$amount   = number_format( (float) $order->get_total(), 2, '.', '' );
			$currency = $order->get_currency();

			$fields = array(
				'access_key'                   => $this->access_key,
				'profile_id'                   => $this->profile_id,
				'transaction_uuid'             => $this->uuid(),
				'signed_field_names'           => '',
				'unsigned_field_names'         => '',
				'signed_date_time'             => gmdate( "Y-m-d\TH:i:s\Z" ),
				'locale'                       => apply_filters( 'hcp_wc_locale', 'en', $order ),
				'transaction_type'             => $this->transaction_type ? $this->transaction_type : 'sale',
				'reference_number'             => $order->get_id(),
				'amount'                       => $amount,
				'currency'                     => $currency,
				// Override the profile's response page so CyberSource returns to WooCommerce.
				'override_custom_receipt_page' => $this->callback,
				// Billing details.
				'bill_to_forename'             => $order->get_billing_first_name(),
				'bill_to_surname'              => $order->get_billing_last_name(),
				'bill_to_email'                => $order->get_billing_email(),
				'bill_to_phone'                => $order->get_billing_phone(),
				'bill_to_address_line1'        => $order->get_billing_address_1(),
				'bill_to_address_city'         => $order->get_billing_city(),
				'bill_to_address_state'        => $order->get_billing_state(),
				'bill_to_address_country'      => $order->get_billing_country(),
				'bill_to_address_postal_code'  => $order->get_billing_postcode(),
			);

			/**
			 * Filter the request fields sent to CyberSource before signing.
			 * Use this to add Level II/III data, merchant_defined_data, etc.
			 *
			 * @param array    $fields The field map.
			 * @param WC_Order $order  The order being paid.
			 */
			$fields = apply_filters( 'hcp_wc_request_fields', $fields, $order );

			// Sign every field we send.
			$fields['signed_field_names'] = implode( ',', array_keys( $fields ) );
			$fields['signature']          = $this->sign_params( $fields );

			$this->log( sprintf( 'Building request for order #%d (amount %s %s).', $order->get_id(), $amount, $currency ) );

			$form = '<form id="hcp_payment_form" method="post" action="' . esc_url( $this->endpoint ) . '">';
			foreach ( $fields as $name => $value ) {
				$form .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
			}
			$form .= '<input type="submit" class="button alt" value="' . esc_attr__( 'Pay Now', 'hosted-card-payments-for-woocommerce' ) . '" />';
			$form .= '</form>';
			$form .= '<script type="text/javascript">document.getElementById("hcp_payment_form").submit();</script>';

			return $form;
		}

		/* ------------------------------------------------------------------ */
		/*  Response handler                                                  */
		/* ------------------------------------------------------------------ */

		public function check_response() {

			// CyberSource posts back form-encoded data. Authenticity is established by
			// the HMAC signature below — not by a WP nonce — so we read the raw params.
			$params = ! empty( $_POST ) ? wp_unslash( $_POST ) : wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

			$order_id = isset( $params['req_reference_number'] ) ? absint( $params['req_reference_number'] ) : 0;
			$order    = $order_id ? wc_get_order( $order_id ) : false;

			if ( ! $order ) {
				$this->log( 'Response with invalid/unknown order reference rejected.', 'error' );
				wp_die( esc_html__( 'Invalid order reference.', 'hosted-card-payments-for-woocommerce' ), 'CyberSource Payment', array( 'response' => 400 ) );
			}

			// Default messages.
			$success_message = trim( (string) $this->success_message ) !== ''
				? $this->success_message
				: __( 'Thank you for shopping with us. Your payment was successful.', 'hosted-card-payments-for-woocommerce' );
			$failed_message  = trim( (string) $this->failed_message ) !== ''
				? $this->failed_message
				: __( 'Thank you for shopping with us. However, the transaction has been declined.', 'hosted-card-payments-for-woocommerce' );

			$msg = array(
				'class'   => 'error',
				'message' => $failed_message,
			);

			$verified = $this->verify_response( $params );
			$decision = isset( $params['decision'] ) ? strtoupper( sanitize_text_field( $params['decision'] ) ) : 'ERROR';
			$receipt  = isset( $params['transaction_id'] ) ? sanitize_text_field( $params['transaction_id'] ) : '';

			// Defence in depth: even with a valid signature, confirm the amount and
			// currency match the order, so a misconfigured profile cannot under-charge.
			$amount_ok = $this->amount_matches( $order, $params );

			$authorised = ( $verified && 'ACCEPT' === $decision && $amount_ok );

			if ( $authorised ) {
				if ( ! $order->has_status( array( 'completed', 'processing' ) ) ) {
					$msg = array(
						'class'   => 'success',
						'message' => $success_message,
					);

					$order->payment_complete( $receipt );
					$order->add_order_note(
						sprintf(
							/* translators: %s: gateway transaction id */
							__( 'CyberSource payment successful. Transaction ID: %s', 'hosted-card-payments-for-woocommerce' ),
							$receipt
						)
					);

					if ( WC()->cart ) {
						WC()->cart->empty_cart();
					}

					$this->log( sprintf( 'Order #%d marked paid. Transaction ID: %s', $order->get_id(), $receipt ) );

					/**
					 * Fires after a verified, accepted payment is recorded.
					 *
					 * @param WC_Order $order  The paid order.
					 * @param array    $params Raw response params from CyberSource.
					 */
					do_action( 'hcp_wc_payment_complete', $order, $params );
				}
			} else {
				if ( ! $verified ) {
					$note = __( 'Payment response signature verification FAILED — possible tampering.', 'hosted-card-payments-for-woocommerce' );
				} elseif ( ! $amount_ok ) {
					$note = __( 'Payment amount/currency mismatch — rejected.', 'hosted-card-payments-for-woocommerce' );
				} else {
					$note = sprintf(
						/* translators: 1: decision 2: reason code */
						__( 'Payment not approved. Decision: %1$s (reason %2$s)', 'hosted-card-payments-for-woocommerce' ),
						$decision,
						isset( $params['reason_code'] ) ? sanitize_text_field( $params['reason_code'] ) : '?'
					);
				}
				$order->update_status( 'failed', $note );
				$this->log( sprintf( 'Order #%d failed: %s', $order->get_id(), $note ), 'error' );

				/**
				 * Fires when a payment is not accepted (declined, unverified or mismatched).
				 *
				 * @param WC_Order $order  The order.
				 * @param array    $params Raw response params from CyberSource.
				 */
				do_action( 'hcp_wc_payment_failed', $order, $params );
			}

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $msg['message'], $msg['class'] );
			}

			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		/* ------------------------------------------------------------------ */
		/*  Secure Acceptance signature helpers                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Confirm the signed amount and currency in the response match the order.
		 */
		private function amount_matches( $order, array $params ) {
			if ( ! isset( $params['req_amount'], $params['req_currency'] ) ) {
				return false;
			}
			$expected_amount   = number_format( (float) $order->get_total(), 2, '.', '' );
			$expected_currency = strtoupper( $order->get_currency() );
			$got_amount        = number_format( (float) $params['req_amount'], 2, '.', '' );
			$got_currency      = strtoupper( sanitize_text_field( $params['req_currency'] ) );

			return hash_equals( $expected_amount, $got_amount ) && hash_equals( $expected_currency, $got_currency );
		}

		private function build_data_to_sign( array $params, $signed_field_names ) {
			$names = explode( ',', $signed_field_names );
			$pairs = array();
			foreach ( $names as $name ) {
				$name    = trim( $name );
				$value   = array_key_exists( $name, $params ) ? $params[ $name ] : '';
				$pairs[] = $name . '=' . $value;
			}
			return implode( ',', $pairs );
		}

		private function sign( $data ) {
			return base64_encode( hash_hmac( 'sha256', $data, $this->secret_key, true ) );
		}

		private function sign_params( array $params ) {
			return $this->sign( $this->build_data_to_sign( $params, $params['signed_field_names'] ) );
		}

		private function verify_response( array $params ) {
			if ( empty( $params['signed_field_names'] ) || ! isset( $params['signature'] ) ) {
				return false;
			}
			$expected = $this->sign( $this->build_data_to_sign( $params, $params['signed_field_names'] ) );
			return hash_equals( $expected, (string) $params['signature'] );
		}

		/**
		 * RFC 4122 version-4 UUID for transaction_uuid.
		 */
		private function uuid() {
			$data    = random_bytes( 16 );
			$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
			$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
			return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
		}

		/**
		 * Write a line to the WooCommerce logger when debug is enabled.
		 * Never logs card data or the secret key.
		 */
		private function log( $message, $level = 'info' ) {
			if ( ! $this->debug || ! function_exists( 'wc_get_logger' ) ) {
				return;
			}
			wc_get_logger()->log( $level, $message, array( 'source' => $this->id ) );
		}
	}

	/**
	 * Register the gateway with WooCommerce.
	 *
	 * @param array $methods Registered gateway class names.
	 * @return array
	 */
	function add_hosted_card_payments_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Hosted_Card_Payments';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_hosted_card_payments_gateway' );
}

/* ---------------------------------------------------------------------- */
/*  WooCommerce Blocks (Cart/Checkout) integration                        */
/*  Without this the gateway only appears in the classic shortcode        */
/*  checkout, not the newer block-based checkout.                         */
/* ---------------------------------------------------------------------- */
add_action(
	'woocommerce_blocks_payment_method_type_registration',
	static function ( $registry ) {

		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payment\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		// Guard against re-declaration if the hook fires more than once.
		if ( ! class_exists( 'Hosted_Card_Payments_WC_Blocks_Support' ) ) {

		/**
		 * Registers the gateway with the Cart/Checkout blocks.
		 */
		final class Hosted_Card_Payments_WC_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payment\Integrations\AbstractPaymentMethodType {

			/** @var string Must match the gateway id. */
			protected $name = 'hosted_card_payments';

			public function initialize() {
				$this->settings = get_option( 'woocommerce_hosted_card_payments_settings', array() );
			}

			public function is_active() {
				return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
			}

			public function get_payment_method_script_handles() {
				$handle = 'hosted-card-payments-blocks';

				wp_register_script(
					$handle,
					plugins_url( 'assets/js/blocks.js', HCP_WC_FILE ),
					array( 'wc-blocks-registry', 'wp-element', 'wp-html-entities', 'wc-settings' ),
					HCP_WC_VERSION,
					true
				);

				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations( $handle, 'hosted-card-payments-for-woocommerce' );
				}

				return array( $handle );
			}

			public function get_payment_method_data() {
				$icon      = '';
				$icon_file = plugin_dir_path( HCP_WC_FILE ) . 'assets/images/card-logo.png';
				if ( file_exists( $icon_file ) ) {
					$icon = plugins_url( 'assets/images/card-logo.png', HCP_WC_FILE );
				}

				return array(
					'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : 'Credit/Debit Card',
					'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
					'icon'        => $icon,
					'supports'    => array( 'products' ),
				);
			}
		}

		} // class_exists guard.

		$registry->register( new Hosted_Card_Payments_WC_Blocks_Support() );
	}
);
