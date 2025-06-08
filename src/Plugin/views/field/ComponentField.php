<?php

namespace Drupal\component_views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\component_field\Service\ComponentDiscovery;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enhanced Views field plugin for Component Field rendering.
 *
 * @ViewsField("component_field")
 */
class ComponentField extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The component discovery service.
   */
  protected ComponentDiscovery $componentDiscovery;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ComponentDiscovery $component_discovery,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->componentDiscovery = $component_discovery;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('component_field.discovery'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['display_mode'] = ['default' => 'full'];
    $options['component_filter'] = ['default' => ''];
    $options['limit_components'] = ['default' => 0];
    $options['show_wrapper'] = ['default' => TRUE];
    $options['enable_animation'] = ['default' => FALSE];
    $options['component_classes'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Get available components for filtering options
    $components = $this->componentDiscovery->discoverComponents();
    $component_options = ['' => $this->t('- All Components -')];
    foreach ($components as $name => $component) {
      $component_options[$name] = $component['label'] ?? $name;
    }

    $form['display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Mode'),
      '#options' => [
        'full' => $this->t('Full Rendering'),
        'summary' => $this->t('Summary/Preview'),
        'list' => $this->t('List View'),
        'compact' => $this->t('Compact View'),
      ],
      '#default_value' => $this->options['display_mode'],
      '#description' => $this->t('How should components be displayed in this view.'),
    ];

    $form['component_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter Components'),
      '#options' => $component_options,
      '#default_value' => $this->options['component_filter'],
      '#description' => $this->t('Show only specific component types. Leave empty to show all.'),
    ];

    $form['limit_components'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit Number of Components'),
      '#default_value' => $this->options['limit_components'],
      '#min' => 0,
      '#description' => $this->t('Maximum number of components to display per item. 0 for unlimited.'),
    ];

    $form['show_wrapper'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show wrapper elements'),
      '#default_value' => $this->options['show_wrapper'],
      '#description' => $this->t('Include wrapper elements around components for styling and structure.'),
    ];

    $form['enable_animation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable animations'),
      '#default_value' => $this->options['enable_animation'],
      '#description' => $this->t('Add CSS animation classes to components.'),
    ];

    $form['component_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Additional CSS Classes'),
      '#default_value' => $this->options['component_classes'],
      '#description' => $this->t('Space-separated CSS classes to add to component wrappers.'),
    ];

    // Display statistics
    $stats = $this->componentDiscovery->getComponentStats();
    $form['stats'] = [
      '#type' => 'details',
      '#title' => $this->t('Component Statistics'),
      '#open' => FALSE,
      'info' => [
        '#markup' => '<div class="component-views-stats">' .
          '<p><strong>' . $this->t('Total Components:') . '</strong> ' . $stats['total'] . '</p>' .
          '<p><strong>' . $this->t('With Properties:') . '</strong> ' . $stats['with_props'] . '</p>' .
          '<p><strong>' . $this->t('Total Properties:') . '</strong> ' . $stats['total_props'] . '</p>' .
          '</div>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    try {
      $entity = $this->getEntity($values);
      if (!$entity) {
        return [];
      }

      $field_name = $this->definition['field_name'];
      if (!$entity->hasField($field_name)) {
        return [];
      }

      $field_items = $entity->get($field_name);
      if ($field_items->isEmpty()) {
        return $this->renderEmptyState();
      }

      $components = $this->componentDiscovery->discoverComponents();
      $rendered_components = [];
      $count = 0;
      $limit = (int) $this->options['limit_components'];

      foreach ($field_items as $delta => $item) {
        // Check limit
        if ($limit > 0 && $count >= $limit) {
          break;
        }

        $component_type = $item->get('component_type')->getValue();
        $configuration = $item->getConfiguration();

        // Apply component filter
        if (!empty($this->options['component_filter']) && 
            $component_type !== $this->options['component_filter']) {
          continue;
        }

        if (!isset($components[$component_type])) {
          // Component not found - log and continue
          $this->loggerFactory->get('component_views')->warning(
            'Component @type not found in Views field rendering', 
            ['@type' => $component_type]
          );
          continue;
        }

        $component_render = $this->renderComponent(
          $component_type, 
          $configuration, 
          $components[$component_type], 
          $delta
        );

        if (!empty($component_render)) {
          $rendered_components[] = $component_render;
          $count++;
        }
      }

      if (empty($rendered_components)) {
        return $this->renderEmptyState();
      }

      return $this->buildFieldOutput($rendered_components);

    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->error(
        'Error rendering component field in Views: @error',
        ['@error' => $e->getMessage()]
      );
      return $this->renderErrorState();
    }
  }

  /**
   * Render individual component based on display mode.
   */
  protected function renderComponent(string $component_type, array $configuration, array $component_info, int $delta): array {
    $display_mode = $this->options['display_mode'];
    
    switch ($display_mode) {
      case 'summary':
        return $this->renderComponentSummary($component_type, $configuration, $component_info, $delta);
      
      case 'list':
        return $this->renderComponentList($component_type, $configuration, $component_info, $delta);
      
      case 'compact':
        return $this->renderComponentCompact($component_type, $configuration, $component_info, $delta);
      
      case 'full':
      default:
        return $this->renderComponentFull($component_type, $configuration, $component_info, $delta);
    }
  }

  /**
   * Render component in full mode.
   */
  protected function renderComponentFull(string $component_type, array $configuration, array $component_info, int $delta): array {
    $build = [
      '#theme' => 'component_field_dynamic',
      '#component_type' => $component_type,
      '#component_name' => $component_info['label'] ?? $component_type,
      '#component_path' => $component_info['relative_path'] ?? '',
      '#configuration' => $configuration,
      '#attributes' => $this->buildComponentAttributes($component_type, $delta),
    ];

    if ($this->options['show_wrapper']) {
      $build = $this->wrapComponent($build, $component_type, $delta);
    }

    return $build;
  }

  /**
   * Render component in summary mode.
   */
  protected function renderComponentSummary(string $component_type, array $configuration, array $component_info, int $delta): array {
    $summary_data = $this->extractSummaryData($component_type, $configuration);
    
    return [
      '#theme' => 'component_views_summary',
      '#component_type' => $component_type,
      '#component_label' => $component_info['label'] ?? $component_type,
      '#summary_data' => $summary_data,
      '#delta' => $delta,
      '#attributes' => $this->buildComponentAttributes($component_type, $delta),
    ];
  }

  /**
   * Render component in list mode.
   */
  protected function renderComponentList(string $component_type, array $configuration, array $component_info, int $delta): array {
    return [
      '#theme' => 'component_views_list_item',
      '#component_type' => $component_type,
      '#component_label' => $component_info['label'] ?? $component_type,
      '#configuration_count' => count($configuration),
      '#delta' => $delta,
      '#attributes' => $this->buildComponentAttributes($component_type, $delta),
    ];
  }

  /**
   * Render component in compact mode.
   */
  protected function renderComponentCompact(string $component_type, array $configuration, array $component_info, int $delta): array {
    // Extract key information for compact display
    $key_info = $this->extractKeyInfo($component_type, $configuration);
    
    return [
      '#theme' => 'component_views_compact',
      '#component_type' => $component_type,
      '#component_label' => $component_info['label'] ?? $component_type,
      '#key_info' => $key_info,
      '#delta' => $delta,
      '#attributes' => $this->buildComponentAttributes($component_type, $delta),
    ];
  }

  /**
   * Build component attributes.
   */
  protected function buildComponentAttributes(string $component_type, int $delta): array {
    $classes = [
      'component-views-item',
      'component-views-' . str_replace('_', '-', $component_type),
      'component-views-delta-' . $delta,
      'component-views-mode-' . $this->options['display_mode'],
    ];

    if ($this->options['enable_animation']) {
      $classes[] = 'component-views-animated';
    }

    if (!empty($this->options['component_classes'])) {
      $custom_classes = array_filter(explode(' ', $this->options['component_classes']));
      $classes = array_merge($classes, $custom_classes);
    }

    return [
      'class' => $classes,
      'data-component-type' => $component_type,
      'data-component-delta' => $delta,
      'data-display-mode' => $this->options['display_mode'],
    ];
  }

  /**
   * Wrap component with container.
   */
  protected function wrapComponent(array $build, string $component_type, int $delta): array {
    return [
      '#type' => 'container',
      '#attributes' => $this->buildComponentAttributes($component_type, $delta),
      'component' => $build,
    ];
  }

  /**
   * Extract summary data from component configuration.
   */
  protected function extractSummaryData(string $component_type, array $configuration): array {
    $summary = [];
    
    // Component-specific summary extraction
    switch ($component_type) {
      case 'button':
        $summary['label'] = $configuration['label'] ?? 'Button';
        $summary['variant'] = $configuration['variant'] ?? 'contained';
        $summary['color'] = $configuration['color'] ?? 'primary';
        break;
        
      case 'card':
        $summary['title'] = $configuration['title'] ?? '';
        $summary['elevation'] = $configuration['elevation'] ?? 1;
        break;
        
      case 'typography':
        $summary['variant'] = $configuration['variant'] ?? 'body1';
        $summary['content'] = isset($configuration['content']) ? 
          mb_substr(strip_tags($configuration['content']), 0, 50) . '...' : '';
        break;
        
      default:
        // Generic summary - show first few props
        $summary = array_slice($configuration, 0, 3, true);
        break;
    }
    
    return $summary;
  }

  /**
   * Extract key information for compact display.
   */
  protected function extractKeyInfo(string $component_type, array $configuration): string {
    switch ($component_type) {
      case 'button':
        return $configuration['label'] ?? 'Button';
        
      case 'card':
        return $configuration['title'] ?? 'Card';
        
      case 'typography':
        $content = $configuration['content'] ?? $configuration['text'] ?? '';
        return mb_substr(strip_tags($content), 0, 30) . (strlen($content) > 30 ? '...' : '');
        
      default:
        // Show most relevant configuration value
        $key_props = ['title', 'label', 'content', 'text', 'name'];
        foreach ($key_props as $prop) {
          if (isset($configuration[$prop]) && !empty($configuration[$prop])) {
            $value = is_string($configuration[$prop]) ? $configuration[$prop] : json_encode($configuration[$prop]);
            return mb_substr(strip_tags($value), 0, 30) . (strlen($value) > 30 ? '...' : '');
          }
        }
        return $component_type;
    }
  }

  /**
   * Build final field output.
   */
  protected function buildFieldOutput(array $rendered_components): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'component-views-field',
          'component-views-mode-' . $this->options['display_mode'],
          'component-views-count-' . count($rendered_components),
        ],
      ],
      'components' => $rendered_components,
    ];

    // Add libraries
    $build['#attached']['library'][] = 'component_views/field';
    
    if ($this->options['enable_animation']) {
      $build['#attached']['library'][] = 'component_views/animations';
    }

    return $build;
  }

  /**
   * Render empty state.
   */
  protected function renderEmptyState(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-views-empty']],
      'message' => [
        '#markup' => '<em>' . $this->t('No components found.') . '</em>',
      ],
    ];
  }

  /**
   * Render error state.
   */
  protected function renderErrorState(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-views-error']],
      'message' => [
        '#markup' => '<em>' . $this->t('Error displaying components.') . '</em>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Ensure the field is added to the query
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    // Component fields can be sorted by component_type
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    if (isset($this->field_alias)) {
      $this->query->addOrderBy($this->tableAlias, 'component_type', $order);
    }
  }
}