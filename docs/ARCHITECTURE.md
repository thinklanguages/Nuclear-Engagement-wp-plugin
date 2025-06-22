# Nuclear Engagement Architecture

This document records major architectural decisions and refactoring efforts.

## Module Overview

The plugin is organized by concern:

- `admin/` handles all wp-admin functionality and bundles TypeScript under `src/admin`.
- `front/` contains public-facing assets and `src/front` for TypeScript sources.
- `includes/` holds framework-agnostic services and core classes.
- `modules/` groups optional features such as the Table of Contents.

Each folder stays under the 300 LOC guideline described in `nuclear-engagement/AGENTS.md`.

## Settings Sanitization Refactor

The settings sanitization logic originally lived inside `SettingsRepository`. To
simplify the repository and isolate responsibilities, sanitization is now
handled by a dedicated `SettingsSanitizer` class under `includes/`.

- `SettingsRepository` now delegates sanitization to `SettingsSanitizer` when
  saving settings.
- All previous helper methods (`sanitize_heading_levels`, etc.) have been moved
  into the new class.
- The plugin autoloader maps `NuclearEngagement\SettingsSanitizer` so the class
  loads automatically.
- Unit tests reference the new class for sanitization routines.

This refactor keeps the repository focused on persistence logic while making the
sanitization rules easier to maintain and test in isolation.
## Slim Bootloader

To align with the maintainable plugin structure, the plugin entry file now only includes the header and requires `bootstrap.php`. Initialization logic—including constant definitions, autoloader registration and hook setup—resides in the bootstrap file. This keeps `nuclear-engagement.php` under 50 lines for better clarity.

## TOC Module Decomposition

`Nuclen_TOC_Render` originally spanned more than 450 lines and mixed shortcode
handling with asset management and content filtering. To stay within the
300 LOC guideline, the functionality has been split into dedicated classes:

- `Nuclen_TOC_Render` – handles the `[nuclear_engagement_toc]` shortcode.
- `Nuclen_TOC_Assets` – registers and enqueues front-end assets.
- `Nuclen_TOC_Headings` – injects unique IDs into post headings.
- `Nuclen_TOC_View` – builds the HTML markup for the TOC.

The loader now requires these files and spins up each class. This keeps
responsibilities narrow and makes future maintenance easier.


## Settings Cache Extraction

`SettingsRepository` previously managed all option caching and invalidation logic in addition to reading and writing settings. To keep the repository focused on persistence, these cache duties now live in a dedicated `SettingsCache` class.

- `SettingsCache` handles object cache operations and registers hooks to invalidate the cache when the option updates.
- `SettingsRepository` composes the new cache class and delegates calls such as `invalidate_cache()` to it.
- The autoloader maps `NuclearEngagement\SettingsCache`.

This keeps `SettingsRepository` under the 300 LOC limit and reduces its method count for easier maintenance.

## Typed Accessors Extraction

`SettingsRepository` still contained numerous typed getter and setter helpers
that inflated its line count. These wrappers (`get_string()`, `set_bool()`, etc.)
now live in a lightweight `SettingsAccessTrait`. The repository simply uses the
trait, keeping the core persistence logic below 300 lines while preserving the
public API.

## Shortcode Handler Classes

The front-end `ShortcodesTrait` still mixed shortcode rendering logic with
settings retrieval. To keep responsibilities narrow, this logic now resides in
two dedicated classes: `QuizShortcode` and `SummaryShortcode`. Each class
registers its shortcode and builds output using the corresponding view class.
`ShortcodesTrait` merely instantiates these handlers and delegates calls.
The autoloader maps the new classes under the `Front` namespace.

## Container Registration Extraction

`Plugin` previously contained a lengthy `initializeContainer()` method that
registered all services and controllers. This pushed the class over the
15-method threshold. The logic now lives in a dedicated
`ContainerRegistrar` class with a static `register()` method. `Plugin`
instantiates the container and delegates the registration work to this new
class, keeping the main class focused on coordinating hooks.

## Pending Changes Trait

The repository still contained several utility methods for managing pending
settings updates. To simplify the core class and keep the method count below
15, these helpers now live in a small `PendingSettingsTrait`.
`SettingsRepository` uses the trait to expose the same public API while keeping
its own implementation lean. The autoloader maps the new trait.

## Onboarding Pointer Definitions Extraction

The original `Onboarding` class bundled a huge array of pointer definitions directly in the `enqueue_nuclen_onboarding_pointers()` method. The file exceeded 240 lines and the method itself was difficult to read. The pointer data now lives in a dedicated `OnboardingPointers` class under `admin/`. `Onboarding` simply pulls the definitions from this new class. This keeps the main class concise and makes the pointer data easier to maintain.

## Opt-in Export Controller

`OptinData` previously registered its CSV export hooks directly and `Plugin`
included a proxy method to trigger the export. This duplicated logic and caused
side effects when the class file loaded. The export hooks now point to a new
`OptinExportController` class that simply delegates to `OptinData::handle_export()`.

`ContainerRegistrar` registers the controller so `Plugin` can obtain it and
attach its `handle()` method to both `admin_post_nuclen_export_optin` and
`wp_ajax_nuclen_export_optin`. The automatic invocation of `OptinData::init()`
at the end of the file was removed, and hook registration occurs explicitly in
`Plugin::nuclen_load_dependencies()`.

This keeps responsibilities clear and avoids unintended behavior when files are
loaded.

## Setup API Service

`SetupHandlersTrait` previously communicated with the SaaS directly to validate
the API key and send the generated WordPress credentials. To keep the trait
focused purely on form handling, these network calls have been extracted to a
dedicated `SetupService` under `includes/Services/`.

- `Setup` instantiates the new service and exposes it via
  `nuclen_get_setup_service()`.
- `SetupHandlersTrait` now delegates API validation and credential upload to this
  service.
- The autoloader maps `NuclearEngagement\Services\SetupService` for automatic
  loading.

Centralizing all setup-related API logic makes the trait easier to test and
keeps the codebase within the single-responsibility guidelines.

## Logging Service Extraction

Utility methods for locating and writing to the plugin log file lived in `Utils`.
To keep that helper focused on page rendering and query helpers, the logic now
resides in a dedicated `LoggingService` under `includes/Services/`.

- `LoggingService::get_log_file_info()` returns the log directory, file path and URL.
- `LoggingService::log()` handles file creation, rotation and message appending.
- All calls to `$this->utils->nuclen_log()` have been replaced with the static
  service method.
- The autoloader maps `NuclearEngagement\Services\LoggingService`.

The service now checks that the uploads directory and log file are writable
before attempting to write. When writing fails, it falls back to PHP's
`error_log()` and registers an admin notice via the `admin_notices` hook so
administrators are alerted to permission issues.

This keeps `Utils` slim while ensuring logging responsibilities are centralized.

## Posts Query Service

Query-building helpers previously lived in `Utils`, which muddled responsibilities.
These routines now reside in a dedicated `PostsQueryService` used by
`PostsCountController` to fetch post IDs and counts for generation screens.

- `PostsQueryService::buildQueryArgs()` constructs WP_Query arguments from a
  `PostsCountRequest` object.
- `PostsQueryService::getPostsCount()` returns the matching post IDs and count.
- Unused helper methods were removed from `Utils`.

This keeps query logic contained while leaving `Utils` focused on rendering tasks.

## jQuery Removal from Onboarding

The original onboarding pointer script relied on jQuery and the `wp-pointer` plugin.
To meet the "no jQuery" guideline, the logic now uses vanilla TypeScript.
`onboarding-pointers.js` manually positions `.wp-pointer` elements and sends
dismissal requests via `fetch`. The PHP loader no longer enqueues the
`wp-pointer` script or lists jQuery as a dependency—only the style sheet
remains. This reduces dependencies and keeps the admin bundle lightweight.

## jQuery Removal from Settings Display

The settings page used jQuery to toggle the "Show TOC content" option when
the toggle button was enabled. To follow the no-jQuery guideline, this inline
script now uses vanilla JavaScript. The behavior remains the same but without
relying on the jQuery library.

## Native Color Inputs

The plugin previously relied on the jQuery-based `wp-color-picker` to style
color fields on the settings page. To further reduce dependencies, these
fields now use the browser's native `<input type="color">` element. The
`SettingsColorPickerTrait` no longer enqueues `wp-color-picker` scripts and is
a no-op kept for backward compatibility.

## Version Constant from Plugin Header

`bootstrap.php` no longer hardcodes the plugin version. Instead it loads the
plugin header with `get_plugin_data()` and defines `NUCLEN_PLUGIN_VERSION` from
the returned value. All other code references this constant so updating the
header automatically propagates the new version.

