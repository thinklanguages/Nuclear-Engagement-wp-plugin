# Admin Module

This directory contains all administrative functionality for the Nuclear Engagement plugin.

## Structure

- **Controller/** - AJAX and export controllers for handling admin requests
  - `Ajax/` - AJAX controllers for generate, pointer, posts count, and updates
  - `OptinExportController.php` - Handles optin data exports

- **Setup/** - Setup handlers for plugin configuration
  - `AppPasswordHandler.php` - Manages application password authentication
  - `ConnectHandler.php` - Handles API connection setup

- **Traits/** - Reusable admin functionality traits
  - Admin UI traits (assets, menu, metaboxes)
  - Settings management traits (collect, persist, sanitize)
  - Security traits for setup handlers

- **Core Files**
  - `Admin.php` - Main admin class orchestrating all admin functionality
  - `Dashboard.php` - Dashboard page implementation
  - `Settings.php` - Settings page management
  - `Setup.php` - Setup wizard implementation
  - `Onboarding.php` - User onboarding flow

## Assets

- **css/** - Admin styles (dashboard and general admin CSS)
- **js/** - Admin JavaScript (compiled from TypeScript sources)

## Key Features

1. **Auto-generation system** - Automated content generation management
2. **Settings management** - Comprehensive settings with sanitization
3. **Dashboard analytics** - Usage statistics and inventory tracking
4. **Setup wizard** - Guided plugin configuration
5. **Onboarding pointers** - Interactive user guidance