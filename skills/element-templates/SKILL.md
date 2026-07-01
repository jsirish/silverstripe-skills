---
name: element-templates
description: Create element template fixtures for the Essentials demo template library
---

# Element Templates Skill

This skill guides you through **designing** and **implementing** element template fixtures for the `recipe-silverstripe-essentials-fixtures` template library.

## Related Skills

| Skill | Purpose |
|-------|---------|
| **Essentials Fixtures Recipe** | Recipe package config, fixture loading, `FixtureRecordExtension`, troubleshooting populate errors |
| **Essentials Theme** | Subtheme customization, color palette, CSS variables |
| **Silverstripe Essentials Website** | Full Essentials project architecture, namespaces, and config |

> [!NOTE]
> **Scope**: This skill covers YAML authoring — composition patterns, element palette, grid system, gotchas. For the **recipe package configuration** (loading fixtures, extensions, running populate, troubleshooting), see the **Essentials Fixtures Recipe** skill. For the **element placeholder fixture system** (`element-fixtures.yml`), see the [Essentials Fixtures Recipe skill](../essentials-fixtures/SKILL.md) in the Essentials Fixtures Recipe skill.

## Core Design Principle

> **A template is NOT a single element.** A template is a multi-element composition that tells a story: **Context → Content → Conversion.**

Every template must contain **at least 2 non-row elements**. A single element (e.g., a lone `ElementBlogPosts` or `ElementStaff`) is just a widget insertion — not a template. Templates should combine complementary elements to create complete, purposeful page sections.

---

## Design Patterns (from Gutenberg, Elementor, and Landing Page Best Practices)

### Pattern 1: Evidence → Content → CTA
**Use for:** Portfolio, Gallery, Case Study templates

| Position | Element Type | Purpose |
|----------|-------------|---------|
| 1 | `ElementStatCounters` or `ElementSponsor` | Credibility proof (numbers or logos) |
| 2 | Main content element (Gallery, Blog, etc.) | The actual content |
| 3 | `ElementCallToAction` | Convert the engaged viewer |

**Example:** Stats ("500+ Projects") → Photo Gallery → CTA ("Start Your Project")

### Pattern 2: Hero → Grid → CTA
**Use for:** Team, Services, Features templates

| Position | Element Type | Purpose |
|----------|-------------|---------|
| 1 | `HeroMedia` or visual intro | Visual hook / context setter |
| 2 | Grid element (Staff, Cards, Content columns) | The main structured content |
| 3 | `ElementCallToAction` | Next step / conversion |

**Example:** Hero ("Our Leadership") → Staff Grid → CTA ("Join Our Team")

### Pattern 3: Content → Objection Handler → CTA
**Use for:** Pricing, Comparison, Decision-heavy templates

| Position | Element Type | Purpose |
|----------|-------------|---------|
| 1 | Main content (Chart, Pricing Cards) | Present the options |
| 2 | `ElementAccordion` | FAQ — reduce friction, answer objections |
| 3 | `ElementCallToAction` | Convert the decided visitor |

**Example:** Comparison Table → FAQ ("Which plan is right for me?") → CTA ("Start Free Trial")

### Pattern 4: Social Proof → CTA → Trust
**Use for:** Conversion-focused templates, CTA sections

| Position | Element Type | Purpose |
|----------|-------------|---------|
| 1 | `ElementTestimonials` | Warm up with social proof |
| 2 | `ElementCallToAction` | The conversion prompt |
| 3 | `ElementSponsor` | Trust reinforcement (client logos) |

**Example:** Testimonial Quote → CTA ("Book a Call") → Client Logos

### Pattern 5: Visual → List → Fallback CTA
**Use for:** Resource, Link, Download templates

| Position | Element Type | Purpose |
|----------|-------------|---------|
| 1 | `ElementImage` or `ElementCarousel` | Visual anchor |
| 2 | `LinksElement` or structured list | The content items |
| 3 | `ElementCallToAction` | Fallback for users who didn't find what they need |

**Example:** Illustration → Resource Links → CTA ("Contact Support")

### Pattern 6: Hook → Content → Social Proof
**Use for:** About, Story, Mission templates

| Position | Element Type | Purpose |
|----------|-------------|---------|
| 1 | `HeroMedia` or `ElementImage` | Emotional visual hook |
| 2 | `ElementContent` or `SimpleContent` | The narrative / story |
| 3 | `ElementTestimonials` or `ElementStatCounters` | Proof that backs the story |

**Example:** Team Photo → About Content → Client Testimonials

---

## Anti-Patterns (What NOT to Do)

| ❌ Anti-Pattern | Why It's Wrong | ✅ Fix |
|----------------|---------------|--------|
| Single element template | Widget insertion, not a template | Add complementary elements per patterns above |
| SimpleContent intro above an element that already has TopTitle/Title/Content | Redundant — every Essentials element already has title fields | Use a *different* element type as the intro (Hero, Image, Stats) |
| Three of the same element type | Monotonous, no composition | Mix element types for visual variety |
| No CTA anywhere in the template | Missed conversion opportunity | Add a CTA as the closing element |
| Rows only for visual background | Rows are layout containers, not content | Ensure rows contain or frame meaningful content |

---

## Available Element Palette

| Element | Class | Best For |
|---------|-------|----------|
| Content | `DNADesign\Elemental\Models\ElementContent` | Rich text with media tab, split layouts |
| Simple Content | `Dynamic\Essentials\Element\SimpleContent` | Text-only (no media tab) |
| Hero Media | `Dynamic\Essentials\Element\HeroMedia` | Hero banners with image/video, CTA buttons |
| Image | `Dynamic\Elements\Image\Elements\ElementImage` | Standalone images with rounding options |
| CTA | `Dynamic\Elements\CTA\Elements\ElementCallToAction` | Button + text, conversion blocks |
| Staff | `Dynamic\Essentials\Element\ElementStaff` | Team member grids |
| Testimonials | `Dynamic\Elements\Elements\ElementTestimonials` | Client quote sliders |
| Stat Counters | `Dynamic\Elements\StatCounters\Elements\ElementStatCounters` | Animated number displays |
| Sponsors | `Dynamic\Elements\Sponsors\Elements\ElementSponsor` | Logo rows (clients, partners) |
| Accordion | `Dynamic\Elements\Accordion\Elements\ElementAccordion` | FAQ panels, collapsible content |
| Gallery | `Dynamic\Elements\Gallery\Elements\ElementPhotoGallery` | Photo grids |
| Carousel | `Dynamic\Elements\Carousel\Elements\ElementCarousel` | Image sliders (many_many Slides) |
| Blog Posts | `Dynamic\Elements\Blog\Elements\ElementBlogPosts` | Post grids (needs Blog relation) |
| Chart | `Dynamic\Essentials\Element\ElementChart` | Tables, data display |
| Links | `Dynamic\Elements\Links\Elements\LinksElement` | Link lists with descriptions |
| Card | `Dynamic\Elements\Card\Elements\ElementCard` | Promo cards with image/button |
| Oembed | `Dynamic\Elements\Oembed\Elements\ElementOembed` | Video embeds (YouTube, Vimeo) |
| Customer Service | `Dynamic\Elements\CustomerService\Elements\ElementCustomerService` | Contact info, hours, map |
| Form | `DNADesign\ElementalUserForms\Model\ElementForm` | User-defined forms |
| Row | `WeDevelop\ElementalGrid\Models\ElementRow` | Container/layout (bg, padding) |

---

## Element Relationship Patterns

Some elements have child records that must be defined separately:

### Has-Many Children (define children, reference parent)
```yaml
# Staff → StaffMember
Dynamic\Essentials\Model\StaffMember:
  member1:
    Name: 'Alex Johnson'
    Position: 'CEO'
    ElementStaff: =>Dynamic\Essentials\Element\ElementStaff.myStaff

# Testimonials → Testimonial
Dynamic\Elements\Model\Testimonial:
  testimonial1:
    Name: 'Dana Smith'
    Affiliation: 'Acme Corp'  # NOT "Company" — field is Affiliation
    ElementTestimonials: =>Dynamic\Elements\Elements\ElementTestimonials.myTestimonials

# Accordion → AccordionPanel
Dynamic\Elements\Accordion\Model\AccordionPanel:
  panel1:
    Title: 'Question here'
    Content: '<p>Answer here</p>'
    Accordion: =>Dynamic\Elements\Accordion\Elements\ElementAccordion.myAccordion

# StatCounters → StatCounter
Dynamic\Elements\StatCounters\Model\StatCounter:
  stat1:
    Title: 'Projects Completed' # descriptor (small caption)
    Statistic: '500' # the number/word, renders large
    StatType: 'Int'  # or 'Percentage'
    # Label left empty - reserve it for a short unit ('%', 'wks'), never the descriptor (see gotcha below)
    ElementStatCounters: =>Dynamic\Elements\StatCounters\Elements\ElementStatCounters.myStats

# Links → LinkListObject
Dynamic\Elements\Links\Model\LinkListObject:
  link1:
    Title: 'Getting Started'
    Content: '<p>Description</p>'
    LinkList: =>Dynamic\Elements\Links\Elements\LinksElement.myLinks

# Sponsors → Sponsor
Dynamic\Elements\Sponsors\Model\Sponsor:
  sponsor1:
    Title: 'Company Name'
    Image: =>SilverStripe\Assets\Image.logoImage
    SortOrder: 1
```

### Many-Many (inline on element)
```yaml
# Carousel → ImageSlide (many_many via Slides)
Dynamic\Elements\Carousel\Elements\ElementCarousel:
  myCarousel:
    Slides: =>Dynamic\Carousel\Model\ImageSlide.slide1, =>Dynamic\Carousel\Model\ImageSlide.slide2

# Staff (alternative — many_many via Staff)
Dynamic\Essentials\Element\ElementStaff:
  myStaff:
    Staff: =>Dynamic\Essentials\Model\StaffMember.member1, =>Dynamic\Essentials\Model\StaffMember.member2

# Sponsors (many_many via Sponsors)
Dynamic\Elements\Sponsors\Elements\ElementSponsor:
  mySponsors:
    Sponsors: =>Dynamic\Elements\Sponsors\Model\Sponsor.sponsor1, =>Dynamic\Elements\Sponsors\Model\Sponsor.sponsor2
```

---

## Fixture File Structure

### File Location
```
vendor/dynamic/recipe-silverstripe-essentials-fixtures/app/fixtures/
```

### Key Files
- `template-preview-images.yml` — Preview images (loads first)
- `blog-fixtures.yml` — Blog + posts (dependency for ElementBlogPosts)
- `element-templates.yml` — Main template definitions
- `element-templates-expanded.yml` — Extended templates (loads after main)

### Load Order Configuration
```yaml
# app/_config/fixtures-populate.yml
Dynamic\Populate\Populate:
  include_fixture_files:
    - 'vendor/dynamic/.../template-preview-images.yml'
    - 'vendor/dynamic/.../blog-fixtures.yml'
    - 'vendor/dynamic/.../element-templates.yml'
    - 'vendor/dynamic/.../element-templates-expanded.yml'
```

### Every Record Must Have
```yaml
SomeElement:
  myRecord:
    FixtureIdentifier: myRecord        # Unique ID for merge matching
    PopulateMergeMatch:
      - FixtureIdentifier              # Idempotent — won't duplicate on re-run
    AvailableGlobally: false           # Template elements are not reusable
    Parent: =>DNADesign\Elemental\Models\ElementalArea.myArea
    Sort: 1                            # Order within the area
```

---

## Grid System

Bootstrap 12-column grid via `SizeMD` and `OffsetMD`:

| Layout | SizeMD | OffsetMD |
|--------|--------|----------|
| Full width | `12` | — |
| Centered narrow | `8` or `10` | `2` or `1` |
| Two equal columns | `6` each | — |
| Three columns | `4` each | — |
| Four columns | `3` each | — |

---

## Row Styling Reference

```yaml
WeDevelop\ElementalGrid\Models\ElementRow:
  myRow:
    IsFluid: true              # Full-width (bypasses container)
    BackgroundStyle: 'bg-dark text-white'   # Bootstrap utility classes
    BackgroundColor: '#CCBEF5'              # Hex color override
    BackgroundFullWidth: true               # Bg extends beyond container
    ContainerStyle: 'container'             # Bootstrap container
    VerticalPadding: 'py-5'                 # Bootstrap spacing
```

Common background combos:
- `bg-white` — Clean white
- `bg-light` — Light grey
- `bg-dark text-white` — Dark mode
- `bg-primary text-white` — Brand color
- Custom hex via `BackgroundColor` + `BackgroundFullWidth: true`

---

## Asset & Content Uniqueness

> [!IMPORTANT]
> **Every template must be 100% unique.** Do not re-use images, names, titles, or copy across templates. Always generate premium, custom AI images for each template using `generate_image`, and write fresh, context-specific copy for every block.

When generating images, follow these isolation rules:

> [!WARNING]
> Do NOT place placeholder template images in the root `assets/` directory or directories likely to be used for genuine client content (like `assets/Uploads/`). 

Always save newly generated images into the existing repository structure (e.g., `vendor/dynamic/recipe-silverstripe-essentials-fixtures/images/Premium/[Category]/`) and configure `YamlFixture` to populate them into a dedicated isolated folder in the CMS, such as `assets/Templates/Premium/[Category]/`.

Example:
```yaml
SilverStripe\Assets\Image:
  myPremiumTemplateImage:
    PopulateFileFrom: 'vendor/dynamic/recipe-silverstripe-essentials-fixtures/images/Premium/Heroes/my-new-hero.png'
    Filename: 'assets/Templates/Premium/Heroes/my-new-hero.png' # ✅ Isolated
    # NOT: 'assets/my-new-hero.png' ❌
```

---

## Common Fixture Gotchas

> [!WARNING]
> **Polymorphic Link Parsing (`SiteTreeLink`, etc.)**: When configuring a link via an element's `$ElementLink` property (or similar) pointing to a `SilverStripe\LinkField\Models\SiteTreeLink`, do **NOT** define `OwnerClass` and `OwnerRelation` in the `SiteTreeLink` fixture definition. Because the `OwnerID` doesn't exist until the relation is fully mapped, explicitly defining these properties causes the `PopulateTask` to crash. The ORM inherently handles the linkage natively when assigning the `ElementLink: =>...` property on the parent element.

> [!WARNING]
> **ElementCallToAction (`ContentAlign`)**: Omission of the `ContentAlign` field inside an `ElementCallToAction` fixture will cause severe PHP 8.x null-to-string deprecation warnings that crash layout rendering upstream. Always forcefully populate `ContentAlign` (e.g. `'Center'`, `'Left'`, `'Right'`).

> [!WARNING]
> **StatCounter field roles + sizing**: `Label` is `Varchar(20)` and renders at the SAME large size as `Statistic` - it is for a short unit (`'%'`, `'wks'`), never the descriptor. Put the number/word in `Statistic`, the descriptor in `Title` (small caption), and leave `Label` empty unless you have a real short unit. Putting a long descriptor in `Label` overflows the stat box - this caused a real production bug ("stat counters too big") on a live client build.

---

## Workflow

1. **Design the composition** — Pick a pattern from the Design Patterns section above. Map out which 2-4 elements you'll combine and why.
2. **Check for dependencies** — Does your template need child records (testimonials, stats, slides, blog posts)?
3. **Download/generate images** — Save to `fixtures/images/[category]/`
4. **Create fixture YAML** — Follow the structure with `FixtureIdentifier` + `PopulateMergeMatch`
5. **Add preview image** — Register in `template-preview-images.yml`
6. **Run populate** — `ddev sake dev/tasks/PopulateTask`
7. **Verify in CMS** — Browse to `/admin/elemental-templates` and check the template preview
8. **Verify frontend** — Browse to `/template-preview/{id}` and confirm rendering

---

## Template Quality Checklist

Before considering a template complete:

- [ ] Contains **2+ non-row elements** (not counting ElementRow)
- [ ] Elements are **different types** (not 3x the same element)
- [ ] Follows one of the named **design patterns** above
- [ ] Every element has `FixtureIdentifier` + `PopulateMergeMatch`
- [ ] Every element has `AvailableGlobally: false`
- [ ] Child records (testimonials, stats, etc.) exist and are linked
- [ ] Template has a `LayoutImage` (preview image)
- [ ] Template has a `Description` (HTML paragraph)
- [ ] Template renders without 500 errors on `/template-preview/{id}`
- [ ] Content is realistic (not "Lorem ipsum" or placeholder text)

---

## Known Issues

### PopulateFactory `return true` Bug

The `dnadesign/silverstripe-populate` package has a bug where `populateFile()` returns `true` instead of the File object when an existing file's hash matches. This crashes on `$file->ID`. **Patch required** — see [Essentials Fixtures → Known Vendor Bug](../essentials-fixtures/references/troubleshooting.md#known-vendor-bug) for the fix.

### Multi-Table Inheritance Orphans

After syncing a production DB that was previously populated, child tables (`Sponsor`, `StaffMember`) may have orphaned rows without matching parent rows in `BaseElementObject`. This causes `Duplicate entry` errors. Clean with:
```bash
ddev mysql -e "DELETE s FROM Sponsor s LEFT JOIN BaseElementObject bo ON s.ID = bo.ID WHERE bo.ID IS NULL;"
ddev mysql -e "DELETE sm FROM StaffMember sm LEFT JOIN BaseElementObject bo ON sm.ID = bo.ID WHERE bo.ID IS NULL;"
```

