/**
 * @file utils/authoritative-state-utils.js
 *
 * Shared actor-ref guardrails for authoritative /state requests.
 * Server state reads scoped by actor should only use player/runtime refs.
 */

function toRef(value) {
  return String(value || '').trim();
}

function isNpcRef(normalizedLowerRef) {
  return normalizedLowerRef.startsWith('npc_')
    || normalizedLowerRef.startsWith('npc-')
    || normalizedLowerRef.startsWith('npc:');
}

function isPlayerRef(normalizedLowerRef) {
  return normalizedLowerRef.startsWith('pc_')
    || normalizedLowerRef.startsWith('pc-')
    || normalizedLowerRef.startsWith('pc:');
}

export function normalizeAuthoritativeStateActorRef(actorRef = '', options = {}) {
  const requestedRef = toRef(actorRef);
  if (!requestedRef) {
    return '';
  }

  const requestedLower = requestedRef.toLowerCase();
  if (isNpcRef(requestedLower)) {
    return '';
  }
  if (isPlayerRef(requestedLower)) {
    return requestedRef;
  }

  const runtimeActorRef = toRef(
    options?.runtimeActorRef
    || options?.runtimeContext?.instanceId
  );
  if (!runtimeActorRef) {
    return '';
  }

  return runtimeActorRef.toLowerCase() === requestedLower
    ? runtimeActorRef
    : '';
}

export function shouldRequestAuthoritativeStateForActorRef(actorRef = '', options = {}) {
  return normalizeAuthoritativeStateActorRef(actorRef, options) !== '';
}

