# Dungeoncrawler navigation hardening: explicit exits, road model, and distance contract

## Context
Recent production hardening improved navigation parsing and destination surfacing. The next step is to enforce a deterministic world-navigation contract so each room exit clearly states where it leads and what travel distance applies.

## Decision summary
Adopt a graph-based navigation model with explicit edge contracts and road anchors.

1. Every room exit must define destination type and destination identity.
2. Direct room-to-room transitions must have distance `0`.
3. Road travel must use explicit road anchors and defined distance values.
4. No full road matrix; use shortest-path over a road graph.

## Data contract
Use these canonical concepts:

- `room` (node): in-world location players can occupy.
- `exit` (edge): from-room transition entry with explicit destination contract:
  - `from_room_id`
  - `exit_label`
  - `to_type` = `room | road`
  - `to_id` (room id or road node id)
  - `distance`
  - `bidirectional`
- `road_node`: node on a named road with an ordered marker.
- `room_road_anchor`: mapping from room to road node plus local access distance.

Distance rules:

- Direct room edge: `distance = 0`.
- Road traversal:
  `total = from_access_distance + road_path_distance + to_access_distance`.

## UX contract
All exits must render as:
`Exit Label -> Destination (type/name) -> Distance`.

Examples:

- `North Door -> Armory (room) -> 0`
- `Gate to Road -> The Road -> access 2`
- `Road travel -> Millhouse -> 14`

## Validation and policy (hard-fail)
- Reject exits with missing destination metadata.
- Reject direct room edges with non-zero distance.
- Reject road-connected rooms without a road anchor.
- Reject duplicate exits that conflict on destination or distance contract.

## Implementation recommendations (ordered)
1. Pass and enforce `connection_id` in transition payloads end-to-end.
2. Make server capability payload the single source of truth.
3. Deprecate legacy parallel navigation endpoint/path.
4. Add source-aware ranking for known destinations in action rail.
5. Add integration tests for transition reachability and multi-edge cases.

## Requested execution
Start implementation in `dungeoncrawler-content` with:

1. Navigation capability payload extension for explicit distance and destination type.
2. Action-rail UI rendering updates to show destination type and distance.
3. Server validation that enforces the distance contract above.
4. Targeted tests for direct edges (`0`) and road-based travel distances.
