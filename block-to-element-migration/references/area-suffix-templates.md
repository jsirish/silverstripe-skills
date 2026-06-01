# Area-Suffix Templates

The mechanism that makes a single Element class render differently depending on which area it lives in. The SS4 equivalent of SS3's `BlockName_AreaName.ss` convention — but the suffix is now the `has_one` relation name, not the SS3 area string.

## The contract

SS4 Elemental's `BaseElement::forTemplate()` calls `getRenderTemplates()`, which walks the element's class hierarchy and produces template candidates. For each ancestor class `<Class>`, the candidates are (in resolution order):

```
<Class>_<AreaRelationName>_<Style>     (only if a Style is configured)
<Class>_<AreaRelationName>              ← THE AREA VARIANT
<Class>_<Style>                          (only if a Style is configured)
<Class>                                  ← THE DEFAULT
```

`<AreaRelationName>` comes from `BaseElement::getAreaRelationName()`, which finds the `has_one` relation on the page model that points to the element's parent `ElementalArea`.

**Source:** `vendor/dnadesign/silverstripe-elemental/src/Models/BaseElement.php` around line 621 (`getRenderTemplates()`) and 959 (`getAreaRelationName()`).

## Examples from production

Youth-sailing has the most extensive use of the pattern:

```
themes/sys/templates/Dynamic/Elements/Promos/Elements/
├── ElementPromos.ss                          ← default (3-col grid)
├── ElementPromos_SidebarElementalArea.ss     ← stacked single col
├── ElementPromos_HomeLeftElementalArea.ss    ← homepage left layout
├── ElementPromos_HomeRightElementalArea.ss   ← homepage right layout
└── ElementPromos_HomeContentElementalArea.ss ← homepage center layout
```

Same `ElementPromos` Element class. Four different renderings driven by which area the element belongs to:
- An element in `Page.SidebarElementalArea` resolves to `ElementPromos_SidebarElementalArea.ss`.
- An element in `HomePage.HomeLeftElementalArea` resolves to `ElementPromos_HomeLeftElementalArea.ss`.
- An element in any other area falls through to `ElementPromos.ss`.

Rockline-iatric uses it for one variant:
```
themes/iatric/templates/Dynamic/Elements/Promos/Elements/
├── ElementPromos.ss
└── ElementPromos_ElementalHomePage.ss
```

## The naming rule

**The suffix must match the page model's `has_one` relation name exactly.** Not approximately. Not "the area's display name". The PHP-side relation name.

If the page model declares:
```yaml
App\Pages\HomePage:
  has_one:
    HomeContentElementalArea: DNADesign\Elemental\Models\ElementalArea
```

Then the template suffix is `_HomeContentElementalArea`, producing:
```
ElementContent_HomeContentElementalArea.ss
```

A typo (e.g. `_HomeContentArea` or `_HomeContent`) silently falls through to the default `ElementContent.ss` — no error, just unexpected rendering. Easy to miss.

## Mapping from SS3 BlockArea to SS4 template suffix

The SS3 → SS4 area mapping (from [area-relation-mapping.md](area-relation-mapping.md)) determines the suffix:

| SS3 BlockArea | SS4 relation name | Template suffix |
|---------------|--------------------|-----------------|
| `Sidebar` | `SidebarElementalArea` | `_SidebarElementalArea` |
| `HomeContent` (safeharbor — main area) | `ElementalArea` | `_ElementalArea` (or just default) |
| `HomeContent` (youth-sailing — separate area) | `HomeContentElementalArea` | `_HomeContentElementalArea` |
| `HomeLeft` (youth-sailing) | `HomeLeftElementalArea` | `_HomeLeftElementalArea` |
| `HomeRight` (youth-sailing) | `HomeRightElementalArea` | `_HomeRightElementalArea` |
| n/a (rockline-iatric — HomePage has its own area) | `ElementalHomePage` | `_ElementalHomePage` |

**Note on safeharbor's HomeContent:** safeharbor reuses the main `ElementalArea` for the HomePage's HomeContent area (no separate `has_one`). For this case, the area variant template would be named `ElementPromo_ElementalArea.ss` — but it only takes effect for elements in the main area (which is everywhere on standard pages). When you want different rendering for the HomePage specifically and the main area is shared, either declare a dedicated HomePage relation (matching youth-sailing's pattern) or use a different approach (e.g. check `$Page.ClassName` in the default template).

## The ElementHolder template

Elemental wraps every element render in `DNADesign/Elemental/Layout/ElementHolder.ss`, which by default produces:

```html
<div class="element $StyleClasses" id="$AnchorName">
    $Element
</div>
```

In SS4.x, `$StyleClasses` is verbose: `ElementContent BaseElement DataObject` plus any `ExtraClass` value. For migration projects that want byte-identical markup with the legacy SS3 site, this extra wrapper is noise.

The fix: override `ElementHolder.ss` in your theme:

```html
<!-- themes/<theme>/templates/DNADesign/Elemental/Layout/ElementHolder.ss -->
$Element
```

This strips the wrapper entirely. The element's own template now controls its outermost markup, matching what the legacy block template produced.

If you want to keep some wrapper but strip the verbose class names:
```html
<!-- alternative — keep wrapper, strip classes -->
<div<% if $AnchorName %> id="$AnchorName"<% end_if %>>$Element</div>
```

Or keep wrapper with only `$ExtraClass` (CMS-set extras):
```html
<div class="$ExtraClass"<% if $AnchorName %> id="$AnchorName"<% end_if %>>$Element</div>
```

Pick based on what your prod HTML actually produces.

## Resolution precedence

When SS4 looks for `ElementContent_HomeContentElementalArea.ss`, it checks **every theme cascade location** in order:

1. `themes/<active-theme>/templates/<Namespace>/Elements/`
2. `themes/<active-theme>/templates/<Namespace>/Models/`
3. `themes/<theme-cascade>/templates/...`
4. The element's own module: `vendor/dnadesign/silverstripe-elemental/templates/<Namespace>/Models/`

For `DNADesign\Elemental\Models\ElementContent`, the `<Namespace>` is `DNADesign/Elemental/Models`. So the search path is:

```
themes/<theme>/templates/DNADesign/Elemental/Models/ElementContent_HomeContentElementalArea.ss
themes/<theme>/templates/DNADesign/Elemental/Models/ElementContent.ss
vendor/dnadesign/silverstripe-elemental/templates/DNADesign/Elemental/Models/ElementContent.ss
```

For a custom element like `App\Elements\ElementPromo`, the `<Namespace>` is `App/Elements`:

```
themes/<theme>/templates/App/Elements/ElementPromo_HomeContentElementalArea.ss
themes/<theme>/templates/App/Elements/ElementPromo.ss
```

You can also place override templates without the namespace, but the cascade gets fuzzier — sticking to the canonical namespace path is safer.

## Common pitfalls

| Pitfall | Symptom | Fix |
|---------|---------|-----|
| Typo in suffix | Default template renders unexpectedly | Compare suffix character-for-character against `has_one` key |
| File in wrong namespace dir | Default renders, variant doesn't | Path must mirror element FQCN: `App\Elements\X` → `App/Elements/X.ss` |
| Variant template exists but renders same as default | Two areas share one `has_one` relation | Different areas need different relation names (e.g. don't reuse `ElementalArea` for both main and home-content) |
| `_<RelationName>_<Style>` not picked up | Style isn't configured | Configure style on the element via YAML `BaseElement.styles` or use just `_<RelationName>` |
| Variant works locally, breaks after `?flush=all` | Template cache stale at file-add time | Always flush after adding new template files: `ddev sake dev/build flush=1` |
| `ElementContent` renders default even from theme override | Theme not active | Confirm theme is in `SilverStripe\Core\Manifest\ModuleLoader` and listed in `SSViewer.themes` config |

## Quick check command

To list every area-variant template a project ships:

```bash
find themes -type f -name 'Element*_*ElementalArea.ss' -o -name 'Element*_ElementalHomePage.ss'
```

To see which areas a page model declares:

```bash
grep -rh 'has_one' app/_config/elemental.yml | grep ElementalArea
```

To verify an element template is being chosen at runtime, log from `BaseElement::forTemplate()` — or just temporarily inject a unique HTML comment into the candidate template and view-source.
