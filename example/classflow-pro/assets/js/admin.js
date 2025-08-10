/**
 * ClassFlow Pro Admin JavaScript
 */
(function($) {
    'use strict';

    const ClassFlowAdmin = {
        init: function() {
            this.bindEvents();
            this.initDatePickers();
            this.initColorPickers();
            this.initSelect2();
        },

        bindEvents: function() {
            // Delete confirmations
            $(document).on('click', '.classflow-delete-link', this.handleDelete);
            
            // Cancel booking
            $(document).on('click', '.classflow-cancel-booking', this.handleCancelBooking);
            
            // Schedule availability check
            $(document).on('change', '#schedule_id', this.checkScheduleAvailability);
            
            // Bulk actions
            $('#doaction, #doaction2').on('click', this.handleBulkAction);
            
            // Settings tabs
            $('.nav-tab-wrapper .nav-tab').on('click', this.handleSettingsTab);
            
            // Media uploader
            $('.classflow-upload-button').on('click', this.handleMediaUpload);
            $('.classflow-remove-button').on('click', this.handleMediaRemove);
            
            // Settings actions
            $('#export-settings').on('click', this.handleExportSettings);
            $('#import-settings').on('click', this.handleImportSettings);
            $('#reset-settings').on('click', this.handleResetSettings);
            
            // Toggle settings visibility
            $('.classflow-toggle-trigger').on('change', this.handleToggleSettings);
            
            // Handle success message
            this.handleSuccessMessage();
        },

        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('.classflow-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    minDate: 0
                });
                
                $('.classflow-timepicker').timepicker({
                    timeFormat: 'HH:mm',
                    interval: 15,
                    minTime: '00:00',
                    maxTime: '23:45',
                    defaultTime: '09:00',
                    startTime: '00:00',
                    dynamic: false,
                    dropdown: true,
                    scrollbar: true
                });
            }
        },

        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.classflow-color-picker').wpColorPicker();
            }
        },

        initSelect2: function() {
            if ($.fn.select2) {
                // Student selector
                $('.classflow-student-select').select2({
                    ajax: {
                        url: classflowPro.ajaxUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'classflow_pro_search_students',
                                q: params.term,
                                nonce: classflowPro.nonce
                            };
                        },
                        processResults: function(data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: 'Search for a student...'
                });
                
                // Instructor selector
                $('.classflow-instructor-select').select2({
                    ajax: {
                        url: classflowPro.ajaxUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'classflow_pro_search_instructors',
                                q: params.term,
                                nonce: classflowPro.nonce
                            };
                        },
                        processResults: function(data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: 'Search for an instructor...'
                });
            }
        },

        handleDelete: function(e) {
            e.preventDefault();
            
            if (!confirm(classflowPro.i18n.confirm_delete)) {
                return;
            }
            
            const $link = $(this);
            const href = $link.attr('href');
            
            window.location.href = href;
        },

        handleCancelBooking: function(e) {
            e.preventDefault();
            
            if (!confirm(classflowPro.i18n.confirm_cancel)) {
                return;
            }
            
            const $button = $(this);
            const bookingId = $button.data('booking-id');
            
            $button.prop('disabled', true).text(classflowPro.i18n.loading);
            
            $.post(classflowPro.ajaxUrl, {
                action: 'classflow_pro_cancel_booking',
                booking_id: bookingId,
                nonce: classflowPro.nonce
            })
            .done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || classflowPro.i18n.error);
                    $button.prop('disabled', false).text('Cancel Booking');
                }
            })
            .fail(function() {
                alert(classflowPro.i18n.error);
                $button.prop('disabled', false).text('Cancel Booking');
            });
        },

        checkScheduleAvailability: function() {
            const scheduleId = $(this).val();
            const $availability = $('#schedule-availability');
            
            if (!scheduleId) {
                $availability.empty();
                return;
            }
            
            $availability.html('<span class="spinner is-active"></span>');
            
            $.get(classflowPro.ajaxUrl, {
                action: 'classflow_pro_get_schedule_availability',
                schedule_id: scheduleId,
                nonce: classflowPro.nonce
            })
            .done(function(response) {
                if (response.success) {
                    const spots = response.data.available_spots;
                    const html = spots > 0 
                        ? `<span style="color: green;">${spots} spots available</span>`
                        : '<span style="color: red;">Fully booked</span>';
                    $availability.html(html);
                } else {
                    $availability.html('<span style="color: red;">Error checking availability</span>');
                }
            })
            .fail(function() {
                $availability.html('<span style="color: red;">Error checking availability</span>');
            });
        },

        handleBulkAction: function(e) {
            const $form = $(this).closest('form');
            const action = $form.find('select[name="action"]').val();
            
            if (action === '-1') {
                return;
            }
            
            const checked = $form.find('input[name="item[]"]:checked').length;
            
            if (checked === 0) {
                alert('Please select at least one item.');
                e.preventDefault();
                return;
            }
            
            if (action === 'delete' && !confirm(classflowPro.i18n.confirm_delete)) {
                e.preventDefault();
            }
        },

        handleSettingsTab: function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const target = $tab.attr('href');
            
            // Update active tab
            $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide content
            $('.classflow-settings-section').hide();
            $(target).show();
            
            // Update URL
            if (history.pushState) {
                const url = new URL(window.location);
                url.searchParams.set('tab', target.substring(1));
                history.pushState({}, '', url);
            }
        },

        handleMediaUpload: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const targetSelector = $button.data('target');
            const previewSelector = $button.data('preview');
            const $input = $(targetSelector);
            const $preview = $(previewSelector);
            const $removeButton = $button.siblings('.classflow-remove-button');
            
            // Create media frame
            const frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            
            // When image is selected
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.id);
                
                if ($preview.length) {
                    const imageUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    $preview.html(`<img src="${imageUrl}" style="max-width: 150px; height: auto;">`);
                }
                
                $removeButton.show();
            });
            
            // Open media frame
            frame.open();
        },

        handleMediaRemove: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const targetSelector = $button.data('target');
            const previewSelector = $button.data('preview');
            const $input = $(targetSelector);
            const $preview = $(previewSelector);
            
            $input.val('');
            $preview.empty();
            $button.hide();
        },

        handleExportSettings: function(e) {
            e.preventDefault();
            
            $.post(classflowPro.ajaxUrl, {
                action: 'classflow_pro_export_settings',
                nonce: classflowPro.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Create download link
                    const blob = new Blob([response.data.data], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert(response.data.message || classflowPro.i18n.error);
                }
            })
            .fail(function() {
                alert(classflowPro.i18n.error);
            });
        },

        handleImportSettings: function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('import-settings-file');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file to import.');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $.post(classflowPro.ajaxUrl, {
                    action: 'classflow_pro_import_settings',
                    import_data: e.target.result,
                    nonce: classflowPro.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || classflowPro.i18n.error);
                    }
                })
                .fail(function() {
                    alert(classflowPro.i18n.error);
                });
            };
            reader.readAsText(file);
        },

        handleResetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.')) {
                return;
            }
            
            $.post(classflowPro.ajaxUrl, {
                action: 'classflow_pro_reset_settings',
                confirm: 'yes',
                nonce: classflowPro.nonce
            })
            .done(function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || classflowPro.i18n.error);
                }
            })
            .fail(function() {
                alert(classflowPro.i18n.error);
            });
        },

        handleSuccessMessage: function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('message') === 'settings-saved') {
                const $wrap = $('.wrap');
                $wrap.prepend('<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>');
                
                // Remove message parameter from URL
                const url = new URL(window.location);
                url.searchParams.delete('message');
                history.replaceState({}, '', url);
            }
        },

        handleToggleSettings: function(e) {
            const $checkbox = $(this);
            const targetSelector = $checkbox.data('toggle-target');
            const $targets = $(targetSelector);
            
            if ($checkbox.is(':checked')) {
                $targets.fadeIn(200);
            } else {
                $targets.fadeOut(200);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ClassFlowAdmin.init();
    });

})(jQuery);