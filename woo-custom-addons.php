<?php
/*
 * Plugin Name: WooCommerce Custom Addons (Repeater)
 * Description: Adds a "Doplňky" tab with repeater fields (CZ/EN/CZK/EUR) compatible with CURCY.
 * Version: 1.0
 * Author: Marián Rehák
 * Dependencies: WooCommerce, CURCY
 * Text Domain: woo-custom-addons
 * Domain Path: /languages
*/

if (! defined('ABSPATH')) exit;

/* 
 * Load textdomain
 */
function woo_custom_addons_load_textdomain()
{
    load_plugin_textdomain(
        'woo-custom-addons',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'woo_custom_addons_load_textdomain');

/*
 * Backend
 */
// 1. Add the Tab
add_filter('woocommerce_product_data_tabs', 'wca_add_custom_tab');
function wca_add_custom_tab($tabs)
{
    $tabs['wca_addons'] = array(
        'label'    => __('Doplňky', 'woo-custom-addons'),
        'target'   => 'wca_addons_options',
        'priority' => 50,
    );
    return $tabs;
}

// 2. Add Tab Content (Repeater UI)
add_action('woocommerce_product_data_panels', 'wca_render_custom_tab');
function wca_render_custom_tab()
{
    global $post;
    // Get existing data
    $addons = get_post_meta($post->ID, '_wca_addons', true);
?>
    <div id="wca_addons_options" class="panel woocommerce_options_panel">
        <div class="options_group">
            <p><strong><?php _e('Doplňky', 'woo-custom-addons'); ?></strong></p>
            <div id="wca-repeater-container">
                <?php
                if (! empty($addons) && is_array($addons)) {
                    foreach ($addons as $index => $addon) {
                        wca_render_repeater_row($index, $addon);
                    }
                } else {
                    // Render one empty row by default
                    wca_render_repeater_row(0, array());
                }
                ?>
            </div>
            <button type="button" class="button" id="wca-add-row"><?php _e('Přidat doplňek', 'woo-custom-addons'); ?></button>
        </div>

        <script type="text/template" id="wca-row-template">
            <?php wca_render_repeater_row('{{index}}', array()); ?>
        </script>

        <style>
            .wca-repeater-row {
                border-bottom: 1px solid #eee;
                padding: 10px;
                display: flex;
                gap: 10px;
                align-items: flex-end;
                flex-wrap: wrap;
            }

            .wca-field {
                display: flex;
                flex-direction: column;
                max-width: 20%;
            }

            .wca-remove-row {
                color: #a00;
                cursor: pointer;
                margin-bottom: 5px;
            }

            #wca_addons_options label {
                float: unset;
                margin: 0;
                width: 100%;
                margin-bottom: 8px;
            }

            #wca_addons_options input {
                width: 100%;
            }

            #wca-add-row {
                margin: 10px;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                var container = $('#wca-repeater-container');
                var template = $('#wca-row-template').html();

                // Add Row
                $('#wca-add-row').on('click', function() {
                    var newIndex = container.children('.wca-repeater-row').length;
                    var newRow = template.replace(/{{index}}/g, newIndex);
                    container.append(newRow);
                });

                // Remove Row
                container.on('click', '.wca-remove-row', function() {
                    $(this).closest('.wca-repeater-row').remove();
                });
            });
        </script>
    </div>
<?php
}

// Helper to render a single row
function wca_render_repeater_row($index, $data)
{
    $txt_cz = isset($data['text_cz']) ? $data['text_cz'] : '';
    $txt_en = isset($data['text_en']) ? $data['text_en'] : '';
    $info_cz = isset($data['info_cz']) ? $data['info_cz'] : '';
    $info_en = isset($data['info_en']) ? $data['info_en'] : '';
    $price_czk = isset($data['price_czk']) ? $data['price_czk'] : '';
    $price_eur = isset($data['price_eur']) ? $data['price_eur'] : '';
?>
    <div class="wca-repeater-row">
        <div class="wca-field"><label>Text (CZ)</label><input type="text" name="wca_addons[<?php echo $index; ?>][text_cz]" value="<?php echo esc_attr($txt_cz); ?>" /></div>
        <div class="wca-field"><label>Text (EN)</label><input type="text" name="wca_addons[<?php echo $index; ?>][text_en]" value="<?php echo esc_attr($txt_en); ?>" /></div>
        <div class="wca-field">
            <label>Info Text (CZ)</label>
            <input type="text" name="wca_addons[<?php echo $index; ?>][info_cz]" value="<?php echo esc_attr($info_cz); ?>" style="width:100%;" />
        </div>
        <div class="wca-field">
            <label>Info Text (EN)</label>
            <input type="text" name="wca_addons[<?php echo $index; ?>][info_en]" value="<?php echo esc_attr($info_en); ?>" style="width:100%;" />
        </div>
        <div class="wca-field"><label>Price (CZK)</label><input type="number" step="0.01" name="wca_addons[<?php echo $index; ?>][price_czk]" value="<?php echo esc_attr($price_czk); ?>" /></div>
        <div class="wca-field"><label>Price (EUR)</label><input type="number" step="0.01" name="wca_addons[<?php echo $index; ?>][price_eur]" value="<?php echo esc_attr($price_eur); ?>" /></div>
        <span class="wca-remove-row dashicons dashicons-trash"></span>
    </div>
<?php
}

// 3. Save Data
add_action('woocommerce_process_product_meta', 'wca_save_custom_fields');
function wca_save_custom_fields($post_id)
{
    // 1. Check if our data is present
    if (! isset($_POST['wca_addons'])) {
        // If the field is missing from POST (and not just empty), strictly do nothing.
        // This prevents accidental wiping if save is triggered from a context without the form.
        return;
    }

    // 2. Sanitize and Prepare Data
    $clean_addons = array();
    if (is_array($_POST['wca_addons'])) {
        foreach ($_POST['wca_addons'] as $addon) {
            // Only save if at least CZ text or EN text is provided
            if (!empty($addon['text_cz']) || !empty($addon['text_en'])) {
                $clean_addons[] = array_map('sanitize_text_field', $addon);
            }
        }
    }

    // 3. Save to the Current Product
    if (! empty($clean_addons)) {
        update_post_meta($post_id, '_wca_addons', $clean_addons);
    } else {
        delete_post_meta($post_id, '_wca_addons');
    }

    // 4. Polylang Synchronization
    // Check if Polylang function exists
    if (function_exists('pll_get_post_translations')) {

        // Get all translations (returns array like ['en' => 102, 'cs' => 101])
        $translations = pll_get_post_translations($post_id);

        if (! empty($translations)) {
            foreach ($translations as $lang_slug => $translated_post_id) {

                // Skip the current product (we already saved it above)
                if ($translated_post_id == $post_id) {
                    continue;
                }

                // Sync the data
                if (! empty($clean_addons)) {
                    update_post_meta($translated_post_id, '_wca_addons', $clean_addons);
                } else {
                    delete_post_meta($translated_post_id, '_wca_addons');
                }
            }
        }
    }
}

/**
 * Copy _wca_addons meta when creating a new Polylang translation draft.
 *
 * @param int    $post_id The ID of the new translation (draft).
 * @param object $post    The post object of the new translation.
 * @param int    $lang    The language slug (not always used here).
 */
add_action('pll_save_post', 'wca_copy_addons_to_translation', 10, 3);
function wca_copy_addons_to_translation($post_id, $post, $lang)
{
    // 1. Check if this is a translation creation event
    // Polylang passes the source post ID in $_GET['from_post'] when clicking "+"
    if (! isset($_GET['from_post'])) {
        return;
    }

    $source_post_id = absint($_GET['from_post']);

    // 2. Security / Logic Check
    // Ensure we are copying from a valid product and the new post is actually a product
    if (get_post_type($source_post_id) !== 'product' || get_post_type($post_id) !== 'product') {
        return;
    }

    // 3. Get the addons from the source
    $addons = get_post_meta($source_post_id, '_wca_addons', true);

    if (! empty($addons)) {
        // 4. Update the new draft with this data
        update_post_meta($post_id, '_wca_addons', $addons);
    }
}

/*
 * Frontend
 */
// Hook for Variable Products: Places it right after the dropdowns
add_action('woocommerce_before_single_variation', 'wca_display_addons_frontend');

function wca_display_addons_frontend()
{
    global $post, $product;
    $addons = get_post_meta($post->ID, '_wca_addons', true);
    if (empty($addons)) return;

    $currency = get_woocommerce_currency();
    $lang = get_locale();
    $base_price = (float) $product->get_price();

    // GET FORMATTING RULES
    $decimals      = wc_get_price_decimals();
    $decimal_sep   = wc_get_price_decimal_separator();
    $thousand_sep  = wc_get_price_thousand_separator();

    // Pass rules to the container
    echo '<div class="wca-addons-wrapper" id="wca-addons-container" 
          data-base-price="' . esc_attr($base_price) . '"
          data-decimals="' . esc_attr($decimals) . '"
          data-decimal-sep="' . esc_attr($decimal_sep) . '"
          data-thousand-sep="' . esc_attr($thousand_sep) . '">';

    foreach ($addons as $index => $addon) {
        $is_cz = (strpos($lang, 'cs') !== false);

        $label = $is_cz ? $addon['text_cz'] : $addon['text_en'];
        if (empty($label)) $label = $addon['text_cz'];

        $info  = $is_cz ? (isset($addon['info_cz']) ? $addon['info_cz'] : '') : (isset($addon['info_en']) ? $addon['info_en'] : '');

        $price_raw = ($currency == 'EUR') ? $addon['price_eur'] : $addon['price_czk'];
        $formatted_price = wc_price($price_raw);

        echo '<div class="wca-addon-item">';
        echo '<label>';
        echo '<div class="addon-price">';
        echo '<span class="wca-label">' . esc_html($label) . '</span> ';
        echo '<span class="wca-price-display">(+' . $formatted_price . ')</span>';
        echo '</div>';
        echo '<input type="checkbox" class="wca-addon-checkbox" name="wca_selected_addons[]" value="' . $index . '" data-price="' . esc_attr($price_raw) . '"> ';
        // Render Info Text if it exists
        if (! empty($info)) {
            echo '<span class="wca-info-text">' . esc_html($info) . '</span>';
        }
        echo '</label>';
        echo '</div>';
    }
    echo '</div>';
}

// frontend js
add_action('wp_footer', 'wca_add_frontend_script');
function wca_add_frontend_script()
{
    if (! is_product()) return;
?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var container = $('#wca-addons-container');
            if (!container.length) return;

            // 1. Load Configurations
            var basePrice = parseFloat(container.data('base-price'));

            // WooCommerce Formatting Settings
            var wcDecimals = parseInt(container.data('decimals'));
            var wcDecimalSep = container.data('decimal-sep');
            var wcThousandSep = container.data('thousand-sep');

            // 2. Identify Target
            var priceTarget = $('#product-price-container .woocommerce-Price-amount bdi');
            if (!priceTarget.length) {
                priceTarget = $('.product .price .woocommerce-Price-amount bdi').last();
            }

            // 3. Capture Symbol
            var originalHtml = priceTarget.html();
            var tempDiv = $('<div>').html(originalHtml);
            var symbolSpan = tempDiv.find('.woocommerce-Price-currencySymbol');
            var currencySymbol = symbolSpan.text(); // e.g., "Kč"

            // Check if symbol is at start ($100) or end (100 Kč)
            var symbolAtStart = originalHtml.trim().indexOf('<span') === 0;

            // Helper: Mimic PHP number_format
            function formatMoney(n, c, d, t) {
                var c = isNaN(c = Math.abs(c)) ? 2 : c,
                    d = d == undefined ? "." : d,
                    t = t == undefined ? "," : t,
                    s = n < 0 ? "-" : "",
                    i = String(parseInt(n = Math.abs(Number(n) || 0).toFixed(c))),
                    j = (j = i.length) > 3 ? j % 3 : 0;
                return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
            }

            function updatePrice() {
                var totalAddons = 0;
                $('.wca-addon-checkbox:checked').each(function() {
                    totalAddons += parseFloat($(this).data('price'));
                });

                var finalPrice = basePrice + totalAddons;

                // Use the WC settings to format the number string
                var formattedNum = formatMoney(finalPrice, wcDecimals, wcDecimalSep, wcThousandSep);

                // Rebuild HTML
                var finalHtml = '';
                if (symbolAtStart) {
                    finalHtml = '<span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span>' + formattedNum;
                } else {
                    finalHtml = formattedNum + '&nbsp;<span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span>';
                }

                // CRITICAL: Re-select element to avoid stale DOM issues
                var currentTarget = $('#product-price-container .woocommerce-Price-amount bdi');
                if (!currentTarget.length) currentTarget = $('.product .price .woocommerce-Price-amount bdi').last();

                currentTarget.html(finalHtml);
            }

            // 4. Events with 50ms Delay
            $('.wca-addon-checkbox').on('change', function() {
                setTimeout(updatePrice, 50);
            });

            $('form.variations_form').on('found_variation', function(event, variation) {
                basePrice = parseFloat(variation.display_price);
                // Wait 50ms for Woo to finish its own updates
                setTimeout(updatePrice, 250);
            });
        });
    </script>
<?php
}

// frontend styling
add_action('wp_head', 'wca_frontend_styles');
function wca_frontend_styles()
{
    if (! is_product()) return;
?>
    <style>
        /* Container Styling */
        .wca-addon-item label {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .wca-addon-item input {
            margin-right: 0;
        }

        .wca-info-text {
            flex: 0 0 100%;
            color: #cccccc;
            font-size: 15px;
        }
    </style>
<?php
}
/*
 * Cart and checkout calculation
 */
// 1. Add custom data to cart item
add_filter('woocommerce_add_cart_item_data', 'wca_add_item_data', 10, 2);
function wca_add_item_data($cart_item_data, $product_id)
{
    if (isset($_POST['wca_selected_addons'])) {
        $cart_item_data['wca_addons'] = $_POST['wca_selected_addons']; // Store array of indexes
    }
    return $cart_item_data;
}

// 2. Adjust Price dynamically based on currency
add_action('woocommerce_before_calculate_totals', 'wca_calculate_totals', 10, 1);
function wca_calculate_totals($cart)
{
    if (is_admin() && ! defined('DOING_AJAX')) return;

    $currency = get_woocommerce_currency(); // Current active currency (CZK or EUR)

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['wca_addons'])) {
            $product_id = $cart_item['product_id']; // This is the generic parent ID
            $all_addons = get_post_meta($product_id, '_wca_addons', true);

            $extra_cost = 0;

            // 1. Calculate the Surcharge
            foreach ($cart_item['wca_addons'] as $addon_index) {
                if (isset($all_addons[$addon_index])) {
                    $addon = $all_addons[$addon_index];
                    $price = ($currency == 'EUR') ? $addon['price_eur'] : $addon['price_czk'];
                    $extra_cost += floatval($price);
                }
            }

            if ($extra_cost > 0) {
                // 2. CRITICAL FIX: Get a fresh instance of the product to find the original Base Price.
                // We use the ID from the cart item data object (handles Variations + Simple products correctly)
                $real_product_id = $cart_item['data']->get_id();
                $fresh_product = wc_get_product($real_product_id);

                // Get the clean price (CURCY will have already converted this base price if needed)
                // We rely on this fresh lookup to ensure we aren't adding the 2500 on top of an already modified 3700.
                $base_price = (float) $fresh_product->get_price();

                // 3. Set the new final price
                $cart_item['data']->set_price($base_price + $extra_cost);
            }
        }
    }
}

/*
 * Display in cart, checkout & orders
 */
// 1. Display in Cart & Checkout
add_filter('woocommerce_get_item_data', 'wca_display_cart_item_data', 10, 2);
function wca_display_cart_item_data($item_data, $cart_item)
{
    if (isset($cart_item['wca_addons'])) {
        $product_id = $cart_item['product_id'];
        $all_addons = get_post_meta($product_id, '_wca_addons', true);
        $lang = get_locale();

        foreach ($cart_item['wca_addons'] as $addon_index) {
            if (isset($all_addons[$addon_index])) {
                $addon = $all_addons[$addon_index];
                $label = (strpos($lang, 'cs') !== false) ? $addon['text_cz'] : $addon['text_en'];

                $item_data[] = array(
                    'name'  => __('Doplňky', 'woo-custom-addons'),
                    'value' => $label
                );
            }
        }
    }
    return $item_data;
}

// 2. Save to Order Lines (so it shows in Admin & Emails forever)
add_action('woocommerce_checkout_create_order_line_item', 'wca_add_order_line_item_meta', 10, 4);
function wca_add_order_line_item_meta($item, $cart_item_key, $values, $order)
{
    if (isset($values['wca_addons'])) {
        $product_id = $values['product_id'];
        $all_addons = get_post_meta($product_id, '_wca_addons', true);
        $final_labels = array();

        // Note: For orders, we usually save the CZ text or both, or based on order lang
        foreach ($values['wca_addons'] as $addon_index) {
            if (isset($all_addons[$addon_index])) {
                // Saving "Name (Price)" is often helpful for admin reference
                $final_labels[] = $all_addons[$addon_index]['text_cz'];
            }
        }

        if (! empty($final_labels)) {
            $item->add_meta_data(__('Doplňky', 'woo-custom-addons'), implode(', ', $final_labels));
        }
    }
}
