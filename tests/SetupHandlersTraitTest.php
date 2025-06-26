<?php
namespace NuclearEngagement\Services {
    class SetupService {
        public bool $validate_return = true;
        public bool $send_return = true;
        public array $validate_args = [];
        public array $send_args = [];
        public function validate_api_key(string $key): bool {
            $this->validate_args[] = $key;
            return $this->validate_return;
        }
        public function send_app_password(array $data): bool {
            $this->send_args[] = $data;
            return $this->send_return;
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Core\SettingsRepository;
    use NuclearEngagement\Core\Container;

    class RedirectException extends \Exception {}

    // Global stubs for WordPress functions
    if (!function_exists('current_user_can')) {
        function current_user_can($cap) {
            return $GLOBALS['can_manage'] ?? false;
        }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($val) { return $val; }
    }
    if (!function_exists('wp_unslash')) {
        function wp_unslash($val) { return $val; }
    }
    if (!function_exists('wp_generate_password')) {
        function wp_generate_password($len, $s1 = false, $s2 = false) { return 'pass'; }
    }
    if (!function_exists('wp_generate_uuid4')) {
        function wp_generate_uuid4() { return 'uuid'; }
    }
    if (!function_exists('wp_get_current_user')) {
        function wp_get_current_user() { return (object)['user_login' => 'admin']; }
    }
    if (!function_exists('get_site_url')) {
        function get_site_url() { return 'http://example.com'; }
    }
    if (!function_exists('wp_cache_delete')) {
        function wp_cache_delete($key, $group = '') { $GLOBALS['cache_deleted'][] = [$key,$group]; }
    }

    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Container.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsRepository.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Defaults.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/admin/SetupHandlersTrait.php';

class DummySetup {
    use \NuclearEngagement\Admin\SetupHandlersTrait;
    public $redirect;
    private $service;
    public function __construct($service) { $this->service = $service; }
    public function nuclen_get_setup_service(): \NuclearEngagement\Services\SetupService { return $this->service; }
    public function nuclen_get_settings_repository() { return \NuclearEngagement\Core\Container::getInstance()->get('settings'); }
    private function nuclen_redirect_with_error($msg): void { $this->redirect = ['error',$msg]; throw new RedirectException(); }
    private function nuclen_redirect_with_success($msg): void { $this->redirect = ['success',$msg]; throw new RedirectException(); }
}

    class SetupHandlersTraitTest extends TestCase {
        private $setup;
        private $service;

        protected function setUp(): void {
            global $wp_options, $wp_autoload, $cache_deleted;
            $wp_options = $wp_autoload = [];
            $cache_deleted = [];
            SettingsRepository::reset_for_tests();
            Container::getInstance()->reset();
            $settings = SettingsRepository::get_instance();
            Container::getInstance()->register('settings', static function() use ($settings) { return $settings; });
            $this->service = new \NuclearEngagement\Services\SetupService();
            $this->setup = new DummySetup($this->service);
            $GLOBALS['can_manage'] = true;
            $GLOBALS['test_verify_nonce'] = true;
            $_POST = [];
        }

        protected function tearDown(): void {
            unset($GLOBALS['can_manage'], $GLOBALS['test_verify_nonce'], $GLOBALS['cache_deleted']);
        }

        public function test_connect_app_invalid_nonce(): void {
            $GLOBALS['test_verify_nonce'] = false;
            $_POST = [ 'nuclen_connect_app_nonce' => 'x', 'nuclen_api_key' => 'k' ];
            $this->expectException(RedirectException::class);
            try { $this->setup->nuclen_handle_connect_app(); } catch (RedirectException $e) {}
            $this->assertSame(['error','Invalid nonce.'], $this->setup->redirect);
            $this->assertEmpty($this->service->validate_args);
        }

        public function test_connect_app_requires_capability(): void {
            $GLOBALS['can_manage'] = false;
            $_POST = [ 'nuclen_connect_app_nonce' => 'valid', 'nuclen_api_key' => 'k' ];
            $this->expectException(RedirectException::class);
            try { $this->setup->nuclen_handle_connect_app(); } catch (RedirectException $e) {}
            $this->assertSame(['error','Insufficient permissions.'], $this->setup->redirect);
        }

        public function test_generate_app_password_invalid_nonce(): void {
            $GLOBALS['test_verify_nonce'] = false;
            $_POST = [ 'nuclen_generate_app_password_nonce' => 'n' ];
            $this->expectException(RedirectException::class);
            try { $this->setup->nuclen_handle_generate_app_password(); } catch (RedirectException $e) {}
            $this->assertSame(['error','Invalid nonce.'], $this->setup->redirect);
        }

        public function test_successful_connect_and_password_creation(): void {
            $_POST = [ 'nuclen_connect_app_nonce' => 'valid', 'nuclen_api_key' => 'gold' ];
            $this->service->validate_return = true;
            $this->service->send_return = true;
            $this->expectException(RedirectException::class);
            try { $this->setup->nuclen_handle_connect_app(); } catch (RedirectException $e) {}
            $settings = SettingsRepository::get_instance();
            $this->assertSame('gold', $settings->get_string('api_key'));
            $this->assertTrue($settings->get_bool('connected'));
            $this->assertTrue($settings->get_bool('wp_app_pass_created'));
            $this->assertSame('uuid', $settings->get_string('wp_app_pass_uuid'));
            $this->assertSame('pass', $settings->get_string('plugin_password'));
            $this->assertSame(['success','Setup completed – you are ready to go!'], $this->setup->redirect);
            $this->assertSame(['gold'], $this->service->validate_args);
            $this->assertCount(1, $this->service->send_args);
        }
    }
}
