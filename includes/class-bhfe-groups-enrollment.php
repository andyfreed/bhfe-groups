<?php
/**
 * Enrollment management for BHFE Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BHFE_Groups_Enrollment {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Enroll user in course via group
	 */
	public function enroll_user( $group_id, $user_id, $course_id, $course_version = 1, $enrolled_by = null ) {
		global $wpdb;
		
		if ( ! $enrolled_by ) {
			$enrolled_by = get_current_user_id();
		}
		
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		// Check if already enrolled
		$existing = $wpdb->get_var( $wpdb->prepare( 
			"SELECT id FROM $table 
			WHERE group_id = %d AND user_id = %d AND course_id = %d AND course_version = %d AND status != 'cancelled'", 
			$group_id, 
			$user_id, 
			$course_id,
			$course_version
		) );
		
		if ( $existing ) {
			return false; // Already enrolled
		}
		
		$result = $wpdb->insert(
			$table,
			array(
				'group_id' => absint( $group_id ),
				'user_id' => absint( $user_id ),
				'course_id' => absint( $course_id ),
				'course_version' => absint( $course_version ),
				'enrolled_by' => absint( $enrolled_by ),
				'status' => 'pending'
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s' )
		);
		
		if ( $result ) {
			$enrollment_id = $wpdb->insert_id;
			
			// If FLMS is available, enroll the user
			if ( function_exists( 'flms_enroll_user' ) ) {
				$this->process_flms_enrollment( $user_id, $course_id, $course_version );
			}
			
			// Update status to active
			$wpdb->update(
				$table,
				array( 'status' => 'active' ),
				array( 'id' => $enrollment_id ),
				array( '%s' ),
				array( '%d' )
			);
			
			return $enrollment_id;
		}
		
		return false;
	}
	
	/**
	 * Unenroll user from course
	 */
	public function unenroll_user( $group_id, $user_id, $course_id, $course_version = 1 ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		$result = $wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array( 
				'group_id' => absint( $group_id ),
				'user_id' => absint( $user_id ),
				'course_id' => absint( $course_id ),
				'course_version' => absint( $course_version )
			),
			array( '%s' ),
			array( '%d', '%d', '%d', '%d' )
		);
		
		// Note: We don't actually remove the FLMS enrollment, just mark it as cancelled in our system
		// This allows for tracking and potential reactivation
		
		return $result !== false;
	}
	
	/**
	 * Get user enrollments for a group
	 */
	public function get_group_enrollments( $group_id, $status = 'active' ) {
		global $wpdb;
		
		$enrollments_table = $wpdb->prefix . 'bhfe_group_enrollments';
		$users_table = $wpdb->users;
		
		$query = "SELECT e.*, u.display_name, u.user_email, p.post_title as course_title
			FROM $enrollments_table e
			INNER JOIN $users_table u ON e.user_id = u.ID
			LEFT JOIN {$wpdb->posts} p ON e.course_id = p.ID
			WHERE e.group_id = %d";
		
		if ( $status ) {
			$query .= $wpdb->prepare( " AND e.status = %s", $status );
		}
		
		$query .= " ORDER BY e.enrolled_at DESC";
		
		return $wpdb->get_results( $wpdb->prepare( $query, $group_id ) );
	}
	
	/**
	 * Get pending enrollments (not yet invoiced)
	 */
	public function get_pending_enrollments( $group_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		return $wpdb->get_results( $wpdb->prepare( 
			"SELECT e.*, u.display_name, u.user_email, p.post_title as course_title,
				pm.meta_value as product_id
			FROM $table e
			INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
			LEFT JOIN {$wpdb->posts} p ON e.course_id = p.ID
			LEFT JOIN {$wpdb->postmeta} pm ON e.course_id = pm.post_id AND pm.meta_key = 'flms_woocommerce_product_id'
			WHERE e.group_id = %d 
			AND e.status = 'active' 
			AND e.order_id IS NULL
			ORDER BY e.enrolled_at ASC",
			$group_id
		) );
	}
	
	/**
	 * Process FLMS enrollment
	 */
	private function process_flms_enrollment( $user_id, $course_id, $course_version ) {
		// Check if FLMS progress class exists
		if ( class_exists( 'FLMS_Course_Progress' ) ) {
			$progress = new FLMS_Course_Progress();
			$progress->enroll_user( $user_id, $course_id, $course_version );
		}
	}
	
	/**
	 * Get course price for enrollment
	 */
	public function get_course_price( $course_id ) {
		$product_id = get_post_meta( $course_id, 'flms_woocommerce_product_id', true );
		
		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				return $product->get_price();
			}
		}
		
		return 0;
	}
}

