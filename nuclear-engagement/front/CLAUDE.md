# Frontend Module

This directory contains all frontend functionality for the Nuclear Engagement plugin.

## Structure

- **Controller/** - REST API controllers
  - `Rest/ContentController.php` - Handles content-related REST requests

- **traits/** - Reusable frontend functionality
  - `AssetsTrait.php` - Frontend asset management
  - `RestTrait.php` - REST API endpoint registration
  - `ShortcodesTrait.php` - Shortcode registration and handling

- **Core Files**
  - `FrontClass.php` - Main frontend class orchestrating all frontend functionality
  - `QuizView.php` - Quiz rendering and display logic
  - `QuizShortcode.php` - Quiz shortcode implementation
  - `SummaryShortcode.php` - Summary shortcode implementation
  - `block.json` - Gutenberg block registration

## Assets

- **css/** - Frontend styles
  - `nuclen-front.css` - Main frontend styles

- **js/** - Frontend JavaScript
  - `nuclen-front.js` - Compiled frontend scripts with lazy loading, quiz functionality, and interactions

## Key Features

1. **Quiz System** - Interactive quizzes with progress tracking
2. **Table of Contents** - Dynamic TOC generation (via TOC module)
3. **Summary Generation** - AI-powered content summaries
4. **REST API** - Content fetching and updates
5. **Lazy Loading** - Performance-optimized asset loading
6. **Opt-in System** - User engagement tracking