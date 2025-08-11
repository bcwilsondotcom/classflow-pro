/* global CFP_ADMIN, jQuery */
(function($){
  function headers(){ return { 'Content-Type': 'application/json', 'X-WP-Nonce': CFP_ADMIN.nonce }; }
  const dayMapNum = { sun:0, mon:1, tue:2, wed:3, thu:4, fri:5, sat:6 };
  const dayOrder = ['sun','mon','tue','wed','thu','fri','sat'];
  function withParams(url, params){ if (!params) return url; const qs=(params instanceof URLSearchParams)?params.toString():params; return url + (url.indexOf('?')>=0 ? '&' : '?') + qs; }
  function monthBounds(d){ const start=new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(),1)); const end=new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth()+1,0,23,59,59)); return {start,end}; }
  function fmtDate(dt){ return dt.toISOString().slice(0,10); }
  function fmtLocal(dt){ try{ return dt.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'});}catch(e){ return dt.toISOString().slice(11,16);} }
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
      const $fc = $('.cfp-filter-class, .cfp-form-class').empty();
      const $fi = $('.cfp-filter-instructor, .cfp-form-instructor').empty();
      const $fl = $('.cfp-filter-location, .cfp-form-location').empty();
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
      // Set default location if present and none selected
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
    // Weekday header row
    const weekdays=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    weekdays.forEach(function(w){ $grid.append('<div style="font-weight:600;color:#475569;text-align:center;">'+w+'</div>'); });
    // Determine first grid date (Sunday start)
    const first=new Date(current.getFullYear(), current.getMonth(), 1);
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
      const items=(rows||[]).filter(r=> (r.start_time||'').slice(0,10)===dateStr);
      items.sort((a,b)=> a.start_time.localeCompare(b.start_time));
      items.forEach(r=>{
        const t=new Date(r.start_time+'Z');
        const label=(r.class_title||'#'+r.class_id)+' — '+fmtLocal(t);
        const pill=$('<div style="font-size:12px;border:1px solid #cbd5e1;border-radius:4px;padding:2px 4px;background:#f8fafc;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>').text(label);
        dayWrap.append(pill);
      });
      cell.append(dayWrap);
      $grid.append(cell);
    }
  }
  async function createSchedules(){
    const $msg=$('.cfp-sched-panel .cfp-msg').text('');
    const classId=parseInt($('.cfp-form-class').val()||0,10);
    const locId=parseInt($('.cfp-form-location').val()||0,10);
    const instId=parseInt($('.cfp-form-instructor').val()||0,10)||null;
    const start=($('.cfp-form-start').val()||'').toString();
    let end=($('.cfp-form-end').val()||'').toString();
    const isPrivate=$('.cfp-form-private').is(':checked');
    if (!classId || !locId || !start){ $msg.text('Select class, location, and start date.'); return; }
    // Collect DoW selections
    const selected=[]; dayOrder.forEach(d=>{ const ck=$('.cfp-dow-ck[value='+d+']'); if(ck.is(':checked')){ const t=$('.cfp-time-'+d).val(); if(t) selected.push({d,t}); }});
    if (!selected.length){ $msg.text('Select at least one day and time.'); return; }
    if (!end){ // default end to end of visible month
      const now=new Date(start); const endOfMonth=new Date(now.getFullYear(), now.getMonth()+1, 0); end = fmtDate(endOfMonth);
    }
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
    // Toggle time input with checkbox
    $(document).on('change','.cfp-dow-ck', function(){ const v=$(this).val(); const $t=$('.cfp-time-'+v); $t.prop('disabled', !$(this).is(':checked')); if ($(this).is(':checked') && !$t.val()) $t.val('09:00'); });
    $(document).on('change', '.cfp-form-class', async function(){ const id=parseInt($(this).val()||0,10); if (id) await fetchClassDetail(id); });
    $('.cfp-cal-prev').on('click', function(){ const c=window.__cfpCurrent||new Date(); window.__cfpCurrent=new Date(c.getFullYear(), c.getMonth()-1, 1); loadMonth(window.__cfpCurrent); });
    $('.cfp-cal-next').on('click', function(){ const c=window.__cfpCurrent||new Date(); window.__cfpCurrent=new Date(c.getFullYear(), c.getMonth()+1, 1); loadMonth(window.__cfpCurrent); });
    $('.cfp-filter-class,.cfp-filter-instructor,.cfp-filter-location').on('change', function(){ loadMonth(window.__cfpCurrent||new Date()); });
    $('.cfp-form-create').on('click', function(e){ e.preventDefault(); createSchedules(); });
  }
  $(async function(){ await loadFilters(); window.__cfpCurrent=new Date(); wire(); await loadMonth(window.__cfpCurrent); });
})(jQuery);
