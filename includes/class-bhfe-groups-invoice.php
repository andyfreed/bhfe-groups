<?php
/**
 * Invoice management for BHFE Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BHFE_Groups_Invoice {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Calculate running total for a group
	 */
	public function calculate_running_total( $group_id ) {
		$enrollment_class = BHFE_Groups_Enrollment::get_instance();
		$pending_enrollments = $enrollment_class->get_pending_enrollments( $group_id );
		
		$total = 0;
		
		foreach ( $pending_enrollments as $enrollment ) {
			$price = $enrollment_class->get_course_price( $enrollment->course_id );
			$total += floatval( $price );
		}
		
		return $total;
	}
	
	/**
	 * Create invoice from pending enrollments
	 */
	public function create_invoice( $group_id, $order_id = null ) {
		global $wpdb;
		
		$enrollment_class = BHFE_Groups_Enrollment::get_instance();
		$pending_enrollments = $enrollment_class->get_pending_enrollments( $group_id );
		
		if ( empty( $pending_enrollments ) ) {
			return false; // No pending enrollments
		}
		
		$total = 0;
		$enrollment_ids = array();
		
		foreach ( $pending_enrollments as $enrollment ) {
			$price = $enrollment_class->get_course_price( $enrollment->course_id );
			$total += floatval( $price );
			$enrollment_ids[] = $enrollment->id;
		}
		
		$invoices_table = $wpdb->prefix . 'bhfe_group_invoices';
		
		$result = $wpdb->insert(
			$invoices_table,
			array(
				'group_id' => absint( $group_id ),
				'order_id' => $order_id ? absint( $order_id ) : null,
				'total_amount' => $total,
				'status' => $order_id ? 'paid' : 'pending',
				'paid_date' => $order_id ? current_time( 'mysql' ) : null
			),
			array( '%d', '%d', '%f', '%s', '%s' )
		);
		
		if ( $result ) {
			$invoice_id = $wpdb->insert_id;
			
			// Link enrollments to invoice/order
			if ( $order_id ) {
				$this->link_enrollments_to_order( $enrollment_ids, $order_id );
			}
			
			return $invoice_id;
		}
		
		return false;
	}
	
	/**
	 * Link enrollments to order
	 */
	private function link_enrollments_to_order( $enrollment_ids, $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		$placeholders = implode( ',', array_fill( 0, count( $enrollment_ids ), '%d' ) );
		$query = "UPDATE $table SET order_id = %d WHERE id IN ($placeholders)";
		
		$params = array_merge( array( $order_id ), $enrollment_ids );
		
		return $wpdb->query( $wpdb->prepare( $query, $params ) );
	}
	
	/**
	 * Get invoices for a group
	 */
	public function get_group_invoices( $group_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_invoices';
		
		return $wpdb->get_results( $wpdb->prepare( 
			"SELECT * FROM $table 
			WHERE group_id = %d 
			ORDER BY invoice_date DESC",
			$group_id
		) );
	}
	
	/**
	 * Get invoice by ID
	 */
	public function get_invoice( $invoice_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_invoices';
		
		return $wpdb->get_row( $wpdb->prepare( 
			"SELECT * FROM $table WHERE id = %d",
			$invoice_id
		) );
	}
	
	/**
	 * Mark invoice as paid
	 */
	public function mark_invoice_paid( $invoice_id, $order_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_invoices';
		
		$data = array(
			'status' => 'paid',
			'paid_date' => current_time( 'mysql' )
		);
		
		if ( $order_id ) {
			$data['order_id'] = absint( $order_id );
		}
		
		return $wpdb->update(
			$table,
			$data,
			array( 'id' => absint( $invoice_id ) ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	}
}

