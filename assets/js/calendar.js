/* global CFP_DATA, jQuery */
(function ($) {
  function headers($root) { return { 'Content-Type': 'application/json', 'X-WP-Nonce': $root.data('nonce') }; }
  function ym(dt) { return [dt.getFullYear(), dt.getMonth()]; }
  function firstDay(dt) { return new Date(dt.getFullYear(), dt.getMonth(), 1); }
  function lastDay(dt) { return new Date(dt.getFullYear(), dt.getMonth()+1, 0); }
  function fmtDateInput(d) { return d.toISOString().slice(0,10); }
  function formatTimeLocal(iso, tz) { try { return new Date(iso + 'Z').toLocaleTimeString(undefined,{ timeZone: tz, hour:'2-digit', minute:'2-digit'});} catch(e){ return new Date(iso+'Z').toLocaleTimeString(); } }

  async function loadMonth($root, refDate) {
    $root.data('refDate', refDate.toISOString());
    const classId = $root.find('.cfp-filter-class').val() || $root.data('class-id') || '';
    const locId = $root.find('.cfp-filter-location').val() || $root.data('location-id') || '';
    const instrId = $root.find('.cfp-filter-instructor').val() || '';
    const from = fmtDateInput(firstDay(refDate));
    const to = fmtDateInput(lastDay(refDate));
    const params = new URLSearchParams({ date_from: from, date_to: to });
    if (classId) params.set('class_id', classId);
    if (locId) params.set('location_id', locId);
    if (instrId) params.set('instructor_id', instrId);
    const url = CFP_DATA.restUrl + 'schedules?' + params.toString();
    const rows = await (await fetch(url)).json();
    renderCalendar($root, refDate, rows || []);
  }

  function renderCalendar($root, refDate, rows) {
    const view = $root.data('view') || 'month';
    const $title = $root.find('.cfp-cal-title');
    const $grid = $root.find('.cfp-cal-grid').empty().toggle(view==='month' || view==='week');
    const $agenda = $root.find('.cfp-agenda').empty().toggle(view==='agenda');
    if (view === 'agenda') {
      const start = firstDay(refDate); const end = lastDay(refDate);
      $title.text(start.toLocaleString(undefined,{month:'short',day:'numeric'}) + ' – ' + end.toLocaleString(undefined,{month:'short',day:'numeric',year:'numeric'}));
      const byDay = {};
      rows.forEach(r => { const d=(r.start_time||'').slice(0,10); if(!byDay[d]) byDay[d]=[]; byDay[d].push(r); });
      Object.keys(byDay).sort().forEach(d => {
        const day = $('<div class="cfp-agenda-day"></div>');
        day.append('<div><strong>'+ new Date(d+'T00:00:00Z').toLocaleDateString() +'</strong></div>');
        byDay[d].forEach(r => {
          const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
          const t = formatTimeLocal(r.start_time, tz);
          const item = $('<div class="cfp-agenda-item"></div>');
          item.append('<span>'+ t +' — '+ (r.class_title||'') +'</span>');
          const btn = $('<button class="button">Book</button>').on('click', ()=> selectSchedule($root, r));
          item.append(btn); day.append(item);
        });
        $agenda.append(day);
      });
      return;
    }
    if (view === 'week') {
      const startOfWeek = new Date(refDate); const day = startOfWeek.getDay(); const diff = (day+6)%7; startOfWeek.setDate(startOfWeek.getDate()-diff);
      const endOfWeek = new Date(startOfWeek); endOfWeek.setDate(endOfWeek.getDate()+6);
      $title.text(startOfWeek.toLocaleDateString(undefined,{month:'short',day:'numeric'}) + ' – ' + endOfWeek.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'}));
      for (let d=0; d<7; d++) {
        const date = new Date(startOfWeek); date.setDate(startOfWeek.getDate()+d);
        const cell = $('<div class="cfp-cal-cell"></div>');
        cell.append('<div class="cfp-cal-day">'+ date.toLocaleDateString(undefined,{weekday:'short', month:'short', day:'numeric'}) +'</div>');
        const dateStr = date.toISOString().slice(0,10);
        rows.filter(r => (r.start_time||'').slice(0,10) === dateStr).forEach(r => {
          const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
          const t = formatTimeLocal(r.start_time, tz);
          const label = t + (r.instructor_name ? (' — ' + r.instructor_name) : '');
          const ev = $('<div class="cfp-cal-event" title="'+(r.class_title||'')+'">'+label+'</div>').on('click', () => selectSchedule($root, r));
          cell.append(ev);
        });
        $grid.append(cell);
      }
      return;
    }
    // Month view
    $title.text(refDate.toLocaleString(undefined, { month:'long', year:'numeric' }));
    const first = firstDay(refDate);
    const startWeekDay = (first.getDay()+7)%7;
    const daysInMonth = lastDay(refDate).getDate();
    for (let i=0;i<startWeekDay;i++) $grid.append('<div class="cfp-cal-cell cfp-cal-empty"></div>');
    for (let d=1; d<=daysInMonth; d++) {
      const cell = $('<div class="cfp-cal-cell"></div>');
      cell.append('<div class="cfp-cal-day">'+d+'</div>');
      const dateStr = new Date(refDate.getFullYear(), refDate.getMonth(), d).toISOString().slice(0,10);
      rows.filter(r => (r.start_time || '').slice(0,10) === dateStr).forEach(r => {
        const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
        const t = formatTimeLocal(r.start_time, tz);
        const label = t + (r.instructor_name ? (' — ' + r.instructor_name) : '');
        const ev = $('<div class="cfp-cal-event" title="'+(r.class_title||'')+'">'+label+'</div>').on('click', () => selectSchedule($root, r));
        cell.append(ev);
      });
      $grid.append(cell);
    }
  }

  function getStripe() { if (!window.Stripe || !CFP_DATA.stripePublishableKey) return null; if (!window.__cfpStripe) window.__cfpStripe = Stripe(CFP_DATA.stripePublishableKey); return window.__cfpStripe; }

  function selectSchedule($root, r) {
    $root.data('selected-schedule', r.id);
    const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
    const dt = new Date(r.start_time + 'Z').toLocaleString(undefined, { timeZone: tz });
    $root.find('.cfp-cal-selected').html('<div><strong>'+ (r.class_title||'') +'</strong><br><small>'+ dt + (r.location_name?(' — '+r.location_name):'') +'</small></div>');
  }

  async function createBooking($root) {
    const scheduleId = $root.data('selected-schedule');
    const name = ($root.find('.cfp-name').val()||'').toString();
    const email = ($root.find('.cfp-email').val()||'').toString();
    const coupon = ($root.find('.cfp-coupon').val()||'').toString();
    const useCredits = $root.find('.cfp-use-credits').is(':checked');
    const $msg = $root.find('.cfp-msg').empty();
    if (!scheduleId) { $msg.text('Please select a time from the calendar.'); return; }
    const res = await fetch(CFP_DATA.restUrl + 'book', { method:'POST', headers: headers($root), body: JSON.stringify({ schedule_id: scheduleId, name, email, use_credits: useCredits, coupon_code: coupon })});
    const data = await res.json();
    if (!res.ok) { $msg.text(data.message || 'Booking failed'); return; }
    if (data.amount_cents > 0) {
      const piRes = await fetch(CFP_DATA.restUrl + 'payment_intent', { method:'POST', headers: headers($root), body: JSON.stringify({ booking_id: data.booking_id, name, email })});
      const pi = await piRes.json();
      if (!piRes.ok) { $msg.text(pi.message || 'Payment setup failed'); return; }
      const stripe = getStripe(); if (!stripe) { $msg.text('Stripe not configured.'); return; }
      const elements = stripe.elements({ clientSecret: pi.payment_intent_client_secret });
      const card = elements.create('payment');
      card.mount($root.find('.cfp-card-element')[0]);
      $root.find('.cfp-payment').show();
      $root.data('pi', pi); $root.data('stripe', stripe); $root.data('elements', elements);
      $msg.text('Enter payment details to complete.');
    } else {
      $msg.text('Booked using credits. You are confirmed!');
    }
  }

  async function confirmPay($root) {
    const stripe = $root.data('stripe'); const elements = $root.data('elements'); const pi = $root.data('pi');
    const $msg = $root.find('.cfp-msg').empty();
    if (!stripe || !elements || !pi) { $msg.text('Payment not initialized.'); return; }
    const res = await stripe.confirmPayment({ clientSecret: pi.payment_intent_client_secret, elements });
    if (res.error) { $msg.text(res.error.message || 'Payment failed.'); return; }
    $msg.text('Payment succeeded! Your booking is confirmed.');
  }

  $(document).on('click', '.cfp-cal-prev', function(){ const $r=$(this).closest('.cfp-calendar-booking'); const ref=new Date($r.data('refDate')||new Date()); const view=$r.data('view')||'month'; if(view==='week'||view==='agenda') ref.setDate(ref.getDate()-7); else ref.setMonth(ref.getMonth()-1); loadMonth($r, ref); });
  $(document).on('click', '.cfp-cal-next', function(){ const $r=$(this).closest('.cfp-calendar-booking'); const ref=new Date($r.data('refDate')||new Date()); const view=$r.data('view')||'month'; if(view==='week'||view==='agenda') ref.setDate(ref.getDate()+7); else ref.setMonth(ref.getMonth()+1); loadMonth($r, ref); });
  $(document).on('click', '.cfp-calendar-booking .cfp-view', function(){ const $r=$(this).closest('.cfp-calendar-booking'); $r.data('view', $(this).data('view')); loadMonth($r, new Date($r.data('refDate')||new Date())); });
  $(document).on('change', '.cfp-calendar-booking .cfp-filter-class, .cfp-calendar-booking .cfp-filter-location', function(){ const $r=$(this).closest('.cfp-calendar-booking'); loadMonth($r, new Date($r.data('refDate')||new Date())); });
  $(document).on('click', '.cfp-calendar-booking .cfp-book', function(){ createBooking($(this).closest('.cfp-calendar-booking')); });
  $(document).on('click', '.cfp-calendar-booking .cfp-pay', function(){ confirmPay($(this).closest('.cfp-calendar-booking')); });

  async function populateFilters($root) {
    try {
      const base = CFP_DATA.restUrl + 'entities/';
      const classes = await (await fetch(base + 'classes?per_page=100')).json();
      const locs = await (await fetch(base + 'locations?per_page=100')).json();
      const instr = await (await fetch(base + 'instructors?per_page=100')).json();
      const $cls = $root.find('.cfp-filter-class'); const $loc = $root.find('.cfp-filter-location');
      const $inst = $root.find('.cfp-filter-instructor');
      if (Array.isArray(classes)) classes.forEach(c => { $cls.append('<option value="'+c.id+'">'+(c.name || ('#'+c.id))+'</option>'); });
      if (Array.isArray(locs)) locs.forEach(l => { $loc.append('<option value="'+l.id+'">'+(l.name || ('#'+l.id))+'</option>'); });
      if (Array.isArray(instr)) instr.forEach(i => { $inst.append('<option value="'+i.id+'">'+(i.name || ('#'+i.id))+'</option>'); });
      if ($root.data('class-id')) $cls.val(String($root.data('class-id')));
      if ($root.data('location-id')) $loc.val(String($root.data('location-id')));
    } catch(e) {}
  }

  $(function(){ $('.cfp-calendar-booking').each(function(){ const $r=$(this); $r.data('view','month'); populateFilters($r).then(()=>loadMonth($r, new Date())); }); });
})(jQuery);
