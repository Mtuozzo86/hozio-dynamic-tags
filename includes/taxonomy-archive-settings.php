<?php
if (!defined('ABSPATH')) exit;

// Register settings
add_action('admin_init', 'hozio_taxonomy_archive_register_settings');
function hozio_taxonomy_archive_register_settings() {
    register_setting('hozio_taxonomy_archive_settings', 'hozio_parent_pages_archive_enabled');
    register_setting('hozio_taxonomy_archive_settings', 'hozio_town_taxonomies_archive_enabled');
}


function hozio_taxonomy_archive_settings_page() {
    // Handle form submission
    if (isset($_POST['submit']) && check_admin_referer('hozio_taxonomy_archive_settings_action')) {
        update_option('hozio_parent_pages_archive_enabled', isset($_POST['hozio_parent_pages_archive_enabled']) ? 1 : 0);
        update_option('hozio_town_taxonomies_archive_enabled', isset($_POST['hozio_town_taxonomies_archive_enabled']) ? 1 : 0);
        
        // Flush rewrite rules after changing settings
        flush_rewrite_rules();
        
        // Check if headers have been sent
        if (headers_sent($file, $line)) {
            echo "<div class='notice notice-error'><p>Headers already sent in $file on line $line. Cannot redirect.</p></div>";
        } else {
            // Redirect to the same page with success parameter
            $redirect_url = add_query_arg(
                array(
                    'page' => 'hozio-taxonomy-archives',
                    'settings-updated' => 'true'
                ),
                admin_url('admin.php')
            );
            wp_redirect($redirect_url);
            exit;
        }
    }

    // Show success message if redirected after save
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        echo '<div class="hozio-success-notice" style="margin: 20px 0;">
            <div class="hozio-success-header">
                <span class="dashicons dashicons-yes-alt"></span>
                Settings Saved Successfully
            </div>
            <div style="color: var(--hozio-green-dark); font-size: 14px;">
                Taxonomy archive settings have been updated. Rewrite rules have been flushed.
            </div>
        </div>';
    }

    $parent_pages_enabled = get_option('hozio_parent_pages_archive_enabled', 0);
    $town_taxonomies_enabled = get_option('hozio_town_taxonomies_archive_enabled', 0);
    
    // Get ALL terms for each taxonomy
    $parent_pages_terms = get_terms(array(
        'taxonomy' => 'parent_pages',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    $town_taxonomies_terms = get_terms(array(
        'taxonomy' => 'town_taxonomies',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    // Calculate stats
    $total_parent_pages_terms = count($parent_pages_terms);
    $total_town_terms = count($town_taxonomies_terms);
    $total_parent_pages = 0;
    $total_town_pages = 0;
    
    foreach ($parent_pages_terms as $term) {
        $total_parent_pages += $term->count;
    }
    
    foreach ($town_taxonomies_terms as $term) {
        $total_town_pages += $term->count;
    }
    ?>
    
    <style>
        :root {
            --hozio-blue: #00A0E3;
            --hozio-blue-dark: #0081B8;
            --hozio-green: #8DC63F;
            --hozio-green-dark: #6FA92E;
            --hozio-orange: #F7941D;
            --hozio-orange-dark: #E67E00;
            --hozio-gray: #6D6E71;
        }
        
        .hozio-archive-wrapper {
            background: #f9fafb;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .hozio-archive-header {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .hozio-archive-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .hozio-archive-header h1 {
            color: white !important;
            font-size: 32px;
            margin: 0 0 10px !important;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            text-shadow: none;
        }
        
        .hozio-archive-header h1 .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
        }
        
        .hozio-archive-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            margin: 0;
        }
        
        .hozio-archive-content {
            padding: 0 40px 40px;
        }
        
        .hozio-archive-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-blue);
        }
        
        .hozio-archive-card.info-card {
            border-left-color: var(--hozio-orange);
        }
        
        .hozio-archive-card.stats-card {
            border-left-color: var(--hozio-green);
        }
        
        .hozio-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .hozio-card-header h2 {
            margin: 0 !important;
            font-size: 20px !important;
            color: var(--hozio-gray);
            font-weight: 600;
        }
        
        .hozio-card-header .dashicons {
            color: var(--hozio-blue);
            font-size: 24px;
            width: 24px;
            height: 24px;
        }
        
        .info-card .hozio-card-header .dashicons {
            color: var(--hozio-orange);
        }
        
        .stats-card .hozio-card-header .dashicons {
            color: var(--hozio-green);
        }
        
        .hozio-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .hozio-stat-item {
            background: linear-gradient(135deg, rgba(0, 160, 227, 0.05) 0%, rgba(141, 198, 63, 0.05) 100%);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hozio-stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .hozio-stat-content {
            flex: 1;
        }
        
        .hozio-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--hozio-blue);
            line-height: 1;
            margin-bottom: 4px;
        }
        
        .hozio-stat-label {
            font-size: 13px;
            color: #6b7280;
        }
        
        .hozio-info-notice {
            background: linear-gradient(135deg, rgba(0, 160, 227, 0.1) 0%, rgba(0, 160, 227, 0.05) 100%);
            border: 1px solid rgba(0, 160, 227, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .hozio-info-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--hozio-blue-dark);
            margin-bottom: 8px;
        }
        
        .hozio-info-text {
            color: var(--hozio-blue-dark);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .hozio-taxonomy-setting {
            background: #f9fafb;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        
        .hozio-taxonomy-setting:hover {
            border-color: var(--hozio-blue);
            box-shadow: 0 2px 4px rgba(0, 160, 227, 0.1);
        }
        
        .hozio-taxonomy-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .hozio-taxonomy-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .hozio-taxonomy-title {
            flex: 1;
        }
        
        .hozio-taxonomy-title h3 {
            margin: 0 0 4px 0 !important;
            color: var(--hozio-gray);
            font-size: 18px !important;
            font-weight: 600;
        }
        
        .hozio-taxonomy-slug {
            color: #6b7280;
            font-size: 13px;
            font-family: monospace;
        }
        
        .hozio-toggle-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hozio-toggle-switch {
            position: relative;
            width: 56px;
            height: 28px;
            flex-shrink: 0;
        }
        
        .hozio-toggle-switch input[type="checkbox"] {
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
            background-color: #cbd5e1;
            transition: 0.4s;
            border-radius: 28px;
        }
        
        .hozio-toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        .hozio-toggle-switch input:checked + .hozio-toggle-slider {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
        }
        
        .hozio-toggle-switch input:checked + .hozio-toggle-slider:before {
            transform: translateX(28px);
        }
        
        .hozio-toggle-label {
            font-weight: 600;
            color: var(--hozio-gray);
        }
        
        .hozio-terms-accordion {
            margin-top: 16px;
        }
        
        .hozio-accordion-toggle {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
            user-select: none;
        }
        
        .hozio-accordion-toggle:hover {
            background: #f9fafb;
            border-color: var(--hozio-blue);
        }
        
        .hozio-accordion-toggle-text {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--hozio-gray);
        }
        
        .hozio-accordion-toggle-text .dashicons {
            color: var(--hozio-blue);
        }
        
        .hozio-accordion-arrow {
            transition: transform 0.3s;
            color: var(--hozio-blue);
        }
        
        .hozio-accordion-toggle.active .hozio-accordion-arrow {
            transform: rotate(180deg);
        }
        
        .hozio-accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .hozio-accordion-content.active {
            max-height: 2000px;
            transition: max-height 0.8s ease-in;
        }
        
        .hozio-terms-list {
            background: white;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 16px;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .hozio-term-item {
            margin-bottom: 8px;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
            overflow: hidden;
        }
        
        .hozio-term-item:last-child {
            margin-bottom: 0;
        }
        
        .hozio-term-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
        }
        
        .hozio-term-header:hover {
            background: #f3f4f6;
        }
        
        .hozio-term-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .hozio-term-info {
            flex: 1;
            min-width: 0;
        }
        
        .hozio-term-name {
            font-weight: 600;
            color: var(--hozio-gray);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hozio-term-url-container {
            display: flex;
            align-items: center;
            gap: 6px;
            pointer-events: auto;
        }
        
        .hozio-term-url {
            font-family: monospace;
            font-size: 12px;
            color: var(--hozio-blue);
            text-decoration: none;
            word-break: break-all;
            display: inline-block;
            flex: 1;
            cursor: pointer;
            pointer-events: auto;
        }
        
        .hozio-term-url:hover {
            text-decoration: underline;
            color: var(--hozio-blue-dark);
        }
        
        .hozio-term-url.disabled {
            color: #9ca3af;
            cursor: default;
            pointer-events: none;
        }
        
        .hozio-copy-btn {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s;
            flex-shrink: 0;
            margin-top: -20px;
        }
        
        .hozio-copy-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 160, 227, 0.3);
        }
        
        .hozio-copy-btn .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        
        .hozio-copy-btn.copied {
            background: var(--hozio-green);
        }
        
        .hozio-term-count {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .hozio-term-arrow {
            transition: transform 0.3s;
            color: var(--hozio-blue);
            flex-shrink: 0;
            cursor: pointer;
        }
        
        .hozio-term-arrow:hover {
            color: var(--hozio-blue-dark);
        }
        
        .hozio-term-item.active .hozio-term-arrow {
            transform: rotate(180deg);
        }
        
        .hozio-pages-list {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        
        .hozio-pages-list.active {
            max-height: 1000px;
            transition: max-height 0.5s ease-in;
        }
        
        .hozio-page-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 12px 12px 56px;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s;
        }
        
        .hozio-page-item:last-child {
            border-bottom: none;
        }
        
        .hozio-page-item:hover {
            background: #f9fafb;
        }
        
        .hozio-page-icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, rgba(0, 160, 227, 0.2) 0%, rgba(141, 198, 63, 0.2) 100%);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--hozio-blue);
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .hozio-page-details {
            flex: 1;
            min-width: 0;
        }
        
        .hozio-page-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        
        .hozio-page-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--hozio-gray);
        }
        
        .hozio-page-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .hozio-page-status.publish {
            background: linear-gradient(135deg, rgba(141, 198, 63, 0.2) 0%, rgba(141, 198, 63, 0.1) 100%);
            color: var(--hozio-green-dark);
        }
        
        .hozio-page-status.draft {
            background: linear-gradient(135deg, rgba(247, 148, 29, 0.2) 0%, rgba(247, 148, 29, 0.1) 100%);
            color: var(--hozio-orange-dark);
        }
        
        .hozio-page-status.pending {
            background: linear-gradient(135deg, rgba(0, 160, 227, 0.2) 0%, rgba(0, 160, 227, 0.1) 100%);
            color: var(--hozio-blue-dark);
        }
        
        .hozio-page-status.private {
            background: rgba(156, 163, 175, 0.2);
            color: #6b7280;
        }
        
        .hozio-page-meta {
            font-size: 12px;
            color: #9ca3af;
        }
        
        .hozio-page-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .hozio-page-link {
            color: var(--hozio-blue);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 4px;
            background: rgba(0, 160, 227, 0.1);
            transition: all 0.2s;
        }
        
        .hozio-page-link:hover {
            background: rgba(0, 160, 227, 0.2);
            text-decoration: none;
        }
        
        .hozio-page-link .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        
        .hozio-page-link.edit {
            color: var(--hozio-orange);
            background: rgba(247, 148, 29, 0.1);
        }
        
        .hozio-page-link.edit:hover {
            background: rgba(247, 148, 29, 0.2);
        }
        
        .hozio-empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        
        .hozio-empty-state .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #f59e0b;
            margin-bottom: 12px;
        }
        
        .hozio-btn-primary {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%) !important;
            border: none !important;
            color: white !important;
            padding: 12px 32px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            text-shadow: none !important;
            box-shadow: 0 4px 6px rgba(0, 160, 227, 0.3) !important;
            height: auto !important;
            line-height: normal !important;
            text-decoration: none !important;
        }
        
        .hozio-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 160, 227, 0.4) !important;
        }
        
        .hozio-success-notice {
            background: linear-gradient(135deg, rgba(141, 198, 63, 0.1) 0%, rgba(141, 198, 63, 0.05) 100%);
            border: 1px solid rgba(141, 198, 63, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .hozio-success-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--hozio-green-dark);
            margin-bottom: 8px;
        }
        
        .hozio-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
        }
        
        .hozio-status-badge.enabled {
            background: linear-gradient(135deg, rgba(141, 198, 63, 0.2) 0%, rgba(141, 198, 63, 0.1) 100%);
            color: var(--hozio-green-dark);
        }
        
        .hozio-status-badge.disabled {
            background: rgba(156, 163, 175, 0.2);
            color: #6b7280;
        }
        
        @media (max-width: 782px) {
            .hozio-archive-wrapper {
                margin: 20px 0;
            }
            
            .hozio-archive-header {
                padding: 30px 20px;
            }
            
            .hozio-archive-header h1 {
                font-size: 24px;
            }
            
            .hozio-archive-content {
                padding: 0 20px 20px;
            }
            
            .hozio-archive-card {
                padding: 20px;
                margin: 20px 0;
            }
            
            .hozio-taxonomy-header {
                flex-wrap: wrap;
            }
            
            .hozio-toggle-container {
                width: 100%;
                justify-content: space-between;
                margin-top: 8px;
            }
            
            .hozio-term-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .hozio-term-count {
                align-self: flex-end;
            }
            
            .hozio-page-item {
                flex-direction: column;
                align-items: flex-start;
                padding-left: 40px;
            }
            
            .hozio-page-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .hozio-stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Main accordion functionality
        const accordionToggles = document.querySelectorAll('.hozio-accordion-toggle');
        accordionToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                this.classList.toggle('active');
                const content = this.nextElementSibling;
                content.classList.toggle('active');
            });
        });
        
        // Only attach click handler to the ARROW, not the entire header
        const termArrows = document.querySelectorAll('.hozio-term-arrow');
        termArrows.forEach(arrow => {
            arrow.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const termItem = this.closest('.hozio-term-item');
                termItem.classList.toggle('active');
                
                const pagesList = termItem.querySelector('.hozio-pages-list');
                if (pagesList) {
                    pagesList.classList.toggle('active');
                }
            });
        });
        
        // Copy URL functionality
        window.hozio_copyUrl = function(button, url) {
            event.stopPropagation();
            
            navigator.clipboard.writeText(url).then(function() {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
                button.classList.add('copied');
                
                setTimeout(function() {
                    button.innerHTML = originalHTML;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy:', err);
            });
        };
    });
    </script>
    
    <div class="hozio-archive-wrapper">
        <div class="hozio-archive-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-archive"></span>
                    Taxonomy Archive Settings
                </h1>
                <p class="hozio-archive-subtitle">Control whether taxonomy archive pages are publicly accessible</p>
            </div>
        </div>

        <div class="hozio-archive-content">
            <!-- Quick Stats -->
            <div class="hozio-archive-card stats-card">
                <div class="hozio-card-header">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <h2>Quick Stats Overview</h2>
                </div>
                
                <div class="hozio-stats-grid">
                    <div class="hozio-stat-item">
                        <div class="hozio-stat-icon">
                            <span class="dashicons dashicons-category"></span>
                        </div>
                        <div class="hozio-stat-content">
                            <div class="hozio-stat-value"><?php echo $total_parent_pages_terms; ?></div>
                            <div class="hozio-stat-label">Page Taxonomy Terms</div>
                        </div>
                    </div>
                    
                    <div class="hozio-stat-item">
                        <div class="hozio-stat-icon">
                            <span class="dashicons dashicons-admin-page"></span>
                        </div>
                        <div class="hozio-stat-content">
                            <div class="hozio-stat-value"><?php echo $total_parent_pages; ?></div>
                            <div class="hozio-stat-label">Pages in Page Taxonomies</div>
                        </div>
                    </div>
                    
                    <div class="hozio-stat-item">
                        <div class="hozio-stat-icon">
                            <span class="dashicons dashicons-location"></span>
                        </div>
                        <div class="hozio-stat-content">
                            <div class="hozio-stat-value"><?php echo $total_town_terms; ?></div>
                            <div class="hozio-stat-label">Town Taxonomy Terms</div>
                        </div>
                    </div>
                    
                    <div class="hozio-stat-item">
                        <div class="hozio-stat-icon">
                            <span class="dashicons dashicons-admin-page"></span>
                        </div>
                        <div class="hozio-stat-content">
                            <div class="hozio-stat-value"><?php echo $total_town_pages; ?></div>
                            <div class="hozio-stat-label">Pages in Town Taxonomies</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info Card -->
            <div class="hozio-archive-card info-card">
                <div class="hozio-card-header">
                    <span class="dashicons dashicons-info"></span>
                    <h2>About Archive Pages</h2>
                </div>
                
                <div class="hozio-info-notice">
                    <div class="hozio-info-header">
                        <span class="dashicons dashicons-lightbulb"></span>
                        What Are Archive Pages?
                    </div>
                    <div class="hozio-info-text">
                        Archive pages display all posts/pages associated with a specific taxonomy term. When enabled, visitors can access these pages through URLs. Expand each section below to see all available archive URLs for each taxonomy, and click on individual terms to view their assigned pages. By default, archives are disabled to prevent unwanted indexing.
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="post" action="">
                <?php wp_nonce_field('hozio_taxonomy_archive_settings_action'); ?>
                
                <div class="hozio-archive-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <h2>Configure Archive Accessibility</h2>
                    </div>
                    
                    <!-- Page Taxonomies Setting -->
                    <div class="hozio-taxonomy-setting">
                        <div class="hozio-taxonomy-header">
                            <div class="hozio-taxonomy-icon">
                                <span class="dashicons dashicons-category"></span>
                            </div>
                            <div class="hozio-taxonomy-title">
                                <h3>Page Taxonomies</h3>
                                <div class="hozio-taxonomy-slug">Taxonomy: parent_pages</div>
                            </div>
                            <div class="hozio-toggle-container">
                                <span class="hozio-toggle-label">Archive Pages</span>
                                <label class="hozio-toggle-switch">
                                    <input type="checkbox" name="hozio_parent_pages_archive_enabled" <?php checked($parent_pages_enabled, 1); ?>>
                                    <span class="hozio-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <?php if (!empty($parent_pages_terms)): ?>
                            <div class="hozio-terms-accordion">
                                <div class="hozio-accordion-toggle">
                                    <div class="hozio-accordion-toggle-text">
                                        <span class="dashicons dashicons-list-view"></span>
                                        <span>View All Page Taxonomy Archive URLs (<?php echo count($parent_pages_terms); ?> total)</span>
                                    </div>
                                    <span class="hozio-status-badge <?php echo $parent_pages_enabled ? 'enabled' : 'disabled'; ?>">
                                        <?php if ($parent_pages_enabled): ?>
                                            <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                            Enabled
                                        <?php else: ?>
                                            <span class="dashicons dashicons-hidden" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                            Disabled
                                        <?php endif; ?>
                                    </span>
                                    <span class="dashicons dashicons-arrow-down-alt2 hozio-accordion-arrow"></span>
                                </div>
                                <div class="hozio-accordion-content">
                                    <div class="hozio-terms-list">
                                        <?php foreach ($parent_pages_terms as $term): 
                                            // Get all pages assigned to this term
                                            $pages = get_posts(array(
                                                'post_type' => 'page',
                                                'posts_per_page' => -1,
                                                'tax_query' => array(
                                                    array(
                                                        'taxonomy' => 'parent_pages',
                                                        'field' => 'term_id',
                                                        'terms' => $term->term_id
                                                    )
                                                ),
                                                'orderby' => 'title',
                                                'order' => 'ASC'
                                            ));
                                            
                                            $archive_url = home_url('/parent-pages/' . $term->slug . '/');
                                        ?>
                                            <div class="hozio-term-item">
                                                <div class="hozio-term-header">
                                                    <div class="hozio-term-icon">
                                                        <span class="dashicons dashicons-category"></span>
                                                    </div>
                                                    <div class="hozio-term-info">
                                                        <div class="hozio-term-name"><?php echo esc_html($term->name); ?></div>
                                                        <div class="hozio-term-url-container">
                                                            <?php if ($parent_pages_enabled): ?>
                                                                <a href="<?php echo esc_url($archive_url); ?>" target="_blank" class="hozio-term-url">
                                                                    <?php echo esc_url($archive_url); ?>
                                                                </a>
                                                                <button type="button" class="hozio-copy-btn" onclick="hozio_copyUrl(this, '<?php echo esc_js($archive_url); ?>');">
                                                                    <span class="dashicons dashicons-admin-page"></span> Copy
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="hozio-term-url disabled">
                                                                    <?php echo esc_html($archive_url); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="hozio-term-count">
                                                        <?php echo $term->count; ?> <?php echo $term->count === 1 ? 'page' : 'pages'; ?>
                                                    </div>
                                                    <?php if (!empty($pages)): ?>
                                                        <span class="dashicons dashicons-arrow-down-alt2 hozio-term-arrow"></span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($pages)): ?>
                                                    <div class="hozio-pages-list">
                                                        <?php foreach ($pages as $page): 
                                                            $status = get_post_status($page->ID);
                                                            $modified = human_time_diff(strtotime($page->post_modified), current_time('timestamp'));
                                                        ?>
                                                            <div class="hozio-page-item">
                                                                <div class="hozio-page-icon">
                                                                    <span class="dashicons dashicons-admin-page"></span>
                                                                </div>
                                                                <div class="hozio-page-details">
                                                                    <div class="hozio-page-title-row">
                                                                        <span class="hozio-page-title"><?php echo esc_html($page->post_title); ?></span>
                                                                        <span class="hozio-page-status <?php echo esc_attr($status); ?>">
                                                                            <?php echo esc_html(ucfirst($status)); ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="hozio-page-meta">
                                                                        Last modified: <?php echo $modified; ?> ago
                                                                    </div>
                                                                </div>
                                                                <div class="hozio-page-actions">
                                                                    <a href="<?php echo get_edit_post_link($page->ID); ?>" class="hozio-page-link edit">
                                                                        <span class="dashicons dashicons-edit"></span>
                                                                        Edit Page
                                                                    </a>
                                                                    <a href="<?php echo get_permalink($page->ID); ?>" target="_blank" class="hozio-page-link">
                                                                        <span class="dashicons dashicons-external"></span>
                                                                        View Page
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="hozio-empty-state">
                                <span class="dashicons dashicons-warning"></span>
                                <p style="margin: 0; font-size: 16px; font-weight: 600;">No Page Taxonomy terms exist yet</p>
                                <p style="margin: 8px 0 0; font-size: 14px; color: #6b7280;">Create some page taxonomy terms to see archive URLs here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Town Taxonomies Setting -->
                    <div class="hozio-taxonomy-setting">
                        <div class="hozio-taxonomy-header">
                            <div class="hozio-taxonomy-icon">
                                <span class="dashicons dashicons-location"></span>
                            </div>
                            <div class="hozio-taxonomy-title">
                                <h3>Town Taxonomies</h3>
                                <div class="hozio-taxonomy-slug">Taxonomy: town_taxonomies</div>
                            </div>
                            <div class="hozio-toggle-container">
                                <span class="hozio-toggle-label">Archive Pages</span>
                                <label class="hozio-toggle-switch">
                                    <input type="checkbox" name="hozio_town_taxonomies_archive_enabled" <?php checked($town_taxonomies_enabled, 1); ?>>
                                    <span class="hozio-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <?php if (!empty($town_taxonomies_terms)): ?>
                            <div class="hozio-terms-accordion">
                                <div class="hozio-accordion-toggle">
                                    <div class="hozio-accordion-toggle-text">
                                        <span class="dashicons dashicons-list-view"></span>
                                        <span>View All Town Taxonomy Archive URLs (<?php echo count($town_taxonomies_terms); ?> total)</span>
                                    </div>
                                    <span class="hozio-status-badge <?php echo $town_taxonomies_enabled ? 'enabled' : 'disabled'; ?>">
                                        <?php if ($town_taxonomies_enabled): ?>
                                            <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                            Enabled
                                        <?php else: ?>
                                            <span class="dashicons dashicons-hidden" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                            Disabled
                                        <?php endif; ?>
                                    </span>
                                    <span class="dashicons dashicons-arrow-down-alt2 hozio-accordion-arrow"></span>
                                </div>
                                <div class="hozio-accordion-content">
                                    <div class="hozio-terms-list">
                                        <?php foreach ($town_taxonomies_terms as $term): 
                                            // Get all pages assigned to this term
                                            $pages = get_posts(array(
                                                'post_type' => 'page',
                                                'posts_per_page' => -1,
                                                'tax_query' => array(
                                                    array(
                                                        'taxonomy' => 'town_taxonomies',
                                                        'field' => 'term_id',
                                                        'terms' => $term->term_id
                                                    )
                                                ),
                                                'orderby' => 'title',
                                                'order' => 'ASC'
                                            ));
                                            
                                            $archive_url = home_url('/town/' . $term->slug . '/');
                                        ?>
                                            <div class="hozio-term-item">
                                                <div class="hozio-term-header">
                                                    <div class="hozio-term-icon">
                                                        <span class="dashicons dashicons-location"></span>
                                                    </div>
                                                    <div class="hozio-term-info">
                                                        <div class="hozio-term-name"><?php echo esc_html($term->name); ?></div>
                                                        <div class="hozio-term-url-container">
                                                            <?php if ($town_taxonomies_enabled): ?>
                                                                <a href="<?php echo esc_url($archive_url); ?>" target="_blank" class="hozio-term-url">
                                                                    <?php echo esc_url($archive_url); ?>
                                                                </a>
                                                                <button type="button" class="hozio-copy-btn" onclick="hozio_copyUrl(this, '<?php echo esc_js($archive_url); ?>');">
                                                                    <span class="dashicons dashicons-admin-page"></span> Copy
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="hozio-term-url disabled">
                                                                    <?php echo esc_html($archive_url); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="hozio-term-count">
                                                        <?php echo $term->count; ?> <?php echo $term->count === 1 ? 'page' : 'pages'; ?>
                                                    </div>
                                                    <?php if (!empty($pages)): ?>
                                                        <span class="dashicons dashicons-arrow-down-alt2 hozio-term-arrow"></span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($pages)): ?>
                                                    <div class="hozio-pages-list">
                                                        <?php foreach ($pages as $page): 
                                                            $status = get_post_status($page->ID);
                                                            $modified = human_time_diff(strtotime($page->post_modified), current_time('timestamp'));
                                                        ?>
                                                            <div class="hozio-page-item">
                                                                <div class="hozio-page-icon">
                                                                    <span class="dashicons dashicons-admin-page"></span>
                                                                </div>
                                                                <div class="hozio-page-details">
                                                                    <div class="hozio-page-title-row">
                                                                        <span class="hozio-page-title"><?php echo esc_html($page->post_title); ?></span>
                                                                        <span class="hozio-page-status <?php echo esc_attr($status); ?>">
                                                                            <?php echo esc_html(ucfirst($status)); ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="hozio-page-meta">
                                                                        Last modified: <?php echo $modified; ?> ago
                                                                    </div>
                                                                </div>
                                                                <div class="hozio-page-actions">
                                                                    <a href="<?php echo get_edit_post_link($page->ID); ?>" class="hozio-page-link edit">
                                                                        <span class="dashicons dashicons-edit"></span>
                                                                        Edit Page
                                                                    </a>
                                                                    <a href="<?php echo get_permalink($page->ID); ?>" target="_blank" class="hozio-page-link">
                                                                        <span class="dashicons dashicons-external"></span>
                                                                        View Page
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="hozio-empty-state">
                                <span class="dashicons dashicons-warning"></span>
                                <p style="margin: 0; font-size: 16px; font-weight: 600;">No Town Taxonomy terms exist yet</p>
                                <p style="margin: 8px 0 0; font-size: 14px; color: #6b7280;">Create some town taxonomy terms to see archive URLs here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" name="submit" class="hozio-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        Save Archive Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>
