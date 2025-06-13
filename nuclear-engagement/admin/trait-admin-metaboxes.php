<?php
/**
 * File: admin/trait-admin-metaboxes.php
 *
 * Aggregates the individual metabox traits for quizzes and summaries.
 * Keeps the public interface identical to the previous monolithic trait
 * while delegating logic to dedicated, easier-to-maintain files.
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/**
 * Trait Admin_Metaboxes
 *
 * Combines quiz and summary metabox functionality.
 */
trait Admin_Metaboxes {
	use Admin_Quiz_Metabox;
	use Admin_Summary_Metabox;
}
