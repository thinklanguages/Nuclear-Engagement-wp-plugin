<?php
/**
 * AdminNoticeService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
	* Handles admin notices for the plugin.
	*/

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminNoticeService {
	/**
	 * @var array<string>
	 */
	private array $messages = array();

	public function add( string $message ): void {
		$this->messages[] = $message;
		if ( count( $this->messages ) === 1 ) {
			add_action( 'admin_notices', array( $this, 'render' ) );
		}
	}

	public function render(): void {
		foreach ( $this->messages as $msg ) {
				load_template(
					NUCLEN_PLUGIN_DIR . 'templates/admin/notice.php',
					true,
					array( 'msg' => $msg )
				);
		}
			$this->messages = array();
	}
}
