"""Service requests router — lifecycle management (AC-9, AC-12, AC-14)."""

import json
from datetime import datetime, timezone, timedelta
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from database import get_db
from models import ServiceRequest, ServiceRequestStatus
from services import audit_service
from services.policy_engine import check_import_policy, PolicyDecision

router = APIRouter(prefix="/api/v1/service-requests", tags=["service-requests"])

REQUEST_TTL_HOURS = 72


class ServiceRequestCreate(BaseModel):
    requester_installation_id: str
    capability_type: str
    payload: Optional[dict] = None


class ServiceRequestUpdate(BaseModel):
    status: ServiceRequestStatus
    result: Optional[dict] = None


@router.post("", status_code=201)
def create_service_request(req: ServiceRequestCreate, db: Session = Depends(get_db)):
    """
    Receive a service request from a peer (AC-9).
    Policy check → pending or rejected.
    """
    decision = check_import_policy(db, req.requester_installation_id, req.capability_type)
    if decision == PolicyDecision.deny:
        raise HTTPException(status_code=403, detail="Policy denies this service request")

    sr = ServiceRequest(
        requester_installation_id=req.requester_installation_id,
        capability_type=req.capability_type,
        payload_json=json.dumps(req.payload) if req.payload else None,
        status=ServiceRequestStatus.accepted if decision == PolicyDecision.allow else ServiceRequestStatus.pending,
        expires_at=datetime.now(timezone.utc) + timedelta(hours=REQUEST_TTL_HOURS),
    )
    db.add(sr)
    db.flush()

    audit_service.record(
        db,
        event_type="service_request_created",
        actor_installation_id=req.requester_installation_id,
        target_id=sr.id,
        detail={"capability_type": req.capability_type, "status": sr.status},
    )
    db.commit()
    db.refresh(sr)
    return {"id": sr.id, "status": sr.status}


@router.get("")
def list_service_requests(status: Optional[ServiceRequestStatus] = None, db: Session = Depends(get_db)):
    """List service requests, optionally filtered by status (AC-12)."""
    q = db.query(ServiceRequest)
    if status:
        q = q.filter(ServiceRequest.status == status)
    requests = q.order_by(ServiceRequest.created_at.desc()).all()
    return [
        {
            "id": r.id,
            "requester_installation_id": r.requester_installation_id,
            "capability_type": r.capability_type,
            "status": r.status,
            "created_at": r.created_at.isoformat(),
            "expires_at": r.expires_at.isoformat() if r.expires_at else None,
        }
        for r in requests
    ]


@router.get("/{request_id}")
def get_service_request(request_id: str, db: Session = Depends(get_db)):
    sr = db.query(ServiceRequest).filter(ServiceRequest.id == request_id).first()
    if not sr:
        raise HTTPException(status_code=404, detail="Service request not found")
    return {
        "id": sr.id,
        "requester_installation_id": sr.requester_installation_id,
        "capability_type": sr.capability_type,
        "status": sr.status,
        "payload": json.loads(sr.payload_json) if sr.payload_json else None,
        "result": json.loads(sr.result_json) if sr.result_json else None,
        "created_at": sr.created_at.isoformat(),
        "expires_at": sr.expires_at.isoformat() if sr.expires_at else None,
    }


@router.patch("/{request_id}")
def update_service_request(
    request_id: str, req: ServiceRequestUpdate, db: Session = Depends(get_db)
):
    """Update service request status (operator/fulfillment flow, AC-14)."""
    sr = db.query(ServiceRequest).filter(ServiceRequest.id == request_id).first()
    if not sr:
        raise HTTPException(status_code=404, detail="Service request not found")

    old_status = sr.status
    sr.status = req.status
    if req.result:
        sr.result_json = json.dumps(req.result)

    audit_service.record(
        db,
        event_type="service_request_updated",
        target_id=request_id,
        detail={"old_status": old_status, "new_status": req.status},
    )
    db.commit()
    return {"id": sr.id, "status": sr.status}
