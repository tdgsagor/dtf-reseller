<?php
function insertDefaultData()
{
    global $wpdb;

    $dataSets = getDefaultData();

    foreach ($dataSets as $table => $rows) {
        $tableName = $wpdb->prefix . $table;
        foreach ($rows as $row) {
            $wpdb->insert($tableName, $row);
        }
    }
}
function getDefaultData()
{
    return [

        // 1. web2ink_settings_print_process
        'web2ink_settings_print_process' => [
            [
                'id' => '1',
                'print_process_name' => 'DTF Print and Press',
                'description' => 'DTF Print and Press',
                'count_ink_color' => 11,
                'is_default' => 1,
                'is_exclude' => 0,
            ],
            [
                'id' => '2',
                'print_process_name' => 'DTF On Caps',
                'description' => 'DTF On Caps',
                'count_ink_color' => 11,
                'is_default' => 0,
                'is_exclude' => 0,
            ],
        ],

        'web2ink_settings_print_process_price' => [

            // DTF Print and Press (process_id = 1)
            [
                'process_id' => 1,
                'qty' => 1,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 7.0,
                'Per_designed_side_add' => 7.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 4.0,
                'Team_order_numbers' => 5.0,
            ],
            [
                'process_id' => 1,
                'qty' => 6,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 6.5,
                'Per_designed_side_add' => 6.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 4.0,
                'Team_order_numbers' => 5.0,
            ],
            [
                'process_id' => 1,
                'qty' => 12,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 6.0,
                'Per_designed_side_add' => 5.5,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 4.0,
                'Team_order_numbers' => 5.0,
            ],
            [
                'process_id' => 1,
                'qty' => 25,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 5.0,
                'Per_designed_side_add' => 4.5,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 4.0,
                'Team_order_numbers' => 5.0,
            ],
            [
                'process_id' => 1,
                'qty' => 50,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 4.5,
                'Per_designed_side_add' => 4.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 4.0,
                'Team_order_numbers' => 5.0,
            ],
            [
                'process_id' => 1,
                'qty' => 100,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 4.25,
                'Per_designed_side_add' => 3.75,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 4.0,
                'Team_order_numbers' => 5.0,
            ],

            // DTF On Caps (process_id = 2)
            [
                'process_id' => 2,
                'qty' => 1,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 7.5,
                'Per_designed_side_add' => 0.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 0.0,
                'Team_order_numbers' => 0.0,
            ],
            [
                'process_id' => 2,
                'qty' => 6,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 5.0,
                'Per_designed_side_add' => 0.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 0.0,
                'Team_order_numbers' => 0.0,
            ],
            [
                'process_id' => 2,
                'qty' => 12,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 4.25,
                'Per_designed_side_add' => 0.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 0.0,
                'Team_order_numbers' => 0.0,
            ],
            [
                'process_id' => 2,
                'qty' => 25,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 3.5,
                'Per_designed_side_add' => 0.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 0.0,
                'Team_order_numbers' => 0.0,
            ],
            [
                'process_id' => 2,
                'qty' => 50,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 2.5,
                'Per_designed_side_add' => 0.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 0.0,
                'Team_order_numbers' => 0.0,
            ],
            [
                'process_id' => 2,
                'qty' => 100,
                'Flat_design' => 0.0,
                'Per_designed_side' => 0.0,
                'Color_setup' => 0.0,
                'flat_charge' => 0.0,
                'Per_designed_side_two' => 1.75,
                'Per_designed_side_add' => 0.0,
                'First_color' => 0.0,
                'Additional_color' => 0.0,
                'Per_color' => json_encode([]),
                'Squar_unit' => 0.0,
                'Addside_discount' => 0.0,
                'Team_order_names' => 0.0,
                'Team_order_numbers' => 0.0,
            ],
        ],


        // 2. web2ink_settings_print_process_colors
        // 'web2ink_settings_print_process_colors' => [
        //     [
        //         'process_id' => 1,
        //         'colorname' => 'Red',
        //         'hexcode' => '#FF0000',
        //         'charge' => 2.50,
        //         'image' => '',
        //     ],
        //     [
        //         'process_id' => 1,
        //         'colorname' => 'Blue',
        //         'hexcode' => '#0000FF',
        //         'charge' => 2.00,
        //         'image' => '',
        //     ],
        // ],

        // // 3. web2ink_settings_print_process_price


        // // 4. web2ink_settings_order_production
        // 'web2ink_settings_order_production' => [
        //     [
        //         'title' => 'Standard Production',
        //         'description' => 'Default production time',
        //         'price' => 0.00,
        //         'pricetype' => 'amount',
        //         'days' => 7,
        //         'sort' => 1,
        //     ],
        // ],

        // // 5. web2ink_settings_order_status
        // 'web2ink_settings_order_status' => [
        //     [
        //         'title' => 'Pending Approval',
        //         'status_key' => 'pending_approval',
        //     ],
        //     [
        //         'title' => 'In Production',
        //         'status_key' => 'in_production',
        //     ],
        // ],

        // // 6. web2ink_settings_order_production_holidays
        // 'web2ink_settings_order_production_holidays' => [
        //     [
        //         'day' => 25,
        //         'month' => 12, // Christmas
        //     ],
        //     [
        //         'day' => 1,
        //         'month' => 1, // New Year
        //     ],
        // ],

        // // 7. web2ink_product_categories
        // 'web2ink_product_categories' => [
        //     [
        //         'import_cat_id' => 1,
        //         'import_cat_name' => 'T-Shirts',
        //         'import_cat_parent' => 0,
        //         'import_cat_position' => 1,
        //         'import_cat_repository' => 1,
        //         'is_imported' => 0,
        //     ],
        //     [
        //         'import_cat_id' => 2,
        //         'import_cat_name' => 'Hoodies',
        //         'import_cat_parent' => 0,
        //         'import_cat_position' => 2,
        //         'import_cat_repository' => 1,
        //         'is_imported' => 0,
        //     ],
        // ],

        // // 8. web2ink_profit_list
        // 'web2ink_profit_list' => [
        //     [
        //         'design_id' => 0,
        //         'order_id' => 0,
        //         'order_item_id' => 0,
        //         'profit_type' => 1,
        //         'profit_price' => 0.00,
        //         'profit_price_total' => 0.00,
        //         'qty' => 0,
        //         'order_date' => current_time('mysql'),
        //         'paid_date' => 0,
        //     ],
        // ],

        // // 9. web2ink_import
        // 'web2ink_import' => [
        //     [
        //         'type' => 'product',
        //         'id' => 1001,
        //         'name' => 'Sample Product',
        //         'code' => 'SP001',
        //         'brand' => 'BrandX',
        //         'vendor' => 'VendorA',
        //         'category' => 'T-Shirts',
        //         'visible' => 1,
        //     ],
        // ],

        // // 10. web2ink_part_price
        // 'web2ink_part_price' => [
        //     [
        //         'post_id' => 0,
        //         'part_id_wp' => 'front',
        //         'part_id_w2i' => 'F001',
        //         'price' => 5.50,
        //     ],
        //     [
        //         'post_id' => 0,
        //         'part_id_wp' => 'back',
        //         'part_id_w2i' => 'B001',
        //         'price' => 4.00,
        //     ],
        // ],

    ];
}