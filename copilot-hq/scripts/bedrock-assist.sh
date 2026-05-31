#!/usr/bin/env bash
set -euo pipefail

# bedrock-assist.sh — Invoke Forseti/DungeonCrawler Bedrock assistant via Drupal service.
#
# Usage:
#   ./scripts/bedrock-assist.sh [site] "prompt text"
#   echo "prompt text" | ./scripts/bedrock-assist.sh [site]
#
# Defaults:
#   site: forseti
#   max tokens: 700
#
# Env overrides:
#   DRUPAL_ROOT                       Absolute Drupal site root (contains vendor/bin/drush)
#   BEDROCK_MAX_TOKENS               Max response tokens (default 700)
#   BEDROCK_OPERATION                Usage-tracking operation label (default hq_bedrock_assistant)
#   BEDROCK_SYSTEM_PROMPT_NODE_ID    System prompt node id (default 10)
#   BEDROCK_SUPPRESS_DRUSH_WARNINGS  Default 1; suppress benign trailing drush warning noise

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/bedrock-assist.sh [site] "prompt text"
  echo "prompt text" | ./scripts/bedrock-assist.sh [site]

Examples:
  ./scripts/bedrock-assist.sh forseti "Summarize production suggestion pipeline health."
  ./scripts/bedrock-assist.sh dungeoncrawler "List top 3 release blockers."
  echo "What should we do next?" | ./scripts/bedrock-assist.sh forseti

Environment:
  DRUPAL_ROOT                       Absolute Drupal root (contains vendor/bin/drush)
  BEDROCK_MAX_TOKENS               Max response tokens (default 700)
  BEDROCK_OPERATION                Usage operation label (default hq_bedrock_assistant)
  BEDROCK_SYSTEM_PROMPT_NODE_ID    PromptManager node id (default 10)
  BEDROCK_SUPPRESS_DRUSH_WARNINGS  Default 1
USAGE
}

SITE="${1:-forseti}"
PROMPT=""

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
  usage
  exit 0
fi

if [ "$#" -ge 2 ]; then
  shift
  PROMPT="$*"
elif [ "$#" -eq 1 ]; then
  shift
  if [ ! -t 0 ]; then
    PROMPT="$(cat)"
  fi
else
  if [ ! -t 0 ]; then
    PROMPT="$(cat)"
  fi
fi

if [ -z "$(printf '%s' "$PROMPT" | tr -d '[:space:]')" ]; then
  echo "ERROR: prompt is required" >&2
  usage >&2
  exit 2
fi

resolve_drupal_root() {
  if [ -n "${DRUPAL_ROOT:-}" ] && [ -d "${DRUPAL_ROOT}" ]; then
    printf '%s\n' "$DRUPAL_ROOT"
    return 0
  fi

  local local_candidates=()
  if [ -n "${HOME:-}" ]; then
    local_candidates+=("$HOME/forseti.life/sites/forseti")
    local_candidates+=("$HOME/forseti.life/sites/dungeoncrawler")
  fi
  local_candidates+=("$ROOT_DIR/../sites/forseti")
  local_candidates+=("$ROOT_DIR/../sites/dungeoncrawler")

  case "$SITE" in
    forseti|forseti.life|forseti-life)
      for candidate in "${local_candidates[@]}"; do
        if [ -d "$candidate" ] && [ "$(basename "$candidate")" = "forseti" ]; then
          printf '%s\n' "$candidate"
          return 0
        fi
      done
      ;;
    dungeoncrawler|dungeoncrawler.forseti.life)
      for candidate in "${local_candidates[@]}"; do
        if [ -d "$candidate" ] && [ "$(basename "$candidate")" = "dungeoncrawler" ]; then
          printf '%s\n' "$candidate"
          return 0
        fi
      done
      ;;
  esac

  case "$SITE" in
    forseti|forseti.life|forseti-life)
      if [ -d "/var/www/html/forseti" ]; then printf '%s\n' "/var/www/html/forseti"; return 0; fi
      if [ -d "/home/ubuntu/forseti.life/sites/forseti" ]; then printf '%s\n' "/home/ubuntu/forseti.life/sites/forseti"; return 0; fi
      ;;
    dungeoncrawler|dungeoncrawler.forseti.life)
      if [ -d "/var/www/html/dungeoncrawler" ]; then printf '%s\n' "/var/www/html/dungeoncrawler"; return 0; fi
      if [ -d "/home/ubuntu/forseti.life/sites/dungeoncrawler" ]; then printf '%s\n' "/home/ubuntu/forseti.life/sites/dungeoncrawler"; return 0; fi
      ;;
  esac

  python3 - "$SITE" <<'PY'
import json
import sys
from pathlib import Path

query = (sys.argv[1] or "").strip().lower()
p = Path("org-chart/products/product-teams.json")
if not p.exists():
    raise SystemExit(1)

data = json.loads(p.read_text(encoding="utf-8"))
for t in data.get("teams", []):
    aliases = [str(a).strip().lower() for a in (t.get("aliases") or []) if str(a).strip()]
    team_id = str(t.get("id") or "").strip().lower()
    site = str(t.get("site") or "").strip().lower()
    if query not in aliases and query != team_id and query != site:
        continue

    web_root = str((t.get("site_audit") or {}).get("drupal_web_root") or "").strip()
    if web_root:
        path = Path(web_root)
        if path.name == "web":
            path = path.parent
        print(str(path))
        raise SystemExit(0)

raise SystemExit(1)
PY
}

DRUPAL_ROOT="$(resolve_drupal_root || true)"
if [ -z "$DRUPAL_ROOT" ] || [ ! -d "$DRUPAL_ROOT" ]; then
  echo "ERROR: unable to resolve Drupal root for site '$SITE'" >&2
  echo "Set DRUPAL_ROOT explicitly and retry." >&2
  exit 2
fi

DRUSH="$DRUPAL_ROOT/vendor/bin/drush"
WEB_ROOT="$DRUPAL_ROOT/web"
if [ ! -d "$WEB_ROOT" ]; then
  echo "ERROR: Drupal web root not found at $WEB_ROOT" >&2
  exit 2
fi

PHP_BIN="${PHP_BIN:-$(command -v php 2>/dev/null || true)}"
if [ -z "$PHP_BIN" ]; then
  echo "ERROR: php binary not found in PATH" >&2
  exit 2
fi

MAX_TOKENS="${BEDROCK_MAX_TOKENS:-700}"
OPERATION="${BEDROCK_OPERATION:-hq_bedrock_assistant}"
SYSTEM_PROMPT_NODE_ID="${BEDROCK_SYSTEM_PROMPT_NODE_ID:-10}"
SUPPRESS_DRUSH_WARNINGS="${BEDROCK_SUPPRESS_DRUSH_WARNINGS:-1}"
MODEL_ID="${BEDROCK_MODEL_ID:-us.anthropic.claude-sonnet-4-6}"

PHP_CODE=$(cat <<'PHP'
$prompt = (string) (getenv('BEDROCK_PROMPT') ?: '');
if (trim($prompt) === '') {
  fwrite(STDERR, "ERROR: BEDROCK_PROMPT is empty\n");
  exit(2);
}

$max_tokens = (int) (getenv('BEDROCK_MAX_TOKENS') ?: '700');
if ($max_tokens <= 0) {
  $max_tokens = 700;
}

$op = (string) (getenv('BEDROCK_OPERATION') ?: 'hq_bedrock_assistant');
$site = (string) (getenv('BEDROCK_SITE') ?: 'unknown');
$prompt_node_id = (int) (getenv('BEDROCK_SYSTEM_PROMPT_NODE_ID') ?: '10');
$model_id = (string) (getenv('BEDROCK_MODEL_ID') ?: 'us.anthropic.claude-sonnet-4-6');

$pm = \Drupal::service('ai_conversation.prompt_manager');
$system_prompt = $pm->getSystemPrompt($prompt_node_id);

$svc = \Drupal::service('ai_conversation.ai_api_service');
$result = $svc->invokeModelDirect(
  $prompt,
  'ai_conversation',
  $op,
  [
    'source' => 'hq-script',
    'site' => $site,
    'ts' => date('c'),
  ],
  [
    'model_id' => $model_id,
    'max_tokens' => $max_tokens,
    'skip_cache' => TRUE,
    'system_prompt' => $system_prompt,
  ]
);

if (!empty($result['success'])) {
  echo (string) ($result['response'] ?? '') . PHP_EOL;
  exit(0);
}

$error = (string) ($result['error'] ?? 'Unknown Bedrock error');
fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
exit(1);
PHP
)

PHP_BOOTSTRAP_CODE=$(cat <<'PHP'
declare(strict_types=1);

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$prompt = (string) (getenv('BEDROCK_PROMPT') ?: '');
if (trim($prompt) === '') {
  fwrite(STDERR, "ERROR: BEDROCK_PROMPT is empty\n");
  exit(2);
}

$max_tokens = (int) (getenv('BEDROCK_MAX_TOKENS') ?: '700');
if ($max_tokens <= 0) {
  $max_tokens = 700;
}

$op = (string) (getenv('BEDROCK_OPERATION') ?: 'hq_bedrock_assistant');
$site = (string) (getenv('BEDROCK_SITE') ?: 'unknown');
$prompt_node_id = (int) (getenv('BEDROCK_SYSTEM_PROMPT_NODE_ID') ?: '10');
$model_id = (string) (getenv('BEDROCK_MODEL_ID') ?: 'us.anthropic.claude-sonnet-4-6');
$web_root = (string) (getenv('BEDROCK_WEB_ROOT') ?: '');
if ($web_root === '' || !is_dir($web_root)) {
  fwrite(STDERR, "ERROR: BEDROCK_WEB_ROOT is invalid\n");
  exit(2);
}

chdir($web_root);
$autoloader = require_once 'autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->preHandle($request);

$pm = \Drupal::service('ai_conversation.prompt_manager');
$system_prompt = $pm->getSystemPrompt($prompt_node_id);

$svc = \Drupal::service('ai_conversation.ai_api_service');
$result = $svc->invokeModelDirect(
  $prompt,
  'ai_conversation',
  $op,
  [
    'source' => 'hq-script',
    'site' => $site,
    'ts' => date('c'),
  ],
  [
    'model_id' => $model_id,
    'max_tokens' => $max_tokens,
    'skip_cache' => TRUE,
    'system_prompt' => $system_prompt,
  ]
);

if (!empty($result['success'])) {
  echo (string) ($result['response'] ?? '') . PHP_EOL;
  exit(0);
}

$error = (string) ($result['error'] ?? 'Unknown Bedrock error');
fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
exit(1);
PHP
)

echo "[bedrock-assist] site=$SITE drupal_root=$DRUPAL_ROOT max_tokens=$MAX_TOKENS op=$OPERATION" >&2
run_drush_eval() {
  if [ ! -x "$DRUSH" ]; then
    return 2
  fi

  if [ "$(id -un)" = "www-data" ]; then
    BEDROCK_PROMPT="$PROMPT" \
    BEDROCK_MAX_TOKENS="$MAX_TOKENS" \
    BEDROCK_OPERATION="$OPERATION" \
    BEDROCK_SITE="$SITE" \
    BEDROCK_SYSTEM_PROMPT_NODE_ID="$SYSTEM_PROMPT_NODE_ID" \
    BEDROCK_MODEL_ID="$MODEL_ID" \
    "$DRUSH" php:eval "$PHP_CODE" 2>&1
    return $?
  fi

  BEDROCK_PROMPT="$PROMPT" \
  BEDROCK_MAX_TOKENS="$MAX_TOKENS" \
  BEDROCK_OPERATION="$OPERATION" \
  BEDROCK_SITE="$SITE" \
  BEDROCK_SYSTEM_PROMPT_NODE_ID="$SYSTEM_PROMPT_NODE_ID" \
  BEDROCK_MODEL_ID="$MODEL_ID" \
  "$DRUSH" php:eval "$PHP_CODE" 2>&1
  local direct_rc=$?
  if [ $direct_rc -eq 0 ]; then
    return 0
  fi

  if ! command -v sudo >/dev/null 2>&1; then
    return $direct_rc
  fi

  if ! sudo -n true >/dev/null 2>&1; then
    return $direct_rc
  fi

  sudo -n -u www-data -E \
    BEDROCK_PROMPT="$PROMPT" \
    BEDROCK_MAX_TOKENS="$MAX_TOKENS" \
    BEDROCK_OPERATION="$OPERATION" \
    BEDROCK_SITE="$SITE" \
    BEDROCK_SYSTEM_PROMPT_NODE_ID="$SYSTEM_PROMPT_NODE_ID" \
    BEDROCK_MODEL_ID="$MODEL_ID" \
    "$DRUSH" php:eval "$PHP_CODE" 2>&1
}

run_php_bootstrap_eval() {
  BEDROCK_PROMPT="$PROMPT" \
  BEDROCK_MAX_TOKENS="$MAX_TOKENS" \
  BEDROCK_OPERATION="$OPERATION" \
  BEDROCK_SITE="$SITE" \
  BEDROCK_SYSTEM_PROMPT_NODE_ID="$SYSTEM_PROMPT_NODE_ID" \
  BEDROCK_MODEL_ID="$MODEL_ID" \
  BEDROCK_WEB_ROOT="$WEB_ROOT" \
  "$PHP_BIN" -r "$PHP_BOOTSTRAP_CODE" 2>&1
}

set +e
RAW_OUTPUT="$(run_drush_eval)"
DRUSH_EXIT=$?
set -e

SANITIZED_OUTPUT="$RAW_OUTPUT"
if [ "$SUPPRESS_DRUSH_WARNINGS" = "1" ]; then
  SANITIZED_OUTPUT="$(printf '%s\n' "$RAW_OUTPUT" | awk 'BEGIN{IGNORECASE=1} index(tolower($0), "drush command terminated abnormally") == 0 { print }')"
fi

if [ $DRUSH_EXIT -ne 0 ]; then
  set +e
  RAW_OUTPUT="$(run_php_bootstrap_eval)"
  DRUSH_EXIT=$?
  set -e
  SANITIZED_OUTPUT="$RAW_OUTPUT"
fi

if [ $DRUSH_EXIT -eq 0 ]; then
  printf '%s\n' "$SANITIZED_OUTPUT"
  exit 0
fi

if printf '%s\n' "$SANITIZED_OUTPUT" | grep -qiE 'ERROR:|ParseError|Fatal error|Uncaught|Exception|Stack trace'; then
  printf '%s\n' "$SANITIZED_OUTPUT" >&2
  exit $DRUSH_EXIT
fi

if [ -n "$(printf '%s' "$SANITIZED_OUTPUT" | tr -d '[:space:]')" ]; then
  printf '%s\n' "$SANITIZED_OUTPUT"
  exit 0
fi

printf '%s\n' "$RAW_OUTPUT" >&2
exit $DRUSH_EXIT
