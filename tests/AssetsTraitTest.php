<?php
	namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Front\AssetsTrait;
	use NuclearEngagement\Core\SettingsRepository;
	
	if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
	return 'admin-url';
	}
	}
	if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $a ) {
	return 'nonce';
	}
	}
	if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
	return $GLOBALS['current_post_id'] ?? 0;
	}
	}
	
	class DummyAssetsHost {
	use AssetsTrait;
	
	public string $plugin_name = 'nuclen';
	
	public function nuclen_get_settings_repository() {
	return SettingsRepository::get_instance();
	}
	}
	
	class AssetsTraitTest extends TestCase {
	private DummyAssetsHost $host;
	
	protected function setUp(): void {
	global $wp_meta, $current_post_id;
	$wp_meta         = array();
	$current_post_id = 1;
	SettingsRepository::reset_for_tests();
	$this->host = new DummyAssetsHost();
	}
	
	public function test_get_optin_ajax_data_returns_expected_array(): void {
	$ref = new \ReflectionMethod( $this->host, 'get_optin_ajax_data' );
	$ref->setAccessible( true );
	$this->assertSame(
	array( 'url' => 'admin-url', 'nonce' => 'nonce' ),
	$ref->invoke( $this->host )
	);
	}
	
	public function test_get_post_quiz_data_reads_meta(): void {
	global $wp_meta, $current_post_id;
	$current_post_id           = 5;
	$wp_meta[5]['nuclen-quiz-data'] = array( 'questions' => array( 1, 2 ) );
	$ref = new \ReflectionMethod( $this->host, 'get_post_quiz_data' );
	$ref->setAccessible( true );
	$this->assertSame( array( 1, 2 ), $ref->invoke( $this->host ) );
	}
	
	public function test_get_numeric_settings_uses_repository(): void {
	SettingsRepository::reset_for_tests();
	SettingsRepository::get_instance(
	array( 'questions_per_quiz' => 3, 'answers_per_question' => 2 )
	);
	$ref = new \ReflectionMethod( $this->host, 'get_numeric_settings' );
	$ref->setAccessible( true );
	$this->assertSame(
	array(
	'questions_per_quiz'   => 3,
	'answers_per_question' => 2,
	),
	$ref->invoke( $this->host )
	);
	}
	
	public function test_get_optin_inline_js_outputs_variables(): void {
	SettingsRepository::reset_for_tests();
	SettingsRepository::get_instance( array( 'enable_optin' => true ) );
	$ref = new \ReflectionMethod( $this->host, 'get_optin_inline_js' );
	$ref->setAccessible( true );
	$result = $ref->invoke( $this->host );
	$this->assertStringContainsString( 'var NuclenOptinEnabled  = true', $result );
	}
	}
}
