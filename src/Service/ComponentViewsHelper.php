<?php

namespace Drupal\component_views\Service;

use Drupal\component_field\Service\ComponentDiscovery;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Helper service for Component Views operations.
 */
class ComponentViewsHelper {

  use DependencySerializationTrait;

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
    ComponentDiscovery $component_discovery,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->componentDiscovery = $component_discovery;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get component options for form elements.
   */
  public function getComponentOptions(bool $include_empty = TRUE, bool $group_by_category = FALSE): array {
    $options = [];
    
    try {
      $components = $this->componentDiscovery->discoverComponents();
      
      if ($include_empty) {
        $options[''] = t('- Any Component -');
      }
      
      if ($group_by_category) {
        $grouped = [];
        $ungrouped = [];
        
        foreach ($components as $name => $component) {
          $label = $this->getComponentDisplayLabel($name, $component);
          $category = $component['metadata']['category'] ?? null;
          
          if ($category && is_string($category)) {
            $grouped[$category][$name] = $label;
          } else {
            $ungrouped[$name] = $label;
          }
        }
        
        // Sort groups and add to options
        ksort($grouped);
        foreach ($grouped as $group_name => $group_options) {
          asort($group_options);
          $options[$group_name] = $group_options;
        }
        
        // Add ungrouped options
        if (!empty($ungrouped)) {
          asort($ungrouped);
          if (!empty($grouped)) {
            $options[t('Other')] = $ungrouped;
          } else {
            $options = array_merge($options, $ungrouped);
          }
        }
      } else {
        foreach ($components as $name => $component) {
          $options[$name] = $this->getComponentDisplayLabel($name, $component);
        }
        asort($options);
      }
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->error(
        'Error loading component options: @error',
        ['@error' => $e->getMessage()]
      );
      
      if ($include_empty) {
        $options = ['' => t('Error loading components')];
      }
    }
    
    return $options;
  }

  /**
   * Get display label for a component.
   */
  public function getComponentDisplayLabel(string $name, array $component, bool $include_props_count = TRUE): string {
    $label = $component['label'] ?? $name;
    
    if ($include_props_count) {
      $props_count = isset($component['props']) && is_array($component['props']) ? count($component['props']) : 0;
      $label .= ' (' . $props_count . ' ' . ($props_count === 1 ? 'prop' : 'props') . ')';
    }
    
    return $label;
  }

  /**
   * Extract key information from component configuration for display.
   */
  public function extractComponentSummary(string $component_type, array $configuration, int $max_length = 50): array {
    $summary = [];
    
    switch ($component_type) {
      case 'button':
        $summary['primary'] = $configuration['label'] ?? 'Button';
        $summary['secondary'] = ($configuration['variant'] ?? 'contained') . ' • ' . ($configuration['color'] ?? 'primary');
        if (!empty($configuration['disabled'])) {
          $summary['status'] = 'disabled';
        }
        break;
        
      case 'card':
        $summary['primary'] = $configuration['title'] ?? 'Card';
        $summary['secondary'] = 'Elevation ' . ($configuration['elevation'] ?? 1);
        if (!empty($configuration['content'])) {
          $content = is_array($configuration['content']) ? 
            ($configuration['content']['value'] ?? '') : 
            $configuration['content'];
          $summary['tertiary'] = $this->truncateText(strip_tags($content), $max_length);
        }
        break;
        
      case 'typography':
        $content = $configuration['content'] ?? $configuration['text'] ?? '';
        if (is_array($content)) {
          $content = $content['value'] ?? '';
        }
        $summary['primary'] = $this->truncateText(strip_tags($content), $max_length) ?: 'Typography';
        $summary['secondary'] = $configuration['variant'] ?? 'body1';
        break;
        
      case 'test':
        $summary['primary'] = $configuration['title'] ?? 'Test Component';
        $summary['secondary'] = ($configuration['variant'] ?? 'primary') . ' • ' . ($configuration['size'] ?? 'medium');
        if (isset($configuration['counter'])) {
          $summary['tertiary'] = 'Counter: ' . $configuration['counter'];
        }
        break;
        
      default:
        // Generic handling
        $key_fields = ['title', 'label', 'name', 'content', 'text'];
        $found = false;
        
        foreach ($key_fields as $field) {
          if (isset($configuration[$field]) && !empty($configuration[$field])) {
            $value = $configuration[$field];
            if (is_array($value)) {
              $value = $value['value'] ?? json_encode($value);
            }
            $summary['primary'] = $this->truncateText(strip_tags($value), $max_length);
            $found = true;
            break;
          }
        }
        
        if (!$found) {
          $summary['primary'] = ucfirst(str_replace('_', ' ', $component_type));
        }
        
        // Show configuration count as secondary info
        $config_count = count($configuration);
        if ($config_count > 0) {
          $summary['secondary'] = $config_count . ' ' . ($config_count === 1 ? 'property' : 'properties');
        }
        break;
    }
    
    return $summary;
  }

  /**
   * Get component usage statistics across all content.
   */
  public function getComponentUsageStats(): array {
    $stats = [];
    
    try {
      // Find all component field instances
      $field_storage_configs = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->loadByProperties(['type' => 'component_field']);
      
      foreach ($field_storage_configs as $field_storage) {
        $entity_type = $field_storage->getTargetEntityTypeId();
        $field_name = $field_storage->getName();
        
        // Query the field table directly for better performance
        $table_name = $entity_type . '__' . $field_name;
        $column_name = $field_name . '_component_type';
        
        $database = \Drupal::database();
        if ($database->schema()->tableExists($table_name)) {
          $query = $database->select($table_name, 't')
            ->fields('t', [$column_name])
            ->condition($column_name, '', '!=')
            ->isNotNull($column_name);
          
          $query->addExpression('COUNT(*)', 'usage_count');
          $query->groupBy($column_name);
          
          $results = $query->execute();
          
          foreach ($results as $row) {
            $component_type = $row->{$column_name};
            if (!isset($stats[$component_type])) {
              $stats[$component_type] = 0;
            }
            $stats[$component_type] += $row->usage_count;
          }
        }
      }
      
      // Sort by usage count descending
      arsort($stats);
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->error(
        'Error calculating component usage stats: @error',
        ['@error' => $e->getMessage()]
      );
    }
    
    return $stats;
  }

  /**
   * Get entities that use a specific component type.
   */
  public function getEntitiesUsingComponent(string $component_type, string $entity_type = 'node', int $limit = 50): array {
    $entities = [];
    
    try {
      // Find component field instances for this entity type
      $field_storage_configs = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->loadByProperties([
          'type' => 'component_field',
          'entity_type' => $entity_type,
        ]);
      
      $entity_storage = $this->entityTypeManager->getStorage($entity_type);
      
      foreach ($field_storage_configs as $field_storage) {
        $field_name = $field_storage->getName();
        
        // Query entities using this component
        $query = $entity_storage->getQuery()
          ->condition($field_name . '.component_type', $component_type)
          ->accessCheck(TRUE)
          ->range(0, $limit);
        
        $entity_ids = $query->execute();
        
        if (!empty($entity_ids)) {
          $loaded_entities = $entity_storage->loadMultiple($entity_ids);
          foreach ($loaded_entities as $entity) {
            $entities[] = [
              'entity' => $entity,
              'field_name' => $field_name,
            ];
          }
        }
      }
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->error(
        'Error finding entities using component @type: @error',
        ['@type' => $component_type, '@error' => $e->getMessage()]
      );
    }
    
    return $entities;
  }

  /**
   * Validate that a component type exists.
   */
  public function componentExists(string $component_type): bool {
    try {
      return $this->componentDiscovery->componentExists($component_type);
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->warning(
        'Error validating component existence: @error',
        ['@error' => $e->getMessage()]
      );
      return false;
    }
  }

  /**
   * Get component information.
   */
  public function getComponentInfo(string $component_type): ?array {
    try {
      return $this->componentDiscovery->getComponent($component_type);
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->warning(
        'Error getting component info for @type: @error',
        ['@type' => $component_type, '@error' => $e->getMessage()]
      );
      return null;
    }
  }

  /**
   * Get popular components based on usage.
   */
  public function getPopularComponents(int $limit = 10): array {
    $usage_stats = $this->getComponentUsageStats();
    return array_slice($usage_stats, 0, $limit, true);
  }

  /**
   * Get unused components.
   */
  public function getUnusedComponents(): array {
    try {
      $all_components = array_keys($this->componentDiscovery->discoverComponents());
      $used_components = array_keys($this->getComponentUsageStats());
      return array_diff($all_components, $used_components);
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->error(
        'Error finding unused components: @error',
        ['@error' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Truncate text to specified length.
   */
  protected function truncateText(string $text, int $max_length, string $suffix = '...'): string {
    if (mb_strlen($text) <= $max_length) {
      return $text;
    }
    
    return mb_substr($text, 0, $max_length - mb_strlen($suffix)) . $suffix;
  }

  /**
   * Clean up component configuration for display.
   */
  public function cleanConfigurationForDisplay(array $configuration): array {
    $cleaned = [];
    
    foreach ($configuration as $key => $value) {
      // Skip empty values
      if ($value === null || $value === '') {
        continue;
      }
      
      // Handle text format fields
      if (is_array($value) && isset($value['value'], $value['format'])) {
        $cleaned[$key] = $value['processed'] ?? strip_tags($value['value']);
        continue;
      }
      
      // Handle entity references
      if (is_array($value) && isset($value['target_id'])) {
        $cleaned[$key] = 'Entity #' . $value['target_id'];
        continue;
      }
      
      // Handle arrays
      if (is_array($value)) {
        if (count($value) <= 3) {
          $cleaned[$key] = implode(', ', array_filter($value));
        } else {
          $cleaned[$key] = count($value) . ' items';
        }
        continue;
      }
      
      // Handle long strings
      if (is_string($value) && strlen($value) > 50) {
        $cleaned[$key] = $this->truncateText($value, 50);
      } else {
        $cleaned[$key] = $value;
      }
    }
    
    return $cleaned;
  }

  /**
   * Get field names that are component fields for an entity type.
   */
  public function getComponentFieldNames(string $entity_type): array {
    $field_names = [];
    
    try {
      $field_storage_configs = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->loadByProperties([
          'type' => 'component_field',
          'entity_type' => $entity_type,
        ]);
      
      foreach ($field_storage_configs as $field_storage) {
        $field_names[] = $field_storage->getName();
      }
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_views')->error(
        'Error getting component field names for @entity_type: @error',
        ['@entity_type' => $entity_type, '@error' => $e->getMessage()]
      );
    }
    
    return $field_names;
  }
}