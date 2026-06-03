---
name: server-sync-deploy
description: Documentation for managing local-to-remote and remote-to-local environment synchronizations utilizing deploy.sh and sync.sh bash scripts. Use when syncing databases, transferring public assets, or pushing tested deployments to staging/production servers.
---

# Server Sync & Deploy Workflow

A unified guide on pulling and pushing environmental data (Database & Assets) between DDEV local instances and remote (Preprod/Production) servers using `sync.sh` and `deploy.sh`.

## Overview

In DDEV architectures, rather than executing builds or variant generations on a live VPS—which risks server timeouts on heavy asset matrices—we rely on localized `rsync` techniques. 
*   **`sync.sh`**: Pulls the remote state Downward (`Remote -> Local`). Useful for onboarding onto a project or grabbing fresh production data to test locally.
*   **`deploy.sh`**: Pushes the local state Upward (`Local -> Remote`). Essential during Silverstripe Major Version upgrades or when deploying pre-computed `public/assets`.

> [!WARNING]
> Both scripts are highly destructive. `sync.sh` will drop and overwrite the local DDEV database/assets. `deploy.sh` will drop and overwrite the remote database/assets. **Always confirm destinations before running.**

## Prerequisites / Safety

Both scripts invoke `ssh`, `rsync`, `mysqldump`, `mysql`, and `gzip`. Execute them from **inside the DDEV container** where SSH credentials are authenticated.
```bash
# Authorize SSH Agent First
ddev auth ssh
# Execute from inside the container
ddev exec ./sync.sh
ddev exec ./deploy.sh
```

## Environment Configuration (`.env`)

The target remote environment relies on specific credentials set inside the project's `.env` file. Notice the prefix disparity: `sync.sh` pulls from `REMOTE_`, while `deploy.sh` pushes to `PREPROD_`.

**For `sync.sh` (Pulling):**
```text
REMOTE_USER="username"
REMOTE_HOST="target-host.com"
REMOTE_ASSETS_PATH="/var/www/html/public/assets"
REMOTE_DB_NAME="db_name"
REMOTE_DB_USER="db_user"
REMOTE_DB_PASSWORD="db_password"
REMOTE_DB_HOST="localhost"
```

**For `deploy.sh` (Pushing):**
```text
PREPROD_USER="username"
PREPROD_HOST="target-host.com"
PREPROD_ASSETS_PATH="/var/www/html/public/assets"
PREPROD_DB_NAME="db_name"
PREPROD_DB_USER="db_user"
PREPROD_DB_PASSWORD="db_password"
PREPROD_DB_HOST="localhost"
```

*Local execution relies on standard SS variables (`SS_DATABASE_NAME`, `SS_DATABASE_SERVER`, etc).*

## Command Flags & Features

Both scripts accept identical command-line flags to isolate deployments or test executions without causing data loss.

| Flag | Description |
|-----------|-------------|
| `--help`  | Shows usage information |
| `--dry-run` | Tests the `rsync` without making changes to the disk or executing MySQL dumps. Always recommend running this first. |
| `--assets`| Bypasses the database dump phase. Only syncs `public/assets/`. |
| `--db`    | Bypasses the asset rsync phase. Only drops and imports the database. |
| `--exclude=PATTERN` | Pass this flag (multiple times if needed) to ignore specific files or globs from the `rsync` cycle. Example: `--exclude=*.log --exclude=_resampled/` |

### Script Internal Logic

#### `sync.sh` (Pulling)
1. **DB Segment:** Performs a `mysqldump` dynamically via SSH on the remote, zips to `/tmp`, pulls via `rsync`, then pipes via `pv` through `gunzip` and drops/imports local DB.
2. **Assets Segment:** Executes `rsync --delete` to map remote `/assets/` directory downwards, purging orphaned local files. 

#### `deploy.sh` (Pushing)
1. **DB Segment:** Dumps local SS_DB to `/tmp`, zips it, pushes to remote `/tmp` via `rsync`, executes remote `gunzip | mysql` via SSH to overwrite the target DB.
2. **Assets Segment:** Executes `rsync --delete` to push local `/assets/` to target. Crucial for syncing locally-resampled Silverstripe Thumbnail/Lightbox variant structures.

> [!TIP]
> Ensure a local deployment task involving heavy computation (e.g. `GalleryResampleTask`) is run locally *before* utilizing `deploy.sh --assets` to offload processing burden successfully from the remote VPS.

## DeployHQ / Ploi caveats

`sync.sh` / `deploy.sh` move data; the actual code deploy is usually handled by **DeployHQ** (build + SSH) onto a **Ploi**-managed server. Three behaviours of that stack each caused a production-style failure and aren't obvious from the scripts above.

> [!CAUTION]
> **`composer install` skips packages whose constraint is already satisfied.** On a server with an existing `vendor/`, `composer install` will **not** re-fetch a package just because the locked content changed — leaving stale or mismatched code that passes locally but breaks the deploy. For a clean parity deploy, wipe first:
> ```bash
> rm -rf vendor && composer install --no-dev -o
> ```
> Add `rm -rf vendor` to the DeployHQ post-deploy SSH command (or build step) so every deploy starts from a clean vendor tree.

> [!WARNING]
> **DeployHQ deletes files that are removed from git.** When a tracked file is **de-tracked** (removed from the repo), DeployHQ deletes it from the server on the next deploy. This silently wiped a server's `.env` after it was removed from version control. Keep server-only files (`.env`, secrets, uploaded assets outside `public/assets/`) in DeployHQ's **config files** / **excluded paths** feature, or recreate them out-of-band — never assume a de-tracked file survives on the server.

> [!WARNING]
> **Ploi requires `SS_DATABASE_SERVER=127.0.0.1`, not `localhost`.** On Ploi-managed servers, `localhost` resolves to a MySQL **socket path** that the SS4 DB layer can't use, and the connection fails. Use the TCP loopback in the server `.env`:
> ```text
> SS_DATABASE_SERVER="127.0.0.1"
> ```
