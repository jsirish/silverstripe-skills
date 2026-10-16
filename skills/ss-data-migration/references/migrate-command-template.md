# `/migrate` command starter

Minimal `.claude/commands/migrate.md` for a project. Fill the task list from the version section of [SKILL.md](../SKILL.md) (SS5: [ss5-task-sequence.md](ss5-task-sequence.md); SS6: discover with `ddev sake tasks`).

````markdown
---
description: Run the full data migration for <PROJECT> after a fresh prod sync
---

# /migrate — <PROJECT> data migration

Run after a fresh production database sync. Idempotent: safe to re-run on every sync.

## When to use
- After a prod DB + assets sync; NOT during a routine code deploy (that only needs `devbuild.sh`; keep tasks out of it).

## Environments
| Role | Host / source | Notes |
|------|---------------|-------|
| Prod sync source | <host> | Read-only; `sync.sh` pulls DB + assets |
| Local dev | https://<project>.ddev.site | Where tasks run |
| Deploy target | <staging-host> | `deploy.sh` pushes migrated state |

## Task sequence
```bash
ddev exec ./devbuild.sh            # cache rebuild only
ddev sake dev/tasks/block-migration "flush=1"
ddev sake dev/tasks/migrate-footer-links "flush=1"
ddev sake dev/tasks/NavigationMigrationTask "flush=1"
# …project-specific tasks…
```

## Expected outputs
- <task> → <confirmation string / row count>

## Verification
- [ ] Frontend QA of each page type — no blank pages
- [ ] ElementalArea_Live populated; CMS Settings/SiteConfig section works
- [ ] <project-specific checks: footer nav, slide-image links, etc.>
````
