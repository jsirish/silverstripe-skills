<?php

/**
 * EXAMPLE — full worked block migration task
 *
 * Synthesised from example-custom with extra patterns from example-manufacturing and
 * example-multiarea. Migrates 5 block types, handles 3 area variants, and
 * demonstrates child-sub-object migration.
 *
 * Drop into app/src/Tasks/BlockMigrationTask.php, adjust the namespace,
 * and edit SKIP_CLASSES, AREA_MAP, mapBlockClass(), and createSubtypeRow()
 * for your project.
 *
 * Run:
 *   ddev sake dev/tasks/block-migration "dry-run=1"
 *   ddev sake dev/tasks/block-migration
 */

namespace App\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class BlockMigrationTask extends BuildTask
{
    private static $segment = 'block-migration';

    protected $title = 'Block → Elemental Migration';

    protected $description = 'Migrates legacy block module data to SilverStripe Elemental.';

    private const MIGRATION_MARKER = 'migrated-from-block';

    private const SKIP_CLASSES = [
        'ChildPagesBlock',
        'SectionNavigationBlock',
    ];

    // All values must be columns on the Page table (base class). If a column
    // lives on a subclass table (e.g. HomeContentElementalAreaID on HomePage),
    // LEFT JOIN that table in discoverBlocks() and add a separate UPDATE in
    // syncPageAreasToLive() for its _Live counterpart.
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
                DB::alteration_message("ERROR block #{$row['BlockID']} ({$row['BlockClassName']}): " . $e->getMessage(), 'error');
            }
        }

        $this->publishElementalAreasToLive();
        $this->syncPageAreasToLive();

        DB::alteration_message("=== Migration Complete ===");
        DB::alteration_message("Placed:  {$stats['placed']}");
        DB::alteration_message("Cloned:  {$stats['cloned']}");
        DB::alteration_message("Skipped: {$stats['skipped']}");
        DB::alteration_message("Errors:  {$stats['errors']}");
    }

    private function discoverBlocks(): array
    {
        // Derive page columns from AREA_MAP so adding a new mapping automatically
        // adds the column to the SELECT without manual sync.
        $uniqueCols = array_unique(array_values(self::AREA_MAP));
        $areaCols = '"p"."' . implode('", "p"."', $uniqueCols) . '"';

        $result = DB::query(
            'SELECT "stb"."SiteTreeID", "stb"."BlockID", "stb"."Sort", "stb"."BlockArea",'
            . ' "b"."ClassName" AS "BlockClassName", "b"."Title" AS "BlockTitle",'
            . ' "b"."Created", "b"."LastEdited",'
            . ' ' . $areaCols
            . ' FROM "SiteTree_Blocks" AS "stb"'
            . ' INNER JOIN "Block" AS "b" ON "b"."ID" = "stb"."BlockID"'
            // Published-only: join "Block_Live" rather than filtering "b"."Published"
            // (that column is always 0 — a versioning artifact, not a live flag).
            . ' INNER JOIN "Block_Live" AS "bl" ON "bl"."ID" = "b"."ID"'
            . ' INNER JOIN "Page" AS "p" ON "p"."ID" = "stb"."SiteTreeID"'
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
        $byType = $byArea = [];
        foreach ($blocks as $row) {
            $byType[$row['BlockClassName'] ?? 'Unknown'] = ($byType[$row['BlockClassName'] ?? 'Unknown'] ?? 0) + 1;
            $byArea[$row['BlockArea'] ?: '(default)'] = ($byArea[$row['BlockArea'] ?: '(default)'] ?? 0) + 1;
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

    private function clearMigratedElements(): void
    {
        $marker = DB::get_conn()->escapeString(self::MIGRATION_MARKER);
        $ids = [];
        foreach (DB::query("SELECT \"ID\" FROM \"Element\" WHERE \"ExtraClass\" LIKE '%{$marker}%'") as $row) {
            $ids[] = (int) $row['ID'];
        }
        if (empty($ids)) {
            DB::alteration_message('No previously migrated elements to clear.');
            return;
        }
        $idList = implode(',', $ids);
        DB::alteration_message('Clearing ' . count($ids) . ' previously migrated elements...');

        $tables = DB::get_schema()->tableList();

        // Child rows of subtypes — gated on tableList() so re-runs stay safe
        // even if an optional child table is absent.
        if (isset($tables['pagesection'])) {
            DB::query("DELETE FROM \"PageSection\" WHERE \"ElementPageSectionID\" IN ({$idList})");
        }
        if (isset($tables['promoitem'])) {
            DB::query("DELETE FROM \"PromoItem\" WHERE \"ElementPromoID\" IN ({$idList})");
        }

        foreach (['ElementContent', 'ElementPageSection', 'ElementPromo', 'ElementEvents', 'ElementStaffMember'] as $table) {
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

        DB::query("DELETE FROM \"Element\" WHERE \"ID\" IN ({$idList})");
        DB::query("DELETE FROM \"Element_Live\" WHERE \"ID\" IN ({$idList})");
        if (isset($tables['element_versions'])) {
            DB::query("DELETE FROM \"Element_Versions\" WHERE \"RecordID\" IN ({$idList})");
        }
    }

    private function migratePlacement(array $row): string
    {
        if (in_array($row['BlockClassName'], self::SKIP_CLASSES, true)) {
            return 'skipped';
        }
        $areaId = $this->resolveAreaId($row);
        if ($areaId <= 0) {
            return 'skipped';
        }

        $elementId = $this->createElement(
            $row['BlockClassName'],
            (int) $row['BlockID'],
            $row['BlockTitle'] ?? '',
            $areaId,
            (int) $row['Sort'],
            $row['Created'],
            $row['LastEdited'],
            ($row['BlockArea'] ?? '') === 'Sidebar'
        );

        if (!$elementId) {
            return 'skipped';
        }

        DB::alteration_message("  ✓ {$row['BlockClassName']} → area {$areaId} (page {$row['SiteTreeID']})");

        static $seen = [];
        $action = isset($seen[$row['BlockID']]) ? 'cloned' : 'placed';
        $seen[$row['BlockID']] = true;
        return $action;
    }

    private function resolveAreaId(array $row): int
    {
        $column = self::AREA_MAP[$row['BlockArea'] ?? 'AfterContent'] ?? 'ElementalAreaID';
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
            return 0;
        }

        $finalTitle = !empty($title) ? $title : $defaultTitle;
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

    private function mapBlockClass(string $blockClass): array
    {
        // Array lookup (PHP 7.0+) keeps this task runnable on PHP 7.4 SS4 servers.
        $map = [
            'ContentBlock'      => ['DNADesign\\Elemental\\Models\\ElementContent', 'Content'],
            'PageSectionBlock'  => ['App\\Elements\\ElementPageSection',            'Page Section'],
            'PromoBlock'        => ['App\\Elements\\ElementPromo',                  'Promo'],
            'StaffMemberBlock'  => ['App\\Elements\\ElementStaffMember',            'Staff Member'],
            'UpcomingEventsBlock' => ['App\\Elements\\ElementEvents',               'Upcoming Events'],
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

            case 'PageSectionBlock':
                $this->insertSubtypeRow('ElementPageSection', $elementId, []);
                $this->migratePageSections($elementId, $blockId);
                break;

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

            case 'StaffMemberBlock':
                $row = DB::prepared_query(
                    'SELECT "StaffMemberID" FROM "StaffMemberBlock" WHERE "ID" = ?',
                    [$blockId]
                )->record();
                $this->insertSubtypeRow('ElementStaffMember', $elementId, [
                    'StaffMemberID' => (int) ($row['StaffMemberID'] ?? 0),
                ]);
                break;

            case 'UpcomingEventsBlock':
                $row = DB::prepared_query(
                    'SELECT "Limit" FROM "UpcomingEventsBlock" WHERE "ID" = ?',
                    [$blockId]
                )->record();
                $this->insertSubtypeRow('ElementEvents', $elementId, [
                    'Limit' => max(1, (int) ($row['Limit'] ?? 5)),
                ]);
                break;
        }
    }

    /**
     * Worked example — children with link resolution via SiteTree URLSegment chain.
     * The legacy `Link` table stored URLs sometimes as raw URL, sometimes as a
     * SiteTreeID reference. Resolve both into a flat URL string for the new
     * Element subtype.
     */
    private function migratePageSections(int $elementId, int $blockId): void
    {
        $tables = DB::get_schema()->tableList();
        if (!isset($tables['pagesectionobject'])) {
            return;
        }
        $children = DB::prepared_query(
            'SELECT "pso".*, "bl"."URL" AS "BL_URL", "bl"."Title" AS "BL_Title",'
            . ' "st"."URLSegment" AS "ST_Seg",'
            . ' "pst"."URLSegment" AS "PST_Seg",'
            . ' "gpst"."URLSegment" AS "GPST_Seg"'
            . ' FROM "PageSectionObject" AS "pso"'
            . ' LEFT JOIN "Link" AS "bl" ON "bl"."ID" = "pso"."BlockLinkID"'
            . ' LEFT JOIN "SiteTree" AS "st" ON "st"."ID" = "bl"."SiteTreeID"'
            . ' LEFT JOIN "SiteTree" AS "pst" ON "pst"."ID" = "st"."ParentID"'
            . ' LEFT JOIN "SiteTree" AS "gpst" ON "gpst"."ID" = "pst"."ParentID"'
            . ' WHERE "pso"."PageSectionBlockID" = ? ORDER BY "pso"."SortOrder" ASC',
            [$blockId]
        );
        foreach ($children as $child) {
            $url = $child['BL_URL'] ?? '';
            if (!$url && ($child['ST_Seg'] ?? '')) {
                $parts = array_filter([$child['GPST_Seg'] ?: null, $child['PST_Seg'] ?: null, $child['ST_Seg']]);
                $url = '/' . implode('/', $parts) . '/';
            }
            DB::prepared_query(
                'INSERT INTO "PageSection"'
                . ' ("Title","SubTitle","Content","BlockLinkURL","BlockLinkText","Sort","ImageID","ElementPageSectionID")'
                . ' VALUES (?,?,?,?,?,?,?,?)',
                [
                    $child['Title'] ?? '',
                    $child['SubTitle'] ?? '',
                    $child['Content'] ?? '',
                    $url,
                    $child['BL_Title'] ?? '',
                    (int) ($child['SortOrder'] ?? 0),
                    (int) ($child['ImageID'] ?? 0),
                    $elementId,
                ]
            );
        }
    }

    /**
     * Promo items live in a many-many `PromoBlock_Promos` join table; the
     * child rows themselves live in `PromoObject`. The new schema uses a
     * has-many `ElementPromoID` on `PromoItem`. Each placement = one PromoItem.
     */
    private function migratePromoItems(int $elementId, int $blockId): void
    {
        $tables = DB::get_schema()->tableList();
        if (!isset($tables['promoobject']) || !isset($tables['promoblock_promos'])) {
            return;
        }
        $items = DB::prepared_query(
            'SELECT "po".*, "pbp"."SortOrder" AS "PBP_Sort",'
            . ' "bl"."URL" AS "BL_URL", "bl"."Title" AS "BL_Title",'
            . ' "st"."URLSegment" AS "ST_Seg"'
            . ' FROM "PromoBlock_Promos" AS "pbp"'
            . ' INNER JOIN "PromoObject" AS "po" ON "po"."ID" = "pbp"."PromoObjectID"'
            . ' LEFT JOIN "Link" AS "bl" ON "bl"."ID" = "po"."BlockLinkID"'
            . ' LEFT JOIN "SiteTree" AS "st" ON "st"."ID" = "bl"."SiteTreeID"'
            . ' WHERE "pbp"."PromoBlockID" = ? ORDER BY "pbp"."SortOrder" ASC',
            [$blockId]
        );
        foreach ($items as $item) {
            $url = $item['BL_URL'] ?? '';
            if (!$url && ($item['ST_Seg'] ?? '')) {
                $url = '/' . $item['ST_Seg'] . '/';
            }
            DB::prepared_query(
                'INSERT INTO "PromoItem"'
                . ' ("Title","Content","BlockLinkURL","BlockLinkText","Sort","ImageID","ElementPromoID")'
                . ' VALUES (?,?,?,?,?,?,?)',
                [
                    $item['Title'] ?? '',
                    $item['Content'] ?? '',
                    $url,
                    $item['BL_Title'] ?? '',
                    (int) ($item['PBP_Sort'] ?? 0),
                    (int) ($item['ImageID'] ?? 0),
                    $elementId,
                ]
            );
        }
    }

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
        DB::prepared_query(
            'INSERT INTO "' . $table . '" ("' . implode('","', $cols) . '") VALUES (' . implode(',', $placeholders) . ')',
            array_values($fields)
        );
        $id = $forcedId ?? (int) DB::get_conn()->getGeneratedID($table);

        if (isset($tables[$lc . '_live'])) {
            $live = $fields;
            $live['ID'] = $id;
            $liveCols = array_keys($live);
            DB::prepared_query(
                'INSERT INTO "' . $table . '_Live" ("' . implode('","', $liveCols) . '") VALUES (' . implode(',', array_fill(0, count($live), '?')) . ')',
                array_values($live)
            );
        }

        if (isset($tables[$lc . '_versions'])) {
            $v = $fields;
            unset($v['ID']);
            $v['RecordID'] = $id;
            $vCols = array_keys($v);
            DB::prepared_query(
                'INSERT INTO "' . $table . '_Versions" ("' . implode('","', $vCols) . '") VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')',
                array_values($v)
            );
        }

        return $id;
    }

    private function insertSubtypeRow(string $table, int $elementId, array $fields): void
    {
        $tables = DB::get_schema()->tableList();
        $lc = strtolower($table);
        $fields = array_merge(['ID' => $elementId], $fields);
        $cols = array_keys($fields);
        $placeholders = array_fill(0, count($fields), '?');
        DB::prepared_query(
            'INSERT INTO "' . $table . '" ("' . implode('","', $cols) . '") VALUES (' . implode(',', $placeholders) . ')',
            array_values($fields)
        );
        if (isset($tables[$lc . '_live'])) {
            DB::prepared_query(
                'INSERT INTO "' . $table . '_Live" ("' . implode('","', $cols) . '") VALUES (' . implode(',', $placeholders) . ')',
                array_values($fields)
            );
        }
        if (isset($tables[$lc . '_versions'])) {
            $v = $fields;
            unset($v['ID']);
            $v['RecordID'] = $elementId;
            $v['Version'] = 1;
            $vCols = array_keys($v);
            DB::prepared_query(
                'INSERT INTO "' . $table . '_Versions" ("' . implode('","', $vCols) . '") VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')',
                array_values($v)
            );
        }
    }

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
