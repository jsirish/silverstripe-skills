# SS6 dependency and branch changes

Companion to Phase 2–3 of [SKILL.md](../SKILL.md).

## SS6 variant

For SS6 upgrades, adapt the branch and dependency steps:

```bash
git checkout -b feature/silverstripe-6-upgrade

# Verify PHP version — SS6 requires PHP 8.3+
ddev php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+'
# Must be 8.3 or higher — update .ddev/config.yaml if needed
# Change "php": "^8.1" to "php": "^8.3" in composer.json

# Update core recipe
ddev composer require silverstripe/recipe-cms:^3 dynamic/recipe-silverstripe-base-site:^8 --no-update

# Update elemental packages (branch 6 for most elemental modules)
ddev composer require dynamic/silverstripe-elemental-accordion:^6.0 --no-update

# Add SS6-required packages that SS5 had as transitive
ddev composer require silverstripe/htmleditor-tinymce:^1.0 --no-update

# Replace deprecated packages (linkfield 4.x is CMS 5 only; the SS6 line is 5.x.
# Run the linkable data migration on SS5 with linkfield ^4 BEFORE this bump)
ddev composer require --no-update silverstripe/linkfield:^5.0
ddev composer remove --no-update sheadawson/silverstripe-linkable

# Remove incompatible
ddev composer remove --no-update nathancox/embedfield

# Resolve
ddev composer update --with-all-dependencies
ddev composer vendor-expose
```

> **Recipe-first:** When consuming the Dynamic Essentials ecosystem, require only the root recipe:
> `ddev composer require dynamic/recipe-silverstripe-essentials-website:^3@dev`
> The recipe's `@dev` constraints cascade the entire tree automatically. Individual module
> constraints are only needed if forcing source install via `preferred-install: dynamic/*: source`.

> **`silverstripe/vendor-plugin` bump:** SS6 Dynamic modules require `^3`. If the project root pins `^2.0` (SS5 default), Composer will conflict. Apply before running `composer update`:
> ```bash
> ddev composer require silverstripe/vendor-plugin:^3 --no-update
> ```

> [!WARNING]
> **SS6 blocker — `silverstripeltd/betamask`:** `silverstripeltd/betamask ^0.0.1` requires `silverstripe/admin ^2.1` (SS5-only). Check for an SS6-compatible release before removing it:
> ```bash
> ddev composer show -a silverstripeltd/betamask   # check for an SS6-compatible release
> ddev composer remove silverstripeltd/betamask    # if none found
> ```
