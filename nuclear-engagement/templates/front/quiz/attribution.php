<?php
/**
 * attribution.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( $show ) : ?>
<div class="nuclen-attribution"><?php echo esc_html__( 'Quiz by', 'nuclear-engagement' ); ?> <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank"><?php echo esc_html__( 'Nuclear Engagement', 'nuclear-engagement' ); ?></a></div>
<?php endif; ?>
