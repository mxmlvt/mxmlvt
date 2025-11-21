/**
 * Let's Fight - Mapa Klubów
 * JavaScript dla wyszukiwarki z integracją Mapbox i JetEngine
 * @version 1.1.0
 */

(function($) {
    'use strict';

    // Zmienne globalne
    let map = null;
    let markers = [];
    let isMapInitialized = false;
    const DEBUG = window.letsfightMap && letsfightMap.debug;

    /**
     * Logowanie debug
     */
    function debugLog(message, data) {
        if (DEBUG) {
            console.log('[LetsFight Mapa]', message, data || '');
        }
    }

    /**
     * Inicjalizacja przy załadowaniu DOM
     */
    $(document).ready(function() {
        if ($('.letsfight-wyszukiwarka').length === 0) {
            debugLog('Nie znaleziono kontenera wyszukiwarki');
            return;
        }

        // Sprawdź czy Mapbox jest załadowany
        if (typeof mapboxgl === 'undefined') {
            console.error('[LetsFight Mapa] Mapbox GL JS nie jest załadowany!');
            return;
        }

        // Sprawdź czy mamy token
        if (!letsfightMap || !letsfightMap.mapboxToken) {
            console.error('[LetsFight Mapa] Brak tokenu Mapbox!');
            return;
        }

        debugLog('Inicjalizacja wtyczki');
        initWyszukiwarka();
    });

    /**
     * Inicjalizacja głównej wyszukiwarki
     */
    function initWyszukiwarka() {
        const $wrapper = $('.letsfight-wyszukiwarka');

        debugLog('Inicjalizacja komponentów UI');

        // Toggle Lista/Mapa
        initToggleView();

        // Filtr miasta
        initCityFilter();

        // Nasłuchuj na AJAX JetEngine
        initJetEngineListener();

        // Initial counter
        updateCounter();

        debugLog('Wtyczka zainicjalizowana poprawnie');
    }

    /**
     * Inicjalizacja przełącznika Lista/Mapa
     */
    function initToggleView() {
        $('.letsfight-toggle__btn').on('click', function() {
            const view = $(this).data('view');
            debugLog('Przełączanie widoku na:', view);

            // Zmień aktywny przycisk
            $('.letsfight-toggle__btn').removeClass('letsfight-toggle__btn--active');
            $(this).addClass('letsfight-toggle__btn--active');

            // Zmień aktywny widok
            $('.letsfight-view').removeClass('letsfight-view--active');
            $(`.letsfight-view--${view}`).addClass('letsfight-view--active');

            // Obsługa mapy
            if (view === 'mapa') {
                if (!isMapInitialized) {
                    initMap();
                } else {
                    // Odśwież rozmiar mapy i markery
                    setTimeout(function() {
                        if (map) {
                            map.resize();
                            updateMapMarkers();
                        }
                    }, 100);
                }
            }
        });
    }

    /**
     * Inicjalizacja filtra miasta
     */
    function initCityFilter() {
        const $select = $('.letsfight-miasto-select');
        const $searchBtn = $('.letsfight-btn--search');

        // Zmiana miasta - pokazuje przycisk "Szukaj"
        $select.on('change', function() {
            const currentSlug = $(this).data('current-slug');
            const newSlug = $(this).val();

            if (newSlug !== currentSlug) {
                $searchBtn.fadeIn(200);
            } else {
                $searchBtn.fadeOut(200);
            }
        });

        // Przycisk Szukaj - redirect do nowego miasta
        $searchBtn.on('click', function() {
            const newSlug = $select.val();
            const baseUrl = '/trenuj/';

            if (newSlug) {
                debugLog('Przekierowanie do miasta:', newSlug);
                window.location.href = baseUrl + newSlug + '/';
            } else {
                window.location.href = baseUrl;
            }
        });
    }

    /**
     * Inicjalizacja nasłuchiwania na AJAX JetEngine
     */
    function initJetEngineListener() {
        // Event wywoływany przez JetSmartFilters po zakończeniu AJAX
        $(document).on('jet-filter-content-rendered', function(event, response) {
            debugLog('JetEngine AJAX - odświeżono listing', response);

            // Aktualizuj licznik
            updateCounter();

            // Jeśli mapa jest aktywna, zaktualizuj markery
            if (isMapInitialized && map) {
                updateMapMarkers();
            }
        });

        // Dodatkowy event dla bezpośrednich zmian w JetEngine
        $(document).on('jet-engine-request-calendar', function() {
            debugLog('JetEngine - zmiana danych');
            updateCounter();
        });
    }

    /**
     * Inicjalizacja mapy Mapbox
     */
    function initMap() {
        debugLog('Rozpoczęcie inicjalizacji mapy Mapbox');

        // Sprawdź czy mapa już istnieje
        if (isMapInitialized && map) {
            debugLog('Mapa już zainicjalizowana');
            return;
        }

        try {
            // Ustaw token
            mapboxgl.accessToken = letsfightMap.mapboxToken;

            // Współrzędne centrum dla Polski (Warszawa)
            const centerLat = 52.2297;
            const centerLng = 21.0122;

            // Stwórz mapę
            map = new mapboxgl.Map({
                container: 'letsfight-map',
                style: 'mapbox://styles/mapbox/dark-v11',
                center: [centerLng, centerLat],
                zoom: 6,
                pitch: 0,
                bearing: 0
            });

            // Dodaj kontrolki
            map.addControl(new mapboxgl.NavigationControl(), 'top-right');
            map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

            // Event po załadowaniu mapy
            map.on('load', function() {
                debugLog('Mapa Mapbox załadowana');
                isMapInitialized = true;
                updateMapMarkers();
            });

            // Obsługa błędów
            map.on('error', function(e) {
                console.error('[LetsFight Mapa] Błąd mapy:', e);
            });

        } catch (error) {
            console.error('[LetsFight Mapa] Nie można zainicjalizować mapy:', error);
            showMapError('Nie można załadować mapy. Spróbuj odświeżyć stronę.');
        }
    }

    /**
     * Aktualizacja markerów na mapie
     */
    function updateMapMarkers() {
        if (!map || !isMapInitialized) {
            debugLog('Mapa nie jest gotowa do aktualizacji markerów');
            return;
        }

        debugLog('Rozpoczęcie aktualizacji markerów');

        // Usuń stare markery
        clearMarkers();

        // Pobierz ID postów z listingu
        const postIds = getVisiblePostIds();

        debugLog('Znaleziono postów:', postIds.length);

        if (postIds.length === 0) {
            debugLog('Brak postów do wyświetlenia');
            showMapMessage('Brak klubów do wyświetlenia na mapie');
            return;
        }

        // Pokaż loading
        showMapLoading(true);

        // AJAX - pobierz współrzędne klubów
        $.ajax({
            url: letsfightMap.ajaxurl,
            type: 'POST',
            data: {
                action: 'letsfight_get_clubs_coords',
                post_ids: postIds,
                nonce: letsfightMap.nonce
            },
            success: function(response) {
                showMapLoading(false);

                if (response.success && response.data && response.data.length > 0) {
                    debugLog('Otrzymano współrzędne klubów:', response.data.length);
                    renderMarkers(response.data);
                } else {
                    const message = response.data && response.data.message
                        ? response.data.message
                        : 'Brak klubów z współrzędnymi';
                    showMapMessage(message);
                }
            },
            error: function(xhr, status, error) {
                showMapLoading(false);
                console.error('[LetsFight Mapa] Błąd AJAX:', error, xhr);
                showMapError('Nie można pobrać lokalizacji klubów');
            }
        });
    }

    /**
     * Renderowanie markerów na mapie
     */
    function renderMarkers(clubs) {
        if (!clubs || clubs.length === 0) return;

        const bounds = new mapboxgl.LngLatBounds();

        clubs.forEach(function(club) {
            try {
                // Stwórz własny marker (SVG pin)
                const el = createMarkerElement();

                // Popup z informacjami
                const popup = createMarkerPopup(club);

                // Dodaj marker do mapy
                const marker = new mapboxgl.Marker(el)
                    .setLngLat([club.lng, club.lat])
                    .setPopup(popup)
                    .addTo(map);

                // Kliknięcie markera → scroll do karty
                el.addEventListener('click', function() {
                    scrollToClub(club.id);
                });

                markers.push(marker);
                bounds.extend([club.lng, club.lat]);

            } catch (error) {
                console.error('[LetsFight Mapa] Błąd przy dodawaniu markera:', error, club);
            }
        });

        // Dopasuj widok mapy do wszystkich markerów
        if (clubs.length > 1) {
            map.fitBounds(bounds, {
                padding: {top: 50, bottom: 50, left: 50, right: 50},
                maxZoom: 14,
                duration: 1000
            });
        } else if (clubs.length === 1) {
            map.flyTo({
                center: [clubs[0].lng, clubs[0].lat],
                zoom: 13,
                duration: 1000
            });
        }

        debugLog('Renderowano markerów:', markers.length);
    }

    /**
     * Stwórz element markera
     */
    function createMarkerElement() {
        const el = document.createElement('div');
        el.className = 'letsfight-marker';
        el.style.backgroundImage = 'url(data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCAzMCA0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTUgMEMxMC44NiAwIDcuNSAzLjM2IDcuNSA3LjVjMCAxLjkzIDEuMjkgMy42IDMuMDcgNC4xNEwxNSAzNS43MWw0LjQzLTI0LjA3QzIxLjIxIDExLjEgMjIuNSA5LjQzIDIyLjUgNy41IDIyLjUgMy4zNiAxOS4xNCAwIDE1IDB6bTAgMTBjLTEuMzggMC0yLjUtMS4xMi0yLjUtMi41UzEzLjYyIDUgMTUgNXMyLjUgMS4xMiAyLjUgMi41UzE2LjM4IDEwIDE1IDEweiIgZmlsbD0iI2Y3OTcxNiIvPjwvc3ZnPg==)';
        el.style.width = '30px';
        el.style.height = '40px';
        el.style.cursor = 'pointer';
        el.style.backgroundSize = 'contain';
        el.style.backgroundRepeat = 'no-repeat';
        return el;
    }

    /**
     * Stwórz popup dla markera
     */
    function createMarkerPopup(club) {
        const popupContent = `
            <div class="letsfight-popup">
                <h3>${escapeHtml(club.title)}</h3>
                ${club.excerpt ? '<p>' + escapeHtml(club.excerpt) + '</p>' : ''}
                <a href="${club.url}" class="letsfight-popup__link">Zobacz szczegóły →</a>
            </div>
        `;

        return new mapboxgl.Popup({
            offset: 25,
            closeButton: true,
            closeOnClick: true,
            maxWidth: '300px'
        }).setHTML(popupContent);
    }

    /**
     * Usuń wszystkie markery z mapy
     */
    function clearMarkers() {
        markers.forEach(function(marker) {
            marker.remove();
        });
        markers = [];
        debugLog('Usunięto wszystkie markery');
    }

    /**
     * Pobierz ID widocznych postów
     */
    function getVisiblePostIds() {
        const postIds = [];

        $('.jet-listing-grid__item').each(function() {
            const postId = $(this).data('post-id');
            if (postId) {
                postIds.push(postId);
            }
        });

        return postIds;
    }

    /**
     * Aktualizacja licznika klubów
     */
    function updateCounter() {
        // Poczekaj na zakończenie animacji/AJAX
        setTimeout(function() {
            const count = $('.jet-listing-grid__item').length;
            $('.letsfight-counter__number').text(count);
            debugLog('Zaktualizowano licznik klubów:', count);
        }, 100);
    }

    /**
     * Scroll do karty klubu i highlight
     */
    function scrollToClub(postId) {
        debugLog('Scroll do klubu:', postId);

        // Przełącz na widok listy
        $('.letsfight-toggle__btn[data-view="lista"]').trigger('click');

        // Znajdź kartę i scroll z opóźnieniem
        setTimeout(function() {
            const $card = $(`.jet-listing-grid__item[data-post-id="${postId}"]`);

            if ($card.length) {
                // Scroll do karty
                $('html, body').animate({
                    scrollTop: $card.offset().top - 120
                }, 600, 'swing');

                // Dodaj efekt highlight
                $card.addClass('jet-listing-grid__item--highlighted');

                setTimeout(function() {
                    $card.removeClass('jet-listing-grid__item--highlighted');
                }, 2000);

                debugLog('Przewinięto do klubu:', postId);
            } else {
                console.warn('[LetsFight Mapa] Nie znaleziono karty klubu:', postId);
            }
        }, 300);
    }

    /**
     * Pokaż loading na mapie
     */
    function showMapLoading(show) {
        const $mapView = $('.letsfight-view--mapa');

        if (show) {
            $mapView.addClass('loading');
        } else {
            $mapView.removeClass('loading');
        }
    }

    /**
     * Pokaż wiadomość na mapie
     */
    function showMapMessage(message) {
        debugLog('Wiadomość na mapie:', message);
        // Można dodać overlay z wiadomością
    }

    /**
     * Pokaż błąd na mapie
     */
    function showMapError(message) {
        console.error('[LetsFight Mapa]', message);
        // Można dodać overlay z błędem
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Eksportuj funkcje dla debugowania (tylko w trybie debug)
    if (DEBUG) {
        window.letsfightMapDebug = {
            updateMarkers: updateMapMarkers,
            updateCounter: updateCounter,
            getPostIds: getVisiblePostIds,
            map: function() { return map; },
            markers: function() { return markers; }
        };
    }

})(jQuery);
