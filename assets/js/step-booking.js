/* global CFP_DATA, jQuery */
(function ($) {
  function headers($root) { return { 'Content-Type': 'application/json', 'X-WP-Nonce': $root.data('nonce') }; }
  function buildTimes($root, rows) {
    const $times = $root.find('.cfp-times').empty();
    if (!rows || !rows.length) { $times.text('No times found.'); return; }
    rows.forEach(r => {
      const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
      const dt = new Date(r.start_time + 'Z').toLocaleString(undefined, { timeZone: tz });
      const el = $('<span class="cfp-time" data-id="'+r.id+'">'+dt+'</span>');
      el.on('click', function(){ $root.data('schedule_id', r.id); $(this).siblings().removeClass('selected'); $(this).addClass('selected'); });
      $times.append(el);
    });
  }
  async function loadTimes($root) {
    const loc = $root.find('.cfp-loc').val() || '';
    const cls = $root.find('.cfp-class').val() || '';
    const date = ($root.find('.cfp-date').val() || '').toString();
    const params = new URLSearchParams(); if (loc) params.set('location_id', loc); if (cls) params.set('class_id', cls); if (date) { params.set('date_from', date); params.set('date_to', date); }
    const url = CFP_DATA.restUrl + 'schedules?' + params.toString();
    const rows = await (await fetch(url)).json();
    buildTimes($root, rows || []);
  }
  async function createBooking($root) {
    const scheduleId = $root.data('schedule_id');
    const name = ($root.find('.cfp-name').val()||'').toString();
    const email = ($root.find('.cfp-email').val()||'').toString();
    const phone = ($root.find('.cfp-phone').val()||'').toString();
    const password = ($root.find('.cfp-password').val()||'').toString();
    const sms_opt_in = $root.find('.cfp-sms-optin').is(':checked');
    const coupon = ($root.find('.cfp-coupon').val()||'').toString();
    const useCredits = $root.find('.cfp-use-credits').is(':checked');
    const $msg = $root.find('.cfp-msg').empty();
    if (!scheduleId) { $msg.text('Please choose a time.'); return null; }
    const res = await fetch(CFP_DATA.restUrl + 'book', { method:'POST', headers: headers($root), body: JSON.stringify({ schedule_id: scheduleId, name, email, phone, password, sms_opt_in, use_credits: useCredits, coupon_code: coupon })});
    const data = await res.json();
    if (!res.ok) { $msg.text(data.message || 'Booking failed'); return null; }
    $root.data('intakeRequired', !!data.intake_required);
    return data;
  }
  async function setupPayment($root, data) {
    const res = await fetch(CFP_DATA.restUrl + 'payment_intent', { method:'POST', headers: headers($root), body: JSON.stringify({ booking_id: data.booking_id, name: ($root.find('.cfp-name').val()||''), email: ($root.find('.cfp-email').val()||'') }) });
    const pi = await res.json();
    if (!res.ok) { $root.find('.cfp-msg').text(pi.message || 'Payment setup failed'); return null; }
    const stripe = Stripe(CFP_DATA.stripePublishableKey);
    const elements = stripe.elements({ clientSecret: pi.payment_intent_client_secret });
    const card = elements.create('payment');
    card.mount($root.find('.cfp-card-element')[0]);
    $root.data('stripe', stripe); $root.data('elements', elements); $root.data('pi', pi);
    $root.find('.cfp-payment').show();
    $root.find('.cfp-msg').text('Enter payment details to complete.');
    return true;
  }
  async function confirm($root) {
    const stripe = $root.data('stripe'); const elements = $root.data('elements'); const pi = $root.data('pi');
    const $msg = $root.find('.cfp-msg').empty();
    if (!stripe || !elements || !pi) { $msg.text('Payment not initialized.'); return; }
    const res = await stripe.confirmPayment({ clientSecret: pi.payment_intent_client_secret, elements });
    if (res.error) { $msg.text(res.error.message || 'Payment failed'); return; }
    $msg.text('Payment succeeded! Your booking is confirmed.');
    if ($root.data('intakeRequired')) {
      const url = (CFP_DATA.intakePageUrl || '#');
      const ret = encodeURIComponent(window.location.href);
      $msg.append(' Please complete your intake before your first visit. ' + (url && url !== '#' ? '<a class="button" href="'+url+(url.indexOf('?')>=0?'&':'?')+'return='+ret+'">Complete Intake now</a>' : ''));
    }
  }

  $(document).on('click', '.cfp-next-1', function(){ const $r=$(this).closest('.cfp-step-booking'); loadTimes($r); $r.find('.cfp-step').hide(); $r.find('.cfp-step-2').show(); });
  $(document).on('click', '.cfp-prev-2', function(){ const $r=$(this).closest('.cfp-step-booking'); $r.find('.cfp-step').hide(); $r.find('.cfp-step-1').show(); });
  $(document).on('click', '.cfp-next-2', function(){ const $r=$(this).closest('.cfp-step-booking'); if (!$r.data('schedule_id')) { alert('Pick a time'); return; } $r.find('.cfp-step').hide(); $r.find('.cfp-step-3').show(); });
  $(document).on('click', '.cfp-prev-3', function(){ const $r=$(this).closest('.cfp-step-booking'); $r.find('.cfp-step').hide(); $r.find('.cfp-step-2').show(); });
  $(document).on('click', '.cfp-next-3', async function(){ const $r=$(this).closest('.cfp-step-booking'); const data=await createBooking($r); if(!data)return; $r.find('.cfp-review').text('Total: ' + (data.amount_cents/100).toFixed(2) + ' ' + (data.currency||'USD').toUpperCase()); if (data.amount_cents>0) { await setupPayment($r, data); } $r.find('.cfp-step').hide(); $r.find('.cfp-step-4').show(); if (data.amount_cents<=0 && data.intake_required) { const url=(CFP_DATA.intakePageUrl||'#'); const ret=encodeURIComponent(window.location.href); $r.find('.cfp-msg').html('Booked with credits. Please complete your intake before your first visit. ' + (url && url !== '#' ? '<a class="button" href="'+url+(url.indexOf('?')>=0?'&':'?')+'return='+ret+'">Complete Intake now</a>' : '')); } });
  $(document).on('click', '.cfp-next-3', async function(){
    const $r=$(this).closest('.cfp-step-booking');
    const data=await createBooking($r); if(!data)return;
    $r.find('.cfp-review').text('Total: ' + (data.amount_cents/100).toFixed(2) + ' ' + (data.currency||'USD').toUpperCase());
    if (data.amount_cents>0) {
      if (CFP_DATA.useStripeCheckout) {
        try {
          const resp = await fetch(CFP_DATA.restUrl + 'stripe/checkout_session', { method:'POST', headers: headers($r), body: JSON.stringify({ booking_id: data.booking_id }) });
          const js = await resp.json();
          if (!resp.ok) { $r.find('.cfp-msg').text(js.message || 'Failed to start checkout'); return; }
          window.location.href = js.url;
          return;
        } catch(e) { $r.find('.cfp-msg').text('Failed to start checkout'); return; }
      } else {
        await setupPayment($r, data);
      }
    }
    $r.find('.cfp-step').hide(); $r.find('.cfp-step-4').show();
  });
  $(document).on('click', '.cfp-prev-4', function(){ const $r=$(this).closest('.cfp-step-booking'); $r.find('.cfp-step').hide(); $r.find('.cfp-step-3').show(); });
  $(document).on('click', '.cfp-step-booking .cfp-pay', function(){ confirm($(this).closest('.cfp-step-booking')); });
  async function populateFilters($root) {
    try {
      const base = CFP_DATA.restUrl + 'entities/';
      const classes = await (await fetch(base + 'classes?per_page=100')).json();
      const locs = await (await fetch(base + 'locations?per_page=100')).json();
      const $cls = $root.find('.cfp-class'); const $loc = $root.find('.cfp-loc');
      if (Array.isArray(classes)) classes.forEach(c => { $cls.append('<option value="'+c.id+'">'+(c.name || ('#'+c.id))+'</option>'); });
      if (Array.isArray(locs)) locs.forEach(l => { $loc.append('<option value="'+l.id+'">'+(l.name || ('#'+l.id))+'</option>'); });
    } catch(e) {}
  }

  $(function(){ $('.cfp-step-booking').each(function(){ populateFilters($(this)); }); });
})(jQuery);
