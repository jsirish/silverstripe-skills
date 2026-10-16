# Phase 10: Production deploy

Companion to Phase 10 of [SKILL.md](../SKILL.md).

### 10.1 Deployment sequencing decision

If your SS6 upgrade also bundles AI modules, pre-1.0 packages, or other high-risk additions, consider splitting into **two deploys** — SS6 cutover first, followed by the new modules. Stacking pre-release packages on a cutover deploy increases blast radius and makes rollback more complex.

### 10.2 DeployHQ (or comparable CD) repoint

The deploy branch must be changed from the old SS5 branch (e.g., `4` or `5`) to `master` (SS6). Do this **right before deploying**, not in advance — the old branch is the rollback target.

**Pre-deploy checks:**

```bash
# Verify composer install succeeds
composer install --no-dev

# Verify all runtime deps are in "require", not "require-dev"
# Notably: silverstripe/htmleditor-tinymce must be in "require"
composer show --direct | grep htmleditor
```

**Private repo access:** If the upgrade adds modules from private GitHub repos (e.g., `silverstripeltd/ai-*`), ensure the production server has an SSH deploy key with read access to the organization before cutover day. Test with a manual `composer install` if possible.

### 10.3 Database strategy

**Option A (recommended): Push local/UAT DB to prod before cutover.** Run migrations locally, then use `deploy.sh --db` to push the already-migrated database to production. No migration tasks needed on prod — it's a drop-in replacement.

**Option B: Run migration on production's existing DB.** Deploy SS6 code, run `dev/build flush=1`, then run each migration task sequentially. Riskier — if a migration fails mid-way, the site is partially broken and requires schema repair.

### 10.4 Post-deploy smoke test

```bash
# Key pages load
for u in / /work /blog /admin/ /about/about-us; do
  curl -sI "https://$DOMAIN$u" | head -3
done

# Verify no redirect loop
curl -sI "https://$DOMAIN/" | grep -c "301\|302"  # should be 0

# Verify CMS renders (WYSIWYG check — log in, edit a page)
# Expected: data-editor="tinyMCE" on Content field
```

### 10.5 Expected post-deploy behaviors

- **All users logged out** on first deploy — SS6's `SameSite=Strict` session cookie default invalidates existing sessions. Normal and expected.
- **TinyMCE missing** → plain textarea — add `silverstripe/htmleditor-tinymce ^1.0` to `require`.
- **Migration tasks not found** `dev/build flush=1` not yet run, or `commandName` property missing from the Symfony Console task.
- **Custom `forTemplate()` methods crash** — add `: string` return type.
