#!/usr/bin/env bash
set -euo pipefail

# Notify the CEO when there are pending decision items.
# Supports:
# - NOTIFY_METHOD=log (default)
# - NOTIFY_METHOD=sendmail  (requires local sendmail configured)
# - NOTIFY_METHOD=twilio    (requires Twilio credentials)
#
# IMPORTANT: Do not commit phone numbers/emails/secrets into this repo.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

active_ceo_agent() {
  local preferred="${ORCHESTRATOR_CEO_AGENT:-}"
  if [ -n "$preferred" ] && [[ "$preferred" == ceo-copilot* ]]; then
    if [ "$(./scripts/is-agent-paused.sh "$preferred" 2>/dev/null || echo false)" != "true" ]; then
      echo "$preferred"
      return
    fi
  fi
  while IFS= read -r id; do
    [ -n "$id" ] || continue
    [[ "$id" == ceo-copilot* ]] || continue
    if [ "$(./scripts/is-agent-paused.sh "$id" 2>/dev/null || echo false)" != "true" ]; then
      echo "$id"
      return
    fi
  done < <(sed -n 's/^[[:space:]]*-[[:space:]]id:[[:space:]]*\([^[:space:]]\+\)[[:space:]]*$/\1/p' org-chart/agents/agents.yaml 2>/dev/null)
  echo "ceo-copilot"
}

METHOD="${NOTIFY_METHOD:-log}"
COOLDOWN_SECONDS="${NOTIFY_COOLDOWN_SECONDS:-14400}"  # default 4h; override with env var
FORCE=0
if [ "${1:-}" = "--force" ]; then
  FORCE=1
fi

STATE_DIR="inbox/responses"
mkdir -p "$STATE_DIR"
STATE_FILE="$STATE_DIR/.notify-pending.last"

now="$(date +%s)"
last="0"
if [ -f "$STATE_FILE" ]; then
  last="$(cat "$STATE_FILE" 2>/dev/null || echo 0)"
  last="${last:-0}"
fi

if [ "$FORCE" -ne 1 ] && [ $((now - last)) -lt "$COOLDOWN_SECONDS" ]; then
  exit 0
fi

ceo_agent="$(active_ceo_agent)"
ceo_inbox="sessions/${ceo_agent}/inbox"
pending_count=0
if [ -d "$ceo_inbox" ]; then
  pending_count="$(find "$ceo_inbox" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | wc -l | tr -d ' ')"
fi

breach_ids=""
breach_count=0
if [ -x ./scripts/sla-report.sh ]; then
  breach_ids="$(./scripts/sla-report.sh 2>/dev/null | grep '^BREACH' | sort || true)"
  breach_count="$(echo "$breach_ids" | grep -c '^BREACH' || true)"
fi

if [ "$pending_count" -eq 0 ] && [ "$breach_count" -eq 0 ]; then
  exit 0
fi

# Deduplication: only notify if breach set has changed since last notification.
SEEN_FILE="$STATE_DIR/.notify-pending.seen-breaches"
breach_fingerprint="$(echo "${breach_ids}" | md5sum | cut -d' ' -f1)"
last_fingerprint=""
if [ -f "$SEEN_FILE" ]; then
  last_fingerprint="$(cat "$SEEN_FILE" 2>/dev/null || true)"
fi

# Skip if: cooldown not expired AND breach set hasn't changed
if [ "$FORCE" -ne 1 ] && [ $((now - last)) -lt "$COOLDOWN_SECONDS" ] && [ "$breach_fingerprint" = "$last_fingerprint" ] && [ "$pending_count" -eq 0 ]; then
  exit 0
fi

subject="[Forseti] Pending decisions: inbox=${pending_count} sla_breaches=${breach_count}"
body="$(cat <<EOF
Pending items require your attention.

- CEO inbox items: ${pending_count}
- SLA breaches: ${breach_count}
${breach_ids:+
Active breaches:
${breach_ids}}
Check:
- /home/ubuntu/forseti.life/sessions/${ceo_agent}/inbox
- /home/ubuntu/forseti.life/inbox/responses/forseti-triage-latest.log
EOF
)"

send_log() {
  echo "[$(date -Iseconds)] $subject" >> "$STATE_DIR/notify-pending.log"
  echo "$body" >> "$STATE_DIR/notify-pending.log"
  echo "----" >> "$STATE_DIR/notify-pending.log"
}

send_sendmail() {
  local to="${NOTIFY_EMAIL_TO:-}"
  if [ -z "$to" ]; then
    send_log
    echo "NOTIFY_EMAIL_TO not set; logged only" >&2
    return 0
  fi
  {
    echo "To: ${to}"
    echo "Subject: ${subject}"
    echo
    echo "$body"
  } | sendmail -t
}

send_twilio() {
  # Required env vars:
  # TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM, TWILIO_TO
  local sid="${TWILIO_ACCOUNT_SID:-}"
  local tok="${TWILIO_AUTH_TOKEN:-}"
  local from="${TWILIO_FROM:-}"
  local to="${TWILIO_TO:-}"
  if [ -z "$sid" ] || [ -z "$tok" ] || [ -z "$from" ] || [ -z "$to" ]; then
    send_log
    echo "Twilio env vars missing; logged only" >&2
    return 0
  fi

  curl -sS -X POST "https://api.twilio.com/2010-04-01/Accounts/${sid}/Messages.json" \
    --data-urlencode "From=${from}" \
    --data-urlencode "To=${to}" \
    --data-urlencode "Body=${subject}" \
    -u "${sid}:${tok}" >/dev/null
}

case "$METHOD" in
  log) send_log ;;
  sendmail) send_sendmail ;;
  twilio) send_twilio ;;
  *)
    send_log
    echo "Unknown NOTIFY_METHOD=$METHOD; logged only" >&2
    ;;
esac

echo "$now" > "$STATE_FILE"
echo "$breach_fingerprint" > "$SEEN_FILE"
