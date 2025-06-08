(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Enhanced behavior for Component Views filters.
   */
  Drupal.behaviors.componentViewsFilter = {
    attach: function (context, settings) {
      // Enhanced component type filter
      $('.component-views-filter-exposed', context).once('component-filter-enhanced').each(function() {
        var $filter = $(this);
        enhanceComponentFilter($filter);
      });

      // Handle filter form enhancements
      $('.views-exposed-form', context).once('component-views-exposed').each(function() {
        var $form = $(this);
        enhanceExposedForm($form);
      });

      function enhanceComponentFilter($filter) {
        // Add search functionality to multi-select filters
        if ($filter.is('select[multiple]')) {
          addFilterSearch($filter);
        }

        // Add component statistics
        if (settings.componentViews && settings.componentViews.usageStats) {
          addComponentStats($filter);
        }

        // Add preview functionality
        addFilterPreview($filter);
      }

      function enhanceExposedForm($form) {
        // Add "Apply Filters" button enhancement
        var $submit = $form.find('input[type="submit"]');
        if ($submit.length) {
          $submit.addClass('component-filter-submit');
          
          // Add loading state
          $form.on('submit', function() {
            $submit.addClass('component-filter-loading')
                   .val('Filtering...')
                   .prop('disabled', true);
          });
        }

        // Add reset functionality
        if (!$form.find('.component-filter-reset').length) {
          var $reset = $('<button type="button" class="component-filter-reset">Reset Filters</button>');
          $submit.after($reset);
          
          $reset.on('click', function() {
            $form.find('select, input').val('');
            $form.submit();
          });
        }

        // Add filter summary
        addFilterSummary($form);
      }

      function addFilterSearch($filter) {
        var $wrapper = $('<div class="component-filter-search-wrapper"></div>');
        var $search = $('<input type="text" class="component-filter-search" placeholder="Search components..." />');
        
        $filter.before($wrapper);
        $wrapper.append($search);
        $wrapper.append($filter);

        $search.on('input', function() {
          var query = $(this).val().toLowerCase();
          
          $filter.find('option').each(function() {
            var $option = $(this);
            var text = $option.text().toLowerCase();
            
            if (!query || text.indexOf(query) !== -1) {
              $option.show();
            } else {
              $option.hide();
            }
          });
        });
      }

      function addComponentStats($filter) {
        var stats = settings.componentViews.usageStats;
        
        if (!stats || Object.keys(stats).length === 0) {
          return;
        }

        var $statsContainer = $('<div class="component-filter-stats"></div>');
        var $statsList = $('<ul class="component-stats-list"></ul>');

        Object.keys(stats).slice(0, 5).forEach(function(componentType) {
          var count = stats[componentType];
          var $statItem = $('<li class="component-stat-item">' +
            '<span class="component-stat-name">' + componentType + '</span>' +
            '<span class="component-stat-count">' + count + '</span>' +
            '</li>');
          
          $statsList.append($statItem);
        });

        $statsContainer.append('<h6>Most Used Components</h6>');
        $statsContainer.append($statsList);
        
        $filter.after($statsContainer);
      }

      function addFilterPreview($filter) {
        var $preview = $('<div class="component-filter-preview"></div>');
        $filter.after($preview);

        $filter.on('change', function() {
          var selectedValues = $filter.val();
          
          if (!selectedValues || selectedValues.length === 0) {
            $preview.empty();
            return;
          }

          var $previewContent = $('<div class="filter-preview-content">' +
            '<h6>Selected Components (' + selectedValues.length + ')</h6>' +
            '<div class="filter-preview-tags"></div>' +
            '</div>');

          var $tags = $previewContent.find('.filter-preview-tags');
          
          selectedValues.forEach(function(value) {
            var $tag = $('<span class="filter-preview-tag">' + value + '</span>');
            $tags.append($tag);
          });

          $preview.html($previewContent);
        });
      }

      function addFilterSummary($form) {
        var $summary = $('<div class="component-filter-summary"></div>');
        $form.prepend($summary);

        function updateSummary() {
          var activeFilters = [];
          
          $form.find('select, input[type="text"]').each(function() {
            var $field = $(this);
            var value = $field.val();
            var label = $field.closest('.form-item').find('label').text() || $field.attr('name');
            
            if (value && value !== '') {
              if (Array.isArray(value)) {
                if (value.length > 0) {
                  activeFilters.push(label + ': ' + value.length + ' selected');
                }
              } else {
                activeFilters.push(label + ': ' + value);
              }
            }
          });

          if (activeFilters.length > 0) {
            $summary.html('<strong>Active Filters:</strong> ' + activeFilters.join(' | '));
            $summary.show();
          } else {
            $summary.hide();
          }
        }

        $form.find('select, input').on('change input', updateSummary);
        updateSummary();
      }

      // Handle AJAX form updates
      $(document).on('ajaxComplete', function(event, xhr, settings) {
        if (settings.url && settings.url.indexOf('views/ajax') !== -1) {
          // Re-run behaviors after AJAX updates
          setTimeout(function() {
            Drupal.behaviors.componentViewsFilter.attach(document, drupalSettings);
          }, 100);
        }
      });
    }
  };

  // Add CSS for filter enhancements
  $(document).ready(function() {
    if (!$('#component-views-filter-styles').length) {
      $('<style id="component-views-filter-styles">' +
        '.component-filter-search-wrapper { position: relative; margin-bottom: 0.5rem; } ' +
        '.component-filter-search { width: 100%; padding: 0.375rem 0.75rem; border: 1px solid #ced4da; border-radius: 0.25rem; margin-bottom: 0.5rem; } ' +
        '.component-filter-stats { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.25rem; padding: 1rem; margin-top: 0.5rem; } ' +
        '.component-filter-stats h6 { margin: 0 0 0.5rem 0; color: #495057; } ' +
        '.component-stats-list { list-style: none; margin: 0; padding: 0; } ' +
        '.component-stat-item { display: flex; justify-content: space-between; padding: 0.25rem 0; border-bottom: 1px solid #e9ecef; } ' +
        '.component-stat-item:last-child { border-bottom: none; } ' +
        '.component-stat-name { color: #495057; } ' +
        '.component-stat-count { font-weight: 600; color: #007bff; } ' +
        '.component-filter-preview { margin-top: 0.5rem; } ' +
        '.filter-preview-content { background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 0.25rem; padding: 0.75rem; } ' +
        '.filter-preview-content h6 { margin: 0 0 0.5rem 0; color: #004085; } ' +
        '.filter-preview-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; } ' +
        '.filter-preview-tag { background: #007bff; color: white; padding: 0.125rem 0.5rem; border-radius: 1rem; font-size: 0.75rem; } ' +
        '.component-filter-submit { background: #007bff; color: white; border: none; padding: 0.375rem 0.75rem; border-radius: 0.25rem; cursor: pointer; } ' +
        '.component-filter-submit.component-filter-loading { background: #6c757d; cursor: not-allowed; } ' +
        '.component-filter-reset { background: #6c757d; color: white; border: none; padding: 0.375rem 0.75rem; border-radius: 0.25rem; cursor: pointer; margin-left: 0.5rem; } ' +
        '.component-filter-summary { background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 0.25rem; padding: 0.75rem; margin-bottom: 1rem; color: #0c5460; } ' +
        '</style>').appendTo('head');
    }
  });

})(jQuery, Drupal, drupalSettings);