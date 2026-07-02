---
name: silverstripe-module-ss6-upgrade
description: Module-level (composer package) Silverstripe 6 upgrade workflow for silverstripe-vendormodule repos with their own CI. Use when upgrading a single module such as dynamic/silverstripe-calendar or dynamic/silverstripe-elemental-accordion to SS6, cutting a new integer branch, bumping framework constraints to ^6 and PHP to ^8.3, running the SS6 code sweep, updating gha-ci and PHPUnit config, tagging a release, and flipping the default branch. For upgrading a full project (a website root with recipes and DDEV) use the silverstripe-version-upgrade skill instead.
---

# Silverstripe Module SS6 Upgrade

Repeatable workflow for upgrading a **single Silverstripe module** (a `silverstripe-vendormodule` composer package with its own repo, CI, and Packagist listing) from SS5 to SS6. This is the loop you run once per module across a suite of repos such as the `dynamic/*` open-source modules.

> **Scope**: This skill covers the **module/package** upgrade loop. It is one of three related skills:
>
> - [silverstripe-version-upgrade](../silverstripe-version-upgrade/SKILL.md): full **project** upgrades (a website root: recipes, DDEV, data migration, deployment). Its SS6 sections are the canonical reference for namespace renames and breaking changes; this skill points there instead of restating them.
> - [ss6-data-migration](../ss6-data-migration/SKILL.md): **DB content** migration after a project upgrade.
> - This skill: the **module** loop (constraints, code sweep, CI, tag, default branch).
>
> Branch-naming and default-branch conventions come from [ss-branch-strategy](../ss-branch-strategy/SKILL.md) (from `jsirish/workflow-skills`); this skill applies that convention, it does not redefine it.

Reference implementation: [dynamic/silverstripe-calendar#112](https://github.com/dynamic/silverstripe-calendar/issues/112) (branch `2` to `3`, tagged `3.0.0`).

## Upgrade Phases (Summary)

| Phase | Key Actions |
|-------|-------------|
| **1. Ordering & Assessment** | Place the module in the dependency graph; upgrade leaf modules first |
| **2. Branch Setup** | New integer branch off the current SS5 default, per ss-branch-strategy |
| **3. Composer Constraints** | `php ^8.3`, framework/cms `^6`, per-dependency bumps, `branch-alias` |
| **4. Code Sweep** | SS6 namespace renames, typed signatures, BuildTask, BaseElement changes |
| **5. Rector, PHPStan, PHPCS** | Automated rules first, then static analysis and style |
| **6. CI & PHPUnit Config** | `silverstripe/gha-ci` matrix regeneration, PHPUnit 11 config |
| **7. PR, Tag, Default Branch** | Merge to the integer branch, tag, flip default, verify Packagist |
| **8. Testbed Verification** | Install into an SS6 project, `dev/build flush=1`, exercise the module |

## Do not rationalize

Every phase gate requires evidence: actual command output, not a recollection or an inference. The shortcuts agents talk themselves into on module upgrades, and the required counter-behavior:

| Rationalization | Required behavior |
|-----------------|-------------------|
| "The constraints look right" | Run `composer validate` and a real `composer update` (testbed or CI) and paste the resolved versions. A constraint that never resolved is a guess. |
| "Rector handled the renames" | Run the grep sweep after Rector and paste the zero-hit output. Rector does not rewrite YAML config, `Injector` strings, or docblocks. |
| "CI is green, so the module works" | CI installs the module against a bare scaffold. Install it into an SS6 testbed, run `dev/build flush=1`, and paste the output. Phase 8 is not optional. |
| "This module has no BuildTasks / elements / validate() overrides" | Paste the grep output proving it: `rg "extends BuildTask|extends BaseElement|function validate\(" src/`. Absence is demonstrated, not assumed. |
| "I tagged it, so Packagist has it" | Paste the version list from `https://packagist.org/packages/<vendor>/<module>` (or its API). Webhooks fail silently. |
| "The default branch is flipped" | Paste `gh api repos/<vendor>/<module> --jq .default_branch`. |
| "Tests probably still pass on PHPUnit 11" | Run `vendor/bin/phpunit` and paste the summary line. PHPUnit 10+ changed config schema and assertions; no run, no claim. |

## Phase 1: Ordering & Assessment

### 1.1 The dependency ordering problem

Modules form a dependency graph, and a module cannot resolve `silverstripe/framework ^6` while one of its own requirements still caps at SS5. **Upgrade leaf modules first**, then modules that depend on them, then recipes last:

1. **Leaf modules** (no dependencies on other in-house modules): e.g. `dynamic/silverstripe-elemental-baseobject`, `dynamic/flexslider`, `dynamic/silverstripe-calendar`.
2. **Mid-tier modules** (depend on leaf modules): e.g. the `dynamic/silverstripe-elemental-*` content blocks, which require `silverstripe-elemental-baseobject`.
3. **Recipes** (aggregate everything): e.g. `dynamic/recipe-silverstripe-base-site`, `dynamic/recipe-silverstripe-essentials-website`. A recipe is upgraded by bumping every member constraint, so it goes last.

While a dependency is upgraded on its integer branch but not yet tagged, depend on it with a dev constraint (`^6@dev`); `minimum-stability: dev` + `prefer-stable: true` in the module's composer.json makes this resolvable. Replace with the tagged constraint once the dependency releases.

### 1.2 Assess the module

```bash
gh repo view <vendor>/<module> --json defaultBranchRef --jq .defaultBranchRef.name
gh api repos/<vendor>/<module>/branches --jq '.[].name'

# What does it require, and are SS6 releases available for each?
cat composer.json | python3 -c "import json,sys; print(json.load(sys.stdin)['require'])"
```

For each third-party requirement, confirm an SS6-compatible release exists on Packagist. If one does not, follow the fork-and-upstream workflow in [ss-branch-strategy](../ss-branch-strategy/SKILL.md) or find a maintained replacement (see 3.3).

**Evidence gate (Phase 1):** a written ordering position ("leaf", "mid-tier after X", "recipe") and, for every requirement, either the SS6-compatible version number or the replacement/fork decision. Paste the requirement list with the target version beside each entry.

## Phase 2: Branch Setup

Integer branch naming per [ss-branch-strategy](../ss-branch-strategy/SKILL.md): the new branch integer is the module's **next major version** (or the recipe version, if the repo versions against a shared recipe). Example: `dynamic/silverstripe-calendar` was on `2` (SS5), so SS6 work went to `3`.

```bash
# Create the new integer branch from the current SS5 default
git fetch origin
git checkout -b 3 origin/2
git push -u origin 3

# Do the work on a feature branch targeting the new integer branch
git checkout -b feature/ss6-upgrade
```

Do **not** flip the default branch yet; that happens after merge and tag (Phase 7). The old integer branch (`2`) stays for SS5 backports.

## Phase 3: Composer Constraints

### 3.1 Core bumps

Every module gets, at minimum:

```json
{
    "require": {
        "php": "^8.3",
        "silverstripe/cms": "^6.0"
    },
    "extra": {
        "branch-alias": {
            "dev-3": "3.x-dev"
        }
    }
}
```

- Modules that only need the framework require `silverstripe/framework: ^6` instead of `silverstripe/cms`.
- The `branch-alias` key must match the new integer branch (`dev-3` for branch `3`) and the new major (`3.x-dev`). Without it, `^3@dev` constraints in downstream consumers will not resolve to the branch.
- Elemental content blocks bump `dnadesign/silverstripe-elemental` to `^6`.

### 3.2 Common third-party bumps

Observed across the `dynamic/*` SS6 sweep (verify each on Packagist rather than trusting this table blindly):

| Package | SS6 constraint |
|---------|----------------|
| `silverstripe/lumberjack` | `^4` |
| `symbiote/silverstripe-gridfieldextensions` | `^5` |
| `symbiote/silverstripe-queuedjobs` | `^6` |
| `unclecheese/display-logic` | `^4` |
| `silverstripe/linkfield` | `^4` |
| `silverstripe/vendor-plugin` (if pinned) | `^3` |

### 3.3 Replacements for packages with no SS6 release

| Package | Replacement |
|---------|-------------|
| `ryanpotter/silverstripe-color-field` | `tractorcow/silverstripe-colorpicker` |
| `nathancox/embedfield` | `fromholdio/silverstripe-embedfield ^5.1` |
| `sheadawson/silverstripe-linkable` | `silverstripe/linkfield ^4` |

The full project-level removal list lives in [silverstripe-version-upgrade references/version-map-ss6.md](../silverstripe-version-upgrade/references/version-map-ss6.md).

### 3.4 Dev dependencies

```json
"require-dev": {
    "cambis/silverstan": "^2.1",
    "phpstan/extension-installer": "^1.3",
    "silverstripe/recipe-testing": "^4",
    "squizlabs/php_codesniffer": "^3.7"
}
```

> [!WARNING]
> A stale `silverstripe/recipe-testing: ^3` blocks resolution: recipe-testing 3.x requires `silverstripe/framework ^5`. SS6 needs `^4`, which also moves the test suite to PHPUnit `^11.3` (see Phase 6.2).

Also drop unused requirements while you are in the file; the reference implementation removed `dft/silverstripe-frontend-multiselectfield` after confirming zero usages by grep.

**Evidence gate (Phase 3):** paste the output of `composer validate` plus a successful dependency resolution (a `composer update` in a throwaway checkout, the Phase 8 testbed, or the first green CI install). The edited JSON alone does not clear this gate.

## Phase 4: Code Sweep

The canonical SS6 rename and breaking-change tables live in [silverstripe-version-upgrade](../silverstripe-version-upgrade/SKILL.md) (Phase 4 and the "SS6 Breaking Changes" section) and [references/version-map-ss6.md](../silverstripe-version-upgrade/references/version-map-ss6.md). Do not restate them; run them against the module's `src/` and `tests/` instead of `app/src/`:

```bash
# Namespace renames (each old FQCN is a runtime fatal in SS6)
rg "SilverStripe\\\\View\\\\ViewableData" src/ tests/
rg "SilverStripe\\\\View\\\\ArrayData" src/ tests/
rg "SilverStripe\\\\ORM\\\\ArrayList" src/ tests/
rg "SilverStripe\\\\ORM\\\\ValidationResult" src/ tests/
rg "SilverStripe\\\\ORM\\\\ValidationException" src/ tests/
```

Module-specific checklist (the items that recur across `dynamic/*` modules):

1. **`ArrayList`** moved to `SilverStripe\Model\List\ArrayList`; `ArrayData` to `SilverStripe\Model\ArrayData`; `ViewableData` is now `SilverStripe\Model\ModelData`.
2. **`ValidationResult`** moved to `SilverStripe\Core\Validation\ValidationResult`, and every `validate()` override must declare the return type: `public function validate(): ValidationResult`.
3. **BuildTask signature**: `run($request)` becomes `execute(InputInterface $input, PolyOutput $output): int` returning `Command::SUCCESS`, with `protected static string $commandName`. The full old-vs-new table and a task template are in [silverstripe-version-upgrade](../silverstripe-version-upgrade/SKILL.md) Phase 4 and its [references/data-migration-tasks.md](../silverstripe-version-upgrade/references/data-migration-tasks.md).
4. **`BaseElement::getType()` / `getDescription()`** are replaced by config: use `private static string $class_description` (and keep `$singular_name` / `$plural_name` config for the type label). Applies to every elemental content block module.
5. **`ModelData` subclass overrides** of `__get`, `__set`, `__isset`, `hasField`, `getField`, `setField`, etc. must add the typed parameters and return types the SS6 parent declares, or PHP throws declaration-compatibility fatals.
6. **`forTemplate()`** must declare `: string` and never return `false`; return `''` instead.

```bash
# Prove presence or absence of each pattern
rg "extends BuildTask" src/
rg "extends BaseElement" src/
rg "function getType\(|function getDescription\(" src/
rg "public function validate\(\)(?!\s*:)" src/ --pcre2
rg "function forTemplate\(\)(?!\s*:)" src/ --pcre2
```

**Evidence gate (Phase 4):** paste the grep sweep output showing zero remaining hits for every old FQCN and untyped signature, run **after** the Rector pass in Phase 5 (Rector misses YAML, string class references, and docblocks).

## Phase 5: Rector, PHPStan, PHPCS

Tooling, configs, and the SS6 Rector level set (`SilverstripeLevelSetList::UP_TO_SS_6_0`) are documented in [silverstripe-version-upgrade references/code-quality.md](../silverstripe-version-upgrade/references/code-quality.md). For a module the paths are `src/` and `tests/`, not `app/src/`:

```bash
vendor/bin/rector --dry-run    # review, then apply
vendor/bin/rector
vendor/bin/phpstan analyse src/ tests/
vendor/bin/phpcs src/ tests/
```

`phpstan.neon.dist` in a module scans the module's own `src/` (there is no vendor-path problem here; that warning in code-quality.md applies to project roots).

**Evidence gate (Phase 5):** paste the summary line of each tool run (Rector applied-rules count, PHPStan error count, PHPCS error count). Then re-run the Phase 4 greps and paste the zero-hit output.

## Phase 6: CI & PHPUnit Config

### 6.1 gha-ci matrix

Modules use the shared Silverstripe CI workflow:

```yaml
# .github/workflows/ci.yml
name: CI
on:
  push:
  pull_request:
  workflow_dispatch:
jobs:
  ci:
    name: CI
    uses: silverstripe/gha-ci/.github/workflows/ci.yml@v1
    with:
      phpcoverage: false
      js: false
```

`gha-ci` **derives the PHP and database matrix from composer.json constraints** (via `silverstripe/gha-generate-matrix`), so once `php ^8.3` and `cms ^6` land on the branch, the next run generates the SS6 matrix automatically. Manual matrix edits are only needed for non-standard setups. Verify:

- The workflow references `@v1` (the floating major tag), not a pinned old minor that predates SS6 support.
- The workflow triggers on `push` and `pull_request` so the new integer branch gets runs.
- No branch filters still name the old branch only.

### 6.2 PHPUnit config for SS6

`silverstripe/recipe-testing ^4` moves modules to PHPUnit `^11.3`. Update `phpunit.xml.dist`: the schema version bumps, and PHPUnit 10+ moved coverage includes from `<coverage>` into `<source>`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="vendor/silverstripe/cms/tests/bootstrap.php"
         colors="true"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.3/phpunit.xsd">
    <testsuites>
        <testsuite name="default">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src/</directory>
        </include>
    </source>
</phpunit>
```

The bootstrap path (`vendor/silverstripe/cms/tests/bootstrap.php`, or `vendor/silverstripe/framework/tests/bootstrap.php` for framework-only modules) is unchanged.

**Evidence gate (Phase 6):** a green CI run on the PR, with the matrix jobs visibly running PHP 8.3+ against CMS 6. Paste the run URL and the job list (`gh run view <id> --json jobs --jq '.jobs[].name'`).

## Phase 7: PR, Tag, Default Branch, Packagist

1. **Open the PR** from `feature/ss6-upgrade` into the new integer branch (`3`), referencing the module's SS6 issue.
2. **Merge** once CI is green and review passes.
3. **Tag** the release on the integer branch:

    ```bash
    git checkout 3 && git pull
    git tag 3.0.0
    git push origin 3.0.0
    ```

    During a coordinated multi-module sweep it is normal to hold tagging until downstream consumers have verified against `^3@dev`; tag when the module is proven in the testbed (Phase 8).

4. **Flip the default branch** to the new integer branch, keeping the old one for backports:

    ```bash
    gh api -X PATCH repos/<vendor>/<module> -f default_branch=3
    ```

5. **Verify Packagist** picked up the tag and the new branch alias (webhook-driven, but webhooks fail silently):

    ```bash
    curl -s https://repo.packagist.org/p2/<vendor>/<module>.json \
      | python3 -c "import json,sys; print([v['version'] for v in list(json.load(sys.stdin)['packages'].values())[0]][:5])"
    ```

**Evidence gate (Phase 7):** paste the merged PR URL, the tag from `git tag -l '3.*'`, the default branch from `gh api repos/<vendor>/<module> --jq .default_branch`, and the Packagist version list showing the new tag.

## Phase 8: Testbed Verification

CI proves the module installs and its unit tests pass against a scaffold. It does not prove the module works inside a real SS6 site. Install it into an SS6 testbed project (any DDEV project already on SS6, or a scratch `silverstripe/installer ^6` checkout):

```bash
# In the testbed project
ddev composer require <vendor>/<module>:^3@dev   # or ^3.0 once tagged
ddev sake dev/build flush=1
```

Then exercise the module:

- `dev/build` output shows the module's tables and fields, with no errors and no obsolete-type warnings introduced by the module.
- The CMS section or elemental block the module provides loads and saves.
- For modules with BuildTasks: `ddev sake tasks` lists them (proves the `commandName` conversion worked), and each task runs to `SUCCESS`.
- The front-end template output renders (no `forTemplate()` fatals).

**Evidence gate (Phase 8):** paste the `dev/build` output and, for each BuildTask, the `ddev sake tasks` listing plus one task run. "CI was green" does not clear this gate.

## Per-module checklist

- [ ] Ordering position established; all dependencies have an SS6 path (Phase 1)
- [ ] Integer branch created off the SS5 default; feature branch targets it (Phase 2)
- [ ] `php ^8.3`, framework/cms `^6`, third-party bumps, `branch-alias` (Phase 3)
- [ ] Namespace renames, typed signatures, BuildTask, `$class_description`, `forTemplate(): string` (Phase 4)
- [ ] Rector SS6 level set applied; PHPStan and PHPCS clean; greps re-run (Phase 5)
- [ ] gha-ci on `@v1`, matrix regenerated; PHPUnit 11 config (Phase 6)
- [ ] PR merged, tagged, default branch flipped, Packagist verified (Phase 7)
- [ ] Installed in an SS6 testbed, `dev/build flush=1` clean, functionality exercised (Phase 8)

## Related skills

- [silverstripe-version-upgrade](../silverstripe-version-upgrade/SKILL.md): the project-level SS6 upgrade this skill feeds into; canonical home of the SS6 rename tables, breaking changes, and version maps.
- [ss6-data-migration](../ss6-data-migration/SKILL.md): DB content migration after the project upgrade.
- [ss-branch-strategy](../ss-branch-strategy/SKILL.md) (from `jsirish/workflow-skills`): the integer-branch, default-branch, and fork-and-upstream conventions applied in Phases 2 and 7.
