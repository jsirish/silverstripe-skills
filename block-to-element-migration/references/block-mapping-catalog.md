# Block Class → Element Class Catalog

A composite catalog from three production migrations (rockline-iatric, youth-sailing, safeharbor). For each legacy block class, this lists the SS4 Element target, the composer module that provides it, and a note about field-name changes that matter for the template-parity pass.

## Standard mappings

| Legacy block class | Element class | Composer module | Field renames |
|---------------------|---------------|------------------|---------------|
| `ContentBlock` | `DNADesign\Elemental\Models\ElementContent` | `dnadesign/silverstripe-elemental` (core) | `$Content` → `$HTML` |
| `PromoBlock` | `Dynamic\Elements\Promos\Elements\ElementPromos` | `dynamic/silverstripe-elemental-promos` | child loop: `$PromoList` |
| `PromoBlock` (custom) | `App\Elements\ElementPromo` | project-local | child loop: `$PromoItems` |
| `PageSectionBlock` | `Dynamic\Elements\Features\Elements\ElementFeatures` | `dynamic/silverstripe-elemental-features` | child loop: `$FeatureList`, items are `FeatureObject` |
| `PageSectionBlock` (custom) | `App\Elements\ElementPageSection` | project-local | child loop: `$PageSections` |
| `PhotoGalleryBlock` | `Dynamic\Elements\Gallery\Elements\ElementPhotoGallery` | `dynamic/silverstripe-elemental-gallery` | children: `$Images`, model `GalleryImage` |
| `AccordionBlock` | `Dynamic\Elements\Accordion\Elements\ElementAccordion` | `dynamic/silverstripe-elemental-accordion` | children: `$AccordionPanels` |
| `EmbedCodeBlock` | `Dynamic\Elements\Embedded\Elements\ElementEmbeddedCode` | `dynamic/silverstripe-elemental-embedded-code` | `$EmbedCode` → `$Code` |
| `CustomerServiceBlock` | `Dynamic\Elements\CustomerService\Elements\ElementCustomerService` | `dynamic/silverstripe-elemental-customer-service` | same field names |
| `ImageBlock` | `Dynamic\Elements\Image\Elements\ElementImage` | `dynamic/silverstripe-elemental-image` | `$BlockImage` → `$Image` |
| `RecentBlogPostsBlock` | `Dynamic\Elements\Blog\Elements\ElementBlogPosts` | `dynamic/silverstripe-elemental-blog` | controller method renamed |
| `StaffMemberBlock` | custom `App\Elements\ElementStaffMember` | project-local | `$Staff` → `$StaffMember` |
| `UpcomingEventsBlock` | custom `App\Elements\ElementEvents` | project-local | `$Events` → controller method |
| `SlideshowBlock` / `FlexSlider SlideImage` | custom `App\Elements\ElementHero` (**one Element per slide**) | project-local | flatten: hero per slide, not per block |
| `FormBlock` | `DNADesign\Elemental\Models\ElementContent` (HTML embed) | n/a | no direct equivalent |

## Skip these (no SS4 equivalent)

| Block class | Reason |
|-------------|--------|
| `ChildPagesBlock` | SS4 has menus + sitetree; render in page template instead |
| `RecentBlogPostsBlock` (some projects) | Use controller method + custom element, or skip |
| `SectionNavigationBlock` | Render in page template via section nav include |
| `EmbedCodeBlock` (sometimes) | Skip if `dynamic/silverstripe-elemental-embedded-code` won't be installed |

## Notes on the field renames

The most common source of "I migrated the block but the template shows blank" bugs is a missed field rename:

- **ContentBlock `$Content` → ElementContent `$HTML`** — this is the big one. The CMS field is still called "Content" but the template variable is `$HTML`. Failure to rename causes a silent blank.
- **EmbedCodeBlock `$EmbedCode` → ElementEmbeddedCode `$Code`** — easy to miss.
- **Title rendering** — SS3 templates often did `<% if $Title %><h3>$Title</h3><% end_if %>`. Elemental adds a `ShowTitle` boolean (defaults `false` in the CMS unless explicitly enabled). The Elemental-correct guard is `<% if $ShowTitle && $Title %>`. The migration task should set `ShowTitle = 1` when the legacy block had a non-empty title.

## Notes on shared blocks

In SS3, `SiteTree_Blocks` allowed the same `BlockID` to attach to multiple pages (shared blocks). In SS4 Elemental, each Element belongs to exactly one ElementalArea. The migration task should treat each row in `SiteTree_Blocks` as one Element placement, cloning the block data per page. The skeleton's `migratePlacement()` handles this via a `static $seen` set that detects re-uses for the cloning stat.

## Notes on custom vs Dynamic-module elements

Two of the three reference projects chose differently:
- **rockline-iatric** + **youth-sailing** use the `dynamic/silverstripe-elemental-*` composer modules
- **safeharbor** uses custom `App\Elements\*` classes (no dependency on the dynamic modules)

The custom approach has more control over HTML but reproduces work the Dynamic modules already do. Default to the Dynamic modules unless:
- The project already has heavy custom data on its blocks that wouldn't map cleanly
- The visual difference between block and dynamic-module element is large enough that template parity gets harder, not easier
- The team prefers no extra composer dependencies

## How to find more block types in a project

```sql
SELECT ClassName, COUNT(*) AS instances
FROM Block
GROUP BY ClassName
ORDER BY instances DESC;
```

If you see a class not in this catalog, common patterns:
- Project-local custom block (`App\Blocks\WhateverBlock`) → likely needs a custom Element
- SheaDawson/blocks add-on → check Packagist for `sheadawson/silverstripe-blocks-*`
- Dynamic blocks add-on → check Packagist for `dynamic/dynamic-blocks-*` and look for a `dynamic/silverstripe-elemental-*` equivalent

Document new mappings back into this catalog when you find them.
