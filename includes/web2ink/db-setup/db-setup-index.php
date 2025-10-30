<?php
require_once DTFRESELLER_SYNC_PATH . 'includes/web2ink/db-setup/db-setup-insert-default-data.php';
add_action('wpmu_new_blog', 'my_custom_subsite_setup', 10, 6);

add_action('wp_ajax_dtfreseller_restore_tables', function () {
    check_ajax_referer('dtfreseller-restore-tables-nonce');

    if (!current_user_can('manage_sites')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $blog_id = intval($_POST['blog_id']);

    if (!$blog_id) {
        wp_send_json_error(['message' => 'Invalid blog ID']);
    }

    // Call your existing function
    my_custom_subsite_setup($blog_id, get_current_user_id(), '', '', get_current_blog_id(), []);

    wp_send_json_success();
});

function my_custom_subsite_setup($blog_id, $user_id, $domain, $path, $site_id, $meta)
{
    switch_to_blog($blog_id);

    $tables = getInstallTables();
    $sqls = getInstallSQL();
    foreach ($tables as $index => $table_name) {
        addTable($table_name, $sqls[$index]);
    }
    insertDefaultData();

    restore_current_blog();
}
function addTable($table_name, $sql)
{
    global $wpdb;

    // Drop the table if exists
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . $table_name);

    // Create table
    $create_sql = "CREATE TABLE " . $wpdb->prefix . $sql . " " . $wpdb->get_charset_collate();
    return $wpdb->query($create_sql);
}
add_action('wpmu_drop_tables', function ($tables, $blog_id) {
    global $wpdb;

    // Add your custom tables to the list
    $custom_tables = getInstallTables();

    foreach ($custom_tables as $table) {
        $tables[] = $wpdb->get_blog_prefix($blog_id) . $table;
    }

    return $tables;
}, 10, 2);

function getInstallTables()
{
    return [
        'web2ink_settings_print_process',
        'web2ink_settings_print_process_price',
        // 'web2ink_settings_print_process_colors',
        // 'web2ink_settings_order_production',
        // 'web2ink_settings_order_status',
        // 'web2ink_settings_order_production_holidays',
        // 'web2ink_product_categories',
        // 'web2ink_profit_list',
        // 'web2ink_import',
        // 'web2ink_part_price'
    ];
}
function getInstallSQL()
{
    $sqls = array(
        "web2ink_settings_print_process (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `print_process_name` varchar(500) NOT NULL,
            `description` varchar(1200) NOT NULL,
            `count_ink_color` int(11) NOT NULL,
            `is_default` int(1) NOT NULL DEFAULT '0',
            `is_exclude` int(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        )",

        "web2ink_settings_print_process_price (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `process_id` int(11) NOT NULL,
            `qty` int(11) NOT NULL,
            `Flat_design` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Per_designed_side` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Color_setup` decimal(6,2) NOT NULL DEFAULT '0.00',
            `flat_charge` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Per_designed_side_two` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Per_designed_side_add` decimal(6,2) NOT NULL DEFAULT '0.00',
            `First_color` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Additional_color` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Per_color` varchar(600) NOT NULL,
            `Squar_unit` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Addside_discount` decimal(5,2) NOT NULL DEFAULT '0.00',      
            `Team_order_names` decimal(6,2) NOT NULL DEFAULT '0.00',
            `Team_order_numbers` decimal(6,2) NOT NULL DEFAULT '0.00',
            PRIMARY KEY (`id`)
        )",

        // "web2ink_settings_print_process_colors (
        //     `id` int(11) NOT NULL AUTO_INCREMENT,
        //     `process_id` int(11) NOT NULL,
        //     `colorname` varchar(50) NOT NULL,
        //     `hexcode` varchar(50) NOT NULL,
        //     `charge` decimal(6,2) NOT NULL DEFAULT '0.00',
        //     `image` varchar(160) NOT NULL,
        //     PRIMARY KEY (`id`)
        // )",

        // "web2ink_settings_order_production (
        //     `id` int(11) NOT NULL AUTO_INCREMENT,
        //     `title` varchar(500) CHARACTER SET utf8 NOT NULL,
        //     `description` text CHARACTER SET utf8 NOT NULL,
        //     `price` decimal(6,2) NOT NULL DEFAULT '0.00',
        //     `pricetype` enum('amount','amount-qty','percent') CHARACTER SET utf8 NOT NULL,
        //     `days` int(11) NOT NULL,
        //     `sort` int(11) NOT NULL DEFAULT '0',
        //     PRIMARY KEY (`id`)
        // )",

        // "web2ink_settings_order_status (
        //     `id` int(11) NOT NULL AUTO_INCREMENT,
        //     `title` varchar(500) NOT NULL,
        //     `status_key` varchar(500) NOT NULL,
        //     PRIMARY KEY (`id`)
        // )",

        // "web2ink_settings_order_production_holidays (
        //     `id` int(11) NOT NULL AUTO_INCREMENT,
        //     `day` int(11) NOT NULL,
        //     `month` int(11) NOT NULL,
        //     PRIMARY KEY (`id`)
        // )",

        // "web2ink_product_categories (
        //     `id` int(11) NOT NULL AUTO_INCREMENT,
        //     `import_cat_id` int(11) NOT NULL,
        //     `import_cat_name` varchar(500) CHARACTER SET utf8 NOT NULL,
        //     `import_cat_parent` int(11) NOT NULL,
        //     `import_cat_position` int(11) NOT NULL,
        //     `import_cat_repository` int(11) NOT NULL,
        //     `is_imported` int(11) NOT NULL DEFAULT '0',
        //     PRIMARY KEY (`id`)
        // )",

        // "web2ink_profit_list (
        //     `id` int(11) NOT NULL AUTO_INCREMENT,
        //     `design_id` int(11) NOT NULL,
        //     `order_id` int(11) NOT NULL,
        //     `order_item_id` int(11) NOT NULL,
        //     `profit_type` int(11) NOT NULL,
        //     `profit_price` decimal(15,2) NOT NULL,
        //     `profit_price_total` decimal(15,2) NOT NULL,
        //     `qty` int(11) NOT NULL,
        //     `order_date` datetime NOT NULL,
        //     `paid_date` int(11) DEFAULT '0',
        //     PRIMARY KEY (`id`),
        //     KEY `design_id` (`design_id`),
        //     KEY `order_id` (`order_id`),
        //     KEY `order_item_id` (`order_id`)
        // )",

        // "web2ink_import (
        //     `type` varchar(7) NOT NULL,
        //     `id` int(7) NOT NULL,
        //     `name` varchar(150) NOT NULL,
        //     `code` varchar(60) NOT NULL,
        //     `brand` varchar(150) NOT NULL,
        //     `vendor` varchar(150) NOT NULL,
        //     `category` varchar(20) NOT NULL,
        //     `visible` tinyint(1) NOT NULL DEFAULT '1',
        //     KEY `type` (`type`),
        //     KEY `code` (`code`),
        //     KEY `brand` (`brand`),
        //     KEY `vendor` (`vendor`),
        //     KEY `visible` (`visible`)
        // )",

        // "web2ink_part_price (
        //     `post_id` BIGINT(20) NOT NULL DEFAULT '0',
        //     `part_id_wp` VARCHAR(45) NULL DEFAULT NULL,
        //     `part_id_w2i` VARCHAR(45) NULL DEFAULT NULL,
        //     `price` FLOAT(7,2) NOT NULL DEFAULT '0',
        //     KEY `post_id` (`post_id`),
        //     KEY `part_id_wp` (`part_id_wp`)
        // )"
    );
    return $sqls;
}