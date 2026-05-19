---
name: silverstripe-3-to-4-upgrade
description: >
  Complete workflow for upgrading legacy Silverstripe 3 projects to Silverstripe 4.
  Use when upgrading SS3 projects, migrating namespaces, or resolving database schema blockers.
  Covers directory structure changes, namespace migration, database schema preflight fixes,
  data migration tasks (Files, Blocks to Elemental), and template adjustments.

---

# Silverstripe 3 to 4 Upgrade Skill

Repeatable workflow for upgrading legacy Silverstripe 3 projects to Silverstripe 4. Based on the successful migration of Iatric Manufacturing, this guide details the exact steps, architectural shifts, and critical gotchas when moving to the SS4 framework.

## Upgrade Phases (Summary)

| Phase | Key Actions |
|-------|-------------|
| **1. Assessment** | Audit SS3 modules, plan block-to-elemental migration |
| **2. Architecture** | Move `mysite` to `app`, introduce `public` directory |
| **3. Dependencies** | Update `composer.json` to `^4.0`, update PHP constraints |
| **4. Namespaces** | Apply PSR-4 namespaces, remap config class names |
| **5. DB Preflight Fixes** | Clear `__TEMP__` table collisions and `FULLTEXT` indices |
| **6. Data Migration** | Run file migration, Elemental tasks, and custom SQL |
| **7. Templates** | Fix `HomePage` resolution, update `$Link` to `$URL` |
| **8. Build & Verify** | `dev/build flush=1`, QA frontend and admin |

## Phase 1: Assessment & Discovery

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

## Phase 4: Code & Namespace Migration

SS4 heavily relies on PHP namespaces.

1. **Namespacing**: Add namespaces to all classes in `app/src/` (e.g., `namespace App\Pages;` or `namespace Dynamic\Base\Page;`).
2. **Config Remapping**: Update `app/_config/mysite.yml` to map legacy class names to new namespaced ones.
   ```yaml
   HomePage:
     class: Dynamic\Base\Page\HomePage
   Iatric\Pages\HomePage:
     class: Dynamic\Base\Page\HomePage
   ```
3. **Legacy Method Signatures**: SS4 alters some Core method signatures.
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

   See [references/block-to-elemental-migration.md](references/block-to-elemental-migration.md) for the canonical block-migration task architecture, covering: `$segment` + dry-run, ExtraClass-marker idempotency, Versioned writes (draft + `_Live` + `_Versions`), `ElementalArea_Live` publishing, `Page_Live` FK sync, shared-block handling, and CSS scope-class compatibility.

## Phase 7: Templates & Front-End

- **Variables**: Update `$Link` to `$URL` in template `.ss` files.
- **Elemental Areas**: Replace `<% with $Blockarea(AreaName) %>` with `$ElementalArea` or specific area relations like `$ElementalHomePage`.

> [!IMPORTANT]
> **Namespaced Base Templates**: SS4 resolves base templates by namespace path first. `Dynamic\Base\Page\HomePage` expects `themes/mytheme/templates/Dynamic/Base/Page/HomePage.ss` — NOT `templates/HomePage.ss`. Without this, the page falls back to `Page.ss`, potentially ruining full-width layout constraints.

> [!NOTE]
> **Template Variant Naming**: Elemental uses `getAreaRelationName()` for suffixing. If a page has `has_one: ['ElementalHomePage' => ElementalArea::class]`, the variant file must be named `ElementPromos_ElementalHomePage.ss`.

## Key Discoveries & Gotchas

> [!WARNING]
> **Raw SQL is Mandatory**: When migrating from SS3 modules that are removed in SS4 (like `dynamic-blocks`), the ORM strips legacy `$db` properties. You cannot rely on `$block->Title` during migration. You must query the legacy `Block` and `Block_Live` tables using raw `DB::query()` and `INSERT ON DUPLICATE KEY UPDATE` into the new Elemental records.

> [!TIP]
> **PublishRecursive Dangers**: Running `publishRecursive()` on a root page will validate all child elements, including forms. If a UserForms setup has obsolete SS3 class names in the database (e.g., `EditableEmailField` instead of `SilverStripe\UserForms\Model\EditableFormField\EditableEmailField`), the publish will fatal error. Always write a migration task to fix `ClassName` rows in the DB before publishing.

> [!IMPORTANT]
> **Image Resize Methods**: `jonom/focuspoint` is rarely carried over to SS4. Update template tags from `$Image.FocusFill()` to `$Image.Fill(X, Y)` or native SS4 crop functions.

> [!WARNING]
> **ElementalArea_Live is empty after dev/build**: SS4 `dev/build` creates `ElementalArea` rows on the **draft** table only. Elements you placed during migration won't appear on the frontend until both `ElementalArea_Live` and `Page_Live.ElementalAreaID` are populated. The block migration task must end with these two SQL passes (see [references/block-to-elemental-migration.md](references/block-to-elemental-migration.md)).

> [!WARNING]
> **Versioned writes need _Live AND _Versions**: When inserting Elements via raw SQL, write to all three tables: base draft, `_Live`, and `_Versions`. Skipping `_Versions` causes "no history" errors when editing in the CMS and can cause `publish()` to silently strip the record from `_Live`. See the `insertVersionedRow()` pattern in [references/block-to-elemental-migration.md](references/block-to-elemental-migration.md).

> [!TIP]
> **Legacy CSS still works if you keep the old class**: SS3 themes scope CSS to block class names (`.pagesectionblock`, `.promoblock`, etc.). Elemental's `$CSSClasses` outputs `element app__elements__elementpagesection` instead. To retain existing CSS during migration, add the legacy class to the Elemental wrapper template: `<div class="$CSSClasses pagesectionblock">`. Defer full CSS rewrite until after migration is verified.

> [!WARNING]
> **JS-dependent CSS will collapse**: SS3 themes often use JavaScript to set fixed heights on block containers, then position child elements absolutely within them. With the JS gone, `position: absolute` children leave the parent at height 0 and the layout collapses. When migrating, remove `vert-centering` (or equivalent) classes from element templates — don't try to revive the JS.
