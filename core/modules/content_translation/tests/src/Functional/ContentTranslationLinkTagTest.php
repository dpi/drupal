<?php

namespace Drupal\Tests\content_translation\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\content_translation_test\Entity\EntityTestTranslatableNoUISkip;

/**
 * Tests whether canonical link tags are present for content entities.
 *
 * @group content_translation
 */
class ContentTranslationLinkTagTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_test', 'content_translation', 'content_translation_test', 'language'];

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The added languages.
   *
   * @var array
   */
  protected $langcodes;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->languageManager = $this->container->get('language_manager');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->setupUsers();
    $this->setupLanguages();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
  }

  /**
   * Add additional languages.
   */
  protected function setupLanguages() {
    $this->langcodes = ['it', 'fr'];
    foreach ($this->langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    // Add default language.
    $this->langcodes[] = $this->languageManager
      ->getDefaultLanguage()
      ->getId();
  }

  /**
   * Set up user.
   */
  protected function setupUsers() {
    $user = $this->drupalCreateUser([
      'view test entity',
      'view test entity translations',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Create a test entity with translations.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An entity with translations.
   */
  protected function createTranslatableEntity() {
    $entity = EntityTestMul::create(['label' => $this->randomString()]);

    // Create translations for non default languages.
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $non_default_langcodes = array_diff($this->langcodes, [$default_langcode]);
    foreach ($non_default_langcodes as $langcode) {
      $entity->addTranslation($langcode, ['label' => $this->randomString()]);
    }
    $entity->save();

    return $entity;
  }

  /**
   * Tests alternate link tag found for entity types with canonical links.
   */
  public function testCanonicalAlternateTags() {
    $definition = $this->entityTypeManager->getDefinition('entity_test_mul');
    $this->assertTrue($definition->hasLinkTemplate('canonical'), 'Canonical link template found for entity_test.');

    $entity = $this->createTranslatableEntity();
    $url_base = $entity->toUrl('canonical');

    /** @var \Drupal\Core\Url[] $urls */
    $urls = array_map(
      function ($langcode) use ($url_base) {
        return (clone $url_base)
          ->setOption('language', $this->languageManager->getLanguage($langcode))
          ->setAbsolute();
      }, $this->langcodes
    );

    // Ensure link tags are found in languages.
    foreach ($urls as $url) {
      $langcode = $url->getOption('language')->getId();
      $this->drupalGet($url);

      foreach ($urls as $url_alternate) {
        $langcode_alternate = $url_alternate->getOption('language')->getId();
        $args = [':href' => $url_alternate->toString(), ':hreflang' => $langcode_alternate];
        $links = $this->xpath('head/link[@rel = "alternate" and @href = :href and @hreflang = :hreflang]', $args);
        $message = sprintf('The "%s" translation has the correct alternate hreflang link for "%s": %s.', $langcode, $langcode_alternate, $url->toString());
        $this->assertTrue(isset($links[0]), $message);
      }
    }
  }

  /**
   * Tests alternate link tag missing for entity types without canonical links.
   */
  public function testCanonicalAlternateTagsMissing() {
    $definition = $this->entityTypeManager->getDefinition('entity_test_translatable_no_skip');
    // Ensure 'canonical' link template does not exist, in case it is added in
    // the future.
    $this->assertFalse($definition->hasLinkTemplate('canonical'), 'Canonical link template does not exist for entity_test_translatable_no_skip entity.');

    $entity = EntityTestTranslatableNoUISkip::create();
    $entity->save();
    $this->drupalGet($entity->toUrl('edit-form'));

    $result = $this->xpath('//link[@rel="alternate" and @hreflang]');
    $this->assertFalse($result, 'No alternate link tag found.');
  }

}
