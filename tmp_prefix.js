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

  // Remove selected pill handler (delegated)
  $(document).on('click', '.cfp-selected-remove', function(e){
    e.preventDefault();
    const $pill = $(this).closest('.cfp-selected-pill');
    const sid = parseInt($pill.data('sid')||0,10);
    const $root = $(this).closest('.cfp-calendar-booking');
    if (!sid || !$root.length) return;
    let sel = $root.data('selected') || [];
    sel = sel.filter(x => x !== sid);
    $root.data('selected', sel);
    // Remove pill immediately
    $pill.remove();
    // Clear selection highlight
    const selector='[data-sid="'+sid+'"]';
    $root.find('.cfp-cal-event'+selector+', .cfp-agenda-item'+selector).removeClass('selected').css('box-shadow','');
    // Re-render panel to keep consistent if multiple
    updateSelectedClassesDisplay($root, sel);
    if (sel.length===0) { $root.find('.cfp-book-selected').remove(); }
  });

  async function loadMonth($root, refDate) {
    $root.data('refDate', refDate.toISOString());
    const classId = $root.find('.cfp-filter-class').val() || $root.data('class-id') || '';
    const locId = $root.find('.cfp-filter-location').val() || $root.data('location-id') || '';
    const instrId = $root.find('.cfp-filter-instructor').val() || '';
    const from = fmtDateInput(firstDay(refDate));
})(jQuery);