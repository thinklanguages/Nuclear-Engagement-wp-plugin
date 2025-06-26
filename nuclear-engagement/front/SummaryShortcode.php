<?php
/**
 * Backwards compatibility wrapper for moved class.
 */

declare(strict_types=1);

namespace NuclearEngagement\Front;

use NuclearEngagement\Modules\Summary\Nuclen_Summary_Shortcode as ModuleSummaryShortcode;

class_alias(
    ModuleSummaryShortcode::class,
    __NAMESPACE__ . '\\SummaryShortcode'
);
