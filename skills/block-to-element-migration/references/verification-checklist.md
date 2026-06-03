# Verification Checklist

The pre-merge sequence to confirm a block-to-element migration didn't break anything visible.

## Phase 5 — Migration smoke-test (after running the task)

### CMS-side checks

- [ ] Open a representative migrated page in the CMS — confirm "Elements" tab/section shows the expected elements in the expected order.
- [ ] Element titles match what was in the legacy block titles (or are empty if ShowTitle was false on prod).
- [ ] Edit an element field, save → no PHP errors, change persists.
- [ ] **Publish the page** — no errors, change appears on frontend.
- [ ] Open an element you just published → confirm `_Versions` row exists (look at the page's history view, or `SELECT COUNT(*) FROM Element_Versions WHERE RecordID = X`).
- [ ] Unpublish the page → frontend 404s correctly.
- [ ] Re-publish → frontend recovers.

If publishing strips the element from `_Live`, **`_Versions` rows are probably missing.** Re-check `insertVersionedRow()` writes to all three tables.

### Database-side checks

```sql
-- Every migrated element has both draft and live rows
SELECT
    COUNT(DISTINCT e.ID) AS draft_count,
    COUNT(DISTINCT el.ID) AS live_count,
    COUNT(DISTINCT ev.RecordID) AS version_count
FROM Element e
LEFT JOIN Element_Live el ON el.ID = e.ID
LEFT JOIN Element_Versions ev ON ev.RecordID = e.ID
WHERE e.ExtraClass LIKE '%migrated-from-block%';
-- draft_count and live_count should be equal; version_count >= draft_count
```

```sql
-- Every migrated element has its subtype row
SELECT
    e.ID,
    e.ClassName,
    CASE e.ClassName
        WHEN 'DNADesign\\Elemental\\Models\\ElementContent' THEN
            (SELECT COUNT(*) FROM ElementContent WHERE ID = e.ID)
        WHEN 'App\\Elements\\ElementPromo' THEN
            (SELECT COUNT(*) FROM ElementPromo WHERE ID = e.ID)
        -- add per-subtype rows
    END AS has_subtype
FROM Element e
WHERE e.ExtraClass LIKE '%migrated-from-block%'
  AND has_subtype = 0;
-- Should return zero rows
```

```sql
-- Every child sub-object resolves to a real parent element, and has its own
-- _Live + _Versions rows. A child that migrated but renders nothing usually
-- means a missing _Live/_Versions row; an orphaned child means the parent FK
-- (or a join-table reference) points at an ID that doesn't exist. See #19.
SELECT pi.ID, pi.ElementPromoID
FROM PromoItem pi
LEFT JOIN PromoItem_Live pil       ON pil.ID = pi.ID
LEFT JOIN PromoItem_Versions piv   ON piv.RecordID = pi.ID
LEFT JOIN ElementPromo ep          ON ep.ID = pi.ElementPromoID
WHERE pil.ID IS NULL          -- not published to _Live
   OR piv.RecordID IS NULL    -- no _Versions row (publish() will strip it)
   OR ep.ID IS NULL;          -- orphaned: parent element missing
-- Should return zero rows. Repeat per child type (PageSection, gallery items, …).

-- If your project uses the many-many base-table model instead (BaseElementObject
-- joined via ElementPromos_Promos), the orphan check is on the join table:
SELECT p.* FROM ElementPromos_Promos p
LEFT JOIN BaseElementObject o ON o.ID = p.PromoObjectID
WHERE o.ID IS NULL;  -- must return 0 rows (every join reference resolves)
```

```sql
-- Every page that had blocks has an ElementalArea, and the area is on _Live too
SELECT DISTINCT
    p.ID, p.Title, p.ElementalAreaID,
    pl.ElementalAreaID AS live_area_id
FROM Page p
INNER JOIN SiteTree_Blocks stb ON stb.SiteTreeID = p.ID
LEFT JOIN Page_Live pl ON pl.ID = p.ID
WHERE p.ElementalAreaID = 0 OR pl.ElementalAreaID = 0 OR p.ElementalAreaID != pl.ElementalAreaID;
-- Should return zero rows. If not, syncPageAreasToLive() didn't run.
```

## Phase 6 — Visual parity (after migration + template updates)

### Per page type / per area variant

For each combination of (page type × element type × area variant), pick one representative page and:

- [ ] **Fetch HTML from prod** and **from local** (`curl -s` both, save to temp files).
- [ ] **Strip dynamic noise** — server-rendered timestamps, CSRF tokens, asset hashes, dev-navigator block (`<div Live>...</div>`). A data-migration skill's `strip_dynamic_bits.sh` helper can automate this.
- [ ] **Run a structural diff** on the cleaned files. Pass = only asset-host differences (image URLs point to different domains).
- [ ] **Browser-side check** — open both URLs side-by-side via Chrome MCP or just two browser windows.
   - Heights match? Widths match? Spacing match?
   - Test mobile/tablet viewports too — sidebar layouts are particularly prone to mobile-only breakage.
- [ ] **Inspect computed CSS** in dev tools for the migrated element on local — confirm the legacy block CSS rules are still matching (e.g. `.contentblock .typography p { ... }` rules still apply if you used Option A from [template-parity-pattern.md](template-parity-pattern.md)).
- [ ] **Click links inside the migrated content** — confirm Linkable migration produced working hrefs (vs `#` or empty).

### Edge cases worth a one-time check

- [ ] **Pages with NO migrated blocks** — confirm they render unchanged (no regression from adding ElementalArea relations).
- [ ] **Pages with only sidebar blocks** (no main-area blocks) — confirm sidebar still renders.
- [ ] **Homepage with multiple ElementalArea relations** — confirm each area renders into its own slot in the layout.
- [ ] **A page with a shared block now cloned into multiple Element placements** — confirm both copies render identically.
- [ ] **The element edit UI in the CMS** — confirm the GridField pickers, image uploaders, link selectors all work on a migrated element.

## Final pre-merge

- [ ] `silverstripe.log` shows no new PHP warnings/errors from any page view.
- [ ] `composer install` and `dev/build flush=1` both run clean on a fresh clone of the branch.
- [ ] One non-author reviewer has opened a migrated page in the CMS and made a content edit, confirming the data-shape is sane.
- [ ] If using a copilot review skill: at least one round of code review with zero outstanding actionable comments.
- [ ] Migration task is committed; template files are committed; migration is documented in the PR description with example screenshots if visual changes were significant.

## On prod (post-deploy)

- [ ] Run `dev/tasks/block-migration "dry-run=1"` on prod first — confirm the by-type/by-area breakdown matches what local showed.
- [ ] Run `dev/tasks/block-migration` (no dry-run).
- [ ] Run `dev/build flush=1`.
- [ ] Spot-check 3–5 prod pages of different types.
- [ ] **Keep the SS3 legacy tables (`Block`, `SiteTree_Blocks`, etc.) for at least one release cycle** in case you need to re-run the migration. Drop them only after a stable prod week.

## When something's wrong

| Symptom | Likely cause | Where to look |
|---------|--------------|----------------|
| Element exists in CMS but page renders nothing | `_Live` row missing OR `Page_Live.ElementalAreaID = 0` | `publishElementalAreasToLive()` + `syncPageAreasToLive()` |
| Edit element → save → element disappears on frontend | `_Versions` row missing — publish strips from `_Live` | `insertVersionedRow()` writes |
| Element renders but child items partly/fully missing (e.g. 1 of 3 promo icons) | Child sub-objects INSERTed with reused SS3 IDs → silent collision, or missing `_Live`/`_Versions` | Run the child orphan-check query above; use `insertVersionedRow()` (auto-increment) for children — see #19 |
| Element renders default template instead of area variant | Template suffix doesn't match `has_one` key | Filename character-for-character vs YAML |
| HTML structure right but CSS broken | Legacy block class not on outer wrapper | Hardcode class in element template |
| Area variant template shown on wrong area | Page model has two areas sharing one `has_one` relation | Declare separate `has_one` for each visual area |
| `<div Live>` showing on local — not on prod | Just SilverStripe's dev navigator, ignore | n/a |
