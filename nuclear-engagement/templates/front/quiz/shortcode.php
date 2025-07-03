<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nuclen-root" data-theme="<?php echo esc_attr( $theme ); ?>">
<?php echo wp_kses_post( $container ); ?>
<?php echo wp_kses_post( $attribution ); ?>
</div>
