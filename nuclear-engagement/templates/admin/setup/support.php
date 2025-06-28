<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Setup - Support & review links
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- ───── Support ───── -->
<h2><?php esc_html_e( 'Support', 'nuclear-engagement' ); ?></h2>
<p>
	<?php
	printf(
		wp_kses(
			/* translators: 1: link to contact form, 2: mailto link */
			__(
				'To report bugs, suggest features, or just any question or comment, please %1$s or drop Stefano an email at %2$s. I\'m constantly developing this service and will respond within 24 hours.',
				'nuclear-engagement'
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		),
		'<a href="https://www.nuclearengagement.com/contact" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'submit the contact form', 'nuclear-engagement' )
			. '</a>',
		'<a href="mailto:stefano@nuclearengagement.com">stefano@nuclearengagement.com</a>'
	);
	?>
</p>

<?php
		$info = \NuclearEngagement\Services\LoggingService::get_log_file_info();
	$log_file = $info['path'];
	$log_url  = $info['url'];

if ( file_exists( $log_file ) ) :
	?>
<p style="margin-top: 20px;">
	<?php
	printf(
		esc_html__( 'For faster results, attach this %1$slog file%2$s to your support request.', 'nuclear-engagement' ),
		'<a href="' . esc_url( $log_url ) . '" target="_blank" rel="noopener noreferrer">',
		'</a>'
	);
	?>
</p>
<?php endif; ?>

<p>
<?php
printf(
	wp_kses(
		/* translators: review links */
		__(
			'If you like what I\'m doing, please leave me a review on %1$s, %2$s, %3$s, %4$s, or %5$s. It takes 1 minute to drop a 1-line review and it helps me immensely.',
			'nuclear-engagement'
		),
		array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
		)
	),
	// Google
	'<a href="https://www.google.com/search?q=Nuclear+Engagement" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'Google', 'nuclear-engagement' )
		. '</a>',
	// TrustPilot
	'<a href="https://www.trustpilot.com/evaluate/nuclearengagement.com?stars=5" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'TrustPilot', 'nuclear-engagement' )
		. '</a>',
	// Facebook
	'<a href="https://www.facebook.com/nuclearengagement/reviews" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'Facebook', 'nuclear-engagement' )
		. '</a>',
	// TrustIndex
	'<a href="https://public.trustindex.io/review/write/slug/www.nuclearengagement.com" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'TrustIndex', 'nuclear-engagement' )
		. '</a>',
	// WordPress.org
	'<a href="https://wordpress.org/support/plugin/nuclear-engagement/reviews/#new-post" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'WordPress.org', 'nuclear-engagement' )
		. '</a>'
);
?>
</p>
