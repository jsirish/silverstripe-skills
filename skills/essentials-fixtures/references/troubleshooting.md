# Troubleshooting & Known Issues

## Common Issues

### 1. Duplicate Entry Errors
**Symptom:** `Duplicate entry 'X' for key 'Table.PRIMARY'`
**Fix:** Clean orphaned child-table records, then re-run:
```bash
ddev mysql -e "DELETE s FROM Sponsor s LEFT JOIN BaseElementObject bo ON s.ID = bo.ID WHERE bo.ID IS NULL;"
ddev mysql -e "DELETE sm FROM StaffMember sm LEFT JOIN BaseElementObject bo ON sm.ID = bo.ID WHERE bo.ID IS NULL;"
ddev sake dev/tasks/PopulateTask "flush=1"
```

### 2. Cross-File Reference Errors
**Symptom:** `Could not resolve reference =>SilverStripe\Assets\Image.someImage`
**Fix:** Ensure `shared-assets.yml` is loaded FIRST in the `include_yaml_fixtures` list.

## Known Vendor Bug
**Issue:** `dnadesign/silverstripe-populate` bug in `populateFile()` returns `true` instead of the File object when hashes match.
**Fix:** Patch `vendor/dnadesign/silverstripe-populate/code/PopulateFactory.php` at the `return true;` in `populateFile()` so it returns the existing `File` object instead. Use whatever variable name holds that existing file in your local version of `PopulateFactory.php` (for example, `$existingObj`):
```diff
- return true;
+ return $existingFileObject; // e.g. $existingObj in some versions
```
