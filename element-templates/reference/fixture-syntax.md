# Fixture YAML Syntax Reference

## Images

```yaml
SilverStripe\Assets\Image:
  imageIdentifier:
    PopulateFileFrom: 'vendor/dynamic/.../path/to/source.jpg'
    Filename: 'assets/Folder/filename.jpg'
    Title: 'Image Title'
    PopulateMergeAny: true  # OR use PopulateMergeMatch
```

## ElementalArea

```yaml
DNADesign\Elemental\Models\ElementalArea:
  area_identifier:
    OwnerClassName: 'Dynamic\ElementalTemplates\Models\Template'
```

## Row (Grid Container)

```yaml
WeDevelop\ElementalGrid\Models\ElementRow:
  row_identifier:
    Title: 'Row Title'
    ShowTitle: false
    AvailableGlobally: false
    IsFluid: false          # true = full viewport width
    BackgroundStyle: ''     # Bootstrap: bg-white, bg-dark, bg-primary, bg-light
    ContainerStyle: 'container'  # container, container-fluid, or empty
    VerticalPadding: 'py-5'      # Bootstrap: py-0 to py-6
    Parent: =>DNADesign\Elemental\Models\ElementalArea.area_identifier
    Sort: 1
```

## Common Elements

### ElementContent
```yaml
DNADesign\Elemental\Models\ElementContent:
  identifier:
    Title: 'Title'
    ShowTitle: true
    AvailableGlobally: false
    HTML: '<p>Content</p>'
    SizeMD: '12'
    Parent: =>DNADesign\Elemental\Models\ElementalArea.area_identifier
    Sort: 1
```

### ElementCard
```yaml
Dynamic\Elements\Card\Elements\ElementCard:
  identifier:
    Title: 'Card Title'
    ShowTitle: true
    AvailableGlobally: false
    TopTitle: 'Eyebrow Text'
    TitleTag: 'h3'
    Content: '<p>Description</p>'
    BackgroundColor: '#FFFFFF'
    ButtonColor: 'Primary'  # Primary, Secondary, Purple, Yellow, etc.
    Position: 'Top'         # Top, Bottom, Left, Right
    SizeMD: '4'
    ContentAlign: 'Center'  # Left, Center, Right
    Image: =>SilverStripe\Assets\Image.imageIdentifier
    ElementLink: =>SilverStripe\LinkField\Models\ExternalLink.linkIdentifier
    Parent: =>DNADesign\Elemental\Models\ElementalArea.area_identifier
    Sort: 1
```

### HeroMedia
```yaml
Dynamic\Essentials\Element\HeroMedia:
  identifier:
    Title: 'Hero Title'
    ShowTitle: true
    TitleTag: 'h1'
    AvailableGlobally: false
    TopTitle: 'Eyebrow'
    Content: '<p class="lead">Subheading</p>'
    MediaType: 'Image'       # Image or Video
    CallToActionStyle: 'primary'
    PrimaryButtonLabel: 'Learn More'
    ContentAlign: 'Center'   # Left, Center, Right
    TextColor: 'white'       # white, dark
    Image: =>SilverStripe\Assets\Image.imageIdentifier
    Parent: =>DNADesign\Elemental\Models\ElementalArea.area_identifier
    Sort: 1
```

### ElementCallToAction
```yaml
Dynamic\Elements\CTA\Elements\ElementCallToAction:
  identifier:
    Title: 'CTA Title'
    ShowTitle: true
    TitleTag: 'h2'
    AvailableGlobally: false
    TopTitle: 'Eyebrow'
    HTML: '<p>Call to action content</p>'
    SizeMD: '8'
    OffsetMD: '2'
    ContentAlign: 'Center'
    Parent: =>DNADesign\Elemental\Models\ElementalArea.area_identifier
    Sort: 1
```

## Links

### External Link
```yaml
SilverStripe\LinkField\Models\ExternalLink:
  linkIdentifier:
    LinkText: 'Click Here'
    OpenInNew: false
    ExternalUrl: 'https://example.com'
```

### Page Link
```yaml
SilverStripe\LinkField\Models\SiteTreeLink:
  linkIdentifier:
    LinkText: 'View Page'
    OpenInNew: false
    Page: =>Page.pageIdentifier
```

## Templates

```yaml
Dynamic\ElementalTemplates\Models\Template:
  template_identifier:
    Title: 'Template Display Name'
    PageType: 'Dynamic\Base\Page\BlockPage'
    Elements: =>DNADesign\Elemental\Models\ElementalArea.area_identifier
    LayoutImage: =>SilverStripe\Assets\Image.previewImageIdentifier
    PopulateMergeMatch:
      - Title
```

## Merge Strategies

| Strategy | Usage |
|----------|-------|
| `PopulateMergeAny: true` | Update if any record exists |
| `PopulateMergeMatch: [Field]` | Update if field matches |
| (none) | Always create new record |

## Grid Sizes

| SizeMD | Width | Common Use |
|--------|-------|------------|
| 12 | 100% | Full width |
| 6 | 50% | 2 columns |
| 4 | 33% | 3 columns |
| 3 | 25% | 4 columns |
