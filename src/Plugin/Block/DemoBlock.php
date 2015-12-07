<?php

/**
 * @file
 * Contains \Drupal\challenge4\Plugin\Block\DemoBlock.
 */

namespace Drupal\challenge4\Plugin\Block;

//  Basic block plugin requirement.
use Drupal\Core\Block\BlockBase;

// Used when injecting services.
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Required for the injected EntityManager object.
use Drupal\Core\Entity\EntityManager;

// Required for the injected QueryFactory object.
use Drupal\Core\Entity\Query\QueryFactory;

/**
 * Provides a 'DemoBlock' block.
 *
 * @Block(
 *  id = "demo_block",
 *  admin_label = @Translation("Demo block"),
 * )
 */
class DemoBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var Drupal\Core\Entity\EntityManager
   */
  protected $entity_manager;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entity_query;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   *
   * Add type-hinted parameters for any services to be injected into this class
   * following the normal parameters.
   */
  public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        EntityManager $entity_manager,
        QueryFactory $entity_query
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Make each of injected service available as a variable in the class.
    $this->entity_manager = $entity_manager;
    $this->entity_query = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    // Add additional services to the list of normal class parameters.
    // Use container->get() for each service, and add them in the same
    // order they were listed in __construct().

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $items = array();

    // When on a node page, the parameter is already upcast as a node.
    $node = \Drupal::routeMatch()->getParameter('node');

    if ($node) {

      // Read about entity query at http://www.sitepoint.com/drupal-8-version-entityfieldquery/.
      // See query API at https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Query%21QueryInterface.php/interface/QueryInterface/8.

      // The static method for using the query service would have been:
      // $query = \Drupal::entityQuery('node');

      // Use injected queryFactory to look for items that are related
      // to the show that matches the input nid.
      $query = $this->entity_query->get('node')
        // published only
        ->condition('status', 1)
        // only nodes of the type 'tv_episode'
        ->condition('type', 'tv_episode')
        // order by season number
        ->sort('field_related_season.entity.field_season_number.value', 'DESC')
        // order by episode number
        ->sort('field_episode_number', 'DESC')
        // just the latest 5 items
        ->range(0, 5)
        // Note we can chain into any field on the related entity in a condition.
        ->condition('field_related_season.entity.field_related_show.entity.nid', $node->id());
      $nids = $query->execute();

      // Note that entity_load() is deprecated.
      // The static method of loading the entity would have been:
      // \Drupal\node\Entity\Node::load();

      // Use the injected entityManager to load the results into an array of node objects.
      $nodes = $this->entity_manager->getStorage('node')->loadMultiple($nids);

       foreach ($nodes as $node) {
        // Create a render array for each title field.

        // Note that field_view_field() is deprecated, use the view method on the field.
        $title = $node->title->view('full');

        // Entities have a handy toLink() method.
        $items[] = $node->toLink($title);

      }

    }

    // Return a render array for a html list.
    return [
        '#theme' => 'item_list',
        '#items' => $items,

        // Cache it by route. Without this the same content would appear on every page.
        // See https://www.drupal.org/developing/api/8/render/arrays/cacheability
        // See https://www.drupal.org/developing/api/8/cache/contexts
        '#cache' => [
          'contexts' => [
            'route',
          ],
        ],
    ];

  }

}
