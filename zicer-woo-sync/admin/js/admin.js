/**
 * ZICER Admin JavaScript
 *
 * @package Zicer_Woo_Sync
 */

/* global jQuery, zicerAdmin */

(function($) {
    'use strict';

    /**
     * Modal dialog system using jQuery UI Dialog
     */
    var ZicerModal = {
        $dialog: null,

        /**
         * Initialize the modal system
         */
        init: function() {
            // Create dialog container if it doesn't exist
            if (!$('#zicer-modal').length) {
                $('body').append(
                    '<div id="zicer-modal" class="zicer-modal-dialog" style="display:none;">' +
                        '<div class="zicer-modal-content"></div>' +
                    '</div>'
                );
            }
            this.$dialog = $('#zicer-modal');
        },

        /**
         * Show an alert modal (replaces window.alert)
         *
         * @param {string} message - The message to display
         * @param {Function} callback - Optional callback after closing
         */
        alert: function(message, callback) {
            var self = this;
            var buttons = {};

            buttons[zicerAdmin.strings.ok] = function() {
                $(this).dialog('close');
                if (typeof callback === 'function') {
                    callback();
                }
            };

            this.$dialog.find('.zicer-modal-content').html(
                '<p>' + self.escapeHtml(message) + '</p>'
            );

            this.$dialog.dialog({
                title: 'ZICER',
                dialogClass: 'zicer-ui-dialog',
                modal: true,
                draggable: false,
                resizable: false,
                closeOnEscape: true,
                width: 400,
                buttons: buttons,
                close: function() {
                    $(this).dialog('destroy');
                }
            });
        },

        /**
         * Show a confirm modal (replaces window.confirm)
         *
         * @param {string} message - The message to display
         * @param {Function} onConfirm - Callback when confirmed
         * @param {Function} onCancel - Optional callback when cancelled
         */
        confirm: function(message, onConfirm, onCancel) {
            var self = this;
            var buttons = {};

            buttons[zicerAdmin.strings.yes] = function() {
                $(this).dialog('close');
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            };

            buttons[zicerAdmin.strings.no] = function() {
                $(this).dialog('close');
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            };

            this.$dialog.find('.zicer-modal-content').html(
                '<p>' + self.escapeHtml(message) + '</p>'
            );

            this.$dialog.dialog({
                title: 'ZICER',
                dialogClass: 'zicer-ui-dialog zicer-ui-dialog-confirm',
                modal: true,
                draggable: false,
                resizable: false,
                closeOnEscape: true,
                width: 450,
                buttons: buttons,
                close: function() {
                    $(this).dialog('destroy');
                    if (typeof onCancel === 'function') {
                        // Only call onCancel if dialog was closed via X or escape
                        // Not if a button was clicked (buttons handle their own callbacks)
                    }
                }
            });
        },

        /**
         * Show a custom modal with HTML content
         *
         * @param {object} options - Dialog options
         */
        custom: function(options) {
            var defaults = {
                title: 'ZICER',
                content: '',
                width: 500,
                buttons: {},
                onClose: null
            };

            var settings = $.extend({}, defaults, options);

            this.$dialog.find('.zicer-modal-content').html(settings.content);

            this.$dialog.dialog({
                title: settings.title,
                dialogClass: 'zicer-ui-dialog',
                modal: true,
                draggable: false,
                resizable: false,
                closeOnEscape: true,
                width: settings.width,
                buttons: settings.buttons,
                close: function() {
                    $(this).dialog('destroy');
                    if (typeof settings.onClose === 'function') {
                        settings.onClose();
                    }
                }
            });
        },

        /**
         * Escape HTML entities
         *
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Validate API token format
     * Token format: zic_ + 32 hex characters = 36 total
     * Example: zic_81f5c4a7840e228b098f77d1e22636dd
     *
     * @param {string} token - The token to validate
     * @return {boolean} True if valid format
     */
    function isValidTokenFormat(token) {
        if (!token || typeof token !== 'string') {
            return false;
        }
        // Token must be exactly: zic_ (4 chars) + 32 hex chars = 36 total
        return /^zic_[a-f0-9]{32}$/.test(token);
    }

    $(document).ready(function() {

        // Initialize modal system
        ZicerModal.init();

        // Token validation - enable/disable Test Connection button
        var $tokenInput = $('#zicer_api_token');
        var $testBtn = $('#zicer-test-connection');

        function updateTestButtonState() {
            var token = $tokenInput.val();
            $testBtn.prop('disabled', !isValidTokenFormat(token));
        }

        if ($tokenInput.length && $testBtn.length) {
            // Initial state
            updateTestButtonState();

            // Update on input
            $tokenInput.on('input', updateTestButtonState);
        }

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
            var $btn = $(this);

            ZicerModal.confirm(zicerAdmin.strings.confirm_disconnect, function() {
                $btn.prop('disabled', true);

                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_disconnect',
                    nonce: zicerAdmin.nonce
                }, function(response) {
                    location.reload();
                }).fail(function() {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                    $btn.prop('disabled', false);
                });
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
                $btn.prop('disabled', false).text(zicerAdmin.strings.test_connection);
                if (response.success) {
                    // Check if different account
                    if (response.data.different_account) {
                        var newToken = response.data.new_token;
                        var buttons = {};

                        buttons[zicerAdmin.strings.clear_data] = function() {
                            $(this).dialog('close');
                            $.post(zicerAdmin.ajaxUrl, {
                                action: 'zicer_confirm_new_account',
                                nonce: zicerAdmin.nonce,
                                token: newToken,
                                clear_data: 'true'
                            }, function() {
                                location.reload();
                            });
                        };
                        buttons[zicerAdmin.strings.keep_data] = function() {
                            $(this).dialog('close');
                            $.post(zicerAdmin.ajaxUrl, {
                                action: 'zicer_confirm_new_account',
                                nonce: zicerAdmin.nonce,
                                token: newToken,
                                clear_data: 'false'
                            }, function() {
                                location.reload();
                            });
                        };

                        ZicerModal.custom({
                            title: 'ZICER - ' + zicerAdmin.strings.different_account_title,
                            content: '<p><strong>' + ZicerModal.escapeHtml(zicerAdmin.strings.different_account_warning) + '</strong></p>' +
                                '<p>' + ZicerModal.escapeHtml(zicerAdmin.strings.previous) + ' <code>' + ZicerModal.escapeHtml(response.data.previous_email || zicerAdmin.strings.unknown) + '</code><br>' +
                                ZicerModal.escapeHtml(zicerAdmin.strings.new) + ' <code>' + ZicerModal.escapeHtml(response.data.user.email || zicerAdmin.strings.unknown) + '</code></p>' +
                                '<p>' + ZicerModal.escapeHtml(zicerAdmin.strings.different_account_msg) + '</p>' +
                                '<p><strong>' + ZicerModal.escapeHtml(zicerAdmin.strings.clear_data) + ':</strong><br>' +
                                '<small>' + ZicerModal.escapeHtml(zicerAdmin.strings.clear_data_desc) + '</small></p>' +
                                '<p><strong>' + ZicerModal.escapeHtml(zicerAdmin.strings.keep_data) + ':</strong><br>' +
                                '<small>' + ZicerModal.escapeHtml(zicerAdmin.strings.keep_data_desc) + '</small></p>',
                            width: 550,
                            buttons: buttons
                        });
                        return;
                    }

                    $('#zicer-connection-status')
                        .removeClass('disconnected')
                        .addClass('connected')
                        .html('&#10003; ' + zicerAdmin.strings.connected);
                    location.reload();
                } else {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + response.data);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text(zicerAdmin.strings.test_connection);
                ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
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
                ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                $btn.prop('disabled', false).text(zicerAdmin.strings.sync_now);
            });
        });

        // Delete listing
        $('.zicer-delete-listing').on('click', function() {
            var $btn = $(this);
            var productId = $btn.data('product-id');

            ZicerModal.confirm(zicerAdmin.strings.confirm_delete, function() {
                $btn.prop('disabled', true);

                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_delete_listing',
                    nonce: zicerAdmin.nonce,
                    product_id: productId
                }, function(response) {
                    location.reload();
                }).fail(function() {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                    $btn.prop('disabled', false);
                });
            });
        });

        // Clear stale data and re-sync (when listing deleted on ZICER)
        $('.zicer-clear-stale').on('click', function() {
            var $btn = $(this);
            var productId = $btn.data('product-id');

            $btn.prop('disabled', true).text(zicerAdmin.strings.clearing);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_clear_stale',
                nonce: zicerAdmin.nonce,
                product_id: productId
            }, function(response) {
                if (response.success) {
                    // Now sync the product
                    $btn.text(zicerAdmin.strings.syncing);
                    $.post(zicerAdmin.ajaxUrl, {
                        action: 'zicer_sync_product',
                        nonce: zicerAdmin.nonce,
                        product_id: productId
                    }, function(syncResponse) {
                        location.reload();
                    }).fail(function() {
                        ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.sync_failed);
                        location.reload();
                    });
                } else {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + response.data);
                    $btn.prop('disabled', false).text(zicerAdmin.strings.clear_recreate);
                }
            }).fail(function() {
                ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                $btn.prop('disabled', false).text(zicerAdmin.strings.clear_recreate);
            });
        });

        // Bulk sync
        $('#zicer-bulk-sync').on('click', function() {
            var $btn = $(this);

            ZicerModal.confirm(zicerAdmin.strings.confirm_bulk, function() {
                $btn.prop('disabled', true).text(zicerAdmin.strings.syncing);

                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_bulk_sync',
                    nonce: zicerAdmin.nonce
                }, function(response) {
                    if (response.success) {
                        ZicerModal.alert(zicerAdmin.strings.added_to_queue.replace('%d', response.data.queued), function() {
                            location.reload();
                        });
                    } else {
                        ZicerModal.alert(zicerAdmin.strings.error + ' ' + response.data);
                        $btn.prop('disabled', false).text(zicerAdmin.strings.sync_all_products);
                    }
                }).fail(function() {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                    $btn.prop('disabled', false).text(zicerAdmin.strings.sync_all_products);
                });
            });
        });

        // Process queue with progress
        var isProcessing = false;
        var initialTotal = 0;

        $('#zicer-process-queue').on('click', function() {
            if (isProcessing) return;

            var $btn = $(this);
            var $spinner = $('#zicer-process-spinner');
            var $progress = $('#zicer-progress-fill');
            var $progressText = $('#zicer-progress-text');

            isProcessing = true;
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            // Get initial total
            initialTotal = parseInt($('#stat-pending').text()) + parseInt($('#stat-processing').text());

            function processNext() {
                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_process_queue',
                    nonce: zicerAdmin.nonce
                }, function(response) {
                    if (response.success) {
                        var stats = response.data;
                        var remaining = stats.pending + stats.processing;
                        var processed = initialTotal - remaining;
                        var percent = initialTotal > 0 ? Math.round((processed / initialTotal) * 100) : 0;

                        // Update stats
                        $('#stat-pending').text(stats.pending);
                        $('#stat-processing').text(stats.processing);
                        $('#stat-completed').text(stats.completed);
                        $('#stat-failed').text(stats.failed);

                        // Update progress bar
                        $progress.css('width', percent + '%');
                        $progressText.text(remaining + ' items remaining');

                        // Continue if more items
                        if (remaining > 0) {
                            setTimeout(processNext, 1000);
                        } else {
                            isProcessing = false;
                            $spinner.removeClass('is-active');
                            $progressText.text(zicerAdmin.strings.complete);
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        isProcessing = false;
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                        ZicerModal.alert(zicerAdmin.strings.error + ' ' + response.data);
                    }
                }).fail(function() {
                    isProcessing = false;
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                });
            }

            processNext();
        });

        // Remove queue item
        $(document).on('click', '.zicer-remove-queue-item', function() {
            var $btn = $(this);
            var $row = $btn.closest('tr');
            var id = $btn.data('id');

            ZicerModal.confirm(zicerAdmin.strings.confirm_remove_item, function() {
                $btn.prop('disabled', true);

                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_remove_queue_item',
                    nonce: zicerAdmin.nonce,
                    id: id
                }, function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        // Update stats
                        $('#stat-pending').text(response.data.pending);
                        $('#stat-processing').text(response.data.processing);
                    } else {
                        ZicerModal.alert(zicerAdmin.strings.error + ' ' + response.data);
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                    $btn.prop('disabled', false);
                });
            });
        });

        // Clear all pending items
        $('#zicer-clear-pending').on('click', function() {
            var $btn = $(this);

            ZicerModal.confirm(zicerAdmin.strings.confirm_clear_pending, function() {
                $btn.prop('disabled', true);

                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_clear_pending',
                    nonce: zicerAdmin.nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        ZicerModal.alert(zicerAdmin.strings.error + ' ' + response.data);
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                    $btn.prop('disabled', false);
                });
            });
        });

        // Refresh rate limit
        $('#zicer-refresh-rate').on('click', function() {
            var $btn = $(this);
            var $icon = $btn.find('.dashicons');

            $btn.prop('disabled', true);
            $icon.addClass('spin');

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_refresh_rate_limit',
                nonce: zicerAdmin.nonce
            }, function(response) {
                $btn.prop('disabled', false);
                $icon.removeClass('spin');

                if (response.success) {
                    var used = response.data.limit - response.data.remaining;
                    $('#stat-rate').text(used + '/' + response.data.limit);
                    $('#stat-rate-updated').html(
                        response.data.updated +
                        ' <button type="button" id="zicer-refresh-rate" class="button-link" title="Refresh">' +
                        '<span class="dashicons dashicons-update"></span></button>'
                    );
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $icon.removeClass('spin');
            });
        });

        // Load categories
        $('#zicer-load-categories').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text(zicerAdmin.strings.loading);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_fetch_categories',
                nonce: zicerAdmin.nonce
            }, function(response) {
                location.reload();
            }).fail(function() {
                ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                $btn.prop('disabled', false).text(zicerAdmin.strings.load_categories);
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
                        $result.addClass('error').text(zicerAdmin.strings.no_match_found);
                    }
                } else {
                    $result.addClass('error').text(zicerAdmin.strings.no_suggestions);
                }
            }).fail(function() {
                $spinner.removeClass('is-active');
                $result.addClass('error').text(zicerAdmin.strings.error);
            });
        });

        // Refresh regions
        $('#zicer-refresh-regions').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text(zicerAdmin.strings.loading);

            $.post(zicerAdmin.ajaxUrl, {
                action: 'zicer_fetch_regions',
                nonce: zicerAdmin.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + response.data);
                    $btn.prop('disabled', false).text(zicerAdmin.strings.refresh);
                }
            }).fail(function() {
                ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                $btn.prop('disabled', false).text(zicerAdmin.strings.refresh);
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
                ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                $btn.prop('disabled', false);
            });
        });

        // Clear failed
        $('#zicer-clear-failed').on('click', function() {
            var $btn = $(this);

            ZicerModal.confirm(zicerAdmin.strings.confirm_clear_failed, function() {
                $btn.prop('disabled', true);

                $.post(zicerAdmin.ajaxUrl, {
                    action: 'zicer_clear_failed',
                    nonce: zicerAdmin.nonce
                }, function(response) {
                    location.reload();
                }).fail(function() {
                    ZicerModal.alert(zicerAdmin.strings.error + ' ' + zicerAdmin.strings.connection_failed);
                    $btn.prop('disabled', false);
                });
            });
        });

    });

})(jQuery);
