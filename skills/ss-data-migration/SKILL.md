---
name: ss-data-migration
description: "Run Silverstripe data-migration tasks after an SS4→SS5 or SS5→SS6 upgrade: sync production, dev/build, run each task with row-count checks. Use for \"SS5 data migration\", \"migrate data\", \"rebuild a local site to match production\", \"after a database sync or deployment\", \"test the linkfield migration\"."
---

# Silverstripe Data Migration (SS5 and SS6)

## Overview

After a major-version upgrade, a synced production database still holds data in the old formats. This skill is the **repeatable** procedure to take a fresh production sync, run every required data-migration task against it in order, and confirm the local site matches production. Run it whenever you re-sync the database while the upgrade PR is in progress, and as the rehearsal for the UAT / production deploy.

Version-specific parts: [SS4 → SS5](#ss4--ss5) and [SS5 → SS6](#ss5--ss6). Everything outside those two sections applies to both.

Throughout, `{project}` is the DDEV project name (local site at `https://{project}.ddev.site`) and `{production-domain}` is the live site.

## Deployment model

Production is the single source of truth for content (see the `ddev-sync` skill). Data only flows **down**, production to local. These tasks transform the down-synced data locally; the same tasks run on UAT and production during the real deploy.

## Prerequisites

- On the upgrade branch, DDEV running, SSH keys available for the production sync.
- SS4 → SS5: the codebase is fully deployed, `dev/build` has completed successfully, and (locally) you have synced the latest production database (e.g. `ddev sync`). The SS5 → SS6 workflow below does the sync and `dev/build` itself in steps 1-2.

## Project-specific `/migrate` command

Exact tasks vary per project (custom element types, legacy structures, data schema). Formalize them as a project runbook at **`.claude/commands/migrate.md`** so typing `/migrate` loads the runbook with context. Prefer this over a plain `.agent/workflows/data-migration.md` file (which works for unattended `// turbo-all` runs but gives the developer far less guidance); keep a thin workflow file only if you also want a one-keystroke auto-runner.

> [!NOTE]
> **`devbuild.sh` = cache rebuild only; `migrate.sh` = the task runner.** Migration tasks belong in `migrate.sh` (or `/migrate`), invoked manually after a fresh prod sync, never in the `devbuild.sh` DeployHQ runs on every deploy. See [devbuild-vs-migrate.md](../silverstripe-version-upgrade/references/devbuild-vs-migrate.md).

Starter template: [references/migrate-command-template.md](references/migrate-command-template.md). The SS3 → SS4 skill ships a richer copy-paste template (breaking-changes table) at [project-migration-command-template.md](../silverstripe-3-to-4-upgrade/references/project-migration-command-template.md); the same structure applies here minus the SS3 → SS4 ClassName-remapping rows.

## Do not rationalize

A step is done when you have pasted the task output and the verification query results, not a recollection of a previous run.

| Rationalization | Required behavior |
|-----------------|-------------------|
| "No errors printed, so it worked" | Paste the task's migrated/skipped/broken counts and the row-count queries below. Silence is not success; many tasks fail quietly on empty source tables. |
| "Row count looks close enough" | Counts match exactly or every missing record is named (skipped class, intentional exclusion). If the task drops the source table on success, capture the source count **before** running. |
| "The page renders, so live data is fine" | Query `_Live` and `_Versions` directly. Draft-only rows render in CMS preview and silently vanish on publish. |
| "It ran on the last sync, so it will run on this one" | Re-run and paste this run's output. Fresh syncs reset source data; a one-shot task proves nothing about this database. |
| "The homepage loads, so the migration is verified" | Run the full page sweep (both versions: see Verify against production below; SS5: the same sweep, see the SS4 → SS5 section) and paste the local/production status table. |

## Per-task verification (required)

After **each** task, paste evidence scoped to what that task writes:

- **Tasks that write versioned rows** (block migration, link migration, element content): count the task's target tables, draft vs live vs versions:

```sql
-- Substitute the task's own target tables
SELECT COUNT(*) FROM Element;
SELECT COUNT(*) FROM Element_Live;
SELECT COUNT(*) FROM Element_Versions;
```

- **Tasks that write unversioned config or relations** (footer links, navigation settings, link fixes): count the target table before and after, and paste the task's own changed/skipped summary.
- **Asset tasks with no row-count target** (gallery resample): paste the task output showing files processed, and spot-check one resampled variant on the frontend.

For versioned targets, draft and `_Live` counts match for published content and `_Versions` is at least the draft count. Record the counts in the project's `/migrate` runbook under "Expected outputs" as a baseline for the next run. An automated run owes the same evidence: capture each task's output and the queries in the run log; exit code 0 alone does not clear the gate. Run tasks one at a time and confirm each exits successfully before starting the next.

## SS4 → SS5

The Dynamic Agency Base Site task order is a baseline; your project's list will differ. Run in sequence to guarantee data integrity, each with `flush=1` (clears cached config and class manifests between tasks):

1. Block migration, 2. Footer links, 3. Navigation settings, 4. Slide-image Linkable → LinkField, 5. Link fixes, 6. HomePage ElementalArea owner repair, 7. Gallery asset resampling (pre-computes manipulation variants so the first remote page load does not time out).

Commands and what each task does: [references/ss5-task-sequence.md](references/ss5-task-sequence.md) (a worked example from the Base Site architecture; treat the task names as examples).

**Deployment.** A local `rsync` push of migrated state is often more reliable than asking the remote to pull and compile: rsync `public/assets/` (carries the locally generated manipulation variants) and use a `.env`-backed `deploy.sh` to dump the migrated DB and sync assets, pushing the exact tested state.

**Page sweep.** After the last task, run the same local-vs-production status sweep as in Verify against production below (one URL per page type, expect HTTP 200 on both) and browser-check the footer, navigation and slide-image links.

**Heuristic link verification.** After migrating structural links (obsolete `BlockLinkID` / flat `PageLinkID` columns → `LinkField` records), check site-specific hardcoded logic: custom templates or features that bypass standard Elemental structures (a slide-image header, a "Learn More" link) may lose their connection. Build small post-migration tasks to map known edge-case links.

## SS5 → SS6

Two SS6-specific rules apply to any migration task you write.

### Query both Versioned stages

Tasks over versioned DataObjects must query **both** `Versioned::DRAFT` and `Versioned::LIVE`. A draft-only query silently misses records that exist only on the live stage (e.g. content published and then deleted from draft):

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

`publishRecursive()` is the default idiom (consistent with [data-migration-tasks.md](../silverstripe-version-upgrade/references/data-migration-tasks.md)): it publishes the record plus everything it owns via `$owns`, which is what you want when owned records (links, images) migrate together.

Use `copyVersionToStage()` to publish **exactly one record without cascading**, e.g. its owned records are not migrated yet, or elemental ownership would cascade-publish drafts you did not intend to touch:

```php
$record->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
```

## Workflow

### 1. Sync the production database

Run the **`ddev-sync`** skill (`/workflow-skills:ddev-sync`): starts DDEV, `composer install`s, authorises SSH, pulls the production DB + assets via `sync.sh`.

> **Why a fresh sync every time:** one-shot tasks (like the linkfield migration below) refuse to run if target records already exist and may **drop** source tables on success. To re-test them, start from a clean, un-migrated database.

### 2. Build the SS6 schema

```bash
ddev sake dev/build flush=1
```

Adapts the down-synced SS5 schema to SS6 and creates the empty target tables the tasks write into (e.g. `LinkField_*`).

### 3. Run the migration tasks

Discover the project's tasks rather than relying on a memorized list:

```bash
ddev sake tasks | grep -i 'migrat\|conversion\|convert'
```

Any BuildTask subclass in `app/src/Task/` whose name contains `Migration` or `Conversion` belongs in this run. Run **each** in order; each self-guards. Every task gets its own `##### 3x` subsection with a **Verification (required)** block.

#### 3a. Linkable to LinkField

The most common SS6 migration: every `sheadawson/linkable` link (`LinkableLink` table) moves to `silverstripe/linkfield` (`LinkField_*` tables).

```bash
ddev sake tasks:linkable-to-linkfield-migration-task
```

- **Task:** a project-local port of linkfield's official `LinkableMigrationTask` (shipped in linkfield 4.x, dropped in 5.x; the supporting traits remain in 5.2.0). Lives in `app/src/Task/`.
- **Config:** an `app/_config/` YAML file that enables the task and declares any `many_many` link relations so they migrate to `has_many` (the relation's `SortOrder` maps to the Link `Sort` column).
- **What it does:** inserts links into `LinkField_Link` + subclass tables preserving IDs, splits `Anchor` into `Anchor` + `QueryString`, sets the polymorphic `Owner` for has_one relations, migrates declared many_many relations, then **drops** the `LinkableLink` table (and migrated join tables) and publishes all links.
- **One-shot:** throws if any `LinkField_Link` row already exists. Re-running requires a fresh `ddev-sync`.
- **Expected result:** `N links migrated, 0 broken links`, where N tracks production content.

**Verification (required):** capture the source count before the task runs (it drops `LinkableLink` on success), then compare after:

```bash
# BEFORE the task
ddev mysql -e "SELECT COUNT(*) FROM LinkableLink;"

# AFTER the task: draft, live, versions
ddev mysql -e "SELECT COUNT(*) FROM LinkField_Link;
               SELECT COUNT(*) FROM LinkField_Link_Live;
               SELECT COUNT(*) FROM LinkField_Link_Versions;"
```

Done when source count = draft count = live count, `_Versions` >= draft count, and the task reported `0 broken links`. Any delta gets explained link by link.

> Add a new `##### 3x` subsection for any future SS6 migration task, each with its own before/after row-count **Verification (required)** block matching the 3a pattern.

## Verify against production

```bash
ddev sake dev/build flush=1
```

Sweep one representative page of every page type (and every link-bearing element type); confirm HTTP 200 with no PHP errors:

```bash
for u in / /page-type-one /page-type-two /page-type-three; do
  l=$(curl -s -o /dev/null -w "%{http_code}" -L "https://{project}.ddev.site$u")
  p=$(curl -s -o /dev/null -w "%{http_code}" -L "https://{production-domain}$u")
  printf "L:%s P:%s  %s\n" "$l" "$p" "$u"
done
```

Then browser-check link-bearing pages side by side with production: call-to-action buttons keep their styles, navigation works, elemental blocks render their links.

## Visual regression (optional)

For automated pixel diffs between production and the upgrade, use the `visual-regression-upgrade` skill (from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills), installed separately):

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

When the local site looks like production with no regressions, the upgrade is verified against current content and the PR can be considered for merge.

## Quality gates (optional but recommended)

```bash
ddev exec vendor/bin/phpunit
ddev exec vendor/bin/phpcs
ddev exec vendor/bin/phpstan analyse
```

> `dev/build` runs `silverleague/ideannotator` (if installed), which rewrites `@method` docblocks to short form and can re-break PHPStan's FQN resolution. Commit code before `dev/build`, or `git checkout` the regenerated docblock churn afterward.

For CI, add `.github/workflows/ci.yml` to run these gates on every PR; an example is in [code-quality.md](../silverstripe-version-upgrade/references/code-quality.md).

## Troubleshooting

| Symptom | Cause / Fix |
|---|---|
| Task fails with a memory or timeout error | Raise PHP limits, or run the task via the browser interface instead of the CLI. |
| Task not listed by `ddev sake tasks` | SS6: missing `is_enabled: true` in the migration YAML, or cache not flushed (run `dev/build flush=1`). SS5: append `flush=1` to each task command. |
| `Cannot perform migration with existing silverstripe/linkfield link records` (SS6) | The task already ran on this DB. Re-`ddev-sync` for a clean state. |
| `Couldn't find join table for many_many relation` (SS6) | A declared many_many join table is missing: DB not freshly synced, or `dev/build` not run. |
| Links render but unstyled (`class=""`) (SS6) | Button-style mapping missing, or `dev/build` not run after editing the migration YAML. |
| Live-only records missing after migration | The task queried only the default (draft) stage. Query both `Versioned::DRAFT` and `Versioned::LIVE`. |

## Cleanup (post-deploy)

Once the migration has run on **every** environment (local, UAT, production), delete the spent migration code: the task class in `app/src/Task/` and its `app/_config/` YAML. Keep permanent extensions the migration introduced (e.g. a link-style extension the front end now depends on).

## Worked example

A concrete SS5 → SS6 run (branch, task, config, expected counts, page sweep, cleanup targets) from dynamicagency.com: [references/worked-example-dynamicagency-ss6.md](references/worked-example-dynamicagency-ss6.md). It is a reference implementation, not a template to copy verbatim.
