<?php
function dbDelta( $sql ) {
	global $dbDelta_called;
	$dbDelta_called = true;
}
