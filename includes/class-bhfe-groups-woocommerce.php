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
		add_filter( 'woocommerce_cart_needs_payment', array( $this, 'maybe_bypass_payment' ), 999, 2 );
		add_filter( 'woocommerce_order_needs_payment', array( $this, 'maybe_bypass_order_payment' ), 10, 2 );
		
		// Remove payment gateways for group members
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_payment_gateways_for_group_members' ), 999 );
		
		// Set cart total to 0 for group members
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'set_group_cart_total_to_zero' ), 999 );
		
		// Handle group checkout
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_group_order' ), 10, 3 );
		
		// Set order total to 0 for group members before order is created
		add_action( 'woocommerce_checkout_create_order', array( $this, 'set_group_order_total_to_zero' ), 10, 2 );
		
		// Add group info to order
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_group_info_to_order_item' ), 10, 4 );
		
		// Save group enrollment data to order items
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_group_enrollment_to_order_item' ), 10, 4 );
		
		// Show group info in cart
		add_action( 'woocommerce_before_cart', array( $this, 'show_group_info_in_cart' ) );
		
		// Add group confirmation to checkout for group members
		add_action( 'woocommerce_before_checkout_form', array( $this, 'show_group_checkout_confirmation' ), 5 );
		add_action( 'woocommerce_checkout_before_order_review', array( $this, 'show_group_checkout_confirmation_inside_form' ), 5 );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_group_checkout_confirmation' ), 10 );
		
		// Hide payment section and billing details for group members
		add_action( 'wp_head', array( $this, 'hide_payment_section_for_group_members' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'remove_billing_fields_for_group_members' ), 999 );
		add_filter( 'woocommerce_order_button_text', array( $this, 'change_place_order_button_text' ), 10 );
		
		// Add endpoint for adding group enrollments to cart
		add_action( 'init', array( $this, 'add_group_checkout_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_group_checkout' ) );
		
		// Store group enrollment data in cart items
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_group_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_group_cart_item_data' ), 10, 2 );
		
		// AJAX: prepare group checkout
		add_action( 'wp_ajax_bhfe_groups_prepare_checkout', array( $this, 'ajax_prepare_group_checkout' ) );
	}
	
	/**
	 * Add endpoint for group checkout
	 */
	public function add_group_checkout_endpoint() {
		add_rewrite_endpoint( 'group-checkout', EP_ROOT | EP_PAGES );
	}
	
	/**
	 * Handle group checkout - add pending enrollments to cart
	 */
	public function handle_group_checkout() {
		if ( ! is_wc_endpoint_url( 'group-checkout' ) ) {
			return;
		}
		
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		if ( 'process' !== $action ) {
			return;
		}
		
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}
		
		$group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
		$result = $this->prepare_group_checkout( $group_id, $user_id );
		
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			
			$redirect = $result->get_error_data( 'redirect' );
			if ( ! $redirect ) {
				$redirect = wc_get_account_endpoint_url( 'group-checkout' ) . '?group_id=' . $group_id;
			}
			
			wp_redirect( $redirect );
			exit;
		}
		
		if ( ! empty( $result['errors'] ) ) {
			foreach ( $result['errors'] as $error_message ) {
				wc_add_notice( $error_message, 'notice' );
			}
		}
		
		if ( $result['added_count'] > 0 ) {
			wc_add_notice(
				sprintf(
					_n( '%d course ready for checkout.', '%d courses ready for checkout.', $result['added_count'], 'bhfe-groups' ),
					$result['added_count']
				),
				'success'
			);
		}
		
		wp_redirect( $result['checkout_url'] );
		exit;
	}
	
	/**
	 * AJAX: Prepare checkout for group manager
	 */
	public function ajax_prepare_group_checkout() {
		check_ajax_referer( 'bhfe-groups-nonce', 'nonce' );
		
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'bhfe-groups' ) ) );
		}
		
		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		
		$result = $this->prepare_group_checkout( $group_id, $user_id );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		wp_send_json_success( array( 'checkout_url' => $result['checkout_url'] ) );
	}
	
	/**
	 * Internal helper: add pending enrollments to cart for a group
	 */
	private function prepare_group_checkout( $group_id, $user_id ) {
		if ( ! $group_id ) {
			return new WP_Error( 'bhfe_invalid_group', __( 'Invalid group ID.', 'bhfe-groups' ) );
		}
		
		$db = BHFE_Groups_Database::get_instance();
		
		if ( ! $db->is_group_admin( $user_id, $group_id ) ) {
			return new WP_Error( 'bhfe_no_permission', __( 'You do not have permission to checkout for this group.', 'bhfe-groups' ) );
		}
		
		$enrollment = BHFE_Groups_Enrollment::get_instance();
		$pending_enrollments = $enrollment->get_pending_enrollments( $group_id );
		
		if ( empty( $pending_enrollments ) ) {
			return new WP_Error(
				'bhfe_no_pending',
				__( 'No pending enrollments to checkout.', 'bhfe-groups' ),
				array( 'redirect' => wc_get_account_endpoint_url( 'group-checkout' ) . '?group_id=' . $group_id )
			);
		}
		
		if ( ! function_exists( 'WC' ) ) {
			return new WP_Error( 'bhfe_missing_wc', __( 'WooCommerce is not available. Please try again.', 'bhfe-groups' ) );
		}
		
		if ( is_null( WC()->cart ) ) {
			wc_load_cart();
		}
		
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		
		if ( is_null( WC()->cart ) ) {
			return new WP_Error( 'bhfe_missing_cart', __( 'Unable to access the cart. Please try again.', 'bhfe-groups' ) );
		}
		
		WC()->cart->empty_cart();
		
		$added_count = 0;
		$errors = array();
		
		foreach ( $pending_enrollments as $enroll ) {
			$product_data = $this->get_product_data_from_course( $enroll->course_id );
			
			if ( ! $product_data ) {
				$errors[] = sprintf( __( 'Could not find product for course: %s', 'bhfe-groups' ), $enroll->course_title ? $enroll->course_title : 'Course #' . $enroll->course_id );
				continue;
			}
			
			$pricing_product_id = $product_data['variation_id'] ? $product_data['variation_id'] : $product_data['product_id'];
			$product = wc_get_product( $pricing_product_id );
			if ( ! $product || ! $product->is_purchasable() ) {
				$errors[] = sprintf( __( 'Product is not available for course: %s', 'bhfe-groups' ), $enroll->course_title ? $enroll->course_title : 'Course #' . $enroll->course_id );
				continue;
			}
			
			$cart_item_key = WC()->cart->add_to_cart(
				$product_data['product_id'],
				1,
				$product_data['variation_id'],
				$product_data['variation']
			);
			
			if ( $cart_item_key ) {
				WC()->cart->cart_contents[ $cart_item_key ]['bhfe_group_enrollment_id'] = $enroll->id;
				WC()->cart->cart_contents[ $cart_item_key ]['bhfe_group_id'] = $group_id;
				WC()->cart->cart_contents[ $cart_item_key ]['bhfe_course_id'] = $enroll->course_id;
				$added_count++;
			} else {
				$errors[] = sprintf( __( 'Failed to add course to cart: %s', 'bhfe-groups' ), $enroll->course_title ? $enroll->course_title : 'Course #' . $enroll->course_id );
			}
		}
		
		WC()->cart->set_session();
		
		if ( 0 === $added_count ) {
			return new WP_Error( 'bhfe_cart_error', __( 'Unable to add pending enrollments to the cart.', 'bhfe-groups' ) );
		}
		
		return array(
			'checkout_url' => wc_get_checkout_url(),
			'added_count'  => $added_count,
			'errors'       => $errors,
		);
	}
	
	/**
	 * Get WooCommerce product data (simple or variation) from course ID
	 */
	private function get_product_data_from_course( $course_id ) {
		$product_id = get_post_meta( $course_id, 'flms_woocommerce_product_id', true );
		
		if ( $product_id ) {
			return $this->normalize_product_data( $product_id );
		}
		
		// Try to find direct product link
		$products = get_posts( array(
			'post_type' => 'product',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'flms_woocommerce_product_id',
					'value' => $course_id,
					'compare' => '='
				)
			)
		) );
		
		if ( ! empty( $products ) ) {
			return $this->normalize_product_data( $products[0]->ID );
		}
		
		// Check for variations linked to the course
		$variation_posts = get_posts( array(
			'post_type' => 'product_variation',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'flms_woocommerce_variable_course_ids',
					'value' => '"' . $course_id . '"',
					'compare' => 'LIKE'
				)
			)
		) );
		
		if ( ! empty( $variation_posts ) ) {
			return $this->normalize_product_data( $variation_posts[0]->ID );
		}
		
		return null;
	}
	
	/**
	 * Normalize product data for cart usage
	 */
	private function normalize_product_data( $product_identifier ) {
		$product = wc_get_product( $product_identifier );
		
		if ( ! $product ) {
			return null;
		}
		
		if ( $product->is_type( 'variation' ) ) {
			$raw_attributes = $product->get_attributes();
			$normalized_attributes = array();
			
			if ( ! empty( $raw_attributes ) && is_array( $raw_attributes ) ) {
				foreach ( $raw_attributes as $attr_key => $attr_value ) {
					$key = 0 === strpos( $attr_key, 'attribute_' ) ? $attr_key : 'attribute_' . $attr_key;
					$normalized_attributes[ $key ] = $attr_value;
				}
			}
			
			return array(
				'product_id'   => $product->get_parent_id(),
				'variation_id' => $product->get_id(),
				'variation'    => $normalized_attributes,
			);
		}
		
		return array(
			'product_id'   => $product->get_id(),
			'variation_id' => 0,
			'variation'    => array(),
		);
	}
	
	/**
	 * Add group enrollment data to cart item
	 */
	public function add_group_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		// This filter is called when adding to cart, but we set the data directly
		// in handle_group_checkout, so this is mainly for compatibility
		return $cart_item_data;
	}
	
	/**
	 * Display group info in cart item
	 */
	public function display_group_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['bhfe_group_id'] ) ) {
			$db = BHFE_Groups_Database::get_instance();
			$group = $db->get_group( $cart_item['bhfe_group_id'] );
			
			if ( $group ) {
				$item_data[] = array(
					'name' => __( 'Group', 'bhfe-groups' ),
					'value' => $group->name
				);
			}
		}
		
		return $item_data;
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
		if ( ! $this->is_group_member_with_courses() ) {
			return $needs_payment;
		}
		
		return false; // No payment needed
	}
	
	/**
	 * Bypass payment for orders from group members
	 */
	public function maybe_bypass_order_payment( $needs_payment, $order ) {
		if ( ! $order ) {
			return $needs_payment;
		}
		
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return $needs_payment;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return $needs_payment;
		}
		
		// Check if order has group meta
		$group_id = $order->get_meta( '_bhfe_group_id' );
		if ( $group_id ) {
			return false; // No payment needed for group orders
		}
		
		// Check if all items are courses
		$all_courses = true;
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$variation_id = $item->get_variation_id();
			$check_product_id = $variation_id > 0 ? $variation_id : $product_id;
			$course_id = $this->get_course_id_from_product( $check_product_id );
			
			if ( ! $course_id ) {
				$all_courses = false;
				break;
			}
		}
		
		if ( $all_courses ) {
			return false; // No payment needed
		}
		
		return $needs_payment;
	}
	
	/**
	 * Remove payment gateways for group members
	 */
	public function remove_payment_gateways_for_group_members( $gateways ) {
		if ( ! $this->is_group_member_with_courses() ) {
			return $gateways;
		}
		
		// Remove all payment gateways for group members
		return array();
	}
	
	/**
	 * Set cart total to zero for group members with courses
	 */
	public function set_group_cart_total_to_zero() {
		if ( ! $this->is_group_member_with_courses() ) {
			return;
		}
		
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		
		// Calculate the total that should be charged
		$total = $cart->get_subtotal() + $cart->get_fee_total() + $cart->get_shipping_total();
		
		// Add a negative fee to make total zero
		if ( $total > 0 ) {
			$cart->add_fee( 
				__( 'Group Member Discount', 'bhfe-groups' ), 
				-$total,
				false,
				'' 
			);
		}
	}
	
	/**
	 * Check if current user is a group member with courses in cart
	 */
	private function is_group_member_with_courses() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return false;
		}
		
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return false;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return false;
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
		
		return $all_courses;
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
	 * Set order total to zero for group members
	 */
	public function set_group_order_total_to_zero( $order, $data ) {
		if ( ! $this->is_group_member_with_courses() ) {
			return;
		}
		
		// Get user's group
		$user_id = get_current_user_id();
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( ! empty( $user_groups ) ) {
			$group = $user_groups[0];
			$order->update_meta_data( '_bhfe_group_id', $group->id );
			$order->update_meta_data( '_bhfe_group_order', 'yes' );
			$order->update_meta_data( '_bhfe_group_member_checkout', 'yes' );
		}
		
		// Set order total to 0
		$order->set_total( 0 );
		$order->set_payment_method( '' );
		$order->set_payment_method_title( __( 'Group Billing', 'bhfe-groups' ) );
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
		
		// Check if this is a group admin checkout (has group enrollment IDs in cart items)
		$group_id = null;
		$enrollment_ids = array();
		
		foreach ( $order->get_items() as $item_id => $item ) {
			// Check if cart item had group enrollment data
			$enrollment_id = wc_get_order_item_meta( $item_id, '_bhfe_group_enrollment_id', true );
			$item_group_id = wc_get_order_item_meta( $item_id, '_bhfe_group_id', true );
			
			if ( $enrollment_id && $item_group_id ) {
				$group_id = $item_group_id;
				$enrollment_ids[] = $enrollment_id;
			}
		}
		
		// If this is a group admin checkout, link enrollments to order
		if ( $group_id && ! empty( $enrollment_ids ) ) {
			$order->update_meta_data( '_bhfe_group_id', $group_id );
			$order->update_meta_data( '_bhfe_group_order', 'yes' );
			$order->update_meta_data( '_bhfe_group_admin_checkout', 'yes' );
			$order->save();
			
			// Link enrollments to order
			global $wpdb;
			$table = $wpdb->prefix . 'bhfe_group_enrollments';
			$placeholders = implode( ',', array_fill( 0, count( $enrollment_ids ), '%d' ) );
			$query = "UPDATE $table SET order_id = %d WHERE id IN ($placeholders)";
			$params = array_merge( array( $order_id ), $enrollment_ids );
			$wpdb->query( $wpdb->prepare( $query, $params ) );
			
			// Create invoice
			$invoice = BHFE_Groups_Invoice::get_instance();
			$invoice->create_invoice( $group_id, $order_id );
			
			return; // Don't process as regular group member checkout
		}
		
		// Regular group member checkout (user is a member, not admin)
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return; // Not a group member
		}
		
		// Get the first group (or you could let user select)
		$group = $user_groups[0];
		
		// Check if user is the group manager/admin
		$is_group_manager = $db->is_group_admin( $user_id, $group->id );
		
		// Add order meta to track it's a group order
		$order->update_meta_data( '_bhfe_group_id', $group->id );
		$order->update_meta_data( '_bhfe_group_order', 'yes' );
		if ( $is_group_manager ) {
			$order->update_meta_data( '_bhfe_group_manager_checkout', 'yes' );
		} else {
			$order->update_meta_data( '_bhfe_group_member_checkout', 'yes' );
		}
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
				
				// Only link enrollment to order if user is the group manager
				// Regular members' enrollments should remain "pending" (order_id = NULL) 
				// until the manager pays, so they stay in the running total
				if ( $is_group_manager ) {
					$this->link_enrollment_to_order( $group->id, $user_id, $course_id, $order_id );
				}
			}
		}
		
		// Only create invoice and link pending enrollments if user is the group manager
		// Regular group members should NOT be able to clear the running total
		if ( $is_group_manager ) {
			// Create invoice for pending enrollments (only managers can do this)
			$invoice = BHFE_Groups_Invoice::get_instance();
			$invoice->create_invoice( $group->id, $order_id );
		}
	}
	
	/**
	 * Link enrollment to order
	 */
	private function link_enrollment_to_order( $group_id, $user_id, $course_id, $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bhfe_group_enrollments';
		
		// Find the most recent active enrollment for this user/course/group that doesn't have an order_id yet
		$enrollment = $wpdb->get_row( $wpdb->prepare( 
			"SELECT id FROM $table 
			WHERE group_id = %d 
			AND user_id = %d 
			AND course_id = %d 
			AND status = 'active'
			AND (order_id IS NULL OR order_id = 0)
			ORDER BY enrolled_at DESC 
			LIMIT 1",
			$group_id,
			$user_id,
			$course_id
		) );
		
		if ( $enrollment ) {
			$wpdb->update(
				$table,
				array( 'order_id' => absint( $order_id ) ),
				array( 'id' => $enrollment->id ),
				array( '%d' ),
				array( '%d' )
			);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Add group info to order item
	 */
	public function add_group_info_to_order_item( $item, $cart_item_key, $values, $order ) {
		$user_id = $order->get_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		// Check if cart item has group enrollment data (group admin checkout)
		if ( isset( $values['bhfe_group_id'] ) ) {
			$db = BHFE_Groups_Database::get_instance();
			$group = $db->get_group( $values['bhfe_group_id'] );
			if ( $group ) {
				$item->add_meta_data( '_bhfe_group_id', $group->id );
				$item->add_meta_data( '_bhfe_group_name', $group->name );
			}
		} else {
			// Regular group member checkout
			$db = BHFE_Groups_Database::get_instance();
			$user_groups = $db->get_user_groups( $user_id );
			
			if ( ! empty( $user_groups ) ) {
				$group = $user_groups[0];
				$item->add_meta_data( '_bhfe_group_id', $group->id );
				$item->add_meta_data( '_bhfe_group_name', $group->name );
			}
		}
	}
	
	/**
	 * Save group enrollment data to order item
	 */
	public function save_group_enrollment_to_order_item( $item, $cart_item_key, $values, $order ) {
		// Save enrollment data if present (from group admin checkout)
		if ( isset( $values['bhfe_group_enrollment_id'] ) ) {
			$item->add_meta_data( '_bhfe_group_enrollment_id', $values['bhfe_group_enrollment_id'] );
		}
		
		if ( isset( $values['bhfe_group_id'] ) ) {
			$item->add_meta_data( '_bhfe_group_id', $values['bhfe_group_id'] );
		}
		
		if ( isset( $values['bhfe_course_id'] ) ) {
			$item->add_meta_data( '_bhfe_course_id', $values['bhfe_course_id'] );
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
			$group_admin = get_userdata( $group->admin_user_id );
			$admin_name = $group_admin ? $group_admin->display_name : __( 'Group Administrator', 'bhfe-groups' );
			
			echo '<div class="woocommerce-info bhfe-group-cart-notice">';
			echo '<strong>' . esc_html__( 'Group Member:', 'bhfe-groups' ) . '</strong> ';
			echo esc_html__( 'You are a member of', 'bhfe-groups' ) . ' "' . esc_html( $group->name ) . '". ';
			echo esc_html__( 'Your courses will be added to the group account and invoiced to', 'bhfe-groups' ) . ' ' . esc_html( $admin_name ) . '.';
			echo '</div>';
		}
	}
	
	/**
	 * Show group checkout confirmation for group members (before form)
	 */
	public function show_group_checkout_confirmation() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return; // Not a group member
		}
		
		$group = $user_groups[0];
		$group_admin = get_userdata( $group->admin_user_id );
		$admin_name = $group_admin ? $group_admin->display_name : __( 'Group Administrator', 'bhfe-groups' );
		$admin_email = $group_admin ? $group_admin->user_email : '';
		
		// Check if cart contains courses
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		
		$has_courses = false;
		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 
				? $cart_item['variation_id'] 
				: $cart_item['product_id'];
			
			$course_id = $this->get_course_id_from_product( $product_id );
			if ( $course_id ) {
				$has_courses = true;
				break;
			}
		}
		
		if ( ! $has_courses ) {
			return; // No courses in cart
		}
		
		?>
		<div class="bhfe-group-checkout-confirmation woocommerce-info" style="background: #f0f8ff; border: 2px solid #2271b1; padding: 20px; margin-bottom: 30px; border-radius: 5px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Group Membership Checkout', 'bhfe-groups' ); ?></h3>
			<p>
				<strong><?php esc_html_e( 'You are a member of:', 'bhfe-groups' ); ?></strong> <?php echo esc_html( $group->name ); ?><br>
				<strong><?php esc_html_e( 'Group Administrator:', 'bhfe-groups' ); ?></strong> <?php echo esc_html( $admin_name ); ?>
				<?php if ( $admin_email ) : ?>
					(<?php echo esc_html( $admin_email ); ?>)
				<?php endif; ?>
			</p>
			<p>
				<?php esc_html_e( 'The courses in your cart will be added to your account immediately, but you will not be charged. Instead, the group administrator will be invoiced for these courses.', 'bhfe-groups' ); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Show group checkout confirmation checkbox inside the form
	 */
	public function show_group_checkout_confirmation_inside_form() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return; // Not a group member
		}
		
		$group = $user_groups[0];
		
		// Check if cart contains courses
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		
		$has_courses = false;
		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 
				? $cart_item['variation_id'] 
				: $cart_item['product_id'];
			
			$course_id = $this->get_course_id_from_product( $product_id );
			if ( $course_id ) {
				$has_courses = true;
				break;
			}
		}
		
		if ( ! $has_courses ) {
			return; // No courses in cart
		}
		
		?>
		<div class="bhfe-group-checkout-confirmation-checkbox" style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
			<p style="margin: 0 0 10px 0;">
				<label for="bhfe_group_checkout_confirm" style="font-weight: bold; display: block; cursor: pointer;">
					<input type="checkbox" id="bhfe_group_checkout_confirm" name="bhfe_group_checkout_confirm" value="1" style="margin-right: 8px;" />
					<?php esc_html_e( 'I understand that these courses will be billed to the group administrator and I will not be charged.', 'bhfe-groups' ); ?>
				</label>
			</p>
			<input type="hidden" name="bhfe_group_id" value="<?php echo esc_attr( $group->id ); ?>" />
		</div>
		<?php
	}
	
	/**
	 * Remove billing fields for group members
	 */
	public function remove_billing_fields_for_group_members( $fields ) {
		if ( ! is_checkout() ) {
			return $fields;
		}
		
		if ( ! $this->is_group_member_with_courses() ) {
			return $fields;
		}
		
		// Remove all billing fields
		if ( isset( $fields['billing'] ) ) {
			unset( $fields['billing'] );
		}
		
		return $fields;
	}
	
	/**
	 * Change Place Order button text for group members
	 */
	public function change_place_order_button_text( $button_text ) {
		if ( ! is_checkout() ) {
			return $button_text;
		}
		
		if ( ! $this->is_group_member_with_courses() ) {
			return $button_text;
		}
		
		return __( 'Proceed to Enroll', 'bhfe-groups' );
	}
	
	/**
	 * Hide payment section and billing details for group members
	 */
	public function hide_payment_section_for_group_members() {
		if ( ! is_checkout() ) {
			return;
		}
		
		if ( ! $this->is_group_member_with_courses() ) {
			return;
		}
		
		?>
		<style>
			/* Hide payment section but keep the button */
			.woocommerce-checkout #payment_methods,
			.woocommerce-checkout .payment_methods,
			.woocommerce-checkout .wc_payment_methods {
				display: none !important;
			}
			
			/* Show payment section container but hide gateway options */
			.woocommerce-checkout #payment {
				display: block !important;
			}
			
			.woocommerce-checkout #payment .payment_methods {
				display: none !important;
			}
			
			/* Hide billing details section */
			.woocommerce-checkout .woocommerce-billing-fields,
			.woocommerce-checkout #customer_details .col-1,
			.woocommerce-checkout .woocommerce-billing-fields__field-wrapper {
				display: none !important;
			}
			
			/* Show Place Order button prominently */
			.woocommerce-checkout #place_order {
				display: block !important;
				width: 100% !important;
				padding: 15px 30px !important;
				font-size: 18px !important;
				font-weight: bold !important;
				background-color: #2271b1 !important;
				color: #fff !important;
				border: none !important;
				border-radius: 5px !important;
				cursor: pointer !important;
				margin-top: 20px !important;
				opacity: 1 !important;
				visibility: visible !important;
			}
			
			.woocommerce-checkout #place_order:hover:not(:disabled) {
				background-color: #135e96 !important;
			}
			
			/* Disabled state - grayed out */
			.woocommerce-checkout #place_order:disabled {
				background-color: #ccc !important;
				color: #666 !important;
				cursor: not-allowed !important;
				opacity: 0.6 !important;
			}
			
			/* Adjust layout - make order review full width */
			.woocommerce-checkout .woocommerce-checkout-review-order {
				width: 100% !important;
			}
			
			/* Hide customer details wrapper if it's empty */
			.woocommerce-checkout #customer_details:empty,
			.woocommerce-checkout #customer_details .col-1:empty {
				display: none !important;
			}
		</style>
		<script>
		jQuery(document).ready(function($) {
			// Check if this is a group member checkout
			var isGroupMember = $('#bhfe_group_checkout_confirm').length > 0;
			
			if (isGroupMember) {
				var $checkbox = $('#bhfe_group_checkout_confirm');
				var $placeOrderBtn = $('#place_order');
				var $checkoutForm = $('form.checkout');
				
				// Initially disable the button
				$placeOrderBtn.prop('disabled', true);
				
				// Enable/disable based on checkbox
				$checkbox.on('change', function() {
					if ($(this).is(':checked')) {
						$placeOrderBtn.prop('disabled', false);
					} else {
						$placeOrderBtn.prop('disabled', true);
					}
				});
				
				// Also check on page load in case checkbox is pre-checked
				if ($checkbox.is(':checked')) {
					$placeOrderBtn.prop('disabled', false);
				}
				
				// Ensure checkbox value is included in form submission
				// Add a hidden field that gets updated based on checkbox state
				if ($checkoutForm.length && !$checkoutForm.find('input[name="bhfe_group_checkout_confirm_hidden"]').length) {
					$checkoutForm.append('<input type="hidden" name="bhfe_group_checkout_confirm_hidden" value="0" />');
				}
				
				// Update hidden field when checkbox changes
				$checkbox.on('change', function() {
					var $hiddenField = $checkoutForm.find('input[name="bhfe_group_checkout_confirm_hidden"]');
					if ($hiddenField.length) {
						$hiddenField.val($(this).is(':checked') ? '1' : '0');
					}
				});
				
				// Ensure checkbox value is included before form submission
				// Hook into WooCommerce checkout form submission
				$checkoutForm.on('submit', function(e) {
					if ($checkbox.is(':checked')) {
						// Ensure the checkbox value is in the form data
						var $hiddenConfirm = $checkoutForm.find('input[name="bhfe_group_checkout_confirm"]');
						if (!$hiddenConfirm.length) {
							$checkoutForm.append('<input type="hidden" name="bhfe_group_checkout_confirm" value="1" />');
						}
						// Also update hidden field
						var $hiddenField = $checkoutForm.find('input[name="bhfe_group_checkout_confirm_hidden"]');
						if ($hiddenField.length) {
							$hiddenField.val('1');
						}
					}
				});
				
				// Handle WooCommerce AJAX checkout (before AJAX request)
				$(document.body).on('checkout_place_order', function() {
					if ($checkbox.is(':checked')) {
						// Ensure the checkbox value is in the form data
						var $hiddenConfirm = $checkoutForm.find('input[name="bhfe_group_checkout_confirm"]');
						if (!$hiddenConfirm.length) {
							$checkoutForm.append('<input type="hidden" name="bhfe_group_checkout_confirm" value="1" />');
						} else {
							$hiddenConfirm.val('1');
						}
						// Also update hidden field
						var $hiddenField = $checkoutForm.find('input[name="bhfe_group_checkout_confirm_hidden"]');
						if ($hiddenField.length) {
							$hiddenField.val('1');
						}
					}
				});
			}
		});
		</script>
		<?php
	}
	
	/**
	 * Validate group checkout confirmation
	 */
	public function validate_group_checkout_confirmation() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$user_groups = $db->get_user_groups( $user_id );
		
		if ( empty( $user_groups ) ) {
			return; // Not a group member
		}
		
		// Check if cart contains courses
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		
		$has_courses = false;
		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 
				? $cart_item['variation_id'] 
				: $cart_item['product_id'];
			
			$course_id = $this->get_course_id_from_product( $product_id );
			if ( $course_id ) {
				$has_courses = true;
				break;
			}
		}
		
		if ( ! $has_courses ) {
			return; // No courses in cart
		}
		
		// Require confirmation checkbox
		// Check both the checkbox field and the hidden field (for AJAX compatibility)
		$confirmed = false;
		if ( isset( $_POST['bhfe_group_checkout_confirm'] ) && $_POST['bhfe_group_checkout_confirm'] == '1' ) {
			$confirmed = true;
		} elseif ( isset( $_POST['bhfe_group_checkout_confirm_hidden'] ) && $_POST['bhfe_group_checkout_confirm_hidden'] == '1' ) {
			$confirmed = true;
		}
		
		if ( ! $confirmed ) {
			wc_add_notice( __( 'Please confirm that you understand the courses will be billed to the group administrator.', 'bhfe-groups' ), 'error' );
		}
	}
}

