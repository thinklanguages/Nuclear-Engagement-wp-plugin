# Hooks Reference

This plugin exposes several WordPress hooks that allow developers to
customise behaviour or integrate additional functionality. Hooks fall
into two groups: **actions** (events you can run code on) and
**filters** (values you can modify).

## Action Hooks

| Hook | Parameters | Usage |
| --- | --- | --- |
| `nuclen_start_generation` | `int $post_id`, `string $workflow_type` | Fired by WP‑Cron to kick off generation of a quiz or summary for a post. You can hook in to log or modify the process before content is generated. |
| `nuclen_poll_generation` | `string $generation_id`, `string $workflow_type`, `array $post_ids`, `int $attempt` | Runs on a schedule to poll the Nuclear Engagement API for generation results. Useful for monitoring or altering the polling behaviour. |
| `wp_ajax_nuclen_trigger_generation` | `$_POST` data | AJAX endpoint to start manual generation from the admin. |
| `wp_ajax_nuclen_fetch_app_updates` | `$_POST` data | Retrieves generation progress updates via AJAX. |
| `wp_ajax_nuclen_get_posts_count` | `$_POST` data | Returns the number of posts that match the current bulk generate filters. |
| `wp_ajax_nuclen_dismiss_pointer` | `$_POST` data | Marks an onboarding pointer as dismissed for the current user. |
| `wp_ajax_nuclen_export_optin` / `admin_post_nuclen_export_optin` | none | Exports opt‑in data to a CSV file. |
| `wp_ajax_nuclen_save_optin` / `wp_ajax_nopriv_nuclen_save_optin` | `$_POST` data | Records a front‑end opt‑in submission. |
| `admin_post_nuclen_connect_app` | `$_POST` data | Handles saving the Gold Code during setup. |
| `admin_post_nuclen_generate_app_password` | `$_POST` data | Creates the WordPress App Password during setup. |
| `admin_post_nuclen_reset_api_key` | none | Disconnects the site by clearing the Gold Code. |
| `admin_post_nuclen_reset_wp_app_connection` | none | Revokes the previously generated App Password. |

## Filter Hooks

| Hook | Parameters | Usage |
| --- | --- | --- |
| `nuclen_setting_*` | `mixed $value`, `string $key` | Called when retrieving a plugin setting from `SettingsRepository`. Replace `*` with the setting key. Return a new value to override the stored setting. |
| `nuclen_toc_enable_heading_ids` | `bool $enabled` | Allows disabling the automatic ID injection used by the Table of Contents module. Return `false` to leave headings untouched. |

## Usage Examples

Register a callback when generation begins:

```php
add_action( 'nuclen_start_generation', function ( $post_id, $workflow_type ) {
    error_log( "Starting $workflow_type for post $post_id" );
} );
```

Override a saved setting when retrieved:

```php
add_filter( 'nuclen_setting_api_timeout', function ( $value ) {
    return 30; // seconds
} );

Register a callback for the `nuclen_start_generation` action:

```php
add_action( 'nuclen_start_generation', function ( $post_id, $workflow_type ) {
    // Custom logic before generation starts.
} );
```

Modify a setting via the `nuclen_setting_*` filter:

```php
add_filter( 'nuclen_setting_quiz_title', function ( $value, $key ) {
    return 'My Custom Title';
}, 10, 2 );
```
