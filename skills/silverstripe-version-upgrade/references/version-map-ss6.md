## Quick Reference: SS5 → SS6 Version Map

### Dynamic Elemental Packages

| Package | SS6 Version |
|---------|-------------|
| `dynamic/recipe-silverstripe-base-site` | ^6.0 |
| `dynamic/silverstripe-elemental-accordion` | ^6.0 |
| `dynamic/silverstripe-elemental-blog` | ^4.0 |
| `dynamic/silverstripe-elemental-customer-service` | ^4.0 |
| `dynamic/silverstripe-elemental-embedded-code` | ^4.0 |
| `dynamic/silverstripe-elemental-features` | ^6.0 |
| `dynamic/silverstripe-elemental-filelist` | ^4.0 |
| `dynamic/silverstripe-elemental-image` | ^4.0 |
| `dynamic/silverstripe-elemental-promos` | ^6.0 |
| `dynamic/silverstripe-elemental-sponsors` | ^5.0 |
| `dynamic/silverstripe-elemental-timeline` | ^5.0 |
| `dynamic/silverstripe-elemental-flexslider` | ^3.0 |
| `dynamic/silverstripe-elemental-stat-counters` | ^4.0 |
| `dynamic/silverstripe-elemental-testimonials` | ^4.0 |
| `silverstripe/elemental-bannerblock` | ^4.0 |
| `dnadesign/silverstripe-elemental-userforms` | ^5.0 |
| `dnadesign/silverstripe-elemental-virtual` | ^3.0 |

### Essentials & Other Dynamic Packages

| Package | SS6 Version | Branch |
|---------|-------------|--------|
| `dynamic/flexslider` | ^6.0 | |
| `dynamic/silverstripe-locator` | ^6.0 | |
| `dynamic/silverstripe-site-notifications` | ^3.0 | |
| `dynamic/recipe-silverstripe-essentials-website` | ^3.0 | 3 |
| `dynamic/recipe-silverstripe-essentials-fixtures` | ^3.0 | 3 |
| `dynamic/silverstripe-essentials-tools` | ^3.0 | 3 |
| `dynamic/silverstripe-essentials-theme` | main | main |
| `dynamic/silverstripe-elemental-baseobject` | ^6.0 | 6 |
| `dynamic/silverstripe-elemental-templates` | ^6.0 | 6 |
| `dynamic/silverstripe-base-site` | ^8.0 | 8 |
| `dynamic/silverstripe-carousel` | main | main |
| `dynamic/silverstripe-svg-image` | main | main |
| `dynamic/silverstripe-bootstrap-forms` | main | main |
| `dynamic/silverstripe-thereisnouserform` | main | main |
| `dynamic/silverstripe-media-field` | main | main |

### Third-Party Packages

| Package | SS6 Version |
|---------|-------------|
| `silverstripe/recipe-cms` | ^6.0 [^1] |
| `silverstripe/login-forms` | ^6.0 |
| `silverstripe/recipe-plugin` | ^2.0 |
| `innoweb/silverstripe-page-icons` | ^4.0 |
| `innoweb/silverstripe-social-metadata` | ^9.0 |
| `jonom/focuspoint` | ^6.0 |
| `silverstripe/linkfield` | ^4.0 |
| `fromholdio/silverstripe-embedfield` | ^5.1 |

[^1]: `silverstripe/recipe-cms ^6.0` is the Composer constraint. The recipe branch for SS6 is `3` (recipe-cms 3.x tracks CMS 6.x). This follows the same pattern as SS5 where recipe-cms `^5.0` corresponded to branch `2`.

### Removed / Replaced Packages (No SS6 Version)

| Package | Replacement |
|---------|-------------|
| `lekoala/silverstripe-debugbar` | None (remove) |
| `sheadawson/silverstripe-linkable` | `silverstripe/linkfield` ^4.0 |
| `nathancox/embedfield` | `fromholdio/silverstripe-embedfield` ^5.1 |
| `undefinedoffset/silverstripe-nocaptcha` | None (remove) |

---

## Namespace Reference (SS6)

| Package | Namespace |
|---------|-----------|
| Silverstripe CMS | `SilverStripe\CMS\Model\*` |
| Silverstripe Framework | `SilverStripe\*` |
| Elemental | `DNADesign\Elemental\Models\*` |
| Dynamic Elements | `Dynamic\Elements\*\Elements\*` |
| LinkField | `SilverStripe\LinkField\Models\*` |
| Dynamic Base | `Dynamic\Base\*` |
| Dynamic Site Tools | `Dynamic\SiteTools\*` |
| Dynamic FlexSlider | `Dynamic\FlexSlider\*` |
| Dynamic Locator | `Dynamic\Locator\*` |

> Namespaces are unchanged from SS5. The upgrade is primarily a version bump with API refinements and dependency upgrades — no PSR-4 namespace migration is required.

---

## Template Sorting Syntax

When using LinkField in templates, use explicit sort syntax:

```html
<%-- Explicit sort direction for clarity --%>
<% loop $NavigationLinks.Sort('Sort', 'ASC') %>
    <li><a href="$URL" title="Go to the $Title page">$Title</a></li>
<% end_loop %>
```

## PHP Version Requirement

- **SS6 requires PHP ^8.3**
- The `silverstripe/recipe-plugin` stays at ^2.0 (no breaking change)

---

## Key Dependency Changes (SS5 → SS6)

| Change | Detail |
|--------|--------|
| `silverstripe/linkfield` becomes required | Replaces `sheadawson/silverstripe-linkable` — template API differences |
| `silverstripe/recipe-cms` | Bumped to ^6.0 |
| PHP minimum | 8.1 → 8.3 |
| TinyMCE | Updated to version 7 |
| DDEV socket path | `/var/run/mysqld/mysqld.sock` (new in SS6 Docker images) |
