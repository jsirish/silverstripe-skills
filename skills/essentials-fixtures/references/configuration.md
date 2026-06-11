# Project Configuration Reference

## Required Files
Every Essentials project using this recipe needs:
1. **app/_config/fixtures-populate.yml** — Fixture loading configuration + extension registrations
2. **app/_config/essentials-element-fixtures.yml** — Element placeholder data config (separate system)

## Example fixtures-populate.yml (Partial)
> [!CAUTION]
> You **must** register `FixtureRecordExtension` on **every DataObject class that appears in your fixture YAML**.

```yaml
---
Name: project-fixtures-populate
Only:
  environment: 'dev'
---
DNADesign\Populate\Populate:
  include_yaml_fixtures:
    - 'vendor/dynamic/recipe-silverstripe-essentials-fixtures/app/fixtures/shared-assets.yml'
    - 'vendor/dynamic/recipe-silverstripe-essentials-fixtures/app/fixtures/templates/template-gutenberg-bold-hero.yml'
  truncate_objects: []

---
Name: project-fixtures-extensions
Only:
  environment: 'dev'
---
# Minimal registration example:
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\Recipe\Fixtures\Extensions\FixtureRecordExtension
SilverStripe\Assets\File:
  extensions:
    - Dynamic\Recipe\Fixtures\Extensions\FixtureRecordExtension
```

## `include_yaml_fixtures` — ordering rules
Populate loads files **in array order**, and references (`=>Class.identifier`) only resolve to
records defined **earlier** in the run. Two consequences:

1. **Shared assets first.** `shared-assets.yml` (Images/Files) must precede any fixture that
   references them, or you get `Could not resolve reference` errors.
2. **Dependencies before dependents.** A template that references a block/element record must be
   listed after the file that defines it.

```yaml
DNADesign\Populate\Populate:
  include_yaml_fixtures:
    # 1. assets — referenced by everything downstream
    - 'vendor/dynamic/recipe-silverstripe-essentials-fixtures/app/fixtures/shared-assets.yml'
    # 2. templates — may reference the assets above
    - 'vendor/dynamic/recipe-silverstripe-essentials-fixtures/app/fixtures/templates/template-gutenberg-bold-hero.yml'
    - 'vendor/dynamic/recipe-silverstripe-essentials-fixtures/app/fixtures/templates/template-feature-grid.yml'
    # 3. project-local overrides last so they win
    - 'app/fixtures/local-overrides.yml'
```

## `truncate_objects`
Listed classes are **emptied before** the fixtures load — use it to make a Populate run
idempotent on re-run instead of accumulating duplicates. Leave empty (`[]`) to preserve
existing data and only insert what's missing.

```yaml
DNADesign\Populate\Populate:
  truncate_objects:
    - DNADesign\Elemental\Models\ElementContent
    - Dynamic\Elements\Hero\Elements\ElementHero
```

> [!CAUTION]
> `truncate_objects` is destructive. Never run Populate (and never include it in a YAML guarded
> only by `environment: 'dev'`) against a database that holds real content.

## Extension registration — which classes?
Register `FixtureRecordExtension` on **every DataObject class that appears as a fixture block** in
your YAML — not just `SiteTree`/`File`. That includes each Element subclass and any nested
`has_one`/`has_many` model you populate. Missing registrations are the most common cause of
records that "load" without error but never appear in the CMS (see
[Troubleshooting → Records load but don't appear](./troubleshooting.md#3-records-load-but-dont-appear)).

```yaml
# Register on each element/model that appears in fixture YAML:
Dynamic\Elements\Hero\Elements\ElementHero:
  extensions:
    - Dynamic\Recipe\Fixtures\Extensions\FixtureRecordExtension
DNADesign\Elemental\Models\ElementContent:
  extensions:
    - Dynamic\Recipe\Fixtures\Extensions\FixtureRecordExtension
```

## The dev-only guard
Both config blocks use `Only: { environment: 'dev' }` so fixtures and their extension
registrations never activate in production. Keep this guard on every fixture-populate YAML.
