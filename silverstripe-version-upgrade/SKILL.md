---
name: silverstripe-version-upgrade
description: Complete workflow for upgrading Silverstripe CMS projects between major versions (e.g. SS4 → SS5). Covers assessment, dependency updates, config migration, data migration tasks, testing, and deployment.
---

# Silverstripe Version Upgrade Skill

Repeatable workflow for upgrading Silverstripe CMS projects (e.g. SS4 → SS5) in Dynamic Agency's module ecosystem and DDEV-based local development.

## Upgrade Phases (Summary)

| Phase | Key Actions |
|-------|-------------|
| **1. Assessment** | Audit versions, packages, incompatibilities |
| **2. Branch & PHP** | Create feature branch, update PHP to `^8.1` |
| **3. Dependencies** | Update recipes with `--no-update`, resolve, `vendor-expose` |
| **4. Config Migration** | Fix extension relocations, ORM relationships, templates |
| **5. Data Migration** | Write idempotent BuildTasks for link/data migrations |
| **6. Build & Verify** | `dev/build`, run tasks, check frontend + CMS |
| **7. Code Quality** | PHPCS, PHPStan, annotation fixes |
| **8. Commit & PR** | Conventional commit, checklist PR body |
| **9. UAT** | Deploy, run tasks, verify, troubleshoot |
| **10. Production** | Merge, deploy, run tasks, verify |

## Phase 1: Assessment & Discovery

### 1.1 Pre-Flight

```bash
ddev start
git branch -a | grep -i 'ss5\|silverstripe-5\|upgrade'
ddev composer show silverstripe/framework | grep versions
ddev php -v
```

### 1.2 Audit Packages

```bash
ddev composer show | grep 'dynamic/\|silverstripe/\|dnadesign/'
```

### 1.3 Incompatible Packages (Common)

| Package | Action |
|---------|--------|
| `lekoala/silverstripe-debugbar` | Remove |
| `ryanpotter/silverstripe-cms-theme` | Remove (SS5 built-in) |
| `fractas/elemental-stylings` | Remove (use native `styles`) |
| `sheadawson/silverstripe-linkable` | Replace with `silverstripe/linkfield` |
| `dynamic/silverstripe-company-config` | Remove (merged into base-site) |
| `dynamic/silverstripe-template-config` | Remove (merged into base-site) |

> [!TIP]
> If the project uses `sheadawson/silverstripe-blocks`, migrate to Elemental **before** SS5 upgrade using `dynamic/silverstripe-blocks-to-elemental-migrator`.

## Phase 2–3: Branch & Dependencies

```bash
git checkout -b feature/silverstripe-5-upgrade

# Update PHP requirement in composer.json
# Change "php": "^7.4" to "php": "^8.1" (SS5 requires PHP 8.1+)

# Update core
ddev composer require silverstripe/recipe-cms:^5 dynamic/recipe-silverstripe-base-site:^5 --no-update

# Update elemental packages (versions vary per package — verify on Packagist)
# Add each package individually, e.g.:
ddev composer require dynamic/silverstripe-elemental-accordion:^5.0 --no-update

# Remove incompatible
ddev composer remove lekoala/silverstripe-debugbar --no-update

# Resolve
ddev composer update --with-all-dependencies
ddev composer vendor-expose
```

## Phase 4: Configuration Migration

Key areas:
- **Extension relocations**: `HeaderImageExtension` moved from base-site → site-tools
- **ORM strictness**: SS5 requires explicit `has_one` back-references for `has_many`
- **Template updates**: Sort field `SortOrder` → `Sort`, link `$Link` → `$URL`
- **Annotations**: Use `@property ?string` for nullable fields in PHP 8.1+

## Phase 5: Data Migration

See [references/data-migration-tasks.md](references/data-migration-tasks.md) for the BuildTask pattern, migration principles, and common scenarios (Linkable → Link, ManyMany → LinkField).

## Phase 6: Build & Verify

```bash
ddev sake dev/build "flush=1"
ddev sake dev/tasks/<task-segment>
```

Verify: Homepage, carousel, navigation, footer, CMS admin, elemental blocks.

## Key Discoveries & Gotchas

> [!WARNING]
> **Raw SQL Data Migrations:** When migrating from deprecated or obsoleted modules (such as legacy `dynamic/silverstripe-blocks`), the modern ORM will **not** map legacy database columns (like `$db` properties) because the field definitions have been removed from the class. You must rely on raw `DB::query()` calls to preserve and remap data (e.g., copying old `PageLinkID` fields directly into new `LinkField` instances).

> [!NOTE]
> **Empty Layout Containers:** During block to element mapping, migrating structural containers (like Accordions or Promo galleries) that have exactly *0* items within them is intentional. Preserving the empty wrapper maintains its hierarchical placement in the DOM so editors can populate it post-upgrade without losing context.

> [!TIP]
> **AJAX Lazy Loading Optimization:** Pre-generating Image asset manipulations (e.g., `ScaleWidth` or `Fill`) and employing AJAX lazy-loading on heavily populated pages (such as dense galleries) is required. Rendering 200+ raw Image objects in a single PHP payload causes hard pre-prod timeouts in SS5. *Note: in SS5, `PageController::init()` will still fire on AJAX endpoints unless explicitly bypassed.*

> [!IMPORTANT]
> **Data duplication / Subtitle:** The unified templates in SS5 ElementContent might render both `Title` and `SubTitle` domains redundantly. SS4 layouts that abused DB columns to handle split headings will show duplicate lines post-migration unless the legacy entries are scrubbed.

> [!NOTE]
> **SS5 `httpError()`:** The `httpError()` routine in SS5 throws an `HTTPResponse_Exception`. Following the call with a simple `return` aids static analysis flow and squashes PHP linting notices without impacting application state.

> [!WARNING]
> **Elemental Styles & `fractas/elemental-stylings`:** In SS4, the `fractas/elemental-stylings` module prefixed the elemental style values with `style-` (e.g., `style-modal`, `style-blue`). In SS5, native styles are used, and `getStyleVariant()` returns the raw value (e.g., `modal`). If templates or SCSS rely on the `style-` prefix, you must either update the templates/SCSS, update the values in the database, OR write a simple `updateStyleVariant` DataExtension to re-apply the prefix.

## Phase 7–10: Quality, PR, UAT, Production

See [references/code-quality.md](references/code-quality.md) for PHPCS/PHPStan steps.

## Reference Documentation

| Topic | File |
|-------|------|
| Data Migration Tasks | [data-migration-tasks.md](references/data-migration-tasks.md) |
| SS5 Version Map | [version-map.md](references/version-map.md) |
| Code Quality | [code-quality.md](references/code-quality.md) |
