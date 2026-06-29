"""Identity router — /.well-known/forseti-node and /api/v1/identity (AC-1, AC-2, AC-16)."""

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from database import get_db
from services.identity_service import get_or_create_installation

router = APIRouter()


@router.get("/.well-known/forseti-node")
def well_known_identity(db: Session = Depends(get_db)):
    """Public identity endpoint — must be reachable without auth (AC-2, AC-16)."""
    installation = get_or_create_installation(db)
    return {
        "installation_id": installation.id,
        "public_key": installation.public_key_b64,
        "base_url": installation.base_url,
        "mission_version": installation.mission_version,
        "api_version": "1",
    }


@router.get("/api/v1/identity")
def get_identity(db: Session = Depends(get_db)):
    """Full identity record (AC-1)."""
    installation = get_or_create_installation(db)
    return {
        "installation_id": installation.id,
        "public_key": installation.public_key_b64,
        "base_url": installation.base_url,
        "mission_version": installation.mission_version,
        "created_at": installation.created_at.isoformat(),
    }
