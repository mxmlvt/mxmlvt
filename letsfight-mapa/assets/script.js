/**
 * Let's Fight - Mapa Klubów
 * JavaScript dla wyszukiwarki z integracją Mapbox i JetEngine
 * @version 1.4.2
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
     * Przeniesienie istniejącego listingu z Elementor widget do naszego kontenera
     * FIX: Zamiast klonować (co powoduje duplikaty), przenosimy istniejący listing
     */
    function moveExistingListing() {
        console.log('[LetsFight Mapa] ========== PRZENOSZENIE LISTINGU ==========');

        const $targetContainer = $('.letsfight-view--lista');
        const listingId = $targetContainer.data('listing-id');

        if (!listingId) {
            console.error('[LetsFight Mapa] ❌ Brak data-listing-id w kontenerze .letsfight-view--lista');
            return;
        }

        console.log('[LetsFight Mapa] Szukam istniejącego listingu o ID:', listingId);

        // Szukaj istniejącego listingu JetEngine na stronie
        // Format klasy: .jet-listing-grid--{ID}
        const $existingListing = $(`.jet-listing-grid--${listingId}`).first();

        if ($existingListing.length === 0) {
            console.error('[LetsFight Mapa] ❌ Nie znaleziono istniejącego listingu .jet-listing-grid--' + listingId + ' na stronie!');
            console.log('[LetsFight Mapa] Sprawdzam czy listing jest gdziekolwiek na stronie...');

            // Sprawdź wszystkie listingi na stronie
            const allListings = $('[class*="jet-listing-grid--"]');
            console.log('[LetsFight Mapa] Znaleziono listingi:', allListings.length);
            allListings.each(function(index) {
                console.log('[LetsFight Mapa]   - Listing', index + ':', this.className);
            });

            $targetContainer.find('.letsfight-listing-placeholder').html(
                '<div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 8px;">' +
                '<strong>⚠️ Nie można załadować klubów</strong><br>' +
                'Listing JetEngine o ID <code>' + listingId + '</code> nie został znaleziony na stronie.<br>' +
                '<small>Upewnij się, że listing jest dodany na stronie przez widget Elementor.</small>' +
                '</div>'
            );
            return;
        }

        console.log('[LetsFight Mapa] ✅ Znaleziono istniejący listing:', {
            html_length: $existingListing.html().length,
            items_count: $existingListing.find('.jet-listing-grid__item').length
        });

        // KLUCZOWE: PRZENIEŚ (nie klonuj!) listing do naszego kontenera
        // To rozwiązuje problem duplikacji - listing będzie tylko raz na stronie
        $targetContainer.find('.letsfight-listing-placeholder').remove();
        $existingListing.detach().appendTo($targetContainer);

        const itemsCount = $existingListing.find('.jet-listing-grid__item').length;
        console.log('[LetsFight Mapa] ✅ Listing przeniesiony pomyślnie! Liczba itemów:', itemsCount);

        // Aktualizuj licznik klubów
        $('.letsfight-counter__number').text(itemsCount);

        // Log dla debugowania - jakie elementy mamy w itemach (żeby filtry wiedziały co szukać)
        if (DEBUG && itemsCount > 0) {
            const $firstItem = $existingListing.find('.jet-listing-grid__item').first();
            console.log('[LetsFight Mapa] Struktura pierwszego itemu (dla debugowania filtrów):', {
                classes: $firstItem.attr('class'),
                html_sample: $firstItem.html().substring(0, 200) + '...'
            });
        }
    }

    /**
     * Inicjalizacja przy załadowaniu DOM
     */
    $(document).ready(function() {
        console.log('[LetsFight Mapa] ========== INIT START ==========');
        console.log('[LetsFight Mapa] DEBUG mode:', DEBUG);

        if ($('.letsfight-wyszukiwarka').length === 0) {
            console.error('[LetsFight Mapa] ❌ Nie znaleziono kontenera .letsfight-wyszukiwarka');
            return;
        }
        console.log('[LetsFight Mapa] ✅ Kontener .letsfight-wyszukiwarka znaleziony');

        // Sprawdź czy Mapbox jest załadowany
        if (typeof mapboxgl === 'undefined') {
            console.error('[LetsFight Mapa] ❌ Mapbox GL JS nie jest załadowany!');
            return;
        }
        console.log('[LetsFight Mapa] ✅ Mapbox GL JS załadowany');

        // Sprawdź czy mamy token
        if (!letsfightMap || !letsfightMap.mapboxToken) {
            console.error('[LetsFight Mapa] ❌ Brak tokenu Mapbox!', letsfightMap);
            return;
        }
        console.log('[LetsFight Mapa] ✅ Token Mapbox dostępny:', letsfightMap.mapboxToken.substring(0, 10) + '...');

        // Sprawdź czy #letsfight-map istnieje
        const mapContainer = document.getElementById('letsfight-map');
        if (mapContainer) {
            console.log('[LetsFight Mapa] ✅ Container #letsfight-map istnieje:', {
                width: mapContainer.offsetWidth,
                height: mapContainer.offsetHeight,
                display: window.getComputedStyle(mapContainer).display,
                visibility: window.getComputedStyle(mapContainer).visibility
            });
        } else {
            console.warn('[LetsFight Mapa] ⚠️ Container #letsfight-map NIE ISTNIEJE w DOM!');
        }

        debugLog('Inicjalizacja wtyczki');

        // KRYTYCZNE: Przenieś istniejący listing z Elementor widget do naszego kontenera
        moveExistingListing();

        initWyszukiwarka();
        console.log('[LetsFight Mapa] ========== INIT COMPLETE ==========');
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

        // Własne filtry (bez JetSmartFilters)
        initCustomFilters();

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
        console.log('[LetsFight Mapa] initToggleView() - liczba przycisków:', $('.letsfight-toggle__btn').length);

        $('.letsfight-toggle__btn').on('click', function() {
            const view = $(this).data('view');
            console.log('[LetsFight Mapa] ========== TOGGLE CLICKED: ' + view + ' ==========');
            debugLog('Przełączanie widoku na:', view);

            // Zmień aktywny przycisk
            $('.letsfight-toggle__btn').removeClass('letsfight-toggle__btn--active');
            $(this).addClass('letsfight-toggle__btn--active');

            // Zmień aktywny widok
            $('.letsfight-view').removeClass('letsfight-view--active');
            $(`.letsfight-view--${view}`).addClass('letsfight-view--active');

            console.log('[LetsFight Mapa] Widok zmieniony na:', view);

            // Obsługa mapy
            if (view === 'mapa') {
                console.log('[LetsFight Mapa] Widok MAPA - isMapInitialized:', isMapInitialized);

                if (!isMapInitialized) {
                    console.log('[LetsFight Mapa] Wywołanie initMap()...');
                    initMap();
                } else {
                    console.log('[LetsFight Mapa] Mapa już zainicjalizowana - resize + update markerów');
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

        // Zmiana miasta - OD RAZU przekierowuje bez przycisku
        $select.on('change', function() {
            const currentSlug = $(this).data('current-slug');
            const newSlug = $(this).val();
            const baseUrl = '/trenuj/';

            // Przekieruj tylko jeśli wybrano inne miasto niż aktualne
            if (newSlug !== currentSlug) {
                console.log('[LetsFight Mapa] Przekierowanie do miasta:', newSlug);

                if (newSlug) {
                    window.location.href = baseUrl + newSlug + '/';
                } else {
                    window.location.href = baseUrl;
                }
            }
        });
    }

    /**
     * Inicjalizacja własnych filtrów (bez JetSmartFilters)
     */
    function initCustomFilters() {
        debugLog('Inicjalizacja własnych filtrów');

        // Filtr dyscypliny
        $('.letsfight-dyscyplina-select').on('change', function() {
            debugLog('Zmiana dyscypliny:', $(this).val());
            filterClubs();
        });

        // Wyszukiwanie
        let searchTimeout;
        $('.letsfight-search-input').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val();
            searchTimeout = setTimeout(function() {
                debugLog('Wyszukiwanie:', query);
                filterClubs();
            }, 300);
        });

        // Przycisk reset
        $('.letsfight-btn--reset').on('click', function() {
            debugLog('Reset filtrów');
            $('.letsfight-dyscyplina-select').val('');
            $('.letsfight-search-input').val('');
            filterClubs();
        });
    }

    /**
     * Filtrowanie klubów (bez AJAX)
     */
    function filterClubs() {
        const dyscyplina = $('.letsfight-dyscyplina-select').val().toLowerCase();
        const searchQuery = $('.letsfight-search-input').val().toLowerCase();

        console.log('[LetsFight Mapa] ========== FILTROWANIE ==========');
        console.log('[LetsFight Mapa] Dyscyplina:', dyscyplina || '(wszystkie)');
        console.log('[LetsFight Mapa] Wyszukiwanie:', searchQuery || '(brak)');

        const $allItems = $('.jet-listing-grid__item');
        console.log('[LetsFight Mapa] Liczba itemów do przeszukania:', $allItems.length);

        // DEBUG: Pokaż strukturę pierwszego itemu
        if (DEBUG && $allItems.length > 0 && dyscyplina) {
            const $firstItem = $allItems.first();
            console.log('[LetsFight Mapa] 🔍 DEBUG - Struktura pierwszego itemu:');
            console.log('[LetsFight Mapa]   Klasy:', $firstItem.attr('class'));
            console.log('[LetsFight Mapa]   Linki z term-*:', $firstItem.find('a[class*="term-"]').map(function() {
                return $(this).attr('class');
            }).get());
            console.log('[LetsFight Mapa]   Elementy .dyscypliny:', $firstItem.find('.dyscypliny, [class*="dyscyplin"]').map(function() {
                return {class: $(this).attr('class'), text: $(this).text()};
            }).get());
            console.log('[LetsFight Mapa]   Linki /dyscypliny/:', $firstItem.find('a[href*="/dyscypliny/"]').map(function() {
                return $(this).attr('href');
            }).get());
            console.log('[LetsFight Mapa]   Linki .jet-listing-dynamic-terms__link:', $firstItem.find('.jet-listing-dynamic-terms__link').map(function() {
                return {class: $(this).attr('class'), text: $(this).text(), href: $(this).attr('href')};
            }).get());
        }

        let visibleCount = 0;

        $allItems.each(function(itemIndex) {
            const $item = $(this);
            let visible = true;
            const postClasses = $item.attr('class') || '';
            const postIdMatch = postClasses.match(/jet-listing-dynamic-post-(\d+)/);
            const postId = postIdMatch ? postIdMatch[1] : 'unknown';

            // Filtr dyscypliny - ULEPSZONE WYSZUKIWANIE
            if (dyscyplina) {
                let hasDyscyplina = false;

                // METODA 1: Sprawdź klasy CSS (JetEngine często dodaje class="term-{slug}")
                const itemClasses = $item.attr('class') || '';
                if (itemClasses.indexOf('term-' + dyscyplina) !== -1) {
                    hasDyscyplina = true;
                    debugLog('  → Znaleziono przez klasę item: term-' + dyscyplina);
                }

                // METODA 2: Sprawdź klasy CSS w linkach/spanach z taxonomiami
                if (!hasDyscyplina) {
                    $item.find('a[class*="term-"], span[class*="term-"]').each(function() {
                        const termClasses = $(this).attr('class') || '';
                        if (termClasses.indexOf('term-' + dyscyplina) !== -1) {
                            hasDyscyplina = true;
                            debugLog('  → Znaleziono przez klasę link: term-' + dyscyplina);
                            return false; // break
                        }
                    });
                }

                // METODA 3: Sprawdź tekst w elementach z klasą "dyscypliny" lub podobną
                if (!hasDyscyplina) {
                    const $dyscyplinyElements = $item.find('.dyscypliny, [class*="dyscyplin"], .jet-listing-dynamic-terms__link, .jet-listing-dynamic-field__content');

                    debugLog('  🔍 METODA 3 - znaleziono elementów:', $dyscyplinyElements.length);

                    $dyscyplinyElements.each(function(index) {
                        const termText = $(this).text().toLowerCase().trim();

                        debugLog('    Element', index + ':', termText.substring(0, 50) + (termText.length > 50 ? '...' : ''));

                        // Sprawdź czy to lista dyscyplin rozdzielona przecinkami
                        if (termText.indexOf(',') !== -1) {
                            // Parse comma-separated disciplines
                            const disciplines = termText.split(',').map(d => d.trim());
                            const disciplineSlugs = disciplines.map(d => d.replace(/\s+/g, '-'));

                            debugLog('      → Zawiera przecinki! Disciplines:', disciplines);
                            debugLog('      → Slugs:', disciplineSlugs);
                            debugLog('      → Szukamy:', dyscyplina);

                            if (disciplines.indexOf(dyscyplina) !== -1 || disciplineSlugs.indexOf(dyscyplina) !== -1) {
                                hasDyscyplina = true;
                                debugLog('  ✅ Znaleziono przez tekst (comma-separated):', dyscyplina, 'w', termText);
                                return false; // break
                            } else {
                                debugLog('      ❌ Nie znaleziono w tym elemencie');
                            }
                        } else {
                            // Single discipline - exact match
                            const termSlug = termText.replace(/\s+/g, '-');
                            if (termText === dyscyplina || termSlug === dyscyplina) {
                                hasDyscyplina = true;
                                debugLog('  ✅ Znaleziono przez tekst:', termText);
                                return false; // break
                            }
                        }
                    });
                }

                // METODA 4: Sprawdź slug w href linków
                if (!hasDyscyplina) {
                    $item.find('a[href*="/dyscypliny/"]').each(function() {
                        const href = $(this).attr('href') || '';
                        if (href.indexOf('/' + dyscyplina + '/') !== -1 || href.indexOf('/' + dyscyplina) !== -1) {
                            hasDyscyplina = true;
                            debugLog('  → Znaleziono przez href:', href);
                            return false; // break
                        }
                    });
                }

                if (!hasDyscyplina) {
                    visible = false;
                    console.log('[LetsFight Mapa] ❌ Post', postId, '- nie ma dyscypliny:', dyscyplina);
                } else {
                    console.log('[LetsFight Mapa] ✅ Post', postId, '- MA dyscyplinę:', dyscyplina);
                }
            }

            // Filtr wyszukiwania (tytuł + treść)
            if (visible && searchQuery) {
                const itemText = $item.text().toLowerCase();
                if (itemText.indexOf(searchQuery) === -1) {
                    visible = false;
                    console.log('[LetsFight Mapa] ❌ Post', postId, '- nie pasuje do wyszukiwania:', searchQuery);
                }
            }

            // Pokaż/ukryj - używamy CSS class z !important
            console.log('[LetsFight Mapa] 🎬 Post', postId, '- visible =', visible);
            if (visible) {
                $item.removeClass('letsfight-item-hidden');
                visibleCount++;
                console.log('[LetsFight Mapa] 👁️ Post', postId, '- POKAZANO (removeClass)');
            } else {
                $item.addClass('letsfight-item-hidden');
                console.log('[LetsFight Mapa] 🙈 Post', postId, '- UKRYTO (addClass)');
            }
        });

        // Aktualizuj licznik
        $('.letsfight-counter__number').text(visibleCount);

        console.log('[LetsFight Mapa] ✅ Widocznych klubów:', visibleCount);

        // Aktualizuj mapę jeśli aktywna
        if (isMapInitialized && map) {
            setTimeout(updateMapMarkers, 300);
        }
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
        debugLog('========== INICJALIZACJA MAPY ==========');

        // Sprawdź container
        const container = document.getElementById('letsfight-map');
        if (!container) {
            console.error('[LetsFight Mapa] BŁĄD: Nie znaleziono kontenera #letsfight-map!');
            return;
        }

        debugLog('Container znaleziony:', {
            width: container.offsetWidth,
            height: container.offsetHeight,
            visible: container.offsetParent !== null
        });

        // Sprawdź czy mapa już istnieje
        if (isMapInitialized && map) {
            debugLog('Mapa już zainicjalizowana');
            return;
        }

        try {
            // Walidacja tokenu Mapbox
            const token = letsfightMap.mapboxToken;
            if (!token || !token.startsWith('pk.')) {
                console.error('[LetsFight Mapa] ❌ BŁĄD: Nieprawidłowy token Mapbox!', {
                    token_exists: !!token,
                    token_preview: token ? token.substring(0, 10) + '...' : 'BRAK',
                    expected_format: 'pk.ey...'
                });
                showMapError('Błąd konfiguracji: Nieprawidłowy token Mapbox. Skontaktuj się z administratorem.');
                return;
            }

            // Ustaw token
            mapboxgl.accessToken = token;
            console.log('[LetsFight Mapa] ✅ Token Mapbox ustawiony:', token.substring(0, 20) + '...');

            // Współrzędne centrum dla Polski (Warszawa)
            const centerLat = 52.2297;
            const centerLng = 21.0122;

            debugLog('Tworzenie mapy...', {center: [centerLng, centerLat], zoom: 6});

            // Stwórz mapę
            map = new mapboxgl.Map({
                container: 'letsfight-map',
                style: 'mapbox://styles/mapbox/dark-v11',
                center: [centerLng, centerLat],
                zoom: 6,
                pitch: 0,
                bearing: 0,
                attributionControl: true
            });

            debugLog('Mapa stworzona, dodawanie kontrolek...');

            // Dodaj kontrolki
            map.addControl(new mapboxgl.NavigationControl(), 'top-right');
            map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

            debugLog('Kontrolki dodane, czekam na załadowanie mapy...');

            // Event po załadowaniu mapy
            map.on('load', function() {
                console.log('[LetsFight Mapa] ✅ Mapa Mapbox załadowana poprawnie!');
                isMapInitialized = true;
                updateMapMarkers();
            });

            // Rozszerzona obsługa błędów
            map.on('error', function(e) {
                console.error('[LetsFight Mapa] ❌ Błąd mapy - szczegóły:', {
                    error: e.error,
                    error_message: e.error ? e.error.message : 'brak',
                    error_status: e.error ? e.error.status : 'brak',
                    sourceId: e.sourceId,
                    full_event: e
                });

                // Mapbox error codes
                if (e.error) {
                    const errorMsg = e.error.message || '';
                    const errorStatus = e.error.status;

                    if (errorStatus === 401) {
                        showMapError('Błąd autoryzacji Mapbox (401): Token może być nieprawidłowy lub wygasły. Sprawdź token w ustawieniach.');
                    } else if (errorStatus === 403) {
                        showMapError('Błąd dostępu Mapbox (403): Token nie ma wymaganych uprawnień.');
                    } else if (errorMsg.includes('token')) {
                        showMapError('Błąd tokenu Mapbox: ' + errorMsg);
                    } else {
                        showMapError('Błąd mapy: ' + errorMsg);
                    }
                }
            });

            // Dodatkowy listener dla stylów
            map.on('style.load', function() {
                console.log('[LetsFight Mapa] ✅ Styl mapy załadowany');
            });

        } catch (error) {
            console.error('[LetsFight Mapa] ❌ Nie można zainicjalizować mapy:', error);
            console.error('[LetsFight Mapa] Error name:', error.name);
            console.error('[LetsFight Mapa] Error message:', error.message);
            console.error('[LetsFight Mapa] Stack trace:', error.stack);
            showMapError('Nie można załadować mapy: ' + error.message);
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
                // Stwórz własny marker z etykietą (pin + nazwa + przycisk)
                const el = createMarkerElementWithLabel(club);

                // Dodaj marker do mapy (bez popup - etykieta jest na stałe widoczna)
                const marker = new mapboxgl.Marker({
                    element: el,
                    anchor: 'bottom' // Pin wskazuje na lokalizację
                })
                    .setLngLat([club.lng, club.lat])
                    .addTo(map);

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
     * Stwórz element markera z etykietą (pin + nazwa + przycisk)
     */
    function createMarkerElementWithLabel(club) {
        // Kontener dla całego markera
        const container = document.createElement('div');
        container.className = 'letsfight-marker-container';

        // Pin (pinezka)
        const pin = document.createElement('div');
        pin.className = 'letsfight-marker-pin';
        pin.style.backgroundImage = 'url(data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCAzMCA0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTUgMEMxMC44NiAwIDcuNSAzLjM2IDcuNSA3LjVjMCAxLjkzIDEuMjkgMy42IDMuMDcgNC4xNEwxNSAzNS43MWw0LjQzLTI0LjA3QzIxLjIxIDExLjEgMjIuNSA5LjQzIDIyLjUgNy41IDIyLjUgMy4zNiAxOS4xNCAwIDE1IDB6bTAgMTBjLTEuMzggMC0yLjUtMS4xMi0yLjUtMi41UzEzLjYyIDUgMTUgNXMyLjUgMS4xMiAyLjUgMi41UzE2LjM4IDEwIDE1IDEweiIgZmlsbD0iI2Y3OTcxNiIvPjwvc3ZnPg==)';
        pin.style.width = '30px';
        pin.style.height = '40px';
        pin.style.backgroundSize = 'contain';
        pin.style.backgroundRepeat = 'no-repeat';

        // Etykieta (zdjęcie + wszystkie dane + przycisk)
        const label = document.createElement('div');
        label.className = 'letsfight-marker-label';

        const imageHTML = club.thumbnail
            ? `<img src="${club.thumbnail}" alt="${escapeHtml(club.title)}" class="letsfight-marker-label__img">`
            : '';

        const adresHTML = club.adres
            ? `<div class="letsfight-marker-label__adres">${escapeHtml(club.adres)}</div>`
            : '';

        const dyscyplinyHTML = club.dyscypliny
            ? `<div class="letsfight-marker-label__dyscypliny">${escapeHtml(club.dyscypliny)}</div>`
            : '';

        const poziomyHTML = club.poziomy
            ? `<div class="letsfight-marker-label__poziomy">${escapeHtml(club.poziomy)}</div>`
            : '';

        label.innerHTML = `
            ${imageHTML}
            <div class="letsfight-marker-label__name">${escapeHtml(club.title)}</div>
            <div class="letsfight-marker-label__stars">★★★★★</div>
            ${adresHTML}
            ${dyscyplinyHTML}
            ${poziomyHTML}
            <a href="${club.url}" class="letsfight-marker-label__btn" onclick="event.stopPropagation();">
                Sprawdź
            </a>
        `;

        container.appendChild(pin);
        container.appendChild(label);

        return container;
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

        console.log('[LetsFight Mapa] ========== getVisiblePostIds START ==========');

        // Szukaj itemów w kontenerze listy (NIE :visible, bo widok może być ukryty)
        const $items = $('.letsfight-view--lista .jet-listing-grid__item');
        console.log('[LetsFight Mapa] Znaleziono itemów w kontenerze:', $items.length);

        $items.each(function(index) {
            const $item = $(this);

            // Sprawdź czy item NIE jest ukryty przez filtry (fadeOut dodaje display:none inline)
            const isHiddenByFilter = $item.css('display') === 'none';

            // JetEngine dodaje klasę: jet-listing-dynamic-post-{ID}
            const classes = $item.attr('class') || '';
            const match = classes.match(/jet-listing-dynamic-post-(\d+)/);

            if (match && match[1]) {
                const postId = parseInt(match[1], 10);

                if (!isHiddenByFilter) {
                    postIds.push(postId);
                    console.log('[LetsFight Mapa]   ✅ Item', index, '- post ID:', postId, '(widoczny)');
                } else {
                    console.log('[LetsFight Mapa]   ⊘ Item', index, '- post ID:', postId, '(ukryty przez filtr)');
                }
            } else {
                console.error('[LetsFight Mapa]   ❌ Item', index, '- BRAK post ID w klasach:', classes);
            }
        });

        console.log('[LetsFight Mapa] ✅ getVisiblePostIds - znaleziono:', postIds.length, 'widocznych klubów');
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
