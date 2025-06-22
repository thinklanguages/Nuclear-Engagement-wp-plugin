# Nuclear Engagement User Guide

This document provides a quick overview of how to get started with the plugin and where to find detailed instructions.

## Installation

1. Download the plugin zip from your Nuclear Engagement account.
2. In your WordPress admin area go to **Plugins → Add New** and upload the zip.
3. Activate the plugin once the upload completes.

See the [Installation & Workflow Guide](USAGE.md) for step-by-step screenshots.

## Initial Setup

The plugin requires a **Gold Code** (API key) to connect with the Nuclear Engagement app.

1. Log into the app and generate a new key.
2. In WordPress open **Nuclear Engagement → Setup**.
3. Paste the key and follow the on-screen prompts to authorise your site.

You can reconnect or change the key at any time via this screen.

## Bulk Generation Workflow

After connecting, you can generate summaries and quizzes across multiple posts at once.

1. Navigate to **Nuclear Engagement → Generate**.
2. Select the post types and filters, then retrieve the number of posts that match.
3. Click **Start Generation** to queue content creation.

Progress is displayed in the admin interface and you may leave the page while generation runs. See [USAGE.md](USAGE.md#typical-workflow) for more details.

## Shortcodes

Place the following shortcodes in your posts or pages to display generated content:

- `[nuclear_engagement_summary]` – shows the AI summary for the current post.
- `[nuclear_engagement_quiz]` – renders the generated quiz.
- `[nuclear_engagement_toc]` – outputs the table of contents when enabled.

You may also enable automatic placement under **Settings → Content Placement**.

## Styling Options

Under **Settings → Styling** you can customise fonts and colours used by plugin components. All front‑end markup is wrapped in a `.nuclen-root` div so your theme overrides remain isolated. Refer to [UI Styling Guidelines](UI-STYLING.md) for conventions and available CSS variables.

## Uninstall Settings

Before removing the plugin you can choose which data should be deleted. Visit **Settings → Uninstall** and tick the items you want to clean up:

- Plugin settings
- Generated summaries and quizzes
- Stored opt‑in data
- Log file
- Custom CSS file

When you delete the plugin these checked items will be removed from the database and file system.

---

For a deeper walkthrough of each screen, including screenshots and troubleshooting tips, see the [Installation & Workflow Guide](USAGE.md).
