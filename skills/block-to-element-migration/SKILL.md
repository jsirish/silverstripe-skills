---
name: block-to-element-migration
description: >
  Migrate legacy SilverStripe blocks (sheadawson/silverstripe-blocks or
  dynamic/dynamic-blocks) to SilverStripe Elemental during an SS3→SS4 (or
  later) upgrade. Covers the migration BuildTask, the block-class →
  element-class mapping catalog, the BlockArea → ElementalArea relation
  mapping, the legacy-template → element-template duplication pattern
  (preserving HTML/CSS verbatim), and the area-suffix template convention
  for area-specific rendering (`Element_RelationName.ss`).

  Use when:
  - A project still has populated `SiteTree_Blocks` / `Block` tables
  - Element templates render but the HTML doesn't match the legacy site
  - You need different rendering of the same Element class in different
    page areas (sidebar vs main content vs home-content)
  - You're starting a new SS3→SS4 upgrade and want a proven workflow
---

# Block → Element Migration

A repeatable workflow for moving legacy SilverStripe Blocks (sheadawson or dynamic-blocks modules) into the modern Elemental system, while preserving exact visual parity with the pre-upgrade site.

Distilled from three production migrations: **example-manufacturing**, **example-multiarea**, and **example-custom**. Each project re-invented the same skeleton — this skill captures the shared shape so you don't start from scratch.

---

## The two halves of a block-to-element migration

1. **Data migration** — a one-shot `BuildTask` that reads `Block` and `SiteTree_Blocks` tables and writes `Element` + subtype + `_Live` + `_Versions` rows.
2. **Template parity** — element templates that produce the same HTML the legacy block templates produced, so existing CSS/JS continues to work without rewriting.

Most failed migrations get half 1 right and skip half 2, leaving the CMS populated but the frontend visually broken. Both halves are required.

---

## The one rule

**Preserve the legacy HTML structure exactly. Only change variables that inject data.**

`ContentBlock_HomeContent.ss` becomes `ElementContent_HomeContentElementalArea.ss` with:
- The `<div>` nesting and class names **identical**
- `$Content` swapped to `$HTML` (Elemental's field name)
- `<% if $Title %>` upgraded to `<% if $ShowTitle && $Title %>` (Elemental convention)
- Optionally the legacy block class (`contentblock block`) appended to the outer wrapper so existing CSS selectors still match

See [references/template-parity-pattern.md](references/template-parity-pattern.md) for the full cheatsheet.

---

## 6-Phase Workflow

### Phase 1 — Discovery

Confirm the legacy tables exist, enumerate what's there, and inventory the project's page models.

```sql
-- What block types exist and how many of each?
SELECT ClassName, COUNT(*) FROM Block GROUP BY ClassName ORDER BY COUNT(*) DESC;

-- What areas are blocks attached to?
SELECT BlockArea, COUNT(*) FROM SiteTree_Blocks GROUP BY BlockArea;

-- What legacy block templates does the theme have?
find themes -name '*Block*.ss' | sort
```

Cross-reference each ClassName against [references/block-mapping-catalog.md](references/block-mapping-catalog.md) to identify the target Element class and any composer modules to install.

### Phase 2 — Page model setup

For each `BlockArea` you discovered, ensure the corresponding `has_one` ElementalArea relation exists on the relevant page model. The relation name becomes the template-suffix; pick deliberately.

```yaml
# app/_config/elemental.yml
Page:
  extensions:
    ElementalPageExtension: DNADesign\Elemental\Extensions\ElementalPageExtension
  has_one:
    SidebarElementalArea: DNADesign\Elemental\Models\ElementalArea
  owns:
    - SidebarElementalArea
  cascade_duplicates:
    - SidebarElementalArea

App\Pages\HomePage:
  has_one:
    HomeContentElementalArea: DNADesign\Elemental\Models\ElementalArea
  owns:
    - HomeContentElementalArea
  cascade_duplicates:
    - HomeContentElementalArea
```

Run `ddev sake dev/build flush=1` to create the columns. See [references/area-relation-mapping.md](references/area-relation-mapping.md) for the SS3 area string → SS4 relation name conventions used in the three reference projects.

### Phase 3 — Migration task

Copy [references/migration-task-skeleton.md](references/migration-task-skeleton.md) into `app/src/Tasks/BlockMigrationTask.php` and fill in three places:

1. `SKIP_CLASSES` — block types with no SS4 equivalent (e.g. `ChildPagesBlock`)
2. `AREA_MAP` — your project's `BlockArea` → page column lookup
3. `mapBlockClass()` — your project's `BlockClassName` → `[ElementClass, defaultTitle]` match

For each non-trivial block type (those with child sub-objects like PromoBlock's PromoObjects), add a `migrate<BlockName>Items()` helper. The skeleton includes worked examples for ContentBlock, PromoBlock, and PageSectionBlock.

If your project prefers YAML config over PHP, see [references/dynamic-blocks-migrator-alternative.md](references/dynamic-blocks-migrator-alternative.md) for the `dynamic/silverstripe-blocks-to-elemental-migrator` module.

### Phase 4 — Template parity (the visual-fidelity half)

For each block class being migrated, walk the project's legacy templates:

```bash
find themes -name '<BlockName>*.ss'
# Typical output:
#   themes/foo/templates/Includes/ContentBlock.ss
#   themes/foo/templates/Includes/ContentBlock_HomeContent.ss
#   themes/foo/templates/Includes/ContentBlock_SideBar.ss
```

For **each** legacy template, create the corresponding element template:

| Legacy SS3 template | New SS4 element template |
|---------------------|--------------------------|
| `Includes/<Block>.ss` | `<NS>/Elements/<Element>.ss` (default) |
| `Includes/<Block>_<Area>.ss` | `<NS>/Elements/<Element>_<RelationName>.ss` |
| `Includes/<Block>_HomeContent.ss` (DNADesign element) | `DNADesign/Elemental/Models/<Element>_<RelationName>.ss` |

Where `<NS>` is the element's PHP namespace path with backslashes replaced by slashes (`App\Elements\ElementPromo` → `App/Elements/ElementPromo.ss`).

Apply the variable-swap cheatsheet from [references/template-parity-pattern.md](references/template-parity-pattern.md). Keep the HTML structure untouched.

If Elemental's verbose `BaseElement DataObject e123` wrapper classes leak through, override `DNADesign/Elemental/Layout/ElementHolder.ss` in your theme's templates directory to just `$Element`. See [references/area-suffix-templates.md](references/area-suffix-templates.md) for full detail on template resolution.

### Phase 5 — Dry-run, execute, smoke-test

```bash
# Preview what would migrate
ddev sake dev/tasks/block-migration "dry-run=1"

# Execute
ddev sake dev/tasks/block-migration

# Verify pages have areas + elements
ddev sake dev/build flush=1
```

In the CMS: open a migrated page, confirm elements appear, edit one, save, **publish** — confirm the page still renders on the frontend. (Missing `_Versions` rows cause this to silently strip the element from `_Live`.)

**Evidence gate (Phase 5):** paste the source-vs-target row counts before declaring the data half done. The source count is **placement rows of published blocks**, not `Block` rows: the skeleton creates one element per `SiteTree_Blocks` row and migrates published blocks only (via the `Block_Live` join), so counting `Block` overcounts drafts and undercounts shared blocks placed on multiple pages.

```sql
-- source: placement rows of published, non-skipped blocks
SELECT COUNT(*) FROM SiteTree_Blocks stb
JOIN Block b       ON b.ID = stb.BlockID
JOIN Block_Live bl ON bl.ID = b.ID
WHERE b.ClassName NOT IN (<skip-classes>);

-- target: migrated elements only, versions counted per record
SELECT COUNT(e.ID)                 AS draft_count,
       COUNT(el.ID)                AS live_count,
       COUNT(DISTINCT ev.RecordID) AS version_count
FROM Element e
LEFT JOIN Element_Live el     ON el.ID = e.ID
LEFT JOIN Element_Versions ev ON ev.RecordID = e.ID
WHERE e.ExtraClass LIKE '%migrated-from-block%';
```

Source count = draft_count = live_count, and version_count >= draft_count. For each child sub-object table (PromoItems, gallery items), run the orphaned-child and subtype-row queries from [references/verification-checklist.md](references/verification-checklist.md): each must return zero rows.

### Phase 6 — Visual parity verification (required gate)

Walk **every item** in [references/verification-checklist.md](references/verification-checklist.md): for each page type that uses a migrated element, curl prod and local, strip dynamic bits, diff. Pass = only asset-host differences remain.

This checklist is the exit gate for the migration, not an optional reference. The migration is not done until each checklist item has pasted command output showing it passed. A migration that skips this phase is the classic half-done failure described at the top of this skill: CMS populated, frontend broken.

## Do not rationalize

| Rationalization | Required behavior |
|-----------------|-------------------|
| "The task reported success, so the data is right" | Paste the Phase 5 draft/live/versions count queries. Explicit-ID child inserts collide silently and still report success. |
| "The element shows in the CMS, so publish works" | Do the edit-save-publish round trip and confirm the frontend still renders. Missing `_Versions` rows only surface on publish. |
| "The templates look the same" | Run the Phase 6 curl-and-diff per page type and paste the diff summary. Eyeballing misses wrapper-class and nesting changes that break CSS. |
| "The verification checklist is optional" | It is the Phase 6 exit gate. Every item passes with pasted output, or the migration is not done. |

---

## Critical files reference

| File | When you touch it |
|------|---------------------|
| `app/src/Tasks/BlockMigrationTask.php` | Phase 3 — copy from skeleton, fill in 3 places |
| `app/_config/elemental.yml` | Phase 2 — declare `has_one` area relations |
| `themes/<theme>/templates/.../Elements/<Element>.ss` | Phase 4 — default template per element |
| `themes/<theme>/templates/.../Elements/<Element>_<RelationName>.ss` | Phase 4 — area-specific variant per legacy block-area template |
| `themes/<theme>/templates/DNADesign/Elemental/Layout/ElementHolder.ss` | Phase 4 — optional wrapper override (`$Element` to strip default classes) |

---

## Anti-patterns

- **Don't add LegacyBlockID columns.** Use the ExtraClass `migrated-from-block` marker — no schema changes. Re-runs stay clean only if cleanup also deletes child rows (PromoItems, gallery items) by their parent FK before re-import; otherwise they orphan or accumulate.
- **Don't skip `_Versions` writes.** Use `insertVersionedRow()` for every Element write **and every child sub-object**. Skipping `_Versions` will appear to work until someone edits the element in the CMS, at which point `publish()` will silently strip it — or its children — from `_Live`.
- **Don't INSERT child sub-objects with their original SS3 IDs.** IDs get reused across object types and re-runs; an explicit-ID INSERT silently collides and leaves join/FK references pointing at rows that don't exist, so the element renders empty even though the migration reports success. Let the DB auto-increment (use `insertVersionedRow()`), keep an old→new map when a join table is involved, and rewrite the join table with the new IDs. See [#19](https://github.com/jsirish/silverstripe-skills/issues/19) and the verification-checklist orphan query.
- **Don't try to revive SS3 JS for height calculations.** If SS3 used JS to set fixed heights and CSS depended on it (e.g. `vert-centering` with `position: absolute`), the layout will collapse in SS4 — strip those classes from the element template rather than trying to port the JS.
- **Don't rewrite the legacy theme CSS during migration.** Either keep the legacy block class on the element wrapper, or override `ElementHolder.ss` — defer the CSS rewrite until after migration is verified.
- **Don't use the namespaced `<% include SilverStripe\Foo\Bar %>` form if the theme has an `Includes/Bar.ss` override.** The namespaced form bypasses theme cascading. Use un-namespaced `<% include Bar %>` to let the theme override apply.

---

## See also

- [references/migration-task-skeleton.md](references/migration-task-skeleton.md) — canonical `BlockMigrationTask.php`
- [references/block-mapping-catalog.md](references/block-mapping-catalog.md) — legacy block class → Element class lookup
- [references/area-relation-mapping.md](references/area-relation-mapping.md) — BlockArea → has_one relation conventions
- [references/template-parity-pattern.md](references/template-parity-pattern.md) — duplicating templates with variable swaps
- [references/area-suffix-templates.md](references/area-suffix-templates.md) — the `Element_RelationName.ss` mechanism
- [references/dynamic-blocks-migrator-alternative.md](references/dynamic-blocks-migrator-alternative.md) — YAML-configured alternative module
- [references/verification-checklist.md](references/verification-checklist.md) — pre-merge visual parity checks (the required Phase 6 exit gate)
- [examples/](examples/) — worked example task, YAML config, and template diff
- Sibling skill: [silverstripe-3-to-4-upgrade](../silverstripe-3-to-4-upgrade/SKILL.md) — the broader upgrade workflow
- [silverstripe-3-to-4-upgrade/references/page-layout-parity.md](../silverstripe-3-to-4-upgrade/references/page-layout-parity.md) — page-level markup parity (margin-collapse wrappers, WidgetHolder, SectionNav, MenuTitle) — the same "preserve the markup exactly" rule applied at the layout-template level
