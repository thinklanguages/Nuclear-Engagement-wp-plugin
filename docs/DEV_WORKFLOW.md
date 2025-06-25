# Development Workflow

This guide shows how to run and debug the plugin locally using `wp-env` and Docker.

## Quick start

1. After cloning the repository, install PHP dependencies from the plugin directory:
   ```bash
   composer install --working-dir=nuclear-engagement
   ```
   Then install Node dependencies with:
   ```bash
   npm install
   ```
   After adding new PHP classes, regenerate the autoloader with:
   ```bash
   composer dump-autoload --working-dir=nuclear-engagement
   ```

2. Start WordPress with the plugin:
   ```bash
   npx wp-env start --xdebug
   ```
   The environment mounts your working directory so changes take effect immediately.

3. Access WordPress at `http://localhost:8888` and log in with the default credentials provided by `wp-env`.

   If port `8888` is already in use, create a `.wp-env.override.json` file and specify a new port, for example:

   ```json
   { "port": 8889 }
   ```
   Restart the environment and visit `http://localhost:8889` instead.

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
composer test --working-dir=nuclear-engagement
```

You can also run static analysis (optional):

```bash
composer require --dev phpstan/phpstan-deprecation --working-dir=nuclear-engagement
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
