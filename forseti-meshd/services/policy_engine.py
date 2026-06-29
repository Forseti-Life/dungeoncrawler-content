"""Policy engine — evaluates PeerPolicy rules (AC-6, AC-13)."""

from sqlalchemy.orm import Session

from models import Peer, PeerPolicy, PeerStatus, PolicyDecision


def is_peer_active(db: Session, installation_id: str) -> bool:
    peer = db.query(Peer).filter(Peer.installation_id == installation_id).first()
    return peer is not None and peer.status == PeerStatus.active


def check_export_policy(
    db: Session, installation_id: str, capability_type: str
) -> PolicyDecision:
    """Return allow/deny/review for exporting capability_type to peer (AC-6, AC-13)."""
    peer = db.query(Peer).filter(Peer.installation_id == installation_id).first()
    if peer is None or peer.status != PeerStatus.active:
        return PolicyDecision.deny

    policy = (
        db.query(PeerPolicy)
        .filter(
            PeerPolicy.peer_id == peer.id,
            PeerPolicy.capability_type == capability_type,
            PeerPolicy.direction == "export",
        )
        .first()
    )
    if policy:
        return policy.decision
    # Default: allow export to active peers with no explicit deny
    return PolicyDecision.allow


def check_import_policy(
    db: Session, installation_id: str, capability_type: str
) -> PolicyDecision:
    """Return allow/deny/review for accepting a service request (AC-6)."""
    peer = db.query(Peer).filter(Peer.installation_id == installation_id).first()
    if peer is None or peer.status != PeerStatus.active:
        return PolicyDecision.deny

    policy = (
        db.query(PeerPolicy)
        .filter(
            PeerPolicy.peer_id == peer.id,
            PeerPolicy.capability_type == capability_type,
            PeerPolicy.direction == "import",
        )
        .first()
    )
    if policy:
        return policy.decision
    return PolicyDecision.allow
