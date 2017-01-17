<?php

namespace Drupal\Tests\menu_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;

/**
 * Tests node type settings are updated when menus are changed.
 *
 * @group menu_ui
 */
class MenuNodeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'menu_ui', 'node', 'user', 'field', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['menu_ui', 'node']);
    $this->installEntitySchema('node');
  }

  /**
   * Tests whether node type available menus are updated when a menu is deleted.
   */
  public function testNodeTypeAvailableMenu() {
    $menu = Menu::create(['id' => $this->randomMachineName()]);
    $menu->save();
    $node_type = NodeType::create(['type' => $this->randomMachineName()])
      ->setThirdPartySetting('menu_ui', 'available_menus', [$menu->id()]);
    $node_type->save();
    $menu->delete();

    $node_type = NodeType::load($node_type->id());
    $menu_ids = $node_type->getThirdPartySetting('menu_ui', 'available_menus');
    $this->assertFalse(in_array($menu->id(), $menu_ids), 'Content type does not contain deleted menu in available_menus setting.');
  }

}
