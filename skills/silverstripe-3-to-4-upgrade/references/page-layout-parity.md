# Page-layout parity reference

The single biggest source of visual-regression FAILs on an SS3→SS4 upgrade is **page-layout template parity** — not block/element parity. SS3 layout templates emit wrapper divs, nav structures, and class names that the SS4 theme silently drops, and the SS3 CSS that targeted them stops applying. The fix is never "redesign the layout" — it's **reproduce the SS3 markup exactly** and the existing CSS works again.

This is the page-level companion to the block-template "one rule" in [block-to-element-migration](../../block-to-element-migration/SKILL.md): *preserve the legacy HTML structure verbatim.* See also the [Philosophy: parity, not redesign](../SKILL.md#philosophy-parity-not-redesign) section.

Each fix below shows the SS3 markup beside the SS4 equivalent. Lead with "reproduce, don't re-author."

---

## 1. Empty `block_area_*` wrappers control margin-collapse

SS3 themes rendered empty placeholder divs around content:

```html
<div class="block_area block_area_beforecontent clearfix"></div>
... content ...
<div class="block_area block_area_aftercontent clearfix">$ElementalArea</div>
```

These look like dead markup, but the `clearfix` pseudo-element **prevents margin-collapse** between the `<h1>` and the first content block — a 20px gap on SS3 vs 10px when the wrapper is dropped. SS4 layout templates that omit these divs collapse that margin, producing a 10–30px vertical offset that **cascades through every inner page and into the footer**. On one migration a single dropped wrapper was the root cause behind ~13 VR FAILs.

**Fix:** restore the empty wrappers verbatim, including the `clearfix` class. Don't try to reproduce the gap with new CSS — keep the markup the CSS already targets.

## 2. `block_area_aftercontent` must sit OUTSIDE both columns

The aftercontent `$ElementalArea` wrapper carries `margin-top: 30px`. When it's placed *inside* `<article>` / `col-sm-8`, it inflates the row height vs SS3, which rendered it **after** the clearfix div, *outside* both columns.

```html
<!-- SS3 — aftercontent OUTSIDE the columns -->
<div class="row">
  <article class="col-sm-8"> ... main content ... </article>
  <aside class="col-sm-4"> ... sidebar ... </aside>
</div>
<div class="block_area block_area_aftercontent clearfix">$ElementalArea</div>
```

**Fix:** move the aftercontent wrapper out of the column wrappers so the row height matches SS3.

## 3. Blog `WidgetHolder.ss` structure parity

SS3 blog rendered a plain `<div class="WidgetHolder">` with plain links. The SS4 blog module's `WidgetHolder.ss` renders `<nav class="secondary">` with `span.arrow` / `span.text`, which the SS3 CSS doesn't match.

**Fix — add theme-level overrides:**
- `WidgetHolder.ss` override emitting the plain `<div class="WidgetHolder">` with plain links.
- Widget templates at the **namespaced** path `themes/<theme>/templates/SilverStripe/Blog/Widgets/*.ss`. The unnamespaced legacy path `templates/widgets/*.ss` **never resolves in SS4** — this is the same namespaced-template-resolution rule that applies to page classes.
- Preserve the Bootstrap rule `ul.list-group { margin-bottom: 20px }` the sidebar relies on.

## 4. `SectionNav` replacement for SS3 `SectionNavigationBlock`

SS3 sites using `SectionNavigationBlock` (often applied site-wide via a BlockSet) have **no SS4 equivalent**. Recreate it as a `SectionNav.ss` include driven by `$Menu(2)`, carrying the legacy CSS classes (`sectionnavigationblock block`) so the existing CSS applies.

Two gotchas that cause phantom sidebars:
- **Only render when the parent has ≥3 children** (`$parent->Children().Count >= 3` / `$parent->Children()->count() >= 3`) — matching SS3 behaviour. Otherwise 2-sibling pages get a sidebar SS3 never showed.
- **Apply it only to the page types prod did.** If prod omitted section nav on Blog / BlogPost / individual StaffMember pages, omit it there too.

## 5. `MenuTitle` vs `Title` in nav (`&` vs "and")

SS3 `SectionNavigationBlock` rendered `$Title`; an SS4 `$Menu`-driven `SectionNav` renders `$MenuTitle`. When a page has `Title = "Advocacy & Case Management"` but `MenuTitle = "Advocacy and Case Management"`, the sidebar text — **and its line-wrapping** — diverges, producing a VR FAIL that looks like a layout bug but is really a content-field mismatch.

**Fix:** audit `MenuTitle` vs `Title` everywhere the nav source changed from SS3's `$Title` to SS4's `$MenuTitle`. Either align the fields or render `$Title` in the include to match SS3 exactly.

---

## Workflow

When a page FAILs visual regression after the upgrade, work in this order before touching any CSS:
1. Diff the rendered HTML against the SS3 legacy instance (see [Phase 1b](../SKILL.md#1b-legacy-ddev-instance)).
2. Look for a **missing wrapper div or dropped class** first (items 1–2 above).
3. Check nav structure and nav-text source (items 3–5).
4. Only after markup parity is confirmed should you consider a CSS change — and even then, prefer restoring the class the CSS already targets.
