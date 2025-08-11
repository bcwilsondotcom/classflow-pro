/* global CFP_ADMIN, jQuery */
(function($){
  function headers(){ return { 'Content-Type': 'application/json', 'X-WP-Nonce': CFP_ADMIN.nonce }; }
  const dayMapNum = { sun:0, mon:1, tue:2, wed:3, thu:4, fri:5, sat:6 };
  const dayOrder = ['sun','mon','tue','wed','thu','fri','sat'];
  function withParams(url, params){ if (!params) return url; const qs=(params instanceof URLSearchParams)?params.toString():params; return url + (url.indexOf('?')>=0 ? '&' : '?') + qs; }
  function monthBounds(d){ const start=new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(),1)); const end=new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth()+1,0,23,59,59)); return {start,end}; }
  function fmtDate(dt){ return dt.toISOString().slice(0,10); }
  function fmtLocal(dt, tz){
    try{ return dt.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit', timeZone: tz || CFP_ADMIN.timezone || undefined}); }
    catch(e){ return dt.toISOString().slice(11,16); }
  }
  // Compute offset (in minutes) for a given IANA timezone at a specific Date
  function tzOffsetMinutes(date, timeZone){
    try{
      const dtf = new Intl.DateTimeFormat('en-US', {
        timeZone: timeZone || CFP_ADMIN.timezone || 'UTC',
        hour12: false,
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
      const parts = dtf.formatToParts(date);
      const map = {};
      parts.forEach(p=>{ map[p.type]=p.value; });
      const asUTC = Date.UTC(parseInt(map.year,10), parseInt(map.month,10)-1, parseInt(map.day,10), parseInt(map.hour,10), parseInt(map.minute,10), parseInt(map.second,10));
      return (asUTC - date.getTime())/60000;
    }catch(e){
      return date.getTimezoneOffset();
    }
  }
  let selectedClass = null;
  async function loadFilters(){
    try{
      const base = CFP_ADMIN.restUrl + 'entities/';
      const clRes = await fetch(withParams(base + 'classes', new URLSearchParams({ per_page: '200' })), { headers: headers() });
      const inRes = await fetch(withParams(base + 'instructors', new URLSearchParams({ per_page: '200' })), { headers: headers() });
      const loRes = await fetch(withParams(base + 'locations', new URLSearchParams({ per_page: '200' })), { headers: headers() });
      const classes = clRes.ok ? await clRes.json() : [];
      const instr = inRes.ok ? await inRes.json() : [];
      const locs = loRes.ok ? await loRes.json() : [];
      $('.cfp-filter-class, .cfp-form-class').empty();
      $('.cfp-filter-instructor, .cfp-form-instructor').empty();
      $('.cfp-filter-location, .cfp-form-location').empty();
      $('.cfp-filter-class').append('<option value="">All Classes</option>');
      $('.cfp-filter-instructor').append('<option value="">All Instructors</option>');
      $('.cfp-filter-location').append('<option value="">All Locations</option>');
      if(Array.isArray(classes) && classes.length){ classes.forEach(c=>{ $('.cfp-filter-class, .cfp-form-class').append('<option value="'+c.id+'">'+(c.name||('#'+c.id))+'</option>'); }); }
      else { $('.cfp-form-class').append('<option value="">'+('No classes')+'</option>'); }
      if(Array.isArray(instr) && instr.length){ instr.forEach(i=>{ $('.cfp-filter-instructor, .cfp-form-instructor').append('<option value="'+i.id+'">'+(i.name||('#'+i.id))+'</option>'); }); }
      else { $('.cfp-form-instructor').append('<option value="">'+('No instructors')+'</option>'); }
      if(Array.isArray(locs) && locs.length){ locs.forEach(l=>{ $('.cfp-filter-location, .cfp-form-location').append('<option value="'+l.id+'">'+(l.name||('#'+l.id))+'</option>'); }); }
      else { $('.cfp-form-location').append('<option value="">'+('No locations')+'</option>'); }
      const preset = parseInt(CFP_ADMIN.presetClassId || 0, 10);
      if (preset > 0) { $('.cfp-filter-class, .cfp-form-class').val(String(preset)); await fetchClassDetail(preset); }
    }catch(e){ try{ console.error('CFP loadFilters failed', e);}catch(_){} }
  }
  async function fetchClassDetail(id){
    selectedClass = null;
    try{
      const res = await fetch(CFP_ADMIN.restUrl + 'classes/' + id, { headers: headers() });
      if (!res.ok) return;
      selectedClass = await res.json();
      if (selectedClass && selectedClass.default_location_id && !($('.cfp-form-location').val())){
        $('.cfp-form-location').val(String(selectedClass.default_location_id));
      }
    }catch(e){}
  }
  async function loadMonth(current){
    try{
      const {start,end}=monthBounds(current);
      const params=new URLSearchParams();
      params.set('date_from', fmtDate(new Date(start)));
      params.set('date_to', fmtDate(new Date(end)));
      const cls=$('.cfp-filter-class').val(); if (cls) params.set('class_id', cls);
      const ins=$('.cfp-filter-instructor').val(); if (ins) params.set('instructor_id', ins);
      const loc=$('.cfp-filter-location').val(); if (loc) params.set('location_id', loc);
      const url=withParams(CFP_ADMIN.restUrl+'schedules', params);
      const res=await fetch(url, { headers: headers() });
      const rows=await res.json();
      renderCalendar(current, rows||[]);
    }catch(e){
      renderCalendar(current, []);
      try{ $('.cfp-cal-grid').find('.cfp-cal-placeholder').text('Failed to load schedules.'); }catch(_e){}
    }
  }
  function renderCalendar(current, rows){
    const $title=$('.cfp-cal-title');
    const $grid=$('.cfp-cal-grid').empty();
    const monthName=current.toLocaleString(undefined,{month:'long', year:'numeric'});
    $title.text(monthName);
    const weekdays=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    weekdays.forEach(function(w){ $grid.append('<div style="font-weight:600;color:#475569;text-align:center;">'+w+'</div>'); });
    const startIdx = (new Date(Date.UTC(current.getUTCFullYear(), current.getUTCMonth(),1)).getUTCDay());
    const gridStart=new Date(current.getFullYear(), current.getMonth(), 1 - startIdx);
    for(let i=0;i<42;i++){
      const d=new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate()+i);
      const isOther = d.getMonth()!==current.getMonth();
      const cell=$('<div class="cfp-cal-cell" style="border:1px solid #e2e8f0;border-radius:6px;min-height:120px;background:#fff;padding:6px;display:flex;flex-direction:column;"></div>');
      const dayHdr=$('<div style="font-size:12px;color:#64748b;display:flex;justify-content:space-between;align-items:center;"><span>'+d.getDate()+'</span></div>');
      if (isOther) cell.css('opacity',0.5);
      cell.append(dayHdr);
      const dayWrap=$('<div style="display:flex;flex-direction:column;gap:4px;margin-top:4px;"></div>');
      const dateStr = d.toISOString().slice(0,10);
      let items=(rows||[]).filter(r=> (r.start_time||'').slice(0,10)===dateStr);
      if ($('.cfp-filter-with-attendees').is(':checked')){ items = items.filter(r => (parseInt(r.booked_count||0,10) > 0)); }
      items.sort((a,b)=> a.start_time.localeCompare(b.start_time));
      items.forEach(r=>{
        const t=new Date(r.start_time+'Z');
        const tz = r.location_timezone || CFP_ADMIN.timezone;
        const label=(r.class_title||('#'+r.class_id))+' — '+fmtLocal(t, tz);
        const pill=$('<div class="cfp-sched-pill" style="font-size:12px;border:1px solid #cbd5e1;border-radius:4px;padding:2px 4px;background:#f8fafc;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;"></div>').text(label);
        pill.data('sched', r);
        dayWrap.append(pill);
      });
      cell.append(dayWrap);
      $grid.append(cell);
    }
  }
  async function refreshAttendees(id){
    const $m=$('#cfp-sched-modal'); const $box=$m.find('.cfp-sched-attendees').empty();
    try{
      const res=await fetch(CFP_ADMIN.adminRestUrl+'schedules/'+id+'/attendees', { headers: headers() });
      const rows=await res.json();
      if (!res.ok || !Array.isArray(rows) || !rows.length){ $box.html('<em>No attendees.</em>'); return; }
      // Table header
      const $tbl=$('<div style="display:grid;grid-template-columns:1fr 120px 110px 90px;gap:6px;align-items:center;"></div>');
      $tbl.append('<div style="font-weight:600;">Name</div><div style="font-weight:600;">Status</div><div style="font-weight:600;">Action</div><div></div>');
      rows.forEach(r=>{
        const name = r.display_name || r.user_email || r.customer_email || ('#'+r.id);
        const status=r.status;
        const $row=$('<div style="display:contents;"></div>');
        $row.append('<div>'+name+'</div>');
        $row.append('<div>'+status+'</div>');
        const selId='act-'+r.id;
        const $sel=$('<select class="cfp-att-action" id="'+selId+'"><option value="auto">Auto</option><option value="refund">Refund</option><option value="credit">Credit</option><option value="cancel">Cancel</option></select>');
        $row.append($('<div></div>').append($sel));
        const $btn=$('<button class="button">Apply</button>');
        $btn.on('click', async function(){
          const action=$sel.val(); const note=($m.find('.cfp-sched-note').val()||'').toString(); const body={ action, note, notify: $m.find('.cfp-sched-notify').is(':checked') };
          $btn.prop('disabled', true).text('Working…');
          try{ const resp=await fetch(CFP_ADMIN.adminRestUrl+'bookings/'+r.id+'/admin_cancel', { method:'POST', headers: headers(), body: JSON.stringify(body) }); const js=await resp.json(); if(!resp.ok){ alert(js && js.message ? js.message : 'Failed'); } else { await refreshAttendees(id); } }catch(e){ alert('Failed'); } finally { $btn.prop('disabled', false).text('Apply'); }
        });
        $row.append($('<div></div>').append($btn));
        $tbl.append($row);
      });
      $box.append($tbl);
    }catch(e){ $box.html('<em>Failed to load attendees.</em>'); }
  }
  async function createSchedules(){
    const $msg = $('.cfp-msg').text('');
    try{
      const classId = parseInt($('.cfp-form-class').val()||0,10);
      const instrId = parseInt($('.cfp-form-instructor').val()||0,10) || null;
      const locId = parseInt($('.cfp-form-location').val()||0,10) || null;
      const startDate = $('.cfp-form-start').val()||'';
      const endDate = $('.cfp-form-end').val()||'';
      const isPrivate = $('.cfp-form-private').is(':checked');
      if (!classId || !startDate){ $msg.text('Pick a class and start date'); return; }
      // Build a list of occurrences based on selected DOW inputs
      const picks = [];
      dayOrder.forEach(d=>{ if ($('.cfp-dow-ck[value="'+d+'"]').is(':checked')) { const t=$('.cfp-time-'+d).val()||'09:00'; picks.push({ dow: d, time: t }); } });
      if (picks.length === 0) { $msg.text('Pick at least one day/time'); return; }
      const start = new Date(startDate + 'T00:00:00');
      const until = endDate ? new Date(endDate + 'T23:59:59') : new Date(start.getFullYear(), start.getMonth(), start.getDate());
      const toSend = [];
      for (let d=new Date(start); d<=until; d.setDate(d.getDate()+1)){
        const dow = ['sun','mon','tue','wed','thu','fri','sat'][d.getDay()];
        picks.filter(p=>p.dow===dow).forEach(p=>{
          const [hh,mm] = p.time.split(':');
          const localStart = new Date(d.getFullYear(), d.getMonth(), d.getDate(), parseInt(hh,10), parseInt(mm,10), 0);
          // Convert from business timezone to UTC for storage
          const offMin = tzOffsetMinutes(localStart, CFP_ADMIN.timezone);
          const startUtc = new Date(localStart.getTime() - offMin*60000);
          const duration = (selectedClass && selectedClass.duration_mins ? selectedClass.duration_mins : 60);
          const endUtc = new Date(startUtc.getTime() + duration*60000);
          toSend.push({
            class_id: classId,
            instructor_id: instrId,
            location_id: locId,
            start_time: startUtc.toISOString().slice(0,19).replace('T',' '),
            end_time: endUtc.toISOString().slice(0,19).replace('T',' '),
            is_private: isPrivate,
            capacity: selectedClass && selectedClass.capacity ? selectedClass.capacity : 8,
            price_cents: selectedClass && selectedClass.price_cents ? selectedClass.price_cents : 0,
            currency: 'usd'
          });
        });
      }
      if (!toSend.length){ $msg.text('Nothing to create for the selected range'); return; }
      $msg.text('Creating '+toSend.length+' schedule(s)...');
      let created=0, failed=0;
      for (const payload of toSend){
        try{
          const res = await fetch(CFP_ADMIN.adminRestUrl + 'schedules', { method:'POST', headers: headers(), body: JSON.stringify(payload) });
          if (!res.ok) failed++; else created++;
        }catch(e){ failed++; }
      }
      $msg.text('Created '+created+' schedule(s)' + (failed?(' — '+failed+' failed') : ''));
      loadMonth(window.__cfpCurrent||new Date());
    }catch(e){ $msg.text('Failed to create schedules'); }
  }
  async function applyScheduleChanges(){
    const $m=$('#cfp-sched-modal'); const id=parseInt($m.data('id')||0,10); if(!id)return;
    const instrId = parseInt($m.find('.cfp-edit-instructor').val()||0,10) || null;
    const locId = parseInt($m.find('.cfp-edit-location').val()||0,10) || null;
    const d=$m.find('.cfp-edit-date').val()||''; const t=$m.find('.cfp-edit-time').val()||'';
    const update={}; if(instrId!==null) update.instructor_id=instrId; if(locId!==null) update.location_id=locId;
    if (d && t){
      // Interpret entered date/time as local, store as UTC
      const local = new Date(d+'T'+t+':00');
      const offMin = tzOffsetMinutes(local, CFP_ADMIN.timezone);
      const utc = new Date(local.getTime() - offMin*60000);
      const duration = (selectedClass && selectedClass.duration_mins ? selectedClass.duration_mins : 60);
      const end = new Date(utc.getTime() + duration*60000);
      update.start_time = utc.toISOString().slice(0,19).replace('T',' ');
      update.end_time = end.toISOString().slice(0,19).replace('T',' ');
    }
    try{
      const res=await fetch(CFP_ADMIN.adminRestUrl+'schedules/'+id, { method:'PUT', headers: headers(), body: JSON.stringify(update) });
      if (!res.ok){ alert('Failed to update'); return; }
      $('#cfp-sched-modal').hide(); loadMonth(window.__cfpCurrent||new Date());
    }catch(e){ alert('Failed to update'); }
  }
  async function cancelSchedule(){
    const id=parseInt($('#cfp-sched-modal').data('id')||0,10); if(!id)return;
    if (!confirm('Cancel this schedule?')) return;
    try{
      const note = ($('#cfp-sched-modal .cfp-sched-note').val()||'').toString();
      const action = ($('#cfp-sched-modal .cfp-sched-action').val()||'auto').toString();
      const notify = $('#cfp-sched-modal .cfp-sched-notify').is(':checked');
      const res=await fetch(CFP_ADMIN.adminRestUrl+'schedules/'+id+'/cancel', { method:'POST', headers: headers(), body: JSON.stringify({ action, note, notify }) });
      const js=await res.json();
      if (!res.ok){ alert(js && js.error ? js.error : 'Failed'); return; }
      $('#cfp-sched-modal').hide(); loadMonth(window.__cfpCurrent||new Date());
    }catch(e){ alert('Failed'); }
  }
  async function moveAttendees(){
    const id=parseInt($('#cfp-sched-modal').data('id')||0,10); if(!id)return;
    const target=parseInt($('#cfp-sched-modal .cfp-move-target').val()||0,10); if(!target){ alert('Pick a target schedule'); return; }
    const notify = $('#cfp-sched-modal .cfp-sched-notify').is(':checked');
    try{ const res=await fetch(CFP_ADMIN.adminRestUrl+'schedules/'+id+'/reschedule_to', { method:'POST', headers: headers(), body: JSON.stringify({ target_schedule_id: target, notify }) }); if(!res.ok){ alert('Failed'); return; } $('#cfp-sched-modal').hide(); loadMonth(window.__cfpCurrent||new Date()); }catch(e){ alert('Failed'); }
  }
  async function openScheduleModal(s){
    const $m=$('#cfp-sched-modal').css('display','flex');
    $m.data('id', s.id);
    $m.find('.cfp-sched-title').text((s.class_title||('#'+s.class_id)));
    try{
      const tz = s.location_timezone || CFP_ADMIN.timezone;
      $m.find('.cfp-sched-info').text(new Date(s.start_time+'Z').toLocaleString(undefined,{ timeZone: tz }));
    }catch(e){ $m.find('.cfp-sched-info').text(new Date(s.start_time+'Z').toLocaleString()); }
    const $ei=$m.find('.cfp-edit-instructor').empty().append($('.cfp-form-instructor').html());
    const $el=$m.find('.cfp-edit-location').empty().append($('.cfp-form-location').html());
    if (s.instructor_id) $ei.val(String(s.instructor_id));
    if (s.location_id) $el.val(String(s.location_id));
    $m.find('.cfp-edit-date').val((s.start_time||'').slice(0,10));
    $m.find('.cfp-edit-time').val((s.start_time||'').slice(11,16));
    await refreshAttendees(s.id);
    // Populate move targets: all future schedules for same class
    try{
      const params=new URLSearchParams(); params.set('class_id', s.class_id); params.set('date_from', new Date().toISOString().slice(0,10));
      const res=await fetch(withParams(CFP_ADMIN.restUrl+'schedules', params), { headers: headers() });
      const rows=await res.json();
      const $sel=$m.find('.cfp-move-target').empty();
      $sel.append('<option value="">— Select —</option>');
      (rows||[]).filter(r=>r.id!==s.id).forEach(r=>{ const dt=new Date(r.start_time+'Z').toLocaleString(); $sel.append('<option value="'+r.id+'">'+dt+'</option>'); });
    }catch(e){}
  }
  async function applyScheduleChanges(){
    const $m=$('#cfp-sched-modal'); const id=parseInt($m.data('id')||0,10); if (!id) return;
    const instr = $m.find('.cfp-edit-instructor').val()||''; const loc = $m.find('.cfp-edit-location').val()||'';
    const d=$m.find('.cfp-edit-date').val()||''; const t=$m.find('.cfp-edit-time').val()||'';
    const body={}; if (instr!=='') body.instructor_id=parseInt(instr,10); if (loc!=='') body.location_id=parseInt(loc,10);
    if (d && t){ const start=new Date(d+'T'+t); const end=new Date(start.getTime() + (selectedClass && selectedClass.duration_mins ? selectedClass.duration_mins : 60)*60000); body.start_time = d+' '+t+':00'; body.end_time = end.toISOString().slice(0,19).replace('T',' '); }
    try{ const res=await fetch(CFP_ADMIN.adminRestUrl+'schedules/'+id, { method:'PUT', headers: headers(), body: JSON.stringify(body) }); if (!res.ok){ const tx=await res.text(); throw new Error(tx||'Failed'); } $('#cfp-sched-modal .cfp-sched-msg').text('Updated'); await loadMonth(window.__cfpCurrent||new Date()); }catch(e){ $('#cfp-sched-modal .cfp-sched-msg').text('Update failed'); }
  }
  async function cancelSchedule(){ const $m=$('#cfp-sched-modal'); const id=parseInt($m.data('id')||0,10); if (!id) return; if (!confirm('Cancel this session?')) return; const body={ notify: $m.find('.cfp-sched-notify').is(':checked'), note: ($m.find('.cfp-sched-note').val()||'').toString(), action: ($m.find('.cfp-sched-action').val()||'auto') }; try{ const res=await fetch(CFP_ADMIN.adminRestUrl+'schedules/'+id+'/cancel', { method:'POST', headers: headers(), body: JSON.stringify(body) }); if (!res.ok){ const tx=await res.text(); throw new Error(tx||'Failed'); } $('#cfp-sched-modal .cfp-sched-msg').text('Session canceled'); await loadMonth(window.__cfpCurrent||new Date()); }catch(e){ $('#cfp-sched-modal .cfp-sched-msg').text('Cancel failed'); } }
  async function createSchedules(){
    const $msg=$('.cfp-sched-panel .cfp-msg').text('');
    const classId=parseInt($('.cfp-form-class').val()||0,10);
    const locId=parseInt($('.cfp-form-location').val()||0,10);
    const instId=parseInt($('.cfp-form-instructor').val()||0,10)||null;
    const start=($('.cfp-form-start').val()||'').toString();
    let end=($('.cfp-form-end').val()||'').toString();
    const isPrivate=$('.cfp-form-private').is(':checked');
    if (!classId || !locId || !start){ $msg.text('Select class, location, and start date.'); return; }
    const selected=[]; dayOrder.forEach(d=>{ const ck=$('.cfp-dow-ck[value='+d+']'); if(ck.is(':checked')){ const t=$('.cfp-time-'+d).val(); if(t) selected.push({d,t}); }});
    if (!selected.length){ $msg.text('Select at least one day and time.'); return; }
    if (!end){ const now=new Date(start); const endOfMonth=new Date(now.getFullYear(), now.getMonth()+1, 0); end = fmtDate(endOfMonth); }
    const begin=new Date(start); const final=new Date(end);
    const promises=[]; const adminBase = CFP_ADMIN.adminRestUrl + 'schedules';
    const durationMs = (selectedClass && selectedClass.duration_mins ? selectedClass.duration_mins : 60) * 60000;
    const capacity = (selectedClass && selectedClass.capacity) ? selectedClass.capacity : 8;
    const price_cents = (selectedClass && selectedClass.price_cents) ? selectedClass.price_cents : 0;
    const currency = (selectedClass && selectedClass.currency) ? selectedClass.currency : 'usd';
    while (begin <= final){
      const dow = begin.getDay();
      selected.forEach(sel=>{
        const target = dayMapNum[sel.d];
        if (dow===target){
          const [hh,mm]=sel.t.split(':');
          const startDt=new Date(begin.getFullYear(), begin.getMonth(), begin.getDate(), parseInt(hh,10)||0, parseInt(mm,10)||0, 0);
          const endDt=new Date(startDt.getTime()+durationMs);
          const payload={ class_id: classId, location_id: locId, instructor_id: instId, start_time: startDt.toISOString().slice(0,19).replace('T',' '), end_time: endDt.toISOString().slice(0,19).replace('T',' '), is_private: isPrivate, capacity: capacity, price_cents: price_cents, currency: currency };
          promises.push(fetch(adminBase, { method:'POST', headers: headers(), body: JSON.stringify(payload)}));
        }
      });
      begin.setDate(begin.getDate()+1);
    }
    if (!promises.length){ $msg.text('No matching dates in range.'); return; }
    try{
      const resps = await Promise.all(promises);
      const ok = resps.filter(r=>r.ok).length;
      if (ok>0){ $msg.text('Created '+ok+' schedules.'); $('.cfp-cal-grid').empty(); await loadMonth(window.__cfpCurrent||new Date()); }
      else { const txt=await resps[0].text(); $msg.text('Failed: '+txt); }
    }catch(e){ $msg.text('Error creating schedules.'); }
  }
  function wire(){
  async function moveAttendees(){ const $m=$('#cfp-sched-modal'); const id=parseInt($m.data('id')||0,10); const target=parseInt($m.find('.cfp-move-target').val()||0,10); if (!id || !target) { alert('Select a target schedule.'); return; } if (!confirm('Move all attendees to the selected schedule?')) return; const body={ notify: $m.find('.cfp-sched-notify').is(':checked'), target_schedule_id: target }; try{ const res=await fetch(CFP_ADMIN.adminRestUrl+'schedules/'+id+'/reschedule_to', { method:'POST', headers: headers(), body: JSON.stringify(body) }); const js=await res.json(); if (!res.ok){ alert(js.error||'Failed'); return; } $('#cfp-sched-modal .cfp-sched-msg').text('Moved '+(js.moved||0)+' attendee(s).'); await loadMonth(window.__cfpCurrent||new Date()); }catch(e){ $('#cfp-sched-modal .cfp-sched-msg').text('Move failed'); } }

    $(document).on('change','.cfp-dow-ck', function(){ const v=$(this).val(); const $t=$('.cfp-time-'+v); $t.prop('disabled', !$(this).is(':checked')); if ($(this).is(':checked') && !$t.val()) $t.val('09:00'); });
    $(document).on('change', '.cfp-form-class', async function(){ const id=parseInt($(this).val()||0,10); if (id) await fetchClassDetail(id); });
    $('.cfp-cal-prev').on('click', function(){ const c=window.__cfpCurrent||new Date(); window.__cfpCurrent=new Date(c.getFullYear(), c.getMonth()-1, 1); loadMonth(window.__cfpCurrent); });
    $('.cfp-cal-next').on('click', function(){ const c=window.__cfpCurrent||new Date(); window.__cfpCurrent=new Date(c.getFullYear(), c.getMonth()+1, 1); loadMonth(window.__cfpCurrent); });
    $('.cfp-filter-class,.cfp-filter-instructor,.cfp-filter-location,.cfp-filter-with-attendees').on('change', function(){ loadMonth(window.__cfpCurrent||new Date()); });
    // Removed: auto-filter to only "my" classes. Users can select themselves via the instructor dropdown.
    $('.cfp-form-create').on('click', function(e){ e.preventDefault(); createSchedules(); });
    $(document).on('click', '.cfp-sched-pill', function(){ const s=$(this).data('sched'); openScheduleModal(s); });
    $(document).on('click', '#cfp-sched-modal .cfp-act-close', function(){ $('#cfp-sched-modal').hide(); });
    $(document).on('click', '#cfp-sched-modal .cfp-act-refresh', function(){ const id=parseInt($('#cfp-sched-modal').data('id')||0,10); if(id) refreshAttendees(id); });
    $(document).on('click', '#cfp-sched-modal .cfp-act-update', function(){ applyScheduleChanges(); });
    $(document).on('click', '#cfp-sched-modal .cfp-act-cancel', function(){ cancelSchedule(); });
    $(document).on('click', '#cfp-sched-modal .cfp-act-move', function(){ moveAttendees(); });
  }
  $(async function(){ await loadFilters(); window.__cfpCurrent=new Date(); wire(); await loadMonth(window.__cfpCurrent); });
})(jQuery);
