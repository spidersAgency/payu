<?php
/**
 * Created by PhpStorm.
 * User: gro
 * Date: 27.08.2017
 * Time: 13:41
 */

class WPDesk_PayU_Rest_API {

	private $pos_id = null;
	private $client_id = null;
	private $client_secret = null;
	private $sandbox = false;
	private $api_url = 'https://secure.payu.com';
	private $bearer = false;

	public function __construct( $pos_id, $client_id, $client_secret, $sandbox = false ) {
		$this->pos_id = $pos_id;
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->sandbox = $sandbox;
		if ( $sandbox ) {
			$this->api_url = 'https://secure.snd.payu.com';
		}
	}

	private function get_bearer() {
		if ( $this->bearer === false ) {
			$transient_name = 'payu_bearer_' . md5( $this->client_id . $this->client_secret );
			$this->bearer = get_transient( $transient_name );
			if ( $this->bearer === false ) {
				$args = array(
					'sslverify' => false,
					'body'      => array(
						'grant_type'    => 'client_credentials',
						'client_id'     => $this->client_id,
						'client_secret' => $this->client_secret,
					),
				);
				$url = trailingslashit( $this->api_url ) . 'pl/standard/user/oauth/authorize';
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					throw new Exception( $response->get_error_message() );
				}
				if ( $response['response']['code'] != '200' ) {
					throw new Exception( $response['response']['message'] );
				}
				$json = json_decode( $response['body'] );
				$this->bearer = $json->access_token;
				set_transient( $transient_name, $this->bearer, intval( $json->expires_in ) - 60 );
			}
		}
		return $this->bearer;
	}

	public function ping() {
		return $this->get_bearer();
	}

	/**
	 * @param $order WC_Order
	 *
	 * @return string|void
	 */
	public function get_notify_url( $order ) {
		//return site_url( '?wpdesk_payu_notify=1' );
		return site_url('?wc-api=WC_Gateway_Payu&rest_api=1&order_id=' . wpdesk_get_order_id( $order ) );
	}

	/**
	 * @param $order WC_Order
	 */
	public function get_continue_url( $order ) {
		return $order->get_checkout_order_received_url();
	}

	/**
	 * @param $order WC_Order
	 *
	 * @throws Exception
	 */
	public function create_order( $order, $order_id, $ia = false, $subs = false, $payu_card_data = array(), $recurring = false ) {
		$bearer = $this->get_bearer();
		$data = array(
			'notifyUrl'     => $this->get_notify_url( $order ),
			'continueUrl'   => $this->get_continue_url( $order ),
			'customerIp'    => $_SERVER['REMOTE_ADDR'],
			'merchantPosId' => $this->pos_id,
			'description'   => sprintf( __( '%s, Zamówienie %s', 'woocommerce_payu' ), get_bloginfo( 'name' ), $order->get_order_number() ),
			'currencyCode'  => wpdesk_get_order_meta( wpdesk_get_order_id( $order ), '_currency', true ),
			'totalAmount'   => $order->get_total()*100,
			'extOrderId'    => $order_id,
			'buyer'         => array(
				'email'     => wpdesk_get_order_meta( $order, '_billing_email', true ),
				'phone'     => wpdesk_get_order_meta( $order, '_billing_phone', true ),
				'firstName' => wpdesk_get_order_meta( $order, '_billing_first_name', true ),
				'lastName'  => wpdesk_get_order_meta( $order, '_billing_last_name', true ),
			),
			'settings'      => array(
				'invoiceDisabled'   => 'true',
			),
			'products'      => array(
				array(
					'name'          => sprintf( __( 'Zamówienie %s', 'woocommerce_payu' ), $order->get_order_number() ),
					'unitPrice'     => $order->get_total()*100,
					'quantity'      => 1
				)
			)
		);

		if ( $recurring ) {
			$data['recurring'] = $recurring;
		}

		if ( $ia ) {
			$data['payMethods'] = array(
				'payMethod' => array(
					'type'  => 'PBL',
					'value' => 'ai'
				),
			);
		}

		if ( $subs ) {
			$data['payMethods'] = array(
				'payMethod' => array(
					'type'  => 'CARD_TOKEN',
					'value' => $payu_card_data['value'],
				),
			);
		}

		$args = array(
			'redirection'   => 0,
			'sslverify'     => false,
			'headers'       => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $bearer
			),
			'body'          => json_encode( $data ),
			'timeout'       => 45
		);

		$url = trailingslashit( $this->api_url ) . 'api/v2_1/orders';
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		if ( $response['response']['code'] != '302' && $response['response']['code'] != '201' ) {
			$json = json_decode( $response['body'], true );
			if ( isset( $json['status'] ) ) {
				$message = '';
				if ( isset( $json['status']['code'] ) ) {
					$message .= $json['status']['code'];
				}
				if ( isset( $json['status']['codeLiteral'] ) ) {
					if ( $message != '' ) {
						$message .= ' - ';
					}
					$message .= $json['status']['codeLiteral'];
				}
				if ( isset( $json['status']['statusDesc'] ) ) {
					if ( $message != '' ) {
						$message .= ' - ';
					}
					$message .= $json['status']['statusDesc'];
				}
				throw new Exception( $message );
			}
			throw new Exception( $response['response']['message'] );
		}
		$json = json_decode( $response['body'], true );
		return $json;
	}

	public function get_order( $order_id ) {
		$bearer = $this->get_bearer();
		$args = array(
			'redirection'   => 0,
			'sslverify'     => false,
			'headers'       => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $bearer
			),
		);
		$url = trailingslashit( $this->api_url ) . 'api/v2_1/orders/' . $order_id;
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		if ( $response['response']['code'] != '200' ) {
			$json = json_decode( $response['body'], true );
			if ( isset( $json['status'] ) ) {
				$message = '';
				if ( isset( $json['status']['code'] ) ) {
					$message .= $json['status']['code'];
				}
				if ( isset( $json['status']['codeLiteral'] ) ) {
					if ( $message != '' ) {
						$message .= ' - ';
					}
					$message .= $json['status']['codeLiteral'];
				}
				if ( isset( $json['status']['statusDesc'] ) ) {
					if ( $message != '' ) {
						$message .= ' - ';
					}
					$message .= $json['status']['statusDesc'];
				}
				throw new Exception( $message );
			}
			throw new Exception( $response['response']['message'] );
		}
		$json = json_decode( $response['body'], true );
		return $json;
	}

	public function refund( $order_id, $amount = null, $reason = '' ) {
		$bearer = $this->get_bearer();
		$data = array(
			'refund'    => array(
				'description'   => $reason
			)
		);
		if ( $amount != null ) {
			$data['refund']['amount'] = floatval( $amount ) * 100;
		}
		$args = array(
			'redirection'   => 0,
			'sslverify'     => false,
			'headers'       => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $bearer
			),
			'body'          => json_encode( $data ),
		);
		$url = trailingslashit( $this->api_url ) . 'api/v2_1/orders/' . $order_id . '/refunds';
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		if ( $response['response']['code'] != '200' ) {
			$json = json_decode( $response['body'], true );
			if ( isset( $json['status'] ) ) {
				$message = '';
				if ( isset( $json['status']['code'] ) ) {
					$message .= $json['status']['code'];
				}
				if ( isset( $json['status']['codeLiteral'] ) ) {
					if ( $message != '' ) {
						$message .= ' - ';
					}
					$message .= $json['status']['codeLiteral'];
				}
				if ( isset( $json['status']['statusDesc'] ) ) {
					if ( $message != '' ) {
						$message .= ' - ';
					}
					$message .= $json['status']['statusDesc'];
				}
				throw new Exception( $message );
			}
			throw new Exception( $response['response']['message'] );
		}
		$json = json_decode( $response['body'], true );
		return $json;
	}

}