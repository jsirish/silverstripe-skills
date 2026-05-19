# Template Parity Pattern

The single most important rule for a block-to-element migration: **preserve the legacy HTML structure exactly**. Only change the variables that inject data.

This doc captures the duplicate-and-swap workflow, the field-name swap cheatsheet, and the CSS scope-class trick that keeps existing CSS working.

## The workflow

For every legacy block template, do this:

1. **Locate** the legacy template under `themes/<theme>/templates/Includes/<BlockName>*.ss` (or `templates/Blocks/<BlockName>.ss` depending on the SS3 module).
2. **Determine** the corresponding new path and area-suffix (see [area-suffix-templates.md](area-suffix-templates.md)).
3. **Copy** the file to the new path.
4. **Apply** the swap cheatsheet below — change only variables, leave HTML untouched.
5. **Add** the legacy block class to the outer wrapper if Elemental's `$CSSClasses` doesn't include it naturally.
6. **Re-render** the page and diff against prod.

## Path translation table

| Legacy path | New path |
|-------------|----------|
| `themes/<theme>/templates/Includes/ContentBlock.ss` | `themes/<theme>/templates/DNADesign/Elemental/Models/ElementContent.ss` |
| `themes/<theme>/templates/Includes/ContentBlock_HomeContent.ss` | `themes/<theme>/templates/DNADesign/Elemental/Models/ElementContent_HomeContentElementalArea.ss` |
| `themes/<theme>/templates/Includes/PromoBlock_SideBar.ss` | `themes/<theme>/templates/App/Elements/ElementPromo_SidebarElementalArea.ss` |
| `themes/<theme>/templates/Includes/UpcomingEventsBlock.ss` | `themes/<theme>/templates/App/Elements/ElementEvents.ss` |
| `themes/<theme>/templates/Blocks/StaffMemberBlock.ss` | `themes/<theme>/templates/App/Elements/ElementStaffMember.ss` |

**The element template path** is the Element class's FQCN with `\` → `/`, plus `.ss`. So `App\Elements\ElementPromo` lives at `App/Elements/ElementPromo.ss`. `DNADesign\Elemental\Models\ElementContent` lives at `DNADesign/Elemental/Models/ElementContent.ss`.

## Variable swap cheatsheet

| Legacy SS3 | New SS4 | Why |
|------------|---------|-----|
| `<% if $Title %>` | `<% if $ShowTitle && $Title %>` | Elemental adds a `ShowTitle` boolean; the CMS-correct guard checks both |
| `$Content` (ContentBlock) | `$HTML` (ElementContent) | CMS label is still "Content" but template variable was renamed |
| `$EmbedCode` (EmbedCodeBlock) | `$Code` (ElementEmbeddedCode) | Field rename |
| `$BlockImage` (ImageBlock) | `$Image` (ElementImage) | Field rename |
| `<% loop $PromoObjects %>` (PromoBlock) | `<% loop $PromoList %>` or `<% loop $PromoItems %>` | depending on Element class (Dynamic vs custom) |
| `<% loop $PageSectionObjects %>` | `<% loop $PageSections %>` or `<% loop $FeatureList %>` | depending on Element class |
| `$BlockLink` / `$LinkType` / `$PageLink.Link` | `$ElementLink` (Linkable migration) or per-item `BlockLinkURL` | depends on whether Linkable module is used |
| `$CSSClasses` (verbose in SS4: includes class hierarchy) | `$ExtraClass` (only CMS-set extra classes) | If you want prod-parity wrapper classes, use `$ExtraClass` and hardcode the legacy block class |
| `$EvenOdd` / `$FirstLast` | identical (still in core) | no change |
| `$Image.Pad(...)` / `$Image.Fill(...)` | identical (Image API unchanged) | no change |

## CSS scope-class trick

SS3 themes typically scope CSS to the block's container class:

```css
.contentblock .typography p { ... }
.pagesectionblock .pagesection__image { ... }
.promoblock .promo h3 { ... }
```

Elemental's `$CSSClasses` outputs `element app__elements__elementcontent` (and similar), which doesn't match those selectors. You have two choices:

### Option A — Keep legacy classes on the element wrapper (recommended for migration)

Hardcode the legacy block class on the outer wrapper:

```html
<!-- ElementContent.ss -->
<div class="contentblock block $ExtraClass">
    <!-- ... -->
</div>
```

The `$ExtraClass` keeps CMS-configured extras working (and lets you keep the `migrated-from-block` marker visible as a class). The hardcoded `contentblock block` keeps all existing CSS working.

If `$CSSClasses` (the verbose form) creeps in via Elemental's default `ElementHolder.ss`, override that template:

```html
<!-- themes/<theme>/templates/DNADesign/Elemental/Layout/ElementHolder.ss -->
$Element
```

This strips the wrapping `<div class="element ...">` entirely. See [area-suffix-templates.md](area-suffix-templates.md) for the full ElementHolder explanation.

### Option B — Update CSS to target Elemental classes (cleaner long-term, more work)

Rewrite selectors:
```css
.element.app__elements__elementcontent .typography p { ... }
```

Defer this until after migration is verified. **Option A is the right call during migration.**

## Worked example — youth-sailing

**SS3 source:** `themes/sys/templates/SheaDawson/Blocks/Model/ContentBlock_HomeContent.ss`

```html
<div class="$CSSClasses">
    <div class="container">
        <div class="lines"></div>
        <div class="col-md-5">
            <div class="inner">
                <% if $Title %><h3>
                    <% if $LinkType != None %><a href="..."><% end_if %>
                        $Title
                    <% if $LinkType != None %></a><% end_if %>
                </h3><% end_if %>
                <div class="typography">
                    <% if $Content %>
                        <p>$Content.Plain
                    <% end_if %>
                    <% if $LinkType != None %><a href="..."><% end_if %>
                        <span class="arrow"></span>
                    <% if $LinkType != None %></a><% end_if %>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <% if $Image %>
                <img src="$Image.URL" class="scale-with-grid find-height" alt="Title">
            <% end_if %>
        </div>
    </div>
</div>
```

**SS4 target:** `themes/sys/templates/DNADesign/Elemental/Models/ElementContent_HomeContentElementalArea.ss`

```html
<div class="$StyleClasses block contentblock">
    <div class="container">
        <div class="lines"></div>
        <div class="col-md-5">
            <div class="inner">
                <% if $ShowTitle && $Title %><h3>
                    <% if $LinkType && $LinkType != 'None' %><a href="..."><% end_if %>
                        $Title
                    <% if $LinkType && $LinkType != 'None' %></a><% end_if %>
                </h3><% end_if %>
                <% if $SubTitle %><h3>$SubTitle</h3><% end_if %>
                <div class="typography">
                    <% if $HTML %>
                        <p>$HTML.Plain
                    <% end_if %>
                    <% if $LinkType && $LinkType != 'None' %><a href="..."><% end_if %>
                        <span class="arrow"></span>
                    <% if $LinkType && $LinkType != 'None' %></a><% end_if %>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <% if $Image %>
                <img src="$Image.URL" class="scale-with-grid find-height img-responsive" alt="$Title">
            <% end_if %>
        </div>
    </div>
</div>
```

### Diff summary

| Change | Type |
|--------|------|
| `$CSSClasses` → `$StyleClasses block contentblock` | CSS scope-class trick (option A) |
| `<% if $Title %>` → `<% if $ShowTitle && $Title %>` | Elemental ShowTitle guard |
| `$Content` → `$HTML` | Field rename |
| Added `$SubTitle` block | Element gained a SubTitle field via extension |
| `$LinkType != None` → `$LinkType && $LinkType != 'None'` | SS4 template engine is stricter about undefined comparisons |
| Added `img-responsive` to image class | Theme-specific; verify against your prod |

The HTML structure — the divs, the column classes, the inner nesting, the typography div, the link wrappers — is **identical**. CSS works untouched.

## Worked example — safeharbor PromoBlock_SideBar.ss

See [../examples/ElementContent_HomeContentElementalArea.ss.example](../examples/ElementContent_HomeContentElementalArea.ss.example) for another annotated side-by-side.

## When the new template must diverge

Not every change is a "swap variables, keep HTML". Three legit reasons to change structure:

1. **The CMS data model changed enough that the rendering logic must change.** E.g. SlideshowBlock had N child SlideImages; ElementHero is **one Element per slide** (flattened). The hero template renders one slide, not a loop.
2. **A SS3 JS-set fixed height was driving CSS that doesn't work in SS4.** SS3 sites often used jQuery to compute heights then positioned children absolutely. With the JS gone, `position: absolute` children collapse the parent. Strip the affected classes (e.g. `vert-centering` on Page Section) from the element template — don't try to revive the JS.
3. **A new field has no equivalent or vice versa.** E.g. ElementContent doesn't have a built-in Image field. If the legacy block had one, either install an extension that adds it, or drop the image from the migrated template (often the right call — old image was decorative).

Document the divergence in a comment at the top of the new template:

```html
<%-- Differs from ContentBlock_HomeContent.ss: removed `vert-centering` (SS3 JS-set
     height is gone); added $SubTitle support (new field on ElementContent). --%>
```

## Validation

After migrating a template:

1. Render the page locally.
2. Curl the prod equivalent.
3. Diff structural HTML (strip whitespace + asset hosts).
4. Visual check in browser — Chrome MCP, side-by-side at the same viewport.
5. Inspect computed CSS for the migrated block in dev tools — confirm the legacy CSS rules are still matching.
6. Test responsive breakpoints (sidebar layouts in particular tend to break at mobile).

See [verification-checklist.md](verification-checklist.md) for the full sequence.
