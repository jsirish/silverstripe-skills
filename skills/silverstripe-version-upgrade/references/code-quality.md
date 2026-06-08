
## Code Quality

### Dev Dependencies

Add the standard Dynamic toolkit to `composer.json`:

```json
"require-dev": {
    "cambis/silverstan": "^2.1",
    "ergebnis/composer-normalize": "^2.44",
    "lekoala/silverstripe-debugbar": "^3.0",
    "phpstan/extension-installer": "^1.3",
    "phpunit/phpunit": "^9.6",
    "silverleague/ideannotator": "~3.5.1",
    "silverstripe/recipe-testing": "^3.0",
    "squizlabs/php_codesniffer": "^3.10",
    "wernerkrauss/silverstripe-rector": "^1.0"
}
```

### PHPCS

```bash
ddev exec vendor/bin/phpcs app/src/
```

### PHPStan (if configured)

```bash
ddev exec vendor/bin/phpstan analyse app/src/
```

#### PHPStan SS6 config

Add to `phpstan.neon` to suppress SS6 PHPDoc false-positives:

```neon
parameters:
    treatPhpDocTypesAsCertain: false
```

`cambis/silverstan ^2.1` is the companion PHPStan tool for SS6 — already in the dev dependencies list above.

#### Known annoyance: ideannotator vs PHPStan

> [!WARNING]
> `silverleague/ideannotator` rewrites `@method`/`@property` docblocks to short-form on every `dev/build`, re-breaking PHPStan's FQN resolution. **Commit code before running `dev/build`**, or revert the regenerated docblocks after.
>
> Options to mitigate:
> - Configure ideannotator to use FQN mode (no `use` imports)
> - Set `treatPhpDocTypesAsCertain: false` in PHPStan (already done above)
> - Remove ideannotator from `require-dev` and run it only on demand

### Rector (if configured)

`wernerkrauss/silverstripe-rector` provides automated upgrade rules for Silverstripe CMS. Always use `--dry-run` first:

```bash
ddev exec vendor/bin/rector --dry-run
ddev exec vendor/bin/rector  # apply when ready
```

Configuration is project-specific — see [github.com/wernerkrauss/silverstripe-rector](https://github.com/wernerkrauss/silverstripe-rector) for available rule sets.

#### SS6 Rector level set

`wernerkrauss/silverstripe-rector` provides SS6 upgrade rules:

```php
// rector.php — SS6 level set
SilverstripeLevelSetList::UP_TO_SS_6_0
```

The SS6 level set handles: namespace migrations (ArrayList, ValidationResult, SSViewer), BuildTask → Symfony conversions, mixin ordering, forTemplate return type enforcement, and more.

```bash
# Run SS6-specific rector rules
ddev exec vendor/bin/rector --dry-run
ddev exec vendor/bin/rector  # apply when ready
```

### GitHub Actions CI (optional but recommended)

Add `.github/workflows/ci.yml` to run quality gates on every PR:

```yaml
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
          php-version: '8.3'  # SS6 minimum
          extensions: intl, gd, mysqli
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/phpstan analyse app/src/ --level 2
  rector:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'  # SS6 minimum
          extensions: intl, gd, mysqli
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/rector --dry-run
  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'  # SS6 minimum
          extensions: intl, gd, mysqli
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/phpunit
```

> [!TIP]
> Start with just PHPCS + PHPStan; add PHPUnit once tests exist. Run only on `pull_request` to avoid redundant per-push runs.

### Common Fixes

- Update `@property $owner` annotations on extensions
- Remove unused imports
- Update namespace references for relocated classes
- Remove references to removed extensions/classes

### Visual Regression

Replace manual side-by-side checks with automated pixel-diff captures. See the [visual-regression-upgrade](../../visual-regression-upgrade/SKILL.md) skill for setup, auth, mask config, and report interpretation.

Basic workflow:
```bash
# Crawl reference URLs from the current site
python ../visual-regression-upgrade/scripts/crawl_urls.py \
  --url https://www.example.com --limit 30 --out paths.txt

# Capture + diff both environments
python ../visual-regression-upgrade/scripts/capture.py \
  --prod https://www.example.com \
  --local https://upgrade.example.com \
  --paths-file paths.txt \
  --out ./vr-out

python ../visual-regression-upgrade/scripts/diff_report.py \
  --in ./vr-out --out ./vr-out/report
```

For SS4→SS5 upgrades, use the legacy local instance (`~/Sites/{project}-legacy`) as the reference to avoid content-drift false positives.

### SS6 code quality summary

| Tool | SS5 Config | SS6 Config |
|------|-----------|-----------|
| PHP version | 8.1 | 8.3 |
| Rector level set | `UP_TO_SS_5_0` | `UP_TO_SS_6_0` |
| PHPStan | `cambis/silverstan ^2.1` | `cambis/silverstan ^2.1` (add `treatPhpDocTypesAsCertain: false`) |
| CI php-version | `'8.1'` | `'8.3'` |
