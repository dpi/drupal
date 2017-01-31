<?php

namespace Drupal\views\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to extract an entity.
 *
 * @ViewsArgumentDefault(
 *   id = "entity",
 *   title = @Translation("Entity ID from URL")
 * )
 */
class Entity extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new Entity instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    if ($entity_type_id = $this->getEntityTypeId()) {
      foreach ($this->routeMatch->getParameters() as $parameter) {
        if ($parameter instanceof EntityInterface && $parameter->getEntityTypeId() == $entity_type_id) {
          return $parameter->id();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url'];
  }

  /**
   * Gets the entity type ID if the route belongs to an entity type.
   *
   * @return string|FALSE
   *   The entity type ID for this route, or FALSE if the route is not for an
   *   entity.
   */
  protected function getEntityTypeId() {
    $route = $this->routeMatch->getRouteObject();
    $current_path = $route->getPath();
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      foreach ($definition->getLinkTemplates() as $path) {
        if ($current_path === $path) {
          return $entity_type_id;
        }
      }
    }
    return FALSE;
  }

}
