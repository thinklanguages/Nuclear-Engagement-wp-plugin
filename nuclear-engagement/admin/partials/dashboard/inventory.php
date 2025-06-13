<?php
// File: admin/partials/dashboard/inventory.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
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
