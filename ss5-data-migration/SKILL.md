---
name: ss5-data-migration
description: Complete workflow for executing Silverstripe 5 data migration tasks. Use this skill when the user wants to "run migration tasks", "migrate data", "sync and migrate", or specifically refers to "SS5 data migration" after a database sync or deployment.
---

# SS5 Data Migration

## Overview

This skill provides a reliable, ordered workflow for executing the necessary data migration tasks after upgrading a Silverstripe 4 project to Silverstripe 5 (specifically for Dynamic Agency Base Site architectures). It ensures all layout, content, and settings data are properly migrated to the new schema formats.

## Prerequisites

Before running these migrations, ensure:
1. The codebase is fully deployed and the `dev/build` has completed successfully.
2. If working locally, you have synchronized the latest production database (e.g., using `ddev sync` or a similar workflow).

## Workspace Migration Skill Strategy

Due to project-specific differences (such as custom element types, obsolete legacy structures, or unique data schema), the exact migration tasks vary per project. 

This global skill strongly recommends **formalizing a Workspace-Specific Migration Skill** (e.g. `<project-name>-ss5-migration/SKILL.md`) that documents the exact tasks to run in order for that specific site. The following workflow is an example baseline.

## Migration Workflow

The migration tasks must be run in the following sequence to guarantee data integrity.

### 1. Block Migration
Migrates legacy Elemental blocks to the SS5 standard Element structures.
**Command:** `ddev sake dev/tasks/block-migration "flush=1"`

### 2. Footer Links Migration
Cleans up and reorganizes footer navigation items.
**Command:** `ddev sake dev/tasks/migrate-footer-links "flush=1"`

### 3. Navigation Migration
Migrates core navigation settings and structures.
**Command:** `ddev sake dev/tasks/NavigationMigrationTask "flush=1"`

### 4. Slide Image Linkable Migration
Upgrades SlideImage links to the new LinkField format.
**Command:** `ddev sake dev/tasks/Sail-Task-SlideImageLinkableTask "flush=1"`

### 5. Fix About Links Task
Repairs specific internal links that may have broken during the schema changes.
**Command:** `ddev sake dev/tasks/FixAboutLinksTask "flush=1"`

### 6. Fix HomePage ElementalArea Owner Task
Repairs incorrect `OwnerClassName` data for ElementalAreas attached to HomePages to ensure specific layout templates resolve correctly.
**Command:** `ddev sake dev/tasks/fix-homepage-elemental-area-owner "flush=1"`

### 7. Asset Resampling (Pre-computation)
Preemptively generates variants (e.g., `Fill` or `ScaleWidth`) for heavily populated entities like Galleries before deployment. This prevents the initial page load on the remote server from timing out while computing hundreds of manipulation variants.
**Command:** `ddev sake dev/tasks/gallery-resample-task "flush=1"`

## Automated Execution

To run all tasks automatically, you can use the `data-migration` workflow if it exists in the project's `.agent/workflows` directory, or execute the commands sequentially as listed above. Ensure each command exits successfully before proceeding to the next.

## Deployment Strategy

When deploying migrated data and pre-computed assets to staging or production, a local `rsync` push is often more reliable than requesting the remote server to pull and compile constraints:
1. **rsync `public/assets/` directly:** Transferring the `public/assets/` directory (which contains all the locally generated manipulation variants from tasks like `GalleryResampleTask`) averts the need for the live environment to lazily reconstruct the cache on the fly.
2. **Deploy script:** Use a `.env`-backed `deploy.sh` script to dump the local migrated database and sync assets, effectively pushing the exact tested state to the remote.

## Heuristic Link Verification

After migrating structural links (especially mapping obsolete `BlockLinkID` or flat `PageLinkID` columns to the new `LinkField` records), verify site-specific hardcoded logic.
*   Custom templates or unique features (e.g., the SlideImage header or an About Page's "Learn More" links) may lose connection if they bypass standard Elemental structures.
*   Manual post-migration tasks (like a `FixAboutLinksTask`) should be built to map known edge case links.

## Troubleshooting

- If a task fails with a memory or timeout error, you may need to increase the PHP limits or execute the task via the browser interface instead of the CLI.
- Ensure `flush=1` is appended to each command to clear cached configuration and class manifests between tasks.
