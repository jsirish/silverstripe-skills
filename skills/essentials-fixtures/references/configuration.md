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
