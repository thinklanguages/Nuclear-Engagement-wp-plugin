<?php
/**
 * container.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="nuclen-quiz-container" class="nuclen-quiz" data-testid="nuclen-quiz">
<?php echo wp_kses_post( $start_message ); ?>
<?php echo wp_kses_post( $title ); ?>
<?php echo wp_kses_post( $progress_bar ); ?>
<?php echo wp_kses_post( $question_container ); ?>
<?php echo wp_kses_post( $answers_container ); ?>
<?php echo wp_kses_post( $result_container ); ?>
<?php echo wp_kses_post( $explanation_container ); ?>
<?php echo wp_kses_post( $next_button ); ?>
<?php echo wp_kses_post( $final_result_container ); ?>
</section>
