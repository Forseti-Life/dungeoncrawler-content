"""Audit log service — write-only helper (AC-10)."""

import json
from datetime import datetime, timezone

from sqlalchemy.orm import Session

from models import AuditLog


def record(
    db: Session,
    *,
    event_type: str,
    actor_installation_id: str | None = None,
    target_id: str | None = None,
    detail: dict | None = None,
) -> AuditLog:
    entry = AuditLog(
        event_type=event_type,
        actor_installation_id=actor_installation_id,
        target_id=target_id,
        detail_json=json.dumps(detail) if detail else None,
    )
    db.add(entry)
    db.flush()
    return entry
