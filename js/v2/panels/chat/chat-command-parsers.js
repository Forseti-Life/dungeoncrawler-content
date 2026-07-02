export function parseGmLocationRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-location|location)\s+(.+)$/i);
  if (!match) {
    return '';
  }
  return String(match[1] ?? '').trim();
}

export function parseGmRoomRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-room|room)\s+([a-z_][a-z0-9_-]*)(?:\s+([a-z_][a-z0-9_-]*))?(?:\s+([a-z_][a-z0-9_-]*))?$/i);
  if (!match) {
    return null;
  }
  return {
    roomType: String(match[1] || 'chamber').toLowerCase(),
    terrainType: String(match[2] || 'stone_floor').toLowerCase(),
    roomSize: String(match[3] || 'medium').toLowerCase(),
  };
}

export function parseGmQuestRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-quests|quests)(?:\s+(\d+))?$/i);
  if (!match) {
    return null;
  }
  return {
    count: Math.max(1, Math.min(5, Number(match[1] || 3))),
  };
}

export function parseGmDungeonRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-dungeon|dungeon)\s+(-?\d+)\s+(-?\d+)(?:\s+(.+))?$/i);
  if (!match) {
    return null;
  }

  const extra = String(match[3] || '').trim();
  let partyLevel = null;
  let theme = '';
  if (extra) {
    const parts = extra.split(/\s+/);
    if (/^\d+$/.test(parts[0] || '')) {
      partyLevel = Math.max(1, Math.min(20, Number(parts.shift())));
    }
    theme = parts.join(' ').trim();
  }

  return {
    locationX: Number(match[1]),
    locationY: Number(match[2]),
    partyLevel,
    theme,
  };
}
