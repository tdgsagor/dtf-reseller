<?php
if (!defined('ABSPATH'))
    exit;

class WC_Gateway_TDG_Payment extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'tdg_payment';
        $this->method_title = 'TDG Stripe Payment';
        $this->method_description = 'Stripe Connect for multisite vendors.';
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable TDG Payment',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'TDG Stripe Payment',
                'description' => 'Shown during checkout.',
            ],
            'description' => [
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Pay securely using your card via Stripe.',
            ]
        ];
    }

    public function payment_fields()
    {
        echo '<div id="tdg-stripe-form"><div id="card-element"></div><div id="card-errors" role="alert"></div></div>';
    }

    public function process_payment($order_id)
    {
        // wc_add_notice('My function is calling.', 'error');
        // return;
        $order = wc_get_order($order_id);

        if (empty($_POST['tdg_stripe_token'])) {
            wc_add_notice('Stripe token is missing.', 'error');
            return;
        }

        $total_paid = 0; // what customer paid
        $total_original = 0; // what customer would've paid using _original_product_price

        foreach ($order->get_items() as $item) {
            $qty = $item->get_quantity();
            $line_total = $item->get_total(); // actual line total (includes price * qty, excludes tax/shipping)

            $product = $item->get_product();
            if (!$product)
                continue;

            $product_id = $product->get_id();
            $original_price = get_post_meta($product_id, '_original_product_price', true);
            $original_price = floatval($original_price);

            $total_paid += $line_total;
            $total_original += ($original_price * $qty);
        }

        require_once plugin_dir_path(__FILE__) . 'stripe-api.php'; // if not autoloaded

        \Stripe\Stripe::setApiKey(get_blog_option(1, 'smc_stripe_secret_key')); // save this via admin settings

        try {
            $client_id = get_option('smc_client_id');
            $application_fee = intval($order->get_total() * 0.10 * 100); // 10% fee

            $charge = \Stripe\Charge::create([
                'amount' => intval($order->get_total() * 100),
                'currency' => strtolower(get_woocommerce_currency()),
                'source' => sanitize_text_field($_POST['tdg_stripe_token']),
                'description' => 'Order #' . $order_id,
                'application_fee_amount' => $total_original * 100,
                'transfer_data' => [
                    'destination' => $client_id
                ]
            ]);

            $order->payment_complete();
            return ['result' => 'success', 'redirect' => $this->get_return_url($order)];
        } catch (Exception $e) {
            wc_add_notice('Payment errorss: ' . $e->getMessage(), 'error');
            return;
        }
    }
}
