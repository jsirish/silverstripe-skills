---
name: silverstripe-version-upgrade
description: Complete workflow for upgrading Silverstripe CMS projects between major versions on the SS4+ line (e.g. SS4 → SS5, SS5 → SS6). Covers assessment, dependency updates, config migration, data migration tasks, testing, and deployment. For legacy SS3 → SS4 upgrades use the silverstripe-3-to-4-upgrade skill instead — that migration is structurally different.
---

# Silverstripe Version Upgrade Skill

Repeatable workflow for upgrading Silverstripe CMS projects (e.g. SS4 → SS5) in Dynamic Agency's module ecosystem and DDEV-based local development.

> **Scope**: This skill covers **SS4 and later** major-version bumps (SS4 → SS5, SS5 → SS6) — a recurring, recipe- and dependency-driven upgrade. For a **legacy SS3 → SS4** upgrade, use the [silverstripe-3-to-4-upgrade](../silverstripe-3-to-4-upgrade/SKILL.md) skill instead: that is a one-time structural migration (PSR-4 namespacing, `mysite`→`app`, `public/` directory, DB schema preflight, Blocks→Elemental) with little mechanical overlap with this workflow.

## Upgrade Phases (Summary)

| Phase | Key Actions |
|-------|-------------|
| **1. Assessment** | Audit versions, packages, incompatibilities; clone legacy instance for VR baseline |
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
# SS5 requires PHP 8.1+; SS6 requires PHP 8.3+ — update .ddev/config.yaml if needed
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

#### SS6-specific

| Package | Action |
|---------|--------|
| `nathancox/embedfield` | Replace with `fromholdio/silverstripe-embedfield ^5.1` |

> [!TIP]
> If the project uses `sheadawson/silverstripe-blocks`, migrate to Elemental **before** SS5 upgrade using `dynamic/silverstripe-blocks-to-elemental-migrator`.

### 1.4 Legacy instance (for VR baseline)

Clone the current SS4 site as a side-by-side reference before starting the upgrade. This gives you a pixel-perfect local baseline to diff against during VR verification — same approach as the SS3→SS4 upgrade pattern.

**Naming convention:** clone into `~/Sites/{project}-legacy` so the upgrade lives in `~/Sites/{project}`.

```bash
cd ~/Sites
git clone <repo> {project}-legacy
cd {project}-legacy
git checkout main  # or the current production branch
ddev config --project-name {project}-legacy
ddev start
ddev auth ssh
ddev exec ./sync.sh  # sync prod DB and assets
```

This gives you `https://{project}-legacy.ddev.site` — a running SS4 instance against the same data the upgrade will use. In the Build & Verify phase you'll diff this against the SS5 upgrade to confirm visual parity.

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

### SS6 Variant

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

# Replace deprecated packages
ddev composer require --no-update silverstripe/linkfield:^4.0
ddev composer remove --no-update sheadawson/silverstripe-linkable

# Remove incompatible
ddev composer remove --no-update nathancox/embedfield

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

> **SS6 note:** BuildTask signature changed (`run($request)` → `execute(InputInterface, PolyOutput): int`). See [references/data-migration-tasks.md](references/data-migration-tasks.md) for the SS6 Symfony Console Command template.

### SS6 Configuration Migration

#### ArrayList namespace migration
SS6 moved `SilverStripe\ORM\ArrayList` to `SilverStripe\Model\List\ArrayList`. Every PHP file using `use SilverStripe\ORM\ArrayList` will break. Use a global grep + replace:

```bash
rg "use SilverStripe\\\\ORM\\\\ArrayList" app/src/ themes/*/code/
# Replace with: use SilverStripe\Model\List\ArrayList;
```

#### BuildTask signature change
SS6 changes `run($request)` → `execute(InputInterface, PolyOutput): int` returning `Command::SUCCESS`.

Key differences:
| SS4/SS5 | SS6 |
|---------|-----|
| `protected $title = '...'` | `protected static string $title = '...'` |
| `protected $description = '...'` | `protected static string $description = '...'` |
| `private static $segment = '...'` | `protected static string $commandName = '...'` (required) |
| `public function run($request)` | `public function execute(InputInterface $input, OutputInterface $output): int` |
| `echo` / `$this->log()` | `$output->writeln()` |
| Implicit return | `return Command::SUCCESS` |

#### forTemplate() return type enforcement
SS6 enforces `: string` return type on `forTemplate()`. Never return `false` — return `` (empty string) instead.

```php
// SS5 (still works but triggers deprecation)
public function forTemplate() { return false; }

// SS6
public function forTemplate(): string { return ''; }
```

#### BaseElement::getDescription() removed
SS6 Elemental removed `BaseElement::getDescription()`. Use `private static string $class_description` instead:

```php
// SS5
public function getDescription() { return "My element"; }

// SS6
private static string $class_description = "My element";
```

#### DDEV database socket config
SS6 defaults to MySQL unix socket connections. DDEV uses TCP, so add to `.ddev/config.yaml`:

```yaml
web_environment:
  - SS_DATABASE_SERVER=db
```

Without this, `dev/build` fails with "Connection refused."

#### TinyMCE extraction
SS6 extracted TinyMCE from `silverstripe/admin` into `silverstripe/htmleditor-tinymce ^1.0`. If missing, the CMS Content field silently degrades to a plain textarea (`data-editor="textarea"` instead of `data-editor="tinyMCE"`).

**Fix:** Add to `composer.json` under `require` (not `require-dev`):
```json
"silverstripe/htmleditor-tinymce": "^1.0"
```

**Diagnostic:**
```javascript
document.querySelector('[data-editor]').dataset.editor
// Returns "textarea" instead of "tinyMCE" → missing package
```

#### Theme template API changes: Linkable → LinkField

When migrating from `sheadawson/silverstripe-linkable` to `silverstripe/linkfield`:

| Linkable (SS5) | Linkfield (SS6) | Notes |
|----------------|-----------------|-------|
| `$Link` | `$URL` | `getURL()` not `getLink()` |
| `$LinkURL` | `$URL` | Consistent — use `$URL` |
| `$OpenInNewWindow` | `$OpenInNew` | Renamed attribute |
| `$MenuTitle` | `$Title` | Different semantics — `getMenuTitle()` returns type label |
| `$Site` (SocialLink) | `$SocialChannel` | Enum → varchar mapping |
| `$X.setStyle('classes')` | Explicit `<a class="...">` | Linkfield renders bare links |
| `<% loop $HasOneLink %>` | `<% with $HasOneLink %>` | has_one is not iterable in SS6 |
| `$ElementLink.LinkURL` | `$ElementLink.URL` | Namespace change on sub-properties |

#### SS6 silent config breakers

**SeoExtension removal** — Remove from `SiteTree.extensions` if using a third-party search provider:
```yaml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\Base\Extension\SeoExtension  # REMOVE — causes PHP worker hangs in SS6
```

**PasswordValidator** — SS6 defaults to `EntropyPasswordValidator`. Old `RulesPasswordValidator` config is silently ignored:
```yaml
# SS6 format (NOT the old min_test_score / test_names pattern)
SilverStripe\Security\PasswordValidator:
  password_strength: 3  # 0-4 scale
```

**Session cookie defaults** — SS6 sets `SameSite=Strict`, `cookie_secure=true`. All existing sessions invalidated on first deploy — expected and normal. Verify:
```bash
curl -sI https://site.example.com/ | grep -i 'set-cookie'
# Expected: PHPSESSID=...; path=/; secure; HttpOnly; SameSite=Strict
```

## Phase 5: Data Migration

See [references/data-migration-tasks.md](references/data-migration-tasks.md) for the BuildTask pattern, migration principles, and common scenarios (Linkable → Link, ManyMany → LinkField).

## Phase 6: Build & Verify

```bash
ddev sake dev/build "flush=1"
ddev sake dev/tasks/<task-segment>
```

Verify: Homepage, carousel, navigation, footer, CMS admin, elemental blocks.

### Visual regression (optional but recommended)

Diff the SS5 upgrade against the legacy SS4 instance from Phase 1.4:

```bash
# Crawl the legacy site for a URL list
cd ~/Sites/{project}-legacy
python ../visual-regression-upgrade/scripts/crawl_urls.py \
  --url https://{project}-legacy.ddev.site --limit 30 --out paths.txt

# Capture + diff both environments
cd ~/Sites/{project}
python ../visual-regression-upgrade/scripts/capture.py \
  --prod https://{project}-legacy.ddev.site \
  --local https://{project}.ddev.site \
  --paths-file ../{project}-legacy/paths.txt \
  --out ./vr-out

python ../visual-regression-upgrade/scripts/diff_report.py \
  --in ./vr-out --out ./vr-out/report
```

See the [visual-regression-upgrade](../visual-regression-upgrade/SKILL.md) skill for setup, auth, mask config, and report interpretation.

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

## SS6 Breaking Changes

> [!WARNING]
> **ArrayList namespace moved in SS6.** `SilverStripe\ORM\ArrayList` → `SilverStripe\Model\List\ArrayList`. Every `use` statement referencing the old namespace will cause a fatal error at runtime. Systematic grep — see Phase 4.
>
> **BuildTask signature changed in SS6.** `run($request)` → `execute(InputInterface $input, PolyOutput $output): int` returning `Command::SUCCESS`. All custom BuildTask subclasses must be updated.
>
> **TinyMCE extracted.** `silverstripe/htmleditor-tinymce ^1.0` must be in `require` (not `require-dev`). If missing, CMS Content fields silently degrade to plain textareas — no error, no console warning.
>
> **DB connection default changed.** SS6 defaults to unix socket. DDEV requires `SS_DATABASE_SERVER=db` in `.ddev/config.yaml`.
>
> **Linkable removed.** `sheadawson/silverstripe-linkable` has no SS6 version. Replace with `silverstripe/linkfield ^4.0`. Template API changes documented above.
>
> **Embedfield replaced.** `nathancox/embedfield` has no SS6 version. Replace with `fromholdio/silverstripe-embedfield ^5.1`.

## Phase 7: Code Quality

See [references/code-quality.md](references/code-quality.md) for PHPCS/PHPStan steps.

## Phase 8: Commit & PR

Conventional commit format, checklist PR body.

## Phase 9: UAT

Deploy to UAT, run migration tasks, verify frontend and CMS, troubleshoot.

## Phase 10: Production

### 10.1 Deployment sequencing decision

If your SS6 upgrade also bundles AI modules, pre-1.0 packages, or other high-risk additions, consider splitting into **two deploys** — SS6 cutover first, followed by the new modules. Stacking pre-release packages on a cutover deploy increases blast radius and makes rollback more complex.

### 10.2 DeployHQ (or comparable CD) repoint

The deploy branch must be changed from the old SS5 branch (e.g., `4` or `5`) to `master` (SS6). Do this **right before deploying**, not in advance — the old branch is the rollback target.

**Pre-deploy checks:**

```bash
# Verify composer install succeeds
composer install --no-dev

# Verify all runtime deps are in "require", not "require-dev"
# Notably: silverstripe/htmleditor-tinymce must be in "require"
composer show --direct | grep htmleditor
```

**Private repo access:** If the upgrade adds modules from private GitHub repos (e.g., `silverstripeltd/ai-*`), ensure the production server has an SSH deploy key with read access to the organization before cutover day. Test with a manual `composer install` if possible.

### 10.3 Database strategy

**Option A (recommended): Push local/UAT DB to prod before cutover.** Run migrations locally, then use `deploy.sh --db` to push the already-migrated database to production. No migration tasks needed on prod — it's a drop-in replacement.

**Option B: Run migration on production's existing DB.** Deploy SS6 code, run `dev/build flush=1`, then run each migration task sequentially. Riskier — if a migration fails mid-way, the site is partially broken and requires schema repair.

### 10.4 Post-deploy smoke test

```bash
# Key pages load
for u in / /work /blog /admin/ /about/about-us; do
  curl -sI "https://$DOMAIN$u" | head -3
done

# Verify no redirect loop
curl -sI "https://$DOMAIN/" | grep -c "301\|302"  # should be 0

# Verify CMS renders (WYSIWYG check — log in, edit a page)
# Expected: data-editor="tinyMCE" on Content field
```

### 10.5 Expected post-deploy behaviors

- **All users logged out** on first deploy — SS6's `SameSite=Strict` session cookie default invalidates existing sessions. Normal and expected.
- **TinyMCE missing** → plain textarea — add `silverstripe/htmleditor-tinymce ^1.0` to `require`.
- **Migration tasks not found** `dev/build flush=1` not yet run, or `commandName` property missing from the Symfony Console task.
- **Custom `forTemplate()` methods crash** — add `: string` return type.

## Reference Documentation

| Topic | File |
|-------|------|
| Data Migration Tasks | [data-migration-tasks.md](references/data-migration-tasks.md) |
| SS5 Version Map | [version-map.md](references/version-map.md) |
| SS6 Version Map | [version-map-ss6.md](references/version-map-ss6.md) |
| Code Quality | [code-quality.md](references/code-quality.md) |

## Recipe Branch Convention (SS6)

When branching repositories for an SS6 upgrade, use:

| CMS Version | Recipe Branch | Elemental Branch | Base-site Branch |
|-------------|---------------|------------------|-----------------|
| SS4 | `1` | `master` (deprecated) | `5` |
| SS5 | `2` | `5` | `7` |
| SS6 | `3` | `6` | `8` |

Recipes follow `recipe-cms` major version numbering. Elemental modules follow their own major version (elemental ^6 = branch `6`). Set the new version branch as the default branch on GitHub for each recipe/module repo.

## Related skills

- [silverstripe-3-to-4-upgrade](../silverstripe-3-to-4-upgrade/SKILL.md) — the prior, structurally different leg. Use it for legacy SS3 → SS4 projects before this skill applies.
- [ss5-data-migration](../ss5-data-migration/SKILL.md) / [ss6-data-migration](../ss6-data-migration/SKILL.md) — version-specific data-migration runbooks for the BuildTasks in Phase 5.
- [visual-regression-upgrade](../visual-regression-upgrade/SKILL.md) — capture pixel diffs against the legacy instance to confirm parity (also linked inline in the Build & Verify phase).
