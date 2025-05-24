<?php
/**
 * File: admin/trait-settings-sanitize-core.php
 *
 * Thin wrapper that merges *General* + *Style* sanitisation.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

/* Pull in the granular traits */
require_once __DIR__ . '/trait-settings-sanitize-general.php';
require_once __DIR__ . '/trait-settings-sanitize-style.php';

trait SettingsSanitizeCoreTrait {

	use SettingsSanitizeGeneralTrait;
	use SettingsSanitizeStyleTrait;

	/**
	 * Aggregate non-opt-in sanitisation.
	 *
	 * @param array $input Raw settings.
	 * @return array       Clean array (still missing Opt-In keys).
	 */
	private function nuclen_sanitize_core( array $input ): array {
		return array_merge(
			$this->nuclen_sanitize_general( $input ),
			$this->nuclen_sanitize_style(   $input )
		);
	}
}
