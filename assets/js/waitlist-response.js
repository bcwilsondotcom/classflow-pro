/* global CFP_DATA, jQuery */
(function($){
  function msg($root, html){ $root.find('.cfp-msg').html(html); }
  async function call(path, body){
    const res = await fetch(CFP_DATA.restUrl + path, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body||{}) });
    const js = await res.json().catch(()=>({}));
    return { ok: res.ok, data: js };
  }
  $(function(){
    $('.cfp-waitlist-response').each(async function(){
      const $root=$(this); const p=new URLSearchParams(window.location.search);
      const action=(p.get('action')||'').toLowerCase(); const token=(p.get('token')||'').toString();
      if (!token || (action!=='accept' && action!=='deny')){ msg($root, 'Invalid or missing link.'); return; }
      msg($root, 'One moment…');
      if (action==='accept'){
        const {ok,data}=await call('waitlist/accept', { token });
        if (!ok){ msg($root, (data && data.message) ? data.message : 'Unable to accept.'); return; }
        if (data.checkout_url){ window.location.href = data.checkout_url; return; }
        msg($root, 'Your seat has been confirmed. See you in class!');
      } else {
        const {ok,data}=await call('waitlist/deny', { token });
        if (!ok){ msg($root, (data && data.message) ? data.message : 'Unable to decline.'); return; }
        msg($root, 'You have declined the spot. Thank you.');
      }
    });
  });
})(jQuery);

