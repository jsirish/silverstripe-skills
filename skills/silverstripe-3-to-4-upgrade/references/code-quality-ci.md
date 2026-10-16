# Code Quality & CI (Phase 9)

After the upgrade builds and renders, lock down code quality with automated tools and CI:

> [!WARNING]
> **Verify the shipped QA config actually runs before trusting the gate.** The installer-provided config is frequently stale on an upgrade and fails silently:
> - `phpstan.neon` often does `includes: phpstan-baseline.neon`, but that baseline file **doesn't exist** — PHPStan won't run at all until you create it (`--generate-baseline`) or remove the include.
> - `phpunit.xml.dist` often points at a vendor test dir that isn't installed (e.g. `vendor/<org>/<tools>/tests`), and there may be **no `app/tests/` directory** at all.
>
> Run each tool once and confirm it executes — "the config is present" is not the same as "the gate runs."

1. **PHPCS** — enforce coding standards:
   ```bash
   ddev exec vendor/bin/phpcs app/src/ app/tests/
   ```
   Common SS3→SS4 issues PHPCS catches: missing namespace declarations, outdated class references, PSR-2/PSR-12 formatting.

2. **PHPStan** — static analysis (if configured):
   ```bash
   ddev exec vendor/bin/phpstan analyse app/src/
   ```
   Run at level 1–2 initially; the SS4 upgrade introduces many dynamic calls that require baseline configuration. Use `--generate-baseline` to create a `phpstan-baseline.neon` for known false positives.

   **Wire in the SilverStripe extension or you'll drown in false positives.** Plain PHPStan flags every SS magic method (`$page->StaffMembers()`, `$this->UtilityLinks()`, `getStaffMembers()`) as an error. `cambis/silverstan` teaches PHPStan about SS's dynamic ORM/`has_one`/`has_many` calls — install it if not already in your dev-deps (it's included in the [Phase 3](../SKILL.md#phase-3-dependencies--branching) standard toolkit):
   ```bash
   composer require --dev cambis/silverstan
   ```
   With `phpstan/extension-installer` present it auto-registers; otherwise add it to `phpstan.neon`:
   ```neon
   includes:
       - vendor/cambis/silverstan/extension.neon
   ```
   Then `--generate-baseline` to adopt the gate incrementally rather than fixing every legacy finding at once. Match the silverstan major to the target CMS major (`^1.0` for SS4, `^2.1` for SS5+).

3. **Rector** — automated refactoring validation:
   ```bash
   ddev exec vendor/bin/rector --dry-run
   ```
   `silverstripe-rector` catches deprecated API usage, class-rename patterns, and namespace issues that phpstan/phpcs miss. Always run with `--dry-run` first to review changes. See the [Phase 4 tip](../SKILL.md#phase-4-code--namespace-migration) for installation and configuration.

4. **PHPUnit** — run the existing test suite:
   ```bash
   ddev exec vendor/bin/phpunit
   ```
   If no tests exist yet, this is the ideal time to add smoke tests for the upgraded page types and Elemental elements.

   > [!NOTE]
   > The PHPCS, PHPStan, and PHPUnit commands above are consistent with the shared code-quality reference in [silverstripe-version-upgrade/references/code-quality.md](../../silverstripe-version-upgrade/references/code-quality.md), which also covers GitHub Actions CI setup and common fixes.

5. **Rector in CI** — add a `rector` job to `.github/workflows/ci.yml`:
   ```yaml
   rector:
     runs-on: ubuntu-latest
     steps:
       - uses: actions/checkout@v4
       - uses: shivammathur/setup-php@v2
         with:
           php-version: '8.1'
           extensions: intl, gd, mysqli
           coverage: none
       - run: composer install --no-interaction --prefer-dist
       - run: vendor/bin/rector --dry-run
   ```

6. **GitHub Actions CI** — automate quality gates for every PR:
   ```yaml
   # .github/workflows/ci.yml
   name: CI
   on: [pull_request]
   jobs:
     phpcs:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v4
         - uses: silverstripe/gha-phpcs@v1
           with:
             path: app/src/
     phpstan:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v4
         - uses: shivammathur/setup-php@v2
           with:
             php-version: '8.1'
             extensions: intl, gd, mysqli
             coverage: none
         - run: composer install --no-interaction --prefer-dist
         - run: vendor/bin/phpstan analyse app/src/ --level 2
     phpunit:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v4
         - uses: shivammathur/setup-php@v2
           with:
             php-version: '8.1'
             extensions: intl, gd, mysqli
         - run: composer install --no-interaction --prefer-dist
         - run: vendor/bin/phpunit
   ```

   > [!TIP]
   > **Start simple**: a single `ci.yml` with just PHPCS and PHPStan will catch 90% of regression issues. Add PHPUnit once the upgrade tests are written. Pin the workflow to run only on `pull_request` to avoid redundant runs on every push to a feature branch.

7. **Commit the CI config** to `.github/workflows/ci.yml` as part of the upgrade branch — it keeps quality enforcement in sync with the new codebase.
