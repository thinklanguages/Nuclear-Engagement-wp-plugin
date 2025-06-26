<?php
declare(strict_types=1);
// File: admin/partials/dashboard/scheduled.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Scheduled Generations', 'nuclear-engagement' ); ?></h2>
<div class="nuclen-dashboard-content">
<?php if ( empty( $scheduled_tasks ) ) : ?>
	<p><?php esc_html_e( 'No scheduled generation tasks.', 'nuclear-engagement' ); ?></p>
<?php else : ?>
	<table class="nuclen-stats-table">
		<tr>
			<th><?php esc_html_e( 'Post', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Type', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Attempt', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Next Check', 'nuclear-engagement' ); ?></th>
		</tr>
		<?php foreach ( $scheduled_tasks as $t ) : ?>
		<tr>
			<td><?php echo esc_html( $t['post_title'] ); ?></td>
			<td><?php echo esc_html( ucfirst( $t['workflow_type'] ) ); ?></td>
			<td><?php echo esc_html( $t['attempt'] ); ?></td>
			<td><?php echo esc_html( $t['next_poll'] ); ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
</div>
