<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * admin/partials/nuclen-dashboard-page.php
 *
 * This file contains the HTML markup for the Nuclear Engagement Admin Dashboard.
 * We'll fix the “No credits info returned.” by referencing `data.data.remaining_credits`.
 */

use NuclearEngagement\SettingsRepository;

// Fetch plugin setup info to decide if we show credits
$settings = SettingsRepository::get_instance();
$fully_setup = ( $settings->get_bool( 'connected', false ) && $settings->get_bool( 'wp_app_pass_created', false ) );

$utils = new \NuclearEngagement\Utils();
$utils->display_nuclen_page_header();
?>
<div class="wrap nuclen-container">
	<h1 class="nuclen-heading"><?php esc_html_e( 'Dashboard', 'nuclear-engagement' ); ?></h1>
	<!-- Post inventory -->
	<h2><?php esc_html_e( 'Post Inventory', 'nuclear-engagement' ); ?></h2>
	<!-- Post inventory Navigation Tabs -->
	<div class="nav-tab-wrapper">
		<a href="#post-status" id="post-status-tab" class="nav-tab nav-tab-active"><?php esc_html_e( 'Post Status', 'nuclear-engagement' ); ?></a>
		<a href="#category" class="nav-tab"><?php esc_html_e( 'Categories', 'nuclear-engagement' ); ?></a>
		<a href="#author" class="nav-tab"><?php esc_html_e( 'Authors', 'nuclear-engagement' ); ?></a>
		<a href="#post-type" id="post-type-tab" class="nav-tab"><?php esc_html_e( 'Post Types', 'nuclear-engagement' ); ?></a>
	</div>
	<!-- Post inventory Main Content -->
	<div class="nuclen-dashboard-content">

		<!-- POST STATUS TAB -->
		<div id="post-status" class="nuclen-tab-content nuclen-section" style="display:block; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Post Status', 'nuclear-engagement' ); ?></h2>
			
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_status_quiz ); // phpcs:ignore ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_status_summary ); // phpcs:ignore ?>
				</div>
			</div>
		</div>

		<!-- CATEGORY TAB -->
		<div id="category" class="nuclen-tab-content nuclen-section" style="display:none; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Categories', 'nuclear-engagement' ); ?></h2>
			
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_category_quiz ); // phpcs:ignore ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_category_summary ); // phpcs:ignore ?>
				</div>
			</div>
		</div>

		<!-- AUTHOR TAB -->
		<div id="author" class="nuclen-tab-content nuclen-section" style="display:none; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Authors', 'nuclear-engagement' ); ?></h2>
			
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_author_quiz ); // phpcs:ignore ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_author_summary ); // phpcs:ignore ?>
				</div>
			</div>
		</div>

		<!-- POST TYPE TAB -->
		<div id="post-type" class="nuclen-tab-content nuclen-section" style="display:none; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Post Types', 'nuclear-engagement' ); ?></h2>
			
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_post_type_quiz ); // phpcs:ignore ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_post_type_summary ); // phpcs:ignore ?>
				</div>
			</div>
		</div>
	</div><!-- .nuclen-dashboard-content -->

	<!-- Analytics -->
	<h2><?php esc_html_e( 'Analytics', 'nuclear-engagement' ); ?></h2>
	<p>
		<?php
		printf(
			wp_kses(
				/* translators: %s is a link */
				__( 'Engagement analytics are available on the Nuclear Engagement web app (create a free account %s).', 'nuclear-engagement' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				)
			),
			'<a href="https://app.nuclearengagement.com/signup" target="_blank">' . esc_html__( 'here', 'nuclear-engagement' ) . '</a>'
		);
		?>
	</p>
	<button class="button button-secondary" onclick="window.open('https://app.nuclearengagement.com/sites', '_blank');">
		<?php esc_html_e( 'View Analytics', 'nuclear-engagement' ); ?>
	</button>

	<?php if ( $fully_setup ) : // Only show credits if plugin setup is complete ?>
		<!-- Show the user’s current credits -->
		<h2 style="margin-top:30px;"><?php esc_html_e( 'Your Credits', 'nuclear-engagement' ); ?></h2>
		<p id="nuclen-credits-dashboard-msg"><?php esc_html_e( 'Loading your credits...', 'nuclear-engagement' ); ?></p>

		<script>
		document.addEventListener('DOMContentLoaded', async () => {
		  const msgEl = document.getElementById('nuclen-credits-dashboard-msg');
		  if (!msgEl) return;

		  try {
		    // We'll reuse the same "nuclen_fetch_app_updates" action with no generation_id
		    const formData = new FormData();
		    formData.append('action', 'nuclen_fetch_app_updates');
		    formData.append('security', '<?php echo esc_js( wp_create_nonce("nuclen_admin_ajax_nonce") ); ?>');

		    // We do not append generation_id => let the SaaS interpret it as "just return credits"
		    const resp = await fetch("<?php echo esc_url( admin_url('admin-ajax.php') ); ?>", {
		      method: 'POST',
		      body: formData
		    });

		    const data = await resp.json();
		    if (!data.success) {
		      throw new Error(data.data?.message || 'Failed to fetch credits');
		    }
		    // "wp_send_json_success($stuff)" => { success:true, data:$stuff }
		    const remoteData = data.data;
		    if (remoteData && typeof remoteData.remaining_credits !== 'undefined') {
		      msgEl.textContent = '<?php echo esc_js( __("You have", "nuclear-engagement") ); ?> ' 
		        + remoteData.remaining_credits 
		        + ' <?php echo esc_js( __("credits left.", "nuclear-engagement") ); ?>';
		    } else {
		      msgEl.textContent = '<?php echo esc_js( __("No credits info returned.", "nuclear-engagement") ); ?>';
		    }
		  } catch (err) {
		    msgEl.textContent = 'Error: ' + err;
		  }
		});
		</script>
	<?php else : ?>
		<!-- Credits hidden: user has not completed plugin setup -->
	<?php endif; ?>
</div><!-- .wrap .nuclen-container -->
