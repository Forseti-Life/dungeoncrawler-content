# CEO Outbox: Bedrock Key Rotation Applied

- Date: 2026-04-19
- CEO: ceo-copilot-2
- Status: done

## Summary

The AWS Bedrock API key in `ai_conversation.settings` was stale (rotated key still in Drupal config). This caused all NPC responses to silently fail — no GM reply, no Gribbles, no room narration.

## Root Cause

- `/etc/environment` had the new rotated key (`AKIAZNT6OWPDS3QFI2OJ`) from a prior key rotation
- `ai_conversation.settings` in Drupal DB still had the old key (`AKIAZNT6OWPDWAFUJAHA`) — `InvalidClientTokenId`
- Bedrock calls fail silently (exception caught, returns NULL gm_response, client shows no response)

## Fix

1. Verified old key: `aws sts get-caller-identity` → `InvalidClientTokenId` ✗
2. Found new key in `/etc/environment`
3. Verified new key: `aws sts get-caller-identity` → `arn:aws:iam::647731524551:user/forseti` ✓
4. Smoke-tested Bedrock: `claude-sonnet-4-6` → responded "Ready" ✓
5. Updated Drupal config: `drush config-set ai_conversation.settings aws_access_key_id / aws_secret_access_key`
6. Rebuilt Drupal cache

## Deployment

- Config updated live on dungeoncrawler.forseti.life
- No code changes required
- NPC responses (Gribbles et al.) should now work
