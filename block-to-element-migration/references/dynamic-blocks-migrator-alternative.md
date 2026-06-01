# Alternative: `dynamic/silverstripe-blocks-to-elemental-migrator`

A YAML-configurable alternative to the hand-rolled `BlockMigrationTask`. Published by Dynamic Agency as a reusable composer module.

**Module:** [`dynamic/silverstripe-blocks-to-elemental-migrator`](https://addons.silverstripe.org/add-ons/dynamic/silverstripe-blocks-to-elemental-migrator) ([Packagist](https://packagist.org/packages/dynamic/silverstripe-blocks-to-elemental-migrator))

**None of the three reference projects use it** (example-manufacturing, example-multiarea, example-custom all hand-rolled their tasks). This doc captures when you'd reach for it instead.

## What it provides

A base BuildTask `Dynamic\BlockMigration\Tasks\BlocksToElementsTask` that reads YAML mapping config and migrates blocks into elements without writing PHP per block type.

Conceptually:
```yaml
Dynamic\BlockMigration\Tasks\BlocksToElementsTask:
  migration_mapping:
    ContentBlock:
      element_class: DNADesign\Elemental\Models\ElementContent
      fields:
        Content: HTML
    PromoBlock:
      element_class: Dynamic\Elements\Promos\Elements\ElementPromos
      relations:
        Promos: { join_table: PromoBlock_Promos, ... }
```

## When to use the module

✅ The project has many block types and a small custom-mapping surface (the standard mappings cover most cases).

✅ The team prefers YAML config to PHP code.

✅ A non-developer will be configuring the migration (less error-prone than editing a `match()` statement).

✅ You want centralised mapping logic that can be reused across multiple SS3 sites the agency is migrating.

## When to stick with the hand-rolled task

❌ The project has heavy custom block logic — Linkable migration, FlexSlider → ElementHero flattening, child sub-tables with multi-level lookups. The YAML doesn't handle these out of the box and you'd extend with PHP anyway.

❌ You want full control over the SQL — e.g. denormalising data, computing derived fields, special-case publishing logic.

❌ You're already comfortable with the example-custom-shaped `BlockMigrationTask` and the project only has 3–5 block types. Adding a composer dep for a small mapping table isn't worth it.

❌ You want zero composer dependencies beyond `dnadesign/silverstripe-elemental`. (Rockline and example-multiarea both made this call.)

## How to add it to a project

```bash
composer require dynamic/silverstripe-blocks-to-elemental-migrator
ddev sake dev/build flush=1
```

Then configure mappings in `app/_config/blocks-migration.yml`. Reference the module's README for the exact YAML schema — it evolves between versions.

Run:
```bash
ddev sake dev/tasks/blocks-to-elements
```

## Migration-task feature parity

| Feature | Hand-rolled (this skill) | Dynamic module |
|---------|--------------------------|----------------|
| Block class → Element class mapping | `match()` in PHP | YAML |
| BlockArea → Page column | `AREA_MAP` constant | YAML |
| Field renames (e.g. `Content` → `HTML`) | Inline per block type | YAML `fields` map |
| Child sub-object migration | `migrate<X>Items()` helpers | YAML `relations` config (limited) |
| Idempotency | ExtraClass `migrated-from-block` marker | Depends on module version — check |
| Dry-run | `dry-run=1` query param | Module flag, check syntax |
| Versioned writes (`_Live` + `_Versions`) | `insertVersionedRow()` helper | Handled internally |
| Custom Linkable / FlexSlider passes | Add a helper method | Extend the base task in PHP |
| Visibility into what's happening | High — you wrote it | Lower — black box module |
| Re-use across projects | Copy the file | `composer require` |

## Combining approaches

Nothing stops you from using the Dynamic module for the standard 80% of mappings and writing a small project-local task for the custom 20%. This is probably the right call for a project with mostly standard blocks plus one or two custom oddballs.

```bash
# Run both, in order:
ddev sake dev/tasks/blocks-to-elements        # Dynamic module: standard blocks
ddev sake dev/tasks/custom-block-migration    # Project-local: the oddballs (FlexSlider, etc.)
```

## Why none of the reference projects use it

Best guess from reading the code:

- **example-manufacturing** — predates the module's maturity; was implemented in 2024 when the module was less battle-tested. Custom Link → LinkableLink migration logic is significant and would have needed extension.
- **example-multiarea** — has 10+ block types including project-specific custom ones (FormBlock, RecentBlogPostsBlock with custom controller hooks). Extension surface would have been large.
- **example-custom** — the refactor explicitly aimed to minimise composer dependencies. The hand-rolled task is ~600 lines and self-contained; adding a module dep for that scope wasn't a clear win.

If you're starting fresh on a project with mostly-standard blocks, **try the Dynamic module first** — the agency built it precisely to avoid the hand-rolled pattern. Only fall back to the skeleton in this skill if the module proves too constraining.

## See also

- Hand-rolled approach: [migration-task-skeleton.md](migration-task-skeleton.md)
- Block mapping reference: [block-mapping-catalog.md](block-mapping-catalog.md)
- Module on Packagist: https://packagist.org/packages/dynamic/silverstripe-blocks-to-elemental-migrator
