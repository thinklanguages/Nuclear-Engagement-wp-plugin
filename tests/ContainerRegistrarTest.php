<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Container;
use NuclearEngagement\ContainerRegistrar;
use NuclearEngagement\SettingsRepository;

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Container.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsRepository.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Defaults.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/ContainerRegistrar.php';

class ContainerRegistrarTest extends TestCase {
    private Container $container;
    private SettingsRepository $settings;

    protected function setUp(): void {
        SettingsRepository::reset_for_tests();
        $this->container = Container::getInstance();
        $this->container->reset();
        $this->settings = SettingsRepository::get_instance();
    }

    public function test_register_adds_expected_services_and_controllers(): void {
        ContainerRegistrar::register($this->container, $this->settings);

        $expected = [
            'settings',
            'admin_notice_service',
            'logging_service',
            'remote_request',
            'api_response_handler',
            'remote_api',
            'content_storage',
            'generation_poller',
            'auto_generation_queue',
            'auto_generation_scheduler',
            'publish_generation_handler',
            'auto_generation_service',
            'generation_service',
            'pointer_service',
            'posts_query_service',
            'dashboard_data_service',
            'version_service',
            'generate_controller',
            'updates_controller',
            'pointer_controller',
            'posts_count_controller',
            'content_controller',
            'optin_export_controller',
        ];

        foreach ($expected as $id) {
            $this->assertTrue(
                $this->container->has($id),
                "Service {$id} not registered"
            );
        }
    }
}
