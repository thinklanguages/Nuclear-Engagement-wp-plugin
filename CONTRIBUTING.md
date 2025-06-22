# Contributing

This repository follows specific coding conventions and build steps. Please review the guidelines below before submitting changes.

## Coding Style

- **PHP**: 4 spaces per indent and adhere to the WordPress Coding Standards.
- **TypeScript**: 2 spaces per indent. Source files live in `src/` and should avoid jQuery.

## Setup

Install dependencies once:

```bash
composer install
npm install
```

## Quality Checks

Run linting and the test suite prior to committing:

```bash
composer lint
composer test
```

If you modify any TypeScript files, rebuild the assets:

```bash
npm run build
```

During development you can watch and rebuild automatically with:

```bash
npm run dev
```

## Further Reading

For design context and architectural decisions, see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
