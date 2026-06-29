"""Messages router — signed inbound messages (AC-4, AC-5, AC-7)."""

import json
from datetime import datetime, timezone

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from config import get_settings
from database import get_db
from models import Peer
from services import audit_service
from services.identity_service import (
    get_or_create_installation,
    sign_payload,
    verify_signature,
)
from services.policy_engine import is_peer_active

router = APIRouter(prefix="/api/v1/messages", tags=["messages"])

_CANONICAL = lambda d: json.dumps(d, sort_keys=True, separators=(",", ":")).encode()


class InboundMessage(BaseModel):
    message_id: str
    sender_installation_id: str
    timestamp: str  # ISO8601
    message_type: str
    payload: dict
    signature: str  # signs canonical(message envelope minus signature field) (AC-4)


class OutboundEnvelope(BaseModel):
    message_id: str
    sender_installation_id: str
    timestamp: str
    message_type: str
    payload: dict
    signature: str


@router.post("/inbound", status_code=202)
def receive_message(msg: InboundMessage, db: Session = Depends(get_db)):
    """
    Accept a signed inbound message from a peer installation.
    - Verify sender's Ed25519 signature (AC-4).
    - Reject replays (AC-7).
    - Require active peer status (AC-4).
    """
    settings = get_settings()

    # Timestamp / replay check (AC-7)
    try:
        ts = datetime.fromisoformat(msg.timestamp)
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid timestamp")

    delta = abs((datetime.now(timezone.utc) - ts).total_seconds())
    if delta > settings.replay_window_seconds:
        raise HTTPException(status_code=400, detail="Message outside replay window (AC-7)")

    # Require active peer
    if not is_peer_active(db, msg.sender_installation_id):
        raise HTTPException(
            status_code=403,
            detail="Sender is not an active peer — handshake and operator approval required",
        )

    # Fetch peer's public key
    peer = db.query(Peer).filter(Peer.installation_id == msg.sender_installation_id).first()

    # Verify signature over canonical message envelope (AC-4)
    envelope = {
        "message_id": msg.message_id,
        "sender_installation_id": msg.sender_installation_id,
        "timestamp": msg.timestamp,
        "message_type": msg.message_type,
        "payload": msg.payload,
    }
    if not verify_signature(peer.public_key_b64, _CANONICAL(envelope), msg.signature):
        raise HTTPException(status_code=401, detail="Invalid message signature (AC-4)")

    # Update peer last seen
    peer.last_seen_at = datetime.now(timezone.utc)
    audit_service.record(
        db,
        event_type="message_received",
        actor_installation_id=msg.sender_installation_id,
        target_id=msg.message_id,
        detail={"message_type": msg.message_type},
    )
    db.commit()

    return {"status": "accepted", "message_id": msg.message_id}


@router.post("/sign-outbound")
def sign_outbound(envelope: dict, db: Session = Depends(get_db)):
    """
    Sign an outbound message envelope on behalf of this installation (AC-5).
    Returns the envelope with 'signature' appended.
    """
    installation = get_or_create_installation(db)
    payload_bytes = _CANONICAL(envelope)
    sig = sign_payload(payload_bytes)
    return {**envelope, "sender_installation_id": installation.id, "signature": sig}
