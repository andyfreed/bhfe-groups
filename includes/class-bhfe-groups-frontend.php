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
		// Add Groups endpoint to My Account
		add_action( 'init', array( $this, 'add_groups_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_groups_menu_item' ) );
		add_action( 'woocommerce_account_groups_endpoint', array( $this, 'render_groups_endpoint' ) );
		
		// Add Group Checkout endpoint display
		add_action( 'init', array( $this, 'add_group_checkout_endpoint' ) );
		add_action( 'woocommerce_account_group-checkout_endpoint', array( $this, 'render_group_checkout_endpoint' ) );
		
		// Enqueue frontend scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		
		// Show group info in My Account dashboard
		add_action( 'woocommerce_account_dashboard', array( $this, 'show_group_info' ), 5 );
		
		// Add group info to course pages
		add_action( 'flms_before_course_content', array( $this, 'show_group_notice' ), 10 );
	}
	
	/**
	 * Enqueue frontend assets for groups management
	 */
	public function enqueue_frontend_assets() {
		// Only load on groups endpoint
		if ( ! is_account_page() || ! is_wc_endpoint_url( 'groups' ) ) {
			return;
		}
		
		// Enqueue Select2 from theme if available
		$theme_url = get_template_directory_uri();
		if ( ! wp_script_is( 'select2', 'enqueued' ) ) {
			wp_enqueue_style( 'select2', $theme_url . '/assets/select2/select2.min.css', false, '4.1.0' );
			wp_enqueue_script( 'select2', $theme_url . '/assets/select2/select2.min.js', array( 'jquery' ), '4.1.0', true );
		}
		
		// Localize script for AJAX
		wp_localize_script( 'jquery', 'bhfeGroupsFrontend', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bhfe-groups-nonce' ),
			'accountUrl' => wc_get_account_endpoint_url( 'groups' )
		) );
	}
	
	/**
	 * Add Groups endpoint to WooCommerce My Account
	 */
	public function add_groups_endpoint() {
		add_rewrite_endpoint( 'groups', EP_ROOT | EP_PAGES );
	}
	
	/**
	 * Add group checkout endpoint (display portion)
	 */
	public function add_group_checkout_endpoint() {
		add_rewrite_endpoint( 'group-checkout', EP_ROOT | EP_PAGES );
	}
	
	/**
	 * Add Groups menu item to My Account
	 */
	public function add_groups_menu_item( $items ) {
		// Only show to users who can manage groups
		if ( ! current_user_can( 'manage_bhfe_group' ) ) {
			return $items;
		}
		
		// Insert after orders
		$new_items = array();
		foreach ( $items as $key => $item ) {
			$new_items[ $key ] = $item;
			if ( $key === 'orders' ) {
				$new_items['groups'] = __( 'Groups', 'bhfe-groups' );
			}
		}
		
		// If orders doesn't exist, add to end
		if ( ! isset( $new_items['groups'] ) ) {
			$new_items['groups'] = __( 'Groups', 'bhfe-groups' );
		}
		
		return $new_items;
	}
	
	/**
	 * Render Groups endpoint content
	 */
	public function render_groups_endpoint() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id || ! current_user_can( 'manage_bhfe_group' ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to manage groups.', 'bhfe-groups' ) . '</p>';
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		$groups = $db->get_groups_by_admin( $user_id );
		
		// Handle group selection
		$selected_group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
		
		if ( $selected_group_id && ! $db->is_group_admin( $user_id, $selected_group_id ) ) {
			echo '<div class="woocommerce-error">';
			echo '<p>' . esc_html__( 'You do not have permission to access this group.', 'bhfe-groups' ) . '</p>';
			echo '</div>';
			$selected_group_id = 0;
		}
		
		// Include the frontend template (variables $groups and $selected_group_id are available)
		include BHFE_GROUPS_PLUGIN_DIR . 'templates/frontend/groups-page.php';
		
		// Include JavaScript (pass selected_group_id)
		$this->render_frontend_scripts( $selected_group_id );
	}
	
	/**
	 * Render group checkout endpoint (summary before processing)
	 */
	public function render_group_checkout_endpoint() {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'You must be logged in to access this page.', 'bhfe-groups' ) . '</p>';
			return;
		}
		
		if ( ! current_user_can( 'manage_bhfe_group' ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to manage groups.', 'bhfe-groups' ) . '</p>';
			return;
		}
		
		$group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
		if ( ! $group_id ) {
			echo '<p>' . esc_html__( 'No group selected. Please choose a group to continue.', 'bhfe-groups' ) . '</p>';
			return;
		}
		
		$db = BHFE_Groups_Database::get_instance();
		
		if ( ! $db->is_group_admin( $user_id, $group_id ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to checkout for this group.', 'bhfe-groups' ) . '</p>';
			return;
		}
		
		$enrollment = BHFE_Groups_Enrollment::get_instance();
		$invoice = BHFE_Groups_Invoice::get_instance();
		
		$group = $db->get_group( $group_id );
		$pending_enrollments = $enrollment->get_pending_enrollments( $group_id );
		$pending_total = $invoice->calculate_running_total( $group_id );
		
		include BHFE_GROUPS_PLUGIN_DIR . 'templates/frontend/group-checkout-summary.php';
	}
	
	/**
	 * Render frontend JavaScript for groups management
	 */
	private function render_frontend_scripts( $selected_group_id = 0 ) {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var groupId = <?php echo $selected_group_id ? $selected_group_id : 0; ?>;
			var ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			var nonce = '<?php echo wp_create_nonce( 'bhfe-groups-nonce' ); ?>';
			var accountUrl = '<?php echo esc_js( wc_get_account_endpoint_url( 'groups' ) ); ?>';
			
			// Initialize Select2 for user search
			$('#bhfe-member-select').select2({
				ajax: {
					url: ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'bhfe_groups_search_users',
							search: params.term,
							nonce: nonce
						};
					},
					processResults: function(data) {
						return {
							results: data.data || []
						};
					}
				},
				minimumInputLength: 2
			});
			
			// Initialize Select2 for course search
			$('#bhfe-enroll-course-select').select2({
				ajax: {
					url: ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'bhfe_groups_search_courses',
							search: params.term,
							nonce: nonce
						};
					},
					processResults: function(data) {
						return {
							results: data.data || []
						};
					}
				},
				minimumInputLength: 2
			});
			
			// Tab switching
			$('.tab-button').on('click', function() {
				var tab = $(this).data('tab');
				$('.tab-button').removeClass('active');
				$(this).addClass('active');
				$('.tab-content').removeClass('active');
				$('#tab-' + tab).addClass('active');
			});
			
			// Add member
			$('#bhfe-add-member-btn').on('click', function() {
				var userId = $('#bhfe-member-select').val();
				if (!userId) {
					alert('<?php esc_html_e( 'Please select a user.', 'bhfe-groups' ); ?>');
					return;
				}
				
				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'bhfe_groups_add_member',
						group_id: groupId,
						user_id: userId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Error adding member.', 'bhfe-groups' ); ?>');
						}
					}
				});
			});
			
			// Remove member
			$('.bhfe-remove-member').on('click', function() {
				if (!confirm('<?php esc_html_e( 'Are you sure you want to remove this member?', 'bhfe-groups' ); ?>')) {
					return;
				}
				
				var userId = $(this).data('user-id');
				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'bhfe_groups_remove_member',
						group_id: groupId,
						user_id: userId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Error removing member.', 'bhfe-groups' ); ?>');
						}
					}
				});
			});
			
			// Enroll user
			$('#bhfe-enroll-btn').on('click', function() {
				var userId = $('#bhfe-enroll-user-select').val();
				var courseId = $('#bhfe-enroll-course-select').val();
				
				if (!userId || !courseId) {
					alert('<?php esc_html_e( 'Please select both a user and a course.', 'bhfe-groups' ); ?>');
					return;
				}
				
				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'bhfe_groups_enroll_user',
						group_id: groupId,
						user_id: userId,
						course_id: courseId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Error enrolling user.', 'bhfe-groups' ); ?>');
						}
					}
				});
			});
			
			// Unenroll user
			$('.bhfe-unenroll').on('click', function() {
				if (!confirm('<?php esc_html_e( 'Are you sure you want to unenroll this user?', 'bhfe-groups' ); ?>')) {
					return;
				}
				
				var userId = $(this).data('user-id');
				var courseId = $(this).data('course-id');
				
				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'bhfe_groups_unenroll_user',
						group_id: groupId,
						user_id: userId,
						course_id: courseId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Error unenrolling user.', 'bhfe-groups' ); ?>');
						}
					}
				});
			});
			
			// Create group
			$('#bhfe-create-group-btn').on('click', function() {
				$('#bhfe-create-group-modal').show();
			});
			
			$('.bhfe-cancel-modal').on('click', function() {
				$('#bhfe-create-group-modal').hide();
			});
			
			$('#bhfe-create-group-form').on('submit', function(e) {
				e.preventDefault();
				var name = $('#group-name').val();
				
				if (!name) {
					alert('<?php esc_html_e( 'Please enter a group name.', 'bhfe-groups' ); ?>');
					return;
				}
				
				var $submitBtn = $(this).find('button[type="submit"]');
				$submitBtn.prop('disabled', true).text('<?php esc_html_e( 'Creating...', 'bhfe-groups' ); ?>');
				
				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'bhfe_groups_create_group',
						name: name,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							window.location.href = accountUrl + '?group_id=' + response.data.group_id;
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Error creating group.', 'bhfe-groups' ); ?>');
							$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Create Group', 'bhfe-groups' ); ?>');
						}
					},
					error: function(xhr, status, error) {
						console.error('AJAX Error:', status, error);
						alert('<?php esc_html_e( 'An error occurred. Please check the browser console for details.', 'bhfe-groups' ); ?>');
						$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Create Group', 'bhfe-groups' ); ?>');
					}
				});
			});
		});
		</script>
		
		<style>
		.bhfe-groups-frontend-manager {
			margin: 20px 0;
		}
		.bhfe-groups-container {
			display: flex;
			gap: 20px;
			margin-top: 20px;
		}
		.bhfe-groups-sidebar {
			width: 250px;
			background: #f9f9f9;
			padding: 20px;
			border: 1px solid #ddd;
		}
		.bhfe-groups-list {
			list-style: none;
			margin: 15px 0 0;
			padding: 0;
		}
		.bhfe-groups-list li {
			margin: 0 0 5px;
		}
		.bhfe-groups-list li a {
			display: block;
			padding: 8px 12px;
			text-decoration: none;
			border-radius: 3px;
		}
		.bhfe-groups-list li.active a,
		.bhfe-groups-list li a:hover {
			background: #2271b1;
			color: #fff;
		}
		.bhfe-groups-main {
			flex: 1;
		}
		.bhfe-group-header {
			border-bottom: 1px solid #ddd;
			padding-bottom: 20px;
			margin-bottom: 20px;
		}
		.bhfe-group-stats {
			display: flex;
			gap: 30px;
			margin-top: 15px;
		}
		.bhfe-group-stats .stat {
			text-align: center;
		}
		.bhfe-group-stats .stat strong {
			display: block;
			font-size: 24px;
			color: #2271b1;
		}
		.bhfe-group-tabs {
			border-bottom: 1px solid #ddd;
			margin-bottom: 20px;
		}
		.tab-button {
			background: none;
			border: none;
			padding: 10px 20px;
			cursor: pointer;
			border-bottom: 2px solid transparent;
			margin-bottom: -1px;
		}
		.tab-button.active {
			border-bottom-color: #2271b1;
			color: #2271b1;
		}
		.tab-content {
			display: none;
		}
		.tab-content.active {
			display: block;
		}
		.bhfe-add-member,
		.bhfe-enroll-user {
			margin-bottom: 20px;
			padding: 15px;
			background: #f9f9f9;
			border: 1px solid #ddd;
		}
		.bhfe-add-member select,
		.bhfe-enroll-user select {
			margin-right: 10px;
			margin-bottom: 10px;
		}
		.bhfe-pending-total {
			margin-top: 30px;
			padding: 20px;
			background: #f0f8ff;
			border: 2px solid #2271b1;
			border-radius: 5px;
		}
		.bhfe-pending-total .total-amount {
			font-size: 32px;
			font-weight: bold;
			color: #2271b1;
			margin: 10px 0;
		}
		.status-pending {
			color: #d63638;
		}
		.status-active {
			color: #00a32a;
		}
		.status-paid {
			color: #2271b1;
		}
		#bhfe-create-group-modal {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0,0,0,0.7);
			z-index: 100000;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.bhfe-modal-content {
			background: #fff;
			padding: 30px;
			border-radius: 5px;
			max-width: 500px;
			width: 90%;
		}
		@media (max-width: 768px) {
			.bhfe-groups-container {
				flex-direction: column;
			}
			.bhfe-groups-sidebar {
				width: 100%;
			}
		}
		</style>
		<?php
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

