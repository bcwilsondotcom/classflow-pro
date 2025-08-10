/* global inlineEditPost, jQuery */
(function ($) {
  // Extend Quick Edit to populate our custom fields
  var $wp_inline_edit = inlineEditPost.edit;
  inlineEditPost.edit = function (id) {
    $wp_inline_edit.apply(this, arguments);

    var postId = 0;
    if (typeof id === 'object') {
      postId = parseInt(this.getId(id), 10);
    }
    if (!postId) return;

    var $row = $('#post-' + postId);
    var $editRow = $('#edit-' + postId);
    if (!$row.length || !$editRow.length) return;

    // Instructors
    if ($row.hasClass('type-cfp_instructor')) {
      var $data = $row.find('.cfp-inline').first();
      if ($data.length) {
        $editRow.find('input[name="cfp_payout_percent"]').val($data.data('payout'));
        $editRow.find('input[name="cfp_stripe_account_id"]').val($data.data('stripe'));
        $editRow.find('input[name="cfp_email"]').val($data.data('email'));
      }
    }

    // Resources
    if ($row.hasClass('type-cfp_resource')) {
      var $dataR = $row.find('.cfp-inline').first();
      if ($dataR.length) {
        $editRow.find('input[name="cfp_capacity"]').val($dataR.data('capacity'));
      }
    }

    // Classes (optional future fields)
    if ($row.hasClass('type-cfp_class')) {
      var $dataC = $row.find('.cfp-inline').first();
      if ($dataC.length) {
        $editRow.find('input[name="cfp_duration_mins"]').val($dataC.data('duration'));
        $editRow.find('input[name="cfp_capacity"]').val($dataC.data('capacity'));
        $editRow.find('input[name="cfp_price_cents"]').val($dataC.data('price'));
        $editRow.find('input[name="cfp_currency"]').val($dataC.data('currency'));
      }
    }
  };
})(jQuery);
