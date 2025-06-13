Maintainable-by-design structure for a complex WordPress plugin
Slice by concern, not by type

```
plugin-root/
├── inc/
│   ├── Modules/
│   │   ├── Quiz/          # 1 feature = 1 folder
│   │   │   ├── class-quiz-service.php
│   │   │   ├── quiz-admin.php
│   │   │   └── assets/
│   │   └── Summary/
│   ├── Core/              # shared kernel (loader, i18n, settings API)
│   └── Utils/             # truly generic helpers
├── templates/             # view partials only—no logic
├── assets/                # compiled JS/CSS
├── languages/
└── tests/
```
Central bootloader (plugin.php) stays 50 LOC max
Register autoloader → instantiate Core\\Plugin → add_action hooks—nothing else.

One class = one responsibility
Quiz_Service deals with data; Quiz_Admin registers settings/UI; Quiz_Ajax handles endpoints.

Never mix HTML with PHP logic
Use small Twig/Blade-like partials (or include locate_template) and pass data only.

Hard limits trigger refactors
File > 300 LOC or class > 15 methods ⇒ split. Enforce with PHP-CS-Fixer + PHPMD rules.

Store options via a repository wrapper
Settings_Repo::get( 'api_key' ) isolates get_option() calls and makes unit testing easy.

Load assets via handles, not paths
Register all scripts/styles once in Core\\Assets and enqueue by handle from modules to avoid duplicates.

Namespace everything
namespace NuclearEngagement\\Modules\\Quiz; prevents collisions and autoloads cleanly with Composer PSR-4.

Composer autoload, even if shipping as a single file
composer dump-autoload -o keeps class maps fast; for WP.org, the build script can prefix vendor deps with PHPCS “dealers-choice”.

Automate quality gates
PHPUnit, WP-Mock, PHPCS with WordPress rules, PHPStan level 6, and GitHub Actions CI on every PR.

Document decisions
Keep a /docs/ARCHITECTURE.md and changelog; note why each module exists and any major refactor rationale.

Keep activation/deactivation idempotent
Activation hooks create tables/options if not present; deactivation leaves data unless a user-triggered uninstall runs a separate cleanup class.
