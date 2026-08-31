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
  'cast_spell',
  'stride',
  'step',
  'interact',
  'talk',
  'search',
  'skill',
  'feat',
  'consume_item',
  'demoralize',
  'raise_shield',
  'delay',
  'feint',
  'point_out',
  'command_animal',
  'aid_setup',
  'administer_first_aid',
  'treat_poison',
  'battle_medicine',
  'treat_wounds',
  'refocus',
  'repair',
  'daily_preparations',
  // Backward-compatible aliases while older markup drains.
  'spell',
  'consumable',
]);

export const ACTION_SELECTION_HANDLERS = Object.freeze({
  attack: 'executeDirectAttack',
  cast_spell: 'executeDirectSpell',
  stride: 'executeDirectStride',
  step: 'executeDirectStep',
  interact: 'executeDirectInteract',
  talk: 'executeDirectTalk',
  search: 'executeDirectSearch',
  skill: 'executeDirectSkill',
  feat: 'executeDirectFeat',
  consume_item: 'executeDirectConsumable',
  demoralize: 'executeDirectDemoralize',
  raise_shield: 'executeDirectRaiseShield',
  delay: 'executeDirectDelay',
  party_recovery: 'executeDirectPartyRecovery',
  feint: 'executeDirectFeint',
  point_out: 'executeDirectPointOut',
  command_animal: 'executeDirectCommandAnimal',
  aid_setup: 'executeDirectAidSetup',
  administer_first_aid: 'executeDirectAdministerFirstAid',
  treat_poison: 'executeDirectTreatPoison',
  battle_medicine: 'executeDirectBattleMedicine',
  // Backward-compatible aliases while older markup drains.
  spell: 'executeDirectSpell',
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
  return normalizeKey(actionType);
}

export function isRestActivityActionKey(actionKey) {
  return REST_ACTIVITY_ACTION_KEYS.has(normalizeKey(actionKey));
}
