---
name: essentials-fixtures
description: Configure and implement the recipe-silverstripe-essentials-fixtures package for template population in Essentials projects.
---

# Essentials Fixtures Recipe Skill

This skill covers the `dynamic/recipe-silverstripe-essentials-fixtures` package — the fixture data layer for Element Template records.

## Quick Links
- [Project Configuration Reference](./references/configuration.md)
- [Troubleshooting & Known Issues](./references/troubleshooting.md)

## Related Skills
| Skill | Purpose |
|-------|---------|
| **Element Templates** | Designing and authoring template fixture YAML |
| **Essentials Theme** | Subtheme customization, color palette, CSS variables |
| **Silverstripe Essentials Website** | Full project architecture and config |

## Before running Populate
Element block colors are **baked into each record at populate time** from the
`ColorConfigurationProvider` palette (`background_colors` / `button_colors` in
`app/_config/essentials-styles.yml`). The recipe ships with essentials-theme
placeholder colors (purple `#9575EA`, lavender `#CCBEF5`, …). Substitute the
project palette **before** the first `PopulateTask` run, or every element template
is created with the wrong colors and must be re-populated.

1. Determine the project palette — Figma variable defs (`mcp__Figma__get_variable_defs`),
   XD prototype PNG sampling, or the brand guide / style tiles.
2. Replace the placeholder `background_colors` / `button_colors` in
   `app/_config/essentials-styles.yml` with the project's brand colors.
3. `ddev sake dev/build "flush=1"` to apply the config.
4. Then run Populate (below).

> [!CAUTION]
> Palette-first ordering matters: changing `essentials-styles.yml` *after* Populate
> does **not** update records already created. See
> [Color palette — set before first Populate](./references/configuration.md#color-palette--set-before-first-populate).

## Running Populate
```bash
# Fresh setup
ddev sake dev/build "flush=1"
ddev sake dev/tasks/PopulateTask "flush=1"
```

## Decision Tree: \"Something is broken with fixtures\"
- **PopulateTask output issue?** → Use this skill.
- **Data missing when adding elements in CMS?** → Use project-level `element-fixtures.yml`.
