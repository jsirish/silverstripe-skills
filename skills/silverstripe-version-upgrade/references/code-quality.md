
## Code Quality

### PHPCS

```bash
ddev exec vendor/bin/phpcs app/src/
```

### PHPStan (if configured)

```bash
ddev exec vendor/bin/phpstan analyse app/src/
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
