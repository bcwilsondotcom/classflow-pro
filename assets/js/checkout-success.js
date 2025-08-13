/* global CFP_DATA, jQuery */
(function($){
  function msg($root, html){ $root.find('.cfp-msg').html(html); }
  async function checkIntakeAndPrompt($root){
    try{
      if (!CFP_DATA.isLoggedIn) return; // best-effort only
      const res = await fetch(CFP_DATA.restUrl + 'me/intake', { headers: { 'X-WP-Nonce': CFP_DATA.nonce } });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.data) {
        const url = CFP_DATA.intakePageUrl || '#';
        const ret = encodeURIComponent(window.location.href);
        if (url && url !== '#') {
          const link = '<a class="button" href="'+url+(url.indexOf('?')>=0?'&':'?')+'return='+ret+'">Complete Intake now</a>';
          $root.find('.cfp-msg').append('<div style="margin-top:8px;">' + 'Please complete your intake before your first visit. ' + link + '</div>');
        }
      }
    }catch(e){}
  }
  $(function(){
    $('.cfp-checkout-success').each(function(){
      const $root=$(this);
      const p=new URLSearchParams(window.location.search);
      const st=(p.get('cfp_checkout')||'').toLowerCase();
      const type=(p.get('type')||'').toLowerCase();
      if (st==='success'){
        msg($root, 'Thank you! Your checkout completed successfully.');
        checkIntakeAndPrompt($root);
        try{
          // GA4 dataLayer event
          window.dataLayer = window.dataLayer || [];
          const amount = parseFloat(p.get('amount')||'');
          const currency = (p.get('currency')||'').toUpperCase();
          const payload = { event: 'cfp_checkout_success', cfp_type: type||'unknown' };
          if (!isNaN(amount)) { payload.value = amount; }
          if (currency) { payload.currency = currency; }
          window.dataLayer.push(payload);
          // Facebook Pixel (optional)
          if (typeof window.fbq === 'function') {
            if (!isNaN(amount) && currency) {
              window.fbq('track', 'Purchase', { value: amount, currency: currency, contents: [{ id: type||'unknown', quantity: 1 }], content_type: 'product' });
            } else {
              window.fbq('trackCustom', 'CFPCheckoutSuccess', { type: type||'unknown' });
            }
          }
        }catch(e){}
      } else if (st==='cancel'){
        msg($root, 'Checkout was canceled. You can try again anytime.');
        try{
          window.dataLayer = window.dataLayer || [];
          window.dataLayer.push({ event: 'cfp_checkout_cancel', cfp_type: type||'unknown' });
          if (typeof window.fbq === 'function') {
            window.fbq('trackCustom', 'CFPCheckoutCancel', { type: type||'unknown' });
          }
        }catch(e){}
      } else {
        msg($root, 'Welcome.');
      }
    });
  });
})(jQuery);
