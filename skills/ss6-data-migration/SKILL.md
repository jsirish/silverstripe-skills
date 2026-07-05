---
name: ss6-data-migration
description: Repeatable workflow for Silverstripe 6 upgrade data migrations. Use this skill when the user wants to "run the SS6 migration", "sync and migrate for SS6", "test the linkfield migration", or rebuild a local site to match production after an SS6 upgrade. Sequence is - sync the production database, dev/build, run each migration task with row-count verification, verify the front end against production.
---

# SS6 Data Migration

## Overview

After a Silverstripe 5 to 6 upgrade, a synced production database still holds
data in pre-SS6 formats. This skill is the **repeatable** procedure to take a
fresh production sync, run every required SS6 data-migration task against it,
and confirm the local site matches production.

Run this whenever you re-sync the database while the upgrade PR is in progress,
and as the rehearsal for the eventual UAT / production deploy.

Throughout this skill, `{project}` is the DDEV project name (local site at
`https://{project}.ddev.site`) and `{production-domain}` is the live site.

## Deployment model

Production is the single source of truth for content (see the `ddev-sync`
skill). Data only ever flows **down**, production to local. These migration
tasks transform that down-synced data into SS6 format locally; the same tasks
are run on UAT and production during the actual deploy.

## Prerequisites

- On the SS6 upgrade branch (e.g. `feature/ss6-upgrade`).
- DDEV running, SSH keys available for the production sync.

## Do not rationalize

Each step's gate is pasted command output, not a recollection of a previous run.

| Rationalization | Required behavior |
|-----------------|-------------------|
| "No errors printed, so the migration worked" | Paste the task's migrated/broken counts and the verification queries in step 3a. |
| "Row count looks close enough" | Source and target counts must match exactly, or each missing record is identified. If the task drops the source table on success, capture the source count **before** running. |
| "It worked on the last sync" | A one-shot task runs once per fresh sync. Paste this run's output; the previous run proves nothing about this database. |
| "The homepage loads, so the migration is verified" | Run the full page sweep in step 4 and paste the local/production status table. |

## Writing SS6 migration tasks

Two SS6-specific rules apply to any migration task you write for this workflow:

### Query both Versioned stages

Migration tasks over versioned DataObjects must query **both**
`Versioned::DRAFT` and `Versioned::LIVE`, not just the default draft stage. A
draft-only query silently misses records that exist only on the live stage
(e.g. content published and then deleted from draft):

```php
use SilverStripe\Versioned\Versioned;

foreach ([Versioned::DRAFT, Versioned::LIVE] as $stage) {
    Versioned::withVersionedMode(function () use ($stage) {
        Versioned::set_stage($stage);
        // ... query and migrate records on this stage
    });
}
```

### Publishing migrated records: publishRecursive vs copyVersionToStage

`publishRecursive()` is the default idiom for publishing migrated records
(consistent with
[silverstripe-version-upgrade/references/data-migration-tasks.md](../silverstripe-version-upgrade/references/data-migration-tasks.md)):
it publishes the record plus everything it owns via `$owns`, which is what you
want when the owned records (links, images) are migrated together.

Reach for `copyVersionToStage()` when you need to publish **exactly one record
without cascading**, e.g. its owned records are not migrated yet, or elemental
ownership would cascade-publish drafts you did not intend to touch:

```php
use SilverStripe\Versioned\Versioned;

$record->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
```

## Workflow

### 1. Sync the production database

Run the **`ddev-sync`** skill (`/workflow-skills:ddev-sync`). It starts
DDEV, `composer install`s, authorises SSH, and pulls the production DB + assets
via `sync.sh`.

> **Why a fresh sync every time:** one-shot migration tasks (like the linkfield
> migration below) refuse to run if target records already exist and may
> **drop** source tables on success. To re-test them you must start from a
> clean, un-migrated database. Always `ddev-sync` before re-running.

### 2. Build the SS6 schema

```bash
ddev sake dev/build flush=1
```

This adapts the down-synced SS5 schema to SS6 and creates the empty target
tables the migration tasks write into (e.g. `LinkField_*`).

### 3. Run the migration tasks

Discover the project's migration tasks rather than relying on a memorized list.
List all registered tasks and pick out the migration/conversion ones:

```bash
ddev sake tasks | grep -i 'migrat\|conversion\|convert'
```

Any BuildTask subclass in `app/src/Task/` whose name contains `Migration` or
`Conversion` belongs in this run. Run **each** in order; each self-guards, but
see the per-task notes. Every task gets its own `#### 3x` subsection with a
**Verification (required)** block (see the note after 3a).

#### 3a. Linkable to LinkField

The most common SS6 migration: every `sheadawson/linkable` link (`LinkableLink`
table) moves to `silverstripe/linkfield` (`LinkField_*` tables).

```bash
ddev sake tasks:linkable-to-linkfield-migration-task
```

- **Task:** a project-local port of linkfield's own official
  `LinkableMigrationTask` (shipped in linkfield 4.x, dropped in 5.x; the
  supporting traits remain in 5.2.0). Lives in `app/src/Task/`.
- **Config:** an `app/_config/` YAML file that enables the task and declares
  any `many_many` link relations so they migrate to `has_many` (the relation's
  `SortOrder` maps to the Link `Sort` column).
- **What it does:** inserts links into `LinkField_Link` + subclass tables
  preserving IDs, splits `Anchor` into `Anchor` + `QueryString`, sets the
  polymorphic `Owner` for has_one relations, migrates declared many_many
  relations, then **drops** the `LinkableLink` table (and any migrated
  many_many join tables) and publishes all links.
- **One-shot:** throws if any `LinkField_Link` row already exists. Re-running
  requires a fresh `ddev-sync` (step 1).
- **Expected result:** `N links migrated, 0 broken links`, where N tracks the
  production content over time.

**Verification (required):** capture the source count before the task runs (the
task drops `LinkableLink` on success), then compare after:

```bash
# BEFORE the task
ddev mysql -e "SELECT COUNT(*) FROM LinkableLink;"

# AFTER the task: draft, live, versions
ddev mysql -e "SELECT COUNT(*) FROM LinkField_Link;
               SELECT COUNT(*) FROM LinkField_Link_Live;
               SELECT COUNT(*) FROM LinkField_Link_Versions;"
```

The step is done when the pasted output shows source count = draft count =
live count (the task publishes all links), `_Versions` >= draft count, and the
task reported `0 broken links`. Any delta gets explained link by link before
moving on.

> Add a new `#### 3x` subsection here for any future SS6 migration task, so this
> skill always lists one step per migration required. Every new subsection gets
> its own **Verification (required)** block with before/after row-count queries,
> matching the 3a pattern.

### 4. Verify against production

```bash
ddev sake dev/build flush=1
```

Then compare the local site to production. Sweep representative pages of every
page type (and every link-bearing element type) and confirm HTTP 200 with no
PHP errors:

```bash
# Substitute one representative URL per page type
for u in / /page-type-one /page-type-two /page-type-three; do
  l=$(curl -s -o /dev/null -w "%{http_code}" -L "https://{project}.ddev.site$u")
  p=$(curl -s -o /dev/null -w "%{http_code}" -L "https://{production-domain}$u")
  printf "L:%s P:%s  %s\n" "$l" "$p" "$u"
done
```

Then browser-check the link-bearing pages side by side with production:
confirm call-to-action buttons render with their styles, navigation works, and
elemental blocks render their links.

### Visual regression (optional)

For a more thorough check, use the `visual-regression-upgrade` skill (from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills), installed separately) to capture automated pixel diffs between production and the SS6 upgrade:
```bash
# Path to the installed skill; adjust for your agent's skills dir
VR=~/.claude/skills/visual-regression-upgrade

python "$VR"/scripts/crawl_urls.py \
  --url https://{production-domain} --limit 30 --out paths.txt

python "$VR"/scripts/capture.py \
  --prod https://{production-domain} \
  --local https://{project}.ddev.site \
  --paths-file paths.txt \
  --out ./vr-out

python "$VR"/scripts/diff_report.py \
  --in ./vr-out --out ./vr-out/report
```

When the local site looks like production with no regressions, the SS6 upgrade
is verified against current content and the upgrade PR can be considered for
merge.

## Quality gates (optional but recommended)

```bash
ddev exec vendor/bin/phpunit
ddev exec vendor/bin/phpcs
ddev exec vendor/bin/phpstan analyse
```

> Note: `dev/build` runs `silverleague/ideannotator` (if installed), which
> rewrites `@method` docblocks to short form and can re-break PHPStan's FQN
> resolution. Commit code before running `dev/build`, or `git checkout` the
> regenerated docblock churn afterward.

### GitHub Actions CI

For projects using CI, add `.github/workflows/ci.yml` to run these quality gates automatically on every PR. See [silverstripe-version-upgrade](../silverstripe-version-upgrade/references/code-quality.md) for an example workflow configuration.

## Troubleshooting

| Symptom | Cause / Fix |
|---|---|
| `Cannot perform migration with existing silverstripe/linkfield link records` | The task already ran on this DB. Re-`ddev-sync` for a clean state. |
| `Couldn't find join table for many_many relation` | A declared many_many join table is missing: DB wasn't freshly synced, or `dev/build` not run. |
| Links render but unstyled (`class=""`) | The button-style mapping is missing, or `dev/build` not run after editing the migration YAML. |
| Task not listed by `ddev sake tasks:` | `is_enabled: true` missing in the migration YAML, or cache not flushed: run `dev/build flush=1`. |
| Live-only records missing after migration | The task queried only the default (draft) stage. Query both `Versioned::DRAFT` and `Versioned::LIVE` (see "Writing SS6 migration tasks"). |

## Cleanup (post-deploy)

Once the migration has run on **every** environment (local, UAT, production),
delete the now-spent migration code: the task class in `app/src/Task/` and its
`app/_config/` YAML. Keep any permanent extensions the migration introduced
(e.g. a link-style extension that the front end now depends on).

---

## Worked example: dynamicagency.com SS6 upgrade

The workflow above was extracted from the dynamicagency.com SS5 to SS6 upgrade
(PR #284 in the site's own installer repository; PR/issue numbers below refer
to that repo). The concrete values used there, as a reference implementation:

- **Branch:** `feature/ss6-upgrade`; local site `https://dynamicagency.ddev.site`,
  production `https://www.dynamicagency.com`.
- **Task (3a):** `Dynamic\Agency\Task\LinkableMigrationTask`
  (`app/src/Task/LinkableMigrationTask.php`), run as
  `ddev sake tasks:linkable-to-linkfield-migration-task`.
- **Config:** `app/_config/linkfield-migration.yml` enables the task and
  declares the `StaffMember.ContactLinks` many_many relation so it migrates to
  a `has_many` (SortOrder to the Link `Sort` column). On success the task drops
  `LinkableLink` and `AgencyStaffMember_ContactLinks`.
- **Button styles:** Linkable's `Template` button-style carried into the
  linkfield `Style` field via `Dynamic\Agency\Extension\LinkStyleExtension`
  (permanent, kept after cleanup).
- **Expected result at time of writing:** `23 links migrated, 0 broken links`.
- **Page sweep (step 4):** `/`, `/about/about-us`, `/web-content-media-dynamic`,
  `/work/{case-study}`, `/community/giving-back`,
  `/about/careers/open-positions`, `/about/news`.
- **Spot check:** `/about/about-us` Single Feature link renders with
  `class="btn btn-primary btn-gradient"`.
- **Cleanup targets:** `app/src/Task/LinkableMigrationTask.php` and
  `app/_config/linkfield-migration.yml`; the `LinkStyleExtension` and linkfield
  app-code changes are permanent.
- The ideannotator docblock churn was tracked as issue #299 in the same repo.
