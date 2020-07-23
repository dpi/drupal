<?php

namespace Drupal\Tests\node\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Provides tests for node routes.
 *
 * @group node
 */
class NodeRoutesTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'content_translation',
    'language',
    'node_routes_test',
  ];

  /**
   * Tests up-casting for revision routes.
   */
  public function testRevisionRoutes() {
    ConfigurableLanguage::createFromLangcode('it')->save();

    $this->drupalPlaceBlock('node_routes_test_block');

    $account = $this->drupalCreateUser([
      'view article revisions',
      'revert article revisions',
      'delete article revisions',
      'edit any article content',
      'delete any article content',
      'translate any entity',
    ]);
    $this->drupalLogin($account);

    $node = Node::create([
      'type' => 'article',
      'title' => 'Foo',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node->save();
    $nid = $node->id();
    $initial_rid = $node->getRevisionId();

    $node->setTitle('Bar');
    $node->setNewRevision(TRUE);
    $node->save();
    $current_rid = $node->getRevisionId();

    $this->drupalGet("node/$nid/revisions");
    $this->assertSession()->pageTextContainsOnce(sprintf('A page with node: (%s) and without revision', $nid));

    $this->drupalGet("node/$nid/revisions/$current_rid/view");
    $this->assertSession()->pageTextContainsOnce(sprintf('A page with node: (%s) and node revision: (%s)', $nid, $current_rid));

    $this->drupalGet("node/$nid/revisions/$initial_rid/revert");
    $this->assertSession()->pageTextContainsOnce(sprintf('A page with node: (%s) and node revision: (%s)', $nid, $initial_rid));

    $this->drupalGet("node/$nid/revisions/$initial_rid/revert/it");
    $this->assertSession()->pageTextContainsOnce(sprintf('A page with node: (%s) and node revision: (%s)', $nid, $initial_rid));

    $this->drupalGet("node/$nid/revisions/$initial_rid/delete");
    $this->assertSession()->pageTextContainsOnce(sprintf('A page with node: (%s) and node revision: (%s)', $nid, $initial_rid));
  }

}
