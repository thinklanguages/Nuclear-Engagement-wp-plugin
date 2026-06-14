<?php
/**
 * Per-file PHPUnit runner.
 *
 * Why this exists: the PHP unit suite uses a minimal hand-rolled bootstrap
 * (tests/bootstrap.php) and many test files declare their own test doubles inside
 * production namespaces (e.g. a fake `NuclearEngagement\Services\LoggingService`).
 * Those doubles are mutually exclusive across files — some tests need the fake,
 * others (e.g. LoggingServiceTest) need the real autoloaded class — so the suite
 * CANNOT run in a single PHP process: declarations collide / shadow each other.
 * The bootstrap also registers anonymous classes in globals, which makes PHPUnit's
 * own `--process-isolation` impossible ("Serialization of class@anonymous is not
 * allowed").
 *
 * Therefore each test file is executed in its own PHPUnit process here. The exit
 * code is non-zero if any file reports failures or errors (skipped / risky / warning
 * are allowed). This mirrors how CI's real WordPress environment isolates state.
 *
 * Usage:
 *   php scripts/run-tests.php              (wired to `composer test`)
 *   php scripts/run-tests.php --coverage   (per-file coverage merged to clover + html)
 */

$root     = dirname( __DIR__ );
$phpunit  = $root . '/vendor/phpunit/phpunit/phpunit';
$coverage = in_array( '--coverage', $argv, true );

if ( ! is_file( $phpunit ) ) {
	fwrite( STDERR, "phpunit not found at $phpunit — run composer install.\n" );
	exit( 2 );
}

$covDir = $root . '/coverage/.cov';
if ( $coverage ) {
	require $root . '/vendor/autoload.php';
	// Start from a clean per-file coverage directory.
	if ( is_dir( $covDir ) ) {
		foreach ( glob( $covDir . '/*.cov' ) as $old ) {
			@unlink( $old );
		}
	} else {
		@mkdir( $covDir, 0777, true );
	}
}

$testsDir = $root . '/tests';
$files    = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $testsDir, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isFile() && substr( $file->getFilename(), -8 ) === 'Test.php' ) {
		$files[] = $file->getPathname();
	}
}
sort( $files );

$failed    = array();
$okCount   = 0;
$skipFiles = 0;
$start     = microtime( true );

foreach ( $files as $i => $file ) {
	$rel = ltrim( str_replace( $root, '', $file ), '/\\' );
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $phpunit ) . ' ';
	if ( $coverage ) {
		$cmd .= '--coverage-php ' . escapeshellarg( $covDir . '/' . $i . '.cov' ) . ' ';
	} else {
		$cmd .= '--no-coverage ';
	}
	$cmd .= escapeshellarg( $file ) . ' 2>&1';

	$output = array();
	$code   = 0;
	exec( $cmd, $output, $code );

	$summary = '';
	foreach ( array_reverse( $output ) as $line ) {
		$trimmed = trim( $line );
		if ( preg_match( '/^(OK|FAILURES!|ERRORS!|WARNINGS!|OK, but|No tests|Tests: )/', $trimmed ) ) {
			$summary = $trimmed;
			break;
		}
	}

	if ( 0 !== $code ) {
		$failed[] = array( $rel, '' !== $summary ? $summary : 'exit ' . $code );
		echo "FAIL  $rel  ::  " . ( '' !== $summary ? $summary : 'exit ' . $code ) . "\n";
	} else {
		++$okCount;
		if ( false !== stripos( implode( "\n", $output ), 'Skipped:' ) ) {
			++$skipFiles;
		}
	}
}

$elapsed = round( microtime( true ) - $start, 1 );
echo "\n========================================\n";
echo 'Files: ' . count( $files ) . " | green-or-skipped: $okCount (with skips: $skipFiles) | FAILED: "
	. count( $failed ) . " | {$elapsed}s\n";

if ( $coverage ) {
	merge_coverage( $covDir, $root );
}

if ( $failed ) {
	echo "\nFailing files:\n";
	foreach ( $failed as $entry ) {
		echo "  - {$entry[0]}  ::  {$entry[1]}\n";
	}
	exit( 1 );
}

echo "All test files green-or-skipped.\n";
exit( 0 );

/**
 * Merge the per-file .cov objects into a single clover + HTML report.
 */
function merge_coverage( string $covDir, string $root ): void {
	$covFiles = glob( $covDir . '/*.cov' );
	if ( empty( $covFiles ) ) {
		echo "No coverage data collected.\n";
		return;
	}

	$merged = null;
	foreach ( $covFiles as $covFile ) {
		$cov = include $covFile; // --coverage-php returns the CodeCoverage object.
		if ( ! $cov instanceof \SebastianBergmann\CodeCoverage\CodeCoverage ) {
			continue;
		}
		if ( null === $merged ) {
			$merged = $cov;
		} else {
			$merged->merge( $cov );
		}
	}

	if ( null === $merged ) {
		echo "No usable coverage objects found.\n";
		return;
	}

	( new \SebastianBergmann\CodeCoverage\Report\Clover() )->process( $merged, $root . '/coverage.xml' );
	( new \SebastianBergmann\CodeCoverage\Report\Html\Facade() )->process( $merged, $root . '/coverage/html' );
	echo "Coverage report written: coverage.xml + coverage/html\n";
}
