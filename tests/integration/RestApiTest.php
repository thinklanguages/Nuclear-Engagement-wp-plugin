<?php

class RestApiIntegrationTest extends WP_UnitTestCase {

    private $admin_user_id;
    private $app_password;
    private $api_key;

    public function setUp(): void {
        parent::setUp();
        
        // Create admin user for testing
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        
        // Mock plugin settings
        update_option('nuclen_api_key', 'test_api_key_123');
        update_option('nuclen_app_password', 'test_app_password');
        
        $this->api_key = 'test_api_key_123';
        $this->app_password = 'test_app_password';
    }

    public function tearDown(): void {
        delete_option('nuclen_api_key');
        delete_option('nuclen_app_password');
        parent::tearDown();
    }

    /**
     * Test REST API endpoint registration
     */
    public function test_rest_api_endpoint_registered() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/nuclear-engagement/v1/receive-content', $routes);
        
        $route = $routes['/nuclear-engagement/v1/receive-content'];
        $this->assertCount(1, $route);
        $this->assertEquals(['POST'], $route[0]['methods']);
    }

    /**
     * Test REST API authentication with app password
     */
    public function test_rest_api_authentication_with_app_password() {
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/receive-content');
        $request->set_header('X-WP-App-Password', $this->app_password);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'post_id' => 1,
            'type' => 'quiz',
            'content' => ['question' => 'Test question']
        ]));

        $response = rest_get_server()->dispatch($request);
        
        // Should not return authentication error
        $this->assertNotEquals(401, $response->get_status());
        $this->assertNotEquals(403, $response->get_status());
    }

    /**
     * Test REST API authentication failure
     */
    public function test_rest_api_authentication_failure() {
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/receive-content');
        $request->set_header('Content-Type', 'application/json');
        // No authentication headers
        
        $response = rest_get_server()->dispatch($request);
        
        // Should return authentication error
        $this->assertContains($response->get_status(), [401, 403]);
    }

    /**
     * Test REST API content validation
     */
    public function test_rest_api_content_validation() {
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/receive-content');
        $request->set_header('X-WP-App-Password', $this->app_password);
        $request->set_header('Content-Type', 'application/json');
        
        // Invalid content - missing required fields
        $request->set_body(json_encode([
            'invalid' => 'data'
        ]));

        $response = rest_get_server()->dispatch($request);
        
        // Should return validation error
        $this->assertEquals(400, $response->get_status());
    }

    /**
     * Test AJAX endpoint registration
     */
    public function test_ajax_endpoints_registered() {
        global $wp_filter;
        
        $ajax_actions = [
            'wp_ajax_nuclen_trigger_generation',
            'wp_ajax_nuclen_fetch_app_updates',
            'wp_ajax_nuclen_get_posts_count',
            'wp_ajax_nuclen_dismiss_pointer',
            'wp_ajax_nuclen_export_optin'
        ];

        foreach ($ajax_actions as $action) {
            $this->assertTrue(
                isset($wp_filter[$action]),
                "AJAX action {$action} should be registered"
            );
        }
    }

    /**
     * Test AJAX authentication with nonce
     */
    public function test_ajax_authentication_with_nonce() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['nuclen_admin_ajax_nonce'] = wp_create_nonce('nuclen_admin_ajax_nonce');
        $_POST['action'] = 'nuclen_get_posts_count';

        // Start output buffering to catch AJAX response
        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_get_posts_count');
        } catch (WPAjaxDieStopException $e) {
            // This is expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should not return nonce verification error
        $this->assertStringNotContainsString('Invalid nonce', $response);
        $this->assertStringNotContainsString('Nonce verification failed', $response);
    }

    /**
     * Test AJAX authentication failure
     */
    public function test_ajax_authentication_failure() {
        wp_set_current_user($this->admin_user_id);
        
        $_POST['nuclen_admin_ajax_nonce'] = 'invalid_nonce';
        $_POST['action'] = 'nuclen_get_posts_count';

        ob_start();
        
        try {
            do_action('wp_ajax_nuclen_get_posts_count');
        } catch (WPAjaxDieStopException $e) {
            // Expected for AJAX calls
        }
        
        $response = ob_get_clean();
        
        // Should return authentication error
        $this->assertTrue(
            strpos($response, 'Invalid nonce') !== false ||
            strpos($response, 'Nonce verification failed') !== false ||
            strpos($response, 'Authentication failed') !== false
        );
    }

    /**
     * Test admin post handlers registration
     */
    public function test_admin_post_handlers_registered() {
        global $wp_filter;
        
        $admin_post_actions = [
            'admin_post_nuclen_connect_app',
            'admin_post_nuclen_generate_app_password',
            'admin_post_nuclen_reset_api_key',
            'admin_post_nuclen_reset_wp_app_connection',
            'admin_post_nuclen_export_optin'
        ];

        foreach ($admin_post_actions as $action) {
            $this->assertTrue(
                isset($wp_filter[$action]),
                "Admin post action {$action} should be registered"
            );
        }
    }

    /**
     * Test rate limiting functionality
     */
    public function test_api_rate_limiting() {
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/receive-content');
        $request->set_header('X-WP-App-Password', $this->app_password);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'post_id' => 1,
            'type' => 'quiz',
            'content' => ['question' => 'Test question']
        ]));

        // Make multiple rapid requests to test rate limiting
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = rest_get_server()->dispatch($request);
        }

        // Check if any requests were rate limited
        $rate_limited = false;
        foreach ($responses as $response) {
            if ($response->get_status() === 429) {
                $rate_limited = true;
                break;
            }
        }

        // Rate limiting should kick in for rapid requests
        $this->assertTrue($rate_limited, 'Rate limiting should be enforced');
    }

    /**
     * Test API input sanitization
     */
    public function test_api_input_sanitization() {
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/receive-content');
        $request->set_header('X-WP-App-Password', $this->app_password);
        $request->set_header('Content-Type', 'application/json');
        
        // Malicious input with XSS attempts
        $malicious_content = [
            'post_id' => 1,
            'type' => 'quiz',
            'content' => [
                'question' => '<script>alert("xss")</script>Test question',
                'answer' => 'javascript:alert("xss")'
            ]
        ];
        
        $request->set_body(json_encode($malicious_content));

        $response = rest_get_server()->dispatch($request);
        
        // Should either sanitize input or reject malicious content
        $this->assertNotEquals(500, $response->get_status(), 'Should handle malicious input gracefully');
        
        if ($response->get_status() === 200) {
            $data = $response->get_data();
            // If accepted, content should be sanitized
            $this->assertStringNotContainsString('<script>', json_encode($data));
            $this->assertStringNotContainsString('javascript:', json_encode($data));
        }
    }

    /**
     * Test API error handling
     */
    public function test_api_error_handling() {
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/receive-content');
        $request->set_header('X-WP-App-Password', $this->app_password);
        $request->set_header('Content-Type', 'application/json');
        
        // Invalid JSON
        $request->set_body('invalid json content');

        $response = rest_get_server()->dispatch($request);
        
        // Should return proper error response
        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('code', $response->get_data());
        $this->assertArrayHasKey('message', $response->get_data());
    }
}