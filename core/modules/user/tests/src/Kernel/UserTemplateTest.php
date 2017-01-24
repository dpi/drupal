<?php

namespace Drupal\Tests\user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\Entity\User;

/**
 * Tests template output for user module.
 *
 * @group user
 */
class UserTemplateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['user', 'user_access_test'];

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The current user service
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * A user for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->renderer = $this->container->get('renderer');
    $this->currentUser = $this->container->get('current_user');
    $this->user = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
  }

  /**
   * Tests user can see a link to own profile.
   */
  function testUsernameTemplateSelfUserLink() {
    $this->user->save();
    $this->currentUser->setAccount($this->user);
    $url = $this->user->toUrl();

    $build = [
      '#theme' => 'username',
      '#account' => $this->user,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));

    $element = $this->xpath('//a[@href=:url]', [':url' => $url->toString()]);
    $this->assertTrue(!empty($element), 'Account can view link to its own profile.');
  }

  /**
   * Tests user cannot see link to own profile when explicitly denied.
   */
  function testUsernameTemplateSelfNoUserLink() {
    $this->user
      // 'no_view' username forces access denied.
      ->setUsername('no_view')
      ->save();

    $this->currentUser->setAccount($this->user);
    $url = $this->user->toUrl();

    $build = [
      '#theme' => 'username',
      '#account' => $this->user,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));

    $element = $this->xpath('//a[@href=:url]', [':url' => $url->toString()]);
    $this->assertTrue(empty($element), 'Account cannot view link to profile.');
  }

  /**
   * Tests user cannot see link to if it has no permissions.
   *
   * Tests an account without the "View user information" permission
   */
  function testUsernameTemplateNoPermissionNoUserLink() {
    $this->currentUser->setAccount(new AnonymousUserSession());

    $this->user->save();
    $url = $this->user->toUrl();

    $build = [
      '#theme' => 'username',
      '#account' => $this->user,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));

    $element = $this->xpath('//a[@href=:url]', [':url' => $url->toString()]);
    $this->assertTrue(empty($element), 'Account without permissions cannot view link to user profile.');
  }

}
