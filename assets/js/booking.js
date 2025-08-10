/* global CFP_DATA, jQuery */
(function ($) {
  function currencyFormat(amountCents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format((amountCents || 0) / 100);
    } catch (e) {
      return (amountCents / 100).toFixed(2) + ' ' + (currency || 'USD');
    }
  }

  function getStripe() {
    if (!window.Stripe) return null;
    if (!CFP_DATA.stripePublishableKey) return null;
    if (!window.__cfpStripe) {
      window.__cfpStripe = Stripe(CFP_DATA.stripePublishableKey);
    }
    return window.__cfpStripe;
  }

  function loadSchedules($root) {
    const classId = $root.data('class-id') || '';
    const locationId = $root.data('location-id') || '';
    const from = $root.find('.cfp-date-from').val() || '';
    const to = $root.find('.cfp-date-to').val() || '';
    const params = {};
    if (classId) params.class_id = classId;
    if (from) params.date_from = from;
    if (to) params.date_to = to;
    if (locationId) params.location_id = locationId;
    const url = CFP_DATA.restUrl + 'schedules' + (Object.keys(params).length ? ('?' + new URLSearchParams(params).toString()) : '');
    $.getJSON(url, function (rows) {
      const $list = $root.find('.cfp-schedules').empty();
      if (!rows || !rows.length) {
        $list.append('<p>No schedules found.</p>');
        return;
      }
      rows.forEach(function (r) {
        const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
        const label = new Date(r.start_time + 'Z').toLocaleString(undefined, { timeZone: tz });
        const price = currencyFormat(r.price_cents, r.currency);
        const $item = $('<div class="cfp-schedule-item"></div>');
        $item.append('<div><strong>' + label + '</strong> · ' + price + '</div>');
        const $btn = $('<button class="button">Select</button>');
        $btn.on('click', function () {
          $root.data('selected-schedule', r.id);
          $root.find('.cfp-booking-form').show();
          $root.find('.cfp-payment').hide();
        });
        $item.append($btn);
        $list.append($item);
      });
    });
  }

  function createHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-WP-Nonce': CFP_DATA.nonce
    };
  }

  function mountStripeElement($root, clientSecret) {
    const stripe = getStripe();
    if (!stripe) return null;
    const elements = stripe.elements({ clientSecret });
    const card = elements.create('payment');
    const el = $root.find('.cfp-card-element')[0];
    if (el) card.mount(el);
    return { stripe, elements };
  }

  async function mountPaymentRequest($root, ctx, amountCents, currency, clientSecret) {
    try {
      const pr = ctx.stripe.paymentRequest({
        country: (CFP_DATA.businessCountry || 'US'),
        currency: (currency || 'usd'),
        total: { label: CFP_DATA.siteName || 'Payment', amount: amountCents },
        requestPayerName: true,
        requestPayerEmail: true
      });
      const result = await pr.canMakePayment();
      if (!result) { return; }
      const prButton = ctx.elements.create('paymentRequestButton', { paymentRequest: pr, style: { paymentRequestButton: { theme: 'dark', height: '40px' } } });
      const mountEl = $root.find('.cfp-prb-element')[0];
      if (!mountEl) return;
      prButton.mount(mountEl);
      $root.find('.cfp-prb').show();
      pr.on('paymentmethod', async (ev) => {
        const confirm = await ctx.stripe.confirmCardPayment(clientSecret, { payment_method: ev.paymentMethod.id }, { handleActions: true });
        if (confirm.error) {
          ev.complete('fail');
          $root.find('.cfp-msg').text(confirm.error.message || 'Payment failed.');
          return;
        }
        if (confirm.paymentIntent && confirm.paymentIntent.status === 'requires_action') {
          const next = await ctx.stripe.confirmCardPayment(clientSecret);
          if (next.error) { ev.complete('fail'); $root.find('.cfp-msg').text(next.error.message || 'Payment failed.'); return; }
        }
        ev.complete('success');
        $root.find('.cfp-msg').text('Payment succeeded! Your booking is confirmed.');
      });
    } catch (e) {
      // silently ignore mounting PRB issues
    }
  }

  $(document).on('click', '.cfp-load-schedules', function () {
    const $root = $(this).closest('.cfp-book-class');
    loadSchedules($root);
  });

  $(document).on('click', '.cfp-book', async function () {
    const $root = $(this).closest('.cfp-book-class');
    const scheduleId = $root.data('selected-schedule');
    const name = $root.find('.cfp-name').val();
    const email = $root.find('.cfp-email').val();
    const couponCode = ($root.find('.cfp-coupon').val() || '').toString();
    const useCredits = $root.find('.cfp-use-credits').is(':checked');
    const $msg = $root.find('.cfp-msg').empty();
    if (!scheduleId) { $msg.text('Please select a schedule.'); return; }
    const res = await fetch(CFP_DATA.restUrl + 'book', {
      method: 'POST',
      headers: createHeaders(),
      body: JSON.stringify({ schedule_id: scheduleId, name, email, use_credits: useCredits, coupon_code: couponCode })
    });
    const data = await res.json();
    if (!res.ok) {
      if (data.code === 'cfp_full') {
        $msg.html('Class is full. <button class="button cfp-join-waitlist">Join Waitlist</button>');
        $root.find('.cfp-join-waitlist').off('click').on('click', async function(){
          const wlRes = await fetch(CFP_DATA.restUrl + 'waitlist/join', { method: 'POST', headers: createHeaders(), body: JSON.stringify({ schedule_id: scheduleId, email }) });
          const wld = await wlRes.json();
          if (!wlRes.ok) { $msg.text(wld.message || 'Unable to join waitlist'); return; }
          $msg.text('Added to waitlist. We will notify you when a spot opens.');
        });
        return;
      }
      $msg.text(data.message || 'Booking failed'); return; }
    if (data.amount_cents > 0) {
      const piRes = await fetch(CFP_DATA.restUrl + 'payment_intent', {
        method: 'POST', headers: createHeaders(),
        body: JSON.stringify({ booking_id: data.booking_id, name, email })
      });
      const pi = await piRes.json();
      if (!piRes.ok) { $msg.text(pi.message || 'Payment setup failed'); return; }
      const ctx = mountStripeElement($root, pi.payment_intent_client_secret);
      if (!ctx) { $msg.text('Stripe is not configured.'); return; }
      $root.find('.cfp-payment').show();
      $root.data('pi', pi);
      $root.data('stripeCtx', ctx);
      $msg.text('Please enter payment details to complete booking.');
      await mountPaymentRequest($root, ctx, data.amount_cents, data.currency, pi.payment_intent_client_secret);
    } else {
      $msg.text('Booked using credits. You are confirmed!');
    }
  });

  $(document).on('click', '.cfp-pay', async function () {
    const $root = $(this).closest('.cfp-book-class, .cfp-buy-package, .cfp-book-private');
    const ctx = $root.data('stripeCtx');
    const pi = $root.data('pi');
    const $msg = $root.find('.cfp-msg').empty();
    if (!ctx || !pi) { $msg.text('Payment not initialized.'); return; }
    const { stripe, elements } = ctx;
    const res = await stripe.confirmPayment({ clientSecret: pi.payment_intent_client_secret, elements });
    if (res.error) {
      $msg.text(res.error.message || 'Payment failed.');
      return;
    }
    $msg.text('Payment succeeded! Your booking is confirmed.');
  });

  // Package purchase
  $(document).on('click', '.cfp-pkg-pay', async function () {
    const $root = $(this).closest('.cfp-buy-package');
    const name = ($root.find('.cfp-pkg-name').val() || '').toString();
    const credits = parseInt($root.find('.cfp-pkg-credits').val(), 10) || 1;
    const priceCents = parseInt($root.find('.cfp-pkg-price').val(), 10) || 1000;
    const email = prompt('Enter your email for receipt:');
    const buyer = prompt('Enter your full name:');
    const $msg = $root.find('.cfp-msg').empty();
    const piRes = await fetch(CFP_DATA.restUrl + 'packages/purchase', {
      method: 'POST', headers: createHeaders(),
      body: JSON.stringify({ name, credits, price_cents: priceCents, email, buyer_name: buyer })
    });
    const pi = await piRes.json();
    if (!piRes.ok) { $msg.text(pi.message || 'Payment setup failed'); return; }
    const ctx = mountStripeElement($root, pi.payment_intent_client_secret);
    if (!ctx) { $msg.text('Stripe is not configured.'); return; }
    $root.data('pi', pi);
    $root.data('stripeCtx', ctx);
    $root.find('.cfp-pay').trigger('click');
  });

  // Private session request
  $(document).on('click', '.cfp-request-private', async function () {
    const $root = $(this).closest('.cfp-book-private');
    const instructor_id = parseInt($root.find('.cfp-instructor-id').val(), 10) || null;
    const date = ($root.find('.cfp-date').val() || '').toString();
    const time = ($root.find('.cfp-time').val() || '').toString();
    const notes = ($root.find('.cfp-notes').val() || '').toString();
    const name = ($root.find('.cfp-name').val() || '').toString();
    const email = ($root.find('.cfp-email').val() || '').toString();
    const $msg = $root.find('.cfp-msg').empty();
    const res = await fetch(CFP_DATA.restUrl + 'private/request', {
      method: 'POST', headers: createHeaders(),
      body: JSON.stringify({ instructor_id, date, time, notes, name, email })
    });
    const data = await res.json();
    if (!res.ok) { $msg.text(data.message || 'Unable to submit request.'); return; }
    $msg.text('Request submitted. We will contact you to schedule and collect payment.');
  });

})(jQuery);
