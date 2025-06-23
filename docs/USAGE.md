# Installation & Workflow Guide

This guide explains how to install and configure the **Nuclear Engagement** plugin, generate content in bulk and display it on your site.

## Plugin Installation

1. Download the plugin zip from your Nuclear Engagement account.
2. In WordPress, go to **Plugins → Add New** and upload the zip.
3. Activate the plugin after the upload completes.

## Initial Setup

Before generating content you need a **Gold Code** (API key).

1. Log into the Nuclear Engagement app and create a new Gold Code.
2. In WordPress, open **Nuclear Engagement → Setup**.
3. Paste the Gold Code and follow the prompts to connect your site.

## Typical Workflow

### 1. Bulk Generation

1. Navigate to **Nuclear Engagement → Generate**.
2. Choose the post types and filters, then retrieve the post count.
3. Start generation to create summaries and quizzes for the selected posts.

### 2. Shortcode Placement

- `[nuclear_engagement_summary]` – displays the AI summary for the current post.
- `[nuclear_engagement_quiz]` – outputs the generated quiz.
- `[nuclear_engagement_toc]` – renders the table of contents if enabled.

Place the shortcodes manually in your content or configure auto insertion under **Settings → Content Placement**.

### 3. Styling

Adjust fonts and colours under **Settings → Styling**. The plugin loads all front‑end markup inside a `.nuclen-root` wrapper so your custom CSS can target only plugin elements.

For general styling conventions see [UI Styling Guidelines](UI-STYLING.md).
