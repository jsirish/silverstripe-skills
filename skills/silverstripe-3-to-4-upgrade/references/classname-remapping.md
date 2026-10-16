# DB ClassName Remapping

Detail for [Phase 4](../SKILL.md#phase-4-code--namespace-migration), step 3.

Map every SS3 short class name stored in the database to its SS4 namespaced equivalent. This runs during `dev/build` and rewrites all `ClassName` columns across **every** DataObject table (including `_Live` and `_Versions`) — not just SiteTree. Without it, SS4 can't resolve the stored class names and pages fall back to `Page.ss` (or render blank). This is **separate from** any Injector/config class aliasing — both may be needed. The remapping is **idempotent** and safe to leave in place during the migration window; remove it once all migrated data is confirmed working. Add to `app/_config/app.yml`:
```yaml
SilverStripe\ORM\DatabaseAdmin:
  classname_value_remapping:
    # Page types
    Page: 'App\Pages\Page'
    HomePage: 'App\Pages\HomePage'
    Blog: 'SilverStripe\Blog\Model\Blog'
    BlogPost: 'SilverStripe\Blog\Model\BlogPost'
    # Orphaned SS3 vendor pages with no SS4 equivalent — map to nearest base:
    EventHolder: 'App\Pages\Page'
    EventPage: 'App\Pages\Page'
    # UserForms field classes — include ALL editable field types the DB contains:
    EditableEmailField: 'SilverStripe\UserForms\Model\EditableFormField\EditableEmailField'
    EditableTextField: 'SilverStripe\UserForms\Model\EditableFormField\EditableTextField'
    EditableDropdown: 'SilverStripe\UserForms\Model\EditableFormField\EditableDropdown'
    EditableCheckbox: 'SilverStripe\UserForms\Model\EditableFormField\EditableCheckbox'
    EditableFormStep: 'SilverStripe\UserForms\Model\EditableFormField\EditableFormStep'
    EditableFormHeading: 'SilverStripe\UserForms\Model\EditableFormField\EditableFormHeading'
    EditableLiteralField: 'SilverStripe\UserForms\Model\EditableFormField\EditableLiteralField'
    EditableSpamProtectionField: 'SilverStripe\SpamProtection\EditableSpamProtectionField'
    # Custom DataObjects with renamed/namespaced classes:
    PromoObject: 'Dynamic\Elements\Promos\Model\PromoObject'
```
Query the DB first to enumerate every value that needs remapping:
```bash
# Page types
ddev exec "mysql -udb -pdb db -e \"SELECT DISTINCT ClassName, COUNT(*) FROM SiteTree GROUP BY ClassName;\""
# UserForms fields (if silverstripe/userforms is installed)
ddev exec "mysql -udb -pdb db -e \"SELECT DISTINCT ClassName, COUNT(*) FROM EditableFormField GROUP BY ClassName;\""
```

> [!CAUTION]
> **Don't write custom SQL tasks to fix `ClassName` values.** A common SS3→SS4 smell is a hand-rolled `BuildTask` doing `UPDATE ... SET ClassName = '...'`. That's exactly what `classname_value_remapping` is for — it's idempotent, runs during `dev/build`, and covers base + `_Live` + `_Versions` automatically. Custom SQL is redundant and error-prone.
>
> **One exception:** `classname_value_remapping` only touches the `ClassName` column, **not** `ParentClass` or other string fields that store class names. A task like `FormParentClassMigrationTask` that fixes `EditableFormField.ParentClass` is still legitimate — but trim it to only fix `ParentClass`, not `ClassName` (which the config now handles).
