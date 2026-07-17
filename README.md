# silverstripe-skills

Agent skills for upgrading and migrating [Silverstripe CMS](https://www.silverstripe.org/)
projects: major-version upgrades (SS3 through SS6), data migrations, legacy Blocks-to-Elemental
migration, and Dynamic's base-site patterns. Maintained by [Jason Irish](https://github.com/jsirish).

Installable via [`npx skills`](https://skills.sh) on any supported agent (Claude Code, Codex,
Gemini CLI, Cursor, Cline, Amp, and others).

## Skills

### Version upgrades

| Skill | What it does |
|-------|-------------|
| `silverstripe-3-to-4-upgrade` | The SS3 to SS4 upgrade: namespace migration, config, directory restructure, the structurally different legacy jump. |
| `silverstripe-version-upgrade` | Major-version upgrades on the SS4+ line (SS4 to SS5, SS5 to SS6): dependency bumps, config migration, the SS6 breaking-change catalog, TinyMCE extraction. |
| `silverstripe-module-ss6-upgrade` | Module-level (composer package) SS6 upgrade loop for a single vendor module: cut the integer branch, bump constraints, run the code sweep, update CI and PHPUnit config, tag a release, flip the default branch. |

### Data migration

| Skill | What it does |
|-------|-------------|
| `ss5-data-migration` | Execute Silverstripe 5 data migration tasks (sync, dev/build, run each task with row-count verification). |
| `ss6-data-migration` | The Silverstripe 6 upgrade data-migration workflow (sync production, migrate, verify against production). |
| `block-to-element-migration` | Migrate legacy Blocks-module data (sheadawson or dynamic/dynamic-blocks) into Elemental elements during an SS3 to SS4+ upgrade: the block-to-element class map, area relation mapping, and template duplication pattern. |

### Base-site reference

| Skill | What it does |
|-------|-------------|
| `dynamic-base-site` | Reference for Dynamic's `dynamic/silverstripe-base-site` stack: per-version namespaces, extension locations, and the upgrade checklist. |

> **Moved out:** the Dynamic Essentials delivery-method skills (`essentials-blocks`,
> `element-templates`, `essentials-fixtures`, `essentials-theme`,
> `silverstripe-essentials-website`, `silverstripe-essentials-cms-training`) now live in the
> private [`dynamic/agency-skills`](https://github.com/dynamic/agency-skills) repo, alongside the
> content pipeline that populates into them. This repo keeps the framework upgrade and migration
> toolkit.

> **Companion repo:** the upgrade skills reference `ddev-sync` and `visual-regression-upgrade`
> from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills). Install both repos
> for the full upgrade workflow.

## Install

```bash
# all skills, Claude Code, global
npx skills add jsirish/silverstripe-skills --skill '*' -a claude-code -g

# specific skills
npx skills add jsirish/silverstripe-skills --skill silverstripe-version-upgrade --skill silverstripe-module-ss6-upgrade -a claude-code -g

# multiple agents
npx skills add jsirish/silverstripe-skills --skill '*' -a claude-code -a opencode -g

# also install the workflow-skills companion
npx skills add jsirish/workflow-skills
```

Update with `npx skills update -g`; list installed with `npx skills list -g`.

## Usage

Skills activate automatically based on your request. For example:

- "upgrade this SS4 project to SS5" activates `silverstripe-version-upgrade`
- "migrate blocks to elemental" activates `block-to-element-migration`
- "upgrade this module to SS6" activates `silverstripe-module-ss6-upgrade`

## Contributing

1. Clone the repo.
2. Author or edit a skill under `skills/<skill-name>/SKILL.md`.
3. Open a pull request against `main`.
