<?php

class AjaxEndpointsIntegrationTest extends WP_UnitTestCase {

    private $admin_user_id;
    private $editor_user_id;

    public function setUp(): void {
        parent::setUp();
        
        // Create test users
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        
        $this->editor_user_id = $this->factory->user->create([
            'role' => 'editor'
        ]);
        
        // Mock plugin settings
        update_option('nuclen_api_key', 'test_api_key_123');
        update_option('nuclen_app_password', 'test_app_password');
    }

    public function tearDown(): void {
        delete_option('nuclen_api_key');
        delete_option('nuclen_app_password');
        parent::tearDown();
    }

    /**
     * Test trigger generation AJAX endpoint
     */
    public function test_trigger_generation_ajax() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
        $_POST['action'] = 'nuclen_trigger_generation';
        $_POST['post_id'] = $this->factory->post->create();
        $_POST['generation_type'] = 'quiz';

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_trigger_generation');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should return valid JSON response
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('success', $data);
    }

    /**
     * Test fetch app updates AJAX endpoint
     */
    public function test_fetch_app_updates_ajax() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
        $_POST['action'] = 'nuclen_fetch_app_updates';

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_fetch_app_updates');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should return valid JSON response
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('success', $data);
    }

    /**
     * Test get posts count AJAX endpoint
     */
    public function test_get_posts_count_ajax() {
        wp_set_current_user($this->admin_user_id);
        
        // Create some test posts
        $this->factory->post->create_many(3);
        
        $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
        $_POST['action'] = 'nuclen_get_posts_count';

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_get_posts_count');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should return valid JSON response with count
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('success', $data);
        
        if ($data['success']) {
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('count', $data['data']);
            $this->assertIsInt($data['data']['count']);
        }
    }

    /**
     * Test dismiss pointer AJAX endpoint
     */
    public function test_dismiss_pointer_ajax() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['nuclen_dismiss_pointer_nonce'] = wp_create_nonce('nuclen_dismiss_pointer_nonce');
        $_POST['action'] = 'nuclen_dismiss_pointer';
        $_POST['pointer'] = 'test_pointer';

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_dismiss_pointer');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should return success response
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('success', $data);
    }

    /**
     * Test export optin AJAX endpoint
     */
    public function test_export_optin_ajax() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['action'] = 'nuclen_export_optin';
        $_POST['_wpnonce'] = wp_create_nonce('nuclen_export_optin');

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_export_optin');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should return CSV data or error response
        $this->assertNotEmpty($response, 'Response should not be empty');
        
        // Check if it's CSV data or JSON error response
        if (strpos($response, 'Content-Type: text/csv') !== false) {
            $this->assertStringContainsString('text/csv', $response);
        } else {
            $data = json_decode($response, true);
            $this->assertNotNull($data, 'If not CSV, should be valid JSON');
        }
    }

    /**
     * Test AJAX endpoints with insufficient privileges
     */
    public function test_ajax_endpoints_insufficient_privileges() {
        wp_set_current_user($this->editor_user_id);
        
        $restricted_actions = [
            'nuclen_trigger_generation',
            'nuclen_fetch_app_updates',
            'nuclen_get_posts_count'
        ];

        foreach ($restricted_actions as $action) {
            $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
            $_POST['action'] = $action;

            ob_start();
            
            try {
                do_action("wp_ajax_{$action}");
            } catch (WPAjaxDieStopException $e) {
                // Expected for AJAX calls
            }
            
            $response = ob_get_clean();
            
            // Should return permission error or handle gracefully
            if (!empty($response)) {
                $data = json_decode($response, true);
                if ($data && isset($data['success']) && !$data['success']) {
                    $this->assertStringContainsString('permission', strtolower($data['data'] ?? ''));
                }
            }
        }
    }

    /**
     * Test AJAX endpoints without authentication
     */
    public function test_ajax_endpoints_no_authentication() {
        // Not logged in
        wp_set_current_user(0);
        
        $ajax_actions = [
            'nuclen_trigger_generation',
            'nuclen_fetch_app_updates',
            'nuclen_get_posts_count',
            'nuclen_dismiss_pointer'
        ];

        foreach ($ajax_actions as $action) {
            $_POST['action'] = $action;
            
            ob_start();
            
            try {
                do_action("wp_ajax_{$action}");
            } catch (WPAjaxDieStopException $e) {
                // Expected for AJAX calls
            }
            
            $response = ob_get_clean();
            
            // Should handle unauthenticated requests gracefully
            $this->assertNotEmpty($response, "Action {$action} should return response for unauthenticated user");
        }
    }

    /**
     * Test AJAX input validation
     */
    public function test_ajax_input_validation() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
        $_POST['action'] = 'nuclen_trigger_generation';
        $_POST['post_id'] = 'invalid_post_id'; // Invalid input
        $_POST['generation_type'] = '<script>alert("xss")</script>'; // XSS attempt

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_trigger_generation');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should handle invalid input gracefully
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        
        if (isset($data['success']) && !$data['success']) {
            $this->assertArrayHasKey('data', $data);
            $this->assertStringContainsString('invalid', strtolower($data['data']));
        }
    }

    /**
     * Test AJAX error handling
     */
    public function test_ajax_error_handling() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
        $_POST['action'] = 'nuclen_trigger_generation';
        $_POST['post_id'] = 999999; // Non-existent post
        $_POST['generation_type'] = 'quiz';

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_trigger_generation');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should return proper error response
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('success', $data);
        
        if (!$data['success']) {
            $this->assertArrayHasKey('data', $data);
            $this->assertNotEmpty($data['data']);
        }
    }

    /**
     * Test concurrent AJAX requests
     */
    public function test_concurrent_ajax_requests() {
        wp_set_current_user($this->admin_user_id);
        
        $post_id = $this->factory->post->create();
        
        $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
        $_POST['action'] = 'nuclen_trigger_generation';
        $_POST['post_id'] = $post_id;
        $_POST['generation_type'] = 'quiz';

        $responses = [];
        
        // Simulate multiple concurrent requests
        for ($i = 0; $i < 3; $i++) {
            ob_start();
            
            try {
                do_action('wp_ajax_nuclen_trigger_generation');
            } catch (WPAjaxDieStopException $e) {
                // Expected for AJAX calls
            }
            
            $responses[] = ob_get_clean();
        }

        // All responses should be valid
        foreach ($responses as $response) {
            $data = json_decode($response, true);
            $this->assertNotNull($data, 'All concurrent responses should be valid JSON');
            $this->assertArrayHasKey('success', $data);
        }
    }
}