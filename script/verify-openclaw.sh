#!/bin/bash

set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

print_ok() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_err() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo "=== OpenClaw Verification ==="

if ! command -v node >/dev/null 2>&1; then
    print_err "Node.js is not installed"
    exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
    print_err "npm is not installed"
    exit 1
fi

NODE_VERSION_RAW=$(node -v | sed 's/^v//')
NODE_MIN_REQUIRED="22.12.0"

echo "Node.js version: v$NODE_VERSION_RAW"
echo "npm version: $(npm -v)"

if [ "$(printf '%s\n' "$NODE_MIN_REQUIRED" "$NODE_VERSION_RAW" | sort -V | head -n1)" != "$NODE_MIN_REQUIRED" ]; then
    print_err "Node.js v$NODE_VERSION_RAW does not satisfy OpenClaw requirement (>= $NODE_MIN_REQUIRED)"
    print_warn "Run setup script or install Node.js 22.x+"
    exit 1
fi

print_ok "Node.js version satisfies OpenClaw requirement"

if ! command -v openclaw >/dev/null 2>&1; then
    print_err "OpenClaw CLI not found on PATH"
    print_warn "Install with: sudo npm install -g openclaw@2026.2.17"
    exit 1
fi

OPENCLAW_PATH=$(command -v openclaw)
echo "OpenClaw path: $OPENCLAW_PATH"

if OPENCLAW_VERSION=$(openclaw --version 2>/dev/null | head -n1); then
    print_ok "OpenClaw version: $OPENCLAW_VERSION"
else
    print_err "OpenClaw executable exists but failed to run"
    exit 1
fi

if npm ls -g openclaw --depth=0 >/dev/null 2>&1; then
    print_ok "Global npm package openclaw is installed"
else
    print_warn "OpenClaw binary exists, but npm global package listing check failed"
fi

echo "=== OpenClaw verification passed ==="