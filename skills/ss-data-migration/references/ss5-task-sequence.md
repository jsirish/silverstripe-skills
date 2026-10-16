# SS4 → SS5 task sequence (worked example)

Companion to the SS4 → SS5 section of [SKILL.md](../SKILL.md). This is the Dynamic Agency Base Site task order; task names and count vary per project. Run in this sequence to guarantee data integrity.

The migration tasks must be run in the following sequence to guarantee data integrity.

## 1. Block Migration
Migrates legacy Elemental blocks to the SS5 standard Element structures.
**Command:** `ddev sake dev/tasks/block-migration "flush=1"`

## 2. Footer Links Migration
Cleans up and reorganizes footer navigation items.
**Command:** `ddev sake dev/tasks/migrate-footer-links "flush=1"`

## 3. Navigation Migration
Migrates core navigation settings and structures.
**Command:** `ddev sake dev/tasks/NavigationMigrationTask "flush=1"`

## 4. Slide Image Linkable Migration
Upgrades SlideImage links to the new LinkField format.
**Command:** `ddev sake dev/tasks/Sail-Task-SlideImageLinkableTask "flush=1"`

## 5. Fix About Links Task
Repairs specific internal links that may have broken during the schema changes.
**Command:** `ddev sake dev/tasks/FixAboutLinksTask "flush=1"`

## 6. Fix HomePage ElementalArea Owner Task
Repairs incorrect `OwnerClassName` data for ElementalAreas attached to HomePages to ensure specific layout templates resolve correctly.
**Command:** `ddev sake dev/tasks/fix-homepage-elemental-area-owner "flush=1"`

## 7. Asset Resampling (Pre-computation)
Preemptively generates variants (e.g., `Fill` or `ScaleWidth`) for heavily populated entities like Galleries before deployment. This prevents the initial page load on the remote server from timing out while computing hundreds of manipulation variants.
**Command:** `ddev sake dev/tasks/gallery-resample-task "flush=1"`
