- Status: done
- Summary: Targeted QA verification for `20260408-194600-impl-dc-apg-rituals` — APPROVE. RITUALS constant confirmed: 26 entries total — 13 CRB (sanctify-water/create-undead/divination/heartbond/geas/atone/community-gathering/planar-binding/call-spirit/commune/raise-dead/teleportation-circle/resurrect) + 13 APG (bless-the-hearth/fantastic-facade/fey-influence/inveigle/angelic-messenger/elemental-sentinel/primal-call/ravenous-reanimation/establish-stronghold/infuse-companion/create-nexus/subjugate-undead/unspeakable-shadow). All entries have `book_id` field (`crb`/`apg` — collision guard confirmed). CRB: 13 entries, APG: 13 entries. `secondary_casters: 0` present on primary-only rituals. `rarity`/`gm_approval` flags on uncommon/rare entries (create-undead, geas, planar-binding, etc.). PHP lint clean. Site audit PASS. Regression checklist updated.

## Verification evidence
- `CharacterManager.php` PHP lint: No syntax errors
- RITUALS: 26 entries (13 CRB lines + 13 APG lines confirmed by book_id count)
- Ritual IDs: all 26 confirmed present
- secondary_casters=0 pattern: present on primary-only rituals
- rarity uncommon/rare entries: confirmed
- Site audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260408-211607/findings-summary.md`

## Test cases status
- TC-RIT-01 through TC-RIT-07: APPROVE

## Next actions
- None.

## Blockers
- None.

## ROI estimate
- ROI: 9
- Rationale: Rituals expand long-form campaign play options; book_id guard prevents name collision between same-named CRB and APG entries.
