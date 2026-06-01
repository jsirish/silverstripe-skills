# BlockArea → ElementalArea Relation Mapping

In SilverStripe 3 with the Blocks module, blocks were placed into named string areas (`BlockArea` column on `SiteTree_Blocks`): `HomeContent`, `Sidebar`, `BeforeContent`, `AfterContent`. The page template then rendered each area with `<% include BlockArea AreaName %>` or via `$BlockArea('Sidebar')`.

In SilverStripe 4 with Elemental, "areas" are explicit `has_one` relations on the page model. Each area is a typed FK to an `ElementalArea` record. Names are first-class — they become the template-suffix used to render area-specific variants.

**The mapping isn't 1:1 because page models declare their own relation names.** This doc captures the conventions used across the three reference projects.

## How the three reference projects set this up

### safeharbor (Page + Sidebar)

```yaml
# app/_config/elemental.yml
Page:
  extensions:
    ElementalPageExtension: DNADesign\Elemental\Extensions\ElementalPageExtension
  has_one:
    SidebarElementalArea: DNADesign\Elemental\Models\ElementalArea
  owns:
    - SidebarElementalArea
  cascade_duplicates:
    - SidebarElementalArea
```

Migration task `AREA_MAP`:
```php
private const AREA_MAP = [
    'Sidebar' => 'SidebarElementalAreaID',
    'HomeContent' => 'ElementalAreaID',
    'AfterContent' => 'ElementalAreaID',
    'BeforeContent' => 'ElementalAreaID',
];
```

### rockline-iatric (HomePage has its own area)

```php
// HomePage gets a separate ElementalArea relation
private static $has_one = [
    'ElementalHomePage' => ElementalArea::class,
];
```

Migration task picks the column based on page type:
```php
$isHomePage = stripos($className ?: '', 'HomePage') !== false;
$areaColumn = $isHomePage ? 'ElementalHomePageID' : 'ElementalAreaID';
$areaTable = $isHomePage ? 'HomePage' : 'Page';
```

### youth-sailing (HomePage has THREE areas)

```yaml
# app/_config/elemental.yml
Sail\Page\HomePage:
  has_one:
    HomeLeftElementalArea: DNADesign\Elemental\Models\ElementalArea
    HomeRightElementalArea: DNADesign\Elemental\Models\ElementalArea
    HomeContentElementalArea: DNADesign\Elemental\Models\ElementalArea
  owns:
    - HomeLeftElementalArea
    - HomeRightElementalArea
    - HomeContentElementalArea
  cascade_duplicates:
    - HomeLeftElementalArea
    - HomeRightElementalArea
    - HomeContentElementalArea
```

Migration task maps each SS3 area to its own column:
```php
$areaMap = [
    'Sidebar'     => ['column' => 'SidebarElementalAreaID',     'table' => 'Page'],
    'HomeLeft'    => ['column' => 'HomeLeftElementalAreaID',    'table' => 'HomePage'],
    'HomeRight'   => ['column' => 'HomeRightElementalAreaID',   'table' => 'HomePage'],
    'HomeContent' => ['column' => 'HomeContentElementalAreaID', 'table' => 'HomePage'],
];
```

## Naming convention guidance

Pick relation names that:

1. **End in `ElementalArea`** — improves grep-ability and matches Elemental's idiom.
2. **Describe the position semantically**, not by classname — `SidebarElementalArea` is better than `Page2ElementalArea`.
3. **Match the legacy BlockArea name where possible** — `HomeContent` (SS3) → `HomeContentElementalArea` (SS4). This makes the template suffix obvious.

The relation name becomes:
- A column on the page table: `<RelationName>ID`
- A property on the page model: `$page->SidebarElementalArea()`
- A template variable in the page layout: `$SidebarElementalArea`
- A **template suffix on element templates**: `ElementContent_SidebarElementalArea.ss`

That last point is the connection between this doc and [area-suffix-templates.md](area-suffix-templates.md).

## Rendering area-specific elements in page templates

The page template renders each area by calling its relation:

```html
<!-- themes/<theme>/templates/App/Pages/Layout/HomePage.ss -->
<div class="container">
    $ElementalArea          <!-- main area -->
</div>
<div class="container">
    $HomeContentElementalArea
</div>
<aside class="sidebar">
    $SidebarElementalArea
</aside>
```

Each `$...ElementalArea` invocation walks its elements, and for each element calls `BaseElement::forTemplate()`, which calls `getRenderTemplates()`, which produces (in order): `ElementContent_HomeContentElementalArea.ss` → `ElementContent.ss`. The area suffix is automatic — you don't pass it manually.

## Declaring the relations: YAML vs PHP

Either works. The three reference projects all use YAML because it keeps page model classes lean and lets you bundle Elemental's `has_one`, `owns`, and `cascade_duplicates` declarations together.

YAML approach (preferred):
```yaml
App\Pages\HomePage:
  has_one:
    HomeContentElementalArea: DNADesign\Elemental\Models\ElementalArea
  owns:
    - HomeContentElementalArea
  cascade_duplicates:
    - HomeContentElementalArea
```

PHP approach:
```php
class HomePage extends Page
{
    private static $has_one = [
        'HomeContentElementalArea' => ElementalArea::class,
    ];

    private static $owns = ['HomeContentElementalArea'];
    private static $cascade_duplicates = ['HomeContentElementalArea'];
}
```

Either way, run `ddev sake dev/build flush=1` to create the FK column.

## `owns` and `cascade_duplicates` matter

- **`owns`** — required for publishing. Without this, publishing the page won't publish the area's elements, and the live frontend won't render them. Skip and you'll spend an afternoon wondering why elements appear in the CMS but not on the public site.
- **`cascade_duplicates`** — required if the CMS allows duplicating pages. Without it, duplicating a page leaves the new copy pointing at the original's area (shared state).

Always declare both for every area relation.

## Common mistakes

| Symptom | Cause | Fix |
|---------|-------|-----|
| Element appears in CMS but not on frontend | Missing `owns` declaration | Add area to `owns` and re-publish the page |
| Duplicating page produces shared elements | Missing `cascade_duplicates` | Add area to `cascade_duplicates` |
| `$SidebarElementalArea` renders nothing despite elements in the area | `dev/build` didn't sync `Page_Live.SidebarElementalAreaID` | Run `syncPageAreasToLive()` at end of migration task |
| Migration task creates Element but it doesn't render | Page has no ElementalArea record yet | `getOrCreateArea()` must create one on first placement |
| Area-variant template not picked up | Relation name in template suffix doesn't match `has_one` key exactly | Check spelling and `Area` vs `ElementalArea` suffix |
