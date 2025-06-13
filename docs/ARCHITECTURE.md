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


