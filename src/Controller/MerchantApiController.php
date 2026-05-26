<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\MerchantTransactionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Merchant panel and chat transaction API endpoints.
 */
class MerchantApiController extends ControllerBase {

  protected MerchantTransactionService $merchantTransactionService;

  /**
   * Constructor.
   */
  public function __construct(MerchantTransactionService $merchant_transaction_service) {
    $this->merchantTransactionService = $merchant_transaction_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.merchant_transaction')
    );
  }

  /**
   * Get merchant panel context for the active room.
   */
  public function getContext(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    Request $request
  ): JsonResponse {
    try {
      $character_id = $request->query->get('character_id');
      $context = $this->merchantTransactionService->getMerchantPanelContext(
        $campaign_id,
        $room_id,
        $merchant_ref,
        $character_id !== NULL && $character_id !== '' ? (int) $character_id : NULL
      );

      return new JsonResponse([
        'success' => TRUE,
        'context' => $context,
      ]);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Search wider trade catalogs for a merchant-panel lookup.
   */
  public function searchCatalog(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    Request $request
  ): JsonResponse {
    try {
      $query = trim((string) $request->query->get('query', ''));
      if ($query === '') {
        throw new \InvalidArgumentException('Merchant search query is required.');
      }

      return new JsonResponse([
        'success' => TRUE,
        'items' => $this->merchantTransactionService->searchMerchantCatalog(
          $campaign_id,
          $room_id,
          $merchant_ref,
          $query
        ),
      ]);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Execute a panel trade request.
   */
  public function transactPanel(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    Request $request
  ): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE) ?: [];
      $result = $this->merchantTransactionService->executePanelTransaction(
        $campaign_id,
        $room_id,
        $merchant_ref,
        $data
      );

      return new JsonResponse($result, !empty($result['success']) ? 200 : 400);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'status' => 'blocked',
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Execute a chat-surface merchant request against the shared backend.
   */
  public function transactChat(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    Request $request
  ): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE) ?: [];
      $message = trim((string) ($data['message'] ?? ''));
      if ($message === '') {
        throw new \InvalidArgumentException('Merchant chat message is required.');
      }

      $result = $this->merchantTransactionService->executeChatTransaction(
        $campaign_id,
        $room_id,
        $merchant_ref,
        $message,
        isset($data['character_id']) ? (int) $data['character_id'] : NULL
      );

      if ($result === NULL) {
        return new JsonResponse([
          'success' => FALSE,
          'status' => 'ignored',
          'message' => 'Message did not resolve to a merchant trade.',
        ], 400);
      }

      return new JsonResponse($result, !empty($result['success']) ? 200 : 400);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'status' => 'blocked',
        'error' => $e->getMessage(),
      ], 400);
    }
  }

}
