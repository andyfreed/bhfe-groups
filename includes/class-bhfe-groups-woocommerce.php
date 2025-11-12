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
		
		// Save group enrollment data to order items
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_group_enrollment_to_order_item' ), 10, 4 );
		
		// Show group info in cart
		add_action( 'woocommerce_before_cart', array( $this, 'show_group_info_in_cart' ) );
		
		// Add endpoint for adding group enrollments to cart
		add_action( 'init', array( $this, 'add_group_checkout_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_group_checkout' ) );
		
		// Store group enrollment data in cart items
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_group_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_group_cart_item_data' ), 10, 2 );
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
		
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}
		
		$group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
		
		if ( ! $group_id ) {
			wc_add_notice( __( 'Invalid group ID.', 'bhfe-groups' ), 'error' );
			wp_redirect( wc_get_account_endpoint_url( 'groups' ) );
			exit;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		if ( ! $db->is_group_admin( $user_id, $group_id ) ) {
			wc_add_notice( __( 'You do not have permission to checkout for this group.', 'bhfe-groups' ), 'error' );
			wp_redirect( wc_get_account_endpoint_url( 'groups' ) );
			exit;
		}
		
		// Get pending enrollments
		$enrollment = BHFE_Groups_Enrollment::get_instance();
		$pending_enrollments = $enrollment->get_pending_enrollments( $group_id );
		
		if ( empty( $pending_enrollments ) ) {
			wc_add_notice( __( 'No pending enrollments to checkout.', 'bhfe-groups' ), 'notice' );
			wp_redirect( wc_get_account_endpoint_url( 'groups' ) . '?group_id=' . $group_id );
			exit;
		}
		
		// Clear cart first
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		
		$added_count = 0;
		$errors = array();
		
		// Store enrollment mapping in session for cart item data
		$enrollment_map = array();
		
		// Add each enrollment's course product to cart
		foreach ( $pending_enrollments as $enroll ) {
			$product_id = $this->get_product_id_from_course( $enroll->course_id );
			
			if ( ! $product_id ) {
				$errors[] = sprintf( __( 'Could not find product for course: %s', 'bhfe-groups' ), $enroll->course_title ? $enroll->course_title : 'Course #' . $enroll->course_id );
				continue;
			}
			
			// Check if product exists and is purchasable
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_purchasable() ) {
				$errors[] = sprintf( __( 'Product is not available for course: %s', 'bhfe-groups' ), $enroll->course_title ? $enroll->course_title : 'Course #' . $enroll->course_id );
				continue;
			}
			
			// Store mapping for this product
			$enrollment_map[ $product_id ] = array(
				'bhfe_group_enrollment_id' => $enroll->id,
				'bhfe_group_id' => $group_id,
				'bhfe_course_id' => $enroll->course_id
			);
			
			// Add to cart
			$cart_item_key = WC()->cart->add_to_cart( $product_id, 1 );
			
			if ( $cart_item_key ) {
				// Add enrollment data to cart item
				WC()->cart->cart_contents[ $cart_item_key ]['bhfe_group_enrollment_id'] = $enroll->id;
				WC()->cart->cart_contents[ $cart_item_key ]['bhfe_group_id'] = $group_id;
				WC()->cart->cart_contents[ $cart_item_key ]['bhfe_course_id'] = $enroll->course_id;
				$added_count++;
			} else {
				$errors[] = sprintf( __( 'Failed to add course to cart: %s', 'bhfe-groups' ), $enroll->course_title ? $enroll->course_title : 'Course #' . $enroll->course_id );
			}
		}
		
		// Store cart data
		WC()->cart->set_session();
		
		if ( $added_count > 0 ) {
			wc_add_notice( sprintf( _n( '%d course added to cart.', '%d courses added to cart.', $added_count, 'bhfe-groups' ), $added_count ), 'success' );
		}
		
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				wc_add_notice( $error, 'error' );
			}
		}
		
		// Redirect to checkout
		wp_redirect( wc_get_checkout_url() );
		exit;
	}
	
	/**
	 * Get WooCommerce product ID from course ID
	 */
	private function get_product_id_from_course( $course_id ) {
		// First check if course has product_id meta
		$product_id = get_post_meta( $course_id, 'flms_woocommerce_product_id', true );
		
		if ( $product_id ) {
			return $product_id;
		}
		
		// Try to find product that links to this course
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
			return $products[0]->ID;
		}
		
		// Check variable products
		$variable_products = get_posts( array(
			'post_type' => 'product_variation',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'flms_woocommerce_variable_course_ids',
					'value' => $course_id,
					'compare' => 'LIKE'
				)
			)
		) );
		
		if ( ! empty( $variable_products ) ) {
			return $variable_products[0]->ID;
		}
		
		return null;
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
			echo '<div class="woocommerce-info bhfe-group-cart-notice">';
			echo '<strong>Group Member:</strong> You are a member of "' . esc_html( $group->name ) . '". ';
			echo 'Your courses will be added to the group account and invoiced to the group administrator.';
			echo '</div>';
		}
	}
}

