# Developer Guide

This repository contains the **Nuclear Engagement** WordPress plugin and its source files.
Follow the guidelines below when making changes.

## Code Style

### PHP
- Use tabs for indentation. Spaces are not allowed.
- Follow WordPress Coding Standards. Run `composer lint` to check.

-### TypeScript
- Use tabs for indentation. Spaces are not allowed.
- Source files live under `src/` and are built into the `nuclear-engagement` plugin directory.
- Do not use jQuery.

## WordPress Plugin Best Practices
- Sanitize all user input and escape output.
- Keep code translation-ready using i18n functions.
- Use nonces to secure forms and actions.
- Document custom hooks and filters.

## Build & Test

1. Install PHP dependencies with `composer install` if not already done.
2. Install Node dependencies with `npm install` if needed.
3. Run **lint** and **tests** before committing:
   ```bash
   composer lint
   composer test
   ```
4. If you modify TypeScript, rebuild JavaScript with:
   ```bash
   npm run build
   ```

## Pull Requests

- Keep commits focused and descriptive.
- Ensure `composer lint` and `composer test` pass before submitting.

## Development Notes

- When files grow too large, refactor them into multiple files for maintainability.
- Always edit the TypeScript source under `src/` rather than the built JavaScript in `nuclear-engagement`.
- Do not remove existing functionality unless explicitly requested or approved by the user.

## Documentation

- Record changes to plugin behavior in `nuclear-engagement/README.txt`.
- Add each item under the latest version heading in the `== Changelog ==` section.

