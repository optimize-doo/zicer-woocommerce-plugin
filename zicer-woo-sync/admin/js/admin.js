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

        // Clear category override link
        $('.zicer-clear-override').on('click', function(e) {
            e.preventDefault();
            var $select = $('#_zicer_category');
            var mappedCategory = $select.data('mapped-category');
            if (mappedCategory) {
                $select.val(mappedCategory).trigger('change');
            } else {
                $select.val('').trigger('change');
            }
        });

        // Save category and enable/disable sync button when category changes
        $('#_zicer_category').on('change', function() {
            var $select = $(this);
            var $syncBtn = $('.zicer-sync-now');
            var productId = $syncBtn.data('product-id');
            var category = $select.val();
            var mappedCategory = $select.data('mapped-category');

            // Enable if category selected, disable if empty and no mapped category
            if (category || mappedCategory) {
                $syncBtn.prop('disabled', false);
            } else {
                $syncBtn.prop('disabled', true);
            }

            // Save category via AJAX
            if (productId) {
                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_save_product_category',
                    nonce: zicerAdmin.nonce,
                    product_id: productId,
                    category: category
                });
            }
        });

        // Disconnect
        $('#zicer-disconnect').on('click', function() {
            if (!confirm('Are you sure you want to disconnect? Previously synced products will keep their ZICER listing IDs.')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_disconnect',
                nonce: zicerAdmin.nonce
            }, function(response) {
                location.reload();
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false);
            });
        });

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
                    // Check if different account
                    if (response.data.different_account) {
                        var msg = 'Warning: You are connecting with a different account.\n\n' +
                            'Previous: ' + (response.data.previous_email || 'Unknown') + '\n' +
                            'New: ' + (response.data.user.email || 'Unknown') + '\n\n' +
                            'Previously synced products have listing IDs from the old account.\n' +
                            'Do you want to clear all ZICER listing data?\n\n' +
                            'Click OK to clear, Cancel to keep existing data.';

                        if (confirm(msg)) {
                            $.post(zicerAdmin.ajaxUrl, {
                                action: 'zicer_clear_all_listings',
                                nonce: zicerAdmin.nonce
                            }, function() {
                                location.reload();
                            });
                            return;
                        }
                    }

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
                // Always reload to show updated status in meta box
                location.reload();
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

        // Clear stale data and re-sync (when listing deleted on ZICER)
        $('.zicer-clear-stale').on('click', function() {
            var $btn = $(this);
            var productId = $btn.data('product-id');

            $btn.prop('disabled', true).text('Clearing...');

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_clear_stale',
                nonce: zicerAdmin.nonce,
                product_id: productId
            }, function(response) {
                if (response.success) {
                    // Now sync the product
                    $btn.text('Syncing...');
                    $.post(zicerAdmin.ajaxUrl, {
                        action: 'zicer_sync_product',
                        nonce: zicerAdmin.nonce,
                        product_id: productId
                    }, function(syncResponse) {
                        location.reload();
                    }).fail(function() {
                        alert(zicerAdmin.strings.error + ' Sync failed');
                        location.reload();
                    });
                } else {
                    alert(zicerAdmin.strings.error + ' ' + response.data);
                    $btn.prop('disabled', false).text('Clear & Re-create');
                }
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false).text('Clear & Re-create');
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

        // Process queue
        $('#zicer-process-queue').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Processing...');

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_process_queue',
                nonce: zicerAdmin.nonce
            }, function(response) {
                if (response.success) {
                    var stats = response.data;
                    if (stats.pending > 0 || stats.processing > 0) {
                        // More items to process, click again
                        $btn.text('Processing... (' + stats.pending + ' remaining)');
                        setTimeout(function() {
                            $btn.click();
                        }, 500);
                    } else {
                        location.reload();
                    }
                } else {
                    alert(zicerAdmin.strings.error + ' ' + response.data);
                    $btn.prop('disabled', false).text('Process Queue Now');
                }
            }).fail(function() {
                alert(zicerAdmin.strings.error + ' Connection failed');
                $btn.prop('disabled', false).text('Process Queue Now');
            });
        });

    });

})(jQuery);
