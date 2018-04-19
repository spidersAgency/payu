<?php

/**
 * Load installer for the WP Desk Helper.
 * @return $api Object
 */
if ( ! class_exists( 'WPDesk_Helper_Plugin' ) && ! function_exists( 'wpdesk_helper_install' ) ) {
	function wpdesk_helper_install( $api, $action, $args ) {
		$download_url = 'http://www.wpdesk.pl/wp-content/uploads/wpdesk-helper.zip';

		if ( 'plugin_information' != $action ||
			false !== $api ||
			! isset( $args->slug ) ||
			'wpdesk-helper' != $args->slug
		) return $api;

		$api = new stdClass();
		$api->name = 'WP Desk Helper';
		$api->version = '1.0';
		$api->download_link = esc_url( $download_url );
		return $api;
	}

	add_filter( 'plugins_api', 'wpdesk_helper_install', 10, 3 );
}

/**
 * WP Desk Helper Installation Prompts
 */
if ( ! class_exists( 'WPDesk_Helper_Plugin' ) && ! function_exists( 'wpdesk_helper_notice' ) ) {

	/**
	 * Display a notice if the "WP Desk Helper" plugin hasn't been installed.
	 * @return void
	 */
	function wpdesk_helper_notice() {
		global $wpdesk_helper_text_domain;

		$active_plugins = apply_filters( 'active_plugins', get_option('active_plugins' ) );
		if ( in_array( 'wpdesk-helper/wpdesk-helper.php', $active_plugins ) ) return;

		$slug = 'wpdesk-helper';
		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), 'install-plugin_' . $slug );
		$activate_url = 'plugins.php?action=activate&plugin=' . urlencode( 'wpdesk-helper/wpdesk-helper.php' ) . '&plugin_status=all&paged=1&s&_wpnonce=' . urlencode( wp_create_nonce( 'activate-plugin_wpdesk-helper/wpdesk-helper.php' ) );

		$message = sprintf( wp_kses( __( '<a href="%s">Zainstaluj wtyczkę WP Desk Helper</a>, aby aktywować i otrzymywać aktualizacje wtyczek WP Desk.', $wpdesk_helper_text_domain ), array(  'a' => array( 'href' => array() ) ) ), esc_url( $install_url ) );
		$is_downloaded = false;
		$plugins = array_keys( get_plugins() );
		foreach ( $plugins as $plugin ) {
			if ( strpos( $plugin, 'wpdesk-helper.php' ) !== false ) {
				$is_downloaded = true;
				$message = sprintf( wp_kses( __( '<a href="%s">Włącz wtyczkę WP Desk Helper</a>, aby aktywować i otrzymywać aktualizacje wtyczek WP Desk.', $wpdesk_helper_text_domain ), array(  'a' => array( 'href' => array() ) ) ), esc_url( admin_url( $activate_url ) ) );
			}
		}
		echo '<div class="updated fade"><p>' . $message . '</p></div>' . "\n";
	}

	add_action( 'admin_notices', 'wpdesk_helper_notice' );
}

if ( ! class_exists( 'WPDesk_Helper_Plugin' ) ) {
	class WPDesk_Helper_Plugin {


		protected $plugin_data;

		protected $text_domain;

		protected $ame_activated_key;

		protected $ame_activation_tab_key;

		function __construct( $plugin_data ) {
			global $wpdesk_helper_plugins;
			global $wpdesk_helper_text_domain;

			$this->plugin_data = $plugin_data;
   			if ( ! isset( $wpdesk_helper_plugins ) ) $wpdesk_helper_plugins = array();
   			$plugin_data['helper_plugin'] = $this;
			$wpdesk_helper_plugins[] = $plugin_data;

			$this->text_domain = $wpdesk_helper_text_domain;
			$this->ame_activated_key = 'api_' . dirname($plugin_data['plugin']) . '_activated';
			$this->ame_activation_tab_key = 'api_' . dirname($plugin_data['plugin']) . '_dashboard';

		}

		public function inactive_notice() { ?>
			<?php if ( ! current_user_can( 'manage_options' ) ) return; ?>
			<?php if ( 1==1 && isset( $_GET['page'] ) && $this->ame_activation_tab_key == $_GET['page'] ) return; ?>
			<div class="update-nag">
				<?php printf( __( 'Klucz licencyjny wtyczki %s%s%s nie został aktywowany, więc wtyczka jest nieaktywna! %sKliknij tutaj%s, aby aktywować klucz licencyjny wtyczki.', $this->text_domain ), '<strong>', $this->plugin_data['product_id'], '</strong>', '<a href="' . esc_url( admin_url( 'admin.php?page='.$this->ame_activation_tab_key ) ) . '">', '</a>' ); ?>
			</div>
			<?php
		}


		function is_active( $add_notice = false ) {
			if ( get_option( $this->ame_activated_key, '0' ) != 'Activated' ) {
			    if ( $add_notice ) {
			  	    add_action( 'admin_notices', array( $this, 'inactive_notice' ) );
			  	}
			  	return false;
			}
			else {
				return true;
			}
		}

	}
}
