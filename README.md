# Nuclear Engagement
**Instantly Generate AI Summaries & Quizzes Across Your Blog**

Nuclear Engagement harnesses AI to transform your entire WordPress blog into interactive quizzes and concise summaries in seconds. No coding or manual work required—just install, click, and watch visitor engagement, time on page, and lead capture soar.

## Why Choose Nuclear Engagement
Nuclear Engagement stands out with true sitewide automation: process hundreds or thousands of posts in seconds. Unlike other tools that require manual per-post setup, NE’s batch generation handles creation, storage, and display end-to-end—so you can focus on content strategy without repetitive tasks.

## Features
- **Bulk Content Generation**: Process 100+ posts at once with a single click.
- **AI-Powered Summaries**: Generate concise outlines to hook readers.
- **Interactive Quizzes**: Create engaging multiple-choice quizzes automatically.
- **Email Opt-ins**: Collect leads via built-in Zapier & Make integration.
- **Flexible Display**: Auto-append content or use shortcodes before/after post content.
- **Engagement Analytics**: Track quiz completions and reader behavior.
- **Mobile-Optimized**: Responsive layouts for any device.
- **Lightweight & Fast**: Minimal codebase with lazy loading for optimal performance.

## Requirements

- PHP 7.4 or higher
- WordPress 6.1 or higher
- Node 18+ (Node 20 recommended) for building assets

Learn more:
See [CHANGELOG](docs/CHANGELOG.md) for release notes.
See [Installation & Workflow Guide](docs/USAGE.md) for setup and usage.
See [TRANSLATION](docs/TRANSLATION.md) for localization instructions.

https://www.nuclearengagement.com

## Development

Install PHP dependencies from the plugin directory and run linting with:

```bash
composer install --working-dir=nuclear-engagement
composer lint --working-dir=nuclear-engagement
```

Run the PHPUnit test suite with:

```bash
composer test --working-dir=nuclear-engagement
```

Run the JavaScript tests with:

```bash
npm run test
```

Run the end-to-end flows using [Maestro](https://maestro.mobile.dev):

```bash
bash scripts/run-e2e.sh
```

Run static analysis using PHPStan:

```bash
composer phpstan --working-dir=nuclear-engagement
```

### Building assets

TypeScript source files live in `src/` and need to be compiled before the plugin can run. Install Node dependencies and build the JavaScript with:

```bash
npm install
npm run build
```

During development you can rebuild on the fly with:

```bash
npm run dev
```

## Release

Create a production archive with runtime dependencies only:

```bash
composer install --no-dev --optimize-autoloader --working-dir=nuclear-engagement
npm ci
npm run build
./scripts/build-release.sh
```

This builds the plugin and packages it. See [CI Guidelines](docs/CI_GUIDELINES.md) for full details.

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [User Guide](docs/USER_GUIDE.md)
- [Translation Guide](docs/TRANSLATION.md)
- [Hooks Reference](docs/hooks.md)
- [CI Guidelines](docs/CI_GUIDELINES.md)
- [Development Workflow](docs/DEV_WORKFLOW.md)

