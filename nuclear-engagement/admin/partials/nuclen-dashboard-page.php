<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Dashboard partial. 
 * We removed "Your Credits" or Pro references from below.
 */
$utils = new \NuclearEngagement\Utils();
$utils->display_nuclen_page_header();
?>
<div class="wrap nuclen-container">
	<h1 class="nuclen-heading"><?php esc_html_e( 'Dashboard', 'nuclear-engagement' ); ?></h1>

	<h2><?php esc_html_e( 'Post Inventory', 'nuclear-engagement' ); ?></h2>
	<div class="nav-tab-wrapper">
		<a href="#post-status" id="post-status-tab" class="nav-tab nav-tab-active">
			<?php esc_html_e( 'Post Status', 'nuclear-engagement' ); ?>
		</a>
		<a href="#category" class="nav-tab"><?php esc_html_e( 'Categories', 'nuclear-engagement' ); ?></a>
		<a href="#author" class="nav-tab"><?php esc_html_e( 'Authors', 'nuclear-engagement' ); ?></a>
		<a href="#post-type" id="post-type-tab" class="nav-tab"><?php esc_html_e( 'Post Types', 'nuclear-engagement' ); ?></a>
	</div>

	<div class="nuclen-dashboard-content">
		<!-- POST STATUS TAB -->
		<div id="post-status" class="nuclen-tab-content nuclen-section" style="display:block; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Post Status', 'nuclear-engagement' ); ?></h2>
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_status_quiz ); // method from trait-admin-menu ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_status_summary ); ?>
				</div>
			</div>
		</div>

		<!-- CATEGORY TAB -->
		<div id="category" class="nuclen-tab-content nuclen-section" style="display:none; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Categories', 'nuclear-engagement' ); ?></h2>
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_category_quiz ); ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_category_summary ); ?>
				</div>
			</div>
		</div>

		<!-- AUTHOR TAB -->
		<div id="author" class="nuclen-tab-content nuclen-section" style="display:none; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Authors', 'nuclear-engagement' ); ?></h2>
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_author_quiz ); ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_author_summary ); ?>
				</div>
			</div>
		</div>

		<!-- POST TYPE TAB -->
		<div id="post-type" class="nuclen-tab-content nuclen-section" style="display:none; margin-top:20px;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Post Types', 'nuclear-engagement' ); ?></h2>
			<div class="nuclen-row">
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Quizzes', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_post_type_quiz ); ?>
				</div>
				<div class="nuclen-col">
					<h3><?php esc_html_e( 'Summaries', 'nuclear-engagement' ); ?></h3>
					<?php echo $this->nuclen_render_dashboard_stats_table( $by_post_type_summary ); ?>
				</div>
			</div>
		</div>
	</div>

	<h2><?php esc_html_e( 'Analytics', 'nuclear-engagement' ); ?></h2>
	<p>
		<?php
		printf(
			wp_kses(
				__( 'Engagement analytics are available at <a href="https://app.nuclearengagement.com" target="_blank">Nuclear Engagement web app</a>.', 'nuclear-engagement' ),
				[ 'a' => [ 'href' => [], 'target' => [] ] ]
			)
		);
		?>
	</p>
</div>
