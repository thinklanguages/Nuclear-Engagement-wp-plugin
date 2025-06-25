<?php
declare(strict_types=1);
/**
 * File: includes/ContainerRegistrar.php
 *
 * Registers services and controllers in the DI container.
 */

namespace NuclearEngagement;

use NuclearEngagement\Services\{GenerationService, RemoteApiService, ContentStorageService, PointerService, PostsQueryService, AutoGenerationService, AutoGenerationQueue, AutoGenerationScheduler, GenerationPoller, PublishGenerationHandler, VersionService, DashboardDataService};
use NuclearEngagement\Admin\Controller\Ajax\{GenerateController, UpdatesController, PointerController, PostsCountController};
use NuclearEngagement\Admin\Controller\OptinExportController;
use NuclearEngagement\Front\Controller\Rest\ContentController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContainerRegistrar {
    public static function register( Container $container, SettingsRepository $settings ): void {
        $container->register( 'settings', static fn() => $settings );

        $container->register( 'remote_api', static fn( $c ) => new RemoteApiService( $c->get( 'settings' ) ) );
        $container->register( 'content_storage', static fn( $c ) => new ContentStorageService( $c->get( 'settings' ) ) );

                $container->register(
                        'generation_poller',
                        static fn( $c ) => new GenerationPoller(
                                $c->get( 'settings' ),
                                $c->get( 'remote_api' ),
                                $c->get( 'content_storage' )
                        )
                );

                $container->register(
                        'auto_generation_queue',
                        static fn( $c ) => new AutoGenerationQueue(
                                $c->get( 'remote_api' ),
                                $c->get( 'content_storage' ),
                                new PostDataFetcher()
                        )
                );

                $container->register(
                        'auto_generation_scheduler',
                        static fn( $c ) => new AutoGenerationScheduler(
                                $c->get( 'generation_poller' )
                        )
                );

        $container->register(
            'publish_generation_handler',
            static fn( $c ) => new PublishGenerationHandler(
                $c->get( 'settings' )
            )
        );

                $container->register(
                        'auto_generation_service',
                        static fn( $c ) => new AutoGenerationService(
                                $c->get( 'settings' ),
                                $c->get( 'auto_generation_queue' ),
                                $c->get( 'auto_generation_scheduler' ),
                                $c->get( 'publish_generation_handler' )
                        )
                );

        $container->register(
            'generation_service',
            static fn( $c ) => new GenerationService(
                $c->get( 'settings' ),
                $c->get( 'remote_api' ),
                $c->get( 'content_storage' )
            )
        );

        $container->register( 'pointer_service', static fn() => new PointerService() );
                $container->register( 'posts_query_service', static fn() => new PostsQueryService() );
                $container->register( 'dashboard_data_service', static fn() => new DashboardDataService() );
                $container->register( 'version_service', static fn() => new VersionService() );

        $container->register( 'generate_controller', static fn( $c ) => new GenerateController( $c->get( 'generation_service' ) ) );
        $container->register( 'updates_controller', static fn( $c ) => new UpdatesController( $c->get( 'remote_api' ), $c->get( 'content_storage' ) ) );
        $container->register( 'pointer_controller', static fn( $c ) => new PointerController( $c->get( 'pointer_service' ) ) );
        $container->register( 'posts_count_controller', static fn( $c ) => new PostsCountController( $c->get( 'posts_query_service' ) ) );
                $container->register(
                        'content_controller',
                        static fn( $c ) => new ContentController(
                                $c->get( 'content_storage' ),
                                $c->get( 'settings' )
                        )
                );
        $container->register( 'optin_export_controller', static fn() => new OptinExportController() );
    }
}
