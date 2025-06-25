# Continuous Integration Guidelines

This project relies on GitHub Actions for linting, testing and building assets. To keep those checks fast and inexpensive we allow contributors to skip workflows when a change cannot affect production code.

## Ad‑hoc CI skips

- Add **`[skip ci]`** or **`skip-checks: true`** to a commit message when your changes only touch documentation, comments or other non-executable files.
- Either token is case-insensitive and must appear in the final commit message to take effect.
- Never use these markers on commits that modify PHP or TypeScript sources—skipped workflows stay pending and can block branch protection rules.
- Prefer automated path filters inside workflow files whenever a file class is always safe to ignore (for example `docs/**`).

Example commit message:

```
Fix typo in contributing guide

[skip ci]
```

## Workflow snippets

Apply path filters at the workflow trigger level:

```yaml
on:
  push:
    paths-ignore:
      - '**/*.md'
      - 'docs/**'
```

Skip individual jobs based on the commit message:

```yaml
jobs:
  heavy-tests:
    if: "!contains(github.event.head_commit.message, '[skip heavy]')"
```

Cancel duplicate runs when pushing new commits:

```yaml
concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
```

## Branch protection

Required checks should point only to jobs that always run. If a workflow is skipped it remains "Pending" and may prevent merging. Align branch protection rules accordingly.

## Local helpers

You may store the marker in a commit template to avoid typing it each time:

```bash
git config commit.template .gitmessage
```

and add `[skip ci]` to the template file. A shell alias can also create the commit message automatically:

```bash
git config --global alias.cis '!git commit --cleanup=verbatim -m "$1" -m "[skip ci]"'
```


## Packaging the plugin

The release archive should contain runtime dependencies only. When creating the production zip run:

```bash
composer install --no-dev --optimize-autoloader --working-dir=nuclear-engagement
npm ci
npm run build
./scripts/build-release.sh
```

Running `composer install --no-dev` removes PHPUnit, PHPCS and other development packages from `vendor/` before the files are zipped.
