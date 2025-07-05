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

## Skipping CI for docs

If your commit only touches documentation or other non-code files, you may avoid
running the full GitHub Actions workflow by appending `[skip ci]` or
`skip-checks: true` to the commit message. See
[docs/CI_GUIDELINES.md](docs/CI_GUIDELINES.md) for details.

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
For instructions on suppressing unnecessary CI runs, see [docs/CI_GUIDELINES.md](docs/CI_GUIDELINES.md).
