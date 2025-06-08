<?php

namespace Drupal\component_views\Plugin\views\display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\component_field\Service\ComponentDiscovery;
use Drupal\component_views\Service\ComponentViewsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Optimized display plugin for component-heavy views.
 *
 * @ViewsDisplay(
 *   id = "component_display",
 *   title = @Translation("Component Display"),
 *   help = @Translation("Optimized display for views with component fields."),
 *   theme = "views_view",
 *   register_theme = FALSE,
 *   uses_hook_menu = FALSE,
 *   uses_route = FALSE,
 *   contextual_links_locations = {"view"},
 *   admin = @Translation("Component Display")
 * )
 */
class ComponentDisplay extends DisplayPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The component discovery service.
   */
  protected ComponentDiscovery $componentDiscovery;

  /**
   * The component views helper service.
   */
  protected ComponentViewsHelper $componentViewsHelper;

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ComponentDiscovery $component_discovery,
    ComponentViewsHelper $component_views_helper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->componentDiscovery = $component_discovery;
    $this->componentViewsHelper = $component_views_helper;
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
      $container->get('component_views.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['component_optimization'] = ['default' => TRUE];
    $options['component_preload'] = ['default' => TRUE];
    $options['component_lazy_load'] = ['default' => FALSE];
    $options['component_cache_strategy'] = ['default' => 'aggressive'];
    $options['component_batch_size'] = ['default' => 20];
    $options['component_grouping'] = ['default' => FALSE];
    $options['component_animation'] = ['default' => TRUE];
    $options['component_responsive'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $categories['component'] = [
      'title' => $this->t('Component Settings'),
      'column' => 'second',
      'build' => [
        '#weight' => -10,
      ],
    ];

    $optimization = $this->getOption('component_optimization') ? $this->t('Enabled') : $this->t('Disabled');
    $options['component_optimization'] = [
      'category' => 'component',
      'title' => $this->t('Component Optimization'),
      'value' => $optimization,
    ];

    $preload = $this->getOption('component_preload') ? $this->t('Enabled') : $this->t('Disabled');
    $options['component_preload'] = [
      'category' => 'component',
      'title' => $this->t('Component Preloading'),
      'value' => $preload,
    ];

    $lazy_load = $this->getOption('component_lazy_load') ? $this->t('Enabled') : $this->t('Disabled');
    $options['component_lazy_load'] = [
      'category' => 'component',
      'title' => $this->t('Lazy Loading'),
      'value' => $lazy_load,
    ];

    $cache_strategy = $this->getOption('component_cache_strategy');
    $options['component_cache_strategy'] = [
      'category' => 'component',
      'title' => $this->t('Cache Strategy'),
      'value' => ucfirst($cache_strategy),
    ];

    $batch_size = $this->getOption('component_batch_size');
    $options['component_batch_size'] = [
      'category' => 'component',
      'title' => $this->t('Batch Size'),
      'value' => $batch_size,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'component_optimization':
        $form['#title'] .= $this->t('Component Optimization');
        $form['component_optimization'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable component optimization'),
          '#default_value' => $this->getOption('component_optimization'),
          '#description' => $this->t('Enable performance optimizations for component rendering.'),
        ];
        break;

      case 'component_preload':
        $form['#title'] .= $this->t('Component Preloading');
        $form['component_preload'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Preload component definitions'),
          '#default_value' => $this->getOption('component_preload'),
          '#description' => $this->t('Load all component definitions before rendering to reduce individual discovery calls.'),
        ];
        break;

      case 'component_lazy_load':
        $form['#title'] .= $this->t('Lazy Loading');
        $form['component_lazy_load'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable lazy loading'),
          '#default_value' => $this->getOption('component_lazy_load'),
          '#description' => $this->t('Load components only when they become visible in the viewport.'),
        ];

        $form['component_batch_size'] = [
          '#type' => 'number',
          '#title' => $this->t('Initial batch size'),
          '#default_value' => $this->getOption('component_batch_size'),
          '#min' => 1,
          '#max' => 100,
          '#description' => $this->t('Number of components to load initially when lazy loading is enabled.'),
          '#states' => [
            'visible' => [
              ':input[name="component_lazy_load"]' => ['checked' => TRUE],
            ],
          ],
        ];
        break;

      case 'component_cache_strategy':
        $form['#title'] .= $this->t('Cache Strategy');
        $form['component_cache_strategy'] = [
          '#type' => 'select',
          '#title' => $this->t('Component cache strategy'),
          '#default_value' => $this->getOption('component_cache_strategy'),
          '#options' => [
            'none' => $this->t('No caching'),
            'basic' => $this->t('Basic caching'),
            'aggressive' => $this->t('Aggressive caching'),
            'intelligent' => $this->t('Intelligent caching'),
          ],
          '#description' => $this->t('Choose how aggressively to cache component data.'),
        ];

        $form['cache_description'] = [
          '#type' => 'details',
          '#title' => $this->t('Cache Strategy Details'),
          '#open' => FALSE,
          'content' => [
            '#markup' => '<ul>' .
              '<li><strong>None:</strong> No component-specific caching</li>' .
              '<li><strong>Basic:</strong> Cache component definitions only</li>' .
              '<li><strong>Aggressive:</strong> Cache definitions and rendered output</li>' .
              '<li><strong>Intelligent:</strong> Adaptive caching based on component complexity</li>' .
              '</ul>',
          ],
        ];
        break;

      case 'component_grouping':
        $form['#title'] .= $this->t('Component Grouping');
        $form['component_grouping'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Group similar components'),
          '#default_value' => $this->getOption('component_grouping'),
          '#description' => $this->t('Group components of the same type together for better performance.'),
        ];
        break;

      case 'component_animation':
        $form['#title'] .= $this->t('Component Animation');
        $form['component_animation'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable component animations'),
          '#default_value' => $this->getOption('component_animation'),
          '#description' => $this->t('Add entrance animations and hover effects to components.'),
        ];
        break;

      case 'component_responsive':
        $form['#title'] .= $this->t('Responsive Settings');
        $form['component_responsive'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable responsive optimizations'),
          '#default_value' => $this->getOption('component_responsive'),
          '#description' => $this->t('Optimize component rendering for different screen sizes.'),
        ];
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $section = $form_state->get('section');
    $options = [
      'component_optimization',
      'component_preload',
      'component_lazy_load',
      'component_cache_strategy',
      'component_batch_size',
      'component_grouping',
      'component_animation',
      'component_responsive',
    ];

    if (in_array($section, $options)) {
      $this->setOption($section, $form_state->getValue($section));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preExecute() {
    parent::preExecute();

    // Apply component optimizations
    if ($this->getOption('component_optimization')) {
      $this->applyComponentOptimizations();
    }

    // Preload component definitions if enabled
    if ($this->getOption('component_preload')) {
      $this->preloadComponentDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    // Apply component display enhancements
    $this->enhanceComponentDisplay($build);

    return $build;
  }

  /**
   * Apply component-specific optimizations.
   */
  protected function applyComponentOptimizations(): void {
    try {
      // Optimize query for component fields
      $this->optimizeComponentQuery();

      // Set up efficient caching
      $this->setupComponentCaching();

      // Configure batch processing if needed
      if ($this->getOption('component_lazy_load')) {
        $this->configureBatchProcessing();
      }

    } catch (\Exception $e) {
      \Drupal::logger('component_views')->warning(
        'Error applying component optimizations: @error',
        ['@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Preload component definitions to avoid multiple discovery calls.
   */
  protected function preloadComponentDefinitions(): void {
    try {
      $components = $this->componentDiscovery->discoverComponents();
      
      // Store in view for access by field plugins
      $this->view->component_definitions = $components;
      
      // Add to drupalSettings for JavaScript access
      if (!empty($components)) {
        $this->view->element['#attached']['drupalSettings']['componentViews']['components'] = $components;
      }

    } catch (\Exception $e) {
      \Drupal::logger('component_views')->warning(
        'Error preloading component definitions: @error',
        ['@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Optimize the query for component fields.
   */
  protected function optimizeComponentQuery(): void {
    $query = $this->view->build_info['query'] ?? null;
    
    if (!$query) {
      return;
    }

    try {
      // Add specific optimizations for component field queries
      $component_fields = $this->getComponentFields();
      
      foreach ($component_fields as $field_name) {
        // Add indexes or query hints if available
        $this->addComponentFieldOptimizations($query, $field_name);
      }

    } catch (\Exception $e) {
      \Drupal::logger('component_views')->debug(
        'Error optimizing component query: @error',
        ['@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Set up component-specific caching.
   */
  protected function setupComponentCaching(): void {
    $strategy = $this->getOption('component_cache_strategy');
    
    switch ($strategy) {
      case 'aggressive':
        $this->view->element['#cache']['max-age'] = 86400; // 24 hours
        $this->view->element['#cache']['contexts'][] = 'component_field.discovery';
        break;
        
      case 'intelligent':
        // Set cache based on component complexity
        $complexity = $this->calculateComponentComplexity();
        $max_age = max(3600, 86400 - ($complexity * 1000)); // Reduce cache time for complex components
        $this->view->element['#cache']['max-age'] = $max_age;
        break;
        
      case 'basic':
        $this->view->element['#cache']['contexts'][] = 'component_field.discovery';
        break;
        
      case 'none':
      default:
        // No additional caching
        break;
    }

    // Always add component cache tags
    $this->view->element['#cache']['tags'][] = 'component_field';
  }

  /**
   * Configure batch processing for lazy loading.
   */
  protected function configureBatchProcessing(): void {
    $batch_size = $this->getOption('component_batch_size');
    
    // Add JavaScript settings for lazy loading
    $this->view->element['#attached']['drupalSettings']['componentViews']['lazyLoad'] = [
      'enabled' => TRUE,
      'batchSize' => $batch_size,
      'threshold' => '100px',
    ];
    
    // Add lazy loading library
    $this->view->element['#attached']['library'][] = 'component_views/lazy-load';
  }

  /**
   * Enhance the component display output.
   */
  protected function enhanceComponentDisplay(array &$build): void {
    // Add component display classes
    if (!isset($build['#attributes']['class'])) {
      $build['#attributes']['class'] = [];
    }
    
    $build['#attributes']['class'][] = 'component-display';
    $build['#attributes']['class'][] = 'component-display-optimized';
    
    // Add animation classes if enabled
    if ($this->getOption('component_animation')) {
      $build['#attributes']['class'][] = 'component-display-animated';
      $build['#attached']['library'][] = 'component_views/animations';
    }
    
    // Add responsive classes if enabled
    if ($this->getOption('component_responsive')) {
      $build['#attributes']['class'][] = 'component-display-responsive';
    }
    
    // Add grouping if enabled
    if ($this->getOption('component_grouping')) {
      $this->applyComponentGrouping($build);
    }
    
    // Add component statistics for debugging
    if (\Drupal::currentUser()->hasPermission('administer views')) {
      $this->addComponentStatistics($build);
    }
  }

  /**
   * Get component field names from the view.
   */
  protected function getComponentFields(): array {
    $component_fields = [];
    
    try {
      $fields = $this->view->display_handler->getHandlers('field');
      
      foreach ($fields as $field_id => $field) {
        if (is_object($field) && method_exists($field, 'getPluginId')) {
          if ($field->getPluginId() === 'component_field') {
            $component_fields[] = $field->definition['field_name'] ?? $field_id;
          }
        }
      }
    } catch (\Exception $e) {
      // Return empty array if we can't determine fields
    }
    
    return $component_fields;
  }

  /**
   * Add component field optimizations to the query.
   */
  protected function addComponentFieldOptimizations($query, string $field_name): void {
    // This would add database-specific optimizations
    // For example, ensuring proper indexes are used
    try {
      if (method_exists($query, 'addMetaData')) {
        $query->addMetaData('component_field_optimization', TRUE);
        $query->addMetaData('component_field_name', $field_name);
      }
    } catch (\Exception $e) {
      // Silently continue if optimization can't be applied
    }
  }

  /**
   * Calculate component complexity for intelligent caching.
   */
  protected function calculateComponentComplexity(): int {
    $complexity = 0;
    
    try {
      $component_fields = $this->getComponentFields();
      $complexity += count($component_fields) * 10;
      
      // Add complexity based on component types used
      if (isset($this->view->component_definitions)) {
        foreach ($this->view->component_definitions as $component) {
          $props_count = isset($component['props']) ? count($component['props']) : 0;
          $complexity += $props_count * 2;
        }
      }
      
      // Add complexity based on number of rows
      $row_count = $this->view->total_rows ?? count($this->view->result ?? []);
      $complexity += $row_count;
      
    } catch (\Exception $e) {
      $complexity = 50; // Default moderate complexity
    }
    
    return min($complexity, 100); // Cap at 100
  }

  /**
   * Apply component grouping to the display.
   */
  protected function applyComponentGrouping(array &$build): void {
    try {
      if (!isset($build['#rows'])) {
        return;
      }
      
      // Group rows by component types used
      $grouped_rows = [];
      $ungrouped_rows = [];
      
      foreach ($build['#rows'] as $index => $row) {
        $component_types = $this->extractComponentTypesFromRow($row);
        
        if (!empty($component_types)) {
          $group_key = implode('_', array_unique($component_types));
          $grouped_rows[$group_key][] = $row;
        } else {
          $ungrouped_rows[] = $row;
        }
      }
      
      // Rebuild rows with grouping
      $new_rows = [];
      foreach ($grouped_rows as $group_key => $group_rows) {
        $new_rows = array_merge($new_rows, $group_rows);
      }
      $new_rows = array_merge($new_rows, $ungrouped_rows);
      
      $build['#rows'] = $new_rows;
      $build['#attributes']['class'][] = 'component-display-grouped';
      
    } catch (\Exception $e) {
      \Drupal::logger('component_views')->debug(
        'Error applying component grouping: @error',
        ['@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Extract component types from a row.
   */
  protected function extractComponentTypesFromRow($row): array {
    $component_types = [];
    
    try {
      // This would need to be implemented based on the row structure
      // For now, return empty array
    } catch (\Exception $e) {
      // Return empty array on error
    }
    
    return $component_types;
  }

  /**
   * Add component statistics to the build for debugging.
   */
  protected function addComponentStatistics(array &$build): void {
    try {
      $stats = [
        'component_fields' => count($this->getComponentFields()),
        'total_rows' => $this->view->total_rows ?? 0,
        'cache_strategy' => $this->getOption('component_cache_strategy'),
        'optimizations_enabled' => $this->getOption('component_optimization'),
      ];
      
      $build['#attached']['drupalSettings']['componentViews']['displayStats'] = $stats;
      
    } catch (\Exception $e) {
      // Silently continue if stats can't be added
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $max_age = parent::getCacheMaxAge();
    
    // Apply component-specific cache settings
    $strategy = $this->getOption('component_cache_strategy');
    
    switch ($strategy) {
      case 'aggressive':
        return min($max_age, 86400); // Max 24 hours
        
      case 'intelligent':
        $complexity = $this->calculateComponentComplexity();
        return min($max_age, max(3600, 86400 - ($complexity * 600)));
        
      case 'none':
        return 0; // No caching
        
      default:
        return $max_age;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    
    // Add component-specific cache contexts
    $contexts[] = 'component_field.discovery';
    
    if ($this->getOption('component_responsive')) {
      $contexts[] = 'headers:user-agent';
    }
    
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    
    // Add component-specific cache tags
    $tags[] = 'component_field';
    
    // Add tags for specific components if known
    if (isset($this->view->component_definitions)) {
      foreach (array_keys($this->view->component_definitions) as $component_type) {
        $tags[] = 'component_field:' . $component_type;
      }
    }
    
    return $tags;
  }
}