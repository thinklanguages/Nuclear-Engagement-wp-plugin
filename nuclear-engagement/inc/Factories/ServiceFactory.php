<?php
/**
 * ServiceFactory.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Factories
 */

declare(strict_types=1);
/**
 * File: inc/Factories/ServiceFactory.php
 *
 * Factory for creating services with proper dependencies.
 *
 * @package NuclearEngagement\Factories
 */

namespace NuclearEngagement\Factories;

use NuclearEngagement\Core\Container;
use NuclearEngagement\Contracts\LoggerInterface;
use NuclearEngagement\Contracts\CacheInterface;
use NuclearEngagement\Contracts\ValidatorInterface;
use NuclearEngagement\Services\ServiceLayer\PostService;
use NuclearEngagement\Repositories\PostRepository;
use NuclearEngagement\Events\EventDispatcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for creating services with dependency injection.
 */
class ServiceFactory {

	/** @var Container */
	private Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Create post service with dependencies.
	 *
	 * @return PostService Post service instance.
	 */
	public function create_post_service(): PostService {
		return new PostService(
			$this->container->get( PostRepository::class ),
			$this->container->get( LoggerInterface::class ),
			$this->container->get( ValidatorInterface::class ),
			$this->container->get( EventDispatcher::class )
		);
	}

	/**
	 * Create post repository with dependencies.
	 *
	 * @return PostRepository Post repository instance.
	 */
	public function create_post_repository(): PostRepository {
		return new PostRepository(
			$this->container->get( CacheInterface::class ),
			$this->container->get( LoggerInterface::class )
		);
	}

	/**
	 * Register all services in container.
	 */
	public function register_services(): void {
		// Register repository.
		$this->container->singleton(
			PostRepository::class,
			function ( Container $container ) {
				return $this->create_post_repository();
			}
		);

		// Register service layer.
		$this->container->singleton(
			PostService::class,
			function ( Container $container ) {
				return $this->create_post_service();
			}
		);

		// Register event dispatcher.
		$this->container->singleton(
			EventDispatcher::class,
			function ( Container $container ) {
				return new EventDispatcher(
					$container->get( LoggerInterface::class )
				);
			}
		);
	}
}
