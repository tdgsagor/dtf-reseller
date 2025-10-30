<?php

use DtfReseller\Admin\CommonFunctions;
// Prevent direct access
if (!defined('ABSPATH'))
    exit;

// Your custom class with all the methods you want
if (!class_exists('dtf_web2ink_import')) {
    class dtf_web2ink_import
    {
        public static function makeTerm($name, $taxonomy)
        {
            $slug = sanitize_title($name);
            $term = get_term_by("slug", $slug, $taxonomy, ARRAY_A);
            if ($term) {
                return $term["term_id"];
            }
            $newterm = wp_insert_term($name, $taxonomy, array("description" => $name, "slug" => $slug));
            if (!is_wp_error($newterm)) {
                return isset($newterm["term_id"]) ? $newterm["term_id"] : 0;
            }
            return 0;
        }
        private static function createVendorTaxonomyTerm($vendor)
        {
            return self::makeTerm($vendor, "web2ink_product_vendor");
        }
        private static function createBrandTaxonomyTerm($brand)
        {
            return self::makeTerm($brand, "web2ink_product_brand");
        }
        private static function createColorSizeTermTaxonomy($slug, $taxonomy, $title, $api_field, $api_id, $api_details)
        {
            $get_term = get_term_by("slug", $slug, $taxonomy, ARRAY_A);
            if ($get_term) {
                if (!empty($api_field)) {
                    if (!empty($api_id)) {
                        update_term_meta($get_term["term_id"], $api_field . "_id", $api_id);
                    }
                    if (!empty($api_details)) {
                        update_term_meta($get_term["term_id"], $api_field . "_details", $api_details);
                    }
                }
                return $get_term;
            }
            $idet = array();
            $idet = wp_insert_term($title, $taxonomy, array("description" => $title, "slug" => $slug));
            if (is_wp_error($idet)) {
                return null;
            }
            if ($idet["term_id"]) {
                if (!empty($api_field)) {
                    if (!empty($api_id)) {
                        update_term_meta($idet["term_id"], $api_field . "_id", $api_id);
                    }
                    if (!empty($api_details)) {
                        update_term_meta($idet["term_id"], $api_field . "_details", $api_details);
                    }
                }
                $get_term = get_term_by("id", $idet["term_id"], $taxonomy, ARRAY_A);
                return $get_term;
            }
            return $idet;
        }

        // reimport from product page => good for fix some values
        public static function fixReimportPostProduct($post_id)
        {
            $w2i_id = (int) get_post_meta($post_id, "_web2ink_productID", true);
            if (empty($w2i_id)) {
                throw new Exception(__web2ink_lang("fixscnow2iid"), 1);
                return null;
            }
            return self::import_single_product($w2i_id);
        }

        // import new product from w2i server using w2i item ID
        public static function import_single_product($w2i_id)
        {
            global $wpdb;
            if (empty($w2i_id) || !is_numeric($w2i_id)) {
                throw new Exception(__web2ink_lang("fixscnow2iid"), 1);
                return false;
            }
            $w2i = web2ink_options();
            if (empty($w2i["clientid"]) || empty($w2i["apikey"])) {
                throw new Exception(__web2ink_lang("Web2InkSettingPageApiErrorMessage"), 2);
                return false;
            }
            $args = array(
                "id" => $w2i_id,
                "repository" => true,
                "description" => true,
                "categories" => true,
            );
            $result = web2ink_api::callAPI("read", "products", $args);
            if (empty($result) || !is_array($result)) {
                throw new Exception(__web2ink_lang("fixscnodata"), 3);
                return false;
            }
            $data = $result[0];
            if (empty($data["id"]) || (int) $data["id"] != (int) $w2i_id) {
                throw new Exception(__web2ink_lang("fixscidnotmatch"), 4);
                return null;
            }
            /*

              multiple prudcts could have same w2ik ID, run SQL

            */
            $ids = array();
            $products_ids = $wpdb->get_results("SELECT DISTINCT post_id FROM {$wpdb->prefix}postmeta where meta_key='_web2ink_productID' AND meta_value='" . $w2i_id . "'", ARRAY_A);
            if ($products_ids) {
                foreach ($products_ids as $products_id) {
                    $ids[] = (int) $products_id["post_id"];
                }
            }
            if (empty($ids)) {
                // post not found, make new product
                $slug = !empty($data["slug"]) ? $data["slug"] : sanitize_title($data["name"]); // try set slug form w2i
                $check_slug = get_posts(array("name" => $slug, "post_type" => "product", "post_status" => "publish", "numberposts" => 1));
                if (!empty($check_slug)) {
                    $slug = "";
                } // leave empty to allow auto-generate new slug   
                add_filter("wp_insert_post_empty_content", "__return_false");
                $args = array(
                    "post_author" => get_current_user_id(),
                    "post_title" => $data["name"],
                    "post_name" => $slug,
                    "post_type" => "product",
                    "post_status" => "publish",
                    "post_excerpt" => trim(isset($data["description"]) ? html_entity_decode($data["description"], ENT_QUOTES) : ""),
                    "meta_input" => array(
                        "_web2ink_productID" => $w2i_id,
                        "_web2ink_allow_designer" => "true",
                        "_web2ink_allow_express" => "true",
                        "_web2ink_allow_upload_general" => "true",
                        "_web2ink_upload_general_force" => "false",
                        "_web2ink_team_order" => "false",
                        "_web2ink_team_order_type" => "both",
                        "_web2ink_all_over" => "false",
                        "_web2ink_price_chart" => array(),
                        "_web2ink_price_group" => "each",
                        "_web2ink_price_method" => "stack",
                        "_web2ink_price_markup_type" => "percent",
                        "_web2ink_price_markup_value" => 0,
                        "_web2ink_price_one_time" => 0,
                        "_web2ink_square_unit_price" => 0,
                        "_web2ink_square_unit_price_dbl" => 0,
                        "_web2ink_square_unit_price_unit" => "inch",
                        "_web2ink_square_unit_chart" => array(),
                        "_web2ink_print_processes" => array(),
                        "_web2ink_product_buttons" => array(),
                        "_web2ink_design_button" => "",
                        "_web2ink_express_button" => "",
                        "_web2ink_restrict_template" => "false",
                        "_web2ink_disallow_rush" => 0,
                        "_web2ink_nosidesform" => "false",
                        "_web2ink_image" => $data["image"],
                        "_importing_from_web2ink" => 1,
                    )
                );
                $post_id = wp_insert_post($args);
                if (is_wp_error($post_id)) {
                    throw new Exception($post_id->get_error_message(), 5);
                    return null;
                }
                if (empty($post_id)) {
                    throw new Exception("Can't insert new product", 6);
                    return null;
                }
                // set default product metas
                update_post_meta($post_id, "_visibility", "visible");
                update_post_meta($post_id, "_default_attributes", array());
                wp_set_object_terms($post_id, "variable", "product_type");
                $ids[] = (int) $post_id;
            }
            $result = false;
            foreach ($ids as $id) {
                $result = self::import_w2i_data($id, $data);
                delete_post_meta($id, "_importing_from_web2ink");
            }

            if (is_main_site() && get_site_option('dtfreseller_enable_products')) {
                $sites = get_sites(['number' => 0]);
                $site_ids = [];

                foreach ($sites as $site) {
                    if ((int) $site->blog_id !== get_main_site_id()) {
                        $site_ids[] = (int) $site->blog_id;
                    }
                }

                CommonFunctions::sync_selected_products($ids, $site_ids);
            }

            return $result;
        }

        // import w2i data into existing post
        public static function import_w2i_data($post_id, $meta)
        {
            $web2ink_options = web2ink_options();

            // clear and set vendor/brand taxonomies
            wp_delete_object_term_relationships($post_id, "web2ink_product_vendor");
            $vendor_id = !empty($meta["vendor"]) ? self::createVendorTaxonomyTerm($meta["vendor"]) : 0;
            if (!empty($vendor_id)) {
                wp_set_object_terms($post_id, $vendor_id, "web2ink_product_vendor");
            }
            wp_delete_object_term_relationships($post_id, "web2ink_product_brand");
            $brand_id = !empty($meta["brand"]) ? self::createBrandTaxonomyTerm($meta["brand"]) : 0;
            if (!empty($brand_id)) {
                wp_set_object_terms($post_id, $brand_id, "web2ink_product_brand");
            }

            // import price charts   
            $markup = !empty($web2ink_options["web2inkmarkupprice"]) ? (float) $web2ink_options["web2inkmarkupprice"] : 0;
            $markup_multi = 1 + max(0, $markup / 100);
            if (!empty($meta["prices"])) {
                $prices = array();
                foreach ($meta["prices"] as $price) {
                    $prices[intval($price["qty"])] = round($price["price"] * $markup_multi, 6);
                }
                update_post_meta($post_id, "_web2ink_price_chart", $prices);
            }
            if (!empty($meta["pricesq"])) {
                update_post_meta($post_id, "_web2ink_square_unit_price", round($meta["pricesq"] * $markup_multi, 6));
            }

            // add design options, size chart, product template
            if (!empty($meta["sides"])) {
                update_post_meta($post_id, "_web2ink_product_sides", (array) $meta["sides"]);
            }
            update_post_meta($post_id, "_web2ink_product_template_allow", (!empty($meta["template"]) ? 1 : 0));
            if (!metadata_exists("post", $post_id, "_web2ink_sizechart") && !empty($meta["sizechart"])) {
                update_post_meta($post_id, "_web2ink_sizechart", (array) $meta["sizechart"]);
            }

            // detect size type range    
            update_post_meta($post_id, "_web2ink_sizetype", (!empty($meta["sizetype"]) ? $meta["sizetype"] : "fixed"));
            $size_range = array();
            if (!empty($meta["sizetype"]) && $meta["sizetype"] == "range") {
                $size_range = (array) $meta["sizes"];
                $unit = !empty($size_range[0]["unit"]) ? $size_range[0]["unit"] : "inch";
                update_post_meta($post_id, "_web2ink_SizeTypeValues", $size_range);
                update_post_meta($post_id, "_web2ink_square_unit_price_unit", $unit);
                unset($meta["sizes"]); // to prevent insert size terms      
            }

            // set product as variable
            wp_delete_object_term_relationships($post_id, "product_type");
            wp_set_object_terms($post_id, "variable", "product_type");

            // create missing variation 
            $variations = get_posts(array("post_parent" => $post_id, "post_type" => "product_variation"));
            if (empty($variations)) {
                $wc_product = wc_get_product($post_id);
                $args = array(
                    "post_title" => $wc_product->get_name(),
                    "post_name" => "product-" . $post_id . "-variation",
                    "post_status" => "publish",
                    "post_parent" => $post_id,
                    "post_type" => "product_variation",
                    "guid" => $wc_product->get_permalink(),
                );
                $variation_id = wp_insert_post($args);
                $variation = wc_get_product($variation_id);
                $variation->set_backorders("no");
                $variation->set_manage_stock(false);
                $variation->set_sale_price(0);
                $variation->set_regular_price(0);
                $variation->set_price(0);
                $variation->set_sold_individually("no");
                $variation->save();
                update_post_meta($variation_id, "_virtual", "no");
                update_post_meta($variation_id, "_downloadable", "no");
                update_post_meta($variation_id, "_download_limit", "-1");
                update_post_meta($variation_id, "_download_expiry", "-1");
                update_post_meta($variation_id, "_variation_description", "");
                update_post_meta($variation_id, "attribute_web2inkcolor", "");
                update_post_meta($variation_id, "attribute_web2inksize", "");
            }

            // create color/size terms
            $colors = array();
            foreach ($meta["colors"] as $color) {
                $term = self::createColorSizeTermTaxonomy("color-" . $color["id"], "web2ink_product_color", $color["name"], "web2ink_color", $color["id"], $color);
                if (!empty($term) && !empty($term["term_id"])) {
                    if (!empty($color["name"]) && $color["name"] != $term["name"]) {
                        wp_update_term($term["term_id"], "web2ink_product_color", array("name" => $color["name"]));
                        $term["name"] = $color["name"];
                    }
                    $colors[$color["id"]] = $term;
                    if (!empty($color["charge"])) {
                        update_term_meta($term["term_id"], "web2ink_color_upcharge", round((float) $color["charge"] * $markup_multi, 2));
                    }
                }
            }
            $sizes = array();
            if (!empty($meta["sizes"])) {
                foreach (self::orderSizesByName($meta["sizes"]) as $size) {
                    $term = self::createColorSizeTermTaxonomy("size-" . $size["id"], "web2ink_product_size", $size["name"], "web2ink_size", $size["id"], $size);
                    if (!empty($term) && !empty($term["term_id"])) {
                        if (!empty($size["name"]) && $size["name"] != $term["name"]) {
                            wp_update_term($term["term_id"], "web2ink_product_size", array("name" => $size["name"]));
                            $term["name"] = $size["name"];
                        }
                        $sizes[$size["id"]] = $term;
                        if (!empty($size["charge"])) {
                            update_term_meta($term["term_id"], "web2ink_size_upcharge", round((float) $size["charge"] * $markup_multi, 2));
                        }
                        if (!empty($size["colors"]) && is_array($size["colors"])) {
                            $exclude = array();
                            foreach ($size["colors"] as $cid) {
                                if (isset($colors[$cid])) {
                                    $exclude[] = (int) $colors[$cid]["term_id"];
                                }
                            }
                            if (!empty($exclude)) {
                                update_term_meta($term["term_id"], "web2ink_exclude_colors", $exclude);
                            }
                        }
                    }
                }
            }
            self::fix_color_problem($post_id, "web2ink_product_color", $colors);
            self::fix_color_problem($post_id, "web2ink_product_size", $sizes);

            // some other options
            if (!empty($meta["image"])) {
                update_post_meta($post_id, "_web2ink_image", $meta["image"]);
            }

            // check SKU code
            if (empty($meta["sku"])) {
                $meta["sku"] = $post_id;
            }
            $test = wc_get_product_id_by_sku($meta["sku"]);
            if (!empty($test) && $test != $post_id) {
                $meta["sku"] = $meta["sku"] . "-" . $post_id;
            }

            // fix some problems with new product
            $wc_product = wc_get_product($post_id);
            $wc_product->set_backorders("no");
            $wc_product->set_stock_status("instock");
            $wc_product->set_stock_quantity("");
            $wc_product->set_manage_stock("");
            $wc_product->set_sale_price(0);
            $wc_product->set_regular_price(0);
            $wc_product->set_price(0);
            $wc_product->set_sku($meta["sku"]);
            $wc_product->set_sold_individually("no");
            $wc_product->set_weight((!empty($meta["weight"]) ? self::fixWeight($meta["weight"]) : 0));
            $wc_product->save();

            // clear cache
            wc_delete_product_transients($post_id);

            return $post_id;
        }

        // ========== importing categorie ===========  
        public function im_categories()
        {
            global $wpdb;
            $web2ink_config = web2ink_options();
            if (isset($web2ink_config['clientid']) && isset($web2ink_config['apikey'])) {
                $categories = web2ink_api::get_categories($web2ink_config);
                foreach ($categories as $categoryKey => $categoryArr) {
                    $import_cat_repository = $categoryKey;
                    if ($categoryArr) {
                        foreach ($categoryArr as $categoryArrDetails) {
                            $import_cat_id = $categoryArrDetails['id'];
                            $import_cat_name = $categoryArrDetails['name'];
                            $import_cat_parent = $categoryArrDetails['parent'];
                            $import_cat_position = $categoryArrDetails['position'];
                            $import_cat_repository = $import_cat_repository;
                            $is_imported = 0;
                            $Exist = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "web2ink_product_categories WHERE import_cat_id='" . $import_cat_id . "'");
                            if (!$Exist) {
                                $q = $wpdb->insert($wpdb->prefix . 'web2ink_product_categories', compact('import_cat_id', 'import_cat_name', 'import_cat_parent', 'import_cat_position', 'import_cat_repository', 'is_imported'));
                            }
                        }
                    }
                }
                $import_to_woo_product_cat = web2ink_import::import_to_woo_product_cat();
            }
        }

        public static function import_to_woo_product_cat($args = array())
        {
            global $wpdb;
            $p_id = (isset($args['p_id']) ? $args['p_id'] : 0);
            $cats = $wpdb->get_results(" SELECT *  FROM " . $wpdb->prefix . "web2ink_product_categories WHERE import_cat_parent = '" . $p_id . "'", ARRAY_A);
            if ($cats) {
                foreach ($cats as $cat) {
                    //========= Inserting to woo product_cat ==================//
                    $woo_p_id = self::insertCatInWooCategories($cat);
                    $child_insertion = self::import_to_woo_product_cat(array('p_id' => $cat['import_cat_id']));
                }
            }
        }

        public static function insertCatInWooCategories($cat)
        {
            $taxonomy = 'product_cat';
            $slug = sanitize_title($cat['import_cat_name']) . '-' . $cat['import_cat_id'];
            //========== checking =================================//
            $args = array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'web2ink_cat_id',
                        'value' => $cat['import_cat_id'],
                        'compare' => '=',
                    ),
                ),
            );
            $checking = get_terms($args);
            if ($checking) {
                return;
            }
            $parent = 0;
            //=============== getting woo parent id ===========================//
            if ($cat['import_cat_parent'] > 0) {
                $args = array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key' => 'web2ink_cat_id',
                            'value' => $cat['import_cat_parent'],
                            'compare' => '=',
                        ),
                    ),
                );
                $get_p = get_terms($args);
                if (isset($get_p[0]->term_id)) {
                    $parent = $get_p[0]->term_id;
                }
            }
            $idet = array();
            $idet = wp_insert_term(
                $cat['import_cat_name'],
                $taxonomy,
                array(
                    'description' => $cat['import_cat_name'],
                    'slug' => $slug,
                    'parent' => $parent,
                )
            );
            if (isset($idet['term_id'])) {
                update_term_meta($idet['term_id'], 'web2ink_cat_id', $cat['import_cat_id']);
            }
        }

        public static function insertWeb2inkCatInWooCategories($category_id, $categories = array())
        {
            if (isset($categories[$category_id])) {
                $taxonomy = 'product_cat';
                $slug = sanitize_title($categories[$category_id]['name']) . '-' . $categories[$category_id]['id'];
                $args = array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key' => 'web2ink_cat_id',
                            'value' => $categories[$category_id]['id'],
                            'compare' => '=',
                        ),
                    ),
                );
                $checking = get_terms($args);
                $term_id = 0;
                if (!$checking) {
                    $idet = wp_insert_term(
                        $categories[$category_id]['name'],
                        $taxonomy,
                        array(
                            'description' => $categories[$category_id]['name'],
                            'slug' => $slug,
                            'parent' => 0,
                        )
                    );
                    if (isset($idet['term_id'])) {
                        $term_id = $idet['term_id'];
                        update_term_meta($term_id, 'web2ink_cat_id', $categories[$category_id]['id']);
                    }
                } else {
                    $term_id = $checking[0]->term_id;
                }
                if ($term_id) {
                    if ($categories[$category_id]['parent']) {
                        $parent_id = self::insertWeb2inkCatInWooCategories($categories[$category_id]['parent'], $categories);
                        wp_update_term($term_id, $taxonomy, array('parent' => $parent_id));
                    }
                }
                return $term_id;
            }
        }

        public static function fixSizeColorProblem($pid)
        {
            $w2i_id = (int) get_post_meta($pid, "_web2ink_productID", true);
            if (empty($w2i_id)) {
                throw new Exception(__web2ink_lang("fixscnow2iid"), 1);
                return null;
            }
            $args = array("id" => $w2i_id, "sizes" => true, "colors" => true, "categories" => false, "prices" => false, "description" => false);
            $result = web2ink_api::callAPI("read", "products", $args);
            if (empty($result) || !is_array($result)) {
                throw new Exception(__web2ink_lang("fixscnodata"), 2);
                return null;
            }
            $w2i = $result[0];
            if (empty($w2i["id"]) || $w2i["id"] != $w2i_id) {
                throw new Exception(__web2ink_lang("fixscidnotmatch"), 3);
                return null;
            }
            if (empty($w2i["colors"])) {
                throw new Exception(__web2ink_lang("fixscnocolors"), 5);
                return null;
            }
            $colors = array();
            foreach ($w2i["colors"] as $color) {
                $term = self::createColorSizeTermTaxonomy("color-" . $color["id"], "web2ink_product_color", $color["name"], "web2ink_color", $color["id"], $color);
                if (!empty($term) && !empty($term["term_id"])) {
                    if (!empty($color["name"]) && $color["name"] != $term["name"]) {
                        wp_update_term($term["term_id"], "web2ink_product_color", array("name" => $color["name"]));
                        $term["name"] = $color["name"];
                    }
                    $colors[$color["id"]] = $term;
                    if (isset($color["charge"])) {
                        update_term_meta($term["term_id"], "web2ink_color_upcharge", (float) $color["charge"]);
                    }
                }
            }
            $sizes = array();
            if (!empty($w2i["sizes"])) {
                foreach (self::orderSizesByName($w2i["sizes"]) as $i => $size) {
                    $term = self::createColorSizeTermTaxonomy("size-" . $size["id"], "web2ink_product_size", $size["name"], "web2ink_size", $size["id"], $size);
                    if (!empty($term) && !empty($term["term_id"])) {
                        if (!empty($size["name"]) && $size["name"] != $term["name"]) {
                            wp_update_term($term["term_id"], "web2ink_product_size", array("name" => $size["name"]));
                            $term["name"] = $size["name"];
                        }
                        $sizes[$size["id"]] = $term;
                        if (isset($size["charge"])) {
                            update_term_meta($term["term_id"], "web2ink_size_upcharge", (float) $size["charge"]);
                        }
                        if (!empty($size["colors"]) && is_array($size["colors"])) {
                            $exlcude = array();
                            foreach ($size["colors"] as $cid) {
                                if (isset($colors[$cid])) {
                                    $dWooId[] = (string) $colors[$cid]["name"];
                                }
                            }
                            if (!empty($exlcude)) {
                                update_term_meta($term["term_id"], "web2ink_exclude_colors", $exlcude);
                            }
                        }
                    }
                }
            }
            self::fix_color_problem($pid, "web2ink_product_color", $colors);
            self::fix_color_problem($pid, "web2ink_product_size", $sizes);
            return array("sizes" => $sizes, "colors" => $colors);
        }

        /*
        pid: product (post) ID
        taxonomy: "web2ink_product_color" or "web2ink_product_size"
        terms: array or terms to insert: term = array, no WP_Term object
        */
        public static function fix_color_problem($pid, $taxonomy, $terms)
        {
            global $wpdb;
            $replaces = array();
            $term_ids = array_filter(array_column($terms, "term_id"));
            $exists = wp_get_object_terms($pid, $taxonomy, array("hide_empty" => false));
            if (!empty($exists)) {
                foreach ($exists as $term) {
                    if (!in_array($term->term_id, $term_ids)) {
                        $replace = array("old" => $term->to_array());
                        foreach ($terms as $term2) {
                            if (strtolower($term2["name"]) == strtolower($term->name)) {
                                $replace["new"] = $term2;
                                break;
                            }
                        }
                        $replaces[] = $replace;
                    }
                }
            }

            // fix term order
            if (empty($replaces) && !empty($terms)) {
                $i = 0;
                foreach ($terms as $term) {
                    $wpdb->update(
                        $wpdb->prefix . "term_relationships",
                        array("term_order" => $i),
                        array(
                            "term_taxonomy_id" => $term["term_id"],
                            "object_id" => $pid,
                        )
                    );
                    $i++;
                }
            }

            // nothing to replace - counts match - all seems to be right
            $ignore = defined("WPML_PLUGIN_BASENAME");
            if (empty($replaces) && count($terms) == count($exists) && !$ignore) {
                return false;
            }

            // replace relationships with new
            wp_set_object_terms($pid, array_filter(array_column($terms, "term_id")), $taxonomy);

            // replace related product variation attributes
            $att_slug = $taxonomy == "web2ink_product_color" ? "web2inkcolor" : "web2inksize";
            $product_atts = array_filter((array) get_post_meta($pid, "_product_attributes", true));
            if (!isset($product_atts[$att_slug])) {
                $product_atts[$att_slug] = array(
                    "name" => $att_slug,
                    "value" => "",
                    "is_visible" => 1,
                    "is_variation" => 1,
                    "is_taxonomy" => 0,
                );
            }
            $product_atts[$att_slug]["value"] = implode(" | ", array_filter(array_column($terms, "name")));
            update_post_meta($pid, "_product_attributes", $product_atts);

            // create missing variation 
            $vars = get_posts(array("post_parent" => $pid, "post_type" => "product_variation"));
            if (empty($vars)) {
                $product = wc_get_product($pid);
                $args = array(
                    "post_title" => $product->get_name(),
                    "post_name" => "product-" . $pid . "-variation",
                    "post_status" => "publish",
                    "post_parent" => $pid,
                    "post_type" => "product_variation",
                    "guid" => $product->get_permalink()
                );
                $variation_id = wp_insert_post($args);
                $variation = wc_get_product($variation_id);
                $variation->set_backorders("no");
                $variation->set_manage_stock(false);
                $variation->set_price(0);
                $variation->set_sold_individually("no");
                $variation->save();
                update_post_meta($variation_id, "_virtual", "no");
                update_post_meta($variation_id, "_downloadable", "no");
                update_post_meta($variation_id, "_download_limit", "-1");
                update_post_meta($variation_id, "_download_expiry", "-1");
                update_post_meta($variation_id, "_variation_description", "");
                update_post_meta($variation_id, "attribute_web2inkcolor", "");
                update_post_meta($variation_id, "attribute_web2inksize", "");
            }
            return true;
        }

        public static function orderSizesByName($sizes)
        {
            $common = array_merge(
                array("newborn", "nb", "3month", "3m", "6month", "6m", "12month", "12m", "18month", "18m", "24month", "24m"),
                array("2t", "3t", "4t", "5/6t", "5t", "6t"),
                array("yxs", "ys", "ysm", "ym", "ymed", "yl", "ylg", "yxl"),
                array("youth xs", "youth s", "youth sm", "youth m", "youth med", "youth l", "youth lg", "youth xl"),
                array("xs", "s", "sm", "m", "md", "med", "l", "lg", "xl"),
                array("xxl", "2xl", "xxxl", "3xl", "xxxxl", "4xl", "xxxxxl", "5xl", "xxxxxxl", "6xl")
            );
            foreach ($sizes as $i => $size) {
                $name = strtolower($size["name"]);
                $pos = array_search($name, $common);
                if ($pos == false) {
                    $name = str_replace(array(" ", "-", "_"), " ", $name);
                    $pos = array_search($name, $common);
                }
                if ($pos === false || $pos == null) {
                    $pos = $i;
                    if (isset($size["position"])) {
                        $pos = (int) $size["position"];
                    }
                }
                $sizes[$i]["position"] = $pos;
            }
            usort($sizes, function ($a, $b) {
                if (!isset($a["position"])) {
                    return 1;
                }
                if (!isset($b["position"])) {
                    return -1;
                }
                if ($a["position"] == $b["position"]) {
                    return 0;
                }
                return ($a["position"] < $b["position"]) ? -1 : 1;
            });
            return $sizes;
        }

        public static function reimport_colors($pid)
        {
            $w2i_id = (int) get_post_meta($pid, "_web2ink_productID", true);
            if (empty($w2i_id)) {
                throw new Exception(__web2ink_lang("fixscnow2iid"), 1);
                return null;
            }
            $args = array("id" => $w2i_id, "sizes" => false, "colors" => true, "categories" => false, "prices" => false, "description" => false);
            $result = web2ink_api::callAPI("read", "products", $args);
            if (empty($result) || !is_array($result)) {
                throw new Exception(__web2ink_lang("fixscnodata"), 2);
                return null;
            }
            $w2i = $result[0];
            if (empty($w2i["id"]) || $w2i["id"] != $w2i_id) {
                throw new Exception(__web2ink_lang("fixscidnotmatch"), 3);
                return null;
            }
            if (empty($w2i["colors"])) {
                throw new Exception(__web2ink_lang("fixscnocolors"), 5);
                return null;
            }
            foreach ($w2i["colors"] as $color) {
                $term = get_term_by("slug", "color-" . $color["id"], "web2ink_product_color", ARRAY_A);
                if ($term) {
                    update_term_meta($term["term_id"], "web2ink_color_details", $color);
                    if (!empty($color["name"]) && $color["name"] != $term["name"]) {
                        wp_update_term($term["term_id"], "web2ink_product_color", array("name" => $color["name"]));
                    }
                }
            }
        }

        public static function reimport_weights()
        {
            global $wpdb;
            $ids = array();
            $sql = "SELECT p.ID, pm2.meta_value AS w2iID FROM {$wpdb->prefix}posts p 
            LEFT JOIN {$wpdb->prefix}postmeta pm ON pm.post_id=p.ID AND pm.meta_key='_weight'
            LEFT JOIN {$wpdb->prefix}postmeta pm2 ON pm2.post_id=p.ID AND pm2.meta_key='_web2ink_productID'
            WHERE p.post_type='product' AND pm2.meta_value > 0 AND (pm.meta_value = 0 OR ISNULL(pm.meta_value))";
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if ($rows) {
                foreach ($rows as $kus) {
                    $ids[$kus["w2iID"]] = $kus["ID"];
                }
            }
            if (empty($ids)) {
                throw new Exception("Fix no required now, no products found to fix weight", 1);
                return null;
            }
            $result = web2ink_api::callAPI("read", "products", array("weights" => array_keys($ids)));
            if (empty($result) || !is_array($result)) {
                throw new Exception(__web2ink_lang("fixscnodata"), 2);
                return null;
            }
            $fixed = 0;
            foreach ($result as $i => $kus) {
                if (isset($ids[$kus["id"]])) {
                    $weight = self::fixWeight($kus["weight"]);
                    if (empty($weight)) {
                        continue;
                    }
                    $post_id = $ids[$kus["id"]];
                    $product = wc_get_product($post_id);
                    $product->set_weight($weight);
                    $product->save();
                    $fixed++;
                }
            }
            return $fixed;
        }

        public static function fixWeight($weight)
        {
            if (empty($weight) || !is_array($weight)) {
                return $weight;
            }
            $ret = !empty($weight["value"]) ? floatval($weight["value"]) : 0;
            if (empty($ret) || empty($weight["unit"])) {
                return $ret;
            }
            return wc_get_weight($ret, get_option("woocommerce_weight_unit"), $weight["unit"]);
        }

        public static function reimport_prices($pid)
        {
            $w2i_id = (int) get_post_meta($pid, "_web2ink_productID", true);
            if (empty($w2i_id)) {
                throw new Exception(__web2ink_lang("fixscnow2iid"), 1);
                return null;
            }
            $result = web2ink_api::callAPI("read", "prices", array("id" => $w2i_id));
            if (empty($result) || !is_array($result)) {
                throw new Exception(__web2ink_lang("fixscnodata"), 2);
                return null;
            }
            $w2i = $result[0];
            if (empty($w2i["id"]) || $w2i["id"] != $w2i_id) {
                throw new Exception(__web2ink_lang("fixscidnotmatch"), 3);
                return null;
            }
            return $w2i;
        }

        public static function reimport_prices_batch($w2i_ids)
        {
            if (empty($w2i_ids)) {
                throw new Exception(__web2ink_lang("fixscnow2iid"), 1);
                return null;
            }
            $result = web2ink_api::callAPI("read", "prices", array("ids" => $w2i_ids));
            if (empty($result) || !is_array($result)) {
                throw new Exception(__web2ink_lang("fixscnodata"), 2);
                return null;
            }
            return $result;
        }

        // ============ export/import designs ============  
        public static function export_design_data($post_id)
        {
            if (empty($post_id)) {
                return null;
            }
            $post = get_post($post_id);
            if (!$post || $post->post_type != "web2ink_design") {
                return null;
            }
            $metas = (array) get_post_meta($post->ID, "_web2ink_saved_design", true);
            $charges = $metas["charges"];
            $product = web2ink_helper::get_product_details($metas["product_id"]);
            $color = get_term($metas["color_id"], "web2ink_product_color");
            $color_metas = get_term_meta($color->term_id, "web2ink_color_details", true);
            $process = web2ink_pricing::getProcess($charges["process"]);
            $design = array(
                "post" => array(
                    "name" => $post->post_title,
                    "excerpt" => $post->post_excerpt,
                    "content" => $post->post_content,
                ),
                "map" => array(
                    "product" => array(
                        "name" => $product["name"],
                        "slug" => $product["slug"],
                        "w2i_id" => $product["web2inkid"],
                    ),
                    "color" => array(
                        "name" => $color->name,
                        "slug" => $color->slug,
                        "w2i_id" => !empty($color_metas["id"]) ? (int) $color_metas["id"] : 0,
                    ),
                    "process" => $process["print_process_name"],
                ),
                "metas" => $metas,
            );
            foreach (get_post_meta($post->ID) as $k => $v) {
                if (substr($k, 0, 8) == "_web2ink" && $k != "_web2ink_saved_design") {
                    $design[substr($k, 9)] = maybe_unserialize(is_array($v) ? array_shift($v) : $v);
                }
            }
            $remove = array("saved_design_product_id", "saved_design_dt_design_id", "saved_item_not_visible", "exclude_saved_design_market_place_item", "style_colors");
            foreach ($remove as $kus) {
                if (isset($design[$kus])) {
                    unset($design[$kus]);
                }
            }
            foreach ($design as $kus => $value) {
                if (stripos($kus, "profit") !== false) {
                    unset($design[$kus]);
                }
                if (stripos($kus, "store") !== false) {
                    unset($design[$kus]);
                }
                if (stripos($kus, "template") !== false) {
                    unset($design[$kus]);
                }
            }
            return $design;
        }

        //================= trace problem with imports ==============
        public static function findProblems()
        {
            global $wpdb;
            echo "<div class=\"wrap\">
              <h2>Find Problems</h2>
              <p class=\"description\">There are a tools for fix common problems, som of functionality may overwrite your existing saved data</p>\n";

            // no variations
            $sql = "SELECT p1.ID, p1.post_title, p1.post_status, COUNT(p2.id) AS kolko FROM {$wpdb->prefix}posts p1
            LEFT JOIN {$wpdb->prefix}posts p2 ON p2.post_parent = p1.ID AND p2.post_type='product_variation'
            WHERE p1.post_type = 'product' AND p1.ID in (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key='_web2ink_productID' AND meta_value<>'')
            GROUP BY p1.ID HAVING kolko < 1";
            $no_varations = $wpdb->get_results($sql, ARRAY_A);
            if (!empty($no_varations)) {
                echo "<div class=\"w2i-bordertop\">
                <b style=\"color:#C00\">Missing variation/s</b>: Reimport product data<br>
                <i style=\"color:#C00\">Detected <b>" . count($no_varations) . "</b> item/s with no product variation - this is critical problem, this issue can be fixed by batch tool, click to re-import product data on detected item/s.</i> 
                <div class=\"w2i-reimportproduct\">
                  <div style=\"margin:5px\"><button type=\"button\" data-web2ink=\"reimportproduct\">Fix problem</button></div><div style=\"max-height:320px;overflow:auto\">";
                foreach ($no_varations as $item) {
                    echo "<div data-id=\"" . $item["ID"] . "\">" . $item["post_title"] . " (<i>" . $item["post_status"] . "</i>)</div>\n";
                }
                echo "</div></div></div>\n";
            }

            echo "<div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"reindexdesign\">Re-index designs for reorder option</a><br>
                <i>This function refresh design meta data, where we store order numbers, where design listed. It is important for detect re-order design for desgin setup charges discounts.<br>
                Updated just design (lookup) meta value, no order or design data udpated</i>
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"reindexprofits\">Re-index store profits</a><br>
                <i>This function rebuild Web2ink custom store profits lookup table, no order or design data removed, updated lookup table only</i>
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"regeneratepages\">Re-generate Web2Ink pages</a><br>
                <i>This tool check and create missing Web2Ink pages. This action create (or update) Pages and update main Web2ink meta value on page setup section</i>
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"regeneratedatabase\">Re-generate Web2Ink database</a><br>
                <i>This action check Web2ink database tables, table structure and update (create) missing structure. Action does not touch saved data</i>
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"reimportweight\">Re-import product weight</a><br>
                <i>In some of cases not imported product weight correctly, this tool check products with empty weight and re-import this value. No other data updated</i>
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"fixdesignhash\">Fix missing design links to worksheet</a><br>
                <i>This tool check saved design for unique hash value required for link to correct Worksheet page. It update just missing value on Design meta values</i>
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"fixdesigncolor\">Check and fix design colors</a><br>
                <i>This tool check saved designs and detect problems with incorrect saved color ID - usually required after fix product colors import. This action my take long time. Updating just incorrect value on Design meta values</i>
              </div>      
              <div class=\"w2i-bordertop\">
                <a href=\"" . admin_url("admin.php?page=wc-status&tab=tools") . "\" target=\"_blank\">Woocommerce tools</a><br>
                <i>Woocommerce Tools page contains options for fix some of problems related to e-commerce part</i><br>
                <i>For fix problem with ordering products by price, try regenerate Product lookup tables</i><br>
                <i>For refresh product data try clear WooCommerce transients</i>
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"detectsidedata\">Detect incorrect imported data</a><br>
                <i>Check imported products if contains side (locations) info, vendor and color/size attribute.<br>Not all found items should be incorrect, for example missing Vendor on custom products</i>
                <div class=\"w2i-detectsidedata\"></div>
              </div> 
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"savecolornames\">Save color names</a><br>
                <i>Your site and Web2ink system are different enviroments with different locations, most of your settings at your site is not necessary on Web2ink.<br>
                This is the reason why Web2ink does not know your color names. Click here to send and refresh names on Web2ink.</i>        
              </div>      
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"reimportprices\">Reimport missing prices</a><br>
                <i>This option reimporint price charts on products with missing charts only</i> 
              </div>
              <div class=\"w2i-bordertop\">
                <a href=\"#\" data-web2ink=\"reimportsizecolor\">Reimport size/color data</a><br>
                <i>This option reimporint all size/color data for all products and refresh atrtibute names.</i> 
                <div class=\"w2i-reimportsizecolor\"></div>
              </div>    
              <div class=\"w2i-bordertop w2i-removeallproducts\">
                <a href=\"#\" data-web2ink=\"removeallproducts\">Remove all imported products</a><br>
                <i>This remove <b style=\"color:#C00\">PERMANENTLY</b> all products and terms imported from Web2ink server</i>
                <div class=\"w2i-progress w2i-hidden\" style=\"margin:10px 0\"><div></div><span></span></div>
                <div class=\"w2i-info\"></div>
              </div>      
              <div class=\"w2i-bordertop w2i-importids\">
                <a href=\"#\" data-web2ink=\"importids\">Import products using IDs</a><br>
                <i>You can import products by list of IDs delimited by comma</i>
                <div class=\"w2i-ids w2i-hidden\">
                  <textarea class=\"w2i-block\"></textarea>
                  <div><button class=\"button\">Submit</button></div>
                </div>        
                <div class=\"w2i-import w2i-hidden\">
                  <div class=\"w2i-progress\" style=\"margin:10px 0\"><div></div><span></span></div>
                  <div class=\"w2i-info\"></div>
                </div>
              </div>      
            ";

            // get products
            $w2i_ids = array();
            $id_cols = $wpdb->get_results("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key='_web2ink_productID' AND meta_value<>''", ARRAY_A);
            if (!empty($id_cols)) {
                $w2i_ids = array_filter(array_map(function ($kus) {
                    return !empty($kus["post_id"]) ? (int) $kus["post_id"] : 0;
                }, $id_cols));
            }

            $products = array();
            $sql = "SELECT {$wpdb->prefix}term_taxonomy.description as colorname,{$wpdb->prefix}posts.ID,{$wpdb->prefix}posts.post_title FROM {$wpdb->prefix}term_taxonomy 
            LEFT JOIN {$wpdb->prefix}term_relationships ON {$wpdb->prefix}term_taxonomy.term_taxonomy_id = {$wpdb->prefix}term_relationships.term_taxonomy_id
            LEFT JOIN {$wpdb->prefix}posts ON {$wpdb->prefix}term_relationships.object_id = {$wpdb->prefix}posts.ID
            WHERE ({$wpdb->prefix}term_taxonomy.taxonomy='web2ink_product_color' OR {$wpdb->prefix}term_taxonomy.taxonomy='web2ink_product_size') 
                  AND {$wpdb->prefix}term_taxonomy.count <> 1 AND {$wpdb->prefix}posts.post_status <> 'trash'
            ORDER BY {$wpdb->prefix}posts.post_title";
            $tax = $wpdb->get_results($sql, ARRAY_A);
            if ($tax) {
                foreach ($tax as $kus) {
                    if (!empty($kus["ID"]) && !isset($products[$kus["ID"]]) && in_array((int) $kus["ID"], $w2i_ids)) {
                        $products[$kus["ID"]] = $kus;
                    }
                }
            }
            // detect duplicates
            if (count($products) > 0) {
                $dup_ids = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key='_web2ink_productID' AND post_id IN (" . implode(",", array_keys($products)) . ")", ARRAY_A);
                if (!empty($dup_ids)) {
                    $ignore = array();
                    $found = array();
                    foreach ($dup_ids as $kus) {
                        if (isset($found[$kus["meta_value"]])) {
                            $ignore[] = (int) $kus["post_id"];
                            $ignore[] = (int) $found[$kus["meta_value"]];
                        } else {
                            $found[$kus["meta_value"]] = $kus["post_id"];
                        }
                    }
                    if (!empty($ignore)) {
                        foreach (array_unique($ignore) as $id) {
                            if (isset($products[$id])) {
                                unset($products[$id]);
                            }
                        }
                    }
                }
            }
            // products with no color
            $sql = "SELECT ID, post_title FROM {$wpdb->prefix}posts 
            WHERE post_type='product' AND post_status <> 'trash' AND ID NOT IN (
              SELECT DISTINCT object_id FROM {$wpdb->prefix}term_relationships WHERE term_taxonomy_id IN 
                (SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy='web2ink_product_color')
            )";
            $tax = $wpdb->get_results($sql, ARRAY_A);
            if ($tax) {
                foreach ($tax as $kus) {
                    if (!isset($products[$kus["ID"]]) && in_array((int) $kus["ID"], $w2i_ids)) {
                        $kus["post_title"] .= " / missing colors";
                        $products[$kus["ID"]] = $kus;
                    }
                }
            }
            if (count($products) > 0) {
                echo "<div class=\"w2i-bordertop\"><b>Found products with incorrect color/size data</b></div>
              <p>Re-import " . count($products) . " products (possible <b>duplicates</b> using clone product!) to fix problem with incorrect color/size imported<br>
              <b>This tool override saved product Web2ink data</b> - load initial data from repository and save it to the database.<br><br>
              Data updated:<br> 
              - Web2ink product colors and Web2ink product sizes, incl. color/size charges, if color/size term not found, created new term/s<br>
              - variable product attributes: reflecting updates on Web2ink product color and Web2ink product size. <b>Replaced products attributes for Web2ink color and Web2ink size only</b><br>
              - variable product variations: reflecting updates on product attributes. <b>Existing variations removed and created new one</b><br><br>
              After finish re-cover tool recount Web2ink product color and Web2ink product size taxonomy terms and remove unused (with zero count of associations).</p>
              <div id=\"w2i-reimport\">";
                foreach ($products as $id => $kus) {
                    echo "<div data-id=\"$id\"><a href=\"" . get_edit_post_link($id) . "\" target=\"_blank\">#" . $id . "</a> <a href=\"" . get_permalink($id) . "\" target=\"_blank\">" . esc_html($kus["post_title"]) . "</a></div>\n";
                }
                echo "</div><p><input type=\"button\" class=\"button btn button-primary w2i-fixitbutton\" value=\"Fix It\" /></p>";
            }
            echo "</div>\n";
        }
    }
}

// Alias your class as the original plugin class
if (!class_exists('web2ink_import')) {
    class_alias('dtf_web2ink_import', 'web2ink_import');
}
