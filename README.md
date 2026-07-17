# silverstripe-skills

Agent skills for [Silverstripe CMS](https://www.silverstripe.org/) development: version upgrades, legacy Blocks-to-Elemental migration, base-site patterns, and deployment workflows. Maintained by [Jason Irish](https://github.com/jsirish).

> **Moved:** the Dynamic Essentials delivery-method skills (`essentials-blocks`, `element-templates`, `essentials-fixtures`, `essentials-theme`, `silverstripe-essentials-website`, `silverstripe-essentials-cms-training`) now live in the private [`dynamic/agency-skills`](https://github.com/dynamic/agency-skills) repo, alongside the content pipeline that populates into them.

## Install

```bash
npx skills add jsirish/silverstripe-skills
```

> **Companion repo:** the upgrade skills reference `ddev-sync` and `visual-regression-upgrade` from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills). Install both repos for the full upgrade workflow:
>
> ```bash
> npx skills add jsirish/workflow-skills
> ```

## Skills

| Skill | Description |
|-------|-------------|
| `block-to-element-migration` | Migrate legacy Blocks module data to Elemental elements |
| `dynamic-base-site` | Dynamic's Silverstripe base-site stack patterns |
| `silverstripe-3-to-4-upgrade` | Complete workflow for upgrading SS3 projects to SS4 |
| `silverstripe-module-ss6-upgrade` | Module-level (composer package) SS6 upgrade loop: branch, constraints, code sweep, CI, tag |
| `silverstripe-version-upgrade` | Upgrade Silverstripe between major versions (e.g. SS4 → SS5) |
| `ss5-data-migration` | Execute Silverstripe 5 data migration tasks |
| `ss6-data-migration` | Silverstripe 6 upgrade data migration workflow |

## Installation

Requires [skills.sh](https://skills.sh) (`npx skills`).

### Install all skills globally (Claude Code)

```bash
npx skills add jsirish/silverstripe-skills --skill '*' -a claude-code -g
```

### Install specific skills

```bash
npx skills add jsirish/silverstripe-skills --skill silverstripe-version-upgrade --skill silverstripe-module-ss6-upgrade -a claude-code -g
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
- *"migrate blocks to elemental"* → `block-to-element-migration`

## Contributing

1. Clone the repo.
2. Author or edit a skill under `skills/<skill-name>/SKILL.md`.
3. Open a pull request against `main`.
