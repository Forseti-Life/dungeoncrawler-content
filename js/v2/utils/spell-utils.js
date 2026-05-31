/**
 * @file utils/spell-utils.js
 *
 * Pure utility functions for spell data.
 *
 * Operates on server-provided spell/character data shapes.
 * No side effects. No dependencies.
 *
 * Spell shape: { spell_id, name, action_cost, rank, max_rank?, traits?: string[] }
 * Character shape: { spell_slots: { [rank]: number }, spells_prepared?: spell_id[] }
 */

/**
 * Get all spells available to a character with a given action cost.
 * @param {object}   character       — character data from server
 * @param {number}   [actionCost]    — 1|2|3; if omitted, returns all
 * @returns {object[]}
 */
export function getAvailableSpells(character, actionCost = null) {
  const spells = character?.spells ?? character?.known_spells ?? [];
  return spells.filter((s) => {
    if (actionCost !== null && getSpellActionCost(s) !== actionCost) return false;
    return canCast(character, s).canCast;
  });
}

/**
 * Return the action cost (1|2|3) for a spell.
 * Defaults to 2 if not specified.
 * @param {object} spell
 * @returns {1|2|3}
 */
export function getSpellActionCost(spell) {
  const cost = Number(spell?.action_cost ?? 2);
  if (cost === 1 || cost === 3) return cost;
  return 2;
}

/**
 * Return available cast ranks for a spell (for heightening).
 * @param {object} character
 * @param {object} spell
 * @returns {number[]}  — sorted ascending
 */
export function getSpellRanks(character, spell) {
  const baseRank = Number(spell?.rank ?? spell?.level ?? 1);
  const slots    = character?.spell_slots ?? {};
  const ranks    = [];
  for (let r = baseRank; r <= 10; r++) {
    if ((slots[r] ?? 0) > 0) ranks.push(r);
  }
  return ranks;
}

/**
 * Check if a character can cast a spell at a given rank.
 * @param {object}  character
 * @param {object}  spell
 * @param {number}  [rank]      — defaults to spell's base rank
 * @returns {{ canCast: boolean, reason?: string }}
 */
export function canCast(character, spell, rank = null) {
  const targetRank = rank ?? Number(spell?.rank ?? spell?.level ?? 1);
  const slots      = character?.spell_slots ?? {};
  const available  = slots[targetRank] ?? 0;
  if (available <= 0) {
    return { canCast: false, reason: `No rank-${targetRank} spell slots remaining` };
  }
  const baseRank = Number(spell?.rank ?? spell?.level ?? 1);
  if (targetRank < baseRank) {
    return { canCast: false, reason: 'Cannot downcast a spell below its base rank' };
  }
  return { canCast: true };
}

