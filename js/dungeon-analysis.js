(function (Drupal, drupalSettings, once) {
  let mermaidInitialized = false;
  let renderCounter = 0;
  let panZoomInstance = null;
  let safetyMapPanZoomInstance = null;
  let roomExplorerPanZoomInstance = null;
  let safetyMapV2Renderer = null;
  let roomExplorerV2Renderer = null;
  let manualGranularityLevel = null;

  const MIN_GRANULARITY = 5;
  const MAX_GRANULARITY = 15;
  const DEFAULT_GRANULARITY = 14;
  const GRANULARITY_LABELS = {
    5: '~251km² districts',
    6: '~36km² city areas',
    7: '~5.2km² neighborhoods',
    8: '~0.7km² block groups',
    9: '~0.1km² street blocks',
    10: '~15,047m² building groups',
    11: '~2,150m² buildings',
    12: '~307m² rooms',
    13: '~44m² ultra-precision',
    14: '~6.3m² rooms / vehicles',
    15: '~0.9m² object-scale',
  };
  const GRANULARITY_ZOOM_FACTORS = {
    5: 0.35,
    6: 0.5,
    7: 0.7,
    8: 0.9,
    9: 1.15,
    10: 1.45,
    11: 1.8,
    12: 2.2,
    13: 2.7,
    14: 3.3,
    15: 4.0,
  };
  const ANALYSIS_DEFAULT_HEX_STYLE = {
    fillColor: 0x2d3748,
    fillAlpha: 1,
    lineColor: 0x4a5568,
    lineAlpha: 1,
    lineWidth: 1,
    showCoordinates: false,
  };

  function escapeLabel(value) {
    return String(value || '')
      .replace(/\\/g, '\\\\')
      .replace(/"/g, '\\"')
      .replace(/\n/g, ' ');
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function readColor(styles, propertyName, fallback) {
    const value = styles && styles.getPropertyValue ? styles.getPropertyValue(propertyName).trim() : '';
    return value || fallback;
  }

  function buildMermaidTheme(root) {
    const styles = root ? window.getComputedStyle(root) : null;
    const surface = readColor(styles, '--analysis-surface', '#ffffff');
    const surfaceMuted = readColor(styles, '--analysis-surface-muted', '#f5f5f5');
    const surfaceDeep = readColor(styles, '--analysis-surface-deep', '#e9ecef');
    const border = readColor(styles, '--analysis-border', '#c6ccd2');
    const text = readColor(styles, '--analysis-text', '#1f2933');
    const textMuted = readColor(styles, '--analysis-text-muted', '#52606d');

    return {
      startOnLoad: false,
      securityLevel: 'strict',
      theme: 'base',
      themeVariables: {
        background: surface,
        mainBkg: surface,
        secondBkg: surfaceMuted,
        tertiaryBkg: surfaceDeep,
        primaryColor: surfaceMuted,
        secondaryColor: surface,
        tertiaryColor: surfaceDeep,
        primaryBorderColor: border,
        secondaryBorderColor: border,
        tertiaryBorderColor: border,
        primaryTextColor: text,
        secondaryTextColor: text,
        tertiaryTextColor: text,
        textColor: text,
        nodeTextColor: text,
        lineColor: textMuted,
        defaultLinkColor: textMuted,
        edgeLabelBackground: surface,
        clusterBkg: surfaceMuted,
        clusterBorder: border,
        titleColor: text,
      },
      flowchart: {
        useMaxWidth: false,
        htmlLabels: false,
        curve: 'basis',
      },
    };
  }

  function roomLabelLookup(graph) {
    const lookup = new Map();
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    nodes.forEach((node) => {
      const roomId = String((node && node.room_id) || '').trim();
      if (!roomId) {
        return;
      }
      const label = String((node && node.label) || roomId);
      lookup.set(roomId, label);
    });
    return lookup;
  }

  function buildMermaidSource(graph) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    const edges = graph && Array.isArray(graph.edges) ? graph.edges : [];
    const idMap = new Map();
    const lines = ['flowchart LR'];

    nodes.forEach((node, index) => {
      const roomId = String((node && node.room_id) || '').trim();
      if (!roomId) {
        return;
      }
      const alias = `R${index + 1}`;
      idMap.set(roomId, alias);

      const label = String((node && node.label) || roomId);
      const tags = [];
      if (Boolean(node && node.is_exit_gateway)) {
        tags.push('EXIT');
      }
      if (Boolean(node && node.is_external)) {
        tags.push('EXTERNAL');
      }
      const tagLine = tags.length ? `\\n[${tags.join(',')}]` : '';
      const fullLabel = `${label}\\n(${roomId})${tagLine}`;
      const escaped = escapeLabel(fullLabel);
      lines.push(`  ${alias}["${escaped}"]`);
    });

    // Absalom layout hints:
    // - Streets centered vertically
    // - North gate above, South gate below
    // - Central/Main gate on the right (via LR flow + streets -> central link)
    const absalomStreets = idMap.get('tpl_room_absalom_streets');
    const absalomNorthGate = idMap.get('tpl_room_absalom_north_city_gates');
    const absalomSouthGate = idMap.get('tpl_room_absalom_south_city_gates');
    if (absalomStreets && absalomNorthGate && absalomSouthGate) {
      lines.push('  subgraph ABSA["Absalom Gate Axis"]');
      lines.push('    direction TB');
      lines.push(`    ${absalomNorthGate}`);
      lines.push(`    ${absalomStreets}`);
      lines.push(`    ${absalomSouthGate}`);
      lines.push('  end');
    }

    edges.forEach((edge) => {
      const fromRoomId = String((edge && edge.from_room_id) || '').trim();
      const toRoomId = String((edge && edge.to_room_id) || '').trim();
      if (!fromRoomId || !toRoomId) {
        return;
      }
      const from = idMap.get(fromRoomId);
      const to = idMap.get(toRoomId);
      if (!from || !to) {
        return;
      }

      const isExit = Boolean(edge && edge.is_dungeon_exit);
      lines.push(isExit ? `  ${from} ==> ${to}` : `  ${from} --> ${to}`);
    });

    return lines.join('\n');
  }

  function toRoomId(node) {
    return String((node && node.room_id) || '').trim();
  }

  function toRoomLabel(node) {
    const roomId = toRoomId(node);
    const label = String((node && node.label) || '').trim();
    return label || roomId;
  }

  function toNonNegativeInteger(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
      return 0;
    }
    return Math.max(0, Math.round(numeric));
  }

  function toHexColorInt(value, fallback) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
      return fallback;
    }
    return numeric >>> 0;
  }

  function hexIntToCssColor(value, fallback = '#000000') {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
      return fallback;
    }
    const normalized = (numeric >>> 0).toString(16).padStart(6, '0').slice(-6);
    return `#${normalized}`;
  }

  function getSharedRenderContract() {
    const sharedContract = window.DCHexmapRenderContract;
    if (!sharedContract || typeof sharedContract !== 'object') {
      throw new Error('Dungeon analysis contract violation: shared hexmap render contract is unavailable.');
    }
    return sharedContract;
  }

  function getSemanticPalette() {
    const sharedContract = getSharedRenderContract();
    const entityTypeColors = sharedContract && typeof sharedContract.ENTITY_TYPE_COLORS === 'object' && !Array.isArray(sharedContract.ENTITY_TYPE_COLORS)
      ? sharedContract.ENTITY_TYPE_COLORS
      : {};
    const hazardColor = hexIntToCssColor(toHexColorInt(entityTypeColors.hazard, 0xf97316), '#f97316');
    const itemColor = hexIntToCssColor(toHexColorInt(entityTypeColors.item, 0xf59e0b), '#f59e0b');
    const actorColor = hexIntToCssColor(toHexColorInt(entityTypeColors.npc, 0x22c55e), '#22c55e');
    const semanticExitColor = '#60a5fa';
    return {
      hazard: { fillColor: hazardColor, textColor: '#0b1020' },
      item: { fillColor: itemColor, textColor: '#0b1020' },
      actor: { fillColor: actorColor, textColor: '#052e16' },
      exit: { fillColor: semanticExitColor, textColor: '#082f49' },
    };
  }

  function applySharedSemanticPalette(root) {
    if (!root || !root.style) {
      return;
    }
    const palette = getSemanticPalette();
    root.style.setProperty('--analysis-semantic-hazard', palette.hazard.fillColor);
    root.style.setProperty('--analysis-semantic-item', palette.item.fillColor);
    root.style.setProperty('--analysis-semantic-actor', palette.actor.fillColor);
    root.style.setProperty('--analysis-semantic-exit', palette.exit.fillColor);
  }

  function resolveRoomHexStyleForAnalysis(roomHex) {
    const sharedContract = getSharedRenderContract();
    if (typeof sharedContract.resolveRoomHexStyle !== 'function') {
      throw new Error('Dungeon analysis contract violation: resolveRoomHexStyle is unavailable in shared render contract.');
    }
    return sharedContract.resolveRoomHexStyle(roomHex, ANALYSIS_DEFAULT_HEX_STYLE);
  }

  function buildSvgHexStyleAttribute(style) {
    const fillColor = hexIntToCssColor(toHexColorInt(style && style.fillColor, ANALYSIS_DEFAULT_HEX_STYLE.fillColor), '#2d3748');
    const lineColor = hexIntToCssColor(toHexColorInt(style && style.lineColor, ANALYSIS_DEFAULT_HEX_STYLE.lineColor), '#4a5568');
    const fillAlpha = Number.isFinite(Number(style && style.fillAlpha)) ? Number(style.fillAlpha) : ANALYSIS_DEFAULT_HEX_STYLE.fillAlpha;
    const lineAlpha = Number.isFinite(Number(style && style.lineAlpha)) ? Number(style.lineAlpha) : ANALYSIS_DEFAULT_HEX_STYLE.lineAlpha;
    const lineWidth = Number.isFinite(Number(style && style.lineWidth)) ? Number(style.lineWidth) : ANALYSIS_DEFAULT_HEX_STYLE.lineWidth;
    return [
      `fill:${fillColor}`,
      `fill-opacity:${Math.max(0, Math.min(1, fillAlpha)).toFixed(3)}`,
      `stroke:${lineColor}`,
      `stroke-opacity:${Math.max(0, Math.min(1, lineAlpha)).toFixed(3)}`,
      `stroke-width:${Math.max(0, lineWidth).toFixed(3)}`,
    ].join(';');
  }

  function summarizeNodeRoomContract(node) {
    const contract = node && typeof node.room_contract === 'object' && !Array.isArray(node.room_contract)
      ? node.room_contract
      : {};
    const sharedContract = window.DCHexmapRenderContract;
    if (sharedContract && typeof sharedContract.summarizeRoomContract === 'function') {
      return sharedContract.summarizeRoomContract(contract);
    }
    const exits = toNonNegativeInteger(contract.exit_count || contract.exit_link_count || contract.exit_point_count);
    const actors = toNonNegativeInteger(contract.actor_count);
    const items = toNonNegativeInteger(contract.item_count);
    const hazards = toNonNegativeInteger(contract.hazard_count);
    const obstacles = toNonNegativeInteger(contract.obstacle_count);
    const interactables = toNonNegativeInteger(contract.interactable_count);
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

  function summarizeCellContractMetadata(metadata) {
    const objectCounts = metadata && typeof metadata.hex_object_counts === 'object' && !Array.isArray(metadata.hex_object_counts)
      ? metadata.hex_object_counts
      : null;
    if (!objectCounts) {
      return null;
    }
    const exits = toNonNegativeInteger(objectCounts.exits);
    const actors = toNonNegativeInteger(objectCounts.actors);
    const items = toNonNegativeInteger(objectCounts.items);
    const hazards = toNonNegativeInteger(objectCounts.hazards);
    return {
      exits,
      actors,
      items,
      hazards,
      hasAny: exits > 0 || actors > 0 || items > 0 || hazards > 0,
      compactLabel: `E:${exits} A:${actors} I:${items} H:${hazards}`,
    };
  }

  function summarizeCellHexDetailMetadata(metadata, contextLabel = '') {
    const detail = metadata && typeof metadata.hex_detail === 'object' && !Array.isArray(metadata.hex_detail)
      ? metadata.hex_detail
      : null;
    if (!detail) {
      throw new Error(`Dungeon analysis contract violation: missing hex_detail metadata${contextLabel ? ` for ${contextLabel}` : ''}.`);
    }
    const objects = Array.isArray(detail.objects)
      ? detail.objects.map((entry) => String(entry || '').trim()).filter((entry) => entry !== '')
      : [];
    const entities = Array.isArray(detail.entities)
      ? detail.entities.map((entry) => String(entry || '').trim()).filter((entry) => entry !== '')
      : [];
    const terrain = String(detail.terrain || '').trim() || 'unknown';
    const lighting = String(detail.lighting || '').trim() || 'unknown';
    const connection = String(detail.connection || '').trim() || 'none';
    const elevationValue = Number(detail.elevation_ft);
    const elevationFt = Number.isFinite(elevationValue) ? elevationValue.toFixed(0) : 'NA';
    const passability = String(detail.passability || '').trim() || 'unknown';
    const isEntry = detail.is_entry === true || String(detail.connection || '').toLowerCase().includes('entry');
    const isVisible = detail.is_visible !== false;
    const isDiscovered = detail.is_discovered !== false;
    return {
      terrain,
      lighting,
      elevationFt,
      passability,
      connection,
      isEntry,
      isVisible,
      isDiscovered,
      objectsList: objects,
      entitiesList: entities,
      objectsLabel: objects.length ? objects.join(', ') : 'None',
      entitiesLabel: entities.length ? entities.join(', ') : 'None',
    };
  }

  function buildHexSemanticBadgeOverlays(cx, cy, mapHexSize, semantics) {
    const semanticPalette = getSemanticPalette();
    const badges = [
      { count: semantics.hazards, glyph: 'H', cls: 'dc-dungeon-analysis__safetymap-badge--hazard', x: cx + (mapHexSize * 0.48), y: cy + (mapHexSize * 0.04), fillColor: semanticPalette.hazard.fillColor, textColor: semanticPalette.hazard.textColor },
      { count: semantics.items, glyph: 'I', cls: 'dc-dungeon-analysis__safetymap-badge--item', x: cx + (mapHexSize * 0.22), y: cy + (mapHexSize * 0.42), fillColor: semanticPalette.item.fillColor, textColor: semanticPalette.item.textColor },
      { count: semantics.actors, glyph: 'A', cls: 'dc-dungeon-analysis__safetymap-badge--actor', x: cx - (mapHexSize * 0.08), y: cy + (mapHexSize * 0.54), fillColor: semanticPalette.actor.fillColor, textColor: semanticPalette.actor.textColor },
      { count: semantics.exits, glyph: 'X', cls: 'dc-dungeon-analysis__safetymap-badge--exit', x: cx - (mapHexSize * 0.36), y: cy + (mapHexSize * 0.38), fillColor: semanticPalette.exit.fillColor, textColor: semanticPalette.exit.textColor },
    ];
    return badges
      .filter((badge) => Number(badge.count) > 0)
      .map((badge) => {
        const label = `${badge.glyph}${Number(badge.count) > 1 ? badge.count : ''}`;
        return `<g class="dc-dungeon-analysis__safetymap-badge ${badge.cls}"><circle cx="${badge.x.toFixed(2)}" cy="${badge.y.toFixed(2)}" r="${Math.max(5, mapHexSize * 0.11).toFixed(2)}" fill="${escapeHtml(badge.fillColor)}"></circle><text x="${badge.x.toFixed(2)}" y="${(badge.y + Math.max(2.5, mapHexSize * 0.03)).toFixed(2)}" text-anchor="middle" fill="${escapeHtml(badge.textColor)}">${escapeHtml(label)}</text></g>`;
      })
      .join('');
  }

  function buildRoomContractBarOverlay(cx, cy, roomSummary) {
    const exits = toNonNegativeInteger(roomSummary && roomSummary.exits);
    const actors = toNonNegativeInteger(roomSummary && roomSummary.actors);
    const items = toNonNegativeInteger(roomSummary && roomSummary.items);
    const hazards = toNonNegativeInteger(roomSummary && roomSummary.hazards);
    const total = exits + actors + items + hazards;
    if (total <= 0) {
      return '';
    }
    const width = 40;
    const height = 3.4;
    const startX = cx - (width / 2);
    const y = cy + 24;
    const semanticPalette = getSemanticPalette();
    const segments = [
      { label: 'exits', value: exits, color: semanticPalette.exit.fillColor },
      { label: 'actors', value: actors, color: semanticPalette.actor.fillColor },
      { label: 'items', value: items, color: semanticPalette.item.fillColor },
      { label: 'hazards', value: hazards, color: semanticPalette.hazard.fillColor },
    ].filter((segment) => segment.value > 0);
    let cursor = startX;
    const rects = segments.map((segment, index) => {
      const segmentWidth = index === segments.length - 1
        ? ((startX + width) - cursor)
        : Math.max(1, Math.round((segment.value / total) * width));
      const rect = `<rect class="dc-dungeon-analysis__roombar-seg" x="${cursor.toFixed(2)}" y="${y.toFixed(2)}" width="${segmentWidth.toFixed(2)}" height="${height.toFixed(2)}" fill="${escapeHtml(segment.color)}"></rect>`;
      cursor += segmentWidth;
      return rect;
    }).join('');
    return `<g class="dc-dungeon-analysis__roombar" aria-hidden="true"><rect class="dc-dungeon-analysis__roombar-track" x="${startX.toFixed(2)}" y="${y.toFixed(2)}" width="${width.toFixed(2)}" height="${height.toFixed(2)}"></rect>${rects}</g>`;
  }

  function buildHoverMetricBar(metricType, label, value) {
    const semanticPalette = getSemanticPalette();
    const fillColor = ({
      exit: semanticPalette.exit.fillColor,
      actor: semanticPalette.actor.fillColor,
      item: semanticPalette.item.fillColor,
      hazard: semanticPalette.hazard.fillColor,
    })[String(metricType || '').trim()] || '#64748b';
    const normalized = toNonNegativeInteger(value);
    const capped = Math.min(9, normalized);
    const widthPct = (capped / 9) * 100;
    return `<div class="dc-dungeon-analysis__map-metric">
      <span class="dc-dungeon-analysis__map-metric-label">${escapeHtml(label)} ${normalized}</span>
      <span class="dc-dungeon-analysis__map-metric-track"><span class="dc-dungeon-analysis__map-metric-fill" style="width:${widthPct.toFixed(2)}%;background:${escapeHtml(fillColor)}"></span></span>
    </div>`;
  }

  function buildSyntheticHexObjectsForCanvas(semantics, detail) {
    const objects = [];
    const pushObjects = (count, category, labelPrefix) => {
      const normalized = toNonNegativeInteger(count);
      for (let index = 0; index < normalized; index++) {
        objects.push({
          category,
          label: normalized > 1 ? `${labelPrefix} ${index + 1}` : labelPrefix,
          passable: true,
          blocks_movement: false,
        });
      }
    };
    pushObjects(semantics.hazards, 'hazard', 'Hazard');
    pushObjects(semantics.items, 'item', 'Item');
    pushObjects(semantics.actors, 'npc', 'Actor');
    pushObjects(semantics.exits, 'exit', 'Exit');
    if (String(detail.passability || '').toLowerCase() === 'blocked') {
      objects.push({
        category: 'obstacle',
        label: 'Blocking obstacle',
        passable: false,
        blocks_movement: true,
      });
    }
    if (Array.isArray(detail.objectsList) && detail.objectsList.length > 0) {
      const existingLabels = new Set(objects.map((object) => String(object.label || '').toLowerCase()));
      detail.objectsList.forEach((label) => {
        const normalized = String(label || '').trim();
        if (normalized === '') {
          return;
        }
        const key = normalized.toLowerCase();
        if (existingLabels.has(key)) {
          return;
        }
        existingLabels.add(key);
        objects.push({
          category: 'feature',
          label: normalized,
          passable: true,
          blocks_movement: false,
        });
      });
    }
    return objects;
  }

  function setMapHoverDefault(hoverEl, defaultMessage) {
    if (!hoverEl) {
      return;
    }
    const message = String(defaultMessage || '').trim();
    hoverEl.dataset.defaultMessage = message;
    hoverEl.textContent = message;
  }

  function normalizeMapHexDetailFromDataset(dataset) {
    const d = dataset || {};
    const objectList = String(d.objects || '').trim();
    const entityList = String(d.entities || '').trim();
    return {
      terrain: String(d.terrain || 'unknown').trim() || 'unknown',
      lighting: String(d.lighting || 'unknown').trim() || 'unknown',
      elevationFt: String(d.elevationFt || 'NA').trim() || 'NA',
      passability: String(d.passability || 'unknown').trim() || 'unknown',
      connection: String(d.connection || 'none').trim() || 'none',
      objects: objectList !== '' ? objectList : 'None',
      entities: entityList !== '' ? entityList : 'None',
    };
  }

  function buildMapHoverHtmlFromHexPolygon(hexPolygon) {
    if (!hexPolygon || !hexPolygon.dataset) {
      return '';
    }
    const d = hexPolygon.dataset;
    const roomLabel = String(d.roomLabel || '').trim();
    const roomId = String(d.roomId || '').trim();
    const hexDesignation = String(d.hexDesignation || '').trim();
    const q = String(d.globalQ || '').trim();
    const r = String(d.globalR || '').trim();
    const lat = String(d.latitude || '').trim();
    const lng = String(d.longitude || '').trim();
    const exits = toNonNegativeInteger(d.exits);
    const actors = toNonNegativeInteger(d.actors);
    const items = toNonNegativeInteger(d.items);
    const hazards = toNonNegativeInteger(d.hazards);
    const role = String(d.role || '').trim() || 'NA';
    const roomContract = String(d.roomContract || '').trim() || 'E:0 A:0 I:0 H:0';
    const hexContract = String(d.hexContract || '').trim() || 'E:0 A:0 I:0 H:0';
    const detail = normalizeMapHexDetailFromDataset(d);
    const elevationDisplay = detail.elevationFt === 'NA' ? 'NA' : `${detail.elevationFt} ft`;
    const metrics = [
      buildHoverMetricBar('exit', 'Exits', exits),
      buildHoverMetricBar('actor', 'Actors', actors),
      buildHoverMetricBar('item', 'Items', items),
      buildHoverMetricBar('hazard', 'Hazards', hazards),
    ].join('');
    return `<div class="dc-dungeon-analysis__map-hover-title">${escapeHtml(roomLabel)} <span class="text-muted">(${escapeHtml(roomId)})</span></div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Hex:</strong> ${escapeHtml(hexDesignation)}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Coords:</strong> q=${escapeHtml(q)} r=${escapeHtml(r)} | lat=${escapeHtml(lat)} lng=${escapeHtml(lng)}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Role:</strong> ${escapeHtml(role)} | <strong>Terrain:</strong> ${escapeHtml(detail.terrain)} | <strong>Lighting:</strong> ${escapeHtml(detail.lighting)}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Elevation:</strong> ${escapeHtml(elevationDisplay)} | <strong>Passability:</strong> ${escapeHtml(detail.passability)} | <strong>Connection:</strong> ${escapeHtml(detail.connection)}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Objects:</strong> ${escapeHtml(detail.objects)}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Entities:</strong> ${escapeHtml(detail.entities)}</div>
      <div class="dc-dungeon-analysis__map-metrics">${metrics}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Hex contract:</strong> E:${exits} A:${actors} I:${items} H:${hazards} <span class="text-muted">(${escapeHtml(hexContract)})</span></div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Room contract:</strong> ${escapeHtml(roomContract)}</div>`;
  }

  function buildMapHoverHtmlFromV2Detail(detail) {
    if (!detail || typeof detail !== 'object') {
      return '';
    }
    const exits = toNonNegativeInteger(detail.exits);
    const actors = toNonNegativeInteger(detail.actors);
    const items = toNonNegativeInteger(detail.items);
    const hazards = toNonNegativeInteger(detail.hazards);
    const metrics = [
      buildHoverMetricBar('exit', 'Exits', exits),
      buildHoverMetricBar('actor', 'Actors', actors),
      buildHoverMetricBar('item', 'Items', items),
      buildHoverMetricBar('hazard', 'Hazards', hazards),
    ].join('');
    return `<div class="dc-dungeon-analysis__map-hover-title">${escapeHtml(String(detail.roomLabel || 'Room'))} <span class="text-muted">(${escapeHtml(String(detail.roomId || 'unknown_room'))})</span></div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Hex:</strong> ${escapeHtml(String(detail.hexDesignation || 'NA'))}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Coords:</strong> q=${escapeHtml(String(detail.q ?? 'NA'))} r=${escapeHtml(String(detail.r ?? 'NA'))} | lat=${escapeHtml(String(detail.latitude || 'NA'))} lng=${escapeHtml(String(detail.longitude || 'NA'))}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Role:</strong> ${escapeHtml(String(detail.role || 'NA'))} | <strong>Terrain:</strong> ${escapeHtml(String(detail.terrain || 'unknown'))} | <strong>Lighting:</strong> ${escapeHtml(String(detail.lighting || 'unknown'))}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Elevation:</strong> ${escapeHtml(String(detail.elevationFt || 'NA')) === 'NA' ? 'NA' : `${escapeHtml(String(detail.elevationFt))} ft`} | <strong>Passability:</strong> ${escapeHtml(String(detail.passability || 'unknown'))} | <strong>Connection:</strong> ${escapeHtml(String(detail.connection || 'none'))}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Objects:</strong> ${escapeHtml(String(detail.objects || 'None'))}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Entities:</strong> ${escapeHtml(String(detail.entities || 'None'))}</div>
      <div class="dc-dungeon-analysis__map-metrics">${metrics}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Hex contract:</strong> ${escapeHtml(String(detail.hexContract || `E:${exits} A:${actors} I:${items} H:${hazards}`))}</div>
      <div class="dc-dungeon-analysis__map-hover-line"><strong>Room contract:</strong> ${escapeHtml(String(detail.roomContract || 'E:0 A:0 I:0 H:0'))}</div>`;
  }

  function hasV2AnalysisRenderer() {
    return Boolean(
      window.DCAnalysisV2Bridge
      && typeof window.DCAnalysisV2Bridge.createRenderer === 'function'
    );
  }

  function bindV2RendererHover(renderer, hoverEl, defaultMessage, debugEl, mapTag) {
    if (!renderer || !hoverEl) {
      return;
    }
    setMapHoverDefault(hoverEl, defaultMessage);
    renderer.setHoverHandler((detail) => {
      if (!detail) {
        setMapHoverDefault(hoverEl, defaultMessage);
        return;
      }
      hoverEl.innerHTML = buildMapHoverHtmlFromV2Detail(detail);
    });
    appendDebug(debugEl, `${mapTag}: v2 hover bridge bound`);
  }

  function bindMapHoverInteractions(mapEl, hoverEl, defaultMessage, debugEl, mapTag) {
    if (!mapEl || !hoverEl) {
      return;
    }
    const svg = mapEl.querySelector('svg');
    if (!svg) {
      setMapHoverDefault(hoverEl, defaultMessage);
      return;
    }
    setMapHoverDefault(hoverEl, defaultMessage);
    const polygons = svg.querySelectorAll('polygon[data-hex-designation]');
    let pinnedPolygon = null;
    const clearPinned = () => {
      if (!pinnedPolygon) {
        return;
      }
      pinnedPolygon.classList.remove('dc-dungeon-analysis__safetymap-cell--selected');
      pinnedPolygon.setAttribute('aria-pressed', 'false');
      pinnedPolygon = null;
    };
    const renderPolygonDetails = (polygon) => {
      hoverEl.innerHTML = buildMapHoverHtmlFromHexPolygon(polygon);
    };
    polygons.forEach((polygon) => {
      polygon.classList.remove('dc-dungeon-analysis__safetymap-cell--hovered');
      polygon.classList.remove('dc-dungeon-analysis__safetymap-cell--selected');
      polygon.setAttribute('tabindex', '0');
      polygon.setAttribute('role', 'button');
      polygon.setAttribute('aria-pressed', 'false');
      const onEnter = () => {
        polygon.classList.add('dc-dungeon-analysis__safetymap-cell--hovered');
        renderPolygonDetails(polygon);
      };
      const onLeave = () => {
        polygon.classList.remove('dc-dungeon-analysis__safetymap-cell--hovered');
        if (pinnedPolygon === polygon) {
          return;
        }
        setMapHoverDefault(hoverEl, defaultMessage);
      };
      const onTogglePin = () => {
        if (pinnedPolygon === polygon) {
          clearPinned();
          setMapHoverDefault(hoverEl, defaultMessage);
          return;
        }
        clearPinned();
        pinnedPolygon = polygon;
        pinnedPolygon.classList.add('dc-dungeon-analysis__safetymap-cell--selected');
        pinnedPolygon.setAttribute('aria-pressed', 'true');
        renderPolygonDetails(polygon);
      };
      const onKeyDown = (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          onTogglePin();
        }
      };
      polygon.addEventListener('mouseenter', onEnter);
      polygon.addEventListener('mouseleave', onLeave);
      polygon.addEventListener('focus', onEnter);
      polygon.addEventListener('blur', onLeave);
      polygon.addEventListener('click', onTogglePin);
      polygon.addEventListener('keydown', onKeyDown);
    });
    svg.addEventListener('mouseleave', () => {
      if (!pinnedPolygon) {
        setMapHoverDefault(hoverEl, defaultMessage);
      }
    });
    appendDebug(debugEl, `${mapTag}: hover overlays bound hexes=${polygons.length}`);
  }

  function findSafetyMapAnchor(nodes) {
    if (!Array.isArray(nodes) || nodes.length === 0) {
      return '';
    }

    const preferredIds = ['tpl_room_tavern_entrance', 'tavern_entrance'];
    for (const roomId of preferredIds) {
      if (nodes.some((node) => toRoomId(node) === roomId)) {
        return roomId;
      }
    }

    const gildedMatch = nodes.find((node) => toRoomLabel(node).toLowerCase().includes('gilded tankard'));
    if (gildedMatch) {
      return toRoomId(gildedMatch);
    }

    const primary = nodes.find((node) => Boolean(node && node.is_primary));
    if (primary) {
      return toRoomId(primary);
    }

    return toRoomId(nodes[0]);
  }

  function findRoomExplorerDefaultRoomId(nodes) {
    if (!Array.isArray(nodes) || nodes.length === 0) {
      return '';
    }

    const gildedMatch = nodes.find((node) => toRoomLabel(node).toLowerCase().includes('gilded tankard'));
    if (gildedMatch) {
      return toRoomId(gildedMatch);
    }

    const preferredIds = ['tpl_room_tavern_entrance', 'tavern_entrance'];
    for (const roomId of preferredIds) {
      if (nodes.some((node) => toRoomId(node) === roomId)) {
        return roomId;
      }
    }

    return toRoomId(nodes[0]);
  }

  function buildSingleRoomGraph(graph, roomId) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    const selectedRoomId = String(roomId || '').trim();
    if (!selectedRoomId) {
      return { nodes: [], edges: [] };
    }
    const selectedNode = nodes.find((node) => toRoomId(node) === selectedRoomId);
    if (!selectedNode) {
      return { nodes: [], edges: [] };
    }
    return {
      nodes: [selectedNode],
      edges: [],
    };
  }

  function resolveRoomExplorerSelection(graph, requestedRoomId) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    if (!nodes.length) {
      return '';
    }
    const requested = String(requestedRoomId || '').trim();
    if (requested && nodes.some((node) => toRoomId(node) === requested)) {
      return requested;
    }
    return findRoomExplorerDefaultRoomId(nodes);
  }

  function populateRoomExplorerRoomOptions(roomSelectEl, graph, preferredRoomId) {
    if (!roomSelectEl) {
      return resolveRoomExplorerSelection(graph, preferredRoomId);
    }
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    roomSelectEl.innerHTML = '';
    if (!nodes.length) {
      return '';
    }

    const sorted = nodes
      .map((node) => ({
        roomId: toRoomId(node),
        label: toRoomLabel(node),
      }))
      .filter((node) => node.roomId !== '')
      .sort((a, b) => a.label.localeCompare(b.label));
    sorted.forEach((node) => {
      const option = document.createElement('option');
      option.value = node.roomId;
      option.textContent = `${node.label} (${node.roomId})`;
      roomSelectEl.appendChild(option);
    });

    const selectedRoomId = resolveRoomExplorerSelection(graph, preferredRoomId);
    if (selectedRoomId) {
      roomSelectEl.value = selectedRoomId;
    }
    return selectedRoomId;
  }

  function buildUndirectedAdjacency(graph) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    const edges = graph && Array.isArray(graph.edges) ? graph.edges : [];
    const adjacency = new Map();

    nodes.forEach((node) => {
      const roomId = toRoomId(node);
      if (!roomId) {
        return;
      }
      adjacency.set(roomId, new Set());
    });

    edges.forEach((edge) => {
      const fromRoomId = String((edge && edge.from_room_id) || '').trim();
      const toRoomId = String((edge && edge.to_room_id) || '').trim();
      if (!fromRoomId || !toRoomId) {
        return;
      }
      if (!adjacency.has(fromRoomId)) {
        adjacency.set(fromRoomId, new Set());
      }
      if (!adjacency.has(toRoomId)) {
        adjacency.set(toRoomId, new Set());
      }
      adjacency.get(fromRoomId).add(toRoomId);
      adjacency.get(toRoomId).add(fromRoomId);
    });

    return adjacency;
  }

  function coordinateKey(q, r) {
    return `${q},${r}`;
  }

  function axialDistance(fromQ, fromR, toQ, toR) {
    const dq = Number(toQ) - Number(fromQ);
    const dr = Number(toR) - Number(fromR);
    const ds = (-Number(toQ) - Number(toR)) - (-Number(fromQ) - Number(fromR));
    return Math.max(Math.abs(dq), Math.abs(dr), Math.abs(ds));
  }

  function findNearestOpenCoordinate(targetQ, targetR, occupied, maxRadius = 200) {
    const normalizedTargetQ = Math.round(Number(targetQ));
    const normalizedTargetR = Math.round(Number(targetR));
    const directKey = coordinateKey(normalizedTargetQ, normalizedTargetR);
    if (!occupied.has(directKey)) {
      return { q: normalizedTargetQ, r: normalizedTargetR, displaced: false };
    }

    let best = null;
    for (let radius = 1; radius <= maxRadius; radius++) {
      for (let dq = -radius; dq <= radius; dq++) {
        for (let dr = -radius; dr <= radius; dr++) {
          const candidateQ = normalizedTargetQ + dq;
          const candidateR = normalizedTargetR + dr;
          if (axialDistance(normalizedTargetQ, normalizedTargetR, candidateQ, candidateR) !== radius) {
            continue;
          }
          const key = coordinateKey(candidateQ, candidateR);
          if (occupied.has(key)) {
            continue;
          }
          if (!best || candidateQ < best.q || (candidateQ === best.q && candidateR < best.r)) {
            best = { q: candidateQ, r: candidateR, displaced: true };
          }
        }
      }
      if (best) {
        return best;
      }
    }
    return { q: normalizedTargetQ, r: normalizedTargetR, displaced: true };
  }

  function findFirstOpenCoordinate(occupied, maxRadius = 200) {
    for (let radius = 0; radius <= maxRadius; radius++) {
      for (let q = -radius; q <= radius; q++) {
        const rMin = Math.max(-radius, -q - radius);
        const rMax = Math.min(radius, -q + radius);
        for (let r = rMin; r <= rMax; r++) {
          const key = coordinateKey(q, r);
          if (!occupied.has(key)) {
            return { q, r };
          }
        }
      }
    }
    return { q: 0, r: 0 };
  }

  function placeRoomsForSafetyMap(graph, anchorRoomId) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    const adjacency = buildUndirectedAdjacency(graph);
    const placements = new Map();
    const occupied = new Set();
    const roomIds = nodes
      .map((node) => toRoomId(node))
      .filter((roomId) => roomId !== '')
      .sort((a, b) => a.localeCompare(b));
    const directions = [
      [1, 0],
      [1, -1],
      [0, -1],
      [-1, 0],
      [-1, 1],
      [0, 1],
    ];

    const effectiveAnchor = roomIds.includes(anchorRoomId) ? anchorRoomId : (roomIds[0] || '');
    if (!effectiveAnchor) {
      return placements;
    }

    placements.set(effectiveAnchor, { q: 0, r: 0 });
    occupied.add(coordinateKey(0, 0));

    const queue = [effectiveAnchor];
    while (queue.length) {
      const current = queue.shift();
      const currentCoord = placements.get(current);
      const neighbors = [...(adjacency.get(current) || new Set())]
        .filter((roomId) => !placements.has(roomId))
        .sort((a, b) => a.localeCompare(b));
      let directionIndex = 0;

      neighbors.forEach((neighbor) => {
        let selected = null;
        for (let offset = 0; offset < directions.length; offset++) {
          const [dq, dr] = directions[(directionIndex + offset) % directions.length];
          const q = currentCoord.q + dq;
          const r = currentCoord.r + dr;
          const key = coordinateKey(q, r);
          if (!occupied.has(key)) {
            selected = { q, r };
            directionIndex = (directionIndex + offset + 1) % directions.length;
            break;
          }
        }

        if (!selected) {
          selected = findFirstOpenCoordinate(occupied);
        }

        placements.set(neighbor, selected);
        occupied.add(coordinateKey(selected.q, selected.r));
        queue.push(neighbor);
      });
    }

    roomIds.forEach((roomId) => {
      if (placements.has(roomId)) {
        return;
      }
      const coord = findFirstOpenCoordinate(occupied);
      placements.set(roomId, coord);
      occupied.add(coordinateKey(coord.q, coord.r));
    });

    return placements;
  }

  function axialToPixel(q, r, size) {
    return {
      x: size * 1.5 * q,
      y: size * Math.sqrt(3) * (r + q / 2),
    };
  }

  function hexPolygonPoints(cx, cy, size) {
    const points = [];
    for (let i = 0; i < 6; i++) {
      const angle = (Math.PI / 180) * (60 * i);
      const x = cx + (size * Math.cos(angle));
      const y = cy + (size * Math.sin(angle));
      points.push(`${x.toFixed(2)},${y.toFixed(2)}`);
    }
    return points.join(' ');
  }

  function truncateLabel(label, maxLength = 22) {
    const text = String(label || '').trim();
    if (text.length <= maxLength) {
      return text;
    }
    return `${text.slice(0, maxLength - 1)}…`;
  }

  function extractSparseAxialCentroid(node) {
    const centroid = node
      && node.h3
      && node.h3.cells
      && node.h3.cells.local_axial_centroid
      ? node.h3.cells.local_axial_centroid
      : null;
    if (!centroid) {
      return null;
    }
    const q = Number(centroid.q);
    const r = Number(centroid.r);
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      return null;
    }
    return { q, r };
  }

  function normalizeGranularity(level, fallback = DEFAULT_GRANULARITY) {
    const numeric = Number(level);
    if (!Number.isFinite(numeric)) {
      return clampGranularity(fallback);
    }
    return clampGranularity(Math.round(numeric));
  }

  function toFiniteNumber(value) {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : null;
  }

  function formatLatLng(value) {
    const numeric = toFiniteNumber(value);
    return numeric === null ? 'NA' : numeric.toFixed(8);
  }

  function extractSparseCellCoordinates(node) {
    const coordinates = node
      && node.h3
      && node.h3.cells
      && Array.isArray(node.h3.cells.coordinates)
      ? node.h3.cells.coordinates
      : [];
    const normalized = coordinates
      .map((cell) => {
        const q = Number(cell && cell.q);
        const r = Number(cell && cell.r);
        if (!Number.isFinite(q) || !Number.isFinite(r)) {
          return null;
        }
        const metadata = cell && typeof cell.metadata === 'object' && !Array.isArray(cell.metadata)
          ? cell.metadata
          : {};
        return {
          q,
          r,
          role: String((cell && cell.role) || '').trim(),
          dungeonId: String((cell && cell.dungeon_id) || '').trim(),
          roomId: String((cell && cell.room_id) || '').trim(),
          h3Resolution: toFiniteNumber(cell && cell.h3_resolution),
          h3Index: String((cell && cell.h3_index) || '').trim(),
          centerLatitude: toFiniteNumber(cell && cell.center_latitude),
          centerLongitude: toFiniteNumber(cell && cell.center_longitude),
          metadata,
        };
      })
      .filter(Boolean);
    if (normalized.length > 0) {
      return normalized;
    }
    const centroid = extractSparseAxialCentroid(node);
    if (!centroid) {
      return [];
    }
    return [{
      q: Number(centroid.q),
      r: Number(centroid.r),
      role: 'room_anchor',
      dungeonId: String((node && node.dungeon_id) || '').trim(),
      roomId: toRoomId(node),
      h3Resolution: toFiniteNumber(
        (node && node.h3 && node.h3.anchor && node.h3.anchor.h3_resolution)
        || (node && node.h3 && node.h3.cells && node.h3.cells.source_resolution)
      ),
      h3Index: String((node && node.h3 && node.h3.anchor && node.h3.anchor.h3_index) || '').trim(),
      centerLatitude: toFiniteNumber(node && node.h3 && node.h3.anchor && node.h3.anchor.center_latitude),
      centerLongitude: toFiniteNumber(node && node.h3 && node.h3.anchor && node.h3.anchor.center_longitude),
      metadata: {},
    }];
  }

  function aggregateSparseCellsForGranularity(cells, sourceResolution, targetResolution) {
    const source = normalizeGranularity(sourceResolution, 13);
    const target = normalizeGranularity(targetResolution, source);
    if (!Array.isArray(cells) || cells.length === 0) {
      return [];
    }
    if (target >= source) {
      return cells;
    }

    const divisor = Math.pow(2, source - target);
    const grouped = new Map();
    cells.forEach((cell) => {
      const q = Math.round(Number(cell.q) / divisor);
      const r = Math.round(Number(cell.r) / divisor);
      if (!Number.isFinite(q) || !Number.isFinite(r)) {
        return;
      }
      const key = coordinateKey(q, r);
      const existing = grouped.get(key);
      const role = String((cell && cell.role) || '').trim();
      if (!existing) {
        grouped.set(key, {
          q,
          r,
          role,
          dungeonId: String((cell && cell.dungeonId) || '').trim(),
          roomId: String((cell && cell.roomId) || '').trim(),
          h3Resolution: toFiniteNumber(cell && cell.h3Resolution),
          h3Index: String((cell && cell.h3Index) || '').trim(),
          centerLatitude: toFiniteNumber(cell && cell.centerLatitude),
          centerLongitude: toFiniteNumber(cell && cell.centerLongitude),
          metadata: cell && typeof cell.metadata === 'object' && !Array.isArray(cell.metadata)
            ? cell.metadata
            : {},
          aggregationCount: 1,
        });
        return;
      }
      if (!existing.role && role) {
        existing.role = role;
      }
      if (role === 'room_anchor') {
        existing.role = role;
      }
      existing.aggregationCount = Number(existing.aggregationCount || 1) + 1;
    });
    return [...grouped.values()];
  }

  function buildPlacementsFromSparseH3(nodes, anchorRoomId) {
    const sparsePlacements = new Map();
    const distinctCoords = new Set();

    nodes.forEach((node) => {
      const roomId = toRoomId(node);
      if (!roomId) {
        return;
      }
      const coord = extractSparseAxialCentroid(node);
      if (!coord) {
        return;
      }
      sparsePlacements.set(roomId, coord);
      distinctCoords.add(coordinateKey(coord.q.toFixed(3), coord.r.toFixed(3)));
    });

    if (sparsePlacements.size === 0) {
      return new Map();
    }
    // Local tactical q/r coordinates are room-scoped; if they collapse to the
    // same few centroids across many rooms, keep using topology placement until
    // authoritative global H3 anchors are populated.
    if (distinctCoords.size < 3) {
      return new Map();
    }

    const normalized = new Map();
    const anchorCoord = sparsePlacements.get(anchorRoomId) || [...sparsePlacements.values()][0];
    const anchorQ = Number(anchorCoord?.q || 0);
    const anchorR = Number(anchorCoord?.r || 0);
    sparsePlacements.forEach((coord, roomId) => {
      normalized.set(roomId, {
        q: Number(coord.q) - anchorQ,
        r: Number(coord.r) - anchorR,
      });
    });

    return normalized;
  }

  function buildSafetyMapSvg(graph, selectedGranularityLevel, dungeonId) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    const edges = graph && Array.isArray(graph.edges) ? graph.edges : [];
    const normalizedDungeonId = String(dungeonId || '').trim() || 'unknown_dungeon';
    const selectedGranularity = normalizeGranularity(selectedGranularityLevel, DEFAULT_GRANULARITY);
    if (!nodes.length) {
      return {
        svg: '',
        anchorRoomId: '',
        anchorLabel: '',
        granularity: selectedGranularity,
        overlapResolvedCount: 0,
        canvasRoom: null,
      };
    }

    const nodeById = new Map();
    nodes.forEach((node) => {
      const roomId = toRoomId(node);
      if (roomId) {
        nodeById.set(roomId, node);
      }
    });

    const anchorRoomId = findSafetyMapAnchor(nodes);
    const placementsFromSparseH3 = buildPlacementsFromSparseH3(nodes, anchorRoomId);
    const placements = placementsFromSparseH3.size > 0
      ? placementsFromSparseH3
      : placeRoomsForSafetyMap(graph, anchorRoomId);
    if (placementsFromSparseH3.size > 0) {
      const occupied = new Set();
      placements.forEach((coord) => {
        occupied.add(coordinateKey(coord.q, coord.r));
      });
      nodes.forEach((node) => {
        const roomId = toRoomId(node);
        if (!roomId || placements.has(roomId)) {
          return;
        }
        const coord = findFirstOpenCoordinate(occupied);
        placements.set(roomId, coord);
        occupied.add(coordinateKey(coord.q, coord.r));
      });
    }

    const roomPlans = new Map();
    let maxRoomRadius = 0;
    [...nodeById.entries()].forEach(([roomId, node]) => {
      const sourceResolution = Number(
        (node && node.h3 && node.h3.cells && node.h3.cells.source_resolution)
        || (node && node.h3 && node.h3.anchor && node.h3.anchor.h3_resolution)
        || 13
      );
      const sparseCells = aggregateSparseCellsForGranularity(
        extractSparseCellCoordinates(node),
        sourceResolution,
        selectedGranularity
      );
      const baseCells = sparseCells.length > 0 ? sparseCells : [{ q: 0, r: 0, role: 'room_anchor' }];
      const centroid = baseCells.reduce((acc, cell) => ({
        q: acc.q + Number(cell.q || 0),
        r: acc.r + Number(cell.r || 0),
      }), { q: 0, r: 0 });
      const centroidQ = centroid.q / baseCells.length;
      const centroidR = centroid.r / baseCells.length;
      const normalizedCells = baseCells.map((cell) => ({
        q: Math.round(Number(cell.q || 0) - centroidQ),
        r: Math.round(Number(cell.r || 0) - centroidR),
        rawQ: Number(cell.q || 0),
        rawR: Number(cell.r || 0),
        role: String((cell && cell.role) || '').trim(),
        dungeonId: String((cell && cell.dungeonId) || '').trim(),
        roomId: String((cell && cell.roomId) || '').trim(),
        h3Resolution: toFiniteNumber(cell && cell.h3Resolution),
        h3Index: String((cell && cell.h3Index) || '').trim(),
        centerLatitude: toFiniteNumber(cell && cell.centerLatitude),
        centerLongitude: toFiniteNumber(cell && cell.centerLongitude),
        metadata: cell && typeof cell.metadata === 'object' && !Array.isArray(cell.metadata)
          ? cell.metadata
          : {},
        aggregationCount: Number(cell && cell.aggregationCount) > 0 ? Number(cell.aggregationCount) : 1,
      }));
      const roomRadius = normalizedCells.reduce(
        (maxDistance, cell) => Math.max(maxDistance, axialDistance(0, 0, cell.q, cell.r)),
        0
      );
      maxRoomRadius = Math.max(maxRoomRadius, roomRadius);
      roomPlans.set(roomId, { node, normalizedCells });
    });

    const roomStride = placementsFromSparseH3.size > 0
      ? 1
      : Math.max(8, (maxRoomRadius * 2) + 6);
    const occupiedGlobalCells = new Set();
    const roomCenters = new Map();
    const roomAssignedCells = new Map();
    let overlapResolvedCount = 0;

    [...roomPlans.entries()]
      .sort((a, b) => a[0].localeCompare(b[0]))
      .forEach(([roomId, plan]) => {
        const roomPlacement = placements.get(roomId) || { q: 0, r: 0 };
        const centerQ = Math.round(Number(roomPlacement.q || 0) * roomStride);
        const centerR = Math.round(Number(roomPlacement.r || 0) * roomStride);
        roomCenters.set(roomId, { q: centerQ, r: centerR });

        const assigned = [];
        plan.normalizedCells.forEach((cell, index) => {
          const targetQ = centerQ + Math.round(Number(cell.q || 0));
          const targetR = centerR + Math.round(Number(cell.r || 0));
          const resolved = placementsFromSparseH3.size > 0
            ? { q: targetQ, r: targetR, displaced: false }
            : findNearestOpenCoordinate(targetQ, targetR, occupiedGlobalCells);
          const resolvedKey = coordinateKey(resolved.q, resolved.r);
          if (occupiedGlobalCells.has(resolvedKey)) {
            throw new Error(`Safety map hex collision detected for room ${roomId} at ${resolvedKey}.`);
          }
          occupiedGlobalCells.add(resolvedKey);
          if (resolved.displaced) {
            overlapResolvedCount++;
          }
          assigned.push({
            q: resolved.q,
            r: resolved.r,
            role: String(cell.role || '').trim(),
            room_id: String(cell.roomId || roomId || '').trim() || roomId,
            dungeon_id: String(cell.dungeonId || normalizedDungeonId || '').trim() || normalizedDungeonId,
            source_q: Number.isFinite(Number(cell.rawQ)) ? Number(cell.rawQ) : Number(cell.q || 0),
            source_r: Number.isFinite(Number(cell.rawR)) ? Number(cell.rawR) : Number(cell.r || 0),
            h3_resolution: toFiniteNumber(cell.h3Resolution),
            h3_index: String(cell.h3Index || '').trim(),
            center_latitude: toFiniteNumber(cell.centerLatitude),
            center_longitude: toFiniteNumber(cell.centerLongitude),
            metadata: cell && typeof cell.metadata === 'object' && !Array.isArray(cell.metadata)
              ? cell.metadata
              : {},
            aggregation_count: Number(cell.aggregationCount || 1),
            ordinal: index + 1,
            displaced: Boolean(resolved.displaced),
          });
        });
        roomAssignedCells.set(roomId, assigned);
      });

    const mapHexSize = Math.max(7, 18 - ((selectedGranularity - MIN_GRANULARITY) * 0.9));
    const pxByRoom = new Map();
    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;

    roomCenters.forEach((coord, roomId) => {
      const pos = axialToPixel(coord.q, coord.r, mapHexSize);
      pxByRoom.set(roomId, pos);
      minX = Math.min(minX, pos.x);
      maxX = Math.max(maxX, pos.x);
      minY = Math.min(minY, pos.y);
      maxY = Math.max(maxY, pos.y);
    });
    roomAssignedCells.forEach((cells) => {
      cells.forEach((cell) => {
        const pos = axialToPixel(cell.q, cell.r, mapHexSize);
        minX = Math.min(minX, pos.x);
        maxX = Math.max(maxX, pos.x);
        minY = Math.min(minY, pos.y);
        maxY = Math.max(maxY, pos.y);
      });
    });

    const pad = mapHexSize * 4.5;
    const width = Math.max(960, (maxX - minX) + (pad * 2));
    const height = Math.max(520, (maxY - minY) + (pad * 2));
    const offsetX = pad - minX;
    const offsetY = pad - minY;

    const uniqueEdges = new Set();
    const edgeLines = [];
    edges.forEach((edge) => {
      const fromRoomId = String((edge && edge.from_room_id) || '').trim();
      const toRoomId = String((edge && edge.to_room_id) || '').trim();
      if (!fromRoomId || !toRoomId) {
        return;
      }
      const from = pxByRoom.get(fromRoomId);
      const to = pxByRoom.get(toRoomId);
      if (!from || !to) {
        return;
      }
      const key = [fromRoomId, toRoomId].sort().join('|');
      if (uniqueEdges.has(key)) {
        return;
      }
      uniqueEdges.add(key);
      edgeLines.push(
        `<line class="dc-dungeon-analysis__safetymap-edge${edge && edge.is_dungeon_exit ? ' dc-dungeon-analysis__safetymap-edge--exit' : ''}" x1="${(from.x + offsetX).toFixed(2)}" y1="${(from.y + offsetY).toFixed(2)}" x2="${(to.x + offsetX).toFixed(2)}" y2="${(to.y + offsetY).toFixed(2)}"></line>`
      );
    });

    const canvasHexes = [];

    const nodeLayers = [...nodeById.entries()]
      .sort((a, b) => a[0].localeCompare(b[0]))
      .map(([roomId, node]) => {
        const centerPos = pxByRoom.get(roomId);
        const assignedCells = roomAssignedCells.get(roomId) || [];
        if (!centerPos || !assignedCells.length) {
          return '';
        }
        const roomContractSummary = summarizeNodeRoomContract(node);
        const cellPolygons = assignedCells.map((cell) => {
          const pos = axialToPixel(cell.q, cell.r, mapHexSize);
          const cx = pos.x + offsetX;
          const cy = pos.y + offsetY;
          const classes = ['dc-dungeon-analysis__safetymap-cell'];
          const cellContractSummary = summarizeCellContractMetadata(cell.metadata);
          if (!cellContractSummary) {
            throw new Error(`Dungeon analysis contract violation: missing hex_object_counts for room ${roomId} at ${Number(cell.source_q)}:${Number(cell.source_r)}.`);
          }
          const semantics = cellContractSummary;
          const hexContractLabel = String(cellContractSummary.compactLabel || 'E:0 A:0 I:0 H:0');
          const hexDetailSummary = summarizeCellHexDetailMetadata(
            cell.metadata,
            `room ${roomId} at ${Number(cell.source_q)}:${Number(cell.source_r)}`
          );
          const syntheticObjects = buildSyntheticHexObjectsForCanvas(semantics, hexDetailSummary);
          const resolvedHexStyle = resolveRoomHexStyleForAnalysis({
            terrain_type: hexDetailSummary.terrain,
            lighting: hexDetailSummary.lighting,
            elevation_ft: Number.isFinite(Number(hexDetailSummary.elevationFt)) ? Number(hexDetailSummary.elevationFt) : 0,
            is_entry: Boolean(hexDetailSummary.isEntry),
            is_visible: Boolean(hexDetailSummary.isVisible),
            is_discovered: Boolean(hexDetailSummary.isDiscovered),
            objects: syntheticObjects,
          });
          const polygonStyle = buildSvgHexStyleAttribute(resolvedHexStyle);
          const points = hexPolygonPoints(cx, cy, mapHexSize);
          const anchorOutline = roomId === anchorRoomId || String(cell.role || '').trim() === 'room_anchor'
            ? `<polygon class="dc-dungeon-analysis__safetymap-cell-outline dc-dungeon-analysis__safetymap-cell-outline--anchor" points="${points}"></polygon>`
            : '';
          const exitGatewayOutline = node && node.is_exit_gateway
            ? `<polygon class="dc-dungeon-analysis__safetymap-cell-outline dc-dungeon-analysis__safetymap-cell-outline--exit-gateway" points="${points}"></polygon>`
            : '';
          const externalOutline = node && node.is_external
            ? `<polygon class="dc-dungeon-analysis__safetymap-cell-outline dc-dungeon-analysis__safetymap-cell-outline--external" points="${points}"></polygon>`
            : '';
          if (semantics.hazards > 0) {
            classes.push('dc-dungeon-analysis__safetymap-cell--has-hazards');
          }
          if (semantics.items > 0) {
            classes.push('dc-dungeon-analysis__safetymap-cell--has-items');
          }
          if (semantics.actors > 0) {
            classes.push('dc-dungeon-analysis__safetymap-cell--has-actors');
          }
          if (semantics.exits > 0) {
            classes.push('dc-dungeon-analysis__safetymap-cell--has-exits');
          }
          const semanticBadges = buildHexSemanticBadgeOverlays(cx, cy, mapHexSize, semantics);
          const cellDungeonId = String(cell.dungeon_id || normalizedDungeonId).trim() || normalizedDungeonId;
          const cellRoomId = String(cell.room_id || roomId).trim() || roomId;
          const hexDesignation = `${cellDungeonId}:${cellRoomId}:${selectedGranularity}:${cell.q}:${cell.r}`;
          canvasHexes.push({
            q: Number(cell.q),
            r: Number(cell.r),
            hex_id: hexDesignation,
            terrain_type: hexDetailSummary.terrain,
            lighting: hexDetailSummary.lighting,
            elevation_ft: Number.isFinite(Number(hexDetailSummary.elevationFt)) ? Number(hexDetailSummary.elevationFt) : 0,
            is_entry: Boolean(hexDetailSummary.isEntry),
            is_visible: Boolean(hexDetailSummary.isVisible),
            is_discovered: Boolean(hexDetailSummary.isDiscovered),
            objects: syntheticObjects,
            analysis_room_label: toRoomLabel(node),
            analysis_room_id: cellRoomId,
            analysis_center_latitude: formatLatLng(cell.center_latitude),
            analysis_center_longitude: formatLatLng(cell.center_longitude),
            analysis_role: String(cell.role || '').trim() || 'NA',
            analysis_passability: hexDetailSummary.passability,
            analysis_connection: hexDetailSummary.connection,
            analysis_room_contract: roomContractSummary.compactLabel,
            analysis_hex_contract: hexContractLabel,
          });
          const metadataText = JSON.stringify(cell.metadata || {});
          const titleText = [
            `${toRoomLabel(node)} (${roomId})`,
            `hex_designation=${hexDesignation}`,
            `dungeon_id=${cellDungeonId}`,
            `room_id=${cellRoomId}`,
            `role=${String(cell.role || '').trim() || 'NA'}`,
            `h3_resolution=${cell.h3_resolution == null ? 'NA' : String(cell.h3_resolution)}`,
            `h3_index=${String(cell.h3_index || '').trim() || 'NA'}`,
            `global_q=${String(cell.q)}`,
            `global_r=${String(cell.r)}`,
            `source_q=${String(cell.source_q)}`,
            `source_r=${String(cell.source_r)}`,
            `terrain=${hexDetailSummary.terrain}`,
            `lighting=${hexDetailSummary.lighting}`,
            `elevation_ft=${hexDetailSummary.elevationFt}`,
            `passability=${hexDetailSummary.passability}`,
            `connection=${hexDetailSummary.connection}`,
            `objects=${hexDetailSummary.objectsLabel}`,
            `entities=${hexDetailSummary.entitiesLabel}`,
            `latitude=${formatLatLng(cell.center_latitude)}`,
            `longitude=${formatLatLng(cell.center_longitude)}`,
            `aggregation_count=${String(cell.aggregation_count || 1)}`,
            `displaced=${cell.displaced ? 'true' : 'false'}`,
            `ordinal=${String(cell.ordinal || 0)}`,
            `room_contract=${roomContractSummary.compactLabel}`,
            `hex_contract=${hexContractLabel}`,
            `metadata=${metadataText}`,
          ].join('\n');
          return `<g class="dc-dungeon-analysis__safetymap-cell-group"><polygon class="${classes.join(' ')}" style="${escapeHtml(polygonStyle)}" data-dungeon-id="${escapeHtml(cellDungeonId)}" data-room-id="${escapeHtml(cellRoomId)}" data-room-label="${escapeHtml(toRoomLabel(node))}" data-hex-designation="${escapeHtml(hexDesignation)}" data-global-q="${escapeHtml(String(cell.q))}" data-global-r="${escapeHtml(String(cell.r))}" data-latitude="${escapeHtml(formatLatLng(cell.center_latitude))}" data-longitude="${escapeHtml(formatLatLng(cell.center_longitude))}" data-role="${escapeHtml(String(cell.role || '').trim() || 'NA')}" data-room-contract="${escapeHtml(roomContractSummary.compactLabel)}" data-hex-contract="${escapeHtml(hexContractLabel)}" data-exits="${escapeHtml(String(semantics.exits))}" data-actors="${escapeHtml(String(semantics.actors))}" data-items="${escapeHtml(String(semantics.items))}" data-hazards="${escapeHtml(String(semantics.hazards))}" data-terrain="${escapeHtml(hexDetailSummary.terrain)}" data-lighting="${escapeHtml(hexDetailSummary.lighting)}" data-elevation-ft="${escapeHtml(hexDetailSummary.elevationFt)}" data-passability="${escapeHtml(hexDetailSummary.passability)}" data-connection="${escapeHtml(hexDetailSummary.connection)}" data-objects="${escapeHtml(hexDetailSummary.objectsLabel)}" data-entities="${escapeHtml(hexDetailSummary.entitiesLabel)}" points="${points}"><title>${escapeHtml(titleText)}</title></polygon>${anchorOutline}${exitGatewayOutline}${externalOutline}${semanticBadges}</g>`;
        }).join('');
        const label = toRoomLabel(node);
        const displayLabel = truncateLabel(label);
        const labelCx = centerPos.x + offsetX;
        const labelCy = centerPos.y + offsetY;
        const featureLabel = roomContractSummary.compactLabel;
        const roomContractBar = buildRoomContractBarOverlay(labelCx, labelCy, roomContractSummary);
        return `<g class="dc-dungeon-analysis__safetymap-room">
          ${cellPolygons}
          <text class="dc-dungeon-analysis__safetymap-label" x="${labelCx.toFixed(2)}" y="${(labelCy + 4).toFixed(2)}" text-anchor="middle">${escapeHtml(displayLabel)}</text>
          <text class="dc-dungeon-analysis__safetymap-feature-label" x="${labelCx.toFixed(2)}" y="${(labelCy + 18).toFixed(2)}" text-anchor="middle">${escapeHtml(featureLabel)}</text>
          ${roomContractBar}
        </g>`;
      })
      .join('');

    const svg = `<svg class="dc-dungeon-analysis__safetymap-svg" viewBox="0 0 ${width.toFixed(2)} ${height.toFixed(2)}" role="img" aria-label="Dungeon analysis map">
      <rect class="dc-dungeon-analysis__safetymap-bg" x="0" y="0" width="${width.toFixed(2)}" height="${height.toFixed(2)}"></rect>
      <text class="dc-dungeon-analysis__safetymap-meta" x="14" y="20">H3 review resolution: ${selectedGranularity} • overlap resolutions applied: ${overlapResolvedCount} • unique hexes: ${occupiedGlobalCells.size}</text>
      <g class="dc-dungeon-analysis__safetymap-edges">${edgeLines.join('')}</g>
      <g class="dc-dungeon-analysis__safetymap-nodes">${nodeLayers}</g>
    </svg>`;

    const anchorLabel = toRoomLabel(nodeById.get(anchorRoomId) || { room_id: anchorRoomId, label: anchorRoomId });
    const isWholeDungeonView = nodeById.size > 1;
    return {
      svg,
      anchorRoomId,
      anchorLabel,
      granularity: selectedGranularity,
      overlapResolvedCount,
      canvasRoom: {
        room_id: `analysis_${normalizedDungeonId}_${anchorRoomId || 'room'}`,
        name: isWholeDungeonView
          ? `${normalizedDungeonId} (Dungeon Analysis Map)`
          : `${anchorLabel || normalizedDungeonId} (Analysis)`,
        subtitle: isWholeDungeonView
          ? `H3 ${selectedGranularity} • ${nodeById.size} rooms`
          : `H3 ${selectedGranularity}`,
        hexes: canvasHexes,
      },
    };
  }

  function destroySafetyMapPanZoom() {
    if (safetyMapV2Renderer && typeof safetyMapV2Renderer.destroy === 'function') {
      safetyMapV2Renderer.destroy();
    }
    safetyMapV2Renderer = null;
    if (!safetyMapPanZoomInstance) {
      return;
    }
    if (typeof safetyMapPanZoomInstance.destroy === 'function') {
      safetyMapPanZoomInstance.destroy();
    }
    safetyMapPanZoomInstance = null;
  }

  function updateSafetyMapZoomReadout(safetyMapZoomLevelEl) {
    if (!safetyMapZoomLevelEl) {
      return;
    }
    const zoom = safetyMapV2Renderer && typeof safetyMapV2Renderer.getZoom === 'function'
      ? Number(safetyMapV2Renderer.getZoom())
      : (safetyMapPanZoomInstance && typeof safetyMapPanZoomInstance.getZoom === 'function'
      ? Number(safetyMapPanZoomInstance.getZoom())
      : 1);
    safetyMapZoomLevelEl.textContent = (Math.round(zoom * 100) / 100).toFixed(2);
  }

  function initializeSafetyMapPanZoom(safetyMapEl, debugEl, safetyMapZoomLevelEl) {
    destroySafetyMapPanZoom();
    const svg = safetyMapEl ? safetyMapEl.querySelector('svg') : null;
    if (!svg) {
      appendDebug(debugEl, 'safetymap: no svg found');
      return;
    }

    if (!window.svgPanZoom) {
      appendDebug(debugEl, 'safetymap: svg-pan-zoom library unavailable');
      return;
    }

    safetyMapPanZoomInstance = window.svgPanZoom(svg, {
      zoomEnabled: true,
      controlIconsEnabled: false,
      fit: false,
      center: false,
      minZoom: 0.2,
      maxZoom: 20,
      zoomScaleSensitivity: 0.2,
      mouseWheelZoomEnabled: true,
      dblClickZoomEnabled: true,
      panEnabled: true,
      onZoom: () => {
        updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
      },
    });
    // Start in readable local view; "Fit" remains available as an explicit action.
    safetyMapPanZoomInstance.resetZoom();
    safetyMapPanZoomInstance.center();
    updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
    appendDebug(debugEl, 'safetymap: initialized');
  }

  function runSafetyMapZoomAction(action, safetyMapZoomLevelEl) {
    if (safetyMapV2Renderer) {
      switch (action) {
        case 'in':
          safetyMapV2Renderer.zoomIn();
          updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
          return true;
        case 'out':
          safetyMapV2Renderer.zoomOut();
          updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
          return true;
        case 'fit':
          safetyMapV2Renderer.fit();
          updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
          return true;
        case 'reset':
          safetyMapV2Renderer.reset();
          updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
          return true;
        default:
          return false;
      }
    }

    if (!safetyMapPanZoomInstance) {
      return false;
    }

    switch (action) {
      case 'in':
        safetyMapPanZoomInstance.zoomIn();
        updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
        return true;
      case 'out':
        safetyMapPanZoomInstance.zoomOut();
        updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
        return true;
      case 'fit':
        safetyMapPanZoomInstance.fit();
        safetyMapPanZoomInstance.center();
        updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
        return true;
      case 'reset':
        safetyMapPanZoomInstance.resetZoom();
        safetyMapPanZoomInstance.center();
        updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
        return true;
      default:
        return false;
    }
  }

  function renderSafetyMap(
    safetyMapEl,
    safetyMapAnchorEl,
    debugEl,
    graph,
    safetyMapZoomLevelEl,
    selectedGranularityLevel,
    safetyMapGranularityEl,
    dungeonId,
    safetyMapHoverEl
  ) {
    if (!safetyMapEl) {
      return;
    }
    const rendered = buildSafetyMapSvg(graph, selectedGranularityLevel, dungeonId);
    if (!rendered.svg || !rendered.canvasRoom) {
      safetyMapEl.innerHTML = '<div class="alert alert-warning mb-0">No room graph available for dungeon analysis map rendering.</div>';
      if (safetyMapAnchorEl) {
        safetyMapAnchorEl.textContent = '-';
      }
      if (safetyMapGranularityEl) {
        safetyMapGranularityEl.textContent = '-';
      }
      destroySafetyMapPanZoom();
      updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
      setMapHoverDefault(
        safetyMapHoverEl,
        'Hover a hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
      );
      return;
    }

    if (hasV2AnalysisRenderer()) {
      if (!safetyMapV2Renderer) {
        safetyMapEl.innerHTML = '';
        safetyMapV2Renderer = window.DCAnalysisV2Bridge.createRenderer(safetyMapEl, {
          // Avoid microscopic initial fit for whole-dungeon sparse extents.
          initialFitZoomFloor: 0.08,
        });
      }
      safetyMapV2Renderer.renderRoom(rendered.canvasRoom);
      bindV2RendererHover(
        safetyMapV2Renderer,
        safetyMapHoverEl,
        'Hover a hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).',
        debugEl,
        'safetymap'
      );
      updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
    } else {
      safetyMapEl.innerHTML = rendered.svg;
      initializeSafetyMapPanZoom(safetyMapEl, debugEl, safetyMapZoomLevelEl);
      bindMapHoverInteractions(
        safetyMapEl,
        safetyMapHoverEl,
        'Hover a hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).',
        debugEl,
        'safetymap'
      );
    }
    if (safetyMapAnchorEl) {
      safetyMapAnchorEl.textContent = rendered.anchorLabel
        ? `${rendered.anchorLabel} (${rendered.anchorRoomId})`
        : '-';
    }
    if (safetyMapGranularityEl) {
      safetyMapGranularityEl.textContent = String(rendered.granularity);
    }
    appendDebug(
      debugEl,
      `safetymap: anchor=${rendered.anchorRoomId || '<none>'} granularity=${rendered.granularity} overlap_resolutions=${rendered.overlapResolvedCount || 0}`
    );
  }

  function destroyRoomExplorerPanZoom() {
    if (roomExplorerV2Renderer && typeof roomExplorerV2Renderer.destroy === 'function') {
      roomExplorerV2Renderer.destroy();
    }
    roomExplorerV2Renderer = null;
    if (!roomExplorerPanZoomInstance) {
      return;
    }
    if (typeof roomExplorerPanZoomInstance.destroy === 'function') {
      roomExplorerPanZoomInstance.destroy();
    }
    roomExplorerPanZoomInstance = null;
  }

  function updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl) {
    if (!roomExplorerZoomLevelEl) {
      return;
    }
    const zoom = roomExplorerV2Renderer && typeof roomExplorerV2Renderer.getZoom === 'function'
      ? Number(roomExplorerV2Renderer.getZoom())
      : (roomExplorerPanZoomInstance && typeof roomExplorerPanZoomInstance.getZoom === 'function'
      ? Number(roomExplorerPanZoomInstance.getZoom())
      : 1);
    roomExplorerZoomLevelEl.textContent = (Math.round(zoom * 100) / 100).toFixed(2);
  }

  function initializeRoomExplorerPanZoom(roomExplorerEl, debugEl, roomExplorerZoomLevelEl) {
    destroyRoomExplorerPanZoom();
    const svg = roomExplorerEl ? roomExplorerEl.querySelector('svg') : null;
    if (!svg) {
      appendDebug(debugEl, 'room-explorer: no svg found');
      return;
    }

    if (!window.svgPanZoom) {
      appendDebug(debugEl, 'room-explorer: svg-pan-zoom library unavailable');
      return;
    }

    roomExplorerPanZoomInstance = window.svgPanZoom(svg, {
      zoomEnabled: true,
      controlIconsEnabled: false,
      fit: false,
      center: false,
      minZoom: 0.2,
      maxZoom: 20,
      zoomScaleSensitivity: 0.2,
      mouseWheelZoomEnabled: true,
      dblClickZoomEnabled: true,
      panEnabled: true,
      onZoom: () => {
        updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
      },
    });
    // Start in readable local view; "Fit" remains available as an explicit action.
    roomExplorerPanZoomInstance.resetZoom();
    roomExplorerPanZoomInstance.center();
    updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
    appendDebug(debugEl, 'room-explorer: initialized');
  }

  function runRoomExplorerZoomAction(action, roomExplorerZoomLevelEl) {
    if (roomExplorerV2Renderer) {
      switch (action) {
        case 'in':
          roomExplorerV2Renderer.zoomIn();
          updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
          return true;
        case 'out':
          roomExplorerV2Renderer.zoomOut();
          updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
          return true;
        case 'fit':
          roomExplorerV2Renderer.fit();
          updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
          return true;
        case 'reset':
          roomExplorerV2Renderer.reset();
          updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
          return true;
        default:
          return false;
      }
    }

    if (!roomExplorerPanZoomInstance) {
      return false;
    }

    switch (action) {
      case 'in':
        roomExplorerPanZoomInstance.zoomIn();
        updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
        return true;
      case 'out':
        roomExplorerPanZoomInstance.zoomOut();
        updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
        return true;
      case 'fit':
        roomExplorerPanZoomInstance.fit();
        roomExplorerPanZoomInstance.center();
        updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
        return true;
      case 'reset':
        roomExplorerPanZoomInstance.resetZoom();
        roomExplorerPanZoomInstance.center();
        updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
        return true;
      default:
        return false;
    }
  }

  function renderRoomExplorerMap(
    roomExplorerEl,
    roomExplorerRoomReadoutEl,
    debugEl,
    graph,
    requestedRoomId,
    roomExplorerZoomLevelEl,
    selectedGranularityLevel,
    roomExplorerGranularityEl,
    dungeonId,
    roomExplorerHoverEl
  ) {
    if (!roomExplorerEl) {
      return '';
    }

    const resolvedRoomId = resolveRoomExplorerSelection(graph, requestedRoomId);
    const roomGraph = buildSingleRoomGraph(graph, resolvedRoomId);
    const rendered = buildSafetyMapSvg(roomGraph, selectedGranularityLevel, dungeonId);
    if (!rendered.svg || !rendered.canvasRoom) {
      roomExplorerEl.innerHTML = '<div class="alert alert-warning mb-0">No room graph available for room explorer rendering.</div>';
      if (roomExplorerRoomReadoutEl) {
        roomExplorerRoomReadoutEl.textContent = '-';
      }
      if (roomExplorerGranularityEl) {
        roomExplorerGranularityEl.textContent = '-';
      }
      destroyRoomExplorerPanZoom();
      updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
      setMapHoverDefault(
        roomExplorerHoverEl,
        'Hover a room hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
      );
      return '';
    }

    if (hasV2AnalysisRenderer()) {
      if (!roomExplorerV2Renderer) {
        roomExplorerEl.innerHTML = '';
        roomExplorerV2Renderer = window.DCAnalysisV2Bridge.createRenderer(roomExplorerEl, {
          // Single-room view can start closer while preserving fit controls.
          initialFitZoomFloor: 0.18,
        });
      }
      roomExplorerV2Renderer.renderRoom(rendered.canvasRoom);
      bindV2RendererHover(
        roomExplorerV2Renderer,
        roomExplorerHoverEl,
        'Hover a room hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).',
        debugEl,
        'room-explorer'
      );
      updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
    } else {
      roomExplorerEl.innerHTML = rendered.svg;
      initializeRoomExplorerPanZoom(roomExplorerEl, debugEl, roomExplorerZoomLevelEl);
      bindMapHoverInteractions(
        roomExplorerEl,
        roomExplorerHoverEl,
        'Hover a room hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).',
        debugEl,
        'room-explorer'
      );
    }
    if (roomExplorerRoomReadoutEl) {
      roomExplorerRoomReadoutEl.textContent = rendered.anchorLabel
        ? `${rendered.anchorLabel} (${rendered.anchorRoomId})`
        : '-';
    }
    if (roomExplorerGranularityEl) {
      roomExplorerGranularityEl.textContent = String(rendered.granularity);
    }
    appendDebug(
      debugEl,
      `room-explorer: room=${rendered.anchorRoomId || '<none>'} granularity=${rendered.granularity} overlap_resolutions=${rendered.overlapResolvedCount || 0}`
    );
    return resolvedRoomId;
  }

  function setStatus(statusEl, message, isError = false) {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = message;
    statusEl.classList.toggle('dc-dungeon-analysis__status--error', isError);
  }

  function appendDebug(debugEl, message) {
    if (!debugEl) {
      return;
    }
    const line = `[${new Date().toISOString()}] ${message}`;
    debugEl.textContent = debugEl.textContent
      ? `${debugEl.textContent}\n${line}`
      : line;
    debugEl.scrollTop = debugEl.scrollHeight;
  }

  function buildEdgeRows(edges, roomLookup) {
    return edges
      .filter((edge) => Boolean(edge))
      .map((edge) => {
        const fromRoomId = String((edge && edge.from_room_id) || '').trim();
        const toRoomId = String((edge && edge.to_room_id) || '').trim();
        const fromLabel = roomLookup.get(fromRoomId) || fromRoomId;
        const toLabel = roomLookup.get(toRoomId) || toRoomId;
        const type = String((edge && edge.type) || 'passage');
        const direction = String((edge && edge.direction) || 'internal');
        const isExit = Boolean(edge && edge.is_dungeon_exit);
        const kind = isExit ? `dungeon-exit (${direction})` : 'internal';

        return `<tr class="${isExit ? 'dc-dungeon-analysis__edge-row--exit' : ''}">
          <td><strong>${escapeHtml(fromLabel)}</strong><br><small class="text-muted">${escapeHtml(fromRoomId)}</small></td>
          <td>${escapeHtml(toLabel)}<br><small class="text-muted">${escapeHtml(toRoomId)}</small></td>
          <td>${escapeHtml(type)}</td>
          <td>${escapeHtml(kind)}</td>
        </tr>`;
      })
      .join('');
  }

  function renderGraphReview(summaryEl, exitsEl, edgesEl, graph) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    const edges = graph && Array.isArray(graph.edges) ? graph.edges : [];
    const roomLookup = roomLabelLookup(graph);
    const exitEdges = edges.filter((edge) => Boolean(edge && edge.is_dungeon_exit));
    const externalRooms = nodes.filter((node) => Boolean(node && node.is_external));
    const exitGateways = nodes.filter((node) => Boolean(node && node.is_exit_gateway));

    if (summaryEl) {
      summaryEl.innerHTML = `
        <div class="dc-dungeon-analysis__summary-grid">
          <span class="badge text-bg-light">Rooms: ${nodes.length}</span>
          <span class="badge text-bg-light">Connections: ${edges.length}</span>
          <span class="badge text-bg-warning">Dungeon exits: ${exitEdges.length}</span>
          <span class="badge text-bg-info">Exit gateways: ${exitGateways.length}</span>
          <span class="badge text-bg-secondary">External rooms: ${externalRooms.length}</span>
        </div>
      `;
    }

    if (exitsEl) {
      if (!exitEdges.length) {
        exitsEl.innerHTML = '<p class="mb-0 text-muted">No cross-dungeon exits detected in this dungeon graph.</p>';
      } else {
        exitsEl.innerHTML = `
          <details open class="dc-dungeon-analysis__details">
            <summary class="fw-semibold">Dungeon exits and targets (${exitEdges.length})</summary>
            <div class="table-responsive mt-2">
              <table class="table table-sm table-striped align-middle mb-0">
                <thead><tr><th>From room</th><th>Connects to</th><th>Type</th><th>Kind</th></tr></thead>
                <tbody>${buildEdgeRows(exitEdges, roomLookup)}</tbody>
              </table>
            </div>
          </details>
        `;
      }
    }

    if (edgesEl) {
      edgesEl.innerHTML = `
        <details class="dc-dungeon-analysis__details">
          <summary class="fw-semibold">All room connections (${edges.length})</summary>
          <div class="table-responsive mt-2">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead><tr><th>From room</th><th>Connects to</th><th>Type</th><th>Kind</th></tr></thead>
              <tbody>${buildEdgeRows(edges, roomLookup)}</tbody>
            </table>
          </div>
        </details>
      `;
    }
  }

  function clearGraphReview(summaryEl, exitsEl, edgesEl) {
    if (summaryEl) {
      summaryEl.innerHTML = '';
    }
    if (exitsEl) {
      exitsEl.innerHTML = '';
    }
    if (edgesEl) {
      edgesEl.innerHTML = '';
    }
  }

  function clampGranularity(level) {
    const numeric = Number(level);
    if (!Number.isFinite(numeric)) {
      return 7;
    }
    return Math.max(MIN_GRANULARITY, Math.min(MAX_GRANULARITY, Math.round(numeric)));
  }

  function getGranularityDescription(level) {
    return GRANULARITY_LABELS[level] || 'unknown';
  }

  function getZoomFactorForGranularity(level) {
    return GRANULARITY_ZOOM_FACTORS[level] || GRANULARITY_ZOOM_FACTORS[7];
  }

  function getOptimalGranularityForZoom(zoomValue) {
    if (!Number.isFinite(zoomValue)) {
      return 7;
    }
    if (zoomValue <= 0.425) return 5;
    if (zoomValue <= 0.6) return 6;
    if (zoomValue <= 0.8) return 7;
    if (zoomValue <= 1.025) return 8;
    if (zoomValue <= 1.3) return 9;
    if (zoomValue <= 1.625) return 10;
    if (zoomValue <= 2.0) return 11;
    if (zoomValue <= 2.45) return 12;
    if (zoomValue <= 3.0) return 13;
    if (zoomValue <= 3.65) return 14;
    return 15;
  }

  function resolveSelectedGranularity(granularitySelectEls) {
    if (manualGranularityLevel !== null) {
      return clampGranularity(manualGranularityLevel);
    }
    const selects = Array.isArray(granularitySelectEls) ? granularitySelectEls : [];
    for (const selectEl of selects) {
      if (!selectEl) {
        continue;
      }
      const value = String(selectEl.value || '').trim();
      if (value !== '') {
        return clampGranularity(value);
      }
    }
    return DEFAULT_GRANULARITY;
  }

  function updateGranularityReadout(zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl) {
    const zoom = panZoomInstance && typeof panZoomInstance.getZoom === 'function'
      ? Number(panZoomInstance.getZoom())
      : 1;
    const effectiveGranularity = resolveSelectedGranularity(granularitySelectEls);

    if (zoomLevelEl) {
      zoomLevelEl.textContent = (Math.round(zoom * 100) / 100).toFixed(2);
    }
    if (granularityEl) {
      granularityEl.textContent = String(effectiveGranularity);
    }
    if (granularityLabelEl) {
      granularityLabelEl.textContent = getGranularityDescription(effectiveGranularity);
    }
    const selects = Array.isArray(granularitySelectEls) ? granularitySelectEls : [];
    const selectedValue = String(effectiveGranularity);
    selects.forEach((selectEl) => {
      if (selectEl && selectEl.value !== selectedValue) {
        selectEl.value = selectedValue;
      }
    });
    if (safetyMapGranularityEl) {
      safetyMapGranularityEl.textContent = selectedValue;
    }
  }

  function applyGranularityLevel(level, zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl) {
    const normalized = clampGranularity(level);
    manualGranularityLevel = normalized;
    if (panZoomInstance && typeof panZoomInstance.zoom === 'function') {
      const targetZoom = getZoomFactorForGranularity(normalized);
      panZoomInstance.zoom(targetZoom);
      if (typeof panZoomInstance.center === 'function') {
        panZoomInstance.center();
      }
    }
    updateGranularityReadout(
      zoomLevelEl,
      granularityEl,
      granularityLabelEl,
      granularitySelectEls,
      safetyMapGranularityEl
    );
    return Boolean(panZoomInstance);
  }

  function destroyPanZoom() {
    if (!panZoomInstance) {
      return;
    }
    if (typeof panZoomInstance.destroy === 'function') {
      panZoomInstance.destroy();
    }
    panZoomInstance = null;
  }

  function initializePanZoom(diagramEl, debugEl, zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl) {
    destroyPanZoom();
    const svg = diagramEl ? diagramEl.querySelector('svg') : null;
    if (!svg) {
      appendDebug(debugEl, 'panzoom: no svg found');
      return;
    }

    if (!window.svgPanZoom) {
      appendDebug(debugEl, 'panzoom: svg-pan-zoom library unavailable');
      return;
    }

    panZoomInstance = window.svgPanZoom(svg, {
      zoomEnabled: true,
      controlIconsEnabled: false,
      fit: true,
      center: true,
      minZoom: 0.2,
      maxZoom: 20,
      zoomScaleSensitivity: 0.2,
      mouseWheelZoomEnabled: true,
      dblClickZoomEnabled: true,
      panEnabled: true,
      onZoom: () => {
        updateGranularityReadout(
          zoomLevelEl,
          granularityEl,
          granularityLabelEl,
          granularitySelectEls,
          safetyMapGranularityEl
        );
      },
    });
    panZoomInstance.fit();
    panZoomInstance.center();
    if (manualGranularityLevel !== null) {
      applyGranularityLevel(
        manualGranularityLevel,
        zoomLevelEl,
        granularityEl,
        granularityLabelEl,
        granularitySelectEls,
        safetyMapGranularityEl
      );
    } else {
      updateGranularityReadout(
        zoomLevelEl,
        granularityEl,
        granularityLabelEl,
        granularitySelectEls,
        safetyMapGranularityEl
      );
    }
    appendDebug(debugEl, 'panzoom: initialized');
  }

  function runZoomAction(action, zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl) {
    if (!panZoomInstance) {
      return false;
    }
    switch (action) {
      case 'in':
        panZoomInstance.zoomIn();
        updateGranularityReadout(zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl);
        return true;
      case 'out':
        panZoomInstance.zoomOut();
        updateGranularityReadout(zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl);
        return true;
      case 'fit':
        panZoomInstance.fit();
        panZoomInstance.center();
        updateGranularityReadout(zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl);
        return true;
      case 'reset':
        panZoomInstance.resetZoom();
        panZoomInstance.center();
        updateGranularityReadout(zoomLevelEl, granularityEl, granularityLabelEl, granularitySelectEls, safetyMapGranularityEl);
        return true;
      default:
        return false;
    }
  }

  async function renderMermaidGraph(container, source) {
    if (!window.mermaid) {
      throw new Error('Mermaid library failed to load.');
    }
    if (!mermaidInitialized) {
      window.mermaid.initialize(buildMermaidTheme(container.closest('.dc-dungeon-analysis')));
      mermaidInitialized = true;
    }

    const renderId = `dc-dungeon-analysis-${++renderCounter}`;
    const result = await window.mermaid.render(renderId, source);
    container.innerHTML = result.svg;
  }

  Drupal.behaviors.dcDungeonAnalysis = {
    attach(context) {
      const root = once('dc-dungeon-analysis', '.dc-dungeon-analysis', context)[0];
      if (!root) {
        return;
      }
      applySharedSemanticPalette(root);

      const settings = drupalSettings
        && drupalSettings.dungeoncrawler_content
        && drupalSettings.dungeoncrawler_content.dungeonAnalysis
        ? drupalSettings.dungeoncrawler_content.dungeonAnalysis
        : {};
      const dungeons = Array.isArray(settings.dungeons) ? settings.dungeons : [];
      const defaultDungeonId = String(settings.defaultDungeonId || '').trim();
      const apiUrlPattern = String(settings.apiUrlPattern || '').trim();
      const selectEl = root.querySelector('#dc-dungeon-analysis-select');
      const statusEl = root.querySelector('#dc-dungeon-analysis-status');
      const summaryEl = root.querySelector('#dc-dungeon-analysis-summary');
      const exitsEl = root.querySelector('#dc-dungeon-analysis-exits');
      const edgesEl = root.querySelector('#dc-dungeon-analysis-edges');
      const diagramEl = root.querySelector('#dc-dungeon-analysis-diagram');
      const safetyMapEl = root.querySelector('#dc-dungeon-analysis-safetymap');
      const safetyMapAnchorEl = root.querySelector('#dc-analysis-safetymap-anchor');
      const safetyMapZoomLevelEl = root.querySelector('#dc-analysis-safetymap-zoom-level');
      const safetyMapGranularityEl = root.querySelector('#dc-analysis-safetymap-granularity');
      const safetyMapHoverEl = root.querySelector('#dc-analysis-safetymap-hover');
      const roomExplorerEl = root.querySelector('#dc-dungeon-analysis-room-explorer');
      const roomExplorerRoomReadoutEl = root.querySelector('#dc-analysis-room-explorer-room');
      const roomExplorerZoomLevelEl = root.querySelector('#dc-analysis-room-explorer-zoom-level');
      const roomExplorerGranularityEl = root.querySelector('#dc-analysis-room-explorer-granularity');
      const roomExplorerHoverEl = root.querySelector('#dc-analysis-room-explorer-hover');
      const roomExplorerRoomSelectEl = root.querySelector('#dc-room-explorer-room-select');
      const debugEl = root.querySelector('#dc-dungeon-analysis-debug');
      const zoomLevelEl = root.querySelector('#dc-analysis-zoom-level');
      const granularityEl = root.querySelector('#dc-analysis-granularity');
      const granularityLabelEl = root.querySelector('#dc-analysis-granularity-label');
      const safetyMapGranularitySelectEl = root.querySelector('#dc-safetymap-granularity-select');
      const roomExplorerGranularitySelectEl = root.querySelector('#dc-room-explorer-granularity-select');
      const granularityToolbarSelectEl = root.querySelector('#dc-analysis-granularity-toolbar-select');
      const controlsGranularitySelectEl = root.querySelector('#dc-dungeon-analysis-granularity-select');
      const granularitySelectEls = [
        safetyMapGranularitySelectEl,
        roomExplorerGranularitySelectEl,
        granularityToolbarSelectEl,
        controlsGranularitySelectEl,
      ]
        .filter(Boolean);
      const granularityDecreaseEl = root.querySelector('#dc-granularity-decrease');
      const granularityIncreaseEl = root.querySelector('#dc-granularity-increase');
      let activeGraph = null;
      let activeRoomExplorerRoomId = '';

      appendDebug(debugEl, `attach: options=${dungeons.length} default=${defaultDungeonId || '<none>'} api=${apiUrlPattern || '<missing>'}`);

      if (!selectEl || !diagramEl) {
        setStatus(statusEl, 'Dungeon analysis page contract violation: required UI elements are missing.', true);
        appendDebug(debugEl, `error: missing required elements select=${!!selectEl} diagram=${!!diagramEl}`);
        return;
      }

      if (!apiUrlPattern || !dungeons.length) {
        setStatus(statusEl, 'No canonical dungeons are available.', true);
        clearGraphReview(summaryEl, exitsEl, edgesEl);
        if (safetyMapEl) {
          safetyMapEl.innerHTML = '';
        }
        if (safetyMapAnchorEl) {
          safetyMapAnchorEl.textContent = '-';
        }
        destroySafetyMapPanZoom();
        setMapHoverDefault(
          safetyMapHoverEl,
          'Hover a hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
        );
        if (roomExplorerEl) {
          roomExplorerEl.innerHTML = '';
        }
        if (roomExplorerRoomReadoutEl) {
          roomExplorerRoomReadoutEl.textContent = '-';
        }
        if (roomExplorerGranularityEl) {
          roomExplorerGranularityEl.textContent = '-';
        }
        if (roomExplorerRoomSelectEl) {
          roomExplorerRoomSelectEl.innerHTML = '';
        }
        destroyRoomExplorerPanZoom();
        setMapHoverDefault(
          roomExplorerHoverEl,
          'Hover a room hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
        );
        appendDebug(debugEl, 'error: no dungeons or missing api URL pattern');
        return;
      }

      selectEl.innerHTML = '';
      dungeons.forEach((dungeon) => {
        const dungeonId = String((dungeon && dungeon.dungeon_id) || '').trim();
        if (!dungeonId) {
          return;
        }
        const label = String((dungeon && dungeon.name) || dungeonId);
        const parsedRoomCount = Number((dungeon && dungeon.room_count) || 0);
        const parsedEdgeCount = Number((dungeon && dungeon.edge_count) || 0);
        const edgeSourceLabel = String((dungeon && dungeon.edge_source_label) || (dungeon && dungeon.edge_source) || 'unknown');
        const roomCount = Number.isFinite(parsedRoomCount) ? parsedRoomCount : 0;
        const edgeCount = Number.isFinite(parsedEdgeCount) ? parsedEdgeCount : 0;
        const option = document.createElement('option');
        option.value = dungeonId;
        option.textContent = `${label} (${dungeonId}) — ${roomCount} rooms / ${edgeCount} exits [${edgeSourceLabel}]`;
        if (dungeonId === defaultDungeonId) {
          option.selected = true;
        }
        selectEl.appendChild(option);
      });

      root.querySelectorAll('[data-dc-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = String(button.getAttribute('data-dc-zoom') || '').trim();
          const handled = runZoomAction(
            action,
            zoomLevelEl,
            granularityEl,
            granularityLabelEl,
            granularitySelectEls,
            safetyMapGranularityEl
          );
          if (!handled) {
            appendDebug(debugEl, `zoom ignored: action=${action || '<empty>'}`);
          }
        });
      });

      root.querySelectorAll('[data-dc-safetymap-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = String(button.getAttribute('data-dc-safetymap-zoom') || '').trim();
          const handled = runSafetyMapZoomAction(action, safetyMapZoomLevelEl);
          if (!handled) {
            appendDebug(debugEl, `safetymap zoom ignored: action=${action || '<empty>'}`);
          }
        });
      });

      root.querySelectorAll('[data-dc-room-explorer-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = String(button.getAttribute('data-dc-room-explorer-zoom') || '').trim();
          const handled = runRoomExplorerZoomAction(action, roomExplorerZoomLevelEl);
          if (!handled) {
            appendDebug(debugEl, `room-explorer zoom ignored: action=${action || '<empty>'}`);
          }
        });
      });

      if (roomExplorerRoomSelectEl) {
        roomExplorerRoomSelectEl.addEventListener('change', () => {
          if (!activeGraph) {
            return;
          }
          const selectedRoomId = String(roomExplorerRoomSelectEl.value || '').trim();
          activeRoomExplorerRoomId = renderRoomExplorerMap(
            roomExplorerEl,
            roomExplorerRoomReadoutEl,
            debugEl,
            activeGraph,
            selectedRoomId,
            roomExplorerZoomLevelEl,
            resolveSelectedGranularity(granularitySelectEls),
            roomExplorerGranularityEl,
            String(selectEl.value || defaultDungeonId || '').trim(),
            roomExplorerHoverEl
          ) || selectedRoomId;
          appendDebug(debugEl, `room-explorer selection changed: ${activeRoomExplorerRoomId || '<none>'}`);
        });
      }

      if (granularityDecreaseEl) {
        granularityDecreaseEl.addEventListener('click', () => {
          const current = resolveSelectedGranularity(granularitySelectEls);
          const next = clampGranularity(current - 1);
          if (next === current) {
            return;
          }
          applyGranularityLevel(
            next,
            zoomLevelEl,
            granularityEl,
            granularityLabelEl,
            granularitySelectEls,
            safetyMapGranularityEl
          );
          if (activeGraph) {
            renderSafetyMap(
              safetyMapEl,
              safetyMapAnchorEl,
              debugEl,
              activeGraph,
              safetyMapZoomLevelEl,
              next,
              safetyMapGranularityEl,
              String(selectEl.value || defaultDungeonId || '').trim(),
              safetyMapHoverEl
            );
            activeRoomExplorerRoomId = renderRoomExplorerMap(
              roomExplorerEl,
              roomExplorerRoomReadoutEl,
              debugEl,
              activeGraph,
              roomExplorerRoomSelectEl ? roomExplorerRoomSelectEl.value : activeRoomExplorerRoomId,
              roomExplorerZoomLevelEl,
              next,
              roomExplorerGranularityEl,
              String(selectEl.value || defaultDungeonId || '').trim(),
              roomExplorerHoverEl
            ) || activeRoomExplorerRoomId;
          }
          appendDebug(debugEl, `granularity decreased: ${next}`);
        });
      }

      if (granularityIncreaseEl) {
        granularityIncreaseEl.addEventListener('click', () => {
          const current = resolveSelectedGranularity(granularitySelectEls);
          const next = clampGranularity(current + 1);
          if (next === current) {
            return;
          }
          applyGranularityLevel(
            next,
            zoomLevelEl,
            granularityEl,
            granularityLabelEl,
            granularitySelectEls,
            safetyMapGranularityEl
          );
          if (activeGraph) {
            renderSafetyMap(
              safetyMapEl,
              safetyMapAnchorEl,
              debugEl,
              activeGraph,
              safetyMapZoomLevelEl,
              next,
              safetyMapGranularityEl,
              String(selectEl.value || defaultDungeonId || '').trim(),
              safetyMapHoverEl
            );
            activeRoomExplorerRoomId = renderRoomExplorerMap(
              roomExplorerEl,
              roomExplorerRoomReadoutEl,
              debugEl,
              activeGraph,
              roomExplorerRoomSelectEl ? roomExplorerRoomSelectEl.value : activeRoomExplorerRoomId,
              roomExplorerZoomLevelEl,
              next,
              roomExplorerGranularityEl,
              String(selectEl.value || defaultDungeonId || '').trim(),
              roomExplorerHoverEl
            ) || activeRoomExplorerRoomId;
          }
          appendDebug(debugEl, `granularity increased: ${next}`);
        });
      }

      if (granularitySelectEls.length > 0) {
        const startingGranularity = clampGranularity(granularitySelectEls[0].value || DEFAULT_GRANULARITY);
        manualGranularityLevel = startingGranularity;
        granularitySelectEls.forEach((selectNode) => {
          selectNode.value = String(startingGranularity);
          selectNode.addEventListener('change', () => {
            const selectedLevel = clampGranularity(selectNode.value);
            applyGranularityLevel(
              selectedLevel,
              zoomLevelEl,
              granularityEl,
              granularityLabelEl,
              granularitySelectEls,
              safetyMapGranularityEl
            );
            if (activeGraph) {
              renderSafetyMap(
                safetyMapEl,
                safetyMapAnchorEl,
                debugEl,
                activeGraph,
                safetyMapZoomLevelEl,
                selectedLevel,
                safetyMapGranularityEl,
                String(selectEl.value || defaultDungeonId || '').trim(),
                safetyMapHoverEl
              );
              activeRoomExplorerRoomId = renderRoomExplorerMap(
                roomExplorerEl,
                roomExplorerRoomReadoutEl,
                debugEl,
                activeGraph,
                roomExplorerRoomSelectEl ? roomExplorerRoomSelectEl.value : activeRoomExplorerRoomId,
                roomExplorerZoomLevelEl,
                selectedLevel,
                roomExplorerGranularityEl,
                String(selectEl.value || defaultDungeonId || '').trim(),
                roomExplorerHoverEl
              ) || activeRoomExplorerRoomId;
            }
            appendDebug(debugEl, `granularity selected: ${selectedLevel}`);
          });
        });
      } else if (safetyMapGranularityEl) {
        safetyMapGranularityEl.textContent = String(resolveSelectedGranularity([]));
      }

      updateGranularityReadout(
        zoomLevelEl,
        granularityEl,
        granularityLabelEl,
        granularitySelectEls,
        safetyMapGranularityEl
      );

      const loadGraph = async (dungeonId) => {
        const selected = String(dungeonId || '').trim();
        if (!selected) {
          activeGraph = null;
          activeRoomExplorerRoomId = '';
          setStatus(statusEl, 'Select a canonical dungeon.', true);
          clearGraphReview(summaryEl, exitsEl, edgesEl);
          if (safetyMapEl) {
            safetyMapEl.innerHTML = '';
          }
          if (safetyMapAnchorEl) {
            safetyMapAnchorEl.textContent = '-';
          }
          if (safetyMapGranularityEl) {
            safetyMapGranularityEl.textContent = '-';
          }
          destroySafetyMapPanZoom();
          if (roomExplorerEl) {
            roomExplorerEl.innerHTML = '';
          }
          if (roomExplorerRoomReadoutEl) {
            roomExplorerRoomReadoutEl.textContent = '-';
          }
          if (roomExplorerGranularityEl) {
            roomExplorerGranularityEl.textContent = '-';
          }
          if (roomExplorerRoomSelectEl) {
            roomExplorerRoomSelectEl.innerHTML = '';
          }
          destroyRoomExplorerPanZoom();
          appendDebug(debugEl, 'error: empty selection');
          return;
        }

        setStatus(statusEl, `Loading ${selected}...`);
        clearGraphReview(summaryEl, exitsEl, edgesEl);
        diagramEl.innerHTML = '';
        destroyPanZoom();
        if (safetyMapEl) {
          safetyMapEl.innerHTML = '<div class="text-muted">Loading dungeon analysis map…</div>';
        }
        if (safetyMapAnchorEl) {
          safetyMapAnchorEl.textContent = '-';
        }
        destroySafetyMapPanZoom();
        updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
        setMapHoverDefault(
          safetyMapHoverEl,
          'Hover a hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
        );
        if (safetyMapGranularityEl) {
          safetyMapGranularityEl.textContent = String(resolveSelectedGranularity(granularitySelectEls));
        }
        if (roomExplorerEl) {
          roomExplorerEl.innerHTML = '<div class="text-muted">Loading room explorer…</div>';
        }
        if (roomExplorerRoomReadoutEl) {
          roomExplorerRoomReadoutEl.textContent = '-';
        }
        if (roomExplorerGranularityEl) {
          roomExplorerGranularityEl.textContent = String(resolveSelectedGranularity(granularitySelectEls));
        }
        destroyRoomExplorerPanZoom();
        updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
        setMapHoverDefault(
          roomExplorerHoverEl,
          'Hover a room hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
        );
        appendDebug(debugEl, `loadGraph: ${selected}`);

        let timeoutId = 0;
        try {
          const url = apiUrlPattern.replace('__DUNGEON_ID__', encodeURIComponent(selected));
          const abortController = new AbortController();
          timeoutId = window.setTimeout(() => abortController.abort(), 20000);
          const response = await fetch(url, {
            method: 'GET',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: abortController.signal,
          });
          appendDebug(debugEl, `response: ${response.status} ${response.statusText}`);
          const payload = await response.json().catch(() => ({}));
          if (!response.ok || !payload || !payload.success || !payload.graph) {
            appendDebug(debugEl, `error: fetch failed status=${response.status}`);
            throw new Error(String((payload && payload.error) || 'Unable to load dungeon graph.'));
          }
          activeGraph = payload.graph;

          const source = buildMermaidSource(payload.graph);
          appendDebug(
            debugEl,
            `graph payload: nodes=${Array.isArray(payload.graph.nodes) ? payload.graph.nodes.length : 0} edges=${Array.isArray(payload.graph.edges) ? payload.graph.edges.length : 0}`
          );
          renderGraphReview(summaryEl, exitsEl, edgesEl, payload.graph);
          renderSafetyMap(
            safetyMapEl,
            safetyMapAnchorEl,
            debugEl,
            payload.graph,
            safetyMapZoomLevelEl,
            resolveSelectedGranularity(granularitySelectEls),
            safetyMapGranularityEl,
            selected,
            safetyMapHoverEl
          );
          activeRoomExplorerRoomId = populateRoomExplorerRoomOptions(
            roomExplorerRoomSelectEl,
            payload.graph,
            activeRoomExplorerRoomId
          );
          activeRoomExplorerRoomId = renderRoomExplorerMap(
            roomExplorerEl,
            roomExplorerRoomReadoutEl,
            debugEl,
            payload.graph,
            roomExplorerRoomSelectEl ? roomExplorerRoomSelectEl.value : activeRoomExplorerRoomId,
            roomExplorerZoomLevelEl,
            resolveSelectedGranularity(granularitySelectEls),
            roomExplorerGranularityEl,
            selected,
            roomExplorerHoverEl
          ) || activeRoomExplorerRoomId;
          if (roomExplorerRoomSelectEl && activeRoomExplorerRoomId) {
            roomExplorerRoomSelectEl.value = activeRoomExplorerRoomId;
          }

          try {
            await renderMermaidGraph(diagramEl, source);
            initializePanZoom(
              diagramEl,
              debugEl,
              zoomLevelEl,
              granularityEl,
              granularityLabelEl,
              granularitySelectEls,
              safetyMapGranularityEl
            );
          } catch (renderError) {
            diagramEl.innerHTML = `<div class="alert alert-warning mb-0">Topology diagram failed to render, but connection data loaded below. ${escapeHtml(String(renderError?.message || renderError))}</div>`;
            appendDebug(debugEl, `mermaid-render-error: ${String(renderError?.message || renderError)}`);
          }

          const nodeCount = Array.isArray(payload.graph.nodes) ? payload.graph.nodes.length : 0;
          const edgeCount = Array.isArray(payload.graph.edges) ? payload.graph.edges.length : 0;
          const exitCount = Array.isArray(payload.graph.edges)
            ? payload.graph.edges.filter((edge) => Boolean(edge && edge.is_dungeon_exit)).length
            : 0;
          const edgeSource = String((payload.graph && payload.graph.edge_source) || '').trim();
          const sparseSummary = payload.graph && payload.graph.sparse_h3_summary ? payload.graph.sparse_h3_summary : null;
          const sparseNodeAnchors = sparseSummary && Number.isFinite(Number(sparseSummary.nodes_with_anchor))
            ? Number(sparseSummary.nodes_with_anchor)
            : 0;
          const sparseTotalCells = sparseSummary && Number.isFinite(Number(sparseSummary.total_cells))
            ? Number(sparseSummary.total_cells)
            : 0;
          const dungeonName = payload && payload.dungeon ? payload.dungeon.name : selected;
          setStatus(
            statusEl,
            `${dungeonName || selected}: ${nodeCount} rooms, ${edgeCount} connections, ${exitCount} dungeon exits [${edgeSource || 'unknown source'}]. Sparse H3 anchors: ${sparseNodeAnchors}/${nodeCount}; cells: ${sparseTotalCells}.`
          );
        } catch (error) {
          activeGraph = null;
          activeRoomExplorerRoomId = '';
          setStatus(statusEl, String(error?.message || 'Failed to render dungeon graph.'), true);
          clearGraphReview(summaryEl, exitsEl, edgesEl);
          if (safetyMapEl) {
            safetyMapEl.innerHTML = '<div class="alert alert-warning mb-0">Dungeon analysis map failed to load for this dungeon.</div>';
          }
          if (safetyMapAnchorEl) {
            safetyMapAnchorEl.textContent = '-';
          }
          if (safetyMapGranularityEl) {
            safetyMapGranularityEl.textContent = '-';
          }
          destroySafetyMapPanZoom();
          updateSafetyMapZoomReadout(safetyMapZoomLevelEl);
          setMapHoverDefault(
            safetyMapHoverEl,
            'Hover a hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
          );
          if (roomExplorerEl) {
            roomExplorerEl.innerHTML = '<div class="alert alert-warning mb-0">Room explorer failed to load for this dungeon.</div>';
          }
          if (roomExplorerRoomReadoutEl) {
            roomExplorerRoomReadoutEl.textContent = '-';
          }
          if (roomExplorerGranularityEl) {
            roomExplorerGranularityEl.textContent = '-';
          }
          if (roomExplorerRoomSelectEl) {
            roomExplorerRoomSelectEl.innerHTML = '';
          }
          destroyRoomExplorerPanZoom();
          updateRoomExplorerZoomReadout(roomExplorerZoomLevelEl);
          setMapHoverDefault(
            roomExplorerHoverEl,
            'Hover a room hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).'
          );
          appendDebug(debugEl, `error: ${String(error?.message || error)}`);
        } finally {
          if (timeoutId) {
            window.clearTimeout(timeoutId);
          }
        }
      };

      selectEl.addEventListener('change', () => {
        appendDebug(debugEl, `selection changed: ${selectEl.value}`);
        void loadGraph(selectEl.value);
      });

      const initialId = String(selectEl.value || defaultDungeonId || '').trim();
      if (initialId) {
        appendDebug(debugEl, `initial load: ${initialId}`);
        void loadGraph(initialId);
      } else {
        appendDebug(debugEl, 'warning: no initial selection');
      }
    },
  };
})(Drupal, drupalSettings, once);
