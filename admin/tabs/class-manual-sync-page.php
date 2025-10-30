<?php
namespace DtfReseller\Admin\Tabs;

use DtfReseller\Admin\CommonFunctions;

class ManualSyncPage
{
    public function __construct()
    {
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    public function render()
    {
        // Get all sites in the network
        $sites = get_sites();

        // Get all products from main site
        switch_to_blog(get_main_site_id());
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish'
        ));
        restore_current_blog();
        ?>
        <form method="post">
            <?php wp_nonce_field('dtfreseller_manual', 'dtfreseller_manual_nonce'); ?>
            <h2 class="dtfreseller-tab-title">Manual Product Sync</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Select Products</th>
                    <td>
                        <div class="dtf_select2__container">
                            <select name="products[]" class="select2" multiple="multiple" style="width: 100%;">
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>">
                                        <?php echo esc_html($product->get_name()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="description">Select products to sync (multiple selection allowed)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Select Target Sites</th>
                    <td>
                        <div class="dtf_select2__container">
                            <select name="sites[]" class="select2" multiple="multiple" style="width: 100%;">
                                <?php foreach ($sites as $site):
                                    if ($site->blog_id != get_main_site_id()): ?>
                                        <option value="<?php echo esc_attr($site->blog_id); ?>">
                                            <?php echo esc_html($site->blogname . ' (' . $site->domain . $site->path . ')'); ?>
                                        </option>
                                    <?php endif;
                                endforeach; ?>
                            </select>
                        </div>
                        <p class="description">Select sites to sync to (multiple selection allowed)</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Sync Selected Products', 'primary', 'sync_products'); ?>
        </form>
        <?php
    }

    public function handle_form_submissions()
    {
        // Handle manual sync
        if (
            is_network_admin() &&
            isset($_GET['page']) &&
            $_GET['page'] === 'dtfreseller' &&
            (!isset($_GET['tab']) || $_GET['tab'] === 'manual-sync') &&
            isset($_POST['sync_products']) &&
            check_admin_referer('dtfreseller_manual', 'dtfreseller_manual_nonce')
        ) {
            $products = isset($_POST['products']) ? array_map('intval', $_POST['products']) : array();
            $sites = isset($_POST['sites']) ? array_map('intval', $_POST['sites']) : array();

            if (empty($products) || empty($sites)) {
                add_action('admin_notices', function () {
                    echo '<div class="error"><p>Please select both products and target sites.</p></div>';
                });
                return;
            }

            $synced = CommonFunctions::sync_selected_products($products, $sites);

            if ($synced['success']) {
                CommonFunctions::add_notice(
                    'updated',
                    sprintf(
                        'Successfully synced %d products to %d sites.',
                        $synced['count'],
                        count($synced['sites'])
                    )
                );
            } else {
                CommonFunctions::add_notice('error', 'Error occurred during sync: ' . esc_html($synced['message']));
            }
        }
    }
}