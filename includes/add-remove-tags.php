<?php
/**
 * Drop-in replacement for your manage tags page
 * Just replace everything in your existing function with this code
 */
?>
<style>
    :root {
        --hozio-blue: #00A0E3;
        --hozio-green: #8DC63F;
        --hozio-orange: #F7941D;
        --hozio-gray: #6D6E71;
    }
    
	.wrap {
		margin-right: 20px;
	}

	.wrap .hozio-manage-wrapper {
		background: #f9fafb;
		margin: 20px !important;
		border-radius: 8px;
		max-width: 100%;
		overflow: hidden;
		box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	}

    .hozio-manage-header {
        background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
        color: white;
        padding: 40px;
        border-radius: 8px;
        position: relative;
        overflow: hidden;
		margin-right: 20px !important;
    }
    
    .hozio-manage-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .hozio-manage-header h1 {
        color: white !important;
        font-size: 32px;
        margin: 0 0 10px !important;
        display: flex;
        align-items: center;
        gap: 12px;
        text-shadow: none;
        font-weight: 600;
    }
    
    .hozio-manage-header h1 .dashicons {
        font-size: 36px;
        width: 36px;
        height: 36px;
    }
    
    .hozio-manage-subtitle {
        color: rgba(255, 255, 255, 0.95);
        font-size: 16px;
        margin: 0;
    }
    
    .hozio-manage-content {
        padding: 0 40px 40px;
        max-width: 100%;
        background: transparent;
    }
    
    .hozio-manage-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        margin: 30px 0 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e5e7eb;
        border-left: 4px solid var(--hozio-blue);
    }
    
    .hozio-manage-card.tags-list {
        border-left-color: var(--hozio-green);
        margin-bottom: 0;
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
    
    .tags-list .hozio-card-header .dashicons {
        color: var(--hozio-green);
    }
    
    .hozio-form-group {
        margin-bottom: 20px;
    }
    
    .hozio-form-group label {
        display: block;
        font-weight: 500;
        color: var(--hozio-gray);
        margin-bottom: 8px;
        font-size: 14px;
    }
    
    .hozio-form-input {
        width: 100%;
        max-width: 500px;
        padding: 10px 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .hozio-form-input:focus {
        outline: none;
        border-color: var(--hozio-blue);
        box-shadow: 0 0 0 3px rgba(0, 160, 227, 0.1);
    }
    
    .hozio-radio-group {
        display: flex;
        gap: 16px;
        margin-top: 8px;
        flex-wrap: wrap;
    }
    
    .hozio-radio-option {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        padding: 10px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        transition: all 0.2s;
        background: white;
    }
    
    .hozio-radio-option:hover {
        border-color: var(--hozio-blue);
        background: rgba(0, 160, 227, 0.05);
    }
    
    .hozio-radio-option.selected {
        border-color: var(--hozio-blue);
        background: rgba(0, 160, 227, 0.1);
    }
    
    .hozio-radio-option input[type="radio"] {
        margin: 0;
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--hozio-blue);
    }
    
    .hozio-radio-option label {
        margin: 0 !important;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--hozio-gray);
        font-weight: 500;
    }
    
    .hozio-radio-option label .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
    }
    
    .hozio-add-btn {
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
        margin-top: 8px;
    }
    
    .hozio-add-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 160, 227, 0.4) !important;
    }
    
    .hozio-add-btn .dashicons {
        font-size: 20px;
        width: 20px;
        height: 20px;
    }
    
    .hozio-tags-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .hozio-tag-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        margin-bottom: 12px;
        background: #f9fafb;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s;
    }
    
    .hozio-tag-item:hover {
        background: white;
        border-color: var(--hozio-green);
        transform: translateX(4px);
    }
    
    .hozio-tag-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .hozio-tag-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--hozio-green) 0%, var(--hozio-blue) 100%);
        color: white;
        flex-shrink: 0;
    }
    
    .hozio-tag-icon .dashicons {
        font-size: 20px;
        width: 20px;
        height: 20px;
    }
    
    .hozio-tag-details {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .hozio-tag-title {
        font-weight: 600;
        color: var(--hozio-gray);
        font-size: 15px;
    }
    
    .hozio-tag-type {
        font-size: 13px;
        color: #6b7280;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .hozio-tag-type .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
    }
    
    .hozio-remove-btn {
        padding: 8px 16px !important;
        border: 2px solid var(--hozio-orange) !important;
        background: white !important;
        color: var(--hozio-orange) !important;
        border-radius: 6px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        height: auto !important;
        line-height: normal !important;
        flex-shrink: 0;
    }
    
    .hozio-remove-btn:hover {
        background: var(--hozio-orange) !important;
        color: white !important;
    }
    
    .hozio-remove-btn .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
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
        color: #d1d5db;
        margin-bottom: 16px;
    }
    
    @media (max-width: 782px) {
        .wrap .hozio-manage-wrapper {
            margin: 20px !important;
        }
        
        .hozio-manage-header {
            padding: 30px 20px;
        }
        
        .hozio-manage-header h1 {
            font-size: 24px;
        }
        
        .hozio-manage-content {
            padding: 0 20px 20px;
        }
        
        .hozio-manage-card {
            padding: 20px;
            margin: 20px 0;
        }
        
        .hozio-radio-group {
            flex-direction: column;
            gap: 12px;
        }
        
        .hozio-radio-option {
            width: 100%;
        }
        
        .hozio-tag-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .hozio-remove-btn {
            align-self: flex-start;
        }
    }
</style>

<div class="hozio-manage-wrapper">
    <div class="hozio-manage-header">
        <div class="hozio-header-content">
            <h1>
                <span class="dashicons dashicons-tag"></span>
                Manage Dynamic Tags
            </h1>
            <p class="hozio-manage-subtitle">Add custom tags and manage existing ones</p>
        </div>
    </div>

    <div class="hozio-manage-content">
        <!-- Add New Tag Card -->
        <div class="hozio-manage-card">
            <div class="hozio-card-header">
                <span class="dashicons dashicons-plus-alt"></span>
                <h2>Add New Dynamic Tag</h2>
            </div>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="hozio_add_tag">
                <?php wp_nonce_field('hozio_add_tag_nonce'); ?>

                <div class="hozio-form-group">
                    <label for="tag-title">Dynamic Tag Title:</label>
                    <input type="text" 
                           id="tag-title" 
                           name="tag_title" 
                           class="hozio-form-input" 
                           placeholder="Enter tag title (e.g., Company Slogan)"
                           required>
                </div>

                <div class="hozio-form-group">
                    <label>Tag Type:</label>
                    <div class="hozio-radio-group">
                        <div class="hozio-radio-option">
                            <input type="radio" id="text" name="tag_type" value="text" required>
                            <label for="text">
                                <span class="dashicons dashicons-editor-alignleft"></span>
                                Text
                            </label>
                        </div>
                        <div class="hozio-radio-option">
                            <input type="radio" id="url" name="tag_type" value="url">
                            <label for="url">
                                <span class="dashicons dashicons-admin-links"></span>
                                URL
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="button hozio-add-btn">
                    <span class="dashicons dashicons-plus"></span>
                    Add Dynamic Tag
                </button>
            </form>
        </div>

        <!-- Existing Tags Card -->
        <div class="hozio-manage-card tags-list">
            <div class="hozio-card-header">
                <span class="dashicons dashicons-list-view"></span>
                <h2>Existing Custom Tags</h2>
            </div>
            
            <?php
            $custom_tags = get_option('hozio_custom_tags', []);
            
            if (!empty($custom_tags)) {
                echo '<ul class="hozio-tags-list">';
                foreach ($custom_tags as $tag) {
                    $icon = $tag['type'] === 'url' ? 'admin-links' : 'editor-alignleft';
                    ?>
                    <li class="hozio-tag-item">
                        <div class="hozio-tag-info">
                            <div class="hozio-tag-icon">
                                <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                            </div>
                            <div class="hozio-tag-details">
                                <span class="hozio-tag-title"><?php echo esc_html($tag['title']); ?></span>
                                <span class="hozio-tag-type">
                                    <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                                    <?php echo esc_html(ucfirst($tag['type'])); ?>
                                </span>
                            </div>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=hozio_remove_tag&tag=' . esc_attr($tag['value']))); ?>" 
                           class="button hozio-remove-btn"
                           onclick="return confirm('Are you sure you want to remove the tag \"<?php echo esc_js($tag['title']); ?>\"? This action cannot be undone.');">
                            <span class="dashicons dashicons-trash"></span>
                            Remove
                        </a>
                    </li>
                    <?php
                }
                echo '</ul>';
            } else {
                ?>
                <div class="hozio-empty-state">
                    <span class="dashicons dashicons-tag"></span>
                    <p>No custom tags found. Create your first dynamic tag above!</p>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Enhanced radio button selection
    $('.hozio-radio-option').on('click', function() {
        var $radio = $(this).find('input[type="radio"]');
        $radio.prop('checked', true);
        $('.hozio-radio-option').removeClass('selected');
        $(this).addClass('selected');
    });
    
    // Mark initially selected option
    $('input[type="radio"]:checked').closest('.hozio-radio-option').addClass('selected');
    
    // Form validation
    $('form[action*="admin-post.php"]').on('submit', function(e) {
        var title = $('#tag-title').val().trim();
        var typeSelected = $('input[name="tag_type"]:checked').length;
        
        if (!title) {
            e.preventDefault();
            alert('Please enter a tag title.');
            $('#tag-title').focus();
            return false;
        }
        
        if (!typeSelected) {
            e.preventDefault();
            alert('Please select a tag type.');
            return false;
        }
    });
});
</script>
