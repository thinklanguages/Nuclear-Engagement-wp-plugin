<?php
declare(strict_types=1);
/**
 * File: admin/trait-settings-sanitize-core.php
 *
 * Thin wrapper that merges *General* + *Style* sanitisation.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

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
			$this->nuclen_sanitize_style( $input )
		);
	}
}
