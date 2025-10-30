<?php
namespace DtfReseller\Admin\Tabs;

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SitePage extends \WP_List_Table
{
    private $sites = [];
    private $initialized = false;

    public function __construct()
    {
        // add_action('wp_ajax_dtfreseller_set_status', [$this, 'ajax_set_status']);
    }

    private function init()
    {
        if ($this->initialized) {
            return;
        }

        parent::__construct([
            'singular' => 'site',
            'plural' => 'sites',
            'ajax' => false
        ]);

        $this->initialized = true;
    }

    public function get_columns()
    {
        return [
            'id' => __('ID', 'dtfreseller'),
            'blogname' => __('Site Name', 'dtfreseller'),
            'domain' => __('Domain', 'dtfreseller'),
            'path' => __('Path', 'dtfreseller'),
            'status' => __('Status', 'dtfreseller'),
            'subscription' => __('Subscription', 'dtfreseller'),
            'status_control' => __('Set Status', 'dtfreseller'),
            'action' => __('Action', 'dtfreseller')
        ];
    }

    public function prepare_items()
    {
        $this->init(); // make sure parent constructor is called

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];

        $this->_column_headers = [$columns, $hidden, $sortable];

        $sites = get_sites(['number' => 0]);

        $this->sites = [];

        foreach ($sites as $site) {
            if ((int) $site->blog_id === get_main_site_id()) {
                continue;
            }
            $details = get_blog_details($site->blog_id);
            $status = get_blog_option($site->blog_id, 'dtfreseller_status', 'Active');

            // Get first admin user of this site
            $admins = get_users([
                'blog_id' => $site->blog_id,
                'role' => 'administrator',
                'number' => 1
            ]);

            $admin_user = !empty($admins) ? $admins[0] : null;
            $subscription_status = 'No User';

            if ($admin_user) {
                // Switch to main site
                switch_to_blog(get_main_site_id());

                if (function_exists('wcs_user_has_subscription')) {
                    if (wcs_user_has_subscription($admin_user->ID, '', 'active')) {
                        $subscription_status = 'Active';
                    } else {
                        $subscription_status = 'Inactive';
                    }
                }

                restore_current_blog();
            }

            $this->sites[] = [
                'id' => $site->blog_id,
                'blogname' => $details->blogname,
                'domain' => $details->domain,
                'path' => $details->path,
                'status' => $status,
                'subscription' => $subscription_status,
                'status_control' => $status,
            ];
        }

        $this->items = $this->sites;
    }

    public function column_status_control($item)
    {
        $blog_id = $item['id'];
        $current_status = $item['status_control'];

        // Render select field
        $html = '<select class="dtf-site-status" data-blog-id="' . esc_attr($blog_id) . '">';
        $html .= '<option value="Active"' . selected($current_status, 'Active', false) . '>Active</option>';
        $html .= '<option value="Inactive"' . selected($current_status, 'Inactive', false) . '>Inactive</option>';
        $html .= '</select>';

        return $html;
    }

    public function column_action($item)
    {
        $blog_id = $item['id'];

        $button = '<button class="button dtf-restore-db" data-blog-id="' . esc_attr($blog_id) . '">';
        $button .= esc_html__('Restore DB Tables', 'dtfreseller');
        $button .= '</button>';

        return $button;
    }

    public function column_blogname($item)
    {
        $name = esc_html($item['blogname']);
        $blog_id = $item['id'];

        if ($item['status'] === 'Inactive') {
            return $name . ' <span style="color:red;">(Inactive)</span>';
        } else {
            $url = get_home_url($blog_id);
            return '<a href="' . esc_url($url) . '" target="_blank">' . $name . '</a>';
        }
    }

    public function column_default($item, $column_name)
    {
        if ($column_name === 'status') {
            if ($item['status'] === 'Active') {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#28a745;color:#fff;font-size:12px;">Active</span>';
            } else {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#dc3545;color:#fff;font-size:12px;">Inactive</span>';
            }
        }

        if ($column_name === 'subscription') {
            if ($item['subscription'] === 'Active') {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#28a745;color:#fff;font-size:12px;">Active</span>';
            } elseif ($item['subscription'] === 'Inactive') {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#dc3545;color:#fff;font-size:12px;">Inactive</span>';
            } else {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#6c757d;color:#fff;font-size:12px;">' . esc_html($item['subscription']) . '</span>';
            }
        }

        return $item[$column_name] ?? '';
    }

    public function render()
    {
        $this->init();      // ensure parent constructor is called
        $this->prepare_items();
        ?>
        <div class="wrap">
            <h2><?php esc_html_e('Sites Manager', 'dtfreseller'); ?></h2>
            <form method="post">
                <?php $this->display(); ?>
            </form>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('.dtf-site-status').change(function () {
                    var blog_id = $(this).data('blog-id');
                    var status = $(this).val();

                    $.post(ajaxurl, {
                        action: 'dtfreseller_set_status',
                        blog_id: blog_id,
                        status: status,
                        _wpnonce: '<?php echo wp_create_nonce("dtfreseller-status-nonce"); ?>'
                    }, function (response) {
                        if (response.success) {
                            // Reload page on success
                            location.reload();
                        } else {
                            alert("Error: " + response.data.message);
                        }
                    });
                });

                $('.dtf-restore-db').click(function () {
                    var blog_id = $(this).data('blog-id');

                    if (!confirm('Are you sure you want to restore DB tables for this site?')) return;

                    $.post(ajaxurl, {
                        action: 'dtfreseller_restore_tables',
                        blog_id: blog_id,
                        _wpnonce: '<?php echo wp_create_nonce("dtfreseller-restore-tables-nonce"); ?>'
                    }, function (response) {
                        if (response.success) {
                            alert('Tables restored successfully!');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                });
            });
        </script>
        <?php
    }
}
