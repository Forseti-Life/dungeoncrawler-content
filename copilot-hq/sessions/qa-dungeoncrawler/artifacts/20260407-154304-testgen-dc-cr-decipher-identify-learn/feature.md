# Feature Brief: Decipher Writing, Identify Magic, Learn a Spell

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-dc-rarity-spell-adjustment, dc-cr-spellcasting

## Description
Implement three related general skill actions that share skill routing across
Arcana, Occultism, Religion, and Society (REQs 1574–1590).

**Decipher Writing** (exploration, secret, trained — REQs 1574–1578):
- Handler in ExplorationPhaseHandler; time: 1 min/page (cipher: 1 hr/page)
- Language literacy check; skills: Arcana/Occultism/Religion/Society
- Degrees: crit=full meaning, success=true meaning, fail=block+retry penalty,
  crit fail=false interpretation

**Identify Magic** (exploration, trained, 10 min — REQs 1583–1586):
- Handler in ExplorationPhaseHandler
- DC: standard by level; +5 for wrong tradition
- Degrees: crit=full+bonus, success=full, fail=1-day block, crit fail=false ID
- Skills: Arcana/Nature/Occultism/Religion

**Learn a Spell** (exploration, trained, 1 hr — REQs 1587–1590):
- Material cost: spell_level × 10 gp, consumed on attempt
- DC: standard by spell level (from dc-cr-dc-rarity-spell-adjustment)
- Degrees: crit=learn+refund half cost, success=learn, fail=nothing,
  crit fail=lose materials

## Security acceptance criteria

- Security AC exemption: game-mechanic logic; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1574, 1575, 1576, 1577, 1578, 1583, 1584, 1585, 1586, 1587, 1588, 1589, 1590
- See `runbooks/roadmap-audit.md` for audit process.
