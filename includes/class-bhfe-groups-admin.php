<?php
/**
 * Admin interface for BHFE Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BHFE_Groups_Admin {
	
	private static $instance = null;
	
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
		$this->init_hooks();
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Ensure capabilities are set (in case plugin was activated before this update)
		add_action( 'admin_init', array( $this, 'ensure_capabilities' ) );
		
		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Enqueue admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// Handle AJAX requests
		add_action( 'wp_ajax_bhfe_groups_add_member', array( $this, 'ajax_add_member' ) );
		add_action( 'wp_ajax_bhfe_groups_remove_member', array( $this, 'ajax_remove_member' ) );
		add_action( 'wp_ajax_bhfe_groups_enroll_user', array( $this, 'ajax_enroll_user' ) );
		add_action( 'wp_ajax_bhfe_groups_unenroll_user', array( $this, 'ajax_unenroll_user' ) );
		add_action( 'wp_ajax_bhfe_groups_create_group', array( $this, 'ajax_create_group' ) );
		add_action( 'wp_ajax_bhfe_groups_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'wp_ajax_bhfe_groups_search_courses', array( $this, 'ajax_search_courses' ) );
	}
	
	/**
	 * Ensure capabilities are assigned to roles
	 */
	public function ensure_capabilities() {
		// Add capability for group admins
		$customer_role = get_role( 'customer' );
		if ( $customer_role && ! $customer_role->has_cap( 'manage_bhfe_group' ) ) {
			$customer_role->add_cap( 'manage_bhfe_group' );
		}
		
		// Also add to administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( 'manage_bhfe_group' ) ) {
			$admin_role->add_cap( 'manage_bhfe_group' );
		}
	}
	
	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin page
		if ( 'toplevel_page_bhfe-groups' !== $hook ) {
			return;
		}
		
		// Enqueue Select2 from theme if available
		if ( ! wp_script_is( 'select2', 'enqueued' ) ) {
			$theme_url = get_template_directory_uri();
			wp_enqueue_style( 'select2', $theme_url . '/assets/select2/select2.min.css', false, '4.1.0' );
			wp_enqueue_script( 'select2', $theme_url . '/assets/select2/select2.min.js', array( 'jquery' ), '4.1.0', true );
		}
		
		// Localize script for AJAX
		wp_localize_script( 'jquery', 'bhfeGroups', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bhfe-groups-nonce' )
		) );
	}
	
	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'BHFE Groups', 'bhfe-groups' ),
			__( 'Groups', 'bhfe-groups' ),
			'manage_bhfe_group',
			'bhfe-groups',
			array( $this, 'render_groups_page' ),
			'dashicons-groups',
			30
		);
		
		add_submenu_page(
			'bhfe-groups',
			__( 'All Groups', 'bhfe-groups' ),
			__( 'All Groups', 'bhfe-groups' ),
			'manage_bhfe_group',
			'bhfe-groups',
			array( $this, 'render_groups_page' )
		);
	}
	
	/**
	 * Render groups page
	 */
	public function render_groups_page() {
		$user_id = get_current_user_id();
		$db = BHFE_Groups_Database::get_instance();
		
		// Get groups for current user (as admin)
		$groups = $db->get_groups_by_admin( $user_id );
		
		// Handle group selection
		$selected_group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
		
		if ( $selected_group_id && ! $db->is_group_admin( $user_id, $selected_group_id ) ) {
			wp_die( __( 'You do not have permission to access this group.', 'bhfe-groups' ) );
		}
		
		include BHFE_GROUPS_PLUGIN_DIR . 'templates/admin/groups-page.php';
	}
	
	/**
	 * AJAX: Add member to group
	 */
	public function ajax_add_member() {
		check_ajax_referer( 'bhfe-groups-nonce', 'nonce' );
		
		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$current_user_id = get_current_user_id();
		
		if ( ! $group_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bhfe-groups' ) ) );
		}
		
		$db = BHFE_Groups_Database::get_instance();
		
		if ( ! $db->is_group_admin( $current_user_id, $group_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bhfe-groups' ) ) );
		}
		
		$result = $db->add_member( $group_id, $user_id, $current_user_id );
		
		if ( $result ) {
			$user = get_user_by( 'id', $user_id );
			wp_send_json_success( array( 
				'message' => sprintf( __( 'User %s added to group.', 'bhfe-groups' ), $user->display_name )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add member.', 'bhfe-groups' ) ) );
		}
	}
	
	/**
	 * AJAX: Remove member from group
	 */
	public function ajax_remove_member() {
		check_ajax_referer( 'bhfe-groups-nonce', 'nonce' );
		
		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$current_user_id = get_current_user_id();
		
		if ( ! $group_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bhfe-groups' ) ) );
		}
		
		$db = BHFE_Groups_Database::get_instance();
		
		if ( ! $db->is_group_admin( $current_user_id, $group_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bhfe-groups' ) ) );
		}
		
		$result = $db->remove_member( $group_id, $user_id );
		
		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => __( 'Member removed from group.', 'bhfe-groups' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to remove member.', 'bhfe-groups' ) ) );
		}
	}
	
	/**
	 * AJAX: Enroll user in course
	 */
	public function ajax_enroll_user() {
		check_ajax_referer( 'bhfe-groups-nonce', 'nonce' );
		
		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$course_version = isset( $_POST['course_version'] ) ? absint( $_POST['course_version'] ) : 1;
		$current_user_id = get_current_user_id();
		
		if ( ! $group_id || ! $user_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bhfe-groups' ) ) );
		}
		
		$db = BHFE_Groups_Database::get_instance();
		
		if ( ! $db->is_group_admin( $current_user_id, $group_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bhfe-groups' ) ) );
		}
		
		// Verify user is a member of the group
		if ( ! $db->is_group_member( $user_id, $group_id ) ) {
			wp_send_json_error( array( 'message' => __( 'User is not a member of this group.', 'bhfe-groups' ) ) );
		}
		
		$enrollment = BHFE_Groups_Enrollment::get_instance();
		$result = $enrollment->enroll_user( $group_id, $user_id, $course_id, $course_version, $current_user_id );
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'User enrolled in course.', 'bhfe-groups' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to enroll user or user already enrolled.', 'bhfe-groups' ) ) );
		}
	}
	
	/**
	 * AJAX: Unenroll user from course
	 */
	public function ajax_unenroll_user() {
		check_ajax_referer( 'bhfe-groups-nonce', 'nonce' );
		
		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$course_version = isset( $_POST['course_version'] ) ? absint( $_POST['course_version'] ) : 1;
		$current_user_id = get_current_user_id();
		
		if ( ! $group_id || ! $user_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bhfe-groups' ) ) );
		}
		
		$db = BHFE_Groups_Database::get_instance();
		
		if ( ! $db->is_group_admin( $current_user_id, $group_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bhfe-groups' ) ) );
		}
		
		$enrollment = BHFE_Groups_Enrollment::get_instance();
		$result = $enrollment->unenroll_user( $group_id, $user_id, $course_id, $course_version );
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'User unenrolled from course.', 'bhfe-groups' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to unenroll user.', 'bhfe-groups' ) ) );
		}
	}
	
	/**
	 * AJAX: Create new group
	 */
	public function ajax_create_group() {
		// Verify nonce
		if ( ! check_ajax_referer( 'bhfe-groups-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'bhfe-groups' ) ) );
		}
		
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$current_user_id = get_current_user_id();
		
		if ( ! $current_user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to create a group.', 'bhfe-groups' ) ) );
		}
		
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Group name is required.', 'bhfe-groups' ) ) );
		}
		
		// Ensure database tables exist
		BHFE_Groups_Database::create_tables();
		
		$db = BHFE_Groups_Database::get_instance();
		$group_id = $db->create_group( $name, $current_user_id );
		
		if ( $group_id ) {
			wp_send_json_success( array( 
				'group_id' => $group_id,
				'message' => __( 'Group created successfully.', 'bhfe-groups' )
			) );
		} else {
			global $wpdb;
			$error_message = __( 'Failed to create group.', 'bhfe-groups' );
			if ( $wpdb->last_error ) {
				$error_message .= ' ' . __( 'Database error:', 'bhfe-groups' ) . ' ' . $wpdb->last_error;
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}
	}
	
	/**
	 * AJAX: Search users
	 */
	public function ajax_search_users() {
		check_ajax_referer( 'bhfe-groups-nonce', 'nonce' );
		
		$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		
		if ( strlen( $search ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter at least 2 characters.', 'bhfe-groups' ) ) );
		}
		
		$users = get_users( array(
			'search' => '*' . esc_attr( $search ) . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
			'number' => 20
		) );
		
		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id' => $user->ID,
				'text' => $user->display_name . ' (' . $user->user_email . ')'
			);
		}
		
		wp_send_json_success( $results );
	}
	
	/**
	 * AJAX: Search courses
	 */
	public function ajax_search_courses() {
		check_ajax_referer( 'bhfe-groups-nonce', 'nonce' );
		
		$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		
		if ( strlen( $search ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter at least 2 characters.', 'bhfe-groups' ) ) );
		}
		
		$args = array(
			'post_type' => 'flms-courses',
			'posts_per_page' => 20,
			's' => $search,
			'post_status' => 'publish'
		);
		
		$query = new WP_Query( $args );
		
		$results = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$results[] = array(
					'id' => get_the_ID(),
					'text' => get_the_title()
				);
			}
		}
		wp_reset_postdata();
		
		wp_send_json_success( $results );
	}
}

