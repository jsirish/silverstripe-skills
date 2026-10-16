# SS6 breaking changes

Companion to Phase 4 of [SKILL.md](../SKILL.md). Each item is a PHP fatal or a silently ignored config; grep for it rather than waiting for the build to fail.

## Configuration migration

### Namespace and class renames

SS6 relocated several core framework classes. Every `use` statement referencing an old FQCN causes a PHP fatal error at runtime (often first surfacing on `dev/build`). Grep for each old name and replace:

| Old (SS5) | New (SS6) |
|-----------|-----------|
| `SilverStripe\View\ViewableData` | `SilverStripe\Model\ModelData` |
| `SilverStripe\View\ArrayData` | `SilverStripe\Model\ArrayData` |
| `SilverStripe\ORM\ArrayList` | `SilverStripe\Model\List\ArrayList` |
| `SilverStripe\ORM\ValidationResult` | `SilverStripe\Core\Validation\ValidationResult` |
| `SilverStripe\ORM\ValidationException` | `SilverStripe\Core\Validation\ValidationException` |

```bash
# Run each grep across app code, module code, and theme code
rg "SilverStripe\\\\View\\\\ViewableData" app/src/ src/ themes/*/code/
rg "SilverStripe\\\\View\\\\ArrayData" app/src/ src/ themes/*/code/
rg "SilverStripe\\\\ORM\\\\ArrayList" app/src/ src/ themes/*/code/
rg "SilverStripe\\\\ORM\\\\ValidationResult" app/src/ src/ themes/*/code/
rg "SilverStripe\\\\ORM\\\\ValidationException" app/src/ src/ themes/*/code/
```

The `wernerkrauss/silverstripe-rector` SS6 level set automates most of these renames (see [references/code-quality.md](code-quality.md)), but always run the greps afterward: string references in config YAML, `Injector` definitions, and docblocks are not rewritten by Rector.

### Typed signature requirements

Two related changes cause PHP declaration-compatibility fatals if missed:

- **`validate()` overrides must declare the return type.** Any `DataObject` subclass overriding `validate()` must use `public function validate(): ValidationResult` (with the new `SilverStripe\Core\Validation\ValidationResult` import). PHP throws a fatal declaration-compatibility error otherwise.
- **`ModelData` magic-method overrides must match parent signatures.** Subclasses of `ModelData` (formerly `ViewableData`) that override `__get`, `__set`, `__isset`, `hasField`, `getField`, `setField`, etc. must add the typed parameters and return types the SS6 parent declares.

```bash
# Find validate() overrides missing the return type
rg "public function validate\(\)(?!\s*:)" app/src/ src/ --pcre2

# Find ModelData subclasses, then inspect their overridden magic methods
rg "extends ModelData|extends ViewableData" app/src/ src/
```

### BuildTask signature change
SS6 changes `run($request)` → `execute(InputInterface, PolyOutput): int` returning `Command::SUCCESS`.

Key differences:
| SS4/SS5 | SS6 |
|---------|-----|
| `protected $title = '...'` | `protected string $title = '...'` |
| `protected $description = '...'` | `protected static string $description = '...'` |
| `private static $segment = '...'` | `protected static string $commandName = '...'` (required) |
| `public function run($request)` | `protected function execute(InputInterface $input, PolyOutput $output): int` |
| `echo` / `$this->log()` | `$output->writeln()` |
| Implicit return | `return Command::SUCCESS` |

### forTemplate() return type enforcement
SS6 enforces `: string` return type on `forTemplate()`. Never return `false` — return `` (empty string) instead.

```php
// SS5 (still works but triggers deprecation)
public function forTemplate() { return false; }

// SS6
public function forTemplate(): string { return ''; }
```

### BaseElement::getDescription() removed
SS6 Elemental removed `BaseElement::getDescription()`. Use `private static string $class_description` instead:

```php
// SS5
public function getDescription() { return "My element"; }

// SS6
private static string $class_description = "My element";
```

### DDEV database socket config
SS6 defaults to MySQL unix socket connections. DDEV uses TCP, so add to `.ddev/config.yaml`:

```yaml
web_environment:
  - SS_DATABASE_SERVER=db
```

Without this, `dev/build` fails with "Connection refused."

### TinyMCE extraction
SS6 extracted TinyMCE from `silverstripe/admin` into `silverstripe/htmleditor-tinymce ^1.0`. If missing, the CMS Content field silently degrades to a plain textarea (`data-editor="textarea"` instead of `data-editor="tinyMCE"`).

> [!NOTE]
> **Essentials recipe exception:** in Essentials SS6 recipe projects the package arrives transitively through the recipe, and an explicit require causes conflicts — check `composer why silverstripe/htmleditor-tinymce` before adding it (see the `silverstripe-essentials-website` skill).

**Fix:** Add to `composer.json` under `require` (not `require-dev`):
```json
"silverstripe/htmleditor-tinymce": "^1.0"
```

**Diagnostic:**
```javascript
document.querySelector('[data-editor]').dataset.editor
// Returns "textarea" instead of "tinyMCE" → missing package
```

### Theme template API changes: Linkable → LinkField

When migrating from `sheadawson/silverstripe-linkable` to `silverstripe/linkfield`:

| Linkable (SS5) | Linkfield (SS6) | Notes |
|----------------|-----------------|-------|
| `$Link` | `$URL` | `getURL()` not `getLink()` |
| `$LinkURL` | `$URL` | Consistent — use `$URL` |
| `$OpenInNewWindow` | `$OpenInNew` | Renamed attribute |
| `$MenuTitle` | `$Title` | Different semantics — `getMenuTitle()` returns type label |
| `$Site` (SocialLink) | `$SocialChannel` | Enum → varchar mapping |
| `$X.setStyle('classes')` | Explicit `<a class="...">` | Linkfield renders bare links |
| `<% loop $HasOneLink %>` | `<% with $HasOneLink %>` | has_one is not iterable in SS6 |
| `$ElementLink.LinkURL` | `$ElementLink.URL` | Namespace change on sub-properties |

### SS6 silent config breakers

**SeoExtension removal** — Remove from `SiteTree.extensions` if using a third-party search provider:
```yaml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\Base\Extension\SeoExtension  # REMOVE — causes PHP worker hangs in SS6
```

**PasswordValidator** — SS6 defaults to `EntropyPasswordValidator`. Old `RulesPasswordValidator` config is silently ignored:
```yaml
# SS6 format (NOT the old min_test_score / test_names pattern)
SilverStripe\Security\PasswordValidator:
  password_strength: 3  # 0-4 scale
```

**Session cookie defaults** — SS6 sets `SameSite=Strict`, `cookie_secure=true`. All existing sessions invalidated on first deploy — expected and normal. Verify:
```bash
curl -sI https://site.example.com/ | grep -i 'set-cookie'
# Expected: PHPSESSID=...; path=/; secure; HttpOnly; SameSite=Strict
```

**`classname_value_remapping` YAML key renamed** — In SS4/5 the key was `SilverStripe\ORM\DatabaseAdmin`. In SS6 the class no longer exists; use `SilverStripe\Dev\Command\DbBuild` instead. Using the old key is **silently ignored** — `dev/build` runs without error but no remapping occurs, leaving legacy class names in the DB and causing "obsolete type" warnings in the CMS for every affected page.

```yaml
# SS4/SS5 (WRONG in SS6 — silently ignored)
SilverStripe\ORM\DatabaseAdmin:
  classname_value_remapping:
    OldClass: NewClass

# SS6 (correct)
SilverStripe\Dev\Command\DbBuild:
  classname_value_remapping:
    OldClass: NewClass
```

After correcting the key, a single `dev/build flush=1` migrates all affected rows in both `SiteTree` and `SiteTree_Live`. The build output will confirm: *"Correcting obsolete ClassName values for N outdated types."* Side effect: migrated pages gain a draft/live diff and show as MODIFIED — republish them afterward.

## Package and extension-point removals

> [!WARNING]
> **Linkable removed.** `sheadawson/silverstripe-linkable` has no SS6 version. Replace with `silverstripe/linkfield ^5` (the 4.x line is CMS 5 only; run the linkable data migration on SS5 with linkfield `^4` BEFORE the SS6 bump). Template API changes: see the Linkable → LinkField table above.
>
> **Embedfield replaced.** `nathancox/embedfield` has no SS6 version. Replace with `fromholdio/silverstripe-embedfield ^5.1`.
>
> **`CMSPageAddController` removed.** Any module extension bound to it (commonly an `updatePageOptions(FieldList $fields)` hook on the "Add new page" flow) is dead code with no error - the extended class no longer exists, so the hook never fires.
>
> - **Add fields to the add form:** extend `SilverStripe\CMS\Forms\CMSMainAddForm` with `updateFields(FieldList $fields)`. The page-type field is now named `RecordType` (was `PageType`), an `OptionsetField` - insert after it.
> - **Act on create:** extend `SilverStripe\CMS\Controllers\CMSMain` with `updateDoAdd(DataObject $record, Form $form)` (`CMSMainAddForm::doAdd()` fires `$controller->extend('updateDoAdd', $record, $form)`).
> - **Read the submitted values:** `$form->getData()` - by the time `updateDoAdd` fires, `FormRequestHandler::httpSubmission()` has already populated the form from the POST vars via `loadDataFrom()`, so `getData()` reflects the submission, not stale construction-time data (verified against `silverstripe/framework` and `silverstripe/cms` SS6 source: `CMSMainAddForm extends Form`, no override of the base submission flow). `Form::getRequestData()` does not exist in any SilverStripe version - do not use it.
> - **Verify in the live CMS, not just a unit test that calls the hook method directly** - the wiring (which class the extension binds to) is exactly what silently breaks, and a direct method-call test passes regardless of the binding.
>
> Real-world hit: `dynamic/silverstripe-elemental-templates` (its template-picker "Step 3" dropdown), fixed in `dynamic/silverstripe-elemental-templates#83`/`#84`.
