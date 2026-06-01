# DB Rebuild Conflicts (__TEMP__ and _Versions)

How to handle the table-rename collisions that `dev/build` produces during an SS3→SS4 schema migration, especially on iterative re-runs against fresh prod data.

## When you hit this

- After every `./sync.sh` (or any prod DB import) followed by `dev/build` on the SS4 branch
- After a previous `dev/build` crashed mid-rename (orphaned `__TEMP__` tables)
- After applying a code change that alters Versioned schema and re-running

## Why it happens

SS4 `dev/build` migrates each table by creating a `__TEMP__<TableName>` copy with the new schema, then renaming it back. If the destination table already exists from a prior run (or from the SS3 source DB), the rename fails:

```
Couldn't run query: ALTER TABLE "__TEMP__SiteTree_Versions" RENAME "SiteTree_Versions"
Table 'SiteTree_Versions' already exists
```

The SS3 source DB usually has many `_Versions` (or `_versions` — case varies by host) tables from `Versioned` extension data. SS4 keeps some of these but renames others. Until you drop the conflicting ones, dev/build can't proceed past the first conflict.

## Fix: drop all conflicting tables in one statement

```bash
ddev mysql -s -N -e "SELECT TABLE_NAME FROM information_schema.TABLES \
  WHERE TABLE_SCHEMA=DATABASE() AND \
  (TABLE_NAME LIKE BINARY '%\\_Versions' OR TABLE_NAME LIKE BINARY '%\\_versions' \
   OR TABLE_NAME LIKE '\\_\\_TEMP\\_\\_%') \
  AND TABLE_NAME NOT LIKE 'App\\_%';" > /tmp/drop_list.txt

awk 'BEGIN{ORS=""; print "DROP TABLE IF EXISTS "} \
     {if(NR>1) printf ","; printf "`%s`", $0} END{print ";"}' \
  /tmp/drop_list.txt | ddev mysql
```

Then re-run dev/build:
```bash
ddev exec rm -rf silverstripe-cache
ddev exec vendor/bin/sake dev/build "flush=1"
```

## Why one DROP statement, not a loop

- 80+ tables = 80+ MySQL session round-trips if looped
- Single statement runs in <1 second
- Atomicity isn't needed (we're dropping in any order)

## What the filters mean

| Filter | Why |
|--------|-----|
| `LIKE BINARY '%\_Versions'` | Match `_Versions` case-sensitively (avoids `_versions` lowercase confusion) |
| `LIKE BINARY '%\_versions'` | Match `_versions` lowercase (SS3 hosts vary) |
| `LIKE '__TEMP__%'` | Orphaned temp tables from prior failed runs |
| `NOT LIKE 'App\_%'` | Preserve SS4-namespaced `App_*` tables that we want to keep (e.g., `App_Pages_StaffMember_Versions`) |

The `App\_` escape is critical — without it the filter would also strip your SS4 tables.

## Verification

After the drop, verify:

```bash
ddev mysql -s -N -e "SELECT COUNT(*) FROM information_schema.TABLES \
  WHERE TABLE_SCHEMA=DATABASE() AND \
  (TABLE_NAME LIKE BINARY '%\\_Versions' OR TABLE_NAME LIKE BINARY '%\\_versions' \
   OR TABLE_NAME LIKE '\\_\\_TEMP\\_\\_%');"
```

Should be near 0 (some SS4 tables like `App_Pages_StaffMember_Versions` may remain — that's correct).

## Iterative dev/build failures

If a single dev/build run hits multiple conflicts, you may need to iterate:

1. Run dev/build
2. Note the conflicting table from the error
3. Drop ALL `_Versions` matches (not just the named one — there are usually 80+)
4. Re-run dev/build

In practice, dropping in bulk (as above) takes one iteration. The "drop one, retry" loop documented in some older skills is slower.

## When NOT to do this

- On production. This is local-only. Production data shouldn't be subjected to bulk drops.
- If you're not sure what's in `_Versions` and might want to keep history. (For SS3→SS4 migration, history is irrelevant — the source data is in `Block`, `ContentBlock`, etc., not in `Block_Versions`.)

## What `App_*` looks like in practice

After the drop and a fresh dev/build, you should have:

```
App_Pages_StaffMember          (SS4 subclass table for App\Pages\StaffMember)
App_Pages_StaffMember_Live     (Versioned live tier)
App_Pages_StaffMember_Versions (Versioned history)
```

If `App_Pages_*` tables are missing, your `app/_config/*.yml` classname remap isn't picking them up.
