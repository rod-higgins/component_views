# Component Views Module

## Overview

The **Component Views** module provides comprehensive Views integration for the Component Field module, enabling powerful filtering, display, and management of Single Directory Components (SDC) through Drupal's Views system. This module transforms how you work with components in content by providing advanced visualization, filtering, and analysis capabilities.

## Features

### 🎯 **Advanced Views Integration**
- **Dedicated Field Plugin**: Component rendering with multiple display modes optimized for Views
- **Component Type Filter**: Advanced filtering with search functionality and usage statistics
- **Component Type Argument**: URL-based filtering for dynamic component galleries
- **Optimized Display Plugin**: Performance-optimized rendering for component-heavy views
- **Smart Caching**: Intelligent cache strategies with component definition preloading

### 📊 **Multiple Display Modes**
- **Full**: Complete component rendering with all properties and configuration
- **Summary**: Key information display with metadata and usage statistics
- **List**: Minimal display showing component name and property count
- **Compact**: Ultra-condensed inline display for space-constrained layouts

### 🔍 **Powerful Filtering & Search**
- **Multi-Select Component Filter**: Filter by multiple component types simultaneously
- **Search Within Components**: Live search functionality within component filter options
- **Usage Statistics Integration**: See component popularity and usage data in filter interface
- **Case-Sensitive/Insensitive**: Flexible matching options for component discovery
- **Exposed Filters**: Allow site visitors to filter component galleries

### ⚡ **Performance Optimizations**
- **Component Definition Preloading**: Reduces discovery overhead in Views
- **Intelligent Caching**: Leverages component_field.discovery cache system
- **Lazy Loading**: Deferred loading for large component lists
- **Batch Processing**: Handles large datasets efficiently
- **Query Optimization**: Optimized database queries for component filtering

### 🎨 **Enhanced User Experience**
- **Live Preview**: Component preview while configuring Views
- **Animation Support**: Smooth transitions and visual feedback
- **Responsive Design**: Mobile-friendly component displays
- **Component Analytics**: Usage tracking and popularity metrics
- **Admin Interface**: Dedicated management pages for component views

## Installation & Setup

### Prerequisites
The Component Views module requires the **Component Field** module to be installed and functional.

```bash
# Install Component Field first
drush en component_field

# Install Component Views
drush en component_views

# Clear all caches
drush cr
```

### Verification
After installation, verify functionality:

1. **Check Requirements**: Visit `/admin/reports/status` to ensure all requirements are met
2. **View Components**: Go to `/admin/structure/component-field` to see discovered components
3. **Create Views**: Navigate to `/admin/structure/views/add` to start creating component views

## Quick Start Guide

### Creating a Component Gallery

1. **Create New View**:
   ```
   URL: /admin/structure/views/add
   Name: Component Gallery
   Base Table: Content (node)
   ```

2. **Add Component Field**:
   - Click "Add Field"
   - Select your component field (e.g., "field_component_field")
   - Choose display mode: Full, Summary, List, or Compact
   - Configure responsiveness and animation options

3. **Add Component Type Filter**:
   - Click "Add Filter Criteria"
   - Select "Component Type"
   - Choose expose options for visitor filtering
   - Enable multiple selection if desired

4. **Configure Display**:
   - Add page display with path like `/components/gallery`
   - Set up menu integration
   - Configure caching for optimal performance

### Component Showcase View

Create a comprehensive component showcase:

```yaml
# Example View Configuration
displays:
  page_1:
    display_plugin: page
    display_title: Component Showcase
    display_options:
      path: components/showcase
      menu:
        type: normal
        title: Component Gallery
        menu_name: main
      filters:
        component_type:
          exposed: true
          multiple: true
          search: true
      fields:
        field_component_field:
          type: component_field
          display_mode: summary
          enable_animations: true
          responsive: true
```

## Configuration Guide

### Field Display Settings

#### Full Display Mode
- Complete component rendering with all properties
- Shows component configuration and metadata
- Best for detailed component documentation
- Higher performance cost but maximum information

#### Summary Display Mode
- Displays key component information
- Shows primary properties and usage statistics
- Balanced between detail and performance
- Ideal for component catalogs

#### List Display Mode
- Minimal component information
- Component name and property count only
- Fastest rendering mode
- Perfect for administrative overviews

#### Compact Display Mode
- Ultra-minimal inline display
- Component type and key identifier only
- Lowest resource usage
- Suitable for embedded component lists

### Filter Configuration

#### Component Type Filter
```php
// Advanced filter configuration
$filter_options = [
  'expose' => TRUE,
  'multiple' => TRUE,
  'search_enabled' => TRUE,
  'show_statistics' => TRUE,
  'case_sensitive' => FALSE,
  'include_unused' => FALSE,
];
```

#### Usage Statistics
The filter automatically displays:
- Component popularity ranking
- Usage count per component type
- Most/least used components
- Component availability status

### Performance Tuning

#### Cache Configuration
```php
// Enable aggressive caching for component views
$view->element['#cache'] = [
  'tags' => ['component_field', 'component_field.discovery'],
  'contexts' => ['user.permissions', 'url.query_args'],
  'max-age' => 3600, // 1 hour cache
];
```

#### Preloading Components
```php
// Components are automatically preloaded for views
// Access preloaded components in templates:
$components = $view->component_definitions ?? [];
```

## API Reference

### Component Views Helper Service

```php
// Get the helper service
$helper = \Drupal::service('component_views.helper');

// Get component options for Views forms
$options = $helper->getComponentOptions($include_stats = TRUE, $include_descriptions = TRUE);

// Get usage statistics across the site
$stats = $helper->getComponentUsageStats();
// Returns: ['button' => 45, 'card' => 23, 'modal' => 12]

// Find entities using specific components
$entities = $helper->getEntitiesUsingComponent('button', $entity_type = 'node', $limit = 50);

// Check component availability
$exists = $helper->componentExists('my_custom_component');

// Get most popular components
$popular = $helper->getPopularComponents($limit = 10);

// Find unused components
$unused = $helper->getUnusedComponents();
```

### Component Summary Extraction

```php
// Extract component summary for display
$summary = $helper->extractComponentSummary(
  $component_type = 'button',
  $configuration = ['label' => 'Click me', 'variant' => 'primary'],
  $max_length = 50
);

// Returns structured summary data:
// [
//   'primary' => 'Click me',
//   'secondary' => 'primary',
//   'metadata' => ['variant' => 'primary']
// ]
```

## Integration with Component Field

### Auto-Discovery Integration

Component Views leverages the Component Field module's auto-discovery system:

```php
// Component discovery is handled automatically
$discovery = \Drupal::service('component_field.discovery');
$components = $discovery->discoverComponents();

// Views automatically refreshes when components change
// Manual refresh available at: /admin/structure/component-field
```

### Component Requirements for Views

Components discovered by Component Field are automatically available in Views if they:

1. **Follow SDC Standards**: Proper Single Directory Component structure
2. **Have Valid Props**: Properly defined component properties
3. **Are Cached**: Successfully processed by component discovery
4. **Have Permissions**: User has access to view the components

### Supported Component Sources

- **Theme Components**: Components in active theme directories
- **MUI Components**: Material UI components with proper imports
- **Custom Components**: User-defined components following SDC patterns
- **Third-Party Components**: Components from contributed themes

## Theming & Customization

### Theme Templates

#### Component Views Field Template
```twig
{# templates/component-views-field.html.twig #}
<div class="component-views-field component-views-mode-{{ display_mode }}">
  {% for delta, component in components %}
    <div class="component-views-item component-views-{{ component.type }} component-views-delta-{{ delta }}">
      {% if display_mode == 'full' %}
        {{ component.rendered }}
        <div class="component-metadata">
          <span class="component-type">{{ component.type }}</span>
          <span class="component-props-count">{{ component.props_count }} props</span>
        </div>
      {% elseif display_mode == 'summary' %}
        <h4>{{ component.label }}</h4>
        <p>{{ component.summary.primary }}</p>
        <small>{{ component.summary.secondary }}</small>
      {% elseif display_mode == 'list' %}
        <span class="component-name">{{ component.type }}</span>
        <span class="component-count">({{ component.props_count }})</span>
      {% else %}
        <code>{{ component.type }}</code>
      {% endif %}
    </div>
  {% endfor %}
</div>
```

#### Component Summary Template
```twig
{# templates/component-views-summary.html.twig #}
<div class="component-summary" data-component-type="{{ component_type }}">
  <div class="component-summary-primary">{{ summary_data.primary }}</div>
  {% if summary_data.secondary %}
    <div class="component-summary-secondary">{{ summary_data.secondary }}</div>
  {% endif %}
  <div class="component-summary-meta">
    {% for key, value in summary_data.metadata %}
      <span class="meta-item">{{ key }}: {{ value }}</span>
    {% endfor %}
  </div>
</div>
```

### CSS Classes

Component Views provides comprehensive CSS classes for styling:

```css
/* Field wrapper classes */
.component-views-field
.component-views-mode-{full|summary|list|compact}
.component-views-count-{number}

/* Individual component classes */
.component-views-item
.component-views-{component-type}
.component-views-delta-{number}
.component-views-animated

/* Display mode specific classes */
.component-views-summary
.component-views-list-item
.component-views-compact

/* Filter classes */
.component-views-filter
.component-views-filter-exposed
.component-views-filter-search
```

### JavaScript Behaviors

```javascript
// Component Views provides several Drupal behaviors

// Field enhancements
Drupal.behaviors.componentViewsField = {
  attach: function (context, settings) {
    // Add interactive features to component displays
    $('.component-views-item', context).once('component-views').each(function() {
      // Add hover effects, click handlers, etc.
    });
  }
};

// Filter enhancements
Drupal.behaviors.componentViewsFilter = {
  attach: function (context, settings) {
    // Enhance exposed filters with search and statistics
    $('.component-views-filter input[type="search"]', context).once('search').on('input', function() {
      // Live search functionality
    });
  }
};

// Admin interface enhancements
Drupal.behaviors.componentViewsAdmin = {
  attach: function (context, settings) {
    // Admin UI improvements
    $('.views-admin .component-views-options', context).once('admin').each(function() {
      // Add configuration helpers
    });
  }
};
```

### Custom JavaScript Integration

```javascript
// Access component data in JavaScript
if (drupalSettings.componentViews) {
  const components = drupalSettings.componentViews.components;
  const usageStats = drupalSettings.componentViews.usageStats;
  
  // Use component data for custom functionality
  Object.keys(components).forEach(type => {
    console.log(`Component ${type} used ${usageStats[type] || 0} times`);
  });
}

// Listen for component rendering events
$(document).on('componentField:component:rendered', function(e, data) {
  console.log('Component rendered in view:', data.type, data.config);
});
```

## Advanced Integration

### Search API Integration

```php
/**
 * Add component content to search index.
 */
function mymodule_search_api_alter_callback(&$value, $type) {
  if ($type === 'component_field') {
    $searchable_text = '';
    foreach ($value as $component) {
      $searchable_text .= ' ' . $component['type'];
      if (isset($component['config']['label'])) {
        $searchable_text .= ' ' . $component['config']['label'];
      }
      // Add other searchable component properties
    }
    $value = $searchable_text;
  }
}
```

### REST API Integration

```php
/**
 * Expose component views via REST API.
 */
function mymodule_rest_resource_alter(&$resources) {
  $resources['component_views'] = [
    'class' => 'Drupal\mymodule\Plugin\rest\resource\ComponentViewsResource',
    'methods' => ['GET'],
    'formats' => ['json'],
    'authentication' => ['basic_auth'],
  ];
}
```

### Custom Views Plugins

```php
/**
 * Add custom Views plugins for components.
 */
function mymodule_views_plugins_alter(array &$plugins) {
  $plugins['field']['custom_component_renderer'] = [
    'id' => 'custom_component_renderer',
    'title' => t('Custom Component Renderer'),
    'help' => t('Custom rendering for component fields.'),
    'class' => 'Drupal\mymodule\Plugin\views\field\CustomComponentRenderer',
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
  
  protected static $modules = ['component_field', 'component_views', 'views'];
  
  public function testComponentFilter() {
    // Create test content with components
    $this->createComponentContent();
    
    // Test component type filter
    $view = Views::getView('test_component_view');
    $view->setFilter('component_type', 'button');
    $view->execute();
    
    $this->assertCount(1, $view->result);
    $this->assertEquals('button', $view->result[0]->_entity->field_component_field[0]->component_type);
  }
  
  public function testComponentFieldDisplay() {
    $view = Views::getView('test_component_view');
    $view->setDisplay('default');
    
    $field = $view->field['field_component_field'];
    $this->assertInstanceOf(ComponentField::class, $field);
    
    // Test display modes
    $field->options['display_mode'] = 'summary';
    $this->assertEquals('summary', $field->options['display_mode']);
  }
}
```

### Functional Tests

```php
/**
 * Test component views UI integration.
 */
class ComponentViewsUITest extends BrowserTestBase {
  
  protected static $modules = ['component_field', 'component_views', 'views_ui'];
  
  public function testViewsIntegration() {
    $admin_user = $this->createUser(['administer views']);
    $this->drupalLogin($admin_user);
    
    // Test creating view with component field
    $this->drupalGet('/admin/structure/views/add');
    $edit = [
      'label' => 'Test Component View',
      'id' => 'test_component_view',
    ];
    $this->submitForm($edit, 'Save and edit');
    
    // Add component field
    $this->clickLink('Add field');
    $this->assertSession()->pageTextContains('Component Field');
    
    // Test filter configuration
    $this->clickLink('Add filter');
    $this->assertSession()->pageTextContains('Component Type');
  }
}
```

## Troubleshooting

### Common Issues

#### No Components Appear in Views
1. **Check Component Field**: Ensure Component Field module is enabled and working
2. **Verify Discovery**: Visit `/admin/structure/component-field` to refresh component discovery
3. **Check Permissions**: Ensure users have permission to view components
4. **Clear Caches**: Run `drush cr` to clear all caches

#### Poor Performance
1. **Enable Caching**: Configure appropriate cache settings for your views
2. **Limit Results**: Use pagination and limit the number of results per page
3. **Optimize Display Mode**: Use 'List' or 'Compact' mode for large datasets
4. **Database Optimization**: Ensure proper database indexing

#### Filter Not Working
1. **Check Field Configuration**: Ensure component field is properly configured
2. **Verify Component Types**: Check that components exist and are discoverable
3. **Review Permissions**: Ensure proper field and component permissions
4. **Debug Queries**: Enable query logging to troubleshoot filter queries

### Debugging

#### Enable Debug Logging
```php
// Add to settings.php for debugging
$config['system.logging']['error_level'] = 'verbose';

// Component Views specific logging
\Drupal::logger('component_views')->debug('Debug message: @data', ['@data' => print_r($data, TRUE)]);
```

#### Performance Profiling
```php
// Profile component rendering performance
$start = microtime(TRUE);
$components = $discovery->discoverComponents();
$discovery_time = microtime(TRUE) - $start;

\Drupal::logger('component_views')->info('Component discovery took @time seconds', [
  '@time' => number_format($discovery_time, 4)
]);
```

## Contributing

### Development Setup

```bash
# Clone the repository
git clone [repository-url]
cd component_views

# Install development dependencies
composer install --dev

# Run coding standards check
vendor/bin/phpcs --standard=Drupal src/

# Run tests
vendor/bin/phpunit tests/
```

### Reporting Issues

When reporting issues, please include:

1. **Steps to Reproduce**: Clear, numbered steps
2. **Expected vs Actual Behavior**: What should happen vs what actually happens
3. **Environment Details**: Drupal version, PHP version, module versions
4. **Error Messages**: Complete error messages and stack traces
5. **Configuration**: Views configuration export if relevant

### Feature Requests

For feature requests, please:

1. **Search Existing Issues**: Check if the feature is already requested
2. **Provide Use Case**: Explain the business need and user story
3. **Include Examples**: Mockups, wireframes, or examples from other systems
4. **Consider Alternatives**: Mention any workarounds you've considered

## License

GPL-2.0-or-later

## Support

- **Documentation**: This README and inline code documentation
- **Issue Queue**: [Component Views Issue Queue]
- **Community Support**: Drupal Slack #components channel
- **Professional Support**: Available through Drupal service providers

## Related Modules

- **Component Field**: Required dependency for component discovery and field functionality
- **Single Directory Components**: Core Drupal SDC system
- **Views**: Core Drupal Views system for listing and filtering content
- **Field API**: Core Drupal field system for storing component data
