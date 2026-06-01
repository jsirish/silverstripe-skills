# Block → Elemental Migration (SS3 → SS4)

> **➡ Superseded by the dedicated [block-to-element-migration](../../block-to-element-migration/SKILL.md) skill.**
>
> This document is preserved as the seed material the new skill was distilled from. For the canonical workflow, the migration-task skeleton, the template-parity pattern, and the area-suffix template convention, use the new skill.
>
> The content below is still accurate but only covers the migration-task half (not the template-parity half) and doesn't include the area-suffix template mechanism.

---

Detailed patterns for migrating `sheadawson/silverstripe-blocks` or `dynamic/dynamic-blocks` to `dnadesign/silverstripe-elemental` during an SS3→SS4 upgrade. Patterns distilled from rockline-iatric, youth-sailing, and sheboygan-safeharbor migrations.

## Architecture: one task or two?

**Use one task** if the legacy `SiteTree_Blocks` table is your single source of placement truth (which is the normal case). A single pass through that join table gives you BlockID + Sort + BlockArea + page ID — everything needed to create the Element and attach it.

**Use two tasks** only if blocks live in multiple tables (e.g., a separate "BlockSet" concept that doesn't appear in `SiteTree_Blocks`) and you need to migrate block bodies before you can resolve placements.

The Safe Harbor refactor (May 2026) collapsed two tasks into one and the result was 30% less code and cleaner idempotency.

## The canonical task structure

```php
<?php

namespace App\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class BlockMigrationTask extends BuildTask
{
    // 1. Short CLI invocation
    private static $segment = 'block-migration';

    protected $title = 'Block → Elemental Migration';

    // 2. Marker-based idempotency (no schema changes)
    private const MIGRATION_MARKER = 'migrated-from-block';

    // 3. Block types with no SS4 equivalent
    private const SKIP_CLASSES = [
        'ChildPagesBlock',
        'RecentBlogPostsBlock',
        // ... your project's skips
    ];

    // 4. BlockArea → Page column lookup
    private const AREA_MAP = [
        'Sidebar' => 'SidebarElementalAreaID',
        'HomeContent' => 'ElementalAreaID',
        // ... add others as needed
    ];

    public function run($request): void
    {
        $dryRun = (bool) $request->getVar('dry-run');
        // ...
    }
}
```

### Five non-negotiable practices

1. **`$segment`** — gives you `ddev sake dev/tasks/block-migration` instead of the awful `dev/tasks/App-Tasks-BlockMigrationTask`.
2. **`dry-run=1`** — report by type and area without writing; lets you preview migrations before committing.
3. **ExtraClass marker** — not LegacyBlockID columns. No schema changes needed; re-runs are clean.
4. **Versioned writes** — every Element row needs draft + `_Live` + `_Versions`. The Versioned module breaks in the CMS without `_Versions` rows.
5. **Publish ElementalArea + sync Page FKs** — without these the elements exist but the page can't find them.

## Pattern: marker-based idempotency

Every Element created by the task includes `migrated-from-block` in its `ExtraClass`. Re-running deletes prior output before re-importing:

```php
private function clearMigratedElements(): void
{
    $marker = DB::get_conn()->escapeString(self::MIGRATION_MARKER);
    $ids = [];
    $rows = DB::query("SELECT \"ID\" FROM \"Element\" WHERE \"ExtraClass\" LIKE '%{$marker}%'");
    foreach ($rows as $row) {
        $ids[] = (int) $row['ID'];
    }
    if (empty($ids)) {
        return;
    }
    $idList = implode(',', $ids);

    // Cascade-delete child rows (PageSection, PromoItem children)
    DB::query("DELETE FROM \"PageSection\" WHERE \"ElementPageSectionID\" IN ({$idList})");

    // Subtype + _Live + _Versions
    foreach (['ElementContent', 'ElementPromo', 'ElementPageSection'] as $table) {
        DB::query("DELETE FROM \"{$table}\" WHERE \"ID\" IN ({$idList})");
        DB::query("DELETE FROM \"{$table}_Live\" WHERE \"ID\" IN ({$idList})");
        DB::query("DELETE FROM \"{$table}_Versions\" WHERE \"RecordID\" IN ({$idList})");
    }

    // Base Element + _Live + _Versions
    DB::query("DELETE FROM \"Element\" WHERE \"ID\" IN ({$idList})");
    DB::query("DELETE FROM \"Element_Live\" WHERE \"ID\" IN ({$idList})");
    DB::query("DELETE FROM \"Element_Versions\" WHERE \"RecordID\" IN ({$idList})");
}
```

**Why this is better than LegacyBlockID columns:**

| Approach | Idempotency | Schema impact | Re-run behavior |
|----------|-------------|---------------|-----------------|
| `LegacyBlockID` column on each subtype | Lookup-based — skip if exists | Adds a column per subtype table | If task changes, old data lingers |
| `ExtraClass` marker | Delete-then-import | None | Clean slate every run |

The ExtraClass approach also survives in the CMS (`<div class="element ... migrated-from-block">`) as a useful debugging breadcrumb.

## Pattern: versioned-row helper

Every Element write needs three rows (draft, live, versions). Centralize:

```php
private function insertVersionedRow(string $table, array $fields): int
{
    $tables = DB::get_schema()->tableList();
    $lc = strtolower($table);

    $fields['Version'] = 1;
    $cols = array_keys($fields);
    $placeholders = array_fill(0, count($fields), '?');
    $colList = '"' . implode('","', $cols) . '"';
    $placeList = implode(',', $placeholders);

    // Draft
    DB::prepared_query(
        "INSERT INTO \"{$table}\" ({$colList}) VALUES ({$placeList})",
        array_values($fields)
    );
    $id = (int) DB::get_conn()->getGeneratedID($table);

    // Live
    if (isset($tables[$lc . '_live'])) {
        $liveFields = $fields;
        $liveFields['ID'] = $id;
        $liveCols = '"' . implode('","', array_keys($liveFields)) . '"';
        $livePlace = implode(',', array_fill(0, count($liveFields), '?'));
        DB::prepared_query(
            "INSERT INTO \"{$table}_Live\" ({$liveCols}) VALUES ({$livePlace})",
            array_values($liveFields)
        );
    }

    // Versions
    if (isset($tables[$lc . '_versions'])) {
        $vFields = $fields;
        unset($vFields['ID']);
        $vFields['RecordID'] = $id;
        $vCols = '"' . implode('","', array_keys($vFields)) . '"';
        $vPlace = implode(',', array_fill(0, count($vFields), '?'));
        DB::prepared_query(
            "INSERT INTO \"{$table}_Versions\" ({$vCols}) VALUES ({$vPlace})",
            array_values($vFields)
        );
    }

    return $id;
}
```

**Skipping `_Versions` is the #1 cause of "edit page in CMS breaks the element" bugs.** The Versioned module assumes `_Versions` rows exist; without them, `publish()` may strip the record from `_Live` because it has no history to reconcile.

## Pattern: ElementalArea publish + Page FK sync

`dev/build` creates `ElementalArea` records on draft only; without these two final steps, elements exist but never render on the frontend.

```php
private function publishElementalAreasToLive(): void
{
    $tables = DB::get_schema()->tableList();
    if (!isset($tables['elementalarea_live'])) {
        return;
    }
    DB::query(
        'INSERT INTO "ElementalArea_Live"'
        . ' (ID, ClassName, LastEdited, Created, Version, OwnerClassName)'
        . ' SELECT ID, ClassName, LastEdited, Created, Version, OwnerClassName'
        . ' FROM "ElementalArea"'
        . ' ON DUPLICATE KEY UPDATE'
        . '   ClassName=VALUES(ClassName),'
        . '   LastEdited=VALUES(LastEdited),'
        . '   OwnerClassName=VALUES(OwnerClassName)'
    );
}

private function syncPageAreasToLive(): void
{
    $tables = DB::get_schema()->tableList();
    if (!isset($tables['page_live'])) {
        return;
    }
    DB::query(
        'UPDATE "Page_Live" pl'
        . ' INNER JOIN "Page" p ON p.ID = pl.ID'
        . ' SET pl.ElementalAreaID = p.ElementalAreaID,'
        . ' pl.SidebarElementalAreaID = p.SidebarElementalAreaID'
        . ' WHERE p.ElementalAreaID != 0 OR p.SidebarElementalAreaID != 0'
    );
}
```

Run both at the **end** of `run()`, after all block placement is done.

## Pattern: shared blocks (one block, multiple pages)

`SiteTree_Blocks` can have the same `BlockID` on multiple pages. Elemental enforces one Element per area, so shared blocks must be cloned per placement.

Iterating through `SiteTree_Blocks` ordered by `(SiteTreeID, Sort)` naturally handles this — each row becomes its own Element via `insertVersionedRow`. The Element ID is per-placement, not per-source-Block. No special "clone" path needed if you treat each `SiteTree_Blocks` row as the unit of work.

```php
// Each row in SiteTree_Blocks = one Element placement
foreach ($placements as $row) {
    // ...always create a new Element, never look up "did this BlockID get migrated"
    $elementId = $this->insertVersionedRow('Element', [...]);
    $this->createSubtypeRow($elementId, $row['BlockClassName'], $row['BlockID']);
}
```

For tracking "this was the 2nd placement of BlockID 11" in your output log:

```php
static $seenBlockIds = [];
$action = isset($seenBlockIds[$blockId]) ? 'cloned' : 'placed';
$seenBlockIds[$blockId] = true;
```

## Pattern: CSS scope class compatibility

SS3 themes typically scope CSS to the block's container class:

```css
.pagesectionblock .pagesection__image { position: absolute; ... }
```

But Elemental's `$CSSClasses` outputs `element app__elements__elementpagesection`. The legacy CSS no longer matches. Two options:

**Option A: Add legacy class to element wrapper** (lower-effort, retains existing CSS)

```html
<!-- ElementPageSection.ss -->
<div class="$CSSClasses pagesectionblock">
    ...
</div>
```

**Option B: Update CSS to target Elemental classes** (cleaner long-term, more work)

```css
.element.app__elements__elementpagesection .pagesection__image { ... }
```

Option A is recommended for migration phase — it lets you defer theme rewrite until after migration is complete.

## Pattern: legacy JS-dependent CSS removal

SS3 themes often set inline heights via JavaScript:

```js
// Old SS3 JS that no longer runs in SS4
$('.pagesection').each(function() {
    $(this).height(Math.max($(this).find('.image').height(), $(this).find('.text').height()));
});
```

With this JS gone, any CSS that assumed a fixed parent height (e.g., `.vert-centering { position: absolute; top: 50%; ... }`) will collapse the layout.

**Fix:** remove the JS-dependent classes from the migrated element templates. Don't try to revive the JS — fix the structure to work without it.

## End-of-task summary

Always log a stats summary so re-runs are diff-able:

```
=== Migration Complete ===
Placed: 10
Cloned (shared blocks): 2
Skipped: 7
Errors: 0
```

## Run order on prod

```bash
# Pre-flight
ddev sake dev/tasks/block-migration "dry-run=1"

# Real run
ddev sake dev/tasks/block-migration

# Then any subclass-data migrations
ddev sake dev/tasks/staff-member-data-migration
```

## See also

- `silverstripe-3-to-4-upgrade/references/db-rebuild-conflicts.md` — handling `__TEMP__` and `_Versions` table conflicts on re-runs
- `silverstripe-version-upgrade/references/data-migration-tasks.md` — ORM-based migration patterns (for SS4→SS5; uses `publishRecursive()` instead of raw `_Live` writes)
