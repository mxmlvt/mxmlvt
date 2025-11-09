<?php
/**
 * WooCommerce Dynamic Grid Variations - JetElements/Elementor
 *
 * Funkcjonalność:
 * - Auto-matching wariantów z kategoriami
 * - Dynamiczne wyświetlanie wariantów w gridach
 * - Dodawanie do koszyka z przekierowaniem na stronę wariantu
 */

// ===== AUTO-MATCHING WARIANTÓW Z KATEGORIAMI =====

// KONFIGURACJA: Mapowanie nazw wariantów na kategorie (slug)
function get_variation_category_mapping() {
    return array(
        'original' => 'originals',
        'Original' => 'originals',
        'canvas print' => 'canvas-prints',
        'Canvas Print' => 'canvas-prints',
        'canvas prints' => 'canvas-prints',
        'Canvas Prints' => 'canvas-prints',
        'paper print' => 'paper-prints',
        'Paper Print' => 'paper-prints',
        'paper prints' => 'paper-prints',
        'Paper Prints' => 'paper-prints',
    );
}

// Helper: Pobiera kategorię dla wariantu na podstawie jego atrybutów
function get_category_for_variation_auto($variation_id) {
    static $cache = array();

    if (isset($cache[$variation_id])) {
        return $cache[$variation_id];
    }

    $variation = wc_get_product($variation_id);
    if (!$variation) {
        $cache[$variation_id] = false;
        return false;
    }

    $attributes = $variation->get_variation_attributes();
    $mapping = get_variation_category_mapping();

    foreach ($attributes as $attr_name => $attr_value) {
        // Sprawdź dokładne dopasowanie (z wielką literą)
        if (isset($mapping[$attr_value])) {
            $category_slug = $mapping[$attr_value];
            $term = get_term_by('slug', $category_slug, 'product_cat');

            if ($term && !is_wp_error($term)) {
                $cache[$variation_id] = $term->term_id;
                return $term->term_id;
            }
        }

        // Sprawdź lowercase dopasowanie
        $attr_value_lower = strtolower(trim($attr_value));
        if (isset($mapping[$attr_value_lower])) {
            $category_slug = $mapping[$attr_value_lower];
            $term = get_term_by('slug', $category_slug, 'product_cat');

            if ($term && !is_wp_error($term)) {
                $cache[$variation_id] = $term->term_id;
                return $term->term_id;
            }
        }
    }

    $cache[$variation_id] = false;
    return false;
}

// 1. FILTRUJ PRODUKTY W KATEGORIACH - pokaż only te z odpowiednimi wariantami
add_action('pre_get_posts', 'filter_products_by_variation_auto');

function filter_products_by_variation_auto($query) {
    if (!is_admin() && $query->is_main_query() && is_tax('product_cat')) {

        $current_category_id = get_queried_object_id();

        // Sprawdź cache
        $cache_key = 'variation_category_products_' . $current_category_id;
        $matching_parent_ids = get_transient($cache_key);

        if ($matching_parent_ids === false) {
            // Cache nie istnieje, przelicz
            global $wpdb;

            // Pobierz tylko variable products
            $variable_products = $wpdb->get_col("
                SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND tt.taxonomy = 'product_type'
                AND t.slug = 'variable'
            ");

            $matching_parent_ids = array();

            foreach ($variable_products as $product_id) {
                $product = wc_get_product($product_id);

                if (!$product || !$product->is_type('variable')) {
                    continue;
                }

                $variations = $product->get_children();

                foreach ($variations as $variation_id) {
                    $variation_category = get_category_for_variation_auto($variation_id);

                    if ($variation_category == $current_category_id) {
                        $matching_parent_ids[] = $product_id;
                        break;
                    }
                }
            }

            // Zapisz w cache na 1 godzinę
            set_transient($cache_key, $matching_parent_ids, HOUR_IN_SECONDS);
        }

        if (!empty($matching_parent_ids)) {
            $query->set('post__in', $matching_parent_ids);
        } else {
            $query->set('post__in', array(0));
        }
    }
}

// Wyczyść cache gdy produkt/wariant jest zapisany
add_action('woocommerce_update_product', 'clear_variation_category_cache');
add_action('woocommerce_save_product_variation', 'clear_variation_category_cache');

function clear_variation_category_cache() {
    global $wpdb;

    // Wyczyść wszystkie cache dla kategorii
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_variation_category_products_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_variation_category_products_%'");
}

// 2. HELPER: Pobiera wariant dla aktualnej kategorii
function get_variation_for_current_category($product_id, $category_id) {
    static $cache = array();

    $cache_key = $product_id . '_' . $category_id;

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $product = wc_get_product($product_id);

    if (!$product || !$product->is_type('variable')) {
        return false;
    }

    $variations = $product->get_available_variations();

    foreach ($variations as $variation) {
        $variation_id = $variation['variation_id'];
        $variation_category = get_category_for_variation_auto($variation_id);

        if ($variation_category == $category_id) {
            $cache[$cache_key] = $variation_id;
            return $variation_id;
        }
    }

    $cache[$cache_key] = false;
    return false;
}


// 2B. HELPER: Pobiera wariant "Original" dla produktu
function get_original_variation_id($product_id) {
    static $cache = array();
    if (isset($cache[$product_id])) {
        return $cache[$product_id];
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        $cache[$product_id] = false;
        return false;
    }

    // 'originals' to slug kategorii z Twojego mapowania
    $originals_term = get_term_by('slug', 'originals', 'product_cat');
    if (!$originals_term) {
         $cache[$product_id] = false;
         return false;
    }
    $originals_category_id = $originals_term->term_id;

    $variations = $product->get_children(); // Pobierz wszystkie ID wariantów
    foreach ($variations as $variation_id) {
        // Użyj istniejącego helpera!
        $variation_category = get_category_for_variation_auto($variation_id);
        if ($variation_category == $originals_category_id) {
            $cache[$product_id] = $variation_id;
            return $variation_id;
        }
    }

    $cache[$product_id] = false;
    return false;
}


// 3. ZMIEŃ CENĘ - pokaż konkretną cenę wariantu
add_filter('woocommerce_get_price_html', 'show_variation_price_auto', 20, 2);

function show_variation_price_auto($price, $product) {
    if (!$product || !$product->is_type('variable')) {
        return $price;
    }

    $variation_id = false;

    if (is_tax('product_cat')) {
        // 1. Logika dla Archiwów Kategorii (tak jak było)
        $current_category_id = get_queried_object_id();
        $variation_id = get_variation_for_current_category($product->get_id(), $current_category_id);
    } else {
        // 2. NOWA LOGIKA: WSZĘDZIE INDZIEJ (Strona główna, Kolekcje, Tryptyki, etc.)
        // Pokaż domyślnie wariant "Original"
        $variation_id = get_original_variation_id($product->get_id());
    }

    if ($variation_id) {
        $variation_obj = wc_get_product($variation_id);
        if ($variation_obj) {
            return $variation_obj->get_price_html();
        }
    }

    return $price;
}

// 4. ZMIEŃ URL ADD TO CART
add_filter('woocommerce_product_add_to_cart_url', 'variation_add_to_cart_url_auto', 10, 2);

function variation_add_to_cart_url_auto($url, $product) {
    if (!$product || !$product->is_type('variable')) {
        return $url;
    }

    $variation_id = false;

    if (is_tax('product_cat')) {
        // 1. Logika dla Archiwów Kategorii
        $current_category_id = get_queried_object_id();
        $variation_id = get_variation_for_current_category($product->get_id(), $current_category_id);
    } else {
        // 2. NOWA LOGIKA: WSZĘDZIE INDZIEJ
        $variation_id = get_original_variation_id($product->get_id());
    }

    if ($variation_id) {
        $variation_obj = wc_get_product($variation_id);
        if ($variation_obj) {
            $attributes = $variation_obj->get_variation_attributes();

            $add_to_cart_url = add_query_arg(array(
                'add-to-cart' => $product->get_id(),
                'variation_id' => $variation_id,
                'quantity' => 1
            ));

            foreach ($attributes as $attr_key => $attr_value) {
                $add_to_cart_url = add_query_arg('attribute_' . sanitize_title($attr_key), urlencode($attr_value), $add_to_cart_url);
            }

            return $add_to_cart_url;
        }
    }

    return $url;
}

// 5. ZMIEŃ TEKST PRZYCISKU
add_filter('woocommerce_product_add_to_cart_text', 'variation_add_to_cart_text_auto', 10, 2);

function variation_add_to_cart_text_auto($text, $product) {
    if (!$product || !$product->is_type('variable')) {
        return $text;
    }

    $variation_id = false;

    if (is_tax('product_cat')) {
        // 1. Logika dla Archiwów Kategorii
        $current_category_id = get_queried_object_id();
        $variation_id = get_variation_for_current_category($product->get_id(), $current_category_id);
    } else {
        // 2. NOWA LOGIKA: WSZĘDZIE INDZIEJ
        $variation_id = get_original_variation_id($product->get_id());
    }

    if ($variation_id) {
        return __('Add to cart', 'woocommerce');
    }

    return $text;
}

// 6. FIX DLA AJAX ADD TO CART
add_filter('woocommerce_loop_add_to_cart_link', 'fix_variation_ajax_add_to_cart_auto', 10, 3);

function fix_variation_ajax_add_to_cart_auto($html, $product, $args) {
    if (!$product || !$product->is_type('variable')) {
        return $html;
    }

    $variation_id = false;

    if (is_tax('product_cat')) {
        // 1. Logika dla Archiwów Kategorii
        $current_category_id = get_queried_object_id();
        $variation_id = get_variation_for_current_category($product->get_id(), $current_category_id);
    } else {
        // 2. NOWA LOGIKA: WSZĘDZIE INDZIEJ
        $variation_id = get_original_variation_id($product->get_id());
    }

    if (!$variation_id) {
        return $html;
    }

    $variation_obj = wc_get_product($variation_id);
    if (!$variation_obj || !$variation_obj->is_in_stock()) {
        return $html;
    }

    $attributes = $variation_obj->get_variation_attributes();

    $add_to_cart_url = add_query_arg(array(
        'add-to-cart' => $product->get_id(),
        'variation_id' => $variation_id,
        'quantity' => 1
    ));

    foreach ($attributes as $attr_key => $attr_value) {
        $add_to_cart_url = add_query_arg('attribute_' . sanitize_title($attr_key), urlencode($attr_value), $add_to_cart_url);
    }

    // Dodajemy klasę 'redirect_to_product' do późniejszej obsługi JS
    $class = 'button product_type_variation add_to_cart_button ajax_add_to_cart redirect_to_product';

    // Budujemy URL do strony produktu z wariantem
    $product_url = get_permalink($product->get_id());
    $product_url = add_query_arg('variation_id', $variation_id, $product_url);
    foreach ($attributes as $attr_key => $attr_value) {
        $product_url = add_query_arg('attribute_' . sanitize_title($attr_key), urlencode($attr_value), $product_url);
    }

    return sprintf(
        '<a href="%s" data-quantity="%s" class="%s" data-product_id="%s" data-variation_id="%s" data-product_url="%s" aria-label="%s" rel="nofollow">%s</a>',
        esc_url($add_to_cart_url),
        esc_attr(isset($args['quantity']) ? $args['quantity'] : 1),
        esc_attr($class),
        esc_attr($product->get_id()),
        esc_attr($variation_id),
        esc_url($product_url), // Nowy atrybut z URL produktu
        esc_attr(sprintf(__('Add "%s" to your cart', 'woocommerce'), $product->get_name())),
        esc_html(__('Add to cart', 'woocommerce'))
    );
}

// 7. PODMIEŃ LINK DO PRODUKTU
add_filter('woocommerce_loop_product_link', 'variation_product_link_auto', 10, 2);

function variation_product_link_auto($link, $product) {
    if (!$product || !$product->is_type('variable')) {
        return $link;
    }

    $variation_id = false;

    if (is_tax('product_cat')) {
        // 1. Logika dla Archiwów Kategorii
        $current_category_id = get_queried_object_id();
        $variation_id = get_variation_for_current_category($product->get_id(), $current_category_id);
    } else {
        // 2. NOWA LOGIKA: WSZĘDZIE INDZIEJ
        $variation_id = get_original_variation_id($product->get_id());
    }

    if ($variation_id) {
        $variation_obj = wc_get_product($variation_id);
        if ($variation_obj) {
            $attributes = $variation_obj->get_variation_attributes();

            $link = add_query_arg('variation_id', $variation_id, $link);

            foreach ($attributes as $attr_key => $attr_value) {
                $link = add_query_arg('attribute_' . sanitize_title($attr_key), urlencode($attr_value), $link);
            }
        }
    }

    return $link;
}

// 8. POPRAW WYŚWIETLANIE KATEGORII W GRIDZIE (DLA JETWOOBUILDER)
add_filter('jet_woo_builder_template_categories_output', 'byku_jet_fix_grid_category_display', 20, 2);

function byku_jet_fix_grid_category_display($categories_html, $settings) {

    // Działaj tylko na stronach archiwów kategorii
    if (is_admin() || !is_tax('product_cat')) {
        return $categories_html;
    }

    global $product;
    if (!$product || !$product->is_type('variable')) {
        return $categories_html;
    }

    // Pobierz oryginalne kategorie (to, co Jet by wyświetlił)
    $original_terms = get_the_terms($product->get_id(), 'product_cat');
    $default_cat_id = get_option('default_product_cat');
    $is_default_only = false;

    if (empty($original_terms) || is_wp_error($original_terms)) {
        $is_default_only = true;
    } elseif (count($original_terms) == 1 && $original_terms[0]->term_id == $default_cat_id) {
        $is_default_only = true;
    }

    // Jeśli Jet pokazuje tylko "Bez kategorii"
    if ($is_default_only) {
        $current_category_id = get_queried_object_id();

        // Sprawdź, czy ten produkt ma wariant pasujący do tej kategorii
        $variation_id = get_variation_for_current_category($product->get_id(), $current_category_id);

        if ($variation_id) {
            // Ma wariant, więc podmieniamy "Bez kategorii" na aktualną kategorię
            $current_term = get_term($current_category_id, 'product_cat');
            if ($current_term && !is_wp_error($current_term)) {

                // Zbuduj HTML dla kategorii ręcznie, tak jak robi to Jet
                $term_link = get_term_link($current_term, 'product_cat');

                return sprintf(
                    '<div class="jet-woo-product-categories"><a href="%s" class="jet-woo-product-category__link" rel="tag">%s</a></div>',
                    esc_url($term_link),
                    esc_html($current_term->name)
                );
            }
        }
    }

    // W każdym innym przypadku zwróć oryginalne HTML kategorii
    return $categories_html;
}

// ===== NOWA FUNKCJONALNOŚĆ: PRZEKIEROWANIE NA STRONĘ WARIANTU PO DODANIU DO KOSZYKA =====

// 9. PRZEKIEROWANIE PO DODANIU DO KOSZYKA (dla standardowego add to cart)
add_filter('woocommerce_add_to_cart_redirect', 'redirect_to_variation_page_after_add_to_cart', 10, 1);

function redirect_to_variation_page_after_add_to_cart($url) {
    // Sprawdź czy mamy variation_id w REQUEST
    if (isset($_REQUEST['variation_id']) && !empty($_REQUEST['variation_id'])) {
        $variation_id = absint($_REQUEST['variation_id']);
        $product_id = absint($_REQUEST['add-to-cart']);

        $variation = wc_get_product($variation_id);
        if ($variation && $variation->exists()) {
            // Budujemy URL do strony produktu z parametrami wariantu
            $product_url = get_permalink($product_id);
            $product_url = add_query_arg('variation_id', $variation_id, $product_url);

            // Dodaj atrybuty wariantu do URL
            $attributes = $variation->get_variation_attributes();
            foreach ($attributes as $attr_key => $attr_value) {
                $product_url = add_query_arg('attribute_' . sanitize_title($attr_key), urlencode($attr_value), $product_url);
            }

            return $product_url;
        }
    }

    return $url;
}

// 10. JAVASCRIPT DO OBSŁUGI AJAX ADD TO CART Z PRZEKIEROWANIEM
add_action('wp_footer', 'variation_ajax_add_to_cart_redirect_script');

function variation_ajax_add_to_cart_redirect_script() {
    if (!is_woocommerce() && !is_front_page() && !is_home()) {
        return; // Ładuj tylko na stronach WooCommerce i stronie głównej
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Przechwytujemy kliknięcie w przycisk add to cart z klasą redirect_to_product
        $(document).on('click', '.redirect_to_product.ajax_add_to_cart', function(e) {
            e.preventDefault();

            var $button = $(this);
            var product_id = $button.data('product_id');
            var variation_id = $button.data('variation_id');
            var product_url = $button.data('product_url');
            var quantity = $button.data('quantity') || 1;

            // Dodaj klasę loading do przycisku
            $button.removeClass('added').addClass('loading');

            // Budujemy dane do wysłania
            var data = {
                action: 'woocommerce_ajax_add_to_cart',
                product_id: product_id,
                variation_id: variation_id,
                quantity: quantity
            };

            // Pobierz wszystkie atrybuty wariantu z href
            var href = $button.attr('href');
            if (href && href.indexOf('attribute_') !== -1) {
                var url_params = new URLSearchParams(href.split('?')[1]);
                url_params.forEach(function(value, key) {
                    if (key.indexOf('attribute_') === 0) {
                        data[key] = value;
                    }
                });
            }

            // Wyślij request AJAX
            $.ajax({
                type: 'POST',
                url: wc_add_to_cart_params.ajax_url,
                data: data,
                success: function(response) {
                    if (response.error && response.product_url) {
                        window.location = response.product_url;
                        return;
                    }

                    // Trigger event dla WooCommerce
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $button]);

                    // Po krótkiej chwili przekieruj na stronę produktu
                    setTimeout(function() {
                        if (product_url) {
                            window.location.href = product_url;
                        }
                    }, 300); // 300ms opóźnienia dla lepszego UX
                },
                error: function() {
                    $button.removeClass('loading');
                }
            });

            return false;
        });
    });
    </script>
    <?php
}

// 11. AJAX HANDLER DLA ADD TO CART (potrzebny do obsługi AJAX request)
add_action('wp_ajax_woocommerce_ajax_add_to_cart', 'ajax_add_variation_to_cart');
add_action('wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'ajax_add_variation_to_cart');

function ajax_add_variation_to_cart() {
    $product_id = absint($_POST['product_id']);
    $variation_id = absint($_POST['variation_id']);
    $quantity = absint($_POST['quantity']);

    // Zbierz atrybuty wariantu
    $variation_data = array();
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'attribute_') === 0) {
            $variation_data[$key] = sanitize_text_field($value);
        }
    }

    // Dodaj wariant do koszyka
    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);

    if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_data)) {
        do_action('woocommerce_ajax_added_to_cart', $product_id);

        WC_AJAX::get_refreshed_fragments();
    } else {
        $data = array(
            'error' => true,
            'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id)
        );

        wp_send_json($data);
    }
}
