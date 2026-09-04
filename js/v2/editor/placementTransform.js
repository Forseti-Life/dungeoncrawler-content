/**
 * @file
 * Rigid transform between room-local and dungeon-level axial hex space.
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   10-placement-transform-spec.md
 *
 * This is the JavaScript mirror of
 * src/Geometry/RoomPlacementTransformer.php. The two are pinned together by
 * config/schemas/fixtures/placement_transform_vectors.json and must never
 * drift. This file and its PHP counterpart are the only places EDGE_DIRECTIONS
 * and the rotation primitive may appear.
 *
 * The client uses this to render drag ghosts and pre-colour illegal drops.
 * It is NEVER an authority: the server recomputes every transform and the
 * shell re-renders from the server draft.
 *
 * All arithmetic is integer. No floating point is permitted.
 */

(function (global) {
  'use strict';

  var EDGE_COUNT = 6;

  /**
   * Frozen edge convention. Edge e of hex H faces H + EDGE_DIRECTIONS[e].
   *
   * Ordering is load bearing: it makes rotation index arithmetic. See the PHP
   * counterpart for the full rationale.
   */
  var EDGE_DIRECTIONS = [
    { q: 1, r: 0 },
    { q: 0, r: 1 },
    { q: -1, r: 1 },
    { q: -1, r: 0 },
    { q: 0, r: -1 },
    { q: 1, r: -1 }
  ];

  function normalizeSteps(steps) {
    assertInteger(steps, 'rotation_steps');
    return ((steps % EDGE_COUNT) + EDGE_COUNT) % EDGE_COUNT;
  }

  function assertInteger(value, label) {
    if (typeof value !== 'number' || !Number.isInteger(value)) {
      throw new Error('placement_transform_not_integer:' + label);
    }
  }

  function readHex(hex) {
    if (!hex || typeof hex !== 'object') {
      throw new Error('placement_transform_hex_missing');
    }
    assertInteger(hex.q, 'q');
    assertInteger(hex.r, 'r');
    return [hex.q, hex.r];
  }

  function readPlacement(placement) {
    if (!placement || typeof placement !== 'object') {
      throw new Error('placement_transform_placement_missing');
    }
    if (!placement.origin || typeof placement.origin !== 'object') {
      throw new Error('placement_transform_origin_missing');
    }
    var origin = readHex(placement.origin);
    assertInteger(placement.rotation_steps, 'rotation_steps');
    if (placement.rotation_steps < 0 || placement.rotation_steps > 5) {
      throw new Error('placement_transform_rotation_out_of_range:' + placement.rotation_steps);
    }
    return [origin[0], origin[1], placement.rotation_steps];
  }

  function assertEdge(edge) {
    assertInteger(edge, 'edge');
    if (edge < 0 || edge >= EDGE_COUNT) {
      throw new Error('placement_transform_edge_out_of_range:' + edge);
    }
  }

  /**
   * Rotates an axial coordinate by `steps` sixty-degree steps about the origin.
   *
   * rotate1(q, r) = (-r, q + r), from the cube permutation
   * (x, y, z) -> (-z, -x, -y).
   */
  function rotate(q, r, steps) {
    assertInteger(q, 'q');
    assertInteger(r, 'r');
    var n = normalizeSteps(steps);
    var nq;
    for (var i = 0; i < n; i++) {
      nq = -r;
      r = q + r;
      q = nq;
    }
    return { q: q, r: r };
  }

  /**
   * Maps a room-local hex into level space under a placement.
   */
  function toLevel(hex, placement) {
    var h = readHex(hex);
    var p = readPlacement(placement);
    var rotated = rotate(h[0], h[1], p[2]);
    return { q: rotated.q + p[0], r: rotated.r + p[1] };
  }

  /**
   * Maps a level-space hex back into room-local space. Inverse of toLevel.
   */
  function toRoomLocal(hex, placement) {
    var h = readHex(hex);
    var p = readPlacement(placement);
    return rotate(h[0] - p[0], h[1] - p[1], EDGE_COUNT - p[2]);
  }

  /**
   * Rotates an edge index. Rotation of the frozen convention is index addition.
   */
  function rotateEdge(edge, steps) {
    assertEdge(edge);
    return (edge + normalizeSteps(steps)) % EDGE_COUNT;
  }

  /**
   * Returns the edge facing the opposite direction.
   */
  function opposite(edge) {
    assertEdge(edge);
    return (edge + 3) % EDGE_COUNT;
  }

  /**
   * Returns the neighbouring hex across the given edge.
   */
  function neighbor(hex, edge) {
    var h = readHex(hex);
    assertEdge(edge);
    var d = EDGE_DIRECTIONS[edge];
    return { q: h[0] + d.q, r: h[1] + d.r };
  }

  /**
   * Maps a room-local port into level space, rotating hex and edge together.
   */
  function toLevelPort(hex, edge, placement) {
    var p = readPlacement(placement);
    var levelHex = toLevel(hex, placement);
    levelHex.edge = rotateEdge(edge, p[2]);
    return levelHex;
  }

  /**
   * Cube distance from the axial origin. Invariant under rotation.
   */
  function distanceFromOrigin(q, r) {
    assertInteger(q, 'q');
    assertInteger(r, 'r');
    return (Math.abs(q) + Math.abs(q + r) + Math.abs(r)) / 2;
  }

  /**
   * Stable string key for a level hex, for occupancy maps.
   */
  function hexKey(hex) {
    var h = readHex(hex);
    return h[0] + ':' + h[1];
  }

  var api = {
    EDGE_COUNT: EDGE_COUNT,
    EDGE_DIRECTIONS: EDGE_DIRECTIONS,
    rotate: rotate,
    toLevel: toLevel,
    toRoomLocal: toRoomLocal,
    rotateEdge: rotateEdge,
    opposite: opposite,
    neighbor: neighbor,
    toLevelPort: toLevelPort,
    distanceFromOrigin: distanceFromOrigin,
    hexKey: hexKey
  };

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  global.DungeonCrawlerPlacementTransform = api;
}(typeof globalThis !== 'undefined' ? globalThis : this));
