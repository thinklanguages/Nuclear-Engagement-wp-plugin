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
	*   - $msg (string) Notice message.
	*
	* @package NuclearEngagement\Admin
	*/
?>
<div class="notice notice-error"><p><?php echo esc_html( $msg ); ?></p></div>

