<?php
/**
 * credit-balance.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
/**
 * Reusable credit balance component
 *
 * Variables:
 *   – $element_id (string) - Unique ID for the element
 *   – $show_title (bool) - Whether to show the title (default: true)
 *   – $title (string) - Custom title (default: 'Your Credits')
 *   – $loading_text (string) - Loading message (default: 'Loading credits...')
 *   – $container_class (string) - Additional CSS classes for container
 *   – $inline (bool) - Whether to display inline (default: false)
 *
 * @package NuclearEngagement\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Default values
$element_id      = $element_id ?? 'nuclen-credits-' . uniqid();
$show_title      = $show_title ?? true;
$title           = $title ?? __( 'Your Credits', 'nuclear-engagement' );
$loading_text    = $loading_text ?? __( 'Loading credits...', 'nuclear-engagement' );
$container_class = $container_class ?? '';
$inline          = $inline ?? false;
?>

<div class="nuclen-credit-balance-container <?php echo esc_attr( $container_class ); ?>">
	<?php if ( $show_title ) : ?>
		<h2 class="nuclen-credit-balance-title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<p id="<?php echo esc_attr( $element_id ); ?>" class="nuclen-credit-balance-display <?php echo $inline ? 'nuclen-inline' : ''; ?>">
		<span class="spinner is-active" style="float: none; vertical-align: middle;"></span>
		<?php echo esc_html( $loading_text ); ?>
	</p>
</div>

<script>
(function() {
	// Encapsulate to avoid global namespace pollution
	const NuclenCreditBalance = {
		elementId: '<?php echo esc_js( $element_id ); ?>',
		nonce: '<?php echo esc_js( wp_create_nonce( 'nuclen_admin_ajax_nonce' ) ); ?>',
		ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
		
		async fetchCredits() {
			const msgEl = document.getElementById(this.elementId);
			if (!msgEl) return;
			
			try {
				const formData = new FormData();
				formData.append('action', 'nuclen_fetch_app_updates');
				formData.append('security', this.nonce);
				
				const response = await fetch(this.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (!data.success) {
					throw new Error(data.data?.message || '<?php echo esc_js( __( 'Failed to fetch credits', 'nuclear-engagement' ) ); ?>');
				}
				
				const remoteData = data.data;
				
				if (remoteData && typeof remoteData.remaining_credits !== 'undefined') {
					const creditsText = '<?php echo esc_js( __( 'You have %d credits remaining', 'nuclear-engagement' ) ); ?>';
					msgEl.innerHTML = creditsText.replace('%d', '<strong>' + remoteData.remaining_credits + '</strong>');
					
					// Add visual indicator based on credit level
					msgEl.classList.remove('nuclen-credits-low', 'nuclen-credits-medium', 'nuclen-credits-high');
					if (remoteData.remaining_credits < 10) {
						msgEl.classList.add('nuclen-credits-low');
					} else if (remoteData.remaining_credits < 50) {
						msgEl.classList.add('nuclen-credits-medium');
					} else {
						msgEl.classList.add('nuclen-credits-high');
					}
					
					// Store in window for other scripts to access
					if (typeof window.nuclenCredits === 'undefined') {
						window.nuclenCredits = {};
					}
					window.nuclenCredits.remaining = remoteData.remaining_credits;
					window.nuclenCredits.lastFetch = Date.now();
					
				} else {
					msgEl.textContent = '<?php echo esc_js( __( 'Credit information unavailable', 'nuclear-engagement' ) ); ?>';
					msgEl.classList.add('nuclen-credits-error');
				}
			} catch (err) {
				msgEl.innerHTML = '<?php echo esc_js( __( 'Error loading credits', 'nuclear-engagement' ) ); ?>: ' + err.message;
				msgEl.classList.add('nuclen-credits-error');
			}
		},
		
		init() {
			// Wait for DOM ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', () => this.fetchCredits());
			} else {
				this.fetchCredits();
			}
		}
	};
	
	NuclenCreditBalance.init();
})();
</script>

<style>
.nuclen-credit-balance-container {
	margin: 1em 0;
}

.nuclen-credit-balance-title {
	margin-bottom: 0.5em;
}

.nuclen-credit-balance-display {
	font-size: 14px;
	margin: 0.5em 0;
}

.nuclen-credit-balance-display.nuclen-inline {
	display: inline-block;
	margin: 0;
}

.nuclen-credit-balance-display strong {
	font-weight: 600;
}

.nuclen-credits-low {
	color: #d63638;
}

.nuclen-credits-medium {
	color: #dba617;
}

.nuclen-credits-high {
	color: #00a32a;
}

.nuclen-credits-error {
	color: #646970;
	font-style: italic;
}
</style>
