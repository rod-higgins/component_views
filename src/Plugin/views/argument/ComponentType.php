<?php

namespace Drupal\component_views\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\StringArgument;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\component_field\Service\ComponentDiscovery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enhanced Views argument for Component Field component types.
 *
 * @ViewsArgument("component_type")
 */
class ComponentType extends StringArgument implements ContainerFactoryPluginInterface {

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
    
    $options['validate_component'] = ['default' => TRUE];
    $options['transform_dashes'] = ['default' => TRUE];
    $options['case_sensitive'] = ['default' => FALSE];
    $options['component_redirect'] = ['default' => FALSE];
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['validate_component'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate component exists'),
      '#default_value' => $this->options['validate_component'],
      '#description' => $this->t('Check if the component type exists before applying the filter.'),
    ];

    $form['transform_dashes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Transform dashes to underscores'),
      '#default_value' => $this->options['transform_dashes'],
      '#description' => $this->t('Convert dashes in URL arguments to underscores for component names (e.g., "my-component" becomes "my_component").'),
    ];

    $form['case_sensitive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Case sensitive matching'),
      '#default_value' => $this->options['case_sensitive'],
      '#description' => $this->t('Whether component type matching should be case sensitive.'),
    ];

    $form['component_redirect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect invalid components'),
      '#default_value' => $this->options['component_redirect'],
      '#description' => $this->t('Redirect to the first available component if the requested component is not found.'),
    ];

    // Show available components
    try {
      $components = $this->componentDiscovery->discoverComponents();
      if (!empty($components)) {
        $component_list = array_keys($components);
        sort($component_list);
        
        $form['available_components'] = [
          '#type' => 'details',
          '#title' => $this->t('Available Components (@count)', ['@count' => count($components)]),
          '#open' => FALSE,
          'list' => [
            '#markup' => '<div class="component-views-available-list">' .
              '<p>' . implode(', ', array_map(function($name) {
                return '<code>' . htmlspecialchars($name) . '</code>';
              }, $component_list)) . '</p>' .
              '</div>',
          ],
        ];
      }
    } catch (\Exception $e) {
      $form['component_error'] = [
        '#markup' => '<div class="messages messages--warning">' . 
          $this->t('Unable to load component list: @error', ['@error' => $e->getMessage()]) . 
          '</div>',
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($argument) {
    // Transform the argument if needed
    $component_type = $this->transformArgument($argument);
    
    if ($this->options['validate_component']) {
      try {
        $components = $this->componentDiscovery->discoverComponents();
        
        // Check if component exists (case sensitive or insensitive)
        if ($this->options['case_sensitive']) {
          $exists = isset($components[$component_type]);
        } else {
          $component_names = array_map('strtolower', array_keys($components));
          $exists = in_array(strtolower($component_type), $component_names);
          
          // If found with different case, update the argument
          if ($exists && !isset($components[$component_type])) {
            foreach ($components as $name => $component) {
              if (strtolower($name) === strtolower($component_type)) {
                $this->argument = $name;
                $component_type = $name;
                break;
              }
            }
          }
        }
        
        if (!$exists) {
          if ($this->options['component_redirect'] && !empty($components)) {
            // Redirect to first available component
            $first_component = array_keys($components)[0];
            $this->argument = $first_component;
            return TRUE;
          }
          return FALSE;
        }
      } catch (\Exception $e) {
        \Drupal::logger('component_views')->error(
          'Error validating component argument: @error',
          ['@error' => $e->getMessage()]
        );
        return FALSE;
      }
    }
    
    // Update the argument with the transformed value
    $this->argument = $component_type;
    return TRUE;
  }

  /**
   * Transform argument based on options.
   */
  protected function transformArgument($argument) {
    $component_type = $argument;
    
    // Transform dashes to underscores if enabled
    if ($this->options['transform_dashes']) {
      $component_type = str_replace('-', '_', $component_type);
    }
    
    // Handle case sensitivity
    if (!$this->options['case_sensitive']) {
      // We'll handle case insensitive matching in validateArgument
      // but keep original case for now
    }
    
    return $component_type;
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    
    $field = $this->tableAlias . '.' . $this->realField;
    $argument = $this->argument;
    
    // Apply case sensitivity
    if (!$this->options['case_sensitive']) {
      // Use case-insensitive comparison
      $this->query->addWhereExpression($this->options['group'], 
        "LOWER($field) = LOWER(:component_type)", 
        [':component_type' => strtolower($argument)]
      );
    } else {
      $this->query->addWhere($this->options['group'], $field, $argument, '=');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function summaryName($data) {
    $component_type = $data->{$this->name_alias};
    
    try {
      $components = $this->componentDiscovery->discoverComponents();
      if (isset($components[$component_type])) {
        $label = $components[$component_type]['label'] ?? $component_type;
        return $this->caseTransform($label, $this->options['case']);
      }
    } catch (\Exception $e) {
      // Fall back to component type name
    }
    
    return $this->caseTransform($component_type, $this->options['case']);
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (empty($this->argument)) {
      return $this->t('All Components');
    }
    
    try {
      $components = $this->componentDiscovery->discoverComponents();
      $component_type = $this->argument;
      
      if (isset($components[$component_type])) {
        $component_info = $components[$component_type];
        $label = $component_info['label'] ?? $component_type;
        
        // Add additional context if available
        $props_count = isset($component_info['props']) ? count($component_info['props']) : 0;
        
        if ($props_count > 0) {
          return $this->t('@label (@count properties)', [
            '@label' => $label,
            '@count' => $props_count,
          ]);
        }
        
        return $label;
      }
    } catch (\Exception $e) {
      \Drupal::logger('component_views')->warning(
        'Error getting component title for argument: @error',
        ['@error' => $e->getMessage()]
      );
    }
    
    return $this->caseTransform($this->argument, $this->options['case']);
  }

  /**
   * {@inheritdoc}
   */
  public function summaryArgument($data) {
    $component_type = $data->{$this->name_alias};
    
    // Transform for URL if needed (reverse of transformArgument)
    if ($this->options['transform_dashes']) {
      $component_type = str_replace('_', '-', $component_type);
    }
    
    return $component_type;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultActions($which = NULL) {
    $actions = parent::defaultActions($which);
    
    // Add component-specific actions
    if ($which) {
      if ($which === 'not found') {
        $actions['component_list'] = [
          'title' => $this->t('Show available components'),
          'method' => 'actionComponentList',
          'help' => $this->t('Display a list of all available components instead of a 404.'),
        ];
      }
    }
    
    return $actions;
  }

  /**
   * Action to show available components when component not found.
   */
  public function actionComponentList() {
    try {
      $components = $this->componentDiscovery->discoverComponents();
      
      $build = [
        '#theme' => 'component_views_component_list',
        '#components' => $components,
        '#title' => $this->t('Available Components'),
        '#description' => $this->t('The requested component "@component" was not found. Here are the available components:', [
          '@component' => $this->argument,
        ]),
      ];
      
      return $build;
    } catch (\Exception $e) {
      return [
        '#markup' => $this->t('Unable to load component list.'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.path';
    $contexts[] = 'component_field.discovery';
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    $tags[] = 'component_field';
    return $tags;
  }
}