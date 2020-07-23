<?php

namespace Drupal\node_routes_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a testing block for node routes.
 *
 * @Block(
 *  id = "node_routes_test_block",
 *  admin_label = @Translation("Node routes test block")
 * )
 */
class NodeRoutesTestBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Creates a NodeRoutesTestBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cacheable_metadata = CacheableMetadata::createFromObject($this)
      ->addCacheContexts(['route']);
    $article_page_markup = 'A page without node';
    $revision_page_markup = ' and without revision';
    // Upcasted node object.
    if ($node = $this->routeMatch->getParameter('node')) {
      $article_page_markup = sprintf('A page with node: (%s)', $node->id());
      $cacheable_metadata->addCacheableDependency($node);
    }
    // Upcasted node revision object.
    if ($node_revision = $this->routeMatch->getParameter('node_revision')) {
      $revision_page_markup = sprintf(' and node revision: (%s)', $node_revision->getRevisionId());
    }

    $build = [
      '#markup' => $article_page_markup . $revision_page_markup,
    ];
    // Apply cacheability.
    $cacheable_metadata->applyTo($build);
    return $build;
  }

}
