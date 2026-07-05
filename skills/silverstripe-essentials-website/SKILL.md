---
name: silverstripe-essentials-website
description: Technical reference for building and modifying Dynamic's Silverstripe Essentials websites — element inventory, namespaces, config patterns, color system, layout grid, and template presets
---

# Silverstripe Essentials Website — Technical Reference

This skill covers **Dynamic's Silverstripe Essentials** product — a rapid-deployment CMS platform built on Silverstripe 5 with `dynamic/recipe-silverstripe-essentials-website`.

## Recipe Architecture

Essentials sites use a layered recipe pattern:

```
silverstripe/recipe-cms (SS5 core)
  └─ dynamic/recipe-silverstripe-essentials-website (meta-recipe, ~34 packages)
      ├─ dynamic/silverstripe-base-site (~7.x) — Page types, base models
      ├─ dynamic/silverstripe-site-tools — Shared extensions/utilities
      ├─ dynamic/silverstripe-essentials-tools — Elements, extensions, services
      ├─ dynamic/silverstripe-essentials-theme — Base theme (Webpack)
      ├─ 16 dynamic/silverstripe-elemental-* modules
      └─ 3rd-party: elemental-grid, bootstrap-forms, social-metadata, blog, userforms
```

Three dev-channel packages are typically pulled from private VCS repos:
- `dynamic/silverstripe-elemental-templates`
- `dynamic/silverstripe-essentials-theme`
- `dynamic/silverstripe-essentials-tools`

## Key Namespaces

| Package | Namespace |
|---------|-----------|
| Base site pages | `Dynamic\Base\Page\*` (HomePage, BlockPage, CampaignLandingPage, SearchPage) |
| Base extensions | `Dynamic\Base\Extension\*` (TemplateDataExtension, CmsDesignDataExtension, SeoExtension) |
| Site tools | `Dynamic\SiteTools\Extension\*` (HeaderImageExtension, PreviewExtension, ContactDataExtension) |
| Essentials tools | `Dynamic\Essentials\*` (Extensions, Elements, Models, Admin) |
| Essentials services | `Dynamic\SilverStripeEssentialsTools\Service\*` (ColorConfigurationProvider) |
| Elemental modules | `Dynamic\Elements\{ModuleName}\Elements\*` (exceptions: Testimonials uses `Dynamic\Elements\Elements\*`, Embedded Code uses `Dynamic\Elements\Embedded\Elements\*`, Call to Action uses the `CTA` segment) |

## Elemental Blocks (20+ Types)

### Standard Elements (from recipe)

| CMS Name | Class | Package |
|----------|-------|---------|
| Text | `DNADesign\Elemental\Models\ElementContent` | `dnadesign/silverstripe-elemental` |
| Accordion | `Dynamic\Elements\Accordion\Elements\ElementAccordion` | `dynamic/silverstripe-elemental-accordion` |
| Blog Posts | `Dynamic\Elements\Blog\Elements\ElementBlogPosts` | `dynamic/silverstripe-elemental-blog` |
| Call to Action | `Dynamic\Elements\CTA\Elements\ElementCallToAction` | `dynamic/silverstripe-elemental-call-to-action` |
| Card | `Dynamic\Elements\Card\Elements\ElementCard` | `dynamic/silverstripe-elemental-card` |
| Carousel | `Dynamic\Elements\Carousel\Elements\ElementCarousel` | `dynamic/silverstripe-elemental-carousel` |
| Customer Service | `Dynamic\Elements\CustomerService\Elements\ElementCustomerService` | `dynamic/silverstripe-elemental-customer-service` |
| Embedded Code | `Dynamic\Elements\Embedded\Elements\ElementEmbeddedCode` | `dynamic/silverstripe-elemental-embedded-code` |
| Photo Gallery | `Dynamic\Elements\Gallery\Elements\*` | `dynamic/silverstripe-elemental-gallery` |
| Image | `Dynamic\Elements\Image\Elements\*` | `dynamic/silverstripe-elemental-image` |
| Links | `Dynamic\Elements\Links\Elements\*` | `dynamic/silverstripe-elemental-links` |
| Media (oEmbed) | `Dynamic\Elements\Oembed\Elements\ElementOembed` | `dynamic/silverstripe-elemental-oembed` |
| Sponsors | `Dynamic\Elements\Sponsors\Elements\ElementSponsor` | `dynamic/silverstripe-elemental-sponsors` |
| Stat Counters | `Dynamic\Elements\StatCounters\Elements\ElementStatCounters` (model: `Dynamic\Elements\StatCounters\Model\StatCounter`) | `dynamic/silverstripe-elemental-stat-counters` |
| Testimonials | `Dynamic\Elements\Elements\ElementTestimonials` (models: `Dynamic\Elements\Model\*`) | `dynamic/silverstripe-elemental-testimonials` |
| Row | `WeDevelop\ElementalGrid\Models\ElementRow` | `wedevelopnl/silverstripe-elemental-grid` |
| Form | `DNADesign\ElementalUserForms\Model\ElementForm` | `dnadesign/silverstripe-elemental-userforms` |
| Virtual | `DNADesign\ElementalVirtual\Model\ElementVirtual` | `dnadesign/silverstripe-elemental-virtual` |

### Essentials-Only Elements (from essentials-tools)

| CMS Name | Class |
|----------|-------|
| Simple Content | `Dynamic\Essentials\Element\SimpleContent` |
| Hero Media | `Dynamic\Essentials\Element\HeroMedia` |
| Staff | `Dynamic\Essentials\Element\ElementStaff` |

### Essentials Models

| Model | Purpose |
|-------|---------|
| `Dynamic\Essentials\Model\StaffMember` | Team member profiles |
| `Dynamic\Essentials\Model\Logo` | Logo/brand assets |
| `Dynamic\Essentials\Model\ContentItem` | Generic content items |
| `Dynamic\Essentials\Admin\StaffAdmin` | Staff ModelAdmin |

## Configuration Architecture

### Config File Ordering

| File | Purpose | Load Order |
|------|---------|------------|
| `essentials.yml` | Extension registry for all element types | After `socialmeta`, `elemental`, `elementalgrid` |
| `essentials-styles.yml` | Color system, feature flags | After `essentials-config` |
| `theme.yml` | Theme cascade | — |
| `mysite.yml` | Project manifest | — |
| `mailer.yml` | SMTP / SendGrid config | — |
| `mimevalidator.yml` | Upload validation | — |
| `myspamprotection.yml` | Form spam protection | — |
| `google-api.yml` | Google API keys | — |

### essentials.yml Pattern

The `essentials.yml` is the **central extension registry**. It:
1. Applies `BaseElementExtension` and `CustomStylesExtension` to `BaseElement`
2. Registers element-specific extensions (e.g., `ElementCardExtension` on `ElementCard`)
3. Configures page type extensions (Elemental, UserForms)
4. Sets `disallowed_elements` on `Page`
5. Extends `SiteConfig` with `TemplateDataExtension` and `SiteConfigExtension`

### essentials-styles.yml Pattern

Configures the `ColorConfigurationProvider` service with:
- **`background_colors`**: Array of hex colors (typically 3-5)
- **`button_colors`**: Nested per-background button/text CSS variable combos
- **Feature flags on BaseElement** (default off, enabled per element):
  - `enable_background_color`, `enable_button_color`
  - `enable_padding`, `enable_margin`
  - `enable_corner_rounding`, `enable_image_rounding`
  - `enable_image_width`, `enable_content_size`
  - `enable_background_image`, `enable_top_title`

Example per-element override:
```yaml
Dynamic\Elements\Card\Elements\ElementCard:
  enable_background_color: true
  enable_button_color: true
  enable_image_rounding: true
  enable_corner_rounding: true
```

## Theme Architecture

Three-layer cascade:
```yaml
SilverStripe\View\SSViewer:
  themes:
    - '$public'
    - '{client}-subtheme'        # Client overrides
    - 'silverstripe-essentials-theme'  # Base essentials theme
    - '$default'
```

Both themes use **Webpack** (SCSS + JS → dist). The subtheme contains:
- `src/scss/` — Client SCSS overrides
- `src/js/` — Client JS
- `templates/Includes/` — Template overrides

## Layout Grid System

Elements use a **12-column grid** via the Elemental Grid module:
- **Size MD** dropdown on each element: `Column 12/12` (full), `6/12` (half), `4/12` (third), `3/12` (quarter)
- **Offset MD** to push elements right
- **Row element** (`wedevelopnl/silverstripe-elemental-grid`):
  - **CRITICAL ARCHITECTURE NOTE**: `ElementRow` does NOT act as a parent element and there is NO physical nesting of child blocks inside it in the CMS.
  - It functions purely as a structural divider. When rendered, it literally just outputs the closing tags for the previous container and opens a new `<div class="container">` (or `<div class="container-fluid">` if `IsFluid` is checked).
  - Rows do NOT control the column widths of subsequent elements — each element manages its own `SizeMD`. To make elements stack full-width below a row, they must be set to `12/12`.

### Common Layouts
- **2-column**: Two elements at `6/12`
- **3-column card grid**: Three Cards at `4/12`, optionally in a Row with background
- **Hero section**: HeroMedia at full width
- **Asymmetric**: `4/12` + `8/12` or `3/12` + `9/12`

## Element Templates (Presets)

The `dynamic/silverstripe-elemental-templates` module provides reusable block presets:
- Templates are managed in CMS under **Element Templates**
- Applied during page creation (Step 3 dropdown) or via Settings → More Options → "Apply Blocks Template"
- Created from existing page layouts via More Options → "Create Blocks Template"
- Templates are copies — changes don't affect the source

## Typical App Layer

Essentials sites have a **minimal app layer**:
- `app/src/Page.php` — Empty `SiteTree` extension
- `app/src/PageController.php` — Empty controller
- `app/_config/` — Config YAML files only
- All functionality lives in recipe vendor modules

## CLI Commands

```bash
# Dev/build
ddev sake dev/build "flush=1"

# Populate fixtures
ddev sake dev/tasks/populate

# Export elemental templates
ddev sake dev/tasks/ExportElementalTemplatesTask
```

## Essentials Fixtures Recipe

**Repo**: `dynamic/recipe-silverstripe-essentials-fixtures` (GitHub, default branch: `main`)

Provides fixture-driven demo content for Essentials sites via Silverstripe Populate. Install with `--prefer-source` to get the git repo for PRs.

```bash
ddev composer require dynamic/recipe-silverstripe-essentials-fixtures --prefer-source
```

### Fixture Files

| File | Size | Purpose |
|------|------|---------|
| `app/fixtures/element-templates.yml` | ~98KB | Pre-built element template presets |
| `app/fixtures/site-demo-content.yml` | ~108KB | Full site demo pages, elements, and content |
| `app/fixtures/template-preview-images.yml` | ~10KB | Preview images for template selector |
| `app/fixtures/images/` | — | Stock images used by fixtures |

### Key Components

- **`src/Extensions/FixtureRecordExtension.php`** — Extension for managing fixture records
- **`_config/config.yml`** — Populate configuration

### Usage

```bash
# After installing, dev/build to register config
ddev sake dev/build "flush=1"

# Run populate to load fixture content
ddev sake dev/tasks/populate
```

> **Note**: Populate reloads fixture content on each run. Editorial changes made in the CMS will be overwritten when Populate runs again.

---

## Subtheme Starter

**Repo**: `dynamic/silverstripe-essentials-subtheme-starter` (GitHub)

The starter template for creating client-specific subthemes. Each Essentials project gets a subtheme (e.g., `skanaaluminum-subtheme`) cloned from this starter.

### Setup Workflow

1. Clone into `themes/`:
   ```bash
   cd themes/
   git clone git@github.com:dynamic/silverstripe-essentials-subtheme-starter.git {client}-subtheme
   cd {client}-subtheme
   rm -rf .git  # Remove starter history
   ```
2. `npm install`
3. Configure `theme.yml` with subtheme before parent theme
4. Expose dist folder in project `composer.json`:
   ```json
   { "extra": { "expose": ["themes/{client}-subtheme/dist"] } }
   ```
5. Run `ddev composer vendor-expose`
6. Update `templates/Includes/Requirements.ss` with correct theme folder name
7. Customize `src/scss/styles.scss` with brand CSS variables

### File Structure

```
{client}-subtheme/
├── src/
│   ├── js/main.js           # JS entry point (imports styles)
│   └── scss/styles.scss     # All CSS variable overrides
├── dist/                    # Compiled output (gitignored)
│   ├── css/main.bundle.css
│   └── js/main.bundle.js
├── templates/
│   └── Includes/
│       └── Requirements.ss  # CSS/JS asset includes
├── package.json
└── webpack.config.js
```

### NPM Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Development build with watch mode |
| `npm run build` | Production build (minified) |
| `npm run lint:styles` | Check SCSS for issues |
| `npm run lint:styles:fix` | Auto-fix SCSS issues |

### CSS Variable System (`--es-*` prefix)

The subtheme overrides CSS custom properties defined by the parent essentials theme. All variables use the `--es-` prefix.

#### Override Categories in `styles.scss`

| Section | Description |
|---------|-------------|
| Brand Colors | Define color palette (`--brand-primary`, `--brand-secondary`, `--brand-dark`) |
| Typography | Font families and text styling |
| Global Navigation | Navbar, dropdowns, mobile menu |
| Footer | Main footer, copyright bar, social icons |
| Buttons | Primary and alternate button styles |
| Global Elements | Titles, content, cards, containers |
| Carousel Controls | Controls, indicators, slides |
| Forms | Input borders, radius |
| Element Overrides | Block-specific customizations |

#### Common CSS Variables Quick Reference

```scss
// Navigation
--es-main-nav-bg-color
--es-navbar-nav-link-color
--es-navbar-nav-link-hover-color
--es-dropdown-menu-bg-color

// Footer
--es-footer-main-bg
--es-footer-copyright-bg
--es-footer-social-icon-bg

// Buttons
--es-element-btn-background-color
--es-element-btn-color
--es-element-btn-border-radius

// Typography
--es-element-top-title-color
--es-element-title-color
--es-element-content-color

// Cards & Containers
--es-element-inner-bg-color
--es-element-card-border-radius
--es-element-card-box-shadow
```

### Template Overrides

To override parent theme templates, mirror the directory structure:
```
templates/
└── SilverStripe/
    └── CMS/
        └── Model/
            └── Page.ss  # Overrides parent Page.ss
```

### Troubleshooting

| Styles not applying | Check theme order in `theme.yml` (subtheme before parent), run `?flush=1` |
| Assets 404 | Run `ddev composer vendor-expose`, check `expose` in `composer.json` |
| CSS variables not overriding | Check exact variable names (case-sensitive), ensure `:root` scope |
| Build errors | Delete `node_modules` and `npm install`, verify Node.js v20.x |
| ContentAlign / CustomStyles not working | 1) Ensure the feature flag (e.g., `enable_content_align`) is `true` for the element class in `essentials-styles.yml`. 2) Check for the upstream SCSS bug where `silverstripe-essentials-theme` incorrectly references `var(--content-align)` instead of `var(--es-element-content-align)`. Override this in the subtheme's `styles.scss` for `.element-simplecontent` and `.element-elementcontent`. |

---

## Silverstripe 6 Greenfield Setup

The `dynamic/recipe-silverstripe-essentials-website` package has a full SS6 release line (`^3.0`, branch `3`). Several non-obvious gotchas apply to a greenfield SS6 install:

### 1. Recipe is not on Packagist — use `--repository`

`composer create-project` fails without an explicit VCS repository pointer. Run `ddev auth ssh` first so Composer can reach the private GitHub repo, then:

```bash
ddev auth ssh
ddev composer create-project \
  --repository='{"type":"vcs","url":"git@github.com:dynamic/recipe-silverstripe-essentials-website.git"}' \
  dynamic/recipe-silverstripe-essentials-website . "^3.0"
```

### 2. `MySQLPDODatabase` does not exist in SS6

SS6 ships only the `MySQLDatabase` connector. Using the SS5 class name causes `dev/build` to crash with `InjectorNotFoundException`. Ensure your `.env` sets:

```
SS_DATABASE_CLASS="MySQLDatabase"
```

### 3. `ddev sake` requires project type `silverstripe`

Set `type: silverstripe` in `.ddev/config.yaml` to enable `ddev sake`. With `type: php` (the generic default), `ddev sake` is unavailable.

As a one-off fallback before changing the project type:

```bash
ddev exec 'vendor/bin/sake dev/build flush=1'
```

### 4. Version map corrections (recipe is authoritative)

The version map reference doc lists some packages at incorrect versions for SS6. The recipe pins are authoritative — do not manually require individual elemental modules, as the recipe resolves them transitively.

| Package | Version map says | Actual pin (SS6) |
|---------|-----------------|------------------|
| `dynamic/silverstripe-essentials-theme` | `main` | `^2@dev` (branch `2`) |
| `dynamic/silverstripe-elemental-templates` | `^6.0` | `^3@dev` |

### 5. TinyMCE: do not require it explicitly in Essentials SS6 projects

In an Essentials SS6 recipe project, `silverstripe/htmleditor-tinymce` already arrives through the recipe's dependency chain — adding it as an explicit dependency causes version conflicts. Verify with `composer why silverstripe/htmleditor-tinymce` (it should be pulled in transitively).

This is the opposite of the guidance for non-Essentials SS6 upgrades, where the package must be explicitly required (see the `silverstripe-version-upgrade` skill, "TinyMCE extraction"). Either way the end state is the same: the package must be installed, or CMS Content fields silently degrade to plain textareas (`data-editor="textarea"`).

### Confirmed working versions (SS6)

| Package | Version |
|---------|---------|
| `silverstripe/framework` | 6.2.0 |
| `silverstripe/recipe-cms` | 6.2.0 |
| `dnadesign/silverstripe-elemental` | 6.2.1 |
| `dynamic/silverstripe-essentials-tools` | 3.0.3 |
| `dynamic/silverstripe-essentials-theme` | 2.0.1 |
| `dynamic/silverstripe-base-site` | 8.0.3 |
| `dynamic/silverstripe-elemental-templates` | 3.0.2 |

---

## New Essentials Project Setup

Quick checklist for bootstrapping a new Essentials site:

1. Install recipe: `ddev composer require dynamic/recipe-silverstripe-essentials-website`
2. Clone subtheme starter (see above)
3. Configure `theme.yml`, `essentials.yml`, `essentials-styles.yml`
4. Optionally install fixtures: `ddev composer require dynamic/recipe-silverstripe-essentials-fixtures --prefer-source`
5. `ddev sake dev/build "flush=1"`
6. `ddev sake dev/tasks/PopulateTask flush=1` (if fixtures installed)
7. Customize `essentials-styles.yml` with client colors
8. Customize `src/scss/styles.scss` in subtheme with brand variables

### Troubleshooting PopulateTask

When running `PopulateTask`, you may encounter three common issues depending on the versions of `silverstripe-populate` and PHP:

| Problem | Cause | Workaround / Solution |
|---------|-------|-----------------------|
| `API access denied` from Geocoder | GoogleGeocoder runs even if API key is empty string | Disable geocoding globally during dev by adding `disable_geocoding: true` to `SilverStripe\ORM\DataObject` in a dev-only config file. |
| `No fixture definitions found for "=>SilverStripe\Assets\Image.xxx"` | `PopulateFactory::populateFile` returns `true` instead of the file object if hashes match, corrupting the fixture map | Manually patch `vendor/dnadesign/silverstripe-populate/code/PopulateFactory.php` to `return $file;` instead of `return true;`. |
| `TypeError: substr(): Argument #1 must be string, array given` | Missing module classes fallback to raw SQL insertion, triggering an array parse failure in PHP 8 | Manually patch `parseValue` in `SilverStripe\Dev\FixtureFactory` to check `is_string($value)` before `substr`, OR wrap `createRaw` in a try/catch in `SilverStripe\Dev\YamlFixture`. |

---

## What's Included vs Custom

### Included in Base Essentials ($10K base + $100/mo)
- 20+ standard element types
- Block-based page editing with drag-and-drop
- Pre-built page templates and fixture-driven content
- Per-element design controls (colors, spacing, rounding, responsive)
- Forms (UserForms), Blog, Analytics, SEO fields
- Up to 10 pages populated

### Requires Custom Development
- Events/Calendar, Job Listings, E-commerce
- CRM integrations (Salesforce, HubSpot APIs)
- Member portals, SSO
- Custom data models, advanced search
- Schema markup, advanced analytics/GTM
