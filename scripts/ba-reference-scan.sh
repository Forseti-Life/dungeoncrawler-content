#!/usr/bin/env bash
# ba-reference-scan.sh — Queue a BA reference document scan inbox item for a product team
#
# This script is called at Stage 3 of each release cycle. It queues a task for the
# BA agent to read the NEXT chapter/section of the product's reference documentation
# and generate up to CAP_PER_CYCLE new feature stubs for the PM to groom.
#
# The BA agent reads the actual content, reasons about what game mechanics/features
# it implies, and creates feature stubs in features/<feature-id>/ for PM triage.
#
# Progress is tracked in tmp/ba-scan-progress/<site>.json so each cycle picks up
# exactly where the last one left off — no re-reading, no skipping.
#
# Usage:
#   ./scripts/ba-reference-scan.sh <site> <next-release-id>
#
# Examples:
#   ./scripts/ba-reference-scan.sh dungeoncrawler 20260301-dc-r2
#   ./scripts/ba-reference-scan.sh forseti 20260301-forseti-r2
#
# Cap: 30 new feature stubs per product per release cycle (configurable below).
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-}"
NEXT_RELEASE_ID="${2:-}"
CAP_PER_CYCLE=30

if [ -z "$SITE" ] || [ -z "$NEXT_RELEASE_ID" ]; then
  echo "Usage: $0 <site> <next-release-id>" >&2
  exit 1
fi

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"
PROGRESS_FILE="tmp/ba-scan-progress/${SITE}.json"

# Resolve BA agent from product-teams.json
BA_AGENT="$(python3 - "$PRODUCT_TEAMS_JSON" "$SITE" <<'PY'
import json, sys
data = json.loads(open(sys.argv[1]).read())
for team in data.get("teams", []):
    aliases = [team.get("id","")] + (team.get("aliases") or [])
    if sys.argv[2] in aliases or team.get("id") == sys.argv[2]:
        print(team.get("ba_agent",""))
        sys.exit(0)
sys.exit(1)
PY
)"

if [ -z "$BA_AGENT" ]; then
  echo "ERROR: No ba_agent configured for site '$SITE' in $PRODUCT_TEAMS_JSON" >&2
  exit 1
fi

BA_INBOX_DIR="sessions/${BA_AGENT}/inbox"

# Check if there are reference docs configured
REF_DOCS_COUNT="$(python3 - "$PRODUCT_TEAMS_JSON" "$SITE" <<'PY'
import json, sys
data = json.loads(open(sys.argv[1]).read())
for team in data.get("teams", []):
    aliases = [team.get("id","")] + (team.get("aliases") or [])
    if sys.argv[2] in aliases or team.get("id") == sys.argv[2]:
        docs = team.get("reference_docs") or []
        print(len(docs))
        sys.exit(0)
print("0")
PY
)"

if [ "$REF_DOCS_COUNT" = "0" ]; then
  echo "INFO: No reference_docs configured for site '$SITE' — skipping BA reference scan." >&2
  exit 0
fi

# Create progress file if it doesn't exist
if [ ! -f "$PROGRESS_FILE" ]; then
  mkdir -p "tmp/ba-scan-progress"
  python3 - "$PRODUCT_TEAMS_JSON" "$SITE" "$PROGRESS_FILE" <<'PY'
import json, sys
data = json.loads(open(sys.argv[1]).read())
for team in data.get("teams", []):
    aliases = [team.get("id","")] + (team.get("aliases") or [])
    if sys.argv[2] in aliases or team.get("id") == sys.argv[2]:
        docs = team.get("reference_docs") or []
        progress = {
            "site": sys.argv[2],
            "books": [
                {
                    "label": d["label"],
                    "path": d["path"],
                    "outline": d.get("outline",""),
                    "type": d.get("type","rulebook"),
                    "last_line": 0,
                    "chapters_completed": [],
                    "status": "pending"
                }
                for d in docs
            ],
            "total_features_generated": 0,
            "last_scan_release": None,
        }
        with open(sys.argv[3], 'w') as f:
            json.dump(progress, f, indent=2)
        sys.exit(0)
PY
  echo "[ba-reference-scan] Created progress file: $PROGRESS_FILE"
fi

# Determine which book + line to scan next
NEXT_BOOK_INFO="$(python3 - "$PROGRESS_FILE" <<'PY'
import json, sys, os
p = json.loads(open(sys.argv[1]).read())
for i, book in enumerate(p.get("books", [])):
    if book.get("status") in ("pending", "in_progress"):
        full_path = book["path"]
        if not os.path.isabs(full_path):
            # Relative to forseti.life repo root
            full_path = os.path.join("/home/ubuntu/forseti.life", full_path)
        if os.path.exists(full_path):
            total_lines = sum(1 for _ in open(full_path, errors='ignore'))
            print(json.dumps({
                "index": i,
                "label": book["label"],
                "path": full_path,
                "outline": book.get("outline",""),
                "type": book.get("type","rulebook"),
                "last_line": book.get("last_line", 0),
                "total_lines": total_lines,
                "status": book.get("status","pending"),
                "chapters_completed": book.get("chapters_completed", [])
            }))
            sys.exit(0)
        else:
            print(json.dumps({"skip": True, "reason": f"File not found: {full_path}", "index": i}))
            sys.exit(0)
# All books complete
print(json.dumps({"all_complete": True}))
PY
)"

if echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); exit(0 if d.get('all_complete') else 1)" 2>/dev/null; then
  echo "[ba-reference-scan] All reference books fully scanned for site '$SITE'. Nothing to queue."
  exit 0
fi

if echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); exit(0 if d.get('skip') else 1)" 2>/dev/null; then
  SKIP_REASON="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('reason',''))")"
  echo "[ba-reference-scan] WARN: Skipping book — $SKIP_REASON"
  exit 0
fi

# Extract book info
BOOK_LABEL="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read())['label'])")"
BOOK_PATH="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read())['path'])")"
BOOK_OUTLINE="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('outline',''))")"
BOOK_TYPE="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('type','rulebook'))")"
LAST_LINE="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read())['last_line'])")"
TOTAL_LINES="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read())['total_lines'])")"
BOOK_INDEX="$(echo "$NEXT_BOOK_INFO" | python3 -c "import json,sys; print(json.loads(sys.stdin.read())['index'])")"

# Read a chunk of the book (lines LAST_LINE to LAST_LINE+CHUNK_SIZE)
# Each BA task reads ~300 lines at a time — enough for 1-2 sections
CHUNK_SIZE=300
CHUNK_START=$((LAST_LINE + 1))
CHUNK_END=$((LAST_LINE + CHUNK_SIZE))
if [ "$CHUNK_END" -gt "$TOTAL_LINES" ]; then
  CHUNK_END=$TOTAL_LINES
fi

# Read the next chunk + a snippet of the outline for context
BOOK_CHUNK="$(sed -n "${CHUNK_START},${CHUNK_END}p" "$BOOK_PATH" 2>/dev/null || echo "(could not read chunk)")"
OUTLINE_CONTENT=""
if [ -n "$BOOK_OUTLINE" ]; then
  OUTLINE_FULL_PATH="/home/ubuntu/forseti.life/${BOOK_OUTLINE}"
  if [ -f "$OUTLINE_FULL_PATH" ]; then
    OUTLINE_CONTENT="$(cat "$OUTLINE_FULL_PATH")"
  fi
fi

PROGRESS_PCTS="$((CHUNK_END * 100 / TOTAL_LINES))%"
TODAY="$(date +%Y%m%d)"
SLUG="ba-refscan-$(echo "$SITE" | tr '[:upper:]' '[:lower:]')-$(echo "$BOOK_LABEL" | tr '[:upper:] ' '[:lower:]-' | tr -cs 'a-z0-9-' '-' | sed 's/^-//;s/-$//' | cut -c1-30)"
ITEM_ID="${TODAY}-${SLUG}"
INBOX_DIR="${BA_INBOX_DIR}/${ITEM_ID}"
OUTBOX_FILE="sessions/${BA_AGENT}/outbox/${ITEM_ID}.md"

if [ -d "$INBOX_DIR" ] || [ -f "$OUTBOX_FILE" ]; then
  echo "[ba-reference-scan] BA task already queued/completed: $ITEM_ID"
  exit 0
fi

# Only allow one active refscan inbox item at a time for the seat. Leaving older
# in-progress scans queued causes the executor to keep revisiting stale chunks.
ACTIVE_SCAN_ITEM="$(
  find "$BA_INBOX_DIR" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" \
    -name "*-ba-refscan-${SITE}-*" -printf '%f\n' 2>/dev/null | sort | head -n 1
)" || ACTIVE_SCAN_ITEM=""
if [ -n "$ACTIVE_SCAN_ITEM" ]; then
  echo "[ba-reference-scan] Active BA refscan item already queued: ${ACTIVE_SCAN_ITEM}"
  exit 0
fi

# Use the explicit progress JSON instead of file mtimes. Mtime counting drifts as
# soon as unrelated feature files change and incorrectly tells BA that the cap is hit.
CYCLE_COUNT_THIS_RELEASE="$(python3 - "$PROGRESS_FILE" "$NEXT_RELEASE_ID" <<'PY'
import json
import sys

with open(sys.argv[1], encoding="utf-8") as fh:
    progress = json.load(fh)

count = progress.get("total_features_generated", 0)
try:
    count = int(count)
except (TypeError, ValueError):
    count = 0

if progress.get("last_scan_release") != sys.argv[2]:
    count = 0

print(max(count, 0))
PY
)" || CYCLE_COUNT_THIS_RELEASE=0

if [ "$CYCLE_COUNT_THIS_RELEASE" -ge "$CAP_PER_CYCLE" ]; then
  echo "[ba-reference-scan] Feature cap already reached for ${NEXT_RELEASE_ID}: ${CYCLE_COUNT_THIS_RELEASE}/${CAP_PER_CYCLE}"
  exit 0
fi

mkdir -p "$INBOX_DIR"
echo "18" > "$INBOX_DIR/roi.txt"

cat > "$INBOX_DIR/command.md" <<EOF
# Reference Document Scan — ${BOOK_LABEL}

**Site:** ${SITE}  
**Next release:** ${NEXT_RELEASE_ID}  
**Book:** ${BOOK_LABEL} (${BOOK_TYPE})  
**Progress:** lines ${CHUNK_START}–${CHUNK_END} of ${TOTAL_LINES} (${PROGRESS_PCTS} through this book)  
**Features generated this cycle so far:** ${CYCLE_COUNT_THIS_RELEASE} / ${CAP_PER_CYCLE} cap  
**Progress state file:** ${PROGRESS_FILE}  

## Your task

Read the source material below and extract **implementable game features** for the dungeoncrawler product.

For each distinct mechanic, rule, creature, spell, item, or system described in the text:
1. Decide if it is **relevant** to the dungeoncrawler digital game (skip pure lore, typography, credits).
2. If relevant and NOT already implemented (check \`features/\` directory), create a feature stub.
3. Stop when you have generated **${CAP_PER_CYCLE} total features this cycle** (across all scan tasks this release).

## Creating a feature stub

For each feature, create \`features/dc-<slug>/feature.md\` using this template:

\`\`\`markdown
# Feature Brief: <title>

- Work item id: dc-<slug>
- Website: dungeoncrawler
- Module: dungeoncrawler_content (or dungeoncrawler_tester)
- Status: pre-triage
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: ${BOOK_LABEL}, lines ${CHUNK_START}–${CHUNK_END}
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: $(date +%Y-%m-%d)

## Goal

<One paragraph: what this feature adds to the dungeoncrawler game. Written for a PM who will decide whether to include it.>

## Source reference

> <Direct quote or paraphrase of the relevant paragraph(s) from the reference material>

## Implementation hint

<Brief note on what Drupal module work this likely implies — content type, field, API endpoint, AI prompt change, etc.>

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
\`\`\`

## Feature slug convention

Use the book abbreviation + short descriptor:
- Core Rulebook → \`dc-cr-<descriptor>\`
- Advanced Players Guide → \`dc-apg-<descriptor>\`
- Bestiary 1/2/3 → \`dc-b1-<descriptor>\` / \`dc-b2-\` / \`dc-b3-\`
- Secrets of Magic → \`dc-som-<descriptor>\`
- Gamemastery Guide → \`dc-gmg-<descriptor>\`
- Guns and Gears → \`dc-gg-<descriptor>\`
- Gods and Magic → \`dc-gam-<descriptor>\`

## After generating features

1. Update \`${PROGRESS_FILE}\`:
   - Set \`books[${BOOK_INDEX}].last_line\` → ${CHUNK_END}
   - Set \`books[${BOOK_INDEX}].status\` → \`in_progress\` (or \`complete\` if end of book)
   - Set \`last_scan_release\` → \`${NEXT_RELEASE_ID}\`
2. Write outbox: list each feature stub created (id + one-line description), total count, lines covered.

## Book outline (for orientation)

${OUTLINE_CONTENT}

---

## Source material (lines ${CHUNK_START}–${CHUNK_END})

\`\`\`
${BOOK_CHUNK}
\`\`\`
EOF

echo "[ba-reference-scan] Queued: ${BA_AGENT} ${ITEM_ID}"
echo "[ba-reference-scan] Book: ${BOOK_LABEL} | Lines ${CHUNK_START}–${CHUNK_END} of ${TOTAL_LINES} (${PROGRESS_PCTS})"
echo "[ba-reference-scan] Cap: ${CAP_PER_CYCLE} features max this cycle"
