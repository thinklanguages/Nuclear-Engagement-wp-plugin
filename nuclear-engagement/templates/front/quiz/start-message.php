<?php
/**
 * start-message.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="nuclen-quiz-start-message">
<?php
// Security fix: Properly sanitize HTML content to prevent XSS attacks.
// Use wp_kses_post() to allow safe HTML tags while stripping malicious content.
echo wp_kses_post( shortcode_unautop( $html_before ) );
?>
</div>
