<?php
declare(strict_types=1);
/**
 * File: admin/SettingsSanitizeTrait.php
 *
 * Public sanitiser that composes Core + Opt-In.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

trait SettingsSanitizeTrait {

    use SettingsSanitizeCoreTrait;
    use SettingsSanitizeOptinTrait;

    /**
     * Merge the two sanitisation branches.
     *
     * @param array $input Raw settings array from the form.
     * @return array       Fully-clean settings.
     */
    public function nuclen_sanitize_settings( $input ) {
        return array_merge(
            $this->nuclen_sanitize_core(  $input ),
            $this->nuclen_sanitize_optin( $input )
        );
    }
}
