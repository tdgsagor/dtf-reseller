if (typeof wcBlocks !== 'undefined') {
    const { registerPaymentMethod } = wcBlocks;

    registerPaymentMethod('tdg_payment', {
        init: (paymentMethodId, gatewayId, updatePaymentMethod) => {
            // Find the container rendered by your gateway
            const container = document.getElementById('tdg-stripe-form');
            if (!container) return;

            const stripe = Stripe(my_custom_checkout_params.stripe_key);
            const elements = stripe.elements();
            const card = elements.create('card');
            card.mount('#card-element');

            const errorEl = document.getElementById('card-errors');
            card.on('change', function (event) {
                if (errorEl) errorEl.textContent = event.error ? event.error.message : '';
            });

            return {
                submit: () => {
                    // This will be called by block checkout when the order is submitted
                    return stripe.createToken(card).then(function (result) {
                        if (result.error) {
                            return Promise.reject(result.error.message);
                        }
                        // Return token to WooCommerce Blocks
                        return { tdg_stripe_token: result.token.id };
                    });
                },
            };
        },
        supports: ['products', 'default_credit_card_form'],
    });
}
