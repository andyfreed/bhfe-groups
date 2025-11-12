<?php
/**
 * Frontend template for Groups management page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = BHFE_Groups_Database::get_instance();
$enrollment = BHFE_Groups_Enrollment::get_instance();
$invoice = BHFE_Groups_Invoice::get_instance();

// Get current group data
$selected_group = null;
$members = array();
$enrollments = array();
$pending_total = 0;

if ( $selected_group_id ) {
	$selected_group = $db->get_group( $selected_group_id );
	if ( $selected_group ) {
		$members = $db->get_group_members( $selected_group_id );
		$enrollments = $enrollment->get_group_enrollments( $selected_group_id );
		$pending_total = $invoice->calculate_running_total( $selected_group_id );
	}
}
?>

<div class="bhfe-groups-frontend-manager">
	<h2><?php esc_html_e( 'Manage Groups', 'bhfe-groups' ); ?></h2>
	
	<div class="bhfe-groups-container">
		<!-- Groups List -->
		<div class="bhfe-groups-sidebar">
			<h3><?php esc_html_e( 'Your Groups', 'bhfe-groups' ); ?></h3>
			
			<button type="button" class="button button-primary" id="bhfe-create-group-btn">
				<?php esc_html_e( '+ Create New Group', 'bhfe-groups' ); ?>
			</button>
			
			<ul class="bhfe-groups-list">
				<?php if ( empty( $groups ) ) : ?>
					<li class="no-groups"><?php esc_html_e( 'No groups yet. Create your first group!', 'bhfe-groups' ); ?></li>
				<?php else : ?>
					<?php foreach ( $groups as $group ) : ?>
						<li class="<?php echo $selected_group_id == $group->id ? 'active' : ''; ?>">
							<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'groups' ) . '?group_id=' . $group->id ); ?>">
								<?php echo esc_html( $group->name ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
		</div>
		
		<!-- Group Details -->
		<div class="bhfe-groups-main">
			<?php if ( $selected_group ) : ?>
				<div class="bhfe-group-header">
					<h3><?php echo esc_html( $selected_group->name ); ?></h3>
					<div class="bhfe-group-stats">
						<div class="stat">
							<strong><?php echo count( $members ); ?></strong>
							<span><?php esc_html_e( 'Members', 'bhfe-groups' ); ?></span>
						</div>
						<div class="stat">
							<strong><?php echo count( $enrollments ); ?></strong>
							<span><?php esc_html_e( 'Enrollments', 'bhfe-groups' ); ?></span>
						</div>
						<div class="stat">
							<strong><?php echo wc_price( $pending_total ); ?></strong>
							<span><?php esc_html_e( 'Pending Total', 'bhfe-groups' ); ?></span>
						</div>
					</div>
				</div>
				
				<!-- Tabs -->
				<div class="bhfe-group-tabs">
					<button class="tab-button active" data-tab="members"><?php esc_html_e( 'Members', 'bhfe-groups' ); ?></button>
					<button class="tab-button" data-tab="enrollments"><?php esc_html_e( 'Enrollments', 'bhfe-groups' ); ?></button>
					<button class="tab-button" data-tab="invoices"><?php esc_html_e( 'Invoices', 'bhfe-groups' ); ?></button>
				</div>
				
				<!-- Members Tab -->
				<div class="tab-content active" id="tab-members">
					<h4><?php esc_html_e( 'Group Members', 'bhfe-groups' ); ?></h4>
					
					<div class="bhfe-add-member">
						<select id="bhfe-member-select" style="width: 100%; max-width: 400px;" data-placeholder="<?php esc_attr_e( 'Search for a user...', 'bhfe-groups' ); ?>">
							<option></option>
						</select>
						<button type="button" class="button" id="bhfe-add-member-btn">
							<?php esc_html_e( 'Add Member', 'bhfe-groups' ); ?>
						</button>
					</div>
					
					<table class="woocommerce-table shop_table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Email', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Added', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bhfe-groups' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $members ) ) : ?>
								<tr>
									<td colspan="4"><?php esc_html_e( 'No members yet.', 'bhfe-groups' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $members as $member ) : ?>
									<tr data-member-id="<?php echo esc_attr( $member->user_id ); ?>">
										<td><?php echo esc_html( $member->display_name ); ?></td>
										<td><?php echo esc_html( $member->user_email ); ?></td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member->added_at ) ) ); ?></td>
										<td>
											<button type="button" class="button-link bhfe-remove-member" data-user-id="<?php echo esc_attr( $member->user_id ); ?>">
												<?php esc_html_e( 'Remove', 'bhfe-groups' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				
				<!-- Enrollments Tab -->
				<div class="tab-content" id="tab-enrollments">
					<h4><?php esc_html_e( 'Enroll Users in Courses', 'bhfe-groups' ); ?></h4>
					
					<div class="bhfe-enroll-user">
						<select id="bhfe-enroll-user-select" style="width: 100%; max-width: 300px;" data-placeholder="<?php esc_attr_e( 'Select a member...', 'bhfe-groups' ); ?>">
							<option></option>
							<?php foreach ( $members as $member ) : ?>
								<option value="<?php echo esc_attr( $member->user_id ); ?>">
									<?php echo esc_html( $member->display_name . ' (' . $member->user_email . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						
						<select id="bhfe-enroll-course-select" style="width: 100%; max-width: 400px;" data-placeholder="<?php esc_attr_e( 'Search for a course...', 'bhfe-groups' ); ?>">
							<option></option>
						</select>
						
						<button type="button" class="button button-primary" id="bhfe-enroll-btn">
							<?php esc_html_e( 'Enroll', 'bhfe-groups' ); ?>
						</button>
					</div>
					
					<h4><?php esc_html_e( 'Current Enrollments', 'bhfe-groups' ); ?></h4>
					<table class="woocommerce-table shop_table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'User', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Course', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Price', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Enrolled', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bhfe-groups' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $enrollments ) ) : ?>
								<tr>
									<td colspan="6"><?php esc_html_e( 'No enrollments yet.', 'bhfe-groups' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $enrollments as $enroll ) : ?>
									<tr data-enrollment-id="<?php echo esc_attr( $enroll->id ); ?>">
										<td><?php echo esc_html( $enroll->display_name ); ?></td>
										<td><?php echo esc_html( $enroll->course_title ? $enroll->course_title : 'Course #' . $enroll->course_id ); ?></td>
										<td><?php echo wc_price( $enrollment->get_course_price( $enroll->course_id ) ); ?></td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $enroll->enrolled_at ) ) ); ?></td>
										<td>
											<span class="status-<?php echo esc_attr( $enroll->status ); ?>">
												<?php echo esc_html( ucfirst( $enroll->status ) ); ?>
											</span>
										</td>
										<td>
											<?php if ( $enroll->status === 'active' && ! $enroll->order_id ) : ?>
												<button type="button" class="button-link bhfe-unenroll" 
													data-user-id="<?php echo esc_attr( $enroll->user_id ); ?>"
													data-course-id="<?php echo esc_attr( $enroll->course_id ); ?>">
													<?php esc_html_e( 'Unenroll', 'bhfe-groups' ); ?>
												</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					
					<?php if ( $pending_total > 0 ) : ?>
						<div class="bhfe-pending-total">
							<h4><?php esc_html_e( 'Pending Invoice Total', 'bhfe-groups' ); ?></h4>
							<p class="total-amount"><?php echo wc_price( $pending_total ); ?></p>
							<a href="<?php echo esc_url( home_url( '/my-account/group-checkout/?group_id=' . $selected_group_id ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Checkout on Site', 'bhfe-groups' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
				
				<!-- Invoices Tab -->
				<div class="tab-content" id="tab-invoices">
					<h4><?php esc_html_e( 'Invoices', 'bhfe-groups' ); ?></h4>
					<?php
					$invoices = $invoice->get_group_invoices( $selected_group_id );
					?>
					<table class="woocommerce-table shop_table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Invoice #', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Date', 'bhfe-groups' ); ?></th>
								<th><?php esc_html_e( 'Order', 'bhfe-groups' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $invoices ) ) : ?>
								<tr>
									<td colspan="5"><?php esc_html_e( 'No invoices yet.', 'bhfe-groups' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $invoices as $inv ) : ?>
									<tr>
										<td>#<?php echo esc_html( $inv->id ); ?></td>
										<td><?php echo wc_price( $inv->total_amount ); ?></td>
										<td>
											<span class="status-<?php echo esc_attr( $inv->status ); ?>">
												<?php echo esc_html( ucfirst( $inv->status ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $inv->invoice_date ) ) ); ?></td>
										<td>
											<?php if ( $inv->order_id ) : ?>
												<a href="<?php echo esc_url( wc_get_endpoint_url( 'view-order', $inv->order_id ) ); ?>">
													<?php esc_html_e( 'View Order', 'bhfe-groups' ); ?>
												</a>
											<?php else : ?>
												—
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="bhfe-no-group-selected">
					<p><?php esc_html_e( 'Select a group from the sidebar or create a new one to get started.', 'bhfe-groups' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Create Group Modal -->
<div id="bhfe-create-group-modal" style="display: none;">
	<div class="bhfe-modal-content">
		<h3><?php esc_html_e( 'Create New Group', 'bhfe-groups' ); ?></h3>
		<form id="bhfe-create-group-form">
			<p>
				<label for="group-name"><?php esc_html_e( 'Group Name', 'bhfe-groups' ); ?></label>
				<input type="text" id="group-name" name="name" class="input-text" required />
			</p>
			<p class="form-row">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Group', 'bhfe-groups' ); ?></button>
				<button type="button" class="button bhfe-cancel-modal"><?php esc_html_e( 'Cancel', 'bhfe-groups' ); ?></button>
			</p>
		</form>
	</div>
</div>

