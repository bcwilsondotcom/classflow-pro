/* global CFP_DATA, jQuery */
(function ($) {
  function headers($root) {
    return { 'X-WP-Nonce': $root.data('nonce') };
  }

  function money(cents, currency) {
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: (currency || 'USD').toUpperCase() }).format((cents || 0) / 100); }
    catch (e) { return ((cents || 0)/100).toFixed(2) + ' ' + (currency || 'USD'); }
  }

  async function loadOverview($root) {
    const res = await fetch(CFP_DATA.restUrl + 'me/overview', { headers: headers($root) });
    const data = await res.json();
    if (!res.ok) { $root.find('.cfp-msg').text(data.message || 'Failed to load dashboard'); return; }
    $root.find('.cfp-credits').text(data.credits);
    // Packages
    const $pkg = $('<div class="cfp-packages"></div>');
    if (data.packages && data.packages.length) {
      const t = $('<table class="widefat"><thead><tr><th>Name</th><th>Credits</th><th>Remaining</th><th>Price</th><th>Expires</th></tr></thead><tbody></tbody></table>');
      data.packages.forEach(p => {
        const price = money(p.price_cents, p.currency);
        t.find('tbody').append('<tr><td>' + p.name + '</td><td>' + p.credits + '</td><td>' + p.credits_remaining + '</td><td>' + price + '</td><td>' + (p.expires_at || '—') + '</td></tr>');
      });
      $pkg.append('<h4>My Packages</h4>').append(t);
    }
    $root.find('.cfp-kpis').after($pkg);
    const $up = $root.find('.cfp-upcoming').empty();
    if (!data.upcoming || !data.upcoming.length) {
      $up.append('<p>No upcoming bookings.</p>');
    } else {
      data.upcoming.forEach(b => {
        const tz = b.timezone || CFP_DATA.businessTimezone || 'UTC';
        const start = new Date(b.start_time + 'Z').toLocaleString(undefined, { timeZone: tz });
        const price = b.credits_used ? 'Credit' : money(b.amount_cents, b.currency);
        const $row = $('<div class="cfp-item" style="display:flex;justify-content:space-between;gap:8px;border:1px solid #e2e8f0;padding:8px;border-radius:4px;margin:6px 0;"></div>');
        var loc = b.location_name ? (' — ' + b.location_name) : '';
        $row.append('<div><strong>' + b.class_title + '</strong><br><small>' + start + loc + '</small></div>');
        const $actions = $('<div></div>');
        $actions.append('<span style="margin-right:8px;">' + price + '</span>');
        const $cancel = $('<button class="button">Cancel</button>');
        $cancel.on('click', () => cancelBooking($root, b.id));
        $actions.append($cancel);
        const $res = $('<button class="button">Reschedule</button>');
        $res.on('click', async () => openReschedule($root, b));
        $actions.append($res);
        $row.append($actions);
        $up.append($row);
      });
    }

    const $past = $root.find('.cfp-past').empty();
    if (!data.past || !data.past.length) {
      $past.append('<p>No past bookings.</p>');
    } else {
      data.past.forEach(b => {
        const tz = b.timezone || CFP_DATA.businessTimezone || 'UTC';
        const start = new Date(b.start_time + 'Z').toLocaleString(undefined, { timeZone: tz });
        const price = b.credits_used ? 'Credit' : money(b.amount_cents, b.currency);
        var loc = b.location_name ? (' — ' + b.location_name) : '';
        const row = $('<div class="cfp-item" style="border:1px solid #e2e8f0;padding:8px;border-radius:4px;margin:6px 0;"></div>');
        row.append('<strong>' + b.class_title + '</strong><br><small>' + start + loc + '</small><div>' + price + ' — ' + b.status + '</div>');
        const actions = $('<div style="margin-top:6px;"></div>');
        if (b.receipt_url) {
          actions.append('<a class="button" target="_blank" rel="noopener" href="' + b.receipt_url + '">Stripe Receipt</a> ');
        }
        if (b.has_quickbooks_receipt) {
          const btn = $('<button class="button">QuickBooks Receipt (PDF)</button>');
          btn.on('click', async () => {
            const pdfRes = await fetch(CFP_DATA.restUrl + 'quickbooks/receipt?booking_id=' + b.id, { headers: headers($root) });
            if (!pdfRes.ok) { alert('Unable to download receipt'); return; }
            const blob = await pdfRes.blob();
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
            setTimeout(() => URL.revokeObjectURL(url), 10000);
          });
          actions.append(btn);
        }
        row.append(actions);
        $past.append(row);
      });
    }
  }

  async function cancelBooking($root, id) {
    if (!confirm('Cancel this booking?')) return;
    const res = await fetch(CFP_DATA.restUrl + 'bookings/' + id + '/cancel', { method: 'POST', headers: headers($root) });
    const data = await res.json();
    if (!res.ok) {
      alert(data.message || 'Unable to cancel.');
      return;
    }
    $root.find('.cfp-msg').text('Booking ' + id + ' ' + data.status + '.');
    loadOverview($root);
  }

  async function rescheduleBooking($root, id, scheduleId) {
    const res = await fetch(CFP_DATA.restUrl + 'bookings/' + id + '/reschedule', {
      method: 'POST', headers: headers($root), body: JSON.stringify({ schedule_id: scheduleId })
    });
    const data = await res.json();
    if (!res.ok) {
      alert(data.message || 'Unable to reschedule.');
      return;
    }
    $root.find('.cfp-msg').text('Booking ' + id + ' rescheduled.');
    loadOverview($root);
  }

  async function openReschedule($root, booking) {
    const container = $('<div class="cfp-reschedule" style="margin-top:8px;"></div>');
    container.append('<div>Loading available times…</div>');
    const url = new URL(CFP_DATA.restUrl + 'schedules/available');
    const today = new Date().toISOString().slice(0,10);
    url.searchParams.set('class_id', booking.class_id);
    url.searchParams.set('date_from', today);
    const resp = await fetch(url.toString());
    const rows = await resp.json();
    container.empty();
    if (!rows || !rows.length) { container.append('<div>No future times found.</div>'); return; }
    const select = $('<select class="cfp-target"></select>');
    rows.forEach(r => {
      if (r.id === booking.schedule_id) return; // skip current
      const label = new Date(r.start_time + 'Z').toLocaleString() + ' — ' + (r.instructor_name || '');
      const suffix = ' (' + (r.seats_left || 0) + ' left)';
      select.append('<option value="' + r.id + '">' + label + suffix + '</option>');
    });
    if (!select.children().length) { container.append('<div>No alternative times available.</div>'); return; }
    const btn = $('<button class="button button-primary" style="margin-left:8px;">Confirm</button>');
    btn.on('click', async () => {
      const targetId = parseInt(select.val(), 10);
      if (!targetId) return;
      await rescheduleBooking($root, booking.id, targetId);
      container.remove();
    });
    container.append(select).append(btn);
    // Attach under corresponding row
    const list = $root.find('.cfp-upcoming');
    list.prepend(container);
  }

  $(function () {
    $('.cfp-client-dashboard').each(function () { loadOverview($(this)); });
  });
})(jQuery);
