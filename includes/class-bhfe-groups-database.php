<?php
/**
 * Database management for BHFE Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BHFE_Groups_Database {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Create database tables
	 */
	public static function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix = $wpdb->prefix;
		
		// Groups table
		$groups_table = $table_prefix . 'bhfe_groups';
		$groups_sql = "CREATE TABLE IF NOT EXISTS $groups_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			admin_user_id bigint(20) NOT NULL,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY admin_user_id (admin_user_id),
			KEY status (status)
		) $charset_collate;";
		
		// Group members table
		$members_table = $table_prefix . 'bhfe_group_members';
		$members_sql = "CREATE TABLE IF NOT EXISTS $members_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			group_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			added_by bigint(20) NOT NULL,
			added_at datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'active',
			PRIMARY KEY (id),
			UNIQUE KEY unique_member (group_id, user_id),
			KEY group_id (group_id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";
		
		// Group enrollments table
		$enrollments_table = $table_prefix . 'bhfe_group_enrollments';
		$enrollments_sql = "CREATE TABLE IF NOT EXISTS $enrollments_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			group_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			course_id bigint(20) NOT NULL,
			course_version int(11) DEFAULT 1,
			enrolled_by bigint(20) NOT NULL,
			enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'pending',
			order_id bigint(20) DEFAULT NULL,
			course_price decimal(10,2) DEFAULT 0,
			reporting_fee_total decimal(10,2) DEFAULT 0,
			reporting_fee_details longtext NULL,
			PRIMARY KEY (id),
			KEY group_id (group_id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY status (status),
			KEY order_id (order_id)
		) $charset_collate;";
		
		// Group invoices table
		$invoices_table = $table_prefix . 'bhfe_group_invoices';
		$invoices_sql = "CREATE TABLE IF NOT EXISTS $invoices_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			group_id bigint(20) NOT NULL,
			order_id bigint(20) DEFAULT NULL,
			total_amount decimal(10,2) NOT NULL,
			status varchar(20) DEFAULT 'pending',
			invoice_date datetime DEFAULT CURRENT_TIMESTAMP,
			paid_date datetime DEFAULT NULL,
			notes text,
			PRIMARY KEY (id),
			KEY group_id (group_id),
			KEY order_id (order_id),
			KEY status (status)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $groups_sql );
		dbDelta( $members_sql );
		dbDelta( $enrollments_sql );
		dbDelta( $invoices_sql );
		
		// Add capability for administrators only by default
		$admin_role = get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( 'manage_bhfe_group' ) ) {
			$admin_role->add_cap( 'manage_bhfe_group' );
		}
		
		// Remove legacy capability from customer role if it exists
		$customer_role = get_role( 'customer' );
		if ( $customer_role && $customer_role->has_cap( 'manage_bhfe_group' ) ) {
			$customer_role->remove_cap( 'manage_bhfe_group' );
		}
	}
	
	/**
	 * Get group by ID
	 */
	public function get_group( $group_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_groups';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $group_id ) );
	}
	
	/**
	 * Get groups by admin user ID
	 */
	public function get_groups_by_admin( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_groups';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE admin_user_id = %d AND status = 'active' ORDER BY name ASC", $user_id ) );
	}
	
	/**
	 * Get groups for a user (where user is a member)
	 */
	public function get_user_groups( $user_id ) {
		global $wpdb;
		$groups_table = $wpdb->prefix . 'bhfe_groups';
		$members_table = $wpdb->prefix . 'bhfe_group_members';
		
		return $wpdb->get_results( $wpdb->prepare( "
			SELECT g.* 
			FROM $groups_table g
			INNER JOIN $members_table m ON g.id = m.group_id
			WHERE m.user_id = %d 
			AND m.status = 'active' 
			AND g.status = 'active'
			ORDER BY g.name ASC
		", $user_id ) );
	}
	
	/**
	 * Create a new group
	 */
	public function create_group( $name, $admin_user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_groups';
		
		$result = $wpdb->insert(
			$table,
			array(
				'name' => sanitize_text_field( $name ),
				'admin_user_id' => absint( $admin_user_id ),
				'status' => 'active'
			),
			array( '%s', '%d', '%s' )
		);
		
		if ( $result ) {
			return $wpdb->insert_id;
		}
		
		return false;
	}
	
	/**
	 * Get group members
	 */
	public function get_group_members( $group_id, $status = 'active' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_members';
		
		$query = "SELECT m.*, u.display_name, u.user_email 
			FROM $table m
			INNER JOIN {$wpdb->users} u ON m.user_id = u.ID
			WHERE m.group_id = %d";
		
		if ( $status ) {
			$query .= $wpdb->prepare( " AND m.status = %s", $status );
		}
		
		$query .= " ORDER BY u.display_name ASC";
		
		return $wpdb->get_results( $wpdb->prepare( $query, $group_id ) );
	}
	
	/**
	 * Add member to group
	 */
	public function add_member( $group_id, $user_id, $added_by ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_members';
		
		// Check if user is already a member
		$existing = $wpdb->get_var( $wpdb->prepare( 
			"SELECT id FROM $table WHERE group_id = %d AND user_id = %d", 
			$group_id, 
			$user_id 
		) );
		
		if ( $existing ) {
			// Reactivate if inactive
			$wpdb->update(
				$table,
				array( 'status' => 'active', 'added_by' => $added_by ),
				array( 'id' => $existing ),
				array( '%s', '%d' ),
				array( '%d' )
			);
			return $existing;
		}
		
		$result = $wpdb->insert(
			$table,
			array(
				'group_id' => absint( $group_id ),
				'user_id' => absint( $user_id ),
				'added_by' => absint( $added_by ),
				'status' => 'active'
			),
			array( '%d', '%d', '%d', '%s' )
		);
		
		if ( $result ) {
			return $wpdb->insert_id;
		}
		
		return false;
	}
	
	/**
	 * Remove member from group
	 */
	public function remove_member( $group_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_members';
		
		return $wpdb->update(
			$table,
			array( 'status' => 'inactive' ),
			array( 'group_id' => absint( $group_id ), 'user_id' => absint( $user_id ) ),
			array( '%s' ),
			array( '%d', '%d' )
		);
	}
	
	/**
	 * Check if user is member of group
	 */
	public function is_group_member( $user_id, $group_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_members';
		
		$result = $wpdb->get_var( $wpdb->prepare( 
			"SELECT COUNT(*) FROM $table 
			WHERE group_id = %d AND user_id = %d AND status = 'active'", 
			$group_id, 
			$user_id 
		) );
		
		return $result > 0;
	}
	
	/**
	 * Check if user is group admin
	 */
	public function is_group_admin( $user_id, $group_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_groups';
		
		$result = $wpdb->get_var( $wpdb->prepare( 
			"SELECT COUNT(*) FROM $table 
			WHERE id = %d AND admin_user_id = %d AND status = 'active'", 
			$group_id, 
			$user_id 
		) );
		
		return $result > 0;
	}
}

