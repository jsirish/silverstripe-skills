# Key Discoveries & Gotchas

> [!CAUTION]
> **Global `Page` class is required — do NOT delete it.** Multiple vendor modules do `use Page` and extend it: `silverstripe/errorpage`, `silverstripe/blog`, `silverstripe/userforms`, `silverstripe/cms` (SiteTree, RedirectorPage, VirtualPage), `dnadesign/silverstripe-elemental`, and `silverstripe/framework` (Security). The file `app/src/Page.php` must define a **global-namespace** `Page extends SiteTree`. Custom project logic goes in `App\Pages\Page extends \Page`. Deleting the global Page causes a fatal during class-manifest build.

> [!CAUTION]
> **A global `\PageController` must exist for base Page controller resolution.** `SiteTree::getControllerName()` walks the class ancestry appending `"Controller"`. For generic `Page`/`App\Pages\Page` records (including those remapped to `App\Pages\Page`), it looks for a `PageController`. Without it, those records fall back to `ContentController` and theme rendering breaks.
>
> Put the global controller in its **own file** `app/src/PageController.php` (NOT inside `Page.php`) and register **both** in the composer `classmap` — add `classmap` as a sibling **inside** the same `autoload` block that retains `psr-4`:
> ```jsonc
> // composer.json
> "autoload": {
>   "psr-4": { "App\\": "app/src/" },
>   "classmap": ["app/src/Page.php", "app/src/PageController.php"]
> }
> ```
> ```php
> // app/src/PageController.php
> class PageController extends App\Controllers\PageController {}
> ```
> If `Page` and `PageController` share one file, the class manifest + classmap can re-include it and fatal with "Cannot declare class Page." Also match the framework `init()` contract: `protected function init()` with **no** return type — `public function init(): void` fatals under PHP 8 against vendor controllers whose parent declares `protected init()`.

> [!WARNING]
> **Gitignored SS3 module directories block dev/build.** If old module dirs (`silverstripe-versioneddataobjects/`, `widgets/`, `userforms/`, etc.) are gitignored but still present on disk, SS4's class manifest scans them and finds SS3-incompatible classes — fatals like `Class "Versioned" not found`. Delete them (`rm -rf silverstripe-versioneddataobjects`) and document it for new devs (README/`devbuild.sh`). The same applies to stray PHP under `.claude/worktrees/` — drop a `.claude/_manifest_exclude` marker so the manifest skips it.

> [!WARNING]
> **Raw SQL is Mandatory**: When migrating from SS3 modules that are removed in SS4 (like `dynamic-blocks`), the ORM strips legacy `$db` properties. You cannot rely on `$block->Title` during migration. You must query the legacy `Block` and `Block_Live` tables using raw `DB::query()` and `INSERT ON DUPLICATE KEY UPDATE` into the new Elemental records.

> [!TIP]
> **PublishRecursive Dangers**: Running `publishRecursive()` on a root page will validate all child elements, including forms. If a UserForms setup has obsolete SS3 class names in the database (e.g., `EditableEmailField` instead of `SilverStripe\UserForms\Model\EditableFormField\EditableEmailField`), the publish will fatal error. Make sure `classname_value_remapping` covers these `ClassName` values before publishing (see [classname-remapping.md](classname-remapping.md)); a task is only needed for `ParentClass`.

> [!IMPORTANT]
> **Image Resize Methods**: `jonom/focuspoint` is rarely carried over to SS4. Update template tags from `$Image.FocusFill()` to `$Image.Fill(X, Y)` or native SS4 crop functions.

> [!WARNING]
> **ElementalArea_Live is empty after dev/build**: SS4 `dev/build` creates `ElementalArea` rows on the **draft** table only. Elements you placed during migration won't appear on the frontend until both `ElementalArea_Live` and `Page_Live.ElementalAreaID` are populated. The block migration task must end with these two SQL passes. Full pattern: [block-to-element-migration](../../block-to-element-migration/SKILL.md).

> [!WARNING]
> **Versioned writes need _Live AND _Versions**: When inserting Elements via raw SQL, write to all three tables: base draft, `_Live`, and `_Versions`. Skipping `_Versions` causes "no history" errors when editing in the CMS and can cause `publish()` to silently strip the record from `_Live`. See the `insertVersionedRow()` helper in [block-to-element-migration/references/migration-task-skeleton.md](../../block-to-element-migration/references/migration-task-skeleton.md).

> [!TIP]
> **Legacy CSS still works if you keep the old class**: SS3 themes scope CSS to block class names (`.pagesectionblock`, `.promoblock`, etc.). Elemental's `$CSSClasses` outputs `element app__elements__elementpagesection` instead. To retain existing CSS during migration, add the legacy class to the Elemental wrapper template: `<div class="$CSSClasses pagesectionblock">`. Defer full CSS rewrite until after migration is verified.

> [!WARNING]
> **JS-dependent CSS will collapse**: SS3 themes often use JavaScript to set fixed heights on block containers, then position child elements absolutely within them. With the JS gone, `position: absolute` children leave the parent at height 0 and the layout collapses. When migrating, remove `vert-centering` (or equivalent) classes from element templates — don't try to revive the JS.

> [!NOTE]
> **Template debugging**: in dev mode, append `?showtemplate=1` to any URL to see which template SS4 resolved for that page, and `?flush=1` to clear the template cache. Installing `lekoala/silverstripe-debugbar` (SS4) adds a toolbar showing the resolved controller, the template chain, and DB queries — invaluable when a namespaced class silently falls back to `Page.ss`.
