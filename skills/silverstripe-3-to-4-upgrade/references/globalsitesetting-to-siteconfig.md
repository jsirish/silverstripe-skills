# GlobalSiteSetting → SiteConfig Migration (SS3 → SS4)

Patterns for migrating `dynamic/core-tools` `GlobalSiteSetting` data onto `SiteConfig` when adopting
`dynamic/silverstripe-base-site` during an SS3→SS4 upgrade. `silverstripe-base-site` provides the
settings + navigation natively on `SiteConfig`, so the only work left is moving the **data** —
scalars and two nav relations — off the removed `GlobalSiteSetting` and onto the live `SiteConfig`.

> **Don't hand-recreate `GlobalSiteSetting` as a custom DataObject.** That's a dead-end that has to be
> unwound later. Adopt the base-site `SiteConfig` extensions and migrate the legacy data into them.

This is the SS3→SS4 analogue of the `ss5-data-migration` skill's `migrate-footer-links` /
`NavigationMigrationTask`. Patterns distilled from a production `GlobalSiteSettingMigrationTask`
(work that landed late and looked like it had never been migrated at all — the reason
this is now captured reusably).

## What moves

| Data | Source (legacy) | Target (`SiteConfig`) | Shape |
|------|-----------------|-----------------------|-------|
| Scalars | `GlobalSiteSetting` row | `SiteConfig` | CompanyName, Phone, Email, helplines, address, hours — source-of-truth overwrite |
| Header utility links | `GlobalSiteSetting_UtilityLinks` junction | `SiteConfig.UtilityLinks` many_many | idempotent `->add()` |
| Footer nav | `NavigationColumn.ConfigID` → old GlobalSiteSetting | `NavigationColumn.ConfigID` → live `SiteConfig` | re-parent rows |

## Three cross-cutting safety patterns

These apply to **every** query in the task, because the source tables may be classless by the time
the task runs.

### 1. `_obsolete_`-prefixed table fallback

`dev/build` renames tables whose class no longer exists (`GlobalSiteSetting` is gone in SS4) to
`_obsolete_GlobalSiteSetting`. The task may run before or after that rename, so resolve the live name
first and fall back to the obsolete one:

```php
private function resolveTable(string $base): ?string
{
    $tables = DB::get_schema()->tableList(); // keys are lowercased
    foreach ([$base, '_obsolete_' . $base] as $candidate) {
        if (isset($tables[strtolower($candidate)])) {
            return $tables[strtolower($candidate)]; // real, correctly-cased name
        }
    }
    return null; // nothing to migrate — bail cleanly
}
```

### 2. `ALLOWED_TABLES` allowlist + `^\w+$` guard

A resolved table name is interpolated into raw SQL (you cannot bind a table name as a parameter), so
it must never come from untrusted input. Allowlist the bases you migrate, and re-validate the resolved
name before it touches a query:

```php
private const ALLOWED_TABLES = [
    'GlobalSiteSetting',
    'GlobalSiteSetting_UtilityLinks',
    'NavigationColumn',
];

private function safeTable(string $base): ?string
{
    if (!in_array($base, self::ALLOWED_TABLES, true)) {
        return null;
    }
    $resolved = $this->resolveTable($base);
    if ($resolved === null || !preg_match('/^\w+$/', $resolved)) {
        return null; // refuse to interpolate anything but [A-Za-z0-9_]
    }
    return $resolved;
}
```

### 3. Idempotent — runs last in `migrate.sh`, safe on every prod sync

The task is the **last** step in `migrate.sh` and re-runs on every prod sync. Each section below is
written so a second run is a no-op: scalars overwrite, links skip-if-present, nav re-parents only
rows still pointing at the old config.

## Pattern 1 — scalars (source-of-truth overwrite)

Read the single `GlobalSiteSetting` row, write its scalar fields onto the live `SiteConfig`. This is an
overwrite (not a skip-if-set) because `GlobalSiteSetting` is the source of truth being retired:

```php
$gss = $this->safeTable('GlobalSiteSetting');
if ($gss === null) {
    echo "No GlobalSiteSetting table — nothing to migrate.\n";
    return;
}

$row = DB::query("SELECT * FROM \"{$gss}\" ORDER BY \"ID\" ASC")->first();
if (!$row) {
    return;
}

$config = SiteConfig::current_site_config();
$config->CompanyName    = $row['CompanyName']    ?? '';
$config->Phone          = $row['Phone']          ?? '';
$config->Email          = $row['Email']          ?? '';
$config->Helpline       = $row['Helpline']       ?? '';
$config->Address        = $row['Address']        ?? '';
$config->OpeningHours   = $row['OpeningHours']   ?? '';
$config->write();
```

> Map only the fields your project's base-site `SiteConfig` extension actually declares. Unknown
> source columns are ignored; missing source columns default to empty.

## Pattern 2 — header utility links (`many_many` junction)

The legacy `GlobalSiteSetting_UtilityLinks` junction maps the old config to `SiteTree` rows. Re-add
each to `SiteConfig.UtilityLinks`, skipping (a) links whose target page was deleted on prod and (b)
rows already linked (idempotent `->add()`):

```php
$junction = $this->safeTable('GlobalSiteSetting_UtilityLinks');
if ($junction === null) {
    return;
}

$rows = DB::query(
    "SELECT \"SiteTreeID\" FROM \"{$junction}\" ORDER BY \"SortOrder\" ASC, \"ID\" ASC"
);

$existing = $config->UtilityLinks()->column('ID'); // already-linked target IDs
$added = $skipped = 0;

foreach ($rows as $link) {
    $pageId = (int) $link['SiteTreeID'];

    // Skip rows whose target page was deleted on prod
    $page = SiteTree::get()->byID($pageId);
    if (!$page) {
        $skipped++;
        continue;
    }

    // Skip already-linked rows — keeps re-runs a no-op
    if (in_array($pageId, $existing, true)) {
        $skipped++;
        continue;
    }

    $config->UtilityLinks()->add($page);
    $added++;
}

echo "Utility links — added: {$added}, skipped: {$skipped}\n";
```

## Pattern 3 — footer nav (re-parent `NavigationColumn.ConfigID`)

Footer nav columns are `NavigationColumn` rows owned by a config via `ConfigID`. SS3 rows synced into
the SS4 DB carry `GlobalConfigID` (the old FK) but `ConfigID` defaults to `0`, so the live `SiteConfig`
never sees them. Re-parent any column still pointing at the old config (or orphaned) onto the live
`SiteConfig.ID`:

```php
$navTable = $this->safeTable('NavigationColumn');
if ($navTable === null) {
    return;
}

$liveConfigId = (int) $config->ID;

// Re-parent rows that still carry the legacy GlobalConfigID but no live ConfigID.
// Guard with the live config id so a re-run touches nothing already migrated.
DB::prepared_query(
    "UPDATE \"{$navTable}\""
    . " SET \"ConfigID\" = ?"
    . " WHERE \"ConfigID\" = 0 AND \"GlobalConfigID\" > 0",
    [$liveConfigId]
);

$moved = DB::affected_rows();
echo "Footer nav columns re-parented: {$moved}\n";
```

> If the columns are `Versioned`, apply the same update to `NavigationColumn_Live` (and publish via the
> ORM where practical). For a flat `DataObject` the single update above is enough.

## Run order

```bash
# Always the LAST task in migrate.sh, after blocks/files/widgets are migrated
ddev sake dev/tasks/globalsitesetting-migration
```

Safe to re-run on every prod sync: scalars overwrite, links skip-if-present, nav re-parents only
not-yet-migrated rows.

## See also

- `ss5-data-migration` — the SS5-line analogue (`migrate-footer-links`, `NavigationMigrationTask`).
- `silverstripe-3-to-4-upgrade/references/block-to-elemental-migration.md` — the idempotent raw-SQL
  `BuildTask` pattern (`$segment`, marker-based idempotency, `_obsolete_` handling) this doc builds on.
- `dynamic/silverstripe-base-site` — provides the `SiteConfig` settings + navigation natively; adopt it
  rather than re-creating `GlobalSiteSetting`.
