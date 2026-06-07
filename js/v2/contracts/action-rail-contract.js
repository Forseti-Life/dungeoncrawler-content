/**
 * @file
 * Canonical Action Rail contracts shared by panel routing and execution systems.
 */

export const ACTION_RAIL_CATEGORIES = Object.freeze([
  'navigate',
  'search',
  'rest',
  'spells',
  'consumables',
  'skills',
  'feats',
  'turn',
]);

const ACTION_RAIL_DIRECT_ROUTE_BUILDERS = Object.freeze({
  navigate: (button) => ({ event: 'user:navigate', payload: { button } }),
  end_turn: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } }),
  choose_not_to_act: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } }),
});

const ACTION_RAIL_SELECTABLE_ACTIONS = new Set([
  'attack',
  'spell',
  'interact',
  'search',
  'skill',
  'feat',
  'consumable',
  'treat_wounds',
  'refocus',
  'repair',
  'daily_preparations',
]);

const ACTION_RAIL_EXECUTE_TO_SERVER_ACTION = Object.freeze({
  search: 'search',
  spell: 'cast_spell',
  consumable: 'consume_item',
  skill: 'skill',
  feat: 'feat',
  choose_not_to_act: 'choose_not_to_act',
  end_turn: 'end_turn',
  treat_wounds: 'treat_wounds',
  refocus: 'refocus',
  repair: 'repair',
  daily_preparations: 'daily_preparations',
});

export const ACTION_SELECTION_HANDLERS = Object.freeze({
  attack: 'executeDirectAttack',
  spell: 'executeDirectSpell',
  interact: 'executeDirectInteract',
  search: 'executeDirectSearch',
  skill: 'executeDirectSkill',
  feat: 'executeDirectFeat',
  consumable: 'executeDirectConsumable',
});

const REST_ACTIVITY_ACTION_KEYS = new Set(['treat_wounds', 'refocus', 'repair', 'daily_preparations']);

function normalizeKey(value = '') {
  return String(value || '').trim().toLowerCase();
}

export function resolveActionRailCategory(category = '', fallback = 'navigate') {
  const normalizedCategory = normalizeKey(category);
  if (ACTION_RAIL_CATEGORIES.includes(normalizedCategory)) {
    return normalizedCategory;
  }
  const normalizedFallback = normalizeKey(fallback);
  if (ACTION_RAIL_CATEGORIES.includes(normalizedFallback)) {
    return normalizedFallback;
  }
  return 'navigate';
}

export function getActionRailDirectRoute(actionType, button) {
  const normalizedActionType = normalizeKey(actionType);
  const routeBuilder = ACTION_RAIL_DIRECT_ROUTE_BUILDERS[normalizedActionType];
  return routeBuilder ? routeBuilder(button, normalizedActionType) : null;
}

export function isActionRailSelectableAction(actionType) {
  return ACTION_RAIL_SELECTABLE_ACTIONS.has(normalizeKey(actionType));
}

export function getServerActionIdForExecute(actionType) {
  const normalizedActionType = normalizeKey(actionType);
  return ACTION_RAIL_EXECUTE_TO_SERVER_ACTION[normalizedActionType] || normalizedActionType;
}

export function isRestActivityActionKey(actionKey) {
  return REST_ACTIVITY_ACTION_KEYS.has(normalizeKey(actionKey));
}
