<?php
// Production code does `require_once ABSPATH . 'wp-admin/includes/upgrade.php'`, which
// resolves here in the test harness. Guard the declaration so it never collides with the
// dbDelta() stub already defined in tests/bootstrap.php (which would be a fatal redeclare).
if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		global $dbDelta_called;
		$dbDelta_called = true;
	}
}
