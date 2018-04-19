<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once 'functions.php';

if ( ! class_exists( 'WPDesk_Plugin_1_5' ) ) {

	require_once 'class-settings.php';

	/**
	 * Base plugin class for WP Desk plugins
	 *
	 * @author Grzegorz
	 *
	 */
    class WPDesk_Plugin_1_5 {

    	const VERSION = '1.4';

    	private $_plugin_is_active = true;

    	protected $_plugin_data = false;

    	protected $_default_view_args; // default args given to template

    	public $_plugin_namespace = 'wpdesk_plugin';
    	public $_plugin_text_domain = 'wpdesk-plugin';
    	public $_plugin_has_settings = true;

    	public $_plugin_path;
	    public $_template_path;
	    public $_plugin_file_path;
	    public $_plugin_url;

	    public $_settings_url = false;
	    public $_docs_url = false;

	    public $_default_settings_tab = 'general';

	    public $settings = null;
	    public $options = null;

	    static $_instance = null;

	    public static function get_instance( $plugin_data ) {
		    if ( self::$_instance == null ) {
			    self::$_instance = new self( $plugin_data );
		    }
		    return self::$_instance;
	    }

	    protected function __construct( $plugin_data = false ) {
	    	$this->init_base_variables();
	    	if ( is_array( $plugin_data ) && count( $plugin_data ) ) {
	    		if ( ! class_exists( 'WPDesk_Helper_Plugin' ) ) {
	    			require_once( 'class-helper.php' );
	    			add_filter( 'plugins_api', array( $this, 'wpdesk_helper_install' ), 10, 3 );
	    			add_action( 'admin_notices', array( $this, 'wpdesk_helper_notice' ) );
	    		}
	    		$helper = new WPDesk_Helper_Plugin( $plugin_data );
	    		if ( !$helper->is_active() ) {
	    			$this->_plugin_is_active = false;
	    		}
	    	}
	    	if ( $this->_plugin_is_active ) {
	    		if ( $this->_plugin_has_settings ) {
	    			$this->settings = new WPDesk_Settings_1_5( $this, $this->get_namespace(), $this->_default_settings_tab );
	    			$this->options = $this->settings->get_settings();
	    		}
	    	}
	    	$this->hooks();
    	}

	    public function plugin_is_active() {
	    	return $this->_plugin_is_active;
	    }

	    public function get_settings() {
	    	return $this->settings;
	    }

	    public function get_option( $key, $default ) {
	    	return $this->settings->get_option( $key, $default );
	    }

    	/**
    	 *
    	 * @return WPDesk_Plugin
    	 */
    	public function get_plugin() {
    		return $this;
    	}

    	public function get_text_domain() {
    		return $this->_plugin_text_domain;
    	}

    	public function load_plugin_text_domain() {
    	    $wpdesk_translation = load_plugin_textdomain( 'wpdesk-plugin', FALSE, $this->get_namespace() . '/classes/wpdesk/lang/' );
    	    $plugin_translation = load_plugin_textdomain( $this->get_text_domain(), FALSE, $this->get_namespace() . '/lang/' );
    	}

    	/**
    	 *
    	 */
    	public function init_base_variables() {
    		$reflection = new ReflectionClass( $this );

    		// Set Plugin Path
    		$this->_plugin_path = dirname( $reflection->getFileName() );

    		// Set Plugin URL
    		$this->_plugin_url = plugin_dir_url( $reflection->getFileName() );

    		$this->_plugin_file_path = $reflection->getFileName();

    		$this->_template_path = $this->get_namespace();

    		$this->_default_view_args = array(
    			'plugin_url' => $this->get_plugin_url()
    		);

    	}

        	/**
    	 *
    	 */
    	public function hooks() {

    		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

    		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

    		add_action( 'plugins_loaded', array( $this, 'load_plugin_text_domain') );

    		add_filter( 'plugin_action_links_' . plugin_basename( $this->get_plugin_file_path() ), array( $this, 'links_filter' ) );

    	}

        /**
         *
         * @return string
         */
        public function get_plugin_url() {
        	return esc_url( trailingslashit( $this->_plugin_url ) );
        }

        public function get_plugin_assets_url() {
            return esc_url( trailingslashit( $this->get_plugin_url() . 'assets' ) );
        }

        /**
         * @return string
         */
        public function get_template_path() {
        	return trailingslashit( $this->_template_path );
        }

        public function get_plugin_file_path() {
        	 return $this->_plugin_file_path;
        }

        public function get_namespace() {
        	return $this->_plugin_namespace;
        }


        /**
		 * Renders end returns selected template
		 *
		 * @param string $name name of the template
		 * @param string $path additional inner path to the template
		 * @param array $args args accesible from template
		 * @return string
		 */
		public function load_template( $name, $path = '', $args = array() ) {
			$path = trim( $path, '/' );

			$template_name = implode( '/', array( get_template_directory(), $this->get_template_path(), $path, $name . '.php' ) );

			if ( !file_exists( $template_name ) ) {
				$template_name = implode( '/', array( untrailingslashit( $this->_plugin_path ), 'templates', $path, $name . '.php' ) );
			}
			extract( $args );
			ob_start();
			include( $template_name );
			return ob_get_clean();
		}

		public function admin_enqueue_scripts( $hooq ) {
		}

    	public function wp_enqueue_scripts() {
		}

		/**
         * action_links function.
         *
         * @access public
         * @param mixed $links
         * @return void
         */
        public function links_filter( $links ) {

            $support_link = get_locale() === 'pl_PL' ? 'https://www.wpdesk.net/support/' : 'https://www.wpdesk.net/support';

        	$plugin_links = array(
        			'<a href="' . $support_link . '">' . __( 'Support', 'wpdesk-plugin' ) . '</a>',
        	);
        	$links = array_merge( $plugin_links, $links );

        	if ( $this->_docs_url ) {
        		$plugin_links = array(
        				'<a href="' . $this->_docs_url . '">' . __( 'Docs', 'wpdesk-plugin' ) . '</a>',
        		);
        		$links = array_merge( $plugin_links, $links );
        	}

        	if ( $this->_settings_url ) {
        		$plugin_links = array(
        				'<a href="' . $this->_settings_url . '">' . __( 'Settings', 'wpdesk-plugin' ) . '</a>',
        		);
        		$links = array_merge( $plugin_links, $links );
        	}

        	return $links;
        }

        /**
         * Helper functions
         */

		/**
		 * Load installer for the WP Desk Helper.
		 * @return $api Object
		 */
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

        /**
         * Display a notice if the "WP Desk Helper" plugin hasn't been installed.
         * @return void
         */
        function wpdesk_helper_notice() {

        	$active_plugins = apply_filters( 'active_plugins', get_option('active_plugins' ) );
        	if ( in_array( 'wpdesk-helper/wpdesk-helper.php', $active_plugins ) ) return;

        	$slug = 'wpdesk-helper';
        	$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), 'install-plugin_' . $slug );
        	$activate_url = 'plugins.php?action=activate&plugin=' . urlencode( 'wpdesk-helper/wpdesk-helper.php' ) . '&plugin_status=all&paged=1&s&_wpnonce=' . urlencode( wp_create_nonce( 'activate-plugin_wpdesk-helper/wpdesk-helper.php' ) );

        	$message = sprintf( wp_kses( __( '<a href="%s">Install the WP Desk Helper plugin</a> to activate and get updates for your WP Desk plugins.', 'wpdesk-plugin' ), array(  'a' => array( 'href' => array() ) ) ), esc_url( $install_url ) );
        	$is_downloaded = false;
        	$plugins = array_keys( get_plugins() );
        	foreach ( $plugins as $plugin ) {
        		if ( strpos( $plugin, 'wpdesk-helper.php' ) !== false ) {
        			$is_downloaded = true;
        			$message = sprintf( wp_kses( __( '<a href="%s">Activate the WP Desk Helper plugin</a> to activate and get updates for your WP Desk plugins.', 'wpdesk-plugin' ), array(  'a' => array( 'href' => array() ) ) ), esc_url( admin_url( $activate_url ) ) );
        		}
        	}
        	echo '<div class="error fade"><p>' . $message . '</p></div>' . "\n";
        }

    }

}
