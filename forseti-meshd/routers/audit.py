"""Audit log router — read-only query (AC-10)."""

from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from typing import Optional
import json

from database import get_db
from models import AuditLog

router = APIRouter(prefix="/api/v1/audit", tags=["audit"])


@router.get("")
def list_audit_log(
    event_type: Optional[str] = None,
    actor: Optional[str] = None,
    limit: int = Query(default=100, le=500),
    db: Session = Depends(get_db),
):
    """Query immutable audit trail (AC-10)."""
    q = db.query(AuditLog).order_by(AuditLog.created_at.desc())
    if event_type:
        q = q.filter(AuditLog.event_type == event_type)
    if actor:
        q = q.filter(AuditLog.actor_installation_id == actor)
    entries = q.limit(limit).all()
    return [
        {
            "id": e.id,
            "event_type": e.event_type,
            "actor_installation_id": e.actor_installation_id,
            "target_id": e.target_id,
            "detail": json.loads(e.detail_json) if e.detail_json else None,
            "created_at": e.created_at.isoformat(),
        }
        for e in entries
    ]
