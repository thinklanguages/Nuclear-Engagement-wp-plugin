<?php
declare(strict_types=1);
/**
 * File: admin/Traits/AdminMetaboxes.php
 *
 * Aggregates the individual metabox traits for quizzes and summaries.
 * Keeps the public interface identical to the previous monolithic trait
 * while delegating logic to dedicated, easier-to-maintain files.
 */

namespace NuclearEngagement\Admin\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/**
 * Trait Admin_Metaboxes
 *
 * Combines quiz and summary metabox functionality.
 */
trait AdminMetaboxes {
	use AdminQuizMetabox;
}
