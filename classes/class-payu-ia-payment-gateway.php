<?php
/**
 * PayU Payment Gateway
 *
 * Provides a PayU Payment Gateway.
 *
 * @class WC_Gateway_PayU
 * @package WooCommerce
 * @category Payment Gateways
 * @author Inspire Labs
 *
 */

if ( class_exists( 'WC_Payment_Gateway' ) ) {


	class WC_Gateway_Payu_IA extends WC_Payment_Gateway {

		/** @var WC_Gateway_Payu */
		private $payu_gateway = null;

		public function __construct() {
			$this->id           = 'payu_ia';
			$this->method_title = __( 'PayU Raty', 'woocommerce_payu' );
			$this->has_fields   = false;

			add_action( 'woocommerce_receipt_payu_ia', [ $this, 'receipt_page' ] );


		} // End Constructor

		public function init_payu( WC_Gateway_Payu $payu_gateway ) {
			$this->payu_gateway = $payu_gateway;
			$this->title        = $payu_gateway->settings['ia_title'];
			$this->description  = $payu_gateway->settings['ia_description'];
			$this->icon         = $payu_gateway->plugin_url() . '/assets/images/icon.png';
			$this->enabled      = 'yes';

			// na razie zawsze tylko PLN
			//if ( empty( $this->settings['api_version'] ) || $this->settings['api_version'] == 'classic_api' ) {
			// Check if the currency is set to PLN. If not we disable the plugin here.
			if ( get_option( 'woocommerce_currency' ) == 'PLN' ) {
				$gw_enabled = 'yes';
			} else {
				$gw_enabled = 'no';
			} // End check currency
			//}

			$this->enabled = $gw_enabled;

		}

		/**
		 * Receipt page.
		 *
		 * Display text and a button to direct the user to the payment screen.
		 *
		 * @since 1.0.0
		 */
		public function receipt_page( $order ) {
			$this->payu_gateway->receipt_page( $order );
		} // End receipt_page()

		public function process_payment( $order_id ) {
			return $this->payu_gateway->process_payment( $order_id );
		}


	}

}