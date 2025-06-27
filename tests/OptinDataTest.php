<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\OptinData;

class OptinDataTest extends TestCase {
	private \ReflectionMethod $escapeMethod;

	protected function setUp(): void {
		$this->escapeMethod = new \ReflectionMethod(OptinData::class, 'escape_csv_field');
		$this->escapeMethod->setAccessible(true);
	}

	public function test_escape_csv_field_prefixes_formula() {
		$this->assertSame("'=1+1", $this->escapeMethod->invoke(null, '=1+1'));
		$this->assertSame("'+SUM(A1)", $this->escapeMethod->invoke(null, '+SUM(A1)'));
	}

	public function test_escape_csv_field_unchanged() {
		$this->assertSame('plain', $this->escapeMethod->invoke(null, 'plain'));
	}

	public function test_dbDelta_not_called_when_table_exists() {
		global $dbDelta_called, $wpdb;

		$dbDelta_called = false;
		$wpdb = new class {
			public $prefix = 'wp_';
			public function get_charset_collate() { return ''; }
			public function prepare($query, $param) { return $param; }
			public function get_var($query) { return 'wp_nuclen_optins'; }
		};

		OptinData::maybe_create_table();

		$this->assertFalse($dbDelta_called);
	}
}

if (!function_exists('sanitize_text_field')) {
function sanitize_text_field($t) { return is_string($t) ? trim($t) : ''; }
}
if (!function_exists('sanitize_email')) {
function sanitize_email($e) { return is_string($e) ? strtolower(trim($e)) : ''; }
}
if (!function_exists('esc_url_raw')) {
function esc_url_raw($u) { return $u; }
}
if (!function_exists('wp_unslash')) {
function wp_unslash($v) { return $v; }
}
if (!function_exists('is_email')) {
function is_email($e) {
if (array_key_exists('test_is_email', $GLOBALS)) {
return (bool) $GLOBALS['test_is_email'];
}
return (bool) filter_var($e, FILTER_VALIDATE_EMAIL);
}
}
if (!function_exists('check_ajax_referer')) {
function check_ajax_referer($a, $b) { return true; }
}
if (!function_exists('wp_send_json_success')) {
function wp_send_json_success($d = null) { $GLOBALS['json_response'] = ['success', $d]; }
}
if (!function_exists('wp_send_json_error')) {
function wp_send_json_error($d, $c = 0) { $GLOBALS['json_response'] = ['error', $d, $c]; }
}

class OptinDataInsertDB {
public string $prefix = 'wp_';
public array $args = [];
public $last_error = '';
public function insert($table, $data, $format) {
$this->args = [$table, $data, $format];
return 1;
}
}

class OptinDataFailDB extends OptinDataInsertDB {
public function insert($t, $d, $f) {
$this->args = [$t, $d, $f];
return false;
}
}

class OptinDataExtendedTest extends OptinDataTest {
protected function setUp(): void {
parent::setUp();
$GLOBALS['json_response'] = null;
unset($GLOBALS['test_is_email']);
}

public function test_insert_sanitizes_and_returns_true() {
global $wpdb;
$wpdb = new OptinDataInsertDB();
$result = OptinData::insert(' Bob ', 'TEST@EXAMPLE.COM', 'http://example.com');
$this->assertTrue($result);
$this->assertSame('wp_nuclen_optins', $wpdb->args[0]);
$data = $wpdb->args[1];
$this->assertSame('http://example.com', $data['url']);
$this->assertSame('Bob', $data['name']);
$this->assertSame('test@example.com', $data['email']);
}

public function test_insert_invalid_email_returns_false() {
global $wpdb;
$wpdb = new OptinDataInsertDB();
$GLOBALS['test_is_email'] = false;
$this->assertFalse(OptinData::insert('n', 'bad', 'u'));
}

public function test_handle_ajax_success() {
global $wpdb;
$wpdb = new OptinDataInsertDB();
$_POST = ['name' => ' A ', 'email' => 'a@b.com', 'url' => 'http://s', 'nonce' => 'n'];
OptinData::handle_ajax();
$this->assertSame(['success', null], $GLOBALS['json_response']);
$this->assertSame('A', $wpdb->args[1]['name']);
}

public function test_handle_ajax_invalid_email_errors() {
global $wpdb;
$wpdb = new OptinDataInsertDB();
$GLOBALS['test_is_email'] = false;
$_POST = ['name' => 'A', 'email' => 'bad', 'url' => 'x', 'nonce' => 'n'];
OptinData::handle_ajax();
$this->assertSame(['error', ['message' => 'Please enter a valid email address.'], 400], $GLOBALS['json_response']);
}

public function test_handle_ajax_insert_failure_errors() {
global $wpdb;
$wpdb = new OptinDataFailDB();
$_POST = ['name' => 'A', 'email' => 'a@b.com', 'url' => 'x', 'nonce' => 'n'];
OptinData::handle_ajax();
$this->assertSame(['error', ['message' => 'Unable to save your submission. Please try again later.'], 500], $GLOBALS['json_response']);
}
}
