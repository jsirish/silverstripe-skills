---
name: ss6-data-migration
description: Repeatable workflow for the dynamicagency.com Silverstripe 6 upgrade data migration. Use this skill when the user wants to "run the SS6 migration", "sync and migrate for SS6", "test the linkfield migration", or rebuild the local site to match production after the SS6 upgrade. Sequence is — ddev-sync, dev/build, run each migration task, verify the front end against production.
---

# SS6 Data Migration (dynamicagency.com)

## Overview

After the Silverstripe 5 → 6 upgrade (installer PR #284), a synced production
database still holds data in pre-SS6 formats. This skill is the **repeatable**
procedure to take a fresh production sync, run every required SS6 data-migration
task against it, and confirm the local site matches production.

Run this whenever you re-sync the database while PR #284 is in progress, and as
the rehearsal for the eventual UAT / production deploy.

## Deployment model

Production is the single source of truth for content (see the `ddev-sync`
skill). Data only ever flows **down** — production → local. These migration
tasks transform that down-synced data into SS6 format locally; the same tasks
are run on UAT and production during the actual deploy.

## Prerequisites

- On branch `feature/ss6-upgrade` (the SS6 code).
- DDEV running, SSH keys available for the production sync.

## Workflow

### 1. Sync the production database

Run the **`ddev-sync`** skill (`/silverstripe-skills:ddev-sync`). It starts
DDEV, `composer install`s, authorises SSH, and pulls the production DB + assets
via `sync.sh`.

> **Why a fresh sync every time:** the linkfield migration task is a strict
> one-shot — it refuses to run if any linkfield `Link` already exists and it
> **drops** the old tables on success. To re-test it you must start from a
> clean, un-migrated database. Always `ddev-sync` before re-running.

### 2. Build the SS6 schema

```bash
ddev sake dev/build flush=1
```

This adapts the down-synced SS5 schema to SS6 and creates the empty
`LinkField_*` tables the migration writes into.

### 3. Run the migration tasks

Run **each** task below in order. Each is idempotent only in the sense that it
self-guards — see the per-task notes.

#### 3a. Linkable → LinkField

```bash
ddev sake tasks:linkable-to-linkfield-migration-task
```

Migrates every `sheadawson/linkable` link (`LinkableLink` table) to
`silverstripe/linkfield` (`LinkField_*` tables).

- **Task:** `Dynamic\Agency\Task\LinkableMigrationTask`
  (`app/src/Task/LinkableMigrationTask.php`) — a port of linkfield's own
  official `LinkableMigrationTask` (shipped in linkfield 4.x, dropped in 5.x;
  the supporting traits remain in 5.2.0).
- **Config:** `app/_config/linkfield-migration.yml` — enables the task and
  declares the `StaffMember.ContactLinks` many_many relation so it migrates to
  a `has_many` (SortOrder → the Link `Sort` column).
- **What it does:** inserts links into `LinkField_Link` + subclass tables
  preserving IDs, splits `Anchor` → `Anchor` + `QueryString`, sets the
  polymorphic `Owner` for has_one relations, migrates the `ContactLinks`
  many_many, carries Linkable's `Template` button-style into the `Style` field
  (`Dynamic\Agency\Extension\LinkStyleExtension`), then **drops** the
  `LinkableLink` and `AgencyStaffMember_ContactLinks` tables and publishes all
  links.
- **One-shot:** throws if any `LinkField_Link` row already exists. Re-running
  requires a fresh `ddev-sync` (step 1).
- **Expected result:** `23 links migrated, 0 broken links` (counts will track
  production content over time).

> Add a new `### 3x` subsection here for any future SS6 migration task, so this
> skill always lists one step per migration required.

### 4. Verify against production

```bash
ddev sake dev/build flush=1
```

Then compare the local site to production. Sweep representative pages of every
type and confirm HTTP 200 with no PHP errors:

```bash
for u in / /about/about-us /web-content-media-dynamic \
         /work/cedar-crest-ice-cream /community/giving-back \
         /about/careers/open-positions /about/news; do
  l=$(curl -s -o /dev/null -w "%{http_code}" -L "https://dynamicagency.ddev.site$u")
  p=$(curl -s -o /dev/null -w "%{http_code}" -L "https://www.dynamicagency.com$u")
  printf "L:%s P:%s  %s\n" "$l" "$p" "$u"
done
```

Then browser-check the link-bearing pages side by side with
https://www.dynamicagency.com — confirm call-to-action buttons render with
their styles (e.g. `/about/about-us` Single Feature link shows
`class="btn btn-primary btn-gradient"`), navigation works, and elemental blocks
(Promos, Features, Single Feature) render their links.

When the local site looks like production with no regressions, the SS6 upgrade
is verified against current content and PR #284 can be considered for merge.

## Quality gates (optional but recommended)

```bash
ddev exec vendor/bin/phpunit
ddev exec vendor/bin/phpcs
ddev exec vendor/bin/phpstan analyse
```

> Note: `dev/build` runs `silverleague/ideannotator`, which rewrites `@method`
> docblocks to short form and can re-break PHPStan's FQN resolution. Commit
> code before running `dev/build`, or `git checkout` the regenerated docblock
> churn afterward. Tracked as installer issue #299.

### GitHub Actions CI

For projects using CI, add `.github/workflows/ci.yml` to run these quality gates automatically on every PR. See [silverstripe-version-upgrade](../silverstripe-version-upgrade/references/code-quality.md) for an example workflow configuration.

## Troubleshooting

| Symptom | Cause / Fix |
|---|---|
| `Cannot perform migration with existing silverstripe/linkfield link records` | The task already ran on this DB. Re-`ddev-sync` for a clean state. |
| `Couldn't find join table for many_many relation` | The `AgencyStaffMember_ContactLinks` table is missing — DB wasn't freshly synced, or `dev/build` not run. |
| Links render but unstyled (`class=""`) | `Template` → `Style` mapping missing, or `dev/build` not run after editing `linkfield-migration.yml`. |
| Task not listed by `ddev sake tasks:` | `is_enabled: true` missing in `linkfield-migration.yml`, or cache not flushed — run `dev/build flush=1`. |

## Cleanup (post-deploy)

Once the migration has run on **every** environment (local, UAT, production),
delete the now-spent migration code:

- `app/src/Task/LinkableMigrationTask.php`
- `app/_config/linkfield-migration.yml`

The `LinkStyleExtension` and the linkfield app-code changes are permanent — keep
those.
