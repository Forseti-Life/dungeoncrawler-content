/**
 * @file
 * Shared navigate-category Action Rail panel builders.
 *
 * This layer is presentation-only. It consumes authoritative navigation
 * capabilities and visited-location feeds; it must not infer or enforce
 * movement legality beyond what server contracts provide.
 */

import { escapeQuestHtml } from '../utils/quest-utils.js';
import { buildActionRailEntrySummary } from '../utils/inventory-utils.js';
function formatNavigationLocationTitle(dungeonName, roomName) {
  const dungeon = String(dungeonName || '').trim();
  const room = String(roomName || '').trim();
  if (dungeon && room) {
    return `${dungeon} — ${room}`;
  }
  return room || dungeon || 'Destination';
}

export function buildNavigateActionRailPanel(panel, context) {
  const groups = collectNavigateExitGroups(panel, context);
  const currentLocationLabel = resolveCurrentLocationLabel(context, panel);

  if (!groups.length) {
    const emptyMsg = 'No room exits are available yet.';
    return {
      title: 'Movement',
      chip: currentLocationLabel ? `📍 ${currentLocationLabel}` : 'No routes',
      html: currentLocationLabel
        ? `<div class="action-rail__current-location"><p class="action-rail__current-location-label">📍 You are here: <strong>${escapeQuestHtml(currentLocationLabel)}</strong></p></div><div class="action-rail__empty"><p>${emptyMsg}</p></div>`
        : `<div class="action-rail__empty"><p>${emptyMsg}</p></div>`,
    };
  }

  const entryCount = groups.reduce((total, group) => total + group.locations.length, 0);
  const currentLocationHtml = currentLocationLabel
    ? `<div class="action-rail__current-location"><p class="action-rail__current-location-label">📍 You are here: <strong>${escapeQuestHtml(currentLocationLabel)}</strong></p></div>`
    : '';

  const html = currentLocationHtml + groups.map((group) => {
    const entries = group.locations.map((location, index) => panel.renderActionRailEntry({
      execute: 'navigate',
      title: group.key === 'room-exits'
        ? `${index + 1}. ${location.roomName}`
        : formatNavigationLocationTitle(location.dungeonName || group.dungeonName, location.roomName),
      summary: buildActionRailEntrySummary([
        location.statusLabel || group.dungeonName || group.title,
        location.lastVisitedLabel,
      ]),
      meta: location.meta,
      disabled: location.disabled === true || location.navigable === false,
      dataset: {
        roomId: location.roomId,
        roomName: location.roomName,
        connectionId: location.connectionId,
        originQ: location.originQ,
        originR: location.originR,
        mapId: location.mapId || group.mapId || '',
        dungeonLevelId: location.dungeonLevelId || group.dungeonLevelId || '',
      },
      actionLabel: location.navigable === false ? 'Unavailable' : 'Travel here',
    })).join('');
    return `<section class="action-rail__group"><p class="action-rail__group-label">${escapeQuestHtml(group.title || group.dungeonName || 'Destinations')}</p>${entries}</section>`;
  }).join('');

  return {
    title: 'Movement',
    chip: entryCount > 0
      ? `${entryCount} destination${entryCount === 1 ? '' : 's'}`
      : 'No routes',
    html,
  };
}

function collectNavigateExitGroups(panel, context) {
  const hexmap = context.hexmap;
  const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
  const activeDungeonName = resolveActiveDungeonName(panel, context);
  const capabilitiesRaw = typeof hexmap?.resolveNavigationCapabilities === 'function'
    ? hexmap.resolveNavigationCapabilities(activeRoomId)
    : [];
  const capabilities = Array.isArray(capabilitiesRaw) ? capabilitiesRaw : [];
  const currentMapId = String(hexmap?.dungeonData?.map_id || hexmap?.launchContext?.map_id || panel.stateManager?.get?.('mapId') || '').trim();
  const currentDungeonLevelId = String(hexmap?.dungeonData?.level_id || hexmap?.launchContext?.dungeon_level_id || '').trim();

  const exits = capabilities
    .map((capability) => {
      const targetRoomId = String(capability?.target_room_id || '').trim();
      const connectionId = String(capability?.connection_id || '').trim();
      const isSyntheticSelfExit = targetRoomId === activeRoomId && connectionId.toLowerCase().endsWith(':self-exit');
      if (!targetRoomId || isSyntheticSelfExit) {
        return null;
      }
      const destinationType = String(capability?.destination_type || 'room').trim().toLowerCase() || 'room';
      const connectionType = String(capability?.type || '').trim().toLowerCase();
      const distanceValue = Number.isFinite(Number(capability?.distance)) ? Number(capability.distance) : 0;
      const navigable = capability?.available !== false;
      const blockedReason = String(capability?.blocked_reason || '').trim().toLowerCase();
      const isDiscovered = Object.prototype.hasOwnProperty.call(capability || {}, 'is_discovered')
        ? Boolean(capability.is_discovered)
        : true;
      const isQuestTarget = capability?.quest_reference === true;
      const questIds = Array.isArray(capability?.quest_ids) ? capability.quest_ids : [];
      if (!isDiscovered) {
        return null;
      }
      
      return {
        roomId: targetRoomId,
        roomName: resolveReadableRoomName([capability?.target_room_name], targetRoomId),
        statusLabel: isQuestTarget ? '🎯 Quest Target' : (navigable ? 'Exit' : 'Unavailable'),
        lastVisitedLabel: 'Linked from current room',
        meta: [
          `Destination: ${formatDestinationType(destinationType)}`,
          `Distance: ${formatDistanceValue(distanceValue, destinationType, connectionType)}`,
          !navigable && blockedReason ? `Blocked: ${formatBlockedReason(blockedReason)}` : '',
          isQuestTarget ? '⭐ This location is a quest objective' : '',
          capability?.type ? `Connection: ${String(capability.type).replace(/_/g, ' ')}` : '',
        ].filter(Boolean).join(' '),
        navigable,
        connectionId: String(capability?.connection_id || ''),
        originQ: capability?.origin_hex?.q ?? '',
        originR: capability?.origin_hex?.r ?? '',
        mapId: currentMapId,
        dungeonLevelId: currentDungeonLevelId,
        questIds: questIds,
      };
    })
    .filter(Boolean)
    .sort((a, b) => {
      const aUnavailable = a.navigable === false ? 1 : 0;
      const bUnavailable = b.navigable === false ? 1 : 0;
      if (aUnavailable !== bUnavailable) {
        return aUnavailable - bUnavailable;
      }
      return String(a.roomName || '').localeCompare(String(b.roomName || ''));
    });

  if (!exits.length) {
    return [];
  }

  return [{
    key: 'room-exits',
    title: 'Room exits',
    dungeonName: activeDungeonName || 'Room exits',
    mapId: currentMapId,
    dungeonLevelId: currentDungeonLevelId,
    locations: exits,
  }];
}

function resolveCurrentLocationLabel(context, panel) {
  const hexmap = context?.hexmap;
  const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
  const dungeonName = resolveActiveDungeonName(panel, context);
  if (!activeRoomId) {
    return dungeonName || '';
  }
  const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
  const activeRoom = visualRooms && typeof visualRooms === 'object' ? visualRooms[activeRoomId] || null : null;
  const roomName = resolveReadableRoomName([activeRoom?.name, activeRoom?.title], activeRoomId);
  return formatNavigationLocationTitle(dungeonName, roomName);
}

function resolveReadableRoomName(roomNameCandidates, roomId = '') {
  const candidates = Array.isArray(roomNameCandidates) ? roomNameCandidates : [roomNameCandidates];
  for (const candidate of candidates) {
    const normalized = String(candidate || '').trim();
    if (!normalized || looksLikeRoomIdentifier(normalized)) {
      continue;
    }
    return normalized;
  }
  return String(roomId || '').trim() ? formatRoomIdentifierLabel(roomId) : '';
}

function resolveActiveDungeonName(panel, context) {
  const serverDungeonName = String(panel?.navigateActiveRoom?.dungeonName || '').trim();
  if (serverDungeonName) {
    return serverDungeonName;
  }
  const hexmap = context?.hexmap;
  return String(
    hexmap?.dungeonData?.name
    || hexmap?.dungeonData?.dungeon_name
    || hexmap?.launchContext?.dungeon_name
    || ''
  ).trim();
}

function formatDestinationType(destinationType) {
  if (destinationType === 'road') {
    return 'Road';
  }
  return 'Room';
}

function formatDistanceValue(distance, destinationType = 'room', connectionType = '') {
  const normalized = Number.isFinite(Number(distance)) ? Math.max(0, Math.trunc(Number(distance))) : 0;
  if (destinationType === 'road') {
    return `access ${normalized}`;
  }
  if (connectionType === 'road_network' && normalized > 0) {
    return `road ${normalized}`;
  }
  return String(normalized);
}

function formatBlockedReason(reason) {
  const normalized = String(reason || '').trim().toLowerCase();
  const labels = {
    missing_road_path: 'no connected road path',
    missing_road_anchor: 'missing road anchor',
    invalid_distance_contract: 'invalid distance contract',
    unresolved_destination: 'unresolved destination',
  };
  if (labels[normalized]) {
    return labels[normalized];
  }
  return normalized ? normalized.replace(/_/g, ' ') : 'unavailable';
}

function looksLikeRoomIdentifier(value) {
  const normalized = String(value || '').trim();
  if (normalized === '') {
    return true;
  }
  if (normalized.startsWith('tpl_room_')) {
    return true;
  }
  return /^[a-z0-9:_-]+$/i.test(normalized) && !/\s/.test(normalized);
}

function formatRoomIdentifierLabel(roomId) {
  const normalized = String(roomId || '').trim();
  if (normalized === '') {
    return 'Unknown room';
  }
  const withoutTemplatePrefix = normalized.replace(/^tpl_room_/i, '');
  const words = withoutTemplatePrefix
    .split(/[_-]+/)
    .filter(Boolean)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase());
  if (!words.length) {
    return normalized;
  }
  const joined = words.join(' ');
  return /^the\s+/i.test(joined) ? joined : `The ${joined}`;
}
