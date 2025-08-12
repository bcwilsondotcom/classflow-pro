/* global CFP_DATA, jQuery */
(function ($) {
  function headers($root) { return { 'Content-Type': 'application/json', 'X-WP-Nonce': $root.data('nonce') }; }
  function ym(dt) { return [dt.getFullYear(), dt.getMonth()]; }
  function firstDay(dt) { return new Date(dt.getFullYear(), dt.getMonth(), 1); }
  function lastDay(dt) { return new Date(dt.getFullYear(), dt.getMonth()+1, 0); }
  function fmtDateInput(d) { return d.toISOString().slice(0,10); }
  function formatTimeLocal(iso, tz) { try { return new Date(iso + 'Z').toLocaleTimeString(undefined,{ timeZone: tz, hour:'2-digit', minute:'2-digit'});} catch(e){ return new Date(iso+'Z').toLocaleTimeString(); } }

  function withParams(url, params){
    const qs = (params instanceof URLSearchParams) ? params.toString() : new URLSearchParams(params).toString();
    return url + (url.indexOf('?')>=0 ? '&' : '?') + qs;
  }

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
    const url = withParams(CFP_DATA.restUrl + 'schedules', params);
    try {
      const response = await fetch(url);
      if (!response.ok) {
        console.error('HTTP error loading schedules:', response.status, response.statusText);
        renderCalendar($root, refDate, []);
        return;
      }
      const text = await response.text();
      if (!text) {
        console.error('Empty response for schedules');
        renderCalendar($root, refDate, []);
        return;
      }
      // Clean any PHP warnings from response
      const jsonStart = text.indexOf('[');
      const cleanJson = jsonStart >= 0 ? text.substring(jsonStart) : text;
      const rows = JSON.parse(cleanJson);
      // Ensure rows is an array
      const rowsArray = Array.isArray(rows) ? rows : [];
      renderCalendar($root, refDate, rowsArray);
    } catch(e) {
      console.error('Failed to load schedules:', e);
      renderCalendar($root, refDate, []);
    }
  }

  function renderCalendar($root, refDate, rows) {
    const view = $root.data('view') || 'month';
    const $title = $root.find('.cfp-cal-title');
    const isFullCalendar = $root.hasClass('cfp-full-calendar');
    const $gridContainer = isFullCalendar ? $root.find('.cfp-full-cal-main') : $root.find('.cfp-cal-main');
    const $grid = $gridContainer.find('.cfp-cal-grid').removeClass('cfp-loading').empty().toggle(view==='month' || view==='week');
    const $agenda = $gridContainer.find('.cfp-agenda').empty().toggle(view==='agenda');
    
    // Update active view button
    $root.find('.cfp-view').removeClass('active');
    $root.find('.cfp-view[data-view="'+view+'"]').addClass('active');
    
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
          const btn = $('<button>Book</button>').on('click', ()=> selectSchedule($root, r));
          item.append(btn); day.append(item);
        });
        $agenda.append(day);
      });
      if (Object.keys(byDay).length === 0) {
        $agenda.append('<div style="text-align:center;padding:40px;color:#9ca3af;">No classes scheduled for this period</div>');
      }
      return;
    }
    if (view === 'week') {
      const startOfWeek = new Date(refDate); 
      const day = startOfWeek.getDay(); 
      const diff = day === 0 ? 6 : day - 1; // Start on Monday
      startOfWeek.setDate(startOfWeek.getDate()-diff);
      const endOfWeek = new Date(startOfWeek); 
      endOfWeek.setDate(endOfWeek.getDate()+6);
      $title.text(startOfWeek.toLocaleDateString(undefined,{month:'short',day:'numeric'}) + ' – ' + endOfWeek.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'}));
      
      // Add weekday headers
      const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
      weekdays.forEach(w => $grid.append('<div class="cfp-cal-weekday">'+w.substr(0,3)+'</div>'));
      
      for (let d=0; d<7; d++) {
        const date = new Date(startOfWeek); 
        date.setDate(startOfWeek.getDate()+d);
        const isToday = date.toDateString() === new Date().toDateString();
        const cell = $('<div class="cfp-cal-cell'+(isToday?' cfp-cal-today':'')+'"></div>');
        cell.append('<div class="cfp-cal-day">'+ date.getDate() +'</div>');
        const dateStr = date.toISOString().slice(0,10);
        const dayEvents = rows.filter(r => (r.start_time||'').slice(0,10) === dateStr);
        dayEvents.forEach(r => {
          const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
          const t = formatTimeLocal(r.start_time, tz);
          const label = t + ' • ' + (r.class_title||'');
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
    const startWeekDay = first.getDay() === 0 ? 6 : first.getDay() - 1; // Adjust for Monday start
    const daysInMonth = lastDay(refDate).getDate();
    const today = new Date();
    
    // Add weekday headers
    const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    weekdays.forEach(w => $grid.append('<div class="cfp-cal-weekday">'+w+'</div>'));
    
    // Add empty cells for days before month starts
    for (let i=0;i<startWeekDay;i++) $grid.append('<div class="cfp-cal-cell cfp-cal-empty"></div>');
    
    // Add days of the month
    for (let d=1; d<=daysInMonth; d++) {
      const currentDate = new Date(refDate.getFullYear(), refDate.getMonth(), d);
      const isToday = currentDate.toDateString() === today.toDateString();
      const cell = $('<div class="cfp-cal-cell'+(isToday?' cfp-cal-today':'')+'"></div>');
      cell.append('<div class="cfp-cal-day">'+d+'</div>');
      const dateStr = currentDate.toISOString().slice(0,10);
      const dayEvents = rows.filter(r => (r.start_time || '').slice(0,10) === dateStr);
      
      // Show up to 3 events, then a count
      dayEvents.slice(0, 3).forEach(r => {
        const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
        const t = formatTimeLocal(r.start_time, tz);
        const ev = $('<div class="cfp-cal-event" title="'+(r.class_title||'')+' at '+t+'">'+t+' '+( r.class_title||'')+'</div>').on('click', () => selectSchedule($root, r));
        cell.append(ev);
      });
      
      if (dayEvents.length > 3) {
        cell.append('<div style="font-size:11px;color:#6b7280;padding:2px 6px;">+'+(dayEvents.length-3)+' more</div>');
      }
      
      $grid.append(cell);
    }
    
    // Fill remaining cells to complete the grid
    const totalCells = startWeekDay + daysInMonth;
    const remainingCells = (7 - (totalCells % 7)) % 7;
    for (let i=0; i<remainingCells; i++) {
      $grid.append('<div class="cfp-cal-cell cfp-cal-empty"></div>');
    }
  }

  function getStripe() { if (!window.Stripe || !CFP_DATA.stripePublishableKey) return null; if (!window.__cfpStripe) window.__cfpStripe = Stripe(CFP_DATA.stripePublishableKey); return window.__cfpStripe; }

  function selectSchedule($root, r) {
    $root.data('selected-schedule', r.id);
    const tz = r.tz || CFP_DATA.businessTimezone || 'UTC';
    const dt = new Date(r.start_time + 'Z').toLocaleString(undefined, { timeZone: tz });
    const cur = (r.currency||'usd').toUpperCase();
    const price = (typeof r.price_cents !== 'undefined' && r.price_cents !== null) ? (Number(r.price_cents)/100).toFixed(2) + ' ' + cur : '';
    const priceLine = price ? ('<div><small>Price: '+ price +'</small></div>') : '';
    $root.find('.cfp-cal-selected').html('<div><strong>'+ (r.class_title||'') +'</strong><br><small>'+ dt + (r.location_name?(' — '+r.location_name):'') +'</small>'+priceLine+'</div>');
  }

  async function createBooking($root) {
    const scheduleId = $root.data('selected-schedule');
    const name = ($root.find('.cfp-name').val()||'').toString();
    const email = ($root.find('.cfp-email').val()||'').toString();
    const phone = ($root.find('.cfp-phone').val()||'').toString();
    const password = ($root.find('.cfp-password').val()||'').toString();
    const sms_opt_in = $root.find('.cfp-sms-optin').is(':checked');
    const useCredits = (CFP_DATA && CFP_DATA.isLoggedIn && CFP_DATA.userCredits > 0) ? $root.find('.cfp-use-credits').is(':checked') : false;
    const $msg = $root.find('.cfp-msg').empty();
    if (!scheduleId) { $msg.text('Please select a time from the calendar.'); return; }
    const body = { schedule_id: scheduleId, name, email, phone, password, sms_opt_in, use_credits: useCredits };
    const res = await fetch(CFP_DATA.restUrl + 'book', { method:'POST', headers: headers($root), body: JSON.stringify(body)});
    const data = await res.json();
    if (!res.ok) { $msg.text(data.message || 'Booking failed'); return; }
    if (data.amount_cents > 0) {
      // Check if we should use Stripe Checkout or Payment Intent
      if (CFP_DATA && CFP_DATA.useStripeCheckout) {
        // Use Stripe Checkout Session
        const sessionRes = await fetch(CFP_DATA.restUrl + 'stripe/checkout_session', { 
          method:'POST', 
          headers: headers($root), 
          body: JSON.stringify({ booking_id: data.booking_id })
        });
        const session = await sessionRes.json();
        if (!sessionRes.ok) { 
          $msg.text(session.message || 'Failed to create checkout session'); 
          return; 
        }
        // Redirect to Stripe Checkout
        if (session.url) {
          window.location.href = session.url;
        } else {
          $msg.text('Failed to get checkout URL');
        }
      } else {
        // Use Payment Intent (embedded form)
        const piRes = await fetch(CFP_DATA.restUrl + 'payment_intent', { 
          method:'POST', 
          headers: headers($root), 
          body: JSON.stringify({ booking_id: data.booking_id, name, email })
        });
        const pi = await piRes.json();
        if (!piRes.ok) { 
          $msg.text(pi.message || 'Payment setup failed'); 
          return; 
        }
        const stripe = getStripe(); 
        if (!stripe) { 
          $msg.text('Stripe is not properly configured. Please check your Stripe settings.'); 
          return; 
        }
        const elements = stripe.elements({ clientSecret: pi.payment_intent_client_secret });
        const card = elements.create('payment');
        card.mount($root.find('.cfp-card-element')[0]);
        $root.find('.cfp-payment').show();
        $root.data('pi', pi); 
        $root.data('stripe', stripe); 
        $root.data('elements', elements);
        $msg.text('Enter payment details to complete.');
      }
    } else {
      $msg.text('Booking confirmed successfully!');
      // Optionally redirect to a success page or reload
      setTimeout(() => window.location.reload(), 2000);
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

  // Enhanced auth modal with Quick Booking field integration
  function openAuthModal(onSuccess, prefillData){
    const $m = $('<div class="cfp-auth-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999999;display:flex;align-items:center;justify-content:center;"></div>');
    const html = '<div style="background:#fff;border-radius:8px;max-width:480px;width:95%;padding:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);">'
      + '<h3 style="margin-top:0;margin-bottom:16px;">Login or Register to Book</h3>'
      + '<div class="cfp-auth-tabs" style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px;">'
      + '<button class="button button-primary cfp-auth-tab" data-tab="login" style="flex:1;">Login</button>'
      + '<button class="button cfp-auth-tab" data-tab="register" style="flex:1;">Register</button>'
      + '</div>'
      + '<div class="cfp-auth-view cfp-auth-login">'
      + '<label style="display:block;margin-bottom:12px;">Email<br><input type="email" class="cfp-auth-login-email" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;margin-top:4px;" placeholder="your@email.com"></label>'
      + '<label style="display:block;margin-bottom:12px;">Password<br><input type="password" class="cfp-auth-login-pass" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;margin-top:4px;" placeholder="Enter your password"></label>'
      + '<button class="button button-primary cfp-auth-login-btn" style="width:100%;padding:10px;margin-top:8px;">Login</button>'
      + '</div>'
      + '<div class="cfp-auth-view cfp-auth-register" style="display:none;">'
      + '<label style="display:block;margin-bottom:12px;">Name<br><input type="text" class="cfp-auth-reg-name" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;margin-top:4px;" placeholder="Your full name"></label>'
      + '<label style="display:block;margin-bottom:12px;">Email<br><input type="email" class="cfp-auth-reg-email" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;margin-top:4px;" placeholder="your@email.com"></label>'
      + '<label style="display:block;margin-bottom:12px;">Phone (optional)<br><input type="tel" class="cfp-auth-reg-phone" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;margin-top:4px;" placeholder="(555) 123-4567"></label>'
      + '<label style="display:block;margin-bottom:12px;">Password<br><input type="password" class="cfp-auth-reg-pass" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;margin-top:4px;" placeholder="Create a password"></label>'
      + '<label style="display:flex;align-items:center;margin-bottom:12px;font-weight:normal;"><input type="checkbox" class="cfp-auth-reg-sms" style="margin-right:8px;"><span>Send me text messages about my bookings (optional)</span></label>'
      + '<button class="button button-primary cfp-auth-register-btn" style="width:100%;padding:10px;margin-top:8px;">Create Account & Continue</button>'
      + '</div>'
      + '<div class="cfp-auth-msg" style="margin-top:12px;padding:8px;border-radius:4px;display:none;"></div>'
      + '<div style="margin-top:16px;text-align:center;"><button class="button cfp-auth-close" style="text-decoration:underline;background:none;border:none;color:#6b7280;cursor:pointer;">Cancel</button></div>'
      + '</div>';
    $m.html(html);
    
    // Prefill data from Quick Booking if available
    if (prefillData) {
      if (prefillData.name) $m.find('.cfp-auth-reg-name').val(prefillData.name);
      if (prefillData.email) {
        $m.find('.cfp-auth-reg-email, .cfp-auth-login-email').val(prefillData.email);
      }
      if (prefillData.phone) $m.find('.cfp-auth-reg-phone').val(prefillData.phone);
      if (prefillData.smsOptin !== undefined) $m.find('.cfp-auth-reg-sms').prop('checked', prefillData.smsOptin);
    }
    
    $('body').append($m);
    $m.on('click','.cfp-auth-close', ()=> $m.remove());
    $m.on('click','.cfp-auth-tab', function(){ 
      const tab=$(this).data('tab'); 
      $m.find('.cfp-auth-tab').removeClass('button-primary'); 
      $(this).addClass('button-primary'); 
      $m.find('.cfp-auth-view').hide(); 
      $m.find('.cfp-auth-'+tab).show(); 
      $m.find('.cfp-auth-msg').hide();
    });
    
    function showMessage(msg, isError) {
      const $msg = $m.find('.cfp-auth-msg');
      $msg.text(msg)
        .css({
          'background-color': isError ? '#fee' : '#efe',
          'color': isError ? '#dc2626' : '#059669',
          'border': '1px solid ' + (isError ? '#fca5a5' : '#86efac')
        })
        .show();
    }
    
    async function doLogin(){
      const email=$m.find('.cfp-auth-login-email').val(); 
      const pass=$m.find('.cfp-auth-login-pass').val();
      if (!email || !pass) {
        showMessage('Please enter both email and password', true);
        return;
      }
      showMessage('Logging in...', false);
      // Get nonce from the calendar container or CFP_DATA
      const nonce = $('.cfp-calendar-booking').first().data('nonce') || CFP_DATA.nonce || '';
      const res=await fetch(CFP_DATA.restUrl+'login', { 
        method:'POST', 
        headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}, 
        body: JSON.stringify({ user_login: email, password: pass, remember: true }) 
      });
      const js=await res.json(); 
      if(!res.ok){ 
        showMessage(js.message||'Login failed. Please check your credentials.', true); 
        return; 
      }
      showMessage('Login successful! Redirecting...', false);
      setTimeout(() => {
        $m.remove(); 
        if (typeof onSuccess==='function') onSuccess(); 
        else window.location.reload();
      }, 500);
    }
    
    async function doRegister(){
      const name=$m.find('.cfp-auth-reg-name').val(); 
      const email=$m.find('.cfp-auth-reg-email').val(); 
      const pass=$m.find('.cfp-auth-reg-pass').val();
      const phone=$m.find('.cfp-auth-reg-phone').val();
      const smsOptin=$m.find('.cfp-auth-reg-sms').is(':checked');
      
      if (!name || !email || !pass) {
        showMessage('Please fill in all required fields', true);
        return;
      }
      
      showMessage('Creating your account...', false);
      // Get nonce from the calendar container or CFP_DATA
      const nonce = $('.cfp-calendar-booking').first().data('nonce') || CFP_DATA.nonce || '';
      const res=await fetch(CFP_DATA.restUrl+'register', { 
        method:'POST', 
        headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}, 
        body: JSON.stringify({ name, email, password: pass }) 
      });
      const js=await res.json(); 
      if(!res.ok){ 
        showMessage(js.message||'Registration failed. This email may already be registered.', true); 
        return; 
      }
      
      // If registration successful and we have phone/SMS preference, update user meta
      if (phone || smsOptin) {
        // These will be saved when the booking is created
        if (typeof onSuccess === 'function') {
          onSuccess({phone, smsOptin});
        }
      }
      
      showMessage('Account created successfully! Redirecting...', false);
      setTimeout(() => {
        $m.remove(); 
        if (typeof onSuccess==='function' && !phone && !smsOptin) onSuccess(); 
        else window.location.reload();
      }, 500);
    }
    
    $m.on('click','.cfp-auth-login-btn', doLogin);
    $m.on('click','.cfp-auth-register-btn', doRegister);
    
    // Allow Enter key to submit
    $m.find('input').on('keypress', function(e) {
      if (e.which === 13) {
        const isLogin = $(this).closest('.cfp-auth-login').length > 0;
        if (isLogin) doLogin();
        else doRegister();
      }
    });
  }

  $(document).on('click', '.cfp-cal-prev', function(){ const $r=$(this).closest('.cfp-calendar-booking'); const ref=new Date($r.data('refDate')||new Date()); const view=$r.data('view')||'month'; if(view==='week'||view==='agenda') ref.setDate(ref.getDate()-7); else ref.setMonth(ref.getMonth()-1); loadMonth($r, ref); });
  $(document).on('click', '.cfp-cal-next', function(){ const $r=$(this).closest('.cfp-calendar-booking'); const ref=new Date($r.data('refDate')||new Date()); const view=$r.data('view')||'month'; if(view==='week'||view==='agenda') ref.setDate(ref.getDate()+7); else ref.setMonth(ref.getMonth()+1); loadMonth($r, ref); });
  $(document).on('click', '.cfp-calendar-booking .cfp-view', function(){ const $r=$(this).closest('.cfp-calendar-booking'); $r.data('view', $(this).data('view')); loadMonth($r, new Date($r.data('refDate')||new Date())); });
  $(document).on('change', '.cfp-calendar-booking .cfp-filter-class, .cfp-calendar-booking .cfp-filter-location', function(){ const $r=$(this).closest('.cfp-calendar-booking'); loadMonth($r, new Date($r.data('refDate')||new Date())); });
  $(document).on('click', '.cfp-calendar-booking .cfp-book', function(){ createBooking($(this).closest('.cfp-calendar-booking')); });
  $(document).on('click', '.cfp-calendar-booking .cfp-pay', function(){ confirmPay($(this).closest('.cfp-calendar-booking')); });

  async function fetchJsonSafe(url) {
    try {
      const response = await fetch(url);
      const text = await response.text();
      
      // Check if response is HTML (error page)
      if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
        console.error('Got HTML instead of JSON for:', url);
        console.error('This usually means the REST API endpoint is not found');
        return [];
      }
      
      // Clean any PHP warnings from response
      let cleanJson = text;
      if (text.includes('Warning:') || text.includes('Notice:')) {
        const jsonStart = text.indexOf('[') >= 0 ? text.indexOf('[') : text.indexOf('{');
        cleanJson = jsonStart >= 0 ? text.substring(jsonStart) : text;
      }
      
      try {
        const parsed = JSON.parse(cleanJson);
        return Array.isArray(parsed) ? parsed : [];
      } catch(parseError) {
        console.error('JSON parse error for URL:', url);
        console.error('Response was:', text.substring(0, 200));
        return [];
      }
    } catch(e) {
      console.error('Fetch error:', url, e);
      return [];
    }
  }

  async function populateFilters($root) {
    try {
      const base = CFP_DATA.restUrl + 'entities/';
      const per = new URLSearchParams({ per_page: '100' });
      const classes = await fetchJsonSafe(withParams(base + 'classes', per));
      const locs = await fetchJsonSafe(withParams(base + 'locations', per));
      const instr = await fetchJsonSafe(withParams(base + 'instructors', per));
      const $cls = $root.find('.cfp-filter-class'); const $loc = $root.find('.cfp-filter-location');
      const $inst = $root.find('.cfp-filter-instructor');
      if (Array.isArray(classes)) classes.forEach(c => { $cls.append('<option value="'+c.id+'">'+(c.name || ('#'+c.id))+'</option>'); });
      if (Array.isArray(locs)) locs.forEach(l => { $loc.append('<option value="'+l.id+'">'+(l.name || ('#'+l.id))+'</option>'); });
      if (Array.isArray(instr)) instr.forEach(i => { $inst.append('<option value="'+i.id+'">'+(i.name || ('#'+i.id))+'</option>'); });
      if ($root.data('class-id')) $cls.val(String($root.data('class-id')));
      if ($root.data('location-id')) $loc.val(String($root.data('location-id')));
    } catch(e) {
      console.error('Failed to populate filters:', e);
    }
  }

  $(function(){ 
    $('.cfp-calendar-booking').each(function(){ 
      const $r=$(this); 
      $r.data('view','month'); 
      // Initialize calendar differently based on type
      if ($r.hasClass('cfp-full-calendar')) {
        // Full calendar specific initialization
        $r.find('.cfp-cal-grid').removeClass('cfp-loading').html('<div style="text-align:center;padding:40px;">Loading calendar...</div>');
      }
      // Handle UI based on login status and requirements
      try {
        // Always hide credits checkbox initially - we'll show it only if user has credits
        $r.find('.cfp-use-credits').closest('label, .cfp-checkbox-label').hide();
        
        if (!(CFP_DATA && CFP_DATA.isLoggedIn)) {
          // Not logged in
          
          if (CFP_DATA && CFP_DATA.requireLoginToBook) {
            // Login required - simplify the form
            if ($r.hasClass('cfp-full-calendar')) {
              // For full calendar, update the Quick Booking section
              const $sidebar = $r.find('.cfp-sidebar-card').last();
              const $form = $sidebar.find('.cfp-booking-form');
              
              // Hide unnecessary fields when login is required
              $form.find('.cfp-password').closest('label').hide();
              $form.find('.cfp-sms-optin').closest('label').hide();
              $form.find('.cfp-use-credits').closest('label').hide();
              
              // Change the button
              const $btn = $form.find('.cfp-book');
              $btn.text('Login or Register to Book');
              $btn.off('click').on('click', function(e){ 
                e.preventDefault();
                // Gather any entered data to prefill in auth modal
                const prefillData = {
                  name: $r.find('.cfp-name').val(),
                  email: $r.find('.cfp-email').val(),
                  phone: $r.find('.cfp-phone').val()
                };
                openAuthModal((extraData) => {
                  // After successful login/register, reload the page to show logged-in state
                  window.location.reload();
                }, prefillData); 
              });
              
              // Add a helpful message
              if (!$sidebar.find('.cfp-login-notice').length) {
                $form.before('<div class="cfp-login-notice" style="background:#f3f4f6;padding:12px;border-radius:6px;margin-bottom:16px;color:#4b5563;">Please login or create an account to book classes. Your information will be saved for future bookings.</div>');
              }
            } else {
              // For small calendar
              $r.find('.cfp-password').closest('label').hide();
              const $btn=$r.find('.cfp-book'); 
              $btn.text('Login or Register to Book');
              $btn.off('click').on('click', function(e){ 
                e.preventDefault();
                const prefillData = {
                  name: $r.find('.cfp-name').val(),
                  email: $r.find('.cfp-email').val()
                };
                openAuthModal(() => window.location.reload(), prefillData); 
              });
            }
          } else {
            // Login not required, but hide password field if set (it will auto-create account)
            $r.find('.cfp-password').closest('label').hide();
            $r.find('.cfp-sms-optin').closest('label').hide();
          }
        } else {
          // User is logged in
          $r.find('.cfp-password').closest('label').hide();
          
          // Show credits checkbox only if user has credits
          if (CFP_DATA && CFP_DATA.userCredits && CFP_DATA.userCredits > 0) {
            const $creditsLabel = $r.find('.cfp-use-credits').closest('label, .cfp-checkbox-label');
            $creditsLabel.show();
            // Update the label to show how many credits they have
            const $creditsText = $creditsLabel.find('span');
            if ($creditsText.length) {
              $creditsText.text('Use available credits (' + CFP_DATA.userCredits + ' available)');
            }
          }
          
          // For logged-in users, we could prefill their data
          if ($r.hasClass('cfp-full-calendar')) {
            // Hide name and email fields for logged-in users as we already have their info
            const $form = $r.find('.cfp-booking-form');
            $form.find('.cfp-name').closest('label').hide();
            $form.find('.cfp-email').closest('label').hide();
            $form.find('.cfp-password').closest('label').hide();
            
            // Add a welcome message
            const $sidebar = $r.find('.cfp-sidebar-card').last();
            if (!$sidebar.find('.cfp-user-welcome').length && typeof CFP_DATA !== 'undefined') {
              let welcomeMsg = 'Welcome back! Select a class to book.';
              if (CFP_DATA.userCredits > 0) {
                welcomeMsg += ' You have ' + CFP_DATA.userCredits + ' credit' + (CFP_DATA.userCredits > 1 ? 's' : '') + ' available.';
              }
              $form.before('<div class="cfp-user-welcome" style="background:#eff6ff;padding:12px;border-radius:6px;margin-bottom:16px;color:#1e40af;">' + welcomeMsg + '</div>');
            }
          } else {
            // Small calendar - hide fields for logged-in users
            $r.find('.cfp-name').closest('label').hide();
            $r.find('.cfp-email').closest('label').hide();
          }
        }
      } catch(e){ console.error('Error setting up auth UI:', e); }
      populateFilters($r).then(()=>loadMonth($r, new Date())).catch(e => {
        console.error('Calendar init error:', e);
        $r.find('.cfp-cal-grid').removeClass('cfp-loading').html('<div style="text-align:center;padding:40px;color:#dc2626;">Failed to load calendar. Please refresh the page.</div>');
      }); 
    }); 
  });
})(jQuery);
