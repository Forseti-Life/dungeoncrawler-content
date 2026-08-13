/**
 * @file utils/action-utils.js
 *
 * Action rail cost helpers ported verbatim from hexmap.js.
 */

export function getActionRailCost(rawValue, fallback = 1) {
  if (typeof rawValue === 'number' && Number.isFinite(rawValue)) {
    return Math.max(0, Math.min(3, rawValue));
  }
  const normalized = String(rawValue ?? '').trim().toLowerCase();
  if (normalized === '') {
    return fallback;
  }
  if (normalized === 'free' || normalized === 'reaction' || normalized === '0') {
    return 0;
  }
  const numeric = Number(normalized);
  if (Number.isFinite(numeric)) {
    return Math.max(0, Math.min(3, numeric));
  }
  return fallback;
}

export function formatActionRailCost(cost) {
  const numericCost = Number(cost);
  if (!Number.isFinite(numericCost)) {
    return '';
  }
  if (numericCost <= 0) {
    return 'Free';
  }
  return `${numericCost} action${numericCost === 1 ? '' : 's'}`;
}

export function getActionRailRemainingActions(context) {
  const serverRemaining = Number(context?.phaseSnapshot?.turn?.actions_remaining);
  if (Number.isFinite(serverRemaining)) {
    return Math.max(0, serverRemaining);
  }
  const remaining = Number(context?.actions?.actionsRemaining);
  return Number.isFinite(remaining) ? Math.max(0, remaining) : null;
}
