---
name: Essentials Blocks Guide
description: Guidelines for working with Essentials block elements in the Silverstripe Essentials Demo workspace
---

# Essentials Blocks Guide

This skill provides guidelines for working with Essentials block elements in the Silverstripe Essentials Demo workspace.

## Essentials Block Elements

### Included in Base Package (20+ Elements)
- ElementContent, SimpleContent, ElementCard
- ElementCallToAction, ElementAccordion, ElementCarousel
- ElementPhotoGallery, ElementTestimonials, ElementBlogPosts
- ElementCustomerService, ElementForm, ElementFeatures
- ElementPromos, ElementSponsors, ElementStatCounters
- ElementLinks, ElementOembed, ElementFileList
- ElementImage, ElementStaff, ElementRow

### NOT Supported (Do Not Use)
- `Dynamic\Elements\Blog\Elements\ElementBlogOverview`
- `Dynamic\Elements\Blog\Elements\ElementBlogPagination`
- `Dynamic\Elements\Blog\Elements\ElementBlogWidgets`

## Essentials Namespaces

| Package | Namespace |
|---------|-----------|
| Essentials Tools | `Dynamic\Essentials\*` |
| Elemental Templates | `Dynamic\ElementalTemplates\*` |
| Base Site | `Dynamic\Base\*` |
| All Dynamic Elements | `Dynamic\Elements\*\Elements\*` |
| DNADesign Elemental | `DNADesign\Elemental\*` |
| Carousel Models | `Dynamic\Carousel\Model\*` |
| Elemental Grid | `WeDevelop\ElementalGrid\Models\*` |

## Theme Guidelines

- **Base theme**: `silverstripe-essentials-theme`
- **Location**: `vendor/dynamic/silverstripe-essentials-theme`
- All Essentials sites use this as the **base theme**
- Per-project, create a **subtheme** that extends the base
- Subthemes override Bootstrap variables from `silverstripe-essentials-theme`

## Fixture Development

### File Locations
- Fixtures: `vendor/dynamic/recipe-silverstripe-essentials-fixtures/app/fixtures/`
- Config: `app/_config/fixtures-populate.yml`

### Important Notes
- Populate config must use `Only: environment: 'dev'`
- **Do NOT use `truncate_objects`** — use `PopulateMergeMatch` on `FixtureIdentifier` for idempotent re-runs
- Use `PopulateFileFrom` for images
- See the **Essentials Fixtures** skill (`essentials-fixtures/SKILL.md`) for complete configuration reference, loading sequence, and troubleshooting

### Running Populate
```bash
ddev sake dev/tasks/PopulateTask "flush=1"
```

### Known Vendor Bug

`dnadesign/silverstripe-populate` has a bug where `populateFile()` returns `true` instead of the File object when file hashes match. Requires a local vendor patch. See the [Known Vendor Bug](../essentials-fixtures/references/troubleshooting.md) section in the Essentials Fixtures Recipe skill troubleshooting guide.