<?php
/**
 * shortcode.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<div class="nuclen-root" data-theme="<?php echo esc_attr( $theme ); ?>">
	<section id="<?php echo esc_attr( $nav_id ); ?>-wrapper" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo wp_kses_post( $sticky_attrs ); ?>>
	<?php if ( $has_sticky ) : ?>
	<div class="nuclen-toc-content">
	<?php endif; ?>
	<?php echo wp_kses_post( $toggle_button ); ?>
	<?php echo wp_kses_post( $nav_markup ); ?>
	<?php if ( $has_sticky ) : ?>
	</div>
	<?php endif; ?>
	</section>
	</div>
