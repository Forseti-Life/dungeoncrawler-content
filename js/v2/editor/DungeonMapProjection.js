import './placementTransform.js?v=20261128-child-validation-1';

const transform = globalThis.DungeonCrawlerPlacementTransform;
if (!transform) {
  throw new Error('placement_transform_unavailable');
}

/**
 * Projects one dungeon read model into the HexCanvas map contract.
 */
export function projectDungeonMap(model, {
  coordinateSpec = null,
  basePlacementId = null,
  baseLayer = () => false,
  tint = () => 0x64748b,
  occupancy = null,
  portHighlight = () => null,
} = {}) {
  const placements = [];
  const ports = [];
  const portIndex = new Map();
  const projectedPlacements = new Map();
  const linkedExitPorts = new Set((model?.port_links || [])
    .map((link) => `${link.from.placement_id}:${link.from.port_id}`));
  const projectedOccupancy = {};
  const toMapHex = (level) => coordinateSpec ? transform.toRoomLocal(level, coordinateSpec) : level;
  const toMapEdge = (edge) => coordinateSpec
    ? transform.rotateEdge(Number(edge), 6 - Number(coordinateSpec.rotation_steps || 0))
    : Number(edge);

  (model?.placements || []).forEach((placement, index) => {
    const spec = {
      origin: { ...placement.origin },
      rotation_steps: Number(placement.rotation_steps || 0),
    };
    const roomName = placement.resolved ? String(placement?.room?.name || '').trim() : '';
    const displayLabel = roomName || String(placement?.label || placement?.placement_id || '').trim();
    const localHexes = placement.resolved
      ? (placement.room.hexes || []).map((hex) => {
          const level = transform.toLevel({ q: Number(hex.q), r: Number(hex.r) }, spec);
          return { ...hex, ...toMapHex(level) };
        })
      : [];
    const isBase = basePlacementId === placement.placement_id || baseLayer(placement);
    projectedPlacements.set(placement.placement_id, {
      placement,
      spec,
      localHexes,
      isBase,
    });
    placements.push({
      placementId: placement.placement_id,
      baseLayer: isBase,
      label: placement.label,
      roomName,
      displayLabel,
      tint: tint(index, model.placements.length, placement),
      hexes: localHexes,
    });
    if (occupancy === null && !isBase) {
      localHexes.forEach((hex) => {
        const key = transform.hexKey(hex);
        projectedOccupancy[key] = [...(projectedOccupancy[key] || []), placement.placement_id];
      });
    }
    if (placement.resolved) {
      (placement.room.ports || []).forEach((port) => {
        const level = transform.toLevelPort(
          { q: Number(port.q), r: Number(port.r) },
          Number(port.edge),
          spec,
        );
        const local = toMapHex(level);
        const key = `${placement.placement_id}:${port.port_id}`;
        const entry = {
          placementId: placement.placement_id,
          portId: port.port_id,
          kind: port.kind,
          q: local.q,
          r: local.r,
          edge: toMapEdge(level.edge),
          linked: port.kind === 'exit' && linkedExitPorts.has(key),
          highlight: portHighlight(key),
        };
        ports.push(entry);
        portIndex.set(key, entry);
      });
    }
  });

  const links = (model?.port_links || []).map((link) => {
    const from = portIndex.get(`${link.from.placement_id}:${link.from.port_id}`);
    const to = portIndex.get(`${link.to.placement_id}:${link.to.port_id}`);
    return from && to ? { linkId: link.link_id, kind: link.kind, direction: link.direction, from, to } : null;
  }).filter(Boolean);

  return {
    placements,
    ports,
    links,
    occupancy: occupancy === null ? projectedOccupancy : occupancy,
    projectedPlacements,
  };
}
