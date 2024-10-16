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
    <label for="url">URL</label><br>
    <input type="radio" id="image" name="tag_type" value="image">
    <label for="image">Image</label><br><br>

    <div id="image-upload" style="display: none;">
        <label for="image-url">Upload Image:</label>
        <input type="text" id="image-url" name="image_url" readonly>
        <button type="button" class="upload-image-button">Upload Image</button>
    </div>

    <button type="submit">Add Dynamic Tag</button>
</form>

<hr>

<h2>Existing Tags</h2>
<ul>
    <?php
    $custom_tags = get_option('hozio_custom_tags', []);
    if (!empty($custom_tags)) {
        foreach ($custom_tags as $tag) {
            echo '<li>' . esc_html($tag['title']) . ' (' . esc_html($tag['type']) . ') - <a href="' . admin_url('admin-post.php?action=hozio_remove_tag&tag=' . esc_attr($tag['value'])) . '">Remove</a></li>';
        }
    } else {
        echo '<li>No custom tags found.</li>';
    }
    ?>
</ul>

<script>
    jQuery(document).ready(function($) {
        $('input[name="tag_type"]').change(function() {
            if ($(this).val() == 'image') {
                $('#image-upload').show();
            } else {
                $('#image-upload').hide();
            }
        });

        $('.upload-image-button').click(function(e) {
            e.preventDefault();
            var imageUploader = wp.media({
                title: 'Upload Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            }).open().on('select', function(e) {
                var uploadedImage = imageUploader.state().get('selection').first().toJSON();
                $('#image-url').val(uploadedImage.url);
            });
        });
    });
</script>
