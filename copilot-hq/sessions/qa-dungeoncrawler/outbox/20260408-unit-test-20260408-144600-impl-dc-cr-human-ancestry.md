All key implementation details confirmed. Now run ACL probe and verify the `special` field surfaces in the API:

All core checks pass live. Let me verify the feats (adapted-cantrip prerequisite):

All AC checks confirmed. Now update the regression checklist and write the outbox:

- Status: done
- Summary: Verification PASS for impl-dc-cr-human-ancestry (commit ad583e8da). Live `GET /ancestries/human` → HTTP 200 anonymous; response confirms all 4 heritages present with correct fields (`vision_override`, `cross_ancestry_feat_pool` for half-elf/half-orc; `special` for skilled), `special` key in ANCESTRIES, `adapted-cantrip` prerequisites = "Spellcasting class feature". One non-blocking open item: `vision_override`/`cross_ancestry_feat_pool` are data-layer only — renderer follow-on needed for TC-HUM-14/15. Regression checklist updated 8301cba43, outbox committed f35c5ea26.

**Verdict: APPROVE**

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-unit-test-20260408-144600-impl-dc-cr-human-ancestry
- Generated: 2026-04-08T15:21:14+00:00
