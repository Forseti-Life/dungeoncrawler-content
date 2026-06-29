"""forseti-meshd — FastAPI application entry point."""

import logging

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from config import get_settings
from database import Base, engine
from routers import audit, capabilities, handshake, identity, messages, mission, peers, service_requests

settings = get_settings()

logging.basicConfig(level=settings.log_level.upper())
logger = logging.getLogger("forseti-meshd")

app = FastAPI(
    title="forseti-meshd",
    description="Forseti Installation Cluster Communication Daemon",
    version="0.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# Ensure tables exist on startup (Alembic handles production migrations)
@app.on_event("startup")
def startup():
    Base.metadata.create_all(bind=engine)
    logger.info("forseti-meshd started on %s:%s", settings.host, settings.port)


# Routers
app.include_router(identity.router)
app.include_router(peers.router)
app.include_router(handshake.router)
app.include_router(messages.router)
app.include_router(capabilities.router)
app.include_router(service_requests.router)
app.include_router(audit.router)
app.include_router(mission.router)


@app.get("/health")
def health():
    return {"status": "ok", "service": "forseti-meshd"}
