<?php
/**
 * ContainerRegistrar.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);
/**
	* File: includes/ContainerRegistrar.php
	*
	* Registers services and controllers in the DI container.
	*/

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Services\{GenerationService, RemoteApiService, ContentStorageService, PointerService, PostsQueryService, AutoGenerationService, AutoGenerationQueue, AutoGenerationScheduler, GenerationPoller, PublishGenerationHandler, VersionService, DashboardDataService};
use NuclearEngagement\Services\{AdminNoticeService, LoggingService};
use NuclearEngagement\Services\PostDataFetcher;
use NuclearEngagement\Services\Remote\{RemoteRequest, ApiResponseHandler};
use NuclearEngagement\Admin\Controller\Ajax\{GenerateController, UpdatesController, PointerController, PostsCountController};
use NuclearEngagement\Admin\Controller\OptinExportController;
use NuclearEngagement\Front\Controller\Rest\ContentController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContainerRegistrar {
	public static function register( ServiceContainer $container, SettingsRepository $settings ): void {
			$container->register( 'settings', static fn() => $settings );

			self::register_base_services( $container );
			self::register_remote_services( $container );
			self::register_generation_services( $container );
			self::register_utility_services( $container );
			self::register_controllers( $container );
	}

	private static function register_base_services( ServiceContainer $container ): void {
			$container->register( 'admin_notice_service', static fn() => new AdminNoticeService() );
			$container->register( 'logging_service', static fn( ServiceContainer $c ) => new LoggingService( $c->get( 'admin_notice_service' ) ) );
	}

	private static function register_remote_services( ServiceContainer $container ): void {
			$container->register( 'remote_request', static fn( ServiceContainer $c ) => new RemoteRequest( $c->get( 'settings' ) ) );
			$container->register( 'api_response_handler', static fn() => new ApiResponseHandler() );
			$container->register( 'remote_api', static fn( ServiceContainer $c ) => new RemoteApiService( $c->get( 'settings' ), $c->get( 'remote_request' ), $c->get( 'api_response_handler' ) ) );
			$container->register( 'content_storage', static fn( ServiceContainer $c ) => new ContentStorageService( $c->get( 'settings' ) ) );
	}

	private static function register_generation_services( ServiceContainer $container ): void {
			$container->register(
				'generation_poller',
				static fn( ServiceContainer $c ) => new GenerationPoller(
					$c->get( 'settings' ),
					$c->get( 'remote_api' ),
					$c->get( 'content_storage' )
				)
			);

			$container->register(
				'auto_generation_queue',
				static fn( ServiceContainer $c ) => new AutoGenerationQueue(
					$c->get( 'remote_api' ),
					$c->get( 'content_storage' ),
					new PostDataFetcher()
				)
			);

			$container->register(
				'auto_generation_scheduler',
				static fn( ServiceContainer $c ) => new AutoGenerationScheduler(
					$c->get( 'generation_poller' )
				)
			);

			$container->register(
				'publish_generation_handler',
				static fn( ServiceContainer $c ) => new PublishGenerationHandler(
					$c->get( 'settings' )
				)
			);

			$container->register(
				'auto_generation_service',
				static fn( ServiceContainer $c ) => new AutoGenerationService(
					$c->get( 'settings' ),
					$c->get( 'auto_generation_queue' ),
					$c->get( 'auto_generation_scheduler' ),
					$c->get( 'publish_generation_handler' )
				)
			);

			$container->register(
				'generation_service',
				static fn( ServiceContainer $c ) => new GenerationService(
					$c->get( 'settings' ),
					$c->get( 'remote_api' ),
					$c->get( 'content_storage' ),
					new PostDataFetcher()
				)
			);
	}

	private static function register_utility_services( ServiceContainer $container ): void {
			$container->register( 'pointer_service', static fn() => new PointerService() );
			$container->register( 'posts_query_service', static fn() => new PostsQueryService() );
			$container->register( 'dashboard_data_service', static fn() => new DashboardDataService() );
			$container->register( 'version_service', static fn() => new VersionService() );
	}

	private static function register_controllers( ServiceContainer $container ): void {
			$container->register( 'generate_controller', static fn( ServiceContainer $c ) => new GenerateController( $c->get( 'generation_service' ) ) );
			$container->register( 'updates_controller', static fn( ServiceContainer $c ) => new UpdatesController( $c->get( 'remote_api' ), $c->get( 'content_storage' ) ) );
			$container->register( 'pointer_controller', static fn( ServiceContainer $c ) => new PointerController( $c->get( 'pointer_service' ) ) );
			$container->register( 'posts_count_controller', static fn( ServiceContainer $c ) => new PostsCountController( $c->get( 'posts_query_service' ) ) );
			$container->register(
				'content_controller',
				static fn( ServiceContainer $c ) => new ContentController(
					$c->get( 'content_storage' ),
					$c->get( 'settings' )
				)
			);
			$container->register( 'optin_export_controller', static fn() => new OptinExportController() );
	}
}
