#!/usr/bin/env python3
"""
GenAI inference server for DungeonCrawler chat.

Wraps Qwen2.5-0.5B-Instruct via HuggingFace Transformers and exposes a
simple /v1/chat/completions endpoint (OpenAI-compatible subset).

Usage:
    python server.py                           # defaults: port 8321, Qwen 0.5B
    python server.py --port 8321 --model Qwen/Qwen2.5-0.5B-Instruct

Requires: fastapi, uvicorn, transformers, torch
"""

import argparse
import os
import time
import logging
from typing import List, Optional

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import uvicorn

# ── Configuration ────────────────────────────────────────────────────────────

DEFAULT_MODEL = "Qwen/Qwen2.5-0.5B-Instruct"
DEFAULT_PORT = 8321
MAX_NEW_TOKENS = 256
TEMPERATURE = 0.8
TOP_P = 0.9

# HuggingFace cache directories (matches earlier warm-cache runs)
HF_CACHE = "/tmp/copilot-agent-tracker-hf-cache"
os.environ.setdefault("HF_HOME", HF_CACHE)
os.environ.setdefault("HUGGINGFACE_HUB_CACHE", f"{HF_CACHE}/hub")
os.environ.setdefault("TRANSFORMERS_CACHE", f"{HF_CACHE}/transformers")
os.environ.setdefault("HF_HUB_CACHE", f"{HF_CACHE}/hub")
# CPU only — no GPU needed for 0.5B model
os.environ["CUDA_VISIBLE_DEVICES"] = ""

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("genai-server")

# ── Pydantic models ─────────────────────────────────────────────────────────

class ChatMessage(BaseModel):
    role: str  # system | user | assistant
    content: str

class ChatRequest(BaseModel):
    model: Optional[str] = None
    messages: List[ChatMessage]
    max_tokens: Optional[int] = MAX_NEW_TOKENS
    temperature: Optional[float] = TEMPERATURE
    top_p: Optional[float] = TOP_P

class ChatChoice(BaseModel):
    index: int = 0
    message: ChatMessage
    finish_reason: str = "stop"

class ChatUsage(BaseModel):
    prompt_tokens: int = 0
    completion_tokens: int = 0
    total_tokens: int = 0

class ChatResponse(BaseModel):
    id: str = "chatcmpl-dc"
    object: str = "chat.completion"
    created: int = 0
    model: str = ""
    choices: List[ChatChoice]
    usage: ChatUsage = ChatUsage()

# ── Global model handle ────────────────────────────────────────────────────

_pipeline = None
_model_name = ""

def load_model(model_name: str):
    """Load the model and tokenizer into a text-generation pipeline."""
    global _pipeline, _model_name
    from transformers import AutoTokenizer, AutoModelForCausalLM, pipeline as hf_pipeline
    import torch

    logger.info("Loading model %s …", model_name)
    t0 = time.time()

    tokenizer = AutoTokenizer.from_pretrained(model_name)
    model = AutoModelForCausalLM.from_pretrained(
        model_name,
        torch_dtype=torch.float32,
        device_map="cpu",
    )

    _pipeline = hf_pipeline(
        "text-generation",
        model=model,
        tokenizer=tokenizer,
        device=-1,  # CPU
    )
    _model_name = model_name
    elapsed = time.time() - t0
    logger.info("Model loaded in %.1fs", elapsed)


# ── System prompt for GM persona ───────────────────────────────────────────

GM_SYSTEM_PROMPT = """\
You are the Game Master (GM) for a Pathfinder 2nd Edition dungeon crawl.
Respond in character as the GM. Keep responses concise (2-4 sentences).
Describe what happens in the scene, narrate NPC dialogue when appropriate,
and indicate any mechanical triggers (e.g., "Roll a Perception check DC 15").
Use vivid, atmospheric language. Do not break character.\
"""

def build_prompt_messages(messages: List[ChatMessage]) -> List[dict]:
    """Ensure a system prompt is present, inject GM persona if missing."""
    dicts = [{"role": m.role, "content": m.content} for m in messages]

    has_system = any(m["role"] == "system" for m in dicts)
    if not has_system:
        dicts.insert(0, {"role": "system", "content": GM_SYSTEM_PROMPT})

    return dicts


# ── FastAPI app ─────────────────────────────────────────────────────────────

app = FastAPI(title="DungeonCrawler GenAI Server", version="0.1.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/health")
def health():
    return {"status": "ok", "model": _model_name, "ready": _pipeline is not None}


@app.get("/v1/models")
def list_models():
    return {
        "object": "list",
        "data": [{"id": _model_name, "object": "model"}] if _model_name else [],
    }


@app.post("/v1/chat/completions", response_model=ChatResponse)
def chat_completions(req: ChatRequest):
    if _pipeline is None:
        raise HTTPException(503, "Model not loaded yet")

    prompt_messages = build_prompt_messages(req.messages)

    t0 = time.time()
    try:
        outputs = _pipeline(
            prompt_messages,
            max_new_tokens=req.max_tokens or MAX_NEW_TOKENS,
            temperature=req.temperature or TEMPERATURE,
            top_p=req.top_p or TOP_P,
            do_sample=True,
            return_full_text=False,
        )
    except Exception as e:
        logger.error("Inference error: %s", e)
        raise HTTPException(500, f"Inference failed: {e}")

    elapsed = time.time() - t0
    generated_text = outputs[0]["generated_text"].strip() if outputs else ""
    logger.info("Generated %d chars in %.1fs", len(generated_text), elapsed)

    return ChatResponse(
        created=int(time.time()),
        model=_model_name,
        choices=[
            ChatChoice(
                message=ChatMessage(role="assistant", content=generated_text),
            )
        ],
        usage=ChatUsage(
            prompt_tokens=0,
            completion_tokens=len(generated_text.split()),
            total_tokens=len(generated_text.split()),
        ),
    )


# ── Entrypoint ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description="DungeonCrawler GenAI Server")
    parser.add_argument("--model", default=DEFAULT_MODEL, help="HuggingFace model ID")
    parser.add_argument("--port", type=int, default=DEFAULT_PORT, help="Listen port")
    parser.add_argument("--host", default="127.0.0.1", help="Bind address")
    args = parser.parse_args()

    load_model(args.model)
    logger.info("Starting server on %s:%d", args.host, args.port)
    uvicorn.run(app, host=args.host, port=args.port, log_level="info")


if __name__ == "__main__":
    main()
