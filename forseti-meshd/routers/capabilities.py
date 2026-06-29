"""Capabilities router — capability and need registry (AC-8, AC-11, AC-13)."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session
from typing import Optional

from database import get_db
from models import Capability, Need, Peer, PeerPolicy, PolicyDecision
from services import audit_service
from services.policy_engine import check_export_policy

router = APIRouter(prefix="/api/v1/capabilities", tags=["capabilities"])


class CapabilityCreate(BaseModel):
    capability_type: str
    description: Optional[str] = None
    meta_json: Optional[str] = None


class NeedCreate(BaseModel):
    capability_type: str
    description: Optional[str] = None


class PolicySet(BaseModel):
    capability_type: str
    direction: str  # "export" | "import"
    decision: PolicyDecision


# ── Capabilities ──────────────────────────────────────────────────────────

@router.get("")
def list_capabilities(db: Session = Depends(get_db)):
    """List this installation's declared capabilities (AC-8)."""
    caps = db.query(Capability).filter(Capability.is_active == True).all()
    return [
        {
            "id": c.id,
            "capability_type": c.capability_type,
            "description": c.description,
            "meta_json": c.meta_json,
        }
        for c in caps
    ]


@router.post("", status_code=201)
def declare_capability(req: CapabilityCreate, db: Session = Depends(get_db)):
    """Declare a new capability for this installation (AC-8)."""
    existing = db.query(Capability).filter(
        Capability.capability_type == req.capability_type
    ).first()
    if existing:
        existing.description = req.description
        existing.meta_json = req.meta_json
        existing.is_active = True
        db.commit()
        return {"id": existing.id, "capability_type": existing.capability_type}

    cap = Capability(
        capability_type=req.capability_type,
        description=req.description,
        meta_json=req.meta_json,
    )
    db.add(cap)
    db.commit()
    db.refresh(cap)
    return {"id": cap.id, "capability_type": cap.capability_type}


@router.delete("/{capability_type}")
def retire_capability(capability_type: str, db: Session = Depends(get_db)):
    cap = db.query(Capability).filter(Capability.capability_type == capability_type).first()
    if not cap:
        raise HTTPException(status_code=404, detail="Capability not found")
    cap.is_active = False
    db.commit()
    return {"status": "retired"}


# ── Needs ─────────────────────────────────────────────────────────────────

@router.get("/needs")
def list_needs(db: Session = Depends(get_db)):
    """List this installation's declared needs (AC-11)."""
    needs = db.query(Need).filter(Need.is_active == True).all()
    return [
        {"id": n.id, "capability_type": n.capability_type, "description": n.description}
        for n in needs
    ]


@router.post("/needs", status_code=201)
def declare_need(req: NeedCreate, db: Session = Depends(get_db)):
    """Declare a capability need (AC-11)."""
    existing = db.query(Need).filter(Need.capability_type == req.capability_type).first()
    if existing:
        existing.description = req.description
        existing.is_active = True
        db.commit()
        return {"id": existing.id, "capability_type": existing.capability_type}
    need = Need(capability_type=req.capability_type, description=req.description)
    db.add(need)
    db.commit()
    db.refresh(need)
    return {"id": need.id, "capability_type": need.capability_type}


# ── Peer export policy ─────────────────────────────────────────────────────

@router.get("/peer-policy/{installation_id}")
def get_peer_policy(installation_id: str, capability_type: str, db: Session = Depends(get_db)):
    """Check export policy for a peer + capability (AC-13)."""
    decision = check_export_policy(db, installation_id, capability_type)
    return {"installation_id": installation_id, "capability_type": capability_type, "decision": decision}


@router.post("/peer-policy/{peer_id}", status_code=201)
def set_peer_policy(peer_id: str, req: PolicySet, db: Session = Depends(get_db)):
    """Set export/import policy for a peer + capability (AC-13)."""
    peer = db.query(Peer).filter(Peer.id == peer_id).first()
    if not peer:
        raise HTTPException(status_code=404, detail="Peer not found")

    existing = (
        db.query(PeerPolicy)
        .filter(
            PeerPolicy.peer_id == peer_id,
            PeerPolicy.capability_type == req.capability_type,
            PeerPolicy.direction == req.direction,
        )
        .first()
    )
    if existing:
        existing.decision = req.decision
    else:
        policy = PeerPolicy(
            peer_id=peer_id,
            capability_type=req.capability_type,
            direction=req.direction,
            decision=req.decision,
        )
        db.add(policy)

    audit_service.record(
        db,
        event_type="peer_policy_set",
        target_id=peer_id,
        detail={"capability_type": req.capability_type, "direction": req.direction, "decision": req.decision},
    )
    db.commit()
    return {"status": "ok"}


# ── Discovery endpoint (called by peers) ─────────────────────────────────

@router.get("/advertise")
def advertise_capabilities(db: Session = Depends(get_db)):
    """
    Return this installation's active capabilities for peer discovery (AC-8, AC-11).
    No auth — public discovery endpoint.
    """
    caps = db.query(Capability).filter(Capability.is_active == True).all()
    needs = db.query(Need).filter(Need.is_active == True).all()
    return {
        "capabilities": [c.capability_type for c in caps],
        "needs": [n.capability_type for n in needs],
    }
