<?php
// Force WordPress to use Imagick (so we can preserve EXIF/profile data). If Imagick isn't available,
// WP will silently fall back, but EXIF on resized images will likely be lost in that case.
add_filter('wp_image_editors', function () {
    return ['WP_Image_Editor_Imagick'];
});

/**
 * Helper: preserve original EXIF/profile data (e.g., GPS) into all resized variants.
 */
function hozio_preserve_exif_on_resize($attachment_id, $file_path) {
    if (!class_exists('Imagick')) {
        return;
    }

    // Load original to extract profiles (EXIF/IPTC/GPS/etc.)
    try {
        $orig = new Imagick($file_path);
        $profiles = $orig->getImageProfiles('*', true); // includes EXIF and other profiles
    } catch (Exception $e) {
        return; // can't proceed if Imagick fails
    }

    // Get existing metadata (sizes) so we know which resized files exist
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (empty($metadata) || empty($metadata['sizes'])) {
        return;
    }

    $base_dir = pathinfo($file_path, PATHINFO_DIRNAME);

    foreach ($metadata['sizes'] as $size_info) {
        $resized_path = trailingslashit($base_dir) . $size_info['file'];
        if (!file_exists($resized_path)) {
            continue;
        }

        try {
            $img = new Imagick($resized_path);
            foreach ($profiles as $name => $value) {
                $img->profileImage($name, $value);
            }
            $img->writeImage($resized_path);
            $img->clear();
            $img->destroy();
        } catch (Exception $e) {
            // silently continue; don't break entire flow
        }
    }

    // Re-update metadata so WP stays consistent (not strictly necessary unless changed)
    wp_update_attachment_metadata($attachment_id, $metadata);
}

/**
 * REST route for replacing media in place or renaming it, with metadata regeneration and EXIF preservation.
 */
add_action('rest_api_init', function () {
    register_rest_route('hozio/v1', '/replace-media', [
        'methods' => 'POST',
        'callback' => 'hozio_replace_media_in_place_or_rename',
        'permission_callback' => function () {
            return current_user_can('upload_files');
        },
        'args' => [
            'attachment_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
            ],
            'new_filename' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_file_name',
            ],
        ],
    ]);
});

function hozio_replace_media_in_place_or_rename(WP_REST_Request $request) {
    $attachment_id = intval($request->get_param('attachment_id'));
    if (!isset($_FILES['file']) || !get_post($attachment_id)) {
        return new WP_Error('missing', 'Attachment or file missing', ['status' => 400]);
    }

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) {
        return new WP_Error('not_found', 'Original file not found', ['status' => 404]);
    }

    $tmp = $_FILES['file']['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return new WP_Error('upload', 'Invalid uploaded file', ['status' => 400]);
    }

    $current_dir = dirname($file_path);
    $original_file_path = $file_path; // keep reference

    // Overwrite or rename logic
    $new_filename = $request->get_param('new_filename');
    $filename_changed = false;
    if ($new_filename) {
        $new_filename = sanitize_file_name($new_filename);
        $new_path = $current_dir . DIRECTORY_SEPARATOR . $new_filename;

        if (!move_uploaded_file($tmp, $new_path)) {
            return new WP_Error('write_failed', 'Failed to move new file', ['status' => 500]);
        }

        // Delete old file if different
        if ($new_path !== $file_path && file_exists($file_path)) {
            @unlink($file_path);
        }

        // Update attachment to point to new file
        $relative_new_path = _wp_relative_upload_path($new_path);
        update_post_meta($attachment_id, '_wp_attached_file', $relative_new_path);

        // Update title/slug to reflect new filename (without extension)
        $title_base = pathinfo($new_filename, PATHINFO_FILENAME);
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => $title_base,
            'post_name' => sanitize_title_with_dashes($title_base),
        ]);

        $file_path = $new_path; // for metadata regen
        $filename_changed = true;
    } else {
        // Overwrite in place
        if (!move_uploaded_file($tmp, $file_path)) {
            return new WP_Error('write_failed', 'Failed to overwrite original file', ['status' => 500]);
        }
    }

    // Regenerate metadata (this creates resized versions)
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
    if (is_wp_error($metadata)) {
        return new WP_Error('meta_fail', 'Metadata generation failed', ['status' => 500]);
    }
    wp_update_attachment_metadata($attachment_id, $metadata);

    // Preserve EXIF/GPS into resized variants if possible
    if (class_exists('Imagick')) {
        hozio_preserve_exif_on_resize($attachment_id, $file_path);
    }

    // Optional fallback: extract GPS from original image and store as separate meta if needed
    if (function_exists('exif_read_data')) {
        try {
            $exif = @exif_read_data($file_path);
            if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLongitude']) && !empty($exif['GPSLatitudeRef']) && !empty($exif['GPSLongitudeRef'])) {
                // Convert to decimal
                $convert = function($coord, $ref) {
                    $parts = array_map(function($v) { return eval('return '.$v.';'); }, explode(',', $coord));
                    if (count($parts) === 3) {
                        list($deg, $min, $sec) = $parts;
                        $decimal = $deg + ($min / 60) + ($sec / 3600);
                        if ($ref === 'S' || $ref === 'W') {
                            $decimal *= -1;
                        }
                        return $decimal;
                    }
                    return null;
                };
                $lat = $convert($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
                $lng = $convert($exif['GPSLongitude'], $exif['GPSLongitudeRef']);
                if ($lat !== null && $lng !== null) {
                    update_post_meta($attachment_id, 'gps_lat', $lat);
                    update_post_meta($attachment_id, 'gps_lng', $lng);
                }
            }
        } catch (Exception $e) {
            // ignore EXIF parse failures
        }
    }

    $url = wp_get_attachment_url($attachment_id);

    return rest_ensure_response([
        'success' => true,
        'attachment_id' => $attachment_id,
        'url' => $url,
        'filename_changed' => $filename_changed,
    ]);
}
