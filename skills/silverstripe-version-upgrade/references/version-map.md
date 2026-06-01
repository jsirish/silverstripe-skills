## Quick Reference: SS4 → SS5 Version Map

### Dynamic Elemental Packages

| Package | SS5 Version |
|---------|-------------|
| `dynamic/recipe-silverstripe-base-site` | ^5.0 |
| `dynamic/silverstripe-elemental-accordion` | ^5.0 |
| `dynamic/silverstripe-elemental-blog` | ^3.0 |
| `dynamic/silverstripe-elemental-customer-service` | ^3.0 |
| `dynamic/silverstripe-elemental-embedded-code` | ^3.0 |
| `dynamic/silverstripe-elemental-features` | ^5.0 |
| `dynamic/silverstripe-elemental-filelist` | ^3.0 |
| `dynamic/silverstripe-elemental-image` | ^3.0 |
| `dynamic/silverstripe-elemental-promos` | ^5.0 |
| `dynamic/silverstripe-elemental-sponsors` | ^4.0 |
| `dynamic/silverstripe-elemental-timeline` | ^4.0 |
| `dynamic/silverstripe-elemental-flexslider` | ^2.0 |
| `dynamic/silverstripe-elemental-stat-counters` | ^3.0 |
| `dynamic/silverstripe-elemental-testimonials` | ^3.0 |
| `silverstripe/elemental-bannerblock` | ^3.0 |
| `dnadesign/silverstripe-elemental-userforms` | ^4.0 |
| `dnadesign/silverstripe-elemental-virtual` | ^2.0 |

### Other Dynamic Packages

| Package | SS5 Version |
|---------|-------------|
| `dynamic/flexslider` | ^5.0 |
| `dynamic/silverstripe-locator` | ^5.0 |
| `dynamic/silverstripe-site-notifications` | ^2.0 |

### Third-Party Packages

| Package | SS5 Version |
|---------|-------------|
| `silverstripe/recipe-cms` | ^5.0 |
| `silverstripe/login-forms` | ^5.0 |
| `silverstripe/recipe-plugin` | ^2.0 |
| `innoweb/silverstripe-page-icons` | ^3.0 |
| `innoweb/silverstripe-social-metadata` | ^8.0 |
| `jonom/focuspoint` | ^5.0 |
| `undefinedoffset/silverstripe-nocaptcha` | ^2.4 |

### Removed Packages (No SS5 Version)

| Package | Replacement |
|---------|-------------|
| `lekoala/silverstripe-debugbar` | None (remove) |
| `ryanpotter/silverstripe-cms-theme` | Built-in CMS theme in SS5 |
| `fractas/elemental-stylings` | Native `styles` config in SS5 |
| `sheadawson/silverstripe-linkable` | `silverstripe/linkfield` or `gorriecoe/link` |

---

## Namespace Reference (SS5)

| Package | Namespace |
|---------|-----------|
| Silverstripe CMS | `SilverStripe\CMS\Model\*` |
| Silverstripe Framework | `SilverStripe\*` |
| Elemental | `DNADesign\Elemental\Models\*` |
| Dynamic Elements | `Dynamic\Elements\*\Elements\*` |
| LinkField | `SilverStripe\LinkField\Models\*` |
| gorriecoe Link | `gorriecoe\Link\Models\*` |
| Dynamic Base | `Dynamic\Base\*` |
| Dynamic Site Tools | `Dynamic\SiteTools\*` |
| Dynamic FlexSlider | `Dynamic\FlexSlider\*` |
| Dynamic Locator | `Dynamic\Locator\*` |

---

## Template Sorting Syntax

When using LinkField or gorriecoe Link in templates, use explicit sort syntax:

```html
<%-- Explicit sort direction for clarity --%>
<% loop $NavigationLinks.Sort('Sort', 'ASC') %>
    <li><a href="$URL" title="Go to the $Title page">$Title</a></li>
<% end_loop %>
```
