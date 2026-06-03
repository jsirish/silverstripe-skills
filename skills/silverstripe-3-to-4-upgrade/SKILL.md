---
name: silverstripe-3-to-4-upgrade
description: >
  Complete workflow for upgrading legacy Silverstripe 3 projects to Silverstripe 4.
  Use when upgrading SS3 projects, migrating namespaces, or resolving database schema blockers.
  Covers directory structure changes, namespace migration, database schema preflight fixes,
  data migration tasks (Files, Blocks to Elemental), and template adjustments.

---

# Silverstripe 3 to 4 Upgrade Skill

Repeatable workflow for upgrading legacy Silverstripe 3 projects to Silverstripe 4. Based on the successful migration of Example Manufacturing, this guide details the exact steps, architectural shifts, and critical gotchas when moving to the SS4 framework.

## Philosophy: parity, not redesign

> [!IMPORTANT]
> **An SS3→SS4 upgrade is a data + markup-parity exercise, not a redesign.** The goal is behavioural and visual parity with the SS3 site — achieved by migrating the data and **reproducing the existing markup** — not by improving or modernising the theme.
>
> - When a page looks wrong after the upgrade, the **default hypothesis is a missing wrapper element or a dropped legacy CSS class** — not a layout that needs re-authoring. The SS3 CSS almost always still works once the markup it targets is back.
> - Reproduce the SS3 markup exactly; defer any genuine redesign to a separate, post-parity phase.
> - This is the same "one rule" the [block-to-element-migration](../block-to-element-migration/SKILL.md) skill applies at the block-template level ("preserve the legacy HTML structure exactly") — it holds for the whole project, not just blocks.
>
> The overwhelming majority of visual-regression FAILs on a real migration traced to exactly this: a missing `<div>` or a dropped class, not layout that needed rebuilding. See [references/page-layout-parity.md](references/page-layout-parity.md) for the concrete page-layout parity fixes.

## Upgrade Phases (Summary)

| Phase | Key Actions |
|-------|-------------|
| **1. Assessment** | **Package audit** (`git diff branch-1 -- composer.json`) · spin up legacy ddev instance · plan block-to-elemental migration |
| **2. Architecture** | Move `mysite` to `app`, introduce `public` directory |
| **3. Dependencies** | Update `composer.json` to `^4.0`, update PHP constraints |
| **4. Namespaces** | Apply PSR-4 namespaces, remap config class names |
| **5. DB Preflight Fixes** | Clear `__TEMP__` table collisions and `FULLTEXT` indices |
| **6. Data Migration** | Run file migration, Elemental tasks, and custom SQL |
| **7. Templates** | Fix `HomePage` resolution, update `$Link` to `$URL` |
| **8. Build & Verify** | `dev/build flush=1`, QA frontend and admin |
| **9. Code Quality & CI** | PHPCS, PHPStan, PHPUnit, GitHub Actions |

## Phase 1: Assessment & Discovery

### 1a. Package Audit (do this before writing any upgrade code)

Run the composer diff against the legacy branch immediately after branching:

```bash
git diff branch-1..feature/silverstripe-4-upgrade -- composer.json
```

For every package **removed** from `require`, document:
- What feature/UI it provided on the frontend
- Whether SS4 has a drop-in replacement (`composer show` or packagist)
- Whether it stored data that needs migrating (DataObject tables in the DB)

**Common removals and their consequences:**

| SS3 Package | What it provided | SS4 situation |
|-------------|-----------------|---------------|
| `silverstripe/widgets` | Blog sidebar widgets (archive, tags, categories, recent posts) | `silverstripe/blog ^3.x` still supports widgets — use `silverstripe/widgets ^2.x`. Require it explicitly (`composer require silverstripe/widgets "^2.4"`), apply `WidgetPageExtension` to Blog + BlogPost in extensions.yml, and backfill `Widget_Live`/`WidgetArea_Live` tables after prod sync (Widget and Widget_Live have different column order — use explicit column lists). |
| `sheadawson/silverstripe-blocks` | Arbitrary content blocks on pages | Replace with `dnadesign/silverstripe-elemental`. Requires `BlockMigrationTask`. |
| `dynamic/dynamic-blocks` | Same as sheadawson blocks, Dynamic flavour | Same as above. |
| `dynamic/core-tools` | `GlobalSiteSetting`, various helpers | Not available for SS4 — recreate `GlobalSiteSetting` as a custom DataObject. |
| `silverstripe/secureassets` | Protected file storage | Merged into SS4 core (`silverstripe/assets`). No action needed, but verify `.protected/` path. |
| `heyday/silverstripe-versioneddataobjects` | Versioning for non-Page DataObjects | Replaced by `Versioned` extension (built into SS4). Remove and add `$extensions = [Versioned::class]` manually. |
| `dynamic/flexslider` | Slider block type | Rebuilt as `ElementPageSection` or similar custom Elemental element. |
| `i-lateral/silverstripe-searchable` | Site search | Version `^2.0` available for SS4. |

> [!WARNING]
> **`silverstripe/widgets` + blog**: If the SS3 site used blog sidebar widgets, add `silverstripe/widgets ^2.4` to the SS4 project. It is NOT dropped in blog ^3.x — it's optional. After requiring it: (1) apply `WidgetPageExtension` to Blog + BlogPost in extensions.yml, (2) run dev/build, (3) backfill `Widget_Live` and `WidgetArea_Live` from draft tables using explicit column lists (column order differs between draft and live tables). Don't rely on `INSERT INTO _Live SELECT * FROM table` — it silently corrupts data.

### 1b. Legacy ddev instance

For any non-trivial upgrade, spin up a parallel local instance of the SS3 branch **before** starting the upgrade. This gives you a pixel-perfect reference to diff against when you reach the VR phase.

**Naming convention:** clone into `~/Sites/{project}-legacy` so the upgrade lives in `~/Sites/{project}` — the VR skill relies on this pattern.

```bash
cd ~/Sites
git clone <repo> {project}-legacy
cd {project}-legacy
git checkout 1  # or whatever the legacy branch is
ddev config --project-name {project}-legacy --project-type php --php-version 7.4
ddev start
ddev auth ssh
ddev exec ./sync.sh  # sync prod DB and assets
```

This gives you `https://{project}-legacy.ddev.site` — a running SS3 instance against the same data you're upgrading. Use it to:
- Confirm what each URL renders on SS3 before starting SS4 work
- Run VR captures against `{project}-legacy.ddev.site` instead of the live prod URL (eliminates content drift between your DB snapshot and live prod)
- Diagnose "was this feature even working on prod?" without loading the live site

> [!TIP]
> The **example-custom** project is the canonical reference for this pattern: `~/Sites/example-custom` (SS4 upgrade) vs `~/Sites/example-custom-legacy` (SS3 legacy). Also see `~/Sites/example-multiarea` / `~/Sites/example-multiarea-legacy` for an earlier example. Both use the same ddev config structure.

### 1c. Package Assessment (original step)

1. **Audit Packages**: Determine which legacy SS3 modules can be replaced with native SS4/Dynamic equivalents.
2. **Block Assessment**: Legacy `dynamic/dynamic-blocks` (or `sheadawson/silverstripe-blocks`) must be mapped to `dnadesign/silverstripe-elemental`.
3. **Database Backup**: Ensure a complete local sync or backup before running dev/build, as obsolete tables will be renamed.

## Phase 2: Architecture & Directory Structure

Silverstripe 4 requires a restructured root directory:
- Rename the `mysite` directory to `app`.
- Move `Page.php` and `PageController.php` to `app/src/`.
- Introduce a `public` directory. Move `assets/` into `public/assets/`, and create `public/index.php` and `public/.htaccess`.

## Phase 3: Dependencies & Branching

```bash
git checkout -b feature/silverstripe-4-upgrade

# Update PHP requirement in composer.json to minimum PHP 7.4
```

**Common Package Replacements:**
- `dynamic/dynamic-blocks` ➔ `dnadesign/silverstripe-elemental`
- `dynamic/core-tools` ➔ `dynamic/silverstripe-site-tools` (via `silverstripe-base-site`)
- Add `dynamic/silverstripe-base-site: ^4.0`

Run `composer update --with-all-dependencies` and `composer vendor-expose`.

**Add modern dev dependencies** (the standard Dynamic toolkit for quality and debugging):

```json
"require-dev": {
    "cambis/silverstan": "^1.0",
    "ergebnis/composer-normalize": "^2.44",
    "lekoala/silverstripe-debugbar": "^3.0",
    "phpstan/extension-installer": "^1.3",
    "phpunit/phpunit": "^9.6",
    "silverleague/ideannotator": "~3.5.1",
    "silverstripe/recipe-testing": "^2.0",
    "squizlabs/php_codesniffer": "^3.10",
    "wernerkrauss/silverstripe-rector": "^2.0"
}
```

> [!NOTE]
> **Version adjustments per SS major**: The constraints above target SS4. For SS5+ projects bump `cambis/silverstan` to `^2.1` and `silverstripe/recipe-testing` to `^3.0`. Check the latest release on Packagist if in doubt.

## Phase 4: Code & Namespace Migration

SS4 heavily relies on PHP namespaces.

1. **Namespacing**: Add namespaces to all classes in `app/src/` (e.g., `namespace App\Pages;` or `namespace Dynamic\Base\Page;`).
2. **Add PSR-4 autoload to `composer.json`** — required for the namespaced classes to load:

> [!TIP]
> **Automate namespace migration with silverstripe-rector**: Instead of adding namespaces manually, use `wernerkrauss/silverstripe-rector` to automate the bulk of the work. After requiring it (see dev-dependencies above), configure `rector.php` and run:
> ```bash
> vendor/bin/rector --dry-run
> vendor/bin/rector  # apply when ready
> ```
> This handles class-rename patterns, PSR-4 restructuring, and many SS3 deprecation fixes that are tedious to do by hand. See [github.com/wernerkrauss/silverstripe-rector](https://github.com/wernerkrauss/silverstripe-rector) for available rule sets.


   ```json
   "autoload": {
     "psr-4": { "App\\": "app/src/" }
   }
   ```
3. **DB ClassName Remapping** (`DatabaseAdmin.classname_value_remapping`): map every SS3 short class name stored in the database to its SS4 namespaced equivalent. This runs during `dev/build` and rewrites all `ClassName` columns across every table (including `_Live` and `_Versions`). Without it, SS4 can't resolve the stored class names and pages fall back to `Page.ss` (or render blank). This is **separate from** any Injector/config class aliasing — both may be needed. The remapping is **idempotent** and safe to leave in place during the migration window; remove it once all migrated data is confirmed working. Add to `app/_config/app.yml`:
   ```yaml
   SilverStripe\ORM\DatabaseAdmin:
     classname_value_remapping:
       Page: 'App\Pages\Page'
       HomePage: 'App\Pages\HomePage'
       # ... all other page types and custom DataObjects
       Blog: 'SilverStripe\Blog\Model\Blog'
       BlogPost: 'SilverStripe\Blog\Model\BlogPost'
       # Orphaned SS3 vendor pages with no SS4 equivalent — map to generic App\Pages\Page:
       EventHolder: 'App\Pages\Page'
       EventPage: 'App\Pages\Page'
   ```
   Query the DB first to discover every `ClassName` value that needs remapping:
   ```bash
   ddev exec "mysql -udb -pdb db -e \"SELECT DISTINCT ClassName, COUNT(*) FROM SiteTree GROUP BY ClassName;\""
   ```
4. **SSViewer themes config** — include `$public` and `$default` so vendor module templates resolve:
   ```yaml
   SilverStripe\View\SSViewer:
     themes:
       - '$public'
       - mytheme
       - '$default'
   ```
5. **Legacy Method Signatures**: SS4 alters some Core method signatures.
   - Example: `Permission::check($member, 'any')` in SS3 is now `Permission::check('any', 'any', $member)` or simpler. Remove legacy 'any' parameters if causing type errors.

## Phase 5: Database Schema Preflight Fixes

Before `dev/build` can succeed, you must resolve SS3 legacy schema blockers.

> [!CAUTION]
> **FULLTEXT Indexes on File**: SS3 `File` tables sometimes have a FULLTEXT index on `Filename` which blocks SS4 column updates.
> ```sql
> ALTER TABLE File DROP INDEX SearchFields;
> ```

> [!WARNING]
> **__TEMP__ and _Versions Table Collisions**: SS4 dev/build creates `__TEMP__` tables to migrate data. SS3 `_Versions` tables from the source DB collide with SS4's rename target. On every fresh prod sync (and after any crashed dev/build), drop both kinds in a single statement before re-running dev/build.
>
> See [references/db-rebuild-conflicts.md](references/db-rebuild-conflicts.md) for the bulk-drop snippet and edge cases. The TL;DR: a single `DROP TABLE IF EXISTS \`a\`,\`b\`,\`c\`,...;` statement is dramatically faster than the per-table loop you'll find in older guides.

## Phase 6: Data Migration Tasks

Run the following tasks sequentially. Custom tasks (`BlockMigrationTask`, `FormParentClassMigrationTask`) are usually required to handle project-specific business logic using raw SQL.

1. **Fix Corrupted ParentClasses (Critical)**
   ```bash
   ddev sake dev/tasks/form-parent-migration
   ```
   *Note: Resolves un-namespaced ClassNames on EditableFormField records to prevent publishRecursive crashes.*

2. **Migrate Files to Hash-Based Storage**
   ```bash
   ddev sake dev/tasks/MigrateFileTask
   ```

3. **Migrate Inline Content to Elemental**
   ```bash
   ddev sake dev/tasks/DNADesign-Elemental-Tasks-MigrateContentToElement
   ```

4. **Custom Block Migration**
   ```bash
   ddev sake dev/tasks/block-migration
   ```
   *Note: Dev/build renames obsolete classes to `_obsolete_PromoObject`. The custom task must read from these `_obsolete_` tables via `DB::query()` and write to Elemental tables.*

   **➡ Use the dedicated [block-to-element-migration](../block-to-element-migration/SKILL.md) skill** for the full workflow: discovery, page-model setup, the migration-task skeleton, the legacy-template → element-template duplication pattern, the area-suffix template convention (`Element_RelationName.ss`), and verification. The earlier inline reference at [references/block-to-elemental-migration.md](references/block-to-elemental-migration.md) is preserved as the seed material the new skill was distilled from.

## Phase 7: Templates & Front-End

- **Variables**: Update `$Link` to `$URL` in template `.ss` files.
- **Elemental Areas**: Replace `<% with $Blockarea(AreaName) %>` with `$ElementalArea` or specific area relations like `$ElementalHomePage`.

> [!IMPORTANT]
> **Page-layout parity is the biggest single source of VR FAILs** — bigger than block/element parity. Empty `block_area_*` wrapper divs that control margin-collapse, `WidgetHolder` structure, `SectionNavigationBlock` replacements, and `MenuTitle` vs `Title` nav text all need reproducing at the **layout-template** level. See [references/page-layout-parity.md](references/page-layout-parity.md) for each fix with the SS3 markup shown beside the SS4 equivalent.

> [!IMPORTANT]
> **Namespaced Base Templates**: SS4 resolves base templates by namespace path first. `Dynamic\Base\Page\HomePage` expects `themes/mytheme/templates/Dynamic/Base/Page/HomePage.ss` — NOT `templates/HomePage.ss`. Without this, the page falls back to `Page.ss`, potentially ruining full-width layout constraints.

> [!NOTE]
> **Template Variant Naming**: Elemental uses `getAreaRelationName()` for suffixing. If a page has `has_one: ['ElementalHomePage' => ElementalArea::class]`, the variant file must be named `ElementPromos_ElementalHomePage.ss`.

## Phase 8: Build & Verify

1. **Run dev/build**:
   ```bash
   ddev sake dev/build flush=1
   ```
2. **Frontend QA**: Walk through the primary page types — HomePage, standard pages, blog, any custom page types. Check for:
   - Blank pages (likely a missing `classname_value_remapping` entry or broken template resolution)
   - Template fallback issues — use `?showtemplate=1` to confirm the correct template is resolving
   - Broken images — `jonom/focuspoint` migrations require template updates
3. **CMS admin QA**: Verify pages load in the CMS tree, elements render in the Elemental editor, and the SiteConfig / Settings section works.
4. **Elemental areas**: Confirm `_Live` tables are populated. If ElementalArea_Live is empty, run the migration task's final SQL passes (see [block-to-element-migration](../block-to-element-migration/SKILL.md)).

5. **Visual regression** — prove pixel parity between the SS3 legacy site and the SS4 upgrade:
   ```bash
   # Using the legacy instance from Phase 1b
   cd ~/Sites/{project}-legacy
   python ../visual-regression-upgrade/scripts/crawl_urls.py \
     --url https://{project}-legacy.ddev.site --limit 30 --out paths.txt

   cd ~/Sites/{project}
   python ../visual-regression-upgrade/scripts/capture.py \
     --prod https://{project}-legacy.ddev.site \
     --local https://{project}.ddev.site \
     --paths-file ../{project}-legacy/paths.txt \
     --out ./vr-out

   python ../visual-regression-upgrade/scripts/diff_report.py \
     --in ./vr-out --out ./vr-out/report
   ```
   See the [visual-regression-upgrade](../visual-regression-upgrade/SKILL.md) skill for setup, auth, mask config, and report interpretation. The legacy-vs-upgrade capture eliminates content-drift false positives and catches layout regressions manual QA misses.

## Phase 9: Code Quality & CI

After the upgrade builds and renders, lock down code quality with automated tools and CI:

> [!WARNING]
> **Verify the shipped QA config actually runs before trusting the gate.** The installer-provided config is frequently stale on an upgrade and fails silently:
> - `phpstan.neon` often does `includes: phpstan-baseline.neon`, but that baseline file **doesn't exist** — PHPStan won't run at all until you create it (`--generate-baseline`) or remove the include.
> - `phpunit.xml.dist` often points at a vendor test dir that isn't installed (e.g. `vendor/<org>/<tools>/tests`), and there may be **no `app/tests/` directory** at all.
>
> Run each tool once and confirm it executes — "the config is present" is not the same as "the gate runs."

1. **PHPCS** — enforce coding standards:
   ```bash
   ddev exec vendor/bin/phpcs app/src/
   ```
   Common SS3→SS4 issues PHPCS catches: missing namespace declarations, outdated class references, PSR-2/PSR-12 formatting.

2. **PHPStan** — static analysis (if configured):
   ```bash
   ddev exec vendor/bin/phpstan analyse app/src/
   ```
   Run at level 1–2 initially; the SS4 upgrade introduces many dynamic calls that require baseline configuration. Use `--generate-baseline` to create a `phpstan-baseline.neon` for known false positives.

   **Wire in the SilverStripe extension or you'll drown in false positives.** Plain PHPStan flags every SS magic method (`$page->StaffMembers()`, `$this->UtilityLinks()`, `getStaffMembers()`) as an error. `cambis/silverstan` (already in the dev-deps in [Phase 3](#phase-3-dependencies--branching)) teaches PHPStan about SS's dynamic ORM/`has_one`/`has_many` calls. With `phpstan/extension-installer` present it auto-registers; otherwise add it to `phpstan.neon`:
   ```neon
   includes:
       - vendor/cambis/silverstan/extension.neon
   ```
   Then `--generate-baseline` to adopt the gate incrementally rather than fixing every legacy finding at once. Match the silverstan major to the target CMS major (`^1.0` for SS4, `^2.1` for SS5+).

3. **Rector** — automated refactoring validation:
   ```bash
   ddev exec vendor/bin/rector --dry-run
   ```
   `silverstripe-rector` catches deprecated API usage, class-rename patterns, and namespace issues that phpstan/phpcs miss. Always run with `--dry-run` first to review changes. See the [Phase 4 tip](#phase-4-code--namespace-migration) for installation and configuration.

4. **PHPUnit** — run the existing test suite:
   ```bash
   ddev exec vendor/bin/phpunit
   ```
   If no tests exist yet, this is the ideal time to add smoke tests for the upgraded page types and Elemental elements.

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

## Key Discoveries & Gotchas

> [!CAUTION]
> **Global `Page` class is required — do NOT delete it.** Multiple vendor modules do `use Page` and extend it: `silverstripe/errorpage`, `silverstripe/blog`, `silverstripe/userforms`, `silverstripe/cms` (SiteTree, RedirectorPage, VirtualPage), `dnadesign/silverstripe-elemental`, and `silverstripe/framework` (Security). The file `app/src/Page.php` must define a **global-namespace** `Page extends SiteTree`. Custom project logic goes in `App\Pages\Page extends \Page`. Deleting the global Page causes a fatal during class-manifest build.

> [!CAUTION]
> **A global `\PageController` must exist for base Page controller resolution.** `SiteTree::getControllerName()` walks the class ancestry appending `"Controller"`. For generic `Page`/`App\Pages\Page` records (including those remapped to `App\Pages\Page`), it looks for a `PageController`. Without it, those records fall back to `ContentController` and theme rendering breaks.
>
> Put the global controller in its **own file** `app/src/PageController.php` (NOT inside `Page.php`) and register **both** in the composer `classmap` — add `classmap` as a sibling **inside** the same `autoload` block that retains `psr-4`:
> ```jsonc
> // composer.json
> "autoload": {
>   "psr-4": { "App\\": "app/src/" },
>   "classmap": ["app/src/Page.php", "app/src/PageController.php"]
> }
> ```
> ```php
> // app/src/PageController.php
> class PageController extends App\Controllers\PageController {}
> ```
> If `Page` and `PageController` share one file, the class manifest + classmap can re-include it and fatal with "Cannot declare class Page." Also match the framework `init()` contract: `protected function init()` with **no** return type — `public function init(): void` fatals under PHP 8 against vendor controllers whose parent declares `protected init()`.

> [!WARNING]
> **Gitignored SS3 module directories block dev/build.** If old module dirs (`silverstripe-versioneddataobjects/`, `widgets/`, `userforms/`, etc.) are gitignored but still present on disk, SS4's class manifest scans them and finds SS3-incompatible classes — fatals like `Class "Versioned" not found`. Delete them (`rm -rf silverstripe-versioneddataobjects`) and document it for new devs (README/`devbuild.sh`). The same applies to stray PHP under `.claude/worktrees/` — drop a `.claude/_manifest_exclude` marker so the manifest skips it.

> [!WARNING]
> **Raw SQL is Mandatory**: When migrating from SS3 modules that are removed in SS4 (like `dynamic-blocks`), the ORM strips legacy `$db` properties. You cannot rely on `$block->Title` during migration. You must query the legacy `Block` and `Block_Live` tables using raw `DB::query()` and `INSERT ON DUPLICATE KEY UPDATE` into the new Elemental records.

> [!TIP]
> **PublishRecursive Dangers**: Running `publishRecursive()` on a root page will validate all child elements, including forms. If a UserForms setup has obsolete SS3 class names in the database (e.g., `EditableEmailField` instead of `SilverStripe\UserForms\Model\EditableFormField\EditableEmailField`), the publish will fatal error. Always write a migration task to fix `ClassName` rows in the DB before publishing.

> [!IMPORTANT]
> **Image Resize Methods**: `jonom/focuspoint` is rarely carried over to SS4. Update template tags from `$Image.FocusFill()` to `$Image.Fill(X, Y)` or native SS4 crop functions.

> [!WARNING]
> **ElementalArea_Live is empty after dev/build**: SS4 `dev/build` creates `ElementalArea` rows on the **draft** table only. Elements you placed during migration won't appear on the frontend until both `ElementalArea_Live` and `Page_Live.ElementalAreaID` are populated. The block migration task must end with these two SQL passes. Full pattern: [block-to-element-migration](../block-to-element-migration/SKILL.md).

> [!WARNING]
> **Versioned writes need _Live AND _Versions**: When inserting Elements via raw SQL, write to all three tables: base draft, `_Live`, and `_Versions`. Skipping `_Versions` causes "no history" errors when editing in the CMS and can cause `publish()` to silently strip the record from `_Live`. See the `insertVersionedRow()` helper in [block-to-element-migration/references/migration-task-skeleton.md](../block-to-element-migration/references/migration-task-skeleton.md).

> [!TIP]
> **Legacy CSS still works if you keep the old class**: SS3 themes scope CSS to block class names (`.pagesectionblock`, `.promoblock`, etc.). Elemental's `$CSSClasses` outputs `element app__elements__elementpagesection` instead. To retain existing CSS during migration, add the legacy class to the Elemental wrapper template: `<div class="$CSSClasses pagesectionblock">`. Defer full CSS rewrite until after migration is verified.

> [!WARNING]
> **JS-dependent CSS will collapse**: SS3 themes often use JavaScript to set fixed heights on block containers, then position child elements absolutely within them. With the JS gone, `position: absolute` children leave the parent at height 0 and the layout collapses. When migrating, remove `vert-centering` (or equivalent) classes from element templates — don't try to revive the JS.

> [!NOTE]
> **Template debugging**: in dev mode, append `?showtemplate=1` to any URL to see which template SS4 resolved for that page, and `?flush=1` to clear the template cache. Installing `lekoala/silverstripe-debugbar` (SS4) adds a toolbar showing the resolved controller, the template chain, and DB queries — invaluable when a namespaced class silently falls back to `Page.ss`.
