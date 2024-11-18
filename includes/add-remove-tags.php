<h1>Add / Remove Dynamic Tags</h1>
<form method="post" action="admin-post.php">
    <input type="hidden" name="action" value="hozio_add_tag">
    <?php wp_nonce_field('hozio_add_tag_nonce'); ?>

    <label for="tag-title">Dynamic Tag Title:</label>
    <input type="text" id="tag-title" name="tag_title" required><br><br>

    <label for="tag-type">Tag Type:</label><br>
    <input type="radio" id="text" name="tag_type" value="text" required>
    <label for="text">Text</label><br>
    <input type="radio" id="url" name="tag_type" value="url">
    <label for="url">URL</label><br><br>

    <button type="submit">Add Dynamic Tag</button>
</form>

<hr>

<h2>Existing Tags</h2>
<ul>
    <?php
    // Retrieve saved custom tags from the options table
    $custom_tags = get_option('hozio_custom_tags', []);
    
    // Debugging: Log the tags to ensure they're fetched correctly (remove after fixing)
    // error_log(print_r($custom_tags, true));  // Log to wp-content/debug.log

    if (!empty($custom_tags)) {
        foreach ($custom_tags as $tag) {
            // Display each tag with a remove link
            echo '<li>' . esc_html($tag['title']) . ' (' . esc_html($tag['type']) . ') - <a href="' . esc_url(admin_url('admin-post.php?action=hozio_remove_tag&tag=' . esc_attr($tag['value']))) . '" onclick="return confirm(\'Are you sure you want to remove this tag?\');">Remove</a></li>';
        }
    } else {
        echo '<li>No custom tags found.</li>';
    }
    ?>
</ul>

<script>
    jQuery(document).ready(function($) {
        // You can add custom JS if needed, but it's not required here
    });
</script>
