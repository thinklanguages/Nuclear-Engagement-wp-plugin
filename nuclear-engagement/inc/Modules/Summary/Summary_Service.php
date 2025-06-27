<?php
declare(strict_types=1);
/**
 * Summary data storage constants.
 *
 * @package NuclearEngagement\Modules\Summary
 */

namespace NuclearEngagement\Modules\Summary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Summary_Service {
	public const META_KEY      = 'nuclen-summary-data';
	public const PROTECTED_KEY = 'nuclen_summary_protected';
}
