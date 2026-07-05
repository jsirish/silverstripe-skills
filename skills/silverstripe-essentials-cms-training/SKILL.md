---
name: silverstripe-essentials-cms-training
description: CMS editing reference for Silverstripe Essentials websites — element guide, layout patterns, design controls, templates, forms, blog, and site settings. Use when building CMS training content or explaining Essentials features.
---

# Silverstripe Essentials CMS Training Reference

This skill provides a complete CMS editing reference for Silverstripe Essentials websites. Use when:
- Generating CMS training documentation for clients
- Explaining how to use specific elements or features
- Troubleshooting CMS editing issues
- Building page layout guidance

Source documentation: the `docs/` directory (15 chapters) of the Essentials demo project, if a checkout is available locally (conventionally `~/Sites/essentials-demo/`). This skill is self-contained when it is not.

## CMS Fundamentals

### Login & Interface
- CMS login at `/admin`
- **Pages** section = site tree (page structure)
- **Files** section = media library (images, documents)
- **Settings** = global site configuration (SiteConfig)

### Draft/Publish Workflow
- **Save** = stores draft (only visible to editors)
- **Publish** = pushes to live site
- All changes are safe in draft — nothing goes live until published
- Use **Preview** to check layout before publishing

### Page Editing
- **Title** = page name in CMS
- **Navigation label** = what appears in menus
- **SEO fields** = meta description, etc.
- **Content tab** = where elemental blocks live

## Element Types Reference

### Text (`ElementContent`)
Rich text block with optional image and link. The most versatile element.
- **Fields**: Content (rich text)

### Simple Content
Lightweight text-only section.
- **Fields**: Content

### Card
Service/feature callout with image, title, description, button.
- **Fields**: Title, Description, Image, Image Position (Left/Right/Top), Link
- **Tip**: Set Size MD to `4/12` for 3-up layouts

### Call to Action
High-contrast CTA band with button.
- **Fields**: Content, CTA Link
- **Tip**: Use one clear action, avoid competing links

### Accordion
Expandable FAQ/content panels.
- **Fields**: Intro (optional), Panels list (Title, Image, Link, Content each)
- **Tip**: Keep panel titles short and scan-friendly

### Carousel
Image slider with captions and links.
- **Fields**: Content (optional intro), Slides list, Carousel Settings
- **Styles**: "Thumbs" (thumbnails, no text) or "Slides" (no text)

### Hero Media
Strong page header/hero with image or video.
- **Fields**: Type (Image/Video/External), Image, Image Mobile, Video/URL, Autoplay, Cinematic, Content overlay
- **Tip**: Always provide a quality image even for video (poster/fallback)

### Photo Gallery
Multiple images displayed as gallery with lightbox.
- **Fields**: Intro (optional), Images list (bulk upload available)

### Customer Service
Contact block for location/department.
- **Fields**: Location Name, Website, Phone, Email, Fax, Content

### Blog Posts
Listing of latest posts or category.
- **Fields**: Featured Blog, Category, Posts to show, Content intro
- **Note**: Falls back to latest posts if no blog selected

### Form (`ElementForm`)
Contact forms, enquiry forms, newsletter signup via UserForms.
- **Fields**: Form fields, recipients, settings
- **Tip**: Always test end-to-end after changes

### Staff
Team member profiles.
- **Fields**: Content (intro), Staff list (select/reorder)
- **Data**: Staff members managed under CMS **Staff** admin

### Stat Counters
Animated statistics/impact numbers.
- **Fields**: Content (intro), Stats list (Label, Number, Title, stat type)

### Testimonials
Client testimonial display.
- **Fields**: Number to show, Categories (optional), Content intro
- **Data**: Managed under CMS **Testimonials**

### Sponsors
Partner/sponsor logo grids.
- **Fields**: Number to show (0=all), Sponsors selection, Content

### Links
Curated link collections.
- **Fields**: Content (intro), Links list

### Media (oEmbed)
Video embed from YouTube/Vimeo.
- **Fields**: Embed video URL, Content

### Image
Stand-alone image block.
- **Fields**: Image (single upload/selection)

### Embedded Code
iframes, widgets, third-party embeds.
- **Fields**: Embed Code (HTML/JS), Content
- **Warning**: Only embed code from trusted sources

### Row
Container grouping elements, optionally full-width.
- **Fields**: Full width toggle, custom classes (advanced)
- **Important**: Rows do NOT control column widths — set Size MD on each child element

### Virtual
Reuse an existing element in multiple places.
- **Fields**: Linked element (the "original")
- **Warning**: Original must be published for Virtual to work on live site

## Layout Grid System

### Column Sizing (12-unit grid)
Set on each element via **Size MD** dropdown:
- `12/12` = Full width (100%)
- `6/12` = Half width (50%)
- `4/12` = One-third (33%)
- `3/12` = One-quarter (25%)

**Offset MD** shifts the element right.

### Common Layouts
- **2-column text + image**: Two elements at `6/12`
- **3-card features**: Three Cards at `4/12` in a Row
- **Asymmetric**: `4/12` sidebar + `8/12` main content
- **Hero + content**: HeroMedia at full width, then content blocks

### Responsive Breakpoints
| Breakpoint | Screen | Size |
|------------|--------|------|
| XS | Mobile phones | < 576px |
| SM | Portrait tablets | ≥ 576px |
| MD | Landscape tablets | ≥ 768px |
| LG | Desktops | ≥ 992px |
| XL | Large desktops | ≥ 1200px |

Common patterns:
- **Full mobile, half desktop**: XS 12/12, MD 6/12
- **Three columns desktop**: XS 12/12, MD 4/12
- **Hide on mobile**: XS Hidden, MD Visible

## Design Controls (Per-Element Tabs)

### Colors Tab
- **Background colors**: Visual swatches from site color palette (typically 3-5 colors)
- **Button colors**: Change based on selected background, tested for contrast
- **Text colors**: Auto-coordinated with background for accessibility
- **Unset**: "Unset Background Color" checkbox to remove

### Spacing Tab
- **Padding** (inside spacing): Top/Bottom — 0, 1rem (small), 2rem (medium), 3rem (large)
- **Margin** (outside spacing): Top/Bottom — creates separation between elements
- Use large padding for hero sections, medium for feature sections

### Rounding Tab
- Corner options: Top Left, Top Right, Bottom Right, Bottom Left
- Values: Default, None, Small (0.25rem), Medium (0.5rem), Large (1rem), Custom
- Best on: Cards, CTAs, Rows with backgrounds, Images

### Responsive Tab
- Per-breakpoint Size, Offset, Visibility controls
- Spacing overrides per breakpoint (padding/margin)

## Element Templates (Presets)

### Viewing Templates
- CMS menu → **Element Templates** → browse available presets with preview images

### Applying to New Page
1. Add new page → Step 3 → select template from dropdown → Create
2. Elements are independent copies (changes don't affect template)

### Applying to Existing Page
1. Open page → Settings tab → "Select Template to Apply" dropdown
2. Click More Options (•••) → **Apply Blocks Template**

### Creating Templates
1. Build layout on a page
2. More Options (•••) → **Create Blocks Template**
3. Set Title, Layout Image, review elements → Save

## Links and Buttons
- **Internal page** (recommended): stays valid if pages move
- **External URL**: links to other websites
- CTA buttons configured via Link fields on elements

## Site-Wide Settings
- Utility links (top-right header) configured in **Settings** (SiteConfig)
- Footer links, logo uploads, social media links
- Color scheme and typography settings

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Changes not showing | Confirm page is published, hard refresh browser |
| Page missing from menu | Check navigation/visibility settings |
| Image not appearing | Confirm upload succeeded, check image selected in block |
| Styles not applying | Save element, refresh Preview, verify correct element |
| Spacing mismatch across devices | Check Responsive tab overrides, empty = inherit |
| Rounded corners cut off | Reduce rounding value, ensure padding with background |
| Element hidden on wrong screen | Check Visibility per breakpoint in Responsive tab |
| Content resets (dev environment) | Populate/fixtures may have been re-run |
