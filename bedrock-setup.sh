#!/usr/bin/env bash
# Source this file to configure AWS/Bedrock credentials
# Usage: source bedrock-setup.sh
#
# Preferred: uses AWS profile (forseti-bedrock role assumption) when configured.
# Fallback: loads direct credentials from .env.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
QUIET="${BEDROCK_SETUP_QUIET:-0}"
force_reload="${BEDROCK_SETUP_FORCE:-0}"

# Prefer profile-based auth (role assumption) if configured
if grep -q '\[profile forseti-bedrock\]' "${HOME}/.aws/config" 2>/dev/null; then
  # Unset direct creds so they don't override the profile
  unset AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_SESSION_TOKEN
  export AWS_PROFILE=forseti-bedrock
  export AWS_DEFAULT_REGION=us-east-1
  if [ "$QUIET" != "1" ]; then
    echo "✅ Bedrock: using AWS profile 'forseti-bedrock' (role assumption)"
    echo "   Region: $AWS_DEFAULT_REGION"
  fi
  return 0 2>/dev/null || exit 0
fi

# Fallback: load direct credentials from .env
if [ -f "$ROOT_DIR/.env" ]; then
  existing_key="${AWS_ACCESS_KEY_ID:-}"
  existing_secret="${AWS_SECRET_ACCESS_KEY:-}"
  existing_region="${AWS_DEFAULT_REGION:-${AWS_REGION:-}}"

  if [ "$force_reload" = "1" ] || [ -z "$existing_key" ] || [ -z "$existing_secret" ]; then
    export AWS_ACCESS_KEY_ID=$(grep "AWS_ACCESS_KEY_ID" "$ROOT_DIR/.env" | cut -d"'" -f2)
    export AWS_SECRET_ACCESS_KEY=$(grep "AWS_SECRET_ACCESS_KEY" "$ROOT_DIR/.env" | cut -d"'" -f2)
  fi

  if [ "$force_reload" = "1" ] || [ -z "$existing_region" ]; then
    export AWS_DEFAULT_REGION=$(grep "AWS_DEFAULT_REGION" "$ROOT_DIR/.env" | cut -d"'" -f2)
  elif [ -n "$existing_region" ]; then
    export AWS_DEFAULT_REGION="$existing_region"
  fi

  if [ -n "$AWS_ACCESS_KEY_ID" ] && [ -n "$AWS_SECRET_ACCESS_KEY" ]; then
    if [ "$QUIET" != "1" ]; then
      echo "✅ Bedrock credentials loaded from .env (direct key)"
      echo "   Region: $AWS_DEFAULT_REGION"
    fi
    return 0 2>/dev/null || exit 0
  else
    echo "❌ Bedrock credentials not found in .env"
    return 1 2>/dev/null || exit 1
  fi
else
  echo "❌ .env file not found and no AWS profile configured"
  return 1 2>/dev/null || exit 1
fi
