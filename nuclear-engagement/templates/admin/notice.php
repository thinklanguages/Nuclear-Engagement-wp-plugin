<?php
/**
 * notice.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
	* Single admin notice.
	*
	* Variables:
	*   - $args['msg'] (string) Notice message.
	*
	* @package NuclearEngagement\Admin
	*/

// Extract the message from $args array
$msg = isset( $args['msg'] ) ? $args['msg'] : '';
?>
<div class="notice notice-error"><p><strong>[NUCLEAR ENGAGEMENT]</strong> <?php echo esc_html( $msg ); ?></p></div>

