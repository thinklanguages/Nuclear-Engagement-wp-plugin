<?php
/**
 * nuclen-tasks-page.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
// File: templates/admin/nuclen-tasks-page.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extract data
$generation_tasks = $data['generation_tasks'] ?? array();
$credits          = $data['credits'] ?? array();
$cron_status      = $data['cron_status'] ?? array();
$pagination       = $data['pagination'] ?? array();
?>
<div class="wrap">
	<?php require NUCLEN_PLUGIN_DIR . 'templates/admin/page-header.php'; ?>
	
	<h1><?php esc_html_e( 'Content Generation Tasks', 'nuclear-engagement' ); ?></h1>
	
	<div class="nuclen-dashboard-container">
		<!-- Credit Balance Section -->
		<div class="nuclen-card">
			<?php
			// Use the modular credit balance component
			require NUCLEN_PLUGIN_DIR . 'templates/admin/components/credit-balance.php';
			?>
		</div>

		<!-- Circuit Breaker Status -->
		<?php 
		$cb_status = $data['circuit_breaker_status'] ?? array();
		if ( ! empty( $cb_status['is_open'] ) ) : 
		?>
		<div class="nuclen-card" style="border-left: 4px solid #d63638;">
			<h3 style="color: #d63638;"><?php esc_html_e( 'API Circuit Breaker Open', 'nuclear-engagement' ); ?></h3>
			<p>
				<?php 
				printf(
					esc_html__( 'The API circuit breaker is currently open due to %d consecutive failures. API calls are temporarily blocked to prevent system overload.', 'nuclear-engagement' ),
					$cb_status['failures']
				);
				?>
			</p>
			<p>
				<?php 
				if ( $cb_status['time_until_retry'] > 0 ) {
					printf(
						esc_html__( 'Automatic retry in %d seconds.', 'nuclear-engagement' ),
						$cb_status['time_until_retry']
					);
				}
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'reset_circuit_breaker' ), 'nuclen_task_action' ) ); ?>" 
				   class="button button-primary"
				   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to reset the circuit breaker? This will allow API calls to proceed immediately.', 'nuclear-engagement' ); ?>');">
					<?php esc_html_e( 'Reset Circuit Breaker', 'nuclear-engagement' ); ?>
				</a>
			</p>
		</div>
		<?php endif; ?>

		<!-- Generation Tasks Section -->
		<div class="nuclen-card">
			<h2><?php esc_html_e( 'Generation Tasks', 'nuclear-engagement' ); ?></h2>
			<p style="margin-top: 5px; margin-bottom: 15px; color: #666;">
				<?php esc_html_e( 'Here you can manage the content generation tasks. Allow for 5-30 minutes per task to complete depending on the number of posts, site memory and cron schedule. Click the Refresh button to see the latest status.', 'nuclear-engagement' ); ?>
			</p>
			<div style="margin-bottom: 15px;">
				<a href="<?php echo esc_url( add_query_arg( 'refresh', '1' ) ); ?>" class="button button-small">
					<span class="dashicons dashicons-update" style="vertical-align: text-bottom;"></span>
					<?php esc_html_e( 'Refresh', 'nuclear-engagement' ); ?>
				</a>
			</div>
			<div class="nuclen-dashboard-content">
				<?php if ( empty( $generation_tasks ) ) : ?>
					<p><?php esc_html_e( 'No generation tasks.', 'nuclear-engagement' ); ?></p>
				<?php else : ?>
					<table class="nuclen-stats-table nuclen-tasks-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Created At', 'nuclear-engagement' ); ?></th>
								<th><?php esc_html_e( 'Scheduled At', 'nuclear-engagement' ); ?></th>
								<th><?php esc_html_e( 'Action', 'nuclear-engagement' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nuclear-engagement' ); ?></th>
								<th><?php esc_html_e( 'Status', 'nuclear-engagement' ); ?></th>
								<th><?php esc_html_e( 'Progress', 'nuclear-engagement' ); ?></th>
								<th><?php esc_html_e( 'Details', 'nuclear-engagement' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nuclear-engagement' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $generation_tasks as $task ) : ?>
								<tr data-task-id="<?php echo esc_attr( $task['id'] ); ?>" data-status="<?php echo esc_attr( $task['status'] ); ?>">
									<td>
										<?php
										if ( ! empty( $task['created_at'] ) && $task['created_at'] > 0 ) {
											$date_format = get_option( 'date_format' );
											$time_format = get_option( 'time_format' );
											echo esc_html( date_i18n( $date_format . ' ' . $time_format, $task['created_at'] ) );
										} else {
											echo '—';
										}
										?>
									</td>
									<td>
										<?php
										if ( ! empty( $task['scheduled_at'] ) && $task['scheduled_at'] > 0 ) {
											$date_format = get_option( 'date_format' );
											$time_format = get_option( 'time_format' );
											echo esc_html( date_i18n( $date_format . ' ' . $time_format, $task['scheduled_at'] ) );
										} else {
											echo '';
										}
										?>
									</td>
									<td>
										<?php
										$action        = $task['action'] ?? '';
										$action_labels = array(
											'single' => __( 'Single post', 'nuclear-engagement' ),
											'bulk'   => __( 'Bulk', 'nuclear-engagement' ),
											'manual' => __( 'Bulk', 'nuclear-engagement' ),  // 'manual' is displayed as 'Bulk'
											'auto'   => __( 'Auto on publish', 'nuclear-engagement' ),
										);
										echo esc_html( $action_labels[ $action ] ?? '' );
										?>
									</td>
									<td><?php echo esc_html( ucfirst( $task['workflow_type'] ?? 'unknown' ) ); ?></td>
									<td class="column-status">
										<?php
										$status_labels = array(
											'pending'    => __( 'Pending', 'nuclear-engagement' ),
											'scheduled'  => __( 'Scheduled', 'nuclear-engagement' ),
											'processing' => __( 'Processing', 'nuclear-engagement' ),
											'completed'  => __( 'Completed', 'nuclear-engagement' ),
											'completed_with_errors' => __( 'Completed with Errors', 'nuclear-engagement' ),
											'failed'     => __( 'Failed', 'nuclear-engagement' ),
											'cancelled'  => __( 'Cancelled', 'nuclear-engagement' ),
										);
										$status_class  = '';
										switch ( $task['status'] ) {
											case 'completed':
												$status_class = 'nuclen-badge-success';
												break;
											case 'scheduled':
												$status_class = 'nuclen-badge-warning';
												break;
											case 'processing':
												$status_class = 'nuclen-badge-info';
												break;
											case 'pending':
												$status_class = 'nuclen-badge-warning';
												break;
											case 'failed':
												$status_class = 'nuclen-badge-error';
												break;
											case 'cancelled':
											default:
												$status_class = 'nuclen-badge-default';
										}
										echo '<span class="nuclen-badge ' . esc_attr( $status_class ) . '">';
										echo esc_html( $status_labels[ $task['status'] ] ?? ucfirst( $task['status'] ) );
										echo '</span>';
										?>
									</td>
									<td class="column-progress">
										<div class="nuclen-progress-container">
											<div class="nuclen-progress-bar">
												<div class="nuclen-progress-fill" style="width: <?php echo esc_attr( $task['progress'] ); ?>%"></div>
											</div>
											<span class="nuclen-progress-text"><?php echo esc_html( $task['progress'] ); ?>%</span>
										</div>
									</td>
									<td>
										<?php echo esc_html( $task['details'] ); ?>
										<?php if ( $task['failed'] > 0 ) : ?>
											<br><span class="nuclen-error-text">
												<?php echo esc_html( sprintf( __( '%d failed', 'nuclear-engagement' ), $task['failed'] ) ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td class="nuclen-task-actions column-actions">
										<?php if ( $task['status'] === 'pending' || $task['status'] === 'scheduled' ) : ?>
											<button type="button" 
													class="button button-small nuclen-run-now" 
													data-task-id="<?php echo esc_attr( $task['id'] ); ?>"
													title="<?php esc_attr_e( 'Run this task immediately', 'nuclear-engagement' ); ?>">
												<?php esc_html_e( 'Run Now', 'nuclear-engagement' ); ?>
											</button>
											<button type="button" 
													class="button button-small nuclen-cancel" 
													data-task-id="<?php echo esc_attr( $task['id'] ); ?>"
													title="<?php esc_attr_e( 'Cancel this task', 'nuclear-engagement' ); ?>">
												<?php esc_html_e( 'Cancel', 'nuclear-engagement' ); ?>
											</button>
										<?php elseif ( $task['status'] === 'processing' ) : ?>
											<span class="spinner is-active"></span>
											<button type="button" 
													class="button button-small nuclen-cancel" 
													data-task-id="<?php echo esc_attr( $task['id'] ); ?>"
													title="<?php esc_attr_e( 'Cancel this task', 'nuclear-engagement' ); ?>">
												<?php esc_html_e( 'Cancel', 'nuclear-engagement' ); ?>
											</button>
										<?php elseif ( $task['status'] === 'failed' || $task['status'] === 'cancelled' ) : ?>
											<button type="button" 
													class="button button-small nuclen-retry" 
													data-task-id="<?php echo esc_attr( $task['id'] ); ?>"
													title="<?php esc_attr_e( 'Retry this task', 'nuclear-engagement' ); ?>">
												<?php esc_html_e( 'Retry', 'nuclear-engagement' ); ?>
											</button>
										<?php else : ?>
											<span class="nuclen-no-actions">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					
					<?php if ( ! empty( $pagination ) && $pagination['total_pages'] > 1 ) : ?>
						<div class="tablenav bottom">
							<div class="tablenav-pages">
								<span class="displaying-num">
									<?php
									echo esc_html(
										sprintf(
											_n( '%s item', '%s items', $pagination['total_items'], 'nuclear-engagement' ),
											number_format_i18n( $pagination['total_items'] )
										)
									);
									?>
								</span>
								<?php
								$base_url   = remove_query_arg( array( 'paged', 'action', 'task_id', '_wpnonce' ) );
								$page_links = paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%', $base_url ),
										'format'    => '',
										'prev_text' => __( '&laquo;', 'nuclear-engagement' ),
										'next_text' => __( '&raquo;', 'nuclear-engagement' ),
										'total'     => $pagination['total_pages'],
										'current'   => $pagination['current_page'],
										'type'      => 'plain',
										'add_args'  => false,
									)
								);

								if ( $page_links ) {
									echo '<span class="pagination-links">' . $page_links . '</span>';
								}
								?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Cron Status -->
		<?php if ( $cron_status['enabled'] && $cron_status['next_run'] ) : ?>
			<div class="nuclen-card">
				<h3><?php esc_html_e( 'System Status', 'nuclear-engagement' ); ?></h3>
				<p>
					<?php
					echo esc_html(
						sprintf(
							__( 'Next cron run: %s', 'nuclear-engagement' ),
							date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cron_status['next_run'] )
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>
