<?php
/**
 * Plugin Name: Let's Fight - Mapa Klubów
 * Description: Integracja mapy Mapbox z JetEngine dla klubów sportowych
 * Version: 1.1.0
 * Author: MaxDigital.pl
 * Text Domain: letsfight-mapa
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

/**
 * Główna klasa wtyczki Let's Fight Mapa
 */
class LetsFight_Mapa {

    /**
     * Token API Mapbox
     * @var string
     */
    private $mapbox_token = 'pk.eyJ1IjoibWF4ZGlnaXRhbCIsImEiOiJjbTN4eWc4dXowMDJqMmpzYnZ6dXNsYnNyIn0.vKhEBdS_KwATxsA5fTqmqg';

    /**
     * Wersja wtyczki
     * @var string
     */
    private $version = '1.1.0';

    /**
     * Singleton instance
     * @var LetsFight_Mapa
     */
    private static $instance = null;

    /**
     * Pobierz instancję singletona
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor - rejestracja hooków
     */
    public function __construct() {
        // Sprawdź wymagane pluginy przy aktywacji
        register_activation_hook(__FILE__, [$this, 'check_requirements']);

        // Inicjalizacja
        add_action('init', [$this, 'register_meta_fields']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // Shortcode
        add_shortcode('letsfight_wyszukiwarka_miasto', [$this, 'render_wyszukiwarka']);
        add_shortcode('letsfight_club_panel', [$this, 'render_wyszukiwarka']); // Alias

        // AJAX
        add_action('wp_ajax_letsfight_get_clubs_coords', [$this, 'ajax_get_clubs_coords']);
        add_action('wp_ajax_nopriv_letsfight_get_clubs_coords', [$this, 'ajax_get_clubs_coords']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Sprawdź wymagania przy aktywacji
     */
    public function check_requirements() {
        $errors = [];

        if (!class_exists('Jet_Engine')) {
            $errors[] = 'JetEngine nie jest zainstalowany lub aktywny.';
        }

        if (!class_exists('Jet_Smart_Filters')) {
            $errors[] = 'JetSmartFilters nie jest zainstalowany lub aktywny.';
        }

        if (!empty($errors)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                '<h1>Błąd aktywacji wtyczki Let\'s Fight - Mapa Klubów</h1>' .
                '<p>Wymagane pluginy:</p><ul><li>' . implode('</li><li>', $errors) . '</li></ul>' .
                '<p><a href="' . admin_url('plugins.php') . '">Powrót do listy wtyczek</a></p>'
            );
        }
    }

    /**
     * Pokaż powiadomienia w panelu admina
     */
    public function admin_notices() {
        if (!class_exists('Jet_Engine') || !class_exists('Jet_Smart_Filters')) {
            ?>
            <div class="notice notice-error">
                <p><strong>Let's Fight - Mapa Klubów:</strong> Wymagane pluginy JetEngine i JetSmartFilters nie są aktywne.</p>
            </div>
            <?php
        }
    }

    /**
     * Rejestracja pól meta dla współrzędnych
     */
    public function register_meta_fields() {
        register_post_meta('kluby', 'club_latitude', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_post_meta('kluby', 'club_longitude', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    /**
     * Załaduj assety (CSS, JS)
     */
    public function enqueue_assets() {
        // Sprawdź czy jesteśmy na stronie z shortcodem
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_shortcode = has_shortcode($post->post_content, 'letsfight_wyszukiwarka_miasto') ||
                         has_shortcode($post->post_content, 'letsfight_club_panel');

        if (!$has_shortcode && !is_singular() && !is_page() && !is_archive()) {
            return;
        }

        // Mapbox GL JS
        wp_enqueue_style('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', [], '2.15.0');
        wp_enqueue_script('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', [], '2.15.0', true);

        // Plugin CSS & JS
        wp_enqueue_style(
            'letsfight-mapa',
            plugins_url('assets/style.css', __FILE__),
            [],
            $this->version
        );

        wp_enqueue_script(
            'letsfight-mapa',
            plugins_url('assets/script.js', __FILE__),
            ['jquery', 'mapbox-gl'],
            $this->version,
            true
        );

        // Lokalizacja zmiennych JS
        wp_localize_script('letsfight-mapa', 'letsfightMap', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'mapboxToken' => $this->mapbox_token,
            'nonce' => wp_create_nonce('letsfight_map_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ]);
    }

    /**
     * Renderowanie shortcode'a wyszukiwarki
     */
    public function render_wyszukiwarka($atts) {
        // Sprawdź wymagane pluginy
        if (!class_exists('Jet_Engine') || !class_exists('Jet_Smart_Filters')) {
            return '<div class="letsfight-error" style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px;">
                <strong>Błąd:</strong> Wymagane pluginy JetEngine i JetSmartFilters nie są aktywne.
            </div>';
        }

        // Parametry shortcode
        $atts = shortcode_atts([
            'listing_id' => '439',
            'filter_dyscyplina' => '467',
            'filter_search' => '516',
        ], $atts);

        // Wykryj miasto z URL
        $miasto_slug = $this->get_city_from_url();

        // Pobierz listę miast
        $miasta = get_terms([
            'taxonomy' => 'miasta',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($miasta)) {
            $miasta = [];
        }

        // Rozpocznij buforowanie outputu
        ob_start();
        ?>
        <div class="letsfight-wyszukiwarka"
             data-listing-id="<?php echo esc_attr($atts['listing_id']); ?>"
             data-miasto-slug="<?php echo esc_attr($miasto_slug); ?>">

            <!-- Panel filtrów -->
            <div class="letsfight-panel">
                <div class="letsfight-panel__filters">

                    <!-- Filtr miasta -->
                    <div class="letsfight-filter letsfight-filter--miasto">
                        <label class="letsfight-filter__label">MIASTO</label>
                        <select class="letsfight-select letsfight-miasto-select" data-current-slug="<?php echo esc_attr($miasto_slug); ?>">
                            <option value="">Wszystkie miasta</option>
                            <?php if (!empty($miasta)): ?>
                                <?php foreach ($miasta as $miasto): ?>
                                    <option value="<?php echo esc_attr($miasto->slug); ?>"
                                            <?php selected($miasto->slug, $miasto_slug); ?>>
                                        <?php echo esc_html($miasto->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Przycisk szukaj (pokazuje się po zmianie miasta) -->
                    <button class="letsfight-btn letsfight-btn--search" style="display: none;">
                        <span class="letsfight-btn__icon">🔍</span>
                        <span class="letsfight-btn__text">Szukaj</span>
                    </button>

                    <!-- Filtr dyscypliny (JetSmartFilters) -->
                    <div class="letsfight-filter letsfight-filter--dyscyplina">
                        <label class="letsfight-filter__label">DYSCYPLINA</label>
                        <?php
                        echo do_shortcode('[jet-smart-filters-select filter_id="' . esc_attr($atts['filter_dyscyplina']) . '" provider="jet-engine" query_id="default" apply_type="ajax" placeholder="Wybierz dyscyplinę"]');
                        ?>
                    </div>

                    <!-- Wyszukiwarka (JetSmartFilters) -->
                    <div class="letsfight-filter letsfight-filter--search">
                        <label class="letsfight-filter__label">WYSZUKAJ KLUB</label>
                        <?php
                        echo do_shortcode('[jet-smart-filters-search filter_id="' . esc_attr($atts['filter_search']) . '" provider="jet-engine" query_id="default" apply_type="ajax" placeholder="Szukaj..."]');
                        ?>
                    </div>

                    <!-- Reset filtrów -->
                    <div class="letsfight-filter-reset-wrapper">
                        <?php
                        echo do_shortcode('[jet-smart-filters-remove-filters provider="jet-engine" query_id="default" label="🔄 Wyczyść filtry"]');
                        ?>
                    </div>

                </div>

                <!-- Akcje (toggle + counter) -->
                <div class="letsfight-panel__actions">

                    <!-- Toggle Lista/Mapa -->
                    <div class="letsfight-toggle">
                        <button class="letsfight-toggle__btn letsfight-toggle__btn--active" data-view="lista">
                            <span class="letsfight-toggle__icon">☰</span>
                            <span class="letsfight-toggle__text">Lista</span>
                        </button>
                        <button class="letsfight-toggle__btn" data-view="mapa">
                            <span class="letsfight-toggle__icon">🗺️</span>
                            <span class="letsfight-toggle__text">Mapa</span>
                        </button>
                    </div>

                    <!-- Licznik klubów -->
                    <div class="letsfight-counter">
                        <span class="letsfight-counter__text">Znaleziono:</span>
                        <span class="letsfight-counter__number">0</span>
                        <span class="letsfight-counter__label">klubów</span>
                    </div>

                </div>
            </div>

            <!-- Widok listy (JetEngine Grid) -->
            <div class="letsfight-view letsfight-view--lista letsfight-view--active">
                <?php
                // Poprawna składnia shortcode JetEngine
                echo do_shortcode('[jet_engine_data component="listings_grid" listing_id="' . esc_attr($atts['listing_id']) . '"]');
                ?>
            </div>

            <!-- Widok mapy (Mapbox) -->
            <div class="letsfight-view letsfight-view--mapa">
                <div id="letsfight-map" style="width: 100%; height: 600px;"></div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Wykryj miasto z URL
     */
    private function get_city_from_url() {
        $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        $url_parts = explode('/', trim($current_url, '/'));

        if (in_array('trenuj', $url_parts)) {
            $key = array_search('trenuj', $url_parts);
            if (isset($url_parts[$key + 1])) {
                return sanitize_title($url_parts[$key + 1]);
            }
        }

        return '';
    }

    /**
     * AJAX Handler - pobierz współrzędne klubów
     */
    public function ajax_get_clubs_coords() {
        // Weryfikacja nonce
        check_ajax_referer('letsfight_map_nonce', 'nonce');

        // Pobierz ID postów
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];

        if (empty($post_ids)) {
            wp_send_json_error(['message' => 'Brak ID postów']);
            return;
        }

        $clubs = [];

        foreach ($post_ids as $post_id) {
            // Sprawdź czy post istnieje
            if (get_post_status($post_id) !== 'publish') {
                continue;
            }

            $lat = get_post_meta($post_id, 'club_latitude', true);
            $lng = get_post_meta($post_id, 'club_longitude', true);

            // Jeśli brak współrzędnych - spróbuj geocodować adres
            if (empty($lat) || empty($lng)) {
                $address = get_post_meta($post_id, 'adres', true);

                if (!empty($address)) {
                    $coords = $this->geocode_address($address);

                    if ($coords) {
                        $lat = $coords['lat'];
                        $lng = $coords['lng'];

                        // Zapisz współrzędne
                        update_post_meta($post_id, 'club_latitude', $lat);
                        update_post_meta($post_id, 'club_longitude', $lng);
                    }
                }
            }

            // Dodaj klub do listy
            if (!empty($lat) && !empty($lng)) {
                $clubs[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'lat' => floatval($lat),
                    'lng' => floatval($lng),
                    'url' => get_permalink($post_id),
                    'excerpt' => wp_trim_words(get_the_excerpt($post_id), 15),
                ];
            }
        }

        if (empty($clubs)) {
            wp_send_json_error(['message' => 'Brak klubów z współrzędnymi']);
            return;
        }

        wp_send_json_success($clubs);
    }

    /**
     * Geocodowanie adresu przez Mapbox API
     */
    private function geocode_address($address) {
        if (empty($address)) {
            return null;
        }

        // Sprawdź cache
        $cache_key = 'letsfight_geocode_' . md5($address);
        $cached = get_transient($cache_key);

        if ($cached) {
            return $cached;
        }

        // Wywołaj API Mapbox
        $address_encoded = urlencode($address);
        $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/{$address_encoded}.json?access_token={$this->mapbox_token}&country=pl&limit=1";

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            error_log('LetsFight Mapa - Błąd geocodowania: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['features'][0]['center'])) {
            $coords = [
                'lng' => floatval($body['features'][0]['center'][0]),
                'lat' => floatval($body['features'][0]['center'][1]),
            ];

            // Zapisz w cache na 30 dni
            set_transient($cache_key, $coords, 30 * DAY_IN_SECONDS);

            return $coords;
        }

        return null;
    }
}

// Inicjalizacja wtyczki
function letsfight_mapa_init() {
    return LetsFight_Mapa::get_instance();
}

add_action('plugins_loaded', 'letsfight_mapa_init');
