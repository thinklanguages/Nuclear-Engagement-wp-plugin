<?php
namespace {
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Traits\AdminMenu;
use NuclearEngagement\Admin\Setup;
use NuclearEngagement\Admin\Settings;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Core\InventoryCache;

require_once dirname(__DIR__) . '/nuclear-engagement/admin/Setup.php';
require_once dirname(__DIR__) . '/nuclear-engagement/admin/Settings.php';
require_once dirname(__DIR__) . '/nuclear-engagement/admin/Traits/AdminMenu.php';
require_once dirname(__DIR__) . '/nuclear-engagement/admin/Traits/AdminAssets.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsRepository.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/ServiceContainer.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/InventoryCache.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/AssetVersions.php';

if (!function_exists('esc_html')) { function esc_html($t){ return $t; } }
if (!function_exists('esc_attr')) { function esc_attr($t){ return $t; } }
if (!function_exists('esc_html__')) { function esc_html__($t,$d=null){ return $t; } }
if (!function_exists('esc_js')) { function esc_js($t){ return $t; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return $t; } }
if (!function_exists('wp_unslash')) { function wp_unslash($v){ return $v; } }
if (!function_exists('sanitize_key')) { function sanitize_key($s){ return $s; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($n,$a){ return true; } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field($a,$n,$r=true,$e=true){ $f='<input type="hidden" name="'.$n.'" value="nonce" />'; if($e) echo $f; return $f; } }
if (!function_exists('submit_button')) { function submit_button($text,$type='primary',$name='submit'){ echo $text; } }
if (!function_exists('admin_url')) { function admin_url($p=''){ return $p; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($file){ return ''; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($file){ return dirname($file).'/'; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script($h){ $GLOBALS['enqueued_scripts'][] = $h; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style($h){ $GLOBALS['enqueued_styles'][] = $h; } }
if (!function_exists('wp_localize_script')) { function wp_localize_script($h,$o,$d){ $GLOBALS['localized'][$o]=$d; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a){ return 'nonce'; } }
if (!function_exists('rest_url')) { function rest_url($p=''){ return 'rest/'.$p; } }

if (!function_exists('get_post_stati')) { function get_post_stati($a=[], $o='objects'){ return ['draft'=>(object)['label'=>'Draft']]; } }
if (!function_exists('get_post_type_object')) { function get_post_type_object($t){ return (object)['labels'=>(object)['name'=>'Post']]; } }
if (!function_exists('get_users')) { function get_users($a){ return []; } }
if (!function_exists('get_object_taxonomies')) { function get_object_taxonomies($pt){ return []; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set($k,$v,$g='',$t=0){ $GLOBALS['wp_cache'][$g][$k]=$v; } }
if (!function_exists('wp_cache_get')) { function wp_cache_get($k,$g='',$f=false,&$found=null){ $found=isset($GLOBALS['wp_cache'][$g][$k]); return $GLOBALS['wp_cache'][$g][$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$t=0){ $GLOBALS['transients'][$k]=$v; } }
if (!function_exists('get_transient')) { function get_transient($k){ return $GLOBALS['transients'][$k] ?? false; } }
}

namespace {
// Load the AdminMenu trait
require_once __DIR__ . '/../nuclear-engagement/admin/Traits/AdminMenu.php';

class DummyAdmin {
use \NuclearEngagement\Admin\Traits\AdminMenu;
private $repo;
private $container;
public function __construct($r,$c){ $this->repo=$r; $this->container=$c; }
public function nuclen_get_settings_repository(){ return $this->repo; }
protected function get_container(){ return $this->container; }
}

class AdminPageDisplayTest extends PHPUnit\Framework\TestCase {
protected function setUp(): void {
global $wp_options, $wp_cache, $transients, $enqueued_scripts, $enqueued_styles, $localized;
$wp_options = $wp_cache = $transients = [];
$enqueued_scripts = $enqueued_styles = $localized = [];

SettingsRepository::reset_for_tests();
ServiceContainer::getInstance()->reset();
if (!defined('NUCLEN_PLUGIN_DIR')) {
define('NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement/');
}
if (!defined('NUCLEN_PLUGIN_FILE')) {
define('NUCLEN_PLUGIN_FILE', NUCLEN_PLUGIN_DIR . 'nuclear-engagement.php');
}
if (!defined('NUCLEN_PLUGIN_VERSION')) {
define('NUCLEN_PLUGIN_VERSION', '1.0');
}
if (!defined('NUCLEN_ASSET_VERSION')) {
define('NUCLEN_ASSET_VERSION', 'dev');
}

}

public function test_render_setup_page_outputs_steps(): void {
$setup = new Setup(SettingsRepository::get_instance());
ob_start();
$setup->nuclen_render_setup_page();
$html = ob_get_clean();
$this->assertStringContainsString('nuclen-setup-step-1', $html);
$this->assertStringContainsString('nuclen-setup-step-2', $html);
}

public function test_display_generate_page_shows_notice_when_not_setup(): void {
$repo = SettingsRepository::get_instance();
$container = ServiceContainer::getInstance();
$container->register('dashboard_data_service', static function(){ return new class { public function get_scheduled_generations(){ return []; } }; });
$admin = new DummyAdmin($repo, $container);
ob_start();
$admin->nuclen_display_generate_page();
$html = ob_get_clean();
$this->assertStringContainsString('Please finish the plugin setup', $html);
}

public function test_display_dashboard_outputs_inventory_heading(): void {
InventoryCache::set([
'by_status_quiz'=>[],
'by_status_summary'=>[],
'by_post_type_quiz'=>[],
'by_post_type_summary'=>[],
'by_author_quiz'=>[],
'by_author_summary'=>[],
'by_category_quiz'=>[],
'by_category_summary'=>[],
]);
$repo = SettingsRepository::get_instance();
$container = ServiceContainer::getInstance();
$container->register('dashboard_data_service', static function(){ return new class { public function get_scheduled_generations(){ return []; } }; });
$admin = new DummyAdmin($repo, $container);
ob_start();
$admin->nuclen_display_dashboard();
$html = ob_get_clean();
$this->assertStringContainsString('Post Inventory', $html);
}

public function test_display_settings_page_renders_form(): void {
$settings = new Settings(SettingsRepository::get_instance());
ob_start();
$settings->nuclen_display_settings_page();
$html = ob_get_clean();
$this->assertStringContainsString('<form', $html);
$this->assertStringContainsString('nuclen_save_settings', $html);
}

public function test_enqueue_scripts_for_generate_page_localizes_ajax(): void {
global $enqueued_scripts, $localized;
$enqueued_scripts = $localized = [];

$host = new class {
use \NuclearEngagement\Admin\Traits\AdminAssets;
public string $plugin_name = 'nuclen';
public function nuclen_get_plugin_name() { return $this->plugin_name; }
public function nuclen_get_version() { return '1.0'; }
};

$host->wp_enqueue_scripts('nuclear-engagement_page_nuclear-engagement-generate');

$this->assertContains('nuclen-admin', $enqueued_scripts);
$this->assertArrayHasKey('nuclenAdminVars', $localized);
$this->assertArrayHasKey('nuclenAjax', $localized);
$this->assertSame('nuclen_fetch_app_updates', $localized['nuclenAjax']['fetch_action']);
}

public function test_enqueue_scripts_for_settings_page_skips_ajax_localize(): void {
global $enqueued_scripts, $localized;
$enqueued_scripts = $localized = [];
$host = new class {
use \NuclearEngagement\Admin\Traits\AdminAssets;
public string $plugin_name = 'nuclen';
public function nuclen_get_plugin_name() { return $this->plugin_name; }
public function nuclen_get_version() { return '1.0'; }
};

$host->wp_enqueue_scripts('nuclear-engagement_page_nuclear-engagement-settings');
$this->assertContains('nuclen-admin', $enqueued_scripts);
$this->assertArrayHasKey('nuclenAdminVars', $localized);
$this->assertArrayNotHasKey('nuclenAjax', $localized);
}

public function test_enqueue_dashboard_styles_only_on_dashboard(): void {
global $enqueued_styles;
$enqueued_styles = [];
$host = new class {
use \NuclearEngagement\Admin\Traits\AdminAssets;
public string $plugin_name = 'nuclen';
public function nuclen_get_plugin_name() { return $this->plugin_name; }
public function nuclen_get_version() { return '1.0'; }
};

$host->nuclen_enqueue_dashboard_styles('toplevel_page_nuclear-engagement');
$this->assertContains('nuclen-dashboard', $enqueued_styles[0]);
}

}
}
