/* global CFP_DATA, jQuery */
(function($){
  function headers($root){ return { 'Content-Type': 'application/json', 'X-WP-Nonce': $root.data('nonce') }; }
  function fmt(dt, tz){ try{ return new Date(dt+'Z').toLocaleString(undefined,{ timeZone: tz||CFP_DATA.businessTimezone||'UTC' }); }catch(e){ return new Date(dt+'Z').toLocaleString(); } }
  async function loadPortal($root){
    try{
      const res = await fetch(CFP_DATA.restUrl + 'me/overview', { headers: headers($root) });
      const data = await res.json();
      if(!res.ok){ $root.find('.cfp-upcoming-list').text('Failed to load'); $root.find('.cfp-past-list').text('Failed to load'); return; }
      const up = $root.find('.cfp-upcoming-list').empty();
      if(!data.upcoming || !data.upcoming.length){ up.text('No upcoming classes.'); }
      else { data.upcoming.forEach(r=>{ up.append('<div class=\"cfp-item\"><div><strong>'+ (r.class_title||'') +'</strong></div><div>'+ fmt(r.start_time, r.timezone) +'</div></div>'); }); }
      const past = $root.find('.cfp-past-list').empty();
      if(!data.past || !data.past.length){ past.text('No past classes.'); }
      else { data.past.forEach(r=>{ past.append('<div class=\"cfp-item\"><div><strong>'+ (r.class_title||'') +'</strong></div><div>'+ fmt(r.start_time, r.timezone) +'</div></div>'); }); }
      const cr = $root.find('.cfp-credits').empty(); cr.text(typeof data.credits==='number' ? (data.credits+' credits') : '—');
    }catch(e){ $root.find('.cfp-upcoming-list,.cfp-past-list,.cfp-credits').text('Error loading.'); }
  }
  async function loadProfile($root){
    try{
      const res = await fetch(CFP_DATA.restUrl + 'me/profile', { headers: headers($root) });
      const p = await res.json();
      if (!res.ok) return;
      $root.find('.cfp-prof-phone').val(p.phone || '');
      $root.find('.cfp-prof-dob').val(p.dob || '');
      $root.find('.cfp-prof-emg-name').val(p.emergency_name || '');
      $root.find('.cfp-prof-emg-phone').val(p.emergency_phone || '');
    } catch(e) {}
  }
  async function saveProfile($root){
    const body = {
      phone: ($root.find('.cfp-prof-phone').val()||'').toString(),
      dob: ($root.find('.cfp-prof-dob').val()||'').toString(),
      emergency_name: ($root.find('.cfp-prof-emg-name').val()||'').toString(),
      emergency_phone: ($root.find('.cfp-prof-emg-phone').val()||'').toString(),
    };
    const $msg = $root.find('.cfp-portal-profile .cfp-msg').empty();
    try{
      const res = await fetch(CFP_DATA.restUrl + 'me/profile', { method:'POST', headers: headers($root), body: JSON.stringify(body) });
      const d = await res.json();
      if (!res.ok) { $msg.text(d.message || 'Failed to save profile.'); return; }
      $msg.text('Profile saved.');
    }catch(e){ $msg.text('Failed to save profile.'); }
  }
  async function loadNotes($root){
    try{
      const res = await fetch(CFP_DATA.restUrl + 'me/notes', { headers: headers($root) });
      const rows = await res.json();
      const list = $root.find('.cfp-notes-list').empty();
      if (!res.ok || !Array.isArray(rows) || rows.length===0){ list.text('No notes available.'); return; }
      rows.forEach(r=>{
        const when = r.created_at ? new Date(r.created_at+'Z').toLocaleString() : '';
        list.append('<div class="cfp-item"><div>'+ (r.note||'') +'</div><div class="cfp-note-date">'+ when +'</div></div>');
      });
    }catch(e){ $root.find('.cfp-notes-list').text('Failed to load notes.'); }
  }
  async function redeemGiftCard($root){
    const $wrap = $root.find('.cfp-portal-credits');
    const $msg = $wrap.find('.cfp-gc-msg').empty();
    const code = ($wrap.find('.cfp-gc-code').val()||'').toString().trim();
    if (!code){ $msg.text('Please enter a code.'); return; }
    const btn = $wrap.find('.cfp-gc-redeem-btn').prop('disabled', true);
    try{
      const res = await fetch(CFP_DATA.restUrl + 'giftcards/redeem', { method:'POST', headers: headers($root), body: JSON.stringify({ code }) });
      const d = await res.json();
      if (!res.ok){ $msg.text(d && d.message ? d.message : 'Invalid or already used code.'); return; }
      $msg.text('Gift card redeemed: +' + (d.credits||0) + ' credits');
      $wrap.find('.cfp-gc-code').val('');
      // Refresh credits and lists
      await loadPortal($root);
    }catch(e){ $msg.text('Failed to redeem gift card.'); }
    finally{ btn.prop('disabled', false); }
  }
  async function loadMySeries($root){
    try{
      const res = await fetch(CFP_DATA.restUrl + 'me/series', { headers: headers($root) });
      const rows = await res.json();
      const list = $root.find('.cfp-series-list').empty();
      if (!res.ok || !Array.isArray(rows) || rows.length===0){ list.text('No series enrolled.'); return; }
      rows.forEach(r=>{
        const next = r.next_session ? new Date(r.next_session+'Z').toLocaleString() : '—';
        list.append('<div class="cfp-item"><div><strong>'+(r.name||'')+'</strong> — '+(r.class_title||'')+'</div><div>'+ (r.start_date||'') +' → '+ (r.end_date||'') +'</div><div>Sessions: '+(r.sessions_total||0) +' · Next: '+next+'</div></div>');
      });
    }catch(e){ $root.find('.cfp-series-list').text('Failed to load.'); }
  }
  $(function(){ $('.cfp-portal').each(function(){ const $r=$(this); loadPortal($r); loadProfile($r); loadNotes($r); }); });
  $(function(){ $('.cfp-portal').each(function(){ const $r=$(this); loadMySeries($r); }); });
  $(document).on('click', '.cfp-prof-save', function(e){ e.preventDefault(); saveProfile($(this).closest('.cfp-portal')); });
  $(document).on('click', '.cfp-gc-redeem-btn', function(e){ e.preventDefault(); redeemGiftCard($(this).closest('.cfp-portal')); });
})(jQuery);
