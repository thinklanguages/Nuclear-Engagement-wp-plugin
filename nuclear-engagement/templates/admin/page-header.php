<?php
/**
 * page-header.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
	* Admin page header template.
	*
	* Variables:
	*   - $image_url (string) URL to the logo image.
	*
	* @package NuclearEngagement\Admin
	*/

$image_url = isset( $image_url ) ? (string) $image_url : '';

// Fallback: Try to construct the URL using plugins_url if image_url is empty.
if ( empty( $image_url ) ) {
	$image_url = plugins_url( 'assets/images/nuclear-engagement-logo.webp', dirname( dirname( __DIR__ ) ) . '/nuclear-engagement.php' );
}
?>
<div id="nuclen-page-header">
	<img height="40" width="40" src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Nuclear Engagement Logo', 'nuclear-engagement' ); ?>" />
	<p><b><?php esc_html_e( 'NUCLEAR ENGAGEMENT', 'nuclear-engagement' ); ?></b></p>
</div>

