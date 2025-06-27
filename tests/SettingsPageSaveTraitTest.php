<?php
namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Admin\Traits\SettingsPageSaveTrait;
    use NuclearEngagement\Admin\Traits\SettingsCollectTrait;
    use NuclearEngagement\Admin\Traits\SettingsPersistTrait;
    use NuclearEngagement\Admin\SettingsSanitizeTrait;
    use NuclearEngagement\Core\SettingsRepository;
    use NuclearEngagement\Core\Defaults;

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($t) { return is_string($t) ? trim($t) : $t; }
    }
    if (!function_exists('wp_unslash')) { function wp_unslash($v){ return $v; } }
    if (!function_exists('wp_kses_post')) { function wp_kses_post($v){ return $v; } }
    if (!function_exists('check_admin_referer')) { function check_admin_referer($a,$b,$c=false){ return true; } }
    if (!function_exists('esc_url_raw')) { function esc_url_raw($u){ return $u; } }
    if (!function_exists('filter_input')) { function filter_input($type,$name,$filter=null,$opt=null){ return $_POST[$name] ?? null; } }

    class DummySettingsPage {
        use SettingsPageSaveTrait;
        use SettingsCollectTrait;
        use SettingsPersistTrait;
        use SettingsSanitizeTrait;
        public function nuclen_get_settings_repository() { return SettingsRepository::get_instance(); }
    }

    class SettingsPageSaveTraitTest extends TestCase {
        private DummySettingsPage $page;

        protected function setUp(): void {
            global $wp_options;
            $wp_options = [];
            SettingsRepository::reset_for_tests();
            $this->page = new DummySettingsPage();
            $_POST = [];
        }

        public function test_collect_input_returns_expected_keys(): void {
            $_POST['nuclen_theme'] = 'dark';
            $_POST['nuclen_font_size'] = '20';
            $_POST['nuclen_display_quiz'] = 'before';
            $_POST['nuclear_engagement_settings']['toc_heading_levels'] = [2,3];
            $ref = new \ReflectionMethod($this->page, 'nuclen_collect_input');
            $ref->setAccessible(true);
            $result = $ref->invoke($this->page);
            $this->assertSame('dark', $result['theme']);
            $this->assertSame('20', $result['font_size']);
            $this->assertSame('before', $result['display_quiz']);
            $this->assertSame([2,3], $result['toc_heading_levels']);
        }

        public function test_sanitize_and_defaults_merges_defaults(): void {
            $raw = ['font_size' => '15'];
            $defaults = Defaults::nuclen_get_default_settings();
            $ref = new \ReflectionMethod($this->page, 'nuclen_sanitize_and_defaults');
            $ref->setAccessible(true);
            $result = $ref->invoke($this->page, $raw, $defaults);
            $this->assertSame(15, $result['font_size']);
            $this->assertSame($defaults['font_color'], $result['font_color']);
        }

        public function test_persist_settings_saves_via_repository(): void {
            $ref = new \ReflectionMethod($this->page, 'nuclen_persist_settings');
            $ref->setAccessible(true);
            $saved = $ref->invoke($this->page, ['theme' => 'dark']);
            $repo = SettingsRepository::get_instance();
            $this->assertSame('dark', $repo->get('theme'));
            $this->assertSame('dark', $saved['theme']);
        }

        public function test_output_save_notice_prints_html(): void {
            $ref = new \ReflectionMethod($this->page, 'nuclen_output_save_notice');
            $ref->setAccessible(true);
            ob_start();
            $ref->invoke($this->page);
            $html = ob_get_clean();
            $this->assertStringContainsString('Settings saved.', $html);
        }
    }
}
