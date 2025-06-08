<?php

namespace Drupal\component_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\component_field\Service\ComponentDiscovery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enhanced Views filter for Component Field component types.
 *
 * @ViewsFilter("component_type")
 */
class ComponentType extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * The component discovery service.
   */
  protected ComponentDiscovery $componentDiscovery;

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ComponentDiscovery $component_discovery
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->componentDiscovery = $component_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('component_field.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['expose']['contains']['multiple']['default'] = TRUE;
    $options['expose']['contains']['remember']['default'] = TRUE;
    $options['expose']['contains']['placeholder']['default'] = $this->t('Any component type');
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $this->valueOptions = [];
    
    try {
      $components = $this->componentDiscovery->discoverComponents();
      
      // Group components by type/category if metadata is available
      $grouped_options = [];
      $ungrouped_options = [];
      
      foreach ($components as $name => $component) {
        $label = $component['label'] ?? $name;
        $category = $component['metadata']['category'] ?? null;
        
        // Add component count in label
        $props_count = isset($component['props']) ? count($component['props']) : 0;
        $display_label = $label . ' (' . $props_count . ' props)';
        
        if ($category && is_string($category)) {
          $grouped_options[$category][$name] = $display_label;
        } else {
          $ungrouped_options[$name] = $display_label;
        }
      }
      
      // Build final options with groups
      if (!empty($grouped_options)) {
        // Sort groups alphabetically
        ksort($grouped_options);
        
        foreach ($grouped_options as $group_name => $group_options) {
          // Sort options within group
          asort($group_options);
          $this->valueOptions[$group_name] = $group_options;
        }
      }
      
      // Add ungrouped options
      if (!empty($ungrouped_options)) {
        asort($ungrouped_options);
        if (!empty($grouped_options)) {
          $this->valueOptions['Other Components'] = $ungrouped_options;
        } else {
          $this->valueOptions = $ungrouped_options;
        }
      }
      
      // If no components found, provide informative message
      if (empty($this->valueOptions)) {
        $this->valueOptions = [
          '' => $this->t('No components available'),
        ];
      }
      
    } catch (\Exception $e) {
      \Drupal::logger('component_views')->error(
        'Error loading component options for filter: @error',
        ['@error' => $e->getMessage()]
      );
      
      $this->valueOptions = [
        '' => $this->t('Error loading components'),
      ];
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    
    if (isset($form['value'])) {
      $form['value']['#description'] = $this->t('Select one or more component types to filter by. Numbers in parentheses indicate the number of configurable properties for each component.');
      
      // Add component statistics
      try {
        $stats = $this->componentDiscovery->getComponentStats();
        $form['component_stats'] = [
          '#type' => 'details',
          '#title' => $this->t('Component Statistics'),
          '#open' => FALSE,
          '#weight' => 10,
          'stats' => [
            '#markup' => '<div class="component-views-filter-stats">' .
              '<p><strong>' . $this->t('Total Components:') . '</strong> ' . $stats['total'] . '</p>' .
              '<p><strong>' . $this->t('Components with Properties:') . '</strong> ' . $stats['with_props'] . '</p>' .
              '<p><strong>' . $this->t('Total Properties:') . '</strong> ' . $stats['total_props'] . '</p>' .
              '</div>',
          ],
        ];
      } catch (\Exception $e) {
        // Silently fail for stats - not critical
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    $info = $this->operators();
    $this->getValueOptions();

    if (!is_array($this->value)) {
      return;
    }

    $operator = $info[$this->operator]['short'];
    $values = '';
    
    if (in_array($this->operator, $this->operatorValues(1))) {
      // Single value operators
      $values = array_pop($this->value);
    } elseif (in_array($this->operator, $this->operatorValues())) {
      // Multiple value operators
      $value_labels = [];
      foreach ($this->value as $value) {
        $value_labels[] = $this->getValueLabel($value);
      }
      $values = implode(', ', $value_labels);
    }

    return $operator . (($values !== '') ? ' ' . $values : '');
  }

  /**
   * Get display label for a component value.
   */
  protected function getValueLabel($value) {
    $options = $this->getValueOptions();
    
    // Handle grouped options
    foreach ($options as $key => $option) {
      if (is_array($option) && isset($option[$value])) {
        return $option[$value];
      } elseif ($key === $value) {
        return $option;
      }
    }
    
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!is_array($this->value) || empty($this->value)) {
      return;
    }

    $this->ensureMyTable();
    
    // Clean up values - remove empty ones
    $values = array_filter($this->value, function($val) {
      return !empty($val);
    });
    
    if (empty($values)) {
      return;
    }

    $field = $this->tableAlias . '.' . $this->realField;
    $operator = $this->operator;

    // Handle different operators
    switch ($operator) {
      case 'in':
        $this->query->addWhere($this->options['group'], $field, $values, 'IN');
        break;
        
      case 'not in':
        $this->query->addWhere($this->options['group'], $field, $values, 'NOT IN');
        break;
        
      case '=':
        if (count($values) === 1) {
          $this->query->addWhere($this->options['group'], $field, reset($values), '=');
        } else {
          $this->query->addWhere($this->options['group'], $field, $values, 'IN');
        }
        break;
        
      case '!=':
      case '<>':
        if (count($values) === 1) {
          $this->query->addWhere($this->options['group'], $field, reset($values), '!=');
        } else {
          $this->query->addWhere($this->options['group'], $field, $values, 'NOT IN');
        }
        break;
        
      default:
        // Fallback to parent implementation
        parent::query();
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opHelper() {
    // Override to provide better error handling
    try {
      return parent::opHelper();
    } catch (\Exception $e) {
      \Drupal::logger('component_views')->warning(
        'Error in component type filter operation: @error',
        ['@error' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);
    
    // Add some enhancements to the exposed form
    if (isset($form[$this->options['expose']['identifier']])) {
      $element = &$form[$this->options['expose']['identifier']];
      
      // Add CSS class for styling
      if (!isset($element['#attributes']['class'])) {
        $element['#attributes']['class'] = [];
      }
      $element['#attributes']['class'][] = 'component-views-filter-exposed';
      
      // Add component count to the form for UX
      try {
        $total_components = $this->componentDiscovery->getComponentCount();
        if ($total_components > 0) {
          $element['#description'] = $this->t('Filter by component type (@count total)', [
            '@count' => $total_components,
          ]);
        }
      } catch (\Exception $e) {
        // Silently continue if we can't get the count
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    // Add cache context for component discovery
    $contexts[] = 'component_field.discovery';
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    // Add cache tag for component discovery
    $tags[] = 'component_field';
    return $tags;
  }
}