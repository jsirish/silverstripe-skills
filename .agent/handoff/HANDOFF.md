# Silverstripe Skills Project Handoff

**Last Updated:** 2026-06-01

---

## Skills & Workflows

| Skill | Path | Status |
|-------|------|--------|
| SS3→SS4 Upgrade | `skills/silverstripe-3-to-4-upgrade/SKILL.md` | Active |
| SS4→SS5 Upgrade | `skills/silverstripe-version-upgrade/SKILL.md` | Active |
| SS6 Data Migration | `skills/ss6-data-migration/SKILL.md` | Active |
| Visual Regression | `skills/visual-regression-upgrade/SKILL.md` | Active |
| Block→Element Migration | `skills/block-to-element-migration/SKILL.md` | Active |

All upgrade skills now link to the VR skill for post-migration visual verification. SS3→SS4 and SS4→SS5 both document the `~/Sites/{project}-legacy` naming convention for side-by-side legacy instances.

## Resolved Issues

| Issue | Resolution | Date |
|-------|-----------|------|
| #15 — Legacy instance pattern + VR integration | example-custom canon, naming convention, VR steps in all 3 upgrade skills | 2026-06-01 |
| #14 — Code quality, CI, rector docs | Dynamic require-dev block, CI workflows, lint/typecheck phases | 2026-05-31 |
| #13 — Namespace/manifest gotchas | SS3→SS4 class resolution, manifest `_config.php` migration, extension syntax | 2026-05-31 |

## Pending Items

| Item | Priority | Tracking |
|------|----------|----------|
| SS5→SS6 upgrade skill | Low | No issue yet |

## Key Files & Resources

| Resource | Path |
|----------|------|
| SS3→SS4 skill | `skills/silverstripe-3-to-4-upgrade/SKILL.md` |
| SS4→SS5 skill | `skills/silverstripe-version-upgrade/SKILL.md` |
| Code quality ref | `skills/silverstripe-version-upgrade/references/code-quality.md` |
| SS6 migration | `skills/ss6-data-migration/SKILL.md` |
| VR skill | `skills/visual-regression-upgrade/SKILL.md` |
| Block→Element | `skills/block-to-element-migration/SKILL.md` |
| pr-agent config | `.pr_agent.toml` |

## Recent Session Logs
1. [example-custom VR integration](handoffs/handoff-2026-06-01-2102.md) — 2026-06-01
