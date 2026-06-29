"""SQLAlchemy ORM models for forseti-meshd."""

import enum
import uuid
from datetime import datetime, timezone

from sqlalchemy import (
    Boolean,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    String,
    Text,
    UniqueConstraint,
)
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from database import Base


def _now() -> datetime:
    return datetime.now(timezone.utc)


# ── Enums ──────────────────────────────────────────────────────────────────

class PeerStatus(str, enum.Enum):
    proposed = "proposed"
    active = "active"
    suspended = "suspended"
    revoked = "revoked"


class PolicyDecision(str, enum.Enum):
    allow = "allow"
    deny = "deny"
    review = "review"


class ServiceRequestStatus(str, enum.Enum):
    pending = "pending"
    accepted = "accepted"
    in_progress = "in_progress"
    completed = "completed"
    rejected = "rejected"
    expired = "expired"


class MissionAlignment(str, enum.Enum):
    aligned = "aligned"
    neutral = "neutral"
    misaligned = "misaligned"
    unknown = "unknown"


# ── Installation (self-identity) ───────────────────────────────────────────

class Installation(Base):
    """Represents THIS installation's identity. Exactly one row expected."""

    __tablename__ = "installation"

    id: Mapped[str] = mapped_column(
        UUID(as_uuid=False), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    public_key_b64: Mapped[str] = mapped_column(String(64), nullable=False)
    base_url: Mapped[str] = mapped_column(String(512), nullable=False)
    mission_version: Mapped[str] = mapped_column(String(32), nullable=False, default="1.0")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=_now)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), default=_now, onupdate=_now
    )


# ── Peer registry ──────────────────────────────────────────────────────────

class Peer(Base):
    """A remote Forseti installation known to this node."""

    __tablename__ = "peer"

    id: Mapped[str] = mapped_column(
        UUID(as_uuid=False), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    installation_id: Mapped[str] = mapped_column(String(512), nullable=False, unique=True)
    base_url: Mapped[str] = mapped_column(String(512), nullable=False)
    public_key_b64: Mapped[str] = mapped_column(String(64), nullable=False)
    status: Mapped[PeerStatus] = mapped_column(
        Enum(PeerStatus), nullable=False, default=PeerStatus.proposed
    )
    mission_alignment: Mapped[MissionAlignment] = mapped_column(
        Enum(MissionAlignment), nullable=False, default=MissionAlignment.unknown
    )
    display_name: Mapped[str | None] = mapped_column(String(255), nullable=True)
    last_seen_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=_now)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), default=_now, onupdate=_now
    )

    policies: Mapped[list["PeerPolicy"]] = relationship(back_populates="peer", cascade="all, delete-orphan")


# ── Peer policy ────────────────────────────────────────────────────────────

class PeerPolicy(Base):
    """Export / accept policy for a specific peer."""

    __tablename__ = "peer_policy"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    peer_id: Mapped[str] = mapped_column(ForeignKey("peer.id", ondelete="CASCADE"), nullable=False)
    capability_type: Mapped[str] = mapped_column(String(128), nullable=False)
    direction: Mapped[str] = mapped_column(String(16), nullable=False)  # "export" | "import"
    decision: Mapped[PolicyDecision] = mapped_column(Enum(PolicyDecision), nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=_now)

    peer: Mapped["Peer"] = relationship(back_populates="policies")

    __table_args__ = (
        UniqueConstraint("peer_id", "capability_type", "direction", name="uq_peer_policy"),
    )


# ── Capability registry ────────────────────────────────────────────────────

class Capability(Base):
    """A service capability this installation can offer to peers."""

    __tablename__ = "capability"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    capability_type: Mapped[str] = mapped_column(String(128), nullable=False, unique=True)
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    meta_json: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=_now)


# ── Needs registry ─────────────────────────────────────────────────────────

class Need(Base):
    """A service need this installation has (broadcasts to peers)."""

    __tablename__ = "need"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    capability_type: Mapped[str] = mapped_column(String(128), nullable=False, unique=True)
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=_now)


# ── Service request ────────────────────────────────────────────────────────

class ServiceRequest(Base):
    """A service request lifecycle record."""

    __tablename__ = "service_request"

    id: Mapped[str] = mapped_column(
        UUID(as_uuid=False), primary_key=True, default=lambda: str(uuid.uuid4())
    )
    requester_installation_id: Mapped[str] = mapped_column(String(512), nullable=False)
    capability_type: Mapped[str] = mapped_column(String(128), nullable=False)
    status: Mapped[ServiceRequestStatus] = mapped_column(
        Enum(ServiceRequestStatus), nullable=False, default=ServiceRequestStatus.pending
    )
    payload_json: Mapped[str | None] = mapped_column(Text, nullable=True)
    result_json: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=_now)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), default=_now, onupdate=_now
    )
    expires_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)


# ── Audit log ──────────────────────────────────────────────────────────────

class AuditLog(Base):
    """Immutable audit trail for all cross-installation events."""

    __tablename__ = "audit_log"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    event_type: Mapped[str] = mapped_column(String(128), nullable=False)
    actor_installation_id: Mapped[str | None] = mapped_column(String(512), nullable=True)
    target_id: Mapped[str | None] = mapped_column(String(512), nullable=True)
    detail_json: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=_now)
