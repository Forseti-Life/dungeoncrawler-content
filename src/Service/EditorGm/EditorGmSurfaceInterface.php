<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * One editor surface the harness can serve (20-gm-harness-extension.md).
 *
 * A surface owns its toolset, its context assembler, its validation profiles
 * and the authority its context reads and writes through. The harness owns
 * everything else: request parsing, dispatch, dry run, proposals, envelopes.
 */
interface EditorGmSurfaceInterface {

  /**
   * Stable id carried in `tool_context.tool_id` and route defaults.
   */
  public function id(): string;

  /**
   * Human label used when grounding the intent parser ("Room Editor").
   */
  public function label(): string;

  /**
   * Toolset registered for this surface.
   */
  public function registry(): EditorGmToolRegistry;

  /**
   * Snapshot builder for this surface.
   */
  public function assembler(): EditorGmContextAssemblerInterface;

  /**
   * Command types the surface can plan and execute (parity assertions).
   *
   * @return string[]
   */
  public function supportedCommandTypes(): array;

  /**
   * Validation profiles the surface's authority understands.
   *
   * @return string[]
   */
  public function validationProfiles(): array;

  /**
   * Grounds a tool context for one draft at one profile.
   */
  public function createContext(string $draft_id, string $profile): EditorGmToolContext;

}
