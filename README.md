# silverstripe-skills

Agent skills for [Silverstripe CMS](https://www.silverstripe.org/) development — version upgrades, Elemental content blocks, the Essentials theme stack, and deployment workflows. Maintained by [Jason Irish](https://github.com/jsirish).

## Install

```bash
npx skills add jsirish/silverstripe-skills
```

## Skills

| Skill | Description |
|-------|-------------|
| `block-to-element-migration` | Migrate legacy Blocks module data to Elemental elements |
| `ddev-sync` | Start DDEV, sync remote database/assets, rebuild local dev |
| `dynamic-base-site` | Dynamic's Silverstripe base-site stack patterns |
| `element-templates` | Create element template fixtures for the Essentials demo library |
| `essentials-blocks` | Guidelines for working with Essentials block elements |
| `essentials-fixtures` | Configure recipe-silverstripe-essentials-fixtures |
| `essentials-theme` | Create and customize subthemes extending silverstripe-essentials-theme |
| `server-sync-deploy` | Sync and deploy with deploy.sh / sync.sh bash scripts |
| `silverstripe-3-to-4-upgrade` | Complete workflow for upgrading SS3 projects to SS4 |
| `silverstripe-essentials-cms-training` | CMS editing reference for Silverstripe Essentials websites |
| `silverstripe-essentials-website` | Technical reference for Dynamic's Silverstripe Essentials websites |
| `silverstripe-version-upgrade` | Upgrade Silverstripe between major versions (e.g. SS4 → SS5) |
| `ss5-data-migration` | Execute Silverstripe 5 data migration tasks |
| `ss6-data-migration` | Silverstripe 6 upgrade data migration workflow |
| `visual-regression-upgrade` | Screenshot diff between two environments to verify upgrade visual parity |

## Installation

Requires [skills.sh](https://skills.sh) (`npx skills`).

### Install all skills globally (Claude Code)

```bash
npx skills add jsirish/silverstripe-skills --skill '*' -a claude-code -g
```

### Install specific skills

```bash
npx skills add jsirish/silverstripe-skills --skill silverstripe-version-upgrade --skill ddev-sync -a claude-code -g
```

### Install for multiple agents

```bash
npx skills add jsirish/silverstripe-skills --skill '*' -a claude-code -a opencode -g
```

### Update

```bash
npx skills update -g
```

### List installed

```bash
npx skills list -g
```

## Usage

Once installed, skills activate automatically based on your request. For example:

- *"upgrade this SS4 project to SS5"* → `silverstripe-version-upgrade`
- *"sync remote database"* → `ddev-sync`
- *"migrate blocks to elemental"* → `block-to-element-migration`

## Contributing

1. Clone the repo.
2. Author or edit a skill under `skills/<skill-name>/SKILL.md`.
3. Open a pull request against `main`.
