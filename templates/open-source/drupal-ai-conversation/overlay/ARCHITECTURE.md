# AI Conversation Architecture

## Overview

`ai_conversation` provides reusable AI chat capabilities inside Drupal. Conversations are stored as Drupal content, provider selection is configuration-driven, and long threads are compacted with rolling summaries to keep context size under control.

## Core parts

### Services

| Service | Purpose |
|---|---|
| `ai_conversation.ai_api_service` | Provider orchestration, message send flow, token tracking |
| `ai_conversation.prompt_manager` | Default/configured system-prompt handling |
| `ai_conversation.storage` | Storage helpers for analytics and reporting |
| `ai_conversation.ollama_api_service` | Ollama-specific provider integration |

### Content model

The module stores conversations in an `ai_conversation` node type with structured fields for:

- message payloads
- system context
- selected model
- conversation summary
- message counts
- token counts

## Request flow

1. A user opens a chat route.
2. The controller loads or creates an `ai_conversation` node.
3. The controller writes the user message to the node.
4. `AIApiService` resolves the active provider and sends the request.
5. The assistant response is stored back on the node.
6. Summary and token counters are updated as needed.

## Provider model

### AWS Bedrock

- configured through module settings
- supports environment-variable or IAM-role credentials

### Ollama

- configured with base URL and allowed models
- suitable for self-hosted local/network inference

## Prompt model

`PromptManager` provides a neutral default prompt for the public package and allows sites to override that prompt through configuration.

## Admin surfaces

The module includes:

- settings UI
- usage dashboard
- pricing/reporting pages
- debug inspection pages

## Public-package notes

This public candidate intentionally removes or renames private-site-specific helpers, branded routes, and branded templates so the package can serve as a standalone Drupal module rather than a Forseti product surface.
