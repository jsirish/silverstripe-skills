---
name: ddev-sync
description: Start DDEV, sync remote database/assets, and rebuild local dev environment. Use when user says "ddev sync", "sync remote database", "pull remote data", "set up local dev", or asks to sync a DDEV project with remote environment.
---

# Skill: DDEV Sync & Dev Build

**Goal:** Fully synchronize a remote production database and assets into the local DDEV environment — start DDEV, install dependencies, pull DB + assets from production via SSH, and run the dev build.

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
   test -f ddev/.ddev.yaml || test -f .ddev/config.yaml
   ```
2. **Sync script exists** in project root:
   ```bash
   test -f sync.sh
   ```
3. **Dev build script exists** (if applicable):
   ```bash
   test -f devbuild.sh
   ```

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

5. **Sync remote database and assets** (will prompt for confirmation — answer `Y`):
   ```bash
   ddev exec ./sync.sh
   ```

### Phase 3: Rebuild Dev Environment

6. **Run the dev build script** (flushes caches, rebuilds database):
   ```bash
   ddev exec ./devbuild.sh
   ```

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
| Changes not appearing after sync | Append `?flush=all` to URL or run `ddev exec ./devbuild.sh flush=1` |
