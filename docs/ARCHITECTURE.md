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


