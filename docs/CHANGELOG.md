# Changelog

Release notes for the Nuclear Engagement plugin.

## 1.1 – 2025-06-13
- Added: Test infrastructure for improved code quality.
- Added: Dashboard section showing scheduled content generation tasks.
- Added: Inventory data caching with auto invalidation.
- Added: Manual refresh control on the dashboard to update cached inventory data.
- Changed: Expanded cache invalidation to cover more post and term events.
- Changed: Architecture refactoring.
- Changed: Improved security.
- Changed: Improved performance.
- Changed: Uninstall data options.

## 1.0.3 – 2025-06-11
- Added: Uninstall data options.
- Fixed: Auto content generation upon post publish.
- Fixed: Default settings.

## 1.0.2 – 2025-06-05
- Fixed: Settings saving.

## 1.0.1 – 2025-05-31
- Fixed: Content generation.

## 1.0 – 2025-05-31
- Changed: Architecture refactoring.

## 0.9 – 2025-05-26
- Added: Table of Contents (TOC) feature with:
  - Styling options.
  - Static and sticky placement.
  - Heading selection (h2–h6).
  - Sticky offsets (left and top).
  - Sticky max-width setting.
  - Sticky z-index setting.
  - Default show/hide state.
  - TOC toggle enable/remove option.

## 0.8.1
- Fixed: Styling settings.

## 0.8
- Added: Display email opt-in form before or alongside results.
- Added: Mandatory or optional opt-in setting.
- Added: Store opt-in data in the database.
- Added: Export opt-in data to CSV.

## 0.7.2
- Fixed: Display of empty sections on the frontend.

## 0.7.1
- Changed: Content generation metaboxes in post editor now apply to all allowed post types.
- Fixed: Quiz start message style.
- Fixed: Auto-generate content upon post publish.

## 0.7
- Changed: Default content placement.
- Improved: Authentication flow.
- Fixed: Cache-clearing logic.

## 0.6.2
- Improved: UI.
- Fixed: Manual summary creation.
- Fixed: Quiz creation.
- Fixed: Quiz frontend display.

## 0.6.1
- Fixed: Various minor issues and improvements.

## 0.6
- Added: Content generation credit system.
- Added: Account page on app with usage log.
- Improved: Security.
- Improved: UX.
- Fixed: Various minor issues.

## 0.5.1
- Fixed: Minor bugs and improvements.

## 0.5
- Added: Expanded quiz and summary styling options.
- Fixed: Various minor bugs and improvements.

## 0.4.9
- Improved: Security.
- Improved: UX.
- Added: Expanded styling options.
- Fixed: Various minor issues.

## 0.4.3
- Added: Multisite support.
- Changed: Auto-clear post cache upon section generation.

## 0.3.1
- Added: Plugin update checker.
- Added: Support for custom post types.
- Added: Option to update post “last modified” date upon storage to signal new content to search engines and visitors.
- Added: Metrics to analytics dashboard: quiz start/quiz view %, quiz answers/quiz start %, quiz end/quiz start %, quiz opt-in/quiz end %.
- Added: Per-post performance with posts ranked by performance.

## 0.2.0
- Added: Summary options, including section title text, format (paragraph vs. bullet list), and length/number of items.
- Added: Additional styling settings.
- Added: Quiz options: section title text, number of questions per quiz, and number of answers per question.
- Added: Embedding at the top or bottom of post content.
- Added: “Summary view” and “Quiz view” events to analytics.
- Added: App pages for connected sites and user profile.
- Added: In-app engagement analytics dashboard with sitewide metrics (average session time, engaged sessions per user) and summary/quiz engagement counts.
- Added: Display of custom HTML at the beginning or end of quizzes (for coupons, secret links, etc.).
- Added: Email opt-in in quizzes.
- Added: Send data to webhook (Zapier, Make, etc.).

## 0.1.0
- Added: Automated quiz and summary generation from blog post content.
- Added: Reading post content, generating, and storing quiz/summary in WordPress.
- Added: Display of quiz and summary on blog posts.
- Added: Bulk post processing in one click (filter by post status, category, author, and post type).
- Added: Styling options for quiz and summary look and feel.
- Added: Content inventory to identify posts lacking a quiz or summary.
- Added: Engagement tracking events on Google Analytics (quiz start, answer, finish).
