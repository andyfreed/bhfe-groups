<?php
/**
 * Frontend template for standard group members (non managers)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="bhfe-groups-member-view">
	<h2><?php esc_html_e( 'Group Membership', 'bhfe-groups' ); ?></h2>
	
	<?php if ( empty( $user_groups ) ) : ?>
		<div class="woocommerce-message">
			<p><?php esc_html_e( 'You are not currently assigned to any groups.', 'bhfe-groups' ); ?></p>
		</div>
	<?php else : ?>
		<table class="woocommerce-table shop_table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Group', 'bhfe-groups' ); ?></th>
					<th><?php esc_html_e( 'Administrator', 'bhfe-groups' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bhfe-groups' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $user_groups as $member_group ) : ?>
					<tr>
						<td><?php echo esc_html( $member_group->name ); ?></td>
						<td>
							<?php
							$admin_user = get_userdata( $member_group->admin_user_id );
							if ( $admin_user ) {
								echo esc_html( $admin_user->display_name );
								echo '<br><span class="description">' . esc_html( $admin_user->user_email ) . '</span>';
							} else {
								esc_html_e( 'Group Administrator', 'bhfe-groups' );
							}
							?>
						</td>
						<td><?php echo esc_html( ucfirst( $member_group->status ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	
	<div class="woocommerce-info" style="margin-top: 25px;">
		<p><?php esc_html_e( 'Need to add or remove members, or checkout on behalf of your group? Please contact your group administrator.', 'bhfe-groups' ); ?></p>
	</div>
</div>

