(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Enhanced behavior for Component Views admin interface.
   */
  Drupal.behaviors.componentViewsAdmin = {
    attach: function (context, settings) {
      // Enhanced Views UI integration
      $('.views-ui-display-tab-bucket', context).once('component-views-admin').each(function() {
        var $bucket = $(this);
        enhanceViewsUI($bucket);
      });

      // Component field configuration enhancements
      $('.component-views-admin', context).once('component-admin-enhanced').each(function() {
        var $admin = $(this);
        enhanceComponentAdmin($admin);
      });

      function enhanceViewsUI($bucket) {
        // Add helper text for component fields
        $bucket.find('.views-display-setting').each(function() {
          var $setting = $(this);
          var $link = $setting.find('a');
          
          if ($link.text().indexOf('Component') !== -1) {
            addComponentHelperText($setting);
          }
        });

        // Add component field detection
        if ($bucket.find('[data-drupal-selector*="component"]').length) {
          $bucket.addClass('has-component-fields');
          addComponentFieldIndicator($bucket);
        }
      }

      function enhanceComponentAdmin($admin) {
        // Add live preview for display modes
        var $displayMode = $admin.find('select[name*="display_mode"]');
        if ($displayMode.length) {
          addDisplayModePreview($displayMode);
        }

        // Add component statistics
        if (settings.componentViews && settings.componentViews.adminStats) {
          addAdminStatistics($admin);
        }

        // Add validation hints
        addValidationHints($admin);
      }

      function addComponentHelperText($setting) {
        if ($setting.find('.component-helper-text').length) {
          return;
        }

        var $helper = $('<div class="component-helper-text">' +
          '<small>💡 This field displays Component Field components with enhanced rendering options.</small>' +
          '</div>');

        $setting.append($helper);
      }

      function addComponentFieldIndicator($bucket) {
        var $indicator = $('<div class="component-field-indicator">' +
          '<span class="indicator-icon">🧩</span>' +
          '<span class="indicator-text">Contains Component Fields</span>' +
          '</div>');

        $bucket.prepend($indicator);
      }

      function addDisplayModePreview($displayMode) {
        var $preview = $('<div class="display-mode-preview"></div>');
        $displayMode.after($preview);

        function updatePreview() {
          var mode = $displayMode.val();
          var previewHtml = '';

          switch (mode) {
            case 'full':
              previewHtml = '<div class="preview-full">📄 Full component rendering with all properties and styling</div>';
              break;
            case 'summary':
              previewHtml = '<div class="preview-summary">📋 Summary view with key information and metadata</div>';
              break;
            case 'list':
              previewHtml = '<div class="preview-list">📝 List view with component name and property count</div>';
              break;
            case 'compact':
              previewHtml = '<div class="preview-compact">🏷️ Compact view with minimal information</div>';
              break;
          }

          $preview.html(previewHtml);
        }

        $displayMode.on('change', updatePreview);
        updatePreview();
      }

      function addAdminStatistics($admin) {
        var stats = settings.componentViews.adminStats;
        
        var $statsContainer = $('<div class="component-admin-stats">' +
          '<h4>Component Statistics</h4>' +
          '<div class="stats-grid"></div>' +
          '</div>');

        var $grid = $statsContainer.find('.stats-grid');

        Object.keys(stats).forEach(function(key) {
          var value = stats[key];
          var label = key.replace(/([A-Z])/g, ' $1').replace(/^./, function(str) {
            return str.toUpperCase();
          });

          var $stat = $('<div class="stat-item">' +
            '<div class="stat-value">' + value + '</div>' +
            '<div class="stat-label">' + label + '</div>' +
            '</div>');

          $grid.append($stat);
        });

        $admin.prepend($statsContainer);
      }

      function addValidationHints($admin) {
        // Add hints for common configuration issues
        $admin.find('input, select, textarea').each(function() {
          var $field = $(this);
          var name = $field.attr('name') || '';

          if (name.indexOf('limit') !== -1) {
            addHint($field, 'Set to 0 for unlimited components');
          } else if (name.indexOf('filter') !== -1) {
            addHint($field, 'Leave empty to show all component types');
          } else if (name.indexOf('classes') !== -1) {
            addHint($field, 'Space-separated CSS classes');
          }
        });
      }

      function addHint($field, text) {
        if ($field.siblings('.field-hint').length) {
          return;
        }

        var $hint = $('<div class="field-hint">💡 ' + text + '</div>');
        $field.after($hint);
      }

      // Add real-time validation
      $(document).on('change', '.component-views-admin input, .component-views-admin select', function() {
        var $field = $(this);
        validateField($field);
      });

      function validateField($field) {
        var value = $field.val();
        var name = $field.attr('name') || '';
        var $error = $field.siblings('.field-error');

        // Remove existing error
        $error.remove();

        // Validate specific fields
        if (name.indexOf('limit') !== -1 && value) {
          if (!$.isNumeric(value) || parseInt(value) < 0) {
            addError($field, 'Must be a positive number or 0');
          }
        }
      }

      function addError($field, message) {
        var $error = $('<div class="field-error">⚠️ ' + message + '</div>');
        $field.after($error);
      }
    }
  };

  // Add CSS for admin enhancements
  $(document).ready(function() {
    if (!$('#component-views-admin-styles').length) {
      $('<style id="component-views-admin-styles">' +
        '.has-component-fields { border-left: 4px solid #007bff; } ' +
        '.component-field-indicator { background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 0.25rem; padding: 0.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; } ' +
        '.indicator-icon { font-size: 1.2em; } ' +
        '.component-helper-text { margin-top: 0.5rem; color: #6c757d; } ' +
        '.display-mode-preview { margin-top: 0.5rem; padding: 0.75rem; background: #f8f9fa; border-radius: 0.25rem; border: 1px solid #e9ecef; } ' +
        '.preview-full, .preview-summary, .preview-list, .preview-compact { padding: 0.5rem; border-radius: 0.25rem; font-size: 0.875rem; } ' +
        '.preview-full { background: #e7f3ff; color: #004085; } ' +
        '.preview-summary { background: #fff3cd; color: #856404; } ' +
        '.preview-list { background: #d1ecf1; color: #0c5460; } ' +
        '.preview-compact { background: #f8d7da; color: #721c24; } ' +
        '.component-admin-stats { background: white; border: 1px solid #e9ecef; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem; } ' +
        '.component-admin-stats h4 { margin: 0 0 1rem 0; color: #495057; } ' +
        '.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; } ' +
        '.stat-item { text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 0.25rem; } ' +
        '.stat-value { font-size: 1.5rem; font-weight: 600; color: #007bff; } ' +
        '.stat-label { font-size: 0.875rem; color: #6c757d; margin-top: 0.25rem; } ' +
        '.field-hint { margin-top: 0.25rem; font-size: 0.75rem; color: #6c757d; } ' +
        '.field-error { margin-top: 0.25rem; font-size: 0.75rem; color: #dc3545; background: #f8d7da; padding: 0.25rem 0.5rem; border-radius: 0.25rem; } ' +
        '</style>').appendTo('head');
    }
  });

})(jQuery, Drupal, drupalSettings);