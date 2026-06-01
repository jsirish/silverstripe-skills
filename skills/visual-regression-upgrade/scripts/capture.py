#!/usr/bin/env python3
"""Capture full-page screenshots of matching URLs on two environments.

Usage:
    capture.py --prod https://prod.example.com --local https://uat.example.com \
               --paths "/,/about,/contact" --out ./vr-out
"""
import argparse
import getpass
import hashlib
import json
import os
import re
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

from playwright.sync_api import sync_playwright


def resolve_auth(cli_auth):
    """Resolve --auth in priority order: explicit CLI > env > prompt.

    CLI accepts:
      user:pass      → use directly (visible in argv — discouraged)
      env:VAR        → read user:pass from environment variable VAR
      env:USER/PASS  → read user from env USER, pass from env PASS
      prompt         → interactively prompt for username + password
    """
    if not cli_auth:
        return None
    if cli_auth == "prompt":
        user = input("HTTP auth username: ").strip()
        pw = getpass.getpass("HTTP auth password: ")
        return f"{user}:{pw}"
    if cli_auth.startswith("env:"):
        spec = cli_auth[4:]
        if "/" in spec:
            user_var, pass_var = spec.split("/", 1)
            user = os.environ.get(user_var, "").strip()
            pw = os.environ.get(pass_var, "")
            if not user or not pw:
                raise SystemExit(f"env vars {user_var}/{pass_var} not set or empty")
            return f"{user}:{pw}"
        val = os.environ.get(spec, "")
        if not val:
            raise SystemExit(f"env var {spec} not set or empty")
        return val
    return cli_auth


def slugify(path: str) -> str:
    """Produce a filesystem-safe, collision-resistant slug for a path.

    Long paths are truncated, and a short hash of the original path is
    appended so distinct inputs never collide (e.g. /foo/bar vs /foo-bar,
    or two long paths sharing the same prefix).
    """
    raw = path.strip("/") or "home"
    sanitized = re.sub(r"[^a-zA-Z0-9._-]+", "-", raw)[:100] or "home"
    digest = hashlib.sha1(path.encode("utf-8")).hexdigest()[:8]
    return f"{sanitized}-{digest}"


def parse_viewport(s: str):
    m = re.match(r"^(\d+)x(\d+)$", s)
    if not m:
        raise argparse.ArgumentTypeError(f"viewport must be WxH, got {s!r}")
    return {"width": int(m.group(1)), "height": int(m.group(2))}


def load_json(path):
    if not path:
        return None
    return json.loads(Path(path).read_text())


def auto_scroll(page):
    """Trigger lazy-load by scrolling to the bottom in steps.

    Bounded by both a maximum iteration count and a maximum elapsed time
    so infinite-scroll pages (where scrollHeight keeps growing as new
    content loads) can't hang the capture until the outer timeout fires.
    """
    page.evaluate(
        """async () => {
            await new Promise((resolve) => {
                const step = 400;
                const maxIterations = 200;          // ~80s worth of steps at 80ms
                const maxScroll = 50000;            // 50k px hard cap on page height
                const startedAt = Date.now();
                const maxElapsedMs = 8000;          // 8s wall-clock cap
                let total = 0;
                let iterations = 0;
                const timer = setInterval(() => {
                    iterations += 1;
                    window.scrollBy(0, step);
                    total += step;
                    const reachedEnd = total >= document.body.scrollHeight;
                    const tooManyIters = iterations >= maxIterations;
                    const tooTall = total >= maxScroll;
                    const tooLong = Date.now() - startedAt > maxElapsedMs;
                    if (reachedEnd || tooManyIters || tooTall || tooLong) {
                        clearInterval(timer);
                        window.scrollTo(0, 0);
                        resolve();
                    }
                }, 80);
            });
        }"""
    )


def apply_masks(page, selectors):
    if not selectors:
        return
    page.evaluate(
        """(sels) => {
            for (const sel of sels) {
                document.querySelectorAll(sel).forEach(el => {
                    el.style.background = '#cccccc';
                    el.style.color = '#cccccc';
                    el.style.borderColor = '#cccccc';
                    el.querySelectorAll('*').forEach(c => c.style.visibility = 'hidden');
                });
            }
        }""",
        selectors,
    )


def masks_for_path(masks_cfg, path):
    if not masks_cfg:
        return []
    out = []
    for pattern, sels in masks_cfg.items():
        if pattern == path or pattern == "*":
            out.extend(sels)
            continue
        if pattern.endswith("/*"):
            # Match the prefix WITH the trailing slash so "/news/*" matches
            # "/news/", "/news/123" but not "/newsletter".
            prefix = pattern[:-1]  # keep the trailing slash
            if path == prefix.rstrip("/") or path.startswith(prefix):
                out.extend(sels)
    return out


def capture_env(label, base_url, paths, out_dir, viewport, wait, auth, cookies, masks_cfg, insecure, wait_until):
    out_dir.mkdir(parents=True, exist_ok=True)
    results = []
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx_kwargs = {
            "viewport": viewport,
            "ignore_https_errors": insecure,
            "device_scale_factor": 1,
        }
        if auth:
            user, _, pw = auth.partition(":")
            ctx_kwargs["http_credentials"] = {"username": user, "password": pw}
        context = browser.new_context(**ctx_kwargs)
        if cookies:
            context.add_cookies(cookies)

        for path in paths:
            slug = slugify(path)
            out_path = out_dir / f"{slug}.png"
            url = base_url.rstrip("/") + path
            print(f"[{label}] {path} → {out_path.name}", flush=True)
            entry = {"path": path, "slug": slug, "url": url, "file": out_path.name}
            page = context.new_page()
            try:
                response = page.goto(url, wait_until=wait_until, timeout=45000)
                status = response.status if response is not None else None
                entry["http_status"] = status
                auto_scroll(page)
                page.wait_for_timeout(int(wait * 1000))
                apply_masks(page, masks_for_path(masks_cfg, path))
                page.screenshot(path=str(out_path), full_page=True)
                if status is not None and status >= 400:
                    # Still saved the screenshot for inspection, but flag
                    # the entry so the diff report classifies it as ERROR
                    # rather than silently passing matched 404/500 pages.
                    entry["ok"] = False
                    entry["error"] = f"HTTP {status}"
                    print(f"  [{label}] HTTP {status} — flagged", flush=True)
                else:
                    entry["ok"] = True
            except Exception as e:
                entry["ok"] = False
                entry["error"] = str(e)
                print(f"  [{label}] ERROR: {e}", flush=True)
            finally:
                page.close()
            results.append(entry)
        context.close()
        browser.close()
    return results


def main():
    ap = argparse.ArgumentParser(description="Capture full-page screenshots of two environments.")
    ap.add_argument("--prod", required=True, help="Production base URL")
    ap.add_argument("--local", required=True, help="Upgraded/UAT base URL")
    ap.add_argument("--paths", required=True, help="Comma-separated paths (e.g. /,/about,/contact)")
    ap.add_argument("--out", required=True, help="Output directory")
    ap.add_argument("--viewport", type=parse_viewport, default=parse_viewport("1440x900"))
    ap.add_argument(
        "--wait-until",
        choices=("load", "domcontentloaded", "networkidle", "commit"),
        default="load",
        help="Playwright navigation wait condition (default: load). Use networkidle for fully-static sites; load is safer on sites with analytics/chat/polling.",
    )
    ap.add_argument("--wait", type=float, default=2.0, help="Extra wait seconds after navigation completes")
    ap.add_argument(
        "--auth",
        help=(
            "HTTP basic auth. Accepts: 'user:pass' (visible in argv — discouraged), "
            "'env:VARNAME' (read user:pass from one env var), "
            "'env:USERVAR/PASSVAR' (split across two env vars), "
            "or 'prompt' for an interactive prompt."
        ),
    )
    ap.add_argument("--cookies", help="Path to Playwright-format cookies JSON")
    ap.add_argument("--mask", help='Path to masks.json: {"/path": ["selector", ...]}')
    ap.add_argument("--insecure", action="store_true", help="Ignore HTTPS errors (self-signed certs)")
    args = ap.parse_args()

    paths = [p.strip() for p in args.paths.split(",") if p.strip()]
    if not paths:
        sys.exit("--paths produced no entries")
    paths = [p if p.startswith("/") else "/" + p for p in paths]

    out = Path(args.out)
    cookies = load_json(args.cookies) if args.cookies else None
    masks_cfg = load_json(args.mask) if args.mask else None
    resolved_auth = resolve_auth(args.auth)

    started = datetime.now(timezone.utc).isoformat()
    prod_results = capture_env(
        "prod", args.prod, paths, out / "prod",
        args.viewport, args.wait, resolved_auth, cookies, masks_cfg, args.insecure, args.wait_until,
    )
    local_results = capture_env(
        "local", args.local, paths, out / "local",
        args.viewport, args.wait, resolved_auth, cookies, masks_cfg, args.insecure, args.wait_until,
    )
    finished = datetime.now(timezone.utc).isoformat()

    manifest = {
        "prod_base": args.prod,
        "local_base": args.local,
        "viewport": args.viewport,
        "wait": args.wait,
        "wait_until": args.wait_until,
        "started": started,
        "finished": finished,
        "paths": paths,
        "prod": prod_results,
        "local": local_results,
    }
    (out / "manifest.json").write_text(json.dumps(manifest, indent=2))
    print(f"\nWrote manifest: {out / 'manifest.json'}")
    print(f"Captured {len(paths)} paths × 2 envs.")


if __name__ == "__main__":
    main()
