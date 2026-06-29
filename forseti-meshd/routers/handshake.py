"""Handshake router — mutual peer introduction (AC-3)."""

import base64
import hashlib
import json
from datetime import datetime, timezone

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from config import get_settings
from database import get_db
from models import Peer, PeerStatus
from services import audit_service
from services.identity_service import (
    get_or_create_installation,
    sign_payload,
    verify_signature,
)

router = APIRouter(prefix="/api/v1/handshake", tags=["handshake"])


class HandshakeRequest(BaseModel):
    installation_id: str
    public_key: str
    base_url: str
    timestamp: str  # ISO8601
    nonce: str
    signature: str  # Ed25519 signature over canonical payload (AC-3)


class HandshakeResponse(BaseModel):
    installation_id: str
    public_key: str
    base_url: str
    timestamp: str
    nonce: str
    signature: str


def _canonical(data: dict) -> bytes:
    """Deterministic JSON serialization for signing."""
    return json.dumps(data, sort_keys=True, separators=(",", ":")).encode()


@router.post("", response_model=HandshakeResponse)
def handshake(req: HandshakeRequest, db: Session = Depends(get_db)):
    """
    Mutual peer introduction.
    1. Verify requester's signature over their payload.
    2. Register/update peer in registry (proposed state).
    3. Return this node's signed identity.
    AC-3.
    """
    settings = get_settings()

    # Replay window check
    try:
        req_ts = datetime.fromisoformat(req.timestamp)
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid timestamp format")

    delta = abs((datetime.now(timezone.utc) - req_ts).total_seconds())
    if delta > settings.replay_window_seconds:
        raise HTTPException(status_code=400, detail="Timestamp outside replay window")

    # Verify requester signature over canonical request payload
    payload_dict = {
        "installation_id": req.installation_id,
        "public_key": req.public_key,
        "base_url": req.base_url,
        "timestamp": req.timestamp,
        "nonce": req.nonce,
    }
    if not verify_signature(req.public_key, _canonical(payload_dict), req.signature):
        raise HTTPException(status_code=401, detail="Invalid handshake signature")

    # Register peer (proposed — operator must promote to active)
    existing = db.query(Peer).filter(Peer.installation_id == req.installation_id).first()
    if existing:
        existing.public_key_b64 = req.public_key
        existing.base_url = req.base_url
        existing.last_seen_at = datetime.now(timezone.utc)
    else:
        peer = Peer(
            installation_id=req.installation_id,
            base_url=req.base_url,
            public_key_b64=req.public_key,
            status=PeerStatus.proposed,
        )
        db.add(peer)

    audit_service.record(
        db,
        event_type="handshake_received",
        actor_installation_id=req.installation_id,
    )
    db.commit()

    # Build and sign our response
    installation = get_or_create_installation(db)
    response_nonce = base64.b64encode(hashlib.sha256(req.nonce.encode()).digest()).decode()
    response_ts = datetime.now(timezone.utc).isoformat()

    response_payload = {
        "installation_id": installation.id,
        "public_key": installation.public_key_b64,
        "base_url": installation.base_url,
        "timestamp": response_ts,
        "nonce": response_nonce,
    }
    sig = sign_payload(_canonical(response_payload))

    return HandshakeResponse(
        installation_id=installation.id,
        public_key=installation.public_key_b64,
        base_url=installation.base_url,
        timestamp=response_ts,
        nonce=response_nonce,
        signature=sig,
    )
