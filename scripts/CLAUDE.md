# Scripts Directory

This directory contains utility scripts for development, testing, and deployment of the Nuclear Engagement plugin.

## Available Scripts

### Build and Release
- `build-release.sh` - Creates a production-ready plugin release
  - Compiles assets
  - Removes development files
  - Creates ZIP archive
  - Validates build integrity

### Testing
- `run-e2e.sh` - Executes end-to-end tests
  - Sets up test environment
  - Runs Playwright tests
  - Generates test reports

- `validate-tests.sh` - Validates test suite integrity
  - Checks for test file naming conventions
  - Verifies test coverage requirements
  - Identifies missing test cases

### Internationalization
- `update-translations.sh` - Updates translation files
  - Extracts translatable strings
  - Updates POT file
  - Syncs with existing translations

## Usage

All scripts should be run from the project root:

```bash
./scripts/build-release.sh
./scripts/run-e2e.sh
./scripts/validate-tests.sh
./scripts/update-translations.sh
```

## Script Guidelines

1. **Error Handling** - All scripts include proper error handling
2. **Logging** - Verbose output for debugging
3. **Idempotency** - Scripts can be run multiple times safely
4. **Documentation** - Each script includes usage instructions
5. **Exit Codes** - Proper exit codes for CI/CD integration