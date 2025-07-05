<?php
/**
 * Summary_Service.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Modules_Summary
 */

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
