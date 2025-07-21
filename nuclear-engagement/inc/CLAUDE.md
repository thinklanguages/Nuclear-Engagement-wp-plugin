# Core Includes Directory

This directory contains the core business logic, services, and infrastructure for the Nuclear Engagement plugin.

## Directory Structure

### Core Infrastructure (`Core/`)
- **Plugin bootstrapping** - `Plugin.php`, `PluginBootstrap.php`
- **Service management** - `ServiceContainer.php`, `ServiceRegistry.php`, `ServiceDiscovery.php`
- **Logging** - Simple error logging via WordPress error_log
- **Background processing** - `BackgroundProcessor.php`, `JobQueue.php`, `JobHandler.php`
- **Module system** - `LazyModuleLoader.php` (primary), `ModuleLoader.php` (legacy), module interfaces and registry
- **Database** - `DatabaseMigrations.php`, query optimization
- **Settings** - `SettingsRepository.php`, `SettingsCache.php`, `SettingsSanitizer.php`
- **Performance** - `PerformanceMonitor.php`, `CacheManager.php`

### Business Logic

#### Contracts (`Contracts/`)
- Interface definitions for cache, logger, repository, and validator implementations

#### Services (`Services/`)
- **Generation services** - Content generation, polling, auto-generation
- **Remote API** - API communication and response handling
- **Theme system** - Theme loading, CSS generation, migration
- **Style generators** - Dynamic CSS generation for components
- **Data services** - Dashboard data, posts queries, content storage
- **Admin services** - Notices, pointers, setup

#### Modules (`Modules/`)
- **Quiz** - Quiz functionality with admin, service, and shortcode components
- **Summary** - Summary generation with metabox and view components
- **TOC** - Table of Contents with heading extraction, rendering, and caching

#### Security (`Security/`)
- API user management, CSS sanitization, rate limiting, token management

#### Repositories (`Repositories/`)
- Data access layer for posts, themes, opt-ins with abstract base

#### Utilities (`Utils/`)
- Helper functions for cache, database, response, security, validation

### Supporting Components

- **Events** - Event system with dispatcher
- **Exceptions** - Custom exception types
- **Factories** - Service factory pattern
- **Handlers** - Base setup handler
- **Helpers** - Form sanitization, validation, settings helpers
- **Models** - Theme model
- **Requests/Responses** - Request/response objects for API
- **Traits** - Reusable functionality for settings, cache, security
- **Validators** - Input validation

## Key Features

1. **Modular Architecture** - Clean separation of concerns with module system
2. **Service Container** - Dependency injection and service management
3. **Background Processing** - Asynchronous job queue system
4. **Robust Logging** - Centralized logging for debugging and monitoring
5. **Performance Optimization** - Caching, lazy loading, query optimization
6. **Security First** - Rate limiting, token management, input sanitization