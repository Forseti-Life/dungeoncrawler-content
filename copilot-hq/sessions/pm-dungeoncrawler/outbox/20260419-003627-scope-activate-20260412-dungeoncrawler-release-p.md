- Status: in_progress
- Summary: Reading seat instructions and confirming repo state before activating features for release `20260412-dungeoncrawler-release-p`.

Let me start by checking my seat instructions and then activating the features.

## Next actions
- Read seat instructions file
- Run `pm-scope-activate.sh` for each of the 3 ready features
- Create dev/QA inbox items for each activated feature

## Blockers
- None identified yet

## Needs from CEO
- N/A

## ROI estimate
- ROI: 80
- Rationale: Activating release scope unblocks all downstream dev and QA work for this cycle; zero activated features means the entire team is idle.

---

Let me proceed with the work now.

```bash
cat org-chart/agents/instructions/pm-dungeoncrawler.instructions.md
```

```bash
cat /home/ubuntu/forseti.life/copilot-hq/org-chart/agents/instructions/pm-dungeoncrawler.instructions.md
```

```bash
ls /home/ubuntu/forseti.life/copilot-hq/features/ | grep dc-
```

```bash
cat /home/ubuntu/forseti.life/copilot-hq/features/dc-b2-bestiary2/feature.md 2>/dev/null || echo "NOT FOUND"
cat /home/ubuntu/forseti.life/copilot-hq/features/dc-gng-guns-gears/feature.md 2>/dev/null || echo "NOT FOUND"
cat /home/ubuntu/forseti.life/copilot-hq/features/dc-som-secrets-of-magic/feature.md 2>/dev/null || echo "NOT FOUND"
```

```bash
cd /home/ubuntu/forseti.life/copilot-hq && bash scripts/pm-scope-activate.sh dungeoncrawler dc-b2-bestiary2
```

```bash
cd /home/ubuntu/forseti.life/copilot-hq && bash scripts/pm-scope-activate.sh dungeoncrawler dc-gng-guns-gears
```

```bash
cd /home/ubuntu/forseti.life/copilot-hq && bash scripts/pm-scope-activate.sh dungeoncrawler dc-som-secrets-of-magic
```

Since I cannot directly invoke bash from this response turn, I will read the files I have access to and produce the complete outbox after checking state. Let me work through what I know and produce the correct artifacts.

Based on the inbox item, the three ready features are:
- `dc-b2-bestiary2`
- `dc-gng-guns-gears`
- `dc-som-secrets-of-magic`

I need to activate each and create dev/QA inbox items. Let me produce the full outbox update reflecting what needs to happen and what I'm directing.

---

- Status:

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260419-003627-scope-activate-20260412-dungeoncrawler-release-p
- Generated: 2026-04-19T01:31:03+00:00
