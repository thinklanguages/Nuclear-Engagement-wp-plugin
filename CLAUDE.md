# Nuclear Engagement Plugin Documentation

**Instantly Generate AI Summaries & Quizzes Across Your Blog**

Nuclear Engagement harnesses AI to transform your entire WordPress blog into interactive quizzes and concise summaries in seconds. This documentation provides a comprehensive guide to the plugin's architecture, features, and development.

## 🚀 Quick Start

- **Installation**: See [docs/USAGE.md](docs/USAGE.md)
- **User Guide**: See [docs/USER_GUIDE.md](docs/USER_GUIDE.md)
- **Architecture**: See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

## 📁 Project Structure

```
plugin-root/
├── nuclear-engagement/    # Main plugin directory
│   ├── admin/            # Admin functionality (see admin/CLAUDE.md)
│   ├── front/            # Frontend functionality (see front/CLAUDE.md)
│   ├── inc/              # Core business logic (see inc/CLAUDE.md)
│   ├── templates/        # PHP templates (see templates/CLAUDE.md)
│   ├── assets/           # Compiled CSS/JS
│   └── languages/        # Translations
├── src/                  # TypeScript sources (see src/CLAUDE.md)
├── tests/                # Test suites (see tests/CLAUDE.md)
├── scripts/              # Build/deploy scripts (see scripts/CLAUDE.md)
├── docs/                 # Project documentation
└── e2e/                  # End-to-end tests (see e2e/CLAUDE.md)
```

## 📚 Documentation Hub

### Core Documentation
- [Architecture Overview](docs/ARCHITECTURE.md) - System design and patterns
- [User Guide](docs/USER_GUIDE.md) - End-user documentation
- [API Reference](docs/API.md) - REST API endpoints
- [Hooks Reference](docs/hooks.md) - WordPress hooks and filters

### Development
- [Development Workflow](docs/DEV_WORKFLOW.md) - Setup and contribution guide
- [Contributing Guidelines](docs/CONTRIBUTING.md) - Code standards and PR process
- [CI/CD Guidelines](docs/CI_GUIDELINES.md) - Continuous integration setup
- [Migration Strategy](docs/MIGRATION_STRATEGY.md) - Version migration guide

### Testing
- [Testing Guide](docs/TESTING.md) - Comprehensive testing documentation
- [Testing Quick Reference](docs/TESTING_QUICK_REFERENCE.md) - Quick commands
- Test Reports in [docs/testing/](docs/testing/)

### Operations
- [Deployment Guide](docs/DEPLOYMENT.md) - Production deployment
- [Performance Guide](docs/PERFORMANCE.md) - Optimization tips
- [Security Guide](docs/SECURITY.md) - Security best practices
- [Troubleshooting](docs/TROUBLESHOOTING.md) - Common issues

### Frontend & UI
- [UI Styling Guide](docs/UI-STYLING.md) - CSS architecture
- [Translation Guide](docs/TRANSLATION.md) - Localization process

## 🏗️ Architecture Principles

### Maintainable-by-Design
- **Slice by concern, not by type** - Features grouped in modules
- **One class = one responsibility** - Clear separation of concerns
- **Hard limits trigger refactors** - Max 300 LOC per file, 15 methods per class

### Key Design Patterns
1. **Service Container** - Dependency injection for testability
2. **Repository Pattern** - Data access abstraction
3. **Module System** - Feature isolation and lazy loading
4. **Event-Driven** - Hooks and filters for extensibility
5. **Background Processing** - Async job queue for heavy operations

### Code Standards
- **Namespace everything** - PSR-4 autoloading via Composer
- **Never mix HTML with PHP logic** - Template separation
- **Store options via repository wrapper** - Testable settings
- **Load assets via handles** - Centralized asset management

## 🔧 Development

### Requirements
- PHP 7.4+
- WordPress 6.1+
- Node.js 18+ (20 recommended)
- Composer 2.0+

### Quick Commands
```bash
# Install dependencies
composer install --working-dir=nuclear-engagement
npm install

# Build assets
npm run build     # Production
npm run dev       # Development with watch

# Run tests
composer test     # All PHP tests
npm test          # JavaScript tests
./scripts/run-e2e.sh  # E2E tests

# Code quality
composer lint     # PHP linting
composer phpstan  # Static analysis
```

## 🚦 Quality Gates

- **PHPUnit** with WP_Mock for unit testing
- **PHPCS** with WordPress coding standards
- **PHPStan** level 6 for static analysis
- **Vitest** for JavaScript testing
- **Playwright** for E2E testing
- **GitHub Actions** CI on every PR

## 📦 Release Process

```bash
# Create production build
composer install --no-dev --optimize-autoloader --working-dir=nuclear-engagement
npm ci && npm run build
./scripts/build-release.sh
```

## 🔗 Important Links

- **Website**: https://www.nuclearengagement.com
- **Support**: See [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)
- **Contributing**: See [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md)

## 📝 Module Documentation

Each major directory contains its own CLAUDE.md file with detailed documentation:

- [Admin Module](nuclear-engagement/admin/CLAUDE.md)
- [Frontend Module](nuclear-engagement/front/CLAUDE.md)
- [Core Includes](nuclear-engagement/inc/CLAUDE.md)
- [Templates](nuclear-engagement/templates/CLAUDE.md)
- [Source Code](src/CLAUDE.md)
- [Tests](tests/CLAUDE.md)
- [Scripts](scripts/CLAUDE.md)
- [E2E Tests](e2e/CLAUDE.md)