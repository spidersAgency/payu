<?php
/*
	Plugin Name: WooCommerce PayU
	Plugin URI: https://www.wpdesk.pl/sklep/payu-woocommerce/
	Description: Wtyczka do WooCommerce. Bramka płatności dla systemu PayU.
	Version: 4.6.5
	Author: WP Desk
	Text Domain: woocommerce_payu
	Domain Path: /languages/
	Author URI: https://www.wpdesk.pl/
	Requires at least: 4.5
	Tested up to: 4.9.4
	WC requires at least: 2.6.14
    WC tested up to: 3.3.4
*/

$payu_plugin_data = [
	'plugin'     => plugin_basename( __FILE__ ),
	'product_id' => 'WooCommerce PayU',
	'version'    => '4.6.5',
	'config_uri' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payu' )
];

require_once( plugin_basename( 'inc/wpdesk-woo27-functions.php' ) );

require_once( 'classes/tracker.php' );


require_once( plugin_basename( 'classes/wpdesk/class-plugin.php' ) );

class WPDesk_WooCommerce_PayU_Plugin extends WPDesk_Plugin_1_5 {

	private $script_version = '11';

	public static $_instance = null;

	public static function get_instance( $plugin_data ) {
		if ( self::$_instance == null ) {
			self::$_instance = new self( $plugin_data );
		}

		return self::$_instance;
	}

	protected function __construct( $plugin_data ) {
		$this->_plugin_namespace   = 'woocommerce_payu';
		$this->_plugin_text_domain = 'woocommerce_payu';
		$this->_settings_url       = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payu' );

		$this->_plugin_has_settings = false;

		parent::__construct( $plugin_data );
		if ( $this->plugin_is_active() ) {
			$this->init();
		}
	}

	public function init() {
		require_once( plugin_basename( 'classes/class-payu-rest-api.php' ) );
		require_once( plugin_basename( 'classes/class-payu-payment-gateway.php' ) );
		require_once( plugin_basename( 'classes/class-payu-ia-payment-gateway.php' ) );
		require_once( plugin_basename( 'classes/class-payu-recurring-payment-gateway.php' ) );
	}

	public function hooks() {
		if ( $this->plugin_is_active() ) {
			parent::hooks();
			add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
			add_action( 'admin_notices', [ $this, 'admin_notices' ] );
			add_filter( 'woocommerce_payment_gateways', [ $this, 'woocommerce_payu_add' ] );
		}
	}

	public function woocommerce_payu_add( $methods ) {
		$payu_method = new WC_Gateway_Payu();
		if ( is_checkout()
		     && ( empty( $payu_method->settings['api_version'] ) || isset( $payu_method->settings['api_version'] ) && $payu_method->settings['api_version'] == 'classic_api' )
		     && get_woocommerce_currency() != 'PLN' ) {
			/* disable on Classic API and currency != PLN */
			return $methods;
		}
		$methods[] = $payu_method;
		$add       = true;
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && $screen->base == 'woocommerce_page_wc-settings' ) {
				$add = false;
			}
		}
		if ( $add && $payu_method->get_option( 'payu_ia_enabled', 'no' ) == 'yes' ) {
			$payu_ia_method = new WC_Gateway_Payu_IA();
			$payu_ia_method->init_payu( $payu_method );
			$methods[] = $payu_ia_method;
		}
		if ( $add && $payu_method->get_option( 'payu_subscriptions_enabled', 'no' ) == 'yes' ) {
			$payu_recurring_method = new WC_Gateway_Payu_Recurring();
			$methods[]             = $payu_recurring_method;
		}

		return $methods;
	}


	public function plugins_loaded() {
	}

	public function wp_enqueue_scripts() {
		parent::wp_enqueue_scripts();
	}

	public function admin_enqueue_scripts( $hooq ) {
		$current_screen = get_current_screen();
		$suffix         = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		if ( in_array( $current_screen->id, [ 'woocommerce_page_wc-settings' ] )
		     && isset( $_GET['tab'] ) && $_GET['tab'] == 'checkout'
		     && isset( $_GET['section'] ) && $_GET['section'] == 'payu'
		) {
			wp_register_style( 'payu_admin_css', $this->get_plugin_assets_url() . 'css/admin' . $suffix . '.css', [],
				$this->script_version );
			wp_enqueue_style( 'payu_admin_css' );

			wp_enqueue_script( 'payu_admin_js', $this->get_plugin_assets_url() . 'js/admin' . $suffix . '.js',
				[ 'jquery' ], $this->script_version, true );

			$protocol = is_ssl() ? 'https://' : 'http://';

			wp_localize_script( 'payu_admin_js', 'payu_admin_object', [
				'site_url' => str_replace( $protocol, '', site_url() ),
				'protocol' => $protocol,
			] );


		}
	}

	public function admin_notices() {
		if ( is_plugin_active( 'wpdesk-helper/wpdesk-helper.php' ) ) {
			$plugin = get_plugin_data( WP_PLUGIN_DIR . '/wpdesk-helper/wpdesk-helper.php' );

			$version_compare = version_compare( $plugin['Version'], '1.3' );
			if ( $version_compare < 0 ) {
				$class = 'notice notice-error';
				//$message = __( 'WooCommerce PayU requires at least version 1.3 of WP Desk Helper plugin.', 'woocommerce_payu' );
				$message = __( 'Wtyczka WooCommerce PayU wymaga wtyczki WP Desk Helper w wersji nie niższej niż 1.3.',
					'woocommerce_payu' );
				printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
			}
		}
	}

	/**
	 * action_links function.
	 *
	 * @access public
	 *
	 * @param mixed $links
	 *
	 * @return array
	 */
	public function links_filter( $links ) {
		$plugin_links = [];
		if ( $this->plugin_is_active() ) {
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.6', '<' ) ) {
				$plugin_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payu' ) . '">' . __( 'Ustawienia',
						'woocommerce_payu' ) . '</a>';
			} else {
				$plugin_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payu' ) . '">' . __( 'Ustawienia',
						'woocommerce_payu' ) . '</a>';
			}
		}
		$plugin_links[] = '<a href="https://www.wpdesk.pl/docs/payu-woocommerce-docs/">' . __( 'Docs',
				'woocommerce_payu' ) . '</a>';
		$plugin_links[] = '<a href="https://www.wpdesk.pl/support/">' . __( 'Wsparcie', 'woocommerce_payu' ) . '</a>';

		return array_merge( $plugin_links, $links );
	}

}

function wpdesk_woocommerce_payu() {
	global $payu_plugin_data;

	return WPDesk_WooCommerce_PayU_Plugin::get_instance( $payu_plugin_data );
}

$woocommerce_payu_plugin = null;
function woocommerce_payu_init() {
	global $woocommerce_payu_plugin;
	$woocommerce_payu_plugin     = wpdesk_woocommerce_payu();
	$GLOBALS['woocommerce_payu'] = $woocommerce_payu_plugin;
}

add_action( 'plugins_loaded', 'woocommerce_payu_init', 1 );


if ( ! function_exists( 'wpdesk_is_plugin_active' ) ) {
	function wpdesk_is_plugin_active( $plugin_file ) {
		$active_plugins = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
		}

		return in_array( $plugin_file, $active_plugins ) || array_key_exists( $plugin_file, $active_plugins );
	}
}

function wpdesk_payu_admin_notices() {
	if ( is_plugin_active( 'wpdesk-helper/wpdesk-helper.php' ) ) {
		$plugin = get_plugin_data( WP_PLUGIN_DIR . '/wpdesk-helper/wpdesk-helper.php' );

		$version_compare = version_compare( $plugin['Version'], '1.3' );
		if ( $version_compare < 0 ) {
			$class = 'notice notice-error';
			//$message = __( 'WooCommerce PayU requires at least version 1.3 of WP Desk Helper plugin.', 'woocommerce_payu' );
			$message = __( 'Wtyczka WooCommerce PayU wymaga wtyczki WP Desk Helper w wersji nie niższej niż 1.3.',
				'woocommerce_payu' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}
	}
}
//add_action( 'admin_notices', 'wpdesk_payu_admin_notices' );

