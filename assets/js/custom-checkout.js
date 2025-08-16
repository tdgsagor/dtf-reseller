jQuery(function ($) {
    let stripe, card, stripeToken = null;

    function initStripe() {
        if (!stripe) {
            stripe = Stripe(my_custom_checkout_params.stripe_key);
            const elements = stripe.elements();
            card = elements.create('card');
            card.mount('#card-element');

            card.on('change', function (event) {
                $('#card-errors').text(event.error ? event.error.message : '');
            });
        }
    }

    // Mount Stripe elements when payment method is selected
    $(document.body).on('change', 'input[name="payment_method"]', function () {
        if ($(this).val() === 'tdg_payment') {
            setTimeout(initStripe, 100); // wait for DOM render
        }
    });

    // This blocks WooCommerce from submitting until we’re ready
    $('form.checkout').on('checkout_place_order_tdg_payment', function () {
        if (!stripe || !card) return true; // just in case

        const $form = $(this);

        // Already got token? Let it submit
        if (stripeToken) return true;

        stripe.createToken(card).then(function (result) {
            console.log(result)
            if (result.error) {
                $('#card-errors').text(result.error.message);
                stripeToken = null;
                $form.removeClass('processing').unblock();
            } else {
                // Append token and resubmit
                stripeToken = result.token.id;
                $('<input type="hidden" name="tdg_stripe_token">')
                    .val(stripeToken)
                    .appendTo($form);

                $form.submit(); // this time it'll skip the token step
            }
        });

        return false; // block original submission until token created
    });
});
