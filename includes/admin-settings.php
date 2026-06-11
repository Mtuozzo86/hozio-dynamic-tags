<?php

// ─── Search-replace protection for ALL Hozio Pro settings ────────────────────
// Every user-editable setting on the Hozio Pro page is stored base64-encoded
// in wp_options (with a "b64:" prefix for strings or "b64arr:" prefix for
// arrays) so that site-wide search/replace operations (Better Search Replace,
// WP-CLI search-replace, Velvet Blues, hosting-panel migrate tools, etc.)
// can't match against the values and accidentally mangle them.
//
// Reads are decoded transparently via the matching option_* filter — every
// shortcode, Elementor dynamic tag, admin field display, and any other
// get_option() consumer sees plain text. The encoding is only visible to
// raw SQL or table dumps.
//
// IMPORTANT: A domain migration that updates URLs via search/replace will
// NOT update any URL stored in these fields. After such a migration,
// re-save the Hozio Pro Dynamic Tags Settings page once.

/** Plain-string options to protect. */
function hozio_protected_string_options() {
    return array(
        // Contact info
        'hozio_company_phone_1',
        'hozio_company_phone_2',
        'hozio_google_ads_phone',
        'hozio_sms_phone',
        'hozio_company_email',
        'hozio_to_email_contact_form',
        // Address
        'hozio_company_address',
        'hozio_address_street',
        'hozio_address_town',
        'hozio_address_state',
        'hozio_address_zip',
        // Business details
        'hozio_business_hours',
        'hozio_start_year',
        // Social / review URLs
        'hozio_yelp_url',
        'hozio_youtube_url',
        'hozio_angies_list_url',
        'hozio_home_advisor_url',
        'hozio_bbb_url',
        'hozio_facebook_url',
        'hozio_instagram_url',
        'hozio_twitter_url',
        'hozio_tiktok_url',
        'hozio_linkedin_url',
        'hozio_gmb_link',
        // Colors
        'hozio_nav_text_color',
        'hozio_sitemap_link_color',
        'hozio_sitemap_link_hover_color',
    );
}

/** Array-typed options to protect (stored serialized + encoded). */
function hozio_protected_array_options() {
    return array(
        'hozio_business_hours_classic',
        'hozio_custom_tags',
    );
}

/** Encode a string for storage. Idempotent — returns already-encoded values unchanged. */
function hozio_encode_string_for_storage( $value ) {
    if ( ! is_string( $value ) || $value === '' ) return $value;
    if ( strpos( $value, 'b64:' ) === 0 )         return $value;
    return 'b64:' . base64_encode( $value );
}

/** Decode a stored string back to plain text. Returns non-encoded values unchanged. */
function hozio_decode_string_for_display( $value ) {
    if ( is_string( $value ) && strpos( $value, 'b64:' ) === 0 ) {
        $decoded = base64_decode( substr( $value, 4 ), true );
        return $decoded === false ? $value : $decoded;
    }
    return $value;
}

/** Encode an array for storage (serialize + base64 + prefix). */
function hozio_encode_array_for_storage( $value ) {
    if ( ! is_array( $value ) || empty( $value ) ) return $value;
    return 'b64arr:' . base64_encode( serialize( $value ) );
}

/** Decode a stored array back to its original structure. */
function hozio_decode_array_for_display( $value ) {
    if ( is_string( $value ) && strpos( $value, 'b64arr:' ) === 0 ) {
        $payload = base64_decode( substr( $value, 7 ), true );
        if ( $payload === false ) return $value;
        $arr = @unserialize( $payload );
        return is_array( $arr ) ? $arr : $value;
    }
    return $value;
}

// Register encode/decode filters for every protected string option.
foreach ( hozio_protected_string_options() as $hozio_protected_opt ) {
    add_filter( "pre_update_option_{$hozio_protected_opt}", 'hozio_encode_string_for_storage' );
    add_filter( "option_{$hozio_protected_opt}",            'hozio_decode_string_for_display' );
    add_filter( "default_option_{$hozio_protected_opt}",    'hozio_decode_string_for_display' );
}

// Register encode/decode filters for every protected array option.
foreach ( hozio_protected_array_options() as $hozio_protected_opt ) {
    add_filter( "pre_update_option_{$hozio_protected_opt}", 'hozio_encode_array_for_storage' );
    add_filter( "option_{$hozio_protected_opt}",            'hozio_decode_array_for_display' );
    add_filter( "default_option_{$hozio_protected_opt}",    'hozio_decode_array_for_display' );
}

// Custom tags are user-defined at runtime, so register their option filters
// dynamically based on the current custom-tag list.
add_action( 'plugins_loaded', function() {
    $tags = get_option( 'hozio_custom_tags', array() );
    if ( ! is_array( $tags ) ) return;
    foreach ( $tags as $tag ) {
        if ( empty( $tag['value'] ) ) continue;
        $opt = 'hozio_' . sanitize_key( $tag['value'] );
        add_filter( "pre_update_option_{$opt}", 'hozio_encode_string_for_storage' );
        add_filter( "option_{$opt}",            'hozio_decode_string_for_display' );
        add_filter( "default_option_{$opt}",    'hozio_decode_string_for_display' );
    }
}, 1 );

// One-time migration: encode existing plain-text values across every protected
// option. Runs once per site (idempotent, safe to re-run).
add_action( 'admin_init', function() {
    if ( get_option( '_hozio_settings_encoded_v2', '0' ) === '1' ) {
        return;
    }

    foreach ( hozio_protected_string_options() as $opt ) {
        $current = get_option( $opt, '' );
        if ( is_string( $current ) && $current !== '' ) {
            update_option( $opt, $current );
        }
    }

    foreach ( hozio_protected_array_options() as $opt ) {
        $current = get_option( $opt, null );
        if ( is_array( $current ) && ! empty( $current ) ) {
            update_option( $opt, $current );
        }
    }

    $custom_tags_for_migration = get_option( 'hozio_custom_tags', array() );
    if ( is_array( $custom_tags_for_migration ) ) {
        foreach ( $custom_tags_for_migration as $tag ) {
            if ( empty( $tag['value'] ) ) continue;
            $opt = 'hozio_' . sanitize_key( $tag['value'] );
            $current = get_option( $opt, '' );
            if ( is_string( $current ) && $current !== '' ) {
                update_option( $opt, $current );
            }
        }
    }

    update_option( '_hozio_settings_encoded_v2', '1' );
}, 5 );

// Enqueue custom admin styles and scripts with INLINE styles as backup
function hozio_dynamic_tags_admin_assets($hook) {
    // Check if we're on the right page
    if (strpos($hook, 'hozio_dynamic_tags') === false) {
        return;
    }
    
    // Enqueue WordPress color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    
    // Try to enqueue external files
    $plugin_dir = plugin_dir_url(__FILE__);
    
    // Enqueue styles
    wp_enqueue_style('hozio-admin-styles', $plugin_dir . 'assets/admin-styles.css', [], time());
    
    // Enqueue scripts
    wp_enqueue_script('hozio-admin-script', $plugin_dir . 'assets/admin-script.js', ['jquery', 'wp-color-picker'], time(), true);
    
    // BACKUP: Add inline styles if external CSS fails to load
    add_action('admin_head', 'hozio_dynamic_tags_inline_styles');
    
    // Add color picker initialization
    add_action('admin_footer', 'hozio_color_picker_init');
}
add_action('admin_enqueue_scripts', 'hozio_dynamic_tags_admin_assets', 999);

// Initialize color picker
function hozio_color_picker_init() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Init color pickers — Nav Text Color updates its swatch on change
        var $navColor = $('#hozio_nav_text_color');

        function paintNavSwatch(color) {
            var $btn = $('.hozio-nav-color-card .wp-color-result.button');
            if (!$btn.length) return;
            $btn[0].style.setProperty('--hozio-swatch-color', color || '#ffffff');
        }

        if ($navColor.length) {
            $navColor.wpColorPicker({
                change: function(event, ui) { paintNavSwatch(ui.color.toString()); },
                clear:  function()           { paintNavSwatch(''); }
            });
            // Initial paint based on saved value
            paintNavSwatch($navColor.val());
        }
        // Init all color pickers except nav (handled above) and hst-cp (handled below)
        $('.hozio-color-picker').not('#hozio_nav_text_color').not('.hst-cp').wpColorPicker();

        // Init service-towns color pickers with live swatch updates
        $('.hst-cp').each(function() {
            var $inp = $(this);
            $inp.wpColorPicker({
                change: function(e, ui) {
                    $inp.closest('.hst-color-row').find('.hst-swatch').css('background', ui.color.toString());
                },
                clear: function() {
                    var def = $inp.data('default-color') || '';
                    $inp.closest('.hst-color-row').find('.hst-swatch').css('background', def);
                }
            });
        });

        // Per-field reset button
        $(document).on('click', '.hst-reset-btn', function() {
            var id  = $(this).data('target');
            var def = $(this).data('default');
            $('#' + id).wpColorPicker('color', def);
            $('#' + id).closest('.hst-color-row').find('.hst-swatch').css('background', def);
        });

        // Reset-all button for the Service Towns section
        $(document).on('click', '.hst-reset-all-btn', function() {
            $(this).closest('.hozio-section').find('.hst-reset-btn').each(function() {
                var id  = $(this).data('target');
                var def = $(this).data('default');
                $('#' + id).wpColorPicker('color', def);
                $('#' + id).closest('.hst-color-row').find('.hst-swatch').css('background', def);
            });
        });

        // Live address builder — updates Company Address textarea as user types
        (function() {
            var streetEl = document.querySelector('[name="hozio_address_street"]');
            var townEl   = document.querySelector('[name="hozio_address_town"]');
            var stateEl  = document.querySelector('[name="hozio_address_state"]');
            var zipEl    = document.querySelector('[name="hozio_address_zip"]');
            var output   = document.getElementById('hozio-addr-output');
            var badge    = document.getElementById('hozio-addr-badge');
            var desc     = document.getElementById('hozio-addr-desc');
            var clearBtn = document.getElementById('hozio-addr-clear');
            if (!streetEl || !output) return;

            // Capture the pre-existing address so we can restore it when all fields are cleared
            var originalAddr = output.value;

            function rebuild() {
                var street = streetEl.value.trim();
                var town   = townEl   ? townEl.value.trim()  : '';
                var state  = stateEl  ? stateEl.value.trim() : '';
                var zip    = zipEl    ? zipEl.value.trim()   : '';
                var hasAny = street || town || state || zip;

                if (hasAny) {
                    var line2 = town;
                    if (state) line2 += (line2 ? ', ' : '') + state;
                    if (zip)   line2 += (line2 ? ' '  : '') + zip;
                    output.value    = street + (line2 ? '<br>' + line2 : '');
                    output.readOnly = true;
                    output.classList.add('hozio-addr-auto');
                    if (badge) badge.style.display = 'inline-flex';
                    if (desc)  desc.textContent = 'Formatted from the fields above. Clear all address fields to edit manually.';
                } else {
                    output.value = originalAddr;
                    output.readOnly = false;
                    output.classList.remove('hozio-addr-auto');
                    if (badge) badge.style.display = 'none';
                    if (desc)  desc.textContent = 'HTML tags allowed. Fill Street / Town / State / ZIP above to auto-build this field.';
                }
            }

            // Run on page load so existing saved values trigger readonly state immediately
            rebuild();

            [streetEl, townEl, stateEl, zipEl].forEach(function(el) {
                if (el) el.addEventListener('input', rebuild);
            });

            // Clear All: wipe the 4 structured fields and restore original address
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    [streetEl, townEl, stateEl, zipEl].forEach(function(el) {
                        if (el) el.value = '';
                    });
                    // Also reset the state custom dropdown UI
                    var stateDisplay = document.getElementById('hozio-state-display');
                    var stateHidden  = document.getElementById('hozio-state-hidden');
                    if (stateDisplay) stateDisplay.value = '';
                    if (stateHidden)  stateHidden.value = '';
                    var stateOpts = document.querySelectorAll('.hozio-state-option-selected');
                    for (var i = 0; i < stateOpts.length; i++) {
                        stateOpts[i].classList.remove('hozio-state-option-selected');
                    }
                    rebuild();
                });
            }
        })();

        // Custom State Dropdown — always shows all states, dedicated search, keyboard nav
        (function() {
            var wrap     = document.getElementById('hozio-state-combo-wrap');
            var display  = document.getElementById('hozio-state-display');
            var dropdown = document.getElementById('hozio-state-dropdown');
            var search   = document.getElementById('hozio-state-search-input');
            var optsEl   = document.getElementById('hozio-state-options');
            var hidden   = document.getElementById('hozio-state-hidden');
            var fmtChk   = document.getElementById('hozio-state-fmt-chk');
            var fmtHid   = document.getElementById('hozio-state-fmt-hidden');
            if (!wrap || !display || !hidden || !optsEl) return;

            var states = {
                'AL':'Alabama','AK':'Alaska','AZ':'Arizona','AR':'Arkansas',
                'CA':'California','CO':'Colorado','CT':'Connecticut','DE':'Delaware',
                'FL':'Florida','GA':'Georgia','HI':'Hawaii','ID':'Idaho',
                'IL':'Illinois','IN':'Indiana','IA':'Iowa','KS':'Kansas',
                'KY':'Kentucky','LA':'Louisiana','ME':'Maine','MD':'Maryland',
                'MA':'Massachusetts','MI':'Michigan','MN':'Minnesota','MS':'Mississippi',
                'MO':'Missouri','MT':'Montana','NE':'Nebraska','NV':'Nevada',
                'NH':'New Hampshire','NJ':'New Jersey','NM':'New Mexico','NY':'New York',
                'NC':'North Carolina','ND':'North Dakota','OH':'Ohio','OK':'Oklahoma',
                'OR':'Oregon','PA':'Pennsylvania','RI':'Rhode Island','SC':'South Carolina',
                'SD':'South Dakota','TN':'Tennessee','TX':'Texas','UT':'Utah',
                'VT':'Vermont','VA':'Virginia','WA':'Washington','WV':'West Virginia',
                'WI':'Wisconsin','WY':'Wyoming'
            };
            var options = optsEl.querySelectorAll('.hozio-state-option');
            var noneEl  = null;
            var activeIdx = -1;

            function isFull() { return fmtChk && fmtChk.checked; }

            function buildDisplay(abbr) {
                if (!abbr || !states[abbr]) return '';
                return abbr + ' \u2014 ' + states[abbr];
            }

            function getSelectedAbbr() {
                for (var i = 0; i < options.length; i++) {
                    if (options[i].classList.contains('hozio-state-option-selected')) return options[i].dataset.abbr;
                }
                return '';
            }

            function setSelection(abbr) {
                hidden.value = abbr ? (isFull() ? states[abbr] : abbr) : '';
                if (fmtHid) fmtHid.value = isFull() ? 'full' : 'abbr';
                display.value = buildDisplay(abbr);
                for (var i = 0; i < options.length; i++) {
                    options[i].classList.toggle('hozio-state-option-selected', options[i].dataset.abbr === abbr);
                }
                hidden.dispatchEvent(new Event('input'));
            }

            function clearActive() {
                for (var i = 0; i < options.length; i++) options[i].classList.remove('hozio-state-option-active');
            }

            function getVisibleOptions() {
                var visible = [];
                for (var i = 0; i < options.length; i++) {
                    if (!options[i].hidden) visible.push(options[i]);
                }
                return visible;
            }

            function setActive(idx) {
                clearActive();
                var visible = getVisibleOptions();
                if (idx < 0 || idx >= visible.length) { activeIdx = -1; return; }
                activeIdx = idx;
                visible[idx].classList.add('hozio-state-option-active');
                visible[idx].scrollIntoView({ block: 'nearest' });
            }

            function filterOptions(q) {
                q = (q || '').trim().toLowerCase();
                var anyVisible = false;
                for (var i = 0; i < options.length; i++) {
                    var abbr = options[i].dataset.abbr.toLowerCase();
                    var name = options[i].dataset.name.toLowerCase();
                    var match = !q || abbr.indexOf(q) === 0 || name.indexOf(q) !== -1;
                    options[i].hidden = !match;
                    if (match) anyVisible = true;
                }
                if (!anyVisible) {
                    if (!noneEl) {
                        noneEl = document.createElement('div');
                        noneEl.className = 'hozio-state-option-none';
                        noneEl.textContent = 'No states match your search';
                        optsEl.appendChild(noneEl);
                    }
                    noneEl.hidden = false;
                } else if (noneEl) {
                    noneEl.hidden = true;
                }
            }

            function openDropdown() {
                dropdown.hidden = false;
                wrap.classList.add('open');
                if (search) {
                    search.value = '';
                    filterOptions('');
                    setTimeout(function() { search.focus(); }, 30);
                }
                activeIdx = -1;
                clearActive();
                var sel = optsEl.querySelector('.hozio-state-option-selected');
                if (sel) setTimeout(function() { sel.scrollIntoView({ block: 'center' }); }, 30);
            }

            function closeDropdown() {
                dropdown.hidden = true;
                wrap.classList.remove('open');
                activeIdx = -1;
                clearActive();
            }

            display.addEventListener('click', function() {
                if (dropdown.hidden) openDropdown(); else closeDropdown();
            });

            optsEl.addEventListener('click', function(e) {
                var opt = e.target.closest('.hozio-state-option');
                if (!opt) return;
                setSelection(opt.dataset.abbr);
                closeDropdown();
            });

            if (search) {
                search.addEventListener('input', function(e) {
                    filterOptions(e.target.value);
                    // Auto-highlight the first visible match so Enter selects it
                    var visible = getVisibleOptions();
                    if (e.target.value.trim() && visible.length > 0) {
                        setActive(0);
                    } else {
                        activeIdx = -1;
                        clearActive();
                    }
                });
                search.addEventListener('keydown', function(e) {
                    var visible = getVisibleOptions();
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setActive(activeIdx < 0 ? 0 : Math.min(activeIdx + 1, visible.length - 1));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setActive(Math.max(activeIdx - 1, 0));
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (activeIdx >= 0 && visible[activeIdx]) {
                            setSelection(visible[activeIdx].dataset.abbr);
                            closeDropdown();
                        }
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeDropdown();
                    }
                });
            }

            document.addEventListener('click', function(e) {
                if (!wrap.contains(e.target) && !dropdown.hidden) closeDropdown();
            });

            if (fmtChk) {
                fmtChk.addEventListener('change', function() {
                    var current = getSelectedAbbr();
                    if (current) setSelection(current);
                    else if (fmtHid) fmtHid.value = isFull() ? 'full' : 'abbr';
                });
            }

            // Initialize display value from saved hidden value on page load
            var saved = hidden.value;
            if (saved) {
                var initAbbr = states[saved] ? saved : '';
                if (!initAbbr) {
                    for (var a in states) { if (states[a] === saved) { initAbbr = a; break; } }
                }
                if (initAbbr) display.value = buildDisplay(initAbbr);
            }
        })();

        // Street address autocomplete via Photon (free OSM-based, no API key)
        (function() {
            var streetInput = document.querySelector('[name="hozio_address_street"]');
            if (!streetInput) return;
            var streetGroup = streetInput.closest('.hozio-input-group');
            if (!streetGroup) return;

            // Mount the dropdown inside the input-group (which is already position: relative)
            var dropdown = document.createElement('div');
            dropdown.className = 'hozio-addr-autocomplete';
            dropdown.hidden = true;
            streetGroup.appendChild(dropdown);

            var townInput  = document.querySelector('[name="hozio_address_town"]');
            var zipInput   = document.querySelector('[name="hozio_address_zip"]');

            var debounceId = null;
            var activeFetch = null;
            var currentResults = [];
            var activeIdx = -1;

            function clearChildren(el) {
                while (el.firstChild) el.removeChild(el.firstChild);
            }

            function showLoading() {
                clearChildren(dropdown);
                var wrap = document.createElement('div');
                wrap.className = 'hozio-addr-loading';
                var spin = document.createElement('span');
                spin.className = 'dashicons dashicons-update';
                wrap.appendChild(spin);
                wrap.appendChild(document.createTextNode(' Searching addresses…'));
                dropdown.appendChild(wrap);
                dropdown.hidden = false;
            }
            function showEmpty() {
                clearChildren(dropdown);
                var wrap = document.createElement('div');
                wrap.className = 'hozio-addr-empty';
                wrap.textContent = 'No matches — keep typing or fill in the fields manually';
                dropdown.appendChild(wrap);
                dropdown.hidden = false;
            }
            function hideDropdown() {
                dropdown.hidden = true;
                currentResults = [];
                activeIdx = -1;
            }

            function fetchSuggestions(query) {
                if (activeFetch && activeFetch.abort) try { activeFetch.abort(); } catch(e) {}
                showLoading();
                var url = 'https://photon.komoot.io/api/?q=' + encodeURIComponent(query) + '&limit=8&lang=en';
                activeFetch = new AbortController();
                fetch(url, { signal: activeFetch.signal })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var results = (data && data.features) ? data.features : [];
                        results = results.filter(function(f) {
                            var p = f.properties || {};
                            return p.countrycode === 'US' || p.country === 'United States';
                        });
                        renderResults(results);
                    })
                    .catch(function(err) {
                        if (err && err.name === 'AbortError') return;
                        hideDropdown();
                    });
            }

            function buildSuggestionEl(r, i) {
                var p = r.properties || {};
                var line1 = '';
                if (p.housenumber) line1 += p.housenumber + ' ';
                if (p.street)      line1 += p.street;
                if (!line1)        line1 = p.name || '';
                var parts = [];
                if (p.city)     parts.push(p.city);
                if (p.state)    parts.push(p.state);
                if (p.postcode) parts.push(p.postcode);

                var item = document.createElement('div');
                item.className = 'hozio-addr-suggestion';
                item.setAttribute('data-idx', String(i));
                var l1 = document.createElement('div');
                l1.className = 'hozio-addr-suggestion-line1';
                l1.textContent = line1;
                var l2 = document.createElement('div');
                l2.className = 'hozio-addr-suggestion-line2';
                l2.textContent = parts.join(', ');
                item.appendChild(l1);
                item.appendChild(l2);
                return item;
            }

            function renderResults(results) {
                currentResults = results;
                activeIdx = -1;
                clearChildren(dropdown);
                if (!results.length) { showEmpty(); return; }
                results.forEach(function(r, i) {
                    dropdown.appendChild(buildSuggestionEl(r, i));
                });
                var credit = document.createElement('div');
                credit.className = 'hozio-addr-credit';
                credit.textContent = 'Powered by Photon · OpenStreetMap';
                dropdown.appendChild(credit);
                dropdown.hidden = false;
            }

            function setActive(idx) {
                var items = dropdown.querySelectorAll('.hozio-addr-suggestion');
                for (var i = 0; i < items.length; i++) items[i].classList.remove('hozio-addr-active');
                if (idx < 0 || idx >= items.length) { activeIdx = -1; return; }
                activeIdx = idx;
                items[idx].classList.add('hozio-addr-active');
                items[idx].scrollIntoView({ block: 'nearest' });
            }

            function applyResult(idx) {
                var r = currentResults[idx];
                if (!r) return;
                var p = r.properties || {};

                var street = '';
                if (p.housenumber) street += p.housenumber + ' ';
                if (p.street)      street += p.street;
                if (!street.trim() && p.name) street = p.name;
                streetInput.value = street.trim();

                if (townInput && p.city)     townInput.value = p.city;
                if (zipInput  && p.postcode) zipInput.value  = p.postcode;

                // State: find matching option by data-name and trigger its click handler
                if (p.state) {
                    var allOpts = document.querySelectorAll('.hozio-state-option');
                    for (var i = 0; i < allOpts.length; i++) {
                        if (allOpts[i].getAttribute('data-name') === p.state) {
                            allOpts[i].click();
                            break;
                        }
                    }
                }

                hideDropdown();

                streetInput.dispatchEvent(new Event('input', { bubbles: true }));
                if (townInput) townInput.dispatchEvent(new Event('input', { bubbles: true }));
                if (zipInput)  zipInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            streetInput.addEventListener('input', function() {
                clearTimeout(debounceId);
                var q = streetInput.value.trim();
                if (q.length < 3) { hideDropdown(); return; }
                debounceId = setTimeout(function() { fetchSuggestions(q); }, 350);
            });

            dropdown.addEventListener('click', function(e) {
                var item = e.target.closest('.hozio-addr-suggestion');
                if (!item) return;
                applyResult(parseInt(item.getAttribute('data-idx'), 10));
            });

            streetInput.addEventListener('keydown', function(e) {
                if (dropdown.hidden) return;
                var items = dropdown.querySelectorAll('.hozio-addr-suggestion');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    setActive(activeIdx < items.length - 1 ? activeIdx + 1 : 0);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    setActive(activeIdx > 0 ? activeIdx - 1 : items.length - 1);
                } else if (e.key === 'Enter' && activeIdx >= 0) {
                    e.preventDefault();
                    applyResult(activeIdx);
                } else if (e.key === 'Escape') {
                    hideDropdown();
                }
            });

            document.addEventListener('click', function(e) {
                if (!streetGroup.contains(e.target) && !dropdown.hidden) hideDropdown();
            });
        })();

        // Business Hours: mode toggle (HTML / Classic) + per-day status + 24/7 master + apply-Mon
        (function() {
            var modeBtns   = document.querySelectorAll('.hozio-bh-mode-btn');
            var modeInput  = document.getElementById('hozio-bh-mode-input');
            var htmlView   = document.querySelector('.hozio-bh-html-view');
            var classicView= document.querySelector('.hozio-bh-classic-view');
            if (!modeBtns.length || !modeInput) return;

            // Mode toggle
            modeBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var mode = btn.getAttribute('data-mode');
                    modeBtns.forEach(function(b) { b.classList.toggle('hozio-bh-mode-active', b === btn); });
                    modeInput.value = mode;
                    if (htmlView)    htmlView.hidden    = (mode !== 'html');
                    if (classicView) classicView.hidden = (mode !== 'classic');
                });
            });

            // 24/7 master toggle dims/disables day rows
            var chk247 = document.getElementById('hozio-bh-247-chk');
            if (chk247 && classicView) {
                var apply247 = function() {
                    classicView.classList.toggle('is-247', chk247.checked);
                };
                chk247.addEventListener('change', apply247);
                apply247();
            }

            // Per-day status pills
            document.querySelectorAll('.hozio-bh-day').forEach(function(row) {
                var statusInput = row.querySelector('.hozio-bh-day-status-input');
                row.querySelectorAll('.hozio-bh-status-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var st = btn.getAttribute('data-status');
                        row.querySelectorAll('.hozio-bh-status-btn').forEach(function(b) {
                            b.classList.toggle('is-active', b === btn);
                        });
                        if (statusInput) statusInput.value = st;
                        row.classList.toggle('is-closed', st === 'closed');
                    });
                });
            });

            // Apply Monday's hours to Tue–Fri
            var applyBtn = document.getElementById('hozio-bh-apply-weekdays');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    var monRow = document.querySelector('.hozio-bh-day[data-day="monday"]');
                    if (!monRow) return;
                    var monStatus = monRow.querySelector('.hozio-bh-day-status-input').value;
                    var monOpen   = monRow.querySelector('select[name*="[open]"]').value;
                    var monClose  = monRow.querySelector('select[name*="[close]"]').value;
                    ['tuesday','wednesday','thursday','friday'].forEach(function(dayKey) {
                        var row = document.querySelector('.hozio-bh-day[data-day="' + dayKey + '"]');
                        if (!row) return;
                        // Set status
                        row.querySelectorAll('.hozio-bh-status-btn').forEach(function(b) {
                            b.classList.toggle('is-active', b.getAttribute('data-status') === monStatus);
                        });
                        row.querySelector('.hozio-bh-day-status-input').value = monStatus;
                        row.classList.toggle('is-closed', monStatus === 'closed');
                        // Set times
                        row.querySelector('select[name*="[open]"]').value  = monOpen;
                        row.querySelector('select[name*="[close]"]').value = monClose;
                    });
                });
            }
        })();

        // Live "Years of Experience" calculation as the start year is typed
        (function() {
            var yearInput = document.getElementById('hozio_start_year');
            var yearsOut  = document.querySelector('.hozio-years-num');
            if (!yearInput || !yearsOut) return;

            function recalc() {
                var startYear = parseInt(yearInput.value, 10);
                var current   = new Date().getFullYear();
                var years     = (startYear && startYear >= 1900 && startYear <= current) ? (current - startYear) : 0;
                yearsOut.textContent = years;
            }

            yearInput.addEventListener('input', recalc);
            recalc();
        })();

        // Contact Form Email(s) — validate format before submit, block save if invalid
        (function() {
            var $emailField = $('[name="hozio_to_email_contact_form"]');
            if ($emailField.length === 0) return;
            var emailRegex = /^[^\s@,]+@[^\s@,]+\.[^\s@,]+$/;

            function clearError() {
                $emailField.removeClass('hozio-input-invalid');
                $('.hozio-email-inline-error').remove();
            }

            function validate() {
                var raw = ($emailField.val() || '').trim();
                if (raw === '') return { ok: true, invalid: [] };
                var parts = raw.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                var bad = [];
                parts.forEach(function(em) {
                    if (!emailRegex.test(em)) bad.push(em);
                });
                return { ok: bad.length === 0, invalid: bad };
            }

            // Block submit if invalid
            $emailField.closest('form').on('submit', function(e) {
                var result = validate();
                if (result.ok) { clearError(); return; }
                e.preventDefault();
                clearError();
                $emailField.addClass('hozio-input-invalid');
                var chips = result.invalid.map(function(s) {
                    return '<code>' + $('<div>').text(s).html() + '</code>';
                }).join(', ');
                var $err = $(
                    '<div class="hozio-email-inline-error" role="alert">' +
                        '<span class="dashicons dashicons-warning"></span> ' +
                        '<strong>Invalid email format:</strong> ' + chips + '. ' +
                        'Use commas to separate multiple addresses (e.g. <code>support@hozio.com, sales@hozio.com</code>).' +
                    '</div>'
                );
                // Insert below the input group
                var $anchor = $emailField.closest('.hozio-input-group').length
                    ? $emailField.closest('.hozio-input-group')
                    : $emailField;
                $anchor.after($err);
                $('html, body').animate({ scrollTop: $emailField.offset().top - 100 }, 250);
                $emailField.trigger('focus');
            });

            // Clear error state as user types/edits
            $emailField.on('input', clearError);
        })();

        // Copy shortcode to clipboard
        $(document).on('click', '.hozio-copy-shortcode', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var shortcode = $btn.data('shortcode');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shortcode).then(function() {
                    $btn.addClass('copied');
                    setTimeout(function() { $btn.removeClass('copied'); }, 1600);
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<textarea>').val(shortcode).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
                $btn.addClass('copied');
                setTimeout(function() { $btn.removeClass('copied'); }, 1600);
            }
        });
    });
    </script>
    <?php
}

// Inline styles as backup (ensures styling always works)
function hozio_dynamic_tags_inline_styles() {
    ?>
    <style>
        /* Critical Hozio Styles - Inline Backup */
        :root {
            --hozio-blue: #00A0E3;
            --hozio-blue-dark: #0081B8;
            --hozio-green: #8DC63F;
            --hozio-orange: #F7941D;
        }
        
        .hozio-settings-wrapper {
            background: #f9fafb;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
        }

        /* Email validation error banner */
        .hozio-email-error-banner {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin: 16px 0 0;
            padding: 16px 20px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #dc2626;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
        }
        .hozio-email-error-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hozio-email-error-icon .dashicons {
            color: white;
            font-size: 24px;
            width: 24px;
            height: 24px;
        }
        .hozio-email-error-content {
            flex: 1;
            min-width: 0;
        }
        .hozio-email-error-content strong {
            display: block;
            font-size: 15px;
            color: #7f1d1d;
            margin-bottom: 4px;
        }
        .hozio-email-error-content p {
            margin: 4px 0 0;
            color: #991b1b;
            font-size: 13px;
            line-height: 1.5;
        }
        .hozio-email-error-content code {
            display: inline-block;
            background: white;
            color: #dc2626;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin: 0 2px;
            border: 1px solid #fecaca;
        }
        .hozio-email-error-hint {
            font-size: 12px !important;
            color: #b91c1c !important;
            font-style: italic;
        }

        /* Invalid input field state — used by inline JS validation */
        .hozio-input.hozio-input-invalid,
        .hozio-input.hozio-input-invalid:focus {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18) !important;
        }
        .hozio-email-inline-error {
            margin-top: 6px;
            padding: 8px 12px;
            background: #fee2e2;
            border-left: 3px solid #dc2626;
            border-radius: 4px;
            color: #991b1b;
            font-size: 12px;
            line-height: 1.5;
        }
        .hozio-email-inline-error .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 2px;
        }
        .hozio-email-inline-error code {
            background: white;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .hozio-header {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
            color: white;
            padding: 40px;
            border-radius: 8px 8px 0 0;
        }
        
        .hozio-logo-wrapper {
            margin-bottom: 20px;
        }
        
        .hozio-logo-wrapper img {
            height: 50px;
            width: auto;
        }
        
        .hozio-header-content h1 {
            color: white !important;
            font-size: 32px;
            margin: 0 0 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hozio-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
        }
        
        .hozio-content {
            padding: 0 40px 40px;
            margin-top: -30px;
        }
        
        .hozio-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-blue);
        }
        
        .hozio-section:nth-child(2) {
            border-left-color: var(--hozio-green);
        }
        
        .hozio-section:nth-child(3) {
            border-left-color: var(--hozio-orange);
        }
        
        .hozio-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .hozio-section-header h2 {
            margin: 0 !important;
            font-size: 20px;
            color: #1f2937;
        }
        
        .hozio-section-header .dashicons {
            color: var(--hozio-blue);
            font-size: 24px;
        }
        
        .hozio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .hozio-grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
        
        .hozio-field {
            display: flex;
            flex-direction: column;
        }
        
        .hozio-field label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .hozio-input-group {
            position: relative;
        }
        
        .hozio-input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--hozio-blue);
            display: flex;
            align-items: center;
        }
        
        .hozio-input,
        .hozio-textarea,
        .hozio-input-number {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        
        .hozio-input-group .hozio-input {
            padding-left: 40px;
        }
        
        .hozio-input-number {
            padding-left: 12px;
        }
        
        .hozio-textarea {
            padding-left: 12px;
            min-height: 100px;
            resize: vertical;
        }
        
        .hozio-input:focus,
        .hozio-textarea:focus,
        .hozio-input-number:focus {
            outline: none;
            border-color: var(--hozio-blue);
            box-shadow: 0 0 0 3px rgba(0, 160, 227, 0.1);
        }
        
        .hozio-color-picker-wrapper {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .hozio-color-picker {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .hozio-color-input {
            width: 60px;
            height: 44px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .hozio-calculated-value {
            margin-top: 12px;
            padding: 12px 16px;
            background: linear-gradient(135deg, rgba(0, 160, 227, 0.1) 0%, rgba(141, 198, 63, 0.1) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hozio-calculated-value .highlight {
            color: var(--hozio-orange);
            font-weight: 600;
            font-size: 16px;
        }
        
        .hozio-field-description {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
            font-style: italic;
        }
        
        .hozio-submit-wrapper {
            display: flex;
            gap: 12px;
            padding: 24px 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .hozio-submit-btn {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%) !important;
            border: none !important;
            color: white !important;
            padding: 12px 32px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            height: auto !important;
            text-shadow: none !important;
            box-shadow: 0 4px 6px rgba(0, 160, 227, 0.3) !important;
        }
        
        .hozio-submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .hozio-reset-btn {
            padding: 12px 24px !important;
            border: 2px solid var(--hozio-orange) !important;
            background: white !important;
            color: var(--hozio-orange) !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            height: auto !important;
        }
        
        .hozio-reset-btn:hover {
            background: var(--hozio-orange) !important;
            color: white !important;
        }
        
        /* Toggle Switch Styles */
        .hozio-toggle-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hozio-toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 32px;
        }
        
        .hozio-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .hozio-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 32px;
        }
        
        .hozio-toggle-slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .hozio-toggle-slider {
            background-color: var(--hozio-blue);
        }
        
        input:checked + .hozio-toggle-slider:before {
            transform: translateX(28px);
        }
        
        .hozio-toggle-label {
            font-weight: 500;
            color: #1f2937;
        }
        
        /* HTML Sitemap Subsection Styles */
        .hozio-subsection {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .hozio-subsection-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 16px 0;
        }
        
        .hozio-subsection-title .dashicons {
            color: var(--hozio-blue);
            font-size: 20px;
        }
        
        .hozio-info-text {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #6b7280;
            margin: 0 0 24px 0;
            font-size: 14px;
            padding: 12px 16px;
            background: #f9fafb;
            border-radius: 6px;
            border-left: 3px solid var(--hozio-blue);
            line-height: 1.6;
        }
        
        .hozio-info-text .dashicons {
            color: var(--hozio-blue);
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        /* Color Picker Field Styles */
        .hozio-color-field {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .hozio-color-input-wrapper {
            flex: 0 0 auto;
        }
        
        .hozio-field-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .hozio-color-info {
            flex: 1;
        }
        
        .hozio-color-info .hozio-field-description {
            margin-top: 0;
            margin-left: 0;
        }
        
        .wp-picker-container {
            margin-top: 0;
        }
        
        /* Copy Shortcode Button */
        .hozio-copy-shortcode {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            padding: 1px 6px;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            color: #9ca3af;
            transition: all 0.15s ease;
        }

        .hozio-copy-shortcode:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #6b7280;
        }

        .hozio-copy-shortcode code {
            background: none;
            padding: 0;
            font-size: 10.5px;
            color: inherit;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        .hozio-copy-shortcode .hozio-copy-icon,
        .hozio-copy-shortcode .hozio-check-icon {
            width: 11px;
            height: 11px;
            flex-shrink: 0;
        }

        .hozio-copy-shortcode .hozio-check-icon {
            display: none;
        }

        .hozio-copy-shortcode.copied {
            color: #059669;
        }

        .hozio-copy-shortcode.copied .hozio-copy-icon {
            display: none;
        }

        .hozio-copy-shortcode.copied .hozio-check-icon {
            display: block;
        }

        /* Structured address grid */
        .hozio-address-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr 0.65fr 0.65fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        /* Read-only company address display (when structured fields filled) */
        .hozio-address-readonly {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            color: #374151;
            font-size: 14px;
            line-height: 1.7;
        }
        .hozio-address-readonly .hozio-lock-icon {
            color: #9ca3af;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .hozio-address-readonly-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 500;
            color: #6b7280;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 2px 8px;
            margin-left: 8px;
            vertical-align: middle;
        }

        /* Business Details bottom — 3 cards in one row */
        .hozio-biz-bottom {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 14px;
            margin-top: 24px;
            align-items: stretch;
        }
        .hozio-biz-card {
            background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px 20px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .hozio-biz-card:hover {
            border-color: #cfe9f5;
            box-shadow: 0 2px 10px rgba(0,160,227,0.08);
        }
        .hozio-biz-card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .hozio-biz-card-header .dashicons {
            color: var(--hozio-blue);
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        .hozio-biz-card-header h3 {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        /* Bulletproof full-width input — beats WP admin defaults via !important */
        .hozio-biz-input {
            width: 100% !important;
            max-width: none !important;
            display: block !important;
            box-sizing: border-box !important;
            padding: 12px 14px !important;
            border: 2px solid #e5e7eb !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            font-family: inherit !important;
            background: white !important;
            transition: border-color 0.2s, box-shadow 0.2s;
            margin: 0 !important;
        }
        .hozio-biz-input:focus {
            outline: none !important;
            border-color: var(--hozio-blue) !important;
            box-shadow: 0 0 0 3px rgba(0,160,227,0.1) !important;
        }
        textarea.hozio-biz-input {
            min-height: 130px !important;
            resize: vertical !important;
            line-height: 1.6 !important;
        }
        .hozio-biz-row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .hozio-biz-year-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: linear-gradient(135deg, rgba(0,160,227,0.08), rgba(141,198,63,0.08));
            border: 1px solid rgba(0,160,227,0.15);
            border-radius: 8px;
            font-size: 13px;
            color: #374151;
            margin-top: 10px;
        }
        .hozio-biz-year-badge .dashicons {
            color: var(--hozio-orange);
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .hozio-biz-year-badge .hozio-years-num {
            color: var(--hozio-orange);
            font-weight: 700;
            font-size: 16px;
        }
        .hozio-biz-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
            min-height: 24px;
        }
        .hozio-biz-card-help {
            font-size: 11px;
            color: #9ca3af;
            font-style: italic;
        }

        /* Business Hours mode toggle (HTML / Classic) */
        .hozio-bh-mode-toggle {
            display: inline-flex;
            margin-left: auto;
            background: #f3f4f6;
            border-radius: 8px;
            padding: 3px;
            gap: 2px;
        }
        .hozio-bh-mode-btn {
            border: none;
            background: transparent;
            padding: 5px 14px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .hozio-bh-mode-btn:hover { color: #1f2937; }
        .hozio-bh-mode-btn.hozio-bh-mode-active {
            background: white;
            color: var(--hozio-blue);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .hozio-biz-card-header { gap: 8px; }
        .hozio-bh-html-view[hidden],
        .hozio-bh-classic-view[hidden] { display: none !important; }

        /* 24/7 master row */
        .hozio-bh-247-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: linear-gradient(135deg, rgba(141,198,63,0.08), rgba(0,160,227,0.08));
            border: 1px solid rgba(0,160,227,0.18);
            border-radius: 8px;
            margin-bottom: 14px;
            cursor: pointer;
        }
        .hozio-bh-247-row .hozio-bh-247-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
            flex-shrink: 0;
        }
        .hozio-bh-247-row .hozio-bh-247-switch input {
            opacity: 0; width: 0; height: 0;
        }
        .hozio-bh-247-row .hozio-bh-247-slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 22px;
            cursor: pointer;
            transition: .3s;
        }
        .hozio-bh-247-row .hozio-bh-247-slider:before {
            content: "";
            position: absolute;
            width: 16px; height: 16px;
            left: 3px; bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: .3s;
        }
        .hozio-bh-247-row input:checked + .hozio-bh-247-slider {
            background: var(--hozio-blue);
        }
        .hozio-bh-247-row input:checked + .hozio-bh-247-slider:before {
            transform: translateX(18px);
        }
        .hozio-bh-247-row strong {
            font-size: 13px;
            color: #1f2937;
            font-weight: 700;
        }
        .hozio-bh-247-row .hozio-bh-247-help {
            font-size: 11px;
            color: #6b7280;
            font-style: italic;
            margin-left: auto;
        }

        /* When 24/7 is on — dim the day rows */
        .hozio-bh-classic-view.is-247 .hozio-bh-days {
            opacity: 0.45;
            pointer-events: none;
            filter: grayscale(0.4);
        }

        /* Day rows */
        .hozio-bh-days {
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: opacity 0.2s;
        }
        .hozio-bh-day {
            display: grid;
            grid-template-columns: 50px 160px 1fr;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: border-color 0.15s, background 0.15s;
        }
        .hozio-bh-day:hover { border-color: #cfe9f5; }
        .hozio-bh-day.is-closed { background: #fafbfc; }
        .hozio-bh-day-label {
            font-weight: 700;
            font-size: 13px;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .hozio-bh-status-pills {
            display: inline-flex;
            background: #f3f4f6;
            border-radius: 6px;
            padding: 2px;
            gap: 2px;
        }
        .hozio-bh-status-btn {
            border: none;
            background: transparent;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.15s;
        }
        .hozio-bh-status-btn:hover { color: #1f2937; }
        .hozio-bh-status-btn.is-active {
            background: white;
            color: var(--hozio-blue);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .hozio-bh-day.is-closed .hozio-bh-status-btn.is-active {
            color: #9ca3af;
        }
        .hozio-bh-day-times {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }
        .hozio-bh-day.is-closed .hozio-bh-day-times {
            opacity: 0;
            pointer-events: none;
        }
        .hozio-bh-time-select {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 12px;
            background: white;
            cursor: pointer;
            min-width: 90px;
        }
        .hozio-bh-time-select:focus {
            outline: none;
            border-color: var(--hozio-blue);
            box-shadow: 0 0 0 2px rgba(0,160,227,0.1);
        }
        .hozio-bh-time-sep {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
        }

        .hozio-bh-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 12px;
        }
        .hozio-bh-apply-weekdays {
            background: transparent;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .hozio-bh-apply-weekdays:hover {
            border-color: var(--hozio-blue);
            color: var(--hozio-blue);
            background: #f0f9ff;
        }
        .hozio-bh-apply-weekdays .dashicons {
            font-size: 14px; width: 14px; height: 14px;
        }

        @media (max-width: 600px) {
            .hozio-bh-day {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .hozio-bh-day-times {
                justify-content: flex-start;
            }
        }

        /* Restyled WP Color Picker — full-width, polished button */
        .hozio-nav-color-card .wp-picker-container {
            display: block;
            width: 100%;
        }
        .hozio-nav-color-card .wp-picker-container .wp-color-result.button {
            width: 100% !important;
            height: 44px !important;
            margin: 0 0 8px 0 !important;
            padding: 0 14px 0 50px !important;
            border: 2px solid #e5e7eb !important;
            border-radius: 8px !important;
            background: white !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #374151 !important;
            text-align: left !important;
            box-shadow: none !important;
            cursor: pointer !important;
            transition: border-color 0.2s, box-shadow 0.2s !important;
            position: relative !important;
            line-height: 40px !important;
            display: flex !important;
            align-items: center !important;
        }
        .hozio-nav-color-card .wp-picker-container .wp-color-result.button:hover {
            border-color: var(--hozio-blue) !important;
            box-shadow: 0 2px 8px rgba(0,160,227,0.12) !important;
        }
        .hozio-nav-color-card .wp-picker-container .wp-color-result.button:focus {
            border-color: var(--hozio-blue) !important;
            box-shadow: 0 0 0 3px rgba(0,160,227,0.15) !important;
            outline: none !important;
        }
        .hozio-nav-color-card .wp-picker-container .wp-color-result-text {
            background: transparent !important;
            color: inherit !important;
            border-left: none !important;
            padding: 0 !important;
            line-height: 40px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.3px !important;
            font-size: 12px !important;
        }
        .hozio-nav-color-card .wp-picker-container .wp-color-result.button:after {
            content: '' !important;
            position: absolute !important;
            left: 8px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 6px !important;
            border: 1px solid rgba(0,0,0,0.12) !important;
            background-color: inherit !important;
            background-image:
                linear-gradient(45deg, #ddd 25%, transparent 25%),
                linear-gradient(-45deg, #ddd 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #ddd 75%),
                linear-gradient(-45deg, transparent 75%, #ddd 75%) !important;
            background-size: 8px 8px !important;
            background-position: 0 0, 0 4px, 4px -4px, -4px 0 !important;
        }
        /* Apply chosen color via CSS variable on the swatch */
        .hozio-nav-color-card .wp-picker-container .wp-color-result.button {
            --hozio-swatch-color: #ffffff;
        }
        .hozio-nav-color-card .wp-picker-container .wp-color-result.button:after {
            background-color: var(--hozio-swatch-color) !important;
            background-image: none !important;
        }
        /* Hide WP's default tiny color square inside the result button */
        .hozio-nav-color-card .wp-picker-container .wp-color-result-text {
            margin-left: 4px !important;
        }
        .hozio-nav-color-card .wp-picker-container .wp-picker-input-wrap {
            margin-top: 4px !important;
        }
        .hozio-nav-color-card .wp-picker-container .wp-picker-holder {
            margin-top: 4px !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
            border: 1px solid #e5e7eb !important;
        }

        /* Auto-built textarea state */
        .hozio-textarea.hozio-addr-auto {
            background: #f9fafb;
            color: #374151;
            border-color: #d1d5db;
            border-style: dashed;
            cursor: not-allowed;
            resize: none;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
        }

        /* Address subsection divider */
        .hozio-address-subsection {
            padding: 20px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .hozio-address-subsection-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin: 0 0 16px 0;
        }
        .hozio-address-subsection-title .dashicons {
            font-size: 16px;
            color: var(--hozio-blue);
        }
        .hozio-addr-clear-btn {
            margin-left: auto;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 500;
            background: transparent;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            color: #9ca3af;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: none;
            letter-spacing: 0;
        }
        .hozio-addr-clear-btn:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: #fef2f2;
        }
        .hozio-addr-clear-btn .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            color: inherit;
        }

        /* State combo + compact format toggle */
        .hozio-state-fmt-row {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 6px;
        }
        .hozio-state-fmt-toggle {
            position: relative;
            display: inline-block;
            width: 30px;
            height: 16px;
            flex-shrink: 0;
        }
        .hozio-state-fmt-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .hozio-state-fmt-toggle .hozio-toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #d1d5db;
            border-radius: 16px;
            transition: .3s;
        }
        .hozio-state-fmt-toggle .hozio-toggle-slider:before {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            left: 2px;
            bottom: 2px;
            background: white;
            border-radius: 50%;
            transition: .3s;
        }
        .hozio-state-fmt-toggle input:checked + .hozio-toggle-slider {
            background: var(--hozio-blue);
        }
        .hozio-state-fmt-toggle input:checked + .hozio-toggle-slider:before {
            transform: translateX(14px);
        }
        .hozio-state-fmt-text {
            font-size: 11px;
            color: #9ca3af;
            font-style: italic;
        }

        /* Custom State Dropdown — replaces native datalist */
        .hozio-state-combo-wrap {
            position: relative;
        }
        .hozio-state-combo-input {
            cursor: pointer !important;
            background: white !important;
            padding-right: 36px !important;
            user-select: none;
        }
        .hozio-state-combo-arrow {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
            transition: transform 0.2s, color 0.2s;
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .hozio-state-combo-wrap.open .hozio-state-combo-arrow {
            transform: translateY(-50%) rotate(180deg);
            color: var(--hozio-blue);
        }
        .hozio-state-combo-wrap.open .hozio-state-combo-input {
            border-color: var(--hozio-blue);
            box-shadow: 0 0 0 3px rgba(0,160,227,0.1);
        }
        .hozio-state-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1000;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            overflow: hidden;
            max-height: 340px;
            display: flex;
            flex-direction: column;
            animation: hozioDropFade 0.15s ease-out;
        }
        @keyframes hozioDropFade {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hozio-state-dropdown[hidden] { display: none; }
        .hozio-state-dropdown-search {
            padding: 8px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafbfc;
            flex-shrink: 0;
        }
        .hozio-state-search-input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 13px;
            box-sizing: border-box;
            background: white;
        }
        .hozio-state-search-input:focus {
            outline: none;
            border-color: var(--hozio-blue);
            box-shadow: 0 0 0 2px rgba(0,160,227,0.1);
        }
        .hozio-state-options {
            overflow-y: auto;
            max-height: 260px;
            flex: 1;
        }
        .hozio-state-options::-webkit-scrollbar { width: 8px; }
        .hozio-state-options::-webkit-scrollbar-track { background: #f9fafb; }
        .hozio-state-options::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        .hozio-state-options::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        .hozio-state-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            cursor: pointer;
            transition: background 0.1s;
            border-bottom: 1px solid #f9fafb;
        }
        .hozio-state-option[hidden] { display: none !important; }
        .hozio-state-option:last-child { border-bottom: none; }
        .hozio-state-option:hover,
        .hozio-state-option.hozio-state-option-active {
            background: #f0f9ff;
        }
        .hozio-state-option.hozio-state-option-selected {
            background: #eff6ff;
        }
        .hozio-state-option.hozio-state-option-selected .hozio-state-option-abbr {
            color: var(--hozio-blue);
        }
        .hozio-state-option-abbr {
            font-weight: 700;
            font-size: 13px;
            color: #1f2937;
            min-width: 28px;
            flex-shrink: 0;
        }
        .hozio-state-option-name {
            font-size: 13px;
            color: #6b7280;
        }
        .hozio-state-option-check {
            margin-left: auto;
            color: var(--hozio-blue);
            font-size: 16px;
            width: 16px;
            height: 16px;
            display: none;
        }
        .hozio-state-option-selected .hozio-state-option-check {
            display: inline-block;
        }
        .hozio-state-option-none {
            padding: 16px 12px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
            font-style: italic;
        }

        /* Street address autocomplete (Photon) */
        .hozio-addr-autocomplete {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 999;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            max-height: 280px;
            overflow-y: auto;
            animation: hozioDropFade 0.15s ease-out;
        }
        .hozio-addr-autocomplete[hidden] { display: none; }
        .hozio-addr-suggestion {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f9fafb;
            transition: background 0.1s;
        }
        .hozio-addr-suggestion:last-child { border-bottom: none; }
        .hozio-addr-suggestion:hover,
        .hozio-addr-suggestion.hozio-addr-active {
            background: #f0f9ff;
        }
        .hozio-addr-suggestion-line1 {
            font-weight: 600;
            font-size: 13px;
            color: #1f2937;
        }
        .hozio-addr-suggestion-line2 {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }
        .hozio-addr-loading,
        .hozio-addr-empty {
            padding: 12px;
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            font-style: italic;
        }
        .hozio-addr-loading .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: middle;
            animation: hozioSpin 1s linear infinite;
        }
        @keyframes hozioSpin {
            to { transform: rotate(360deg); }
        }
        .hozio-addr-credit {
            padding: 6px 10px;
            font-size: 10px;
            color: #cbd5e1;
            text-align: right;
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        @media (max-width: 1100px) and (min-width: 783px) {
            .hozio-address-grid { grid-template-columns: 1fr 1fr; }
            .hozio-biz-bottom  { grid-template-columns: 1fr 1fr; }
            .hozio-biz-bottom > .hozio-biz-card:first-child { grid-column: 1 / -1; }
        }

        @media (max-width: 782px) {
            .hozio-grid,
            .hozio-grid-3 {
                grid-template-columns: 1fr;
            }
            .hozio-address-grid,
            .hozio-biz-bottom {
                grid-template-columns: 1fr;
            }
            .hozio-header {
                padding: 30px 20px;
            }
            .hozio-content {
                padding: 0 20px 20px;
            }
            .hozio-color-field {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
    <?php
}

// Register settings for Hozio Dynamic Tags
function hozio_dynamic_tags_register_settings() {
    $fields = [
        'hozio_company_phone_1',
        'hozio_company_phone_2',
        'hozio_google_ads_phone',
        'hozio_sms_phone',
        'hozio_company_email',
        'hozio_company_address',
        'hozio_address_street',
        'hozio_address_town',
        'hozio_address_state',
        'hozio_address_state_format',
        'hozio_address_zip',
        'hozio_business_hours',
        'hozio_business_hours_mode',
        'hozio_business_hours_classic',
        'hozio_yelp_url',
        'hozio_youtube_url',
        'hozio_angies_list_url',
        'hozio_home_advisor_url',
        'hozio_bbb_url',
        'hozio_facebook_url',
        'hozio_instagram_url',
        'hozio_twitter_url',
        'hozio_tiktok_url',
        'hozio_linkedin_url',
        'hozio_gmb_link',
        'hozio_to_email_contact_form',
        'hozio_nav_text_color',
        'hozio_start_year',
        'hozio_hst_bg',
        'hozio_hst_border',
        'hozio_hst_header_text',
        'hozio_hst_header_hover',
        'hozio_hst_divider',
        'hozio_hst_search_border',
        'hozio_hst_search_bg',
        'hozio_hst_search_text',
        'hozio_hst_link',
        'hozio_hst_link_hover',
        'hozio_hst_heading',
        'hozio_hst_county_order',
    ];

    foreach ($fields as $field) {
        register_setting('hozio_dynamic_tags_options', $field);
    }
    
    $custom_tags = get_option('hozio_custom_tags', []);
    if (is_array($custom_tags)) {
        foreach ($custom_tags as $tag) {
            register_setting('hozio_dynamic_tags_options', 'hozio_' . $tag['value']);
        }
    }
}
add_action('admin_init', 'hozio_dynamic_tags_register_settings');

// Map option keys to [hozio] shortcode tag slugs
function hozio_get_shortcode_tag_slug( $field_id ) {
    $map = array(
        'hozio_company_phone_1'        => 'company-phone-1',
        'hozio_company_phone_2'        => 'company-phone-2',
        'hozio_google_ads_phone'       => 'google-ads-phone',
        'hozio_sms_phone'              => 'sms-phone',
        'hozio_company_email'          => 'company-email',
        'hozio_to_email_contact_form'  => 'to-email-contact-form',
        'hozio_company_address'        => 'company-address',
        'hozio_address_street'         => 'company-address-street',
        'hozio_address_town'           => 'company-address-town',
        'hozio_address_state'          => 'company-address-state',
        'hozio_address_zip'            => 'company-address-zip',
        'hozio_business_hours'         => 'business-hours',
        'hozio_start_year'             => 'years-of-experience',
        'hozio_gmb_link'               => 'gmb-link',
        'hozio_facebook_url'           => 'facebook',
        'hozio_instagram_url'          => 'instagram',
        'hozio_twitter_url'            => 'twitter',
        'hozio_tiktok_url'             => 'tiktok',
        'hozio_linkedin_url'           => 'linkedin',
        'hozio_youtube_url'            => 'youtube',
        'hozio_yelp_url'               => 'yelp',
        'hozio_angies_list_url'        => 'angies-list',
        'hozio_home_advisor_url'       => 'home-advisor',
        'hozio_bbb_url'                => 'bbb',
    );

    if ( isset( $map[ $field_id ] ) ) {
        return $map[ $field_id ];
    }

    // Custom tags: option key is hozio_{value}, tag slug is the {value}
    $custom_tags = get_option( 'hozio_custom_tags', array() );
    if ( is_array( $custom_tags ) ) {
        foreach ( $custom_tags as $tag ) {
            if ( 'hozio_' . $tag['value'] === $field_id ) {
                return $tag['value'];
            }
        }
    }

    return '';
}

// Render input fields with enhanced styling
function hozio_dynamic_tags_render_input($args) {
    $option = get_option($args['label_for'], '');
    $field_id = $args['label_for'];

    // Check if this is a custom tag
    $is_custom_tag = false;
    if (strpos($field_id, 'hozio_') === 0) {
        $custom_tags = get_option('hozio_custom_tags', []);
        if (is_array($custom_tags)) {
            foreach ($custom_tags as $tag) {
                if ('hozio_' . $tag['value'] === $field_id) {
                    $is_custom_tag = true;
                    break;
                }
            }
        }
    }

    echo '<div class="hozio-field-wrapper">';
    
    if ($field_id === 'hozio_start_year') {
        $stored_start_year = get_option('hozio_start_year', '');
        $current_year = (int) date('Y');
        $years_of_experience = ($stored_start_year) ? $current_year - (int) $stored_start_year : 0;
        
        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="hozio-input-number" min="1900" max="%3$s" />
            <div class="hozio-calculated-value">
                <span class="dashicons dashicons-calendar-alt"></span>
                <strong>Years of Experience:</strong> <span class="highlight">%4$s years</span>
            </div>',
            esc_attr($field_id),
            esc_attr($stored_start_year),
            esc_attr($current_year),
            esc_html($years_of_experience)
        );
    } elseif ($field_id === 'hozio_nav_text_color') {
        printf(
            '<div class="hozio-color-picker-wrapper">
                <input type="text" id="%1$s" name="%1$s" value="%2$s" class="hozio-color-picker" />
                <input type="color" class="hozio-color-input" value="%2$s" />
            </div>',
            esc_attr($field_id),
            esc_attr($option)
        );
    } elseif ($field_id === 'hozio_company_address' || $field_id === 'hozio_business_hours' || $is_custom_tag) {
        // Render textarea for fields that may contain HTML (including ALL custom tags)
        printf(
            '<textarea id="%1$s" name="%1$s" class="hozio-textarea" rows="4" placeholder="Enter content...">%2$s</textarea>
            <p class="hozio-field-description">HTML tags are allowed in this field</p>',
            esc_attr($field_id),
            esc_textarea($option)
        );
    } else {
        $icon = hozio_get_field_icon($field_id);
        $placeholder = hozio_get_field_placeholder($field_id);
        
        printf(
            '<div class="hozio-input-group">
                <span class="hozio-input-icon">%1$s</span>
                <input type="text" id="%2$s" name="%2$s" value="%3$s" class="hozio-input" placeholder="%4$s" />
            </div>',
            $icon,
            esc_attr($field_id),
            esc_attr($option),
            esc_attr($placeholder)
        );
    }

    // Copy shortcode button
    $tag_slug = hozio_get_shortcode_tag_slug( $field_id );
    if ( $tag_slug !== '' ) {
        printf(
            '<button type="button" class="hozio-copy-shortcode" data-shortcode="%s" title="Copy shortcode">
                <code>%s</code>
                <svg class="hozio-copy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2H5.5A1.5 1.5 0 004 3.5v9A1.5 1.5 0 005.5 14h5a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0011 2z"/><path d="M4.5 0A1.5 1.5 0 003 1.5V11a.5.5 0 001 0V1.5a.5.5 0 01.5-.5H9a.5.5 0 000-1H4.5z"/></svg>
                <svg class="hozio-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg>
            </button>',
            esc_attr( '[hozio tag="' . $tag_slug . '"]' ),
            esc_html( $tag_slug )
        );
    }

    echo '</div>';
}

// Helper function to get icon for each field
function hozio_get_field_icon($field_id) {
    $icons = [
        'hozio_company_phone_1' => '<span class="dashicons dashicons-phone"></span>',
        'hozio_company_phone_2' => '<span class="dashicons dashicons-phone"></span>',
        'hozio_google_ads_phone' => '<span class="dashicons dashicons-phone"></span>',
        'hozio_sms_phone' => '<span class="dashicons dashicons-smartphone"></span>',
        'hozio_company_email' => '<span class="dashicons dashicons-email"></span>',
        'hozio_yelp_url' => '<span class="dashicons dashicons-star-filled"></span>',
        'hozio_youtube_url' => '<span class="dashicons dashicons-video-alt3"></span>',
        'hozio_facebook_url' => '<span class="dashicons dashicons-facebook"></span>',
        'hozio_instagram_url' => '<span class="dashicons dashicons-instagram"></span>',
        'hozio_twitter_url' => '<span class="dashicons dashicons-twitter"></span>',
        'hozio_linkedin_url' => '<span class="dashicons dashicons-linkedin"></span>',
        'hozio_gmb_link' => '<span class="dashicons dashicons-location"></span>',
    ];
    
    // Check if it's a custom tag
    if (strpos($field_id, 'hozio_') === 0) {
        $custom_tags = get_option('hozio_custom_tags', []);
        if (is_array($custom_tags)) {
            foreach ($custom_tags as $tag) {
                if ('hozio_' . $tag['value'] === $field_id) {
                    // Return icon based on tag type
                    if ($tag['type'] === 'url') {
                        return '<span class="dashicons dashicons-admin-links"></span>';
                    } else {
                        return '<span class="dashicons dashicons-editor-alignleft"></span>';
                    }
                }
            }
        }
    }
    
    return isset($icons[$field_id]) ? $icons[$field_id] : '<span class="dashicons dashicons-admin-links"></span>';
}

// Helper function to get placeholder text
function hozio_get_field_placeholder($field_id) {
    $placeholders = [
        'hozio_company_phone_1' => '123-456-7890',
        'hozio_company_phone_2' => '123-456-7890',
        'hozio_google_ads_phone' => '123-456-7890',
        'hozio_sms_phone' => '123-456-7890',
        'hozio_company_email' => 'info@company.com',
        'hozio_to_email_contact_form' => 'contact@company.com, sales@company.com',
    ];
    
    if (strpos($field_id, '_url') !== false) {
        return 'https://...';
    }
    
    return isset($placeholders[$field_id]) ? $placeholders[$field_id] : '';
}

// Display the enhanced settings page
function hozio_dynamic_tags_settings_page() {
    // Pull and immediately consume any email-error transient set by the save handler
    $hozio_email_error = null;
    if ( isset( $_GET['email-error'] ) && $_GET['email-error'] === '1' ) {
        $hozio_email_error = get_transient( 'hozio_settings_email_error' );
        delete_transient( 'hozio_settings_email_error' );
    }
    ?>
    <div class="hozio-settings-wrapper">
        <div class="hozio-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-tag"></span>
                    <?php esc_html_e('Dynamic Tags Settings', 'hozio-dynamic-tags'); ?>
                </h1>
                <p class="hozio-subtitle">Configure your dynamic tags and contact information</p>
            </div>
        </div>

        <?php if ( $hozio_email_error && ! empty( $hozio_email_error['invalid'] ) ) : ?>
        <div class="hozio-email-error-banner">
            <div class="hozio-email-error-icon"><span class="dashicons dashicons-warning"></span></div>
            <div class="hozio-email-error-content">
                <strong>Settings not fully saved — invalid email format detected</strong>
                <p>
                    The <em>Contact Form Email(s)</em> field contains <?php echo count( $hozio_email_error['invalid'] ) === 1 ? 'an invalid address' : 'invalid addresses'; ?>:
                    <?php foreach ( $hozio_email_error['invalid'] as $bad ) : ?>
                        <code><?php echo esc_html( $bad ); ?></code>
                    <?php endforeach; ?>
                </p>
                <p class="hozio-email-error-hint">
                    All other settings were saved. Use commas (with no quotes) to separate multiple emails — for example:
                    <code>support@hozio.com, sales@hozio.com</code>. Then click Save again.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="hozio-content">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hozio-form">
                <?php wp_nonce_field('hozio_save_settings_nonce', 'hozio_save_settings_nonce_field'); ?>
                <input type="hidden" name="action" value="hozio_save_settings" />

                <!-- Contact Information Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-phone"></span>
                        <h2>Contact Information</h2>
                    </div>
                    <div class="hozio-grid">
                        <?php
                        $contact_fields = [
                            'hozio_company_phone_1' => 'Company Phone 1',
                            'hozio_company_phone_2' => 'Company Phone 2',
                            'hozio_google_ads_phone' => 'Google Ads Phone Number',
                            'hozio_sms_phone' => 'SMS Phone Number',
                            'hozio_company_email' => 'Company Email',
                            'hozio_to_email_contact_form' => 'Contact Form Email(s)',
                        ];
                        
                        foreach ($contact_fields as $key => $label) {
                            echo '<div class="hozio-field"><label>' . esc_html($label) . '</label>';
                            hozio_dynamic_tags_render_input(['label_for' => $key]);
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <!-- CallRail noswap toggle for SMS -->
                    <div style="margin-top: 16px; padding: 12px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #374151;">
                            <input type="checkbox" name="hozio_sms_calltrk_noswap" value="1" <?php checked(get_option('hozio_sms_calltrk_noswap', '0'), '1'); ?> style="margin: 0;">
                            <span>Add <code style="background:#e5e7eb;padding:2px 6px;border-radius:4px;font-size:12px;">data-calltrk-noswap</code> to SMS Phone Number</span>
                        </label>
                        <span style="font-size: 11px; color: #6b7280;">(Prevents CallRail from swapping this number)</span>
                    </div>
                </div>

                <!-- Business Details Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-building"></span>
                        <h2>Business Details</h2>
                    </div>

                    <!-- Address subsection -->
                    <div class="hozio-address-subsection">
                        <p class="hozio-address-subsection-title">
                            <span class="dashicons dashicons-location"></span>
                            Address Fields
                            <button type="button" id="hozio-addr-clear" class="hozio-addr-clear-btn">
                                <span class="dashicons dashicons-dismiss"></span> Clear All
                            </button>
                        </p>

                        <!-- Structured inputs: Street / Town / State / ZIP -->
                        <div class="hozio-address-grid">
                            <?php
                            $addr_inputs = [
                                'hozio_address_street' => ['label' => 'Street Name', 'placeholder' => '123 Main St'],
                                'hozio_address_town'   => ['label' => 'Town / City', 'placeholder' => 'Springfield'],
                                'hozio_address_state'  => ['label' => 'State',        'placeholder' => ''],
                                'hozio_address_zip'    => ['label' => 'ZIP Code',     'placeholder' => '01844'],
                            ];
                            $us_states = [
                                'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas',
                                'CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware',
                                'FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho',
                                'IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas',
                                'KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland',
                                'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi',
                                'MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada',
                                'NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York',
                                'NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma',
                                'OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina',
                                'SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah',
                                'VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia',
                                'WI'=>'Wisconsin','WY'=>'Wyoming',
                            ];
                            foreach ( $addr_inputs as $opt_key => $meta ) :
                                $val      = get_option( $opt_key, '' );
                                $tag_slug = hozio_get_shortcode_tag_slug( $opt_key );
                                if ( $opt_key === 'hozio_address_state' ) :
                                    $state_format  = get_option( 'hozio_address_state_format', 'abbr' );
                                    $selected_abbr = '';
                                    foreach ( $us_states as $abbr => $name ) {
                                        if ( $val === $abbr || $val === $name ) { $selected_abbr = $abbr; break; }
                                    }
                            ?>
                            <div class="hozio-field hozio-state-field">
                                <label><?php echo esc_html( $meta['label'] ); ?></label>
                                <div class="hozio-state-combo-wrap" id="hozio-state-combo-wrap">
                                    <input type="text" id="hozio-state-display" class="hozio-input hozio-state-combo-input"
                                           placeholder="Select a state..." autocomplete="off" readonly>
                                    <span class="hozio-state-combo-arrow dashicons dashicons-arrow-down-alt2"></span>
                                    <div class="hozio-state-dropdown" id="hozio-state-dropdown" hidden>
                                        <div class="hozio-state-dropdown-search">
                                            <input type="text" id="hozio-state-search-input" class="hozio-state-search-input"
                                                   placeholder="Search by abbreviation or name..." autocomplete="off">
                                        </div>
                                        <div class="hozio-state-options" id="hozio-state-options">
                                            <?php foreach ( $us_states as $abbr => $name ) : ?>
                                            <div class="hozio-state-option<?php echo ( $selected_abbr === $abbr ) ? ' hozio-state-option-selected' : ''; ?>"
                                                 data-abbr="<?php echo esc_attr( $abbr ); ?>"
                                                 data-name="<?php echo esc_attr( $name ); ?>">
                                                <span class="hozio-state-option-abbr"><?php echo esc_html( $abbr ); ?></span>
                                                <span class="hozio-state-option-name"><?php echo esc_html( $name ); ?></span>
                                                <span class="hozio-state-option-check dashicons dashicons-yes"></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="hozio_address_state" id="hozio-state-hidden"
                                       value="<?php echo esc_attr( $val ); ?>">
                                <input type="hidden" name="hozio_address_state_format" id="hozio-state-fmt-hidden"
                                       value="<?php echo esc_attr( $state_format ); ?>">
                                <div class="hozio-state-fmt-row">
                                    <label class="hozio-state-fmt-toggle">
                                        <input type="checkbox" id="hozio-state-fmt-chk"
                                               <?php echo ( $state_format === 'full' ) ? 'checked' : ''; ?>>
                                        <span class="hozio-toggle-slider"></span>
                                    </label>
                                    <span class="hozio-state-fmt-text">Full name</span>
                                </div>
                                <button type="button" class="hozio-copy-shortcode"
                                        data-shortcode="<?php echo esc_attr( '[hozio tag="' . $tag_slug . '"]' ); ?>"
                                        title="Copy shortcode">
                                    <code><?php echo esc_html( $tag_slug ); ?></code>
                                    <svg class="hozio-copy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2H5.5A1.5 1.5 0 004 3.5v9A1.5 1.5 0 005.5 14h5a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0011 2z"/><path d="M4.5 0A1.5 1.5 0 003 1.5V11a.5.5 0 001 0V1.5a.5.5 0 01.5-.5H9a.5.5 0 000-1H4.5z"/></svg>
                                    <svg class="hozio-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg>
                                </button>
                            </div>
                            <?php else : ?>
                            <div class="hozio-field">
                                <label><?php echo esc_html( $meta['label'] ); ?></label>
                                <div class="hozio-input-group">
                                    <span class="hozio-input-icon"><span class="dashicons dashicons-location"></span></span>
                                    <input type="text" name="<?php echo esc_attr( $opt_key ); ?>"
                                           value="<?php echo esc_attr( $val ); ?>"
                                           class="hozio-input"
                                           placeholder="<?php echo esc_attr( $meta['placeholder'] ); ?>">
                                </div>
                                <button type="button" class="hozio-copy-shortcode"
                                        data-shortcode="<?php echo esc_attr( '[hozio tag="' . $tag_slug . '"]' ); ?>"
                                        title="Copy shortcode">
                                    <code><?php echo esc_html( $tag_slug ); ?></code>
                                    <svg class="hozio-copy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2H5.5A1.5 1.5 0 004 3.5v9A1.5 1.5 0 005.5 14h5a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0011 2z"/><path d="M4.5 0A1.5 1.5 0 003 1.5V11a.5.5 0 001 0V1.5a.5.5 0 01.5-.5H9a.5.5 0 000-1H4.5z"/></svg>
                                    <svg class="hozio-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg>
                                </button>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>

                        <!-- Company Address: JS toggles readonly when structured fields are filled -->
                        <?php $addr_current = get_option( 'hozio_company_address', '' ); ?>
                        <div class="hozio-field" style="margin-top:16px;">
                            <label>
                                Company Address
                                <span class="hozio-address-readonly-badge" id="hozio-addr-badge" style="display:none;">
                                    <span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px;"></span>
                                    auto-built
                                </span>
                            </label>
                            <textarea name="hozio_company_address" id="hozio-addr-output"
                                      class="hozio-textarea" rows="3"
                                      placeholder="e.g. 123 Main St&lt;br&gt;Springfield, MA 01844"><?php echo esc_textarea( $addr_current ); ?></textarea>
                            <p class="hozio-field-description" id="hozio-addr-desc">HTML tags allowed. Fill Street / Town / State / ZIP above to auto-build this field.</p>
                            <button type="button" class="hozio-copy-shortcode"
                                    data-shortcode="[hozio tag=&quot;company-address&quot;]"
                                    title="Copy shortcode">
                                <code>company-address</code>
                                <svg class="hozio-copy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2H5.5A1.5 1.5 0 004 3.5v9A1.5 1.5 0 005.5 14h5a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0011 2z"/><path d="M4.5 0A1.5 1.5 0 003 1.5V11a.5.5 0 001 0V1.5a.5.5 0 01.5-.5H9a.5.5 0 000-1H4.5z"/></svg>
                                <svg class="hozio-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg>
                            </button>
                        </div>
                    </div><!-- /.hozio-address-subsection -->

                    <!-- Business Hours / Start Year / Nav Color — Redesigned Cards -->
                    <div class="hozio-biz-bottom">
                        <!-- Business Hours card (full width) -->
                        <?php
                        $bh_mode    = get_option( 'hozio_business_hours_mode', 'html' );
                        $bh_classic = get_option( 'hozio_business_hours_classic', null );
                        if ( ! is_array( $bh_classic ) || ! isset( $bh_classic['days'] ) ) {
                            $bh_classic = function_exists( 'hozio_default_business_hours_classic' )
                                ? hozio_default_business_hours_classic()
                                : array(
                                    'always_open' => false,
                                    'days' => array(
                                        'monday'    => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                                        'tuesday'   => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                                        'wednesday' => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                                        'thursday'  => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                                        'friday'    => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                                        'saturday'  => array( 'status' => 'closed', 'open' => '09:00', 'close' => '17:00' ),
                                        'sunday'    => array( 'status' => 'closed', 'open' => '09:00', 'close' => '17:00' ),
                                    ),
                                );
                        }
                        // Build 15-minute time options (00:00 → 23:45)
                        $bh_time_options = array();
                        for ( $h = 0; $h < 24; $h++ ) {
                            foreach ( array( '00', '15', '30', '45' ) as $m ) {
                                $val = sprintf( '%02d:%s', $h, $m );
                                $h12 = ( $h % 12 === 0 ) ? 12 : ( $h % 12 );
                                $period = ( $h >= 12 ) ? 'PM' : 'AM';
                                $bh_time_options[ $val ] = $h12 . ':' . $m . ' ' . $period;
                            }
                        }
                        $bh_day_labels = array(
                            'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
                            'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat',
                            'sunday' => 'Sun',
                        );
                        ?>
                        <div class="hozio-biz-card">
                            <div class="hozio-biz-card-header">
                                <span class="dashicons dashicons-clock"></span>
                                <h3>Business Hours</h3>
                                <div class="hozio-bh-mode-toggle" role="tablist">
                                    <button type="button" class="hozio-bh-mode-btn <?php echo $bh_mode === 'html' ? 'hozio-bh-mode-active' : ''; ?>" data-mode="html">HTML</button>
                                    <button type="button" class="hozio-bh-mode-btn <?php echo $bh_mode === 'classic' ? 'hozio-bh-mode-active' : ''; ?>" data-mode="classic">Classic</button>
                                </div>
                                <input type="hidden" name="hozio_business_hours_mode" id="hozio-bh-mode-input"
                                       value="<?php echo esc_attr( $bh_mode ); ?>">
                            </div>

                            <!-- HTML mode -->
                            <div class="hozio-bh-html-view" <?php echo $bh_mode === 'html' ? '' : 'hidden'; ?>>
                                <textarea name="hozio_business_hours" id="hozio_business_hours"
                                          class="hozio-biz-input"
                                          rows="5"
                                          placeholder="Mon - Fri: 8am - 7pm&lt;br&gt;Sat: 8am - 4pm&lt;br&gt;Sun: Closed"><?php echo esc_textarea( get_option( 'hozio_business_hours', '' ) ); ?></textarea>
                            </div>

                            <!-- Classic mode -->
                            <div class="hozio-bh-classic-view <?php echo ! empty( $bh_classic['always_open'] ) ? 'is-247' : ''; ?>"
                                 <?php echo $bh_mode === 'classic' ? '' : 'hidden'; ?>>
                                <label class="hozio-bh-247-row">
                                    <span class="hozio-bh-247-switch">
                                        <input type="checkbox" name="hozio_business_hours_classic[always_open]" id="hozio-bh-247-chk" value="1"
                                               <?php checked( ! empty( $bh_classic['always_open'] ) ); ?>>
                                        <span class="hozio-bh-247-slider"></span>
                                    </span>
                                    <strong>Open 24/7</strong>
                                    <span class="hozio-bh-247-help">Overrides per-day settings</span>
                                </label>

                                <div class="hozio-bh-days">
                                    <?php foreach ( $bh_day_labels as $day_key => $day_label ) :
                                        $day = isset( $bh_classic['days'][ $day_key ] ) ? $bh_classic['days'][ $day_key ] : array();
                                        $status = ( $day['status'] ?? 'open' ) === 'closed' ? 'closed' : 'open';
                                        $open_v  = $day['open']  ?? '09:00';
                                        $close_v = $day['close'] ?? '17:00';
                                    ?>
                                    <div class="hozio-bh-day <?php echo $status === 'closed' ? 'is-closed' : ''; ?>" data-day="<?php echo esc_attr( $day_key ); ?>">
                                        <div class="hozio-bh-day-label"><?php echo esc_html( $day_label ); ?></div>
                                        <div class="hozio-bh-status-pills">
                                            <button type="button" class="hozio-bh-status-btn <?php echo $status === 'open' ? 'is-active' : ''; ?>" data-status="open">Open</button>
                                            <button type="button" class="hozio-bh-status-btn <?php echo $status === 'closed' ? 'is-active' : ''; ?>" data-status="closed">Closed</button>
                                        </div>
                                        <div class="hozio-bh-day-times">
                                            <input type="hidden" name="hozio_business_hours_classic[days][<?php echo esc_attr( $day_key ); ?>][status]"
                                                   value="<?php echo esc_attr( $status ); ?>" class="hozio-bh-day-status-input">
                                            <select name="hozio_business_hours_classic[days][<?php echo esc_attr( $day_key ); ?>][open]" class="hozio-bh-time-select">
                                                <?php foreach ( $bh_time_options as $tv => $tl ) : ?>
                                                <option value="<?php echo esc_attr( $tv ); ?>" <?php selected( $open_v, $tv ); ?>><?php echo esc_html( $tl ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="hozio-bh-time-sep">to</span>
                                            <select name="hozio_business_hours_classic[days][<?php echo esc_attr( $day_key ); ?>][close]" class="hozio-bh-time-select">
                                                <?php foreach ( $bh_time_options as $tv => $tl ) : ?>
                                                <option value="<?php echo esc_attr( $tv ); ?>" <?php selected( $close_v, $tv ); ?>><?php echo esc_html( $tl ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="hozio-bh-actions">
                                    <button type="button" class="hozio-bh-apply-weekdays" id="hozio-bh-apply-weekdays">
                                        <span class="dashicons dashicons-controls-repeat"></span>
                                        Apply Mon to Tue–Fri
                                    </button>
                                </div>
                            </div>

                            <div class="hozio-biz-card-footer">
                                <span class="hozio-biz-card-help">Toggle between freeform HTML and structured per-day editor</span>
                                <button type="button" class="hozio-copy-shortcode"
                                        data-shortcode="[hozio tag=&quot;business-hours&quot;]"
                                        title="Copy shortcode">
                                    <code>business-hours</code>
                                    <svg class="hozio-copy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2H5.5A1.5 1.5 0 004 3.5v9A1.5 1.5 0 005.5 14h5a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0011 2z"/><path d="M4.5 0A1.5 1.5 0 003 1.5V11a.5.5 0 001 0V1.5a.5.5 0 01.5-.5H9a.5.5 0 000-1H4.5z"/></svg>
                                    <svg class="hozio-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Start Year card -->
                        <?php
                        $stored_start_year   = get_option( 'hozio_start_year', '' );
                        $current_year        = (int) date( 'Y' );
                        $years_of_experience = ( $stored_start_year ) ? $current_year - (int) $stored_start_year : 0;
                        ?>
                        <div class="hozio-biz-card">
                            <div class="hozio-biz-card-header">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <h3>Start Year</h3>
                            </div>
                            <input type="number" name="hozio_start_year" id="hozio_start_year"
                                   class="hozio-biz-input"
                                   value="<?php echo esc_attr( $stored_start_year ); ?>"
                                   min="1900" max="<?php echo esc_attr( $current_year ); ?>"
                                   placeholder="e.g. 2010">
                            <div class="hozio-biz-year-badge">
                                <span class="dashicons dashicons-awards"></span>
                                <span>Years: <span class="hozio-years-num"><?php echo esc_html( $years_of_experience ); ?></span></span>
                            </div>
                            <div class="hozio-biz-card-footer">
                                <span class="hozio-biz-card-help">&nbsp;</span>
                                <button type="button" class="hozio-copy-shortcode"
                                        data-shortcode="[hozio tag=&quot;years-of-experience&quot;]"
                                        title="Copy shortcode">
                                    <code>years-of-experience</code>
                                    <svg class="hozio-copy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2H5.5A1.5 1.5 0 004 3.5v9A1.5 1.5 0 005.5 14h5a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0011 2z"/><path d="M4.5 0A1.5 1.5 0 003 1.5V11a.5.5 0 001 0V1.5a.5.5 0 01.5-.5H9a.5.5 0 000-1H4.5z"/></svg>
                                    <svg class="hozio-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Nav Text Color card -->
                        <div class="hozio-biz-card hozio-nav-color-card">
                            <div class="hozio-biz-card-header">
                                <span class="dashicons dashicons-art"></span>
                                <h3>Navigation Text Color</h3>
                            </div>
                            <input type="text" name="hozio_nav_text_color" id="hozio_nav_text_color"
                                   class="hozio-color-picker"
                                   value="<?php echo esc_attr( get_option( 'hozio_nav_text_color', '' ) ); ?>" />
                            <div class="hozio-biz-card-footer">
                                <span class="hozio-biz-card-help">Color for navigation menu text</span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Media & Review Sites Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-share"></span>
                        <h2>Social Media & Review Sites</h2>
                    </div>
                    <div class="hozio-grid hozio-grid-3">
                        <?php
                        $social_fields = [
                            'hozio_facebook_url' => 'Facebook',
                            'hozio_instagram_url' => 'Instagram',
                            'hozio_twitter_url' => 'Twitter',
                            'hozio_tiktok_url' => 'TikTok',
                            'hozio_linkedin_url' => 'LinkedIn',
                            'hozio_youtube_url' => 'YouTube',
                            'hozio_yelp_url' => 'Yelp',
                            'hozio_angies_list_url' => "Angi's List",
                            'hozio_home_advisor_url' => 'Home Advisor',
                            'hozio_bbb_url' => 'BBB',
                            'hozio_gmb_link' => 'Google My Business',
                        ];
                        
                        foreach ($social_fields as $key => $label) {
                            echo '<div class="hozio-field"><label>' . esc_html($label) . '</label>';
                            hozio_dynamic_tags_render_input(['label_for' => $key]);
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>


                <!-- HTML Sitemap Settings Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <h2>HTML Sitemap Settings</h2>
                    </div>
                    
                    <!-- Dark Mode Toggle -->
                    <div class="hozio-field">
                        <div class="hozio-toggle-wrapper">
                            <label class="hozio-toggle-switch">
                                <input type="checkbox" name="hozio_sitemap_dark_mode" value="1" <?php checked(get_option('hozio_sitemap_dark_mode'), '1'); ?> />
                                <span class="hozio-toggle-slider"></span>
                            </label>
                            <span class="hozio-toggle-label">Enable Dark Mode for HTML Sitemap</span>
                        </div>
                        <p class="hozio-field-description">When enabled, the HTML sitemap will display with black backgrounds and white text. Links will remain visible for accessibility.</p>
                    </div>

                    <!-- Link Colors Subsection -->
                    <div class="hozio-subsection">
                        <h3 class="hozio-subsection-title">
                            <span class="dashicons dashicons-admin-links"></span>
                            Link Colors
                        </h3>
                        <p class="hozio-info-text">
                            <span class="dashicons dashicons-info"></span>
                            By default, links inherit colors from your Elementor global styles. Set custom colors below to override the global settings for the sitemap only.
                        </p>

                        <!-- Link Color Field -->
                        <div class="hozio-color-field">
                            <div class="hozio-color-input-wrapper">
                                <label class="hozio-field-label">Link Color</label>
                                <input type="text" name="hozio_sitemap_link_color" value="<?php echo esc_attr(get_option('hozio_sitemap_link_color', '')); ?>" class="hozio-color-picker" />
                            </div>
                            <div class="hozio-color-info">
                                <p class="hozio-field-description">
                                    Set the default color for all links in the sitemap. Leave empty to use Elementor global link color.
                                </p>
                            </div>
                        </div>

                        <!-- Link Hover Color Field -->
                        <div class="hozio-color-field">
                            <div class="hozio-color-input-wrapper">
                                <label class="hozio-field-label">Link Hover Color</label>
                                <input type="text" name="hozio_sitemap_link_hover_color" value="<?php echo esc_attr(get_option('hozio_sitemap_link_hover_color', '')); ?>" class="hozio-color-picker" />
                            </div>
                            <div class="hozio-color-info">
                                <p class="hozio-field-description">
                                    Set the color for links when hovering over them. Leave empty to use Elementor global hover color.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Custom Tags Section -->
                <?php
                wp_cache_delete( 'hozio_custom_tags', 'options' );
                wp_cache_delete( 'alloptions', 'options' );
                $custom_tags = get_option('hozio_custom_tags', []);
                if (!empty($custom_tags) && is_array($custom_tags)) :
                ?>
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <h2>Custom Dynamic Tags</h2>
                    </div>
                    <div class="hozio-grid">
                        <?php
                        foreach ($custom_tags as $tag) {
                            echo '<div class="hozio-field"><label>' . esc_html($tag['title']) . '</label>';
                            hozio_dynamic_tags_render_input(['label_for' => 'hozio_' . $tag['value']]);
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Service Towns Colors Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-location"></span>
                        <h2>Service Towns Shortcode Colors</h2>
                    </div>
                    <p style="margin:0 0 20px;color:#6b7280;font-size:13px;">
                        Controls the <code>[hozio_service_towns]</code> accordion colors. Click <strong>↺</strong> next to any field to restore its default.
                    </p>

                    <style>
                    .hst-color-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 40px; }
                    .hst-color-group-title { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#9ca3af; padding-bottom:8px; margin:0 0 2px; border-bottom:2px solid #f3f4f6; }
                    .hst-color-group-spacer { height:18px; }
                    .hst-color-row { display:flex; align-items:center; gap:10px; padding:6px 0; border-bottom:1px solid #f5f5f5; }
                    .hst-color-label { flex:1; font-size:13px; color:#374151; font-weight:500; cursor:pointer; }
                    .hst-swatch { width:24px; height:24px; border-radius:5px; border:1px solid rgba(0,0,0,.13); flex-shrink:0; display:inline-block; }
                    .hst-reset-btn { background:none; border:1px solid #e2e8f0; color:#94a3b8; padding:3px 8px; border-radius:5px; cursor:pointer; font-size:11px; white-space:nowrap; flex-shrink:0; line-height:1.6; }
                    .hst-reset-btn:hover { border-color:#fca5a5; color:#ef4444; background:#fef2f2; }
                    .hst-reset-all-wrap { margin-top:18px; padding-top:14px; border-top:1px solid #f3f4f6; }
                    .hst-reset-all-btn { background:none; border:1px solid #e2e8f0; color:#6b7280; padding:7px 16px; border-radius:7px; cursor:pointer; font-size:13px; }
                    .hst-reset-all-btn:hover { border-color:#fca5a5; color:#ef4444; background:#fef2f2; }
                    /* wp-color-picker: hide the big Select Color button, show only the swatch button */
                    .hst-color-row .wp-picker-container { display:inline-flex !important; align-items:center; flex-shrink:0; }
                    .hst-color-row .wp-color-result.button { width:60px !important; height:28px !important; min-height:0 !important; padding:0 !important; border-radius:6px !important; box-shadow:none !important; border:1px solid #d1d5db !important; font-size:0 !important; }
                    .hst-color-row .wp-color-result-text { font-size:0 !important; width:0; overflow:hidden; display:block; }
                    </style>

                    <div class="hst-color-grid">

                        <!-- Left: Accordion Card -->
                        <div>
                            <div class="hst-color-group-title">Accordion Card</div>
                            <?php
                            $hst_group1 = [
                                'hozio_hst_heading'      => [ 'Heading Text',       '#111827' ],
                                'hozio_hst_bg'           => [ 'Card Background',     '#ffffff' ],
                                'hozio_hst_border'       => [ 'Card Border',         '#e5e7eb' ],
                                'hozio_hst_header_text'  => [ 'County Header',       '#111827' ],
                                'hozio_hst_header_hover' => [ 'Header Hover BG',     '#f3f4f6' ],
                                'hozio_hst_divider'      => [ 'Divider Line',        '#e5e7eb' ],
                            ];
                            foreach ( $hst_group1 as $opt => [ $label, $default ] ) :
                                $val = get_option( $opt, '' );
                                $swatch_bg = $val ?: $default;
                            ?>
                            <div class="hst-color-row">
                                <label class="hst-color-label" for="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $label ); ?></label>
                                <span class="hst-swatch" style="background:<?php echo esc_attr( $swatch_bg ); ?>"></span>
                                <input type="text" name="<?php echo esc_attr( $opt ); ?>"
                                       id="<?php echo esc_attr( $opt ); ?>"
                                       class="hozio-color-picker hst-cp"
                                       value="<?php echo esc_attr( $val ); ?>"
                                       data-default-color="<?php echo esc_attr( $default ); ?>">
                                <button type="button" class="hst-reset-btn"
                                        data-default="<?php echo esc_attr( $default ); ?>"
                                        data-target="<?php echo esc_attr( $opt ); ?>">↺ Default</button>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Right: Search Bar + Town Links -->
                        <div>
                            <div class="hst-color-group-title">Search Bar</div>
                            <?php
                            $hst_group2 = [
                                'hozio_hst_search_bg'     => [ 'Background', '#ffffff' ],
                                'hozio_hst_search_border' => [ 'Border',     '#d1d5db' ],
                                'hozio_hst_search_text'   => [ 'Text',       '#111827' ],
                            ];
                            foreach ( $hst_group2 as $opt => [ $label, $default ] ) :
                                $val = get_option( $opt, '' );
                                $swatch_bg = $val ?: $default;
                            ?>
                            <div class="hst-color-row">
                                <label class="hst-color-label" for="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $label ); ?></label>
                                <span class="hst-swatch" style="background:<?php echo esc_attr( $swatch_bg ); ?>"></span>
                                <input type="text" name="<?php echo esc_attr( $opt ); ?>"
                                       id="<?php echo esc_attr( $opt ); ?>"
                                       class="hozio-color-picker hst-cp"
                                       value="<?php echo esc_attr( $val ); ?>"
                                       data-default-color="<?php echo esc_attr( $default ); ?>">
                                <button type="button" class="hst-reset-btn"
                                        data-default="<?php echo esc_attr( $default ); ?>"
                                        data-target="<?php echo esc_attr( $opt ); ?>">↺ Default</button>
                            </div>
                            <?php endforeach; ?>

                            <div class="hst-color-group-spacer"></div>
                            <div class="hst-color-group-title">Town Links</div>
                            <?php
                            $hst_group3 = [
                                'hozio_hst_link'       => [ 'Link Color',  '#2563eb' ],
                                'hozio_hst_link_hover' => [ 'Link Hover',  '#1d4ed8' ],
                            ];
                            foreach ( $hst_group3 as $opt => [ $label, $default ] ) :
                                $val = get_option( $opt, '' );
                                $swatch_bg = $val ?: $default;
                            ?>
                            <div class="hst-color-row">
                                <label class="hst-color-label" for="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $label ); ?></label>
                                <span class="hst-swatch" style="background:<?php echo esc_attr( $swatch_bg ); ?>"></span>
                                <input type="text" name="<?php echo esc_attr( $opt ); ?>"
                                       id="<?php echo esc_attr( $opt ); ?>"
                                       class="hozio-color-picker hst-cp"
                                       value="<?php echo esc_attr( $val ); ?>"
                                       data-default-color="<?php echo esc_attr( $default ); ?>">
                                <button type="button" class="hst-reset-btn"
                                        data-default="<?php echo esc_attr( $default ); ?>"
                                        data-target="<?php echo esc_attr( $opt ); ?>">↺ Default</button>
                            </div>
                            <?php endforeach; ?>
                        </div>

                    </div>

                    <div class="hst-reset-all-wrap">
                        <button type="button" class="hst-reset-all-btn">↺ Reset all to defaults</button>
                    </div>

                    <div style="margin-top:22px;padding-top:18px;border-top:1px solid #f3f4f6;">
                        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:8px;">County Display Order</label>
                        <select name="hozio_hst_county_order" id="hozio_hst_county_order" style="min-width:220px;font-size:13px;">
                            <?php
                            $order_val = get_option( 'hozio_hst_county_order', 'count_desc' );
                            $order_opts = [
                                'count_desc' => 'Most cities first',
                                'count_asc'  => 'Fewest cities first',
                                'alpha'      => 'Alphabetical (A → Z)',
                                'manual'     => 'Manual (ACF field order)',
                            ];
                            foreach ( $order_opts as $v => $lbl ) :
                            ?>
                            <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $order_val, $v ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="margin:6px 0 0;font-size:12px;color:#9ca3af;">Controls how county accordions are sorted on the front end.</p>
                    </div>
                </div>

                <div class="hozio-submit-wrapper">
                    <?php submit_button(__('Save All Settings', 'hozio-dynamic-tags'), 'primary hozio-submit-btn', 'submit', false); ?>
                    <button type="button" class="button hozio-reset-btn">
                        <span class="dashicons dashicons-image-rotate"></span>
                        Reset to Default
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Handle the settings save functionality
function hozio_dynamic_tags_save_settings() {
    if (!isset($_POST['hozio_save_settings_nonce_field']) || !wp_verify_nonce($_POST['hozio_save_settings_nonce_field'], 'hozio_save_settings_nonce')) {
        wp_die('Nonce verification failed');
    }

    // ─── Validate Contact Form Email(s) ──────────────────────────────────────
    // Allow comma-separated list. If anything is invalid, skip saving this
    // field, store the bad input + invalid items in a transient so the page
    // can show a clear error on redirect.
    $contact_email_raw = isset( $_POST['hozio_to_email_contact_form'] )
        ? trim( wp_unslash( (string) $_POST['hozio_to_email_contact_form'] ) )
        : '';
    $contact_email_invalid_list = array();
    if ( $contact_email_raw !== '' ) {
        $emails = array_filter( array_map( 'trim', explode( ',', $contact_email_raw ) ) );
        foreach ( $emails as $em ) {
            if ( ! is_email( $em ) ) {
                $contact_email_invalid_list[] = $em;
            }
        }
    }
    $contact_email_has_error = ! empty( $contact_email_invalid_list );
    if ( $contact_email_has_error ) {
        set_transient( 'hozio_settings_email_error', array(
            'invalid' => $contact_email_invalid_list,
            'value'   => $contact_email_raw,
        ), 5 * MINUTE_IN_SECONDS );
        // Skip saving the field — the existing value in the DB remains intact
        unset( $_POST['hozio_to_email_contact_form'] );
    } else {
        // Clear any stale error transient from a previous failed save
        delete_transient( 'hozio_settings_email_error' );
    }

    // Save structured address fields; auto-build company_address when any are filled.
    $addr_street = isset( $_POST['hozio_address_street'] ) ? sanitize_text_field( $_POST['hozio_address_street'] ) : '';
    $addr_town   = isset( $_POST['hozio_address_town'] )   ? sanitize_text_field( $_POST['hozio_address_town'] )   : '';
    $addr_state  = isset( $_POST['hozio_address_state'] )  ? sanitize_text_field( $_POST['hozio_address_state'] )  : '';
    $addr_zip    = isset( $_POST['hozio_address_zip'] )    ? sanitize_text_field( $_POST['hozio_address_zip'] )    : '';
    update_option( 'hozio_address_street', $addr_street );
    update_option( 'hozio_address_town',   $addr_town );
    $addr_state_fmt = isset( $_POST['hozio_address_state_format'] ) ? sanitize_text_field( $_POST['hozio_address_state_format'] ) : 'abbr';
    update_option( 'hozio_address_state',        $addr_state );
    update_option( 'hozio_address_state_format', $addr_state_fmt );
    update_option( 'hozio_address_zip',          $addr_zip );
    if ( $addr_street || $addr_town || $addr_state || $addr_zip ) {
        $line2 = trim( $addr_town . ( $addr_state ? ', ' . $addr_state : '' ) . ( $addr_zip ? ' ' . $addr_zip : '' ) );
        update_option( 'hozio_company_address', $addr_street . ( $line2 ? '<br>' . $line2 : '' ) );
    } elseif ( isset( $_POST['hozio_company_address'] ) ) {
        update_option( 'hozio_company_address', wp_kses_post( wp_unslash( $_POST['hozio_company_address'] ) ) );
    }

    // --- Business Hours mode + classic structure ---
    $bh_mode_in = isset( $_POST['hozio_business_hours_mode'] ) ? $_POST['hozio_business_hours_mode'] : 'html';
    update_option( 'hozio_business_hours_mode', $bh_mode_in === 'classic' ? 'classic' : 'html' );

    $bh_classic_default = function_exists( 'hozio_default_business_hours_classic' )
        ? hozio_default_business_hours_classic()
        : array(
            'always_open' => false,
            'days' => array(
                'monday'    => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                'tuesday'   => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                'wednesday' => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                'thursday'  => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                'friday'    => array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' ),
                'saturday'  => array( 'status' => 'closed', 'open' => '09:00', 'close' => '17:00' ),
                'sunday'    => array( 'status' => 'closed', 'open' => '09:00', 'close' => '17:00' ),
            ),
        );
    $bh_classic_in = isset( $_POST['hozio_business_hours_classic'] ) && is_array( $_POST['hozio_business_hours_classic'] )
        ? wp_unslash( $_POST['hozio_business_hours_classic'] )
        : array();
    $bh_save = $bh_classic_default;
    $bh_save['always_open'] = ! empty( $bh_classic_in['always_open'] );
    if ( isset( $bh_classic_in['days'] ) && is_array( $bh_classic_in['days'] ) ) {
        foreach ( $bh_classic_default['days'] as $day_key => $day_default ) {
            if ( isset( $bh_classic_in['days'][ $day_key ] ) && is_array( $bh_classic_in['days'][ $day_key ] ) ) {
                $d      = $bh_classic_in['days'][ $day_key ];
                $status = ( isset( $d['status'] ) && $d['status'] === 'closed' ) ? 'closed' : 'open';
                $open_v  = isset( $d['open'] )  ? sanitize_text_field( $d['open'] )  : $day_default['open'];
                $close_v = isset( $d['close'] ) ? sanitize_text_field( $d['close'] ) : $day_default['close'];
                if ( ! preg_match( '/^\d{2}:\d{2}$/', $open_v ) )  { $open_v  = $day_default['open']; }
                if ( ! preg_match( '/^\d{2}:\d{2}$/', $close_v ) ) { $close_v = $day_default['close']; }
                $bh_save['days'][ $day_key ] = array(
                    'status' => $status, 'open' => $open_v, 'close' => $close_v,
                );
            }
        }
    }
    update_option( 'hozio_business_hours_classic', $bh_save );

    $fields = [
        'hozio_company_phone_1',
        'hozio_company_phone_2',
        'hozio_google_ads_phone',
        'hozio_sms_phone',
        'hozio_company_email',
        'hozio_business_hours',
        'hozio_yelp_url',
        'hozio_youtube_url',
        'hozio_angies_list_url',
        'hozio_home_advisor_url',
        'hozio_bbb_url',
        'hozio_facebook_url',
        'hozio_instagram_url',
        'hozio_twitter_url',
        'hozio_tiktok_url',
        'hozio_linkedin_url',
        'hozio_gmb_link',
        'hozio_to_email_contact_form',
        'hozio_nav_text_color',
        'hozio_start_year',
        'hozio_hst_bg',
        'hozio_hst_border',
        'hozio_hst_header_text',
        'hozio_hst_header_hover',
        'hozio_hst_divider',
        'hozio_hst_search_border',
        'hozio_hst_search_bg',
        'hozio_hst_search_text',
        'hozio_hst_link',
        'hozio_hst_link_hover',
        'hozio_hst_heading',
        'hozio_hst_county_order',
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            if ($field === 'hozio_company_address' || $field === 'hozio_business_hours') {
                update_option($field, wp_kses_post($_POST[$field]));
            } else {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    // Save CallRail noswap toggle for SMS phone
    update_option('hozio_sms_calltrk_noswap', isset($_POST['hozio_sms_calltrk_noswap']) ? '1' : '0');

    // Save dark mode setting
    update_option('hozio_sitemap_dark_mode', isset($_POST['hozio_sitemap_dark_mode']) ? '1' : '0');

    // Save link color settings
    $link_color = isset($_POST['hozio_sitemap_link_color']) ? sanitize_hex_color($_POST['hozio_sitemap_link_color']) : '';
    update_option('hozio_sitemap_link_color', $link_color);
    
    $link_hover_color = isset($_POST['hozio_sitemap_link_hover_color']) ? sanitize_hex_color($_POST['hozio_sitemap_link_hover_color']) : '';
    update_option('hozio_sitemap_link_hover_color', $link_hover_color);

    // FIXED: Use wp_kses_post() for custom tags to allow HTML
	$custom_tags = get_option('hozio_custom_tags', []);
	if (is_array($custom_tags)) {
		foreach ($custom_tags as $tag) {
			if (isset($_POST['hozio_' . $tag['value']])) {
				$value = wp_unslash($_POST['hozio_' . $tag['value']]);
				
				// If it contains <script> tag, store as-is (only for admin users)
				if (strpos($value, '<script') !== false && current_user_can('manage_options')) {
					update_option('hozio_' . $tag['value'], $value);
				} else {
					// For other HTML, use normal sanitization
					update_option('hozio_' . $tag['value'], wp_kses_post($value));
				}
			}
		}
	}

    $redirect_args = array( 'settings-updated' => 'true' );
    if ( $contact_email_has_error ) {
        $redirect_args['email-error'] = '1';
    }
    wp_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php?page=hozio_dynamic_tags' ) ) );
    exit;
}
add_action('admin_post_hozio_save_settings', 'hozio_dynamic_tags_save_settings');
?>
