/**
 * @file utils/dom-utils.js
 *
 * Shared DOM/tooltip helpers ported verbatim from hexmap.js.
 * Note: escapeTooltipAttr delegates to quest-utils escapeQuestHtml.
 */

import { escapeQuestHtml } from './quest-utils.js';

export function escapeTooltipAttr(value) {
  return escapeQuestHtml(value);
}

export function slugifyTooltipKey(value) {
  return String(value ?? '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

export function uniqueTooltipStrings(values) {
  return Array.from(new Set((Array.isArray(values) ? values : [])
    .map(value => String(value ?? '').trim())
    .filter(Boolean)));
}

export function flattenTooltipBuckets(value) {
  if (Array.isArray(value)) {
    return value;
  }
  if (!value || typeof value !== 'object') {
    return [];
  }
  return Object.values(value).flatMap(entry => Array.isArray(entry) ? entry : []);
}

export function tooltipSourceMatches(candidate, sourceId) {
  if (!candidate || !sourceId) {
    return false;
  }
  return candidate === sourceId || candidate.indexOf(`${sourceId}-`) === 0;
}

export function formatTooltipActionCost(actionCost) {
  if (actionCost == null || actionCost === '') {
    return '';
  }
  if (typeof actionCost === 'number') {
    return `${actionCost} action${actionCost === 1 ? '' : 's'}`;
  }
  return String(actionCost).replace(/_/g, ' ');
}
