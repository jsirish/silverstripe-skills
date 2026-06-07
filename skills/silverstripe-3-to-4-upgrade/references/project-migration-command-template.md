# Project Migration Command Template

A copy-paste starter for a project-specific migration runbook. Two completed SS3→SS4
projects (`rockline-iatric`, `safeharbor`) independently converged on the same
structure, so it's been distilled here as a reusable skeleton.

## Where it lives

Put the project runbook at **`.claude/commands/migrate.md`** in the project repo. This
makes it a Claude Code slash command: typing `/migrate` loads the full runbook with
context, which is more discoverable and better documented than a plain
`.agent/workflows/*.md` workflow file. (A `// turbo-all` workflow file still works for
unattended runs, but gives the developer far less guidance — prefer the slash command as
the canonical, human-facing runbook and keep the workflow file, if any, as a thin
auto-runner.)

## Conventions this template assumes

- **`devbuild.sh` = cache rebuild only** (3 lines: clear `silverstripe-cache`, recreate
  it, `dev/build`). DeployHQ runs this after a code deploy — it must **never** run
  migration tasks. See [Phase 8](../SKILL.md#phase-8-build--verify).
- **`migrate.sh` = the task runner** — the ordered data-migration tasks, invoked manually
  (or by this `/migrate` command) after a fresh prod sync. Keeping the two separate avoids
  a double `dev/build` when a full-loop script calls both, and keeps "rebuild cache" from
  being conflated with "migrate data."

---

## Template — copy into `.claude/commands/migrate.md`

````markdown
---
description: Run the full SS3→SS4 data migration for <PROJECT> after a fresh prod sync
---

# /migrate — <PROJECT> data migration

Run after a fresh production database sync to bring synced SS3 data up to the SS4 schema.
Idempotent: safe to re-run on every sync.

## When to use

- After `./sync.sh` pulls a fresh prod DB + assets
- After a crashed `dev/build` left the schema half-migrated
- NOT during a routine code deploy — that only needs `devbuild.sh` (cache rebuild)

## Environments

| Role | Host / source | Notes |
|------|---------------|-------|
| Prod sync source | `<prod-ssh-or-host>` | Read-only; `sync.sh` pulls DB + `assets/` from here |
| Local dev | `https://<project>.ddev.site` | Where migration tasks run |
| Pre-prod deploy target | `<staging-host>` | `deploy.sh` pushes the migrated state here |

`.env` keeps the two separate — `SYNC_*` vars point at prod (source), `DEPLOY_*` vars at
pre-prod (target). Never let a deploy var resolve to the prod host.

## SS3→SS4 breaking changes handled here

| Change | Handler | Notes |
|--------|---------|-------|
| Un-namespaced `ClassName` values | `DatabaseAdmin.classname_value_remapping` (config, runs in `dev/build`) | NOT a task — see SKILL Phase 4 |
| `EditableFormField.ParentClass` | `dev/tasks/form-parent-migration` | Fixes `ParentClass` only |
| Files → hash storage | `dev/tasks/MigrateFileTask` | |
| Blocks → Elemental | `dev/tasks/block-migration` | Reads `_obsolete_*` tables via raw SQL |
| `GlobalSiteSetting` → `SiteConfig` | `dev/tasks/<GlobalSiteSettingMigrationTask>` | Scalars + UtilityLinks + footer nav |
| <add project-specific rows> | | |

## Workflow

```bash
# 1. Fresh prod data
./sync.sh

# 2. Drop SS3 _Versions / __TEMP__ collisions (see SKILL Phase 5)
#    (bulk DROP snippet — references/db-rebuild-conflicts.md)

# 3. Rebuild cache + schema (cache-only script; applies classname_value_remapping)
ddev exec ./devbuild.sh

# 4. Run migration tasks in order
ddev exec ./migrate.sh
#   form-parent-migration
#   MigrateFileTask
#   block-migration
#   <GlobalSiteSettingMigrationTask>
#   <project-specific tasks…>

# 5. Publish (only after ClassName + ParentClass are fixed — else publishRecursive fatals)
```

## Expected outputs

Record the confirmation string / row count each task prints, so a bad run is obvious:

- `form-parent-migration` → `Fixed N EditableFormField ParentClass rows`
- `block-migration` → `Migrated N blocks → M elements`
- `<GlobalSiteSettingMigrationTask>` → `SiteConfig updated; linked N utility links, M nav columns`
- <add expected counts for project-specific tasks>

## Verification

- [ ] Frontend QA of each page type (HomePage, standard, blog, custom) — no blank pages
- [ ] CMS tree loads; Elemental editor renders; Settings/SiteConfig section works
- [ ] `ElementalArea_Live` populated (elements visible on the frontend, not just draft)
- [ ] Visual regression vs the `-legacy` instance (see visual-regression-upgrade skill)
- [ ] <project-specific checks: utility links, footer nav, custom features>

## Known gotchas (this project)

- <e.g. SiteTreeID rows deleted on prod — UtilityLinks task must skip them>
- <e.g. OrbStack stale ports after ddev restart — re-read `ddev describe`>
- <add as discovered>
````

---

## Notes on filling it in

- **Enumerate `classname_value_remapping` from the DB** before writing the breaking-changes
  table — `SELECT DISTINCT ClassName FROM SiteTree`, `EditableFormField`, etc. (SKILL Phase 4).
- **Expected outputs are the cheapest safety net you have.** A task that "ran fine" but
  migrated 0 rows is the most common silent failure on a re-sync; a recorded expected count
  turns it into an obvious mismatch.
- Keep the **gotchas** section a living log — every surprise that cost debugging time on
  this project belongs here so the next sync doesn't re-discover it.
