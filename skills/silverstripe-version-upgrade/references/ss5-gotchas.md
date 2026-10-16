# SS4 → SS5 gotchas

Companion to the Build & Verify phases of [SKILL.md](../SKILL.md).

> [!WARNING]
> **Raw SQL Data Migrations:** When migrating from deprecated or obsoleted modules (such as legacy `dynamic/silverstripe-blocks`), the modern ORM will **not** map legacy database columns (like `$db` properties) because the field definitions have been removed from the class. You must rely on raw `DB::query()` calls to preserve and remap data (e.g., copying old `PageLinkID` fields directly into new `LinkField` instances).

> [!NOTE]
> **Empty Layout Containers:** During block to element mapping, migrating structural containers (like Accordions or Promo galleries) that have exactly *0* items within them is intentional. Preserving the empty wrapper maintains its hierarchical placement in the DOM so editors can populate it post-upgrade without losing context.

> [!TIP]
> **AJAX Lazy Loading Optimization:** Pre-generating Image asset manipulations (e.g., `ScaleWidth` or `Fill`) and employing AJAX lazy-loading on heavily populated pages (such as dense galleries) is required. Rendering 200+ raw Image objects in a single PHP payload causes hard pre-prod timeouts in SS5. *Note: in SS5, `PageController::init()` will still fire on AJAX endpoints unless explicitly bypassed.*

> [!IMPORTANT]
> **Data duplication / Subtitle:** The unified templates in SS5 ElementContent might render both `Title` and `SubTitle` domains redundantly. SS4 layouts that abused DB columns to handle split headings will show duplicate lines post-migration unless the legacy entries are scrubbed.

> [!NOTE]
> **SS5 `httpError()`:** The `httpError()` routine in SS5 throws an `HTTPResponse_Exception`. Following the call with a simple `return` aids static analysis flow and squashes PHP linting notices without impacting application state.

> [!WARNING]
> **Elemental Styles & `fractas/elemental-stylings`:** In SS4, the `fractas/elemental-stylings` module prefixed the elemental style values with `style-` (e.g., `style-modal`, `style-blue`). In SS5, native styles are used, and `getStyleVariant()` returns the raw value (e.g., `modal`). If templates or SCSS rely on the `style-` prefix, you must either update the templates/SCSS, update the values in the database, OR write a simple `updateStyleVariant` DataExtension to re-apply the prefix.
