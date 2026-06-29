#!/usr/bin/env bash

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ISSUES_FILE="${ISSUES_FILE:-$REPO_ROOT/Issues.md}"
SITE_DIR="${SITE_DIR:-$REPO_ROOT/sites/dungeoncrawler}"
DRUSH_BIN="${DRUSH_BIN:-$SITE_DIR/vendor/bin/drush}"
SLEEP_SECONDS="${SLEEP_SECONDS:-180}"
BATCH_SIZE="${BATCH_SIZE:-50}"
GITHUB_REPO="${GITHUB_REPO:-}"
DRY_RUN="${DRY_RUN:-0}"

usage() {
  cat <<'USAGE'
Usage: ./script/import-open-issues-to-github.sh [options]

Imports remaining Open rows from Issues.md into GitHub issues using the
existing Drupal service: dungeoncrawler_tester.github_issue_pr_client.

Options:
  --issues-file PATH   Path to Issues.md (default: ./Issues.md)
  --site-dir PATH      Drupal site dir with vendor/bin/drush (default: ./sites/dungeoncrawler)
  --drush-bin PATH     Drush binary path (default: <site-dir>/vendor/bin/drush)
  --repo OWNER/NAME    Override GitHub repo (default: resolved from module settings)
  --sleep SECONDS      Delay between created issues (default: 180)
  --batch-size COUNT   Stop after creating COUNT new issues (default: 50)
  --dry-run            Parse and print what would be created; do not call GitHub
  -h, --help           Show this help

Environment overrides:
  ISSUES_FILE, SITE_DIR, DRUSH_BIN, GITHUB_REPO, SLEEP_SECONDS, BATCH_SIZE, DRY_RUN

Notes:
  - Issue titles are created as: "<ID> <Title>" for stable dedupe matching.
  - Script skips items that already exist in the target repo (title exact match).
  - Assignment attempts use username "copilot" (for @copilot).
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --issues-file)
      ISSUES_FILE="$2"
      shift 2
      ;;
    --site-dir)
      SITE_DIR="$2"
      shift 2
      ;;
    --drush-bin)
      DRUSH_BIN="$2"
      shift 2
      ;;
    --repo)
      GITHUB_REPO="$2"
      shift 2
      ;;
    --sleep)
      SLEEP_SECONDS="$2"
      shift 2
      ;;
    --batch-size)
      BATCH_SIZE="$2"
      shift 2
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ ! -f "$ISSUES_FILE" ]]; then
  echo "Issues file not found: $ISSUES_FILE" >&2
  exit 1
fi

if [[ ! -x "$DRUSH_BIN" ]]; then
  echo "Drush binary not found or not executable: $DRUSH_BIN" >&2
  exit 1
fi

if ! [[ "$SLEEP_SECONDS" =~ ^[0-9]+$ ]]; then
  echo "--sleep must be an integer number of seconds." >&2
  exit 1
fi

if ! [[ "$BATCH_SIZE" =~ ^[0-9]+$ ]] || [[ "$BATCH_SIZE" -lt 1 ]]; then
  echo "--batch-size must be an integer greater than 0." >&2
  exit 1
fi

parse_open_rows() {
  python3 - "$ISSUES_FILE" <<'PY'
import sys

path = sys.argv[1]

def clean(cell: str) -> str:
    return cell.strip()

with open(path, 'r', encoding='utf-8') as f:
    for raw in f:
        line = raw.rstrip('\n')
        if not line.startswith('|'):
            continue
        parts = [clean(p) for p in line.split('|')]
        # Expected markdown row: | ID | Title | Current Status | Owner | Created | Last Updated | Notes |
        if len(parts) < 9:
            continue
        issue_id = parts[1]
        title = parts[2]
        status = parts[3]
        owner = parts[4]
        created = parts[5]
        updated = parts[6]
        notes = parts[7]

        if issue_id in ("ID", "---"):
            continue
        if '-' not in issue_id:
            continue
        if status != 'Open':
            continue

        # Output TSV with escaped tabs/newlines removed for shell-safe reads.
        title = title.replace('\t', ' ').replace('\r', ' ').replace('\n', ' ')
        owner = owner.replace('\t', ' ').replace('\r', ' ').replace('\n', ' ')
        created = created.replace('\t', ' ').replace('\r', ' ').replace('\n', ' ')
        updated = updated.replace('\t', ' ').replace('\r', ' ').replace('\n', ' ')
        notes = notes.replace('\t', ' ').replace('\r', ' ').replace('\n', ' ')
        print(f"{issue_id}\t{title}\t{owner}\t{created}\t{updated}\t{notes}")
PY
}

open_rows=()
while IFS= read -r line; do
  [[ -z "$line" ]] && continue
  open_rows+=("$line")
done < <(parse_open_rows)

if [[ ${#open_rows[@]} -eq 0 ]]; then
  echo "No open issue rows found in $ISSUES_FILE"
  exit 0
fi

echo "Found ${#open_rows[@]} open rows in $ISSUES_FILE"
echo "Batch target: create up to ${BATCH_SIZE} new issues this run"
if [[ "$DRY_RUN" == "1" ]]; then
  echo "Running in DRY_RUN mode."
fi

created_count=0
skipped_count=0
failed_count=0
processed_count=0
batch_limit_reached=0

for row in "${open_rows[@]}"; do
  if [[ "$created_count" -ge "$BATCH_SIZE" ]]; then
    batch_limit_reached=1
    break
  fi

  processed_count=$((processed_count + 1))
  IFS=$'\t' read -r issue_id issue_title issue_owner issue_created issue_updated issue_notes <<< "$row"

  full_title="$issue_id $issue_title"
  body=$(cat <<EOF
Source: Issues.md

Tracker ID: $issue_id
Owner: $issue_owner
Created: $issue_created
Last Updated: $issue_updated

Notes:
$issue_notes

Imported by script/import-open-issues-to-github.sh.
EOF
)

  echo "---"
  echo "[$(date '+%H:%M:%S')] Processing: $full_title"

if [[ "$DRY_RUN" == "1" ]]; then
  echo "[DRY RUN] Would create issue and assign @copilot"
  skipped_count=$((skipped_count + 1))
  if [[ "$processed_count" -lt "${#open_rows[@]}" ]]; then
    echo "[$(date '+%H:%M:%S')] Sleeping ${SLEEP_SECONDS}s before next issue..."
    sleep "$SLEEP_SECONDS"
  fi
  continue
fi

title_b64="$(printf '%s' "$full_title" | base64 -w0)"
body_b64="$(printf '%s' "$body" | base64 -w0)"
id_b64="$(printf '%s' "$issue_id" | base64 -w0)"
repo_b64=""
if [[ -n "$GITHUB_REPO" ]]; then
  repo_b64="$(printf '%s' "$GITHUB_REPO" | base64 -w0)"
fi

set +e
result=$(cd "$SITE_DIR" && ISSUE_TITLE_B64="$title_b64" ISSUE_BODY_B64="$body_b64" ISSUE_ID_B64="$id_b64" ISSUE_REPO_B64="$repo_b64" "$DRUSH_BIN" php:eval '
  $client = \Drupal::service("dungeoncrawler_tester.github_issue_pr_client");
  $title = base64_decode((string) getenv("ISSUE_TITLE_B64"));
  $body = base64_decode((string) getenv("ISSUE_BODY_B64"));
  $issueId = base64_decode((string) getenv("ISSUE_ID_B64"));
  $repoOverride = base64_decode((string) getenv("ISSUE_REPO_B64"));

  $context = $client->resolveContext();
  $repo = trim((string) ($repoOverride !== "" ? $repoOverride : ($context["repo"] ?? "")));
  $token = trim((string) ($context["token"] ?? ""));
  if ($repo === "") {
    print "ERROR: repo-not-configured\n";
    return;
  }

  $quotedTitle = "\"" . str_replace("\"", "\\\\\"", $title) . "\"";
  $existing = $client->searchIssuesTotalCount("repo:{$repo} is:issue in:title {$quotedTitle}");
  if ($existing > 0) {
    print "SKIP_EXISTS:{$issueId}\n";
    return;
  }

  $payload = $client->createIssue($repo, [
    "title" => $title,
    "body" => $body,
  ]);

  if (!is_array($payload) || empty($payload["number"])) {
    print "ERROR:create-failed:{$issueId}\n";
    return;
  }

  $number = (int) $payload["number"];
  $assignmentOk = FALSE;
  $assignmentError = "Unknown assignment error.";

  foreach (["@copilot", "Copilot", "copilot"] as $identifier) {
    try {
      $assignedPayload = $client->addIssueAssignees($repo, $number, [$identifier], $token !== "" ? $token : NULL) ?: [];
      $assignedLogins = array_map(
        static fn(array $assignee): string => strtolower((string) ($assignee["login"] ?? "")),
        $assignedPayload["assignees"] ?? []
      );
      if (in_array("copilot", $assignedLogins, TRUE)) {
        $assignmentOk = TRUE;
        break;
      }

      $assignmentError = "GitHub response did not include Copilot in assignees.";
    }
    catch (\Throwable $e) {
      $assignmentError = $e->getMessage();
    }
  }

  if (!$assignmentOk && $token !== "") {
    try {
      $process = new \Symfony\Component\Process\Process([
        "gh",
        "issue",
        "edit",
        (string) $number,
        "--repo",
        $repo,
        "--add-assignee",
        "@copilot",
      ]);
      $process->setEnv(array_merge($_ENV, [
        "GH_TOKEN" => $token,
        "GITHUB_TOKEN" => $token,
      ]));
      $process->setTimeout(20);
      $process->run();

      if ($process->isSuccessful()) {
        $assignmentOk = TRUE;
      }
      else {
        $assignmentError = trim($process->getErrorOutput() ?: $process->getOutput()) ?: "gh issue edit failed without output.";
      }
    }
    catch (\Throwable $e) {
      $assignmentError = "GitHub CLI fallback failed: " . $e->getMessage();
    }
  }

  if (!$assignmentOk) {
    $assignmentError = str_replace(["\n", "\r"], " ", $assignmentError);
    print "CREATED_UNASSIGNED:#{$number}:{$issueId}:{$assignmentError}\n";
    return;
  }

  print "CREATED:#{$number}:{$issueId}\n";
')
status=$?
set -e

if [[ $status -ne 0 ]]; then
  echo "FAILED (drush exit $status) for $issue_id"
  failed_count=$((failed_count + 1))
  echo "[$(date '+%H:%M:%S')] Sleeping ${SLEEP_SECONDS}s before next issue..."
  sleep "$SLEEP_SECONDS"
  continue
fi

if grep -q '^SKIP_EXISTS:' <<< "$result"; then
  echo "$result"
  skipped_count=$((skipped_count + 1))
  echo "[$(date '+%H:%M:%S')] Sleeping ${SLEEP_SECONDS}s before next issue..."
  sleep "$SLEEP_SECONDS"
  continue
fi

if grep -q '^CREATED_UNASSIGNED:' <<< "$result"; then
  echo "$result"
  echo "Warning: issue created but @copilot assignment did not complete via API."
  created_count=$((created_count + 1))
elif grep -q '^CREATED:' <<< "$result"; then
  echo "$result"
  created_count=$((created_count + 1))
else
  echo "$result"
  failed_count=$((failed_count + 1))
  echo "[$(date '+%H:%M:%S')] Sleeping ${SLEEP_SECONDS}s before next issue..."
  sleep "$SLEEP_SECONDS"
  continue
fi

echo "[$(date '+%H:%M:%S')] Sleeping ${SLEEP_SECONDS}s before next issue..."
sleep "$SLEEP_SECONDS"
done

echo "---"
if [[ "$batch_limit_reached" -eq 1 ]]; then
  echo "Batch limit reached (${BATCH_SIZE} created)."
fi
echo "Done. Processed: $processed_count | Created: $created_count | Skipped: $skipped_count | Failed: $failed_count"
