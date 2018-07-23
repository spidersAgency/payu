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


	class WC_Gateway_Payu_Recurring extends WC_Gateway_Payu {
		/** @var WC_Gateway_Payu */
		private $payu_gateway = null;

		public function __construct() {
			parent::__construct();
			$this->id           = 'payu_recurring';
			$this->method_title = __( 'PayU Płatności cykliczne', 'woocommerce_payu' );
			$this->has_fields   = false;

			$this->title       = $this->settings['subscriptions_title'];
			$this->description = $this->settings['subscriptions_description'];
			$this->icon        = $this->plugin_url() . '/assets/images/icon.png';
			$this->enabled     = 'yes';

			if ( isset( $this->settings['api_version'] ) && $this->settings['api_version'] == 'rest_api' ) {
				$this->supports = [
					'products',
					'refunds',
					'subscriptions',
					'subscription_cancellation',
					'subscription_reactivation',
					'subscription_suspension',
					'subscription_amount_changes',
					'subscription_payment_method_change',
					'subscription_payment_method_change_customer',
					'subscription_payment_method_change_admin',
					'subscription_date_changes',
					'multiple_subscriptions',
				];
			} else {
				$this->enabled = 'no';
			}

			$this->subscriptions_hooks();

		} // End Constructor

		protected function subscriptions_hooks() {
			if ( class_exists( 'WC_Subscriptions_Order' ) ) {
				add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id,
					[ $this, 'scheduled_subscription_payment' ], 10, 2 );
				add_action( 'woocommerce_subscription_failing_payment_method_updated_payu',
					[ $this, 'update_failing_payment_method' ], 10, 2 );
				// display the credit card used for a subscription in the "My Subscriptions" table
				add_filter( 'woocommerce_my_subscriptions_payment_method',
					[ $this, 'maybe_render_subscription_payment_method' ], 10, 2 );

			}
		}

		/**
		 * Update PayU data to complete a payment to make up for
		 * an automatic renewal payment which previously failed.
		 *
		 * @access public
		 *
		 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
		 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
		 *
		 * @return void
		 */
		public function update_failing_payment_method( $subscription, $renewal_order ) {
			$payu_card_data = wpdesk_get_order_meta( $renewal_order, '_payu_card_data', true );
			$payu_order     = wpdesk_get_order_meta( $renewal_order, '_payu_order', true );
			if ( $this->wc_pre_30 ) {
				update_post_meta( $subscription->id, '_payu_card_data', $payu_card_data );
				update_post_meta( $subscription->id, '_stripe_card_id', $payu_order );
			} else {
				$subscription->update_meta_data( '_stripe_customer_id', $payu_card_data );
				$subscription->update_meta_data( '_stripe_card_id', $payu_order );
			}
		}

		public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {

			// bail for other payment methods
			if ( $this->id !== ( $this->wc_pre_30 ? $subscription->payment_method : $subscription->get_payment_method() ) ) {
				return $payment_method_to_display;
			}

			$payu_card_data = wpdesk_get_order_meta( $subscription, '_payu_card_data', true );
			if ( is_array( $payu_card_data ) && isset( $payu_card_data['masked_card'] ) ) {
				$payment_method_to_display = sprintf( __( 'PayU, karta: %s', 'woocommercee_payu' ),
					$payu_card_data['masked_card'] );
			}

			//$payment_method_to_display = 'GRO PayU';

			return $payment_method_to_display;
		}


		/**
		 * scheduled_subscription_payment function.
		 *
		 * @param $amount_to_charge float The amount to charge.
		 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
		 */
		public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

			if ( isset( $this->settings['api_version'] ) && $this->settings['api_version'] == 'rest_api' ) {
				try {
					$this->process_subscription_payment( $renewal_order, $amount_to_charge );
				} catch ( Exception $e ) {
					$renewal_order->update_status( 'failed',
						sprintf( __( 'Transakcja PayU zakończyła się niepowodzeniem (%s)', 'woocommerce_payu' ),
							$e->getMessage() ) );
				}
			} else {
				$renewal_order->update_status( 'failed',
					__( 'Transakcja PayU zakończyła się niepowodzeniem (włącz REST API w ustawieniach PayU)',
						'woocommerce_payu' ) );
			}

		}


		/**
		 * @param WC_Order $order
		 * @param int $amount
		 *
		 * @throws Exception
		 */
		public function process_subscription_payment( WC_Order $order, $amount = 0 ) {
			$payu_card_data = wpdesk_get_order_meta( $order, '_payu_card_data', true );
			$payu_order     = wpdesk_get_order_meta( $order, '_payu_order', true );

			if ( $payu_card_data == '' ) {
				throw new Exception( __( 'Brak danych do płatności: dane karty', 'woocommerce_payu' ) );
			}

			$recuring = 'STANDARD';
			if ( is_array( $payu_order ) && isset( $payu_order['payMethods']['payMethod'] ) && isset( $payu_order['payMethods']['payMethod']['value'] ) ) {
				$payu_card_data['value'] = $payu_order['payMethods']['payMethod']['value'];
			} else {
				$recuring = false;
				if ( strpos( $payu_card_data['value'], 'TOKC_' ) === 0 ) {
					$recuring = 'STANDARD';
				}
			}
			wpdesk_update_order_meta( $order, '_payu_card_data', $payu_card_data );

			$payu_order   = $this->create_payu_order( $order, false, true, $payu_card_data, $recuring );
			$status_codes = [
				'WARNING_CONTINUE_3DS',
				'WARNING_CONTINUE_CVV',
			];
			if ( isset( $payu_order['status'] ) && isset( $payu_order['status']['statusCode'] ) && in_array( $payu_order['status']['statusCode'],
					$status_codes ) ) {
				$order->update_status( 'failed',
					__( 'Zamówienie nie zostało opłacone - wymagana dodatkowa autoryzacja karty.',
						'woocommerce_payu' ) );
				$order->add_order_note( sprintf( __( 'Zamówienie nie zostało opłacone - wymagana dodatkowa autoryzacja karty. Aby przeprowadzić autoryzację kliknij tutaj: %s.',
					'woocommerce_payu' ),
					'<a href="' . $payu_order['redirectUri'] . '">' . $payu_order['redirectUri'] . '</a>' ), 1 );
				if ( ! $this->wc_pre_30 ) {
					$order->save();
				}
			}

			$order_id = $this->wc_pre_30 ? $order->id : $order->get_id();

			// Also store it on the subscriptions being purchased or paid for in the order
			if ( wcs_order_contains_subscription( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			} elseif ( wcs_order_contains_renewal( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			} else {
				$subscriptions = [];
			}

			$payu_card_data = wpdesk_get_order_meta( $order, '_payu_card_data', true );
			$payu_order     = wpdesk_get_order_meta( $order, '_payu_order', true );

			foreach ( $subscriptions as $subscription ) {
				$subscription_id = $this->wc_pre_30 ? $subscription->id : $subscription->get_id();
				update_post_meta( $subscription_id, '_payu_card_data', $payu_card_data );
				update_post_meta( $subscription_id, '_payu_order', $payu_order );
			}

		}

		protected function is_subscription( $order_id ) {
			return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
		}

		/**
		 * Include the payment meta data required to process automatic recurring payments so that store managers can
		 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen
		 *
		 * @param array $payment_meta associative array of meta data required for automatic payments
		 * @param WC_Subscription $subscription An instance of a subscription object
		 *
		 * @return array
		 */
		public function add_subscription_payment_meta( $payment_meta, $subscription ) {
			$payment_meta[ $this->id ] = [
				'post_meta' => [
					'_payu_card_data' => [
						'value' => get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ),
							'_payu_card_data', true ),
					],
					'_payu_order'     => [
						'value' => get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ),
							'_payu_order', true ),
					],
				],
			];

			return $payment_meta;
		}

		public function payment_fields() {
			parent::payment_fields();
			$cart = WC()->cart;
			$cart->calculate_totals();
			$customer                   = WC()->customer;
			$checkout                   = WC()->checkout();
			$is_cart_contains_subscription = $this->is_cart_contains_subscription();
			$subscription_renewal       = false;
			if ( ! empty( $wp->query_vars['order-pay'] ) ) {
				$order = wc_get_order( $wp->query_vars['order-pay'] );
				if ( get_class( $order ) == 'WC_Subscription' ) {
					$subscription_renewal = true;
				}
			}
			$widget_url      = 'https://secure.payu.com/front/widget/js/payu-bootstrap.js';
			$merchant_pos_id = $this->get_option( 'pos_id', '' );
			$key_2           = $this->get_option( 'key_2', '' );
			if ( isset( $this->settings['sandbox'] ) && $this->settings['sandbox'] == 'yes' ) {
				$widget_url      = 'https://secure.snd.payu.com/front/widget/js/payu-bootstrap.js';
				$merchant_pos_id = $this->get_option( 'sandbox_pos_id', '' );
				$key_2           = $this->get_option( 'sandbox_key_2', '' );
			}
			$shop_name         = $this->get_blog_alnum_name();
			$total_amount      = round( $cart->total, 2 ) * 100;
			$currency_code     = get_woocommerce_currency();
			$customer_language = 'pl';
			$store_card        = 'true';
			$recurring_payment = 'true';

			$customer_email = $checkout->get_value( 'billing_email' );

			$sig_parameters = $currency_code . $customer_email . $customer_language . $merchant_pos_id . $recurring_payment . $shop_name . $store_card . $total_amount;

			$sig = hash( 'sha256', $sig_parameters . $key_2 );

			include( 'views/payu-subscription-form.php' );
		} // End payment_fields()

		/**
		 * Returns blog alphanumeric name from option.
		 *
		 * @return string
		 */
		private function get_blog_alnum_name() {
			$blog_name = get_option( 'blogname', '' );
			if (function_exists('iconv')) {
				$blog_name = iconv("UTF-8","ISO-8859-2//IGNORE", $blog_name);
				$blog_name = iconv("ISO-8859-2","UTF-8", $blog_name);
			}
			$blog_name = preg_replace("/[^a-zA-Z0-9_.-]/", '', $blog_name);
			return $blog_name;
		}


		/**
		 * Process the payment and return the result.
		 *
		 * @since 1.0.0
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;

			$ia = false;

			$order = wc_get_order( $order_id );

			$subs           = false;
			$payu_card_data = [];

			if ( get_class( $order ) == 'WC_Subscription' ) {
				$redirect_url = $order->get_view_order_url();
				wpdesk_update_order_meta( $order, '_payu_order', '' );

				return [
					'result'   => 'success',
					'redirect' => $redirect_url
				];
			}

			if ( $order->get_status() != 'pending' ) {
				$order->set_status( 'pending' );
			}

			$this->save_payu_data( $order );

			if ( $order->get_total() == 0 ) {
				$order->add_order_note( __( 'Płatność PayU zatwierdzona - bezpłatny okres próbny.',
					'woocommerce_payu' ) );
				wpdesk_update_order_meta( $order, '_payu_payment_completed', 1 );
				$order->payment_complete();
				//$order->update_status( 'completed' );
				if ( ! $this->wc_pre_30 ) {
					$order->save();
				}

				// Also store it on the subscriptions being purchased or paid for in the order
				if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
					$subscriptions = wcs_get_subscriptions_for_order( $order_id );
				} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
					$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
				} else {
					$subscriptions = [];
				}

				$payu_card_data = wpdesk_get_order_meta( $order, '_payu_card_data', true );

				foreach ( $subscriptions as $subscription ) {
					$subscription_id = $this->wc_pre_30 ? $subscription->id : $subscription->get_id();
					update_post_meta( $subscription_id, '_payu_card_data', $payu_card_data );
				}

				$redirect_url = $order->get_checkout_order_received_url();

				return [
					'result'   => 'success',
					'redirect' => $redirect_url
				];
			}

			if ( $order->get_status() != 'pending' ) {
				$order->set_status( 'pending' );
			}

			if ( isset( $this->settings['api_version'] ) && $this->settings['api_version'] == 'rest_api' ) {
				try {
					$subs           = true;
					$payu_card_data = wpdesk_get_order_meta( $order, '_payu_card_data', true );

					$payu_order = $this->create_payu_order( $order, $ia, $subs, $payu_card_data );
				} catch ( Exception $e ) {
					wc_add_notice( $e->getMessage(), 'error' );

					return [
						'result' => 'failure',
					];
				}

				// Also store it on the subscriptions being purchased or paid for in the order
				if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
					$subscriptions = wcs_get_subscriptions_for_order( $order_id );
				} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
					$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
				} else {
					$subscriptions = [];
				}

				$payu_card_data = wpdesk_get_order_meta( $order, '_payu_card_data', true );
				$payu_order     = wpdesk_get_order_meta( $order, '_payu_order', true );

				foreach ( $subscriptions as $subscription ) {
					$subscription_id = $this->wc_pre_30 ? $subscription->id : $subscription->get_id();
					update_post_meta( $subscription_id, '_payu_card_data', $payu_card_data );
					update_post_meta( $subscription_id, '_payu_order', $payu_order );
				}

				$redirect_url = $order->get_checkout_order_received_url();
				if ( ! empty( $payu_order['redirectUri'] ) ) {
					$redirect_url = $payu_order['redirectUri'];
				}

				return [
					'result'   => 'success',
					'redirect' => $redirect_url
				];

			}
		} // End process_payment()

		/**
		 * @return bool
		 */
		public function is_cart_contains_subscription() {
			$cart = WC()->cart;
			if ( ! empty( $cart ) ) {
				/** @var WC_Product $item */
				foreach ( $cart->get_cart() as $cart_item ) {
					$item = $cart_item['data'];
					if ( $item->is_type( 'subscription' ) ) {
						return true;
					}
					if ( $item->is_type( 'subscription_variation' ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * @return bool
		 */
		public function is_available() {
			return $this->is_cart_contains_subscription();
		}

	}

}