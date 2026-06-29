#!/usr/bin/env bash
# Shared site path resolver — source this file from any HQ script that needs
# filesystem paths for Drupal sites or the HQ repo root.
#
# Environment overrides take precedence over defaults so that CI, staging, and
# non-standard server layouts can override without editing scripts.

# HQ repo root — resolved relative to this file so it survives future directory renames.
HQ_ROOT="${HQ_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

# Drupal site roots.
FORSETI_SITE_DIR="${FORSETI_SITE_DIR:-/var/www/html/forseti}"
DUNGEONCRAWLER_SITE_DIR="${DUNGEONCRAWLER_SITE_DIR:-/var/www/html/dungeoncrawler}"

# Back-compat alias used by several legacy scripts.
FORSITI_SITE_DIR="${FORSETI_SITE_DIR}"
