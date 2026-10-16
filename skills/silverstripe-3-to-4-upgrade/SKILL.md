---
name: silverstripe-3-to-4-upgrade
description: >
  Upgrade legacy Silverstripe 3 projects to Silverstripe 4 - mysite to app, namespaces,
  ClassName remapping, DB preflight fixes, file and block-to-Elemental data migration, templates.
  Use for "upgrade SS3 to SS4", "SS3 migration", "mysite to app", or dev/build schema errors on a legacy site.
---

# Silverstripe 3 to 4 Upgrade Skill

Repeatable workflow for upgrading legacy Silverstripe 3 projects to Silverstripe 4. This guide details the steps, architectural shifts, and gotchas when moving to the SS4 framework.

> **Companion skills** (install separately from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills)): `ddev-sync` (Phase 1b prod sync) and `visual-regression-upgrade` (Phase 8 parity check).

> **Scope**: This skill is **SS3 → SS4 specific** — a one-time structural migration. Once a project is on SS4, use the [silverstripe-version-upgrade](../silverstripe-version-upgrade/SKILL.md) skill for SS4 → SS5 and later major-version bumps.

## Philosophy: parity, not redesign

> [!IMPORTANT]
> **Parity, not redesign — and minimal transformation.** An SS3→SS4 upgrade is a data + markup-parity exercise. The goal is behavioural and visual parity with the SS3 site — achieved by migrating data and **reproducing the existing markup** — not by improving or modernising anything.
>
> Apply one test before any action: **"does this already exist natively in SS4?"** If a field, class, or data shape already exists in the target version, it must not be transformed. Only run a migration task for data that a **removed or renamed class** genuinely requires.
>
> - **Markup side:** When a page looks wrong after the upgrade, the default hypothesis is a missing wrapper element or a dropped legacy CSS class — not a layout that needs re-authoring. The SS3 CSS almost always still works once the markup it targets is back. See [references/page-layout-parity.md](references/page-layout-parity.md).
> - **Data side:** When a migration task transforms content that was already native in SS4, it breaks things for zero benefit. Example: `MigrateContentToElement` moves `SiteTree.Content` into Elemental and blanks the field, but SS4 still has `$Content` natively and the theme renders it directly, so the task is not in the default sequence. See the CAUTION in [Phase 6](#phase-6-data-migration-tasks).
> - Defer any genuine redesign to a separate, post-parity phase. This is the same "one rule" the [block-to-element-migration](../block-to-element-migration/SKILL.md) skill applies at the block-template level — it holds for the whole project.

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
| `dynamic/core-tools` | `GlobalSiteSetting`, various helpers | Adopt `dynamic/silverstripe-base-site ^4.0` (see [Phase 3](#phase-3-dependencies--branching)), which provides the equivalent settings + navigation natively on `SiteConfig`. Then migrate the legacy `GlobalSiteSetting` data — scalars **and** its nav relations (header `UtilityLinks` junction, footer `NavigationColumns`) — onto `SiteConfig` with an idempotent `BuildTask`. Don't hand-recreate `GlobalSiteSetting` as a custom DataObject (a dead-end that has to be unwound later). Full migration-task pattern: [globalsitesetting-to-siteconfig.md](references/globalsitesetting-to-siteconfig.md). |
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

> [!NOTE]
> The `-legacy` instance hits the same Mutagen `upload_dirs` conflict as the upgrade instance on its
> first prod sync (`Mutagen sync completed with problems … unable to relocate staged file: file exists`).
> Set `upload_dirs: [assets]` in **both** projects' `.ddev/config.yaml`, then `ddev mutagen reset && ddev restart`.
> See the `ddev-sync` skill (from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills), installed separately), "Mutagen upload_dirs conflicts after prod sync", for details.

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

   ```json
   "autoload": {
     "psr-4": { "App\\": "app/src/" }
   }
   ```
   Also add a `classmap` for the global `Page` and `PageController` (see [references/key-discoveries.md](references/key-discoveries.md)).

> [!TIP]
> **Automate namespace migration with silverstripe-rector**: Instead of adding namespaces manually, use `wernerkrauss/silverstripe-rector` to automate the bulk of the work. After requiring it (see dev-dependencies above), configure `rector.php` and run:
> ```bash
> vendor/bin/rector --dry-run
> vendor/bin/rector  # apply when ready
> ```
> This handles class-rename patterns, PSR-4 restructuring, and many SS3 deprecation fixes that are tedious to do by hand. See [github.com/wernerkrauss/silverstripe-rector](https://github.com/wernerkrauss/silverstripe-rector) for available rule sets.

3. **DB ClassName Remapping** (`DatabaseAdmin.classname_value_remapping`): map every SS3 short class name stored in the database to its SS4 namespaced equivalent. It runs during `dev/build`, rewrites `ClassName` across every DataObject table (including `_Live` and `_Versions`), and is idempotent. Without it, pages fall back to `Page.ss` or render blank. Enumerate the values to remap with `SELECT DISTINCT ClassName` on `SiteTree` and `EditableFormField`. Don't write custom SQL to fix `ClassName`; `ParentClass` is the one column it does not cover. Config example, query commands and caveats: [references/classname-remapping.md](references/classname-remapping.md).
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

> [!TIP]
> **Capture the per-project task sequence as a `/migrate` slash command.** Every project ends up with its own ordered task list, environment table, and gotchas. Rather than re-inventing the structure each time, formalize it as `.claude/commands/migrate.md` so a developer can type `/migrate` and load the full runbook. A copy-paste starter — environments table, breaking-changes table, repeatable bash workflow, expected outputs, and verification checklist — is in [references/project-migration-command-template.md](references/project-migration-command-template.md). Keep the migration tasks in a `migrate.sh` runner (see the `devbuild.sh` convention in [Phase 8](#phase-8-build--verify)), not in `devbuild.sh`.

1. **Fix Corrupted ParentClasses (Critical)**
   ```bash
   ddev sake dev/tasks/form-parent-migration
   ```
   *Note: Fixes un-namespaced `ParentClass` values on EditableFormField records (`ClassName` is handled by `classname_value_remapping`) to prevent publishRecursive crashes.*

2. **Migrate Files to Hash-Based Storage**
   ```bash
   ddev sake dev/tasks/MigrateFileTask
   ```

> [!CAUTION]
> **`MigrateContentToElement` — only run for an intentional Elemental-only architecture, after backup.**
> This vendor task (`dev/tasks/DNADesign-Elemental-Tasks-MigrateContentToElement`) moves
> `SiteTree.Content` into an `ElementContent` block and then **blanks `Content`**. Do **NOT** run it
> unless you have a verified database backup/export and have confirmed that templates, search
> indexes, summaries/excerpts, feeds, and any custom code no longer read `SiteTree.Content`. If
> `Page.ss` / `BlogPost.ss` / your page templates output `$Content` directly — the Dynamic base-site
> default — it empties those fields and breaks blog listing excerpts (`$Summary`/`$Excerpt` derive
> from `Content`), content alignment, and anything else reading `$Content`, for no benefit.
>
> Decide with the minimal-transformation test from [Philosophy](#philosophy-parity-not-redesign):
> `SiteTree.Content` exists natively in both SS3 and SS4, so it must not be transformed. Only migrate
> content into Elemental if the project is *deliberately* adopting an Elemental-only content
> architecture. (This task is **not** in the default sequence for exactly this reason.)

3. **Custom Block Migration**
   ```bash
   ddev sake dev/tasks/block-migration
   ```
   *Note: Dev/build renames obsolete classes to `_obsolete_PromoObject`. The custom task must read from these `_obsolete_` tables via `DB::query()` and write to Elemental tables.*

   **➡ Use the dedicated [block-to-element-migration](../block-to-element-migration/SKILL.md) skill** for the full workflow: discovery, page-model setup, the migration-task skeleton, the legacy-template → element-template duplication pattern, the area-suffix template convention (`Element_RelationName.ss`), and verification. Legacy background: [references/block-to-elemental-migration.md](references/block-to-elemental-migration.md).

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

> [!IMPORTANT]
> **`devbuild.sh` is cache-rebuild-only; migration tasks go in a separate `migrate.sh` (or the `/migrate` slash command, see [Phase 6](#phase-6-data-migration-tasks)).** DeployHQ runs `devbuild.sh` after every code deploy, so it must never run data migrations. The 3-line canonical script and the reasoning are in [devbuild-vs-migrate.md](../silverstripe-version-upgrade/references/devbuild-vs-migrate.md).

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
   # Path to the installed skill; adjust for your agent's skills dir
   VR=~/.claude/skills/visual-regression-upgrade

   # Using the legacy instance from Phase 1b
   cd ~/Sites/{project}-legacy
   python "$VR"/scripts/crawl_urls.py \
     --url https://{project}-legacy.ddev.site --limit 30 --out paths.txt

   cd ~/Sites/{project}
   python "$VR"/scripts/capture.py \
     --prod https://{project}-legacy.ddev.site \
     --local https://{project}.ddev.site \
     --paths-file ../{project}-legacy/paths.txt \
     --out ./vr-out

   python "$VR"/scripts/diff_report.py \
     --in ./vr-out --out ./vr-out/report
   ```
   See the `visual-regression-upgrade` skill (from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills), installed separately) for setup, auth, mask config, and report interpretation. The legacy-vs-upgrade capture eliminates content-drift false positives and catches layout regressions manual QA misses.

## Phase 9: Code Quality & CI

Once the upgrade builds and renders, run PHPCS, PHPStan (with `cambis/silverstan`), Rector, and PHPUnit, then wire them into GitHub Actions. Check that the shipped `phpstan.neon` and `phpunit.xml.dist` actually execute before trusting the gate. Commands, CI YAML and caveats: [references/code-quality-ci.md](references/code-quality-ci.md).

## Key Discoveries & Gotchas

Read [references/key-discoveries.md](references/key-discoveries.md) when `dev/build` fatals or pages render wrong. It covers:

- The global `Page` and `PageController` classes (required, own file, classmap registration)
- Gitignored SS3 module directories breaking the class manifest
- Raw SQL for removed-module data, `_Live` and `_Versions` writes, and an empty `ElementalArea_Live`
- `publishRecursive()` failures from un-namespaced `ClassName` rows
- `jonom/focuspoint` image methods, legacy CSS classes, JS-dependent CSS collapse, template debugging

## Related skills

- [silverstripe-version-upgrade](../silverstripe-version-upgrade/SKILL.md) — **the next step**. Once the project is on SS4, use it for SS4 → SS5 and later major-version bumps (a recurring, recipe-driven upgrade, mechanically different from this one-time SS3 → SS4 migration).
- [block-to-element-migration](../block-to-element-migration/SKILL.md) — the dedicated Blocks → Elemental workflow referenced throughout Phase 6 (discovery, migration-task skeleton, template duplication, area-suffix convention, verification).
