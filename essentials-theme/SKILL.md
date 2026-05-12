---
name: Essentials Theme Customization
description: Create and customize subthemes extending silverstripe-essentials-theme
---

# Essentials Theme Customization Skill

This skill guides you through creating subthemes that extend `silverstripe-essentials-theme`.

## Overview

All Essentials sites use `silverstripe-essentials-theme` as the base theme. Per-project customization is done via:
1. **Subtheme** - A child theme that extends the base
2. **CSS Variables** - Override `--es-` prefixed variables
3. **Bootstrap Variables** - Override Bootstrap 5 SCSS variables

## Base Theme Location

```
vendor/dynamic/silverstripe-essentials-theme/
├── src/scss/           # Source SCSS files
├── dist/               # Compiled CSS
├── templates/          # SS templates
└── docs/               # Documentation
    ├── css-variables.md      # CSS variable reference
    └── testing.md            # Test suite docs
```

## Theme Configuration

In `app/_config/theme.yml`:
```yaml
SilverStripe\View\SSViewer:
  themes:
    - '$public'
    - 'my-subtheme'                    # Your subtheme first
    - 'silverstripe-essentials-theme'  # Base theme as fallback
    - '$default'
```

## Creating a Subtheme

### 1. Create Theme Directory

```
themes/my-subtheme/
├── src/
│   └── scss/
│       ├── _variables.scss    # Bootstrap overrides
│       └── main.scss          # Main entry point
├── templates/                 # Override SS templates
├── package.json
└── webpack.config.js
```

### 2. Variable Override Structure

In `main.scss`:
```scss
// Import Bootstrap variables BEFORE functions
@import "variables";

// Import essentials theme base (brings in Bootstrap)
@import "../../../vendor/dynamic/silverstripe-essentials-theme/src/scss/main";

// Override CSS custom properties
:root {
  --es-element-top-title-color: #FF6B35;
  --es-carousel-indicators-active-bg: #FF6B35;
}
```

### 3. Bootstrap Variable Overrides

In `_variables.scss`:
```scss
// Brand colors
$primary: #FF6B35;
$secondary: #2E4057;

// Typography
$font-family-base: 'Inter', sans-serif;
$font-family-heading: 'Playfair Display', serif;

// Spacing
$spacer: 1rem;
```

## CSS Variables Reference

All theme variables use the `--es-` prefix. Key categories:

| Category | Prefix | Example |
|----------|--------|---------|
| Element container | `--es-element-` | `--es-element-padding-top` |
| Typography | `--es-element-title-` | `--es-element-title-color` |
| Carousel | `--es-carousel-` | `--es-carousel-control-bg` |
| Testimonials | `--es-testimonials-` | `--es-testimonials-name-color` |

Full reference: [css-variables.md](docs/css-variables.md)

## Build Commands

```bash
cd themes/my-subtheme
npm install
npm run build      # Production build
npm run dev        # Development with watch
npm run lint       # Run linting
```

## Testing

The base theme includes visual regression tests (run from project root):
```bash
cd vendor/dynamic/silverstripe-essentials-theme
npm test                 # Run all tests
npm run test:baseline    # Update baselines
npm run test:navbar      # Navbar-specific tests
```

## Component Styling Architecture & Workflows

A common pitfall when styling Essentials components is trying to override Bootstrap's native CSS custom properties directly on the block level (e.g., adding `--bs-card-border-radius: 0;` inside `.element-elementcard`).

**This circumvents the intended architecture and will lead to styling conflicts!**

### The Base Theme Architecture
The base theme actually utilizes Bootstrap for its grid and skeleton, but **purposefully nullifies Bootstrap's visual styles** (e.g., zeroing out `--bs-card-border-radius` and `--bs-card-bg` inside `.card`) to provide a blank slate. 

It then maps its own `--es-` CSS variables onto these components with fallback properties referencing Bootstrap's keys. For example, in the base theme's `_card.scss`:
```scss
--es-element-card-border-radius: var(--bs-card-border-radius, 0.375rem);

.card {
    border-radius: var(--es-element-card-border-radius);
}
```

### The Correct Workflow for Styling
When you need to override component styles (like sharpening card borders or changing CTA backgrounds), you have two valid paths:

1. **Compile-time structure (Global)**: We override Bootstrap's native `$variable` SCSS syntax inside `src/scss/_variables.scss` (e.g., `$card-border-radius: 0;`). The base theme natively inherits these when compiling the SCSS.
2. **Runtime styling (Targeted/Component)**: We handle specific elements or CMS-injected styles using the `--es-` namespace keys in `src/scss/styles.scss` (e.g., setting `--es-element-card-border-radius: 0;` mapped to `.element-elementcard`, or globally via `:root`).

Do not directly inject `--bs-*` variables in your subtheme component configurations.

## Best Practices

1. **Never modify base theme** - All changes go in subtheme
2. **Use ES CSS variables** (`--es-*`) for runtime/component customization in `styles.scss`
3. **Use Bootstrap SCSS variables** (`$variable`) for compile-time/global layout customization in `_variables.scss`
4. **Never directly override `--bs-*` properties** on components
5. **Keep templates minimal** - Only override what's necessary
6. **Test responsively** - Base theme is mobile-first
