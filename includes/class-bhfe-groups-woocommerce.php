<?php
/**
 * WooCommerce integration for BHFE Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BHFE_Groups_WooCommerce {
	
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
		// Check if user is in a group before checkout
		add_action( 'woocommerce_checkout_process', array( $this, 'check_group_membership' ), 10 );
		
		// Allow group members to bypass payment
		add_filter( 'woocommerce_cart_needs_payment', array( $this, 'maybe_bypass_payment' ), 10, 2 );
		
		// Handle group checkout
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_group_order' ), 10, 3 );
		
		// Add group info to order
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_group_info_to_order_item' ), 10, 4 );
		
		// Show group info in cart
		add_action( 'woocommerce_before_cart', array( $this, 'show_group_info_in_cart' ) );
	}
	
	/**
	 * Check if user is a group member and handle checkout accordingly
	 */
	public function check_group_membership() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return; // Not a group member, proceed normally
		}
		
		// User is in a group - they can checkout but we'll track it
		// The payment bypass will be handled by maybe_bypass_payment
	}
	
	/**
	 * Maybe bypass payment for group members
	 */
	public function maybe_bypass_payment( $needs_payment, $cart ) {
		$user_id = get_current_user_id();
		
		if ( ! $user_id || ! $cart ) {
			return $needs_payment;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return $needs_payment;
		}
		
		// Check if all items in cart are courses
		$all_courses = true;
		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 
				? $cart_item['variation_id'] 
				: $cart_item['product_id'];
			
			// Check if product is linked to a course
			$course_id = $this->get_course_id_from_product( $product_id );
			
			if ( ! $course_id ) {
				$all_courses = false;
				break;
			}
		}
		
		// If all items are courses and user is in a group, bypass payment
		// The group admin will be invoiced separately
		if ( $all_courses ) {
			return false; // No payment needed
		}
		
		return $needs_payment;
	}
	
	/**
	 * Get course ID from product ID
	 * Handles both simple and variable products
	 */
	private function get_course_id_from_product( $product_id ) {
		// First check if product has course_id meta (some setups)
		$course_id = get_post_meta( $product_id, 'flms_woocommerce_product_id', true );
		
		if ( $course_id ) {
			return $course_id;
		}
		
		// Check for variable product course IDs
		$variable_courses = get_post_meta( $product_id, 'flms_woocommerce_variable_course_ids', true );
		if ( ! empty( $variable_courses ) && is_array( $variable_courses ) ) {
			// Return first course ID for now
			$course_data = explode( ':', $variable_courses[0] );
			return isset( $course_data[0] ) ? $course_data[0] : null;
		}
		
		// Check for simple product course IDs
		$simple_courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
		if ( ! empty( $simple_courses ) && is_array( $simple_courses ) ) {
			// Return first course ID for now
			$course_data = explode( ':', $simple_courses[0] );
			return isset( $course_data[0] ) ? $course_data[0] : null;
		}
		
		// Reverse lookup: find course that has this product_id
		$courses = get_posts( array(
			'post_type' => 'flms-courses',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'flms_woocommerce_product_id',
					'value' => $product_id,
					'compare' => '='
				)
			)
		) );
		
		if ( ! empty( $courses ) ) {
			return $courses[0]->ID;
		}
		
		return null;
	}
	
	/**
	 * Process group order after checkout
	 */
	public function process_group_order( $order_id, $data, $order ) {
		$user_id = $order->get_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return; // Not a group member
		}
		
		// Get the first group (or you could let user select)
		$group = $user_groups[0];
		
		// Add order meta to track it's a group order
		$order->update_meta_data( '_bhfe_group_id', $group->id );
		$order->update_meta_data( '_bhfe_group_order', 'yes' );
		$order->save();
		
		// Process enrollments for each item
		$enrollment = BHFE_Groups_Enrollment::get_instance();
		
		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();
			$variation_id = $item->get_variation_id();
			
			// Use variation ID if available, otherwise product ID
			$check_product_id = $variation_id > 0 ? $variation_id : $product_id;
			$course_id = $this->get_course_id_from_product( $check_product_id );
			
			if ( $course_id ) {
				// Get course version from product if available
				$course_version = 1; // Default version
				
				// Try to get version from variation or product meta
				if ( $variation_id > 0 ) {
					$variable_courses = get_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', true );
					if ( ! empty( $variable_courses ) && is_array( $variable_courses ) ) {
						foreach ( $variable_courses as $course_data ) {
							$parts = explode( ':', $course_data );
							if ( isset( $parts[0] ) && $parts[0] == $course_id && isset( $parts[1] ) ) {
								$course_version = intval( $parts[1] );
								break;
							}
						}
					}
				} else {
					$simple_courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
					if ( ! empty( $simple_courses ) && is_array( $simple_courses ) ) {
						foreach ( $simple_courses as $course_data ) {
							$parts = explode( ':', $course_data );
							if ( isset( $parts[0] ) && $parts[0] == $course_id && isset( $parts[1] ) ) {
								$course_version = intval( $parts[1] );
								break;
							}
						}
					}
				}
				
				// Enroll user in course
				$enrollment->enroll_user( 
					$group->id, 
					$user_id, 
					$course_id, 
					$course_version,
					$user_id // Self-enrolled via checkout
				);
				
				// Link enrollment to order
				$this->link_enrollment_to_order( $group->id, $user_id, $course_id, $order_id );
			}
		}
		
		// Create invoice for pending enrollments
		$invoice = BHFE_Groups_Invoice::get_instance();
		$invoice->create_invoice( $group->id, $order_id );
	}
	
	/**
	 * Link enrollment to order
	 */
	private function link_enrollment_to_order( $group_id, $user_id, $course_id, $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		$wpdb->update(
			$table,
			array( 'order_id' => absint( $order_id ) ),
			array( 
				'group_id' => absint( $group_id ),
				'user_id' => absint( $user_id ),
				'course_id' => absint( $course_id )
			),
			array( '%d' ),
			array( '%d', '%d', '%d' )
		);
	}
	
	/**
	 * Add group info to order item
	 */
	public function add_group_info_to_order_item( $item, $cart_item_key, $values, $order ) {
		$user_id = $order->get_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( ! empty( $user_groups ) ) {
			$group = $user_groups[0];
			$item->add_meta_data( '_bhfe_group_id', $group->id );
			$item->add_meta_data( '_bhfe_group_name', $group->name );
		}
	}
	
	/**
	 * Show group info in cart
	 */
	public function show_group_info_in_cart() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( ! empty( $user_groups ) ) {
			$group = $user_groups[0];
			echo '<div class="woocommerce-info bhfe-group-cart-notice">';
			echo '<strong>Group Member:</strong> You are a member of "' . esc_html( $group->name ) . '". ';
			echo 'Your courses will be added to the group account and invoiced to the group administrator.';
			echo '</div>';
		}
	}
}

