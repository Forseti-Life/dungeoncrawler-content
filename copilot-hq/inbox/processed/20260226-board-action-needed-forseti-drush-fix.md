# Board Action Required: Forseti.life Gate 2 Drush Fix

- created_at: 2026-02-26T14:26:00Z
- work_item: 20260226-needs-qa-forseti-unit-test-gate2
- topic: board-action-forseti-drush-fix
- ROI: 15 (~7 min, closes forseti.life release Gate 2)

## What is blocked
forseti.life release is blocked at Gate 2 QA. Two issues need terminal drush access:
1. Node 22 may be unpublished
2. `content_editor` role is missing a permission for the `forseti_content.talk_with_forseti_content` route

All agent-chain decisions are resolved — terminal-execution gate only.

## Action needed from Board (Keith)

```bash
cd /home/keithaumiller/forseti.life/sites/forseti

# Step 1: check + publish node 22
vendor/bin/drush php-eval '
  $node = \Drupal::entityTypeManager()->getStorage("node")->load(22);
  echo "Published: " . ($node->isPublished() ? "yes" : "NO") . "\n";
  if ($node->hasField("moderation_state")) { echo "Moderation: " . $node->get("moderation_state")->value . "\n"; }
' 2>&1
# If unpublished, run:
vendor/bin/drush php-eval '
  $node = \Drupal::entityTypeManager()->getStorage("node")->load(22);
  $node->setPublished();
  if ($node->hasField("moderation_state")) { $node->set("moderation_state", "published"); }
  $node->save(); echo "Node 22 published.\n";
' 2>&1

# Step 2: get the required permission string for the route
vendor/bin/drush php-eval '
  $r = \Drupal::service("router.route_provider")->getRouteByName("forseti_content.talk_with_forseti_content");
  var_export($r->getRequirements());
' 2>&1

# Step 3: grant content_editor the permission (replace <perm> with output from Step 2)
vendor/bin/drush role:perm:add content_editor "<perm>" && vendor/bin/drush cr

# Step 4: verify node 22 stays published after cron
vendor/bin/drush cron && vendor/bin/drush php-eval '
  $n = \Drupal::entityTypeManager()->getStorage("node")->load(22);
  echo "Published after cron: " . ($n->isPublished() ? "yes" : "NO") . "\n";
' 2>&1

# Step 5: full post-fix audit
cd /home/keithaumiller/copilot-sessions-hq && \
  ALLOW_PROD_QA=0 FORSETI_BASE_URL=http://localhost bash scripts/site-audit-run.sh forseti-life
```
Deliver audit output to `sessions/qa-forseti/inbox/` for Gate 2 sign-off.
