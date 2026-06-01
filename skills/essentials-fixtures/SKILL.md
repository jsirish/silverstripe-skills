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

## Running Populate
```bash
# Fresh setup
ddev sake dev/build "flush=1"
ddev sake dev/tasks/PopulateTask "flush=1"
```

## Decision Tree: \"Something is broken with fixtures\"
- **PopulateTask output issue?** → Use this skill.
- **Data missing when adding elements in CMS?** → Use project-level `element-fixtures.yml`.
