# skills — Agent Skills Repos Handoff

**Last Updated:** 2026-06-07 CDT

---

## Architecture & Services

Two skill repos under `/Users/jsirish/AI/skills/`:

| Repo | Remote | Purpose |
|------|--------|---------|
| `silverstripe-skills/` | `github.com/jsirish/silverstripe-skills` | SS CMS dev skills (upgrades, Essentials, DDEV, deployment) |
| `workflow-skills/` | `github.com/jsirish/workflow-skills` | Cross-project lifecycle skills (onboard, handoff, pr-review, merge-pr, ss-branch-strategy, ddev-legacy-php) |

All repos use the `skills.sh` / `npx skills` installable format: `package.json`, `skills.sh.json`, per-skill `skills/<slug>/{SKILL.md,metadata.json}`.

## Skills & Workflows

**Install format:** `npx skills add jsirish/<repo> --skill '*' -a claude-code -g`

**Update installed:** `npx skills update`

**Installed locations:**
- Claude Code: `~/.claude/skills/` (real dirs, npx-managed)
- Registry/lock: `~/.agents/.skill-lock.json`

**silverstripe-skills groupings:** Version Upgrades · Essentials Stack · Dev Environment & Deployment (15 skills). No open issues as of 2026-06-07. All Safe Harbor SS3→SS4 operational knowledge captured (issues #30–#37, PRs #39–#43).

**workflow-skills groupings:** Session Lifecycle (onboard, handoff) · Pull Requests (pr-review, merge-pr) · Dev Patterns (ss-branch-strategy, ddev-legacy-php). No open issues as of 2026-06-07.

## Public Repo Policy (codified 2026-06-02)

Both repos are **PUBLIC**. `.agent/` is gitignored in both. Client identifiers in skill docs use anonymized project names (e.g. "Safe Harbor"). **History not purged** — residual client names remain in past commits; `git filter-repo` + force-push needed if that becomes a concern.

## Pending Items

| Item | Priority | Notes |
|------|----------|-------|
| History purge `silverstripe-skills` | Low | Client names remain in past public commits. Needs `git filter-repo` + force-push + GitHub cache purge. Destructive — requires explicit approval. |
| Stale remote branches on `silverstripe-skills` | Low | `feature/ss3-ss4-block-migration-patterns` and `docs/vr-same-machine-22` — check if safe to delete. |

## Key Files & Resources

| Resource | Path |
|----------|------|
| silverstripe-skills repo | `/Users/jsirish/AI/skills/silverstripe-skills/` |
| workflow-skills repo | `/Users/jsirish/AI/skills/workflow-skills/` |
| npx skills registry | `~/.agents/.skill-lock.json` |
| Claude Code skills dir | `~/.claude/skills/` |

## Recent Session Logs

1. [Resolve all open issues — #30–#37 (7 PRs, 0 issues remaining)](./../handoffs/handoff-2026-06-07-1830.md) — 2026-06-07
2. [#17 resolved — upgrade skill cross-ref added (PR #29)](./../handoffs/handoff-2026-06-03-0125.md) — 2026-06-03
3. [VR skill — same-machine legacy-vs-upgrade mode + threshold split (#22, PR #28)](./../handoffs/handoff-2026-06-03-0114.md) — 2026-06-03
4. [ddev-sync #21 — prod-vs-preprod pre-flight + migration handoff (PR #27)](./../handoffs/handoff-2026-06-03-0050.md) — 2026-06-03
5. [Fix #18 + #19: data-integrity backports from iatric-mfg (2 PRs)](./../handoffs/handoff-2026-06-03-0535.md) — 2026-06-03
