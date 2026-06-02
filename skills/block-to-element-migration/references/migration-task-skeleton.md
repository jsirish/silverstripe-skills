# Migration Task Skeleton

The canonical `BlockMigrationTask.php`. Distilled from the example-custom refactor (the cleanest of the three reference implementations). Copy verbatim into `app/src/Tasks/BlockMigrationTask.php`, then fill in the three highlighted sections:

1. **`SKIP_CLASSES`** — block types your project doesn't migrate (no SS4 equivalent)
2. **`AREA_MAP`** — your project's `BlockArea` strings → page column lookup
3. **`mapBlockClass()`** — your project's `BlockClassName` → `[ElementClass, defaultTitle]` match

For non-trivial blocks (those with child sub-objects), add a `migrate<BlockName>Items()` helper. The skeleton ships with worked patterns for `ContentBlock`, `PromoBlock`, and `PageSectionBlock` — copy and adapt them.

## Why this skeleton

| Concern | How it's handled |
|---------|------------------|
| Idempotency | ExtraClass marker `migrated-from-block`, no schema changes |
| Dry-run preview | `dry-run=1` query param, reports by-type and by-area |
| Versioned writes | `insertVersionedRow()` writes draft + `_Live` + `_Versions` in one call |
| Shared blocks | Each `SiteTree_Blocks` row = one Element placement, naturally cloned |
| Live publishing | `publishElementalAreasToLive()` + `syncPageAreasToLive()` at end |
| Schema safety | Every table touch is gated on `DB::get_schema()->tableList()` membership |

## The skeleton

```php
<?php

namespace App\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Migrate legacy sheadawson/silverstripe-blocks (or dynamic/dynamic-blocks)
 * data to dnadesign/silverstripe-elemental elements.
 *
 * Usage:
 *   ddev sake dev/tasks/block-migration             # execute
 *   ddev sake dev/tasks/block-migration "dry-run=1" # preview only
 *
 * Idempotent: elements created here include the migration marker in their
 * ExtraClass. Re-running clears and re-imports cleanly — no schema changes.
 *
 * See: silverstripe-skills/block-to-element-migration/SKILL.md
 */
class BlockMigrationTask extends BuildTask
{
    private static $segment = 'block-migration';

    protected $title = 'Block → Elemental Migration';

    protected $description = 'Migrates legacy block module data to SilverStripe Elemental.';

    private const MIGRATION_MARKER = 'migrated-from-block';

    // ─── FILL IN: block types with no SS4 equivalent ──────────────────────
    private const SKIP_CLASSES = [
        // 'ChildPagesBlock',
        // 'SectionNavigationBlock',
    ];

    // ─── FILL IN: BlockArea → Page column lookup ──────────────────────────
    // Maps each legacy BlockArea string to the Page-table column that holds
    // the target ElementalArea FK. All values MUST be columns on the Page
    // table (the base class). If a column lives on a subclass table
    // (e.g. HomeContentElementalAreaID declared as a has_one on HomePage),
    // you also need to LEFT JOIN that subclass table in discoverBlocks() and
    // add a second UPDATE in syncPageAreasToLive() for its _Live counterpart.
    private const AREA_MAP = [
        'Sidebar' => 'SidebarElementalAreaID',
        'HomeContent' => 'ElementalAreaID',
        'AfterContent' => 'ElementalAreaID',
        'BeforeContent' => 'ElementalAreaID',
    ];

    public function run($request): void
    {
        $dryRun = (bool) $request->getVar('dry-run');
        $prefix = $dryRun ? '[DRY-RUN] ' : '';

        DB::alteration_message("{$prefix}=== Block → Elemental Migration ===");

        $tables = DB::get_schema()->tableList();
        if (!isset($tables['sitetree_blocks']) || !isset($tables['block'])) {
            DB::alteration_message('No legacy block tables found — nothing to migrate.', 'notice');
            return;
        }

        $blocks = $this->discoverBlocks();
        DB::alteration_message("{$prefix}Found " . count($blocks) . " block–page attachments.");

        if ($dryRun) {
            $this->reportDryRun($blocks);
            return;
        }

        $this->clearMigratedElements();

        $stats = ['placed' => 0, 'cloned' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($blocks as $row) {
            try {
                $stats[$this->migratePlacement($row)]++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                DB::alteration_message(
                    "ERROR migrating block #{$row['BlockID']} ({$row['BlockClassName']}): " . $e->getMessage(),
                    'error'
                );
            }
        }

        $this->publishElementalAreasToLive();
        $this->syncPageAreasToLive();

        DB::alteration_message("=== Migration Complete ===");
        DB::alteration_message("Placed:  {$stats['placed']}");
        DB::alteration_message("Cloned:  {$stats['cloned']}  (shared blocks placed on multiple pages)");
        DB::alteration_message("Skipped: {$stats['skipped']}");
        DB::alteration_message("Errors:  {$stats['errors']}");
    }

    private function discoverBlocks(): array
    {
        // Derive page columns from AREA_MAP so adding a mapping there
        // automatically adds the column to the SELECT without manual sync.
        // All AREA_MAP values must be columns on the Page table. If a column
        // lives on a subclass table (e.g. HomePage), add a LEFT JOIN for that
        // table here and select its columns with an alias.
        $uniqueCols = array_unique(array_values(self::AREA_MAP));
        $areaCols = '"p"."' . implode('", "p"."', $uniqueCols) . '"';

        $result = DB::query(
            'SELECT "stb"."SiteTreeID", "stb"."BlockID", "stb"."Sort", "stb"."BlockArea",'
            . ' "b"."ClassName" AS "BlockClassName", "b"."Title" AS "BlockTitle",'
            . ' "b"."Created", "b"."LastEdited",'
            . ' ' . $areaCols
            . ' FROM "SiteTree_Blocks" AS "stb"'
            . ' INNER JOIN "Block" AS "b" ON "b"."ID" = "stb"."BlockID"'
            . ' INNER JOIN "Page" AS "p" ON "p"."ID" = "stb"."SiteTreeID"'
            // Add LEFT JOINs here for any subclass tables referenced in AREA_MAP
            . ' ORDER BY "stb"."SiteTreeID", "stb"."BlockArea", "stb"."Sort" ASC'
        );
        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function reportDryRun(array $blocks): void
    {
        $byType = [];
        $byArea = [];
        foreach ($blocks as $row) {
            $type = $row['BlockClassName'] ?? 'Unknown';
            $area = $row['BlockArea'] ?: '(default)';
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $byArea[$area] = ($byArea[$area] ?? 0) + 1;
        }
        DB::alteration_message('[DRY-RUN] By block type:');
        foreach ($byType as $type => $count) {
            DB::alteration_message("  {$type}: {$count}");
        }
        DB::alteration_message('[DRY-RUN] By block area:');
        foreach ($byArea as $area => $count) {
            DB::alteration_message("  {$area}: {$count}");
        }
    }

    /**
     * Delete previously-migrated elements (idempotent re-run).
     */
    private function clearMigratedElements(): void
    {
        $marker = DB::get_conn()->escapeString(self::MIGRATION_MARKER);
        $ids = [];
        $rows = DB::query("SELECT \"ID\" FROM \"Element\" WHERE \"ExtraClass\" LIKE '%{$marker}%'");
        foreach ($rows as $row) {
            $ids[] = (int) $row['ID'];
        }

        if (empty($ids)) {
            DB::alteration_message('No previously migrated elements to clear.');
            return;
        }

        $idList = implode(',', $ids);
        DB::alteration_message('Clearing ' . count($ids) . ' previously migrated elements...');

        $tables = DB::get_schema()->tableList();

        // Child rows of subtypes — list every subtype's child table here.
        // Gate each on tableList() so this is safe to copy into a project that
        // doesn't have these optional child tables (re-run would otherwise fail
        // with a missing-table error before cleanup completes).
        if (isset($tables['pagesection'])) {
            DB::query("DELETE FROM \"PageSection\" WHERE \"ElementPageSectionID\" IN ({$idList})");
        }
        if (isset($tables['promoitem'])) {
            DB::query("DELETE FROM \"PromoItem\" WHERE \"ElementPromoID\" IN ({$idList})");
        }

        // Subtype tables (+ _Live + _Versions) — list every Element subtype here
        $subTables = ['ElementContent', 'ElementPageSection', 'ElementPromo'];
        foreach ($subTables as $table) {
            $lc = strtolower($table);
            if (isset($tables[$lc])) {
                DB::query("DELETE FROM \"{$table}\" WHERE \"ID\" IN ({$idList})");
            }
            if (isset($tables[$lc . '_live'])) {
                DB::query("DELETE FROM \"{$table}_Live\" WHERE \"ID\" IN ({$idList})");
            }
            if (isset($tables[$lc . '_versions'])) {
                DB::query("DELETE FROM \"{$table}_Versions\" WHERE \"RecordID\" IN ({$idList})");
            }
        }

        // Base Element + _Live + _Versions
        DB::query("DELETE FROM \"Element\" WHERE \"ID\" IN ({$idList})");
        DB::query("DELETE FROM \"Element_Live\" WHERE \"ID\" IN ({$idList})");
        if (isset($tables['element_versions'])) {
            DB::query("DELETE FROM \"Element_Versions\" WHERE \"RecordID\" IN ({$idList})");
        }
    }

    private function migratePlacement(array $row): string
    {
        $blockClass = $row['BlockClassName'];
        if (in_array($blockClass, self::SKIP_CLASSES, true)) {
            return 'skipped';
        }

        $areaId = $this->resolveAreaId($row);
        if ($areaId <= 0) {
            DB::alteration_message("  Skip: no ElementalArea for page {$row['SiteTreeID']} (area={$row['BlockArea']})", 'notice');
            return 'skipped';
        }

        $elementId = $this->createElement(
            $blockClass,
            (int) $row['BlockID'],
            $row['BlockTitle'] ?? '',
            $areaId,
            (int) $row['Sort'],
            $row['Created'],
            $row['LastEdited'],
            ($row['BlockArea'] ?? '') === 'Sidebar'
        );

        // createElement() returns 0 for unmapped block classes — don't log or
        // count those as placed.
        if (!$elementId) {
            return 'skipped';
        }

        DB::alteration_message("  ✓ Placed {$blockClass} \"{$row['BlockTitle']}\" → area {$areaId} (page {$row['SiteTreeID']}, {$row['BlockArea']})");

        // Cloning detection
        static $seen = [];
        $action = isset($seen[$row['BlockID']]) ? 'cloned' : 'placed';
        $seen[$row['BlockID']] = true;
        return $action;
    }

    private function resolveAreaId(array $row): int
    {
        $blockArea = $row['BlockArea'] ?? 'AfterContent';
        $column = self::AREA_MAP[$blockArea] ?? 'ElementalAreaID';
        return (int) ($row[$column] ?? 0);
    }

    private function createElement(
        string $blockClass,
        int $blockId,
        string $title,
        int $areaId,
        int $sort,
        string $created,
        string $lastEdited,
        bool $isSidebar = false
    ): int {
        [$elementClass, $defaultTitle] = $this->mapBlockClass($blockClass);
        if (!$elementClass) {
            return 0; // unmapped → caller treats as skipped
        }

        $finalTitle = !empty($title) ? $title : $defaultTitle;
        // Sidebar blocks often use admin-only titles. Don't render them as headings.
        $showTitle = (!empty($title) && !$isSidebar) ? 1 : 0;

        $elementId = $this->insertVersionedRow('Element', [
            'ClassName' => $elementClass,
            'Title' => $finalTitle,
            'ShowTitle' => $showTitle,
            'Sort' => $sort,
            'ExtraClass' => self::MIGRATION_MARKER,
            'Style' => '',
            'ParentID' => $areaId,
            'Created' => $created,
            'LastEdited' => $lastEdited,
        ]);

        $this->createSubtypeRow($elementId, $blockClass, $blockId);

        return $elementId;
    }

    /**
     * ─── FILL IN: legacy block class → [Element class, default title] ────
     */
    private function mapBlockClass(string $blockClass): array
    {
        // ─── FILL IN: legacy block class → [Element class, default title] ────
        // Array lookup (PHP 7.0+) keeps this task runnable on PHP 7.4 SS4 servers.
        $map = [
            'ContentBlock' => ['DNADesign\\Elemental\\Models\\ElementContent', 'Content'],
            // 'PromoBlock'        => ['App\\Elements\\ElementPromo',        'Promo'],
            // 'PageSectionBlock'  => ['App\\Elements\\ElementPageSection',  'Page Section'],
        ];
        return $map[$blockClass] ?? ['', ''];
    }

    private function createSubtypeRow(int $elementId, string $blockClass, int $blockId): void
    {
        switch ($blockClass) {
            case 'ContentBlock':
                $row = DB::prepared_query(
                    'SELECT "Content" FROM "ContentBlock" WHERE "ID" = ?',
                    [$blockId]
                )->record();
                $this->insertSubtypeRow('ElementContent', $elementId, [
                    'HTML' => $row['Content'] ?? '',
                ]);
                break;

            // Add a case per block type. See examples/BlockMigrationTask.example.php
            // for PromoBlock + PageSectionBlock patterns with child sub-objects.
        }
    }

    /**
     * Insert into a Versioned table — writes draft + _Live + _Versions.
     * The Versioned module assumes _Versions rows exist; without them,
     * publish() will silently strip the record from _Live.
     */
    private function insertVersionedRow(string $table, array $fields, ?int $forcedId = null): int
    {
        $tables = DB::get_schema()->tableList();
        $lc = strtolower($table);

        if ($forcedId !== null) {
            $fields['ID'] = $forcedId;
        }
        $fields['Version'] = 1;

        $cols = array_keys($fields);
        $placeholders = array_fill(0, count($fields), '?');
        $colList = '"' . implode('","', $cols) . '"';
        $placeList = implode(',', $placeholders);

        DB::prepared_query(
            "INSERT INTO \"{$table}\" ({$colList}) VALUES ({$placeList})",
            array_values($fields)
        );
        $id = $forcedId ?? (int) DB::get_conn()->getGeneratedID($table);

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

    /**
     * Subtype tables share the ID with the base Element — no auto-increment,
     * just `INSERT ... (ID, ...)`.
     */
    private function insertSubtypeRow(string $table, int $elementId, array $fields): void
    {
        $tables = DB::get_schema()->tableList();
        $lc = strtolower($table);

        $fields = array_merge(['ID' => $elementId], $fields);
        $cols = '"' . implode('","', array_keys($fields)) . '"';
        $place = implode(',', array_fill(0, count($fields), '?'));

        DB::prepared_query("INSERT INTO \"{$table}\" ({$cols}) VALUES ({$place})", array_values($fields));

        if (isset($tables[$lc . '_live'])) {
            DB::prepared_query("INSERT INTO \"{$table}_Live\" ({$cols}) VALUES ({$place})", array_values($fields));
        }

        if (isset($tables[$lc . '_versions'])) {
            $vFields = $fields;
            unset($vFields['ID']);
            $vFields['RecordID'] = $elementId;
            $vFields['Version'] = 1;
            $vCols = '"' . implode('","', array_keys($vFields)) . '"';
            $vPlace = implode(',', array_fill(0, count($vFields), '?'));
            DB::prepared_query(
                "INSERT INTO \"{$table}_Versions\" ({$vCols}) VALUES ({$vPlace})",
                array_values($vFields)
            );
        }
    }

    /**
     * dev/build creates ElementalArea on draft only. Without this pass,
     * elements exist but pages can't find them on the live frontend.
     */
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
        DB::alteration_message('Published ElementalArea records to live.');
    }

    private function syncPageAreasToLive(): void
    {
        $tables = DB::get_schema()->tableList();
        if (!isset($tables['page_live'])) {
            return;
        }
        // Build SET/WHERE from AREA_MAP so custom Page columns are synced
        // automatically alongside the defaults. Columns that live on a subclass
        // table (e.g. HomePage) need a separate UPDATE against that table's
        // _Live counterpart — add it below.
        $uniqueCols = array_unique(array_values(self::AREA_MAP));
        $setClauses = [];
        $whereClauses = [];
        foreach ($uniqueCols as $col) {
            $setClauses[]   = "pl.\"{$col}\" = p.\"{$col}\"";
            $whereClauses[] = "p.\"{$col}\" != 0";
        }
        DB::query(
            'UPDATE "Page_Live" pl'
            . ' INNER JOIN "Page" p ON p.ID = pl.ID'
            . ' SET ' . implode(', ', $setClauses)
            . ' WHERE ' . implode(' OR ', $whereClauses)
        );
        DB::alteration_message('Synced Page area FKs to Page_Live.');
    }
}
```

## Adding a block type with children

The skeleton's `createSubtypeRow()` handles `ContentBlock` directly. For blocks with child sub-objects (PromoBlock → PromoItems, PageSectionBlock → PageSections), add a helper:

```php
case 'PromoBlock':
    $row = DB::prepared_query(
        'SELECT "Content" FROM "PromoBlock" WHERE "ID" = ?',
        [$blockId]
    )->record();
    $this->insertSubtypeRow('ElementPromo', $elementId, [
        'Content' => $row['Content'] ?? '',
    ]);
    $this->migratePromoItems($elementId, $blockId);
    break;
```

```php
private function migratePromoItems(int $elementId, int $blockId): void
{
    $tables = DB::get_schema()->tableList();
    if (!isset($tables['promoobject']) || !isset($tables['promoblock_promos'])) {
        return;
    }

    $items = DB::prepared_query(
        'SELECT "po".*, "pbp"."SortOrder" AS "PBP_Sort"'
        . ' FROM "PromoBlock_Promos" AS "pbp"'
        . ' INNER JOIN "PromoObject" AS "po" ON "po"."ID" = "pbp"."PromoObjectID"'
        . ' WHERE "pbp"."PromoBlockID" = ? ORDER BY "pbp"."SortOrder" ASC',
        [$blockId]
    );

    foreach ($items as $item) {
        DB::prepared_query(
            'INSERT INTO "PromoItem"'
            . ' ("Title","Content","Sort","ImageID","ElementPromoID")'
            . ' VALUES (?,?,?,?,?)',
            [
                $item['Title'] ?? '',
                $item['Content'] ?? '',
                (int) ($item['PBP_Sort'] ?? 0),
                (int) ($item['ImageID'] ?? 0),
                $elementId,
            ]
        );
    }
}
```

See [../examples/BlockMigrationTask.example.php](../examples/BlockMigrationTask.example.php) for a complete worked task with 5 block types.

## Don't

- **Don't add `LegacyBlockID` columns** to subtype tables for "did this already migrate" lookups. The ExtraClass marker handles idempotency without schema changes, and re-runs stay clean.
- **Don't skip `_Versions` writes** even when the subtype table looks like it doesn't need them. The Versioned module assumes they exist; a missing `_Versions` row can cause `publish()` to silently strip the element from `_Live`.
- **Don't run this task before declaring page-model `has_one` relations.** Phase 2 is required first — `dev/build` needs to have created the area columns.
- **Don't run on prod without dry-run first.** Always preview by-type and by-area before writing.
