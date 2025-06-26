<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Onboarding;

namespace {
    if (!function_exists('wp_enqueue_style')) {
        function wp_enqueue_style($handle) { $GLOBALS['enqueued_styles'][] = $handle; }
    }
    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script($handle, $src='', $deps=array(), $ver='', $in_footer=false) { $GLOBALS['enqueued_scripts'][] = $handle; }
    }
    if (!function_exists('wp_add_inline_script')) {
        function wp_add_inline_script($handle, $code, $pos='after') { $GLOBALS['inline_script'][$handle] = $code; }
    }
    if (!function_exists('admin_url')) {
        function admin_url($path='') { return 'admin-ajax.php'; }
    }
    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce($a) { return 'nonce123'; }
    }
    if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url($file) { return ''; }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can($cap) { return $GLOBALS['can_manage'] ?? true; }
    }
    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer($a,$f) { return true; }
    }
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data) { $GLOBALS['json_response'] = ['success',$data]; }
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data,$code=0) { $GLOBALS['json_response'] = ['error',$data,$code]; }
    }
    if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return $t; } }
    if (!function_exists('wp_unslash')) { function wp_unslash($d){ return $d; } }
}

namespace {
    require_once __DIR__ . '/../nuclear-engagement/admin/OnboardingPointers.php';
    require_once __DIR__ . '/../nuclear-engagement/admin/Onboarding.php';

    class OnboardingTest extends TestCase {
        protected function setUp(): void {
            global $enqueued_scripts, $inline_script, $wp_user_meta, $json_response, $can_manage;
            $enqueued_scripts = $inline_script = [];
            $wp_user_meta = [];
            $json_response = null;
            $can_manage = true;
        }

        public function test_enqueue_pointers_enqueues_and_injects(): void {
            global $enqueued_scripts, $inline_script, $wp_user_meta;
            $wp_user_meta[1]['nuclen_pointer_dismissed_nuclen_postedit_step1'] = true;
            $onb = new Onboarding();
            $onb->enqueue_nuclen_onboarding_pointers('post.php');
            $this->assertContains('nuclen-onboarding', $enqueued_scripts);
            $this->assertArrayHasKey('nuclen-onboarding', $inline_script);
            $json = str_replace('window.nePointerData = ', '', rtrim($inline_script['nuclen-onboarding'], ';'));
            $data = json_decode($json, true);
            $ids = array_column($data['pointers'], 'id');
            $this->assertNotContains('nuclen_postedit_step1', $ids);
        }

        public function test_ajax_dismiss_pointer_permission_failure(): void {
            global $json_response, $can_manage;
            $can_manage = false;
            $onb = new Onboarding();
            $_POST = ['pointer' => 'x', 'nonce' => 'n'];
            $onb->nuclen_ajax_dismiss_pointer();
            $this->assertSame(['error',['message'=>'No permission'],0], $json_response);
        }

        public function test_ajax_dismiss_pointer_updates_meta(): void {
            global $json_response, $wp_user_meta;
            $onb = new Onboarding();
            $_POST = ['pointer' => 'abc', 'nonce' => 'n'];
            $onb->nuclen_ajax_dismiss_pointer();
            $this->assertTrue($wp_user_meta[1]['nuclen_pointer_dismissed_abc']);
            $this->assertSame(['success',['message'=>'Pointer dismissed.']], $json_response);
        }
    }
}
