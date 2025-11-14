<?php
/**
 * Frontend template: Group checkout summary for managers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="bhfe-group-checkout-summary">
	<h2><?php esc_html_e( 'Group Checkout', 'bhfe-groups' ); ?></h2>
	
	<div class="bhfe-group-checkout-intro woocommerce-info">
		<p>
			<?php printf(
				/* translators: %s: group name */
				esc_html__( 'You are viewing unpaid enrollments for %s.', 'bhfe-groups' ),
				'<strong>' . esc_html( $group->name ) . '</strong>'
			); ?>
		</p>
		<p>
			<?php esc_html_e( 'Review the outstanding enrollments below. When you click Proceed to Checkout, the courses will be added to your cart and the total will be set to $0 so that you can finalize the order on behalf of the group.', 'bhfe-groups' ); ?>
		</p>
	</div>
	
	<?php if ( empty( $pending_enrollments ) ) : ?>
		<div class="woocommerce-message">
			<p><?php esc_html_e( 'Great news! There are no pending enrollments for this group.', 'bhfe-groups' ); ?></p>
		</div>
	<?php else : ?>
		<table class="woocommerce-table shop_table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'User', 'bhfe-groups' ); ?></th>
					<th><?php esc_html_e( 'Course', 'bhfe-groups' ); ?></th>
					<th><?php esc_html_e( 'Price', 'bhfe-groups' ); ?></th>
					<th><?php esc_html_e( 'Enrolled', 'bhfe-groups' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bhfe-groups' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending_enrollments as $pending ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $pending->display_name ); ?></strong><br>
							<span class="description"><?php echo esc_html( $pending->user_email ); ?></span>
						</td>
						<td>
							<?php echo esc_html( $pending->course_title ? $pending->course_title : 'Course #' . $pending->course_id ); ?>
						</td>
						<td><?php echo wc_price( $enrollment->get_course_price( $pending->course_id ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $pending->enrolled_at ) ) ); ?></td>
						<td><span class="status-<?php echo esc_attr( $pending->status ); ?>"><?php echo esc_html( ucfirst( $pending->status ) ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<div class="bhfe-group-checkout-total woocommerce-info" style="margin-top: 25px;">
			<h3><?php esc_html_e( 'Pending Invoice Total', 'bhfe-groups' ); ?></h3>
			<p class="amount" style="font-size: 26px; font-weight: bold;"><?php echo wc_price( $pending_total ); ?></p>
			<p class="description"><?php esc_html_e( 'This total will be billed to the group once you complete the checkout.', 'bhfe-groups' ); ?></p>
		</div>
		
		<div class="bhfe-group-checkout-errors" style="display:none;margin-bottom:15px;color:#d63638;"></div>
		
		<div class="bhfe-group-checkout-actions">
			<a class="button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'groups' ) . '?group_id=' . $group_id ); ?>">
				<?php esc_html_e( 'Back to Group Management', 'bhfe-groups' ); ?>
			</a>
			<button type="button"
				class="button button-primary"
				id="bhfe-group-checkout-proceed"
				data-group-id="<?php echo esc_attr( $group_id ); ?>">
				<?php esc_html_e( 'Proceed to Checkout', 'bhfe-groups' ); ?>
			</button>
		</div>
	<?php endif; ?>
</div>

