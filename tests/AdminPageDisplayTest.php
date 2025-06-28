<?php
namespace {
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Traits\AdminMenu;
use NuclearEngagement\Admin\Setup;
use NuclearEngagement\Admin\Settings;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\Container;
use NuclearEngagement\Core\InventoryCache;

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
class DummyAdmin {
use AdminMenu;
private $repo;
private $container;
public function __construct($r,$c){ $this->repo=$r; $this->container=$c; }
public function nuclen_get_settings_repository(){ return $this->repo; }
protected function get_container(){ return $this->container; }
}

class AdminPageDisplayTest extends TestCase {
protected function setUp(): void {
global $wp_options, $wp_cache, $transients;
$wp_options = $wp_cache = $transients = [];
SettingsRepository::reset_for_tests();
Container::getInstance()->reset();
if (!defined('NUCLEN_PLUGIN_DIR')) {
define('NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement/');
}
if (!defined('NUCLEN_PLUGIN_FILE')) {
define('NUCLEN_PLUGIN_FILE', NUCLEN_PLUGIN_DIR . 'nuclear-engagement.php');
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
$container = Container::getInstance();
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
$container = Container::getInstance();
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
}
}
