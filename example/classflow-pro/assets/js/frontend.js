/**
 * ClassFlow Pro Frontend JavaScript
 */
(function($) {
    'use strict';

    const ClassFlowFrontend = {
        stripe: null,
        elements: null,
        
        init: function() {
            this.bindEvents();
            this.initFilters();
            
            // Initialize Stripe if needed
            if (window.Stripe && classflowProFrontend.stripePublishableKey) {
                this.stripe = Stripe(classflowProFrontend.stripePublishableKey);
            }
        },

        bindEvents: function() {
            // View schedule details
            $(document).on('click', '.classflow-view-schedule', this.viewScheduleDetails.bind(this));
            
            // Book class
            $(document).on('submit', '#classflow-booking-form', this.handleBookingSubmit.bind(this));
            
            // Cancel booking
            $(document).on('click', '.classflow-cancel-booking', this.handleCancelBooking.bind(this));
            
            // Filter changes
            $('.classflow-filter').on('change', this.applyFilters.bind(this));
            
            // Load more
            $(document).on('click', '.classflow-load-more', this.loadMore.bind(this));
        },

        initFilters: function() {
            // Initialize any filter UI components
            const $filters = $('.classflow-calendar-filter select');
            if ($filters.length) {
                $filters.select2({
                    width: '100%',
                    minimumResultsForSearch: 10
                });
            }
        },

        viewScheduleDetails: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const scheduleId = $button.data('schedule-id');
            
            this.showLoading();
            
            $.post(classflowProFrontend.ajaxUrl, {
                action: 'classflow_get_schedule_details',
                schedule_id: scheduleId,
                nonce: classflowProFrontend.nonce
            })
            .done((response) => {
                this.hideLoading();
                
                if (response.success) {
                    this.showScheduleModal(response.data);
                } else {
                    this.showError(response.data.message);
                }
            })
            .fail(() => {
                this.hideLoading();
                this.showError(classflowProFrontend.i18n.error);
            });
        },

        showScheduleModal: function(data) {
            const modalHtml = `
                <div class="classflow-modal-overlay">
                    <div class="classflow-modal">
                        <div class="classflow-modal-header">
                            <h2>${data.class.name}</h2>
                            <button class="classflow-modal-close">&times;</button>
                        </div>
                        <div class="classflow-modal-body">
                            <div class="classflow-schedule-details">
                                <p><strong>Date & Time:</strong> ${data.schedule.formatted_date}</p>
                                <p><strong>Duration:</strong> ${data.class.duration}</p>
                                <p><strong>Instructor:</strong> ${data.instructor.name}</p>
                                <p><strong>Price:</strong> ${data.class.price}</p>
                                <p><strong>Available Spots:</strong> ${data.availability.available_spots} / ${data.availability.total_capacity}</p>
                                
                                ${data.class.description ? `<div class="classflow-description">${data.class.description}</div>` : ''}
                            </div>
                            
                            ${this.getBookingButton(data)}
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Close modal
            $('.classflow-modal-close, .classflow-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('.classflow-modal-overlay').remove();
                }
            });
        },

        getBookingButton: function(data) {
            if (!classflowProFrontend.isLoggedIn) {
                return `<a href="${window.location.href}?login=1" class="classflow-btn classflow-btn-primary">Login to Book</a>`;
            }
            
            if (!data.can_book) {
                return `<div class="classflow-alert classflow-alert-warning">${data.booking_issues.join('<br>')}</div>`;
            }
            
            if (data.availability.is_full) {
                return `<button class="classflow-btn classflow-btn-secondary" disabled>Fully Booked</button>`;
            }
            
            return `<button class="classflow-btn classflow-btn-primary classflow-book-now" data-schedule-id="${data.schedule.id}">Book Now</button>`;
        },

        handleBookingSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text(classflowProFrontend.i18n.loading);
            
            // Get form data
            const formData = new FormData($form[0]);
            formData.append('action', 'classflow_create_booking');
            formData.append('nonce', classflowProFrontend.nonce);
            
            $.ajax({
                url: classflowProFrontend.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((response) => {
                if (response.success) {
                    if (response.data.requires_payment) {
                        this.handlePayment(response.data);
                    } else {
                        this.showSuccess(classflowProFrontend.i18n.bookingSuccess);
                        setTimeout(() => {
                            window.location.href = response.data.redirect_url;
                        }, 2000);
                    }
                } else {
                    this.showError(response.data.message);
                    $submitBtn.prop('disabled', false).text('Book Class');
                }
            })
            .fail(() => {
                this.showError(classflowProFrontend.i18n.error);
                $submitBtn.prop('disabled', false).text('Book Class');
            });
        },

        handlePayment: async function(bookingData) {
            if (!this.stripe) {
                this.showError('Payment system not initialized');
                return;
            }
            
            // Show payment form
            const paymentHtml = `
                <div class="classflow-payment-form">
                    <h3>Complete Payment</h3>
                    <p>Amount: ${bookingData.payment.currency} ${bookingData.payment.amount}</p>
                    <div id="classflow-payment-element"></div>
                    <div class="classflow-payment-message"></div>
                    <button id="classflow-submit-payment" class="classflow-btn classflow-btn-primary classflow-btn-block">
                        Pay Now
                    </button>
                </div>
            `;
            
            $('#classflow-booking-form').html(paymentHtml);
            
            // Create payment element
            const appearance = {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#2271b1',
                }
            };
            
            this.elements = this.stripe.elements({
                appearance,
                clientSecret: bookingData.payment.client_secret
            });
            
            const paymentElement = this.elements.create('payment');
            paymentElement.mount('#classflow-payment-element');
            
            // Handle payment submission
            $('#classflow-submit-payment').on('click', async (e) => {
                e.preventDefault();
                
                const $button = $(e.currentTarget);
                $button.prop('disabled', true).text(classflowProFrontend.i18n.loading);
                
                const {error} = await this.stripe.confirmPayment({
                    elements: this.elements,
                    confirmParams: {
                        return_url: window.location.origin + '/booking/' + bookingData.booking_code,
                    },
                    redirect: 'if_required'
                });
                
                if (error) {
                    this.showPaymentMessage(error.message, 'error');
                    $button.prop('disabled', false).text('Pay Now');
                } else {
                    // Payment succeeded
                    this.confirmPayment(bookingData.payment.payment_intent_id);
                }
            });
        },

        confirmPayment: function(paymentIntentId) {
            $.post(classflowProFrontend.ajaxUrl, {
                action: 'classflow_confirm_payment',
                payment_intent_id: paymentIntentId,
                nonce: classflowProFrontend.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showPaymentMessage(classflowProFrontend.i18n.bookingSuccess, 'success');
                    setTimeout(() => {
                        window.location.href = window.location.origin + '/my-account/bookings/';
                    }, 2000);
                } else {
                    this.showPaymentMessage(response.data.message, 'error');
                }
            })
            .fail(() => {
                this.showPaymentMessage(classflowProFrontend.i18n.error, 'error');
            });
        },

        handleCancelBooking: function(e) {
            e.preventDefault();
            
            if (!confirm(classflowProFrontend.i18n.confirmCancel)) {
                return;
            }
            
            const $button = $(e.currentTarget);
            const bookingId = $button.data('booking-id');
            
            $button.prop('disabled', true).text(classflowProFrontend.i18n.loading);
            
            $.post(classflowProFrontend.ajaxUrl, {
                action: 'classflow_cancel_booking',
                booking_id: bookingId,
                nonce: classflowProFrontend.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showSuccess(response.data.message);
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    this.showError(response.data.message);
                    $button.prop('disabled', false).text('Cancel Booking');
                }
            })
            .fail(() => {
                this.showError(classflowProFrontend.i18n.error);
                $button.prop('disabled', false).text('Cancel Booking');
            });
        },

        applyFilters: function() {
            const filters = {};
            
            $('.classflow-filter').each(function() {
                const $filter = $(this);
                const name = $filter.attr('name');
                const value = $filter.val();
                
                if (value) {
                    filters[name] = value;
                }
            });
            
            // Update URL with filters
            const url = new URL(window.location);
            Object.keys(filters).forEach(key => {
                url.searchParams.set(key, filters[key]);
            });
            
            window.location.href = url.toString();
        },

        loadMore: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const page = parseInt($button.data('page')) || 1;
            const container = $button.data('container');
            
            $button.prop('disabled', true).text(classflowProFrontend.i18n.loading);
            
            $.get($button.attr('href'), {
                ajax: 1
            })
            .done((response) => {
                const $newItems = $(response).find(container).html();
                $(container).append($newItems);
                
                // Update or remove load more button
                const $newButton = $(response).find('.classflow-load-more');
                if ($newButton.length) {
                    $button.replaceWith($newButton);
                } else {
                    $button.remove();
                }
            })
            .fail(() => {
                this.showError(classflowProFrontend.i18n.error);
                $button.prop('disabled', false).text('Load More');
            });
        },

        showLoading: function() {
            const loadingHtml = `
                <div class="classflow-loading-overlay">
                    <div class="classflow-spinner"></div>
                </div>
            `;
            $('body').append(loadingHtml);
        },

        hideLoading: function() {
            $('.classflow-loading-overlay').remove();
        },

        showError: function(message) {
            this.showNotification(message, 'error');
        },

        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },

        showNotification: function(message, type) {
            const notificationHtml = `
                <div class="classflow-notification classflow-notification-${type}">
                    ${message}
                </div>
            `;
            
            $('body').append(notificationHtml);
            
            setTimeout(() => {
                $('.classflow-notification').fadeOut(() => {
                    $('.classflow-notification').remove();
                });
            }, 5000);
        },

        showPaymentMessage: function(message, type) {
            const $message = $('.classflow-payment-message');
            $message.removeClass('error success').addClass(type).text(message).show();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ClassFlowFrontend.init();
    });

})(jQuery);