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
		$course_price = $this->get_course_price( $course_id );
		
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
				'status' => 'pending',
				'course_price' => $course_price,
				'reporting_fee_total' => 0,
				'reporting_fee_details' => maybe_serialize( array() ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%f', '%f', '%s' )
		);
		
		if ( $result ) {
			$enrollment_id = $wpdb->insert_id;
			
			// If FLMS is available, enroll the user
			$this->process_flms_enrollment( $user_id, $course_id, $course_version );
			
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
	public function unenroll_user( $group_id, $user_id, $course_id, $course_version = 1, $enrollment_id = 0 ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		$target_enrollment = null;
		
		if ( $enrollment_id ) {
			$target_enrollment = $this->get_enrollment( $enrollment_id );
			if ( ! $target_enrollment || intval( $target_enrollment->group_id ) !== intval( $group_id ) ) {
				return false;
			}
		} else {
			$target_enrollment = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $table 
				WHERE group_id = %d AND user_id = %d AND course_id = %d 
				AND status = 'active'
				ORDER BY enrolled_at DESC 
				LIMIT 1",
				absint( $group_id ),
				absint( $user_id ),
				absint( $course_id )
			) );
		}
		
		if ( ! $target_enrollment ) {
			return false;
		}
		
		$result = $wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array( 'id' => absint( $target_enrollment->id ) ),
			array( '%s' ),
			array( '%d' )
		);
		
		if ( false === $result ) {
			return false;
		}
		
		$this->process_flms_unenrollment(
			$target_enrollment->user_id,
			$target_enrollment->course_id,
			$target_enrollment->course_version
		);
		
		return true;
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
		// Try function first (if available)
		if ( function_exists( 'flms_enroll_user' ) ) {
			flms_enroll_user( $user_id, $course_id, $course_version );
			return;
		}
		
		// Fallback to class method
		if ( class_exists( 'FLMS_Course_Progress' ) ) {
			$progress = new FLMS_Course_Progress();
			if ( method_exists( $progress, 'enroll_user' ) ) {
				$progress->enroll_user( $user_id, $course_id, $course_version );
			}
		}
	}
	
	/**
	 * Process FLMS unenrollment when removing a user from a course
	 */
	private function process_flms_unenrollment( $user_id, $course_id, $course_version ) {
		if ( function_exists( 'flms_unenroll_user' ) ) {
			flms_unenroll_user( $user_id, $course_id, $course_version );
			return;
		}
		
		if ( class_exists( 'FLMS_Course_Progress' ) ) {
			$progress = new FLMS_Course_Progress();
			if ( method_exists( $progress, 'unenroll_user' ) ) {
				$progress->unenroll_user( $user_id, $course_id, $course_version );
				return;
			}
			
			if ( method_exists( $progress, 'reset_course_progress' ) ) {
				$progress->reset_course_progress( $user_id, $course_id, $course_version );
			}
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
	
	/**
	 * Retrieve enrollment row by ID
	 */
	public function get_enrollment( $enrollment_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d",
			absint( $enrollment_id )
		) );
	}
	
	/**
	 * Update reporting fee details for an enrollment
	 */
	public function set_enrollment_fee_details( $enrollment_id, $fee_amount, $fee_label = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		$enrollment = $this->get_enrollment( $enrollment_id );
		if ( ! $enrollment ) {
			return false;
		}
		
		$current_total = isset( $enrollment->reporting_fee_total ) ? floatval( $enrollment->reporting_fee_total ) : 0;
		$fee_amount = floatval( $fee_amount );
		if ( $fee_amount <= 0 ) {
			return true;
		}
		
		$details = array();
		if ( ! empty( $enrollment->reporting_fee_details ) ) {
			$decoded = maybe_unserialize( $enrollment->reporting_fee_details );
			if ( is_array( $decoded ) ) {
				$details = $decoded;
			}
		}
		
		$details[] = array(
			'label' => $fee_label ? sanitize_text_field( $fee_label ) : __( 'Reporting Fee', 'bhfe-groups' ),
			'amount' => $fee_amount,
		);
		
		$new_total = $current_total + $fee_amount;
		
		return $wpdb->update(
			$table,
			array(
				'reporting_fee_total' => $new_total,
				'reporting_fee_details' => maybe_serialize( $details ),
			),
			array( 'id' => absint( $enrollment_id ) ),
			array( '%f', '%s' ),
			array( '%d' )
		);
	}
}

