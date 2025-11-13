<?php
/**
 * Plugin Name: BHFE Groups
 * Plugin URI: https://beaconhillfinancial.com
 * Description: Allows group admins to enroll/unenroll users in courses and track running totals for invoicing
 * Version: 1.0.0
 * Author: Beacon Hill Financial Educators
 * Author URI: https://beaconhillfinancial.com
 * License: GPL v2 or later
 * Text Domain: bhfe-groups
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'BHFE_GROUPS_VERSION', '1.0.0' );
define( 'BHFE_GROUPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BHFE_GROUPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BHFE_GROUPS_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class
 */
class BHFE_Groups {
	
	/**
	 * Instance of this class
	 */
	private static $instance = null;
	
	/**
	 * Get instance of this class
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}
	
	/**
	 * Initialize plugin
	 */
	private function init() {
		// Load plugin files
		$this->load_dependencies();
		
		// Initialize components
		$this->init_database();
		$this->init_admin();
		$this->init_frontend();
		$this->init_woocommerce();
		
		// Activation/Deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}
	
	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		require_once BHFE_GROUPS_PLUGIN_DIR . 'includes/class-bhfe-groups-database.php';
		require_once BHFE_GROUPS_PLUGIN_DIR . 'includes/class-bhfe-groups-admin.php';
		require_once BHFE_GROUPS_PLUGIN_DIR . 'includes/class-bhfe-groups-frontend.php';
		require_once BHFE_GROUPS_PLUGIN_DIR . 'includes/class-bhfe-groups-woocommerce.php';
		require_once BHFE_GROUPS_PLUGIN_DIR . 'includes/class-bhfe-groups-enrollment.php';
		require_once BHFE_GROUPS_PLUGIN_DIR . 'includes/class-bhfe-groups-invoice.php';
	}
	
	/**
	 * Initialize database
	 */
	private function init_database() {
		BHFE_Groups_Database::get_instance();
	}
	
	/**
	 * Initialize admin
	 */
	private function init_admin() {
		if ( is_admin() ) {
			BHFE_Groups_Admin::get_instance();
		}
	}
	
	/**
	 * Initialize frontend
	 */
	private function init_frontend() {
		BHFE_Groups_Frontend::get_instance();
	}
	
	/**
	 * Initialize WooCommerce integration
	 */
	private function init_woocommerce() {
		if ( class_exists( 'WooCommerce' ) ) {
			BHFE_Groups_WooCommerce::get_instance();
		}
	}
	
	/**
	 * Plugin activation
	 */
	public static function activate() {
		// Create database tables
		BHFE_Groups_Database::create_tables();
		
		// Add rewrite endpoints
		add_rewrite_endpoint( 'groups', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'group-checkout', EP_ROOT | EP_PAGES );
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin
 */
function bhfe_groups_init() {
	return BHFE_Groups::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'bhfe_groups_init' );

