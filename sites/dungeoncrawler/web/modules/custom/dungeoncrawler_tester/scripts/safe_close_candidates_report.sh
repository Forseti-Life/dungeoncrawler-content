#!/usr/bin/env bash
set -euo pipefail

REPO="${1:-keithaumiller/forseti.life}"
STALE_DAYS="${STALE_DAYS:-60}"
CUTOFF_DATE="$(date -u -d "${STALE_DAYS} days ago" +%Y-%m-%d)"

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

require_cmd gh
require_cmd jq

if ! gh auth status >/dev/null 2>&1; then
  echo "GitHub CLI is not authenticated. Run: gh auth login" >&2
  exit 1
fi

issue_link() {
  local n="$1"
  printf 'https://github.com/%s/issues/%s' "$REPO" "$n"
}

pr_link() {
  local n="$1"
  printf 'https://github.com/%s/pull/%s' "$REPO" "$n"
}

# Open issues index (excluding PR records).
declare -A OPEN_ISSUES=()
while IFS= read -r issue_number; do
  [[ -n "$issue_number" ]] || continue
  OPEN_ISSUES["$issue_number"]=1
done < <(
  gh api --paginate "repos/${REPO}/issues" -f state=open -f per_page=100 \
    --jq '.[] | select(.pull_request == null) | .number'
)

# Open and closed PR snapshots.
OPEN_PRS_JSON="$(gh api --paginate "repos/${REPO}/pulls" -f state=open -f per_page=100 | jq -s 'add')"
CLOSED_PRS_JSON="$(gh api --paginate "repos/${REPO}/pulls" -f state=closed -f per_page=100 | jq -s 'add')"

printf '# Safe-Close Candidates Report\n\n'
printf -- '- Repo: `%s`\n' "$REPO"
printf -- '- Generated (UTC): `%s`\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
printf -- '- Stale cutoff: `%s` (%s days)\n\n' "$CUTOFF_DATE" "$STALE_DAYS"
printf '_Goal: identify PRs/issues that can be closed with no implementation action._\n\n'

# Category 1: Dead-value PRs.
printf '## 1) Dead-value PRs (no diff from main)\n\n'
mapfile -t OPEN_PR_NUMBERS < <(printf '%s' "$OPEN_PRS_JSON" | jq -r '.[].number')
DEAD_VALUE_COUNT=0
for pr_number in "${OPEN_PR_NUMBERS[@]:-}"; do
  [[ -n "${pr_number:-}" ]] || continue
  details="$(gh api "repos/${REPO}/pulls/${pr_number}")"
  if printf '%s' "$details" | jq -e '.base.ref == "main" and (.draft | not) and .changed_files == 0 and .additions == 0 and .deletions == 0' >/dev/null; then
    title="$(printf '%s' "$details" | jq -r '.title')"
    printf -- '- PR #%s: %s (%s)\n' "$pr_number" "$title" "$(pr_link "$pr_number")"
    DEAD_VALUE_COUNT=$((DEAD_VALUE_COUNT + 1))
  fi
done
if [[ "$DEAD_VALUE_COUNT" -eq 0 ]]; then
  printf -- '- None\n'
fi
printf '\n'

# Category 2: Open issues already resolved by merged PR reference.
printf '## 2) Open issues referenced by merged PRs\n\n'
declare -A ISSUE_RESOLVED_BY_MERGED_PR=()
while IFS=$'\t' read -r pr_number pr_title pr_body; do
  [[ -n "${pr_number:-}" ]] || continue
  refs="$(printf '%s\n%s\n' "${pr_title:-}" "${pr_body:-}" | grep -oE '#[0-9]+' | tr -d '#' | sort -u || true)"
  while IFS= read -r ref_issue; do
    [[ -n "${ref_issue:-}" ]] || continue
    if [[ -n "${OPEN_ISSUES[$ref_issue]+x}" ]]; then
      ISSUE_RESOLVED_BY_MERGED_PR["$ref_issue"]+=" #${pr_number}"
    fi
  done <<< "$refs"
done < <(
  printf '%s' "$CLOSED_PRS_JSON" | jq -r '.[] | select(.merged_at != null) | [.number, .title, (.body // "")] | @tsv'
)
if [[ "${#ISSUE_RESOLVED_BY_MERGED_PR[@]}" -eq 0 ]]; then
  printf -- '- None\n'
else
  for issue_number in "${!ISSUE_RESOLVED_BY_MERGED_PR[@]}"; do
    printf -- '- Issue #%s (%s) referenced by merged PR(s):%s\n' "$issue_number" "$(issue_link "$issue_number")" "${ISSUE_RESOLVED_BY_MERGED_PR[$issue_number]}"
  done | sort -V
fi
printf '\n'

# Category 3: Open issues already marked for non-action resolution.
printf '## 3) Open issues with non-action labels (duplicate/invalid/wontfix)\n\n'
NON_ACTION_QUERY="repo:${REPO} is:issue is:open (label:duplicate OR label:invalid OR label:wontfix)"
NON_ACTION_JSON="$(gh api --paginate search/issues -f q="$NON_ACTION_QUERY" -f per_page=100 | jq -s '{items: [.[].items[]]}')"
NON_ACTION_COUNT="$(printf '%s' "$NON_ACTION_JSON" | jq '.items | length')"
if [[ "$NON_ACTION_COUNT" -eq 0 ]]; then
  printf -- '- None\n'
else
  printf '%s' "$NON_ACTION_JSON" | jq -r '.items[] | "- Issue #\(.number): \(.title) (\(.html_url))"'
fi
printf '\n'

# Category 4: Open PRs referencing only already-closed issues.
printf '## 4) Open PRs whose referenced issues are all closed\n\n'
ONLY_CLOSED_REF_COUNT=0
while IFS=$'\t' read -r pr_number pr_title pr_body; do
  [[ -n "${pr_number:-}" ]] || continue
  refs="$(printf '%s\n%s\n' "${pr_title:-}" "${pr_body:-}" | grep -oE '#[0-9]+' | tr -d '#' | sort -u || true)"
  [[ -n "$refs" ]] || continue

  all_closed=1
  ref_list=""
  while IFS= read -r ref_issue; do
    [[ -n "${ref_issue:-}" ]] || continue
    if [[ -n "${OPEN_ISSUES[$ref_issue]+x}" ]]; then
      all_closed=0
      break
    fi
    ref_list+=" #${ref_issue}"
  done <<< "$refs"

  if [[ "$all_closed" -eq 1 ]]; then
    printf -- '- PR #%s: %s (%s) refs:%s\n' "$pr_number" "$pr_title" "$(pr_link "$pr_number")" "$ref_list"
    ONLY_CLOSED_REF_COUNT=$((ONLY_CLOSED_REF_COUNT + 1))
  fi
done < <(
  printf '%s' "$OPEN_PRS_JSON" | jq -r '.[] | [.number, .title, (.body // "")] | @tsv'
)
if [[ "$ONLY_CLOSED_REF_COUNT" -eq 0 ]]; then
  printf -- '- None\n'
fi
printf '\n'

# Category 5: Stale unassigned testing/defect issues.
printf '## 5) Stale unassigned testing issues (operational noise candidates)\n\n'
STALE_TESTING_QUERY="repo:${REPO} is:issue is:open no:assignee updated:<${CUTOFF_DATE} (label:testing OR label:testing-defect OR label:ci-failure OR label:program-defect OR label:tester)"
STALE_TESTING_JSON="$(gh api --paginate search/issues -f q="$STALE_TESTING_QUERY" -f per_page=100 | jq -s '{items: [.[].items[]]}')"
STALE_TESTING_COUNT="$(printf '%s' "$STALE_TESTING_JSON" | jq '.items | length')"
if [[ "$STALE_TESTING_COUNT" -eq 0 ]]; then
  printf -- '- None\n'
else
  printf '%s' "$STALE_TESTING_JSON" | jq -r '.items[] | "- Issue #\(.number): \(.title) (updated: \(.updated_at), \(.html_url))"'
fi
printf '\n'

printf '## Suggested Next Step\n\n'
printf -- '- For each listed item, use your dashboard close action or `gh issue close` / `gh pr close` with a standard reason comment.\n'
printf -- '- Recommended close reason for dead-value items: "No diff from main; closing as no-action."\n'
