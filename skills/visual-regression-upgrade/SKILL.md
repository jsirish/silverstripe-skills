---
name: visual-regression-upgrade
description: Verify visual parity between two web environments (production vs upgraded/UAT) by capturing full-page screenshots of matching URLs and producing a pixel-diff HTML report. USE THIS SKILL whenever an agent needs to answer "does it look the same", "check the upgrade visually", "compare prod and UAT", "did the layout change", "screenshot diff", "visual regression", or verify a CMS major-version upgrade (SilverStripe 4→5, Drupal 9→10, WordPress major bumps) hasn't broken the front-end. Self-contained — bundles a Playwright capture script, URL crawler, and pixel-diff report generator.
---

# Visual Regression for CMS Upgrades

Compares two deployed environments (e.g. production vs an upgraded UAT site) screenshot-by-screenshot and emits a single self-contained HTML report classifying every page as PASS / WARN / FAIL.

Use this whenever you need to prove — or disprove — that an upgrade looks identical to the live site.

## When to use

Trigger phrases:
- "does the upgrade look the same as prod"
- "compare prod and UAT visually"
- "visual regression check"
- "screenshot diff between two sites"
- "the layout looks different after the upgrade"
- "verify the SilverStripe 4 → 5 upgrade visually"
- "does it look identical"

## Prerequisites

```bash
pip install playwright pillow numpy requests
python -m playwright install chromium
```

Outputs live in a working directory you choose (e.g. `./vr-out/`).

## Workflow

### Step 1 — Discover URLs

```bash
python scripts/crawl_urls.py --url https://www.example.com --limit 30 --out paths.txt
```

Tries `/sitemap.xml` first; falls back to depth-2 link crawl from the homepage. Output is one path per line (e.g. `/`, `/about`, `/services/widgets`).

Review `paths.txt` and trim anything irrelevant (search pages, paginated archives) before continuing.

### Step 2 — Capture screenshots

```bash
PATHS=$(paste -sd, paths.txt)
python scripts/capture.py \
  --prod  https://www.example.com \
  --local https://uat.example.com \
  --paths "$PATHS" \
  --out ./vr-out
```

Optional flags:
- `--viewport 1440x900` (default)
- `--wait-until load` (default) — Playwright navigation condition. Use `networkidle` for fully-static sites; `load` is safer on sites with analytics/chat widgets/long-polling
- `--wait 2.0` — extra seconds to wait after navigation completes
- `--auth` / `--prod-auth` / `--local-auth` — HTTP basic auth, applied to both environments or scoped to one. Prefer `env:VR_AUTH` / `env:VR_USER/VR_PASS` / `prompt`. Use `--local-auth` when only UAT is protected to avoid sending UAT credentials to production.
- `--cookies` / `--prod-cookies` / `--local-cookies` — Playwright-format cookie list, applied to both or scoped to one environment
- `--mask masks.json` — `{ "/path/or/*": ["selector1", ".cookie-banner"] }` — paints these regions `#cccccc` before snapping. Use this for rotating banners, date stamps, "users online now" counters.

Writes `vr-out/prod/<slug>.png`, `vr-out/local/<slug>.png`, and `vr-out/manifest.json`.

### Step 3 — Diff + report

```bash
python scripts/diff_report.py --in ./vr-out --out ./vr-out/report
```

Produces:
- `vr-out/report/results.json` — machine-readable
- `vr-out/report/index.html` — **single self-contained file** (diff images base64-embedded) — open in browser or attach to a ticket

### Step 4 — Interpret

| Diff % | Status | Meaning |
|--------|--------|---------|
| < 0.5% | **PASS** (green) | Anti-aliasing / sub-pixel font rendering — ignore |
| 0.5 – 5% | **WARN** (amber) | Likely a real but minor change — inspect (margin shift, image swap, color tweak) |
| > 5% | **FAIL** (red) | Significant regression — investigate before signing off |

Tune masks and re-run if WARN/FAIL pages are all caused by the same dynamic widget.

## Example end-to-end

```bash
# 1. Discover
python scripts/crawl_urls.py --url https://www.example.com --limit 25 --out paths.txt

# 2. Capture (UAT behind basic auth — scoped to local only so prod doesn't receive UAT credentials)
export VR_AUTH="uatuser:uatpass"
python scripts/capture.py \
  --prod        https://www.example.com \
  --local       https://uat.example.com \
  --paths       "$(paste -sd, paths.txt)" \
  --local-auth  env:VR_AUTH \
  --mask        masks.json \
  --out         ./vr-out

# 3. Report
python scripts/diff_report.py --in ./vr-out --out ./vr-out/report
open ./vr-out/report/index.html
```

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| All screenshots blank/white | JS-heavy SPA not finished rendering | Bump `--wait` to 3-5s; check console errors in `manifest.json` |
| Every page reports ~100% diff | Viewport mismatch, or one env is mobile-redirecting | Force `--viewport 1440x900` on both; check redirects with curl |
| Font rendering diffs everywhere | UAT can't reach Google Fonts (firewall) | Add Google Fonts CSS link or font-rendered elements to `masks.json` |
| One side much taller | Lazy-loaded images on one env only | Capture script auto-scrolls; if still failing, increase `--wait` |
| Auth fails on UAT | Site uses form login, not basic auth | Capture cookies via browser devtools, save as `cookies.json` |
| Diff image looks like static | Both screenshots loaded but viewports differ | Confirm `--viewport` matches; some themes are width-responsive |
| `playwright._impl._errors.Error: net::ERR_CERT_AUTHORITY_INVALID` | Self-signed UAT cert | Use `--insecure` flag (sets `ignore_https_errors=True`) |

## SilverStripe-specific notes

See `references/silverstripe.md` for the full rundown. Quick checklist:

- Append `?flush=1` to the **first** path on each side (or pass it as the first entry in `--paths`) to bust template/manifest caches
- Compare **Live → Live**, never Live → Stage
- Admin paths (`/admin`, `/Security/login`) need auth — usually capture with cookies, not basic auth
- If UAT can't reach `fonts.googleapis.com`, mask `<link rel="stylesheet">` font-driven regions
- Missing images on UAT → check `assets/.protected/` symlink and `_resources/` publishing
- `silverstripe-cache/` left over from SS4 can poison SS5 — `rm -rf` and `?flush=1` before capturing

## Output artifact

The `index.html` report is fully self-contained and portable: hand it to a PM, attach it to a Jira ticket, or commit it alongside the upgrade PR. No external assets, no CDN dependencies.
