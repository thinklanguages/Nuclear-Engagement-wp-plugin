<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_nonce_field( 'nuclen_quiz_data_nonce', 'nuclen_quiz_data_nonce' );
?>
<div><label>
    <input type="checkbox" name="nuclen_quiz_protected" value="1" <?php checked( $quiz_protected, 1 ); ?> />
    Protected? <span nuclen-tooltip="Tick this box and save post to prevent overwriting during bulk generation.">🛈</span>
</label></div>
<div>
    <button type="button"
            id="nuclen-generate-quiz-single"
            class="button nuclen-generate-single"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-workflow="quiz">
        Generate Quiz with AI
    </button>
    <span nuclen-tooltip="(re)Generate. Data will be stored automatically (no need to save post).">🛈</span>
</div>
<p><strong>Date</strong><br>
    <input type="text" name="nuclen_quiz_data[date]" value="<?php echo esc_attr( $date ); ?>" readonly class="nuclen-meta-date-input" />
</p>
<?php for ( $q_index = 0; $q_index < 10; $q_index++ ) :
    $q_data  = $questions[ $q_index ];
    $q_text  = $q_data['question'] ?? '';
    $answers = isset( $q_data['answers'] ) && is_array( $q_data['answers'] )
        ? $q_data['answers']
        : array( '', '', '', '' );
    $explan  = $q_data['explanation'] ?? '';
    $answers = array_pad( $answers, 4, '' );
    ?>
    <div class="nuclen-quiz-metabox-question">
        <h4><?php echo esc_html( 'Question ' . ( $q_index + 1 ) ); ?></h4>
        <input type="text" name="nuclen_quiz_data[questions][<?php echo esc_attr( $q_index ); ?>][question]" value="<?php echo esc_attr( $q_text ); ?>" class="nuclen-width-full" />
        <p><strong>Answers</strong></p>
        <?php foreach ( $answers as $a_index => $answer ) :
            $class = $a_index === 0 ? 'nuclen-answer-correct' : '';
            ?>
            <p class="nuclen-answer-label <?php echo esc_attr( $class ); ?>">Answer <?php echo esc_html( $a_index + 1 ); ?><br>
                <input type="text" name="nuclen_quiz_data[questions][<?php echo esc_attr( $q_index ); ?>][answers][<?php echo esc_attr( $a_index ); ?>]" value="<?php echo esc_attr( $answer ); ?>" class="nuclen-width-full" />
            </p>
        <?php endforeach; ?>
        <p><strong>Explanation</strong><br>
            <textarea name="nuclen_quiz_data[questions][<?php echo esc_attr( $q_index ); ?>][explanation]" rows="3" class="nuclen-width-full"><?php echo esc_textarea( $explan ); ?></textarea>
        </p>
    </div>
<?php endfor; ?>
