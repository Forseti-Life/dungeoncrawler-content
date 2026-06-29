- Status: done
- Summary: Release 20260412-dungeoncrawler-release-m arrived with 0 features activated. Evaluated all 15 candidate features: 10 were fully groomed (status ready, AC + test plan present) and eligible; 3 (dc-b2-bestiary2, dc-gng-guns-gears, dc-som-secrets-of-magic) lacked required AC/test-plan artifacts and could not be activated; 2 (dc-cr-goblin-weapon-frenzy, dc-ui-scene-layer-contract) were already in_progress on release-l and not re-activatable. All 10 eligible features were activated via `scripts/pm-scope-activate.sh`, hitting the 10-feature auto-close cap. Dev and QA inbox items were created for each activated feature. Committed as `7f7e24df4`.

## Next actions
- Monitor dev-dungeoncrawler and qa-dungeoncrawler execution on the 10 activated features
- Address clarify-escalation outbox item: add Decision needed + Recommendation to release-l signoff outbox
- Begin grooming release-n (20260414-groom-20260412-dungeoncrawler-release-n)
- Sign off release-m once Gate 2 APPROVE arrives from qa-dungeoncrawler

## Blockers
- None — all eligible features activated at cap

## ROI estimate
- ROI: 18
- Rationale: Release-m was at zero scope — activating 10 features immediately unblocks all downstream dev and QA work for dungeoncrawler. Delay would have stalled the entire delivery pipeline.
