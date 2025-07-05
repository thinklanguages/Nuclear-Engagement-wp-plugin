# Source Directory (TypeScript/JavaScript)

This directory contains all TypeScript source files that are compiled into JavaScript for the Nuclear Engagement plugin.

## Structure

### Admin Sources (`admin/ts/`)

#### Blocks (`blocks/`)
- `nuclen-editor-blocks.ts` - Gutenberg block implementations

#### Generate Page (`generate/`)
- `elements.ts` - UI element management
- `filters.ts` - Post filtering logic
- `generate-page-handlers.ts` - Event handlers
- `generate-page-utils.ts` - Utility functions
- `navigation.ts` - Page navigation
- `step1.ts` - First step logic
- `step2.ts` - Second step logic

#### Generation System (`generation/`)
- `api.ts` - API communication
- `polling.ts` - Progress polling
- `results.ts` - Result handling

#### Single Generation (`single/`)
- `single-generation-handlers.ts` - Individual post generation
- `single-generation-utils.ts` - Helper functions

#### Utilities (`utils/`)
- `api.ts` - API helper functions
- `displayError.ts` - Error display utilities
- `logger.ts` - Logging system

#### Main Admin Files
- `nuclen-admin.ts` - Main admin script
- `nuclen-admin-generate.ts` - Generation page main
- `nuclen-admin-single-generation.ts` - Single post generation
- `nuclen-admin-ui.ts` - UI components
- `onboarding-pointers.ts` - Onboarding system

### Frontend Sources (`front/ts/`)

#### Quiz System
- `nuclen-quiz-main.ts` - Main quiz controller
- `nuclen-quiz-question.ts` - Question handling
- `nuclen-quiz-progress.ts` - Progress tracking
- `nuclen-quiz-results.ts` - Result calculation
- `nuclen-quiz-optin.ts` - Opt-in functionality
- `nuclen-quiz-utils.ts` - Quiz utilities
- `nuclen-quiz-types.ts` - TypeScript type definitions

#### Core Frontend
- `nuclen-front.ts` - Main frontend entry
- `nuclen-front-global.ts` - Global functionality
- `nuclen-front-lazy.ts` - Lazy loading system
- `logger.ts` - Frontend logging

### Module Sources (`modules/`)

#### TOC Module (`toc/ts/`)
- `nuclen-toc-front.ts` - Frontend TOC functionality
- `nuclen-toc-admin.ts` - Admin TOC settings
- `sticky-toc.ts` - Sticky positioning
- `toc-scroll-spy.ts` - Active section tracking
- `toc-toggle.ts` - Expand/collapse functionality
- `toc-interactions.ts` - User interactions
- `toc-analytics.ts` - Analytics tracking
- `toc-click-close.ts` - Click outside to close

### Shared Sources (`shared/`)
- `constants.ts` - Shared constants
- `logger.ts` - Shared logging utilities

### Other Source Files

#### Database (`Database/Schema/`)
- `ThemeSchema.php` - Theme database schema

#### Models (`Models/`)
- `Theme.php` - Theme model

#### Services (`Services/`)
- **Styles/** - Style generator implementations
- **Theme Services** - Theme system services

## Build Process

1. TypeScript files are compiled using Vite
2. Separate builds for admin and frontend bundles
3. Source maps generated for debugging
4. Tree-shaking for optimal bundle size
5. Module federation for shared dependencies

## Development Guidelines

1. **TypeScript First** - All new code in TypeScript
2. **Type Safety** - Proper type definitions
3. **Module Pattern** - ES6 modules
4. **No Global Pollution** - Namespace all globals
5. **Progressive Enhancement** - Graceful degradation