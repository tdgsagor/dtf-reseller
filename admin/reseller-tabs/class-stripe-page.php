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
    }
    public function render()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce
            if (!isset($_POST['smc_nonce']) || !wp_verify_nonce($_POST['smc_nonce'], 'smc_save_settings')) {
                echo '<div class="error"><p>Nonce verification failed. Please try again.</p></div>';
                return;
            }

            // Save settings logic here
            if (isset($_POST['smc_client_id'])) {
                update_option('smc_client_id', sanitize_text_field($_POST['smc_client_id']));
                echo '<div class="updated"><p>Settings saved.</p></div>';
            }
        }
        ?>
        <div class="wrap">


            <!-- 🔽 Add this block below -->
            <div class="notice notice-info"
                style="margin-bottom: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #007cba;">
                <p style="margin: 0 0 10px;">To create a Stripe Connect account, click the button below.</p>
                <a href="#" class="button button-primary">Create Stripe Connect</a>
            </div>
            <!-- 🔼 End of new block -->

            <h1 class="dtfreseller-tab-title">Subsite Stripe Connect Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('smc_save_settings', 'smc_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="smc_client_id">Stripe Connect Client ID</label></th>
                        <td>
                            <div class="toggle-password-container">
                                <input type="password" name="smc_client_id" id="smc_client_id"
                                    value="<?php echo esc_attr(get_option('smc_client_id')); ?>" class="regular-text">
                                <button type="button" class="toggle-password dashicons dashicons-visibility"
                                    data-target="smc_client_id"></button>
                            </div>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Client ID', 'primary', 'smc_save_button', false); ?>
            </form>
        </div>
        <?php
    }
}
