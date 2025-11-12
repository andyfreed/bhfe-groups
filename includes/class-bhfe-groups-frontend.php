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
		
		// Show group info in My Account dashboard
		add_action( 'woocommerce_account_dashboard', array( $this, 'show_group_info' ), 5 );
		
		// Add group info to course pages
		add_action( 'flms_before_course_content', array( $this, 'show_group_notice' ), 10 );
	}
	
	/**
	 * Add Groups endpoint to WooCommerce My Account
	 */
	public function add_groups_endpoint() {
		add_rewrite_endpoint( 'groups', EP_ROOT | EP_PAGES );
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
		
		?>
		<div class="bhfe-groups-frontend">
			<h2><?php esc_html_e( 'Manage Groups', 'bhfe-groups' ); ?></h2>
			
			<?php if ( empty( $groups ) && ! $selected_group_id ) : ?>
				<div class="woocommerce-info">
					<p><?php esc_html_e( 'You haven\'t created any groups yet.', 'bhfe-groups' ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bhfe-groups' ) ); ?>" class="button">
						<?php esc_html_e( 'Go to Groups Admin', 'bhfe-groups' ); ?>
					</a></p>
					<p class="description">
						<?php esc_html_e( 'Note: Full group management is available in the WordPress admin area.', 'bhfe-groups' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="bhfe-groups-list">
					<h3><?php esc_html_e( 'Your Groups', 'bhfe-groups' ); ?></h3>
					<ul>
						<?php foreach ( $groups as $group ) : ?>
							<li>
								<strong><?php echo esc_html( $group->name ); ?></strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=bhfe-groups&group_id=' . $group->id ) ); ?>" class="button">
									<?php esc_html_e( 'Manage', 'bhfe-groups' ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="description">
						<?php esc_html_e( 'Click "Manage" to view members, enrollments, and invoices for each group.', 'bhfe-groups' ); ?>
					</p>
				</div>
			<?php endif; ?>
			
			<div class="bhfe-groups-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bhfe-groups' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Go to Full Groups Admin', 'bhfe-groups' ); ?>
				</a>
			</div>
		</div>
		
		<style>
		.bhfe-groups-frontend {
			margin: 20px 0;
		}
		.bhfe-groups-list ul {
			list-style: none;
			padding: 0;
		}
		.bhfe-groups-list li {
			padding: 15px;
			margin-bottom: 10px;
			background: #f9f9f9;
			border: 1px solid #ddd;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.bhfe-groups-actions {
			margin-top: 30px;
			padding-top: 20px;
			border-top: 1px solid #ddd;
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

