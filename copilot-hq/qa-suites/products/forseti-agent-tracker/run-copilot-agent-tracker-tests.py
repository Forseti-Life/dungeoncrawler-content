#!/usr/bin/env python3
"""
QA Test Suite: copilot_agent_tracker
Covers: dashboard ACL routes, telemetry API error modes, data integrity, performance.

PM decision (2026-03-01): Accept HTTP 403 for anonymous access to all /admin/* routes.
Basis: org-chart/sites/forseti.life/qa-permissions.json admin-area rule (anon: deny).
Both 403 and 302 are considered pass for anon routes.

Usage:
    FORSETI_COOKIE_ADMIN=<cookie-value> \
    TELEMETRY_TOKEN=<token-value> \
    python3 qa-suites/products/forseti-agent-tracker/run-copilot-agent-tracker-tests.py

Or set FORSETI_BASE_URL (default: http://localhost).
"""
import os
import sys
import time
import json
import subprocess
import urllib.request
import urllib.error
import urllib.parse

BASE_URL = os.environ.get("FORSETI_BASE_URL", "http://localhost").rstrip("/")
COOKIE = os.environ.get("FORSETI_COOKIE_ADMIN", "")
TOKEN = os.environ.get("TELEMETRY_TOKEN", "")
DRUPAL_ROOT = os.environ.get(
    "DRUPAL_ROOT",
    "/home/ubuntu/forseti.life/sites/forseti"
)

# Attempt to auto-fetch token from drush if not set
if not TOKEN:
    try:
        result = subprocess.run(
            [
                "vendor/bin/drush", "php:eval",
                "echo \\Drupal::state()->get('copilot_agent_tracker.telemetry_token', 'NOTSET');"
            ],
            capture_output=True, text=True, cwd=DRUPAL_ROOT, timeout=15
        )
        TOKEN = result.stdout.strip()
    except Exception:
        TOKEN = ""

# Attempt to auto-fetch admin cookie from drush if not set
if not COOKIE:
    try:
        result = subprocess.run(
            ["vendor/bin/drush", "--uri=http://localhost", "user:login", "--uid=1"],
            capture_output=True, text=True, cwd=DRUPAL_ROOT, timeout=15
        )
        login_url = result.stdout.strip().split("\n")[-1].strip()
        if login_url.startswith("http"):
            curl = subprocess.run(
                ["curl", "-s", "-c", "/tmp/qa-tracker-cookie.txt", "-L", login_url, "-o", "/dev/null"],
                capture_output=True, timeout=15
            )
            with open("/tmp/qa-tracker-cookie.txt") as cf:
                for line in cf:
                    if "SESS" in line:
                        # Handle both plain and #HttpOnly_ prefixed Netscape cookie lines
                        clean = line.strip().lstrip("#HttpOnly_").lstrip("#")
                        parts = clean.split("\t")
                        if len(parts) >= 7:
                            COOKIE = f"{parts[5]}={parts[6]}"
                            break
    except Exception:
        COOKIE = ""

RESULTS = []

def http_get(path, cookie=None, follow=True):
    url = BASE_URL + path
    req = urllib.request.Request(url)
    if cookie:
        req.add_header("Cookie", cookie)
    try:
        redirect_count = [0]
        if follow:
            resp = urllib.request.urlopen(req, timeout=10)
        else:
            old_do_request = urllib.request.urlopen
            # Disable redirects by using a custom opener
            no_redirect = urllib.request.build_opener(
                urllib.request.HTTPErrorProcessor()
            )
            no_redirect.addheaders = req.headers.items() if hasattr(req.headers, 'items') else []
            try:
                resp = no_redirect.open(req, timeout=10)
            except urllib.error.HTTPError as e:
                return e.code, ""
        return resp.status, resp.read().decode(errors="replace")
    except urllib.error.HTTPError as e:
        return e.code, ""
    except Exception as e:
        return 0, str(e)


def http_post(path, data, token=None, content_type="application/json", cookie=None):
    url = BASE_URL + path
    if isinstance(data, str):
        body = data.encode()
    else:
        body = json.dumps(data).encode()
    req = urllib.request.Request(url, data=body, method="POST")
    req.add_header("Content-Type", content_type)
    if token:
        req.add_header("X-Copilot-Agent-Tracker-Token", token)
    if cookie:
        req.add_header("Cookie", cookie)
    try:
        resp = urllib.request.urlopen(req, timeout=10)
        return resp.status, resp.read().decode(errors="replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode(errors="replace")
    except Exception as e:
        return 0, str(e)


def run_drush(args, cwd=None):
    cmd = ["vendor/bin/drush"] + args
    r = subprocess.run(cmd, capture_output=True, text=True, cwd=cwd or DRUPAL_ROOT, timeout=30)
    return r.returncode, r.stdout, r.stderr


def check(test_id, description, actual, expected, body=""):
    passed = actual == expected
    status = "PASS" if passed else "FAIL"
    RESULTS.append({
        "id": test_id,
        "description": description,
        "expected": expected,
        "actual": actual,
        "status": status,
        "body_excerpt": body[:200] if body else ""
    })
    icon = "✓" if passed else "✗"
    print(f"  [{status}] {icon} {test_id}: {description} (expected={expected}, got={actual})")
    return passed


def check_in(test_id, description, actual, accepted, body=""):
    passed = actual in accepted
    status = "PASS" if passed else "FAIL"
    RESULTS.append({
        "id": test_id,
        "description": description,
        "expected": accepted,
        "actual": actual,
        "status": status,
        "body_excerpt": body[:200] if body else ""
    })
    icon = "✓" if passed else "✗"
    print(f"  [{status}] {icon} {test_id}: {description} (accepted={accepted}, got={actual})")
    return passed


# --- Suite ---

print("\n== copilot_agent_tracker QA Suite ==\n")

# 1. Dashboard admin → 200 (follow redirect)
code, body = http_get("/admin/reports/copilot-agent-tracker", cookie=COOKIE, follow=True)
check("dashboard-admin-200", "Dashboard admin → 200", code, 200)

# 2. Releases page admin → 200
code, body = http_get("/admin/reports/copilot-agent-tracker/releases", cookie=COOKIE, follow=True)
check("releases-admin-200", "Releases page admin → 200", code, 200)

# 3. Waiting-on-Keith page admin → 200
code, body = http_get("/admin/reports/waitingonkeith", cookie=COOKIE, follow=True)
check("waitingonkeith-admin-200", "Waiting-on-Keith admin → 200", code, 200)

# 4-6. Anon access to admin routes → 403 or 302 (PM: accept both)
for path, tid in [
    ("/admin/reports/copilot-agent-tracker", "dashboard-anon-403"),
    ("/admin/reports/copilot-agent-tracker/releases", "releases-anon-403"),
    ("/admin/reports/waitingonkeith", "waitingonkeith-anon-403"),
]:
    code, body = http_get(path, cookie=None, follow=False)
    check_in(tid, f"Anon {path} → 403 or 302", code, [403, 302])

# 7. API POST no token → 403
code, body = http_post("/api/copilot-agent-tracker/event",
                       {"agent_id": "qa-test", "summary": "test"}, token=None)
check("api-no-token-403", "API POST no token → 403", code, 403)

# 8. API POST bad token → 403
code, body = http_post("/api/copilot-agent-tracker/event",
                       {"agent_id": "qa-test", "summary": "test"}, token="bad-token-xyz")
check("api-bad-token-403", "API POST bad token → 403", code, 403)

# 9. API POST valid token + payload → 200
code, body = http_post("/api/copilot-agent-tracker/event",
                       {"agent_id": "qa-test-agent", "summary": "QA recovery test"},
                       token=TOKEN)
check("api-valid-200", "API POST valid token + payload → 200", code, 200)

# 10. API POST empty body → 400 "Missing JSON payload."
code, body = http_post("/api/copilot-agent-tracker/event", data="", token=TOKEN,
                       content_type="application/json")
check("api-empty-body-400", "API empty body → 400", code, 400)
check("api-empty-body-msg", "API empty body message contains 'Missing JSON payload'",
      "Missing JSON payload." in body, True, body)

# 11. API POST missing summary → 400 "summary is required."
code, body = http_post("/api/copilot-agent-tracker/event",
                       {"agent_id": "qa-test"}, token=TOKEN)
check("api-missing-summary-400", "API missing summary → 400", code, 400)
check("api-missing-summary-msg", "API missing summary message correct",
      "summary is required." in body, True, body)

# 12. API POST malformed JSON → 400
code, body = http_post("/api/copilot-agent-tracker/event", data="{not valid json",
                       token=TOKEN, content_type="application/json")
check("api-malformed-json-400", "API malformed JSON → 400", code, 400)
check("api-malformed-json-msg", "API malformed JSON message correct",
      "Invalid JSON payload." in body, True, body)

# 13. drush updb exit 0
rc, out, err = run_drush(["updb", "-y"])
check("drush-updb-clean", "drush updb exits 0 (no pending updates)", rc, 0)

# 14. Watchdog: 0 ERROR/CRITICAL from copilot_agent_tracker
rc2, out2, err2 = run_drush(["watchdog:show", "--count=200"])
error_lines = [l for l in out2.splitlines()
               if "copilot_agent_tracker" in l and
               any(x in l.lower() for x in ["error", "critical"])]
check("watchdog-clean", "0 error/critical watchdog entries for copilot_agent_tracker",
      len(error_lines), 0, "\n".join(error_lines[:3]))

# 15. Performance: dashboard loads < 3s
start = time.time()
http_get("/admin/reports/copilot-agent-tracker", cookie=COOKIE, follow=True)
elapsed = time.time() - start
check("perf-dashboard-lt3s", f"Dashboard loads < 3s (actual={elapsed:.2f}s)",
      elapsed < 3.0, True)

# 16. DB tables exist
rc3, out3, err3 = run_drush([
    "php:eval",
    "echo \\Drupal::database()->schema()->tableExists('copilot_agent_tracker_agents') ? 'Y' : 'N'; "
    "echo \\Drupal::database()->schema()->tableExists('copilot_agent_tracker_events') ? 'Y' : 'N';"
])
db_out = out3.strip()
check("db-table-agents-exists", "DB table copilot_agent_tracker_agents exists",
      "Y" in db_out, True, db_out)
check("db-table-events-exists", "DB table copilot_agent_tracker_events exists",
      db_out.count("Y") >= 2, True, db_out)

# 17. Telemetry token is non-empty
check("token-nonempty", "Telemetry token is set (non-empty)", bool(TOKEN), True)

# --- EXTEND test cases ---

# 18. CSRF: forged approve POST without valid token → 403
code, body = http_post("/admin/reports/waitingonkeith/9999/approve",
                       data="token=forged-token-xyz",
                       cookie=COOKIE,
                       content_type="application/x-www-form-urlencoded")
check("csrf-forged-approve-403",
      "CSRF: forged approve POST (no valid token) → 403",
      code, 403)

# 19. Upsert dedup: same agent_id posted twice → exactly 1 row in agents table
DEDUP_AGENT = "qa-extend-dedup-test"
# Remove prior test row if present
run_drush(["php:eval",
           f"\\Drupal::database()->delete('copilot_agent_tracker_agents')"
           f"->condition('agent_id', '{DEDUP_AGENT}')->execute();"])
http_post("/api/copilot-agent-tracker/event",
          {"agent_id": DEDUP_AGENT, "summary": "first ingest", "role": "qa"},
          token=TOKEN)
http_post("/api/copilot-agent-tracker/event",
          {"agent_id": DEDUP_AGENT, "summary": "second ingest", "role": "qa"},
          token=TOKEN)
rc_dup, out_dup, _ = run_drush(["php:eval",
    f"echo \\Drupal::database()->query("
    f"'SELECT COUNT(*) FROM {{copilot_agent_tracker_agents}} WHERE agent_id = :id',"
    f"[':id' => '{DEDUP_AGENT}'])->fetchField();"])
row_count = int(out_dup.strip()) if out_dup.strip().isdigit() else -1
check("upsert-dedup-1-row",
      "Upsert dedup: double POST with same agent_id → exactly 1 row in agents table",
      row_count, 1, f"row_count={row_count}")

# 20. hook_uninstall: all 4 tables absent after drush pmu, then re-enable
TABLES = [
    "copilot_agent_tracker_agents",
    "copilot_agent_tracker_events",
    "copilot_agent_tracker_replies",
    "copilot_agent_tracker_inbox_resolutions",
]
run_drush(["pmu", "copilot_agent_tracker", "-y"])
check_expr = " ".join(
    [f"echo \\Drupal::database()->schema()->tableExists('{t}') ? 'Y' : 'N';"
     for t in TABLES]
)
rc_u, out_u, _ = run_drush(["php:eval", check_expr])
tables_absent = out_u.strip().count("Y") == 0
check("hook-uninstall-tables-absent",
      "hook_uninstall: all 4 module tables absent after drush pmu",
      tables_absent, True, f"drush output: {out_u.strip()}")
# Re-enable module so subsequent test environments are clean
run_drush(["pm-enable", "copilot_agent_tracker", "-y"])
run_drush(["cr"])

# --- Summary ---
total = len(RESULTS)
passed = sum(1 for r in RESULTS if r["status"] == "PASS")
failed = total - passed

print(f"\n== Results: {passed}/{total} PASS ==")
if failed:
    print("\nFailed tests:")
    for r in RESULTS:
        if r["status"] == "FAIL":
            print(f"  - {r['id']}: {r['description']}")

# Write results JSON
out_dir = "sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest"
os.makedirs(out_dir, exist_ok=True)
results_file = os.path.join(out_dir, "test-results.json")
with open(results_file, "w") as f:
    json.dump({"total": total, "passed": passed, "failed": failed, "results": RESULTS}, f, indent=2)
print(f"\nResults written to: {results_file}")

sys.exit(0 if failed == 0 else 1)
