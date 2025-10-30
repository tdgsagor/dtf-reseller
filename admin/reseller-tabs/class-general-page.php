<?php
namespace DtfReseller\Admin\ResellerTabs;

use DtfReseller\Admin\CommonFunctions;

class ResellerGeneralPage
{
    public function __construct()
    {
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }

    public function render()
    {
        ?>
        <form method="post">
            <?php wp_nonce_field('dtfreseller_reseller_settings', 'dtfreseller_reseller_settings_nonce'); ?>
            <h2 class="dtfreseller-tab-title">Global Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="dtfr_default_product_margin">Default Product Margin (%)</label></th>
                    <td>
                        <div>
                            <input type="number" id="dtfr_default_product_margin" name="dtfr_default_product_margin"
                                value="<?php echo esc_attr(get_option('dtfr_default_product_margin')); ?>" class="regular-text">
                        </div>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Global Settings', 'primary', 'save_general_submit'); ?>
        </form>
        <?php
    }

    public function handle_form_submissions()
    {
        // if (isset($_POST['submit']) && check_admin_referer('dtfreseller_reseller_settings')) {
        if (
            isset($_GET['page']) &&
            $_GET['page'] === 'dtfreseller-resellers' &&
            (!isset($_GET['tab']) || $_GET['tab'] === 'general') &&
            isset($_POST['save_general_submit']) &&
            check_admin_referer('dtfreseller_reseller_settings', 'dtfreseller_reseller_settings_nonce')
        ) {
            update_option('dtfreseller_enable_products', isset($_POST['enable_product_sync']) ? 1 : 0);
            update_option('dtfreseller_enable_orders', isset($_POST['enable_order_sync']) ? 1 : 0);

            $margin = isset($_POST['dtfr_default_product_margin']) ? sanitize_text_field($_POST['dtfr_default_product_margin']) : 0;
            update_option('dtfr_default_product_margin', $margin);

            CommonFunctions::add_notice('updated', 'Settings saved successfully.');
        }
    }
}
