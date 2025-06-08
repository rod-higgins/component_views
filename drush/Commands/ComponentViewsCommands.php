<?php

namespace Drush\Commands;

use Drupal\component_field\Service\ComponentDiscovery;
use Drupal\component_views\Service\ComponentViewsHelper;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for Component Views.
 */
class ComponentViewsCommands extends DrushCommands {

  /**
   * The component discovery service.
   */
  protected ComponentDiscovery $componentDiscovery;

  /**
   * The component views helper.
   */
  protected ComponentViewsHelper $componentViewsHelper;

  /**
   * Constructor.
   */
  public function __construct(
    ComponentDiscovery $component_discovery,
    ComponentViewsHelper $component_views_helper
  ) {
    $this->componentDiscovery = $component_discovery;
    $this->componentViewsHelper = $component_views_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('component_field.discovery'),
      $container->get('component_views.helper')
    );
  }

  /**
   * List component usage statistics.
   *
   * @command component-views:stats
   * @aliases cv:stats
   * @usage component-views:stats
   *   Show component usage statistics
   */
  public function componentStats() {
    $stats = $this->componentViewsHelper->getComponentUsageStats();
    
    if (empty($stats)) {
      $this->output()->writeln('No component usage found.');
      return;
    }

    $this->output()->writeln('Component Usage Statistics:');
    $this->output()->writeln('');
    
    foreach ($stats as $component_type => $count) {
      $this->output()->writeln("  $component_type: $count uses");
    }
    
    $total = array_sum($stats);
    $this->output()->writeln('');
    $this->output()->writeln("Total: $total component instances");
  }

  /**
   * List unused components.
   *
   * @command component-views:unused
   * @aliases cv:unused
   * @usage component-views:unused
   *   Show components that are not being used
   */
  public function unusedComponents() {
    $unused = $this->componentViewsHelper->getUnusedComponents();
    
    if (empty($unused)) {
      $this->output()->writeln('All components are being used.');
      return;
    }

    $this->output()->writeln('Unused Components:');
    $this->output()->writeln('');
    
    foreach ($unused as $component_type) {
      $this->output()->writeln("  - $component_type");
    }
  }

  /**
   * Analyze component complexity.
   *
   * @command component-views:analyze
   * @aliases cv:analyze
   * @usage component-views:analyze
   *   Analyze component complexity and performance
   */
  public function analyzeComponents() {
    $components = $this->componentDiscovery->discoverComponents();
    
    $this->output()->writeln('Component Analysis:');
    $this->output()->writeln('');
    
    foreach ($components as $name => $component) {
      $props_count = isset($component['props']) ? count($component['props']) : 0;
      $complexity = $this->calculateComplexity($component);
      
      $this->output()->writeln("$name:");
      $this->output()->writeln("  Properties: $props_count");
      $this->output()->writeln("  Complexity: $complexity");
      $this->output()->writeln('');
    }
  }

  /**
   * Calculate component complexity score.
   */
  protected function calculateComplexity(array $component): string {
    $score = 0;
    
    // Base complexity from props
    $props_count = isset($component['props']) ? count($component['props']) : 0;
    $score += $props_count * 2;
    
    // Additional complexity factors
    if (!empty($component['template_file'])) {
      $score += 5;
    }
    if (!empty($component['css_file'])) {
      $score += 3;
    }
    if (!empty($component['js_file'])) {
      $score += 10;
    }
    
    if ($score < 10) return 'Low';
    if ($score < 25) return 'Medium';
    if ($score < 50) return 'High';
    return 'Very High';
  }
}