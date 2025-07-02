<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_nonce_field( 'nuclen_quiz_data_nonce', 'nuclen_quiz_data_nonce' );
?>
<div><label>
	<input type="checkbox" name="nuclen_quiz_protected" value="1" <?php checked( $quiz_protected, 1 ); ?> />
	<?php esc_html_e( 'Protected?', 'nuclear-engagement' ); ?>
	<span nuclen-tooltip="<?php esc_attr_e( 'Tick this box and save post to prevent overwriting during bulk generation.', 'nuclear-engagement' ); ?>">🛈</span>
</label></div>
<div>
	<button type="button"
			id="nuclen-generate-quiz-single"
			class="button nuclen-generate-single"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			data-workflow="quiz">
		<?php esc_html_e( 'Generate Quiz with AI', 'nuclear-engagement' ); ?>
	</button>
	<span nuclen-tooltip="<?php esc_attr_e( '(re)Generate. Data will be stored automatically (no need to save post).', 'nuclear-engagement' ); ?>">🛈</span>
</div>
<p><strong><?php esc_html_e( 'Date', 'nuclear-engagement' ); ?></strong><br>
	<input type="text" name="nuclen_quiz_data[date]" value="<?php echo esc_attr( $date ); ?>" readonly class="nuclen-meta-date-input" />
</p>
<?php
for ( $q_index = 0; $q_index < 10; $q_index++ ) :
	$q_data  = $questions[ $q_index ];
	$q_text  = $q_data['question'] ?? '';
	$answers = isset( $q_data['answers'] ) && is_array( $q_data['answers'] )
		? $q_data['answers']
		: array( '', '', '', '' );
	$explan  = $q_data['explanation'] ?? '';
	$answers = array_pad( $answers, 4, '' );
	?>
	<div class="nuclen-quiz-metabox-question">
		<h4><?php printf( esc_html__( 'Question %d', 'nuclear-engagement' ), $q_index + 1 ); ?></h4>
		<input type="text" name="nuclen_quiz_data[questions][<?php echo esc_attr( $q_index ); ?>][question]" value="<?php echo esc_attr( $q_text ); ?>" class="nuclen-width-full" />
		<p><strong><?php esc_html_e( 'Answers', 'nuclear-engagement' ); ?></strong></p>
		<?php
		foreach ( $answers as $a_index => $answer ) :
			$class = $a_index === 0 ? 'nuclen-answer-correct' : '';
			?>
			<p class="nuclen-answer-label <?php echo esc_attr( $class ); ?>"><?php printf( esc_html__( 'Answer %d', 'nuclear-engagement' ), $a_index + 1 ); ?><br>
				<input type="text" name="nuclen_quiz_data[questions][<?php echo esc_attr( $q_index ); ?>][answers][<?php echo esc_attr( $a_index ); ?>]" value="<?php echo esc_attr( $answer ); ?>" class="nuclen-width-full" />
			</p>
		<?php endforeach; ?>
		<p><strong><?php esc_html_e( 'Explanation', 'nuclear-engagement' ); ?></strong><br>
			<textarea name="nuclen_quiz_data[questions][<?php echo esc_attr( $q_index ); ?>][explanation]" rows="3" class="nuclen-width-full"><?php echo esc_textarea( $explan ); ?></textarea>
		</p>
	</div>
<?php endfor; ?>
