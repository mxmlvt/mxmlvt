<?php
/**
 * Plugin Name: Printumo WooCommerce Integration
 * Description: Integracja sklepu WooCommerce z Printumo API
 * Version: 2.3.2
 * Author: MaxDigital.pl
 */

if (!defined('ABSPATH')) exit;

class Printumo_WooCommerce_Integration {

    private $api_key;
    private $api_url = 'https://printumo.com/api/v1';
    private $debug_mode = true; // Włącz debug

    public function __construct() {
        $this->api_key = get_option('printumo_api_key');

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('woocommerce_order_status_processing', [$this, 'send_order_to_printumo'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'send_order_to_printumo'], 10, 1);
        add_action('wp_ajax_printumo_import_products', [$this, 'ajax_import_products']);
        add_action('wp_ajax_printumo_sync_orders', [$this, 'ajax_sync_orders']);
        add_action('wp_ajax_printumo_test_image', [$this, 'ajax_test_image']);
        add_action('wp_ajax_printumo_debug_product', [$this, 'ajax_debug_product']);
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
        add_shortcode('printumo_configurator', [$this, 'configurator_shortcode']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_canvas_config_to_cart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_canvas_config_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_canvas_config_to_order'], 10, 4);
        add_filter('woocommerce_attribute_label', [$this, 'translate_attribute_labels'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_filter('woocommerce_dropdown_variation_attribute_options_html', [$this, 'custom_variation_display'], 100, 2);

        if (!wp_next_scheduled('printumo_sync_orders_cron')) {
            wp_schedule_event(time(), 'hourly', 'printumo_sync_orders_cron');
        }
        add_action('printumo_sync_orders_cron', [$this, 'sync_order_statuses']);
        add_action('init', [$this, 'ensure_attributes_exist']);

        // NIE ukrywamy domyślnej ceny WooCommerce - użytkownik chce ją widzieć
        // add_filter('woocommerce_variable_price_html', [$this, 'hide_default_price'], 10, 2);
        // add_filter('woocommerce_get_price_html', [$this, 'hide_default_price'], 10, 2);
    }

    public function custom_variation_display($html, $args) {
        if (!in_array($args['attribute'], ['pa_size', 'pa_framing'])) {
            return $html;
        }

        $options = $args['options'];
        $product = $args['product'];
        $attribute = $args['attribute'];
        $name = $args['name'] ? $args['name'] : 'attribute_' . sanitize_title($attribute);
        $id = $args['id'] ? $args['id'] : sanitize_title($attribute);
        $class = $args['class'];

        if (empty($options) && !empty($product) && !empty($attribute)) {
            $attributes = $product->get_variation_attributes();
            $options = $attributes[$attribute];
        }

        $html = '<select id="' . esc_attr($id) . '" class="' . esc_attr($class) . '" name="' . esc_attr($name) . '" data-attribute_name="attribute_' . esc_attr(sanitize_title($attribute)) . '" data-show_option_none="yes" style="display:none !important;">';
        $html .= '<option value="">Choose an option</option>';

        if (!empty($options)) {
            foreach ($options as $option) {
                $html .= '<option value="' . esc_attr($option) . '">' . esc_html(apply_filters('woocommerce_variation_option_name', $option)) . '</option>';
            }
        }

        $html .= '</select>';
        $html .= '<div class="printumo-variation-buttons" data-attribute="' . esc_attr($attribute) . '">';

        if (!empty($options)) {
            foreach ($options as $option) {
                $term = get_term_by('slug', $option, $attribute);
                $label = $term ? $term->name : $option;

                $html .= '<button type="button" class="printumo-variation-btn" data-value="' . esc_attr($option) . '">';
                $html .= esc_html($label);
                $html .= '</button>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    public function load_textdomain() {
        if (!is_admin()) {
            add_filter('locale', function($locale) { return 'en_US'; });
        }
    }

    public function translate_attribute_labels($label, $name, $product) {
        $translations = ['Size' => 'Sizes', 'Framing' => 'Frame'];
        return $translations[$label] ?? $label;
    }

    public function ensure_attributes_exist() {
        if (!taxonomy_exists('pa_size')) {
            wc_create_attribute(['name' => 'Size', 'slug' => 'size', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
            register_taxonomy('pa_size', ['product'], ['hierarchical' => false, 'label' => 'Size', 'show_ui' => true, 'query_var' => true, 'rewrite' => false]);
        }

        if (!taxonomy_exists('pa_framing')) {
            wc_create_attribute(['name' => 'Framing', 'slug' => 'framing', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
            register_taxonomy('pa_framing', ['product'], ['hierarchical' => false, 'label' => 'Framing', 'show_ui' => true, 'query_var' => true, 'rewrite' => false]);
        }
    }

    public function hide_default_price($price, $product) {
        // Sprawdź czy $product jest obiektem
        if (!is_object($product) || !method_exists($product, 'get_id')) {
            $this->debug_log('hide_default_price: Nieprawidłowy obiekt produktu');
            return $price;
        }

        // Ukryj domyślną cenę tylko dla produktów Printumo
        $printumo_id = get_post_meta($product->get_id(), '_printumo_product_id', true);

        if ($printumo_id) {
            $this->debug_log('hide_default_price: Ukrywam cenę dla produktu Printumo ID: ' . $product->get_id());
            return '';
        }

        return $price;
    }

    // Funkcja debugowania
    private function debug_log($message) {
        if ($this->debug_mode) {
            error_log('[Printumo Debug] ' . $message);
        }
    }

    public function configurator_shortcode() {
        ob_start();
        $this->display_canvas_configurator();
        return ob_get_clean();
    }

    public function auto_display_configurator() {
        global $product;

        $this->debug_log('auto_display_configurator: Wywołane');

        if (!$product || !$product->get_id()) {
            $this->debug_log('auto_display_configurator: Brak produktu');
            return;
        }

        $is_canvas = get_post_meta($product->get_id(), '_printumo_is_canvas', true);
        $this->debug_log('auto_display_configurator: Product ID ' . $product->get_id() . ', is_canvas: ' . var_export($is_canvas, true));

        if ($is_canvas) {
            $this->debug_log('auto_display_configurator: Wyświetlam konfigurator dla produktu canvas');
            $this->display_canvas_configurator();
        } else {
            $this->debug_log('auto_display_configurator: To nie jest produkt canvas, pomijam');
        }
    }

    public function enqueue_frontend_scripts() {
        if (is_product()) {
            $this->debug_log('enqueue_frontend_scripts: Ładowanie skryptów dla strony produktu');

            wp_enqueue_script('jquery');

            // Rejestruj i enqueue własny style handle
            wp_register_style('printumo-styles', false);
            wp_enqueue_style('printumo-styles');

            // NOWY MINIMALISTYCZNY DESIGN - FIOLET + TURKUS
            wp_add_inline_style('printumo-styles', '
                /* ========== GLOBALNE ZMIENNE KOLORÓW ========== */
                :root {
                    --printumo-primary: #773fc6 !important;
                    --printumo-primary-hover: #5f2fa3 !important;
                    --printumo-primary-light: rgba(119, 63, 198, 0.05) !important;
                    --printumo-primary-medium: rgba(119, 63, 198, 0.08) !important;
                    --printumo-secondary: #88d8d3 !important;
                    --printumo-bg: #FFFFFF !important;
                    --printumo-text: #333333 !important;
                    --printumo-border: #E0E0E0 !important;
                }

                /* ========== PRZYCISKI WYBORU WARIANTÓW (SIZES & FRAME) ========== */
                .printumo-variation-buttons {
                    display: flex !important;
                    flex-direction: column !important;
                    gap: 10px !important;
                    margin: 15px 0 25px 0 !important;
                    background: transparent !important;
                }

                .printumo-variation-btn {
                    padding: 12px 20px !important;
                    border: 1px solid var(--printumo-border) !important;
                    background: transparent !important;
                    border-radius: 8px !important;
                    cursor: pointer !important;
                    font-size: 15px !important;
                    font-weight: 400 !important;
                    transition: all 0.3s ease !important;
                    color: var(--printumo-text) !important;
                    line-height: 1.4 !important;
                    box-shadow: none !important;
                }

                .printumo-variation-btn:hover {
                    border-color: var(--printumo-primary) !important;
                    background: var(--printumo-primary-light) !important;
                    transform: translateY(-2px) !important;
                }

                .printumo-variation-btn.selected {
                    border: 2px solid var(--printumo-primary) !important;
                    background: var(--printumo-primary-medium) !important;
                    font-weight: 500 !important;
                    color: var(--printumo-primary) !important;
                }

                .printumo-variation-btn:disabled {
                    opacity: 0.3 !important;
                    cursor: not-allowed !important;
                    border-color: var(--printumo-border) !important;
                    transform: none !important;
                }

                .printumo-variation-btn:disabled:hover {
                    border-color: var(--printumo-border) !important;
                    background: transparent !important;
                    transform: none !important;
                }

                /* ========== KONFIGURATOR CANVAS ========== */
                .printumo-configurator {
                    margin: 40px 0 30px 0 !important;
                    padding: 0 !important;
                    background: transparent !important;
                    border-radius: 0 !important;
                    border: none !important;
                }

                .printumo-configurator h4 {
                    margin-top: 0 !important;
                    margin-bottom: 20px !important;
                    font-size: 16px !important;
                    font-weight: 500 !important;
                    color: var(--printumo-text) !important;
                    text-transform: uppercase !important;
                    letter-spacing: 0.5px !important;
                }

                .printumo-option {
                    margin-bottom: 25px !important;
                }

                .printumo-option > label {
                    display: block !important;
                    font-weight: 500 !important;
                    margin-bottom: 12px !important;
                    font-size: 14px !important;
                    color: var(--printumo-text) !important;
                }

                /* ========== OPCJE WRAPPINGU - ZMNIEJSZONE O 30% ========== */
                .printumo-wrap-options {
                    display: grid !important;
                    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)) !important;
                    gap: 12px !important;
                }

                @media (max-width: 768px) {
                    .printumo-wrap-options {
                        grid-template-columns: 1fr !important;
                    }
                }

                .printumo-wrap-option {
                    position: relative !important;
                }

                .printumo-wrap-option input[type="radio"] {
                    display: none !important;
                }

                .printumo-wrap-option label {
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: center !important;
                    justify-content: center !important;
                    padding: 16px !important;
                    border: 1px solid var(--printumo-border) !important;
                    border-radius: 12px !important;
                    cursor: pointer !important;
                    transition: all 0.3s ease !important;
                    background: var(--printumo-bg) !important;
                    box-shadow: none !important;
                    min-height: auto !important;
                    text-align: center !important;
                }

                .printumo-wrap-option label:hover {
                    border-color: var(--printumo-primary) !important;
                    box-shadow: 0 2px 8px rgba(119, 63, 198, 0.15) !important;
                }

                .printumo-wrap-option input[type="radio"]:checked + label {
                    border: 2px solid var(--printumo-primary) !important;
                    background: rgba(119, 63, 198, 0.03) !important;
                }

                /* Ikona checkmark */
                .printumo-wrap-option input[type="radio"]:checked + label::after {
                    content: "✓" !important;
                    position: absolute !important;
                    top: 8px !important;
                    right: 8px !important;
                    width: 20px !important;
                    height: 20px !important;
                    background: var(--printumo-primary) !important;
                    color: white !important;
                    border-radius: 50% !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    font-size: 12px !important;
                    font-weight: bold !important;
                }

                .printumo-wrap-option label strong {
                    display: block !important;
                    margin-bottom: 6px !important;
                    font-size: 14px !important;
                    font-weight: 500 !important;
                    color: var(--printumo-text) !important;
                }

                .printumo-wrap-option input[type="radio"]:checked + label strong {
                    color: var(--printumo-primary) !important;
                }

                .printumo-wrap-option label p {
                    margin: 0 !important;
                    font-size: 11px !important;
                    color: #666 !important;
                    font-weight: 400 !important;
                    line-height: 1.4 !important;
                }

                /* Ikony - zmniejszone do 40px */
                .printumo-wrap-option label strong::before {
                    content: "" !important;
                    display: block !important;
                    width: 40px !important;
                    height: 40px !important;
                    margin: 0 auto 10px auto !important;
                    border-radius: 6px !important;
                    background: #e8e8e8 !important;
                }

                .printumo-wrap-option:nth-child(1) label strong::before {
                    background: linear-gradient(90deg, #773fc6 0%, #773fc6 50%, #5f2fa3 50%, #5f2fa3 100%) !important;
                }

                .printumo-wrap-option:nth-child(2) label strong::before {
                    background: linear-gradient(135deg, #88d8d3 0%, #773fc6 100%) !important;
                }

                .printumo-wrap-option:nth-child(3) label strong::before {
                    background: var(--printumo-primary) !important;
                    border: 2px solid white !important;
                    box-shadow: 0 0 0 1px var(--printumo-primary) !important;
                }

                /* ========== WYBÓR KOLORU RAMKI - MAŁE PRZYCISKI ========== */
                .printumo-color-picker-wrapper {
                    display: none !important;
                    margin-top: 15px !important;
                    padding: 0 !important;
                    background: transparent !important;
                    border: none !important;
                }

                .printumo-color-picker-wrapper.active {
                    display: block !important;
                }

                .printumo-color-picker-wrapper > label {
                    display: block !important;
                    font-weight: 500 !important;
                    margin-bottom: 12px !important;
                    font-size: 14px !important;
                    color: var(--printumo-text) !important;
                }

                .printumo-color-swatches {
                    display: flex !important;
                    gap: 8px !important;
                    flex-wrap: wrap !important;
                }

                .printumo-color-swatch {
                    width: 40px !important;
                    height: 40px !important;
                    border-radius: 8px !important;
                    border: 2px solid var(--printumo-border) !important;
                    cursor: pointer !important;
                    transition: all 0.3s ease !important;
                    position: relative !important;
                }

                .printumo-color-swatch:hover {
                    transform: scale(1.1) !important;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
                }

                .printumo-color-swatch.selected {
                    border: 3px solid var(--printumo-primary) !important;
                    box-shadow: 0 0 0 2px rgba(119, 63, 198, 0.2) !important;
                }

                .printumo-color-swatch.selected::after {
                    content: "✓" !important;
                    position: absolute !important;
                    top: 50% !important;
                    left: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    color: white !important;
                    font-size: 16px !important;
                    font-weight: bold !important;
                    text-shadow: 0 1px 3px rgba(0,0,0,0.5) !important;
                }

                /* ========== CENA I PRZYCISK ADD TO CART W JEDNEJ LINII ========== */
                .single_variation_wrap {
                    background: transparent !important;
                }

                .woocommerce-variation-add-to-cart.variations_button {
                    display: flex !important;
                    align-items: center !important;
                    gap: 20px !important;
                    flex-wrap: wrap !important;
                }

                .woocommerce-variation-price {
                    order: -1 !important;
                    margin: 0 !important;
                }

                .woocommerce-variation-price .price {
                    font-family: 'Poppins', sans-serif !important;
                    font-size: 25px !important;
                    font-weight: 600 !important;
                    color: var(--printumo-primary) !important;
                    margin: 0 !important;
                }

                /* ========== PRZYCISK ADD TO CART ========== */
                .single_add_to_cart_button {
                    background: var(--printumo-primary) !important;
                    color: white !important;
                    border: none !important;
                    border-radius: 8px !important;
                    padding: 14px 32px !important;
                    font-weight: 500 !important;
                    transition: all 0.3s ease !important;
                }

                .single_add_to_cart_button:hover {
                    background: var(--printumo-primary-hover) !important;
                    transform: translateY(-2px) !important;
                    box-shadow: 0 4px 12px rgba(119, 63, 198, 0.3) !important;
                }

                /* ========== UKRYCIE WIDGETU CENY ELEMENTOR ========== */
                /* Ukrywa tylko konkretny widget Elementor z zakresem cen, nie wpływa na inne ceny */
                .elementor-element-cefb45c {
                    display: none !important;
                }

                /* ========== RESPONSYWNOŚĆ ========== */
                @media (max-width: 600px) {
                    .printumo-variation-btn {
                        padding: 10px 16px !important;
                        font-size: 14px !important;
                    }

                    .printumo-wrap-option label {
                        padding: 12px !important;
                    }

                    .printumo-wrap-option label strong {
                        font-size: 13px !important;
                    }

                    .printumo-wrap-option label p {
                        font-size: 10px !important;
                    }

                    .printumo-color-swatch {
                        width: 36px !important;
                        height: 36px !important;
                    }
                }
            ');

            wp_add_inline_script('jquery', '
                (function($) {
                    "use strict";
                    console.log("[Printumo] JavaScript załadowany");

                    function initPrintumoButtons() {
                        console.log("[Printumo] initPrintumoButtons wywołane");
                        console.log("[Printumo] Znalezione inputy wrap_type:", $("input[name=printumo_wrap_type]").length);
                        console.log("[Printumo] Znalezione przyciski wariantów:", $(".printumo-variation-btn").length);

                        // Obsługa wyboru typu wrappingu
                        $("input[name=printumo_wrap_type]").off("change.printumo").on("change.printumo", function() {
                            console.log("[Printumo] Zmiana typu wrappingu:", $(this).val());
                            if ($(this).val() === "solid_color") {
                                $(".printumo-color-picker-wrapper").addClass("active");
                            } else {
                                $(".printumo-color-picker-wrapper").removeClass("active");
                            }
                        });

                        // Obsługa wyboru koloru
                        $(".printumo-color-swatch").off("click.printumo").on("click.printumo", function() {
                            var color = $(this).data("color");
                            $(".printumo-color-swatch").removeClass("selected");
                            $(this).addClass("selected");
                            $("#printumo_wrap_color_input").val(color);
                        });

                        // Obsługa przycisków wariantów
                        $(".printumo-variation-btn").off("click.printumo").on("click.printumo", function(e) {
                            e.preventDefault();

                            var $btn = $(this);
                            if ($btn.prop("disabled")) {
                                return false;
                            }

                            var $container = $btn.closest(".printumo-variation-buttons");
                            var attribute = $container.data("attribute");
                            var value = $btn.data("value");

                            $container.find(".printumo-variation-btn").removeClass("selected");
                            $btn.addClass("selected");

                            var $select = $("select[data-attribute_name=\"attribute_" + attribute + "\"]");
                            $select.val(value).trigger("change");

                            return false;
                        });

                        // Auto-select pierwszej opcji
                        $(".printumo-variation-buttons").each(function() {
                            var $container = $(this);
                            var $selected = $container.find(".printumo-variation-btn.selected");

                            if ($selected.length === 0) {
                                var $firstBtn = $container.find(".printumo-variation-btn:not(:disabled):first");
                                if ($firstBtn.length) {
                                    setTimeout(function() {
                                        $firstBtn.trigger("click.printumo");
                                    }, 100);
                                }
                            }
                        });
                    }

                    $(document).ready(function() {
                        initPrintumoButtons();
                    });

                    $(document).on("woocommerce_update_variation_values", function() {
                        setTimeout(function() {
                            initPrintumoButtons();
                        }, 50);
                    });

                    $("form.variations_form").on("woocommerce_update_variation_values", function() {
                        $(".printumo-variation-buttons").each(function() {
                            var $container = $(this);
                            var attribute = $container.data("attribute");
                            var $select = $("select[data-attribute_name=\"attribute_" + attribute + "\"]");

                            $container.find(".printumo-variation-btn").each(function() {
                                var $btn = $(this);
                                var value = $btn.data("value");
                                var $option = $select.find("option[value=\"" + value + "\"]");

                                if ($option.length && $option.is(":disabled")) {
                                    $btn.prop("disabled", true);
                                } else {
                                    $btn.prop("disabled", false);
                                }
                            });
                        });
                    });

                })(jQuery);
            ');
        }
    }

    public function display_canvas_configurator() {
        global $product;

        $this->debug_log('display_canvas_configurator: Wywołane');

        if (!$product) {
            $this->debug_log('display_canvas_configurator: Brak globalnego obiektu $product');
            return;
        }

        $this->debug_log('display_canvas_configurator: Product ID: ' . $product->get_id());

        $is_canvas = get_post_meta($product->get_id(), '_printumo_is_canvas', true);
        $this->debug_log('display_canvas_configurator: _printumo_is_canvas = ' . var_export($is_canvas, true));

        if (!$is_canvas) {
            $this->debug_log('display_canvas_configurator: To nie jest produkt canvas, wyświetlanie przerwane');
            return;
        }

        // 10 popularnych kolorów ramek
        $frame_colors = [
            '#FFFFFF' => 'White',
            '#000000' => 'Black',
            '#8B4513' => 'Brown',
            '#D4AF37' => 'Gold',
            '#C0C0C0' => 'Silver',
            '#1E3A8A' => 'Navy',
            '#064E3B' => 'Green',
            '#7C2D12' => 'Rust',
            '#BE185D' => 'Pink',
            '#4B5563' => 'Gray'
        ];

        $this->debug_log('display_canvas_configurator: Wyświetlam HTML konfiguratora');
        ?>
        <!-- PRINTUMO CONFIGURATOR START -->
        <div class="printumo-configurator" data-debug="loaded">
            <h4>Canvas Edge Configuration</h4>
            <div class="printumo-option">
                <label>Choose edge finish:</label>
                <div class="printumo-wrap-options">
                    <div class="printumo-wrap-option">
                        <input type="radio" id="wrap_mirrored" name="printumo_wrap_type" value="mirrored" checked>
                        <label for="wrap_mirrored">
                            <strong>Mirrored</strong>
                            <p>Edge reflection</p>
                        </label>
                    </div>
                    <div class="printumo-wrap-option">
                        <input type="radio" id="wrap_stretched" name="printumo_wrap_type" value="stretched">
                        <label for="wrap_stretched">
                            <strong>Stretched</strong>
                            <p>Full stretch</p>
                        </label>
                    </div>
                    <div class="printumo-wrap-option">
                        <input type="radio" id="wrap_solid" name="printumo_wrap_type" value="solid_color">
                        <label for="wrap_solid">
                            <strong>Solid Color</strong>
                            <p>Choose color</p>
                        </label>
                    </div>
                </div>
            </div>
            <div class="printumo-option printumo-color-picker-wrapper">
                <label>Select edge color:</label>
                <div class="printumo-color-swatches">
                    <?php foreach ($frame_colors as $hex => $name): ?>
                        <div class="printumo-color-swatch <?php echo $hex === '#FFFFFF' ? 'selected' : ''; ?>"
                             style="background-color: <?php echo esc_attr($hex); ?>;"
                             data-color="<?php echo esc_attr($hex); ?>"
                             title="<?php echo esc_attr($name); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="printumo_wrap_color_input" name="printumo_wrap_color" value="#FFFFFF">
            </div>
        </div>
        <!-- PRINTUMO CONFIGURATOR END -->
        <?php
        $this->debug_log('display_canvas_configurator: Zakończono wyświetlanie konfiguratora');
    }

    public function add_canvas_config_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['printumo_wrap_type'])) {
            $cart_item_data['printumo_wrap_type'] = sanitize_text_field($_POST['printumo_wrap_type']);
            if ($_POST['printumo_wrap_type'] === 'solid_color' && isset($_POST['printumo_wrap_color'])) {
                $color = sanitize_text_field($_POST['printumo_wrap_color']);
                if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
                    $cart_item_data['printumo_wrap_color'] = $color;
                }
            }
        }
        return $cart_item_data;
    }

    public function display_canvas_config_in_cart($item_data, $cart_item) {
        if (isset($cart_item['printumo_wrap_type'])) {
            $wrap_labels = ['mirrored' => 'Mirrored', 'stretched' => 'Stretched', 'solid_color' => 'Solid Color'];
            $item_data[] = ['key' => 'Edge Finish', 'value' => $wrap_labels[$cart_item['printumo_wrap_type']] ?? $cart_item['printumo_wrap_type']];
            if ($cart_item['printumo_wrap_type'] === 'solid_color' && !empty($cart_item['printumo_wrap_color'])) {
                $color = $cart_item['printumo_wrap_color'];
                $item_data[] = ['key' => 'Edge Color', 'value' => '<span style="display:inline-block;width:20px;height:20px;background:' . esc_attr($color) . ';border:1px solid #ddd;vertical-align:middle;margin-right:5px;border-radius:4px;"></span>' . esc_html($color)];
            }
        }
        return $item_data;
    }

    public function save_canvas_config_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['printumo_wrap_type'])) {
            $item->add_meta_data('_printumo_wrap_type', $values['printumo_wrap_type'], true);
            if ($values['printumo_wrap_type'] === 'solid_color' && !empty($values['printumo_wrap_color'])) {
                $item->add_meta_data('_printumo_wrap_color', $values['printumo_wrap_color'], true);
            }
        }
    }

    private function api_request($endpoint, $method = 'GET', $data = null) {
        if (empty($this->api_key)) return new WP_Error('no_api_key', 'Brak klucza API');
        $url = $this->api_url . $endpoint;
        $args = ['method' => $method, 'headers' => ['Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'], 'timeout' => 30];
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) $args['body'] = json_encode($data);
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return $response;
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) return new WP_Error('api_error', $decoded['error'] ?? 'API Error');
        return $decoded;
    }

    public function add_admin_menu() {
        add_menu_page('Printumo Integration', 'Printumo', 'manage_woocommerce', 'printumo-integration', [$this, 'admin_page'], 'dashicons-cart', 56);
    }

    public function register_settings() {
        register_setting('printumo_settings', 'printumo_api_key');
        register_setting('printumo_settings', 'printumo_auto_fulfill');
        register_setting('printumo_settings', 'printumo_product_category');
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Printumo Integration</h1>
            <form method="post" action="options.php">
                <?php settings_fields('printumo_settings'); ?>
                <table class="form-table">
                    <tr><th>Klucz API</th><td><input type="text" name="printumo_api_key" value="<?php echo esc_attr(get_option('printumo_api_key')); ?>" class="regular-text" /></td></tr>
                    <tr><th>Kategoria</th><td><?php wp_dropdown_categories(['taxonomy' => 'product_cat', 'name' => 'printumo_product_category', 'selected' => get_option('printumo_product_category'), 'show_option_none' => 'Wybierz', 'option_none_value' => '']); ?></td></tr>
                    <tr><th>Auto-wysyłka</th><td><label><input type="checkbox" name="printumo_auto_fulfill" value="1" <?php checked(get_option('printumo_auto_fulfill'), 1); ?> /> Automatycznie</label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Import</h2>
            <button type="button" class="button button-primary" id="printumo-import-products">Importuj produkty</button>
            <span id="import-status"></span>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#printumo-import-products').on('click', function() {
                var btn = $(this); btn.prop('disabled', true); $('#import-status').html('Importowanie...');
                $.post(ajaxurl, {action: 'printumo_import_products'}, function(response) {
                    btn.prop('disabled', false);
                    $('#import-status').html(response.success ? '<span style="color:green">✓ ' + response.data + '</span>' : '<span style="color:red">✗ ' + response.data + '</span>');
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_import_products() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Brak uprawnień');
        $response = $this->api_request('/products');
        if (is_wp_error($response)) wp_send_json_error($response->get_error_message());
        if (!$response['success']) wp_send_json_error('Błąd API');
        $products = $response['data'];
        $imported = 0;
        $category_id = get_option('printumo_product_category');
        foreach ($products as $printumo_product) {
            $existing = $this->get_product_by_printumo_id($printumo_product['id']);
            if ($existing) {
                $this->update_product($existing, $printumo_product, $category_id);
            } else {
                $this->create_product($printumo_product, $category_id);
            }
            $imported++;
        }
        wp_send_json_success("Zaimportowano {$imported} produktów");
    }

    private function get_product_by_printumo_id($printumo_id) {
        $products = get_posts(['post_type' => 'product', 'meta_key' => '_printumo_product_id', 'meta_value' => $printumo_id, 'posts_per_page' => 1]);
        return !empty($products) ? wc_get_product($products[0]->ID) : null;
    }

    private function create_product($printumo_product, $category_id) {
        $product = new WC_Product_Variable();
        $product->set_name($printumo_product['title']);
        $product->set_slug(sanitize_title($printumo_product['title']));
        $product->set_description($printumo_product['description'] ?? '');
        $product->set_status('publish');
        if ($category_id) $product->set_category_ids([$category_id]);
        $product_id = $product->save();

        update_post_meta($product_id, '_printumo_product_id', $printumo_product['id']);
        $is_canvas = (isset($printumo_product['print_type']['slug']) && $printumo_product['print_type']['slug'] === 'canvas');
        update_post_meta($product_id, '_printumo_is_canvas', $is_canvas ? '1' : '0');

        if (!empty($printumo_product['original_image_url'])) {
            $this->set_product_image_from_url($product_id, $printumo_product['original_image_url']);
        }

        $this->create_variants($product_id, $printumo_product['variants']);
        return $product_id;
    }

    private function update_product($product, $printumo_product, $category_id) {
        $product->set_name($printumo_product['title']);
        $product->set_slug(sanitize_title($printumo_product['title']));
        $product->set_description($printumo_product['description'] ?? '');
        if ($category_id) $product->set_category_ids([$category_id]);
        $product->save();
        $is_canvas = (isset($printumo_product['print_type']['slug']) && $printumo_product['print_type']['slug'] === 'canvas');
        update_post_meta($product->get_id(), '_printumo_is_canvas', $is_canvas ? '1' : '0');
        $this->create_variants($product->get_id(), $printumo_product['variants']);
    }

    private function set_product_image_from_url($product_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $response = wp_remote_get($image_url, ['timeout' => 60, 'sslverify' => false]);
        if (is_wp_error($response)) return false;

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) return false;

        $upload_dir = wp_upload_dir();
        $filename = 'printumo-' . $product_id . '-' . time() . '.jpg';
        $file = $upload_dir['path'] . '/' . $filename;

        file_put_contents($file, $image_data);

        if (!file_exists($file) || filesize($file) == 0) return false;

        $filetype = wp_check_filetype($filename);
        $attachment = [
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $file, $product_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        set_post_thumbnail($product_id, $attach_id);

        return $attach_id;
    }

    private function create_variants($product_id, $variants) {
        $size_terms = [];
        $framing_terms = [];

        foreach ($variants as $variant) {
            $size_name = $variant['print_product']['size']['name'];
            $framing_name = $variant['print_product']['framing']['name'];
            $size_slug = sanitize_title($size_name);
            $framing_slug = sanitize_title($framing_name);

            if (!isset($size_terms[$size_slug])) {
                $size_terms[$size_slug] = $size_name;
            }
            if (!isset($framing_terms[$framing_slug])) {
                $framing_terms[$framing_slug] = $framing_name;
            }
        }

        $size_term_ids = [];
        foreach ($size_terms as $slug => $name) {
            $term = get_term_by('slug', $slug, 'pa_size');
            if (!$term) {
                $result = wp_insert_term($name, 'pa_size', ['slug' => $slug]);
                if (!is_wp_error($result)) {
                    $size_term_ids[] = $result['term_id'];
                }
            } else {
                $size_term_ids[] = $term->term_id;
            }
        }

        $framing_term_ids = [];
        foreach ($framing_terms as $slug => $name) {
            $term = get_term_by('slug', $slug, 'pa_framing');
            if (!$term) {
                $result = wp_insert_term($name, 'pa_framing', ['slug' => $slug]);
                if (!is_wp_error($result)) {
                    $framing_term_ids[] = $result['term_id'];
                }
            } else {
                $framing_term_ids[] = $term->term_id;
            }
        }

        wp_set_object_terms($product_id, $size_term_ids, 'pa_size');
        wp_set_object_terms($product_id, $framing_term_ids, 'pa_framing');

        $attributes = [];

        $size_attr = new WC_Product_Attribute();
        $size_attr->set_id(wc_attribute_taxonomy_id_by_name('pa_size'));
        $size_attr->set_name('pa_size');
        $size_attr->set_options($size_term_ids);
        $size_attr->set_visible(true);
        $size_attr->set_variation(true);
        $attributes['pa_size'] = $size_attr;

        $framing_attr = new WC_Product_Attribute();
        $framing_attr->set_id(wc_attribute_taxonomy_id_by_name('pa_framing'));
        $framing_attr->set_name('pa_framing');
        $framing_attr->set_options($framing_term_ids);
        $framing_attr->set_visible(true);
        $framing_attr->set_variation(true);
        $attributes['pa_framing'] = $framing_attr;

        $product = wc_get_product($product_id);
        $product->set_attributes($attributes);
        $product->save();

        foreach ($variants as $variant) {
            $size_slug = sanitize_title($variant['print_product']['size']['name']);
            $framing_slug = sanitize_title($variant['print_product']['framing']['name']);

            $existing = $this->get_variation_by_printumo_id($variant['id']);

            if (!$existing) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
            } else {
                $variation = $existing;
            }

            $variation->set_attributes(['pa_size' => $size_slug, 'pa_framing' => $framing_slug]);
            $price = $variant['price'] / 100;
            $variation->set_regular_price($price);
            $variation->set_manage_stock(false);
            $variation->set_stock_status('instock');
            $variation_id = $variation->save();
            update_post_meta($variation_id, '_printumo_variant_id', $variant['id']);
        }
    }

    private function get_variation_by_printumo_id($printumo_id) {
        $variations = get_posts(['post_type' => 'product_variation', 'meta_key' => '_printumo_variant_id', 'meta_value' => $printumo_id, 'posts_per_page' => 1]);
        return !empty($variations) ? wc_get_product($variations[0]->ID) : null;
    }

    public function ajax_test_image() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Brak uprawnień');
        $response = $this->api_request('/products');
        if (is_wp_error($response) || !$response['success'] || empty($response['data'])) wp_send_json_error('Brak produktów');
        $image_url = $response['data'][0]['original_image_url'];
        $test = wp_remote_get($image_url, ['timeout' => 60]);
        if (is_wp_error($test)) wp_send_json_error('Błąd: ' . $test->get_error_message());
        $size = strlen(wp_remote_retrieve_body($test));
        wp_send_json_success("Pobrano! Rozmiar: " . round($size / 1024, 2) . " KB");
    }

    public function ajax_debug_product() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Brak uprawnień');
        $product_id = intval($_POST['product_id']);
        if (!$product_id) wp_send_json_error('Nieprawidłowe ID');
        $product = wc_get_product($product_id);
        if (!$product) wp_send_json_error('Produkt nie istnieje');

        $debug = [];
        $debug['ID'] = $product_id;
        $debug['Name'] = $product->get_name();
        $debug['Type'] = $product->get_type();
        $debug['Image ID'] = $product->get_image_id();

        wp_send_json_success(print_r($debug, true));
    }

    public function send_order_to_printumo($order_id) {
        if (!get_option('printumo_auto_fulfill') || get_post_meta($order_id, '_printumo_order_id', true)) return;
        $order = wc_get_order($order_id);
        $line_items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            $variant_id = get_post_meta($product->get_id(), '_printumo_variant_id', true);
            if (!$variant_id) continue;
            $line_item = ['variant_id' => (int)$variant_id, 'quantity' => $item->get_quantity()];
            $wrap_type = $item->get_meta('_printumo_wrap_type', true);
            if ($wrap_type) {
                $line_item['canvas_wrapping'] = ['wrap_type' => $wrap_type];
                if ($wrap_type === 'solid_color') {
                    $wrap_color = $item->get_meta('_printumo_wrap_color', true);
                    if ($wrap_color) $line_item['canvas_wrapping']['wrap_color'] = $wrap_color;
                }
            }
            $line_items[] = $line_item;
        }
        if (empty($line_items)) return;

        $shipping_address = [
            'email' => $order->get_billing_email(),
            'first_name' => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
            'last_name' => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
            'address1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            'address2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
            'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
            'postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
            'country_code' => $order->get_shipping_country() ?: $order->get_billing_country(),
            'phone' => $order->get_billing_phone(),
        ];

        $data = ['line_items' => $line_items, 'shipping_address' => $shipping_address, 'selected_currency' => $order->get_currency()];
        $response = $this->api_request('/orders', 'POST', $data);

        if (is_wp_error($response)) {
            $order->add_order_note('Błąd: ' . $response->get_error_message());
        } elseif ($response['success']) {
            update_post_meta($order_id, '_printumo_order_id', $response['data']['id']);
            $order->add_order_note("Wysłano do Printumo (ID: {$response['data']['id']})");
        }
    }

    public function ajax_sync_orders() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Brak uprawnień');
        $synced = $this->sync_order_statuses();
        wp_send_json_success("Zsynchronizowano {$synced} zamówień");
    }

    public function sync_order_statuses() {
        $response = $this->api_request('/orders');
        if (is_wp_error($response) || !$response['success']) return 0;
        $printumo_orders = $response['data'];
        $synced = 0;
        foreach ($printumo_orders as $printumo_order) {
            $order = $this->get_order_by_printumo_id($printumo_order['id']);
            if (!$order) continue;
            $status_map = ['pending' => 'processing', 'processing' => 'processing', 'shipped' => 'completed', 'delivered' => 'completed', 'cancelled' => 'cancelled'];
            $printumo_status = $printumo_order['status'] ?? 'pending';
            $wc_status = $status_map[$printumo_status] ?? 'processing';
            if ($order->get_status() !== $wc_status) {
                $order->update_status($wc_status);
                $synced++;
            }
        }
        return $synced;
    }

    private function get_order_by_printumo_id($printumo_id) {
        $orders = get_posts(['post_type' => 'shop_order', 'meta_key' => '_printumo_order_id', 'meta_value' => $printumo_id, 'posts_per_page' => 1]);
        return !empty($orders) ? wc_get_order($orders[0]->ID) : null;
    }

    public function add_order_meta_box() {
        add_meta_box('printumo_order_info', 'Printumo Order', [$this, 'render_order_meta_box'], 'shop_order', 'side');
    }

    public function render_order_meta_box($post) {
        $printumo_order_id = get_post_meta($post->ID, '_printumo_order_id', true);
        echo $printumo_order_id ? '<p><strong>Printumo ID:</strong><br>' . esc_html($printumo_order_id) . '</p>' : '<p>Nie wysłano</p>';
    }
}

new Printumo_WooCommerce_Integration();
