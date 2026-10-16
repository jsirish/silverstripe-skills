---
name: silverstripe-version-upgrade
description: Complete workflow for upgrading Silverstripe CMS projects between major versions on the SS4+ line (e.g. SS4 → SS5, SS5 → SS6). Covers assessment, dependency updates, config migration, data migration tasks, testing, and deployment. For legacy SS3 → SS4 upgrades use the silverstripe-3-to-4-upgrade skill instead — that migration is structurally different.
---

# Silverstripe Version Upgrade Skill

Repeatable workflow for upgrading Silverstripe CMS projects (e.g. SS4 → SS5) in Dynamic Agency's module ecosystem and DDEV-based local development.

> **Scope**: This skill covers **SS4 and later** major-version bumps (SS4 → SS5, SS5 → SS6) — a recurring, recipe- and dependency-driven upgrade. For a **legacy SS3 → SS4** upgrade, use the [silverstripe-3-to-4-upgrade](../silverstripe-3-to-4-upgrade/SKILL.md) skill instead: that is a one-time structural migration (PSR-4 namespacing, `mysite`→`app`, `public/` directory, DB schema preflight, Blocks→Elemental) with little mechanical overlap with this workflow.

## Upgrade Phases (Summary)

| Phase | Key Actions |
|-------|-------------|
| **1. Assessment** | Audit versions, packages, incompatibilities; clone legacy instance for VR baseline |
| **2. Branch & PHP** | Create feature branch, update PHP to `^8.1` |
| **3. Dependencies** | Update recipes with `--no-update`, resolve, `vendor-expose` |
| **4. Config Migration** | Fix extension relocations, ORM relationships, templates |
| **5. Data Migration** | Write idempotent BuildTasks for link/data migrations |
| **6. Build & Verify** | `dev/build`, run tasks, check frontend + CMS |
| **7. Code Quality** | PHPCS, PHPStan, annotation fixes |
| **8. Commit & PR** | Conventional commit, checklist PR body |
| **9. UAT** | Deploy, run tasks, verify, troubleshoot |
| **10. Production** | Merge, deploy, run tasks, verify |

## Do not rationalize

Every phase gate below requires evidence: the actual command output, not a recollection or an inference. "Seems right" is insufficient. The shortcuts agents talk themselves into, and the required counter-behavior:

| Rationalization | Required behavior |
|-----------------|-------------------|
| "The build passed, so the migration ran" | Run each migration task and paste its output, including row counts. `dev/build` succeeding proves nothing about task execution. |
| "The vendor diff looks fine" | Run `ddev composer validate` and `ddev composer show --direct`, paste the output. Eyeballing `composer.lock` is not validation. |
| "Tests probably still pass" | Run the suite (`ddev exec vendor/bin/phpunit`) and paste the summary line. No run, no claim. |
| "The homepage loads, so the site works" | Curl one URL per page type and paste the HTTP codes (see Phase 6 and 10.4). One page proves one template. |
| "dev/build ran without errors, so the schema is correct" | Paste the build output showing the table and field changes you expected. A silent build can mean silently ignored config (see `classname_value_remapping`). |
| "That warning is probably pre-existing" | Grep the pre-upgrade branch for the same warning and paste both results. Confirmed pre-existing or it is yours. |

## Phase 1: Assessment & Discovery

### 1.1 Pre-Flight

```bash
ddev start
git branch -a | grep -i 'ss5\|silverstripe-5\|upgrade'
ddev composer show silverstripe/framework | grep versions
ddev php -v
# SS5 requires PHP 8.1+; SS6 requires PHP 8.3+ — update .ddev/config.yaml if needed
```

### 1.2 Audit Packages

```bash
ddev composer show | grep 'dynamic/\|silverstripe/\|dnadesign/'
```

### 1.3 Incompatible Packages (Common)

| Package | Action |
|---------|--------|
| `lekoala/silverstripe-debugbar` | Remove |
| `ryanpotter/silverstripe-cms-theme` | Remove (SS5 built-in) |
| `fractas/elemental-stylings` | Remove (use native `styles`) |
| `sheadawson/silverstripe-linkable` | Replace with `silverstripe/linkfield` |
| `dynamic/silverstripe-company-config` | Remove (merged into base-site) |
| `dynamic/silverstripe-template-config` | Remove (merged into base-site) |

#### SS6-specific

| Package | Action |
|---------|--------|
| `nathancox/embedfield` | Replace with `fromholdio/silverstripe-embedfield ^5.1` |

> [!TIP]
> If the project uses `sheadawson/silverstripe-blocks`, migrate to Elemental **before** SS5 upgrade using `dynamic/silverstripe-blocks-to-elemental-migrator`.

### 1.4 Legacy instance (for VR baseline)

Clone the current SS4 site as a side-by-side reference before starting the upgrade. This gives you a pixel-perfect local baseline to diff against during VR verification — same approach as the SS3→SS4 upgrade pattern.

**Naming convention:** clone into `~/Sites/{project}-legacy` so the upgrade lives in `~/Sites/{project}`.

```bash
cd ~/Sites
git clone <repo> {project}-legacy
cd {project}-legacy
git checkout main  # or the current production branch
ddev config --project-name {project}-legacy
ddev start
ddev auth ssh
ddev exec ./sync.sh  # sync prod DB and assets
```

This gives you `https://{project}-legacy.ddev.site` — a running SS4 instance against the same data the upgrade will use. In the Build & Verify phase you'll diff this against the SS5 upgrade to confirm visual parity.

## Phase 2–3: Branch & Dependencies

```bash
git checkout -b feature/silverstripe-5-upgrade

# Update PHP requirement in composer.json
# Change "php": "^7.4" to "php": "^8.1" (SS5 requires PHP 8.1+)

# Update core
ddev composer require silverstripe/recipe-cms:^5 dynamic/recipe-silverstripe-base-site:^5 --no-update

# Update elemental packages (versions vary per package — verify on Packagist)
# Add each package individually, e.g.:
ddev composer require dynamic/silverstripe-elemental-accordion:^5.0 --no-update

# Remove incompatible
ddev composer remove lekoala/silverstripe-debugbar --no-update

# Resolve
ddev composer update --with-all-dependencies
ddev composer vendor-expose
```

### SS6 Variant

SS6 changes the branch name, PHP floor (8.3), recipe versions, and swaps several packages (linkfield `^5`, `htmleditor-tinymce`, no Linkable/embedfield). The commands and blockers are in [references/ss6-dependencies.md](references/ss6-dependencies.md). The phase gate below applies to SS6 too.

**Evidence gate (Phase 3):** before moving to config migration, paste the output of:

```bash
ddev composer validate
ddev composer show --direct   # confirm expected major versions resolved
```

A clean `composer update` exit code is not the gate; the resolved version list is.

## Phase 4: Configuration Migration

Key areas:
- **Extension relocations**: `HeaderImageExtension` moved from base-site → site-tools
- **ORM strictness**: SS5 requires explicit `has_one` back-references for `has_many`
- **Template updates**: Sort field `SortOrder` → `Sort`, link `$Link` → `$URL`
- **Annotations**: Use `@property ?string` for nullable fields in PHP 8.1+

> **SS6 note:** BuildTask signature changed (`run($request)` → `execute(InputInterface, PolyOutput): int`). See [references/data-migration-tasks.md](references/data-migration-tasks.md) for the SS6 Symfony Console Command template.

### SS6 Configuration Migration

SS6 has mandatory code and config changes that fail loudly (PHP fatals) or silently (ignored config). Work through [references/ss6-breaking-changes.md](references/ss6-breaking-changes.md): class renames, typed signatures, BuildTask `execute()`, `forTemplate(): string`, `getDescription()` removal, DDEV `SS_DATABASE_SERVER=db`, TinyMCE extraction, Linkable → LinkField template API, silent config breakers (`classname_value_remapping`, password validator, session cookies), and the removed `CMSPageAddController`.

## Phase 5: Data Migration

See [references/data-migration-tasks.md](references/data-migration-tasks.md) for the BuildTask pattern, migration principles, and common scenarios (Linkable → Link, ManyMany → LinkField).

**Evidence gate (Phase 5):** a migration step is done when you can paste, for each task:
1. The task's own output (migrated / skipped / broken counts).
2. A row-count query against the target tables, compared to the source count:

```sql
SELECT COUNT(*) FROM <SourceTable>;                -- before
SELECT COUNT(*) FROM <TargetTable>;                -- after
SELECT COUNT(*) FROM <TargetTable>_Live;           -- if versioned
SELECT COUNT(*) FROM <TargetTable>_Versions;
```

Counts must match, or every row of the delta must be accounted for (skipped classes, intentional exclusions). "The task finished without errors" is not the gate.

## Phase 6: Build & Verify

```bash
ddev sake dev/build "flush=1"
ddev sake dev/tasks/<task-segment>
```

Verify: Homepage, carousel, navigation, footer, CMS admin, elemental blocks.

**Evidence gate (Phase 6):** paste the `dev/build` output (expected table/field changes present, no obsolete-type warnings you cannot explain) and the HTTP status of one URL per page type:

```bash
for u in / /<page-type-urls>; do
  curl -s -o /dev/null -w "%{http_code}  $u\n" "https://{project}.ddev.site$u"
done
```

### Visual regression (optional but recommended)

Diff the SS5 upgrade against the legacy SS4 instance from Phase 1.4:

```bash
# Path to the installed skill; adjust for your agent's skills dir
VR=~/.claude/skills/visual-regression-upgrade

# Crawl the legacy site for a URL list
cd ~/Sites/{project}-legacy
python "$VR"/scripts/crawl_urls.py \
  --url https://{project}-legacy.ddev.site --limit 30 --out paths.txt

# Capture + diff both environments
cd ~/Sites/{project}
python "$VR"/scripts/capture.py \
  --prod https://{project}-legacy.ddev.site \
  --local https://{project}.ddev.site \
  --paths-file ../{project}-legacy/paths.txt \
  --out ./vr-out

python "$VR"/scripts/diff_report.py \
  --in ./vr-out --out ./vr-out/report
```

See the `visual-regression-upgrade` skill (from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills), installed separately) for setup, auth, mask config, and report interpretation.

## Key Discoveries & Gotchas

SS4 → SS5 data and template gotchas (raw-SQL migrations, empty layout containers, AJAX lazy loading, Title/SubTitle duplication, `httpError()`, `fractas/elemental-stylings` prefixes) are in [references/ss5-gotchas.md](references/ss5-gotchas.md). The SS6 equivalents are in [references/ss6-breaking-changes.md](references/ss6-breaking-changes.md).

Quick symptom index for SS6 (details in the reference):

- **All users logged out** on first deploy: `SameSite=Strict` session cookie default. Expected.
- **TinyMCE missing** → plain textarea: add `silverstripe/htmleditor-tinymce ^1.0` to `require`.
- **Migration tasks not found:** `dev/build flush=1` not yet run, or `commandName` missing from the Symfony Console task.
- **Custom `forTemplate()` crashes:** add `: string` return type.

## Phase 7: Code Quality

See [references/code-quality.md](references/code-quality.md) for PHPCS/PHPStan steps.

> **PHPStan vendor path:** Only scan `app/src` and `app/tests` — never include `vendor/` in `phpstan.neon` `paths`. Vendor errors such as `method.childReturnType` cannot be baselined (PHPStan re-reports them regardless). File upstream bugs in the relevant module repo. Full details in [references/code-quality.md](references/code-quality.md).

**Evidence gate (Phase 7):** paste the summary line of each tool run (PHPUnit test/assertion counts, PHPCS error count, PHPStan error count). "Probably still passes" does not clear this gate; only a run in this working tree does.

## Phase 8: Commit & PR

Conventional commit format, checklist PR body.

## Phase 9: UAT

Deploy to UAT, run migration tasks, verify frontend and CMS, troubleshoot.

## Phase 10: Production

Sequencing decision, DeployHQ repoint, database strategy, post-deploy smoke test, and expected post-deploy behaviors are in [references/production-deploy.md](references/production-deploy.md). Read it before the first production deploy.

## Reference Documentation

| Topic | File |
|-------|------|
| Data Migration Tasks | [data-migration-tasks.md](references/data-migration-tasks.md) |
| SS5 Version Map | [version-map.md](references/version-map.md) |
| SS6 Version Map | [version-map-ss6.md](references/version-map-ss6.md) |
| Code Quality | [code-quality.md](references/code-quality.md) |
| SS6 dependency changes | [ss6-dependencies.md](references/ss6-dependencies.md) |
| SS6 breaking changes | [ss6-breaking-changes.md](references/ss6-breaking-changes.md) |
| SS5 gotchas | [ss5-gotchas.md](references/ss5-gotchas.md) |
| Production deploy (Phase 10) | [production-deploy.md](references/production-deploy.md) |
| devbuild.sh vs migrate.sh | [devbuild-vs-migrate.md](references/devbuild-vs-migrate.md) |

## Recipe Branch Convention (SS6)

When branching repositories for an SS6 upgrade, use:

| CMS Version | Recipe Branch | Elemental Branch | Base-site Branch |
|-------------|---------------|------------------|-----------------|
| SS4 | `1` | `master` (deprecated) | `5` |
| SS5 | `2` | `5` | `7` |
| SS6 | `3` | `6` | `8` |

Recipes follow `recipe-cms` major version numbering. Elemental modules follow their own major version (elemental ^6 = branch `6`). Set the new version branch as the default branch on GitHub for each recipe/module repo.

## Related skills

- [silverstripe-3-to-4-upgrade](../silverstripe-3-to-4-upgrade/SKILL.md) — the prior, structurally different leg. Use it for legacy SS3 → SS4 projects before this skill applies.
- [ss-data-migration](../ss-data-migration/SKILL.md) — SS5 and SS6 data-migration runbooks for the BuildTasks in Phase 5.
- `visual-regression-upgrade` (from [jsirish/workflow-skills](https://github.com/jsirish/workflow-skills), installed separately) — capture pixel diffs against the legacy instance to confirm parity (also referenced inline in the Build & Verify phase).
