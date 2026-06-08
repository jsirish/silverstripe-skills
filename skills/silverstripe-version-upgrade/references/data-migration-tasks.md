## Phase 5: Data Migration Tasks

When upgrading, link modules typically change (e.g., Linkable → LinkField, ManyMany → has_many via LinkField). Write BuildTask classes to migrate data.

### 5.1 Migration Task Pattern (SS4/SS5)

Every migration task should follow this pattern:

```php
<?php

namespace App\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;

class MigrateExampleTask extends BuildTask
{
    private static $segment = 'migrate-example-data';

    protected $title = 'Migrate Example Data';
    protected $description = 'Migrates data from legacy table to new structure';

    public function run($request)
    {
        $this->log('Starting migration...');

        // 1. Check if legacy table exists
        if (!$this->tableExists('legacy_TableName')) {
            $this->log('ERROR: Legacy table does not exist.');
            return;
        }

        // 2. Fetch legacy data via raw SQL (avoids ORM issues with removed classes)
        $legacyRows = SQLSelect::create()
            ->setFrom('legacy_TableName')
            ->setOrderBy('SortOrder ASC')
            ->execute();

        $count = 0;

        foreach ($legacyRows as $row) {
            // 3. Check for existing record (idempotency)
            $existing = NewModel::get()->filter([
                'UniqueField' => $row['UniqueField'],
            ])->first();

            if ($existing) {
                $this->log("  - Skipped: Already exists (ID: {$existing->ID})");
                continue;
            }

            // 4. Create new record
            $record = NewModel::create();
            $record->Field = $row['LegacyField'];
            $record->write();

            // 5. Publish if needed (LinkField records need this)
            $record->publishRecursive();

            $count++;
            $this->log("  - Created ID: {$record->ID}");
        }

        $this->log("Migration complete. Created {$count} records.");
    }

    private function tableExists(string $tableName): bool
    {
        $tables = DB::table_list();
        return in_array(strtolower($tableName), array_map('strtolower', $tables));
    }

    private function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
```

### 5.1b SS6 Migration Task Pattern (Symfony Console)

SilverStripe 6 changes the `BuildTask` signature. The SS6 variant uses Symfony Console conventions:

```php
<?php

namespace App\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateExampleTask extends BuildTask
{
    protected static string $commandName = 'migrate-example-data';
    protected string $title = 'Migrate Example Data';
    protected static string $description = 'Migrates data from legacy table to new structure';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting migration...');

        // 1. Check if legacy table exists
        if (!$this->tableExists('legacy_TableName')) {
            $output->writeln('ERROR: Legacy table does not exist.');
            return Command::FAILURE;
        }

        // 2. Fetch legacy data via raw SQL
        $legacyRows = \SilverStripe\ORM\Queries\SQLSelect::create()
            ->setFrom('legacy_TableName')
            ->setOrderBy('SortOrder ASC')
            ->execute();

        $count = 0;
        foreach ($legacyRows as $row) {
            // 3. Check for existing record (idempotency)
            $existing = NewModel::get()->filter([
                'UniqueField' => $row['UniqueField'],
            ])->first();
            if ($existing) {
                $output->writeln("  - Skipped: Already exists (ID: {$existing->ID})");
                continue;
            }

            // 4. Create new record, write, publish
            $record = NewModel::create();
            $record->Field = $row['LegacyField'];
            $record->write();
            $record->publishRecursive();

            $count++;
            $output->writeln("  - Created ID: {$record->ID}");
        }

        $output->writeln("Migration complete. Created {$count} records.");
        return Command::SUCCESS;
    }

    private function tableExists(string $tableName): bool
    {
        $tables = DB::table_list();
        return in_array(strtolower($tableName), array_map('strtolower', $tables));
    }
}
```

> [!IMPORTANT]
> Key differences from SS5:
> - `$commandName` static property is **required** — without it the task is unreachable
> - `run($request)` → `execute(InputInterface, OutputInterface): int`
> - `echo` → `$output->writeln()`
> - `$title` / `$description` are typed properties (string or static string)
> - Return `Command::SUCCESS` (0) or `Command::FAILURE` (1)

**Template API cross-reference:** See "Theme template API changes: Linkable → LinkField" in the [silverstripe-version-upgrade SKILL.md](../SKILL.md) for the template-level migration reference table (`$Link` → `$URL`, `$OpenInNewWindow` → `$OpenInNew`, etc.).

### 5.2 Key Migration Principles

1. **Idempotent**: Tasks MUST be safe to run multiple times. Always check for existing records before creating.
2. **Use raw SQL for legacy data**: Use `SQLSelect` to read from legacy/renamed tables since ORM classes may no longer exist.
3. **Publish LinkField records**: Call `publishRecursive()` on Link records so they appear in `_Live` tables.
4. **Preserve sort order**: Legacy tables may use `SortOrder`; LinkField uses `Sort`. Map accordingly.
5. **Don't modify legacy tables**: Read from them but never delete or alter — they serve as rollback safety.
6. **SS6 commandName required**: In SS6, every BuildTask must define `protected static string $commandName = '...'`. Without it, `dev/tasks/<name>` cannot resolve the task.

### 5.3 Common Migration Scenarios

#### Linkable → gorriecoe/Link

```php
// Legacy: Sheadawson\Linkable stored in legacy_LinkableLink
// New: gorriecoe\Link\Models\Link

$link = Link::create();
$link->Title = $legacyLink['Title'];
$link->OpenInNewWindow = (bool) $legacyLink['OpenInNewWindow'];
$link->Sort = (int) $legacyLink['SortOrder']; // Field name change!
$link->Type = $legacyLink['Type']; // URL, SiteTree, Email, Phone

switch ($legacyLink['Type']) {
    case 'URL':
        $link->URL = $legacyLink['URL'];
        break;
    case 'SiteTree':
        $link->SiteTreeID = (int) $legacyLink['SiteTreeID'];
        break;
    // ... handle other types
}
$link->write();
```

#### ManyMany Join → LinkField (SiteTreeLink)

```php
// Legacy: ManyMany join table with SiteTreeID
// New: SilverStripe\LinkField\Models\SiteTreeLink

$link = SiteTreeLink::create();
$link->PageID = $siteTreeId;
$link->LinkText = $pageTitle;
$link->OwnerID = $groupId;
$link->OwnerClass = NavigationGroup::class;
$link->OwnerRelation = 'NavigationLinks';
$link->Sort = $sortOrder;
$link->write();
$link->publishRecursive(); // Critical for frontend visibility
```

#### Linkable → LinkField (SS6)

This is the most common SS6 data migration. Two approaches:

**Option A (preferred): Port linkfield's own `LinkableMigrationTask`**

- linkfield 4.x shipped an official `LinkableMigrationTask`; traits remain in 5.2.0
- Your port re-targets that machinery at the `LinkableLink` table
- Extend `base_link_columns` to carry Linkable's `Template` → `Style` via `LinkStyleExtension`

**Option B: Hand-rolled raw SQL task**

```php
// Legacy: sheadawson\linkable\models\LinkableLink
// New: SilverStripe\LinkField\Models\Link

$legacyRows = SQLSelect::create()
    ->setFrom('LinkableLink')
    ->execute();

foreach ($legacyRows as $row) {
    // Use Owner triple (OwnerClass/OwnerID/OwnerRelation) for idempotency
    $existing = Link::get()->filter([
        'OwnerClass' => $row['OwnerClass'],
        'OwnerID' => (int) $row['OwnerID'],
        'OwnerRelation' => $row['OwnerRelation'],
        'Sort' => (int) $row['SortOrder'],
    ])->first();

    if ($existing) {
        continue;
    }

    $link = Link::create();
    $link->Title = $row['Title'];
    $link->OpenInNew = (bool) $row['OpenInNewWindow'];
    $link->Sort = (int) $row['SortOrder'];
    $link->OwnerID = (int) $row['OwnerID'];
    $link->OwnerClass = $row['OwnerClass'];
    $link->OwnerRelation = $row['OwnerRelation'];
    $link->Type = $row['Type'];

    if ($row['URL']) {
        $link->URL = $row['URL'];
    }
    if ($row['SiteTreeID']) {
        $link->SiteTreeID = (int) $row['SiteTreeID'];
    }
    if ($row['Email']) {
        $link->Email = $row['Email'];
    }
    if ($row['Phone']) {
        $link->Phone = $row['Phone'];
    }
    if (!empty($row['Anchor'])) {
        // Append fragment — project-specific judgement call
        $anchor = ltrim($row['Anchor'], '#');
        if ($link->URL) {
            $link->URL .= '#' . $anchor;
        }
    }

    $link->write();
    $link->publishRecursive();
}
```

> [!WARNING]
> This task is **one-shot** — it drops the `LinkableLink` and join tables on success. Re-running requires a fresh DB sync.

Required config structure:

```yaml
# app/_config/linkfield-migration.yml
SilverStripe\LinkField\Migration\LinkableMigrationTask:
  is_enabled: true
  many_many_links_data:
    StaffMember:
      ContactLinks:
        SortOrder: Sort
  base_link_columns:
    Template: Style  # carries button style into LinkStyleExtension
```

#### SiteConfig data migrations (SS6)

When `dynamic/silverstripe-base-site` upgrades for SS6, two patterns emerge:

**UtilityLinks**: `many_many SiteTree` → `has_many SiteTreeLink`

```php
// Legacy: SiteConfig_UtilityLinks join table (SiteTreeID + SiteConfigID)
// New: SiteTreeLink records owned by SiteConfig
use SilverStripe\LinkField\Models\SiteTreeLink;
use SilverStripe\ORM\Queries\SQLSelect;

$legacyRows = SQLSelect::create()
    ->setFrom('SiteConfig_UtilityLinks')
    ->setOrderBy('"SiteConfig_UtilityLinks"."SortOrder" ASC')
    ->execute();

$createdCount = 0;

foreach ($legacyRows as $row) {
    // Skip if any SiteTreeLink with this OwnerRelation already exist (skip-if-exists idempotency)
    if ($createdCount > 0 || SiteTreeLink::get()->filter([
        'OwnerClass' => 'SilverStripe\\SiteConfig\\SiteConfig',
        'OwnerRelation' => 'UtilityLinks',
    ])->exists()) {
        if ($createdCount === 0) {
            $createdCount = SiteTreeLink::get()->filter([
                'OwnerClass' => 'SilverStripe\\SiteConfig\\SiteConfig',
                'OwnerRelation' => 'UtilityLinks',
            ])->count();
        }
        $this->log("  - Skipped: UtilityLinks already migrated ({$createdCount} records exist)");
        return;
    }

    $link = SiteTreeLink::create();
    $link->PageID = (int) $row['SiteTreeID'];
    $link->Sort = (int) ($row['SortOrder'] ?? 0);
    $link->OwnerID = (int) $row['SiteConfigID'];
    $link->OwnerClass = 'SilverStripe\\SiteConfig\\SiteConfig';
    $link->OwnerRelation = 'UtilityLinks';
    $link->write();
    $link->publishRecursive();
    $createdCount++;
}

$this->log("Created {$createdCount} UtilityLinks.");
```

**SocialLink model**: Standalone → extends `ExternalLink`

```php
// Legacy: SocialLink table with ConfigID, Link (URL), Site (enum)
// New: ExternalLink records in LinkField_Link with SocialChannel varchar
use SilverStripe\LinkField\Models\ExternalLink;
use SilverStripe\ORM\Queries\SQLSelect;

$channelMap = [
    'twitter' => 'x',
    'facebook' => 'facebook',
    'instagram' => 'instagram',
    'linkedin' => 'linkedin',
    'youtube' => 'youtube',
    'vimeo' => 'vimeo',
    'email' => 'email',
];

$legacyRows = SQLSelect::create()
    ->setFrom('SocialLink')
    ->where(['"SocialLink"."ConfigID" > ?' => 0])  // SS5 records only
    ->execute();

foreach ($legacyRows as $row) {
    $existing = ExternalLink::get()->filter([
        'ExternalUrl' => $row['Link'],
        'OwnerID' => (int) $row['ConfigID'],
        'OwnerClass' => 'SilverStripe\\SiteConfig\\SiteConfig',
        'OwnerRelation' => 'SocialLinks',
    ])->first();

    if ($existing) {
        continue;
    }

    $link = ExternalLink::create();
    $link->ExternalUrl = $row['Link'];
    $link->Title = $row['Title'] ?? '';
    $link->Sort = (int) ($row['Sort'] ?? 0);

    // Map the legacy enum to the new SocialChannel varchar
    $channel = strtolower(trim($row['Site'] ?? ''));
    $link->SocialChannel = $channelMap[$channel] ?? $channel;

    $link->OwnerID = (int) $row['ConfigID'];
    $link->OwnerClass = 'SilverStripe\\SiteConfig\\SiteConfig';
    $link->OwnerRelation = 'SocialLinks';
    $link->write();
    $link->publishRecursive();
}
```

> [!TIP]
> Old columns reference: `ConfigID` (SS5), `Link` (URL), `Site` (enum). New columns: `OwnerID`, `ExternalUrl`, `SocialChannel` (varchar). Identify SS5 records via `ConfigID > 0` — SS6 records use `OwnerID` in `LinkField_Link`.

**Legacy Anchor field handling** (Linkable → LinkField):

If your legacy `LinkableLink` records have non-empty `Anchor` values (e.g. `#section`), append them to the URL when creating LinkField records. This is a judgment call per project — some teams drop anchors, others preserve them:

```php
if (!empty($row['Anchor'])) {
    $anchor = ltrim($row['Anchor'], '#');
    if ($link->URL) {
        $link->URL .= '#' . $anchor;
    } elseif ($link->Type === 'URL') {
        $link->URL = '#' . $anchor;
    }
}
```
