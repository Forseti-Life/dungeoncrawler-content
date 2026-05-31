/**
 * @file utils/spell-utils.js
 *
 * Spell data normalization helpers ported verbatim from hexmap.js.
 * Note: appendSpells and appendRankedCollection are inner closures
 * inside collectSpellRankGroups and do not need separate exports.
 */

export function getSpellRankNumber(rankKey) {
  const normalized = String(rankKey ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');
  const directMap = {
    cantrip: 0,
    cantrips: 0,
    first: 1,
    first_level: 1,
    second: 2,
    second_level: 2,
    third: 3,
    third_level: 3,
    fourth: 4,
    fourth_level: 4,
    fifth: 5,
    fifth_level: 5,
    sixth: 6,
    sixth_level: 6,
    seventh: 7,
    seventh_level: 7,
    eighth: 8,
    eighth_level: 8,
    ninth: 9,
    ninth_level: 9,
    tenth: 10,
    tenth_level: 10,
  };
  if (Object.prototype.hasOwnProperty.call(directMap, normalized)) {
    return directMap[normalized];
  }
  const digitMatch = normalized.match(/^(\d{1,2})(?:st|nd|rd|th)?(?:_level)?$/);
  if (digitMatch) {
    return Number(digitMatch[1]);
  }
  const levelMatch = normalized.match(/^level_(\d{1,2})$/);
  if (levelMatch) {
    return Number(levelMatch[1]);
  }
  return null;
}

export function formatOrdinalRank(rank) {
  const numericRank = Number(rank);
  if (!Number.isFinite(numericRank)) {
    return String(rank ?? '');
  }
  const mod100 = numericRank % 100;
  if (mod100 >= 11 && mod100 <= 13) {
    return `${numericRank}th`;
  }
  switch (numericRank % 10) {
    case 1:
      return `${numericRank}st`;
    case 2:
      return `${numericRank}nd`;
    case 3:
      return `${numericRank}rd`;
    default:
      return `${numericRank}th`;
  }
}

export function formatSpellRankLabel(rankOrKey, { longForm = false } = {}) {
  const rank = typeof rankOrKey === 'number' ? rankOrKey : getSpellRankNumber(rankOrKey);
  if (rank === 0) {
    return 'Cantrips';
  }
  if (rank == null) {
    return String(rankOrKey ?? '');
  }
  const ordinal = formatOrdinalRank(rank);
  return longForm ? `${ordinal} Level` : ordinal;
}

export function normalizeDisplayedSpellSlots(runtimeSlots, slotDisplay) {
  const normalizedSlots = {};

  if (runtimeSlots && typeof runtimeSlots === 'object') {
    Object.entries(runtimeSlots).forEach(([slotKey, slotState]) => {
      const rank = getSpellRankNumber(slotKey);
      if (rank == null || rank === 0) {
        return;
      }
      const max = Math.max(0, Number(slotState?.max ?? slotState?.current ?? 0));
      const current = Math.max(0, Math.min(Number(slotState?.current ?? max), max || Number(slotState?.current ?? 0)));
      normalizedSlots[String(rank)] = { current, max };
    });
  }

  if (slotDisplay && typeof slotDisplay === 'object') {
    Object.entries(slotDisplay).forEach(([slotKey, slotCount]) => {
      const rank = getSpellRankNumber(slotKey);
      if (rank == null || rank === 0) {
        return;
      }
      const max = Math.max(0, Number(slotCount ?? 0));
      if (max <= 0) {
        return;
      }
      const existing = normalizedSlots[String(rank)] || {};
      const current = Math.max(0, Math.min(Number(existing.current ?? existing.max ?? max), max));
      normalizedSlots[String(rank)] = { current, max };
    });
  }

  return Object.fromEntries(
    Object.entries(normalizedSlots).sort(([a], [b]) => Number(a) - Number(b))
  );
}

export function collectSpellRankGroups(spells) {
  if (!spells || typeof spells !== 'object') {
    return [];
  }
  const innateFeatSpells = Array.isArray(spells.featAugments?.innate_spells)
    ? spells.featAugments.innate_spells.map((entry) => ({
      ...entry,
      id: entry?.spell_id || entry?.id || '',
      name: entry?.spell_name || entry?.spell_id || entry?.name || '',
      rank: 0,
    }))
    : [];
  const grouped = new Map();
  const getSpellIdentity = (spell) => {
    if (typeof spell === 'string') {
      return spell;
    }
    if (!spell || typeof spell !== 'object') {
      return String(spell ?? '');
    }
    return String(spell.spell_id || spell.id || spell.name || spell.spell_name || JSON.stringify(spell));
  };
  const appendSpells = (entries, fallbackRank = null) => {
    if (!Array.isArray(entries) || entries.length === 0) {
      return;
    }
    entries.forEach((entry) => {
      const spell = entry && typeof entry === 'object' && entry.spell && typeof entry.spell === 'object'
        ? entry.spell
        : entry;
      const rank = getSpellRankNumber(
        spell?.rank
        ?? spell?.level
        ?? spell?.spell_level
        ?? spell?.cast_at_level
        ?? entry?.rank
        ?? entry?.level
        ?? fallbackRank
      );
      if (rank == null) {
        return;
      }
      if (!grouped.has(rank)) {
        grouped.set(rank, []);
      }
      const bucket = grouped.get(rank);
      const spellIdentity = getSpellIdentity(spell);
      if (!bucket.some((existing) => getSpellIdentity(existing) === spellIdentity)) {
        bucket.push(spell);
      }
    });
  };
  const appendRankedCollection = (collection, fallbackRank = null) => {
    if (!collection) {
      return;
    }
    if (Array.isArray(collection)) {
      appendSpells(collection, fallbackRank);
      return;
    }
    if (typeof collection !== 'object') {
      return;
    }
    Object.entries(collection).forEach(([rankKey, entries]) => {
      appendSpells(entries, getSpellRankNumber(rankKey) ?? fallbackRank);
    });
  };

  Object.entries(spells).forEach(([groupKey, groupSpells]) => {
    if (!Array.isArray(groupSpells) || groupSpells.length === 0) {
      return;
    }
    const rank = getSpellRankNumber(groupKey);
    if (rank == null) {
      return;
    }
    appendSpells(groupSpells, rank);
  });
  appendSpells(spells.cantrips, 0);
  appendSpells(spells.focusSpells);
  appendSpells(spells.preparedSpells);
  appendSpells(spells.knownSpells);
  appendRankedCollection(spells.spellbook, 1);
  appendRankedCollection(spells.spellsKnown ?? spells.spells_known);
  appendSpells(innateFeatSpells, 0);

  return Array.from(grouped.entries())
    .map(([rank, rankSpells]) => ({
      rank,
      label: formatSpellRankLabel(rank, { longForm: rank !== 0 }),
      spells: rankSpells,
    }))
    .sort((a, b) => a.rank - b.rank);
}

export function normalizeSpellcastingData(spells, ...sources) {
  if (!spells || typeof spells !== 'object') {
    return {};
  }
  const sourcePool = [spells, ...sources].filter((entry) => entry && typeof entry === 'object');
  const pickArray = (...values) => {
    for (const value of values) {
      if (Array.isArray(value) && value.length > 0) {
        return value;
      }
    }
    return [];
  };
  const pickObject = (...values) => {
    for (const value of values) {
      if (value && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length > 0) {
        return value;
      }
    }
    return {};
  };
  const topLevelSpellbook = pickObject(...sourcePool.map((entry) => entry.spellbook));
  const topLevelKnownSpells = pickObject(
    ...sourcePool.map((entry) => entry.spellsKnown),
    ...sourcePool.map((entry) => entry.spells_known)
  );
  return {
    ...spells,
    tradition: spells.tradition || spells.spellcastingTradition || spells.spellcasting_tradition || '',
    casting_ability: spells.casting_ability || spells.castingAbility || spells.key_ability || '',
    slots: spells.slots || spells.spellSlots || spells.spell_slots || {},
    first_level: pickArray(
      spells.first_level,
      spells.firstLevel,
      Array.isArray(spells.spellbook) ? spells.spellbook : null,
      ...sourcePool.map((entry) => entry.first_level),
      ...sourcePool.map((entry) => entry.firstLevel),
      ...sourcePool.map((entry) => entry.spells_first),
      ...sourcePool.map((entry) => Array.isArray(entry.spellbook) ? entry.spellbook : null)
    ),
    spellbook: pickObject(spells.spellbook, topLevelSpellbook),
    spellsKnown: pickObject(spells.spellsKnown, spells.spells_known, topLevelKnownSpells),
  };
}
