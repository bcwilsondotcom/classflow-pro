/* global CFP_ADMIN, jQuery */
(function ($) {
  function headers() { return { 'Content-Type': 'application/json', 'X-WP-Nonce': CFP_ADMIN.nonce }; }
  function currency(cents) { return (cents/100).toFixed(2) + ' ' + (CFP_ADMIN.currency || 'USD').toUpperCase(); }

  async function createBooking() {
    const scheduleId = parseInt($('.cfp-schedule-select').val(), 10) || 0;
    const email = ($('.cfp-email').val() || '').toString();
    const name = ($('.cfp-name').val() || '').toString();
    const useCredits = $('.cfp-use-credits').is(':checked');
    const $msg = $('.cfp-msg').empty();
    if (!scheduleId) { $msg.text('Please select a schedule.'); return; }
    const res = await fetch(CFP_ADMIN.restUrl + 'book', { method: 'POST', headers: headers(), body: JSON.stringify({ schedule_id: scheduleId, email, name, use_credits: useCredits }) });
    const data = await res.json();
    if (!res.ok) { $msg.text(data.message || 'Failed to create booking.'); return; }
    if (data.amount_cents > 0) {
      const piRes = await fetch(CFP_ADMIN.restUrl + 'payment_intent', { method: 'POST', headers: headers(), body: JSON.stringify({ booking_id: data.booking_id, name, email }) });
      const pi = await piRes.json();
      if (!piRes.ok) { $msg.text(pi.message || 'Failed to prepare payment.'); return; }
      await setupStripe(pi.payment_intent_client_secret);
      $('.cfp-admin-payment').show().data('pi', pi);
      $msg.text('Booking created. Collect payment to confirm (' + currency(data.amount_cents) + ').');
    } else {
      $msg.text('Booking created and confirmed using credits.');
    }
  }

  async function setupStripe(clientSecret) {
    if (!window.Stripe || !CFP_ADMIN.stripePublishableKey) { $('.cfp-msg').text('Stripe not configured.'); return; }
    const stripe = Stripe(CFP_ADMIN.stripePublishableKey);
    const elements = stripe.elements({ clientSecret });
    const pe = elements.create('payment');
    pe.mount($('.cfp-card-element')[0]);
    $('.cfp-admin-pay-btn').off('click').on('click', async function () {
      const res = await stripe.confirmPayment({ clientSecret, elements });
      if (res.error) { $('.cfp-msg').text(res.error.message || 'Payment failed'); return; }
      $('.cfp-msg').text('Payment succeeded and booking confirmed.');
      $('.cfp-admin-payment').hide();
    });
  }

  $(document).on('click', '.cfp-create-booking-btn', function (e) {
    e.preventDefault();
    createBooking();
  });

  // Refund functionality
  $(document).on('click', '.cfp-refund-btn', function(e) {
    e.preventDefault();
    const bookingId = $(this).data('booking-id');
    const amount = $(this).data('amount');
    $('#cfp-refund-booking-id').text('#' + bookingId);
    $('#cfp-refund-original-amount').text(amount);
    $('#cfp-refund-modal').show();
    $('#cfp-refund-modal').data('booking-id', bookingId);
    $('#cfp-refund-modal').data('original-amount', amount);
  });

  $('#cfp-refund-type').on('change', function() {
    if ($(this).val() === 'partial') {
      $('#cfp-partial-amount-wrap').show();
    } else {
      $('#cfp-partial-amount-wrap').hide();
    }
  });

  $('#cfp-cancel-refund, .cfp-modal-overlay').on('click', function() {
    $('#cfp-refund-modal').hide();
    $('#cfp-refund-msg').empty();
  });

  $('#cfp-process-refund').on('click', async function() {
    const bookingId = $('#cfp-refund-modal').data('booking-id');
    const refundType = $('#cfp-refund-type').val();
    const reason = $('#cfp-refund-reason').val();
    let refundAmount = null;
    
    if (refundType === 'partial') {
      refundAmount = parseFloat($('#cfp-refund-amount').val()) * 100; // Convert to cents
      if (!refundAmount || refundAmount <= 0) {
        $('#cfp-refund-msg').text('Please enter a valid refund amount.');
        return;
      }
    }

    $('#cfp-refund-msg').text('Processing refund...');
    
    try {
      const res = await fetch(CFP_ADMIN.restUrl + 'admin/refund', {
        method: 'POST',
        headers: headers(),
        body: JSON.stringify({
          booking_id: bookingId,
          refund_type: refundType,
          refund_amount: refundAmount,
          reason: reason
        })
      });
      
      const data = await res.json();
      
      if (!res.ok) {
        $('#cfp-refund-msg').text(data.message || 'Failed to process refund.');
        return;
      }
      
      $('#cfp-refund-msg').text('Refund processed successfully. Refreshing...');
      setTimeout(() => location.reload(), 2000);
    } catch (error) {
      $('#cfp-refund-msg').text('Error processing refund: ' + error.message);
    }
  });

  // Cancel functionality
  $(document).on('click', '.cfp-cancel-btn', async function(e) {
    e.preventDefault();
    if (!confirm('Are you sure you want to cancel this booking?')) return;
    
    const bookingId = $(this).data('booking-id');
    const $btn = $(this);
    $btn.prop('disabled', true).text('Cancelling...');
    
    try {
      const res = await fetch(CFP_ADMIN.restUrl + 'admin/cancel', {
        method: 'POST',
        headers: headers(),
        body: JSON.stringify({ booking_id: bookingId })
      });
      
      const data = await res.json();
      
      if (!res.ok) {
        alert(data.message || 'Failed to cancel booking.');
        $btn.prop('disabled', false).text('Cancel');
        return;
      }
      
      location.reload();
    } catch (error) {
      alert('Error cancelling booking: ' + error.message);
      $btn.prop('disabled', false).text('Cancel');
    }
  });
})(jQuery);

