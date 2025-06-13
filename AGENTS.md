# Developer Guide

This repository contains the **Nuclear Engagement** WordPress plugin and its source files.
Follow the guidelines below when making changes.

## Code Style

### PHP
- Use 4 spaces for indentation.
- Follow WordPress Coding Standards. Run `composer lint` to check.

### TypeScript
- Use 2 spaces for indentation.
- Source files live under `src/` and are built into the `nuclear-engagement` plugin directory.
- Do not use jQuery.

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

