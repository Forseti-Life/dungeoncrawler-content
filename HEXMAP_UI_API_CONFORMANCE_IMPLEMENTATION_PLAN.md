# Hexmap UI API Conformance Implementation Plan

**Initiative**: `hexmap-ui-api-conformance`  
**Depends on**: `HEXMAP_UI_API_FRAMEWORK.md`  
**Policy**: explicit authority, no silent fallback authority, no panel-local canonical state

---

## Objective

Validate every Hexmap V2 panel and UI action against the API framework, then close the conformance gaps so:

1. every UI surface can name its read authority,
2. every UI mutation can name its write authority,
3. every endpoint family can name its owned data objects,
4. every mutation has a deterministic post-mutation refresh path,
5. no panel replaces API-owned truth with local shell inference.

---

## Phase 1 — Freeze the conformance inventory

**Owner**: Architecture + Dev

### Deliverables

- one frozen inventory of:
  - panel → read authority
  - panel → write authority
  - panel → owned DOM surface
  - authority family → owned data objects
  - mutation → refresh path
- one gap list:
  - missing ownership
  - split ownership
  - local fallback authority
  - undocumented mutation refreshes

### Exit gate

- every active Hexmap panel is listed
- every active UI-originated POST/DELETE path is listed
- every known conformance violation is named

---

## Phase 2 — Define the validation checklist

**Owner**: Architecture + QA

### Per-panel validation checklist

Each panel must pass all checks:

1. **Read authority named**
2. **Write authority named** (or explicitly none)
3. **Data objects named**
4. **Owned DOM surface named**
5. **Local state is presentation-only**
6. **Refresh path defined**
7. **Failure/degraded behavior defined**

### Deliverables

- reusable conformance checklist
- panel scorecard format:
  - `conformant`
  - `partial`
  - `non-conformant`

### Exit gate

- QA and Dev use the same checklist

---

## Phase 3 — Add contract tests for conformance

**Owner**: Dev

### Deliverables

- source-level contract tests for:
  - Action Rail authority source
  - Combat Panel authority source
  - Character Panel authority source
  - Inventory Panel authority source
  - Chat Panel read/write lanes
  - Merchant Panel read/write lanes
  - Navigation/movement mutation lane
- response-shape contract tests for authority families:
  - bootstrap/world projection
  - gameplay runtime
  - character
  - inventory
  - room/chat
  - merchant/navigation

### Required assertions

- panels do not null out API-owned actor identity
- panels do not invent alternate action legality
- mutations reload from the declared refresh path
- no UI mutation ends in an unowned local state

### Exit gate

- conformance can fail in CI from code changes, not only from manual review

---

## Phase 4 — Fix highest-risk runtime seams

**Owner**: Dev

### Priority order

1. **Action Rail**
2. **Combat/turn projections**
3. **Character Panel**
4. **Inventory + Merchant sync**
5. **Chat/channel/session views**
6. **Navigation/movement**

### Required fixes

- remove local authority inversions
- move ambiguous actor identity resolution behind declared authority surfaces
- replace local “best guess” refreshes with explicit authority refreshes
- make blocked/degraded state explicit in the UI

### Exit gate

- highest-risk panels are `conformant` on the checklist

---

## Phase 5 — Normalize mutation response handling

**Owner**: Dev

### Deliverables

- one documented response-handling rule per mutation family:
  - **authoritative response contains enough state**
  - **authoritative response requires explicit re-query**
  - **authoritative response is completed by event stream**
- one shared mutation result vocabulary:
  - `success`
  - `error`
  - `message`
  - `data` / `result`
  - `events`
  - `refresh_required`
  - `snapshot_id` when runtime authority is involved

### Exit gate

- every mutation family has one approved completion pattern

---

## Phase 6 — Add observability for conformance drift

**Owner**: Dev + QA

### Deliverables

- telemetry on:
  - panel render authority
  - mutation endpoint family
  - mutation refresh path
  - authority mismatch
  - degraded mode entry
- logs/metrics for:
  - repeated authority mismatches
  - repeated local fallback usage
  - stale panel render after mutation

### Exit gate

- conformance drift is measurable directly

---

## Phase 7 — QA verification matrix

**Owner**: QA

### Query verification

- panel load uses declared read authority
- panel refresh uses declared re-query/event authority
- stale/local-only state does not appear as canonical

### Mutation verification

- button/form posts to declared endpoint family
- response produces the declared refresh behavior
- rejection leaves visible blocked/error state
- follow-up renders reflect authoritative state

### Scenario set

- Action Rail action during encounter
- Action Rail action outside encounter
- inventory assign/unequip
- merchant buy/sell
- room chat send
- channel open/close
- room move / drag-drop move
- destination request
- settings mode change
- character conversion

### Exit gate

- every scenario has a passing query + mutation conformance result

---

## Phase 8 — Release gate

**Owner**: CEO + Dev + QA

Do not treat the initiative as complete until:

1. framework doc is current
2. implementation plan is current
3. panel checklist is complete
4. contract tests exist for all critical panels
5. highest-risk seams are fixed
6. QA scenario matrix passes

---

## Immediate next actions

1. convert the current framework doc into a panel scorecard workbook
2. add missing contract tests for Combat, Chat, Merchant, and Inventory authority lanes
3. complete Action Rail follow-through on authority-only rendering
4. validate mutation refresh paths one family at a time, starting with gameplay and inventory
