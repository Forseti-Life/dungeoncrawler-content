"""Identity service — Ed25519 key lifecycle (AC-1)."""

import base64
import os
from pathlib import Path

import nacl.signing
from sqlalchemy.orm import Session

from config import get_settings
from models import Installation


def _key_path() -> Path:
    settings = get_settings()
    return Path(settings.key_dir) / "signing.key"


def load_or_generate_signing_key() -> nacl.signing.SigningKey:
    """Load persisted Ed25519 signing key or generate and persist a new one."""
    path = _key_path()
    path.parent.mkdir(parents=True, exist_ok=True)

    if path.exists():
        raw = path.read_bytes()
        return nacl.signing.SigningKey(raw)

    key = nacl.signing.SigningKey.generate()
    # Write with restrictive permissions (owner read/write only)
    path.write_bytes(bytes(key))
    os.chmod(path, 0o600)
    return key


def get_or_create_installation(db: Session) -> Installation:
    """Return the single Installation row, creating it if absent (AC-1)."""
    installation = db.query(Installation).first()
    if installation:
        return installation

    settings = get_settings()
    signing_key = load_or_generate_signing_key()
    verify_key = signing_key.verify_key
    public_key_b64 = base64.b64encode(bytes(verify_key)).decode()

    installation = Installation(
        base_url=settings.base_url,
        public_key_b64=public_key_b64,
        mission_version=settings.mission_version,
    )
    db.add(installation)
    db.commit()
    db.refresh(installation)
    return installation


def sign_payload(payload: bytes) -> str:
    """Sign payload bytes; return base64-encoded signature."""
    signing_key = load_or_generate_signing_key()
    signed = signing_key.sign(payload)
    return base64.b64encode(signed.signature).decode()


def verify_signature(public_key_b64: str, payload: bytes, signature_b64: str) -> bool:
    """Verify an Ed25519 signature from a peer (AC-4, AC-7)."""
    try:
        public_key_bytes = base64.b64decode(public_key_b64)
        verify_key = nacl.signing.VerifyKey(public_key_bytes)
        sig_bytes = base64.b64decode(signature_b64)
        verify_key.verify(payload, sig_bytes)
        return True
    except Exception:
        return False
