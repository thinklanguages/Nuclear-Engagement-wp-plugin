<?php
/**
 * scheduled.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
// File: admin/partials/dashboard/scheduled.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Generation Tasks', 'nuclear-engagement' ); ?></h2>
<div class="nuclen-dashboard-content">
<?php if ( empty( $generation_tasks ) ) : ?>
	<p><?php esc_html_e( 'No generation tasks.', 'nuclear-engagement' ); ?></p>
<?php else : ?>
	<table class="nuclen-stats-table">
		<tr>
			<th><?php esc_html_e( 'Created At', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Content', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Type', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Status', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Progress', 'nuclear-engagement' ); ?></th>
			<th><?php esc_html_e( 'Details', 'nuclear-engagement' ); ?></th>
		</tr>
		<?php foreach ( $generation_tasks as $t ) : ?>
		<tr>
			<td>
				<?php
				if ( ! empty( $t['created_at'] ) && $t['created_at'] > 0 ) {
					$date_format = get_option( 'date_format' );
					$time_format = get_option( 'time_format' );
					echo esc_html( date_i18n( $date_format . ' ' . $time_format, $t['created_at'] ) );
				} else {
					echo '—';
				}
				?>
			</td>
			<td><?php echo esc_html( $t['post_title'] ); ?></td>
			<td><?php echo esc_html( ucfirst( $t['workflow_type'] ) ); ?></td>
			<td>
				<?php
				$status_labels = array(
					'queued'    => __( 'Queued', 'nuclear-engagement' ),
					'active'    => __( 'Processing', 'nuclear-engagement' ),
					'completed' => __( 'Completed', 'nuclear-engagement' ),
					'failed'    => __( 'Failed', 'nuclear-engagement' ),
					'retry'     => __( 'Retrying', 'nuclear-engagement' ),
				);
				echo esc_html( $status_labels[ $t['status'] ] ?? ucfirst( $t['status'] ) );
				?>
			</td>
			<td>
				<?php
				if ( isset( $t['progress'] ) ) {
					echo esc_html( $t['progress'] );
				} else {
					// Calculate progress based on status
					switch ( $t['status'] ) {
						case 'completed':
							echo '100%';
							break;
						case 'failed':
							echo '—';
							break;
						case 'queued':
							echo '0%';
							break;
						case 'active':
						case 'retry':
							echo '—';
							break;
						default:
							echo '—';
					}
				}
				?>
			</td>
			<td>
				<?php
				if ( ! empty( $t['details'] ) ) {
					echo esc_html( $t['details'] );
				} else {
					echo '—';
				}
				?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
</div>
