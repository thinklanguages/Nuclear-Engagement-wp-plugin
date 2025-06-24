# Development Workflow

This guide shows how to run and debug the plugin locally using `wp-env` and Docker.

## Quick start

1. Install dependencies:
   ```bash
   composer install
   npm install
   ```

2. Start WordPress with the plugin:
   ```bash
   npx wp-env start --xdebug
   ```
   The environment mounts your working directory so changes take effect immediately.

3. Access WordPress at `http://localhost:8888` and log in with the default credentials provided by `wp-env`.

## Fast-loop debugging

Use these commands as needed while the environment is running:

- **View PHP errors live**
  ```bash
  npx wp-env run cli tail -f /var/www/html/wp-content/debug.log
  ```
- **Trigger plugin hooks from the command line**
  ```bash
  npx wp-env run cli wp eval 'do_action("your_hook");'
  ```
- **Inspect queries and REST calls**
  ```bash
  npx wp-env run cli wp plugin install query-monitor --activate
  ```
  Then reload the page and use the admin bar to view debugging information.

## Running tests

After starting the environment, run PHPUnit from the repository root:

```bash
composer test
```

You can also run static analysis (optional):

```bash
composer require --dev phpstan/phpstan-deprecation
vendor/bin/phpstan analyse
```

## Typical workflow

```bash
wp-env destroy && wp-env start --xdebug
# set breakpoints in your IDE mapped to /var/www/html
# visit the admin page that triggers plugin code
# tail debug.log in another terminal
```

This loop lets you step through the plugin, inspect variables, and iterate quickly.
