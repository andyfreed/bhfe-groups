<?php
/**
 * Admin template for Groups management page
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

<div class="wrap bhfe-groups-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="bhfe-groups-container">
		<!-- Groups List -->
		<div class="bhfe-groups-sidebar">
			<h2><?php esc_html_e( 'Your Groups', 'bhfe-groups' ); ?></h2>
			
			<button type="button" class="button button-primary" id="bhfe-create-group-btn">
				<?php esc_html_e( '+ Create New Group', 'bhfe-groups' ); ?>
			</button>
			
			<ul class="bhfe-groups-list">
				<?php if ( empty( $groups ) ) : ?>
					<li class="no-groups"><?php esc_html_e( 'No groups yet. Create your first group!', 'bhfe-groups' ); ?></li>
				<?php else : ?>
					<?php foreach ( $groups as $group ) : ?>
						<li class="<?php echo $selected_group_id == $group->id ? 'active' : ''; ?>">
							<a href="<?php echo esc_url( add_query_arg( 'group_id', $group->id, admin_url( 'admin.php?page=bhfe-groups' ) ) ); ?>">
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
					<h2><?php echo esc_html( $selected_group->name ); ?></h2>
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
					<h3><?php esc_html_e( 'Group Members', 'bhfe-groups' ); ?></h3>
					
					<div class="bhfe-add-member">
						<select id="bhfe-member-select" style="width: 400px;" data-placeholder="<?php esc_attr_e( 'Search for a user...', 'bhfe-groups' ); ?>">
							<option></option>
						</select>
						<button type="button" class="button" id="bhfe-add-member-btn">
							<?php esc_html_e( 'Add Member', 'bhfe-groups' ); ?>
						</button>
					</div>
					
					<table class="wp-list-table widefat fixed striped">
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
					<h3><?php esc_html_e( 'Enroll Users in Courses', 'bhfe-groups' ); ?></h3>
					
					<div class="bhfe-enroll-user">
						<select id="bhfe-enroll-user-select" style="width: 300px;" data-placeholder="<?php esc_attr_e( 'Select a member...', 'bhfe-groups' ); ?>">
							<option></option>
							<?php foreach ( $members as $member ) : ?>
								<option value="<?php echo esc_attr( $member->user_id ); ?>">
									<?php echo esc_html( $member->display_name . ' (' . $member->user_email . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						
						<select id="bhfe-enroll-course-select" style="width: 400px;" data-placeholder="<?php esc_attr_e( 'Search for a course...', 'bhfe-groups' ); ?>">
							<option></option>
						</select>
						
						<button type="button" class="button button-primary" id="bhfe-enroll-btn">
							<?php esc_html_e( 'Enroll', 'bhfe-groups' ); ?>
						</button>
					</div>
					
					<h4><?php esc_html_e( 'Current Enrollments', 'bhfe-groups' ); ?></h4>
					<table class="wp-list-table widefat fixed striped">
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
							<button type="button" class="button button-primary" id="bhfe-create-invoice-btn">
								<?php esc_html_e( 'Create Invoice', 'bhfe-groups' ); ?>
							</button>
							<a href="<?php echo esc_url( home_url( '/my-account/group-checkout/?group_id=' . $selected_group_id ) ); ?>" class="button">
								<?php esc_html_e( 'Checkout on Site', 'bhfe-groups' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
				
				<!-- Invoices Tab -->
				<div class="tab-content" id="tab-invoices">
					<h3><?php esc_html_e( 'Invoices', 'bhfe-groups' ); ?></h3>
					<?php
					$invoices = $invoice->get_group_invoices( $selected_group_id );
					?>
					<table class="wp-list-table widefat fixed striped">
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
												<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $inv->order_id . '&action=edit' ) ); ?>">
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
		<h2><?php esc_html_e( 'Create New Group', 'bhfe-groups' ); ?></h2>
		<form id="bhfe-create-group-form">
			<p>
				<label for="group-name"><?php esc_html_e( 'Group Name', 'bhfe-groups' ); ?></label>
				<input type="text" id="group-name" name="name" class="regular-text" required />
			</p>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Group', 'bhfe-groups' ); ?></button>
				<button type="button" class="button bhfe-cancel-modal"><?php esc_html_e( 'Cancel', 'bhfe-groups' ); ?></button>
			</p>
		</form>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var groupId = <?php echo $selected_group_id ? $selected_group_id : 0; ?>;
	var ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
	var nonce = '<?php echo wp_create_nonce( 'bhfe-groups-nonce' ); ?>';
	
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
		
		// Disable button to prevent double submission
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
					window.location.href = '<?php echo admin_url( 'admin.php?page=bhfe-groups' ); ?>&group_id=' + response.data.group_id;
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error creating group.', 'bhfe-groups' ); ?>');
					$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Create Group', 'bhfe-groups' ); ?>');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', status, error);
				console.error('Response:', xhr.responseText);
				alert('<?php esc_html_e( 'An error occurred. Please check the browser console for details.', 'bhfe-groups' ); ?>');
				$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Create Group', 'bhfe-groups' ); ?>');
			}
		});
	});
	
	// Create invoice
	$('#bhfe-create-invoice-btn').on('click', function() {
		if (!confirm('<?php esc_html_e( 'This will create an invoice for all pending enrollments. Continue?', 'bhfe-groups' ); ?>')) {
			return;
		}
		
		// Redirect to checkout with group info
		window.location.href = '<?php echo esc_url( wc_get_checkout_url() ); ?>';
	});
});
</script>

<style>
.bhfe-groups-admin {
	margin: 20px 0;
}

.bhfe-groups-container {
	display: flex;
	gap: 20px;
}

.bhfe-groups-sidebar {
	width: 250px;
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
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
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
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
</style>

