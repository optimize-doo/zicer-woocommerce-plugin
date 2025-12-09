/**
 * ZICER Admin JavaScript
 *
 * @package Zicer_Woo_Sync
 */

/* global jQuery, zicerAdmin */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Initialize Select2 for category dropdowns
        if ($.fn.select2) {
            $('.zicer-select2').select2({
                placeholder: '-- Select --',
                allowClear: true,
                width: '100%'
            });
        }

        // Test connection
        $('#zicer-test-connection').on('click', function() {
            var $btn = $(this);
            var token = $('#zicer_api_token').val();

            $btn.prop('disabled', true).text(zicerAdmin.strings.testing);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_test_connection',
                nonce: zicerAdmin.nonce,
                token: token
            }, function(response) {
                $btn.prop('disabled', false).text('Test Connection');
                if (response.success) {
                    $('#zicer-connection-status')
                        .removeClass('disconnected')
                        .addClass('connected')
                        .html('&#10003; ' + zicerAdmin.strings.connected);
                    location.reload();
                } else {
                    alert(zicerAdmin.strings.error + ' ' + response.data);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Test Connection');
                alert(zicerAdmin.strings.error + ' Connection failed');
            });
        });

        // Sync product
        $('.zicer-sync-now').on('click', function() {
            var $btn = $(this);
            var productId = $btn.data('product-id');

            $btn.prop('disabled', true).text(zicerAdmin.strings.syncing);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_sync_product',
                nonce: zicerAdmin.nonce,
                product_id: productId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(zicerAdmin.strings.error + ' ' + response.data);
                    $btn.prop('disabled', false).text('Sync Now');
                }
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false).text('Sync Now');
            });
        });

        // Delete listing
        $('.zicer-delete-listing').on('click', function() {
            if (!confirm('Are you sure?')) {
                return;
            }

            var $btn = $(this);
            var productId = $btn.data('product-id');

            $btn.prop('disabled', true);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_delete_listing',
                nonce: zicerAdmin.nonce,
                product_id: productId
            }, function(response) {
                location.reload();
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false);
            });
        });

        // Bulk sync
        $('#zicer-bulk-sync').on('click', function() {
            if (!confirm(zicerAdmin.strings.confirm_bulk)) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text(zicerAdmin.strings.syncing);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_bulk_sync',
                nonce: zicerAdmin.nonce
            }, function(response) {
                if (response.success) {
                    alert('Added ' + response.data.queued + ' products to queue.');
                    location.reload();
                } else {
                    alert(zicerAdmin.strings.error + ' ' + response.data);
                    $btn.prop('disabled', false).text('Start Bulk Sync');
                }
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false).text('Start Bulk Sync');
            });
        });

        // Load categories
        $('#zicer-load-categories').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Loading...');

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_fetch_categories',
                nonce: zicerAdmin.nonce
            }, function(response) {
                location.reload();
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false).text('Load Categories');
            });
        });

        // Category suggestions
        $('.zicer-suggest-category').on('click', function() {
            var $btn = $(this);
            var termName = $btn.data('term-name');
            var termId = $btn.data('term-id');
            var $select = $('select[name="mapping[' + termId + ']"]');
            var $spinner = $btn.next('.spinner');
            var $result = $btn.siblings('.zicer-suggest-result');

            $spinner.addClass('is-active');
            $result.removeClass('success error').text('');

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_suggest_category',
                nonce: zicerAdmin.nonce,
                title: termName
            }, function(response) {
                $spinner.removeClass('is-active');
                if (response.success && response.data.length > 0) {
                    var suggestion = response.data[0];
                    var suggestionId = suggestion.uuid || suggestion.id;

                    // Try to find and select the suggestion
                    if (suggestionId && $select.find('option[value="' + suggestionId + '"]').length) {
                        $select.val(suggestionId).trigger('change');
                        $result.addClass('success').text('✓');
                    } else {
                        $result.addClass('error').text('No match found');
                    }
                } else {
                    $result.addClass('error').text('No suggestions');
                }
            }).fail(function() {
                $spinner.removeClass('is-active');
                $result.addClass('error').text('Error');
            });
        });

        // Refresh regions
        $('#zicer-refresh-regions').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Loading...');

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_fetch_regions',
                nonce: zicerAdmin.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(zicerAdmin.strings.error + ' ' + response.data);
                    $btn.prop('disabled', false).text('Refresh');
                }
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false).text('Refresh');
            });
        });

        // Region change - load cities
        $('#zicer_default_region').on('change', function() {
            var regionId = $(this).val();
            var $citySelect = $('#zicer_default_city');

            if (!regionId) {
                $citySelect.html('<option value="">-- Select region first --</option>');
                return;
            }

            $citySelect.html('<option value="">Loading...</option>');

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_fetch_cities',
                nonce: zicerAdmin.nonce,
                region_id: regionId
            }, function(response) {
                if (response.success) {
                    var options = '<option value="">-- Select --</option>';
                    var currentCity = $('#zicer_current_city').val();
                    $.each(response.data, function(i, city) {
                        var selected = city.id === currentCity ? ' selected' : '';
                        options += '<option value="' + city.id + '"' + selected + '>' + city.title + '</option>';
                    });
                    $citySelect.html(options);
                } else {
                    $citySelect.html('<option value="">Error loading cities</option>');
                }
            }).fail(function() {
                $citySelect.html('<option value="">Error loading cities</option>');
            });
        });

        // Trigger city load on page load if region is selected
        if ($('#zicer_default_region').val()) {
            $('#zicer_default_region').trigger('change');
        }

        // Retry failed
        $('#zicer-retry-failed').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_retry_failed',
                nonce: zicerAdmin.nonce
            }, function(response) {
                location.reload();
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false);
            });
        });

        // Clear failed
        $('#zicer-clear-failed').on('click', function() {
            if (!confirm('Are you sure you want to clear all failed items?')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_clear_failed',
                nonce: zicerAdmin.nonce
            }, function(response) {
                location.reload();
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false);
            });
        });

    });

})(jQuery);
