<?php
declare(strict_types=1);
// File: admin/partials/settings/placement.php
if ( ! defined( 'ABSPATH' ) ) {
		exit;
}
/**
 * Placement tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- PLACEMENT TAB -->
<div id="placement" class="nuclen-tab-content nuclen-section" style="display:block;">

		<h2 class="nuclen-subheading"><?php esc_html_e( 'Placement', 'nuclear-engagement' ); ?></h2>
		<p>
				<?php esc_html_e( 'Choose how and where to display quizzes, summaries and the Table of Contents.', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'Shortcodes are the most versatile method. If your theme or page-builder lacks suitable slots you can append sections automatically.', 'nuclear-engagement' ); ?>">🛈</span>
		</p>

		<?php
				$placement_dir = plugin_dir_path( __FILE__ ) . 'placement/';
				require $placement_dir . 'positions.php';
				require $placement_dir . 'sticky-toc.php';
		?>

</div><!-- /#placement -->
