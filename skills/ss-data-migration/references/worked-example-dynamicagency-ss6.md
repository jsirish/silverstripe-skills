# Worked example: dynamicagency.com SS6 upgrade

Reference implementation for the SS5 → SS6 section of [SKILL.md](../SKILL.md). Site-specific values, not a template.

The workflow was extracted from the dynamicagency.com SS5 to SS6 upgrade
(PR #284 in the site's own installer repository; PR/issue numbers below refer
to that repo). The concrete values used there, as a reference implementation:

- **Branch:** `feature/ss6-upgrade`; local site `https://dynamicagency.ddev.site`,
  production `https://www.dynamicagency.com`.
- **Task (3a):** `Dynamic\Agency\Task\LinkableMigrationTask`
  (`app/src/Task/LinkableMigrationTask.php`), run as
  `ddev sake tasks:linkable-to-linkfield-migration-task`.
- **Config:** `app/_config/linkfield-migration.yml` enables the task and
  declares the `StaffMember.ContactLinks` many_many relation so it migrates to
  a `has_many` (SortOrder to the Link `Sort` column). On success the task drops
  `LinkableLink` and `AgencyStaffMember_ContactLinks`.
- **Button styles:** Linkable's `Template` button-style carried into the
  linkfield `Style` field via `Dynamic\Agency\Extension\LinkStyleExtension`
  (permanent, kept after cleanup).
- **Expected result at time of writing:** `23 links migrated, 0 broken links`.
- **Page sweep (step 4):** `/`, `/about/about-us`, `/web-content-media-dynamic`,
  `/work/{case-study}`, `/community/giving-back`,
  `/about/careers/open-positions`, `/about/news`.
- **Spot check:** `/about/about-us` Single Feature link renders with
  `class="btn btn-primary btn-gradient"`.
- **Cleanup targets:** `app/src/Task/LinkableMigrationTask.php` and
  `app/_config/linkfield-migration.yml`; the `LinkStyleExtension` and linkfield
  app-code changes are permanent.
- The ideannotator docblock churn was tracked as issue #299 in the same repo.
