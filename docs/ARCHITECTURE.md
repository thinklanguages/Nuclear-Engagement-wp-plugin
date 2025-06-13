# Nuclear Engagement Architecture

This document records major architectural decisions and refactoring efforts.

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
