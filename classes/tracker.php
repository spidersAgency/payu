<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WPDesk_PayU_Tracker' ) ) {
	class WPDesk_PayU_Tracker {

		public function __construct() {
			$this->hooks();
		}

		public function hooks() {
			add_filter( 'wpdesk_tracker_data', array( $this, 'wpdesk_tracker_data' ), 11 );
			add_filter( 'wpdesk_tracker_notice_screens', array( $this, 'wpdesk_tracker_notice_screens' ) );
			add_filter( 'wpdesk_track_plugin_deactivation', array( $this, 'wpdesk_track_plugin_deactivation' ) );
		}

		public function wpdesk_track_plugin_deactivation( $plugins ) {
			$plugins['woocommerce-gateway-payu-pl/gateway-payu.php'] = 'woocommerce-gateway-payu-pl/gateway-payu.php';
			return $plugins;
		}

		public function wpdesk_tracker_data( $data ) {
			$plugin_data = array(
				'total_number_of_transactions'          => 0,
				'avg_number_of_transactions_per_month'   => 0,
			);

			global $wpdb;
			$sql = "
				SELECT count(p.ID) AS count, min(p.post_date) AS min, max(p.post_date) AS max, TIMESTAMPDIFF(MONTH, min(p.post_date), max(p.post_date) )+1 AS months
				FROM {$wpdb->posts} p, {$wpdb->postmeta} m 
				WHERE p.post_type = 'shop_order'
					AND p.post_status = 'wc-completed'
					AND p.ID = m.post_id
					AND m.meta_key = '_payment_method'
					AND m.meta_value = 'payu'
			";
			$query = $wpdb->get_results( $sql );
			if ( $query ) {
				foreach ( $query as $row ) {
					$plugin_data['total_number_of_transactions'] = $row->count;
					if ( $row->months != 0 ) {
						$plugin_data['avg_number_of_transactions_per_month'] = floatval( $row->count )/floatval( $row->months );
					}
				}
			}

			$payu_settings = array();
			$payment = new WC_Payment_Gateways();
			$payment_gateways = $payment->payment_gateways();
			if ( isset( $payment_gateways['payu'] ) ) {
				$payu = $payment_gateways['payu'];
				$payu_settings = $payu->settings;
			}
			$plugin_data['api_version'] = 'not_set';
			if ( isset( $payu_settings['api_version'] ) ) {
				$plugin_data['api_version'] = $payu_settings['api_version'];
			}

			$data['gateway_payu'] = $plugin_data;

			$plugin_data = array(
				'enabled'                                => 'no',
				'total_number_of_transactions'           => 0,
				'avg_number_of_transactions_per_month'   => 0,
			);

			$sql = "
				SELECT count(p.ID) AS count, min(p.post_date) AS min, max(p.post_date) AS max, TIMESTAMPDIFF(MONTH, min(p.post_date), max(p.post_date) )+1 AS months
				FROM {$wpdb->posts} p, {$wpdb->postmeta} m 
				WHERE p.post_type = 'shop_order'
					AND p.post_status = 'wc-completed'
					AND p.ID = m.post_id
					AND m.meta_key = '_payment_method'
					AND m.meta_value = 'payu_ia'
			";
			$query = $wpdb->get_results( $sql );
			if ( $query ) {
				foreach ( $query as $row ) {
					$plugin_data['total_number_of_transactions'] = $row->count;
					if ( $row->months != 0 ) {
						$plugin_data['avg_number_of_transactions_per_month'] = floatval( $row->count )/floatval( $row->months );
					}
				}
			}

			if ( isset( $payu_settings['payu_ia_enabled'] ) ) {
				$plugin_data['enabled'] = $payu_settings['payu_ia_enabled'];
			}

			$data['gateway_payu_ia'] = $plugin_data;

			$plugin_data = array(
				'total_number_of_transactions'           => 0,
				'avg_number_of_transactions_per_month'   => 0,
			);

			$sql = "
				SELECT count(p.ID) AS count, min(p.post_date) AS min, max(p.post_date) AS max, TIMESTAMPDIFF(MONTH, min(p.post_date), max(p.post_date) )+1 AS months
				FROM {$wpdb->posts} p, {$wpdb->postmeta} m 
				WHERE p.post_type = 'shop_order'
					AND p.post_status = 'wc-completed'
					AND p.ID = m.post_id
					AND m.meta_key = '_payment_method'
					AND m.meta_value = 'payu_recurring'
			";
			$query = $wpdb->get_results( $sql );
			if ( $query ) {
				foreach ( $query as $row ) {
					$plugin_data['total_number_of_transactions'] = $row->count;
					if ( $row->months != 0 ) {
						$plugin_data['avg_number_of_transactions_per_month'] = floatval( $row->count )/floatval( $row->months );
					}
				}
			}

			$data['gateway_payu_recurring'] = $plugin_data;

			return $data;
		}

		public function wpdesk_tracker_notice_screens( $screens ) {
			$current_screen = get_current_screen();
			if ( $current_screen->id == 'woocommerce_page_wc-settings' ) {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'checkout' && isset( $_GET['section'] ) && $_GET['section'] == 'payu' ) {
					$screens[] = $current_screen->id;
				}
			}
			return $screens;
		}

	}

	new WPDesk_PayU_Tracker();

}