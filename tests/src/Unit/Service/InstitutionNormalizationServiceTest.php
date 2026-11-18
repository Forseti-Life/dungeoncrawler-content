<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers first-slice institution normalization rules.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService
 */
class InstitutionNormalizationServiceTest extends UnitTestCase {

  /**
   * @covers ::normalizeDomain
   */
  public function testNormalizeDomainMapsStandardAliases(): void {
    $service = new InstitutionNormalizationService();

    $this->assertSame('ancestry', $service->normalizeDomain('race'));
    $this->assertSame('profession', $service->normalizeDomain('occupation'));
    $this->assertSame('place', $service->normalizeDomain('community'));
    $this->assertSame('state', $service->normalizeDomain('watch'));
    $this->assertSame('faith', $service->normalizeDomain('religion'));
    $this->assertSame('organization', $service->normalizeDomain('faction'));
    $this->assertSame('kin', $service->normalizeDomain('clan'));
    // An ID built under an older type name maps to the current one.
    $this->assertSame('institution_kin_blackroot-clan', $service->currentSubjectId('institution_family_blackroot-clan'));
    $this->assertSame('institution_ancestry_elf', $service->currentSubjectId('institution_ancestry_elf'));
  }

  /**
   * @covers ::normalizeInstitutionInput
   * @covers ::buildInstitutionSubjectId
   */
  public function testNormalizeInstitutionInputBuildsDeterministicSubjectId(): void {
    $service = new InstitutionNormalizationService();

    $normalized = $service->normalizeInstitutionInput([
      'domain' => 'class',
      'display_name' => '   City   Watch  ',
      'parent_subject_id' => ' institution_state_fordwatch-crown ',
    ]);

    $this->assertSame('profession', $normalized['domain']);
    $this->assertSame('City Watch', $normalized['display_name']);
    $this->assertSame('city-watch', $normalized['normalized_label']);
    $this->assertSame('institution_profession_city-watch', $normalized['subject_id']);
    $this->assertSame('institution_state_fordwatch-crown', $normalized['parent_subject_id']);
  }

  /**
   * @covers ::normalizeInstitutionInput
   */
  public function testNormalizeInstitutionInputRejectsUnsupportedDomains(): void {
    $service = new InstitutionNormalizationService();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Institution domain is required and must be supported.');

    $service->normalizeInstitutionInput([
      'domain' => 'planet',
      'display_name' => 'Golarion',
    ]);
  }

}
