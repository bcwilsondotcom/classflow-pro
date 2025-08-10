/* global CFP_DATA, jQuery */
(function ($) {
  function headers($root) { return { 'Content-Type': 'application/json', 'X-WP-Nonce': $root.data('nonce') }; }

  async function load($root) {
    const res = await fetch(CFP_DATA.restUrl + 'me/intake', { headers: headers($root) });
    if (!res.ok) return;
    const d = await res.json();
    if (!d || !d.data) return;
    const f = d.data;
    $root.find('.cfp-phone').val(f.phone || '');
    $root.find('.cfp-dob').val(f.dob || '');
    $root.find('.cfp-emg-name').val(f.emergency_name || '');
    $root.find('.cfp-emg-phone').val(f.emergency_phone || '');
    $root.find('.cfp-med').val(f.medical || '');
    $root.find('.cfp-inj').val(f.injuries || '');
    $root.find('.cfp-preg').prop('checked', !!f.pregnant);
  }

  async function submit($root) {
    const body = {
      phone: ($root.find('.cfp-phone').val() || '').toString(),
      dob: ($root.find('.cfp-dob').val() || '').toString(),
      emergency_name: ($root.find('.cfp-emg-name').val() || '').toString(),
      emergency_phone: ($root.find('.cfp-emg-phone').val() || '').toString(),
      medical: ($root.find('.cfp-med').val() || '').toString(),
      injuries: ($root.find('.cfp-inj').val() || '').toString(),
      pregnant: $root.find('.cfp-preg').is(':checked'),
      signature: ($root.find('.cfp-sign').val() || '').toString(),
      consent: $root.find('.cfp-consent').is(':checked'),
    };
    const $msg = $root.find('.cfp-msg').empty();
    if (!body.signature || !body.consent) { $msg.text('Signature and consent required.'); return; }
    const res = await fetch(CFP_DATA.restUrl + 'me/intake', { method: 'POST', headers: headers($root), body: JSON.stringify(body) });
    const d = await res.json();
    if (!res.ok) { $msg.text(d.message || 'Submission failed.'); return; }
    $msg.text('Intake submitted. Thank you!');
  }

  $(function(){
    $('.cfp-intake').each(function(){ load($(this)); });
  });
  $(document).on('click', '.cfp-intake-submit', function(e){ e.preventDefault(); submit($(this).closest('.cfp-intake')); });
})(jQuery);

