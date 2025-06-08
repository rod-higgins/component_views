# Component Views Module

## Overview

The Component Views module provides comprehensive Views integration for the Component Field module, enabling powerful filtering, display, and management of Single Directory Components (SDC) through Drupal's Views system.

## Features

### 🎯 **Advanced Views Integration**
- Dedicated field plugin for component rendering with multiple display modes
- Component type filter with search and statistics
- Component type argument for URL-based filtering
- Optimized display plugin for component-heavy views

### 📊 **Multiple Display Modes**
- **Full**: Complete component rendering with all properties
- **Summary**: Key information with metadata display  
- **List**: Component name and property count
- **Compact**: Minimal inline display

### 🔍 **Powerful Filtering**
- Filter by component type with multi-select support
- Search within component filter options
- Component usage statistics in filter interface
- Case-sensitive and case-insensitive matching

### ⚡ **Performance Optimizations**
- Component definition preloading
- Intelligent caching strategies
- Lazy loading for large component lists
- Batch processing for better performance

### 🎨 **Enhanced UX**
- Live preview while configuring
- Animation support
- Responsive design
- Component usage analytics

## Installation

1. **Prerequisites**: Ensure the Component Field module is installed and enabled
   ```bash
   drush en component_field
   ```

2. **Install Component Views**:
   ```bash
   drush en component_views
   ```

3. **Clear caches**:
   ```bash
   drush cr
   ```

## Quick Start

### Creating a Component Gallery View

1. **Create a new View**:
   - Go to `/admin/structure/views/add`
   - Choose "Content" as the base table
   - Name it "Component Gallery"

2. **Add Component Field**:
   - Add your component field to the view
   - Configure display mode (Full, Summary, List, or Compact)
   - Enable animations and responsive features

3. **Add Component Filter**:
   - Add a filter for "Component Type"
   - Expose it to visitors
   - Enable multiple selection

4. **Configure Display**:
   - Use "Component Display" for optimal performance
   - Enable component grouping and lazy loading
   - Set appropriate cache strategy

## Field Plugin Configuration

The Component Field plugin offers extensive configuration options:

### Display Modes

```yaml
Full Mode:
  - Complete component rendering
  - All properties and styling
  - Best for detailed display

Summary Mode:
  - Key information extraction
  - Metadata display
  - Compact but informative

List Mode:
  - Component name and type
  - Property count
  - Minimal overhead

Compact Mode:
  - Inline display
  - Essential info only
  - Space-efficient
```

### Field Settings

```php
// Example field configuration
$field_config = [
  'display_mode' => 'summary',        // full|summary|list|compact
  'component_filter' => '',           // Filter specific components
  'limit_components' => 10,           // Limit number shown
  'show_wrapper' => TRUE,             // Include wrapper elements
  'enable_animation' => TRUE,         // Add animations
  'component_classes' => 'my-class',  // Custom CSS classes
];
```

## Filter Plugin Usage

### Basic Component Type Filter

```php
// In Views UI, add filter:
// Component Type (component_type)
// - Expose filter: Yes
// - Allow multiple selections: Yes
// - Remember last selection: Yes
```

### Advanced Filter Configuration

```yaml
Filter Options:
  - Grouped by component category
  - Usage statistics display
  - Search within options
  - Validation and error handling
```

### Programmatic Filter Usage

```php
// Get components by type
$view = Views::getView('my_component_view');
$view->setArguments(['button']); // Filter to button components
$view->execute();
```

## Argument Plugin for URLs

Create URLs like `/components/button` or `/gallery/card`:

```php
// Configure argument in Views:
// - Add Component Type argument
// - Enable case transformation
// - Set validation options
// - Configure fallback behavior
```

### URL Examples

```
/components/button      → Show only button components
/gallery/card           → Show only card components  
/showcase/test          → Show only test components
/components/non-exist   → Show fallback or 404
```

## Display Plugin Features

The Component Display plugin provides optimized rendering:

### Performance Settings

```yaml
Optimization Options:
  component_optimization: true     # Enable optimizations
  component_preload: true         # Preload definitions
  component_lazy_load: false      # Lazy loading
  component_cache_strategy: 'aggressive'
  component_batch_size: 20        # Items per batch
  component_grouping: false       # Group similar components
  component_animation: true       # Enable animations
  component_responsive: true      # Responsive features
```

### Cache Strategies

```php
// Cache strategies available:
'none'        => No component caching
'basic'       => Cache definitions only  
'aggressive'  => Cache definitions and output
'intelligent' => Adaptive based on complexity
```

## Templates and Theming

### Available Templates

```twig
{# Summary display #}
templates/component-views-summary.html.twig

{# List item display #}
templates/component-views-list-item.html.twig

{# Compact display #}
templates/component-views-compact.html.twig

{# Component listing page #}
templates/component-views-component-list.html.twig

{# Usage statistics #}
templates/component-views-usage-stats.html.twig
```

### Template Variables

```twig
{# Summary template variables #}
- component_type: Machine name
- component_label: Human label  
- summary_data: Key information array
- delta: Item position
- attributes: HTML attributes

{# List template variables #}
- component_type: Machine name
- component_label: Human label
- configuration_count: Number of props
- delta: Item position  
- attributes: HTML attributes
```

### CSS Classes

```css
/* Field wrapper classes */
.component-views-field
.component-views-mode-{mode}
.component-views-count-{number}

/* Item classes */
.component-views-item
.component-views-{component-type}
.component-views-delta-{number}
.component-views-animated

/* Display mode classes */
.component-views-summary
.component-views-list-item
.component-views-compact
```

## JavaScript Enhancement

### Available Behaviors

```javascript
// Field enhancements
Drupal.behaviors.componentViewsField

// View-level enhancements  
Drupal.behaviors.componentViewsView

// Filter enhancements
Drupal.behaviors.componentViewsFilter

// Admin interface enhancements
Drupal.behaviors.componentViewsAdmin
```

### Custom JavaScript

```javascript
// Access component data
const components = drupalSettings.componentViews.components;
const usageStats = drupalSettings.componentViews.usageStats;

// Listen for component events
$(document).on('componentField:component:rendered', function(e, data) {
  console.log('Component rendered:', data.type, data.config);
});
```

## Service Usage

### Component Views Helper Service

```php
// Get the helper service
$helper = \Drupal::service('component_views.helper');

// Get component options for forms
$options = $helper->getComponentOptions(TRUE, TRUE);

// Get usage statistics
$stats = $helper->getComponentUsageStats();

// Get entities using a component
$entities = $helper->getEntitiesUsingComponent('button', 'node', 50);

// Check if component exists
$exists = $helper->componentExists('my_component');

// Get popular components
$popular = $helper->getPopularComponents(10);

// Get unused components
$unused = $helper->getUnusedComponents();
```

### Component Summary Extraction

```php
// Extract summary for display
$summary = $helper->extractComponentSummary(
  'button',
  ['label' => 'Click me', 'variant' => 'primary'],
  50 // max length
);

// Returns:
// [
//   'primary' => 'Click me',
//   'secondary' => 'primary'
// ]
```

## Hooks and Integration

### Theme Hooks

```php
/**
 * Implements hook_theme().
 */
function mymodule_theme($existing, $type, $theme, $path) {
  return [
    'component_views_custom_summary' => [
      'variables' => [
        'component_type' => NULL,
        'summary_data' => [],
      ],
    ],
  ];
}
```

### Views Hooks

```php
/**
 * Implements hook_views_data_alter().
 */
function mymodule_views_data_alter(array &$data) {
  // Add custom component field handlers
  $data['node__field_components']['custom_component_field'] = [
    'title' => t('Custom Component Renderer'),
    'field' => [
      'id' => 'custom_component_field',
    ],
  ];
}
```

### Form Alterations

```php
/**
 * Implements hook_form_alter().
 */
function mymodule_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  // Enhance component filter forms
  if ($form_id === 'views_exposed_form') {
    if (isset($form['component_type'])) {
      $form['component_type']['#description'] = t('Custom help text');
    }
  }
}
```

## Advanced Usage Examples

### Custom Field Formatter

```php
/**
 * Custom component field formatter.
 */
class CustomComponentFormatter extends ComponentFieldFormatter {
  
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    
    // Add custom processing
    foreach ($elements as $delta => &$element) {
      $element['#attributes']['class'][] = 'custom-component';
    }
    
    return $elements;
  }
}
```

### Component-Specific Views

```php
// Create a view programmatically
$view = View::create([
  'id' => 'button_components',
  'label' => 'Button Components',
  'base_table' => 'node_field_data',
  'display' => [
    'default' => [
      'display_plugin' => 'component_display',
      'display_options' => [
        'filters' => [
          'component_type' => [
            'plugin_id' => 'component_type',
            'value' => ['button'],
          ],
        ],
      ],
    ],
  ],
]);
$view->save();
```

### Custom Display Mode

```php
/**
 * Add custom display mode to component field.
 */
function mymodule_component_field_display_modes_alter(&$modes) {
  $modes['gallery'] = [
    'label' => t('Gallery View'),
    'description' => t('Optimized for image-heavy components'),
  ];
}
```

## Performance Tips

### 1. **Choose Appropriate Display Modes**
```php
// For listings: use 'list' or 'compact'
// For detail pages: use 'full'  
// For overviews: use 'summary'
```

### 2. **Configure Caching**
```php
// High-traffic sites: 'aggressive' caching
// Dynamic content: 'intelligent' caching
// Development: 'none' or 'basic'
```

### 3. **Use Component Preloading**
```php
// Enable for views with multiple component types
'component_preload' => TRUE
```

### 4. **Limit Component Count**
```php
// For performance, limit components per page
'limit_components' => 50
```

### 5. **Use Lazy Loading**
```php
// For long lists of components
'component_lazy_load' => TRUE,
'component_batch_size' => 20
```

## Troubleshooting

### Common Issues

#### Components Not Appearing in Filter
```php
// Check component discovery
drush eval "print_r(\Drupal::service('component_field.discovery')->discoverComponents());"

// Clear caches
drush cr

// Refresh component discovery
drush eval "\Drupal::service('component_field.discovery')->clearCache();"
```

#### Performance Issues
```php
// Enable query logging
$GLOBALS['conf']['db_log_queries'] = TRUE;

// Check component complexity
$helper = \Drupal::service('component_views.helper');
$stats = $helper->getComponentUsageStats();
```

#### JavaScript Errors
```javascript
// Check for component data
console.log(drupalSettings.componentViews);

// Verify behaviors are attaching
Drupal.behaviors.componentViewsField.attach(document, drupalSettings);
```

### Debug Mode

Enable debug information for administrators:

```php
// In views field configuration
'show_debug_info' => TRUE

// Check JavaScript console for:
// - Component load times
// - Configuration data
// - Performance metrics
```

## Integration Examples

### With Layout Builder

```php
/**
 * Add component views blocks to Layout Builder.
 */
function mymodule_layout_builder_blocks_alter(&$blocks) {
  $blocks['component_gallery'] = [
    'label' => t('Component Gallery'),
    'category' => t('Components'),
    'class' => 'Drupal\mymodule\Plugin\Block\ComponentGalleryBlock',
  ];
}
```

### With Search API

```php
/**
 * Index component data for search.
 */
function mymodule_search_api_alter_callback(&$value, $type) {
  if ($type === 'component_field') {
    // Extract searchable text from components
    $searchable_text = '';
    foreach ($value as $component) {
      $searchable_text .= ' ' . $component['label'];
      // Add configuration text
      if (isset($component['config']['label'])) {
        $searchable_text .= ' ' . $component['config']['label'];
      }
    }
    $value = $searchable_text;
  }
}
```

### With REST API

```php
/**
 * Expose component views via REST.
 */
function mymodule_rest_resource_alter(&$resources) {
  $resources['component_views'] = [
    'class' => 'Drupal\mymodule\Plugin\rest\resource\ComponentViewsResource',
    'methods' => ['GET'],
    'formats' => ['json'],
  ];
}
```

## Testing

### Unit Tests

```php
/**
 * Test component views functionality.
 */
class ComponentViewsTest extends KernelTestBase {
  
  public function testComponentFilter() {
    // Create test data
    $this->createComponentContent();
    
    // Test filter
    $view = Views::getView('test_component_view');
    $view->setFilter('component_type', 'button');
    $view->execute();
    
    $this->assertCount(1, $view->result);
  }
}
```

### Functional Tests

```php
/**
 * Test component views UI.
 */
class ComponentViewsUITest extends BrowserTestBase {
  
  public function testViewsIntegration() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/views/add');
    
    // Test creating view with component field
    $edit = [
      'label' => 'Test Component View',
      'id' => 'test_component_view',
    ];
    $this->submitForm($edit, 'Save and edit');
    
    // Add component field
    $this->clickLink('Add field');
    // ... test continues
  }
}
```

## Contributing

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `phpunit`
4. Check coding standards: `phpcs`

### Reporting Issues

Please report issues on the project's issue queue with:
- Steps to reproduce
- Expected vs actual behavior  
- Environment details
- Error messages or logs

## License

GPL-2.0-or-later

## Support

- Documentation: See this README
- Issue queue: [Project issues]
- Community support: Drupal Slack #components channel