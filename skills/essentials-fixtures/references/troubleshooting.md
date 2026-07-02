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

### 2. Cross-File Reference Errors / Load Order
**Symptom:** `Could not resolve reference =>SilverStripe\Assets\Image.someImage`
**Cause:** References (`=>Class.identifier`) only resolve to records defined **earlier** in the run.
**Fix:** Order `include_yaml_fixtures` so assets and dependencies load before the files that
reference them — `shared-assets.yml` FIRST. See
[Configuration → ordering rules](./configuration.md#include_yaml_fixtures--ordering-rules).

### 3. Records Load But Don't Appear
**Symptom:** `PopulateTask` reports success, no errors, but the records never show in the CMS.
**Cause:** The DataObject class is in your fixture YAML but `FixtureRecordExtension` was not
registered on it, so Populate has no identity tracking for the record.
**Fix:** Register `FixtureRecordExtension` on **every** class that appears as a fixture block (each
Element subclass + nested models, not just `SiteTree`/`File`). See
[Configuration → Extension registration](./configuration.md#extension-registration--which-classes).

### 4. Missing Dependency / Class Not Found
**Symptom:** `Class "Dynamic\Recipe\Fixtures\Extensions\FixtureRecordExtension" not found`, or a
referenced fixture path errors as missing.
**Fix:** Confirm `dynamic/recipe-silverstripe-essentials-fixtures` is installed
(`composer show | grep essentials-fixtures`) and the `vendor/dynamic/...` fixture paths in
`include_yaml_fixtures` actually exist for the installed version. Run `ddev composer install` if the
package was added but not pulled.

### 5. YAML Syntax / Parse Errors
**Symptom:** `dev/build` or the Populate run dies parsing the fixture config.
**Fix:** YAML is whitespace-sensitive — use spaces (never tabs), keep `---` document separators
around each `Name:` block, and quote values containing `:` or leading `=>`. Validate a suspect file
with `ddev exec php -r "print_r(yaml_parse_file('app/_config/fixtures-populate.yml'));"` or any YAML
linter before re-running.

### 6. Stale / Duplicated Data After Re-run
**Symptom:** Re-running `PopulateTask` accumulates duplicate records instead of refreshing.
**Fix:** Add the affected classes to `truncate_objects` so each run starts clean (see
[Configuration → truncate_objects](./configuration.md#truncate_objects)). Never point
`truncate_objects` at a database with real content.

### 7. Elements Populated With Placeholder / Wrong Colors
**Symptom:** Every populated block renders with the essentials-theme placeholder
palette (purple `#9575EA`, lavender `#CCBEF5`, …) instead of the project's brand colors.
**Cause:** `PopulateTask` ran before the project palette was substituted into
`app/_config/essentials-styles.yml`. The `ColorConfigurationProvider` palette is
read once at populate time and stored on each record; editing the YAML afterward
does not update existing records.
**Fix:** Set the palette first, then rebuild the affected records — see
[Configuration → Color palette](./configuration.md#color-palette--set-before-first-populate).
Re-run with the affected Element classes in
[`truncate_objects`](./configuration.md#truncate_objects); never against real content.

### 8. Many-Many Join Rows Point at Phantom IDs After Re-run
**Symptom:** Blocks that depend on many-many relations (testimonials via category, sponsors)
render empty or wrong after re-running `PopulateTask` over an already-populated DB, even though
the run reported success. The join rows exist but reference record IDs that do not exist.
**Cause:** Re-running populate can write many-many join rows with phantom IDs. This is the known
populate v4 ID-registration issue that a project-level `PopulateFactory` override can only partially cover. A fresh single
populate links correctly.
**Fix:** When verifying many-many-dependent blocks, test on a clean populate rather than a re-run,
or confirm the join rows resolve to real IDs:
```bash
# Example: element-to-category join rows with no matching category record
ddev mysql -e "SELECT j.* FROM ElementTestimonials_TestimonialCategories j
               LEFT JOIN TestimonialCategory c ON j.TestimonialCategoryID = c.ID
               WHERE c.ID IS NULL;"
```
Any rows returned are phantom links: rebuild from a clean populate (see
[truncate_objects](./configuration.md#truncate_objects), never against real content).

## Known Vendor Bug
<a id="known-vendor-bug"></a>
**Issue:** `dnadesign/silverstripe-populate` bug in `populateFile()` returns `true` instead of the File object when hashes match.
**Fix:** Patch `vendor/dnadesign/silverstripe-populate/code/PopulateFactory.php` at the `return true;` in `populateFile()` so it returns the existing `File` object instead. Use whatever variable name holds that existing file in your local version of `PopulateFactory.php` (for example, `$existingObj`):
```diff
- return true;
+ return $existingFileObject; // e.g. $existingObj in some versions
```
