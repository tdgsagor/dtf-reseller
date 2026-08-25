jQuery(function ($) {
  $(document).on('click', '#w2i-connect-stripe', function () {
    const $btn = $(this);
    $btn.prop('disabled', true).text('Redirecting to Stripe...');

    $.post(w2iSC.ajaxurl, {
      action: 'w2i_sc_create_account',
      nonce: w2iSC.nonce
    })
      .done(function (res) {
        if (res.success && res.data.url) {
          window.location.href = res.data.url;
        } else {
          alert('Unable to start Stripe onboarding');
          $btn.prop('disabled', false).text('Connect with Stripe');
        }
      })
      .fail(function () {
        alert('Stripe request failed');
        $btn.prop('disabled', false).text('Connect with Stripe');
      });
  });
});
