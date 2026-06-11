---
name: ddev-sync
description: Start DDEV, sync remote database/assets, and rebuild local dev environment. Use when user says "ddev sync", "sync remote database", "pull remote data", "set up local dev", or asks to sync a DDEV project with remote environment.
---

# Skill: DDEV Sync & Dev Build

**Goal:** Fully synchronize a remote production database and assets into the local DDEV environment — start DDEV, install dependencies, pull DB + assets from production via SSH, and run the dev build.

## Prerequisites

DDEV must run the PHP version the project's Silverstripe major requires, or the `dev/build` after
the sync fails with cryptic errors:

| Silverstripe | PHP floor |
|--------------|-----------|
| SS4 | PHP 7.4+ |
| SS5 | PHP 8.1+ |
| SS6 | PHP 8.3+ |

Set it in `.ddev/config.yaml` (`php_version`) before syncing if it doesn't already match.

## Deployment Model (Dynamic Agency standard)

- **Code deploys via DeployHQ** — never via manual SSH or scripts.
- **Production is the single source of truth for content.** Database and assets always flow **down** from production to local (and optionally to UAT/staging). They are never pushed **up** to production from local.
- **`sync.sh`** pulls production DB + assets into local DDEV. It is a one-way **down-sync** tool.
- **`deploy.sh`** pushes local DB + assets to UAT/staging only. It is never used for production.
- Content is added and updated on production, then synced to other environments as needed.

---

## When to Use

Automatically activate when the user:

- Asks to "sync" or "pull" remote data into local dev
- Says "ddev sync", "sync remote database", or "pull remote assets"
- Requests to "set up local dev environment" for a DDEV project
- Mentions syncing remote database and assets

---

## Prerequisites

Verify the following before proceeding:

1. **Project has DDEV configuration:**
   ```bash
   test -f .ddev/config.yaml
   ```
2. **Sync script exists** in project root:
   ```bash
   test -f sync.sh
   ```
3. **Dev build script** (optional — falls back to `ddev sake dev/build` if not present).

---

## Workflow

### Phase 1: Start DDEV & Install Dependencies

1. **Start the DDEV container:**
   ```bash
   ddev start
   ```

2. **Install composer dependencies:**
   ```bash
   ddev composer install
   ```

3. **Add SSH keys to the DDEV SSH agent:**
   ```bash
   ddev auth ssh
   ```

4. **Expose vendor module web directories:**
   ```bash
   ddev composer vendor-expose
   ```

### Phase 2: Sync Remote Data

> **⚠️ WARNING:** `sync.sh` will **drop and overwrite** your local database and assets with production data. Any local content changes (uncommitted DB changes, uploaded files) will be **permanently lost**. Ensure you've committed any work-in-progress before proceeding.

> [!IMPORTANT]
> Confirm `sync.sh` pulls from PRODUCTION, not pre-prod/UAT:
> `grep REMOTE_HOST .env` — must be your production server.
> `REMOTE_*` = prod (sync FROM); `PREPROD_*` = pre-prod (deploy TO). If both
> sets point at the same host, you are syncing from the wrong environment.

5. **Sync remote database and assets** (will prompt for confirmation — answer `Y`):
   ```bash
   ddev exec ./sync.sh
   ```

### Phase 3: Rebuild Dev Environment

6. **Run the dev build** (flushes caches, rebuilds database):
   ```bash
   # Use devbuild.sh if available, otherwise run the standard build command
   if test -f devbuild.sh; then
     ddev exec ./devbuild.sh
   else
     ddev sake dev/build
   fi
   ```

### Phase 4: Major-version upgrades — run the migration tasks

After `dev/build`, a synced DB from a *different major version* contains
source-version data in the target schema. It is NOT ready to serve. Run the
project's migration runbook:

```bash
ls .claude/commands/ | grep -i migrat      # project /command
ls .agent/skills/ 2>/dev/null              # project-specific skill
```

If none exists, see the `ss5-data-migration` / `silverstripe-3-to-4-upgrade`
skills for the task sequence, and formalize a project-specific runbook (strongly
recommended — each project's task list differs; `ss6-data-migration` is the
canonical example of one).

Do NOT assume the site is ready just because pages render —
`classname_value_remapping` makes pages resolve, but block/settings/form/file
data is still unmigrated.

---

## Important Notes

- **`sync.sh` typically requires SSH authentication.** Ensure `ddev auth ssh` has completed successfully before running the sync step.
- **The sync script usually prompts for confirmation.** Be prepared to confirm when prompted.
- **After a full sync, flush SilverStripe caches** by appending `?flush=all` to any page URL if templates or config changes aren't reflecting.
- **Asset permissions** may need adjustment after sync — check file/folder ownership inside the container if assets fail to load.

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| `ddev: command not found` | Install DDEV: https://ddev.com/install/ |
| SSH auth fails inside container | Run `ddev auth ssh` again and verify keys are loaded |
| Sync script not found | Verify `sync.sh` exists in project root |
| Assets appear broken after sync | Run `ddev composer vendor-expose` and check file permissions |
| Changes not appearing after sync | Append `?flush=all` to URL, or run `ddev exec ./devbuild.sh flush=1` if `devbuild.sh` exists (otherwise `ddev sake dev/build "flush=1"`) |
| `Mutagen sync completed with problems … unable to relocate staged file: file exists` | Mutagen is trying to sync user-uploaded assets. Set `upload_dirs` so the assets dir is bind-mounted instead — see [Mutagen `upload_dirs` conflicts](#mutagen-upload_dirs-conflicts-after-prod-sync) below. |

## Mutagen `upload_dirs` conflicts after prod sync

On Mutagen-enabled DDEV projects, the **first prod asset sync after a fresh start** frequently fails with:

```
Mutagen sync completed with problems
  <file>: unable to create file: unable to relocate staged file: file exists
```

The conflicting files are user-uploaded assets (e.g. `assets/SecureUploads/<file>.docx`, resampled image
variants under `_resampled/`). The site won't serve until it's resolved, and it recurs on **every** prod
sync — a standing trap during the sync → migrate → VR loop.

**Fix:** set `upload_dirs` in `.ddev/config.yaml` so Mutagen **bind-mounts** the assets dir instead of
syncing it through Mutagen:

```yaml
upload_dirs:
  - assets
  # add ../node_modules too if it's a large sibling dir that doesn't need Mutagen sync
```

Then:

```bash
ddev mutagen reset && ddev restart
ddev mutagen st <project>   # confirm it shows: ok: watching
```
