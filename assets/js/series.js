/* global CFP_DATA, jQuery */
(function($){
  function headers($root){ return { 'Content-Type': 'application/json', 'X-WP-Nonce': $root.data('nonce') }; }
  function fmtPrice(cents){ return '$' + (cents/100).toFixed(2); }
  function cardHTML(s){
    const seats = typeof s.seats_left === 'number' ? s.seats_left : 0;
    const soldOut = seats <= 0;
    const dates = (s.start_date || '') + (s.end_date ? (' → ' + s.end_date) : '');
    return (
      '<div class="cfp-series-card" data-id="'+ (s.id||'') +'">'
        + '<div class="cfp-series-hd"><strong>' + (s.name||'') + '</strong></div>'
        + '<div class="cfp-series-meta">'
          + '<div>' + (s.class_title||'') + '</div>'
          + '<div>' + dates + '</div>'
          + '<div>' + fmtPrice(parseInt(s.price_cents||0,10)) + '</div>'
        + '</div>'
        + '<div class="cfp-series-actions">'
          + (soldOut ? '<button class="button cfp-series-wait">Join Waitlist</button>' : '<button class="button button-primary cfp-series-buy">Buy</button>')
        + '</div>'
      + '</div>'
    );
  }
  async function loadSeries($root){
    const list = $root.find('.cfp-series-list').empty().addClass('cfp-loading');
    try{
      const res = await fetch(CFP_DATA.restUrl + 'series', { headers: headers($root) });
      const rows = await res.json();
      list.removeClass('cfp-loading');
      if (!res.ok || !Array.isArray(rows) || rows.length===0){ list.text('No series available.'); return; }
      rows.forEach(s=>{ list.append(cardHTML(s)); });
    }catch(e){ list.removeClass('cfp-loading').text('Failed to load series.'); }
  }
  async function checkoutSeries($root, seriesId){
    const $msg = $root.find('.cfp-msg').empty();
    try{
      const res = await fetch(CFP_DATA.restUrl + 'series/checkout', { method:'POST', headers: headers($root), body: JSON.stringify({ series_id: seriesId }) });
      const d = await res.json();
      if (!res.ok){
        if (res.status === 401){
          const ret = encodeURIComponent(window.location.href);
          const login = (window.cfpLoginUrl || ('/wp-login.php?redirect_to=' + ret));
          $msg.html('Please <a href="' + login + '">log in</a> to purchase.');
          return;
        }
        $msg.text(d && d.message ? d.message : 'Could not start checkout.'); return;
      }
      if (d && d.url){ window.location.href = d.url; return; }
      $msg.text('Unexpected response.');
    }catch(e){ $msg.text('Could not start checkout.'); }
  }
  $(function(){ $('.cfp-series').each(function(){ const $r=$(this); loadSeries($r); }); });
  $(document).on('click', '.cfp-series .cfp-series-buy', function(e){ e.preventDefault(); const $r=$(this).closest('.cfp-series'); const id=parseInt($(this).closest('.cfp-series-card').data('id'),10)||0; if(id){ checkoutSeries($r,id); }});
  $(document).on('click', '.cfp-series .cfp-series-wait', async function(e){ e.preventDefault(); const $r=$(this).closest('.cfp-series'); const $msg=$r.find('.cfp-msg').empty(); const id=parseInt($(this).closest('.cfp-series-card').data('id'),10)||0; if(!id) return; try{ const res=await fetch(CFP_DATA.restUrl+'series/waitlist_join',{method:'POST',headers:headers($r),body:JSON.stringify({series_id:id})}); const d=await res.json(); if(!res.ok){ if(res.status===401){ const ret=encodeURIComponent(window.location.href); const login=(window.cfpLoginUrl||('/wp-login.php?redirect_to='+ret)); $msg.html('Please <a href="'+login+'">log in</a> to join the waitlist.'); return;} $msg.text((d&&d.message)||'Failed to join waitlist.'); return;} $msg.text('Added to waitlist. We will email you if a spot opens.'); }catch(e){ $msg.text('Failed to join waitlist.'); } });
})(jQuery);
