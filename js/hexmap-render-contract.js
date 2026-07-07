(function (global) {
  const DEFAULT_HEX_STYLE = {
    fillColor: 0x2d3748,
    fillAlpha: 1,
    lineColor: 0x4a5568,
    lineAlpha: 1,
    lineWidth: 1,
    showCoordinates: false,
  };

  const ENTITY_TYPE_COLORS = {
    player_character: 0x3b82f6,
    npc: 0x22c55e,
    creature: 0xef4444,
    item: 0xf59e0b,
    obstacle: 0x6b7280,
    hazard: 0xf97316,
  };

  function normalizeObjectCategory(raw) {
    return String(raw || '').trim().toLowerCase();
  }

  function classifyObjectCategory(category) {
    const value = normalizeObjectCategory(category);
    if (value === '') {
      return '';
    }
    if (value.includes('hazard') || value.includes('trap') || value.includes('lava')) {
      return 'hazard';
    }
    if (value.includes('item') || value.includes('loot') || value.includes('treasure') || value.includes('quest_item')) {
      return 'item';
    }
    if (
      value.includes('npc')
      || value.includes('entity')
      || value.includes('creature')
      || value.includes('actor')
      || value.includes('player')
      || value.includes('follower')
    ) {
      return 'actor';
    }
    if (value.includes('exit') || value.includes('door') || value.includes('portal') || value.includes('gateway')) {
      return 'exit';
    }
    if (value.includes('entry')) {
      return 'entry';
    }
    if (
      value.includes('obstacle')
      || value.includes('wall')
      || value.includes('barrier')
      || value.includes('barricade')
      || value.includes('cover')
      || value.includes('terrain')
      || value.includes('feature')
    ) {
      return 'obstacle';
    }
    if (value.includes('interact')) {
      return 'interactable';
    }
    return 'other';
  }

  function summarizeHexObjects(objects, roomHex) {
    const result = {
      total: 0,
      actors: 0,
      items: 0,
      hazards: 0,
      obstacles: 0,
      interactables: 0,
      exits: 0,
      entries: 0,
    };
    const list = Array.isArray(objects) ? objects : [];
    list.forEach((object) => {
      if (!object || typeof object !== 'object') {
        return;
      }
      result.total += 1;
      const category = classifyObjectCategory(object.category || object.type || object.object_type || '');
      if (category === 'actor') result.actors += 1;
      if (category === 'item') result.items += 1;
      if (category === 'hazard') result.hazards += 1;
      if (category === 'obstacle') result.obstacles += 1;
      if (category === 'interactable') result.interactables += 1;
      if (category === 'exit') result.exits += 1;
      if (category === 'entry') result.entries += 1;
    });

    const terrain = String(roomHex?.terrain_type || '').toLowerCase();
    if (terrain.includes('hazard') || terrain.includes('lava')) {
      result.hazards += 1;
    }
    if (roomHex?.is_entry === true) {
      result.entries += 1;
    }

    return result;
  }

  function resolveRoomHexStyle(roomHex, fallbackStyle) {
    const styleBase = fallbackStyle && typeof fallbackStyle === 'object' ? fallbackStyle : DEFAULT_HEX_STYLE;
    const summary = summarizeHexObjects(roomHex?.objects, roomHex);
    const terrain = String(roomHex?.terrain_type || '').toLowerCase();
    const lighting = String(roomHex?.lighting || '').toLowerCase();
    const objects = Array.isArray(roomHex?.objects) ? roomHex.objects : [];
    const blocked = objects.some((object) => object?.blocks_movement === true || object?.passable === false);
    const isWall = blocked || summary.obstacles > 0;
    const isDoor = summary.exits > 0;
    const isHazard = summary.hazards > 0;
    const isWater = terrain.includes('water');

    let style = styleBase;
    if (isWall) {
      style = {
        fillColor: 0x1f2937,
        fillAlpha: 0.95,
        lineColor: 0x94a3b8,
        lineAlpha: 1,
        lineWidth: 2,
        showCoordinates: false,
      };
    } else if (isDoor) {
      style = {
        fillColor: 0x3f3f46,
        fillAlpha: 0.95,
        lineColor: 0xfbbf24,
        lineAlpha: 1,
        lineWidth: 2,
        showCoordinates: false,
      };
    } else if (isHazard) {
      style = {
        fillColor: 0x7f1d1d,
        fillAlpha: 0.88,
        lineColor: 0xf97316,
        lineAlpha: 1,
        lineWidth: 1.5,
        showCoordinates: false,
      };
    } else if (isWater) {
      style = {
        fillColor: 0x1d4ed8,
        fillAlpha: 0.72,
        lineColor: 0x93c5fd,
        lineAlpha: 0.9,
        lineWidth: 1,
        showCoordinates: false,
      };
    } else if (lighting === 'dark') {
      style = {
        fillColor: 0x1e293b,
        fillAlpha: 0.9,
        lineColor: 0x475569,
        lineAlpha: 1,
        lineWidth: 1,
        showCoordinates: false,
      };
    } else if (lighting === 'dim') {
      style = {
        fillColor: 0x24324a,
        fillAlpha: 0.92,
        lineColor: 0x64748b,
        lineAlpha: 1,
        lineWidth: 1,
        showCoordinates: false,
      };
    } else if (summary.total > 0) {
      style = {
        fillColor: 0x334155,
        fillAlpha: 0.92,
        lineColor: 0x64748b,
        lineAlpha: 1,
        lineWidth: 1,
        showCoordinates: false,
      };
    }

    const isDiscovered = roomHex?.is_discovered !== false;
    const isVisible = roomHex?.is_visible !== false;
    if (!isDiscovered) {
      return {
        fillColor: 0x0b1020,
        fillAlpha: 0.98,
        lineColor: 0x0b1020,
        lineAlpha: 0.7,
        lineWidth: Math.max(0.5, style.lineWidth ?? 1),
        showCoordinates: false,
      };
    }
    if (!isVisible) {
      return {
        ...style,
        fillAlpha: Math.min(style.fillAlpha ?? 1, 0.55),
        lineAlpha: Math.min(style.lineAlpha ?? 1, 0.55),
        lineColor: 0x334155,
      };
    }

    return style;
  }

  function buildHexTooltipLines(roomHex, context = {}) {
    const q = Number(context.q);
    const r = Number(context.r);
    const roomId = String(context.roomId || '').trim();
    const fallbackHexId = roomId !== '' && Number.isFinite(q) && Number.isFinite(r)
      ? `${roomId}:${q}:${r}`
      : `${q}:${r}`;
    const hexId = String(context.hexId || roomHex?.hex_id || fallbackHexId);
    const terrainType = String(roomHex?.terrain_type || 'unknown');
    const lighting = String(roomHex?.lighting || 'unknown');
    const elevation = Number.isFinite(Number(roomHex?.elevation_ft)) ? Number(roomHex.elevation_ft) : 0;
    const summary = summarizeHexObjects(roomHex?.objects, roomHex);
    const flags = [
      roomHex?.is_entry === true ? 'entry' : null,
      roomHex?.is_visible === true ? 'visible' : null,
      roomHex?.is_discovered === true ? 'discovered' : null,
    ].filter(Boolean).join(', ');

    return [
      `hex: ${hexId}`,
      `q=${Number.isFinite(q) ? q : 0} r=${Number.isFinite(r) ? r : 0}${flags ? ` (${flags})` : ''}`,
      `terrain=${terrainType} | light=${lighting} | elev_ft=${elevation}`,
      `objects=${summary.total} | exits=${summary.exits} | actors=${summary.actors} | items=${summary.items} | hazards=${summary.hazards}`,
    ];
  }

  function toCount(value) {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? Math.max(0, Math.round(numeric)) : 0;
  }

  function summarizeRoomContract(contract = {}) {
    const contentBucketCounts = contract && typeof contract.content_bucket_counts === 'object' && !Array.isArray(contract.content_bucket_counts)
      ? contract.content_bucket_counts
      : {};
    const hexObjectCounts = contract && typeof contract.hex_object_counts === 'object' && !Array.isArray(contract.hex_object_counts)
      ? contract.hex_object_counts
      : {};
    const exits = Math.max(
      toCount(contract.exit_point_count),
      toCount(contract.exit_link_count),
      toCount(contract.exit_count),
      toCount(hexObjectCounts.exits)
    );
    const actors = Math.max(
      toCount(contract.actor_count),
      toCount(contentBucketCounts.npcs) + toCount(contentBucketCounts.entities),
      toCount(hexObjectCounts.actors)
    );
    const items = Math.max(
      toCount(contract.item_count),
      toCount(contentBucketCounts.items),
      toCount(hexObjectCounts.items)
    );
    const hazards = Math.max(
      toCount(contract.hazard_count),
      toCount(contentBucketCounts.hazards),
      toCount(hexObjectCounts.hazards)
    );
    const obstacles = Math.max(
      toCount(contract.obstacle_count),
      toCount(contentBucketCounts.obstacles),
      toCount(hexObjectCounts.obstacles)
    );
    const interactables = Math.max(
      toCount(contract.interactable_count),
      toCount(contentBucketCounts.interactables),
      toCount(hexObjectCounts.interactables)
    );
    return {
      exits,
      actors,
      items,
      hazards,
      obstacles,
      interactables,
      hasAny: exits > 0 || actors > 0 || items > 0 || hazards > 0 || obstacles > 0 || interactables > 0,
      compactLabel: `E:${exits} A:${actors} I:${items} H:${hazards}`,
    };
  }

  global.DCHexmapRenderContract = {
    ENTITY_TYPE_COLORS,
    summarizeHexObjects,
    resolveRoomHexStyle,
    buildHexTooltipLines,
    summarizeRoomContract,
  };
})(typeof window !== 'undefined' ? window : globalThis);
