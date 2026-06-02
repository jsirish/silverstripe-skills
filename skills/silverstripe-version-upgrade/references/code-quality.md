
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

### Rector (if configured)

`wernerkrauss/silverstripe-rector` provides automated upgrade rules for Silverstripe CMS. Always use `--dry-run` first:

```bash
ddev exec vendor/bin/rector --dry-run
ddev exec vendor/bin/rector  # apply when ready
```

Configuration is project-specific — see [github.com/wernerkrauss/silverstripe-rector](https://github.com/wernerkrauss/silverstripe-rector) for available rule sets.

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
          php-version: '8.1'
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
          php-version: '8.1'
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
          php-version: '8.1'
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

### Visual Comparison with Staging/Production

Open the live/staging site and your local site side-by-side to confirm content parity:
- Same number of slides in carousel
- Same navigation links and order
- Same footer content
- Same elemental blocks on key pages
