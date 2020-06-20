<?php

namespace Drupal\node\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for node revisions.
 *
 * @ingroup node_access
 */
class NodeRevisionAccessCheck implements AccessInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a new NodeRevisionAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * Checks routing access for the node revision.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $node_revision
   *   (optional) The node revision ID. If not specified, but $node is, access
   *   is checked for that object's revision.
   * @param \Drupal\node\NodeInterface $node
   *   (optional) A node object. Used for checking access to a node's default
   *   revision when $node_revision is unspecified. Ignored when $node_revision
   *   is specified. If neither $node_revision nor $node are specified, then
   *   access is denied.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, $node_revision = NULL, NodeInterface $node = NULL) {
    @trigger_error('NodeRevisionAccessCheck is deprecated in Drupal 9.1.x and will be removed before Drupal 10.0.x. Use "_entity_access" requirement with relevant operation instead. See https://www.drupal.org/node/3043321', E_USER_DEPRECATED);
    if ($node_revision) {
      $node = $this->nodeStorage->loadRevision($node_revision);
    }
    $operation = $route->getRequirement('_access_node_revision');
    return AccessResult::allowedIf($node && $this->checkAccess($node, $account, $operation))->cachePerPermissions()->addCacheableDependency($node);
  }

  /**
   * Checks node revision access.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   * @param string $op
   *   (optional) The specific operation being checked. Defaults to 'view.'
   *
   * @return bool
   *   TRUE if the operation may be performed, FALSE otherwise.
   */
  public function checkAccess(NodeInterface $node, AccountInterface $account, $op = 'view') {
    @trigger_error('NodeRevisionAccessCheck is deprecated in Drupal 9.1.x and will be removed before Drupal 10.0.x. Use "_entity_access" requirement with relevant operation instead. See https://www.drupal.org/node/3043321', E_USER_DEPRECATED);
    // Converts legacy operations for this access check to new revision
    // operation found in access control handler.
    // E.g route _access_node_revision: 'update' to $node->access('revert')
    $entityOperationMap = [
      'view' => 'view all revisions',
      'update' => 'revert',
      'delete' => 'delete revision',
    ];
    return isset($entityOperationMap[$op]) ?
      $node->access($entityOperationMap[$op], $account) :
      FALSE;
  }

}
