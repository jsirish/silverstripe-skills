---
name: dynamic-base-site
description: Reference for Dynamic's Silverstripe base-site stack - the dynamic/silverstripe-base-site module set, per-version namespaces (SS4 through SS6), extension locations, and upgrade checklist. Use when working in a project that requires dynamic/silverstripe-base-site, when resolving "class not found" or namespace errors in a Dynamic base-site project, or when planning an upgrade of a base-site project. Triggers on "base-site", "base site stack", "Dynamic base site", or unfamiliar dynamic/* module questions.
---

# Dynamic Silverstripe Base-Site Stack

This skill documents the Dynamic base-site module ecosystem used in Silverstripe projects.

## Core Modules

| Package | Purpose |
|---------|---------|
| `dynamic/recipe-silverstripe-base-site` | Meta-package that bundles all base-site dependencies |
| `dynamic/silverstripe-base-site` | Core page types, models, and extensions |
| `dynamic/silverstripe-site-tools` | Shared utilities, extensions, and helper classes |

## Namespace Changes by Version

### Silverstripe 6 (dynamic/silverstripe-base-site:^8, branch `8`)
- Namespaces are **unchanged from SS5**: `Dynamic\Base\*` (page types, models), `Dynamic\SiteTools\*`
  (extensions, utilities). No PSR-4 migration needed for base-site's own classes - only SS6 core
  framework classes moved (see [version-map-ss6.md](../silverstripe-version-upgrade/references/version-map-ss6.md)
  for the core class renames, e.g. `ViewableData` → `ModelData`).
- `SiteConfig`'s header nav (`UtilityLinks`) moves from a `many_many` join table
  (`SiteConfig_UtilityLinks`) to a `has_many SiteTreeLink` relation via `silverstripe/linkfield ^5` -
  same data-migration pattern as the general SS5→SS6 LinkField conversion. See
  [data-migration-tasks.md](../silverstripe-version-upgrade/references/data-migration-tasks.md)
  ("UtilityLinks" section) for the migration task.
- SS6-relevant deprecations that hit base-site projects specifically: `sheadawson/silverstripe-linkable`
  → `silverstripe/linkfield ^5` (run the linkable data migration on SS5 with linkfield `^4` **before**
  the SS6 bump); `nswdpc/silverstripe-thereisnouserform` removed from the SS6 recipe (drop any
  `UserDefinedFormPageExtension` registrations in `app/_config/essentials.yml` or `dev/build` fatals);
  `undefinedoffset/silverstripe-nocaptcha` removed, no replacement.

### Silverstripe 5 (dynamic/silverstripe-base-site:^7)
- Namespace: `Dynamic\BaseRecipe\*` (some classes)
- Namespace: `Dynamic\Base\*` (page types, models)
- Namespace: `Dynamic\SiteTools\*` (extensions, utilities)

### Silverstripe 4 (dynamic/silverstripe-base-site:^4)
- Namespace: `Dynamic\Base\*` (all classes)

## Common Extensions

Extensions may move between `silverstripe-base-site` and `silverstripe-site-tools` in major versions:

| Extension | SS5 Location | Notes |
|-----------|--------------|-------|
| `TemplateDataExtension` | `Dynamic\Base\Extension` | For SiteConfig |
| `ReviewContentDataExtension` | `Dynamic\SiteTools\Extension` | For SiteConfig |
| `CmsDesignDataExtension` | `Dynamic\Base\Extension` | For SiteTree |
| `SeoExtension` | `Dynamic\Base\Extension` | For SiteTree |
| `HeaderImageExtension` | `Dynamic\SiteTools\Extension` | For pages needing header images |
| `PreviewExtension` | `Dynamic\SiteTools\Extension` | For BlogPost |
| `DataobjectPermissionExtension` | `Dynamic\SiteTools\Extension` | For UserForms |
| `ContactDataExtension` | `Dynamic\SiteTools\Extension` | For CompanyAddress |

## Configuration Patterns

### base-site-config.yml (project)
Should closely mirror the recipe's version. Contains:
- SiteConfig extensions
- SiteTree extensions  
- Page type configurations (elemental, etc.)

### mysite.yml (project)
Contains project-specific customizations:
- Project-specific extensions (FlexSlider, etc.)
- Custom element configurations
- Third-party module configs

## Upgrade Checklist

When upgrading major versions:

1. **Check Recipe Config**: View the recipe's `app/_config/base-site-config.yml` to understand the current version's patterns
   ```bash
   cat vendor/dynamic/recipe-silverstripe-base-site/app/_config/base-site-config.yml
   ```

2. **Verify Extension Locations**: Extensions may move between modules
   ```bash
   grep -r "class ExtensionName" vendor/dynamic/silverstripe-*/src/
   ```

3. **Check for Removed Extensions**: Some custom extensions get replaced by third-party modules
   - `CompanyDataExtension` - removed in SS5
   - `IntegrationsDataExtension` - removed in SS5

4. **Namespace Updates**: Check for namespace changes
   - `Dynamic\Base` → `Dynamic\BaseRecipe` in some classes

5. **Third-Party Replacements**: Custom code often gets replaced:
   - FlexSlider → Carousel (`dynamic/carousel`)
   - Linkable → LinkField (`silverstripe/linkfield`)
   - ElementalStylings → removed (use native styles config)

## Common Upgrade Issues

### Missing Extension Errors
```
InvalidArgumentException: ClassName references nonexistent Extension in 'extensions'
```
**Solution**: Check if extension moved to different module or was removed. Search vendor:
```bash
grep -r "class ExtensionName" vendor/dynamic/
```

### has_many Relation Errors
```
No has_one found on class 'X', the has_many relation from 'Y' to 'X' requires a has_one
```
**Solution**: Add reciprocal has_one via config in mysite.yml:
```yaml
Namespace\ClassName:
  has_one:
    ParentClass: Namespace\ParentClass
```

### Deprecated Packages
- `ryanpotter/silverstripe-cms-theme` - SS5 has built-in CMS theme
- `lekoala/silverstripe-debugbar` - check for SS5 compatible version
- `fractas/elemental-stylings` - use native `styles` config instead
