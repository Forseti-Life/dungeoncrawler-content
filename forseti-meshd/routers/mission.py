"""Mission alignment router — export alignment declaration (AC-15, AC-17)."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel
from sqlalchemy.orm import Session
from typing import Optional

from database import get_db
from models import MissionAlignment, Peer
from services import audit_service
from services.identity_service import get_or_create_installation

router = APIRouter(prefix="/api/v1/mission", tags=["mission"])

MISSION_STATEMENT = (
    "Democratize and decentralize internet services by building community-managed "
    "versions of core systems for scientific, technology-focused, and tolerant people."
)


class MissionDeclaration(BaseModel):
    """Peer broadcasts its mission statement for alignment review (AC-15)."""

    installation_id: str
    mission_statement: str
    mission_version: str


class AlignmentUpdate(BaseModel):
    installation_id: str
    alignment: MissionAlignment
    notes: Optional[str] = None


@router.get("")
def get_mission(db: Session = Depends(get_db)):
    """Return this installation's mission statement for peer validation (AC-17)."""
    installation = get_or_create_installation(db)
    return {
        "mission_statement": MISSION_STATEMENT,
        "mission_version": installation.mission_version,
        "installation_id": installation.id,
    }


@router.post("/alignment", status_code=200)
def record_alignment(req: AlignmentUpdate, db: Session = Depends(get_db)):
    """
    Operator records mission alignment decision for a peer (AC-15).
    Misaligned peers may be automatically suspended per policy.
    """
    peer = db.query(Peer).filter(Peer.installation_id == req.installation_id).first()
    if not peer:
        return {"status": "peer_not_found"}

    old_alignment = peer.mission_alignment
    peer.mission_alignment = req.alignment

    # AC-15: auto-suspend if misaligned
    if req.alignment == MissionAlignment.misaligned:
        from models import PeerStatus
        peer.status = PeerStatus.suspended

    audit_service.record(
        db,
        event_type="mission_alignment_recorded",
        target_id=peer.id,
        detail={
            "old_alignment": old_alignment,
            "new_alignment": req.alignment,
            "notes": req.notes,
        },
    )
    db.commit()
    return {"status": "ok", "peer_status": peer.status}
