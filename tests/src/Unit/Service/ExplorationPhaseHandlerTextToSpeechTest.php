<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CampaignClockService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler;
use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;
use Drupal\dungeoncrawler_content\Service\KnowledgeAcquisitionService;
use Drupal\dungeoncrawler_content\Service\MagicItemService;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\dungeoncrawler_content\Service\TextToSpeechIntegrationService;
use Drupal\dungeoncrawler_content\Service\HazardService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests TTS room-entry behavior for exploration narration.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerTextToSpeechTest extends UnitTestCase {

  /**
   * @covers ::buildRoomEntryNarrationAudio
   */
  public function testRoomEntryAudioUsesNarratorTextWhenAvailable(): void {
    $tts = $this->getMockBuilder(TextToSpeechIntegrationService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['synthesizeSpeech', 'storeAudioResult'])
      ->getMock();
    $tts->expects($this->once())
      ->method('synthesizeSpeech')
      ->with('The corridor opens into an ancient shrine lit by blue witchfire.', [
        'voice_name' => 'en-US-Standard-D',
        'audio_encoding' => 'MP3',
        'speaking_rate' => 0.85,
        'pitch' => -6.0,
        'volume_gain_db' => 2.0,
      ])
      ->willReturn(['success' => TRUE, 'audioContent' => 'encoded']);
    $tts->expects($this->once())
      ->method('storeAudioResult')
      ->with(['success' => TRUE, 'audioContent' => 'encoded'], 'public://forseti-tts-room-entry')
      ->willReturn([
        'success' => TRUE,
        'uri' => 'public://forseti-tts-room-entry/shrine.mp3',
      ]);

    $file_url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $file_url_generator->expects($this->once())
      ->method('generateString')
      ->with('public://forseti-tts-room-entry/shrine.mp3')
      ->willReturn('/sites/default/files/forseti-tts-room-entry/shrine.mp3');

    $handler = $this->buildHandler($tts, $file_url_generator);
    $method = new \ReflectionMethod($handler, 'buildRoomEntryNarrationAudio');
    $method->setAccessible(TRUE);

    $result = $method->invoke($handler, [
      'room_id' => 'shrine',
      'name' => 'Ancient Shrine',
      'description' => 'Dust hangs in the air.',
    ], 'The corridor opens into an ancient shrine lit by blue witchfire.');

    $this->assertSame('/sites/default/files/forseti-tts-room-entry/shrine.mp3', $result['narration_audio_url']);
    $this->assertSame('The corridor opens into an ancient shrine lit by blue witchfire.', $result['narration_audio_text']);
    $this->assertSame('room_entry', $result['narration_audio_source']);
  }

  /**
   * @covers ::buildRoomEntryNarrationAudio
   */
  public function testRoomEntryAudioFallsBackToRoomDescription(): void {
    $tts = $this->getMockBuilder(TextToSpeechIntegrationService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['synthesizeSpeech', 'storeAudioResult'])
      ->getMock();
    $tts->expects($this->once())
      ->method('synthesizeSpeech')
      ->with('Ancient Shrine. Dust hangs in the air.', [
        'voice_name' => 'en-US-Standard-D',
        'audio_encoding' => 'MP3',
        'speaking_rate' => 0.85,
        'pitch' => -6.0,
        'volume_gain_db' => 2.0,
      ])
      ->willReturn(['success' => TRUE, 'audioContent' => 'encoded']);
    $tts->expects($this->once())
      ->method('storeAudioResult')
      ->with(['success' => TRUE, 'audioContent' => 'encoded'], 'public://forseti-tts-room-entry')
      ->willReturn([
        'success' => TRUE,
        'uri' => 'public://forseti-tts-room-entry/shrine-description.mp3',
      ]);

    $file_url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $file_url_generator->expects($this->once())
      ->method('generateString')
      ->with('public://forseti-tts-room-entry/shrine-description.mp3')
      ->willReturn('/sites/default/files/forseti-tts-room-entry/shrine-description.mp3');

    $handler = $this->buildHandler($tts, $file_url_generator);
    $method = new \ReflectionMethod($handler, 'buildRoomEntryNarrationAudio');
    $method->setAccessible(TRUE);

    $result = $method->invoke($handler, [
      'room_id' => 'shrine',
      'name' => 'Ancient Shrine',
      'description' => 'Dust hangs in the air.',
    ], NULL);

    $this->assertSame('Ancient Shrine. Dust hangs in the air.', $result['narration_audio_text']);
    $this->assertSame('room_description', $result['narration_audio_source']);
  }

  /**
   * Builds an ExplorationPhaseHandler with lightweight mocks.
   */
  private function buildHandler(?TextToSpeechIntegrationService $tts = NULL, ?FileUrlGeneratorInterface $file_url_generator = NULL): ExplorationPhaseHandler {
    $update = $this->createMock(Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $database = $this->createMock(Connection::class);
    $database->method('update')->willReturn($update);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new ExplorationPhaseHandler(
      $database,
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $this->createMock(CharacterStateService::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(AiGmService::class),
      $this->createMock(NarrationEngine::class),
      $this->createMock(KnowledgeAcquisitionService::class),
      $this->createMock(HazardService::class),
      $this->createMock(MagicItemService::class),
      $this->createMock(GameplayActionProcessor::class),
      $this->createMock(CampaignClockService::class),
      $tts,
      $file_url_generator
    );
  }

}
