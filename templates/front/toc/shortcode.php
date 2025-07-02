<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<div class="nuclen-root" data-theme="<?php echo esc_attr( $theme ); ?>">
	<section id="<?php echo esc_attr( $nav_id ); ?>-wrapper" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $sticky_attrs; ?>>
	<?php if ( $has_sticky ) : ?>
	<div class="nuclen-toc-content">
	<?php endif; ?>
	<?php echo $toggle_button; ?>
	<?php echo $nav_markup; ?>
	<?php if ( $has_sticky ) : ?>
	</div>
	<?php endif; ?>
	</section>
	</div>
