/**
 * Extracted GameShell projection/runtime helper functions.
 */

export function _getPresentationObjectDefinitions(mapVisualState = {}, dungeonData = {}) {
  const visualDefinitions = mapVisualState?.presentation?.object_definitions;
  if (visualDefinitions && typeof visualDefinitions === 'object') {
    return visualDefinitions;
  }
  return {};
}

export function _getVisualOccupants(mapVisualState = {}) {
  return [
    ...(Array.isArray(mapVisualState?.occupants?.party)
      ? mapVisualState.occupants.party.map((occupant) => ({ ...occupant, is_party: true }))
      : []),
    ...(Array.isArray(mapVisualState?.occupants?.entities) ? mapVisualState.occupants.entities : []),
  ];
}

export function _getVisualActorRoster(mapVisualState = {}) {
  const roster = mapVisualState?.actor_roster;
  const entries = Array.isArray(roster?.entries) ? roster.entries : [];
  return entries
    .filter((entry) => entry && typeof entry === 'object')
    .map((entry) => ({ ...entry }));
}

export function _getEntityDisplayName(entity = null) {
  if (!entity || typeof entity !== 'object') {
    return 'Unknown';
  }

  const identity = entity.getComponent?.('IdentityComponent');
  if (identity?.name) {
    return String(identity.name);
  }

  return String(
    entity?.dcLabel
    || entity?.dcName
    || entity?.dcStatePayload?.label
    || entity?.dcStatePayload?.display_name
    || entity?.dcStatePayload?.name
    || entity?.dcStatePayload?.metadata?.name
    || entity?.dcEntityRef
    || entity?.id
    || 'Unknown'
  );
}

export function _isVisualOccupantVisible(occupant, activeRoomId = '') {
  if (!occupant) {
    return false;
  }

  const hidden = occupant?.hidden === true || occupant?.state?.hidden === true;
  const detected = occupant?.detected === true || occupant?.state?.detected === true;
  const inActiveRoom = String(occupant?.room_id || '').trim() !== ''
    && String(occupant?.room_id || '').trim() === String(activeRoomId || '').trim();

  if (occupant.visible === true) {
    return true;
  }

  // Occupants standing in the active room are authoritative over the legacy
  // `visible` flag, which goes stale once the projection moves an actor into
  // the current room. Hidden-and-undetected still wins so stealth works.
  if (inActiveRoom) {
    return !(hidden && !detected);
  }

  if (occupant.visible === false) {
    return false;
  }

  if (hidden && !detected) {
    return false;
  }

  return true;
}

export function _parseVisualHexId(hexId) {
  const normalized = String(hexId || '').trim();
  if (!normalized) {
    return null;
  }

  const segments = normalized.split(':');
  if (segments.length < 3) {
    return null;
  }

  const r = Number(segments.pop());
  const q = Number(segments.pop());
  const roomId = segments.join(':');
  if (!roomId || !Number.isFinite(q) || !Number.isFinite(r)) {
    return null;
  }

  return {
    room_id: roomId,
    q,
    r,
  };
}

export function _getConnectionRoomId(connection, side) {
  const key = side === 'to' ? 'to' : 'from';
  return String(connection?.[`${key}_room_id`] || connection?.[`${key}_room`] || '').trim() || null;
}

export function _getConnectionHex(connection, side) {
  const key = side === 'to' ? 'to' : 'from';
  return _parseVisualHexId(connection?.[`${key}_hex_id`]) || connection?.[`${key}_hex`] || null;
}

export function _getActiveRoomData(rooms = {}, activeRoomId = null) {
  const roomId = String(activeRoomId || '').trim();
  if (!roomId) {
    return null;
  }
  return rooms?.[roomId] || null;
}

export function _getActiveRoomHex(room = null, q, r) {
  if (!room || !Array.isArray(room.hexes)) {
    return null;
  }

  return room.hexes.find((candidate) => Number(candidate?.q) === Number(q) && Number(candidate?.r) === Number(r)) || null;
}

export function _buildActiveRoomOccupantSummary(roomId, occupants = [], isVisible = () => true) {
  const normalizedRoomId = String(roomId || '').trim();
  if (!normalizedRoomId) {
    return '';
  }

  const groupedNames = { pc: [], npc: [], creature: [] };
  const seen = new Set();
  const pushGroupedName = (bucket, name) => {
    if (!bucket || !name) {
      return;
    }
    const dedupeKey = `${bucket}:${String(name).toLowerCase()}`;
    if (seen.has(dedupeKey)) {
      return;
    }
    seen.add(dedupeKey);
    groupedNames[bucket].push(name);
  };

  occupants
    .filter((occupant) => String(occupant?.room_id || '') === normalizedRoomId && isVisible(occupant))
    .forEach((occupant) => {
      const rawType = String(occupant?.occupant_type || '').toLowerCase();
      let bucket = '';
      if (rawType === 'player_character' || rawType === 'player' || rawType === 'pc') {
        bucket = 'pc';
      } else if (rawType === 'npc') {
        bucket = 'npc';
      } else if (rawType === 'creature') {
        bucket = 'creature';
      }

      const name = String(occupant?.label || occupant?.content_id || '').trim();
      pushGroupedName(bucket, name);
    });

  const parts = [];
  if (groupedNames.pc.length) {
    parts.push(`Party present: ${groupedNames.pc.join(', ')}`);
  }
  if (groupedNames.npc.length) {
    parts.push(`NPCs present: ${groupedNames.npc.join(', ')}`);
  }
  if (groupedNames.creature.length) {
    parts.push(`Other creatures present: ${groupedNames.creature.join(', ')}`);
  }

  return parts.join('. ');
}

export function _getObjectDefinition(contentId, mapVisualState = {}, dungeonData = {}) {
  if (!contentId) {
    return null;
  }

  const definitions = _getPresentationObjectDefinitions(mapVisualState, dungeonData);
  return definitions && typeof definitions === 'object' ? (definitions[contentId] || null) : null;
}

export function _buildObstacleMobilityProfile(objectDefinition, metadata = {}, contentId = '') {
  const definitionMovement = objectDefinition?.movement || {};
  const normalizedContentId = String(contentId || '').toLowerCase();
  const metadataBlocksMovement = (typeof metadata.blocks_movement === 'boolean') ? metadata.blocks_movement : null;
  const definitionBlocksMovement = (typeof definitionMovement.blocks_movement === 'boolean')
    ? definitionMovement.blocks_movement
    : ((typeof objectDefinition?.blocks_movement === 'boolean') ? objectDefinition.blocks_movement : null);
  const movable = (typeof metadata.movable === 'boolean') ? metadata.movable : Boolean(objectDefinition?.movable);
  const passable = (typeof metadata.passable === 'boolean')
    ? metadata.passable
    : (metadataBlocksMovement !== null)
      ? !metadataBlocksMovement
      : (typeof definitionMovement.passable === 'boolean')
        ? definitionMovement.passable
        : (definitionBlocksMovement === true ? false : Boolean(definitionMovement.passable));
  const stackable = (typeof metadata.stackable === 'boolean') ? metadata.stackable : Boolean(objectDefinition?.stackable);
  const indicatorValues = [
    metadata.fixture_type,
    metadata.obstacle_type,
    metadata.category,
    metadata.type,
    objectDefinition?.category,
    objectDefinition?.type,
    objectDefinition?.object_type,
    normalizedContentId,
  ]
    .filter((value) => typeof value === 'string' && value.length)
    .map((value) => value.toLowerCase());
  const tagValues = [
    ...(Array.isArray(objectDefinition?.tags) ? objectDefinition.tags : []),
    ...(Array.isArray(objectDefinition?.traits) ? objectDefinition.traits : []),
  ]
    .filter((value) => typeof value === 'string' && value.length)
    .map((value) => value.toLowerCase());
  const isWall =
    metadata.is_wall === true ||
    indicatorValues.some((value) => value.includes('wall')) ||
    tagValues.some((value) => value === 'wall' || value.includes('boundary_wall') || value.includes('perimeter_wall'));

  return { movable, passable, stackable, isWall };
}

export function _getObstacleMobilityAtHex(room = null, definitions = {}, q, r) {
  const roomHex = _getActiveRoomHex(room, q, r);
  const roomObjects = Array.isArray(roomHex?.objects) ? roomHex.objects : [];
  const candidate = roomObjects.find((object) => {
    const objectId = String(object?.object_id || '').trim();
    const objectDefinition = definitions?.[objectId] || null;
    const category = String(object?.category || objectDefinition?.category || '').toLowerCase();
    const movement = objectDefinition?.movement || {};
    if (typeof object?.blocks_movement === 'boolean' || typeof object?.passable === 'boolean') {
      return object.blocks_movement === true || object.passable === false;
    }
    return movement.blocks_movement === true
      || movement.passable === false
      || ['obstacle', 'wall', 'barrier', 'barricade', 'door', 'collapsed'].some((token) => category.includes(token));
  });
  if (!candidate) {
    return null;
  }

  const objectId = String(candidate?.object_id || '').trim();
  return _buildObstacleMobilityProfile(definitions?.[objectId] || null, candidate || {}, objectId);
}

export function _getAxialLine(fromQ, fromR, toQ, toR, movementSystem = null) {
  const toCube = (q, r) => ({ x: q, z: r, y: -q - r });
  const fromCube = toCube(fromQ, fromR);
  const targetCube = toCube(toQ, toR);
  const distance = movementSystem?.hexDistance
    ? movementSystem.hexDistance(fromQ, fromR, toQ, toR)
    : Math.max(Math.abs(fromQ - toQ), Math.abs(fromR - toR), Math.abs((fromQ + fromR) - (toQ + toR)));

  const points = [];
  for (let step = 0; step <= distance; step += 1) {
    const t = distance === 0 ? 0 : step / distance;
    const x = fromCube.x + (targetCube.x - fromCube.x) * t;
    const y = fromCube.y + (targetCube.y - fromCube.y) * t;
    const z = fromCube.z + (targetCube.z - fromCube.z) * t;

    let rx = Math.round(x);
    let ry = Math.round(y);
    let rz = Math.round(z);
    const dx = Math.abs(rx - x);
    const dy = Math.abs(ry - y);
    const dz = Math.abs(rz - z);

    if (dx > dy && dx > dz) {
      rx = -ry - rz;
    } else if (dy > dz) {
      ry = -rx - rz;
    } else {
      rz = -rx - ry;
    }

    points.push({ q: rx, r: rz });
  }
  return points;
}

export function _hasLineOfSight(fromQ, fromR, toQ, toR, getObstacleMobilityAtHex, movementSystem = null) {
  if (fromQ === toQ && fromR === toR) {
    return true;
  }

  const line = _getAxialLine(fromQ, fromR, toQ, toR, movementSystem);
  for (let i = 1; i < line.length - 1; i += 1) {
    const { q, r } = line[i];
    const obstacle = getObstacleMobilityAtHex(q, r);
    if (obstacle && !obstacle.passable) {
      return false;
    }
  }

  return true;
}

export function _getHostileTargets(actor, entityManager, movementSystem = null, hasLineOfSight = () => true) {
  const actorCombat = actor?.getComponent?.('CombatComponent');
  const actorPos = actor?.getComponent?.('PositionComponent');
  if (!actorCombat || !actorPos || !entityManager?.getEntitiesWith) {
    return [];
  }

  const candidates = entityManager.getEntitiesWith('CombatComponent', 'StatsComponent', 'PositionComponent');
  const hostileTargets = [];

  candidates.forEach((candidate) => {
    if (candidate.id === actor.id) {
      return;
    }

    const targetCombat = candidate.getComponent('CombatComponent');
    const targetStats = candidate.getComponent('StatsComponent');
    const targetPos = candidate.getComponent('PositionComponent');
    if (!targetCombat || !targetPos) {
      return;
    }

    const alive = typeof targetStats?.isAlive === 'function'
      ? targetStats.isAlive()
      : Number(targetStats?.currentHp ?? 1) > 0;
    if (!alive) {
      return;
    }

    const actorTeam = String(actorCombat?.team || '').toLowerCase();
    const targetTeam = String(targetCombat?.team || '').toLowerCase();
    const hostile = typeof actorCombat?.isHostileTo === 'function'
      ? actorCombat.isHostileTo(targetCombat)
      : (actorTeam && targetTeam && actorTeam !== targetTeam);
    if (!hostile) {
      return;
    }

    const distance = movementSystem?.hexDistance
      ? movementSystem.hexDistance(actorPos.q, actorPos.r, targetPos.q, targetPos.r)
      : Math.max(Math.abs(actorPos.q - targetPos.q), Math.abs(actorPos.r - targetPos.r), Math.abs((actorPos.q + actorPos.r) - (targetPos.q + targetPos.r)));
    if (!hasLineOfSight(actorPos.q, actorPos.r, targetPos.q, targetPos.r)) {
      return;
    }
    hostileTargets.push({ target: candidate, distance });
  });

  hostileTargets.sort((left, right) => left.distance - right.distance);
  return hostileTargets;
}

export function _normalizeAuthoritativeNavigationCapability(exit, activeRoomId) {
  const targetRoomId = String(exit?.target_room_id || '').trim();
  const targetRoomName = String(exit?.target_room_name || exit?.to_room_name || '').trim();
  const destinationType = String(exit?.destination_type || 'room').trim().toLowerCase() || 'room';
  const destinationId = String(exit?.destination_id || (destinationType === 'room' ? targetRoomId : '')).trim();
  const type = String(exit?.type || 'passage').trim() || 'passage';
  const distance = Number.isFinite(Number(exit?.distance)) ? Math.max(0, Math.trunc(Number(exit.distance))) : 0;
  const blockedReason = String(exit?.blocked_reason || '').trim() || null;
  const isDiscovered = Object.prototype.hasOwnProperty.call(exit || {}, 'is_discovered') ? Boolean(exit.is_discovered) : true;
  const isPassable = Object.prototype.hasOwnProperty.call(exit || {}, 'is_passable') ? Boolean(exit.is_passable) : true;
  const available = typeof exit?.available === 'boolean'
    ? exit.available
    : (blockedReason === null && Boolean(targetRoomId) && isDiscovered && isPassable);
  const originHex = _normalizeHexPayload(exit?.origin_hex) || _normalizeHexPayload(_getConnectionHex(exit, 'from'));
  const targetHex = _normalizeHexPayload(exit?.target_hex) || _normalizeHexPayload(_getConnectionHex(exit, 'to'));

  return {
    connection_id: String(exit?.connection_id || `${activeRoomId || 'unknown'}__${targetRoomId || 'unknown'}`),
    origin_room_id: String(exit?.origin_room_id || activeRoomId || '').trim(),
    target_room_id: targetRoomId,
    target_room_name: targetRoomName,
    destination_type: destinationType,
    destination_id: destinationId,
    type,
    available,
    blocked_reason: blockedReason || (available ? null : 'blocked'),
    is_discovered: isDiscovered,
    is_passable: isPassable,
    bidirectional: Object.prototype.hasOwnProperty.call(exit || {}, 'bidirectional')
      ? Boolean(exit.bidirectional)
      : type !== 'one_way',
    requires_interaction: Object.prototype.hasOwnProperty.call(exit || {}, 'requires_interaction')
      ? Boolean(exit.requires_interaction)
      : !isPassable,
    distance,
    quest_reference: exit?.quest_reference === true,
    quest_ids: Array.isArray(exit?.quest_ids)
      ? exit.quest_ids.map((value) => String(value || '').trim()).filter(Boolean)
      : [],
    origin_hex: originHex,
    target_hex: targetHex,
    connection: exit,
  };
}

export function _normalizeHexPayload(hex) {
  if (!hex || typeof hex !== 'object') {
    return null;
  }
  const q = Number(hex.q);
  const r = Number(hex.r);
  if (!Number.isFinite(q) || !Number.isFinite(r)) {
    return null;
  }
  return { q, r };
}

export function _findLaunchPlayerEntity(entityManager, launchContext = {}, launchCharacterId = 0) {
  if (!entityManager?.getEntitiesWith) {
    return null;
  }

  const entities = entityManager.getEntitiesWith('PositionComponent');
  if (!Array.isArray(entities) || !entities.length) {
    return null;
  }

  const playerEntities = entities.filter((entity) => {
    const combat = entity.getComponent?.('CombatComponent');
    if (combat) {
      return typeof combat.isPlayerTeam === 'function'
        ? combat.isPlayerTeam()
        : String(combat?.team || '').toLowerCase() === 'player';
    }

    const entityType = String(entity?.dcEntityType || entity?.dcStatePayload?.entity_type || '').toLowerCase();
    const metadata = entity?.dcStatePayload?.state?.metadata || entity?.dcStatePayload?.metadata || {};
    const metadataTeam = String(metadata.team || '').toLowerCase();
    const campaignCharacterId = Number(metadata.campaign_character_id || metadata.character_id || entity?.dcCharacterId || 0);

    return entityType === 'player_character'
      || metadataTeam === 'player'
      || (launchCharacterId > 0 && campaignCharacterId === launchCharacterId);
  });

  if (!playerEntities.length) {
    return null;
  }

  const preferredPlayerEntities = playerEntities.filter((entity) => {
    const entityRef = String(
      entity?.dcEntityRef
      || entity?.dcEntityInstanceId
      || entity?.instanceId
      || entity?.id
      || ''
    ).trim().toLowerCase();
    const entityType = String(entity?.dcEntityType || entity?.dcStatePayload?.entity_type || '').trim().toLowerCase();
    const metadata = entity?.dcStatePayload?.state?.metadata || entity?.dcStatePayload?.metadata || {};
    const followerKind = String(metadata?.follower_kind || metadata?.bond_contract?.follower_kind || '').trim().toLowerCase();
    const roleKind = String(
      metadata?.role
      || metadata?.bond_contract?.role
      || entity?.dcStatePayload?.role
      || entity?.dcStatePayload?.state?.role
      || ''
    ).trim().toLowerCase();
    const isFollowerLike = entityRef.startsWith('familiar-')
      || entityRef.startsWith('companion-')
      || entityRef.startsWith('follower-')
      || followerKind === 'familiar'
      || followerKind === 'companion'
      || followerKind === 'follower'
      || roleKind.includes('familiar')
      || roleKind.includes('companion')
      || roleKind.includes('follower');
    if (isFollowerLike) {
      return false;
    }
    const campaignCharacterId = Number(metadata.campaign_character_id || metadata.character_id || entity?.dcCharacterId || 0);
    return entityType === 'player_character'
      || (launchCharacterId > 0 && campaignCharacterId === launchCharacterId);
  });
  const launchCandidates = preferredPlayerEntities.length ? preferredPlayerEntities : playerEntities;

  const startQ = Number.isFinite(Number(launchContext?.start_q)) ? Number(launchContext.start_q) : 0;
  const startR = Number.isFinite(Number(launchContext?.start_r)) ? Number(launchContext.start_r) : 0;
  const onStartHex = launchCandidates.find((entity) => {
    const pos = entity.getComponent?.('PositionComponent');
    return pos && pos.q === startQ && pos.r === startR;
  });

  return onStartHex || launchCandidates[0] || null;
}

/**
 * Derive a stable sprite key for an actor whose only art is a portrait URL.
 *
 * Map tokens render from `PIXI.utils.TextureCache[spriteKey]`, while chat renders
 * straight from `metadata.portrait_url`. Actors without an authored `sprite_id`
 * therefore had no key to render under and fell back to colored circles even
 * though the server had already resolved a portrait for them. Deriving a key
 * here lets the existing preload → texture-cache → token pipeline supply the
 * same art the chat tab uses.
 */
export function _buildActorPortraitSpriteId(entityType, metadata = {}, instanceId = '') {
  const portraitUrl = String(metadata?.portrait_url || metadata?.portrait || '').trim();
  if (!portraitUrl) {
    return '';
  }
  const actorType = String(entityType || '').trim().toLowerCase();
  if (!['player_character', 'npc', 'creature'].includes(actorType)) {
    return '';
  }
  const ref = String(instanceId || '').trim();
  return ref ? `portrait__${ref}` : '';
}

export function _preloadSpriteUrls(spriteService, blueprints = [], objectDefinitions = {}, launchCharacter = null) {
  if (!spriteService?.preloadUrl) {
    return;
  }

  blueprints.forEach((blueprint) => {
    const spriteId = String(blueprint?.render?.spriteKey || '').trim();
    if (!spriteId) {
      return;
    }

    const definition = objectDefinitions?.[blueprint?.contentId] || {};
    const url = String(
      // A server-resolved actor portrait is the same art the chat tab renders,
      // so it takes precedence over generic object-definition artwork.
      blueprint?.render?.portraitUrl
      || definition?.visual?.image_url
      || definition?.visual?.portrait_url
      || definition?.visual?.url
      || '',
    ).trim();
    if (url) {
      spriteService.preloadUrl(spriteId, url);
    }
  });

  const portraitSpriteId = String(
    launchCharacter?.portrait?.sprite_id
    || launchCharacter?.portrait_sprite_id
    || launchCharacter?.portraitSpriteId
    || '',
  ).trim();
  const portraitUrl = String(
    launchCharacter?.portrait?.url
    || launchCharacter?.portrait_url
    || launchCharacter?.portraitUrl
    || '',
  ).trim();
  if (portraitSpriteId && portraitUrl) {
    spriteService.preloadUrl(portraitSpriteId, portraitUrl);
  }
}

/**
 * Flatten phase-based objectives from a quest entry (server shape) into a
 * flat array that QuestPanel can render directly.
 *
 * Server shape: quest.objective_states = [{ phase_id, objectives: [{label, status, ...}] }]
 *
 * @param {object} quest
 * @returns {Array<{label: string, status: string, children?: Array}>}
 */
export function _flattenQuestObjectives(quest) {
  const phases = quest.objective_states ?? quest.generated_objectives ?? [];
  if (!Array.isArray(phases)) return [];
  return phases.flatMap((phase) => Array.isArray(phase.objectives) ? phase.objectives : []);
}

export function _isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

export function _hasMeaningfulValue(value) {
  if (value === null || value === undefined) return false;
  if (typeof value === 'string') return value.trim() !== '';
  if (Array.isArray(value)) return value.length > 0;
  if (typeof value === 'object') return Object.keys(value).length > 0;
  return true;
}

export function _mergeRoomMetadata(visualRoom = {}, apiRoom = {}, roomId = '') {
  const merged = {
    ...(_isPlainObject(visualRoom) ? visualRoom : {}),
    ...(_isPlainObject(apiRoom) ? apiRoom : {}),
    room_id: apiRoom?.room_id || visualRoom?.room_id || roomId,
  };

  ['name', 'description', 'room_type', 'size_category', 'terrain', 'lighting'].forEach((key) => {
    if (!_hasMeaningfulValue(apiRoom?.[key]) && _hasMeaningfulValue(visualRoom?.[key])) {
      merged[key] = visualRoom[key];
    }
  });

  if (typeof merged.lighting !== 'string') {
    delete merged.lighting;
  }
  if (!_isPlainObject(merged.terrain) || typeof merged.terrain.type !== 'string') {
    delete merged.terrain;
  }

  if (!_hasMeaningfulValue(merged.subtitle)) {
    merged.subtitle = _buildRoomSubtitle(merged);
  }

  return merged;
}

export function _buildRoomSubtitle(room = {}) {
  if (!_isPlainObject(room)) {
    return '';
  }

  const terrainValue = typeof room?.terrain?.type === 'string' ? room.terrain.type : '';
  const terrainLabel = String(terrainValue || '').replace(/_/g, ' ').trim();
  const lightingValue = typeof room?.lighting === 'string' ? room.lighting : '';
  const lightingLabel = lightingValue && lightingValue !== 'normal'
    ? `Lighting: ${String(lightingValue).replace(/_/g, ' ')}`
    : '';
  const sizeLabel = room?.size_category && room.size_category !== 'medium'
    ? String(room.size_category).replace(/_/g, ' ')
    : '';

  return [terrainLabel, lightingLabel, sizeLabel].filter(Boolean).join(' | ');
}

/**
 * Build a connections array for the navigate sub-panel from mapVisualState topology.
 * Returns connections that originate FROM the given roomId (or are passable to/from it).
 *
 * @param {string} roomId
 * @param {object} mapVisualState
 * @returns {Array<{room_id, room_name, connection_id, direction?}>}
 */
export function _buildRoomConnections(roomId, mapVisualState) {
  const topology = mapVisualState?.topology ?? {};
  const rooms = topology.rooms ?? {};
  const room = rooms?.[roomId] ?? null;
  const exits = Array.isArray(room?.exits) ? room.exits : [];

  const result = [];
  const seen = new Set();

  exits.forEach((exit) => {
    if (!exit?.is_passable) return;

    const targetRoomId = String(exit?.target_room_id || '').trim();
    const connectionId = String(exit?.connection_id || '').trim();
    if (!targetRoomId || !connectionId || seen.has(connectionId)) return;

    seen.add(connectionId);
    result.push({
      connection_id: connectionId,
      room_id:       targetRoomId,
      room_name:     rooms[targetRoomId]?.name ?? targetRoomId,
      type:          exit.type ?? 'open_passage',
    });
  });

  return result;
}

export function _buildRenderableEntityBlueprints(dungeonData = {}, activeRoomId = '', launchCharacter = {}, mapVisualState = {}) {
  const roomId = String(activeRoomId || '').trim();
  if (!roomId) {
    return [];
  }

  const objectDefinitions = _getPresentationObjectDefinitions(mapVisualState, dungeonData);
  const visualOccupants = _buildVisualOccupantIndex(mapVisualState);
  const blueprints = [];
  const seen = new Set();
  const projectedEntitySignatures = new Set();
  const logicalActorSignatures = new Set();
  const launchCharacterId = Number(
    launchCharacter?.id
    || launchCharacter?.character_id
    || 0,
  ) || null;
  const launchCharacterName = String(
    launchCharacter?.basicInfo?.name
    || launchCharacter?.name
    || launchCharacter?.character_name
    || '',
  ).trim();
  const normalizedLaunchCharacterName = launchCharacterName.toLowerCase();
  const launchPortraitSpriteId = String(
    launchCharacter?.portrait?.sprite_id
    || launchCharacter?.portrait_sprite_id
    || launchCharacter?.portraitSpriteId
    || '',
  ).trim();

  const entities = Array.isArray(dungeonData?.entities) ? dungeonData.entities : [];
  entities.forEach((entity) => {
    const placement = _isPlainObject(entity?.placement) ? entity.placement : {};
    const hex = _isPlainObject(placement?.hex) ? placement.hex : {};
    const entityRoomId = String(placement?.room_id || '').trim();
    const q = Number(hex?.q);
    const r = Number(hex?.r);
    if (entityRoomId !== roomId || !Number.isFinite(q) || !Number.isFinite(r)) {
      return;
    }

    const metadata = _isPlainObject(entity?.state?.metadata) ? entity.state.metadata : {};
    const rawType = String(entity?.entity_type || entity?.entityType || '').trim().toLowerCase();
    const entityType = _normalizeRenderableEntityType(rawType, entity?.entity_ref?.content_type, metadata);
    const contentId = String(entity?.entity_ref?.content_id || '').trim();
    const definition = contentId ? (objectDefinitions[contentId] || {}) : {};
    const instanceId = String(entity?.entity_instance_id || entity?.instance_id || entity?.id || '').trim()
      || `payload-entity:${roomId}:${q}:${r}:${contentId || rawType || 'unknown'}`;
    const visual = _resolveVisualOccupant(visualOccupants, instanceId, contentId, roomId, q, r);
    const entityCharacterId = Number(metadata?.character_id || entity?.character_id || 0) || null;
    const isLaunchPlayerEntity = entityType === 'player_character'
      || Boolean(entityCharacterId && launchCharacterId && entityCharacterId === launchCharacterId);
    const name = String(
      metadata?.display_name
      || metadata?.name
      || entity?.display_name
      || (isLaunchPlayerEntity ? launchCharacterName : '')
      || definition?.label
      || contentId
      || rawType
      || 'entity',
    ).trim();
    const team = _normalizeRenderableEntityTeam(
      metadata?.team
      || visual?.presentation?.badge
      || (entityCharacterId && launchCharacterId && entityCharacterId === launchCharacterId ? 'player' : ''),
    );
    const hidden = visual?.visible === false || entity?.state?.hidden === true;
    const logicalActorKey = _buildLogicalActorIdentityKey(rawType, metadata, instanceId, roomId);
    const normalizedConditions = _normalizeRenderableConditions(
      Array.isArray(metadata?.conditions)
        ? metadata.conditions
        : (Array.isArray(entity?.state?.conditions) ? entity.state.conditions : []),
    );
    const isDefeated = _resolveRenderableIsIncapacitated(
      Boolean(entity?.state?.is_defeated ?? metadata?.is_defeated),
      normalizedConditions,
    );

    const blueprint = {
      key: _buildRenderableEntityKey(instanceId, contentId, q, r),
      sourceKind: 'entity',
      roomId,
      q,
      r,
      instanceId,
      entityRef: instanceId,
      entityType,
      contentId,
      characterId: entityCharacterId,
      name: name !== '' ? name : 'entity',
      description: String(metadata?.description || definition?.description || '').trim(),
      hidden,
      combatCapable: entityType === 'player_character' || entityType === 'npc' || entityType === 'creature',
      isDefeated,
      conditions: normalizedConditions,
      team,
      actionsPerTurn: Number(metadata?.actions_per_turn || 3) || 3,
      initiativeBonus: Number(metadata?.initiative_bonus || 0) || 0,
      attackBonus: Number(metadata?.attack_bonus || 0) || 0,
      stats: {
        maxHp: Number(metadata?.stats?.maxHp ?? metadata?.stats?.max_hp ?? metadata?.max_hp ?? 10) || 10,
        currentHp: Number(metadata?.stats?.currentHp ?? metadata?.stats?.current_hp ?? metadata?.hp ?? metadata?.max_hp ?? 10) || 10,
        ac: Number(metadata?.stats?.ac ?? metadata?.armor_class ?? 10) || 10,
        perception: Number(metadata?.stats?.perception ?? metadata?.perception ?? 0) || 0,
        speed: Number(metadata?.movement_speed ?? metadata?.stats?.speed ?? 30) || 30,
      },
      render: {
        spriteKey: String(
          metadata?.sprite_id
          || definition?.visual?.sprite_id
          || visual?.presentation?.sprite_id
          || (isLaunchPlayerEntity ? launchPortraitSpriteId : '')
          || _buildActorPortraitSpriteId(entityType, metadata, instanceId)
          || '',
        ).trim() || null,
        portraitUrl: String(
          metadata?.portrait_url
          || metadata?.portrait
          || '',
        ).trim() || null,
        scale: Number(metadata?.render_scale ?? (entityType === 'item' ? 0.55 : 1)) || (entityType === 'item' ? 0.55 : 1),
        orientation: String(placement?.orientation || metadata?.orientation || definition?.visual?.orientation || 'n').trim().toLowerCase() || 'n',
        objectCategory: String(definition?.category || metadata?.object_category || '').trim() || null,
        objectColor: definition?.visual?.color || metadata?.object_color || visual?.presentation?.color || null,
      },
      state: _isPlainObject(entity?.state) ? entity.state : {},
      source: entity,
    };

    if (!hidden && !seen.has(blueprint.key) && (!logicalActorKey || !logicalActorSignatures.has(logicalActorKey))) {
      seen.add(blueprint.key);
      if (logicalActorKey) {
        logicalActorSignatures.add(logicalActorKey);
      }
      if (contentId) {
        projectedEntitySignatures.add(_buildRenderableProjectionKey(contentId, roomId, q, r));
      }
      blueprints.push(blueprint);
    }
  });

  const activeRoom = mapVisualState?.topology?.rooms?.[roomId];
  const roomHexes = Array.isArray(activeRoom?.hexes) ? activeRoom.hexes : [];
  roomHexes.forEach((hex) => {
    const q = Number(hex?.q);
    const r = Number(hex?.r);
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      return;
    }

    const objects = Array.isArray(hex?.objects) ? hex.objects : [];
    objects.forEach((object, objectIndex) => {
      const contentId = String(object?.object_id || object?.id || '').trim();
      if (!contentId) {
        return;
      }

      const definition = objectDefinitions[contentId] || {};
      const entityType = _normalizeRenderableEntityType('', object?.category, object);
      const projectionKey = _buildRenderableProjectionKey(contentId, roomId, q, r);
      if (projectedEntitySignatures.has(projectionKey)) {
        return;
      }

      const instanceId = String(object?.object_instance_id || '').trim()
        || `room-object:${roomId}:${q}:${r}:${contentId}:${objectIndex}`;
      const key = _buildRenderableEntityKey(instanceId, roomId, q, r);
      if (seen.has(key)) {
        return;
      }

      const blueprint = {
        key,
        sourceKind: 'hex-object',
        roomId,
        q,
        r,
        instanceId,
        entityRef: contentId,
        entityType,
        contentId,
        characterId: null,
        name: String(object?.label || object?.name || definition?.label || contentId).trim() || contentId,
        description: String(object?.description || definition?.description || '').trim(),
        hidden: false,
        combatCapable: false,
        team: 'neutral',
        actionsPerTurn: 0,
        initiativeBonus: 0,
        attackBonus: 0,
        stats: {
          maxHp: 10,
          currentHp: 10,
          ac: 10,
          perception: 0,
          speed: 0,
        },
        render: {
          spriteKey: String(object?.visual?.sprite_id || definition?.visual?.sprite_id || '').trim() || null,
          scale: Number(entityType === 'item' ? 0.55 : 0.95) || 1,
          orientation: String(object?.orientation || definition?.visual?.orientation || 'n').trim().toLowerCase() || 'n',
          objectCategory: String(object?.category || definition?.category || '').trim() || null,
          objectColor: object?.visual?.color || definition?.visual?.color || null,
        },
        state: {
          active: true,
          metadata: {
            passable: object?.passable,
            movable: object?.movable,
            collectible: object?.collectible,
            blocks_movement: object?.blocks_movement,
            stackable: typeof object?.stackable === 'boolean' ? object.stackable : Boolean(definition?.stackable),
          },
        },
        source: object,
      };

      seen.add(key);
      blueprints.push(blueprint);
    });
  });

  _getVisualOccupants(mapVisualState)
    .filter((occupant) => {
      if (!_isVisualOccupantVisible(occupant)) {
        return false;
      }
      return String(occupant?.room_id || '').trim() === roomId;
    })
    .forEach((occupant, occupantIndex) => {
      const q = Number(occupant?.placement?.q);
      const r = Number(occupant?.placement?.r);
      if (!Number.isFinite(q) || !Number.isFinite(r)) {
        return;
      }

      const contentId = String(occupant?.content_id || '').trim();
      const occupantId = String(occupant?.occupant_id || '').trim();
      const projectionKey = contentId ? _buildRenderableProjectionKey(contentId, roomId, q, r) : '';
      if ((projectionKey && projectedEntitySignatures.has(projectionKey)) || (occupantId && seen.has(_buildRenderableEntityKey(occupantId, roomId, q, r)))) {
        return;
      }

      const definition = contentId ? (objectDefinitions[contentId] || {}) : {};
      const occupantType = String(occupant?.occupant_type || '').trim().toLowerCase();
      const entityType = _normalizeRenderableEntityType(occupantType, definition?.category, occupant);
      const isPartyOccupant = occupant?.is_party === true || occupantType === 'player_character' || occupantType === 'player' || occupantType === 'pc';
      const occupantCharacterId = Number(occupant?.character_id || occupant?.state?.character_id || 0) || null;
      const occupantLabel = String(occupant?.label || '').trim();
      const isLaunchPlayerOccupant = isPartyOccupant && (
        Boolean(occupantCharacterId && launchCharacterId && occupantCharacterId === launchCharacterId)
        || Boolean(occupantLabel && normalizedLaunchCharacterName && occupantLabel.toLowerCase() === normalizedLaunchCharacterName)
      );
      const instanceId = occupantId || `visual-occupant:${roomId}:${q}:${r}:${contentId || entityType || occupantIndex}`;
      const occupantMetadata = _isPlainObject(occupant?.state?.metadata) ? occupant.state.metadata : {};
      const key = _buildRenderableEntityKey(instanceId, roomId, q, r);
      const logicalActorKey = _buildLogicalActorIdentityKey(occupantType, occupantMetadata, instanceId, roomId, Boolean(isPartyOccupant));
      if (seen.has(key) || (logicalActorKey && logicalActorSignatures.has(logicalActorKey))) {
        return;
      }

      const team = _normalizeRenderableEntityTeam(
        isPartyOccupant
          ? 'player'
          : (occupant?.presentation?.badge || occupant?.team || occupant?.state?.team || '')
      );
      const combatCapable = entityType === 'player_character' || entityType === 'npc' || entityType === 'creature';
      const occupantNormalizedConditions = _normalizeRenderableConditions(
        Array.isArray(occupantMetadata?.conditions)
          ? occupantMetadata.conditions
          : (Array.isArray(occupant?.state?.conditions) ? occupant.state.conditions : []),
      );
      const occupantIsDefeated = _resolveRenderableIsIncapacitated(
        Boolean(occupant?.state?.is_defeated ?? occupantMetadata?.is_defeated),
        occupantNormalizedConditions,
      );
      const blueprint = {
        key,
        sourceKind: 'visual-occupant',
        roomId,
        q,
        r,
        instanceId,
        entityRef: occupantId || contentId || instanceId,
        entityType,
        contentId,
        characterId: occupantCharacterId,
        name: String(occupantLabel || (isLaunchPlayerOccupant ? launchCharacterName : '') || definition?.label || contentId || occupantType || 'occupant').trim(),
        description: String(occupant?.presentation?.summary || definition?.description || '').trim(),
        hidden: false,
        combatCapable,
        isDefeated: occupantIsDefeated,
        conditions: occupantNormalizedConditions,
        team,
        actionsPerTurn: Number(occupant?.state?.actions_per_turn || 3) || 3,
        initiativeBonus: Number(occupant?.state?.initiative_bonus || 0) || 0,
        attackBonus: Number(occupant?.state?.attack_bonus || 0) || 0,
        stats: {
          maxHp: Number(occupant?.state?.max_hp ?? occupant?.state?.stats?.max_hp ?? occupant?.state?.stats?.maxHp ?? 10) || 10,
          currentHp: Number(occupant?.state?.hp ?? occupant?.state?.current_hp ?? occupant?.state?.stats?.current_hp ?? occupant?.state?.stats?.currentHp ?? occupant?.state?.max_hp ?? 10) || 10,
          ac: Number(occupant?.state?.armor_class ?? occupant?.state?.stats?.ac ?? 10) || 10,
          perception: Number(occupant?.state?.perception ?? occupant?.state?.stats?.perception ?? 0) || 0,
          speed: Number(occupant?.state?.movement_speed ?? occupant?.state?.stats?.speed ?? 30) || 30,
        },
        render: {
          spriteKey: String(
            occupant?.presentation?.sprite_id
            || definition?.visual?.sprite_id
            || (isLaunchPlayerOccupant ? launchPortraitSpriteId : '')
            || '',
          ).trim() || null,
          scale: Number(occupant?.presentation?.render_scale ?? (entityType === 'item' ? 0.55 : 1)) || (entityType === 'item' ? 0.55 : 1),
          orientation: String(occupant?.placement?.orientation || occupant?.presentation?.orientation || definition?.visual?.orientation || 'n').trim().toLowerCase() || 'n',
          objectCategory: String(definition?.category || occupant?.category || '').trim() || null,
          objectColor: occupant?.presentation?.color || definition?.visual?.color || null,
        },
        state: _isPlainObject(occupant?.state) ? occupant.state : {},
        source: occupant,
      };

      seen.add(key);
      if (logicalActorKey) {
        logicalActorSignatures.add(logicalActorKey);
      }
      if (projectionKey) {
        projectedEntitySignatures.add(projectionKey);
      }
      blueprints.push(blueprint);
    });

  return blueprints;
}

export function _buildVisualOccupantIndex(mapVisualState = {}) {
  const index = new Map();
  const buckets = mapVisualState?.occupants || {};
  const occupants = [
    ...(Array.isArray(buckets.party) ? buckets.party : []),
    ...(Array.isArray(buckets.entities) ? buckets.entities : []),
  ];

  occupants.forEach((occupant) => {
    const occupantId = String(occupant?.occupant_id || '').trim();
    const contentId = String(occupant?.content_id || '').trim();
    const roomId = String(occupant?.room_id || '').trim();
    const q = Number(occupant?.placement?.q);
    const r = Number(occupant?.placement?.r);
    if (occupantId) {
      index.set(occupantId, occupant);
    }
    if (contentId && roomId && Number.isFinite(q) && Number.isFinite(r)) {
      index.set(_buildRenderableProjectionKey(contentId, roomId, q, r), occupant);
    }
    if (contentId && roomId && !index.has(`${roomId}:${contentId}`)) {
      index.set(`${roomId}:${contentId}`, occupant);
    }
  });

  return index;
}

export function _resolveVisualOccupant(visualOccupants, instanceId = '', contentId = '', roomId = '', q = 0, r = 0) {
  if (!(visualOccupants instanceof Map)) {
    return null;
  }

  const normalizedInstanceId = String(instanceId || '').trim();
  if (normalizedInstanceId && visualOccupants.has(normalizedInstanceId)) {
    return visualOccupants.get(normalizedInstanceId) || null;
  }

  const normalizedContentId = String(contentId || '').trim();
  const normalizedRoomId = String(roomId || '').trim();
  if (!normalizedContentId || !normalizedRoomId) {
    return null;
  }

  const exactKey = _buildRenderableProjectionKey(normalizedContentId, normalizedRoomId, q, r);
  if (visualOccupants.has(exactKey)) {
    return visualOccupants.get(exactKey) || null;
  }

  const roomKey = `${normalizedRoomId}:${normalizedContentId}`;
  if (visualOccupants.has(roomKey)) {
    return visualOccupants.get(roomKey) || null;
  }

  return null;
}

export function _normalizeRenderableEntityType(rawType = '', fallbackCategory = '', metadata = {}) {
  const normalizedType = String(rawType || '').trim().toLowerCase();
  if (normalizedType === 'player_character' || normalizedType === 'player' || normalizedType === 'pc') {
    return 'player_character';
  }
  if (normalizedType === 'npc') {
    return 'npc';
  }
  if (normalizedType === 'creature') {
    return 'creature';
  }
  if (normalizedType === 'item' || normalizedType === 'treasure') {
    return 'item';
  }
  if (normalizedType === 'obstacle' || normalizedType === 'trap' || normalizedType === 'hazard') {
    return normalizedType;
  }

  const category = String(fallbackCategory || metadata?.category || metadata?.type || '').trim().toLowerCase();
  if (metadata?.is_party === true || metadata?.party_member === true || metadata?.isPlayer === true) {
    return 'player_character';
  }
  if (
    category.includes('item')
    || category.includes('loot')
    || category.includes('collect')
    || category.includes('quest_item')
    || metadata?.collectible === true
  ) {
    return 'item';
  }

  return 'obstacle';
}

export function _normalizeRenderableEntityTeam(rawTeam = '') {
  const normalized = String(rawTeam || '').trim().toLowerCase();
  if (normalized === 'player' || normalized === 'ally' || normalized === 'enemy' || normalized === 'neutral') {
    return normalized;
  }
  return 'neutral';
}

/** Condition type codes treated as "incapacitated" for the map token badge. */
const RENDERABLE_INCAPACITATED_CONDITION_TYPES = new Set(['dead', 'unconscious', 'dying']);

/**
 * Normalize a raw conditions array (from combat participant sync or
 * character_data) into the {condition_type, name, value, source} shape
 * used consistently by CharacterPanel.resolveEncounterConditionsForEntity()
 * and the condition tooltip renderers, so the map tab's hover list matches
 * the same condition names/casing shown elsewhere in the UI.
 */
export function _normalizeRenderableConditions(rawConditions) {
  if (!Array.isArray(rawConditions) || rawConditions.length === 0) {
    return [];
  }

  return rawConditions
    .map((condition) => {
      if (!condition || typeof condition !== 'object') {
        const label = String(condition || '').trim();
        if (!label) {
          return null;
        }
        return { condition_type: label.toLowerCase().replace(/\s+/g, '_'), name: label };
      }

      const rawType = String(
        condition.condition_type || condition.type || condition.id || condition.name || '',
      ).trim();
      if (!rawType) {
        return null;
      }

      return {
        condition_type: rawType.toLowerCase().replace(/\s+/g, '_'),
        name: String(condition.name || rawType).trim() || rawType,
        value: Number.isFinite(Number(condition.value)) ? Number(condition.value) : null,
        source: String(condition.source || '').trim() || null,
      };
    })
    .filter(Boolean);
}

/**
 * Determine whether a renderable entity should show the map's
 * unconscious/dead token indicator: either an explicit defeated flag from
 * combat participant sync, or an incapacitating condition (dead/unconscious/
 * dying) in its normalized condition list.
 */
export function _resolveRenderableIsIncapacitated(explicitDefeated, normalizedConditions) {
  if (explicitDefeated === true) {
    return true;
  }
  return Array.isArray(normalizedConditions)
    && normalizedConditions.some((condition) => RENDERABLE_INCAPACITATED_CONDITION_TYPES.has(condition?.condition_type));
}

export function _buildRenderableEntityKey(instanceId = '', roomId = '', q = 0, r = 0) {
  const stableId = String(instanceId || '').trim() || 'entity';
  const stableRoomId = String(roomId || '').trim() || 'room';
  return `${stableRoomId}:${stableId}:${Number(q)}:${Number(r)}`;
}

export function _buildRenderableProjectionKey(contentId = '', roomId = '', q = 0, r = 0) {
  const stableContentId = String(contentId || '').trim();
  const stableRoomId = String(roomId || '').trim() || 'room';
  if (stableContentId === '') {
    return '';
  }
  return `${stableRoomId}:${stableContentId}:${Number(q)}:${Number(r)}`;
}

export function _buildLogicalActorIdentityKey(rawType = '', metadata = {}, instanceId = '', roomId = '', isPartyMember = false) {
  const stableRoomId = String(roomId || '').trim();
  if (!stableRoomId || !_isPlainObject(metadata)) {
    return '';
  }

  const entityType = String(rawType || '').trim().toLowerCase();
  const team = _normalizeRenderableEntityTeam(metadata?.team || '');
  const followerKind = String(metadata?.follower_kind || metadata?.bond_contract?.follower_kind || '').trim().toLowerCase();
  const sourceCharacterId = Number(metadata?.source_character_id || 0) || 0;
  const campaignCharacterId = Number(metadata?.campaign_character_id || 0) || 0;
  const characterId = Number(metadata?.character_id || 0) || 0;
  const ownerSourceCharacterId = Number(metadata?.owner_source_character_id || metadata?.bond_contract?.owner_source_character_id || 0) || 0;
  const ownerCharacterId = Number(metadata?.owner_character_id || metadata?.bond_contract?.owner_character_id || 0) || 0;
  const followerSourceCharacterId = Number(metadata?.follower_source_character_id || 0) || 0;
  const runtimeEntityId = String(metadata?.runtime_entity_id || instanceId || '').trim();
  const isPlayerLike = Boolean(isPartyMember)
    || entityType === 'player_character'
    || entityType === 'player'
    || entityType === 'pc'
    || team === 'player';

  if (followerKind && followerSourceCharacterId > 0) {
    return `${stableRoomId}:follower-source:${followerSourceCharacterId}:${followerKind}`;
  }
  if (followerKind && ownerSourceCharacterId > 0) {
    return `${stableRoomId}:follower-owner-source:${ownerSourceCharacterId}:${followerKind}`;
  }
  if (followerKind && ownerCharacterId > 0) {
    return `${stableRoomId}:follower-owner:${ownerCharacterId}:${followerKind}`;
  }
  if (isPlayerLike && sourceCharacterId > 0) {
    return `${stableRoomId}:player-source:${sourceCharacterId}`;
  }
  if (campaignCharacterId > 0) {
    return `${stableRoomId}:campaign-character:${campaignCharacterId}`;
  }
  if (isPlayerLike && characterId > 0) {
    return `${stableRoomId}:player-character:${characterId}`;
  }
  if (runtimeEntityId) {
    return `${stableRoomId}:runtime:${runtimeEntityId}`;
  }

  return '';
}

export function _buildRuntimeBundleQueryForRoom(shell, roomId = '', options = {}) {
  const normalizedRoomId = String(roomId || '').trim();
  const campaignId = Number(shell?.resolveCampaignId?.() || shell?.launchContext?.campaign_id || 0) || 0;
  const characterId = Number(shell?.launchContext?.character_id || 0) || 0;
  const mapId = String(
    options?.mapId
    || shell?.launchContext?.map_id
    || shell?.dungeonData?.map_id
    || shell?.dungeonData?.dungeon_id
    || ''
  ).trim();
  const dungeonLevelId = String(
    options?.dungeonLevelId
    || shell?.launchContext?.dungeon_level_id
    || shell?.dungeonData?.level_id
    || ''
  ).trim();
  const nextRoomId = String(options?.nextRoomId || '').trim();
  const startQ = Number.isFinite(Number(options?.startQ))
    ? Number(options.startQ)
    : Number(shell?.launchContext?.start_q ?? 0);
  const startR = Number.isFinite(Number(options?.startR))
    ? Number(options.startR)
    : Number(shell?.launchContext?.start_r ?? 0);

  const query = {
    campaign_id: campaignId,
    start_q: Number.isFinite(startQ) ? startQ : 0,
    start_r: Number.isFinite(startR) ? startR : 0,
  };
  if (characterId > 0) {
    query.character_id = characterId;
  }
  if (normalizedRoomId !== '') {
    query.room_id = normalizedRoomId;
  }
  if (mapId !== '') {
    query.map_id = mapId;
  }
  if (dungeonLevelId !== '') {
    query.dungeon_level_id = dungeonLevelId;
  }
  if (nextRoomId !== '') {
    query.next_room_id = nextRoomId;
  }
  return query;
}
