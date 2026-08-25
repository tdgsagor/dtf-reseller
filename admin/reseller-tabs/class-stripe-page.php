<?php
namespace DtfReseller\Admin\ResellerTabs;

class ResellerStripePage
{
    public function __construct()
    {
        add_filter('woocommerce_payment_gateways', function ($gateways) {
            require_once DTFRESELLER_SYNC_PATH . 'includes/class-wc-gateway-tdg-payment.php';
            $gateways[] = 'WC_Gateway_TDG_Payment';
            return $gateways;
        });

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_w2i_sc_create_account', [$this, 'ajax_create_account']);
        add_action('init', [$this, 'handle_stripe_return']);
    }
    public function enqueue_assets()
    {
        if (
            empty($_GET['page']) ||
            $_GET['page'] !== 'dtfreseller-resellers' ||
            ($_GET['tab'] ?? '') !== 'stripe'
        ) {
            return;
        }

        wp_enqueue_script(
            'dtfreseller-stripe-connect',
            DTFRESELLER_SYNC_URL . 'admin/assets/js/stripe-connect.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('dtfreseller-stripe-connect', 'w2iSC', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            '
            nce' => wp_create_nonce('w2i_sc_nonce'),
        ]);
    }

    public function render()
    {
        $client_id = get_option('smc_client_id');
        $connected = get_option('smc_stripe_connected') === 'yes';
        ?>
        <div class="wrap">

            <h1 class="dtfreseller-tab-title">Subsite Stripe Connect Settings</h1>

            <div class="">

                <?php if ($client_id && $connected): ?>
                    <p style="margin:0;">
                        ✅ <strong>Stripe Connected</strong><br>
                        <code><?php echo esc_html($client_id); ?></code>
                    </p>
                <?php else: ?>
                    <p style="margin:0 0 10px;">
                        Connect your Stripe account to receive payments.
                    </p>
                    <button id="w2i-connect-stripe" class="button button-primary">
                        Connect with Stripe
                    </button>
                <?php endif; ?>

            </div>

        </div>
        <?php
    }

    public function ajax_create_account()
    {
        check_ajax_referer('w2i_sc_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Get Stripe key from MAIN SITE
        switch_to_blog(1);
        $secret_key = get_option('smc_stripe_secret_key');
        restore_current_blog();

        if (!$secret_key) {
            wp_send_json_error('Stripe secret key missing');
        }

        // Create Stripe Express account
        $account = wp_remote_post('https://api.stripe.com/v1/accounts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'type' => 'express',
                'capabilities[card_payments][requested]' => 'true',
                'capabilities[transfers][requested]' => 'true',
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($account)) {
            wp_send_json_error($account->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($account), true);

        if (empty($body['id'])) {
            wp_send_json_error('Stripe account creation failed');
        }

        // Save client ID in SUBSITE
        update_option('smc_client_id', sanitize_text_field($body['id']));
        delete_option('smc_stripe_connected');

        // Create onboarding link
        $link = wp_remote_post('https://api.stripe.com/v1/account_links', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'account' => $body['id'],
                'refresh_url' => admin_url('admin.php?page=dtfreseller-resellers&tab=stripe'),
                'return_url' => home_url('/stripe-return'),
                'type' => 'account_onboarding',
            ]),
            'timeout' => 30,
        ]);

        $link_body = json_decode(wp_remote_retrieve_body($link), true);

        if (empty($link_body['url'])) {
            wp_send_json_error('Failed to create onboarding link');
        }

        wp_send_json_success([
            'url' => $link_body['url'],
        ]);
    }

    public function handle_stripe_return()
    {
        if (
            empty($_SERVER['REQUEST_URI']) ||
            strpos($_SERVER['REQUEST_URI'], 'stripe-return') === false
        ) {
            return;
        }

        $client_id = get_option('smc_client_id');

        if (!$client_id) {
            wp_safe_redirect(
                admin_url('admin.php?page=dtfreseller-resellers&tab=stripe&status=failed')
            );
            exit;
        }

        update_option('smc_stripe_connected', 'yes');

        wp_safe_redirect(
            admin_url('admin.php?page=dtfreseller-resellers&tab=stripe&status=connected')
        );
        exit;
    }
}
