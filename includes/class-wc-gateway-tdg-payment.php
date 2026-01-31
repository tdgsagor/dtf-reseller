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

        $default_reseller_margin = get_option('dtfr_default_product_margin', 0);
        $application_fee = 0;

        foreach ($order->get_items() as $item) {
            $line_total = $item->get_total();

            $_original_product_id = get_post_meta($item->get_product_id(), '_original_product_id', true);
            
            if ($_original_product_id) {
                $reseller_margin = get_post_meta($item->get_product_id(), '_ms_price_margin', true);
                $reseller_margin = $reseller_margin ? $reseller_margin : $default_reseller_margin;
                // Direct charges: platform fee equals base price before reseller margin
                // base_price = final_price / (1 + margin%)
                $base_price = $reseller_margin > -100 ? ($line_total / (1 + ($reseller_margin / 100))) : $line_total;
                $application_fee += $base_price;
            } else {
                switch_to_blog(get_main_site_id());
                $main_site_commission = get_site_option('reseller_product_comission');
                restore_current_blog();
                $application_fee += $line_total * ($main_site_commission) / 100;
            }


        }

        require_once plugin_dir_path(__FILE__) . 'stripe-api.php'; // if not autoloaded

        \Stripe\Stripe::setApiKey(get_blog_option(1, 'smc_stripe_secret_key')); // save this via admin settings

        try {
            $connected_account_id = get_option('smc_client_id');

            if (empty($connected_account_id)) {
                wc_add_notice('Payment error: Connected account is not configured.', 'error');
                return;
            }

            // Direct charge on the connected account; processing fees billed to connected account
            $charge = \Stripe\Charge::create([
                'amount' => intval($order->get_total() * 100),
                'currency' => strtolower(get_woocommerce_currency()),
                'source' => sanitize_text_field($_POST['tdg_stripe_token']),
                'description' => 'Order #' . $order_id,
                'application_fee_amount' => intval($application_fee * 100)
            ], [
                'stripe_account' => $connected_account_id
            ]);

            $order->payment_complete();

            // Save order meta
            $order->update_meta_data('application_fee', $application_fee);
            $order->update_meta_data('reseller_fee', $order->get_total() - $application_fee);
            $order->save();

            return ['result' => 'success', 'redirect' => $this->get_return_url($order)];
        } catch (Exception $e) {
            wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
            return;
        }
    }
}