<?php
/**
 * Frontend functionality for BHFE Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BHFE_Groups_Frontend {
	
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
		// Show group info in My Account
		add_action( 'woocommerce_account_dashboard', array( $this, 'show_group_info' ), 5 );
		
		// Add group info to course pages
		add_action( 'flms_before_course_content', array( $this, 'show_group_notice' ), 10 );
	}
	
	/**
	 * Show group information in My Account
	 */
	public function show_group_info() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return;
		}
		
		echo '<div class="bhfe-group-info">';
		echo '<h3>' . esc_html__( 'Group Membership', 'bhfe-groups' ) . '</h3>';
		echo '<p>' . esc_html__( 'You are a member of the following group(s):', 'bhfe-groups' ) . '</p>';
		echo '<ul>';
		foreach ( $user_groups as $group ) {
			echo '<li><strong>' . esc_html( $group->name ) . '</strong></li>';
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'Courses you add to your cart will be added to your group account and invoiced to the group administrator.', 'bhfe-groups' ) . '</p>';
		echo '</div>';
	}
	
	/**
	 * Show group notice on course pages
	 */
	public function show_group_notice() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( ! empty( $user_groups ) ) {
			$group = $user_groups[0];
			echo '<div class="woocommerce-info bhfe-group-course-notice">';
			echo '<strong>' . esc_html__( 'Group Member:', 'bhfe-groups' ) . '</strong> ';
			echo esc_html__( 'You are a member of', 'bhfe-groups' ) . ' "' . esc_html( $group->name ) . '". ';
			echo esc_html__( 'This course will be added to your group account.', 'bhfe-groups' );
			echo '</div>';
		}
	}
}

