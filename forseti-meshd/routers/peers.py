"""Peer registry router — AC-2, AC-16."""

from datetime import datetime, timezone
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from database import get_db
from models import Peer, PeerStatus, MissionAlignment
from services import audit_service

router = APIRouter(prefix="/api/v1/peers", tags=["peers"])


class PeerRegisterRequest(BaseModel):
    installation_id: str
    base_url: str
    public_key: str
    display_name: Optional[str] = None


class PeerStatusUpdateRequest(BaseModel):
    status: PeerStatus
    mission_alignment: Optional[MissionAlignment] = None


@router.get("")
def list_peers(db: Session = Depends(get_db)):
    """List all known peers (AC-16)."""
    peers = db.query(Peer).all()
    return [
        {
            "id": p.id,
            "installation_id": p.installation_id,
            "base_url": p.base_url,
            "public_key": p.public_key_b64,
            "status": p.status,
            "mission_alignment": p.mission_alignment,
            "display_name": p.display_name,
            "last_seen_at": p.last_seen_at.isoformat() if p.last_seen_at else None,
        }
        for p in peers
    ]


@router.post("", status_code=201)
def register_peer(req: PeerRegisterRequest, db: Session = Depends(get_db)):
    """Register a new peer or update an existing peer record (AC-2)."""
    existing = db.query(Peer).filter(Peer.installation_id == req.installation_id).first()
    if existing:
        existing.base_url = req.base_url
        existing.public_key_b64 = req.public_key
        if req.display_name:
            existing.display_name = req.display_name
        db.commit()
        db.refresh(existing)
        audit_service.record(
            db,
            event_type="peer_updated",
            actor_installation_id=req.installation_id,
            target_id=existing.id,
        )
        db.commit()
        return {"id": existing.id, "status": existing.status}

    peer = Peer(
        installation_id=req.installation_id,
        base_url=req.base_url,
        public_key_b64=req.public_key,
        display_name=req.display_name,
        status=PeerStatus.proposed,
    )
    db.add(peer)
    db.flush()
    audit_service.record(
        db,
        event_type="peer_registered",
        actor_installation_id=req.installation_id,
        target_id=peer.id,
    )
    db.commit()
    db.refresh(peer)
    return {"id": peer.id, "status": peer.status}


@router.get("/{peer_id}")
def get_peer(peer_id: str, db: Session = Depends(get_db)):
    peer = db.query(Peer).filter(Peer.id == peer_id).first()
    if not peer:
        raise HTTPException(status_code=404, detail="Peer not found")
    return {
        "id": peer.id,
        "installation_id": peer.installation_id,
        "base_url": peer.base_url,
        "public_key": peer.public_key_b64,
        "status": peer.status,
        "mission_alignment": peer.mission_alignment,
        "display_name": peer.display_name,
        "last_seen_at": peer.last_seen_at.isoformat() if peer.last_seen_at else None,
    }


@router.patch("/{peer_id}/status")
def update_peer_status(
    peer_id: str, req: PeerStatusUpdateRequest, db: Session = Depends(get_db)
):
    """Promote/suspend/revoke a peer (operator action, AC-2)."""
    peer = db.query(Peer).filter(Peer.id == peer_id).first()
    if not peer:
        raise HTTPException(status_code=404, detail="Peer not found")

    old_status = peer.status
    peer.status = req.status
    if req.mission_alignment is not None:
        peer.mission_alignment = req.mission_alignment

    audit_service.record(
        db,
        event_type="peer_status_changed",
        target_id=peer_id,
        detail={"old_status": old_status, "new_status": req.status},
    )
    db.commit()
    return {"id": peer.id, "status": peer.status}
