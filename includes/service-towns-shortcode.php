<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [hozio_service_towns] shortcode
 *
 * Renders county accordions with town links for the current service page.
 * - ACF field name: county_groups (Taxonomy field, parent_pages, Checkbox, Term Object)
 * - Towns must have both their service term AND county term in parent_pages taxonomy
 * - Empty field = flat fallback list (all service towns, no county grouping)
 */

// ── Shared CSS (output once per page) ───────────────────────────────────────
function hozio_service_towns_styles() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;

    $c_heading      = esc_attr( get_option( 'hozio_hst_heading',      '#111827' ) );
    $c_bg           = esc_attr( get_option( 'hozio_hst_bg',           '#ffffff' ) );
    $c_border       = esc_attr( get_option( 'hozio_hst_border',       '#e5e7eb' ) );
    $c_header_text  = esc_attr( get_option( 'hozio_hst_header_text',  '#111827' ) );
    $c_header_hover = esc_attr( get_option( 'hozio_hst_header_hover', '#f3f4f6' ) );
    $c_divider      = esc_attr( get_option( 'hozio_hst_divider',      '#e5e7eb' ) );
    $c_search_bg    = esc_attr( get_option( 'hozio_hst_search_bg',    '#ffffff' ) );
    $c_search_bdr   = esc_attr( get_option( 'hozio_hst_search_border','#d1d5db' ) );
    $c_search_txt   = esc_attr( get_option( 'hozio_hst_search_text',  '#111827' ) );
    $c_link         = esc_attr( get_option( 'hozio_hst_link',         '#2563eb' ) );
    $c_link_hover   = esc_attr( get_option( 'hozio_hst_link_hover',   '#1d4ed8' ) );
    // County Header color also drives city count text, chevron, and placeholder
    $c_secondary    = '#9ca3af';
    ?>
    <style>
    .hst-wrap { width: 100% !important; max-width: 1400px !important; margin: 0 auto !important; font-family: inherit !important; box-sizing: border-box !important; padding: 40px 32px 60px !important; }
    .hst-heading { font-size: clamp(22px, 3vw, 34px) !important; font-weight: 700 !important; text-align: center !important; color: <?php echo $c_heading; ?> !important; margin: 0 0 28px !important; line-height: 1.3 !important; }
    .hst-heading em { font-style: normal !important; }

    .hst-search-wrap { margin: 0 auto 28px !important; max-width: 100% !important; }
    .hst-search { width: 100% !important; box-sizing: border-box !important; padding: 14px 18px !important; border: 1.5px solid <?php echo $c_search_bdr; ?> !important; border-radius: 10px !important; font-size: 15px !important; color: <?php echo $c_search_txt; ?> !important; background: <?php echo $c_search_bg; ?> !important; outline: none !important; transition: border-color .15s, box-shadow .15s; }
    .hst-search::placeholder { color: <?php echo $c_secondary; ?> !important; }
    .hst-search:focus { border-color: #6b7280 !important; box-shadow: 0 0 0 3px rgba(107,114,128,.12) !important; }

    .hst-county-list { display: flex !important; flex-direction: column !important; gap: 10px !important; }
    .hst-county { border: 1.5px solid <?php echo $c_border; ?> !important; border-radius: 12px !important; background: <?php echo $c_bg; ?> !important; overflow: hidden !important; }
    .hst-county-btn { width: 100% !important; display: flex !important; align-items: center !important; gap: 12px !important; padding: 20px 26px !important; background: none !important; border: none !important; cursor: pointer !important; text-align: left !important; border-radius: 10px !important; transition: background .12s; }
    .hst-county-btn:hover { background: <?php echo $c_header_hover; ?> !important; border-radius: 10px !important; }
    .hst-county.hst-open .hst-county-btn { border-radius: 10px 10px 0 0 !important; }
    .hst-county.hst-open .hst-county-btn:hover { border-radius: 10px 10px 0 0 !important; }
    .hst-county-name { flex: 1 !important; font-size: 16px !important; font-weight: 600 !important; color: <?php echo $c_header_text; ?> !important; line-height: 1.4 !important; }
    .hst-county-count { font-size: 12px !important; color: <?php echo $c_heading; ?> !important; font-weight: 400 !important; }
    .hst-chevron { width: 18px !important; height: 18px !important; flex-shrink: 0 !important; color: <?php echo $c_secondary; ?> !important; transition: transform .2s; }
    .hst-county.hst-open .hst-chevron { transform: rotate(180deg) !important; }
    .hst-county-body { padding: 20px 26px 26px !important; border-top: 1.5px solid <?php echo $c_divider; ?> !important; }

    .hst-town-grid { display: grid !important; grid-template-columns: repeat(4, 1fr) !important; gap: 4px 0 !important; padding-top: 16px !important; }
    .hst-town-link { font-size: 15px !important; color: <?php echo $c_link; ?> !important; text-decoration: none !important; padding: 7px 0 !important; display: block !important; }
    .hst-town-link:hover { color: <?php echo $c_link_hover; ?> !important; text-decoration: underline !important; }
    .hst-town-link.hst-hide { display: none !important; }

    .hst-no-results { text-align: center !important; padding: 32px !important; color: <?php echo $c_heading; ?> !important; font-size: 14px !important; margin: 0 !important; }

    /* Search mode — keep county label visible, strip interactive chrome */
    .hst-searching .hst-county { border: none !important; background: transparent !important; box-shadow: none !important; overflow: visible !important; }
    .hst-searching .hst-county-btn { pointer-events: none !important; cursor: default !important; background: transparent !important; padding: 6px 0 8px !important; border-bottom: 1.5px solid <?php echo $c_divider; ?> !important; margin-bottom: 4px !important; border-radius: 0 !important; }
    .hst-searching .hst-county-btn:hover { background: transparent !important; border-radius: 0 !important; }
    .hst-searching .hst-county-count { display: none !important; }
    .hst-searching .hst-chevron { display: none !important; }
    .hst-searching .hst-county-body { display: block !important; padding: 0 !important; border-top: none !important; }
    .hst-searching .hst-county-list { gap: 24px !important; }
    .hst-searching .hst-town-grid { grid-template-columns: repeat(4, 1fr) !important; padding-top: 8px !important; }

    @media (max-width: 900px)  { .hst-town-grid { grid-template-columns: repeat(3, 1fr) !important; } .hst-searching .hst-town-grid { grid-template-columns: repeat(3, 1fr) !important; } }
    @media (max-width: 600px)  { .hst-wrap { padding: 24px 16px 60px !important; } .hst-town-grid { grid-template-columns: repeat(2, 1fr) !important; } .hst-searching .hst-town-grid { grid-template-columns: repeat(2, 1fr) !important; } }
    </style>
    <?php
}

// ── Flat fallback ────────────────────────────────────────────────────────────
function hozio_service_towns_flat_fallback( $page_id, $page_title, $service_slug, $restrict_parent = 0 ) {
    $flat_args = [
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
        'post__not_in'   => [ $page_id ],
        'tax_query'      => [ [
            'taxonomy' => 'parent_pages',
            'field'    => 'slug',
            'terms'    => $service_slug,
            'operator' => 'IN',
        ] ],
    ];
    // When "Filter by parent page" is enabled, scope to this service page's direct
    // child towns so same-slug services under different parents don't share towns.
    if ( $restrict_parent ) {
        $flat_args['post_parent'] = $restrict_parent;
    }
    $towns = get_posts( $flat_args );

    if ( empty( $towns ) ) return '';

    hozio_service_towns_styles();
    $uid     = 'hst-' . $page_id;
    $body_id = $uid . '-body';
    ob_start();
    ?>
    <div class="hst-wrap" id="<?php echo esc_attr( $uid ); ?>">
        <h2 class="hst-heading">Cities We Provide <em><?php echo esc_html( $page_title ); ?></em> In</h2>
        <div class="hst-county-list">
            <div class="hst-county hst-open">
                <button type="button" class="hst-county-btn"
                        aria-expanded="true"
                        aria-controls="<?php echo esc_attr( $body_id ); ?>">
                    <span class="hst-county-name">All Cities</span>
                    <span class="hst-county-count"><?php echo count( $towns ); ?> cities</span>
                    <svg class="hst-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="hst-county-body" id="<?php echo esc_attr( $body_id ); ?>">
                    <div class="hst-search-wrap">
                        <input type="text" class="hst-search" placeholder="Search cities..." autocomplete="off">
                    </div>
                    <div class="hst-town-grid">
                        <?php foreach ( $towns as $town ) :
                            $label = function_exists( 'get_field' ) ? get_field( 'location', $town->ID ) : '';
                            if ( empty( $label ) ) $label = $town->post_title;
                        ?>
                        <a href="<?php echo esc_url( get_permalink( $town ) ); ?>"
                           class="hst-town-link"
                           data-name="<?php echo esc_attr( strtolower( $label ) ); ?>">
                            <?php echo esc_html( $label ); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <p class="hst-no-results" style="display:none;">No cities found for &ldquo;<span class="hst-echo"></span>&rdquo;.</p>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var w=document.getElementById(<?php echo wp_json_encode($uid); ?>);
        if(!w)return;

        // Accordion
        w.querySelectorAll('.hst-county-btn').forEach(function(btn){
            btn.addEventListener('click',function(){
                var block=btn.closest('.hst-county');
                var body=document.getElementById(btn.getAttribute('aria-controls'));
                var open=block.classList.contains('hst-open');
                block.classList.toggle('hst-open',!open);
                btn.setAttribute('aria-expanded',open?'false':'true');
                open?body.setAttribute('hidden',''):body.removeAttribute('hidden');
            });
        });

        // Search
        var s=w.querySelector('.hst-search'),nr=w.querySelector('.hst-no-results'),ec=w.querySelector('.hst-echo');
        s.addEventListener('input',function(){
            var q=this.value.trim().toLowerCase(),t=0;
            w.querySelectorAll('.hst-town-link').forEach(function(a){
                var m=!q||a.getAttribute('data-name').indexOf(q)!==-1;
                a.classList.toggle('hst-hide',!m);
                if(m)t++;
            });
            if(ec)ec.textContent=this.value.trim();
            nr.style.display=(q&&t===0)?'':'none';
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Main shortcode ───────────────────────────────────────────────────────────
function hozio_service_towns_shortcode() {
    if ( ! function_exists( 'get_field' ) ) return '';

    $page_id      = get_the_ID();
    if ( ! $page_id ) return '';

    $page_title   = get_the_title( $page_id );
    $service_slug = basename( untrailingslashit( get_permalink( $page_id ) ) );

    // When "Filter by parent page" (hozio_filter_by_parent_page) is enabled on this
    // service page, restrict towns to its direct child pages (post_parent). This
    // disambiguates service pages that share the same parent_pages term slug but live
    // under different hierarchies — e.g. /commercial/sprinkler-system-installation/
    // vs /residential/sprinkler-system-installation/ — mirroring the same filter the
    // dynamic parent/county Elementor queries already honor.
    $restrict_parent = get_post_meta( $page_id, 'hozio_filter_by_parent_page', true ) ? $page_id : 0;

    $county_slugs = get_field( 'county_groups', $page_id );
    if ( empty( $county_slugs ) ) {
        return hozio_service_towns_flat_fallback( $page_id, $page_title, $service_slug, $restrict_parent );
    }

    $counties_data = [];
    foreach ( $county_slugs as $county_item ) {
        if ( is_object( $county_item ) ) {
            $county_term = $county_item;
            $county_slug = $county_item->slug;
        } else {
            $county_slug = (string) $county_item;
            $county_term = get_term_by( 'slug', $county_slug, 'parent_pages' );
        }
        if ( ! $county_term || is_wp_error( $county_term ) ) continue;

        $town_args = [
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'post__not_in'   => [ $page_id ],
            'tax_query'      => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'slug',
                    'terms'    => $service_slug,
                    'operator' => 'IN',
                ],
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'slug',
                    'terms'    => $county_slug,
                    'operator' => 'IN',
                ],
            ],
        ];
        // Same parent-page scoping as the flat fallback (see note above).
        if ( $restrict_parent ) {
            $town_args['post_parent'] = $restrict_parent;
        }
        $towns = get_posts( $town_args );

        if ( ! empty( $towns ) ) {
            $counties_data[] = [
                'name'  => $county_term->name,
                'towns' => $towns,
            ];
        }
    }

    if ( empty( $counties_data ) ) return '';

    $county_order = get_option( 'hozio_hst_county_order', 'count_desc' );
    if ( $county_order === 'count_desc' ) {
        usort( $counties_data, fn( $a, $b ) => count( $b['towns'] ) - count( $a['towns'] ) );
    } elseif ( $county_order === 'count_asc' ) {
        usort( $counties_data, fn( $a, $b ) => count( $a['towns'] ) - count( $b['towns'] ) );
    } elseif ( $county_order === 'alpha' ) {
        usort( $counties_data, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
    }
    // 'manual' = preserve ACF field order, no sort needed

    hozio_service_towns_styles();
    $uid = 'hst-' . $page_id;
    ob_start();
    ?>
    <div class="hst-wrap" id="<?php echo esc_attr( $uid ); ?>">

        <h2 class="hst-heading">Cities We Provide <em><?php echo esc_html( $page_title ); ?></em> In</h2>

        <div class="hst-search-wrap">
            <input type="text" class="hst-search" placeholder="Search cities..." autocomplete="off">
        </div>

        <div class="hst-county-list" id="<?php echo esc_attr( $uid ); ?>-list">
            <?php foreach ( $counties_data as $i => $county ) :
                $body_id  = $uid . '-body-' . $i;
                $is_first = $i === 0;
            ?>
            <div class="hst-county<?php echo $is_first ? ' hst-open' : ''; ?>">
                <button type="button" class="hst-county-btn"
                        aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr( $body_id ); ?>">
                    <span class="hst-county-name"><?php echo esc_html( $county['name'] ); ?></span>
                    <span class="hst-county-count"><?php echo count( $county['towns'] ); ?> cities</span>
                    <svg class="hst-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="hst-county-body" id="<?php echo esc_attr( $body_id ); ?>"<?php echo $is_first ? '' : ' hidden'; ?>>
                    <div class="hst-town-grid">
                        <?php foreach ( $county['towns'] as $town ) :
                            $label = function_exists( 'get_field' ) ? get_field( 'location', $town->ID ) : '';
                            if ( empty( $label ) ) $label = $town->post_title;
                        ?>
                        <a href="<?php echo esc_url( get_permalink( $town ) ); ?>"
                           class="hst-town-link"
                           data-name="<?php echo esc_attr( strtolower( $label ) ); ?>">
                            <?php echo esc_html( $label ); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="hst-no-results" style="display:none;">No cities found for &ldquo;<span class="hst-echo"></span>&rdquo;.</p>

    </div>
    <script>
    (function(){
        var w=document.getElementById(<?php echo wp_json_encode($uid); ?>);
        if(!w)return;
        var s=w.querySelector('.hst-search'),nr=w.querySelector('.hst-no-results'),ec=w.querySelector('.hst-echo');

        // Accordion
        w.querySelectorAll('.hst-county-btn').forEach(function(btn){
            btn.addEventListener('click',function(){
                var block=btn.closest('.hst-county');
                var body=document.getElementById(btn.getAttribute('aria-controls'));
                var open=block.classList.contains('hst-open');
                block.classList.toggle('hst-open',!open);
                btn.setAttribute('aria-expanded',open?'false':'true');
                open?body.setAttribute('hidden',''):body.removeAttribute('hidden');
            });
        });

        // Search
        s.addEventListener('input',function(){
            var q=this.value.trim().toLowerCase(),total=0;
            if(!q){
                w.classList.remove('hst-searching');
                w.querySelectorAll('.hst-county').forEach(function(c){c.style.display='';});
                w.querySelectorAll('.hst-town-link').forEach(function(a){a.classList.remove('hst-hide');});
                nr.style.display='none';
                return;
            }
            w.classList.add('hst-searching');
            w.querySelectorAll('.hst-county').forEach(function(block){
                var links=block.querySelectorAll('.hst-town-link'),vis=0;
                links.forEach(function(a){
                    var m=a.getAttribute('data-name').indexOf(q)!==-1;
                    a.classList.toggle('hst-hide',!m);
                    if(m)vis++;
                });
                block.style.display=vis?'':'none';
                total+=vis;
            });
            if(ec)ec.textContent=this.value.trim();
            nr.style.display=total===0?'':'none';
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'hozio_service_towns', 'hozio_service_towns_shortcode' );
