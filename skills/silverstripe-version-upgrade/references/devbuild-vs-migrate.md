# `devbuild.sh` vs `migrate.sh`

Shared convention for every Silverstripe upgrade and data-migration skill. Keep cache rebuild and data migration in two separate scripts.

## `devbuild.sh`: cache rebuild only

DeployHQ runs `devbuild.sh` after every code deploy, so it must do nothing but clear the cache and rebuild the schema. The canonical version is three lines:

```bash
#!/usr/bin/env bash
rm -rf silverstripe-cache
mkdir silverstripe-cache
vendor/bin/sake dev/build
```

It must **never** run migration tasks.

## `migrate.sh` (or the `/migrate` slash command): the task runner

The ordered data-migration tasks live in a separate `migrate.sh`, or in the project's `/migrate` slash command (`.claude/commands/migrate.md`), invoked manually after a fresh prod sync (not on a routine code deploy). Task runs should be idempotent so they are safe to re-run on every sync.

## Why they stay separate

- **No double `dev/build`.** A full-loop script that runs an explicit `dev/build` and then `ddev exec ./devbuild.sh` builds twice if `devbuild.sh` also runs `dev/build` plus tasks. Worse, migration tasks then run in a context that only meant to rebuild cache.
- **"Build" is not "migrate."** DeployHQ deploys code and rebuilds cache; it should never fire migration tasks. Conflating the two means a routine deploy silently re-runs data migrations.
