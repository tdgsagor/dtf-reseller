<?php
// Prevent direct access
if (!defined('ABSPATH'))
    exit;

// Your custom class with all the methods you want
if (!class_exists('dtf_web2ink_pricing')) {
    class dtf_web2ink_pricing
    {
        public static $instance = null;
        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function applyMarkup($price = 0, $product_id = 0)
        {
            $markup = 0;
            $w2i = web2ink_options();
            if (!empty((float) $w2i["price_markup_value"])) {
                if ($w2i["price_markup_type"] == "percent") {
                    $markup = round($price * ($w2i["price_markup_value"] / 100), 2);
                }
                if ($w2i["price_markup_type"] == "amount") {
                    $markup = (float) $w2i["price_markup_value"];
                }
            }
            if (!empty($product_id)) {
                $details = web2ink_product_details($product_id);
                if (!empty($details["price_markup_value"]) && floatval($details["price_markup_value"]) > 0) {
                    if ($details["price_markup_type"] == "percent") {
                        $markup += round($price * ($details["price_markup_value"] / 100), 2);
                    }
                    if ($details["price_markup_type"] == "amount") {
                        $markup += (float) $details["price_markup_value"];
                    }
                }
            }
            if ($markup > 0) {
                $price += $markup;
            }
            return (float) $price;
        }

        public static function updateQuantity($metas)
        {
            $pole = self::getPrice($metas["args"], true);
            $price = $pole["price"];
            // update size charge, not included in pricing
            if (empty($price["size"]) && !empty($pole["args"]["sizeid"])) {
                $sizecharge = (float) get_term_meta($pole["args"]["sizeid"], "web2ink_size_upcharge", true);
                if (!empty($sizecharge)) {
                    $price["size"] = $sizecharge;
                    $price["unit"] += $sizecharge;
                }
            }
            if (!empty($metas["size"])) {
                $size_id = (int) $metas["size"]["id"];
                $sizecharge = (float) get_term_meta($size_id, "web2ink_size_upcharge", true);
                if (!empty($metas["args"]["design"])) {
                    $size_charges_custom = (array) get_post_meta($metas["args"]["design"], "_web2ink_size_charges", true);
                    if (isset($size_charges_custom[$size_id])) {
                        $sizecharge = (float) $size_charges_custom[$size_id];
                    }
                }
                if ($sizecharge != (float) $price["size"]) {
                    $diff = $sizecharge - (float) $price["size"];
                    $price["size"] = $diff;
                    $price["unit"] += $diff;
                }
            }
            $price["qty"] = $metas["price"]["qty"];
            $price["total"] = round($metas["price"]["qty"] * $price["unit"], 2);
            $metas["price"] = $price;
            if (!empty($metas["size"])) {
                $price_variation = web2ink_pricing::productPriceVariation($metas, $metas["size"]["id"]);
                if (!empty($price_variation)) {
                    $metas["price"]["unit"] = $price_variation;
                    $metas["price"]["total"] = round($metas["price"]["qty"] * $price_variation, 2);
                    $metas["price"]["variation"] = $price_variation;
                }
                $metas["size"]["price_each"] = $price["unit"];
                $metas["size"]["price"] = $price["total"];
            }
            return $metas;
        }

        /* ================================
        ==== MIX STYLES METHODS ===========
        =================================*/

        public static function quoteMixStyles($items, $charges)
        {
            $web2ink = web2ink_options();
            $collectby = empty($web2ink["designer_mix_qty"]) ? "design" : $web2ink["designer_mix_qty"]; // style | design
            $process = self::getProcess(!empty($charges["process"]) ? $charges["process"] : 0);
            $minqty = max(1, $process["minqty"]);
            $qtyall = $priceall = 0;
            $metas = array();
            foreach ($items as $product) {
                if (empty($product["colors"]) || !is_array($product["colors"])) {
                    continue;
                }
                $pidqty = 0;
                $colors = array();
                foreach ($product["colors"] as $color) {
                    $sizes = array();
                    foreach ($color["sizes"] as $size) {
                        if (intval($size["qty"]) < 1) {
                            continue;
                        }
                        $qtyall += intval($size["qty"]);
                        $pidqty += intval($size["qty"]);
                        $sizes[intval($size["size"])] = intval($size["qty"]);
                    }
                    if (!empty($sizes)) {
                        $colors[intval($color["color"])] = $sizes;
                    }
                }
                if (!empty($colors)) {
                    $pid = intval($product["product"]);
                    $kus = self::getMetas($pid);
                    $minqty = max($minqty, $kus["minqty"]);
                    $metas[$pid] = array("qty" => $pidqty, "items" => $colors, "metas" => $kus);
                }
            }
            $forqty = max($minqty, $qtyall);
            // design price => based on total quantity on mix-style, equal for any style
            if ($collectby == "design") {
                $design = self::getChargesPrice($charges, $forqty);
            }
            // product base price
            $items = array();
            foreach ($metas as $pid => $product) {
                $metas = $product["metas"];
                if ($collectby == "style") {
                    $design = self::getChargesPrice($charges, $product["qty"]);
                    $minqty = max(1, $process["minqty"], $metas["minqty"]);
                }
                $baseprice = self::findPrice($metas["price_chart"], $product["qty"], $metas["price_method"]); // qty based on all color/size for the product
                if ($metas["price_group"] == "all") {
                    $baseprice = round($baseprice / $product["qty"], 4);
                }
                $subtotal = $product["qty"] * ($baseprice + $design);
                // color/size upcharges
                foreach ($product["items"] as $cid => $sizes) {
                    $cprice = (float) get_term_meta($cid, "web2ink_color_upcharge", true);
                    foreach ($sizes as $sid => $qty) {
                        $sprice = (float) get_term_meta($sid, "web2ink_size_upcharge", true);
                        $subtotal += (($cprice + $sprice) * $qty);
                    }
                }
                $items[] = array("id" => $pid, "qty" => $product["qty"], "minqty" => $minqty, "subtotal" => $subtotal, "each" => round($subtotal / $product["qty"], 4));
                $priceall += $subtotal;
            }
            return array("minqty" => $minqty, "qty" => $qtyall, "total" => $priceall, "items" => $items, "collectby" => $collectby);
        }

        public static function cartMixStyles($items, $designid)
        {
            $design = self::getDesign($designid);
            if (empty($design)) {
                return array("error" => __web2ink_lang("Design details not found"));
            }
            $charges = $design["charges"];
            $process = self::getProcess(!empty($charges["process"]) ? $charges["process"] : 0);
            $web2ink = web2ink_options();
            $collectby = empty($web2ink["designer_mix_qty"]) ? "design" : $web2ink["designer_mix_qty"];
            $minqtyall = max(1, $process["minqty"]);
            $qtyall = 0;
            if ($collectby == "design") {
                $qtyall = self::getCartQtyDesign($designid);
            } // include items already in cart
            $metas = array();
            // check minimum
            foreach ($items as $pid => $colors) {
                $pidqty = 0;
                foreach ($colors as $cid => $sizes) {
                    foreach ($sizes as $sid => $qty) {
                        $pidqty += $qty;
                        $qtyall += $qty;
                    }
                }
                $meta = self::getMetas($pid);
                $minqty = max(1, $process["minqty"], $meta["minqty"]);
                $minqtyall = max($minqtyall, $meta["minqty"]);
                if ($collectby == "style" && $pidqty < $minqty) {
                    return array("error" => __web2ink_lang("Minimum quantity required") . " " . $minqty . " - " . $meta["title"]);
                }
                $metas[$pid] = array("qty" => $pidqty, "metas" => $meta);
            }
            if ($collectby == "design" && $qtyall < $minqtyall) {
                return array("error" => __web2ink_lang("Minimum quantity required") . " " . $minqtyall);
            }
            // start pricing
            $w2idid = $design["w2i_id"];
            $designargs = self::fixArgs(array("design" => $designid));
            $cartitems = array();
            $design = self::getChargesPrice($charges, $qtyall);
            foreach ($items as $pid => $colors) {
                $pqty = $metas[$pid]["qty"];
                $pmetas = $metas[$pid]["metas"];
                $args = array("design" => $designid, "product" => $pid, "qty" => $pqty, "collect" => true);
                foreach ($colors as $cid => $sizes) {
                    $args["color"] = $cid;
                    if ($designargs["product"] != $pid || $designargs["color"] != $cid) {
                        $w2icolor = get_term_meta($cid, "web2ink_color_details", true);
                        $w2ipid = $pmetas["productID"];
                        $w2icid = (int) $w2icolor["id"];
                        $args["image"] = "https://www.web2ink.com/web/stock/design/" . $w2ipid . "-" . $w2icid . "-" . $w2idid . ".jpg";
                        $args["thumbnail"] = "https://www.web2ink.com/web/stock/design/thumb/" . $w2ipid . "-" . $w2icid . "-" . $w2idid . ".jpg";
                    }
                    $item = self::getPrice($args, true);
                    if ($collectby == "design") {
                        $item["price"]["design"] = $design;
                    }
                    foreach ($sizes as $sid => $qty) {
                        $w2isize = get_term_meta($sid, "web2ink_size_details", true);
                        $item["price"]["qty"] = $qty;
                        $item["price"]["size"] = (float) get_term_meta($sid, "web2ink_size_upcharge", true);
                        $item["price"]["unit"] = $item["price"]["base"] + $item["price"]["design"] + $item["price"]["color"] + $item["price"]["size"];
                        $item["price"]["total"] = $item["price"]["unit"] * $item["price"]["qty"];
                        $size = array("id" => $sid, "name" => $w2isize["name"], "qty" => $qty, "price" => $item["price"]["total"], "price_each" => $item["price"]["unit"]);
                        $item["sizes"] = array($size);
                        $item["args"]["sizeid"] = $sid;
                        $w2isize["wooid"] = $sid;
                        unset($w2isize["colors"]);
                        $item["args"]["size"] = $w2isize;
                        web2ink_cart::web2inkWooCustomAddToCart($item, false);
                        $cartitems[] = $item;
                    }
                }
            }
            web2ink_cart::cart_updated();
            return $cartitems;
        }

        public static function getMixCartQtyProduct($pid, $did)
        {
            $cart = WC()->cart->get_cart();
            if (empty($cart)) {
                return 0;
            }
            $cache = "w2i-mix-qty-pid-" . $pid;
            $qty = wp_cache_get($cache, "w2i_pricing");
            if ($qty !== false) {
                return $qty;
            }
            $qty = 0;
            foreach ($cart as $key => $cart_item) {
                if (!isset($cart_item["_web2ink_option"])) {
                    continue;
                }
                $args = $cart_item["_web2ink_option"]["args"];
                if (!empty($args["collect"]) && $args["product"] == $pid && $args["design"] == $did) {
                    $qty += $cart_item["quantity"];
                }
            }
            wp_cache_add($cache, $qty, "w2i_pricing");
            return $qty;
        }

        public static function getCartQtyDesign($did)
        {
            $cart = WC()->cart->get_cart();
            if (empty($cart)) {
                return 0;
            }
            $cache = "w2i-qty-did-" . $did;
            $qty = wp_cache_get($cache, "w2i_pricing");
            if ($qty !== false) {
                return $qty;
            }
            $qty = 0;
            foreach ($cart as $key => $cart_item) {
                if (!isset($cart_item["_web2ink_option"])) {
                    continue;
                }
                $args = $cart_item["_web2ink_option"]["args"];
                if ($args["design"] == $did) {
                    $qty += $cart_item["quantity"];
                }
            }
            wp_cache_add($cache, $qty, "w2i_pricing");
            return $qty;
        }

        public static function getCartTotelQty($product_id = 0)
        {
            $cart = WC()->cart->get_cart();
            if (empty($cart)) {
                return 0;
            }
            $cache = "w2i-qty-ttl-" . $product_id;
            $qty = wp_cache_get($cache, "w2i_pricing");
            if ($qty !== false) {
                return $qty;
            }
            $qty = 0;
            foreach ($cart as $key => $cart_item) {
                if (!isset($cart_item["_web2ink_option"])) {
                    continue;
                }
                if (empty($product_id)) {
                    $qty += $cart_item["quantity"];
                } else {
                    $args = $cart_item["_web2ink_option"]["args"];
                    if ($args["product"] == $product_id) {
                        $qty += $cart_item["quantity"];
                    }
                }
            }
            wp_cache_add($cache, $qty, "w2i_pricing");
            return $qty;
        }

        public static function getCartQtyGroupCart($group_term)
        {
            if (WC()->cart->is_empty()) {
                return 0;
            }
            $qty = 0;
            foreach (WC()->cart->get_cart() as $key => $cart_item) {
                if (isset($cart_item["_web2ink_design_form"])) {
                    if (!empty($cart_item["_web2ink_design_form"]["labels"]["design"]) && $cart_item["_web2ink_design_form"]["labels"]["design"] == $group_term) {
                        $qty += $cart_item["quantity"];
                    }
                }
            }
            return $qty;
        }

        /* ================================
        ==== PRICE QUOTE SUPPLY WAY =======
        =================================*/

        // @params - array, assumed: charges, addons, qty
        public static function getPriceSupply($params)
        {
            $cache = "w2i-pricing-supply-" . md5(serialize($params));
            $price = wp_cache_get($cache, "w2i_pricing");
            if ($price !== false) {
                return $price;
            }
            $qty = max(1, !empty($params["qty"]) ? $params["qty"] : 1);
            $addons = !empty($params["addons"]) ? $params["addons"] : array();
            $charges = !empty($params["charges"]) ? $params["charges"] : array();
            $user_discount = web2ink_pricing::getUserDiscount();
            if (!empty($user_discount["process"])) {
                $charges["process"] = $user_discount["process"];
            }
            $process = self::getProcess($charges["process"]);
            $minqty = max(1, $process["minqty"]);
            if ($user_discount["minqty"] === true) {
                $minqty = 1;
            }
            $price = self::getChargesPrice($charges, $qty);
            if (!empty($user_discount["design"])) {
                $discount = round($price * (min(100, $user_discount["design"]) / 100), 4);
                $price = max(0, $price - $discount);
            }
            if (!empty($addons)) {
                $price += self::getPriceSupplyAddons($addons, $qty);
            }
            $ret = array("price" => $price, "total" => round($price * $qty, 2), "qty" => $qty, "minqty" => $minqty);
            wp_cache_add($cache, $ret, "w2i_pricing", 60);
            return $ret;
        }

        public static function getPriceSupplyAddons($addons, $qty)
        {
            if (empty($addons)) {
                return 0;
            }
            $web2ink = web2ink_options();
            if (empty($web2ink["supply_options"])) {
                return 0;
            }
            $price = 0;
            $ids = array();
            foreach ($addons as $name => $value) {
                $ids[] = preg_replace("/[^0-9]/", '', $value);
            }
            foreach ($web2ink["supply_options"] as $addon) {
                if (!empty($addon["options"])) {
                    foreach ($addon["options"] as $option) {
                        if (in_array($option["id"], $ids) && !empty($option["price"])) {
                            $charge = self::findPrice($option["price"], $qty);
                            if (!empty($charge)) {
                                $price += $charge;
                            }
                        }
                    }
                }
            }
            return $price;
        }

        /* ================================
        ==== MAIN PRICING METHOD ==========
        =================================*/

        public static function getPrice($args = null, $asArray = false)
        {
            $args = self::fixArgs($args);
            if (empty($args)) {
                return 0;
            }
            if (empty($args["product"])) {
                return 0;
            }
            $is_store = false;
            if (!empty($args["design"]) && !empty($args["saved_design_is_store_item"])) {
                $is_store = true;
            }

            // check the cache
            $cache = "w2i-pricing-" . md5(serialize($args));
            $data = wp_cache_get($cache, "w2i_pricing");
            if ($data !== false) {
                $filtered = apply_filters("web2ink_pricing_get_price_result", $data);
                if (!empty($filtered)) {
                    $data = $filtered;
                }
                return $asArray ? $data : round($data["price"]["unit"], 4);
            }

            // get product details
            $metas = self::getMetas($args["product"]);
            if (empty($metas)) {
                return 0;
            } // probably not web2ink product
            $user_discount = web2ink_pricing::getUserDiscount();
            $args["user_discount"] = $user_discount;
            $custom_pricing = !empty($args["custom_price"]) ? $args["custom_price"] : array();

            // detect print process
            $prcid = !empty($metas["print_process_id"]) ? $metas["print_process_id"] : (!empty($metas["process_id"]) ? $metas["process_id"] : 0);
            if (!empty($args["charges"]["process"])) {
                $prcid = $args["charges"]["process"];
            }
            if (empty($prcid) && !empty($metas["print_processes"])) {
                $prcid = (int) $metas["print_processes"][0];
            }
            if ($is_store) {
                $prcid = self::getStoreProcess($prcid);
            } // custom stores may has own process
            if (!empty($user_discount["process"])) {
                $prcid = $user_discount["process"];
            } // customer discount option
            $args["charges"]["process"] = $prcid;
            $process = self::getProcess($prcid);

            // decide min quantity
            $minqty = max(1, $metas["minqty"], $process["minqty"]);
            if (!empty($custom_pricing)) {
                $minqty = max(1, min(array_keys($custom_pricing)));
            } // design has custom pricing
            if ($user_discount["minqty"] === true) {
                $minqty = 1;
            } // min qty from user discount
            $forqty = max($minqty, $args["qty"]);
            if ($is_store) {
                $stores = web2ink_helper::getCustomStoreSettingsValue();
                if ($stores["minimum_quantity_status"] == 1) { // ignore min qty for store item
                    $minqty = 1;
                    $forqty = max(1, $args["qty"]);
                }
            }
            // prepare data
            if (!isset($args["title"])) {
                $args["title"] = "";
            }
            if (!isset($metas["title"])) {
                $metas["title"] = !empty($metas["name"]) ? $metas["name"] : "";
            }
            $retArray = array(
                "price" => array("qty" => $args["qty"], "minqty" => $minqty, "unit" => 0, "total" => 0, "squnit" => 0, "design" => 0, "color" => 0, "size" => 0, "addons" => 0, "onetime" => 0, "profit" => 0, "discount" => 0),
                "labels" => array("item" => $args["title"], "product" => $metas["title"], "process" => $process["print_process_name"], "color" => "", "size" => "", "addons" => array()),
                "addons" => array(),
                "args" => $args
            );
            if (empty($retArray["labels"]["item"])) {
                $retArray["labels"]["item"] = $retArray["labels"]["product"];
            }

            // product base price 
            if (empty($metas["price_chart"])) {
                $metas["price_chart"] = array();
            }
            $price = self::findPrice($metas["price_chart"], $forqty, $metas["price_method"]);
            if (empty($metas["price_group"])) {
                $metas["price_group"] = "each";
            }
            // price based on qty price break point
            if ($metas["price_group"] == "all" || $metas["price_group"] == "onetime") {
                $qty_point = 0;
                foreach ($metas["price_chart"] as $price_qty => $price_price) {
                    if (empty($qty_point) || $price_qty <= $forqty) {
                        $qty_point = $price_qty;
                    }
                }
                if (empty($qty_point)) {
                    $qty_point = $forqty;
                }
                if ($metas["price_group"] == "all") {
                    $price = round($price / $qty_point, 4);
                } else {
                    $price = round($price / $forqty, 4);
                }
            }
            $round_decimals = floor($price) < 2 ? 4 : 2;

            // product part pricing option - usually no size, find lowest    
            if (!empty($metas["use_parts"])) {
                $_price = self::getPartPrice($args["product"], (!empty($args["color"]) ? $args["color"] : 0), (!empty($args["size"]) ? $args["size"] : 0));
                if (!empty($_price)) {
                    $retArray["args"]["use_part"] = true;
                    $retArray["price"]["part"] = $_price;
                    $price = $_price;
                }
            }

            // product one time
            if (!empty($metas["price_one_time"])) {
                $product_one_time = (float) $metas["price_one_time"];
                if (is_user_logged_in() && self::hasReorderProduct($args["product"])) {
                    $product_one_time = 0;
                } else {
                    $cart_qty = self::getCartTotelQty($args["product"]);
                    if (empty($cart_qty) || empty($args["cart_hash"])) {
                        $cart_qty = max(1, $args["qty"]);
                    }
                    $price += round($product_one_time / $cart_qty, 4);
                }
                $retArray["price"]["onetime"] = $product_one_time;
            }

            // allow filter base price by plugins
            $filtered = apply_filters("web2ink_pricing_get_price_base", $price, $args["product"], $forqty);
            if ($filtered != $price) {
                $retArray["price"]["base_unfiltered"] = $price;
                $price = $filtered;
            }
            $retArray["price"]["base"] = $price;

            // square unit size charge
            if (empty($metas["sizetype"])) {
                $metas["sizetype"] = "fixed";
            }
            if ($metas["sizetype"] == "range") {
                $sides = isset($args["charges"]["sides"]) ? max(1, $args["charges"]["sides"]) : 1;
                $psize = self::normalizeSqUnits($args, $metas);
                $uprice = isset($metas["square_unit_price"]) ? (float) $metas["square_unit_price"] : 0;
                if ($sides > 1 && !empty($metas["square_unit_price_dbl"])) {
                    $uprice = $metas["square_unit_price_dbl"];
                }
                if (!empty($metas["square_unit_chart"])) {
                    $size_total = $psize * $args["qty"];
                    foreach ($metas["square_unit_chart"] as $kus) {
                        if ($kus["size"] <= $size_total) {
                            $uprice = $kus["single"];
                            if ($sides > 1 && !empty($kus["double"])) {
                                $uprice = $kus["double"];
                            }
                        }
                    }
                }
                $filtered = apply_filters("web2ink_pricing_get_price_base", $uprice, $args["product"]);
                if ($filtered != $uprice) {
                    $retArray["price"]["base_unfiltered"] = $uprice;
                    $uprice = $filtered;
                }
                $retArray["price"]["squnit"] = round($psize * $uprice, 4);
                $retArray["labels"]["size"] = sprintf("%0.2f", $args["width"]) . " x " . sprintf("%0.2f", $args["height"]) . " " . $args["unit"];

                // allow calculate square unit price by advanced logic per each, since 1stJuly25
                $filtered = apply_filters("web2ink_pricing_get_price_base_range", 0, $args);
                if (!empty($filtered)) {
                    $retArray["price"]["base_unfiltered"] = $retArray["price"]["squnit"];
                    $retArray["price"]["squnit"] = $filtered;
                }

                $price += $retArray["price"]["squnit"];
            }

            // markup for store items
            if ($is_store) {
                $markup = self::getStoreMarkup($args["author"]);
                if ($markup != 0) {
                    $retArray["price"]["markup"] = (100 + $markup) / 100;
                    $price = $price * $retArray["price"]["markup"];
                }
            }

            // color/size upcharges
            if (empty($args["price"]["part"])) {
                if (!empty($args["color"])) {
                    $retArray["labels"]["color"] = "Color #" . $args["color"];
                    $color = get_term($args["color"], "web2ink_product_color");
                    if ($color) {
                        $retArray["labels"]["color"] = $color->name;
                    }
                    $retArray["price"]["color"] = (float) get_term_meta($args["color"], "web2ink_color_upcharge", true);
                    $price += $retArray["price"]["color"];
                }
                if (!empty($args["size"])) {
                    $sizeid = is_array($args["size"]) ? (isset($args["size"]["wooid"]) ? $args["size"]["wooid"] : $args["size"]["id"]) : intval($args["size"]);
                    if (!empty($sizeid)) {
                        $size = get_term($sizeid, "web2ink_product_size");
                        if ($size && empty($retArray["labels"]["size"])) {
                            $retArray["labels"]["size"] = $size->name;
                            $retArray["args"]["sizeid"] = $sizeid;
                        }
                    }
                }
            }

            // detect variation ID
            $retArray["args"]["variable_id"] = 0;
            $atts = array();
            if (!empty($args["color"])) {
                $atts[] = array("id" => $args["color"], "type" => "term", "taxonomy" => "web2ink_product_color");
            }
            if (!empty($sizeid)) {
                $atts[] = array("id" => $sizeid, "type" => "term", "taxonomy" => "web2ink_product_size");
            }
            $variations = self::findVariations($args["product"], $atts, false);
            if (!empty($variations)) {
                $retArray["args"]["variable_id"] = $variations[0]["id"];
            }

            // addons / variations    
            $addons = array();
            if (!empty($args["addons"]) && is_array($args["addons"])) {
                // fix addons data
                $kus = reset($args["addons"]);
                $key = key($args["addons"]);
                if (substr($key, 0, 10) == "attribute_") {
                    $addons = self::fixAddons($args["product"], $args["addons"], $forqty, $price); // pricing form way
                } elseif (is_array($kus) && isset($kus["label"])) {
                    $pole = array(); // designer tool request
                    foreach ($args["addons"] as $kus) {
                        if (!isset($pole[$kus["label"]]) && !empty($kus["value"])) {
                            $pole[$kus["label"]] = $kus["value"];
                        }
                    }
                    $addons = self::fixAddons($args["product"], $pole, $forqty, $price);
                }
                // get addon price
                $addons = !empty($addons) ? array_filter($addons) : array();
                if (!empty($addons)) {
                    $retArray["addons"] = $addons;
                    foreach ($addons as $kus) {
                        $retArray["labels"]["addons"][] = $kus["title"];
                    }
                    // variation prices - woo
                    $atts = array_filter($addons, function ($kus) {
                        return empty($kus["type"]) ? false : ($kus["type"] != "addon");
                    });
                    if (!empty($args["color"])) {
                        $atts[] = array("id" => $args["color"], "type" => "term", "taxonomy" => "web2ink_product_color");
                    }
                    if (!empty($sizeid)) {
                        $atts[] = array("id" => $sizeid, "type" => "term", "taxonomy" => "web2ink_product_size");
                    }
                    $variations = self::findVariations($args["product"], $atts);
                    if (!empty($variations)) {
                        foreach ($variations as $variation) {
                            if ($retArray["args"]["variable_id"] == 0) {
                                $retArray["args"]["variable_id"] = $variation["id"];
                                ;
                            }
                            if (!empty($variation["price"])) {
                                $retArray["price"]["addons"] += $variation["price"];
                                $retArray["args"]["variable_id"] = $variation["id"];
                            }
                        }
                    }
                    // addons price - plugin
                    $adds = array_filter($addons, function ($kus) {
                        return empty($kus["type"]) ? false : ($kus["type"] == "addon");
                    });
                    foreach ($adds as $addon) {
                        if (!empty($addon["price"])) {
                            $retArray["price"]["addons"] += $addon["price"];
                        }
                    }
                    $price += $retArray["price"]["addons"];
                }
            }

            // apply user discount
            if (!empty($user_discount["product"])) {
                $discount = round($price * (min(100, $user_discount["product"]) / 100), 4);
                $retArray["price"]["discount"] += $discount;
                $price = max(0, $price - $discount);
            }

            // design price = print charge  
            if (!empty($args["charges"])) {
                $design_qty = $forqty;
                $retArray["args"]["designqty"] = $forqty;
                if (!empty($args["collect"]) && !empty($args["collect_qty"])) {
                    $design_qty = $args["collect_qty"];
                    $retArray["price"]["designqty"] = $design_qty;
                }
                $retArray["price"]["design"] = self::getChargesPrice($args["charges"], $design_qty);
                if (!empty($user_discount["design"])) {
                    $discount = round($retArray["price"]["design"] * (min(100, $user_discount["design"]) / 100), 4);
                    $retArray["price"]["discount"] += $discount;
                    $retArray["price"]["design"] = max(0, $retArray["price"]["design"] - $discount);
                }
                $price += $retArray["price"]["design"];
            }

            // store template profit and min qty
            $store_id = !empty($args["author"]) ? $args["author"] : 0;
            $store_did = !empty($args["design"]) ? $args["design"] : 0;
            if (!empty($args["charges"]["template"])) {
                $stores = !empty($stores) ? $stores : web2ink_helper::getCustomStoreSettingsValue();
                if ($stores["profit_type"] > 1) { // store profit allowed generally
                    $template = self::getDesign($args["charges"]["template"]);
                    if (!empty($template["store_template"])) {
                        $is_store = true;
                        $store_did = (int) $args["charges"]["template"];
                        $store_id = (int) $template["author"];
                        if (!empty($template["custom_price"])) {
                            $custom_pricing = $template["custom_price"];
                        }
                    }
                }
                // check min qty
                if ($retArray["price"]["minqty"] > 1) {
                    if (!empty($stores["minimum_quantity_status"])) {
                        $retArray["price"]["minqty"] = 1;
                    } else {
                        // detect minimum by custom store price
                        $template_metas = (array) get_post_meta($args["charges"]["template"], "_web2ink_saved_design", true);
                        if (!empty($template_metas["custom_price"]) && is_array($template_metas["custom_price"])) {
                            $retArray["price"]["minqty"] = max(1, min(array_keys($template_metas["custom_price"])));
                        }
                    }
                }
            }

            // design custom pricing - apply also from templates
            if (!empty($custom_pricing)) {
                $price = self::findPrice($custom_pricing, $forqty);
                $retArray["price"]["custom"] = $price;
                if (!empty($retArray["price"]["addons"])) {
                    $price += $retArray["price"]["addons"];
                }
            }

            // profits
            if (!empty($store_did) && $is_store && $stores["profit_type"] > 1) {
                $shop = array("id" => $store_id, "profittype" => $stores["profit_type"], "value" => 0, "type" => "percent", "profit" => 0);
                // Profit from sales - no added price
                if ($stores["profit_type"] == 2) {
                    $shop["value"] = (float) $stores["profit_percentage_every_sale"];
                    $shop["profit"] = round(($price * $shop["value"]) / 100, 2);
                }
                // General up-charge
                if ($stores["profit_type"] == 3) {
                    $shop["value"] = (float) $stores["profit_item_upcharged_value"];
                    $shop["profit"] = round(($price * $shop["value"]) / 100, 2);
                }
                // Individual up-charge
                if ($stores["profit_type"] == 4) {
                    $shop["value"] = (float) $stores["profit_default_percentage_upcharge"];
                    $custom = (int) get_user_meta($shop["id"], "web2ink_store_individual_up_charge_profit_percentage_apply", true);
                    if ($custom == 1) {
                        $shop["value"] = (float) get_user_meta($shop["id"], "web2ink_store_individual_up_charge_profit_percentage", true);
                    }
                    $shop["profit"] = round(($price * $shop["value"]) / 100, 2);
                }
                // Profit decide store owner
                if ($stores["profit_type"] == 5) {
                    $customtype = get_user_meta($shop["id"], "web2ink_store_profit_type", true);
                    if ($customtype == "amount") {
                        $shop["type"] = "amount";
                        $shop["value"] = (float) get_user_meta($shop["id"], "web2ink_store_profit_percentage", true);
                        $shop["profit"] = $shop["value"];
                    } else {
                        $shop["value"] = (float) get_user_meta($shop["id"], "web2ink_store_profit_percentage", true);
                        $shop["profit"] = round(($price * $shop["value"]) / 100, 2);
                    }
                    // custom profit set by owner
                    $admin_profit = (float) get_post_meta($store_did, "_web2ink_custom_profit", true);
                    if (!empty($admin_profit)) {
                        $shop["type"] = get_post_meta($store_did, "_web2ink_custom_profit_type", true);
                        $shop["value"] = $shop["profit"] = $admin_profit;
                        $shop["forced"] = "design";
                        if ($shop["type"] == "percent") {
                            $shop["profit"] = round(($admin_profit / 100) * $price, 2);
                        }
                    }
                }
                // custom profit set by adminstrator
                $admin_profit = (float) get_post_meta($store_did, "_web2ink_admin_profit", true);
                if (!empty($admin_profit)) {
                    $shop["profittype"] = "admin";
                    $shop["forced"] = "admin";
                    $shop["type"] = get_post_meta($store_did, "_web2ink_admin_profit_type", true);
                    $shop["value"] = $shop["profit"] = $admin_profit;
                    if ($shop["type"] == "percent") {
                        $shop["profit"] = round(($admin_profit / 100) * $price, 2);
                    }
                }
                // apply profit
                $retArray["price"]["profit"] = $shop["profit"];
                if ($stores["profit_type"] > 2) {
                    $price += $retArray["price"]["profit"];
                }
                $retArray["store"] = $shop;
            }

            // add group-related unique hash
            $prehash = array($args["product"], (empty($args["color"]) ? 0 : $args["color"]), (empty($args["design"]) ? 0 : $args["design"]));
            if (!empty($retArray["web2inkAddItemName"])) {
                $prehash[] = $retArray["web2inkAddItemName"];
            }
            if (!empty($retArray["web2inkAddItemNumber"])) {
                $prehash[] = $retArray["web2inkAddItemNumber"];
            }
            if (!empty($retArray["addons"])) {
                $prehash[] = serialize($retArray["addons"]);
            }
            if (!empty($args["ordertag"])) {
                $prehash[] = serialize($args["ordertag"]);
            }
            $retArray["args"]["uhash"] = md5(implode("|", $prehash));

            // general/product markup
            $markuped = apply_filters("web2ink_markup_price", $price, $args["product"]);
            if ($markuped > $price) {
                $retArray["price"]["markup"] = round($markuped - $price, $round_decimals);
                $price += $retArray["price"]["markup"];
            }

            // addon charges related to total cost or printing, since 20th Feb 2022
            $adds = array_filter($addons, function ($kus) {
                if (empty($kus["type"]) || $kus["type"] != "addon" || empty($kus["w2i_type"]) || empty($kus["percent"])) {
                    return false;
                }
                return ($kus["w2i_type"] == "percent2" || $kus["w2i_type"] == "percent3");
            });
            if (!empty($adds)) {
                $charge = 0;
                foreach ($adds as $add) {
                    // percent off total cost
                    if ($add["w2i_type"] == "percent2") {
                        $charge += round(($add["percent"] / 100) * $price, 2);
                    }
                    // percent of printing
                    if ($add["w2i_type"] == "percent3") {
                        $charge += round(($add["percent"] / 100) * $retArray["price"]["design"], 4);
                    }
                }
                if (!empty($charge)) {
                    $price += $charge;
                    $retArray["price"]["addons"] += $charge;
                }
            }

            // rounding to nearest
            $rounded = apply_filters("web2ink_round_price", $price);
            if ($rounded != $price) {
                $retArray["price"]["rounding"] = $rounded - $price;
                ;
                $price = $rounded;
            }

            // summarry
            $retArray["price"]["unit"] = round($price, 4);
            $retArray["price"]["total"] = $retArray["price"]["unit"] * $retArray["price"]["qty"];
            wp_cache_add($cache, $retArray, "w2i_pricing", 60);
            $filtered = apply_filters("web2ink_pricing_get_price_result", $retArray);
            if (!empty($filtered)) {
                $retArray = $filtered;
            }

            /* Boss Started Here */
            $new_price = $asArray ? $retArray : round($price, $round_decimals);

            $reseller_margin = get_post_meta($args["product"], '_ms_price_margin', true);
            $_original_product_id = get_post_meta($args["product"], '_original_product_id', true);
            if ($_original_product_id) {
                $reseller_margin = $reseller_margin ? $reseller_margin : get_option('dtfr_default_product_margin', 0);

                // file_put_contents(__DIR__ . '/log.txt', 'reseller_margin: ' . print_r($reseller_margin, true) . PHP_EOL, FILE_APPEND);

                // file_put_contents(__DIR__ . '/log.txt', 'before' . PHP_EOL, FILE_APPEND);
                // file_put_contents(__DIR__ . '/log.txt', 'unit: ' . print_r($new_price['price']['unit'], true) . PHP_EOL, FILE_APPEND);
                // file_put_contents(__DIR__ . '/log.txt', 'design: ' . print_r($new_price['price']['design'], true) . PHP_EOL, FILE_APPEND);
                // file_put_contents(__DIR__ . '/log.txt', 'total: ' . print_r($new_price['price']['total'], true) . PHP_EOL, FILE_APPEND);
                
                $new_price['price']['unit'] = $new_price['price']['unit'] + $new_price['price']['unit'] * $reseller_margin / 100;
                $new_price['price']['design'] = $new_price['price']['design'] + $new_price['price']['design'] * $reseller_margin / 100;
                $new_price['price']['total'] = $new_price['price']['total'] + $new_price['price']['total'] * $reseller_margin / 100;

                // file_put_contents(__DIR__ . '/log.txt', 'after' . PHP_EOL, FILE_APPEND);
                // file_put_contents(__DIR__ . '/log.txt', 'unit: ' . print_r($new_price['price']['unit'], true) . PHP_EOL, FILE_APPEND);
                // file_put_contents(__DIR__ . '/log.txt', 'design: ' . print_r($new_price['price']['design'], true) . PHP_EOL, FILE_APPEND);
                // file_put_contents(__DIR__ . '/log.txt', 'total: ' . print_r($new_price['price']['total'], true) . PHP_EOL, FILE_APPEND);
            }
            /* Boss Ended Here */

            return $new_price;
        }

        public static function getPartPrice($product_id, $color_id, $size_id = null)
        {
            global $wpdb;
            if (empty($product_id) || empty($color_id)) {
                return 0;
            }
            if (empty($size_id)) {
                $size_id = 0;
            }
            if (is_array($size_id)) {
                $size_id = isset($size_id["id"]) ? intval($size_id["id"]) : 0;
            }
            $cache = "w2i-pp-" . implode("_", array($product_id, $color_id, $size_id));
            $price = wp_cache_get($cache, "w2i_pricing");
            if ($price !== false) {
                return $price;
            }
            $sql = "SELECT price FROM {$wpdb->prefix}web2ink_part_price WHERE post_id='" . $product_id . "' AND price > 0 AND ";
            if (!empty($size_id)) {
                $sql .= "part_id_wp='" . strval(intval($color_id)) . "_" . strval(intval($size_id)) . "' LIMIT 1";
            } else {
                $sql .= "part_id_wp LIKE '" . strval(intval($color_id)) . "_%' ORDER BY price LIMIT 1";
            }
            $result = $wpdb->get_var($sql);
            $price = !empty($result) ? (float) $result : 0;
            wp_cache_add($cache, $price, "w2i_pricing");
            return $price;
        }

        public static function productPriceVariation($price_details, $size_id)
        {
            global $wpdb;
            if (empty($price_details) || empty($size_id) || empty($price_details["args"])) {
                return null;
            }
            $options = array(
                "product" => !empty($price_details["args"]["product"]) ? (int) $price_details["args"]["product"] : 0,
                "color" => !empty($price_details["args"]["color"]) ? (int) $price_details["args"]["color"] : 0,
                "size" => (int) $size_id,
                "qty" => !empty($price_details["args"]["qty"]) ? (int) $price_details["args"]["qty"] : 1,
            );
            if (!empty($price_details["args"]["addons"])) {
                foreach ($price_details["args"]["addons"] as $attr => $opt) {
                    if (is_array($opt)) {
                        $options[$opt["value"]] = 1;
                    } else {
                        $options[$opt] = 1;
                    }
                }
            }
            $cache = "w2i-pv-" . md5(serialize($options));
            $price = wp_cache_get($cache, "w2i_pricing");
            if ($price !== false) {
                return apply_filters("web2ink_round_price", (float) $price);
            }
            // check product part pricing - is based on color/size, this is the reason why here
            if (!empty($price_details["args"]["use_part"])) {
                $part_price = self::getPartPrice($options["product"], $options["color"], $options["size"]);
                if (!empty($part_price)) {
                    // add charges
                    foreach (array("design", "addons", "onetime", "profit") as $price_item) {
                        if (!empty($price_details["price"][$price_item])) {
                            $part_price += $price_details["price"][$price_item];
                        }
                    }
                    // apply discount
                    foreach (array("discount") as $price_item) {
                        if (!empty($price_details["price"][$price_item])) {
                            $part_price = max(0, $part_price - $price_details["price"][$price_item]);
                        }
                    }
                    // markup for store items
                    if (!empty($price_details["price"]["markup"])) {
                        $part_price = $part_price * $retArray["price"]["markup"];
                    }
                    // design custom pricing - apply also from templates
                    if (!empty($price_details["price"]["custom"])) {
                        $part_price = $price_details["price"]["custom"];
                        if (!empty($price_details["price"]["addons"])) {
                            $part_price += $price_details["price"]["addons"];
                        }
                    }
                    // general/product markup
                    $markuped = apply_filters("web2ink_markup_price", $part_price, $options["product"]);
                    if ($markuped > $part_price) {
                        $part_price += round($markuped - $part_price, 2);
                    }
                    // rounding to nearest
                    $price = apply_filters("web2ink_round_price", $part_price);
                }
            }
            $vars = $wpdb->get_results($wpdb->prepare("SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s ORDER BY meta_id DESC", $options["product"], "_web2ink_price_variation"), ARRAY_A);
            if (empty($vars)) {
                wp_cache_add($cache, (float) $price, "w2i_pricing", 60);
                return 0;
            }
            // arrange results
            $arrange = array_filter((array) get_post_meta($options["product"], "_web2ink_price_variation_arrange", true));
            if (!empty($arrange)) {
                usort($vars, function ($a, $b) use ($arrange) {
                    $ak = array_search($a["meta_id"], $arrange);
                    $bk = array_search($b["meta_id"], $arrange);
                    if ($ak === $bk) {
                        return 0;
                    }
                    if ($ak === false && $bk !== false) {
                        return 1;
                    }
                    if ($ak !== false && $bk === false) {
                        return -1;
                    }
                    return ($ak < $bk) ? -1 : 1;
                });
            }
            // lookup for valid price chart
            $variation = null;
            $price_chart = null;
            foreach ($vars as $var) {
                $data = maybe_unserialize($var["meta_value"]);
                if (empty($data["options"]) || empty($data["prices"])) {
                    continue;
                }
                $is_valid = true;
                foreach ($data["options"] as $kus) {
                    if ($kus["value"] == "any" || $kus["value"] == "any2") {
                        continue;
                    }
                    if ($kus["name"] == "color" && $options["color"] != (int) $kus["value"]) {
                        $is_valid = false;
                        break;
                    }
                    if ($kus["name"] == "size" && $options["size"] != (int) $kus["value"]) {
                        $is_valid = false;
                        break;
                    }
                    if ($kus["name"] == "addon" && !isset($options[$kus["value"]])) {
                        $is_valid = false;
                        break;
                    }
                }
                if ($is_valid) {
                    $variation = $data;
                    $price_chart = $data["prices"];
                    break;
                }
            }
            // no price chart = no variation price
            if (empty($price_chart)) {
                wp_cache_add($cache, 0, "w2i_pricing", 60);
                return 0;
            }
            // detect  price method and lookup price
            $metas = self::getMetas($options["product"]);
            $price = self::findPrice($price_chart, $options["qty"], $metas["price_method"]);
            if (empty($metas["price_group"])) {
                $metas["price_group"] = "each";
            }
            if ($metas["price_group"] == "all" || $metas["price_group"] == "onetime") {
                $forqty = 0;
                foreach ($price_chart as $price_qty => $price_price) {
                    if (empty($forqty) || $price_qty <= $options["qty"]) {
                        $forqty = $price_qty;
                    }
                }
                if (empty($forqty)) {
                    $forqty = $options["qty"];
                }
                if ($metas["price_group"] == "all") {
                    $price = round($price / $forqty, 4);
                } else {
                    $price = round($price / $options["qty"], 4);
                }
            }
            // variation any with pricing options
            $any2 = array_filter($variation["options"], function ($kus) {
                return !empty($kus["value"]) ? ($kus["value"] == "any2") : false;
            });
            if (!empty($any2)) {
                $addons_charges = array();
                $addons = !empty($price_details["args"]["addons"]) ? $price_details["args"]["addons"] : array();
                foreach ($any2 as $kus) {
                    if ($kus["name"] == "size" && !empty($options["size"])) {
                        $charge = (float) get_term_meta($options["size"], "web2ink_size_upcharge", true);
                        if (!empty($charge)) {
                            $price += $charge;
                        }
                    }
                    if ($kus["name"] == "color" && !empty($options["color"])) {
                        $charge = (float) get_term_meta($options["color"], "web2ink_color_upcharge", true);
                        if (!empty($charge)) {
                            $price += $charge;
                        }
                    }
                    if ($kus["name"] == "addon" && !empty($kus["parent"])) {
                        $tax = "attribute_" . $kus["parent"];
                        $term = isset($addons[$tax]) ? $addons[$tax] : null;
                        if (!empty($term)) {
                            $addons_charges[$tax] = $term;
                        }
                    }
                }
                if (!empty($addons_charges)) {
                    $fix = self::fixAddons($options["product"], $addons_charges, $options["qty"], $price);
                    if (!empty($fix)) {
                        foreach ($fix as $addon) {
                            if (!empty($addon["price"])) {
                                $price += $addon["price"];
                            }
                        }
                    }
                }
            }
            // product one time
            if (!empty($metas["price_one_time"]) && is_user_logged_in()) {
                if (!self::hasReorderProduct($options["product"])) {
                    $cart_qty = self::getCartTotelQty($options["product"]);
                    if (empty($cart_qty) || empty($price_details["cart_hash"])) {
                        $cart_qty = max(1, $options["qty"]);
                    }
                    $price += round((float) $metas["price_one_time"] / $cart_qty, 4);
                }
            }
            // markup for store items
            if (!empty($price_details["args"]["design"]) && !empty($price_details["args"]["saved_design_is_store_item"])) {
                $markup = self::getStoreMarkup($price_details["args"]["author"]);
                if ($markup != 0) {
                    $price = round($price * ((100 + $markup) / 100), 2);
                }
            }
            // discount by user
            $user_discount = web2ink_pricing::getUserDiscount();
            if (!empty($user_discount["product"])) {
                $discount = round($price * (min(100, $user_discount["product"]) / 100), 4);
                $price = max(0, $price - $discount);
            }
            // add printing charges
            if (!empty($price_details["price"]["design"])) {
                $price = $price + $price_details["price"]["design"];
            }
            // design custom pricing
            if (!empty($price_details["price"]["custom"])) {
                $price = $price_details["price"]["custom"];
                if (!empty($price_details["price"]["addons"])) {
                    $price += $price_details["price"]["addons"];
                }
            }
            // addon charges related to total cost or printing, since 20th Feb 2022
            if (!empty($any2)) {
                $fix = self::fixAddons($options["product"], $price_details["args"]["addons"], $options["qty"], $price);
                $adds = array_filter($fix, function ($kus) {
                    if (empty($kus["type"]) || $kus["type"] != "addon" || empty($kus["w2i_type"]) || empty($kus["percent"])) {
                        return false;
                    }
                    return ($kus["w2i_type"] == "percent2" || $kus["w2i_type"] == "percent3");
                });
                if (!empty($adds)) {
                    $charge = 0;
                    foreach ($adds as $add) {
                        // percent off total cost
                        if ($add["w2i_type"] == "percent2") {
                            $charge += round(($add["percent"] / 100) * $price, 2);
                        }
                        // percent of printing
                        if ($add["w2i_type"] == "percent3") {
                            $charge += round(($add["percent"] / 100) * $price_details["price"]["design"], 4);
                        }
                    }
                    if (!empty($charge)) {
                        $price += $charge;
                    }
                }
            }

            // ??? is profits applicable ???
            $price = apply_filters("web2ink_round_price", $price);
            wp_cache_add($cache, $price, "w2i_pricing", 60);
            return $price;
        }

        public static function getChargesPrice($charges, $qty = 0, $ignore_cache = false)
        {
            if (empty($charges) || !is_array($charges)) {
                return 0;
            }
            $process = self::getProcess(isset($charges["process"]) ? $charges["process"] : 0);
            if (empty($process["prices"])) {
                return 0;
            }
            $charges["process"] = $process["id"];
            if (!$ignore_cache) {
                $cache = "w2i-" . md5(serialize($charges) . "-" . $qty);
                $price = wp_cache_get($cache, "w2i_pricing");
                if ($price !== false) {
                    return $price;
                }
            }
            $is_reorder = isset($charges["reorder"]) ? $charges["reorder"] : false; // see web2ink_fronted::web2ink_update_order_production_date_based_on_status
            $qty = max(1, $process["minqty"], $qty);
            $prices = null;
            foreach ($process["prices"] as $kus) {
                if ($prices == null || $kus["qty"] <= $qty) {
                    $prices = $kus;
                }
            }

            // apply reorder options
            if ($is_reorder == true) {
                $reoptions = get_option("w2i_reprc_" . $charges["process"], array());
                if (!empty($reoptions)) {
                    foreach ($reoptions as $field) {
                        if (isset($prices[$field])) {
                            $prices[$field] = 0;
                        }
                    }
                }
            }

            // allow filter pricing line
            $_prices = apply_filters("web2ink_pricing_get_print_process_pricing_line", $prices, $charges, $qty);
            if (!empty($_prices)) {
                $prices = $_prices;
            }

            // fix charges data, since v.1.2.116
            if (!empty($charges["elements"]) && ($prices["Team_order_names"] + $prices["Team_order_numbers"]) > 0) { // only if team order charges present of pricing
                $exclude_sides = array();
                foreach ($charges["elements"] as $side_id => $elements) {
                    if (array_sum($elements) > 0 && array_sum($elements) == ($elements["names"] + $elements["numbers"])) {
                        $exclude_sides[] = $side_id;
                    }
                }
                if (!empty($exclude_sides)) {
                    $locations = array();
                    foreach ($charges["locations"] as $side_id => $location) {
                        if (!in_array($side_id, $exclude_sides)) {
                            $locations[$side_id] = $location;
                        }
                    }
                    $charges["sides"] = count($locations);
                    $inks = array();
                    foreach ($locations as $side_id => $location) {
                        $inks = array_merge($inks, $location);
                    }
                    $charges["unique"] = array_unique($inks);
                    $charges["colors"] = count($charges["unique"]);
                    $charges["names"] = $charges["numbers"] = 0;
                    foreach ($charges["elements"] as $side_id => $elements) {
                        if (in_array($side_id, $exclude_sides)) {
                            $charges["names"] += (int) $elements["names"];
                            $charges["numbers"] += (int) $elements["numbers"];
                        }
                    }
                    foreach ($exclude_sides as $side_id) {
                        if (isset($charges["surface"][$side_id])) {
                            unset($charges["surface"][$side_id]);
                        }
                        if (isset($charges["areas"][$side_id])) {
                            unset($charges["areas"][$side_id]);
                        }
                    }
                    $charges["locations"] = $locations;
                }
            }

            // prepare important design values
            $sides = max(1, empty($charges["sides"]) ? 1 : $charges["sides"]);
            $ucolors = 0;
            $inkcolors = array();

            // detect used colors from locations since 1.2.42
            if (!empty($charges["locations"]) && is_array($charges["locations"])) {
                foreach ($charges["locations"] as $side => $hexinks) {
                    if (is_array($hexinks) && count($hexinks) > 0) {
                        $ucolors += count($hexinks);
                        $inkcolors[] = count($hexinks);
                    }
                }
            }
            $sorted_ink_colors = $inkcolors;
            rsort($sorted_ink_colors);
            if (empty($ucolors)) {
                $ucolors = max(1, empty($charges["colors"]) ? 1 : $charges["colors"]);
            }
            $sidedisc = $discount = 0;
            if (!empty($prices["Addside_discount"]) && $sides > 1) {
                $sidedisc = min(100, abs($prices["Addside_discount"])) / 100;
            }

            // one time charges 
            $onetime = empty($prices["Flat_design"]) ? 0 : $prices["Flat_design"];
            $onetime += (empty($prices["Per_designed_side"]) ? 0 : ($prices["Per_designed_side"] * $sides));
            $onetime += (empty($prices["Color_setup"]) ? 0 : ($prices["Color_setup"] * $ucolors));
            if ($sidedisc > 0 && $sides > 1) {
                $discount += (($sides - 1) * $prices["Per_designed_side"] * $sidedisc);
                if (count($sorted_ink_colors) > 1) {
                    foreach ($sorted_ink_colors as $i => $inks) {
                        if ($i > 0) {
                            $discount += (($prices["Color_setup"] * $inks) * $sidedisc);
                        }
                    }
                }
            }
            if (!empty($discount)) {
                $onetime = round($onetime - $discount, 2);
            }
            $discount = 0;
            $sq_charges = array();

            // charges per one piece
            $unit = empty($prices["flat_charge"]) ? 0 : $prices["flat_charge"];
            $unit += (empty($charges["names"]) ? 0 : ($charges["names"] * $prices["Team_order_names"]));
            $unit += (empty($charges["numbers"]) ? 0 : ($charges["numbers"] * $prices["Team_order_numbers"]));
            if (!empty($prices["Per_designed_side_two"])) {
                $unit += $prices["Per_designed_side_two"];
            }
            if (!empty($prices["Per_designed_side_add"]) && $sides > 1) {
                $unit += ($prices["Per_designed_side_add"] * max(0, $sides - 1));
            }
            $unit += (empty($prices["First_color"]) ? 0 : ($prices["First_color"] * $sides));
            if (!empty($prices["Additional_color"]) && !empty($charges["locations"]) && is_array($charges["locations"])) {
                foreach ($charges["locations"] as $location) {
                    if (is_array($location) && count($location) > 1) {
                        $unit += ((count($location) - 1) * $prices["Additional_color"]);
                    }
                }
            }
            if (!empty($prices["Per_color"]) && count($sorted_ink_colors) > 0) {
                foreach ($sorted_ink_colors as $i => $inks) {
                    $per_color = 0;
                    foreach ($prices["Per_color"] as $color_count => $color_charges) {
                        if (empty($per_color) || $color_count <= $inks) {
                            $per_color = ($i == 0 ? $color_charges["first"] : (!empty($color_charges["additional"]) ? $color_charges["additional"] : $color_charges["first"]));
                        }
                    }
                    $unit += max(0, $per_color);
                }
            }
            if (!empty($prices["Squar_unit"])) {
                $sqinchdim = $surface = 0;
                if (isset($charges["dimension"]) && is_array($charges["dimension"])) {
                    $squnit = $charges["dimension"]["unit"];
                    if (!in_array($squnit, array("inch", "foot", "cm", "m"))) {
                        $squnit = "inch";
                    }
                    $sqinchdim = (float) $charges["dimension"]["width"] * (float) $charges["dimension"]["height"];
                    if ($squnit == "foot") {
                        $sqinchdim *= 144;
                    }
                    if ($squnit == "cm") {
                        $sqinchdim *= pow(1 / 2.54, 2);
                    }
                    if ($squnit == "m") {
                        $sqinchdim *= pow(1 / 0.0254);
                    }
                    $sqinchdim = round($sqinchdim, 4);
                }
                if (!empty($charges["surface"])) {
                    foreach ($charges["surface"] as $side => $percent) {
                        $sqside = $sqinchdim;
                        if (!empty($charges["areas"][$side])) {
                            $sqside = $charges["areas"][$side]["inches"] * ($charges["areas"][$side]["inches"] * ($charges["areas"][$side]["height"] / $charges["areas"][$side]["width"]));
                        }
                        $charge = round($sqside * ($percent / 100), 4);
                        $sq_charges[] = $charge;
                        $surface += $charge;
                    }
                }
                if (!empty($surface)) {
                    $unit += round($surface * $prices["Squar_unit"], 4);
                }
                rsort($sq_charges);
            }

            // design discount on "per each" part
            if ($sidedisc > 0 && $sides > 1) {
                $discount += ($prices["Per_designed_side_two"] * ($sides - 1) * $sidedisc);
                $discount += ($prices["First_color"] * ($sides - 1) * $sidedisc);
                foreach ($sorted_ink_colors as $i => $inks) {
                    if ($i > 0) {
                        $discount += (($prices["Additional_color"] * max(0, $inks - 1)) * $sidedisc);
                    }
                }
                foreach ($sq_charges as $i => $charge) {
                    if ($i > 0) {
                        $discount += ($charge * $sidedisc);
                    }
                }
                if (!empty($prices["Per_color"]) && count($sorted_ink_colors) > 1) {
                    foreach ($sorted_ink_colors as $i => $inks) {
                        if ($i == 0) {
                            continue;
                        } // highest is first side, skip first, apply on additional side discount only
                        $per_color = 0;
                        foreach ($prices["Per_color"] as $color_count => $charges) {
                            if (empty($per_color) || $color_count <= $inks) {
                                $per_color = (!empty($charges["additional"]) ? $charges["additional"] : $charges["first"]);
                            }
                        }
                        $discount += ($sidedisc * max(0, $per_color));
                    }
                }
            }
            if (!empty($discount)) {
                $unit = round($unit - $discount, 4);
            }

            // ink color charges
            if (!empty($charges["inkids"])) {
                $ink_charges = array();
                foreach ($process["colors"] as $pcolor) {
                    if (isset($pcolor["charge"]) && (float) $pcolor["charge"] > 0) {
                        $ink_charges[(int) $pcolor["id"]] = (float) $pcolor["charge"];
                    }
                }
                foreach ($charges["inkids"] as $sideid => $ink_ids) {
                    $ink_ids = is_array($ink_ids) ? array_filter(array_map("intval", $ink_ids)) : array();
                    if (!empty($ink_ids)) {
                        foreach ($ink_ids as $ink_id) {
                            if (isset($ink_charges[$ink_id])) {
                                $unit += max(0, (float) $ink_charges[$ink_id]);
                            }
                        }
                    }
                }
            }
            $ret = round(($onetime + ($unit * $qty)) / $qty, 2);
            if (!$ignore_cache) {
                wp_cache_add($cache, $ret, "w2i_pricing");
            }
            return $ret;
        }

        public static function findVariations($pid, $attributes, $nopriced = true)
        {
            if (empty($pid) || empty($attributes) || !is_array($attributes)) {
                return $attributes;
            }
            $attnames = array();
            foreach ($attributes as $kus) {
                if ($kus["type"] != "addon" && !empty($kus["taxonomy"])) {
                    if (substr($kus["taxonomy"], 0, 16) == "web2ink_product_" && is_numeric($kus["id"])) {
                        $att_term = get_term($kus["id"]);
                        if (!empty($att_term)) {
                            $attnames["attribute_web2ink" . substr($kus["taxonomy"], 16)] = strtolower($att_term->name);
                            continue;
                        }
                    }
                    $attnames["attribute_" . $kus["taxonomy"]] = empty($kus["slug"]) ? $kus["id"] : strtolower($kus["slug"]);
                }
            }
            if (!$nopriced) {
                add_filter("woocommerce_hide_invisible_variations", "__return_false", 10);
            }
            $ret = array();
            $product = new WC_Product_Variable($pid);
            $available = $product->get_available_variations();
            if (empty($available)) {
                return array();
            }
            $pos = 0;
            foreach ($available as $i => $variation) {
                $price = !empty($variation["display_regular_price"]) ? (float) $variation["display_regular_price"] : (!empty($variation["display_price"]) ? (float) $variation["display_price"] : 0);
                if (empty($price) && $nopriced) {
                    continue;
                } // no added price
                $att = array_filter($variation["attributes"]);
                if (count($att) == 0) {
                    $ret[] = array("id" => $variation["variation_id"], "price" => $price, "type" => "variation", "position" => 99);
                    continue;
                } // valid for all
                $isvalid = true;
                foreach ($att as $tax => $slug) {
                    if (!isset($attnames[$tax]) || $attnames[$tax] != strtolower($slug)) {
                        $isvalid = false;
                        break;
                    }
                }
                if ($isvalid) {
                    $ret[] = array("id" => $variation["variation_id"], "price" => $price, "type" => "variation", "position" => $pos);
                    $pos++;
                }
            }
            if (!empty($ret) && count($ret) > 1) {
                usort($ret, function ($a, $b) {
                    if ($a["position"] == $b["position"]) {
                        return 0;
                    }
                    return ($a["position"] < $b["position"]) ? -1 : 1;
                });
            }
            return $ret;
        }

        // item: array of w2i metadata, return attributes for woo add to cart method  
        public static function getVariationAttributes($item)
        {
            if (empty($item) || !is_array($item)) {
                return array();
            }
            $atts = array();
            if (!empty($item["addons"])) {
                foreach ($item["addons"] as $addon) {
                    if ($addon["type"] == "term") {
                        if (empty($addon["slug"]) && !empty($addon["id"])) {
                            $term = get_term((int) $addon["id"]);
                            if ($term) {
                                $addon["slug"] = $term->slug;
                            }
                        }
                        if ($addon["taxonomy"] == "web2ink_product_color") {
                            $addon["taxonomy"] = "web2inkcolor";
                        }
                        if ($addon["taxonomy"] == "web2ink_product_size") {
                            $addon["taxonomy"] = "web2inksize";
                        }
                        $atts["attribute_" . $addon["taxonomy"]] = $addon["slug"];
                    }
                    if ($addon["type"] == "meta") {
                        $atts["attribute_" . $addon["taxonomy"]] = $addon["option"];
                    }
                }
            }
            // fix missing attributes
            $product = wc_get_product($item["args"]["product"]);
            foreach ($product->get_attributes() as $tax => $attribute) {
                if ($attribute["is_variation"] && !isset($atts["attribute_" . $tax])) {
                    $opts = $attribute->get_options();
                    if (substr($attribute["name"], 0, 3) == "pa_") {
                        $term = get_term((int) $opts[0]);
                        if ($term) {
                            $atts["attribute_" . $tax] = $term->slug;
                        }
                    } else {
                        $atts["attribute_" . $tax] = $opts[0];
                    }
                }
            }
            return $atts;
        }

        // ================ print processes ==========

        public static function getProcess($id)
        {
            $processes = self::getProcesses();
            if (empty($processes)) {
                return array();
            }
            if (isset($processes[$id])) {
                $_process = apply_filters("web2ink_print_process", $processes[$id]);
                return !empty($_process) ? $_process : $processes[$id];
            }
            foreach ($processes as $process) {
                if ($process["is_default"] == 1) {
                    $_process = apply_filters("web2ink_print_process", $process);
                    return !empty($_process) ? $_process : $process;
                }
            }
            $process = reset($processes);
            $_process = apply_filters("web2ink_print_process", $process);
            return !empty($_process) ? $_process : $process;
        }

        public static function getProcesses()
        {
            global $wpdb;
            $cache = get_transient("w2i_processes");
            if (!empty($cache)) {
                return $cache;
            }
            $processes = array();
            $d = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "web2ink_settings_print_process ORDER BY position, id ASC", ARRAY_A);
            if ($d) {
                foreach ($d as $h) {
                    foreach ($h as $name => $value) {
                        if (is_string($value)) {
                            $h[$name] = stripslashes($value);
                        }
                    }
                    $h["colors"] = $wpdb->get_results("SELECT id,colorname,hexcode,image,charge FROM " . $wpdb->prefix . "web2ink_settings_print_process_colors WHERE process_id= " . $h['id'] . ' ORDER BY id ASC', ARRAY_A);
                    $h["prices"] = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "web2ink_settings_print_process_price WHERE process_id='" . $h['id'] . "' ORDER BY qty", ARRAY_A);
                    if (!$h["colors"]) {
                        $h["colors"] = array();
                    }
                    if (!$h["prices"]) {
                        $h["prices"] = array();
                    }
                    if (!empty($h["prices"])) {
                        $prices = array();
                        foreach ($h["prices"] as $kus) {
                            foreach ($kus as $name => $val) {
                                if ($name == "qty") {
                                    $val = max(0, intval($val));
                                } elseif ($name == "Per_color") {
                                    if (empty($val) || trim($val) == "") {
                                        $val = "[]";
                                    }
                                    $val = json_decode($val, true);
                                    $percolor = array();
                                    foreach ($val as $line) {
                                        if (!isset($line["addprice"])) {
                                            $line["addprice"] = 0;
                                        }
                                        $percolor[intval($line["colors"])] = array("first" => (float) $line["price"], "additional" => (float) $line["addprice"]);
                                    }
                                    $val = $percolor;
                                } else {
                                    $val = max(0, floatval($val));
                                }
                                $kus[$name] = $val;
                            }
                            $prices[] = $kus;
                        }
                        $h["prices"] = $prices;
                    }
                    $h["minqty"] = !empty($h["prices"]) ? ($h["prices"][0]["qty"]) : 1;
                    $processes[$h['id']] = $h;
                }
            }
            set_transient("w2i_processes", $processes);
            return $processes;
        }

        public static function getStoreProcess($id = 0)
        {
            $details = wp_cache_get("w2i-store-process", "w2i_pricing");
            if ($details !== false) {
                return $details;
            }
            $w2i = web2ink_options();
            $stores = !empty($w2i["web2inkcustomstores"]) ? $w2i["web2inkcustomstores"] : array();
            if (empty($stores["process"])) {
                return $id;
            }
            $processes = self::getProcesses();
            if (!isset($processes[$stores["process"]])) {
                return $id;
            }
            wp_cache_add("w2i-store-process", $stores["process"], "w2i_pricing");
            return $stores["process"];
        }

        public static function getStoreMarkup($storeid = 0)
        {
            $details = wp_cache_get("w2i-store-markup-" . $storeid, "w2i_pricing");
            if ($details !== false) {
                return $details;
            }
            $markup = 0;
            $w2i = web2ink_options();
            $stores = !empty($w2i["web2inkcustomstores"]) ? $w2i["web2inkcustomstores"] : array();
            if (!empty($stores["markup"])) {
                $markup = (float) $stores["markup"];
            }
            if (!empty($storeid)) {
                $meta = get_user_meta($storeid, "web2ink_markup", true);
                if (trim($meta) != "") {
                    $markup = (float) $meta;
                }
            }
            if ($markup < 0) {
                $markup = max(-100, $markup);
            }
            wp_cache_add("w2i-store-markup-" . $storeid, $markup, "w2i_pricing");
            return $markup;
        }

        // ================ supporting methods ========== 

        public static function fixArgs($args)
        {
            if (empty($args) && $_POST) {
                $args = self::fixNumbers($_POST);
            }
            if (empty($args)) {
                return $args;
            }
            $cache = "w2i-args-" . md5(serialize($args));
            $details = wp_cache_get($cache, "w2i_pricing");
            if ($details !== false) {
                return $details;
            }
            // detect product    
            if (empty($args["product"])) {
                if (empty($args["product"]) && !empty($args["productId"])) {
                    $args["product"] = (int) $args["productId"];
                    ;
                }
                if (empty($args["product"]) && !empty($args["productID"])) {
                    $args["product"] = (int) $args["productID"];
                }
                if (empty($args["product"]) && !empty($args["product_id"])) {
                    $args["product"] = (int) $args["product_id"];
                }
            }
            // another important variables
            if (empty($args["color"]) && !empty($args["color_id"])) {
                $args["color"] = (int) $args["color_id"];
                unset($args["color_id"]);
            }
            if (empty($args["design"]) && !empty($args["design_id"])) {
                $args["design"] = (int) $args["design_id"];
                unset($args["design_id"]);
            }
            if (empty($args["process"]) && !empty($args["process_id"])) {
                $args["process"] = (int) $args["process_id"];
                unset($args["process_id"]);
            }
            if (empty($args["qty"])) {
                $args["qty"] = 0;
            }
            // detect design - non zero    
            $did = !empty($args["design"]) ? (int) $args["design"] : 0;
            if (!empty($did)) {
                $design = self::getDesign($did);
                if (!empty($design)) {
                    if (!empty($design["saved_design_is_store_item"]) && !empty($args["color"]) && $design["color"] != $args["color"]) {
                        // multiple design colors allowed for store item
                        $design["color"] = $args["color"];
                        $pidcid = implode("-", array(get_post_meta($design["product"], "_web2ink_productID", true), get_term_meta($design["color"], "web2ink_color_id", true), $design["w2i_id"]));
                        $design["image"] = "https://www.web2ink.com/web/stock/design/" . $pidcid . ".jpg";
                        $design["thumbnail"] = $design["image"] . "?size=thumb";
                        if (!empty($design["side_images"])) {
                            foreach ($design["side_images"] as $i => $image) {
                                if (!empty($image["tag"])) {
                                    $design["side_images"][$i]["image"] = $design["image"] . "?side=" . $image["tag"];
                                }
                            }
                        }
                    }
                    if (!empty($design["addons"])) {
                        unset($design["addons"]);
                    }
                    if (!empty($args["collect"])) {
                        $args = array_merge($design, $args);
                    } else {
                        $args = array_merge($args, $design);
                    }
                }
            }
            // find dimension
            $dim = empty($args["charges"]["dimension"]) ? null : $args["charges"]["dimension"];
            if (empty($args["width"]) && !empty($args["charges"]) && is_array($dim)) {
                $args["width"] = !empty($dim["width"]) ? $dim["width"] : 0;
                $args["height"] = !empty($dim["height"]) ? $dim["height"] : 0;
                $args["unit"] = !empty($dim["unit"]) ? $dim["unit"] : "inch";
            }
            // check multiple sizes
            if (!empty($args["sizes"]) && is_array($args["sizes"])) {
                $sizes = array();
                foreach ($args["sizes"] as $sizeid => $sizeqty) {
                    if (is_array($sizeqty)) {
                        $sizes[$sizeqty["id"]] = $sizeqty["qty"];
                    } else {
                        $sizes[$sizeid] = $sizeqty;
                    }
                }
                $sizes = array_filter($sizes);
                $args["qty"] = max(0, array_sum($sizes));
                $args["sizes"] = $sizes;
                if (count($sizes) == 1) {
                    $args["size"] = key($sizes);
                    unset($args["sizes"]);
                }
            }
            $ret = self::fixNumbers($args);
            wp_cache_add($cache, $ret, "w2i_pricing");
            return $ret;
        }

        public static function getMetas($pid)
        {
            $cache = "w2i-product-" . $pid;
            $details = wp_cache_get($cache, "w2i_pricing");
            if ($details !== false) {
                $filtered = apply_filters("web2ink_product", $details);
                return !empty($filtered) ? $filtered : $details;
            }
            $metas = array(
                "id" => $pid,
                "sizetype" => "fixed",
                "minqty" => 1,
                "title" => "Product #" . $pid,
                "price_method" => "stack",
                "price_group" => "each",
            );
            $product = get_post($pid);
            if (!$product) {
                return $metas;
            }
            $metas["title"] = $product->post_title;
            foreach (get_post_meta($pid) as $k => $v) {
                if (substr($k, 0, 8) == "_web2ink") {
                    $val = is_array($v) ? array_shift($v) : $v;
                    $metas[substr($k, 9)] = maybe_unserialize($val);
                }
            }
            foreach (array("square_unit_price") as $name) {
                if (isset($metas[$name])) {
                    $metas[$name] = (float) $metas[$name];
                } else {
                    $metas[$name] = 0;
                }
            }
            foreach (array("product_template_allow", "productID", "print_process_id") as $name) {
                if (isset($metas[$name])) {
                    $metas[$name] = intval($metas[$name]);
                } else {
                    $metas[$name] = 0;
                }
            }
            if (!empty($metas["price_chart"]) && empty($details["price_chart_filtered"])) {
                $metas["price_chart"] = apply_filters("web2ink_pricing_get_price_chart", $metas["price_chart"], $pid);
                $metas["price_chart_filtered"] = true;
            }
            if (!empty($metas["price_chart"]) && is_array($metas["price_chart"])) {
                $metas["minqty"] = max(1, min(array_keys($metas["price_chart"])));
            }
            wp_cache_add($cache, $metas, "w2i_pricing");
            $filtered = apply_filters("web2ink_product", $metas);
            return !empty($filtered) ? $filtered : $metas;
        }

        public static function design_w2i_to_post_ID($w2i_id)
        {
            if (empty($w2i_id)) {
                return null;
            }
            $args = array(
                "meta_query" => array(array("key" => "_web2ink_saved_design_dt_design_id", "value" => $w2i_id, "compare" => "=")),
                "post_type" => "web2ink_design",
                "posts_per_page" => 1,
                "post_status" => array("publish", "pending", "draft", "auto-draft", "future", "private", "inherit", "trash")
            );
            $result = get_posts($args);
            if (!isset($result[0]->ID)) {
                return null;
            }
            $design = $result[0];
            return $result->ID;
        }

        public static function design_post_to_w2i_ID($post_id)
        {
            if (empty($post_id)) {
                return null;
            }
            $post = get_post($post_id);
            if (empty($post) || $post->post_type != "web2ink_design") {
                return null;
            }
            return (int) get_post_meta($post_id, "_web2ink_saved_design_dt_design_id", true);
        }

        public static function getDesign($did)
        {
            if (empty($did)) {
                return null;
            }
            $cache = "w2i-desing-" . $did;
            $details = wp_cache_get($cache, "w2i_pricing");
            if ($details !== false) {
                $filtered = apply_filters("web2ink_design", $details);
                return (is_array($filtered) && !empty($filtered)) ? $filtered : $details;
            }
            if (is_string($did) && substr($did, 0, 4) == "w2i_") {
                $w2idid = preg_replace("/[^0-9]/", "", str_replace("w2i_", "", $did));
                if (empty($w2idid)) {
                    return null;
                }
                $args = array(
                    "meta_query" => array(array("key" => "_web2ink_saved_design_dt_design_id", "value" => $w2idid, "compare" => "=")),
                    "post_type" => "web2ink_design",
                    "posts_per_page" => -1,
                    "post_status" => array("publish", "pending", "draft", "auto-draft", "future", "private", "inherit", "trash")
                );
                $result = get_posts($args);
                if (!isset($result[0]->ID)) {
                    return null;
                }
                $design = $result[0];
                $did = $result->ID;
            }
            $design = get_post($did);
            if (!$design || $design->post_type != "web2ink_design") {
                return array();
            }
            $metas = array("id" => $did, "description" => $design->post_excerpt);
            foreach (get_post_meta($did) as $k => $v) {
                if (substr($k, 0, 8) == "_web2ink") {
                    $val = is_array($v) ? array_shift($v) : $v;
                    $metas[substr($k, 9)] = maybe_unserialize($val);
                }
            }
            if (!empty($metas["saved_design_product_id"])) {
                $metas["product"] = intval($metas["saved_design_product_id"]);
            }
            if (!empty($metas["saved_design_dt_design_id"])) {
                $metas["w2i_id"] = intval($metas["saved_design_dt_design_id"]);
            }
            if (!empty($metas["saved_design"]) && is_array($metas["saved_design"])) {
                $metas = array_merge($metas, $metas["saved_design"]);
            }
            if (!empty($metas["product_id"])) {
                $metas["product"] = intval($metas["product_id"]);
                unset($metas["product_id"]);
            }
            if (!empty($metas["color_id"])) {
                $metas["color"] = intval($metas["color_id"]);
                unset($metas["color_id"]);
            }
            if (!empty($metas["design_id"])) {
                $metas["w2i_id"] = intval($metas["design_id"]);
            }
            foreach (array("security", "action", "frmaction", "saved_design", "saved_design_product_id", "saved_design_dt_design_id", "design_id") as $name) {
                if (isset($metas[$name])) {
                    unset($metas[$name]);
                }
            }
            if (!empty($metas["charges"])) {
                $charges = $metas["charges"];
                if (is_array($charges["dimension"])) {
                    $metas["width"] = (float) $charges["dimension"]["width"];
                    $metas["height"] = (float) $charges["dimension"]["height"];
                    $metas["unit"] = !empty($charges["dimension"]["unit"]) ? $charges["dimension"]["unit"] : "inch";
                    if (!in_array($metas["unit"], array("inch", "foot", "cm", "m"))) {
                        $metas["unit"] = "inch";
                    }
                }
                $metas["charges"]["reorder"] = !empty($metas["orders"]);
            }
            if (!empty($metas["size"]) && is_array($metas["size"])) {
                if (isset($metas["size"]["width"])) {
                    $metas["width"] = (float) $metas["size"]["width"];
                    $metas["height"] = (float) $metas["size"]["height"];
                    $metas["unit"] = !empty($metas["size"]["unit"]) ? $metas["size"]["unit"] : "inch";
                }
            }
            $metas["title"] = $design->post_title;
            $metas["author"] = $design->post_author;
            $metas["design"] = $design->ID;
            $metas["storeitem"] = !empty($metas["saved_design_is_store_item"]);
            if (!empty($metas["storeitem"]) && isset($metas["charges"]["process"])) {
                $metas["charges"]["process"] = self::getStoreProcess($metas["charges"]["process"]);
            }
            if (!empty($metas["storeitem"]) && !empty($metas["custom_image"])) {
                $metas["thumbnail"] = $metas["image"] = $metas["custom_image"];
                if (!empty($metas["side_images"])) {
                    foreach ($metas["side_images"] as $i => $mi) {
                        $metas["side_images"][$i]["image"] = $metas["custom_image"];
                        break;
                    }
                }
            }
            ksort($metas, SORT_STRING);
            $ret = self::fixNumbers($metas);
            wp_cache_add($cache, $ret, "w2i_pricing");
            $filtered = apply_filters("web2ink_design", $ret);
            return (is_array($filtered) && !empty($filtered)) ? $filtered : $ret;
        }

        public static function sizesFromW2i($pid, $fromSizes)
        {
            if (empty($pid)) {
                return null;
            }
            $pole = wp_get_post_terms($pid, "web2ink_product_size");
            if (!$pole || empty($fromSizes)) {
                return array();
            }
            $ids = array();
            foreach ($pole as $kus) {
                $size = get_term_meta($kus->term_id, "web2ink_size_details", true);
                $ids[$size["id"]] = $kus->term_id;
            }
            $sizes = array();
            foreach ($fromSizes as $kus) {
                $kus = array_filter(is_array($kus) ? array_filter($kus, "is_numeric") : array());
                if (empty($kus) || intval($kus["qty"]) < 1) {
                    continue;
                }
                if (isset($ids[$kus["id"]])) {
                    $kus["id"] = $ids[$kus["id"]];
                }
                $sizes[] = $kus;
            }
            return self::fixNumbers($sizes);
        }

        public static function w2iSize($pid, $sizeid)
        {
            if (empty($pid)) {
                return null;
            }
            $pole = wp_get_post_terms($pid, "web2ink_product_size");
            if (!$pole || empty($fromSizes)) {
                return array();
            }
            $ids = array();
            foreach ($pole as $kus) {
                $size = get_term_meta($kus->term_id, "web2ink_size_details", true);
                if ($size["id"] == $sizeid) {
                    return intval($kus->term_id);
                }
            }
            return null;
        }

        // ========= fix new addons on product metas =======
        public static function fixAddons($pid, $inarray, $qty = 1, $product_price = 0)
        {
            if (empty($pid) || empty($inarray) || !is_array($inarray)) {
                return array();
            }
            $product = wc_get_product($pid);
            $ret = array();
            $pattributes = $product->get_attributes();
            $addons = (array) get_post_meta($pid, "_web2ink_addons", true);
            foreach ($inarray as $name => $value) {
                if (substr($name, 0, 10) == "attribute_") {
                    $name = substr($name, 10);
                }
                // product meta addons
                if (substr($name, 0, 4) == "_w2i") {
                    $addonid = preg_replace("/[^0-9]/", "", str_replace("_w2i", "", $name));
                    $optionid = preg_replace("/[^0-9]/", "", str_replace("_w2i", "", $value));
                    if (!empty($addonid)) {
                        foreach ($addons as $addon) {
                            if ($addon["id"] == $addonid) {
                                foreach ($addon["options"] as $option) {
                                    if ($option["id"] == $optionid) {
                                        $percent = $price = self::findPriceQtyTable($option["price"], $qty);
                                        $o_type = !empty($option["type"]) ? $option["type"] : "each";
                                        if ($o_type == "all") {
                                            $price = round($price / $qty, 4);
                                        }
                                        if ($o_type == "percent") {
                                            $price = round(($price / 100) * $product_price, 4);
                                        }
                                        if ($o_type == "percent2" || $o_type == "percent3") {
                                            $price = 0;
                                        }
                                        if (strpos($o_type, "percent") === false) {
                                            $percent = 0;
                                        }
                                        $ret[] = array(
                                            "addon" => $addon["name"],
                                            "option" => $option["name"],
                                            "title" => $addon["name"] . ": " . $option["name"],
                                            "price" => $price,
                                            "type" => "addon",
                                            "id" => $option["id"],
                                            "w2i_type" => $o_type,
                                            "percent" => $percent,
                                        );
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    }
                    continue;
                }
                // public term
                if (substr($name, 0, 3) == "pa_" && substr($value, 0, 6) == "_term_") {
                    $tid = (int) preg_replace("/[^0-9]/", "", $value);
                    if (!empty($tid)) {
                        $terms = get_terms($name);
                        foreach ($terms as $term) {
                            if ($term->term_id == $tid) {
                                $value = $term->slug;
                                $tax = get_taxonomy($term->taxonomy);
                                $ret[] = array("addon" => $tax->label, "option" => $term->name, "title" => $tax->label . ": " . $term->name, "price" => 0, "type" => "term", "id" => $tid, "taxonomy" => $name, "slug" => $value);
                                break;
                            }
                        }
                    }
                    continue;
                }
                // product atrtibute
                $tax = wc_attribute_label($name, $product);
                if (!$tax) {
                    $tax = $name;
                }
                $slug = sanitize_title_with_dashes(is_array($value) ? implode("_", $value) : $value);
                if ($slug == $value && isset($pattributes[$name])) {
                    $kus = $pattributes[$name];
                    foreach ($kus->get_slugs() as $kusslug) {
                        if (sanitize_title_with_dashes($kusslug) == $slug) {
                            $value = $kusslug;
                        }
                    }
                }
                $ret[] = array("addon" => $tax, "option" => $value, "title" => $tax . ": " . $value, "price" => 0, "type" => "meta", "id" => $slug, "taxonomy" => $name, "slug" => $slug);
            }
            return $ret;
        }

        public static function findPrice($chart, $forqty, $method = "stack")
        {
            // chart: qty=>price values
            if (empty($chart) || !is_array($chart)) {
                return 0;
            }
            if (!in_array($method, array("stack", "smooth"))) {
                $method = "stack";
            }
            $forqty = max(1, $forqty);
            $ret = 0;
            if (isset($chart[$forqty])) {
                return max(0, (float) $chart[$forqty]);
            } // exact quantity found on chart
            $keys = array_keys($chart);
            if (max($keys) < $forqty) {
                return max(0, $chart[max($keys)]);
            } // requested quantity higher as highest quantity on chart
            if (min($keys) > $forqty) {
                return max(0, $chart[min($keys)]);
            } // requested quantity lower as lowest quantity on chart
            if ($method == "stack") {
                foreach ($chart as $qty => $price) {
                    if (intval($qty) == 0 || (float) $price == 0) {
                        continue;
                    }
                    if ($ret == 0 || $forqty >= $qty) {
                        $ret = $price;
                    }
                }
            } else {
                $dole = $hore = $x = $dq = $hq = $minqty = 0;
                foreach ($chart as $qty => $price) {
                    if ($minqty == 0) {
                        $minqty = $qty;
                    }
                    if ($forqty <= $minqty) {
                        return max(0, $price);
                    } // requested quantity is under minimum quantity
                    if ($qty <= $forqty) {
                        $dole = $price;
                        $dq = $qty;
                    }
                    if ($qty > $forqty) {
                        $hore = $price;
                        $hq = $qty;
                        break;
                    }
                }
                if ($hq == 0 || $hore == 0) {
                    $hq = $dq;
                    $hore = $dole;
                }
                $kolko = $hq - $dq;
                if ($kolko > 0) {
                    $x = ($dole - $hore) / $kolko;
                    $x = $x * ($forqty - $dq);
                }
                $ret = round($dole - $x, 4);
            }
            return max(0, $ret);
        }

        public static function findPriceQtyTable($prices, $qty = 1)
        {
            if (empty($prices) || !is_array($prices)) {
                return 0;
            }
            $qty = max(1, intval($qty));
            if (isset($prices[$qty])) {
                return $prices[$qty];
            }
            $ret = 0;
            ksort($prices, SORT_NUMERIC);
            foreach ($prices as $inqty => $price) {
                if ($ret == 0) {
                    $ret = $price;
                }
                if ($inqty <= $qty) {
                    $ret = $price;
                } else {
                    break;
                }
            }
            return (float) $ret;
        }

        public static function fixNumbers($pole)
        {
            foreach ($pole as $name => $value) {
                if (strval($name) == "hex" && is_numeric($value)) {
                    $pole[$name] = str_pad($value, 6, "0", STR_PAD_LEFT);
                }
                if (is_string($value)) {
                    if (is_numeric($value)) {
                        if (preg_match('/^[a-f0-9]{6}$/i', $value)) {
                            continue;
                        }
                        if (strlen($value) > 2) {
                            if (stripos($name, "color") !== false || $name == "hex") {
                                continue;
                            }
                        }
                        $pole[$name] = (float) $value;
                    } else if ($value == "true") {
                        $pole[$name] = true;
                    } else if ($value == "false") {
                        $pole[$name] = false;
                    }
                }
                if (is_array($value)) {
                    $pole[$name] = self::fixnumbers($value);
                }
            }
            return $pole;
        }

        public static function normalizeSqUnits($args, $metas)
        {
            $width = (isset($args["width"]) ? (float) $args["width"] : 0);
            $height = (isset($args["height"]) ? (float) $args["height"] : 0);
            if (($width * $height) == 0) {
                return 0;
            } // size or price empty  
            $dunit = !isset($args["unit"]) ? "inch" : $args["unit"];
            if (!in_array($dunit, array("inch", "foot", "cm", "m"))) {
                $dunit = "inch";
            }
            $punit = isset($metas["square_unit_price_unit"]) ? $metas["square_unit_price_unit"] : "inch";
            if (!in_array($punit, array("inch", "foot", "cm", "m"))) {
                $punit = "inch";
            }
            // convert design dimension to product price units
            if ($dunit != $punit) {
                $ratio = 1;
                switch ($dunit) {
                    case "inch":
                        if ($punit == "foot") {
                            $ratio = 1 / 12;
                        }
                        if ($punit == "cm") {
                            $ratio = 2.54;
                        }
                        if ($punit == "m") {
                            $ratio = 0.0254;
                        }
                        break;
                    case "foot":
                        if ($punit == "inch") {
                            $ratio = 12;
                        }
                        if ($punit == "cm") {
                            $ratio = 30.48;
                        }
                        if ($punit == "m") {
                            $ratio = 0.3048;
                        }
                        break;
                    case "cm":
                        if ($punit == "inch") {
                            $ratio = 1 / 2.54;
                        }
                        if ($punit == "foot") {
                            $ratio = 1 / 30.48;
                        }
                        if ($punit == "m") {
                            $ratio = 1 / 100;
                        }
                        break;
                    case "m":
                        if ($punit == "inch") {
                            $ratio = 1 / 0.0254;
                        }
                        if ($punit == "foot") {
                            $ratio = 1 / 0.3048;
                        }
                        if ($punit == "cm") {
                            $ratio = 100;
                        }
                        break;
                }
                $width *= $ratio;
                $height *= $ratio;
            }
            return $width * $height;
        }

        // ============ user discount ===================
        public static function getUserDiscount($user_id = 0)
        {
            $discount = array(
                "group" => 0,
                "product" => 0,
                "design" => 0,
                "process" => 0,
                "shipping" => false,
                "tax" => false,
                "minqty" => false,
            );
            if ($user_id === false) {
                return $discount;
            }
            if ($user_id == 0 && is_user_logged_in()) {
                $user_id = get_current_user_id();
            }
            if (empty($user_id)) {
                return $discount;
            }
            $metas = (array) get_user_meta($user_id, "web2ink_user_discount", true);
            if (empty($metas["group"])) {
                $web2ink = web2ink_options();
                $def_group = !empty($web2ink["default_user_group"]) ? (int) $web2ink["default_user_group"] : 0;
                if (!empty($def_group)) {
                    $metas["group"] = $def_group;
                }
            }
            if (empty($metas)) {
                return $discount;
            }
            if (!empty($metas["group"])) {
                $groups = (array) get_option("web2ink_user_discount_groups", array());
                if (!empty($groups)) {
                    foreach ($groups as $group) {
                        if ($group["id"] == $metas["group"]) {
                            foreach ($group as $name => $value) {
                                if (isset($discount[$name])) {
                                    $discount[$name] = $value;
                                }
                            }
                            break;
                        }
                    }
                }
            }
            foreach ($metas as $name => $value) {
                if (isset($discount[$name]) && !empty($value)) {
                    $discount[$name] = $value;
                }
            }
            return $discount;
        }

        // get order due date
        public static function get_due_date($order)
        {
            if (gettype($order) == "string" || gettype($order) == "integer") {
                $order = wc_get_order(intval($order));
            }
            $due_date = get_post_meta($order->get_id(), "_web2ink_order_production_end_datetime", true);
            if (!empty($due_date)) {
                return strtotime($due_date);
            }
            $defs = web2ink_helper::getDefaultOrderProductionSetup();
            $businessStartTime = $defs["businessStartTime"];
            $businessEndTime = $defs["businessEndTime"];
            if (!empty($order->get_date_created())) {
                $CurrentDateTime = $order->get_date_created()->format("Y-m-d H:i:s");
                $CurrentDate = $order->get_date_created()->format("Y-m-d");
                $CurrentTime = $order->get_date_created()->format("H:i");
            } else {
                $CurrentDateTime = date("Y-m-d H:i:s");
                $CurrentDate = date("Y-m-d");
                $CurrentTime = date("H:i");
            }
            $order_production_data = (array) get_post_meta($order->get_id(), "_web2ink_order_production_data", true);
            $productiondays = $defs["defaultProductionDays"];
            if (!empty($order_production_data["days"])) {
                $productiondays = $order_production_data["days"];
            }
            $productionStartDate = $CurrentDate;
            if (preg_match("/^(?:2[0-4]|[01][1-9]|10):([0-5][0-9])$/", $businessStartTime) && preg_match("/^(?:2[0-4]|[01][1-9]|10):([0-5][0-9])$/", $businessEndTime)) {
                $productionStartDate = $productionStartDate . " " . $businessStartTime;
                if ($CurrentTime > $businessStartTime && $CurrentTime < $businessEndTime) {
                    $productionStartDate = $CurrentDate . " " . $CurrentTime;
                } elseif ($CurrentTime > $businessEndTime) {
                    $productionStartDate = date("Y-m-d", strtotime($CurrentDate . " +1 day")) . " " . $businessStartTime;
                }
            }
            $productionStartDate = web2ink_helper::checkDateSunSatHolidays($productionStartDate, $defs["excludeSaturday"], $defs["excludeSunday"]);
            $productiondays = max(1, $productiondays - 1);
            $productionEndDate = web2ink_helper::calculateProductionEndDate($productionStartDate, $productiondays, $defs["excludeSaturday"], $defs["excludeSunday"]);
            return strtotime($productionEndDate);
        }

        // check if specific product bought by customer
        public static function hasReorderProduct($product_id, $user_id = 0)
        {
            global $wpdb;
            if (empty($user_id)) {
                $user_id = get_current_user_id();
            }
            $cache = "w2i-reopid-" . $product_id . "-" . $user_id;
            $details = wp_cache_get($cache, "w2i_pricing");
            if ($details !== false) {
                return $details;
            }
            $ret = false;
            $args = array(
                "numberposts" => -1,
                "meta_key" => "_customer_user",
                "meta_value" => $user_id,
                "post_type" => wc_get_order_types(),
                "post_status" => self::getGoodStatuses(),
                "fields" => "ids",
            );
            $orders = get_posts($args);
            if (!empty($orders)) {
                $sql = "SELECT COUNT(*) as num_row FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_item_id = oim.order_item_id
            WHERE oim.meta_key='_product_id' AND oim.meta_value='" . $product_id . "' AND oi.order_item_type='line_item' AND oi.order_id IN (" . implode(",", $orders) . ")";
                $count = $wpdb->get_var($sql);
                $ret = !empty($count);
            }
            wp_cache_add($cache, $ret, "w2i_pricing");
            return $ret;
        }

        public static function getGoodStatuses()
        {
            $exclude = array("wc-failed", "wc-refunded", "wc-cancelled", "wc-on-hold");
            $pole = array_keys(wc_get_order_statuses());
            return array_diff($pole, $exclude);
        }

        // get order due date
        public static function estimate_delivery($days, $timestamp = 0)
        {
            $days = max(1, intval($days));
            if (empty($timestamp)) {
                $timestamp = time();
            }
            $cache = "w2i-delivery-" . $days . "-" . $timestamp;
            $date_cached = wp_cache_get($cache, "w2i_pricing");
            if ($date_cached !== false) {
                return $date_cached;
            }
            $defs = web2ink_helper::getDefaultOrderProductionSetup();
            $time_start = $defs["businessStartTime"];
            $time_end = $defs["businessEndTime"];
            $CurrentDateTime = date("Y-m-d H:i:s", $timestamp);
            $CurrentDate = date("Y-m-d", $timestamp);
            $CurrentTime = date("H:i", $timestamp);
            $productionStartDate = $CurrentDate;
            if (preg_match("/^(?:2[0-4]|[01][1-9]|10):([0-5][0-9])$/", $time_start) && preg_match("/^(?:2[0-4]|[01][1-9]|10):([0-5][0-9])$/", $time_end)) {
                $productionStartDate = $productionStartDate . " " . $time_start;
                if ($CurrentTime > $time_start && $CurrentTime < $time_end) {
                    $productionStartDate = $CurrentDate . " " . $CurrentTime;
                } elseif ($CurrentTime > $time_end) {
                    $productionStartDate = date("Y-m-d", strtotime($CurrentDate . " +1 day")) . " " . $time_start;
                }
            }
            $productionStartDate = web2ink_helper::checkDateSunSatHolidays($productionStartDate, $defs["excludeSaturday"], $defs["excludeSunday"]);
            $days = max(1, $days - 1);
            $productionEndDate = web2ink_helper::calculateProductionEndDate($productionStartDate, $days, $defs["excludeSaturday"], $defs["excludeSunday"]);
            $date_cached = strtotime($productionEndDate);
            wp_cache_add($cache, $date_cached, "w2i_pricing");
            return $date_cached;
        }
    }
}

// Alias your class as the original plugin class
if (!class_exists('web2ink_pricing')) {
    class_alias('dtf_web2ink_pricing', 'web2ink_pricing');
}
