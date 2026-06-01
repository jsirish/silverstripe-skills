## Phase 5: Data Migration Tasks

When upgrading, link modules typically change (e.g., Linkable → LinkField, ManyMany → has_many via LinkField). Write BuildTask classes to migrate data.

### 5.1 Migration Task Pattern

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
        if (!$this->tableExists('_legacy_TableName')) {
            $this->log('ERROR: Legacy table does not exist.');
            return;
        }

        // 2. Fetch legacy data via raw SQL (avoids ORM issues with removed classes)
        $legacyRows = SQLSelect::create()
            ->setFrom('_legacy_TableName')
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

### 5.2 Key Migration Principles

1. **Idempotent**: Tasks MUST be safe to run multiple times. Always check for existing records before creating.
2. **Use raw SQL for legacy data**: Use `SQLSelect` to read from legacy/renamed tables since ORM classes may no longer exist.
3. **Publish LinkField records**: Call `publishRecursive()` on Link records so they appear in `_Live` tables.
4. **Preserve sort order**: Legacy tables may use `SortOrder`; LinkField uses `Sort`. Map accordingly.
5. **Don't modify legacy tables**: Read from them but never delete or alter — they serve as rollback safety.

### 5.3 Common Migration Scenarios

#### Linkable → gorriecoe/Link

```php
// Legacy: Sheadawson\Linkable stored in _legacy_LinkableLink
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

