<?php

namespace Drupal\media;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an access control handler for media items.
 */
class MediaAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MediaAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager = NULL) {
    parent::__construct($entity_type);
    if (!isset($entity_type_manager)) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $entity_type_manager argument is deprecated in drupal:9.3.0 and will be required in drupal:10.0.0. See https://www.drupal.org/node/3214171', E_USER_DEPRECATED);
      $entity_type_manager = \Drupal::entityTypeManager();
    }
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $administerPermission = $this->entityType->getAdminPermission();
    /** @var \Drupal\media\MediaInterface $entity */
    // Allow admin permission to override all operations except for 'view all
    // revisions' so that the number of revisions is still checked and the
    // revisions tab is still hidden for users with the admin permission when
    // there is only 1 revision.
    if ($operation !== 'view all revisions' && $account->hasPermission($administerPermission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $type = $entity->bundle();
    $is_owner = ($account->id() && $account->id() === $entity->getOwnerId());
    switch ($operation) {
      case 'view':
        if ($entity->isPublished()) {
          $access_result = AccessResult::allowedIf($account->hasPermission('view media'))
            ->cachePerPermissions()
            ->addCacheableDependency($entity);
          if (!$access_result->isAllowed()) {
            $access_result->setReason("The 'view media' permission is required when the media item is published.");
          }
        }
        elseif ($account->hasPermission('view own unpublished media')) {
          $access_result = AccessResult::allowedIf($is_owner)
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
          if (!$access_result->isAllowed()) {
            $access_result->setReason("The user must be the owner and the 'view own unpublished media' permission is required when the media item is unpublished.");
          }
        }
        else {
          $access_result = AccessResult::neutral()
            ->cachePerPermissions()
            ->addCacheableDependency($entity)
            ->setReason("The user must be the owner and the 'view own unpublished media' permission is required when the media item is unpublished.");
        }
        return $access_result;

      case 'update':
        if ($account->hasPermission('edit any ' . $type . ' media')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($account->hasPermission('edit own ' . $type . ' media') && $is_owner) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        }
        // @todo Deprecate this permission in
        // https://www.drupal.org/project/drupal/issues/2925459.
        if ($account->hasPermission('update any media')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($account->hasPermission('update media') && $is_owner) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::neutral("The following permissions are required: 'update any media' OR 'update own media' OR '$type: edit any media' OR '$type: edit own media'.")->cachePerPermissions();

      case 'delete':
        if ($account->hasPermission('delete any ' . $type . ' media')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($account->hasPermission('delete own ' . $type . ' media') && $is_owner) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        }
        // @todo Deprecate this permission in
        // https://www.drupal.org/project/drupal/issues/2925459.
        if ($account->hasPermission('delete any media')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($account->hasPermission('delete media') && $is_owner) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::neutral("The following permissions are required: 'delete any media' OR 'delete own media' OR '$type: delete any media' OR '$type: delete own media'.")->cachePerPermissions();

      case 'view all revisions':
        // Perform basic permission checks first.
        if (!$account->hasPermission('view all media revisions') && !$account->hasPermission($administerPermission)) {
          return AccessResult::neutral(sprintf("The following permissions are required: 'view all media revisions' OR '%s'.", $administerPermission))->cachePerPermissions();
        }

        // There should be at least two revisions. If the revision ID of the
        // given media item and the revision ID of the default revision differ,
        // then we already have two different revisions so there is no need for
        // a separate database check.
        if ($entity->isDefaultRevision() && ($this->countDefaultLanguageRevisions($entity) == 1)) {
          return AccessResult::forbidden('There must be at least two revisions.')->cachePerPermissions()->addCacheableDependency($entity);
        }
        elseif ($account->hasPermission($administerPermission)) {
          return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
        }
        else {
          // First check the access to the default revision and finally, if the
          // media passed in is not the default revision then access to that,
          // too.
          return AccessResult::allowedIf(
            $this->access($this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id()), 'view', $account) &&
            ($entity->isDefaultRevision() || $this->access($entity, 'view', $account))
          )->cachePerPermissions()->addCacheableDependency($entity);
        }

      default:
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $permissions = [
      'administer media',
      'create media',
      'create ' . $entity_bundle . ' media',
    ];
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item for which to to count the revisions.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  protected function countDefaultLanguageRevisions(MediaInterface $media) {
    $count = $this->entityTypeManager->getStorage($media->getEntityTypeId())
      ->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($this->entityType->getKey('id'), $media->id())
      ->condition($this->entityType->getKey('default_langcode'), 1)
      ->count()
      ->execute();
    return $count;
  }

}
