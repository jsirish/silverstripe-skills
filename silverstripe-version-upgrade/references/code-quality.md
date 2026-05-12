
## Code Quality

### PHPCS

```bash
ddev exec vendor/bin/phpcs app/src/
```

### PHPStan (if configured)

```bash
ddev exec vendor/bin/phpstan analyse app/src/
```

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
